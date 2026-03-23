<?php
/**
 * customer/dashboard.php — Customer Dashboard
 * Virginia Market Square
 *
 * Phase 4 update — replaces Phase 3 placeholder.
 * Shows welcome message, quick-action cards with live data,
 * and recent orders.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_customer();

$page_title = 'My Account';

$customer_id = get_customer_id();

// ─── Get cart item count ────────────────────────────────────────────────────
$cart_count = 0;
if ($customer_id) {
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM cart WHERE customer_id = ?'
    );
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $cart_count = (int) $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

// ─── Get order count and total spent ────────────────────────────────────────
$order_count = 0;
$total_spent = 0.00;
if ($customer_id) {
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS total
         FROM orders WHERE customer_id = ?'
    );
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $order_count = (int) $stats['cnt'];
    $total_spent = (float) $stats['total'];
    $stmt->close();
}

// ─── Get recent orders (last 5) ─────────────────────────────────────────────
$recent_orders = null;
if ($customer_id) {
    $stmt = $conn->prepare(
        "SELECT order_id, order_status, total_amount, order_date,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
         FROM orders o
         WHERE customer_id = ?
         ORDER BY order_date DESC
         LIMIT 5"
    );
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $recent_orders = $stmt->get_result();
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-10 mx-auto">
        <h1 class="mb-4">My Account</h1>

        <!-- Welcome card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">
                    Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Customer', ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <p class="text-muted mb-0">
                    Signed in as <?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    &middot; <span class="badge bg-primary">Customer</span>
                </p>
            </div>
        </div>

        <!-- Quick action cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <a href="<?= $base_url ?>/customer/cart.php" class="text-decoration-none">
                    <div class="card text-center h-100 card-top-accent-green">
                        <div class="card-body">
                            <h3 class="mb-2 text-success"><?= $cart_count ?></h3>
                            <h6>Shopping Cart</h6>
                            <p class="small text-muted mb-0">
                                <?= $cart_count ?> item<?= $cart_count !== 1 ? 's' : '' ?> in cart
                            </p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= $base_url ?>/customer/orders.php" class="text-decoration-none">
                    <div class="card text-center h-100 card-top-accent-green">
                        <div class="card-body">
                            <h3 class="mb-2 text-success"><?= $order_count ?></h3>
                            <h6>Order History</h6>
                            <p class="small text-muted mb-0">
                                $<?= number_format($total_spent, 2) ?> total
                            </p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?= $base_url ?>/products.php" class="text-decoration-none">
                    <div class="card text-center h-100 card-top-accent-green">
                        <div class="card-body">
                            <h3 class="mb-2">🌱</h3>
                            <h6>Browse Products</h6>
                            <p class="small text-muted mb-0">
                                Find fresh local goods
                            </p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent orders -->
        <h4 class="mb-3">Recent Orders</h4>

        <?php if ($recent_orders && $recent_orders->num_rows > 0): ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()):
                                // Map status to Bootstrap badge colors
                                $status_badges = [
                                    'pending'    => 'bg-warning text-dark',
                                    'processing' => 'bg-info text-dark',
                                    'shipped'    => 'bg-primary',
                                    'delivered'  => 'bg-success',
                                    'cancelled'  => 'bg-secondary',
                                    'refunded'   => 'bg-danger',
                                ];
                                $badge = $status_badges[$order['order_status']] ?? 'bg-secondary';
                            ?>
                                <tr>
                                    <td><strong>#<?= (int) $order['order_id'] ?></strong></td>
                                    <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                    <td><?= (int) $order['item_count'] ?> item<?= (int) $order['item_count'] !== 1 ? 's' : '' ?></td>
                                    <td>$<?= number_format((float) $order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="badge <?= $badge ?>">
                                            <?= ucfirst(htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= $base_url ?>/customer/order-confirmation.php?order_id=<?= (int) $order['order_id'] ?>"
                                           class="btn btn-outline-success btn-sm">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($order_count > 5): ?>
                <div class="text-center mt-3">
                    <a href="<?= $base_url ?>/customer/orders.php" class="btn btn-outline-secondary btn-sm">
                        View All Orders
                    </a>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info">
                No orders yet. <a href="<?= $base_url ?>/products.php">Start shopping</a>!
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
