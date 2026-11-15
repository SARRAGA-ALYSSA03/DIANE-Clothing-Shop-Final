<?php
/**
 * DIANE CLOTHING SHOP
 * Local demo payment screen for the school project.
 * This does NOT connect to GCash/Maya or collect real money.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/includes/supabase.php';

if (empty($_SESSION['customer_id']) || empty($_SESSION['customer_access_token'])) {
    header('Location: login.php');
    exit;
}

$userId = (string) $_SESSION['customer_id'];
$refreshToken = trim((string) ($_SESSION['customer_refresh_token'] ?? ''));
if ($refreshToken === '') {
    header('Location: login.php?expired=1');
    exit;
}

$refreshResult = supabaseRequest(
    '/auth/v1/token?grant_type=refresh_token',
    'POST',
    ['refresh_token' => $refreshToken]
);
$refreshData = $refreshResult['data'] ?? null;

if (
    empty($refreshResult['success']) ||
    !is_array($refreshData) ||
    empty($refreshData['access_token']) ||
    empty($refreshData['refresh_token']) ||
    (!empty($refreshData['user']['id']) && $refreshData['user']['id'] !== $userId)
) {
    unset(
        $_SESSION['customer_id'],
        $_SESSION['customer_access_token'],
        $_SESSION['customer_refresh_token'],
        $_SESSION['customer_name'],
        $_SESSION['customer_email'],
        $_SESSION['customer_csrf']
    );
    header('Location: login.php?expired=1');
    exit;
}

$_SESSION['customer_access_token'] = $refreshData['access_token'];
$_SESSION['customer_refresh_token'] = $refreshData['refresh_token'];
$accessToken = (string) $_SESSION['customer_access_token'];

if (empty($_SESSION['customer_csrf'])) {
    $_SESSION['customer_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['customer_csrf'];

function demoPaymentEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$orderId = (isset($_GET['order']) && is_string($_GET['order'])) ? trim($_GET['order']) : '';
if ($orderId === '' && isset($_POST['order_id']) && is_string($_POST['order_id'])) {
    $orderId = trim($_POST['order_id']);
}

$error = '';
$order = null;
$completed = false;

if ($orderId === '') {
    $error = 'Missing order reference. Please return to checkout.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $error = 'Your session has expired. Please refresh and try again.';
    } else {
        $confirmResult = supabaseRequest(
            '/rest/v1/rpc/confirm_demo_payment',
            'POST',
            ['p_order_id' => $orderId],
            $accessToken
        );
        $confirmData = $confirmResult['data'] ?? null;

        if (
            empty($confirmResult['success']) ||
            !is_array($confirmData) ||
            empty($confirmData['success'])
        ) {
            $error = is_array($confirmData) ? (string) ($confirmData['message'] ?? '') : '';
            if ($error === '') {
                $error = (string) ($confirmResult['error'] ?? 'Unable to confirm the demo payment.');
            }
        } else {
            header('Location: checkout.php?success=1&order=' . rawurlencode($orderId));
            exit;
        }
    }
}

if ($orderId !== '') {
    $orderResult = supabaseRequest(
        '/rest/v1/orders?select=id,total,payment_method,payment_status,status' .
        '&id=eq.' . rawurlencode($orderId) .
        '&user_id=eq.' . rawurlencode($userId) .
        '&limit=1',
        'GET',
        null,
        $accessToken
    );

    if (!empty($orderResult['success']) && is_array($orderResult['data'] ?? null) && !empty($orderResult['data'][0])) {
        $order = $orderResult['data'][0];
        if (!in_array($order['payment_method'] ?? '', ['GCash', 'Maya'], true)) {
            $error = 'This order does not use GCash or Maya.';
            $order = null;
        } elseif (($order['payment_status'] ?? '') === 'paid') {
            $completed = true;
        } elseif (($order['payment_status'] ?? '') !== 'pending_verification') {
            $error = 'This order is not awaiting demo payment confirmation.';
            $order = null;
        }
    } elseif ($error === '') {
        $error = 'We could not find this order under your account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Demo Payment | DIANE Clothing Shop</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f5f3;color:#111;font-family:"Helvetica Neue",Arial,sans-serif}
.payment-card{width:min(100%,470px);padding:38px;background:#fff;border:1px solid #e5e5e5}
.brand{margin:0;text-align:center;font:34px Georgia,"Times New Roman",serif;letter-spacing:3px}
.eyebrow{margin:7px 0 30px;text-align:center;font-size:9px;letter-spacing:3px}
h1{margin:0 0 12px;font:normal 27px Georgia,"Times New Roman",serif}
p{color:#666;font-size:12px;line-height:1.8}
.demo-label{margin:20px 0;padding:12px;background:#fff8e8;border:1px solid #ead9ad;color:#6b531d;font-size:11px;line-height:1.7}
.details{margin:22px 0;padding:18px 0;border-top:1px solid #e5e5e5;border-bottom:1px solid #e5e5e5}
.detail-row{display:flex;justify-content:space-between;gap:15px;margin:9px 0;font-size:12px}
.amount{font-size:20px;font-weight:700}
button,.back-link{display:block;width:100%;min-height:49px;margin-top:13px;padding:14px;border:1px solid #111;background:#111;color:#fff;text-align:center;text-decoration:none;font-size:10px;font-weight:700;letter-spacing:1px;cursor:pointer}
.back-link{background:#fff;color:#111}
.error{margin:18px 0;padding:13px;background:#fff1f1;color:#a21d1d}
@media(max-width:520px){.payment-card{padding:27px 21px}}
</style>
</head>
<body>
<main class="payment-card">
    <p class="brand">DIANE</p>
    <p class="eyebrow">CLOTHING SHOP · DEMO CHECKOUT</p>
    <?php if ($error !== ''): ?>
        <div class="error"><?= demoPaymentEscape($error) ?></div>
        <a class="back-link" href="cart.php">RETURN TO SHOPPING BAG</a>
    <?php elseif ($completed && is_array($order)): ?>
        <h1>Payment already confirmed</h1>
        <p>This demo payment has already been confirmed for your order.</p>
        <a class="back-link" href="checkout.php?success=1&order=<?= rawurlencode($orderId) ?>">VIEW ORDER</a>
    <?php elseif (is_array($order)): ?>
        <h1>Confirm your <?= demoPaymentEscape($order['payment_method']) ?> payment</h1>
        <p>Review the order details, then confirm the simulated payment below.</p>
        <div class="demo-label">
            <strong>School project demo only.</strong><br>
            This is not the official GCash or Maya app. No real wallet account is connected and no real money will be collected.
        </div>
        <div class="details">
            <div class="detail-row"><span>Order number</span><strong><?= demoPaymentEscape($order['id']) ?></strong></div>
            <div class="detail-row"><span>Payment method</span><strong><?= demoPaymentEscape($order['payment_method']) ?></strong></div>
            <div class="detail-row"><span>Amount</span><strong class="amount">₱<?= number_format((float) $order['total'], 2) ?></strong></div>
        </div>
        <form method="POST" action="demo_payment.php">
            <input type="hidden" name="order_id" value="<?= demoPaymentEscape($orderId) ?>">
            <input type="hidden" name="csrf_token" value="<?= demoPaymentEscape($csrfToken) ?>">
            <button type="submit">CONFIRM DEMO PAYMENT</button>
        </form>
        <a class="back-link" href="cart.php">CANCEL AND RETURN TO SHOP</a>
    <?php endif; ?>
</main>
</body>
</html>
