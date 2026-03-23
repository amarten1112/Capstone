<?php
/**
 * customer/orders.php — Order History Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.11
 *
 * Shows all orders for the logged-in customer, newest first.
 * Each row links to the order confirmation/detail page.
 * Includes a status filter (all, pending, processing, shipped, delivered).
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_customer();

$page_title = 'Order History';

$customer_id = get_customer_id();

if (!$customer_id) {
    set_flash('error', 'Customer profile not found.');
    redirect($base_url . '/index.php');
}

// ─── Status filter ──────────────────────────────────────────────────────────
$status_filter = trim($_GET['status'] ?? '');

$allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
if ($status_filter !== '' && !in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = '';
}

// ─── Fetch orders ───────────────────────────────────────────────────────────
$where  = ['o.customer_id = ?'];
$params = [$customer_id];
$types  = 'i';

if ($status_filter !== '') {
    $where[]  = 'o.order_status = ?';
    $params[] = $status_filter;
    $types   .= 's';
}

$where_clause = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT o.order_id, o.order_status, o.subtotal, o.total_amount,
               o.order_date,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count,
               (SELECT GROUP_CONCAT(DISTINCT v.vendor_name SEPARATOR ', ')
                FROM order_items oi2
                JOIN vendors v ON oi2.vendor_id = v.vendor_id
                WHERE oi2.order_id = o.order_id) AS vendor_names
        FROM orders o
        $where_clause
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

// ─── Status badge mapping ───────────────────────────────────────────────────
$status_badges = [
    'pending'    => 'bg-warning text-dark',
    'processing' => 'bg-info text-dark',
    'shipped'    => 'bg-primary',
    'delivered'  => 'bg-success',
    'cancelled'  => 'bg-secondary',
    'refunded'   => 'bg-danger',
];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Order History</h1>
    <span class="text-muted">
        <?= $orders->num_rows ?> order<?= $orders->num_rows !== 1 ? 's' : '' ?>
    </span>
</div>

<!-- Status filter tabs -->
<div class="mb-4">
    <div class="btn-group flex-wrap">
        <a href="<?= $base_url ?>/customer/orders.php"
           class="btn btn-sm <?= $status_filter === '' ? 'btn-success' : 'btn-outline-success' ?>">
            All
        </a>
        <?php foreach ($allowed_statuses as $s): ?>
            <a href="<?= $base_url ?>/customer/orders.php?status=<?= $s ?>"
               class="btn btn-sm <?= $status_filter === $s ? 'btn-success' : 'btn-outline-success' ?>">
                <?= ucfirst($s) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($orders->num_rows > 0): ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Vendors</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders->fetch_assoc()):
                        $badge = $status_badges[$order['order_status']] ?? 'bg-secondary';
                    ?>
                        <tr>
                            <td><strong>#<?= (int) $order['order_id'] ?></strong></td>
                            <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                            <td>
                                <small class="text-muted">
                                    <?= htmlspecialchars($order['vendor_names'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </td>
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
<?php else: ?>
    <div class="alert alert-info">
        <?php if ($status_filter !== ''): ?>
            No <?= htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') ?> orders found.
            <a href="<?= $base_url ?>/customer/orders.php">View all orders</a>.
        <?php else: ?>
            You haven't placed any orders yet.
            <a href="<?= $base_url ?>/products.php">Start shopping</a>!
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Back to dashboard -->
<div class="mt-3">
    <a href="<?= $base_url ?>/customer/dashboard.php" class="btn btn-outline-secondary btn-sm">
        &larr; Back to My Account
    </a>
</div>

<?php include '../includes/footer.php'; ?>
