<?php
/**
 * customer/cart.php — Shopping Cart Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.7
 *
 * Layout (based on wireframe):
 *   Left (col-md-8):  Order summary — cart items with image, name, vendor,
 *                      price, quantity controls (+/-), Remove button, line total
 *   Right (col-md-4): Order totals sidebar — subtotal, shipping note,
 *                      estimated total, "Proceed to Checkout" button
 *
 * Cart data comes from a JOIN across cart → products → vendors → categories.
 * Line totals (quantity × price) are calculated in PHP, not stored in the DB.
 *
 * Quantity update and Remove actions POST to customer/cart-update.php (Task 4.8).
 * Proceed to Checkout links to customer/checkout.php (Task 4.9).
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Only logged-in customers can view the cart
require_customer();

$page_title = 'Shopping Cart';

// ─── Get customer_id ────────────────────────────────────────────────────────
$customer_id = get_customer_id();

if (!$customer_id) {
    set_flash('error', 'Customer profile not found.');
    redirect($base_url . '/index.php');
}

// ─── Fetch cart items with product, vendor, and category details ─────────────
// This is the main cart query — JOINs pull everything we need for display.
// We also check that products are still available and from verified vendors.
// Items where the product was deactivated or vendor was unverified will still
// show but with a warning (handled in the template below).
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
$cart_items = $stmt->get_result();
$stmt->close();

// ─── Calculate totals ───────────────────────────────────────────────────────
// We loop through the result set to calculate the subtotal, then rewind
// the result pointer so we can loop again in the HTML template.
$subtotal    = 0.00;
$item_count  = 0;
$has_issues  = false;  // Flag if any items are unavailable

$items = [];  // Store items in array so we can loop twice

while ($item = $cart_items->fetch_assoc()) {
    // Calculate line total
    $item['line_total'] = (float) $item['price'] * (int) $item['quantity'];

    // Check if item is still valid (available product from verified vendor)
    $item['is_valid'] = ($item['is_available'] && $item['verified']
                         && $item['stock_quantity'] > 0);

    // Check if quantity exceeds current stock
    $item['over_stock'] = ($item['quantity'] > $item['stock_quantity']);

    // Only count valid items toward the subtotal
    if ($item['is_valid']) {
        $subtotal   += $item['line_total'];
        $item_count += (int) $item['quantity'];
    } else {
        $has_issues = true;
    }

    $items[] = $item;
}

include '../includes/header.php';
?>

<!-- Page heading -->
<h1 class="mb-4">Your Shopping Cart</h1>

<?php if (empty($items)): ?>
    <!-- Empty cart -->
    <div class="text-center py-5">
        <h4 class="text-muted mb-3">Your cart is empty</h4>
        <p class="text-muted">Browse our products and add something you love!</p>
        <a href="<?= $base_url ?>/products.php" class="btn btn-success btn-lg">
            Browse Products
        </a>
    </div>

<?php else: ?>

    <?php if ($has_issues): ?>
        <div class="alert alert-warning">
            Some items in your cart are no longer available and won't be included
            in your order. Please remove them or update your cart.
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ── LEFT: Order Summary (Cart Items) ─────────────────────────── -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body p-0">

                    <?php foreach ($items as $index => $item): ?>
                        <div class="d-flex gap-3 p-3 <?= $index > 0 ? 'border-top' : '' ?>
                                    <?= !$item['is_valid'] ? 'bg-light opacity-75' : '' ?>">

                            <!-- Product image -->
                            <?php if (!empty($item['image_url'])): ?>
                                <a href="<?= $base_url ?>/product-detail.php?id=<?= (int) $item['product_id'] ?>">
                                    <img src="<?= htmlspecialchars($item['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                         style="width:80px;height:80px;object-fit:cover;"
                                         class="rounded"
                                         alt="<?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>">
                                </a>
                            <?php endif; ?>

                            <!-- Product info -->
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <a href="<?= $base_url ?>/product-detail.php?id=<?= (int) $item['product_id'] ?>"
                                       class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h6>
                                <small class="text-muted">
                                    by <?= htmlspecialchars($item['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                                <br>
                                <span class="text-success fw-bold">
                                    $<?= number_format((float) $item['price'], 2) ?>
                                </span>
                                <?php if (!empty($item['unit'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($item['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>

                                <!-- Availability warnings -->
                                <?php if (!$item['is_available']): ?>
                                    <br><small class="text-danger">This product is no longer available</small>
                                <?php elseif (!$item['verified']): ?>
                                    <br><small class="text-danger">This vendor is currently unavailable</small>
                                <?php elseif ($item['stock_quantity'] <= 0): ?>
                                    <br><small class="text-danger">Out of stock</small>
                                <?php elseif ($item['over_stock']): ?>
                                    <br><small class="text-warning">Only <?= (int) $item['stock_quantity'] ?> available — quantity will be adjusted</small>
                                <?php endif; ?>
                            </div>

                            <!-- Quantity controls + Remove -->
                            <div class="text-end" style="min-width:160px;">
                                <?php if ($item['is_valid']): ?>
                                    <!-- Quantity update form -->
                                    <form action="<?= $base_url ?>/customer/cart-update.php" method="POST"
                                          class="d-inline-flex align-items-center gap-1 mb-2">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                                        <input type="hidden" name="action" value="update">

                                        <!-- Minus button -->
                                        <button type="submit" name="quantity"
                                                value="<?= max(1, (int) $item['quantity'] - 1) ?>"
                                                class="btn btn-outline-secondary btn-sm"
                                                <?= (int) $item['quantity'] <= 1 ? 'disabled' : '' ?>>
                                            &minus;
                                        </button>

                                        <!-- Quantity display -->
                                        <span class="px-2 fw-bold"><?= (int) $item['quantity'] ?></span>

                                        <!-- Plus button -->
                                        <button type="submit" name="quantity"
                                                value="<?= min((int) $item['stock_quantity'], (int) $item['quantity'] + 1) ?>"
                                                class="btn btn-outline-secondary btn-sm"
                                                <?= (int) $item['quantity'] >= (int) $item['stock_quantity'] ? 'disabled' : '' ?>>
                                            +
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Line total -->
                                <div class="fw-bold mb-1">
                                    $<?= number_format($item['line_total'], 2) ?>
                                </div>

                                <!-- Remove button -->
                                <form action="<?= $base_url ?>/customer/cart-update.php" method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="cart_id" value="<?= (int) $item['cart_id'] ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        Remove
                                    </button>
                                </form>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <!-- Continue shopping link -->
            <a href="<?= $base_url ?>/products.php" class="btn btn-outline-secondary mt-3">
                &larr; Continue Shopping
            </a>
        </div>

        <!-- ── RIGHT: Order Totals Sidebar ───────────────────────────────── -->
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">Order Summary</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal (<?= $item_count ?> item<?= $item_count !== 1 ? 's' : '' ?>)</span>
                        <strong>$<?= number_format($subtotal, 2) ?></strong>
                    </div>

                    <div class="d-flex justify-content-between mb-2 text-muted">
                        <span>Shipping</span>
                        <span>Calculated at checkout</span>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-3">
                        <strong class="fs-5">Estimated Total</strong>
                        <strong class="fs-5 text-success">$<?= number_format($subtotal, 2) ?></strong>
                    </div>

                    <?php if ($subtotal > 0): ?>
                        <a href="<?= $base_url ?>/customer/checkout.php"
                           class="btn btn-success btn-lg w-100">
                            Proceed to Checkout
                        </a>
                    <?php else: ?>
                        <button class="btn btn-success btn-lg w-100" disabled>
                            Proceed to Checkout
                        </button>
                        <small class="text-muted d-block text-center mt-2">
                            Remove unavailable items to continue
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
