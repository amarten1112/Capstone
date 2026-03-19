<?php
/**
 * auth.php — Authentication & Authorization
 * Virginia Market Square
 *
 * Provides: session management, login, logout, registration,
 * auth checks, role guards, user data helpers, password utilities,
 * input validation, CSRF protection, flash messages, redirect helper.
 *
 * Requires: includes/config.php (provides $conn mysqli instance, timezone)
 *
 * Include pattern:
 *   Root pages:    require_once 'includes/config.php';
 *                  require_once 'includes/auth.php';
 *   Subdirectories (admin/, vendor/, customer/):
 *                  require_once '../includes/config.php';
 *                  require_once '../includes/auth.php';
 *
 * Session keys standardized across the project:
 *   $_SESSION['user_id']    int                          — set by login_user()
 *   $_SESSION['email']      string                       — set by login_user()
 *   $_SESSION['user_type']  'admin'|'vendor'|'customer'  — set by login_user()
 *   $_SESSION['full_name']  string                       — set by login_user()
 *   $_SESSION['vendor_id']  int|null                     — set by login_user() / get_vendor_id()
 *   $_SESSION['flash']      array ['type','message']     — set by set_flash()
 *   $_SESSION['csrf_token'] string                       — set by generate_csrf_token()
 */


// =============================================================================
// SECTION 2 — Session Bootstrap
// =============================================================================

/**
 * Initialize the PHP session with secure cookie settings.
 * Called automatically when this file is included — do not call manually.
 */
function start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return; // Already started — no-op
    }

    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,          // Session cookie (expires when browser closes)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,  // HTTPS-only in production, off in XAMPP dev
        'httponly' => true,       // Not accessible via JavaScript
        'samesite' => 'Strict',   // Prevents CSRF via cross-site requests
    ]);

    session_start();
}

// Start the session immediately when this file is included
start_session();


// =============================================================================
// SECTION 3 — Password Utilities
// =============================================================================

/**
 * Hash a plaintext password using bcrypt.
 * Always use this instead of calling password_hash() directly.
 */
function hash_password(string $plain): string {
    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Verify a plaintext password against a stored bcrypt hash.
 * Returns false safely if either argument is empty or malformed.
 */
function verify_password(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}


// =============================================================================
// SECTION 4 — Input Validation
// =============================================================================

/**
 * Validate an email address format.
 * Does not check whether the address is taken — that is register_user()'s job.
 */
function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength rules.
 * Rules match the seed data format (Admin1234!, Vendor1234!, Customer1234!):
 *   - Minimum 8 characters
 *   - At least one uppercase letter
 *   - At least one digit
 *   - At least one non-alphanumeric (special) character
 *
 * @return array ['valid' => bool, 'errors' => string[]]
 */
function validate_password(string $password): array {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character.';
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}


// =============================================================================
// SECTION 5 — Auth Checks & Current User
// =============================================================================

/**
 * Check whether a user is currently logged in.
 * Lightweight in-session check — does not query the database.
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id'])
        && is_int($_SESSION['user_id'])
        && $_SESSION['user_id'] > 0;
}

/**
 * Get the logged-in user's ID.
 *
 * @return int|null user_id, or null if not logged in
 */
function get_current_user_id(): int|null {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Get the logged-in user's role.
 *
 * @return string|null 'admin'|'vendor'|'customer', or null if not logged in
 */
function get_current_user_type(): string|null {
    return $_SESSION['user_type'] ?? null;
}

/**
 * Fetch the full users row for the current session user.
 * Note: password_hash is excluded — never load it unless verifying.
 *
 * @return array|null Associative users row, or null if not logged in / not found
 */
function get_auth_user(): array|null {
    if (!is_logged_in()) {
        return null;
    }

    global $conn;

    $stmt = $conn->prepare(
        'SELECT user_id, email, full_name, user_type, created_date, last_login, is_active
         FROM users
         WHERE user_id = ?'
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

/**
 * Get the vendor_id for the currently logged-in vendor.
 * Result is cached in $_SESSION['vendor_id'] to avoid repeated queries.
 *
 * @return int|null vendor_id, or null if not a vendor or no vendors row found
 */
function get_vendor_id(): int|null {
    if (get_current_user_type() !== 'vendor') {
        return null;
    }

    // Return cached value if available
    if (isset($_SESSION['vendor_id']) && $_SESSION['vendor_id'] > 0) {
        return (int) $_SESSION['vendor_id'];
    }

    global $conn;

    $user_id = $_SESSION['user_id'];
    $stmt    = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result    = $stmt->get_result();
    $row       = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        $_SESSION['vendor_id'] = (int) $row['vendor_id'];
        return (int) $row['vendor_id'];
    }

    return null;
}


// =============================================================================
// SECTION 6 — Login & Logout
// =============================================================================

/**
 * Authenticate a user by email and password.
 * On success, populates $_SESSION and updates last_login in the DB.
 * On failure, returns the same generic error for wrong email and wrong
 * password to prevent user enumeration.
 *
 * @return array ['success' => bool, 'error' => string|null]
 */
function login_user(string $email, string $password): array {
    $email = trim($email);

    if (!validate_email($email)) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    global $conn;

    // Fetch user row by email
    $stmt = $conn->prepare(
        'SELECT user_id, email, password_hash, full_name, user_type, is_active
         FROM users
         WHERE email = ?'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    // No matching account
    if (!$user) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // Account suspended — give a specific message here (not user enumeration risk
    // because the existence check already passed; admin-suspended accounts should
    // know why they cannot log in)
    if (!$user['is_active']) {
        return [
            'success' => false,
            'error'   => 'Your account has been suspended. Please contact the market administrator.',
        ];
    }

    // Wrong password — same generic message as "no account found"
    if (!verify_password($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    // Prevent session fixation: generate a new session ID before writing data
    session_regenerate_id(true);

    // Populate session
    $_SESSION['user_id']   = (int) $user['user_id'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'] ?? '';

    // Pre-cache vendor_id so vendor pages don't need an extra query
    if ($user['user_type'] === 'vendor') {
        get_vendor_id();
    }

    // Update last_login — failure does not block the login
    $uid  = (int) $user['user_id'];
    $stmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'error' => null];
}

/**
 * Log out the current user.
 * Destroys the session and redirects to login.php.
 * This function never returns.
 */
function logout_user(): void {
    // Clear all session data
    $_SESSION = [];

    // Expire the session cookie in the browser
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    redirect('login.php');
}


// =============================================================================
// SECTION 7 — Registration
// =============================================================================

/**
 * Register a new user account.
 *
 * For vendors, $extra_data must include 'vendor_name' (required by DB schema).
 * Optional vendor fields: 'business_email', 'phone', 'description'.
 * Optional customer fields: 'phone', 'address_line1', 'city', 'state', 'zip'.
 * New vendor accounts are created with verified=0 — admin approval required.
 * Admin accounts have no separate profile table.
 *
 * @param  string $email
 * @param  string $password    Plaintext — will be validated and hashed
 * @param  string $full_name
 * @param  string $user_type   'admin'|'vendor'|'customer'
 * @param  array  $extra_data  Role-specific profile fields
 * @return array ['success' => bool, 'error' => string|null, 'user_id' => int|null]
 */
function register_user(
    string $email,
    string $password,
    string $full_name,
    string $user_type,
    array  $extra_data = []
): array {
    $allowed_types = ['admin', 'vendor', 'customer'];
    if (!in_array($user_type, $allowed_types, true)) {
        return ['success' => false, 'error' => 'Invalid account type.', 'user_id' => null];
    }

    $email     = trim($email);
    $full_name = trim($full_name);

    if (!validate_email($email)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.', 'user_id' => null];
    }

    $pw_check = validate_password($password);
    if (!$pw_check['valid']) {
        return ['success' => false, 'error' => $pw_check['errors'][0], 'user_id' => null];
    }

    if ($full_name === '') {
        return ['success' => false, 'error' => 'Full name is required.', 'user_id' => null];
    }

    if ($user_type === 'vendor' && empty(trim($extra_data['vendor_name'] ?? ''))) {
        return ['success' => false, 'error' => 'Business name is required for vendor accounts.', 'user_id' => null];
    }

    global $conn;

    // Check email uniqueness before opening a transaction
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->get_result()->fetch_assoc() && ($exists = true);
    $stmt->close();

    if (!empty($exists)) {
        return ['success' => false, 'error' => 'An account with this email address already exists.', 'user_id' => null];
    }

    $password_hash = hash_password($password);

    $conn->begin_transaction();

    try {
        // Insert into users
        $stmt = $conn->prepare(
            'INSERT INTO users (email, password_hash, full_name, user_type) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $email, $password_hash, $full_name, $user_type);
        $stmt->execute();
        $user_id = (int) $conn->insert_id;
        $stmt->close();

        if ($user_type === 'vendor') {
            $vendor_name     = trim($extra_data['vendor_name']);
            $business_email  = trim($extra_data['business_email'] ?? '');
            $phone           = trim($extra_data['phone'] ?? '');
            $description     = trim($extra_data['description'] ?? '');

            $stmt = $conn->prepare(
                'INSERT INTO vendors (user_id, vendor_name, business_email, phone, description, verified, featured)
                 VALUES (?, ?, ?, ?, ?, 0, 0)'
            );
            $stmt->bind_param('issss', $user_id, $vendor_name, $business_email, $phone, $description);
            $stmt->execute();
            $stmt->close();

        } elseif ($user_type === 'customer') {
            $phone         = trim($extra_data['phone'] ?? '');
            $address_line1 = trim($extra_data['address_line1'] ?? '');
            $city          = trim($extra_data['city'] ?? '');
            $state         = trim($extra_data['state'] ?? '');
            $zip           = trim($extra_data['zip'] ?? '');

            $stmt = $conn->prepare(
                'INSERT INTO customers (user_id, phone, address_line1, city, state, zip)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('isssss', $user_id, $phone, $address_line1, $city, $state, $zip);
            $stmt->execute();
            $stmt->close();
        }
        // admin: no separate profile table

        $conn->commit();
        return ['success' => true, 'error' => null, 'user_id' => $user_id];

    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => 'Registration failed. Please try again.', 'user_id' => null];
    }
}


// =============================================================================
// SECTION 8 — Role Guards
// =============================================================================

/**
 * Require the visitor to be logged in.
 * Preserves the intended URL so login.php can redirect back after success.
 * This function returns normally if the check passes, or exits via redirect.
 *
 * @param string $redirect  Login page path (relative to project root)
 */
function require_login(string $redirect = 'login.php'): void {
    if (is_logged_in()) {
        return;
    }

    set_flash('warning', 'Please log in to access that page.');

    $intended      = $_SERVER['REQUEST_URI'] ?? '';
    $redirect_url  = $intended !== ''
        ? $redirect . '?redirect=' . urlencode($intended)
        : $redirect;

    redirect($redirect_url);
}

/**
 * Require the logged-in user to have a specific role.
 * If the check fails, the user is redirected to their own dashboard
 * with a flash error — not to the login page (they are already logged in).
 *
 * @param string $role  'admin'|'vendor'|'customer'
 */
function require_role(string $role): void {
    require_login(); // Handles the not-logged-in case first

    if (get_current_user_type() === $role) {
        return; // Authorized
    }

    set_flash('error', 'You do not have permission to access that page.');

    // Redirect to their own area, not login
    $type = get_current_user_type();
    switch ($type) {
        case 'admin':
            redirect('admin/dashboard.php');
            break;
        case 'vendor':
            redirect('vendor/dashboard.php');
            break;
        case 'customer':
            redirect('customer/dashboard.php');
            break;
        default:
            redirect('index.php');
    }
}

/** Require the current user to be an admin. Redirects otherwise. */
function require_admin(): void {
    require_role('admin');
}

/** Require the current user to be a vendor. Redirects otherwise. */
function require_vendor(): void {
    require_role('vendor');
}

/** Require the current user to be a customer. Redirects otherwise. */
function require_customer(): void {
    require_role('customer');
}


// =============================================================================
// SECTION 9 — Redirect Helper
// =============================================================================

/**
 * Send a Location header and halt execution.
 * Always call exit() — without it PHP continues running after the header.
 *
 * @param string $url  Relative or absolute URL to redirect to
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit();
}


// =============================================================================
// SECTION 10 — Flash Messages
// =============================================================================

/**
 * Store a one-time flash message in the session.
 * The message is consumed (removed) the first time get_flash() is called.
 *
 * @param string $type     Alert context: 'success', 'error', 'warning', 'info'
 *                         Note: 'error' maps to Bootstrap class 'alert-danger'.
 *                         Render with: $alert_class = $flash['type'] === 'error' ? 'danger' : $flash['type'];
 * @param string $message  Human-readable message. Always htmlspecialchars() when echoing.
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Read and remove the pending flash message from the session.
 * Returns null if no flash is set. Always htmlspecialchars() the message when rendering.
 *
 * Typical usage in header.php:
 *   $flash = get_flash();
 *   if ($flash) {
 *       $cls = $flash['type'] === 'error' ? 'danger' : $flash['type'];
 *       echo '<div class="alert alert-' . htmlspecialchars($cls) . ' alert-dismissible fade show">';
 *       echo htmlspecialchars($flash['message']);
 *       echo '</div>';
 *   }
 *
 * @return array|null ['type' => string, 'message' => string], or null
 */
function get_flash(): array|null {
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Check whether a flash message is pending without consuming it.
 */
function has_flash(): bool {
    return isset($_SESSION['flash']);
}


// =============================================================================
// SECTION 11 — CSRF Protection
// =============================================================================

/**
 * Get (or create) the CSRF token for the current session.
 * Tokens are per-session, not per-request, so multiple open tabs stay valid.
 *
 * Usage in forms:
 *   <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
 *
 * @return string 64-character hex token
 */
function generate_csrf_token(): string {
    if (!empty($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }

    $token                 = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Validate a CSRF token submitted with a form against the session token.
 * Uses hash_equals() for timing-safe comparison.
 *
 * Usage in POST handlers:
 *   if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
 *       set_flash('error', 'Invalid form submission. Please try again.');
 *       redirect('current-page.php');
 *   }
 *
 * @param  string $token  Token from $_POST['csrf_token']
 * @return bool           true if valid, false otherwise
 */
function validate_csrf_token(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
