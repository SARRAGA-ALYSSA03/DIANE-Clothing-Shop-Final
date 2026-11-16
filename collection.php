<?php
/**
 * DIANE CLOTHING SHOP
 * Dedicated Collection / Shop page.
 * Uses the existing Supabase products and customer session.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/includes/supabase.php';

$categories = ['All', 'New In', 'Dresses', 'Tops', 'Bottoms', 'Outerwear', 'Knitwear', 'Basics'];
$selectedCategory = (isset($_GET['category']) && is_string($_GET['category']))
    ? trim($_GET['category']) : 'All';
if (!in_array($selectedCategory, $categories, true)) {
    $selectedCategory = 'All';
}
$searchQuery = (isset($_GET['search']) && is_string($_GET['search']))
    ? trim(substr($_GET['search'], 0, 100)) : '';
$sortOption = (isset($_GET['sort']) && is_string($_GET['sort']))
    ? $_GET['sort'] : 'newest';
$allowedSortOptions = ['newest', 'price_low', 'price_high'];
if (!in_array($sortOption, $allowedSortOptions, true)) {
    $sortOption = 'newest';
}

$isCustomerLoggedIn = !empty($_SESSION['customer_id']) && !empty($_SESSION['customer_access_token']);
$fullCustomerName = trim((string)($_SESSION['customer_name'] ?? ''));
$nameParts = preg_split('/\s+/', $fullCustomerName);
$customerName = (is_array($nameParts) && !empty($nameParts[0])) ? $nameParts[0] : 'Customer';
$customerProfileImageUrl = '';
$cartCount = 0;

if ($isCustomerLoggedIn) {
    $avatarProfileResult = supabaseRequest(
        '/rest/v1/profiles?select=avatar_url&id=eq.' . rawurlencode((string)$_SESSION['customer_id']) . '&limit=1',
        'GET', null, (string)$_SESSION['customer_access_token']
    );
    if (!empty($avatarProfileResult['success']) && !empty($avatarProfileResult['data'][0]['avatar_url']) && function_exists('curl_init')) {
        $avatarPath = (string)$avatarProfileResult['data'][0]['avatar_url'];
        $encodedAvatarPath = str_replace('%2F', '/', rawurlencode($avatarPath));
        $signUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/object/sign/customer-profiles/' . $encodedAvatarPath;
        $signCurl = curl_init($signUrl);
        curl_setopt_array($signCurl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['expiresIn' => 3600]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . SUPABASE_KEY,
                'Authorization: Bearer ' . (string)$_SESSION['customer_access_token']
            ],
            CURLOPT_TIMEOUT => 20
        ]);
        $signResponse = curl_exec($signCurl);
        $signStatus = curl_getinfo($signCurl, CURLINFO_HTTP_CODE);
        curl_close($signCurl);
        if ($signResponse !== false && $signStatus >= 200 && $signStatus < 300) {
            $signData = json_decode($signResponse, true);
            $signedPath = $signData['signedURL'] ?? '';
            if (is_string($signedPath) && $signedPath !== '') {
                if (strpos($signedPath, 'http') === 0) {
                    $customerProfileImageUrl = $signedPath;
                } elseif (strpos($signedPath, '/storage/v1/') === 0) {
                    $customerProfileImageUrl = rtrim(SUPABASE_URL, '/') . $signedPath;
                } else {
                    $customerProfileImageUrl = rtrim(SUPABASE_URL, '/') . '/storage/v1/' . ltrim($signedPath, '/');
                }
            }
        }
    }

    $cartResult = supabaseRequest(
        '/rest/v1/cart_items?select=quantity&user_id=eq.' . rawurlencode((string)$_SESSION['customer_id']),
        'GET', null, (string)$_SESSION['customer_access_token']
    );
    if (!empty($cartResult['success']) && is_array($cartResult['data'] ?? null)) {
        foreach ($cartResult['data'] as $cartItem) {
            $cartCount += (int)($cartItem['quantity'] ?? 0);
        }
    }
}

$productResult = supabaseRequest('/rest/v1/products?select=*&order=created_at.desc', 'GET');
$productLoadError = empty($productResult['success']) || !is_array($productResult['data'] ?? null);
$products = [];
if (!$productLoadError) {
    foreach ($productResult['data'] as $row) {
        if (!is_array($row)) continue;
        $imageUrl = trim((string)($row['image'] ?? ''));
        if ($imageUrl === '' && !empty($row['images']) && is_array($row['images'])) {
            $imageUrl = trim((string)($row['images'][0] ?? ''));
        }
        $products[] = [
            'id' => (string)($row['id'] ?? ''),
            'name' => (string)($row['name'] ?? 'Unnamed Product'),
            'price' => (float)($row['price'] ?? 0),
            'original_price' => isset($row['original_price']) ? (float)$row['original_price'] : null,
            'category' => (string)($row['category'] ?? ''),
            'image' => $imageUrl,
            'is_new' => filter_var($row['is_new'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'rating' => max(0, min(5, (float)($row['rating'] ?? 0))),
            'review_count' => max(0, (int)($row['review_count'] ?? 0)),
            'created_at' => (string)($row['created_at'] ?? '')
        ];
    }
}

$visibleProducts = array_values(array_filter($products, function ($product) use ($selectedCategory, $searchQuery) {
    if ($selectedCategory === 'New In') {
        if (empty($product['is_new'])) return false;
    } elseif ($selectedCategory !== 'All' && $product['category'] !== $selectedCategory) {
        return false;
    }
    if ($searchQuery !== '') {
        $searchable = $product['name'] . ' ' . $product['category'];
        if (stripos($searchable, $searchQuery) === false) return false;
    }
    return true;
}));

// Sort the filtered products while preserving the newest-first default.
usort($visibleProducts, function ($a, $b) use ($sortOption) {
    if ($sortOption === 'price_low') {
        return ($a['price'] <=> $b['price']) ?: strcmp($b['created_at'], $a['created_at']);
    }
    if ($sortOption === 'price_high') {
        return ($b['price'] <=> $a['price']) ?: strcmp($b['created_at'], $a['created_at']);
    }
    return strcmp($b['created_at'], $a['created_at']);
});

function collectionEscape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collection | DIANE Clothing Shop</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root { --black:#111; --gray:#777; --border:#e5e5e5; --serif:Georgia,"Times New Roman",serif; --sans:"Helvetica Neue",Arial,sans-serif; }
        * { box-sizing:border-box; }
        body { margin:0; color:var(--black); background:#fff; font-family:var(--sans); }
        a { color:inherit; }
        .diane-header { position:relative; z-index:10; width:100%; min-height:86px; padding:12px 5%; background:#fff; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:25px; }
        .diane-brand { flex:0 0 auto; display:flex; flex-direction:column; align-items:center; text-decoration:none; }
        .diane-brand-name { font:normal 34px/1 var(--serif); letter-spacing:2px; }
        .diane-brand-subtitle { margin-top:6px; font-size:8px; font-weight:600; letter-spacing:4px; }
        .diane-header-right,.diane-navigation,.diane-account { display:flex; align-items:center; }
        .diane-header-right { justify-content:flex-end; gap:30px; margin-left:auto; }
        .diane-navigation { gap:24px; }
        .diane-navigation a,.diane-account a { color:#111; text-decoration:none; font-size:10px; font-weight:500; letter-spacing:1px; white-space:nowrap; }
        .diane-navigation a:hover,.diane-account a:hover { opacity:.55; }
        .diane-account { gap:15px; white-space:nowrap; }
        .diane-greeting { color:#666; font-size:11px; }
        .diane-profile-icon { width:30px; height:30px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #ddd; border-radius:50%; overflow:hidden; text-decoration:none; }
        .diane-profile-icon img { width:100%; height:100%; object-fit:cover; }
        .diane-profile-icon svg { width:16px; height:16px; }
        .shop-hero { padding:70px 5% 55px; background:#f5f5f3; text-align:center; }
        .eyebrow { margin:0 0 14px; color:#777; font-size:9px; font-weight:600; letter-spacing:3px; }
        .shop-hero h1 { margin:0; font:normal clamp(42px,6vw,68px)/1.1 var(--serif); }
        .shop-hero p:last-child { margin:16px auto 0; max-width:500px; color:#666; font-size:12px; line-height:1.8; }
        .shop-main { max-width:1500px; margin:auto; padding:48px 5% 100px; }
        .shop-top { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; margin-bottom:28px; }
        .shop-top h2 { margin:0; font:normal clamp(30px,3vw,40px) var(--serif); }
        .shop-count { color:#777; font-size:11px; white-space:nowrap; }
        .category-list { display:flex; gap:26px; overflow-x:auto; padding:0 0 14px; margin-bottom:28px; border-bottom:1px solid var(--border); }
        .category-list a { flex:0 0 auto; padding-bottom:8px; color:#777; font-size:11px; text-decoration:none; }
        .category-list a:hover,.category-list a.active { color:#111; border-bottom:1px solid #111; }
        .shop-tools { display:flex; justify-content:space-between; align-items:center; gap:18px; margin-bottom:30px; }
        .search-row { display:flex; align-items:center; gap:12px; flex:1; min-width:0; }
        .sort-form { display:flex; align-items:center; gap:10px; flex:0 0 auto; }
        .sort-form label { color:#777; font-size:9px; font-weight:700; letter-spacing:1px; white-space:nowrap; }
        .sort-form select { min-width:180px; min-height:44px; padding:0 34px 0 12px; border:1px solid #ddd; border-radius:0; background:#fff; color:#111; font:11px var(--sans); cursor:pointer; }
        .search-form { display:flex; width:min(100%,520px); min-height:44px; border:1px solid #ddd; }
        .search-form input { flex:1; min-width:0; padding:0 14px; border:0; outline:0; font-size:11px; }
        .search-form button { min-width:90px; padding:0 16px; border:0; background:#111; color:#fff; font-size:9px; font-weight:700; letter-spacing:1px; cursor:pointer; }
        .clear-search { color:#777; font-size:10px; }
        .product-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:42px 22px; }
        .product-card { min-width:0; }
        .product-visual { position:relative; aspect-ratio:3/4; overflow:hidden; background:#f3f3f1; }
        .product-visual a { display:block; width:100%; height:100%; }
        .product-visual img { display:block; width:100%; height:100%; object-fit:contain; mix-blend-mode:multiply; transition:transform .45s ease; }
        .product-card:hover .product-visual img { transform:scale(1.035); }
        .product-badge { position:absolute; top:12px; left:12px; padding:7px 10px; background:#fff; font-size:8px; font-weight:700; letter-spacing:1px; }
        .product-info { padding-top:13px; }
        .product-category { margin:0 0 6px; color:#888; font-size:9px; letter-spacing:1px; text-transform:uppercase; }
        .product-name { display:inline-block; color:#111; font-size:13px; font-weight:500; line-height:1.5; text-decoration:none; }
        .product-name:hover { text-decoration:underline; }
        .price-row { display:flex; gap:9px; margin-top:8px; font-size:12px; }
        .price { font-weight:600; }
        .old-price { color:#999; text-decoration:line-through; }
        .rating { margin-top:7px; color:#888; font-size:10px; }
        .stars { color:#9a7838; letter-spacing:1px; }
        .view-details { display:inline-flex; align-items:center; justify-content:center; min-height:38px; margin-top:13px; padding:0 15px; border:1px solid #111; color:#111; font-size:9px; font-weight:700; letter-spacing:1px; text-decoration:none; transition:.2s; }
        .view-details:hover { background:#111; color:#fff; }
        .empty-message { grid-column:1/-1; padding:65px 20px; color:#777; text-align:center; font-size:12px; line-height:1.8; }
        .footer { padding:50px 5% 22px; background:#111; color:#fff; }
        .footer-top,.footer-bottom { display:flex; justify-content:space-between; gap:25px; }
        .footer-top { padding-bottom:30px; border-bottom:1px solid #393939; }
        .footer-brand { color:#fff; text-decoration:none; font:normal 30px var(--serif); letter-spacing:2px; }
        .footer-brand small { display:block; margin-top:5px; font:600 8px var(--sans); letter-spacing:4px; }
        .footer-links { display:flex; flex-wrap:wrap; gap:20px; }
        .footer-links a { color:#ddd; font-size:10px; text-decoration:none; letter-spacing:.7px; }
        .footer-bottom { padding-top:20px; color:#aaa; font-size:10px; }
        @media(max-width:1100px) { .product-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } .diane-header-right { gap:16px; } .diane-navigation { gap:13px; } }
        @media(max-width:800px) { .diane-header { flex-wrap:wrap; justify-content:center; } .diane-header-right { width:100%; justify-content:center; flex-wrap:wrap; margin:0; } }
        @media(max-width:600px) { .diane-header { padding:18px 4%; flex-direction:column; gap:15px; } .diane-header-right { flex-direction:column; gap:14px; } .diane-navigation,.diane-account { justify-content:center; flex-wrap:wrap; gap:12px; } .shop-hero { padding:55px 6% 42px; } .shop-main { padding:32px 4% 65px; } .shop-top { align-items:flex-start; flex-direction:column; } .product-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:30px 12px; } .shop-tools { align-items:stretch; flex-direction:column; } .search-row { align-items:stretch; flex-direction:column; } .sort-form { justify-content:space-between; } .sort-form select { flex:1; min-width:0; } .footer-top,.footer-bottom { flex-direction:column; } }
    </style>
</head>
<body>
<header class="diane-header">
    <a class="diane-brand" href="index.php">
        <span class="diane-brand-name">DIANE</span>
        <span class="diane-brand-subtitle">CLOTHING SHOP</span>
    </a>
    <div class="diane-header-right">
        <nav class="diane-navigation" aria-label="Main navigation">
            <a href="index.php">HOME</a>
            <a href="collection.php" aria-current="page">COLLECTION</a>
            <a href="index.php#try-on">VIRTUAL TRY-ON</a>
        </nav>
        <div class="diane-account">
            <?php if ($isCustomerLoggedIn): ?>
                <a href="cart.php">MY BAG (<?= (int)$cartCount ?>)</a>
                <a href="my_orders.php">MY ORDERS</a>
                <a href="logout.php">LOG OUT</a>
                <span class="diane-greeting">Hi, <?= collectionEscape($customerName) ?></span>
                <a class="diane-profile-icon" href="profile.php" aria-label="Open my profile" title="My profile">
                    <?php if ($customerProfileImageUrl !== ''): ?>
                        <img src="<?= collectionEscape($customerProfileImageUrl) ?>" alt="My profile">
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c.7-3.3 3.2-5 7-5s6.3 1.7 7 5"></path>
                        </svg>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <a href="login.php">SIGN IN</a>
                <a href="signup.php">CREATE ACCOUNT</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<section class="shop-hero">
    <p class="eyebrow">THE DIANE COLLECTION</p>
    <h1>Find your everyday statement.</h1>
    <p>Explore timeless pieces, modern essentials, and carefully selected styles made for you.</p>
</section>

<main class="shop-main">
    <div class="shop-top">
        <div>
            <p class="eyebrow">DISCOVER YOUR NEXT FAVORITE LOOK</p>
            <h2>Shop All Clothing</h2>
        </div>
        <span class="shop-count"><?= count($visibleProducts) ?> <?= $searchQuery !== '' ? 'RESULTS' : (count($visibleProducts) === 1 ? 'PRODUCT' : 'PRODUCTS') ?></span>
    </div>

    <nav class="category-list" aria-label="Filter products by category">
        <?php foreach ($categories as $category): ?>
            <a class="<?= $selectedCategory === $category ? 'active' : '' ?>"
               href="collection.php?category=<?= rawurlencode($category) ?><?= $searchQuery !== '' ? '&amp;search=' . rawurlencode($searchQuery) : '' ?>&amp;sort=<?= rawurlencode($sortOption) ?>"
               <?= $selectedCategory === $category ? 'aria-current="page"' : '' ?>>
                <?= collectionEscape($category) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="shop-tools">
        <div class="search-row">
            <form class="search-form" method="GET" action="collection.php" role="search">
                <input type="hidden" name="category" value="<?= collectionEscape($selectedCategory) ?>">
                <input type="hidden" name="sort" value="<?= collectionEscape($sortOption) ?>">
                <input type="search" name="search" value="<?= collectionEscape($searchQuery) ?>"
                       placeholder="Search dresses, tops, trousers..." aria-label="Search products" maxlength="100">
                <button type="submit">SEARCH</button>
            </form>
            <?php if ($searchQuery !== ''): ?>
                <a class="clear-search" href="collection.php?category=<?= rawurlencode($selectedCategory) ?>&amp;sort=<?= rawurlencode($sortOption) ?>">Clear search</a>
            <?php endif; ?>
        </div>
        <form class="sort-form" method="GET" action="collection.php">
            <input type="hidden" name="category" value="<?= collectionEscape($selectedCategory) ?>">
            <?php if ($searchQuery !== ''): ?>
                <input type="hidden" name="search" value="<?= collectionEscape($searchQuery) ?>">
            <?php endif; ?>
            <label for="collection-sort">SORT BY</label>
            <select id="collection-sort" name="sort" onchange="this.form.submit()">
                <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="price_low" <?= $sortOption === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_high" <?= $sortOption === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
            </select>
        </form>
    </div>

    <div class="product-grid">
        <?php if ($productLoadError): ?>
            <div class="empty-message">We couldn't load the collection right now.<br>Please refresh the page and try again.</div>
        <?php elseif (empty($visibleProducts)): ?>
            <div class="empty-message">No products found in this selection.<br>Try another category or search term.</div>
        <?php else: ?>
            <?php foreach ($visibleProducts as $product): ?>
                <article class="product-card">
                    <div class="product-visual">
                        <a href="product.php?id=<?= rawurlencode($product['id']) ?>" aria-label="View <?= collectionEscape($product['name']) ?>">
                            <?php if ($product['image'] !== ''): ?>
                                <img src="<?= collectionEscape($product['image']) ?>" alt="<?= collectionEscape($product['name']) ?>" loading="lazy">
                            <?php endif; ?>
                        </a>
                        <?php if (!empty($product['is_new'])): ?>
                            <span class="product-badge">NEW</span>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <p class="product-category"><?= collectionEscape($product['category']) ?></p>
                        <a class="product-name" href="product.php?id=<?= rawurlencode($product['id']) ?>"><?= collectionEscape($product['name']) ?></a>
                        <div class="price-row">
                            <span class="price">₱<?= number_format($product['price'], 2) ?></span>
                            <?php if ($product['original_price'] !== null && $product['original_price'] > $product['price']): ?>
                                <span class="old-price">₱<?= number_format($product['original_price'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rating">
                            <?php if ($product['review_count'] > 0): ?>
                                <span class="stars"><?= str_repeat('★', (int)round($product['rating'])) . str_repeat('☆', 5 - (int)round($product['rating'])) ?></span>
                                <?= number_format($product['rating'], 1) ?> (<?= (int)$product['review_count'] ?> <?= $product['review_count'] === 1 ? 'review' : 'reviews' ?>)
                            <?php else: ?>
                                No reviews yet
                            <?php endif; ?>
                        </div>
                        <a class="view-details" href="product.php?id=<?= rawurlencode($product['id']) ?>">VIEW DETAILS</a>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    <div class="footer-top">
        <a class="footer-brand" href="index.php">DIANE<small>CLOTHING SHOP</small></a>
        <nav class="footer-links" aria-label="Footer navigation">
            <a href="index.php">HOME</a>
            <a href="collection.php">COLLECTION</a>
            <a href="index.php#try-on">VIRTUAL TRY-ON</a>
            <?php if ($isCustomerLoggedIn): ?>
                <a href="cart.php">MY BAG</a><a href="my_orders.php">MY ORDERS</a><a href="logout.php">LOG OUT</a>
            <?php else: ?>
                <a href="login.php">SIGN IN</a><a href="signup.php">CREATE ACCOUNT</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="footer-bottom">
        <span>DIANE CLOTHING SHOP &copy; <?= date('Y') ?></span>
        <span>SIMPLE. TIMELESS. YOU.</span>
    </div>
</footer>
</body>
</html>
