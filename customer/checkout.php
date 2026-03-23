<?php
/**
 * customer/checkout.php — Checkout Step 1: Shipping Address
 * Virginia Market Square
 *
 * Phase 4, Task 4.9
 *
 * Step 1 of 2-step checkout:
 *   1. Shipping address (this page) → stores in $_SESSION['checkout']
 *   2. Review & place order (checkout-review.php)
 *
 * Pre-fills shipping fields from the customer's profile (customers table).
 * On submit, validates required fields, stores them in session, and
 * redirects to checkout-review.php.
 *
 * If the cart is empty, redirects back to cart.php.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_customer();

$page_title = 'Checkout — Shipping';

$customer_id = get_customer_id();

if (!$customer_id) {
    set_flash('error', 'Customer profile not found.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Verify cart is not empty ───────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt
     FROM cart c
     JOIN products p ON c.product_id = p.product_id AND p.is_available = 1
     JOIN vendors v  ON p.vendor_id  = v.vendor_id  AND v.verified = 1
     WHERE c.customer_id = ? AND p.stock_quantity > 0"
);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$cart_count = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

if ($cart_count === 0) {
    set_flash('warning', 'Your cart is empty. Add some products before checking out.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Fetch customer profile for pre-fill ────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT cust.phone, cust.address_line1, cust.address_line2,
            cust.city, cust.state, cust.zip,
            u.full_name, u.email
     FROM customers cust
     JOIN users u ON cust.user_id = u.user_id
     WHERE cust.customer_id = ?"
);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─── Handle form submission ─────────────────────────────────────────────────
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        // Collect shipping fields
        $ship_name     = trim($_POST['ship_name']     ?? '');
        $ship_address1 = trim($_POST['ship_address1'] ?? '');
        $ship_address2 = trim($_POST['ship_address2'] ?? '');
        $ship_city     = trim($_POST['ship_city']     ?? '');
        $ship_state    = trim($_POST['ship_state']    ?? '');
        $ship_zip      = trim($_POST['ship_zip']      ?? '');
        $notes         = trim($_POST['notes']         ?? '');

        // Validate required fields
        if ($ship_name === '' || $ship_address1 === '' || $ship_city === ''
            || $ship_state === '' || $ship_zip === '') {
            $error = 'Please fill in all required shipping fields.';
        } else {
            // Store shipping data in session for step 2
            $_SESSION['checkout'] = [
                'ship_name'     => $ship_name,
                'ship_address1' => $ship_address1,
                'ship_address2' => $ship_address2,
                'ship_city'     => $ship_city,
                'ship_state'    => $ship_state,
                'ship_zip'      => $ship_zip,
                'notes'         => $notes,
            ];

            redirect($base_url . '/customer/checkout-review.php');
        }
    }
}

// ─── Determine pre-fill values ──────────────────────────────────────────────
// Priority: POST values (if validation failed) > session (if going back from
// review) > profile data > empty string
$checkout = $_SESSION['checkout'] ?? [];

$val = function(string $field) use ($profile, $checkout) {
    // POST value takes priority (form resubmission after error)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$field])) {
        return $_POST[$field];
    }
    // Session value (returning from review page)
    if (isset($checkout[$field])) {
        return $checkout[$field];
    }
    // Map form field names to profile column names
    $profile_map = [
        'ship_name'     => 'full_name',
        'ship_address1' => 'address_line1',
        'ship_address2' => 'address_line2',
        'ship_city'     => 'city',
        'ship_state'    => 'state',
        'ship_zip'      => 'zip',
        'notes'         => null,
    ];
    $col = $profile_map[$field] ?? null;
    return ($col && isset($profile[$col])) ? $profile[$col] : '';
};

include '../includes/header.php';
?>

<!-- Checkout progress indicator -->
<div class="mb-4">
    <div class="d-flex justify-content-center gap-3">
        <span class="badge bg-success px-3 py-2">1. Shipping</span>
        <span class="badge bg-secondary px-3 py-2">2. Review & Pay</span>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <h2 class="mb-4">Shipping Address</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="checkout.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="row g-3">
                        <!-- Full name -->
                        <div class="col-12">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ship_name"
                                   value="<?= htmlspecialchars($val('ship_name'), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <!-- Address line 1 -->
                        <div class="col-12">
                            <label class="form-label">Street Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ship_address1"
                                   placeholder="123 Main St"
                                   value="<?= htmlspecialchars($val('ship_address1'), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <!-- Address line 2 -->
                        <div class="col-12">
                            <label class="form-label">Apt, Suite, Unit <span class="text-muted">(optional)</span></label>
                            <input type="text" class="form-control" name="ship_address2"
                                   placeholder="Apt 4B"
                                   value="<?= htmlspecialchars($val('ship_address2'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <!-- City -->
                        <div class="col-md-5">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ship_city"
                                   value="<?= htmlspecialchars($val('ship_city'), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <!-- State -->
                        <div class="col-md-4">
                            <label class="form-label">State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ship_state"
                                   placeholder="MN"
                                   maxlength="50"
                                   value="<?= htmlspecialchars($val('ship_state'), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <!-- ZIP -->
                        <div class="col-md-3">
                            <label class="form-label">ZIP Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="ship_zip"
                                   placeholder="55792"
                                   maxlength="10"
                                   value="<?= htmlspecialchars($val('ship_zip'), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>

                        <!-- Order notes -->
                        <div class="col-12">
                            <label class="form-label">Order Notes <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control" name="notes" rows="3"
                                      placeholder="Special delivery instructions, allergies, etc."
                                      maxlength="500"><?= htmlspecialchars($val('notes'), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?= $base_url ?>/customer/cart.php" class="btn btn-outline-secondary">
                            &larr; Back to Cart
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            Continue to Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
