<?php
require_once 'jwt.php';
if (jwtFromRequest()) {
    header('Location: grid.php');
    exit;
}

$errorMap = [
    'expired'         => 'Verification link has expired — request a new one below.',
    'invalid_token'   => 'Invalid verification link.',
    'server_error'    => 'Something went wrong. Please try again.',
    'oauth_config'    => 'Social login is not configured.',
    'oauth_cancelled' => 'Login cancelled — please try again.',
    'oauth_state'     => 'Invalid login state — please try again.',
    'oauth_failed'    => 'Social login failed — please use email instead.',
];
$initError  = $errorMap[$_GET['error'] ?? ''] ?? '';
$showResend = isset($_GET['error']) && in_array($_GET['error'], ['expired', 'invalid_token']);

$hasGoogle = (bool)(getenv('GOOGLE_CLIENT_ID') && getenv('GOOGLE_CLIENT_SECRET'));
$hasApple  = (bool)(getenv('APPLE_CLIENT_ID') && getenv('APPLE_TEAM_ID') && getenv('APPLE_KEY_ID') && getenv('APPLE_PRIVATE_KEY'));
$hasSocial = $hasGoogle || $hasApple;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Pixel Playground</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="auth">
<div class="card">
    <p class="card-title">Pixel Playground</p>
    <h1>Login</h1>

    <div id="msg" class="msg" style="display:none"></div>

    <form id="form-login" novalidate>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" autocomplete="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" autocomplete="current-password" required>
        </div>
        <button type="submit" id="btn-submit" class="btn-primary">Login</button>
    </form>

    <button type="button" id="btn-resend" class="btn-link"
            style="<?= $showResend ? '' : 'display:none' ?>">Resend verification email</button>

    <?php if ($hasSocial): ?>
    <div class="divider">or continue with</div>
    <div class="social-btns">
        <?php if ($hasGoogle): ?>
        <a href="oauth_google.php?action=start" class="btn-social btn-google">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Sign in with Google
        </a>
        <?php endif; ?>
        <?php if ($hasApple): ?>
        <a href="oauth_apple.php" class="btn-social btn-apple">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="white">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
            </svg>
            Sign in with Apple
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="footer-link"><a href="signup.php">Don't have an account? Sign up</a></p>
</div>

<script>
    const INIT_ERROR  = <?= json_encode($initError) ?>;
    const INIT_RESEND = <?= $showResend ? 'true' : 'false' ?>;

    const msgEl    = document.getElementById('msg');
    const btnResend = document.getElementById('btn-resend');

    function showMsg(text, type) {
        msgEl.textContent = text;
        msgEl.className   = `msg ${type}`;
        msgEl.style.display = 'block';
    }

    if (INIT_ERROR) showMsg(INIT_ERROR, 'error');

    document.getElementById('form-login').addEventListener('submit', async e => {
        e.preventDefault();
        const email    = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const btn      = document.getElementById('btn-submit');
        btn.disabled   = true;
        btn.textContent = 'Logging in…';
        try {
            const res  = await fetch('auth.php?action=login', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ email, password }),
            });
            const data = await res.json();
            if (data.ok) {
                location.href = 'grid.php';
            } else {
                showMsg(data.error, 'error');
                if (data.code === 'unverified') btnResend.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Login';
            }
        } catch {
            showMsg('Network error — try again', 'error');
            btn.disabled = false;
            btn.textContent = 'Login';
        }
    });

    btnResend.addEventListener('click', async () => {
        const email = document.getElementById('email').value;
        if (!email) { showMsg('Enter your email above first', 'error'); return; }
        btnResend.disabled = true;
        try {
            const res  = await fetch('auth.php?action=resend', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ email }),
            });
            const data = await res.json();
            showMsg(data.message || data.error, data.ok ? 'success' : 'error');
        } catch {
            showMsg('Network error — try again', 'error');
        } finally {
            btnResend.disabled = false;
        }
    });

    document.getElementById('email').focus();
</script>
</body>
</html>
