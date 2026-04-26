<?php
/**
 * admin/applications.php — Vendor Application Review
 * Virginia Market Square
 *
 * Lists vendor applications submitted via vendor-apply.php.
 * Admin can approve or reject each application.
 * Approval does NOT auto-create a vendor account — admin must do that manually.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

$page_title = 'Vendor Applications';

// ─── Filter ───────────────────────────────────────────────────────────────────
$allowed_filters = ['pending', 'approved', 'rejected', 'all'];
$filter = in_array($_GET['filter'] ?? '', $allowed_filters, true) ? $_GET['filter'] : 'pending';

$where = match($filter) {
    'pending'  => "WHERE application_status = 'pending'",
    'approved' => "WHERE application_status = 'approved'",
    'rejected' => "WHERE application_status = 'rejected'",
    default    => '',
};

$result = $conn->query(
    "SELECT application_id, applicant_name, applicant_email, applicant_phone,
            business_name, business_description, business_category,
            miles_from_virginia, application_status, admin_notes,
            submitted_date, reviewed_date
     FROM vendor_applications
     $where
     ORDER BY submitted_date DESC"
);
$applications = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// ─── Tab counts ───────────────────────────────────────────────────────────────
$counts = [];
foreach (['pending', 'approved', 'rejected', 'all'] as $f) {
    $w = match($f) {
        'pending'  => "WHERE application_status = 'pending'",
        'approved' => "WHERE application_status = 'approved'",
        'rejected' => "WHERE application_status = 'rejected'",
        default    => '',
    };
    $r = $conn->query("SELECT COUNT(*) AS cnt FROM vendor_applications $w");
    $counts[$f] = $r ? (int) $r->fetch_assoc()['cnt'] : 0;
}

include '../includes/header.php';
?>

<!-- Admin Sub-nav -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-2">
    <h1 class="mb-0 fs-3">Vendor Applications</h1>
    <div class="d-flex gap-2">
        <a href="<?= $base_url ?>/admin/dashboard.php"
           class="btn btn-sm btn-outline-secondary">Dashboard</a>
        <a href="<?= $base_url ?>/admin/vendors.php"
           class="btn btn-sm btn-outline-secondary">Vendors</a>
        <a href="<?= $base_url ?>/admin/applications.php"
           class="btn btn-sm btn-success">Applications</a>
        <a href="<?= $base_url ?>/admin/users.php"
           class="btn btn-sm btn-outline-secondary">Users</a>
    </div>
</div>

<?php
// Show new vendor credentials if approval just happened
if (!empty($_SESSION['new_vendor'])):
    $nv = $_SESSION['new_vendor'];
    unset($_SESSION['new_vendor']);
?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <h5 class="alert-heading mb-2">Vendor account created for <?= htmlspecialchars($nv['business'], ENT_QUOTES, 'UTF-8') ?></h5>
    <p class="mb-1">Send these login credentials to <strong><?= htmlspecialchars($nv['name'], ENT_QUOTES, 'UTF-8') ?></strong>:</p>
    <ul class="mb-2">
        <li><strong>Email:</strong> <?= htmlspecialchars($nv['email'], ENT_QUOTES, 'UTF-8') ?></li>
        <li><strong>Temp password:</strong> <code><?= htmlspecialchars($nv['password'], ENT_QUOTES, 'UTF-8') ?></code></li>
    </ul>
    <p class="mb-0 small">The vendor should log in and change their password immediately.</p>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php
$flash = get_flash();
if ($flash):
?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filter tabs -->
<ul class="nav nav-pills mb-4">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label): ?>
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

<?php if (empty($applications)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-muted p-4">No <?= $filter !== 'all' ? $filter : '' ?> applications found.</div>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-3">
    <?php foreach ($applications as $app):
        $status_class = match($app['application_status']) {
            'approved' => 'success',
            'rejected' => 'danger',
            default    => 'warning',
        };
    ?>
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <span class="fw-semibold"><?= htmlspecialchars($app['business_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-muted small ms-2">by <?= htmlspecialchars($app['applicant_name'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <span class="badge bg-<?= $status_class ?>">
                    <?= ucfirst($app['application_status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="small text-muted mb-1">Contact</div>
                        <div><?= htmlspecialchars($app['applicant_email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($app['applicant_phone']): ?>
                            <div class="text-muted small"><?= htmlspecialchars($app['applicant_phone'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted mb-1">Category</div>
                        <div><?= htmlspecialchars($app['business_category'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($app['miles_from_virginia'] !== null): ?>
                            <div class="text-muted small"><?= number_format((float) $app['miles_from_virginia'], 1) ?> miles from Virginia, MN</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="small text-muted mb-1">Submitted</div>
                        <div class="small"><?= date('M j, Y g:i a', strtotime($app['submitted_date'])) ?></div>
                        <?php if ($app['reviewed_date']): ?>
                            <div class="text-muted small">Reviewed <?= date('M j, Y', strtotime($app['reviewed_date'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($app['business_description']): ?>
                    <div class="col-12">
                        <div class="small text-muted mb-1">Description</div>
                        <div class="small"><?= nl2br(htmlspecialchars($app['business_description'], ENT_QUOTES, 'UTF-8')) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($app['admin_notes']): ?>
                    <div class="col-12">
                        <div class="small text-muted mb-1">Admin Notes</div>
                        <div class="small text-secondary"><?= nl2br(htmlspecialchars($app['admin_notes'], ENT_QUOTES, 'UTF-8')) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($app['application_status'] === 'pending'): ?>
                <hr>
                <form action="<?= $base_url ?>/admin/application-action.php" method="POST">
                    <input type="hidden" name="csrf_token"      value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="application_id"  value="<?= (int) $app['application_id'] ?>">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Admin Notes (optional, internal only)</label>
                        <textarea class="form-control form-control-sm" name="admin_notes" rows="2"
                                  placeholder="Reason for approval/rejection, follow-up needed, etc."><?= htmlspecialchars($app['admin_notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="approve"
                                class="btn btn-success btn-sm">Approve</button>
                        <button type="submit" name="action" value="reject"
                                class="btn btn-outline-danger btn-sm">Reject</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
