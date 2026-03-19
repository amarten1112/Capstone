<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

require_vendor();

$vendor_id = get_vendor_id();

// Vendor profile
$stmt = $conn->prepare(
    'SELECT vendor_id, vendor_name, business_email, phone, description, verified, featured
     FROM vendors WHERE vendor_id = ?'
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Stats: total products
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM products WHERE vendor_id = ?');
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$total_products = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Stats: available products
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM products WHERE vendor_id = ? AND is_available = 1');
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$available_products = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Stats: orders and revenue
$stmt = $conn->prepare(
    'SELECT COUNT(DISTINCT oi.order_id) AS total_orders,
            COALESCE(SUM(oi.line_total), 0) AS total_revenue
     FROM order_items oi WHERE oi.vendor_id = ?'
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$order_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();
$total_orders   = (int) $order_stats['total_orders'];
$total_revenue  = (float) $order_stats['total_revenue'];

// Products list
$stmt = $conn->prepare(
    'SELECT product_id, product_name, price, stock_quantity, is_available, created_date
     FROM products WHERE vendor_id = ?
     ORDER BY created_date DESC LIMIT 20'
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Recent orders
$stmt = $conn->prepare(
    'SELECT o.order_id, o.order_date, o.order_status,
            oi.quantity, oi.price_each, oi.line_total,
            p.product_name
     FROM orders o
     JOIN order_items oi ON o.order_id = oi.order_id
     JOIN products p ON oi.product_id = p.product_id
     WHERE oi.vendor_id = ?
     ORDER BY o.order_date DESC LIMIT 10'
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Vendor Dashboard';
include '../includes/header.php';

$flash = get_flash();
if ($flash) {
    $cls = $flash['type'] === 'error' ? 'danger' : htmlspecialchars($flash['type']);
    echo '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($flash['message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h1 style="color: var(--primary-color);">
        <?= htmlspecialchars($vendor['vendor_name'] ?? 'Vendor Dashboard') ?>
    </h1>
    <?php if ($vendor['verified']): ?>
        <span class="badge bg-success fs-6">✓ Verified Vendor</span>
    <?php else: ?>
        <span class="badge bg-warning text-dark fs-6">⏳ Pending Approval</span>
    <?php endif; ?>
</div>
<p class="text-muted mb-4">Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?></p>

<!-- Stats Widgets -->
<div class="row g-3 mb-5">
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-green shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold" style="color: var(--primary-color);">
                    <?= $total_products ?>
                </div>
                <div class="text-muted mt-1">Total Products</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-green shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold" style="color: var(--secondary-color);">
                    <?= $available_products ?>
                </div>
                <div class="text-muted mt-1">Available Now</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-earth shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold" style="color: var(--accent-color);">
                    <?= $total_orders ?>
                </div>
                <div class="text-muted mt-1">Total Orders</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-earth shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold text-success">
                    $<?= number_format($total_revenue, 2) ?>
                </div>
                <div class="text-muted mt-1">Total Revenue</div>
            </div>
        </div>
    </div>
</div>

<!-- Products -->
<section class="mb-5">
    <h2 class="mb-3" style="color: var(--primary-color);">Your Products</h2>
    <?php if (empty($products)): ?>
        <div class="alert alert-info">You haven't added any products yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= htmlspecialchars($product['product_name']) ?></td>
                        <td>$<?= number_format((float) $product['price'], 2) ?></td>
                        <td><?= (int) $product['stock_quantity'] ?></td>
                        <td>
                            <?php if ($product['is_available']): ?>
                                <span class="badge bg-success">Available</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Unavailable</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap text-muted">
                            <?= htmlspecialchars(date('M j, Y', strtotime($product['created_date']))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Recent Orders -->
<section class="mb-5">
    <h2 class="mb-3" style="color: var(--primary-color);">Recent Orders</h2>
    <?php if (empty($recent_orders)): ?>
        <div class="alert alert-info">No orders yet.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Order #</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Each</th>
                        <th>Line Total</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td>#<?= (int) $order['order_id'] ?></td>
                        <td><?= htmlspecialchars($order['product_name']) ?></td>
                        <td><?= (int) $order['quantity'] ?></td>
                        <td>$<?= number_format((float) $order['price_each'], 2) ?></td>
                        <td>$<?= number_format((float) $order['line_total'], 2) ?></td>
                        <td>
                            <?php
                            $status_colors = [
                                'pending'    => 'warning',
                                'processing' => 'info',
                                'shipped'    => 'primary',
                                'delivered'  => 'success',
                                'cancelled'  => 'danger',
                                'refunded'   => 'secondary',
                            ];
                            $s = $order['order_status'];
                            $color = $status_colors[$s] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>">
                                <?= htmlspecialchars(ucfirst($s)) ?>
                            </span>
                        </td>
                        <td class="text-nowrap text-muted">
                            <?= htmlspecialchars(date('M j, Y', strtotime($order['order_date']))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include '../includes/footer.php';
