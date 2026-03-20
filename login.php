<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    $type = get_current_user_type();
    if ($type === 'admin')    redirect('admin/dashboard.php');
    if ($type === 'vendor')   redirect('vendor/dashboard.php');
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $result = login_user(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? ''
        );

        if ($result['success']) {
            $redirect = $_GET['redirect'] ?? '';
            // Only allow relative redirects to prevent open redirect
            if ($redirect && str_starts_with($redirect, '/')) {
                redirect($redirect);
            }
            $type = get_current_user_type();
            if ($type === 'admin')    redirect('admin/dashboard.php');
            if ($type === 'vendor')   redirect('vendor-portal/dashboard.php');
            if ($type === 'customer') redirect('customer/dashboard.php');
            redirect('index.php');
        } else {
            $error = $result['error'];
        }
    }
}

$page_title = 'Login';
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title mb-4 text-center">Log In</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-success btn-lg">Log In</button>
                    </div>
                </form>

                <hr>
                <p class="text-center mb-1">Don't have an account? <a href="register.php">Register here</a></p>
                <p class="text-center mb-0">Interested in selling? <a href="vendor-apply.php">Apply as a Vendor</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
