<?php
require_once __DIR__ . '/oauth.php';

$clientId    = getenv('APPLE_CLIENT_ID')    ?: ''; // Services ID, e.g. com.example.signin
$teamId      = getenv('APPLE_TEAM_ID')      ?: '';
$keyId       = getenv('APPLE_KEY_ID')       ?: '';
$privateKey  = getenv('APPLE_PRIVATE_KEY')  ?: ''; // base64-encoded PEM (or raw PEM)
$appUrl      = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
$redirectUri = $appUrl . '/oauth_apple.php';

if (!$clientId || !$teamId || !$keyId || !$privateKey) {
    header('Location: /login.php?error=oauth_config');
    exit;
}

// Apple always POSTs the callback; absence of POST data means this is the start
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $state  = oauthGenerateState('apple');
    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code id_token',
        'scope'         => 'name email',
        'state'         => $state,
        'response_mode' => 'form_post',
    ]);
    header('Location: https://appleid.apple.com/auth/authorize?' . $params);
    exit;
}

// --- Apple POST callback ---
if ($_POST['error'] ?? null) {
    header('Location: /login.php?error=oauth_cancelled');
    exit;
}

$code  = $_POST['code']  ?? '';
$state = $_POST['state'] ?? '';
if (!$code || !$state || !oauthVerifyState($state, 'apple')) {
    header('Location: /login.php?error=oauth_state');
    exit;
}

$secret = appleClientSecret($teamId, $clientId, $keyId, $privateKey);
if (!$secret) {
    error_log('Apple OAuth: failed to build client secret — check APPLE_PRIVATE_KEY');
    header('Location: /login.php?error=oauth_config');
    exit;
}

$tokens = oauthHttpPost('https://appleid.apple.com/auth/token', [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $secret,
    'redirect_uri'  => $redirectUri,
]);

$idToken = $tokens['id_token'] ?? null;
if (!$idToken) {
    header('Location: /login.php?error=oauth_failed');
    exit;
}

$claims = oauthDecodeIdToken($idToken);
if (!$claims || empty($claims['sub'])) {
    header('Location: /login.php?error=oauth_failed');
    exit;
}

$pdo = getDb();

// Apple omits email on repeat logins — handle returning users without it
$email = $claims['email'] ?? null;
if (!$email) {
    $stmt = $pdo->prepare('SELECT id, username FROM users WHERE oauth_provider = ? AND oauth_subject = ?');
    $stmt->execute(['apple', $claims['sub']]);
    $user = $stmt->fetch();
    if (!$user) {
        header('Location: /login.php?error=oauth_failed');
        exit;
    }
    jwtSetCookie($user['id'], $user['username']);
    header('Location: /grid.php');
    exit;
}

// Name only arrives in POST body on first authorization
$name = '';
if (!empty($_POST['user'])) {
    $appleUser = json_decode($_POST['user'], true);
    $first = $appleUser['name']['firstName'] ?? '';
    $last  = $appleUser['name']['lastName']  ?? '';
    $name  = trim("$first $last");
}

$user = oauthFindOrCreateUser($pdo, 'apple', $claims['sub'], $email, $name);
if (!$user) {
    header('Location: /login.php?error=oauth_failed');
    exit;
}

jwtSetCookie($user['id'], $user['username']);
header('Location: /grid.php');
exit;

// --- Apple-specific helpers ---

function appleClientSecret(string $teamId, string $clientId, string $keyId, string $keyEnv): ?string {
    // Accept raw PEM or base64-encoded PEM
    $pem = (strpos($keyEnv, '-----BEGIN') !== false) ? $keyEnv : base64_decode($keyEnv);
    if (!$pem) return null;

    $now     = time();
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'ES256', 'kid' => $keyId])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode([
        'iss' => $teamId,
        'iat' => $now,
        'exp' => $now + 15552000, // 180 days
        'aud' => 'https://appleid.apple.com',
        'sub' => $clientId,
    ])), '+/', '-_'), '=');

    $data = "$header.$payload";
    $key  = openssl_pkey_get_private($pem);
    if (!$key) return null;

    if (!openssl_sign($data, $derSig, $key, OPENSSL_ALGO_SHA256)) return null;

    $sig = rtrim(strtr(base64_encode(derToP1363($derSig)), '+/', '-_'), '=');
    return "$data.$sig";
}

function derToP1363(string $der): string {
    // Convert DER SEQUENCE { INTEGER r, INTEGER s } → fixed 64-byte r||s (P-256)
    $pos  = 2;                          // skip SEQUENCE tag + length byte
    $rLen = ord($der[$pos + 1]);
    $r    = substr($der, $pos + 2, $rLen);
    $pos += 2 + $rLen;
    $sLen = ord($der[$pos + 1]);
    $s    = substr($der, $pos + 2, $sLen);
    // DER INTEGER may have a leading 0x00 sign-extension byte; strip it
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
}
