<?php
/**
 * header.php - Navigation Header (Included on every page)
 * 
 * This file contains the HTML header, navigation menu, and opening of the main container.
 * Include this at the top of the page body on every public page.
 * 
 * Usage: include 'includes/header.php';
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
        $base_title = 'Virginia Market Square';
        $full_title = isset($page_title) && $page_title !== ''
            ? $page_title . ' - ' . $base_title
            : $base_title;
        echo htmlspecialchars($full_title, ENT_QUOTES, 'UTF-8');
        ?>
    </title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark" aria-label="Main navigation">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            🌱 Virginia Market Square
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="vendors.php">Vendors</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="events.php">Events</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin/">Admin</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content Container -->
<main class="py-4">
    <div class="container">
