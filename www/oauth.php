<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

// --- State (CSRF protection, stateless HMAC approach) ---

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
