<?php
require_once 'jwt.php';
if (jwtFromRequest()) {
    header('Location: grid');
    exit;
}

$hasGoogle = (bool)(getenv('GOOGLE_CLIENT_ID') && getenv('GOOGLE_CLIENT_SECRET'));
$hasApple  = (bool)(getenv('APPLE_CLIENT_ID') && getenv('APPLE_TEAM_ID') && getenv('APPLE_KEY_ID') && getenv('APPLE_PRIVATE_KEY'));
$hasSocial = $hasGoogle || $hasApple;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign Up — Pixel Playground</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="auth">
<div class="card">
    <p class="card-title">Pixel Playground</p>
    <h1>Create Account</h1>

    <div id="msg" class="msg" style="display:none"></div>

    <form id="form-signup" novalidate>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" autocomplete="username" minlength="3" maxlength="64" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" autocomplete="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" autocomplete="new-password" minlength="8" required>
        </div>
        <div class="form-group">
            <label for="confirm">Confirm Password</label>
            <input type="password" id="confirm" autocomplete="new-password" required>
        </div>
        <button type="submit" id="btn-submit" class="btn-primary">Create Account</button>
    </form>

    <?php if ($hasSocial): ?>
    <div class="divider">or sign up with</div>
    <div class="social-btns">
        <?php if ($hasGoogle): ?>
        <a href="oauth_google?action=start" class="btn-social btn-google">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Sign up with Google
        </a>
        <?php endif; ?>
        <?php if ($hasApple): ?>
        <a href="oauth_apple" class="btn-social btn-apple">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="white">
                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
            </svg>
            Sign up with Apple
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p class="footer-link"><a href="login">Already have an account? Login</a></p>
</div>

<script>
    const msgEl = document.getElementById('msg');

    function showMsg(text, type) {
        msgEl.textContent = text;
        msgEl.className   = `msg ${type}`;
        msgEl.style.display = 'block';
    }

    document.getElementById('form-signup').addEventListener('submit', async e => {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const email    = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const confirm  = document.getElementById('confirm').value;

        if (password !== confirm) {
            showMsg('Passwords do not match', 'error');
            return;
        }

        const btn       = document.getElementById('btn-submit');
        btn.disabled    = true;
        btn.textContent = 'Creating account…';

        try {
            const res  = await fetch('auth?action=signup', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ username, email, password }),
            });
            const data = await res.json();
            if (data.ok) {
                showMsg(data.message, 'success');
                document.getElementById('form-signup').reset();
                btn.textContent = 'Account created';
            } else {
                showMsg(data.error, 'error');
                btn.disabled    = false;
                btn.textContent = 'Create Account';
            }
        } catch {
            showMsg('Network error — try again', 'error');
            btn.disabled    = false;
            btn.textContent = 'Create Account';
        }
    });

    document.getElementById('username').focus();
</script>
</body>
</html>
