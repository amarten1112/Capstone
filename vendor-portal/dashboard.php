<?php
/**
 * vendor-portal/dashboard.php — Vendor Dashboard
 * Virginia Market Square
 *
 * Phase 4, Task 4.12 (logic)
 * Phase 6, Task 6.7 (UI polish — metric classes, breakpoints)
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_vendor();

$page_title = 'Vendor Dashboard';

$vendor_id = get_vendor_id();

if (!$vendor_id) {
    set_flash('error', 'Vendor profile not found.');
    redirect($base_url . '/index.php');
}

// ─── Fetch vendor name ──────────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT vendor_name FROM vendors WHERE vendor_id = ?');
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─── Metrics ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT COUNT(*) AS cnt FROM products WHERE vendor_id = ? AND is_available = 1'
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$total_products = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT oi.order_id) AS cnt
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     WHERE oi.vendor_id = ? AND o.order_status = 'pending'"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$pending_orders = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(oi.line_total), 0) AS total
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     WHERE oi.vendor_id = ? AND o.order_status NOT IN ('cancelled', 'refunded')"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$total_revenue = (float) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(oi.line_total), 0) AS total
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     WHERE oi.vendor_id = ?
       AND o.order_status NOT IN ('cancelled', 'refunded')
       AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$revenue_30d = (float) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT oi.order_id) AS order_count,
            COALESCE(SUM(oi.quantity), 0) AS items_sold
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     WHERE oi.vendor_id = ? AND o.order_status NOT IN ('cancelled', 'refunded')"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$sales_stats = $stmt->get_result()->fetch_assoc();
$total_orders = (int) $sales_stats['order_count'];
$items_sold   = (int) $sales_stats['items_sold'];
$stmt->close();

// ─── Recent Orders ──────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_status, o.order_date,
            u.full_name AS customer_name,
            SUM(oi.line_total) AS vendor_total,
            SUM(oi.quantity) AS vendor_items
     FROM order_items oi
     JOIN orders o    ON oi.order_id = o.order_id
     JOIN customers c ON o.customer_id = c.customer_id
     JOIN users u     ON c.user_id = u.user_id
     WHERE oi.vendor_id = ?
     GROUP BY o.order_id, o.order_status, o.order_date, u.full_name
     ORDER BY o.order_date DESC
     LIMIT 10"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$recent_orders = $stmt->get_result();
$stmt->close();

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

<div class="row">
    <div class="col-lg-10 mx-auto">

        <h1 class="mb-4">Vendor Dashboard</h1>

        <!-- Welcome card -->
        <div class="card shadow-sm mb-4 card-top-accent-green">
            <div class="card-body">
                <h5 class="card-title">
                    Welcome, <?= htmlspecialchars($vendor['vendor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <p class="text-muted mb-0">
                    Signed in as <?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    &middot; <span class="badge bg-success">Vendor</span>
                </p>
            </div>
        </div>

        <!-- ─── Metric Cards ─────────────────────────────────────────────── -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-4">
                <a href="<?= $base_url ?>/vendor-portal/products.php" class="text-decoration-none">
                    <div class="card text-center h-100 card-top-accent-green">
                        <div class="card-body">
                            <div class="metric-value"><?= $total_products ?></div>
                            <div class="metric-label">Total Products</div>
                            <div class="metric-sublabel">Active listings</div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 card-top-accent-earth">
                    <div class="card-body">
                        <div class="metric-value text-accent-earth"><?= $pending_orders ?></div>
                        <div class="metric-label">Pending Orders</div>
                        <div class="metric-sublabel">Awaiting processing</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <div class="metric-value">$<?= number_format($total_revenue, 2) ?></div>
                        <div class="metric-label">Total Revenue</div>
                        <div class="metric-sublabel">All time</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <div class="metric-value">$<?= number_format($revenue_30d, 2) ?></div>
                        <div class="metric-label">Recent Revenue</div>
                        <div class="metric-sublabel">Last 30 days</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <div class="metric-value"><?= $total_orders ?></div>
                        <div class="metric-label">Total Orders</div>
                        <div class="metric-sublabel">Completed &amp; in progress</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <div class="metric-value"><?= $items_sold ?></div>
                        <div class="metric-label">Items Sold</div>
                        <div class="metric-sublabel">Total units</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="d-flex gap-2 mb-4 flex-wrap">
            <a href="<?= $base_url ?>/vendor-portal/products.php" class="btn btn-success">
                Manage Products
            </a>
            <a href="<?= $base_url ?>/vendor-detail.php?vendor_id=<?= $vendor_id ?>" class="btn btn-outline-success">
                View My Storefront
            </a>
        </div>

        <!-- ─── Recent Orders ────────────────────────────────────────────── -->
        <h4 class="mb-3">Recent Orders</h4>

        <?php if ($recent_orders->num_rows > 0): ?>
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Your Items</th>
                                <th>Your Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $recent_orders->fetch_assoc()):
                                $badge = $status_badges[$order['order_status']] ?? 'bg-secondary';
                            ?>
                                <tr>
                                    <td><strong>#<?= (int) $order['order_id'] ?></strong></td>
                                    <td><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                                    <td><?= (int) $order['vendor_items'] ?> item<?= (int) $order['vendor_items'] !== 1 ? 's' : '' ?></td>
                                    <td>$<?= number_format((float) $order['vendor_total'], 2) ?></td>
                                    <td>
                                        <span class="badge <?= $badge ?>">
                                            <?= ucfirst(htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8')) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No orders yet. Once customers purchase your products, orders will appear here.
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
