<?php
require_once 'jwt.php';
if (jwtFromRequest()) {
    header('Location: grid.php');
    exit;
}

$errorMap = [
    'expired'       => 'Verification link has expired — request a new one below.',
    'invalid_token' => 'Invalid verification link.',
    'server_error'  => 'Something went wrong. Please try again.',
];
$initError   = $errorMap[$_GET['error'] ?? ''] ?? '';
$showResend  = isset($_GET['error']) && in_array($_GET['error'], ['expired', 'invalid_token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — Pixel Playground</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: #1a1a2e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, sans-serif;
            color: #eee;
            padding: 16px;
        }

        .card {
            background: #16213e;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 36px 32px;
            width: 380px;
            max-width: 100%;
        }

        .card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            opacity: 0.4;
            text-align: center;
            margin-bottom: 6px;
        }

        h1 {
            font-size: 1.4rem;
            text-align: center;
            font-weight: 600;
            margin-bottom: 26px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .form-group { display: flex; flex-direction: column; gap: 5px; }

        label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            opacity: 0.5;
        }

        input {
            background: rgba(255,255,255,0.06);
            color: #eee;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 6px;
            padding: 9px 11px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.15s;
            width: 100%;
        }
        input:focus { border-color: #1982c4; }

        .btn-primary {
            background: #1982c4;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 11px;
            font-size: 0.9rem;
            cursor: pointer;
            margin-top: 4px;
            transition: background 0.15s;
            width: 100%;
        }
        .btn-primary:hover    { background: #1570aa; }
        .btn-primary:disabled { opacity: 0.6; cursor: default; }

        .msg {
            font-size: 0.82rem;
            padding: 9px 12px;
            border-radius: 6px;
            text-align: center;
        }
        .msg.error   { background: rgba(230,57,70,0.2);  color: #e63946; }
        .msg.success { background: rgba(138,201,38,0.2); color: #8ac926; }

        .btn-link {
            background: none;
            border: none;
            color: rgba(255,255,255,0.35);
            font-size: 0.78rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 2px 0;
            display: block;
            text-align: center;
            width: 100%;
            margin-top: 6px;
        }
        .btn-link:hover { color: rgba(255,255,255,0.65); background: none; }

        .footer-link {
            margin-top: 22px;
            text-align: center;
            font-size: 0.8rem;
            opacity: 0.45;
        }
        .footer-link a { color: inherit; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.25); }
        .footer-link a:hover { opacity: 1; }
    </style>
</head>
<body>
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
