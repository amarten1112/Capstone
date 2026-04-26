<?php
/**
 * admin/application-action.php — Vendor Application Action Handler
 * Virginia Market Square
 *
 * POST-only. Handles approve/reject actions for vendor_applications.
 * Redirects back to admin/applications.php after processing.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($base_url . '/admin/applications.php');
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid form submission. Please try again.');
    redirect($base_url . '/admin/applications.php');
}

$application_id = isset($_POST['application_id']) ? (int) $_POST['application_id'] : 0;
$action         = trim($_POST['action'] ?? '');
$admin_notes    = trim($_POST['admin_notes'] ?? '');

$allowed_actions = ['approve', 'reject'];
if ($application_id <= 0 || !in_array($action, $allowed_actions, true)) {
    set_flash('error', 'Invalid request.');
    redirect($base_url . '/admin/applications.php');
}

$stmt = $conn->prepare(
    "SELECT application_id, applicant_name, business_name, application_status
     FROM vendor_applications WHERE application_id = ?"
);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    set_flash('error', 'Application not found.');
    redirect($base_url . '/admin/applications.php');
}

$new_status = $action === 'approve' ? 'approved' : 'rejected';
$biz_name   = htmlspecialchars($app['business_name'], ENT_QUOTES, 'UTF-8');

$stmt = $conn->prepare(
    "UPDATE vendor_applications
     SET application_status = ?, admin_notes = ?, reviewed_date = NOW()
     WHERE application_id = ?"
);
$stmt->bind_param('ssi', $new_status, $admin_notes, $application_id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    set_flash('success', $action === 'approve'
        ? "$biz_name has been approved. Remember to create their vendor account."
        : "$biz_name's application has been rejected."
    );
} else {
    set_flash('error', 'Could not update application. Please try again.');
}

redirect($base_url . '/admin/applications.php');
