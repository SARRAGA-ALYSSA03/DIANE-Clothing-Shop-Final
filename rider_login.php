<?php
/**
 * DIANE Clothing Shop - Rider Login
 * Uses Supabase Auth and verifies the public.profiles role before granting rider access.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/includes/supabase.php';

if (!empty($_SESSION['rider_id']) && !empty($_SESSION['rider_access_token'])) {
    header('Location: rider_dashboard.php');
    exit;
}

$error = '';
$emailValue = '';

if (empty($_SESSION['rider_login_csrf'])) {
    $_SESSION['rider_login_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $emailValue = $email;

    if ($postedToken === '' || !hash_equals((string)$_SESSION['rider_login_csrf'], $postedToken)) {
        http_response_code(403);
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter your email address and password.';
    } else {
        $authResult = supabaseRequest(
            '/auth/v1/token?grant_type=password',
            'POST',
            ['email' => $email, 'password' => $password]
        );

        $authData = $authResult['data'] ?? [];
        $authUser = is_array($authData) ? ($authData['user'] ?? []) : [];
        $userId = (string)($authUser['id'] ?? '');
        $accessToken = (string)($authData['access_token'] ?? '');
        $refreshToken = (string)($authData['refresh_token'] ?? '');

        if (empty($authResult['success']) || $userId === '' || $accessToken === '') {
            $error = 'Unable to sign in. Check your email and password.';
        } else {
            $profileResult = supabaseRequest(
                '/rest/v1/profiles?select=id,name,email,role&id=eq.' . rawurlencode($userId) . '&limit=1',
                'GET',
                null,
                $accessToken
            );
            $profile = $profileResult['data'][0] ?? null;

            if (empty($profileResult['success']) || !is_array($profile) || ($profile['role'] ?? '') !== 'rider') {
                // Do not create a rider session for admins or customers.
                $error = 'This account does not have rider access. Please contact the DIANE administrator.';
            } else {
                session_regenerate_id(true);
                unset(
                    $_SESSION['admin_id'], $_SESSION['access_token'], $_SESSION['admin_name'],
                    $_SESSION['customer_id'], $_SESSION['customer_access_token'],
                    $_SESSION['customer_refresh_token'], $_SESSION['customer_name'],
                    $_SESSION['customer_email']
                );
                $_SESSION['rider_id'] = $userId;
                $_SESSION['rider_access_token'] = $accessToken;
                $_SESSION['rider_refresh_token'] = $refreshToken;
                $_SESSION['rider_name'] = (string)($profile['name'] ?? 'Rider');
                $_SESSION['rider_email'] = (string)($profile['email'] ?? $email);
                $_SESSION['rider_csrf'] = bin2hex(random_bytes(32));
                unset($_SESSION['rider_login_csrf']);
                header('Location: rider_dashboard.php');
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
    <title>Rider Login | DIANE Clothing Shop</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;background:#f5f5f3;color:#111;font-family:Arial,Helvetica,sans-serif;display:flex;flex-direction:column}
        header{height:76px;background:#111;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 5.5%;}
        .brand{font-family:Georgia,serif;font-size:25px;letter-spacing:5px;font-weight:600}.brand small{display:block;font-family:Arial,sans-serif;font-size:9px;letter-spacing:2px;margin-top:3px}
        .header-note{font-size:10px;letter-spacing:1.7px;text-align:right;line-height:1.7}
        main{width:min(440px,calc(100% - 36px));margin:auto;padding:55px 0}
        .eyebrow{font-size:10px;letter-spacing:2.5px;color:#777;text-transform:uppercase;margin:0 0 12px}
        h1{font-family:Georgia,serif;font-size:38px;font-weight:400;margin:0 0 10px}
        .intro{font-size:13px;color:#777;line-height:1.7;margin:0 0 28px}
        .panel{background:#fff;border:1px solid #e5e5e5;padding:30px}
        label{display:block;font-size:11px;font-weight:700;margin:0 0 8px}
        .field{margin-bottom:19px}
        input{width:100%;height:47px;border:1px solid #ddd;padding:0 13px;font-size:13px;background:#fff}
        input:focus{outline:1px solid #111;border-color:#111}
        button{width:100%;height:48px;border:0;background:#111;color:#fff;font-size:11px;letter-spacing:1.8px;font-weight:700;cursor:pointer;margin-top:5px}
        button:hover{background:#333}
        .error{border:1px solid #e9caca;background:#fff5f4;color:#922d2d;padding:12px 14px;font-size:12px;line-height:1.6;margin-bottom:18px}
        .foot{font-size:11px;color:#888;text-align:center;line-height:1.7;margin:20px 0 0}
        footer{text-align:center;padding:18px;color:#888;font-size:10px;letter-spacing:1.4px}
        @media(max-width:520px){header{padding:0 20px}.panel{padding:23px}h1{font-size:33px}}
    </style>
</head>
<body>
<header>
    <div class="brand">DIANE<small>CLOTHING SHOP</small></div>
    <div class="header-note">RIDER PORTAL<br>DELIVERY MANAGEMENT</div>
</header>
<main>
    <p class="eyebrow">DIANE CLOTHING SHOP · RIDER ACCESS</p>
    <h1>Rider Login</h1>
    <p class="intro">Sign in using the email address and temporary password provided by your DIANE administrator.</p>
    <section class="panel">
        <?php if ($error !== ''): ?>
            <div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="POST" action="rider_login.php" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['rider_login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" autocomplete="username" maxlength="254" required value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button type="submit">SIGN IN</button>
        </form>
    </section>
    <p class="foot">Rider accounts are created and managed by the DIANE administrator.</p>
</main>
<footer>© <?= date('Y') ?> DIANE CLOTHING SHOP</footer>
</body>
</html>
