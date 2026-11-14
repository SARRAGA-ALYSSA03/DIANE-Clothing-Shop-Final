
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
    $csrfSubmitted = $_POST['csrf_token'] ?? '';
    $csrfSession = $_SESSION['customer_csrf'] ?? '';

    if (
        $csrfSession === '' ||
        $csrfSubmitted === '' ||
        !hash_equals($csrfSession, $csrfSubmitted)
    ) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page and try again.');

    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Please enter a valid email address.';
        $messageType = 'error';

    } else {

        $redirectUrl = 'http://localhost/diane-clothing-shop/reset_password.php';

        $result = supabaseRequest(
            '/auth/v1/recover?redirect_to=' . rawurlencode($redirectUrl),
            'POST',
            ['email' => $email]
        );

        if (empty($result['success'])) {
            $message = 'We could not process your request right now. Please try again later.';
            $messageType = 'error';
        } else {
            $message = 'If an account exists with that email, a password recovery link will be sent. Please check your inbox.';
            $messageType = 'success';
            $email = '';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | DIANE Clothing Shop</title>

    <style>
        :root {
            --ink: #111;
            --muted: #777;
            --line: #e5e5e5;
            --soft: #f7f7f5;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #fff;
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

        .page-wrapper {
            min-height: calc(100vh - 82px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 58px 20px;
            background: var(--soft);
        }

        .auth-card {
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

        label {
            display: block;
            margin-bottom: 9px;
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

        input:focus {
            border-color: var(--ink);
        }

        input::placeholder {
            color: #999;
        }

        .message {
            margin: 0 0 21px;
            padding: 13px 14px;
            font-size: 12px;
            line-height: 1.6;
        }

        .message.error {
            border: 1px solid #edcece;
            background: #fbf0f0;
            color: #8e2929;
        }

        .message.success {
            border: 1px solid #cfe3d2;
            background: #f1f8f2;
            color: #285c35;
        }

        .submit-button {
            width: 100%;
            min-height: 50px;
            margin-top: 24px;
            padding: 15px 18px;
            border: 1px solid var(--ink);
            border-radius: 0;
            background: var(--ink);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.7px;
            cursor: pointer;
        }

        .submit-button:hover {
            background: #333;
        }

        .auth-links {
            margin: 25px 0 0;
            color: var(--muted);
            font-size: 12px;
            text-align: center;
        }

        .auth-links a {
            color: var(--ink);
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 2px solid #777;
            outline-offset: 3px;
        }

        @media (max-width: 520px) {
            .auth-header {
                height: 70px;
                padding: 0 20px;
            }

            .brand-name {
                font-size: 27px;
            }

            .page-wrapper {
                min-height: calc(100vh - 70px);
                align-items: flex-start;
                padding: 34px 16px;
            }

            .auth-card {
                padding: 34px 24px 30px;
            }
        }
    </style>
</head>

<body>

<header class="auth-header">
    <a href="index.php" class="brand" aria-label="DIANE Clothing Shop home">
        <span class="brand-name">DIANE</span>
        <span class="brand-caption">CLOTHING SHOP</span>
    </a>

    <a href="login.php" class="back-link">BACK TO SIGN IN</a>
</header>

<main class="page-wrapper">
    <section class="auth-card">

        <p class="eyebrow">DIANE CLOTHING SHOP</p>

        <h1>Forgot Password?</h1>

        <p class="subtitle">
            Enter your email address and we'll send you a link
            to reset your password.
        </p>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>"
                 role="status" aria-live="polite">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['customer_csrf']) ?>"
            >

            <label for="email">Email Address</label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email) ?>"
                placeholder="Enter your registered email"
                autocomplete="email"
                inputmode="email"
                required
            >

            <button type="submit" class="submit-button">
                SEND RESET LINK
            </button>

        </form>

        <p class="auth-links">
            Remember your password?
            <a href="login.php">Sign In</a>
        </p>

    </section>
</main>

</body>
</html>
