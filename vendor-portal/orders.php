<?php
/**
 * vendor-portal/orders.php — Vendor Order Fulfillment
 * Virginia Market Square
 *
 * Shows orders containing this vendor's products. Vendors advance their
 * fulfillment status (processing → shipped → delivered). The master
 * orders.order_status reflects the most-behind fulfillment across all vendors.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_vendor();

$page_title = 'My Orders';

$vendor_id = get_vendor_id();
if (!$vendor_id) {
    set_flash('error', 'Vendor profile not found.');
    redirect($base_url . '/index.php');
}

// ─── Filter ───────────────────────────────────────────────────────────────────
$allowed = ['all', 'processing', 'shipped', 'delivered'];
$filter  = in_array($_GET['filter'] ?? '', $allowed, true) ? $_GET['filter'] : 'all';

$where = $filter !== 'all' ? "AND f.status = '$filter'" : '';

// ─── Orders for this vendor ───────────────────────────────────────────────────
$result = $conn->query(
    "SELECT o.order_id, o.order_date, o.order_status,
            o.ship_name, o.ship_address1, o.ship_city, o.ship_state, o.ship_zip,
            f.fulfillment_id, f.status AS fulfillment_status, f.updated_at,
            u.full_name AS customer_name,
            SUM(oi.line_total) AS vendor_total
     FROM order_fulfillments f
     JOIN orders o       ON f.order_id      = o.order_id
     JOIN customers c    ON o.customer_id   = c.customer_id
     JOIN users u        ON c.user_id       = u.user_id
     JOIN order_items oi ON oi.order_id     = f.order_id
                        AND oi.vendor_id    = f.vendor_id
     WHERE f.vendor_id = $vendor_id $where
     GROUP BY o.order_id, o.order_date, o.order_status,
              o.ship_name, o.ship_address1, o.ship_city, o.ship_state, o.ship_zip,
              f.fulfillment_id, f.status, f.updated_at, u.full_name
     ORDER BY o.order_date DESC"
);
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// ─── Line items per order (for detail display) ────────────────────────────────
$order_ids = array_column($orders, 'order_id');
$items_by_order = [];
if (!empty($order_ids)) {
    $placeholders = implode(',', $order_ids);
    $items_result = $conn->query(
        "SELECT oi.order_id, oi.quantity, oi.price_each, oi.line_total,
                p.product_name
         FROM order_items oi
         JOIN products p ON oi.product_id = p.product_id
         WHERE oi.vendor_id = $vendor_id
           AND oi.order_id IN ($placeholders)
         ORDER BY oi.item_id"
    );
    while ($row = $items_result->fetch_assoc()) {
        $items_by_order[$row['order_id']][] = $row;
    }
}

// ─── Tab counts ───────────────────────────────────────────────────────────────
$counts = [];
foreach (['all', 'processing', 'shipped', 'delivered'] as $f) {
    $w = $f !== 'all' ? "AND f.status = '$f'" : '';
    $r = $conn->query(
        "SELECT COUNT(*) AS cnt FROM order_fulfillments f WHERE f.vendor_id = $vendor_id $w"
    );
    $counts[$f] = $r ? (int) $r->fetch_assoc()['cnt'] : 0;
}

$fulfillment_badges = [
    'processing' => 'bg-info text-dark',
    'shipped'    => 'bg-primary',
    'delivered'  => 'bg-success',
];

// Forward-only allowed next statuses per current status
$next_statuses = [
    'processing' => ['shipped' => 'Mark Shipped', 'delivered' => 'Mark Delivered'],
    'shipped'    => ['delivered' => 'Mark Delivered'],
    'delivered'  => [],
];

include '../includes/header.php';
?>

<div class="row">
<div class="col-lg-10 mx-auto">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h1 class="mb-0 fs-3">My Orders</h1>
    <div class="d-flex gap-2">
        <a href="<?= $base_url ?>/vendor-portal/dashboard.php"
           class="btn btn-sm btn-outline-secondary">Dashboard</a>
        <a href="<?= $base_url ?>/vendor-portal/products.php"
           class="btn btn-sm btn-outline-secondary">Products</a>
        <a href="<?= $base_url ?>/vendor-portal/orders.php"
           class="btn btn-sm btn-success">Orders</a>
    </div>
</div>

<?php
$flash = get_flash();
if ($flash):
    $cls = $flash['type'] === 'error' ? 'danger' : $flash['type'];
?>
<div class="alert alert-<?= $cls ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter tabs -->
<ul class="nav nav-pills mb-4">
    <?php foreach (['all' => 'All', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'] as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filter === $key ? 'active' : '' ?>"
               href="?filter=<?= $key ?>">
                <?= $label ?>
                <span class="badge <?= $filter === $key ? 'bg-white text-success' : 'bg-secondary' ?> ms-1">
                    <?= $counts[$key] ?>
                </span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($orders)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-muted p-4">
            No <?= $filter !== 'all' ? $filter : '' ?> orders found.
        </div>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-3">
    <?php foreach ($orders as $order):
        $fstatus  = $order['fulfillment_status'];
        $badge    = $fulfillment_badges[$fstatus] ?? 'bg-secondary';
        $nexts    = $next_statuses[$fstatus] ?? [];
        $locked   = in_array($order['order_status'], ['cancelled', 'refunded'], true);
        $items    = $items_by_order[$order['order_id']] ?? [];
    ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="fw-semibold">Order #<?= (int) $order['order_id'] ?></span>
                    <span class="text-muted small ms-2">
                        <?= date('M j, Y', strtotime($order['order_date'])) ?>
                    </span>
                    <?php if ($locked): ?>
                        <span class="badge bg-secondary ms-2">
                            <?= ucfirst($order['order_status']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <span class="badge <?= $badge ?>">
                    <?= ucfirst($fstatus) ?>
                </span>
            </div>

            <div class="card-body">
                <div class="row g-3">

                    <!-- Customer & shipping -->
                    <div class="col-md-5">
                        <div class="small text-muted mb-1">Ship to</div>
                        <div class="fw-semibold">
                            <?= htmlspecialchars($order['ship_name'] ?? $order['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php if ($order['ship_address1']): ?>
                        <div class="small text-muted">
                            <?= htmlspecialchars($order['ship_address1'], ENT_QUOTES, 'UTF-8') ?><br>
                            <?= htmlspecialchars($order['ship_city'] . ', ' . $order['ship_state'] . ' ' . $order['ship_zip'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Items -->
                    <div class="col-md-4">
                        <div class="small text-muted mb-1">Your items</div>
                        <?php foreach ($items as $item): ?>
                            <div class="small">
                                <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                &times; <?= (int) $item['quantity'] ?>
                                <span class="text-muted">
                                    ($<?= number_format((float) $item['line_total'], 2) ?>)
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div class="small fw-semibold mt-1">
                            Your total: $<?= number_format((float) $order['vendor_total'], 2) ?>
                        </div>
                    </div>

                    <!-- Status updated -->
                    <div class="col-md-3">
                        <div class="small text-muted mb-1">Last updated</div>
                        <div class="small">
                            <?= date('M j, Y g:i a', strtotime($order['updated_at'])) ?>
                        </div>
                    </div>

                </div>

                <!-- Action buttons -->
                <?php if (!$locked && !empty($nexts)): ?>
                <hr class="my-3">
                <form action="<?= $base_url ?>/vendor-portal/order-action.php" method="POST"
                      class="d-flex gap-2 flex-wrap">
                    <input type="hidden" name="csrf_token"      value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="order_id"        value="<?= (int) $order['order_id'] ?>">
                    <input type="hidden" name="fulfillment_id"  value="<?= (int) $order['fulfillment_id'] ?>">
                    <input type="hidden" name="redirect_filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($nexts as $new_status => $label): ?>
                        <button type="submit" name="new_status" value="<?= $new_status ?>"
                                class="btn btn-sm <?= $new_status === 'delivered' ? 'btn-success' : 'btn-outline-primary' ?>">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                </form>
                <?php elseif ($locked): ?>
                <div class="small text-muted mt-2">
                    This order has been <?= htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8') ?> — no further updates available.
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>
</div>

<?php include '../includes/footer.php'; ?>
