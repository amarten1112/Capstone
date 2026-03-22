<?php
/**
 * contact.php — Contact Form Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.5
 *
 * Changes from Phase 2/3 version:
 *   - Auto-fills name and email for logged-in users (any role)
 *   - Auto-fills phone for logged-in customers (from customers table)
 *   - Shows "Signed in as..." note when auto-filled
 *   - No functional changes to form processing or validation
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Contact';
$success    = false;
$error      = '';

// ─── Pre-fill fields for logged-in users ────────────────────────────────────
// Saves customers from retyping info they already gave us at registration.
$prefill_name  = '';
$prefill_email = '';
$prefill_phone = '';

if (is_logged_in()) {
    $prefill_name  = $_SESSION['full_name'] ?? '';
    $prefill_email = $_SESSION['email']     ?? '';

    // For customers, also grab their phone from the customers table
    if (get_current_user_type() === 'customer') {
        $uid  = (int) $_SESSION['user_id'];
        $stmt = $conn->prepare('SELECT phone FROM customers WHERE user_id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $cust = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $prefill_phone = $cust['phone'] ?? '';
    }
}

// Optional: pre-select a vendor from query string
$vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;
$vendor    = null;
if ($vendor_id > 0) {
    $stmt = $conn->prepare('SELECT vendor_id, vendor_name FROM vendors WHERE vendor_id = ? AND verified = 1');
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ─── Handle form submission ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $name    = trim($_POST['name']    ?? '');
        $email   = trim($_POST['email']   ?? '');
        $phone   = trim($_POST['phone']   ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $vid     = isset($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : null;
        if ($vid === 0) $vid = null;

        if (!$name || !$email || !$message) {
            $error = 'Please fill in your name, email, and message.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO contacts (name, email, phone, subject, message, vendor_id) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssssi', $name, $email, $phone, $subject, $message, $vid);
            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = 'Submission failed. Please try again.';
            }
            $stmt->close();
        }
    }
}

// Get verified vendors for the dropdown
$vendor_list = $conn->query('SELECT vendor_id, vendor_name FROM vendors WHERE verified = 1 ORDER BY vendor_name ASC');

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <h1 class="mb-1">Contact Us</h1>
        <p class="text-muted mb-4">Questions about the market, a vendor, or anything else? We'd love to hear from you.</p>

        <?php if ($success): ?>
            <div class="alert alert-success text-center py-4">
                <h4>Message Sent!</h4>
                <p>Thank you for reaching out. We'll get back to you as soon as possible.</p>
                <a href="index.php" class="btn btn-success">Back to Home</a>
            </div>
        <?php else: ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if ($vendor): ?>
                <div class="alert alert-info">
                    Contacting: <strong><?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>

            <?php if (is_logged_in()): ?>
                <div class="alert alert-light border small mb-3">
                    Signed in as <strong><?= htmlspecialchars($_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    — your name and email have been filled in automatically.
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="contact.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name"
                                       value="<?= htmlspecialchars($_POST['name'] ?? $prefill_name, ENT_QUOTES, 'UTF-8') ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email"
                                       value="<?= htmlspecialchars($_POST['email'] ?? $prefill_email, ENT_QUOTES, 'UTF-8') ?>"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
                                <input type="tel" class="form-control" name="phone"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? $prefill_phone, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Regarding a Vendor? <span class="text-muted">(optional)</span></label>
                                <select class="form-select" name="vendor_id">
                                    <option value="0">General inquiry</option>
                                    <?php if ($vendor_list): while ($v = $vendor_list->fetch_assoc()):
                                        $selected_vid = $_POST['vendor_id'] ?? $vendor_id;
                                    ?>
                                        <option value="<?= (int) $v['vendor_id'] ?>"
                                            <?= (int) $selected_vid === (int) $v['vendor_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($v['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" name="subject"
                                       value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="message" rows="5" required><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success btn-lg">Send Message</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Market contact info -->
            <div class="row mt-4 g-3">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5>📧 Email</h5>
                            <a href="mailto:virginiamarketsquare@gmail.com">virginiamarketsquare@gmail.com</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5>📍 Market Location</h5>
                            111 South 9th Avenue W<br>Virginia, MN 55792
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
