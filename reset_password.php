
<?php
session_start();

require_once __DIR__ . '/includes/supabase.php';

if (empty($_SESSION['customer_csrf'])) {
    $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';
$passwordUpdated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfSubmitted = $_POST['csrf_token'] ?? '';
    $csrfSession = $_SESSION['customer_csrf'] ?? '';

    $accessToken = trim((string)($_POST['access_token'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if (
        $csrfSession === '' ||
        $csrfSubmitted === '' ||
        !hash_equals($csrfSession, $csrfSubmitted)
    ) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page and try again.');

    } elseif ($accessToken === '') {

        $message = 'Your recovery link is missing or expired. Please request a new one.';
        $messageType = 'error';

    } elseif (strlen($password) < 8) {

        $message = 'Your password must contain at least 8 characters.';
        $messageType = 'error';

    } elseif ($password !== $confirmPassword) {

        $message = 'Your passwords do not match.';
        $messageType = 'error';

    } else {

        $result = supabaseRequest(
            '/auth/v1/user',
            'PUT',
            ['password' => $password],
            $accessToken
        );

        if (empty($result['success'])) {
            $message = 'Unable to update your password. Your recovery link may have expired. Please request a new one.';
            $messageType = 'error';
        } else {
            $message = 'Your password has been updated successfully. You can now sign in using your new password.';
            $messageType = 'success';
            $passwordUpdated = true;

            // Do not keep the recovery token in the PHP session.
            unset($_SESSION['customer_access_token']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | DIANE Clothing Shop</title>

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

        .form-group {
            margin-bottom: 21px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            font-size: 11px;
            font-weight: 700;
        }

        .field-wrap {
            position: relative;
        }

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
        }

        input:focus {
            border-color: var(--ink);
        }

        input::placeholder {
            color: #999;
        }

        .password-field {
            padding-right: 48px;
        }

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
            margin-top: 5px;
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

        <h1>Reset Password</h1>

        <p class="subtitle">
            Create a new password for your DIANE account.
        </p>

        <?php if ($message !== ''): ?>
            <div class="message <?= htmlspecialchars($messageType) ?>"
                 role="status" aria-live="polite">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($passwordUpdated): ?>

            <p class="auth-links">
                <a href="login.php">SIGN IN WITH YOUR NEW PASSWORD</a>
            </p>

        <?php else: ?>

            <form method="POST" action="reset_password.php" id="resetForm">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['customer_csrf']) ?>"
                >

                <input
                    type="hidden"
                    name="access_token"
                    id="accessToken"
                    value=""
                >

                <div class="form-group">
                    <label for="password">New Password</label>

                    <div class="field-wrap">
                        <input
                            class="password-field"
                            type="password"
                            id="password"
                            name="password"
                            placeholder="At least 8 characters"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >

                        <button class="password-toggle" type="button"
                                data-target="password"
                                aria-label="Show password"
                                aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>

                    <div class="field-wrap">
                        <input
                            class="password-field"
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Enter your password again"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >

                        <button class="password-toggle" type="button"
                                data-target="confirm_password"
                                aria-label="Show password"
                                aria-pressed="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-button">
                    UPDATE PASSWORD
                </button>

            </form>

            <p class="auth-links">
                Remember your password?
                <a href="login.php">Sign In</a>
            </p>

        <?php endif; ?>

    </section>
</main>

<script>
(function () {
    const tokenField = document.getElementById('accessToken');
    const resetForm = document.getElementById('resetForm');

    if (tokenField) {
        // Supabase's recovery access token is returned in the URL fragment.
        const hash = window.location.hash.substring(1);
        const params = new URLSearchParams(hash);

        const accessToken = params.get('access_token');
        const recoveryType = params.get('type');

        if (accessToken && recoveryType === 'recovery') {
            tokenField.value = accessToken;

            // Remove the token from the visible browser URL.
            history.replaceState(
                null,
                document.title,
                window.location.pathname + window.location.search
            );
        }
    }

    if (resetForm) {
        resetForm.addEventListener('submit', function (event) {
            const token = tokenField.value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (!token) {
                event.preventDefault();
                alert('Please open the password recovery link sent to your email. If it has expired, request a new link.');
                return;
            }

            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Your passwords do not match.');
            }
        });
    }

    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.target);
            const shouldShow = input.type === 'password';

            input.type = shouldShow ? 'text' : 'password';
            button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
            button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
        });
    });
})();
</script>

</body>
</html>
