<?php
session_start();

require_once __DIR__ . '/includes/supabase.php';

date_default_timezone_set('Asia/Manila');

if (empty($_SESSION['signup_csrf'])) {
    $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
}

$name = '';
$email = '';
$message = '';
$messageType = '';

if (($_GET['success'] ?? '') === '1') {
    $message = 'Registration successful! Check your email for a confirmation message if email confirmation is enabled, then sign in.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $csrfSubmitted = (string)($_POST['csrf_token'] ?? '');
    $csrfSession = (string)($_SESSION['signup_csrf'] ?? '');

    if (
        $csrfSession === '' ||
        $csrfSubmitted === '' ||
        !hash_equals($csrfSession, $csrfSubmitted)
    ) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page and try again.');
    } elseif (
        $name === '' ||
        $email === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {
        $message = 'Please complete all required fields.';
        $messageType = 'error';
    } elseif (strlen($name) > 80) {
        $message = 'Your name must not exceed 80 characters.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Your password must contain at least 8 characters.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Your passwords do not match.';
        $messageType = 'error';
    } else {
        $signupResult = supabaseRequest(
            '/auth/v1/signup',
            'POST',
            [
                'email' => $email,
                'password' => $password,
                'data' => ['name' => $name]
            ]
        );

        if (!empty($signupResult['success'])) {
            $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
            header('Location: signup.php?success=1');
            exit;
        }

        $message = $signupResult['error']
            ?? 'Registration failed. Please check your information and try again.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">
    <title>Create Account | DIANE Clothing Shop</title>
    <style>
        :root {
            --ink: #111111;
            --muted: #777777;
            --line: #e5e5e5;
            --soft: #f7f7f5;
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #ffffff;
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
        .auth-header {
            min-height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px clamp(22px, 6vw, 84px);
            border-bottom: 1px solid var(--line);
            background: #fff;
        }
        .brand {
            display: inline-flex;
            flex-direction: column;
            color: var(--ink);
            text-decoration: none;
            line-height: 1;
        }
        .brand-name {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 31px;
            letter-spacing: 1px;
        }
        .brand-caption {
            margin-top: 5px;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 4px;
            text-align: center;
        }
        .back-link {
            color: var(--ink);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-decoration: none;
        }
        .back-link:hover, .auth-links a:hover { opacity: .62; }
        .page-wrapper {
            min-height: calc(100vh - 82px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            background: var(--soft);
        }
        .signup-card {
            width: 100%;
            max-width: 460px;
            padding: 40px 44px 36px;
            background: #fff;
            border: 1px solid var(--line);
        }
        .eyebrow {
            margin: 0 0 13px;
            color: var(--muted);
            font-size: 9px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }
        h1 {
            margin: 0 0 10px;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: clamp(29px, 5vw, 35px);
            font-weight: 400;
            line-height: 1.15;
        }
        .subtitle {
            margin: 0 0 26px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }
        .form-group { margin-bottom: 17px; }
        label {
            display: block;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: 700;
        }
        input {
            width: 100%;
            min-height: 48px;
            padding: 13px 14px;
            border: 1px solid #dcdcdc;
            border-radius: 0;
            background: #fff;
            color: var(--ink);
            font: inherit;
            font-size: 13px;
            outline: none;
        }
        input::placeholder { color: #999; }
        input:focus { border-color: var(--ink); box-shadow: 0 0 0 1px var(--ink); }
        .password-input-wrap { position: relative; width: 100%; }
        .password-input-wrap input { padding-right: 50px; }
        .password-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            padding: 0;
            border: 0;
            background: transparent;
            color: #777;
            cursor: pointer;
        }
        .password-toggle:hover { color: var(--ink); }
        .password-toggle:focus-visible { outline: 2px solid #777; outline-offset: 1px; }
        .password-toggle svg {
            width: 19px;
            height: 19px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .password-toggle .eye-closed { display: none; }
        .password-toggle[aria-pressed="true"] .eye-open { display: none; }
        .password-toggle[aria-pressed="true"] .eye-closed { display: block; }
        .password-hint {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }
        .message {
            padding: 12px 13px;
            margin: 0 0 20px;
            font-size: 12px;
            line-height: 1.6;
            border: 1px solid transparent;
        }
        .message.success { background: #eef5ef; color: #245c35; border-color: #d5e6d8; }
        .message.error { background: #fbefef; color: #982b2b; border-color: #efd7d7; }
        .submit-button {
            width: 100%;
            min-height: 51px;
            margin-top: 5px;
            padding: 15px;
            border: 1px solid var(--ink);
            border-radius: 0;
            background: var(--ink);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            cursor: pointer;
            transition: background .2s ease, opacity .2s ease;
        }
        .submit-button:hover { background: #333; }
        .submit-button:disabled { opacity: .65; cursor: wait; }
        .auth-links {
            margin-top: 23px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }
        .auth-links a { margin-left: 4px; color: var(--ink); font-weight: 700; text-decoration: none; }
        @media (max-width: 520px) {
            .auth-header { min-height: 72px; }
            .brand-name { font-size: 28px; }
            .page-wrapper { min-height: calc(100vh - 72px); align-items: flex-start; padding: 28px 14px; }
            .signup-card { max-width: 100%; padding: 32px 23px 28px; }
            h1 { font-size: 29px; }
        }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { transition-duration: .01ms !important; } }
    </style>
</head>
<body>
<header class="auth-header">
    <a href="index.php" class="brand" aria-label="DIANE Clothing Shop home">
        <span class="brand-name">DIANE</span>
        <span class="brand-caption">CLOTHING SHOP</span>
    </a>
    <a href="index.php" class="back-link">BACK TO SHOP</a>
</header>
<main class="page-wrapper">
    <section class="signup-card" aria-labelledby="signup-title">
        <p class="eyebrow">DIANE CLOTHING SHOP</p>
        <h1 id="signup-title">Create Your Account</h1>
        <p class="subtitle">Join us and discover your next favorite style.</p>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="<?= $messageType === 'error' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form id="signup-form" method="POST" action="signup.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['signup_csrf'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" maxlength="80" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter your full name" autocomplete="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter your email" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-wrap">
                    <input type="password" id="password" name="password" minlength="8" placeholder="At least 8 characters" autocomplete="new-password" aria-describedby="password-hint" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password" aria-pressed="false">
                        <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.2A11 11 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3.2 4.1"/><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4.2-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </div>
                <p class="password-hint" id="password-hint">Use at least 8 characters.</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" placeholder="Enter your password again" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password" aria-pressed="false">
                        <svg class="eye-open" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.2A11 11 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3.2 4.1"/><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4.2-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </div>
                <p class="password-hint" id="match-hint" aria-live="polite"></p>
            </div>

            <button type="submit" class="submit-button" id="signup-submit">CREATE ACCOUNT</button>
        </form>

        <div class="auth-links">Already have an account? <a href="login.php">Sign In</a></div>
    </section>
</main>
<script>
document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
        const input = document.getElementById(this.dataset.target);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        this.setAttribute('aria-pressed', String(show));
        this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});

const signupForm = document.getElementById('signup-form');
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const matchHint = document.getElementById('match-hint');
const submitButton = document.getElementById('signup-submit');

function updatePasswordMatch() {
    if (!confirmInput.value) {
        matchHint.textContent = '';
        confirmInput.setCustomValidity('');
        return;
    }
    const matches = passwordInput.value === confirmInput.value;
    matchHint.textContent = matches ? 'Passwords match.' : 'Passwords do not match yet.';
    confirmInput.setCustomValidity(matches ? '' : 'Your passwords do not match.');
}
passwordInput.addEventListener('input', updatePasswordMatch);
confirmInput.addEventListener('input', updatePasswordMatch);

signupForm.addEventListener('submit', function (event) {
    updatePasswordMatch();
    if (!signupForm.checkValidity()) {
        event.preventDefault();
        signupForm.reportValidity();
        return;
    }
    submitButton.disabled = true;
    submitButton.textContent = 'CREATING ACCOUNT...';
});
</script>
</body>
</html>
