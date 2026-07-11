<?php
require_once __DIR__ . '/oauth.php';
// require_once __DIR__ . '/PHPMailer/index.php';

$env = parse_ini_file( '.env', false, INI_SCANNER_RAW );

$clientId    = $env['GOOGLE_CLIENT_ID']     ?: '';
$clientSecret = $env['GOOGLE_CLIENT_SECRET'] ?: '';
$appUrl      = rtrim($env['APP_URL'] ?: 'http://pixels.tolleycoder.com', '/');
$redirectUri = $appUrl . '/oauth_google.php?action=callback';

// echo '"' . $redirectUri . '"';
// die();

// if (!$clientId || !$clientSecret) {
//     header('Location: /login.php?error=oauth_config');
//     exit;
// }

$action = $_GET['action'] ?? 'start';

if ($action === 'start') {
    $state  = oauthGenerateState('google');
    $params = http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// --- Callback ---
if ($_GET['error'] ?? null) {
    header('Location: /login?error=oauth_cancelled');
    exit;
}

$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

if (!$code || !$state || !oauthVerifyState($state, 'google')) {
    header('Location: /login?error=oauth_state');
    exit;
}

$tokens = oauthHttpPost('https://oauth2.googleapis.com/token', [
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
]);

$idToken = $tokens['id_token'] ?? null;
if (!$idToken) {
    header('Location: /login?error=oauth_failed');
    exit;
}

$claims = oauthDecodeIdToken($idToken);

if (!$claims || empty($claims['sub']) || empty($claims['email'])) {
    header('Location: /login?error=oauth_failed');
    exit;
}

$pdo  = getDb();
$user = oauthFindOrCreateUser($pdo, 'google', $claims['sub'], $claims['email'], $claims['name'] ?? '');

if (!$user) {
    header('Location: /login?error=oauth_failed');
    exit;
}

jwtSetCookie($user['id'], $user['username'], (bool)($user['is_admin'] ?? false));

header('Location: /loginlanding');
exit;
