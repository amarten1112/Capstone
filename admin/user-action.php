<?php
/**
 * admin/user-action.php — User Action Handler
 * Virginia Market Square
 *
 * POST-only. Handles toggle_active for a user account.
 * Prevents the logged-in admin from suspending their own account.
 * Redirects back to admin/users.php after processing.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($base_url . '/admin/users.php');
}

if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Invalid form submission. Please try again.');
    redirect($base_url . '/admin/users.php');
}

$target_user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$action         = trim($_POST['action'] ?? '');
$filter         = $_POST['redirect_filter'] ?? 'all';

$allowed_filters = ['all', 'customer', 'vendor', 'admin'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

$redirect = $base_url . '/admin/users.php?filter=' . $filter;

if ($target_user_id <= 0 || $action !== 'toggle_active') {
    set_flash('error', 'Invalid request.');
    redirect($redirect);
}

// Prevent self-suspension
if ($target_user_id === get_current_user_id()) {
    set_flash('error', 'You cannot suspend your own account.');
    redirect($redirect);
}

// Fetch current state
$stmt = $conn->prepare(
    "SELECT user_id, full_name, user_type, is_active FROM users WHERE user_id = ?"
);
$stmt->bind_param('i', $target_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    set_flash('error', 'User not found.');
    redirect($redirect);
}

$name    = htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8');
$new_val = $user['is_active'] ? 0 : 1;

$stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE user_id = ?');
$stmt->bind_param('ii', $new_val, $target_user_id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    set_flash('success', $new_val
        ? "$name's account has been reactivated."
        : "$name's account has been suspended."
    );
} else {
    set_flash('error', 'Could not update user. Please try again.');
}

redirect($redirect);
