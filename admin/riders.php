<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/admin_navigation.php';

// Only an authenticated DIANE administrator may manage rider accounts.
if (empty($_SESSION['admin_id']) || empty($_SESSION['access_token'])) {
    header('Location: login.php');
    exit;
}

$adminCheck = supabaseRequest(
    '/rest/v1/profiles?select=id,role&id=eq.' . rawurlencode((string) $_SESSION['admin_id']) . '&limit=1',
    'GET',
    null,
    (string) $_SESSION['access_token']
);
$adminProfile = $adminCheck['data'][0] ?? null;

if (empty($adminCheck['success']) || !$adminProfile || ($adminProfile['role'] ?? '') !== 'admin') {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

/** Make a server-only request using the Supabase secret API key. */
function dianeSupabaseSecretRequest(string $endpoint, string $method, ?array $payload = null): array
{
    if (!defined('SUPABASE_SECRET_KEY') || SUPABASE_SECRET_KEY === '') {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'SUPABASE_SECRET_KEY is missing from the server environment.'];
    }
    if (!function_exists('curl_init')) {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'PHP cURL is not enabled.'];
    }

    $ch = curl_init(SUPABASE_URL . $endpoint);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'apikey: ' . SUPABASE_SECRET_KEY
        ];

        if (
            $method === 'POST' &&
            str_starts_with($endpoint, '/rest/v1/profiles')
        ) {
            $headers[] = 'Prefer: resolution=merge-duplicates,return=representation';
        }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25
    ];

    if ($payload !== null) {
        $json = json_encode($payload);
        if ($json === false) {
            curl_close($ch);
            return ['success' => false, 'status' => 0, 'data' => null, 'error' => 'Unable to encode request.'];
        }
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    curl_setopt_array($ch, $options);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['success' => false, 'status' => 0, 'data' => null, 'error' => $curlError ?: 'Connection failed.'];
    }

    $decoded = json_decode($body, true);
    $success = $status >= 200 && $status < 300;
    $error = null;
    if (!$success) {
        $error = is_array($decoded)
            ? ($decoded['msg'] ?? $decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? 'Supabase rejected the request.')
            : ('Supabase returned HTTP ' . $status . '.');
    }

    return ['success' => $success, 'status' => $status, 'data' => $decoded, 'error' => $error];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $phone === '' || $password === '') {
        $message = 'Please complete all rider fields.';
        $messageType = 'error';
    } elseif (mb_strlen($name) > 80) {
        $message = 'Rider name must not exceed 80 characters.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Temporary password must contain at least 8 characters.';
        $messageType = 'error';
    } elseif (strlen($phone) > 30) {
        $message = 'Phone number must not exceed 30 characters.';
        $messageType = 'error';
    } elseif (!defined('SUPABASE_SECRET_KEY') || SUPABASE_SECRET_KEY === '') {
        $message = 'Server secret key is not configured. Check SUPABASE_SECRET_KEY in your .env file.';
        $messageType = 'error';
    } else {
        // Create the Auth user from the trusted PHP server only.
        $createUser = dianeSupabaseSecretRequest('/auth/v1/admin/users', 'POST', [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true,
            'user_metadata' => ['name' => $name, 'role' => 'rider']
        ]);

        if (empty($createUser['success'])) {
            $message = 'Rider account was not created. ' . (string) ($createUser['error'] ?? 'Please check the Supabase Auth response.');
            if (($createUser['status'] ?? 0) === 403 || stripos((string) ($createUser['error'] ?? ''), 'bad_jwt') !== false) {
                $message .= ' If the response mentions bad_jwt, this Supabase Auth Admin endpoint may require the legacy service_role key; do not put the secret key in an Authorization Bearer header.';
            }
            $messageType = 'error';
        } else {
            $newUser = $createUser['data']['user'] ?? $createUser['data'];
            $newUserId = (string) ($newUser['id'] ?? '');

            if ($newUserId === '') {
                $message = 'Supabase created a response, but no user ID was returned. Check Authentication → Users before trying again.';
                $messageType = 'error';
            } else {
                // Upsert the matching public profile as a rider. This also handles projects
                // where the auth-user trigger initially inserts a customer profile.
                $profileResult = dianeSupabaseSecretRequest(
                    '/rest/v1/profiles?on_conflict=id',
                    'POST',
                    ['id' => $newUserId, 'name' => $name, 'email' => $email, 'phone' => $phone, 'role' => 'rider']
                );

                if (!empty($profileResult['success'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: riders.php?created=1');
                    exit;
                }

                // Best-effort rollback so an Auth account is not left without its rider profile.
                dianeSupabaseSecretRequest('/auth/v1/admin/users/' . rawurlencode($newUserId), 'DELETE');
                $message = 'The Auth account was created, but the rider profile could not be saved. The system attempted to remove the incomplete Auth account. ' . (string) ($profileResult['error'] ?? '');
                $messageType = 'error';
            }
        }
    }
}

if (($_GET['created'] ?? '') === '1') {
    $message = 'Rider account created successfully. The rider can now sign in using the email and password you set.';
    $messageType = 'success';
}

$riderResult = supabaseRequest(
    '/rest/v1/profiles?select=id,name,email,phone,created_at,role&role=eq.rider&order=created_at.desc&limit=500',
    'GET',
    null,
    (string) $_SESSION['access_token']
);
$riders = !empty($riderResult['success']) && is_array($riderResult['data']) ? $riderResult['data'] : [];
if (empty($riderResult['success']) && $message === '') {
    $message = 'Unable to load rider profiles. Check the profiles table permissions.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Riders | DIANE Clothing Shop</title>
    <style>
        :root{--ink:#111;--muted:#777;--line:#e8e8e8;--paper:#fff;--soft:#f7f7f5}
        *{box-sizing:border-box}body{margin:0;background:#f7f7f5;color:var(--ink);font-family:Arial,Helvetica,sans-serif}
        .page{width:min(1180px,calc(100% - 40px));margin:42px auto 70px}.eyebrow{font-size:10px;letter-spacing:2.5px;color:#888;text-transform:uppercase}
        h1{font-family:Georgia,serif;font-size:clamp(32px,5vw,46px);font-weight:400;margin:8px 0}.intro{color:#666;font-size:13px;line-height:1.7;margin:0 0 28px}
        .grid{display:grid;grid-template-columns:minmax(280px,360px) minmax(0,1fr);gap:22px;align-items:start}.panel{background:#fff;border:1px solid var(--line);padding:25px}
        .panel h2{font-size:13px;letter-spacing:1.4px;text-transform:uppercase;margin:0 0 20px}.field{margin-bottom:15px}.field label{display:block;font-size:11px;font-weight:700;margin-bottom:7px}
        .field input{width:100%;height:44px;border:1px solid #ddd;padding:0 12px;font:13px Arial;background:#fff}.field input:focus{outline:1px solid #111;border-color:#111}
        .hint{font-size:11px;color:#777;line-height:1.6;margin:-3px 0 16px}.btn{width:100%;height:46px;border:0;background:#111;color:#fff;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer}.btn:hover{background:#333}
        .notice{padding:13px 15px;margin:0 0 20px;font-size:12px;line-height:1.6;border:1px solid}.notice.success{background:#f1f7f1;border-color:#cfe1cf;color:#245b2b}.notice.error{background:#fff4f3;border-color:#edcece;color:#8e2929}
        .table-wrap{overflow-x:auto}table{width:100%;border-collapse:collapse;min-width:520px}th,td{text-align:left;padding:14px 12px;border-bottom:1px solid var(--line);font-size:12px}th{font-size:10px;letter-spacing:1px;text-transform:uppercase;color:#777;background:#fafafa}.name{font-weight:700}.sub{display:block;color:#777;font-size:11px;margin-top:4px}.pill{display:inline-block;padding:5px 9px;border:1px solid #ddd;font-size:9px;letter-spacing:1px;text-transform:uppercase}.empty{padding:28px 10px;text-align:center;color:#777;font-size:12px}
        @media(max-width:850px){.grid{grid-template-columns:1fr}.page{margin-top:28px}.panel{padding:20px}}
    </style>
</head>
<body>
<?php renderAdminNavigation(); ?>
<main class="page">
    <p class="eyebrow">DIANE CLOTHING SHOP · ADMINISTRATION</p>
    <h1>Manage Riders</h1>
    <p class="intro">Create and manage delivery rider accounts. Rider accounts are created by an administrator only.</p>

    <?php if ($message !== ''): ?>
        <div class="notice <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="grid">
        <section class="panel">
            <h2>Create Rider Account</h2>
            <form method="POST" action="riders.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="field"><label for="name">Full name</label><input id="name" name="name" type="text" maxlength="80" required value="<?= htmlspecialchars((string)($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="field"><label for="email">Email address</label><input id="email" name="email" type="email" maxlength="254" autocomplete="off" required value="<?= htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="field"><label for="phone">Phone number</label><input id="phone" name="phone" type="tel" maxlength="30" required value="<?= htmlspecialchars((string)($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="field"><label for="password">Temporary password</label><input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required></div>
                <p class="hint">Use at least 8 characters. Give the temporary password to the rider privately. Never share it in screenshots or chat.</p>
                <button class="btn" type="submit">Create Rider</button>
            </form>
        </section>

        <section class="panel">
            <h2>Registered Riders <span class="sub"><?= count($riders) ?> rider account(s)</span></h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Rider</th><th>Phone</th><th>Registered</th><th>Role</th></tr></thead>
                    <tbody>
                    <?php if (!$riders): ?>
                        <tr><td colspan="4" class="empty">No rider accounts yet. Create the first rider using the form.</td></tr>
                    <?php else: foreach ($riders as $rider): ?>
                        <tr>
                            <td><span class="name"><?= htmlspecialchars((string)($rider['name'] ?? 'Unnamed rider'), ENT_QUOTES, 'UTF-8') ?></span><span class="sub"><?= htmlspecialchars((string)($rider['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars((string)($rider['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($rider['created_at']) ? htmlspecialchars(date('M j, Y', strtotime((string)$rider['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td><span class="pill">Rider</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
</body>
</html>
