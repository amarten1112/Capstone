<?php
/**
 * admin/vendor-action.php — Vendor Action Handler
 * Virginia Market Square
 *
 * POST-only. Handles toggle_verified and toggle_featured for a vendor.
 * Redirects back to admin/vendors.php after processing.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($base_url . '/admin/vendors.php');
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid form submission. Please try again.');
    redirect($base_url . '/admin/vendors.php');
}

$vendor_id = isset($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : 0;
$action    = trim($_POST['action'] ?? '');
$filter    = $_POST['redirect_filter'] ?? 'all';

$allowed_filters = ['all', 'pending', 'verified', 'featured'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

$redirect = $base_url . '/admin/vendors.php?filter=' . $filter;

if ($vendor_id <= 0 || !in_array($action, ['toggle_verified', 'toggle_featured'], true)) {
    set_flash('error', 'Invalid request.');
    redirect($redirect);
}

// Fetch current vendor state
$stmt = $conn->prepare(
    "SELECT v.vendor_id, v.vendor_name, v.verified, v.featured
     FROM vendors v WHERE v.vendor_id = ?"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vendor) {
    set_flash('error', 'Vendor not found.');
    redirect($redirect);
}

$name = htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8');

if ($action === 'toggle_verified') {
    $new_val = $vendor['verified'] ? 0 : 1;
    $stmt = $conn->prepare('UPDATE vendors SET verified = ? WHERE vendor_id = ?');
    $stmt->bind_param('ii', $new_val, $vendor_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        set_flash('success', $new_val
            ? "$name has been approved and is now visible to customers."
            : "$name has been unverified and hidden from customers."
        );
    } else {
        set_flash('error', 'Could not update vendor. Please try again.');
    }
}

if ($action === 'toggle_featured') {
    $new_val = $vendor['featured'] ? 0 : 1;
    $stmt = $conn->prepare('UPDATE vendors SET featured = ? WHERE vendor_id = ?');
    $stmt->bind_param('ii', $new_val, $vendor_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        set_flash('success', $new_val
            ? "$name is now featured on the homepage."
            : "$name has been removed from featured."
        );
    } else {
        set_flash('error', 'Could not update vendor. Please try again.');
    }
}

redirect($redirect);
