<?php
/**
 * TEMPORARY DIAGNOSTIC — DELETE THIS FILE IMMEDIATELY AFTER USE
 * Do not leave this file on a public server.
 */

require_once 'includes/config.php';

echo '<pre>';

// 1. Check DB connection
echo "DB connected: YES\n\n";

// 2. Look up admin user
$stmt = $conn->prepare("SELECT user_id, email, password_hash, is_active FROM users WHERE email = 'admin@virginiamn.gov'");
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo "ERROR: admin@virginiamn.gov not found in users table.\n";
    echo "The seed data may not have run correctly on this server.\n";
} else {
    echo "User found: YES\n";
    echo "is_active: " . $user['is_active'] . "\n";
    echo "Hash length: " . strlen($user['password_hash']) . " (expected 60)\n";
    echo "Hash: " . $user['password_hash'] . "\n\n";

    $passwords = ['Admin1234!', 'Vendor1234!', 'Customer1234!'];
    foreach ($passwords as $pw) {
        $result = password_verify($pw, $user['password_hash']) ? 'MATCH' : 'no match';
        echo "password_verify('$pw'): $result\n";
    }
}

echo "\nPHP version: " . PHP_VERSION . "\n";
echo '</pre>';
?>
