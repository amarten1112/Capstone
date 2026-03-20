<?php
/**
 * header.php - Navigation Header (Included on every page)
 *
 * This file contains the HTML header, navigation menu, and opening of the main container.
 * Include this at the top of the page body on every public page.
 *
 * Requires: config.php (provides $base_url) and auth.php (provides session functions)
 *           must be included BEFORE this file.
 *
 * Usage: include 'includes/header.php';
 */
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
    <link href="<?= $base_url ?>/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="<?= $base_url ?>/index.php">
            🌱 Virginia Market Square
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>/vendors.php">Vendors</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>/products.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>/events.php">Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>/contact.php">Contact</a>
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

                    <!-- Dashboard link (role-specific) -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $dashboard_url ?>">
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
                    <!-- Not logged in — show login and register links -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>/register.php">Register</a>
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
