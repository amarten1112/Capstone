<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_customer();

$page_title = 'My Account';
include '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-1">My Account</h1>
    <p class="text-muted mb-4">Welcome, <?= htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8') ?></p>

    <div class="alert alert-info">
        Customer dashboard coming in a future phase. You are logged in successfully.
    </div>

    <a href="../logout.php" class="btn btn-outline-secondary">Log Out</a>
</div>

<?php include '../includes/footer.php'; ?>
