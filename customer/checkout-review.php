<?php
/**
 * customer/checkout-review.php — Checkout Step 2: Review & Place Order
 * Virginia Market Square
 *
 * Phase 4, Tasks 4.9 + 4.10
 *
 * Step 2 of 2-step checkout:
 *   1. Shipping address (checkout.php) → stored in $_SESSION['checkout']
 *   2. Review & place order (this page)
 *
 * GET:  Shows order review — cart items, shipping address, totals
 * POST: Creates the order — wraps everything in a transaction:
 *   1. INSERT into orders (with shipping address snapshot + totals)
 *   2. INSERT into order_items (one row per cart item, with price snapshot)
 *   3. UPDATE stock_quantity on each product (decrement)
 *   4. DELETE cart rows for this customer
 *   5. COMMIT
 *   6. Clear session checkout data
 *   7. Redirect to order confirmation page
 *
 * Payment integration (Stripe) will be added in Phase 5.
 * For now, orders are created with status='pending' and no transaction record.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_customer();

$page_title = 'Checkout — Review Order';

$customer_id = get_customer_id();

if (!$customer_id) {
    set_flash('error', 'Customer profile not found.');
    redirect($base_url . '/customer/cart.php');
}

// ─── Require shipping data from step 1 ─────────────────────────────────────
if (empty($_SESSION['checkout'])) {
    set_flash('warning', 'Please enter your shipping address first.');
    redirect($base_url . '/customer/checkout.php');
}

$shipping = $_SESSION['checkout'];

// ─── Fetch cart items (same query as cart.php) ──────────────────────────────
$stmt = $conn->prepare(
    "SELECT c.cart_id, c.quantity,
            p.product_id, p.product_name, p.price, p.image_url, p.unit,
            p.stock_quantity, p.is_available,
            v.vendor_id, v.vendor_name, v.verified
     FROM cart c
     JOIN products p ON c.product_id = p.product_id
     JOIN vendors v  ON p.vendor_id  = v.vendor_id
     WHERE c.customer_id = ?
     ORDER BY c.added_date DESC"
);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// Build items array, calculating totals and filtering valid items
$items       = [];
$valid_items = [];
$subtotal    = 0.00;
$item_count  = 0;

while ($row = $result->fetch_assoc()) {
    $row['line_total'] = (float) $row['price'] * (int) $row['quantity'];
    $row['is_valid']   = ($row['is_available'] && $row['verified']
                          && $row['stock_quantity'] > 0);

    $items[] = $row;

    if ($row['is_valid']) {
        // Cap quantity at available stock
        if ($row['quantity'] > $row['stock_quantity']) {
            $row['quantity']   = $row['stock_quantity'];
            $row['line_total'] = (float) $row['price'] * (int) $row['quantity'];
        }
        $valid_items[] = $row;
        $subtotal     += $row['line_total'];
        $item_count   += (int) $row['quantity'];
    }
}

// If no valid items, redirect back to cart
if (empty($valid_items)) {
    set_flash('error', 'Your cart has no available items. Please update your cart.');
    redirect($base_url . '/customer/cart.php');
}

// Calculate totals — tax and shipping are placeholders for now
$tax_rate        = 0.0;    // 0% tax — adjust later if needed
$tax_amount      = round($subtotal * $tax_rate, 2);
$shipping_amount = 0.00;   // Free shipping — Stripe phase can add shipping options
$total_amount    = $subtotal + $tax_amount + $shipping_amount;

// ─── Handle PLACE ORDER submission ──────────────────────────────────────────
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        // ── Begin transaction ───────────────────────────────────────────
        $conn->begin_transaction();

        try {
            // 1. Create the order record
            $stmt = $conn->prepare(
                "INSERT INTO orders
                    (customer_id, order_status, subtotal, tax_amount,
                     shipping_amount, total_amount,
                     ship_name, ship_address1, ship_address2,
                     ship_city, ship_state, ship_zip, notes)
                 VALUES (?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iddddsssssss',
                $customer_id,
                $subtotal,
                $tax_amount,
                $shipping_amount,
                $total_amount,
                $shipping['ship_name'],
                $shipping['ship_address1'],
                $shipping['ship_address2'],
                $shipping['ship_city'],
                $shipping['ship_state'],
                $shipping['ship_zip'],
                $shipping['notes']
            );
            $stmt->execute();
            $order_id = (int) $conn->insert_id;
            $stmt->close();

            // 2. Insert order_items — one row per valid cart item
            //    Snapshot the price at time of purchase so future price
            //    changes don't affect historical orders.
            $item_stmt = $conn->prepare(
                "INSERT INTO order_items
                    (order_id, product_id, vendor_id, quantity, price_each, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            // 3. Decrement stock for each product
            $stock_stmt = $conn->prepare(
                "UPDATE products
                 SET stock_quantity = stock_quantity - ?
                 WHERE product_id = ? AND stock_quantity >= ?"
            );

            foreach ($valid_items as $vi) {
                $line_total = (float) $vi['price'] * (int) $vi['quantity'];

                // Insert order item
                $item_stmt->bind_param('iiiidd',
                    $order_id,
                    $vi['product_id'],
                    $vi['vendor_id'],
                    $vi['quantity'],
                    $vi['price'],
                    $line_total
                );
                $item_stmt->execute();

                // Decrement stock
                $stock_stmt->bind_param('iii',
                    $vi['quantity'],
                    $vi['product_id'],
                    $vi['quantity']
                );
                $stock_stmt->execute();

                // Verify stock was actually decremented (prevents overselling)
                if ($stock_stmt->affected_rows === 0) {
                    throw new Exception(
                        'Insufficient stock for ' . $vi['product_name']
                    );
                }
            }

            $item_stmt->close();
            $stock_stmt->close();

            // 4. Clear the customer's cart
            $stmt = $conn->prepare('DELETE FROM cart WHERE customer_id = ?');
            $stmt->bind_param('i', $customer_id);
            $stmt->execute();
            $stmt->close();

            // 5. Commit the transaction
            $conn->commit();

            // 6. Clear checkout session data
            unset($_SESSION['checkout']);

            // 7. Redirect to order confirmation
            set_flash('success', 'Order placed successfully! Your order number is #' . $order_id . '.');
            redirect($base_url . '/customer/order-confirmation.php?order_id=' . $order_id);

        } catch (Exception $e) {
            $conn->rollback();
            $error = 'There was a problem placing your order: '
                   . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                   . '. Please try again.';
        }
    }
}

include '../includes/header.php';
?>

<!-- Checkout progress indicator -->
<div class="mb-4">
    <div class="d-flex justify-content-center gap-3">
        <a href="<?= $base_url ?>/customer/checkout.php"
           class="badge bg-success px-3 py-2 text-decoration-none">1. Shipping</a>
        <span class="badge bg-success px-3 py-2">2. Review & Pay</span>
    </div>
</div>

<h2 class="mb-4">Review Your Order</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── LEFT: Order Items ─────────────────────────────────────────────── -->
    <div class="col-md-8">

        <!-- Cart items summary -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Order Items (<?= $item_count ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php foreach ($valid_items as $index => $item): ?>
                    <div class="d-flex gap-3 p-3 <?= $index > 0 ? 'border-top' : '' ?>">
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
                                &middot; $<?= number_format((float) $item['price'], 2) ?>
                                <?php if (!empty($item['unit'])): ?>
                                    <?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div class="text-end fw-bold">
                            $<?= number_format($item['line_total'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Shipping address -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Shipping Address</h5>
                <a href="<?= $base_url ?>/customer/checkout.php"
                   class="btn btn-outline-secondary btn-sm">Edit</a>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong><?= htmlspecialchars($shipping['ship_name'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                <p class="mb-1"><?= htmlspecialchars($shipping['ship_address1'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($shipping['ship_address2'])): ?>
                    <p class="mb-1"><?= htmlspecialchars($shipping['ship_address2'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <p class="mb-0">
                    <?= htmlspecialchars($shipping['ship_city'], ENT_QUOTES, 'UTF-8') ?>,
                    <?= htmlspecialchars($shipping['ship_state'], ENT_QUOTES, 'UTF-8') ?>
                    <?= htmlspecialchars($shipping['ship_zip'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if (!empty($shipping['notes'])): ?>
                    <hr>
                    <p class="mb-0 text-muted small">
                        <strong>Notes:</strong> <?= htmlspecialchars($shipping['notes'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: Order Totals + Place Order ─────────────────────────────── -->
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Order Total</h5>

                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <span>$<?= number_format($subtotal, 2) ?></span>
                </div>

                <?php if ($tax_amount > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax</span>
                        <span>$<?= number_format($tax_amount, 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between mb-2 text-muted">
                    <span>Shipping</span>
                    <span><?= $shipping_amount > 0 ? '$' . number_format($shipping_amount, 2) : 'Free' ?></span>
                </div>

                <hr>

                <div class="d-flex justify-content-between mb-4">
                    <strong class="fs-5">Total</strong>
                    <strong class="fs-5 text-success">$<?= number_format($total_amount, 2) ?></strong>
                </div>

                <!-- Place Order form -->
                <form method="POST" action="checkout-review.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <button type="submit" class="btn btn-success btn-lg w-100">
                        Place Order
                    </button>
                </form>

                <p class="text-muted small text-center mt-2 mb-0">
                    Payment will be collected in a future update.
                    Orders are placed as "pending" for now.
                </p>

                <hr>

                <a href="<?= $base_url ?>/customer/checkout.php"
                   class="btn btn-outline-secondary btn-sm w-100">
                    &larr; Edit Shipping
                </a>
            </div>
        </div>
    </div>

</div>

<?php include '../includes/footer.php'; ?>
