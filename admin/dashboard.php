<?php
/**
 * admin/dashboard.php — Admin Dashboard
 * Virginia Market Square
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

$page_title = 'Admin Dashboard';

// ─── Stats ───────────────────────────────────────────────────────────────────

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS cnt FROM vendors v
     JOIN users u ON v.user_id = u.user_id
     WHERE v.verified = 0 AND u.is_active = 1"
);
$stmt->execute();
$pending_vendors = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM vendors WHERE verified = 1");
$stmt->execute();
$verified_vendors = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM customers");
$stmt->execute();
$total_customers = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orders");
$stmt->execute();
$total_orders = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM contacts WHERE status = 'new'");
$stmt->execute();
$new_contacts = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM vendor_applications WHERE application_status = 'pending'");
$stmt->execute();
$pending_apps = (int) $stmt->get_result()->fetch_assoc()['cnt'];
$stmt->close();

// Recent orders (last 5)
$result = $conn->query(
    "SELECT o.order_id, o.order_date, o.total_amount, o.order_status,
            u.full_name AS customer_name
     FROM orders o
     JOIN customers c  ON o.customer_id = c.customer_id
     JOIN users    u   ON c.user_id     = u.user_id
     ORDER BY o.order_date DESC
     LIMIT 5"
);
$recent_orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

include '../includes/header.php';
?>

<!-- Admin Sub-nav -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <h1 class="mb-0 fs-3">Admin Dashboard</h1>
    <div class="d-flex gap-2">
        <a href="<?= $base_url ?>/admin/dashboard.php"
           class="btn btn-sm btn-success">Dashboard</a>
        <a href="<?= $base_url ?>/admin/vendors.php"
           class="btn btn-sm btn-outline-secondary">
            Vendors
            <?php if ($pending_vendors > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?= $pending_vendors ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $base_url ?>/admin/users.php"
           class="btn btn-sm btn-outline-secondary">Users</a>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">

    <div class="col-6 col-md-3">
        <div class="card h-100 text-center <?= $pending_vendors > 0 ? 'border-warning' : 'shadow-sm' ?>">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold <?= $pending_vendors > 0 ? 'text-warning' : 'text-success' ?>">
                    <?= $pending_vendors ?>
                </div>
                <div class="small text-muted">Pending Approval</div>
                <?php if ($pending_vendors > 0): ?>
                    <a href="<?= $base_url ?>/admin/vendors.php?filter=pending"
                       class="btn btn-warning btn-sm mt-2">Review</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100 text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success"><?= $verified_vendors ?></div>
                <div class="small text-muted">Active Vendors</div>
                <a href="<?= $base_url ?>/admin/vendors.php"
                   class="btn btn-outline-success btn-sm mt-2">Manage</a>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100 text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success"><?= $total_customers ?></div>
                <div class="small text-muted">Customers</div>
                <a href="<?= $base_url ?>/admin/users.php?filter=customer"
                   class="btn btn-outline-success btn-sm mt-2">View</a>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100 text-center">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success"><?= $total_orders ?></div>
                <div class="small text-muted">Total Orders</div>
            </div>
        </div>
    </div>

</div>

<!-- Secondary stats -->
<?php if ($new_contacts > 0 || $pending_apps > 0): ?>
<div class="row g-3 mb-4">
    <?php if ($new_contacts > 0): ?>
    <div class="col-md-4">
        <div class="alert alert-info mb-0 d-flex justify-content-between align-items-center">
            <span><strong><?= $new_contacts ?></strong> unread contact submission<?= $new_contacts !== 1 ? 's' : '' ?></span>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($pending_apps > 0): ?>
    <div class="col-md-4">
        <div class="alert alert-warning mb-0 d-flex justify-content-between align-items-center">
            <span><strong><?= $pending_apps ?></strong> pending vendor application<?= $pending_apps !== 1 ? 's' : '' ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Recent orders -->
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent Orders</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent_orders)): ?>
            <p class="text-muted p-4 mb-0">No orders yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                            <?php
                            $status_class = match($order['order_status']) {
                                'delivered'  => 'success',
                                'shipped'    => 'info',
                                'processing' => 'primary',
                                'cancelled'  => 'danger',
                                'refunded'   => 'secondary',
                                default      => 'warning',
                            };
                            ?>
                            <tr>
                                <td class="text-muted">#<?= (int) $order['order_id'] ?></td>
                                <td><?= htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-muted small">
                                    <?= date('M j, Y', strtotime($order['order_date'])) ?>
                                </td>
                                <td>$<?= number_format((float) $order['total_amount'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $status_class ?>">
                                        <?= htmlspecialchars(ucfirst($order['order_status']), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
