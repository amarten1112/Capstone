<?php
/**
 * config.example.php — Database Connection TEMPLATE
 * ===================================================
 * SAFE TO COMMIT TO GITHUB — contains no real credentials.
 *
 * PURPOSE:
 *   This file documents the exact structure of config.php so any
 *   developer (or your future self) can recreate it from scratch.
 *   config.php is blocked by .gitignore and will never appear in the repo.
 *
 * HOW TO USE:
 *   1. Copy this file:   includes/config.example.php
 *                    →   includes/config.php
 *   2. Replace every placeholder value with your real credentials
 *   3. Never commit config.php — it stays local or lives on the server only
 *
 * INCLUDE SYNTAX (from any PHP page):
 *   require_once 'includes/config.php';    // from project root pages
 *   require_once '../includes/config.php'; // from /admin/, /vendor/, /customer/
 */


// -----------------------------------------------------------------------------
// Database Connection Settings
// -----------------------------------------------------------------------------
// LOCAL DEV (XAMPP):
//   $db_host = '127.0.0.1'  — numeric IP forces TCP, avoids socket issues in XAMPP
//   $db_user = 'root'        — XAMPP default username
//   $db_pass = ''            — XAMPP default is blank password
//   $db_name = 'farmers_market'
//
// PRODUCTION (IONOS Shared Hosting):
//   Get all four values from IONOS control panel → Databases → MySQL
//   The host will look something like: db12345678.hosting-data.io
// -----------------------------------------------------------------------------

$db_host = 'YOUR_DB_HOST';        // XAMPP: '127.0.0.1' | IONOS: from hosting panel
$db_port = 3306;                   // Default MySQL/MariaDB port — do not change
$db_user = 'YOUR_DB_USERNAME';     // XAMPP: 'root' | IONOS: assigned by host
$db_pass = 'YOUR_DB_PASSWORD';     // XAMPP: '' (blank) | IONOS: assigned by host
$db_name = 'farmers_market';       // Same name on local and production

// Create the connection — $conn is used by every page that queries the database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Stop execution and show error if connection fails
// This surfaces clearly during development — consider a friendlier message in production
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set character encoding — matches your database charset setting
$conn->set_charset("utf8");

// Set server timezone — PHP date functions use this for timestamps
date_default_timezone_set('America/Chicago'); // Central Time (Minnesota)


// -----------------------------------------------------------------------------
// Stripe API Keys — Phase 5
// -----------------------------------------------------------------------------
// Get your test keys from: https://dashboard.stripe.com/test/apikeys
//   pk_test_ = publishable key (safe to use in frontend JavaScript)
//   sk_test_ = secret key (server-side PHP ONLY — never expose in HTML or JS)
//
// IONOS deployment: add these as GitHub Actions secrets:
//   STRIPE_PUBLIC_KEY and STRIPE_SECRET_KEY
// -----------------------------------------------------------------------------

define('STRIPE_PUBLIC_KEY', 'pk_test_YOUR_PUBLISHABLE_KEY_HERE');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE');

// -----------------------------------------------------------------------------
// Composer Autoloader — loads Stripe PHP SDK
// -----------------------------------------------------------------------------
// Install with: composer require stripe/stripe-php
// The vendor/ directory is in .gitignore — run composer install after cloning.
// -----------------------------------------------------------------------------

$autoload_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}
?>
