<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error   = '';
$success = '';
$type    = $_POST['user_type'] ?? $_GET['type'] ?? 'customer';
if (!in_array($type, ['customer', 'vendor'], true)) {
    $type = 'customer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $user_type  = $_POST['user_type'] ?? 'customer';
        $extra_data = [];

        if ($user_type === 'vendor') {
            $extra_data['vendor_name']     = $_POST['vendor_name']     ?? '';
            $extra_data['business_email']  = $_POST['business_email']  ?? '';
            $extra_data['phone']           = $_POST['phone']           ?? '';
            $extra_data['description']     = $_POST['description']     ?? '';
        } else {
            $extra_data['phone'] = $_POST['phone'] ?? '';
        }

        $result = register_user(
            trim($_POST['email']     ?? ''),
            $_POST['password']       ?? '',
            trim($_POST['full_name'] ?? ''),
            $user_type,
            $extra_data
        );

        if ($result['success']) {
            if ($user_type === 'vendor') {
                set_flash('success', 'Your vendor account has been created and is pending admin approval. You can log in once approved.');
            } else {
                // Auto-login customer after registration
                login_user(trim($_POST['email']), $_POST['password']);
                set_flash('success', 'Welcome! Your account has been created.');
                redirect('index.php');
            }
            redirect('login.php');
        } else {
            $error = $result['error'];
        }
    }
}

$page_title = 'Register';
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title mb-1 text-center">Create an Account</h2>
                <p class="text-center text-muted mb-4">Join the Virginia Market Square community</p>

                <!-- Account Type Tabs -->
                <ul class="nav nav-pills justify-content-center mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?= $type === 'customer' ? 'active' : '' ?>"
                           href="register.php?type=customer">Customer</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $type === 'vendor' ? 'active' : '' ?>"
                           href="register.php?type=vendor">Vendor</a>
                    </li>
                </ul>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if ($type === 'vendor'): ?>
                    <div class="alert alert-info">
                        Vendor accounts require admin approval before you can log in.
                        Alternatively, <a href="vendor-apply.php">submit an application</a> first.
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="user_type" value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone <span class="text-muted">(optional)</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Min 8 characters, one uppercase, one number, one special character.</div>
                    </div>

                    <?php if ($type === 'vendor'): ?>
                    <hr>
                    <h5 class="mb-3">Business Information</h5>

                    <div class="mb-3">
                        <label for="vendor_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="vendor_name" name="vendor_name"
                               value="<?= htmlspecialchars($_POST['vendor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="business_email" class="form-label">Business Email <span class="text-muted">(optional)</span></label>
                        <input type="email" class="form-control" id="business_email" name="business_email"
                               value="<?= htmlspecialchars($_POST['business_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Business Description <span class="text-muted">(optional)</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success btn-lg">
                            <?= $type === 'vendor' ? 'Create Vendor Account' : 'Create Account' ?>
                        </button>
                    </div>
                </form>

                <hr>
                <p class="text-center mb-0">Already have an account? <a href="login.php">Log in here</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
