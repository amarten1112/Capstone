<?php
/**
 * customer/cart-update.php — Cart Update Handler
 * Virginia Market Square
 *
 * Phase 4, Task 4.8 (original — form POST with redirect)
 * Phase 7, Task 7.2 (AJAX — returns JSON when X-Requested-With header present)
 *
 * POST-only endpoint. Handles two actions from customer/cart.php:
 *   action=update  — change quantity for a cart item (+/- buttons)
 *   action=remove  — delete a cart item (Remove button)
 *
 * AJAX detection:
 *   If the request includes X-Requested-With: XMLHttpRequest (set by fetch),
 *   the response is JSON with updated cart state. Otherwise, the original
 *   redirect-based flow runs as a non-JS fallback.
 *
 * Security:
 *   - Requires logged-in customer
 *   - Validates CSRF token
 *   - Verifies the cart_id belongs to the current customer
 *   - Caps quantity at stock_quantity
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// ─── Helper: detect AJAX request ────────────────────────────────────────────
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// ─── Helper: send JSON response and exit ────────────────────────────────────
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ─── Helper: recalculate full cart totals for the sidebar ────────────────────
// Called after update/remove so the AJAX response includes fresh totals.
function get_cart_totals($conn, $customer_id) {
    $stmt = $conn->prepare(
        "SELECT COALESCE(SUM(p.price * c.quantity), 0) AS subtotal,
                COALESCE(SUM(c.quantity), 0) AS item_count
         FROM cart c
         JOIN products p ON c.product_id = p.product_id AND p.is_available = 1
         JOIN vendors v  ON p.vendor_id  = v.vendor_id  AND v.verified = 1
         WHERE c.customer_id = ? AND p.stock_quantity > 0"
    );
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'subtotal'   => (float) $row['subtotal'],
        'item_count' => (int) $row['item_count'],
    ];
}

// Only logged-in customers can modify the cart
if ($is_ajax && !isset($_SESSION['user_id'])) {
    json_response(['success' => false, 'message' => 'Please log in.'], 401);
}
require_customer();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) json_response(['success' => false, 'message' => 'Invalid request.'], 405);
    redirect($base_url . '/customer/cart.php');
}

// Validate CSRF token
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    if ($is_ajax) json_response(['success' => false, 'message' => 'Session expired. Please reload the page.'], 403);
    set_flash('error', 'Invalid form submission. Please try again.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Collect inputs ─────────────────────────────────────────────────────────
$cart_id  = isset($_POST['cart_id']) ? (int) $_POST['cart_id'] : 0;
$action   = trim($_POST['action'] ?? '');

if ($cart_id <= 0 || !in_array($action, ['update', 'remove'], true)) {
    if ($is_ajax) json_response(['success' => false, 'message' => 'Invalid request.'], 400);
    set_flash('error', 'Invalid request.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Get customer_id and verify ownership ───────────────────────────────────
$customer_id = get_customer_id();

if (!$customer_id) {
    if ($is_ajax) json_response(['success' => false, 'message' => 'Customer profile not found.'], 400);
    set_flash('error', 'Customer profile not found.');
    redirect($base_url . '/customer/cart.php');
}

// Verify this cart row belongs to the current customer
$stmt = $conn->prepare(
    "SELECT c.cart_id, c.quantity,
            p.product_id, p.product_name, p.stock_quantity, p.price
     FROM cart c
     JOIN products p ON c.product_id = p.product_id
     WHERE c.cart_id = ? AND c.customer_id = ?"
);
$stmt->bind_param('ii', $cart_id, $customer_id);
$stmt->execute();
$cart_item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cart_item) {
    if ($is_ajax) json_response(['success' => false, 'message' => 'Cart item not found.'], 404);
    set_flash('error', 'Cart item not found.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Handle REMOVE action ───────────────────────────────────────────────────
if ($action === 'remove') {
    $stmt = $conn->prepare('DELETE FROM cart WHERE cart_id = ? AND customer_id = ?');
    $stmt->bind_param('ii', $cart_id, $customer_id);
    $removed = $stmt->execute();
    $stmt->close();

    if ($is_ajax) {
        $totals = get_cart_totals($conn, $customer_id);
        json_response([
            'success'    => $removed,
            'action'     => 'remove',
            'cart_id'    => $cart_id,
            'message'    => $removed
                ? htmlspecialchars($cart_item['product_name'], ENT_QUOTES, 'UTF-8') . ' removed from your cart.'
                : 'Could not remove item.',
            'subtotal'   => $totals['subtotal'],
            'item_count' => $totals['item_count'],
        ]);
    }

    if ($removed) {
        set_flash('success',
            htmlspecialchars($cart_item['product_name'], ENT_QUOTES, 'UTF-8')
            . ' removed from your cart.'
        );
    } else {
        set_flash('error', 'Could not remove item. Please try again.');
    }
    redirect($base_url . '/customer/cart.php');
}

// ─── Handle UPDATE action ───────────────────────────────────────────────────
$new_quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;

if ($new_quantity < 1) {
    $new_quantity = 1;
}

// Cap at available stock
$capped = false;
if ($new_quantity > $cart_item['stock_quantity']) {
    $new_quantity = $cart_item['stock_quantity'];
    $capped = true;
}

// Update the cart row
$stmt = $conn->prepare(
    'UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?'
);
$stmt->bind_param('iii', $new_quantity, $cart_id, $customer_id);
$updated = $stmt->execute();
$stmt->close();

if ($is_ajax) {
    $totals    = get_cart_totals($conn, $customer_id);
    $line_total = (float) $cart_item['price'] * $new_quantity;

    json_response([
        'success'    => $updated,
        'action'     => 'update',
        'cart_id'    => $cart_id,
        'quantity'   => $new_quantity,
        'line_total' => $line_total,
        'stock'      => (int) $cart_item['stock_quantity'],
        'message'    => $capped
            ? 'Quantity adjusted to ' . $new_quantity . ' (max available).'
            : 'Cart updated.',
        'capped'     => $capped,
        'subtotal'   => $totals['subtotal'],
        'item_count' => $totals['item_count'],
    ]);
}

// Non-AJAX fallback
if ($updated) {
    if ($capped) {
        set_flash('warning',
            'Quantity adjusted to ' . $new_quantity
            . ' (maximum available for '
            . htmlspecialchars($cart_item['product_name'], ENT_QUOTES, 'UTF-8')
            . ').'
        );
    } elseif (!has_flash()) {
        set_flash('success', 'Cart updated.');
    }
} else {
    set_flash('error', 'Could not update cart. Please try again.');
}

redirect($base_url . '/customer/cart.php');