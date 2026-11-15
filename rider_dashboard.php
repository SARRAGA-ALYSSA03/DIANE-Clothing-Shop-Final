
<?php
/**
 * DIANE Clothing Shop - Rider Dashboard
 * Shows only orders assigned to the signed-in rider.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/includes/supabase.php';

function rider_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Require a valid rider session.
if (
    empty($_SESSION['rider_id']) ||
    empty($_SESSION['rider_access_token'])
) {
    header('Location: rider_login.php');
    exit;
}

$riderId = (string)$_SESSION['rider_id'];
$accessToken = (string)$_SESSION['rider_access_token'];

// Verify that the logged-in account still has the rider role.
$profileResult = supabaseRequest(
    '/rest/v1/profiles?select=id,name,email,role&id=eq.'
    . rawurlencode($riderId) . '&limit=1',
    'GET',
    null,
    $accessToken
);

$profile = $profileResult['data'][0] ?? null;

if (
    empty($profileResult['success']) ||
    !is_array($profile) ||
    ($profile['role'] ?? '') !== 'rider'
) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: rider_login.php');
    exit;
}

$_SESSION['rider_name'] =
    (string)($profile['name'] ?? 'Rider');

$_SESSION['rider_email'] =
    (string)($profile['email'] ?? '');

// Handle logout.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'logout'
) {
    $postedToken = (string)($_POST['csrf_token'] ?? '');

    if (
        empty($_SESSION['rider_csrf']) ||
        $postedToken === '' ||
        !hash_equals($_SESSION['rider_csrf'], $postedToken)
    ) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page.');
    }

    // Sign out from Supabase.
    supabaseRequest(
        '/auth/v1/logout',
        'POST',
        [],
        $accessToken
    );

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: rider_login.php');
    exit;
}

// Generate CSRF token.
if (empty($_SESSION['rider_csrf'])) {
    $_SESSION['rider_csrf'] = bin2hex(random_bytes(32));
}

// Handle rider delivery-status updates using the secure Supabase RPC.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'update_delivery_status'
) {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $orderId = trim((string)($_POST['order_id'] ?? ''));
    $newStatus = trim((string)($_POST['new_status'] ?? ''));

    $paymentCollected = (
        ($_POST['payment_collected'] ?? '') === '1'
    );

    if (
        empty($_SESSION['rider_csrf']) ||
        $postedToken === '' ||
        !hash_equals($_SESSION['rider_csrf'], $postedToken)
    ) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page.');
    }

    if (
        $orderId === '' ||
        !in_array($newStatus, ['Shipped', 'Out for Delivery', 'Delivered'], true)
    ) {
        $_SESSION['rider_flash'] = 'Invalid delivery update.';
        header('Location: rider_dashboard.php');
        exit;
    }

    $updateResult = supabaseRequest(
        '/rest/v1/rpc/rider_update_delivery',
        'POST',
        [
            'p_order_id' => $orderId,
            'p_new_status' => $newStatus,
            'p_payment_collected' => $paymentCollected
        ],
        $accessToken
    );

    $rpcData = $updateResult['data'] ?? null;

    if (
        !empty($updateResult['success']) &&
        is_array($rpcData) &&
        !empty($rpcData['success'])
    ) {
        $_SESSION['rider_flash'] = $rpcData['message']
            ?? 'Delivery status updated.';
    } elseif (
        !empty($updateResult['success']) &&
        is_array($rpcData)
    ) {
        $_SESSION['rider_flash'] = $rpcData['message']
            ?? 'Delivery update was rejected.';
    } else {
        $_SESSION['rider_flash'] = $updateResult['error']
            ?? 'Unable to update delivery status.';
    }

    header('Location: rider_dashboard.php');
    exit;
}
// Fetch only orders assigned to this rider.
$ordersResult = supabaseRequest(
    '/rest/v1/orders?select=id,full_name,total,status,payment_method,payment_status,created_at,rider_id'
    . '&rider_id=eq.' . rawurlencode($riderId)
    . '&order=created_at.desc&limit=200',
    'GET',
    null,
    $accessToken
);

$orders = (
    !empty($ordersResult['success']) &&
    is_array($ordersResult['data'] ?? null)
)
    ? $ordersResult['data']
    : [];

$loadError = empty($ordersResult['success'])
    ? (string)($ordersResult['error'] ?? 'Unable to load assigned orders.')
    : '';

function rider_date($value): string
{
    if (!$value) {
        return '—';
    }

    try {
        return (new DateTime((string)$value))
            ->setTimezone(new DateTimeZone('Asia/Manila'))
            ->format('M d, Y · h:i A');
    } catch (Throwable $e) {
        return '—';
    }
}

function rider_status_class($status): string
{
    $status = strtolower((string)$status);

    if ($status === 'delivered') return 'delivered';
    if ($status === 'cancelled') return 'cancelled';
    if ($status === 'shipped') return 'shipped';
    if ($status === 'out for delivery') return 'out-for-delivery';
    if ($status === 'confirmed') return 'confirmed';

    return 'processing';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rider Dashboard | DIANE Clothing Shop</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f5f3;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
        }

        header {
            min-height: 76px;
            background: #111;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 5.5%;
            gap: 20px;
        }

        .brand {
            font-family: Georgia, serif;
            font-size: 25px;
            letter-spacing: 5px;
            font-weight: 600;
        }

        .brand small {
            display: block;
            font-family: Arial, sans-serif;
            font-size: 9px;
            letter-spacing: 2px;
            margin-top: 3px;
        }

        .head-right {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .head-note {
            font-size: 10px;
            letter-spacing: 1.6px;
            text-align: right;
            line-height: 1.7;
            color: #ddd;
        }

        .logout {
            background: transparent;
            border: 1px solid #777;
            color: #fff;
            padding: 11px 15px;
            font-size: 10px;
            letter-spacing: 1.3px;
            cursor: pointer;
        }

        .logout:hover {
            background: #fff;
            color: #111;
        }

        main {
            width: min(1180px, calc(100% - 40px));
            margin: 42px auto 70px;
        }

        .eyebrow {
            font-size: 10px;
            letter-spacing: 2.5px;
            color: #777;
            text-transform: uppercase;
            margin: 0 0 12px;
        }

        .heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }

        h1 {
            font-family: Georgia, serif;
            font-size: 42px;
            font-weight: 400;
            margin: 0 0 8px;
        }

        .welcome {
            font-size: 13px;
            color: #777;
            margin: 0;
        }

        .count {
            border: 1px solid #ddd;
            background: #fff;
            padding: 15px 19px;
            min-width: 130px;
        }

        .count strong {
            display: block;
            font-size: 25px;
            font-weight: 400;
        }

        .count span {
            font-size: 9px;
            letter-spacing: 1.6px;
            color: #777;
        }

        .panel {
            background: #fff;
            border: 1px solid #e5e5e5;
            padding: 25px;
            margin-bottom: 22px;
        }

        .panel-title {
            font-size: 12px;
            letter-spacing: 1.7px;
            font-weight: 700;
            margin: 0 0 18px;
        }

        .notice {
            border: 1px solid #e9caca;
            background: #fff5f4;
            color: #922d2d;
            padding: 13px 15px;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .empty {
            text-align: center;
            color: #777;
            padding: 38px 15px;
            font-size: 13px;
            line-height: 1.8;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th {
            text-align: left;
            background: #fafafa;
            color: #777;
            font-size: 9px;
            letter-spacing: 1.5px;
            font-weight: 600;
            padding: 14px 12px;
        }

        td {
            border-bottom: 1px solid #eee;
            padding: 16px 12px;
            font-size: 12px;
            vertical-align: middle;
        }

        .order-id {
            font-family: monospace;
            font-size: 11px;
            color: #555;
        }

        .customer {
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            font-size: 9px;
            letter-spacing: 1px;
            text-transform: uppercase;
            border: 1px solid #ddd;
        }

        .status.delivered {
            background: #f0f7f0;
            border-color: #cfe2cf;
            color: #356b35;
        }

        .status.cancelled {
            background: #fff3f2;
            border-color: #eccdca;
            color: #963e36;
        }

        .status.shipped {
            background: #f0f4fa;
            border-color: #d5dfef;
            color: #3e5d86;
        }

        .status.confirmed {
            background: #f7f5eb;
            border-color: #e8dfb9;
            color: #756322;
        }

        .status.out-for-delivery {
            background: #fff6e9;
            border-color: #f0d7ad;
            color: #8a5a16;
        }

        .status.processing {
            background: #f5f5f5;
            color: #555;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #888;
            font-size: 10px;
            letter-spacing: 1.4px;
        }

        @media (max-width: 650px) {
            header {
                padding: 14px 20px;
                align-items: flex-start;
            }

            .head-right {
                gap: 10px;
                flex-direction: column;
                align-items: flex-end;
            }

            .head-note {
                display: none;
            }

            main {
                width: calc(100% - 28px);
                margin: 28px auto 50px;
            }

            .heading {
                align-items: flex-start;
                flex-direction: column;
            }

            h1 {
                font-size: 35px;
            }

            .panel {
                padding: 17px;
            }

            .count {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="brand">
        DIANE
        <small>CLOTHING SHOP</small>
    </div>

    <div class="head-right">
        <div class="head-note">
            RIDER PORTAL<br>
            DELIVERY MANAGEMENT
        </div>

        <form
            method="POST"
            action="rider_dashboard.php"
            onsubmit="return confirm('Are you sure you want to sign out?');"
        >
            <input type="hidden" name="action" value="logout">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= rider_h($_SESSION['rider_csrf']) ?>"
            >

            <button class="logout" type="submit">
                LOG OUT
            </button>
        </form>
    </div>
</header>

<main>

    <p class="eyebrow">
        DIANE CLOTHING SHOP · RIDER PORTAL
    </p>

    <div class="heading">

        <div>
            <h1>My Deliveries</h1>

            <p class="welcome">
                Welcome,
                <?= rider_h($_SESSION['rider_name']) ?>.
                Here are the orders assigned to you.
            </p>
        </div>

        <div class="count">
            <strong><?= count($orders) ?></strong>
            <span>ASSIGNED ORDER(S)</span>
        </div>

    </div>

    <?php if ($loadError !== ''): ?>

        <div class="notice" role="alert">
            We couldn't load your assigned orders:
            <?= rider_h($loadError) ?>
            <br>
            Please contact the DIANE administrator if this continues.
        </div>

    <?php endif; ?>

    <?php if (!empty($_SESSION['rider_flash'])): ?>
        <div class="notice" role="status" style="border-color:#cfe2cf;background:#f0f7f0;color:#356b35;">
            <?= rider_h($_SESSION['rider_flash']) ?>
        </div>
        <?php unset($_SESSION['rider_flash']); ?>
    <?php endif; ?>

    <section class="panel">

        <h2 class="panel-title">
            ASSIGNED ORDERS
        </h2>

        <?php if ($loadError === '' && count($orders) === 0): ?>

            <div class="empty">
                You don't have any assigned deliveries yet.
                <br>
                The DIANE administrator will assign orders to you.
            </div>

        <?php elseif (count($orders) > 0): ?>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>ORDER</th>
                            <th>CUSTOMER</th>
                            <th>ORDER DATE</th>
                            <th>TOTAL</th>
                            <th>PAYMENT</th>
                            <th>PAYMENT STATUS</th>
                            <th>DELIVERY STATUS / ACTION</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($orders as $order): ?>

                            <tr>

                                <td>
                                    <span class="order-id">
                                        #<?= rider_h(substr(
                                            (string)($order['id'] ?? ''),
                                            0,
                                            8
                                        )) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="customer">
                                        <?= rider_h(
                                            $order['full_name'] ?? 'Customer'
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= rider_h(
                                        rider_date($order['created_at'] ?? null)
                                    ) ?>
                                </td>

                                <td>
                                    ₱<?= number_format(
                                        (float)($order['total'] ?? 0),
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?= rider_h(
                                        $order['payment_method'] ?? '—'
                                    ) ?>
                                </td>

                                <td>
                                    <?= rider_h(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string)($order['payment_status'] ?? '—')
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status <?= rider_h(
                                        rider_status_class($order['status'] ?? '')
                                    ) ?>">
                                        <?= rider_h($order['status'] ?? 'Unknown') ?>
                                    </span>

                                    <?php if (($order['status'] ?? '') === 'Confirmed'): ?>
                                        <form method="POST" action="rider_dashboard.php" style="margin-top:10px;" onsubmit="return confirm('Mark this order as shipped?');">
                                            <input type="hidden" name="action" value="update_delivery_status">
                                            <input type="hidden" name="order_id" value="<?= rider_h($order['id'] ?? '') ?>">
                                            <input type="hidden" name="new_status" value="Shipped">
                                            <input type="hidden" name="csrf_token" value="<?= rider_h($_SESSION['rider_csrf']) ?>">
                                            <button class="logout" type="submit" style="background:#111;color:#fff;">MARK SHIPPED</button>
                                        </form>
                                    <?php elseif (($order['status'] ?? '') === 'Shipped'): ?>
                                        <form method="POST" action="rider_dashboard.php" style="margin-top:10px;" onsubmit="return confirm('Mark this order as out for delivery?');">
                                            <input type="hidden" name="action" value="update_delivery_status">
                                            <input type="hidden" name="order_id" value="<?= rider_h($order['id'] ?? '') ?>">
                                            <input type="hidden" name="new_status" value="Out for Delivery">
                                            <input type="hidden" name="csrf_token" value="<?= rider_h($_SESSION['rider_csrf']) ?>">
                                            <button class="logout" type="submit" style="background:#111;color:#fff;">OUT FOR DELIVERY</button>
                                        </form>
                                    <?php elseif (($order['status'] ?? '') === 'Out for Delivery'): ?>
                                        <?php
                                            $isCOD = strtolower(trim(
                                                (string)($order['payment_method'] ?? '')
                                            )) === 'cash on delivery';
                                        ?>
                                        <form method="POST" action="rider_dashboard.php" style="margin-top:10px;" onsubmit="return confirm('Confirm that this order has been delivered to the customer?');">
                                            <input type="hidden" name="action" value="update_delivery_status">
                                            <input type="hidden" name="order_id" value="<?= rider_h($order['id'] ?? '') ?>">
                                            <input type="hidden" name="new_status" value="Delivered">
                                            <input type="hidden" name="csrf_token" value="<?= rider_h($_SESSION['rider_csrf']) ?>">

                                            <?php if ($isCOD): ?>
                                                <label style="display:block;margin-bottom:10px;font-size:11px;line-height:1.5;">
                                                    <input type="checkbox" name="payment_collected" value="1" required>
                                                    I confirm that I collected the cash payment.
                                                </label>
                                            <?php endif; ?>

                                            <button class="logout" type="submit" style="background:#111;color:#fff;">MARK DELIVERED</button>
                                        </form>
                                    <?php elseif (($order['status'] ?? '') === 'Delivered'): ?>
                                        <small style="display:block;margin-top:8px;color:#356b35;">Delivery completed</small>
                                    <?php elseif (($order['status'] ?? '') === 'Cancelled'): ?>
                                        <small style="display:block;margin-top:8px;color:#963e36;">Order cancelled</small>
                                    <?php else: ?>
                                        <small style="display:block;margin-top:8px;color:#777;">Waiting for admin confirmation</small>
                                    <?php endif; ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer>
    © <?= date('Y') ?> DIANE CLOTHING SHOP
</footer>

</body>
</html>
