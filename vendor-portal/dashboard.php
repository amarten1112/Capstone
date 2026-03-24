<?php
/**
 * vendor-portal/dashboard.php — Vendor Dashboard
 * Virginia Market Square
 *
 * Phase 4, Task 4.12
 *
 * Replaces Phase 3 placeholder. Shows:
 *   - Welcome message with vendor name
 *   - Metric cards: Total Products, Pending Orders, Total Revenue,
 *     Revenue (Last 30 Days), Total Orders, Items Sold
 *   - Recent Orders table (orders containing this vendor's products)
 *   - Quick links to product management and storefront
 *
 * All metrics are calculated via JOINs through order_items.vendor_id
 * (the denormalized FK designed specifically for this dashboard).
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

// ─── Metric: Total Products ─────────────────────────────────────────────────
$stmt = $conn->prepare(
    'SELECT COUNT(*) AS cnt FROM products WHERE vendor_id = ? AND is_available = 1'
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$total_products = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// ─── Metric: Pending Orders (orders with at least one of this vendor's items) ──
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

// ─── Metric: Total Revenue (all time) ───────────────────────────────────────
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

// ─── Metric: Revenue Last 30 Days ───────────────────────────────────────────
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

// ─── Metric: Total Orders + Items Sold ──────────────────────────────────────
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

// ─── Recent Orders (last 10 containing this vendor's products) ──────────────
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

// Status badge mapping
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
    <div class="col-md-10 mx-auto">

        <h1 class="mb-4">Vendor Dashboard</h1>

        <!-- Welcome card -->
        <div class="card shadow-sm mb-4">
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
            <div class="col-md-4">
                <a href="<?= $base_url ?>/vendor-portal/products.php" class="text-decoration-none">
                    <div class="card text-center h-100 card-top-accent-green">
                        <div class="card-body">
                            <h3 class="mb-2 text-success"><?= $total_products ?></h3>
                            <h6>Total Products</h6>
                            <p class="small text-muted mb-0">Active listings</p>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-earth">
                    <div class="card-body">
                        <h3 class="mb-2" style="color:var(--accent-color);"><?= $pending_orders ?></h3>
                        <h6>Pending Orders</h6>
                        <p class="small text-muted mb-0">Awaiting processing</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <h3 class="mb-2 text-success">$<?= number_format($total_revenue, 2) ?></h3>
                        <h6>Total Revenue</h6>
                        <p class="small text-muted mb-0">All time</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <h3 class="mb-2 text-success">$<?= number_format($revenue_30d, 2) ?></h3>
                        <h6>Recent Revenue</h6>
                        <p class="small text-muted mb-0">Last 30 days</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <h3 class="mb-2 text-success"><?= $total_orders ?></h3>
                        <h6>Total Orders</h6>
                        <p class="small text-muted mb-0">Completed &amp; in progress</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <h3 class="mb-2 text-success"><?= $items_sold ?></h3>
                        <h6>Items Sold</h6>
                        <p class="small text-muted mb-0">Total units</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="d-flex gap-2 mb-4">
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
                        <thead class="table-light">
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
