<?php
/**
 * admin/users.php — User Management
 * Virginia Market Square
 *
 * Lists all users with their type and active status.
 * Suspend/activate actions are handled by admin/user-action.php.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

$page_title = 'Manage Users';

$allowed_filters = ['all', 'customer', 'vendor', 'admin'];
$filter = in_array($_GET['filter'] ?? '', $allowed_filters, true) ? $_GET['filter'] : 'all';

// ─── Build query ─────────────────────────────────────────────────────────────
$where = $filter !== 'all' ? "AND u.user_type = '$filter'" : '';

$result = $conn->query(
    "SELECT u.user_id, u.full_name, u.email, u.user_type,
            u.is_active, u.created_date, u.last_login,
            v.vendor_name
     FROM users u
     LEFT JOIN vendors v ON u.user_id = v.user_id AND u.user_type = 'vendor'
     WHERE 1=1 $where
     ORDER BY u.created_date DESC"
);
$users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// ─── Tab counts ───────────────────────────────────────────────────────────────
$counts = [];
foreach ($allowed_filters as $f) {
    $w = $f !== 'all' ? "AND user_type = '$f'" : '';
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE 1=1 $w");
    $counts[$f] = $r ? (int) $r->fetch_assoc()['cnt'] : 0;
}

$current_user_id = get_current_user_id();

include '../includes/header.php';
?>

<!-- Admin Sub-nav -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <h1 class="mb-0 fs-3">Manage Users</h1>
    <div class="d-flex gap-2">
        <a href="<?= $base_url ?>/admin/dashboard.php"
           class="btn btn-sm btn-outline-secondary">Dashboard</a>
        <a href="<?= $base_url ?>/admin/vendors.php"
           class="btn btn-sm btn-outline-secondary">Vendors</a>
        <a href="<?= $base_url ?>/admin/users.php"
           class="btn btn-sm btn-success">Users</a>
    </div>
</div>

<!-- Filter tabs -->
<ul class="nav nav-pills mb-4">
    <?php foreach (['all' => 'All', 'customer' => 'Customers', 'vendor' => 'Vendors', 'admin' => 'Admins'] as $key => $label): ?>
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

<!-- Users table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <p class="text-muted p-4 mb-0">No users found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Status</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <?php
                            $is_self = ((int) $u['user_id'] === $current_user_id);
                            $type_badge = match($u['user_type']) {
                                'admin'    => 'danger',
                                'vendor'   => 'primary',
                                'customer' => 'success',
                                default    => 'secondary',
                            };
                            ?>
                            <tr class="<?= !$u['is_active'] ? 'table-secondary' : '' ?>">

                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($is_self): ?>
                                            <span class="badge bg-secondary ms-1">You</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($u['vendor_name']): ?>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($u['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="small">
                                    <?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-<?= $type_badge ?>">
                                        <?= ucfirst($u['user_type']) ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Suspended</span>
                                    <?php endif; ?>
                                </td>

                                <td class="small text-muted">
                                    <?= date('M j, Y', strtotime($u['created_date'])) ?>
                                </td>

                                <td class="small text-muted">
                                    <?= $u['last_login']
                                        ? date('M j, Y', strtotime($u['last_login']))
                                        : '—' ?>
                                </td>

                                <td>
                                    <?php if ($is_self): ?>
                                        <span class="text-muted small">—</span>
                                    <?php else: ?>
                                        <form action="<?= $base_url ?>/admin/user-action.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token"       value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="user_id"          value="<?= (int) $u['user_id'] ?>">
                                            <input type="hidden" name="action"           value="toggle_active">
                                            <input type="hidden" name="redirect_filter"  value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                                <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
