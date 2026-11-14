
<?php
session_start();

require_once __DIR__ . '/includes/supabase.php';

if (empty($_SESSION['customer_csrf'])) {
    $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
}

$email = '';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $csrfSubmitted = $_POST['csrf_token'] ?? '';
    $csrfSession = $_SESSION['customer_csrf'] ?? '';

    if (
        $csrfSession === '' ||
        $csrfSubmitted === '' ||
        !hash_equals($csrfSession, $csrfSubmitted)
    ) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page and try again.');

    } elseif ($email === '' || $password === '') {

        $message = 'Please enter your email and password.';
        $messageType = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Please enter a valid email address.';
        $messageType = 'error';

    } else {

        // Sign in through Supabase Auth.
        $loginResult = supabaseRequest(
            '/auth/v1/token?grant_type=password',
            'POST',
            [
                'email' => $email,
                'password' => $password
            ]
        );

        if (
            empty($loginResult['success']) ||
            empty($loginResult['data']['access_token']) ||
            empty($loginResult['data']['user']['id'])
        ) {

            $message = 'Unable to sign in. Check your email and password, and confirm your email if required.';
            $messageType = 'error';

        } else {

            $user = $loginResult['data']['user'];
            $accessToken = $loginResult['data']['access_token'];

            // Check that this account has a customer profile.
            $profileResult = supabaseRequest(
                '/rest/v1/profiles?select=id,name,email,role&id=eq.' .
                rawurlencode($user['id']),
                'GET',
                null,
                $accessToken
            );

            $profile = $profileResult['data'][0] ?? null;

            if (
                empty($profileResult['success']) ||
                !is_array($profile) ||
                ($profile['role'] ?? '') !== 'customer'
            ) {

                $message = 'This is not a customer account. Please use the correct login page.';
                $messageType = 'error';

            } else {

                // Create a separate customer session.
                session_regenerate_id(true);

                $_SESSION['customer_id'] = $user['id'];
                $_SESSION['customer_access_token'] = $accessToken;
                $_SESSION['customer_refresh_token'] =
                    $loginResult['data']['refresh_token'] ?? '';

                $_SESSION['customer_name'] =
                    $profile['name'] ?? '';

                $_SESSION['customer_email'] =
                    $profile['email'] ?? $email;

                // Generate a fresh CSRF token.
                unset($_SESSION['customer_csrf']);

                header('Location: index.php');
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Login | Diane Clothing Shop</title>

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
            height: 82px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 clamp(22px, 6vw, 84px);
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
            padding: 58px 20px;
            background: var(--soft);
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 46px 44px 40px;
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
            font-size: clamp(30px, 5vw, 36px);
            font-weight: 400;
            line-height: 1.15;
        }

        .subtitle {
            margin: 0 0 31px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        .form-group { margin-bottom: 21px; }

        label {
            display: block;
            margin-bottom: 9px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .field-wrap { position: relative; }

        input {
            display: block;
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
            transition: border-color .18s ease;
        }

        input:focus { border-color: var(--ink); }
        input::placeholder { color: #999; }
        input.password-field { padding-right: 48px; }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            padding: 0;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #666;
            cursor: pointer;
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .form-options {
            display: flex;
            justify-content: flex-end;
            margin: -7px 0 22px;
        }

        .form-options a, .auth-links a {
            color: var(--ink);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .submit-button {
            width: 100%;
            min-height: 50px;
            padding: 15px 18px;
            border: 1px solid var(--ink);
            border-radius: 0;
            background: var(--ink);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.7px;
            cursor: pointer;
            transition: background .18s ease, color .18s ease;
        }

        .submit-button:hover { background: #333; }
        .submit-button:focus-visible, a:focus-visible, button:focus-visible {
            outline: 2px solid #777;
            outline-offset: 3px;
        }

        .message {
            margin: 0 0 21px;
            padding: 13px 14px;
            border: 1px solid #edcece;
            background: #fbf0f0;
            color: #8e2929;
            font-size: 12px;
            line-height: 1.6;
        }

        .auth-links {
            margin: 25px 0 0;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        .auth-links a { margin-left: 4px; }

        @media (max-width: 520px) {
            .auth-header { height: 70px; padding: 0 20px; }
            .brand-name { font-size: 27px; }
            .page-wrapper {
                min-height: calc(100vh - 70px);
                align-items: flex-start;
                padding: 34px 16px;
            }
            .login-card { padding: 34px 24px 30px; }
            .subtitle { margin-bottom: 26px; }
        }
    </style>

</head>

<body>

<header class="auth-header">
    <a href="index.php" class="brand" aria-label="DIANE Clothing Shop home">
        <span class="brand-name">DIANE</span>
        <span class="brand-caption">CLOTHING SHOP</span>
    </a>
    <a class="back-link" href="index.php">BACK TO SHOP</a>
</header>

<main class="page-wrapper">
    <section class="login-card" aria-labelledby="login-title">
        <p class="eyebrow">DIANE CLOTHING SHOP</p>
        <h1 id="login-title">Welcome Back</h1>
        <p class="subtitle">Sign in to access your Diane Clothing Shop account.</p>

        <?php if ($message !== ''): ?>
            <div class="message" role="alert" aria-live="polite">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['customer_csrf']) ?>"
            >

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?= htmlspecialchars($email) ?>"
                    placeholder="Enter your email"
                    autocomplete="email"
                    inputmode="email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="field-wrap">
                    <input
                        class="password-field"
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        class="password-toggle"
                        type="button"
                        id="togglePassword"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

            <button class="submit-button" type="submit">SIGN IN</button>
        </form>

        <p class="auth-links">
            Don't have an account?
            <a href="signup.php">Create Account</a>
        </p>
    </section>
</main>

<script>
(function () {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('togglePassword');

    if (!passwordInput || !toggleButton) return;

    toggleButton.addEventListener('click', function () {
        const shouldShow = passwordInput.type === 'password';
        passwordInput.type = shouldShow ? 'text' : 'password';
        toggleButton.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
        toggleButton.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
    });
})();
</script>
</body>
</html>
