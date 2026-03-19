<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Become a Vendor';
$success    = false;
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $name          = trim($_POST['applicant_name']      ?? '');
        $email         = trim($_POST['applicant_email']     ?? '');
        $phone         = trim($_POST['applicant_phone']     ?? '');
        $biz_name      = trim($_POST['business_name']       ?? '');
        $biz_desc      = trim($_POST['business_description']?? '');
        $biz_category  = trim($_POST['business_category']   ?? '');
        $miles         = $_POST['miles_from_virginia']      ?? '';

        if (!$name || !$email || !$biz_name) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $miles_val = ($miles !== '') ? (float)$miles : null;

            $stmt = $conn->prepare(
                'INSERT INTO vendor_applications
                 (applicant_name, applicant_email, applicant_phone, business_name, business_description, business_category, miles_from_virginia)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssssssd', $name, $email, $phone, $biz_name, $biz_desc, $biz_category, $miles_val);

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Submission failed. Please try again.';
            }
            $stmt->close();
        }
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">

        <div class="text-center mb-5">
            <h1>Become a Vendor</h1>
            <p class="lead">Join Virginia Market Square and connect with local shoppers who care about quality, sustainability, and community.</p>
        </div>

        <!-- Requirements -->
        <div class="row mb-5">
            <div class="col-md-4 mb-3">
                <div class="card h-100 text-center border-success">
                    <div class="card-body">
                        <h3>📍</h3>
                        <h6>Local</h6>
                        <p class="small text-muted">Within 50 miles of Virginia, MN</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100 text-center border-success">
                    <div class="card-body">
                        <h3>🌱</h3>
                        <h6>Sustainable</h6>
                        <p class="small text-muted">Eco-friendly and sustainable practices</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100 text-center border-success">
                    <div class="card-body">
                        <h3>🤝</h3>
                        <h6>Community</h6>
                        <p class="small text-muted">Committed to the local community</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success text-center py-4">
                <h4>Application Submitted!</h4>
                <p>Thank you for your interest. We'll review your application and be in touch at your email address.</p>
                <a href="index.php" class="btn btn-success">Back to Home</a>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h3 class="mb-4">Vendor Application</h3>
                    <form method="POST" action="vendor-apply.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <h5 class="mb-3">Your Information</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="applicant_name"
                                       value="<?= htmlspecialchars($_POST['applicant_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="applicant_email"
                                       value="<?= htmlspecialchars($_POST['applicant_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
                                <input type="tel" class="form-control" name="applicant_phone"
                                       value="<?= htmlspecialchars($_POST['applicant_phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <h5 class="mb-3">Business Information</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="business_name"
                                       value="<?= htmlspecialchars($_POST['business_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Business Category</label>
                                <select class="form-select" name="business_category">
                                    <option value="">Select a category...</option>
                                    <?php
                                    $cats = ['Produce', 'Baked Goods', 'Dairy & Eggs', 'Meat & Poultry', 'Honey & Preserves', 'Plants & Flowers', 'Crafts & Artisan', 'Other'];
                                    foreach ($cats as $cat):
                                        $sel = ($_POST['business_category'] ?? '') === $cat ? 'selected' : '';
                                    ?>
                                        <option value="<?= $cat ?>" <?= $sel ?>><?= $cat ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Miles from Virginia, MN <span class="text-muted">(approx.)</span></label>
                                <input type="number" class="form-control" name="miles_from_virginia" min="0" max="50" step="0.1"
                                       value="<?= htmlspecialchars($_POST['miles_from_virginia'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Tell us about your business</label>
                                <textarea class="form-control" name="business_description" rows="4"
                                          placeholder="What do you sell? How is it produced? Why do you want to join Virginia Market Square?"><?= htmlspecialchars($_POST['business_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success btn-lg">Submit Application</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
