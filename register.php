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
        redirect('register.php');
    }

    $full_name        = trim($_POST['full_name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone            = trim($_POST['phone'] ?? '');

    if ($password !== $confirm_password) {
        set_flash('error', 'Passwords do not match.');
        redirect('register.php');
    }

    $result = register_user($email, $password, $full_name, 'customer', ['phone' => $phone]);

    if ($result['success']) {
        // Auto-login immediately after registration
        $login = login_user($email, $password);
        if ($login['success']) {
            set_flash('success', 'Welcome to Virginia Market Square, ' . htmlspecialchars($full_name) . '!');
            redirect('customer/dashboard.php');
        } else {
            set_flash('success', 'Account created! Please sign in.');
            redirect('login.php');
        }
    } else {
        set_flash('error', $result['error']);
        redirect('register.php');
    }
}

$page_title = 'Create Account';
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
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm card-top-accent-green mt-4">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-1" style="color: var(--primary-color);">
                    Create Account
                </h2>
                <p class="text-center text-muted mb-4">Customer registration — shop local!</p>

                <form method="POST" action="register.php" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars(generate_csrf_token()) ?>">

                    <div class="mb-3">
                        <label for="full_name" class="form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               required autocomplete="name" autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">
                            Email Address <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               required autocomplete="email">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="password"
                               name="password" required autocomplete="new-password">
                        <div class="form-text">
                            Min 8 characters — must include an uppercase letter, a number, and a special character.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            Confirm Password <span class="text-danger">*</span>
                        </label>
                        <input type="password" class="form-control" id="confirm_password"
                               name="confirm_password" required autocomplete="new-password">
                    </div>

                    <div class="mb-4">
                        <label for="phone" class="form-label">
                            Phone <span class="text-muted">(optional)</span>
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               autocomplete="tel" placeholder="(218) 555-0100">
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-lg"
                                style="background-color: var(--primary-color); border-color: var(--primary-color); color: #fff;">
                            Create Account
                        </button>
                    </div>
                </form>

                <hr>
                <p class="text-center mb-2">
                    Already have an account?
                    <a href="login.php" style="color: var(--secondary-color);">Sign in here</a>
                </p>
                <p class="text-center mb-0">
                    Want to sell at the market?
                    <a href="vendor-apply.php" style="color: var(--secondary-color);">Apply as a vendor</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php';
