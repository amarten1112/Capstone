<?php
/**
 * admin/application-action.php — Vendor Application Action Handler
 * Virginia Market Square
 *
 * POST-only. Handles approve/reject for vendor_applications.
 * On approval, creates the user + vendor account automatically and stores
 * the temporary password in session for the admin to relay to the applicant.
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

if ($application_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    set_flash('error', 'Invalid request.');
    redirect($base_url . '/admin/applications.php');
}

$stmt = $conn->prepare(
    "SELECT application_id, applicant_name, applicant_email, applicant_phone,
            business_name, business_description, business_category,
            miles_from_virginia, application_status
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

if ($app['application_status'] !== 'pending') {
    set_flash('error', 'This application has already been reviewed.');
    redirect($base_url . '/admin/applications.php');
}

$biz_name = htmlspecialchars($app['business_name'], ENT_QUOTES, 'UTF-8');

// ─── Reject ───────────────────────────────────────────────────────────────────
if ($action === 'reject') {
    $new_status = 'rejected';
    $stmt = $conn->prepare(
        "UPDATE vendor_applications
         SET application_status = ?, admin_notes = ?, reviewed_date = NOW()
         WHERE application_id = ?"
    );
    $stmt->bind_param('ssi', $new_status, $admin_notes, $application_id);
    $ok = $stmt->execute();
    $stmt->close();

    set_flash($ok ? 'success' : 'error', $ok
        ? "$biz_name's application has been rejected."
        : 'Could not update application. Please try again.'
    );
    redirect($base_url . '/admin/applications.php');
}

// ─── Approve: create user + vendor account ────────────────────────────────────

// Guard: email must not already have an account
$stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
$stmt->bind_param('s', $app['applicant_email']);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    set_flash('error', "An account with {$app['applicant_email']} already exists. Approve the application manually.");
    redirect($base_url . '/admin/applications.php');
}

// Generate a temp password that always meets validation requirements:
// uppercase, digit, special char, 8+ chars total
function generate_temp_password(): string {
    $lower   = 'abcdefghjkmnpqrstuvwxyz'; // omit i, l for readability
    $upper   = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    $digits  = '23456789';                 // omit 0, 1 for readability
    $special = '!@#$';

    $pw  = $upper[random_int(0, strlen($upper) - 1)];
    $pw .= $upper[random_int(0, strlen($upper) - 1)];
    $pw .= $digits[random_int(0, strlen($digits) - 1)];
    $pw .= $digits[random_int(0, strlen($digits) - 1)];
    $pw .= $special[random_int(0, strlen($special) - 1)];
    $pw .= $lower[random_int(0, strlen($lower) - 1)];
    $pw .= $lower[random_int(0, strlen($lower) - 1)];
    $pw .= $lower[random_int(0, strlen($lower) - 1)];

    return str_shuffle($pw);
}

$temp_password = generate_temp_password();
$password_hash = hash_password($temp_password);

$conn->begin_transaction();

try {
    // Create user account
    $stmt = $conn->prepare(
        'INSERT INTO users (email, password_hash, full_name, user_type) VALUES (?, ?, ?, "vendor")'
    );
    $stmt->bind_param('sss', $app['applicant_email'], $password_hash, $app['applicant_name']);
    $stmt->execute();
    $user_id = (int) $conn->insert_id;
    $stmt->close();

    // Create vendor profile — verified=1 since admin explicitly approved
    $miles = $app['miles_from_virginia'];
    $stmt = $conn->prepare(
        'INSERT INTO vendors
             (user_id, vendor_name, business_email, phone, description, category_text, miles_from_va, verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $stmt->bind_param(
        'isssssd',
        $user_id,
        $app['business_name'],
        $app['applicant_email'],
        $app['applicant_phone'],
        $app['business_description'],
        $app['business_category'],
        $miles
    );
    $stmt->execute();
    $stmt->close();

    // Mark application approved
    $new_status = 'approved';
    $stmt = $conn->prepare(
        "UPDATE vendor_applications
         SET application_status = ?, admin_notes = ?, reviewed_date = NOW()
         WHERE application_id = ?"
    );
    $stmt->bind_param('ssi', $new_status, $admin_notes, $application_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Store credentials in session — consumed once by applications.php display
    $_SESSION['new_vendor'] = [
        'name'     => $app['applicant_name'],
        'email'    => $app['applicant_email'],
        'business' => $app['business_name'],
        'password' => $temp_password,
    ];

    redirect($base_url . '/admin/applications.php');

} catch (Throwable $e) {
    $conn->rollback();
    set_flash('error', 'Account creation failed. Please try again.');
    redirect($base_url . '/admin/applications.php');
}
