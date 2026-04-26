<?php
/**
 * vendor-portal/order-action.php — Vendor Fulfillment Status Handler
 * Virginia Market Square
 *
 * POST-only. Advances a vendor's fulfillment status for one order.
 * After updating, recomputes the master orders.order_status as the
 * most-behind fulfillment across all vendors for that order.
 *
 * Allowed forward transitions:
 *   processing → shipped | delivered
 *   shipped    → delivered
 *   delivered  → (terminal, no further updates)
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_vendor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($base_url . '/vendor-portal/orders.php');
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid form submission. Please try again.');
    redirect($base_url . '/vendor-portal/orders.php');
}

$vendor_id      = get_vendor_id();
$order_id       = isset($_POST['order_id'])       ? (int) $_POST['order_id']       : 0;
$fulfillment_id = isset($_POST['fulfillment_id']) ? (int) $_POST['fulfillment_id'] : 0;
$new_status     = trim($_POST['new_status'] ?? '');
$redirect_filter = in_array($_POST['redirect_filter'] ?? '', ['all','processing','shipped','delivered'], true)
    ? $_POST['redirect_filter'] : 'all';

$redirect = $base_url . '/vendor-portal/orders.php?filter=' . $redirect_filter;

$allowed_statuses = ['shipped', 'delivered'];
if (!$vendor_id || $order_id <= 0 || $fulfillment_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
    set_flash('error', 'Invalid request.');
    redirect($redirect);
}

// Load fulfillment — verify it belongs to this vendor
$stmt = $conn->prepare(
    "SELECT f.fulfillment_id, f.order_id, f.vendor_id, f.status,
            o.order_status
     FROM order_fulfillments f
     JOIN orders o ON f.order_id = o.order_id
     WHERE f.fulfillment_id = ? AND f.vendor_id = ? AND f.order_id = ?"
);
$stmt->bind_param('iii', $fulfillment_id, $vendor_id, $order_id);
$stmt->execute();
$fulfillment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fulfillment) {
    set_flash('error', 'Order not found.');
    redirect($redirect);
}

// Block updates on cancelled / refunded orders
if (in_array($fulfillment['order_status'], ['cancelled', 'refunded'], true)) {
    set_flash('error', 'This order cannot be updated.');
    redirect($redirect);
}

// Enforce forward-only transitions
$valid_transitions = [
    'processing' => ['shipped', 'delivered'],
    'shipped'    => ['delivered'],
];

$allowed_next = $valid_transitions[$fulfillment['status']] ?? [];
if (!in_array($new_status, $allowed_next, true)) {
    set_flash('error', 'Invalid status transition.');
    redirect($redirect);
}

$user_id = $_SESSION['user_id'] ?? null;

$conn->begin_transaction();

try {
    // 1. Update this vendor's fulfillment row
    $stmt = $conn->prepare(
        "UPDATE order_fulfillments
         SET status = ?, updated_at = NOW(), updated_by = ?
         WHERE fulfillment_id = ?"
    );
    $stmt->bind_param('sii', $new_status, $user_id, $fulfillment_id);
    $stmt->execute();
    $stmt->close();

    // 2. Recompute the overall order status (most-behind fulfillment)
    //    Priority: processing=1, shipped=2, delivered=3
    //    The overall status = the lowest-priority fulfillment for this order.
    $r = $conn->query(
        "SELECT status FROM order_fulfillments WHERE order_id = $order_id"
    );
    $priority = ['processing' => 1, 'shipped' => 2, 'delivered' => 3];
    $min_p = PHP_INT_MAX;
    while ($row = $r->fetch_assoc()) {
        $p = $priority[$row['status']] ?? 1;
        if ($p < $min_p) {
            $min_p = $p;
        }
    }
    $overall = array_search($min_p, $priority) ?: 'processing';

    $stmt = $conn->prepare(
        "UPDATE orders SET order_status = ? WHERE order_id = ?
         AND order_status NOT IN ('cancelled','refunded')"
    );
    $stmt->bind_param('si', $overall, $order_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    $labels = ['shipped' => 'shipped', 'delivered' => 'delivered'];
    set_flash('success', 'Order #' . $order_id . ' marked as ' . ($labels[$new_status] ?? $new_status) . '.');

} catch (Throwable $e) {
    $conn->rollback();
    set_flash('error', 'Could not update order status. Please try again.');
}

redirect($redirect);
