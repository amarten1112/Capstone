<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

// Handle POST: approve or reject a vendor application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid form submission.');
        redirect('dashboard.php');
    }

    $action         = $_POST['action'] ?? '';
    $application_id = (int) ($_POST['application_id'] ?? 0);

    if ($application_id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare(
            "UPDATE vendor_applications
             SET application_status = ?, reviewed_date = NOW()
             WHERE application_id = ?"
        );
        $stmt->bind_param('si', $new_status, $application_id);
        $stmt->execute();
        $stmt->close();

        $verb = $action === 'approve' ? 'approved' : 'rejected';
        set_flash('success', "Application #{$application_id} has been {$verb}.");
    } else {
        set_flash('error', 'Invalid action.');
    }

    redirect('dashboard.php');
}

// Stats
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM users');
$stmt->execute();
$total_users = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM vendor_applications WHERE application_status = 'pending'");
$stmt->execute();
$pending_apps = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM products');
$stmt->execute();
$total_products = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM events WHERE event_date >= CURDATE()');
$stmt->execute();
$upcoming_events = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Pending vendor applications
$stmt = $conn->prepare(
    "SELECT application_id, applicant_name, applicant_email, business_name,
            business_description, business_category, submitted_date
     FROM vendor_applications
     WHERE application_status = 'pending'
     ORDER BY submitted_date ASC
     LIMIT 10"
);
$stmt->execute();
$pending_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// New contact submissions
$stmt = $conn->prepare(
    "SELECT contact_id, name, email, subject, submitted_date
     FROM contacts
     WHERE status = 'new'
     ORDER BY submitted_date DESC
     LIMIT 5"
);
$stmt->execute();
$new_contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Upcoming events
$stmt = $conn->prepare(
    "SELECT event_id, event_name, event_date, event_time, event_type
     FROM events
     WHERE event_date >= CURDATE()
     ORDER BY event_date ASC
     LIMIT 5"
);
$stmt->execute();
$upcoming_event_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Admin Dashboard';
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

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 style="color: var(--primary-color);">Admin Dashboard</h1>
    <span class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
</div>

<!-- Stats Widgets -->
<div class="row g-3 mb-5">
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-green shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold" style="color: var(--primary-color);">
                    <?= $total_users ?>
                </div>
                <div class="text-muted mt-1">Total Users</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-earth shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold text-warning"><?= $pending_apps ?></div>
                <div class="text-muted mt-1">Pending Applications</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-green shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold" style="color: var(--secondary-color);">
                    <?= $total_products ?>
                </div>
                <div class="text-muted mt-1">Total Products</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card text-center card-top-accent-earth shadow-sm h-100">
            <div class="card-body">
                <div class="display-6 fw-bold" style="color: var(--accent-color);">
                    <?= $upcoming_events ?>
                </div>
                <div class="text-muted mt-1">Upcoming Events</div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Vendor Applications -->
<section class="mb-5">
    <h2 class="mb-3" style="color: var(--primary-color);">Pending Vendor Applications</h2>
    <?php if (empty($pending_applications)): ?>
        <div class="alert alert-info">No pending applications at this time.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Business</th>
                        <th>Category</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pending_applications as $app): ?>
                    <tr>
                        <td><?= (int) $app['application_id'] ?></td>
                        <td><?= htmlspecialchars($app['applicant_name']) ?></td>
                        <td>
                            <a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>">
                                <?= htmlspecialchars($app['applicant_email']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($app['business_name']) ?>
                            <?php if (!empty($app['business_description'])): ?>
                                <br><small class="text-muted">
                                    <?= htmlspecialchars(mb_substr($app['business_description'], 0, 80)) ?>
                                    <?= mb_strlen($app['business_description']) > 80 ? '…' : '' ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($app['business_category'] ?? '—') ?></td>
                        <td class="text-nowrap">
                            <?= htmlspecialchars(date('M j, Y', strtotime($app['submitted_date']))) ?>
                        </td>
                        <td class="text-nowrap">
                            <form method="POST" action="dashboard.php" class="d-inline">
                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <input type="hidden" name="application_id"
                                       value="<?= (int) $app['application_id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-sm btn-success me-1">Approve</button>
                            </form>
                            <form method="POST" action="dashboard.php" class="d-inline">
                                <input type="hidden" name="csrf_token"
                                       value="<?= htmlspecialchars(generate_csrf_token()) ?>">
                                <input type="hidden" name="application_id"
                                       value="<?= (int) $app['application_id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- New Contact Submissions -->
<section class="mb-5">
    <h2 class="mb-3" style="color: var(--primary-color);">New Contact Submissions</h2>
    <?php if (empty($new_contacts)): ?>
        <div class="alert alert-info">No new contact submissions.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($new_contacts as $contact): ?>
                    <tr>
                        <td><?= (int) $contact['contact_id'] ?></td>
                        <td><?= htmlspecialchars($contact['name']) ?></td>
                        <td>
                            <a href="mailto:<?= htmlspecialchars($contact['email']) ?>">
                                <?= htmlspecialchars($contact['email']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($contact['subject'] ?? '—') ?></td>
                        <td class="text-nowrap">
                            <?= htmlspecialchars(date('M j, Y', strtotime($contact['submitted_date']))) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Upcoming Events -->
<section class="mb-5">
    <h2 class="mb-3" style="color: var(--primary-color);">Upcoming Events</h2>
    <?php if (empty($upcoming_event_list)): ?>
        <div class="alert alert-info">No upcoming events scheduled.</div>
    <?php else: ?>
        <div class="row g-3">
        <?php foreach ($upcoming_event_list as $event): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-top-accent-green shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                        <p class="card-text text-muted mb-1">
                            📅 <?= htmlspecialchars(date('l, F j, Y', strtotime($event['event_date']))) ?>
                        </p>
                        <?php if (!empty($event['event_time'])): ?>
                            <p class="card-text text-muted mb-1">
                                🕐 <?= htmlspecialchars($event['event_time']) ?>
                            </p>
                        <?php endif; ?>
                        <span class="badge bg-secondary"><?= htmlspecialchars(str_replace('_', ' ', $event['event_type'])) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include '../includes/footer.php';
