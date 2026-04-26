<?php
/**
 * admin/vendors.php — Vendor Management
 * Virginia Market Square
 *
 * Lists all vendors with their verification and featured status.
 * Actions (verify, feature) are handled by admin/vendor-action.php.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

$page_title = 'Manage Vendors';

// ─── Filter ───────────────────────────────────────────────────────────────────
$allowed_filters = ['all', 'pending', 'verified', 'featured'];
$filter = in_array($_GET['filter'] ?? '', $allowed_filters, true) ? $_GET['filter'] : 'all';

// ─── Build query ─────────────────────────────────────────────────────────────
$where = match($filter) {
    'pending'  => 'AND v.verified = 0 AND u.is_active = 1',
    'verified' => 'AND v.verified = 1',
    'featured' => 'AND v.featured = 1',
    default    => '',
};

$result = $conn->query(
    "SELECT v.vendor_id, v.vendor_name, v.business_email, v.category_text,
            v.verified, v.featured, v.created_date,
            u.user_id, u.full_name, u.email, u.is_active, u.last_login,
            (SELECT COUNT(*) FROM products p WHERE p.vendor_id = v.vendor_id) AS product_count
     FROM vendors v
     JOIN users u ON v.user_id = u.user_id
     WHERE 1=1 $where
     ORDER BY v.verified ASC, v.created_date DESC"
);
$vendors = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// ─── Tab counts ───────────────────────────────────────────────────────────────
$counts = [];
foreach (['all', 'verified', 'featured'] as $f) {
    $w = match($f) {
        'verified' => "AND v.verified = 1",
        'featured' => "AND v.featured = 1",
        default    => '',
    };
    $r = $conn->query(
        "SELECT COUNT(*) AS cnt FROM vendors v JOIN users u ON v.user_id = u.user_id WHERE 1=1 $w"
    );
    $counts[$f] = $r ? (int) $r->fetch_assoc()['cnt'] : 0;
}

// Pending Approval = pending vendor applications (separate table)
$r = $conn->query("SELECT COUNT(*) AS cnt FROM vendor_applications WHERE application_status = 'pending'");
$counts['pending'] = $r ? (int) $r->fetch_assoc()['cnt'] : 0;

include '../includes/header.php';
?>

<!-- Admin Sub-nav -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <h1 class="mb-0 fs-3">Manage Vendors</h1>
    <div class="d-flex gap-2">
        <a href="<?= $base_url ?>/admin/dashboard.php"
           class="btn btn-sm btn-outline-secondary">Dashboard</a>
        <a href="<?= $base_url ?>/admin/vendors.php"
           class="btn btn-sm btn-success">Vendors</a>
        <a href="<?= $base_url ?>/admin/applications.php"
           class="btn btn-sm btn-outline-secondary">Applications</a>
        <a href="<?= $base_url ?>/admin/users.php"
           class="btn btn-sm btn-outline-secondary">Users</a>
    </div>
</div>

<!-- Filter tabs -->
<ul class="nav nav-pills mb-4">
    <!-- Pending Approval links to applications page — applications are in a separate table -->
    <li class="nav-item">
        <a class="nav-link <?= $counts['pending'] > 0 ? 'text-warning' : '' ?>"
           href="<?= $base_url ?>/admin/applications.php?filter=pending">
            Pending Approval
            <span class="badge <?= $counts['pending'] > 0 ? 'bg-warning text-dark' : 'bg-secondary' ?> ms-1">
                <?= $counts['pending'] ?>
            </span>
        </a>
    </li>
    <?php foreach (['all' => 'All', 'verified' => 'Verified', 'featured' => 'Featured'] as $key => $label): ?>
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

<!-- Vendors table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($vendors)): ?>
            <p class="text-muted p-4 mb-0">No vendors found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vendor</th>
                            <th>Contact</th>
                            <th>Category</th>
                            <th class="text-center">Products</th>
                            <th class="text-center">Verified</th>
                            <th class="text-center">Featured</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $v): ?>
                            <tr class="<?= !$v['is_active'] ? 'table-secondary text-muted' : '' ?>">

                                <td>
                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($v['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?= htmlspecialchars($v['full_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php if (!$v['is_active']): ?>
                                        <span class="badge bg-secondary">Suspended</span>
                                    <?php endif; ?>
                                </td>

                                <td class="small">
                                    <?= htmlspecialchars($v['business_email'] ?: $v['email'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="small text-muted">
                                    <?= htmlspecialchars($v['category_text'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="text-center">
                                    <?= (int) $v['product_count'] ?>
                                </td>

                                <td class="text-center">
                                    <?php if ($v['verified']): ?>
                                        <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">No</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <?php if ($v['featured']): ?>
                                        <span class="badge bg-primary">Yes</span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>

                                <td class="small text-muted">
                                    <?= date('M j, Y', strtotime($v['created_date'])) ?>
                                </td>

                                <td>
                                    <div class="d-flex gap-1 flex-wrap">

                                        <!-- Verify / Unverify -->
                                        <form action="<?= $base_url ?>/admin/vendor-action.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token"  value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="vendor_id"   value="<?= (int) $v['vendor_id'] ?>">
                                            <input type="hidden" name="action"      value="toggle_verified">
                                            <input type="hidden" name="redirect_filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= $v['verified'] ? 'btn-outline-warning' : 'btn-success' ?>"
                                                    title="<?= $v['verified'] ? 'Remove verification' : 'Approve vendor' ?>">
                                                <?= $v['verified'] ? 'Unverify' : 'Approve' ?>
                                            </button>
                                        </form>

                                        <!-- Feature / Unfeature -->
                                        <form action="<?= $base_url ?>/admin/vendor-action.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token"  value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="vendor_id"   value="<?= (int) $v['vendor_id'] ?>">
                                            <input type="hidden" name="action"      value="toggle_featured">
                                            <input type="hidden" name="redirect_filter" value="<?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit"
                                                    class="btn btn-sm <?= $v['featured'] ? 'btn-outline-secondary' : 'btn-outline-primary' ?>"
                                                    title="<?= $v['featured'] ? 'Remove from featured' : 'Mark as featured' ?>">
                                                <?= $v['featured'] ? 'Unfeature' : 'Feature' ?>
                                            </button>
                                        </form>

                                        <!-- View public profile -->
                                        <a href="<?= $base_url ?>/vendor-detail.php?id=<?= (int) $v['vendor_id'] ?>"
                                           class="btn btn-sm btn-outline-secondary" target="_blank"
                                           title="View public profile">View</a>

                                    </div>
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
