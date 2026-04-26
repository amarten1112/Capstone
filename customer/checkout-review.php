<?php
/**
 * customer/checkout-review.php — Checkout Step 2: Review, Pay & Place Order
 * Virginia Market Square
 *
 * Phase 5 — Stripe Payment Integration
 * Phase 6, Task 6.6 — UI polish (progress indicator, breakpoints, inline styles)
 *
 * All PHP logic and Stripe integration unchanged from Phase 5.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_customer();

$page_title = 'Checkout — Review & Pay';

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

// ─── Fetch cart items ───────────────────────────────────────────────────────
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
        if ($row['quantity'] > $row['stock_quantity']) {
            $row['quantity']   = $row['stock_quantity'];
            $row['line_total'] = (float) $row['price'] * (int) $row['quantity'];
        }
        $valid_items[] = $row;
        $subtotal     += $row['line_total'];
        $item_count   += (int) $row['quantity'];
    }
}

if (empty($valid_items)) {
    set_flash('error', 'Your cart has no available items. Please update your cart.');
    redirect($base_url . '/customer/cart.php');
}

// Calculate totals
$tax_rate        = 0.0;
$tax_amount      = round($subtotal * $tax_rate, 2);
$shipping_amount = 0.00;
$total_amount    = $subtotal + $tax_amount + $shipping_amount;


// ─── Create Stripe PaymentIntent ────────────────────────────────────────────
$stripe_error = '';
$client_secret = '';

try {
    \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

    if (!empty($_SESSION['stripe_payment_intent_id'])) {
        $intent = \Stripe\PaymentIntent::retrieve($_SESSION['stripe_payment_intent_id']);

        if (in_array($intent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'])) {
            $intent = \Stripe\PaymentIntent::update(
                $_SESSION['stripe_payment_intent_id'],
                ['amount' => (int) round($total_amount * 100)]
            );
        } else {
            unset($_SESSION['stripe_payment_intent_id']);
            $intent = \Stripe\PaymentIntent::create([
                'amount'   => (int) round($total_amount * 100),
                'currency' => 'usd',
                'metadata' => [
                    'customer_id' => $customer_id,
                    'store'       => 'Virginia Market Square',
                ],
            ]);
            $_SESSION['stripe_payment_intent_id'] = $intent->id;
        }
    } else {
        $intent = \Stripe\PaymentIntent::create([
            'amount'   => (int) round($total_amount * 100),
            'currency' => 'usd',
            'metadata' => [
                'customer_id' => $customer_id,
                'store'       => 'Virginia Market Square',
            ],
        ]);
        $_SESSION['stripe_payment_intent_id'] = $intent->id;
    }

    $client_secret = $intent->client_secret;

} catch (\Stripe\Exception\ApiErrorException $e) {
    $stripe_error = 'Payment system error: ' . $e->getMessage();
} catch (Exception $e) {
    $stripe_error = 'Payment system is currently unavailable. Please try again later.';
}


// ─── Handle POST: Verify payment and create order ───────────────────────────
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($_POST['payment_intent_id'])) {
        $error = 'Payment information is missing. Please try again.';
    } else {
        $payment_intent_id = trim($_POST['payment_intent_id']);

        try {
            \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

            if ($intent->status === 'succeeded') {
                $conn->begin_transaction();

                try {
                    // 1. Create order
                    $stmt = $conn->prepare(
                        "INSERT INTO orders
                            (customer_id, order_status, subtotal, tax_amount,
                             shipping_amount, total_amount,
                             ship_name, ship_address1, ship_address2,
                             ship_city, ship_state, ship_zip, notes)
                         VALUES (?, 'processing', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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

                    // 2. Insert order_items
                    $item_stmt = $conn->prepare(
                        "INSERT INTO order_items
                            (order_id, product_id, vendor_id, quantity, price_each, line_total)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );

                    // 3. Decrement stock
                    $stock_stmt = $conn->prepare(
                        "UPDATE products
                         SET stock_quantity = stock_quantity - ?
                         WHERE product_id = ? AND stock_quantity >= ?"
                    );

                    foreach ($valid_items as $vi) {
                        $line_total = (float) $vi['price'] * (int) $vi['quantity'];

                        $item_stmt->bind_param('iiiidd',
                            $order_id,
                            $vi['product_id'],
                            $vi['vendor_id'],
                            $vi['quantity'],
                            $vi['price'],
                            $line_total
                        );
                        $item_stmt->execute();

                        $stock_stmt->bind_param('iii',
                            $vi['quantity'],
                            $vi['product_id'],
                            $vi['quantity']
                        );
                        $stock_stmt->execute();

                        if ($stock_stmt->affected_rows === 0) {
                            throw new Exception(
                                'Insufficient stock for ' . $vi['product_name']
                            );
                        }
                    }

                    $item_stmt->close();
                    $stock_stmt->close();

                    // 3b. Create one fulfillment row per unique vendor
                    $vendor_ids = array_unique(array_column($valid_items, 'vendor_id'));
                    $fulfillment_stmt = $conn->prepare(
                        "INSERT IGNORE INTO order_fulfillments (order_id, vendor_id, status)
                         VALUES (?, ?, 'processing')"
                    );
                    foreach ($vendor_ids as $vid) {
                        $fulfillment_stmt->bind_param('ii', $order_id, $vid);
                        $fulfillment_stmt->execute();
                    }
                    $fulfillment_stmt->close();

                    // 4. Log transaction
                    $txn_stmt = $conn->prepare(
                        "INSERT INTO transactions
                            (order_id, stripe_payment_id, amount, transaction_status)
                         VALUES (?, ?, ?, 'success')"
                    );
                    $txn_stmt->bind_param('isd',
                        $order_id,
                        $payment_intent_id,
                        $total_amount
                    );
                    $txn_stmt->execute();
                    $txn_stmt->close();

                    // 5. Clear cart
                    $stmt = $conn->prepare('DELETE FROM cart WHERE customer_id = ?');
                    $stmt->bind_param('i', $customer_id);
                    $stmt->execute();
                    $stmt->close();

                    // 6. Commit
                    $conn->commit();

                    // 7. Clean up session
                    unset($_SESSION['checkout']);
                    unset($_SESSION['stripe_payment_intent_id']);

                    // 8. Redirect
                    set_flash('success', 'Payment received! Your order #' . $order_id . ' is being processed.');
                    redirect($base_url . '/customer/order-confirmation.php?order_id=' . $order_id);

                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Payment was successful, but there was a problem creating your order: '
                           . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                           . '. Please contact support with your payment ID: '
                           . htmlspecialchars($payment_intent_id, ENT_QUOTES, 'UTF-8');
                }

            } else {
                $error = 'Payment was not completed. Status: '
                       . htmlspecialchars($intent->status, ENT_QUOTES, 'UTF-8')
                       . '. Please try again.';
                unset($_SESSION['stripe_payment_intent_id']);
            }

        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = 'Payment verification failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            unset($_SESSION['stripe_payment_intent_id']);
        }
    }
}

include '../includes/header.php';
?>

<!-- Checkout progress indicator -->
<div class="checkout-progress">
    <a href="<?= $base_url ?>/customer/checkout.php"
       class="checkout-step checkout-step-done text-decoration-none">1. Shipping</a>
    <span class="checkout-step checkout-step-active">2. Review &amp; Pay</span>
</div>

<h2 class="mb-4">Review Your Order</h2>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if ($stripe_error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($stripe_error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- ── LEFT: Order Items ─────────────────────────────────────────────── -->
    <div class="col-lg-8">

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
                                 class="rounded product-thumb"
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

    <!-- ── RIGHT: Order Totals + Payment ─────────────────────────────────── -->
    <div class="col-lg-4">
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

                <?php if ($client_secret): ?>
                    <!-- ── Stripe Payment Form ───────────────────────────── -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Details</label>
                        <div id="payment-element" class="border rounded p-3 bg-white"></div>
                        <div id="payment-errors" class="text-danger small mt-2" role="alert"></div>
                    </div>

                    <button id="pay-button" type="button" class="btn btn-success btn-lg w-100">
                        <span id="pay-button-text">Pay $<?= number_format($total_amount, 2) ?></span>
                        <span id="pay-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>

                    <p class="text-muted small text-center mt-2 mb-0">
                        <span class="me-1">&#128274;</span> Payments processed securely by Stripe
                    </p>

                    <!-- Hidden form that JS submits after successful payment -->
                    <form id="order-form" method="POST" action="checkout-review.php" class="d-none">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="payment_intent_id" id="payment-intent-id" value="">
                    </form>

                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        Payment system is temporarily unavailable. Please try again in a few minutes.
                    </div>
                    <button class="btn btn-secondary btn-lg w-100" disabled>
                        Payment Unavailable
                    </button>
                <?php endif; ?>

                <hr>

                <a href="<?= $base_url ?>/customer/checkout.php"
                   class="btn btn-outline-secondary btn-sm w-100">
                    &larr; Edit Shipping
                </a>
            </div>
        </div>

        <!-- Test card info -->
        <div class="card shadow-sm mt-3 border-info">
            <div class="card-body py-2">
                <p class="mb-1 small"><strong>Test Card:</strong> 4242 4242 4242 4242</p>
                <p class="mb-1 small"><strong>Expiry:</strong> Any future date (e.g. 12/34)</p>
                <p class="mb-0 small"><strong>CVC:</strong> Any 3 digits (e.g. 123)</p>
            </div>
        </div>
    </div>

</div>

<?php if ($client_secret): ?>
<!-- Stripe.js -->
<script src="https://js.stripe.com/v3/"></script>

<script>
const stripe = Stripe('<?= STRIPE_PUBLIC_KEY ?>');

const elements = stripe.elements({
    clientSecret: '<?= $client_secret ?>',
    appearance: {
        theme: 'stripe',
        variables: {
            colorPrimary: '#2d5016',
            colorBackground: '#ffffff',
            colorText: '#333333',
            borderRadius: '6px',
        }
    }
});

const paymentElement = elements.create('payment');
paymentElement.mount('#payment-element');

const payButton      = document.getElementById('pay-button');
const payButtonText  = document.getElementById('pay-button-text');
const paySpinner     = document.getElementById('pay-spinner');
const errorDisplay   = document.getElementById('payment-errors');

payButton.addEventListener('click', async function () {
    payButton.disabled = true;
    payButtonText.textContent = 'Processing...';
    paySpinner.classList.remove('d-none');
    errorDisplay.textContent = '';

    const { error, paymentIntent } = await stripe.confirmPayment({
        elements,
        redirect: 'if_required',
    });

    if (error) {
        errorDisplay.textContent = error.message;
        payButton.disabled = false;
        payButtonText.textContent = 'Pay $<?= number_format($total_amount, 2) ?>';
        paySpinner.classList.add('d-none');
        return;
    }

    if (paymentIntent && paymentIntent.status === 'succeeded') {
        document.getElementById('payment-intent-id').value = paymentIntent.id;
        document.getElementById('order-form').submit();
    } else {
        errorDisplay.textContent = 'Payment was not completed. Please try again.';
        payButton.disabled = false;
        payButtonText.textContent = 'Pay $<?= number_format($total_amount, 2) ?>';
        paySpinner.classList.add('d-none');
    }
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
