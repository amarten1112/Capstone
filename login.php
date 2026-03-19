<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Already logged in — send to role dashboard
if (is_logged_in()) {
    $type = get_current_user_type();
    if ($type === 'admin')    redirect('admin/dashboard.php');
    if ($type === 'vendor')   redirect('vendor-portal/dashboard.php');
    if ($type === 'customer') redirect('customer/dashboard.php');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid form submission. Please try again.');
        redirect('login.php');
    }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $result   = login_user($email, $password);

    if ($result['success']) {
        set_flash('success', 'Welcome back!');

        // Honor ?redirect= param — whitelist to prevent open redirect
        $redirect_to      = $_POST['redirect'] ?? '';
        $allowed_prefixes = ['admin/', 'vendor/', 'customer/', 'index.php', 'products.php', 'vendors.php', 'events.php'];
        $safe             = false;
        foreach ($allowed_prefixes as $prefix) {
            if (strncmp(ltrim($redirect_to, '/'), $prefix, strlen($prefix)) === 0) {
                $safe = true;
                break;
            }
        }
        if ($safe && $redirect_to !== '') {
            redirect($redirect_to);
        }

        $type = get_current_user_type();
        if ($type === 'admin')    redirect('admin/dashboard.php');
        if ($type === 'vendor')   redirect('vendor-portal/dashboard.php');
        redirect('customer/dashboard.php');
    } else {
        set_flash('error', $result['error']);
        redirect('login.php');
    }
}

$page_title = 'Sign In';
include 'includes/header.php';

$flash = get_flash();
if ($flash) {
    $cls = $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type']);
    echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($flash['message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}
?>

<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm card-top-accent-green mt-4">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-1" style="color: var(--primary-color);">
                    🌱 Sign In
                </h2>
                <p class="text-center text-muted mb-4">Virginia Market Square</p>

                <form method="POST" action="login.php" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                    <input type="hidden" name="redirect"
                           value="<?= htmlspecialchars($_GET['redirect'] ?? '') ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required autocomplete="email" autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password"
                               name="password" required autocomplete="current-password">
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-lg"
                                style="background-color: var(--primary-color); border-color: var(--primary-color); color: #fff;">
                            Sign In
                        </button>
                    </div>
                </form>

                <hr>
                <p class="text-center mb-0">
                    Don't have an account?
                    <a href="register.php" style="color: var(--secondary-color);">Register here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php';
