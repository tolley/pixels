<?php

require_once 'db.php';
require_once 'jwt.php';
require_once './PHPMailer/index.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'verify') {
    doVerify();
    exit;
}

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'signup': doSignup(getDb()); break;
        case 'login':  doLogin(getDb());  break;
        case 'logout': doLogout();        break;
        case 'resend': doResend(getDb()); break;
        case 'me':     doMe();            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ── helpers ────────────────────────────────────────────────────────────────

function createVerifyToken(PDO $pdo, int $userId): string {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $pdo->prepare('INSERT INTO verify_tokens (token, user_id, expires_at) VALUES (?, ?, ?)')
        ->execute([$token, $userId, $expires]);
    return $token;
}

// ── actions ────────────────────────────────────────────────────────────────

function doSignup(PDO $pdo): void {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($body['username'] ?? '');
    $email    = trim($body['email']    ?? '');
    $password =       $body['password'] ?? '';

    if (!$username || !$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, email and password are required']);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        return;
    }
    if (strlen($username) < 3 || strlen($username) > 64) {
        http_response_code(400);
        echo json_encode(['error' => 'Username must be 3–64 characters']);
        return;
    }
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 8 characters']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $username]);
    if ($existing = $stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => $existing['email'] === $email
            ? 'Email already registered'
            : 'Username already taken']);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT)]);
    $userId = (int)$pdo->lastInsertId();

    $token = createVerifyToken($pdo, $userId);
    sendVerificationEmail($email, $username, $token);

    echo json_encode(['ok' => true, 'message' => "Check $email for a verification link"]);
}

function doLogin(PDO $pdo): void {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $email    = trim($body['email']    ?? '');
    $password =       $body['password'] ?? '';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id, username, password, email_verified, is_admin FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        return;
    }
    if (!$user['email_verified']) {
        http_response_code(403);
        echo json_encode(['error' => 'Please verify your email before logging in', 'code' => 'unverified']);
        return;
    }

    jwtSetCookie((int)$user['id'], $user['username'], (bool)$user['is_admin']);
    echo json_encode(['ok' => true]);
}

function doLogout(): void {
    jwtClearCookie();
    echo json_encode(['ok' => true]);
}

function doResend(PDO $pdo): void {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $email = trim($body['email'] ?? '');

    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        return;
    }

    $stmt = $pdo->prepare('SELECT id, username, email_verified FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Don't reveal whether the email exists
    if (!$user || $user['email_verified']) {
        echo json_encode(['ok' => true, 'message' => "If that address is pending verification, we've resent the link"]);
        return;
    }

    $pdo->prepare('DELETE FROM verify_tokens WHERE user_id = ?')->execute([$user['id']]);
    $token = createVerifyToken($pdo, $user['id']);
    sendVerificationEmail($email, $user['username'], $token);

    echo json_encode(['ok' => true, 'message' => "Verification link resent to $email"]);
}

function doMe(): void {
    $user = jwtFromRequest();
    echo json_encode($user
        ? ['loggedIn' => true, 'username' => $user['username']]
        : ['loggedIn' => false]);
}

function doVerify(): void {
    $token = trim($_GET['token'] ?? '');

    if (!$token) {
        header('Location: login?error=invalid_token');
        return;
    }

    try {
        $pdo  = getDb();
        $stmt = $pdo->prepare(
            'SELECT vt.user_id, u.username, u.is_admin
               FROM verify_tokens vt
               JOIN users u ON u.id = vt.user_id
              WHERE vt.token = ? AND vt.expires_at > NOW()'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            header('Location: login?error=expired');
            return;
        }

        $pdo->prepare('UPDATE users SET email_verified = 1 WHERE id = ?')
            ->execute([$row['user_id']]);
        $pdo->prepare('DELETE FROM verify_tokens WHERE token = ?')
            ->execute([$token]);

        jwtSetCookie((int)$row['user_id'], $row['username'], (bool)($row['is_admin'] ?? false));
        header('Location: grid?verified=1');

    } catch (Exception $e) {
        header('Location: login?error=server_error');
    }
}
