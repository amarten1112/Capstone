<?php
/**
 * config.php - Database Connection & API Configuration
 * 
 * This file contains database connection settings and API keys.
 * Include this file at the top of every PHP page that needs database access.
 * 
 * SECURITY: This file is in .gitignore — never commit it to GitHub.
 * 
 * Usage: include 'includes/config.php';
 */


// Database connection details
$db_host = '127.0.0.1';      // Use numeric IP to force TCP connection
$db_port = 3306;             // MySQL/MariaDB default port
$db_user = 'root';           // MySQL username (change for production)
$db_pass = 'root';               // MySQL password (change for production)
$db_name = 'farmers_market'; // Database name

// Create connection (explicit port to avoid socket vs TCP issues)
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8 for proper character encoding
$conn->set_charset("utf8");

// Optional: Set timezone to match your location
date_default_timezone_set('America/Chicago'); // For Minnesota

// Base URL — used for asset paths and links in subdirectory pages
// Change this value depending on environment:
//   XAMPP local:  '/Capstone'
//   IONOS live:   ''  (site is at domain root)
$base_url = '/Capstone';


// ─── Stripe API Keys (Phase 5) ──────────────────────────────────────────────
// Test-mode keys — safe for development.
// Production keys would come from environment variables on the server.
// These are NEVER committed to Git (config.php is in .gitignore).
define('STRIPE_PUBLIC_KEY', 'pk_test_51TEZQj5JbZXPz2KR2VomYqktnlFlTFm6ewE9SoIi3Ru9cScec8Y9AIecPJ7WZSLf9fKS7YhjqR3C3w9dNDpczubb00TGp6UInI');
define('STRIPE_SECRET_KEY', 'sk_test_51TEZQj5JbZXPz2KRt5x8hSW4ZLlpi2r9zgxBxMNh8ZW5wzQVrgK9i1PGy9BwjPmgEPziwsNoUMCu0jFRfxk63Qzn002HROHTvs');

// ─── Composer Autoloader ─────────────────────────────────────────────────────
// Loads the Stripe PHP SDK (installed via: composer require stripe/stripe-php)
// The path assumes composer.json is in the project root alongside includes/
$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}
?>