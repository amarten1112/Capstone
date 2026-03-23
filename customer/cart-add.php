<?php
/**
 * customer/cart-add.php — Add to Cart Handler
 * Virginia Market Square
 *
 * Phase 4, Task 4.6
 *
 * POST-only endpoint. Receives product_id and quantity from the
 * Add to Cart form on product-detail.php (and later from product cards).
 *
 * Logic:
 *   1. Verify customer is logged in (require_customer)
 *   2. Validate CSRF token
 *   3. Validate product_id exists, is available, and has stock
 *   4. Check if product is already in cart:
 *      - Yes → UPDATE quantity (add to existing)
 *      - No  → INSERT new cart row
 *   5. Cap quantity at stock_quantity (can't add more than available)
 *   6. Redirect back to product detail page with success/error flash
 *
 * The cart table has a UNIQUE KEY on (customer_id, product_id) which
 * prevents duplicate rows. We use INSERT ... ON DUPLICATE KEY UPDATE
 * to handle both insert and update in a single query.
 */

// Subdirectory — use ../ to reach includes
require_once '../includes/config.php';
require_once '../includes/auth.php';

// ─── Only logged-in customers can add to cart ───────────────────────────────
require_customer();

// ─── Only accept POST requests ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($base_url . '/products.php');
}

// ─── Validate CSRF token ────────────────────────────────────────────────────
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid form submission. Please try again.');
    redirect($base_url . '/products.php');
}

// ─── Collect and validate inputs ────────────────────────────────────────────
$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$quantity   = isset($_POST['quantity'])   ? (int) $_POST['quantity']   : 1;

if ($product_id <= 0) {
    set_flash('error', 'Invalid product.');
    redirect($base_url . '/products.php');
}

// Quantity must be at least 1
if ($quantity < 1) {
    $quantity = 1;
}

// ─── Get customer_id from session ───────────────────────────────────────────
$customer_id = get_customer_id();

if (!$customer_id) {
    set_flash('error', 'Customer profile not found. Please contact support.');
    redirect($base_url . '/products.php');
}

// ─── Verify product exists, is available, and has stock ─────────────────────
$stmt = $conn->prepare(
    "SELECT p.product_id, p.product_name, p.stock_quantity, p.price
     FROM products p
     JOIN vendors v ON p.vendor_id = v.vendor_id AND v.verified = 1
     WHERE p.product_id = ?
       AND p.is_available = 1
       AND p.stock_quantity > 0"
);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    set_flash('error', 'This product is no longer available.');
    redirect($base_url . '/products.php');
}

// ─── Check current cart quantity for this product ───────────────────────────
// If the customer already has this product in their cart, we need to know
// the existing quantity so we don't exceed stock when adding more.
$stmt = $conn->prepare(
    'SELECT quantity FROM cart WHERE customer_id = ? AND product_id = ?'
);
$stmt->bind_param('ii', $customer_id, $product_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

$existing_qty = $existing ? (int) $existing['quantity'] : 0;
$new_total    = $existing_qty + $quantity;

// Cap at available stock
if ($new_total > $product['stock_quantity']) {
    $new_total = $product['stock_quantity'];

    // If they already have the max in cart, let them know
    if ($existing_qty >= $product['stock_quantity']) {
        set_flash('warning', 'You already have the maximum available quantity of '
            . htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8')
            . ' in your cart (' . $product['stock_quantity'] . ').');
        redirect($base_url . '/product-detail.php?id=' . $product_id);
    }
}

// ─── Insert or update cart row ──────────────────────────────────────────────
// INSERT ... ON DUPLICATE KEY UPDATE handles both cases in one query:
//   - New item → INSERT with the requested quantity
//   - Existing item → UPDATE to the new total (existing + added, capped at stock)
// The UNIQUE KEY uk_cart_item (customer_id, product_id) triggers the ON DUPLICATE path.
$stmt = $conn->prepare(
    'INSERT INTO cart (customer_id, product_id, quantity, added_date)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE quantity = ?, added_date = NOW()'
);
$stmt->bind_param('iiii', $customer_id, $product_id, $new_total, $new_total);

if ($stmt->execute()) {
    $action = $existing ? 'updated in' : 'added to';
    set_flash('success',
        htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8')
        . ' ' . $action . ' your cart (' . $new_total . ').'
    );
} else {
    set_flash('error', 'Could not add item to cart. Please try again.');
}
$stmt->close();

// ─── Redirect back to the product detail page ───────────────────────────────
redirect($base_url . '/product-detail.php?id=' . $product_id);
