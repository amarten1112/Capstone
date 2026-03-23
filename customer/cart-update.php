<?php
/**
 * customer/cart-update.php — Cart Update Handler
 * Virginia Market Square
 *
 * Phase 4, Task 4.8
 *
 * POST-only endpoint. Handles two actions from customer/cart.php:
 *   action=update  — change quantity for a cart item (+/- buttons)
 *   action=remove  — delete a cart item (Remove button)
 *
 * Security:
 *   - Requires logged-in customer
 *   - Validates CSRF token
 *   - Verifies the cart_id belongs to the current customer (prevents
 *     a customer from modifying another customer's cart by changing
 *     the hidden cart_id value)
 *   - Caps quantity at stock_quantity
 *
 * Always redirects back to cart.php after processing.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Only logged-in customers can modify the cart
require_customer();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($base_url . '/customer/cart.php');
}

// Validate CSRF token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid form submission. Please try again.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Collect inputs ─────────────────────────────────────────────────────────
$cart_id  = isset($_POST['cart_id']) ? (int) $_POST['cart_id'] : 0;
$action   = trim($_POST['action'] ?? '');

if ($cart_id <= 0 || !in_array($action, ['update', 'remove'], true)) {
    set_flash('error', 'Invalid request.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Get customer_id and verify ownership ───────────────────────────────────
$customer_id = get_customer_id();

if (!$customer_id) {
    set_flash('error', 'Customer profile not found.');
    redirect($base_url . '/customer/cart.php');
}

// Verify this cart row belongs to the current customer
// Also fetch product info for stock validation and flash messages
$stmt = $conn->prepare(
    "SELECT c.cart_id, c.quantity,
            p.product_id, p.product_name, p.stock_quantity
     FROM cart c
     JOIN products p ON c.product_id = p.product_id
     WHERE c.cart_id = ? AND c.customer_id = ?"
);
$stmt->bind_param('ii', $cart_id, $customer_id);
$stmt->execute();
$cart_item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cart_item) {
    set_flash('error', 'Cart item not found.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Handle REMOVE action ───────────────────────────────────────────────────
if ($action === 'remove') {
    $stmt = $conn->prepare('DELETE FROM cart WHERE cart_id = ? AND customer_id = ?');
    $stmt->bind_param('ii', $cart_id, $customer_id);

    if ($stmt->execute()) {
        set_flash('success',
            htmlspecialchars($cart_item['product_name'], ENT_QUOTES, 'UTF-8')
            . ' removed from your cart.'
        );
    } else {
        set_flash('error', 'Could not remove item. Please try again.');
    }
    $stmt->close();
    redirect($base_url . '/customer/cart.php');
}

// ─── Handle UPDATE action ───────────────────────────────────────────────────
$new_quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;

// Quantity must be at least 1 — if they want 0, they should use Remove
if ($new_quantity < 1) {
    $new_quantity = 1;
}

// Cap at available stock
if ($new_quantity > $cart_item['stock_quantity']) {
    $new_quantity = $cart_item['stock_quantity'];
    set_flash('warning',
        'Quantity adjusted to ' . $new_quantity
        . ' (maximum available for '
        . htmlspecialchars($cart_item['product_name'], ENT_QUOTES, 'UTF-8')
        . ').'
    );
}

// Update the cart row
$stmt = $conn->prepare(
    'UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?'
);
$stmt->bind_param('iii', $new_quantity, $cart_id, $customer_id);

if ($stmt->execute()) {
    // Only show success flash if we didn't already show a warning about stock
    if (!has_flash()) {
        set_flash('success', 'Cart updated.');
    }
} else {
    set_flash('error', 'Could not update cart. Please try again.');
}
$stmt->close();

redirect($base_url . '/customer/cart.php');
