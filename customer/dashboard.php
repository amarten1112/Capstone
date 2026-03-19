<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

require_customer();

$user_id = get_current_user_id();

// Customer profile
$stmt = $conn->prepare(
    'SELECT customer_id, phone, address_line1, address_line2, city, state, zip, created_date
     FROM customers WHERE user_id = ?'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Order history
$stmt = $conn->prepare(
    'SELECT o.order_id, o.order_date, o.order_status,
            o.total_amount, COUNT(oi.item_id) AS item_count
     FROM orders o
     JOIN customers c ON o.customer_id = c.customer_id
     JOIN order_items oi ON o.order_id = oi.order_id
     WHERE c.user_id = ?
     GROUP BY o.order_id
     ORDER BY o.order_date DESC LIMIT 10'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'My Account';
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

<h1 class="mb-1" style="color: var(--primary-color);">
    Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>!
</h1>
<p class="text-muted mb-4">Manage your account and view your order history.</p>

<div class="row g-4 mb-5">

    <!-- Profile Card -->
    <div class="col-lg-4">
        <div class="card card-top-accent-green shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3" style="color: var(--primary-color);">My Profile</h2>

                <p class="mb-1">
                    <strong>Name:</strong>
                    <?= htmlspecialchars($_SESSION['full_name']) ?>
                </p>
                <p class="mb-1">
                    <strong>Email:</strong>
                    <?= htmlspecialchars($_SESSION['email']) ?>
                </p>

                <?php if (!empty($customer['phone'])): ?>
                    <p class="mb-1">
                        <strong>Phone:</strong>
                        <?= htmlspecialchars($customer['phone']) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($customer['address_line1'])): ?>
                    <p class="mb-1"><strong>Address:</strong></p>
                    <p class="mb-0 ms-2">
                        <?= htmlspecialchars($customer['address_line1']) ?><br>
                        <?php if (!empty($customer['address_line2'])): ?>
                            <?= htmlspecialchars($customer['address_line2']) ?><br>
                        <?php endif; ?>
                        <?php
                        $city_line = implode(', ', array_filter([
                            $customer['city'] ?? '',
                            $customer['state'] ?? '',
                        ]));
                        if ($city_line) echo htmlspecialchars($city_line) . '<br>';
                        if (!empty($customer['zip'])) echo htmlspecialchars($customer['zip']);
                        ?>
                    </p>
                <?php else: ?>
                    <p class="text-muted mb-1"><em>No address on file</em></p>
                <?php endif; ?>

                <?php if (!empty($customer['created_date'])): ?>
                    <hr>
                    <p class="text-muted mb-0 text-sm">
                        Member since <?= htmlspecialchars(date('F Y', strtotime($customer['created_date']))) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="col-lg-4">
        <div class="card card-top-accent-earth shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3" style="color: var(--primary-color);">Shop the Market</h2>
                <div class="d-grid gap-2">
                    <a href="../products.php" class="btn btn-outline-success">
                        🛒 Browse Products
                    </a>
                    <a href="../vendors.php" class="btn btn-outline-success">
                        🌿 Browse Vendors
                    </a>
                    <a href="../events.php" class="btn btn-outline-success">
                        📅 Upcoming Events
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Order History -->
<section class="mb-5">
    <h2 class="mb-3" style="color: var(--primary-color);">Order History</h2>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">
            You haven't placed any orders yet.
            <a href="../products.php" style="color: var(--primary-color);">Start shopping →</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= (int) $order['order_id'] ?></td>
                        <td class="text-nowrap">
                            <?= htmlspecialchars(date('M j, Y', strtotime($order['order_date']))) ?>
                        </td>
                        <td><?= (int) $order['item_count'] ?></td>
                        <td>$<?= number_format((float) $order['total_amount'], 2) ?></td>
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
                            $s     = $order['order_status'];
                            $color = $status_colors[$s] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>">
                                <?= htmlspecialchars(ucfirst($s)) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include '../includes/footer.php';
