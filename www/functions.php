<?php
namespace pixels\functions;

use \PHPMailer\PHPMailer\PHPMailer;

/**
 * A file to hold various functions
 */

function createPDO(): \PDO {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
        $_ENV['DB_HOST'],
        $_ENV['DB_PORT'],
        $_ENV['DB_NAME']
    );

    $pdo = new \PDO(
        $dsn,
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
}

/**
 * Creates the token used to keep track of if and who a user is
 * 
 * @param   PDO The database resource
 * @param   int The id of the logged in user
 * @return  The hex token used to keep track of this user.
 */
function createVerifyToken( \PDO $pdo, int $userId ): string {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    $pdo->prepare('INSERT INTO verify_tokens (token, user_id, expires_at) VALUES (?, ?, ?)')
        ->execute([$token, $userId, $expires]);

    return $token;
}

/**
 * Sends the user an email in order to verify their account
 * @param string to: The user's email address
 * @param string username: The user's choosen name
 * @param string token: The verification token that we generated
 * @return bool True if the email was sent successfully, false otherwise
 */
function sendVerificationEmail(string $to, string $username, string $token): bool {
    try {
        //Create an instance; passing `true` enables exceptions
        $mail = createEmailObject();

        $mail->addAddress( $to );

        $appUrl = rtrim( $_ENV['APP_URL'] ?: 'https://pixels.tolleycoder.com', '/');
        $url    = "$appUrl/auth?action=verify&token=$token";

        //Content
        $mail->isHTML( true ); // Set email format to HTML
        $mail->Subject = 'Verify your email address';
        $mail->Body    = "<span>
                            <h1>Hi $username!</h1>
                            Please verify your email: <a href='$url'>$appUrl/auth</a>
                            <br />

                            <span>Expires in 24 hours.</span>
                        </span>";
        // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        $mail->send();
        return true;
    } catch( Exception $e ) {
        return false;
    }
}

/**
 * Creates a basic email object using the env variables and returns it.
 * 
 * @return  object  PHPMailer object configured to send an email
 */
function createEmailObject(): object {
    $gmailHost     = $_ENV['GMAIL_HOST'];
    $gmailUsername = $_ENV['GMAIL_USERNAME'];
    $gmailPassword = $_ENV['GMAIL_PASSWORD'];
    $appUrl        = $_ENV['APP_URL'];
    $appEmail      = $_ENV['APP_EMAIL'];

    //Create an instance; passing `true` enables exceptions
    $mail = new \PHPMailer\PHPMailer\PHPMailer( true );
    $mail->isSMTP();                                 // Send using SMTP
    $mail->Host       = $gmailHost;                  // Set the SMTP server to send through
    $mail->SMTPAuth   = true;                        // Enable SMTP authentication
    $mail->Username   = $gmailUsername;              // SMTP username
    $mail->Password   = $gmailPassword;              // SMTP password
    // In Case of emergency, enable debug: $mail->SMTPDebug  = 2;

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
    $mail->Port       = 465;                         // TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

    // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    // $mail->Port       = 587;

    $mail->addReplyTo( $appEmail, 'Reply to ' . $appEmail );
    $mail->SetFrom( $appEmail, $appEmail );

    $mail->From = $appEmail;

    return $mail;
}

function _jwtB64Encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function _jwtB64Decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

function _jwtSecret(): string {
    return getenv('JWT_SECRET') ?: 'dev-secret-change-in-production';
}

function jwtEncode(array $payload): string {
    $header = _jwtB64Encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = _jwtB64Encode(json_encode($payload));
    $sig    = _jwtB64Encode(hash_hmac('sha256', "$header.$body", _jwtSecret(), true));
    return "$header.$body.$sig";
}

function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $body, $sig] = $parts;
    $expected = _jwtB64Encode(hash_hmac('sha256', "$header.$body", _jwtSecret(), true));
    if (!hash_equals($expected, $sig)) return null;

    $data = json_decode(_jwtB64Decode($body), true);
    if (!is_array($data)) return null;
    if (isset($data['exp']) && $data['exp'] < time()) return null;

    return $data;
}

function jwtFromRequest(): ?array {
    $token = $_COOKIE['jwt'] ?? null;
    if (!$token) return null;
    return jwtDecode($token);
}

function jwtSetCookie(int $userId, string $username, bool $isAdmin = false, bool $emailAllowed = true ): void {
    $exp = time() + 86400 * 7;
    setcookie('jwt', jwtEncode([
        'sub'           => $userId,
        'username'      => $username,
        'email_allowed' => $emailAllowed,
        'is_admin'      => $isAdmin,
        'iat'           => time(),
        'exp'           => $exp,
    ]), [
        'expires'  => $exp,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function jwtClearCookie(): void {
    setcookie('jwt', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function oauthGenerateState(string $provider): string {
    $nonce = bin2hex(random_bytes(16));
    $ts    = time();
    $data  = "$provider:$nonce:$ts";
    $hmac  = hash_hmac('sha256', $data, _jwtSecret());
    return rtrim(strtr(base64_encode("$data:$hmac"), '+/', '-_'), '=');
}

function oauthVerifyState(string $state, string $provider): bool {
    $raw = base64_decode(strtr($state, '-_', '+/'));
    if (!$raw) return false;
    $lastColon = strrpos($raw, ':');
    if ($lastColon === false) return false;
    $data = substr($raw, 0, $lastColon);
    $hmac = substr($raw, $lastColon + 1);
    $parts = explode(':', $data);
    if (count($parts) !== 3) return false;
    if ($parts[0] !== $provider) return false;
    if (abs(time() - (int)$parts[2]) > 600) return false; // 10-minute window
    $expected = hash_hmac('sha256', $data, _jwtSecret());
    return hash_equals($expected, $hmac);
}

// --- Token helpers ---

function oauthDecodeIdToken(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    $json = base64_decode(strtr($parts[1], '-_', '+/'));
    if (!$json) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function oauthHttpPost(string $url, array $fields): ?array {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => http_build_query($fields),
        'timeout'       => 10,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return ($body !== false) ? (json_decode($body, true) ?: null) : null;
}

// --- User management ---

function oauthFindOrCreateUser(PDO $pdo, string $provider, string $subject, string $email, string $name): ?array {
    // Returning OAuth user
    $stmt = $pdo->prepare('SELECT id, username, is_admin FROM users WHERE oauth_provider = ? AND oauth_subject = ?');
    $stmt->execute([$provider, $subject]);
    $user = $stmt->fetch();
    if ($user) return $user;

    // Email matches an existing account — link it
    $stmt = $pdo->prepare('SELECT id, username, is_admin FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare('UPDATE users SET oauth_provider=?, oauth_subject=?, email_verified=1 WHERE id=?')
            ->execute([$provider, $subject, $user['id']]);
        return $user;
    }

    // First-time OAuth user — create account
    $username = oauthMakeUsername($pdo, $name ?: $email);
    $pdo->prepare('INSERT INTO users (username, email, password, email_verified, oauth_provider, oauth_subject) VALUES (?,?,NULL,1,?,?)')
        ->execute([$username, $email, $provider, $subject]);
    return ['id' => (int)$pdo->lastInsertId(), 'username' => $username, 'is_admin' => false];
}

function oauthMakeUsername(PDO $pdo, string $hint): string {
    $base = preg_replace('/[^a-z0-9_]/', '', strtolower(explode('@', $hint)[0]));
    $base = substr($base ?: 'user', 0, 24);
    $name = $base;
    for ($i = 2; ; $i++) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$name]);
        if (!$stmt->fetch()) return $name;
        $name = $base . $i;
    }
}


/**
 * Gets the user's data from the database
 * 
 * @param   PDO The database connection resource
 * @param   int The id of the user to pull
 * @return  array   The user's data
 */
function getUserInfo( \PDO $pdo, string $username ): array {
    $stmt = $pdo->prepare('SELECT id, 
                                username,
                                email,
                                email_allowed,
                                email_verified,
                                is_admin
                            FROM users
                            WHERE username = ?');

    $stmt->execute([$username]);
    $userData = $stmt->fetch();

    return $userData;
}

/**
 * Returns the decoded user info from the JWT token
 * 
 * @returns array The user's data stored in the JWT cookie
 */
function getUserFromJWT() {
    $token = $_COOKIE['jwt'];
    return \pixels\functions\jwtDecode( $token );
}

/**
 * Sends a user an email containing weather or not the pixels they submitted
 * where approved or not.
 * @param   string email: The user's email address
 * @return  void
 */
function sendPixelReviewEmail( string $to, string $username, string $status ): void {
    $appUrl   = rtrim( $_ENV['APP_URL'] ?: 'https://pixels.tolleycoder.com', '/');
    $imageUrl = $appUrl . '/image';
    $approved = $status === 'approved';
    $subject  = $approved ? 'Your pixels have been approved!' : 'Your pixels were not approved';

    $headingColor = $approved ? '#2e7d32' : '#c62828';
    $heading      = $approved ? 'Pixels Approved!' : 'Pixel Submission Update';
    $safeUser     = htmlspecialchars( $username, ENT_QUOTES, 'UTF-8' );
    $safeUrl      = htmlspecialchars( $imageUrl, ENT_QUOTES, 'UTF-8' );

    $statusLine = $approved
        ? 'Great news! Your pixel submission has been <strong>approved</strong> and your colors are now live on the canvas!'
        : 'Unfortunately your pixel submission was <strong>not approved</strong> this time. Feel free to try again!';

    $body  = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>';
    $body .= '<body style="font-family:sans-serif;max-width:560px;margin:40px auto;color:#333">';
    $body .= '<h2 style="color:' . $headingColor . '">' . $heading . '</h2>';
    $body .= '<p>Hi ' . $safeUser . ',</p>';
    $body .= '<p>' . $statusLine . '</p>';
    $body .= '<p><a href="' . $safeUrl . '" style="display:inline-block;padding:10px 20px;background:#1565c0;color:#fff;border-radius:4px;text-decoration:none">View the Canvas</a></p>';
    $body .= '<p>Thank you so much for contributing and helping make this experiment look amazing &mdash; every pixel counts!</p>';
    $body .= '<p style="color:#888;font-size:0.85em">&mdash; The Pixel Playground Team</p>';
    $body .= '</body></html>';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Pixel Playground <noreply@pixelplayground.local>\r\n";

    try {
        $mail = \pixels\functions\createEmailObject();

        $mail->addAddress( $to );

        //Content
        $mail->isHTML( true ); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = 'Thank you for contributing!  You can see it at http://pixels.tolleycoder.com/image';

        $mail->send();
    } catch( Exception $e ) {
        // Ignore this exception, for now....
    }
}
