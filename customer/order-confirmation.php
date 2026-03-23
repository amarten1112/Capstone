<?php
/**
 * customer/order-confirmation.php — Order Confirmation Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.10
 *
 * Displayed after a successful order placement. Shows order number,
 * items purchased, shipping address, and totals.
 *
 * Only accessible to the customer who placed the order (verified by
 * matching order.customer_id to the logged-in customer).
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_customer();

$page_title = 'Order Confirmation';

$customer_id = get_customer_id();
$order_id    = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if (!$customer_id || $order_id <= 0) {
    set_flash('error', 'Order not found.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Fetch order (verify it belongs to this customer) ───────────────────────
$stmt = $conn->prepare(
    "SELECT * FROM orders WHERE order_id = ? AND customer_id = ?"
);
$stmt->bind_param('ii', $order_id, $customer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Fetch order items with product and vendor names ────────────────────────
$stmt = $conn->prepare(
    "SELECT oi.quantity, oi.price_each, oi.line_total,
            p.product_name, p.image_url, p.unit,
            v.vendor_name
     FROM order_items oi
     JOIN products p ON oi.product_id = p.product_id
     JOIN vendors v  ON oi.vendor_id  = v.vendor_id
     WHERE oi.order_id = ?
     ORDER BY oi.item_id ASC"
);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order_items = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">

        <!-- Success banner -->
        <div class="text-center mb-4">
            <div class="text-success mb-2" style="font-size:3rem;">&#10003;</div>
            <h2>Thank You for Your Order!</h2>
            <p class="text-muted">
                Order <strong>#<?= (int) $order['order_id'] ?></strong>
                was placed on <?= date('F j, Y \a\t g:i A', strtotime($order['order_date'])) ?>
            </p>
            <span class="badge bg-warning text-dark fs-6">
                Status: <?= ucfirst(htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8')) ?>
            </span>
        </div>

        <!-- Order items -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Items Ordered</h5>
            </div>
            <div class="card-body p-0">
                <?php $idx = 0; while ($item = $order_items->fetch_assoc()): ?>
                    <div class="d-flex gap-3 p-3 <?= $idx > 0 ? 'border-top' : '' ?>">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                 style="width:60px;height:60px;object-fit:cover;"
                                 class="rounded"
                                 alt="<?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <strong><?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <br>
                            <small class="text-muted">
                                by <?= htmlspecialchars($item['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                &middot; Qty: <?= (int) $item['quantity'] ?>
                                &middot; $<?= number_format((float) $item['price_each'], 2) ?>
                                <?php if (!empty($item['unit'])): ?>
                                    <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="text-end fw-bold">
                            $<?= number_format((float) $item['line_total'], 2) ?>
                        </div>
                    </div>
                <?php $idx++; endwhile; ?>
            </div>
        </div>

        <div class="row g-4">
            <!-- Shipping address -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="card-title">Shipping Address</h6>
                        <p class="mb-1"><?= htmlspecialchars($order['ship_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mb-1"><?= htmlspecialchars($order['ship_address1'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (!empty($order['ship_address2'])): ?>
                            <p class="mb-1"><?= htmlspecialchars($order['ship_address2'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <p class="mb-0">
                            <?= htmlspecialchars($order['ship_city'] ?? '', ENT_QUOTES, 'UTF-8') ?>,
                            <?= htmlspecialchars($order['ship_state'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            <?= htmlspecialchars($order['ship_zip'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <?php if (!empty($order['notes'])): ?>
                            <hr>
                            <small class="text-muted">
                                <strong>Notes:</strong> <?= htmlspecialchars($order['notes'], ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Order totals -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="card-title">Order Total</h6>

                        <div class="d-flex justify-content-between mb-1">
                            <span>Subtotal</span>
                            <span>$<?= number_format((float) $order['subtotal'], 2) ?></span>
                        </div>

                        <?php if ((float) $order['tax_amount'] > 0): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Tax</span>
                                <span>$<?= number_format((float) $order['tax_amount'], 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between mb-1 text-muted">
                            <span>Shipping</span>
                            <span>
                                <?= (float) $order['shipping_amount'] > 0
                                    ? '$' . number_format((float) $order['shipping_amount'], 2)
                                    : 'Free' ?>
                            </span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <strong>Total</strong>
                            <strong class="text-success">$<?= number_format((float) $order['total_amount'], 2) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="text-center mt-4">
            <a href="<?= $base_url ?>/products.php" class="btn btn-success me-2">
                Continue Shopping
            </a>
            <a href="<?= $base_url ?>/customer/orders.php" class="btn btn-outline-secondary">
                View All Orders
            </a>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
