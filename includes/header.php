<?php
/**
 * header.php - Navigation Header (Included on every page)
 *
 * Phase 6, Task 6.9 — Navigation & Layout improvements:
 *   - Active page highlighting on nav links
 *   - Cart badge with live item count (customers only)
 *   - Visual separator between public links and auth links
 *   - Improved mobile spacing
 *
 * Requires: config.php (provides $base_url, $conn) and auth.php (provides session functions)
 *           must be included BEFORE this file.
 *
 * Usage: include 'includes/header.php';
 */

// ─── Detect current page for active nav highlighting ─────────────────────────
// Compare the current script filename against each nav link target.
// REQUEST_URI includes query strings — basename(parse_url) strips them.
$_current_page = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

/**
 * Returns ' active' if the current page matches the given filename(s).
 * Accepts a single filename or an array for pages that share a link
 * (e.g. vendor-detail.php should highlight the "Vendors" link).
 */
function nav_active(string|array $pages): string {
    global $_current_page;
    $pages = (array) $pages;
    return in_array($_current_page, $pages, true) ? ' active' : '';
}

// ─── Cart count for badge (customers only) ──────────────────────────────────
// One lightweight query here so the count is always current in the navbar.
$_cart_count = 0;
if (is_logged_in() && get_current_user_type() === 'customer') {
    $cid = get_customer_id();
    if ($cid) {
        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM cart WHERE customer_id = ?');
        $stmt->bind_param('i', $cid);
        $stmt->execute();
        $_cart_count = (int) $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        $base_title = 'Virginia Market Square';
        $full_title = isset($page_title) && $page_title !== ''
            ? $page_title . ' - ' . $base_title
            : $base_title;
        echo htmlspecialchars($full_title, ENT_QUOTES, 'UTF-8');
        ?>
    </title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= $base_url ?>/css/style.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="<?= $base_url ?>/index.php">
            🌱 Virginia Market Square
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">

            <!-- ── Public navigation links ─────────────────────────────────── -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('index.php') ?>" href="<?= $base_url ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active(['vendors.php', 'vendor-detail.php']) ?>" href="<?= $base_url ?>/vendors.php">Vendors</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active(['products.php', 'product-detail.php']) ?>" href="<?= $base_url ?>/products.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('events.php') ?>" href="<?= $base_url ?>/events.php">Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('about.php') ?>" href="<?= $base_url ?>/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= nav_active('contact.php') ?>" href="<?= $base_url ?>/contact.php">Contact</a>
                </li>

                <?php if (is_logged_in()): ?>
                    <?php
                    // Determine the correct dashboard link based on user role
                    $user_type = get_current_user_type();
                    $dashboard_url = $base_url . '/index.php'; // fallback
                    $dashboard_label = 'Dashboard';

                    if ($user_type === 'admin') {
                        $dashboard_url = $base_url . '/admin/dashboard.php';
                        $dashboard_label = 'Admin';
                    } elseif ($user_type === 'vendor') {
                        $dashboard_url = $base_url . '/vendor-portal/dashboard.php';
                        $dashboard_label = 'Dashboard';
                    } elseif ($user_type === 'customer') {
                        $dashboard_url = $base_url . '/customer/dashboard.php';
                        $dashboard_label = 'My Account';
                    }
                    ?>

                    <!-- ── Separator between public and auth links ─────────── -->
                    <li class="nav-item d-none d-lg-flex align-items-center mx-1">
                        <span class="nav-separator"></span>
                    </li>

                    <?php if ($user_type === 'customer'): ?>
                        <!-- Cart link with item count badge (customers only) -->
                        <li class="nav-item">
                            <a class="nav-link<?= nav_active(['cart.php']) ?>"
                               href="<?= $base_url ?>/customer/cart.php">
                                🛒 Cart
                                <?php if ($_cart_count > 0): ?>
                                    <span class="badge rounded-pill bg-warning text-dark cart-badge">
                                        <?= $_cart_count ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Dashboard link (role-specific) -->
                    <li class="nav-item">
                        <a class="nav-link<?= nav_active('dashboard.php') ?>" href="<?= $dashboard_url ?>">
                            <?= htmlspecialchars($dashboard_label, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>

                    <!-- User dropdown with name and logout -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <?= htmlspecialchars($_SESSION['full_name'] ?? 'Account', ENT_QUOTES, 'UTF-8') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text text-muted small">
                                Signed in as <?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= $base_url ?>/logout.php">Log Out</a></li>
                        </ul>
                    </li>

                <?php else: ?>
                    <!-- ── Separator before login/register ─────────────────── -->
                    <li class="nav-item d-none d-lg-flex align-items-center mx-1">
                        <span class="nav-separator"></span>
                    </li>

                    <!-- Not logged in — show login and register links -->
                    <li class="nav-item">
                        <a class="nav-link<?= nav_active('login.php') ?>" href="<?= $base_url ?>/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= nav_active('register.php') ?>" href="<?= $base_url ?>/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php
// Display flash messages (set by set_flash() in auth.php)
$flash = get_flash();
if ($flash):
    $alert_class = $flash['type'] === 'error' ? 'danger' : $flash['type'];
?>
<div class="container mt-3">
    <div class="alert alert-<?= htmlspecialchars($alert_class, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>
<?php endif; ?>

<!-- Main Content Container -->
<main class="py-4">
    <div class="container">
