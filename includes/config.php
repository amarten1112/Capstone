<?php
/**
 * config.php - Database Connection Configuration
 * 
 * This file contains the database connection settings.
 * Include this file at the top of every PHP page that needs database access.
 * 
 * Usage: include 'includes/config.php';
 */


// Database connection details
$db_host = '127.0.0.1';      // Use numeric IP to force TCP connection
$db_port = 3306;             // MySQL/MariaDB default port
$db_user = 'root';           // MySQL username (change for production)
$db_pass = '';               // MySQL password (change for production)
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

?>
