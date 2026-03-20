<?php
/**
 * admin/dashboard.php - Admin Dashboard (Placeholder)
 *
 * Phase 3 placeholder — confirms authentication is working.
 * Will be built out fully in Phase 4.
 */

// Subdirectory pages use ../ to reach includes
require_once '../includes/config.php';
require_once '../includes/auth.php';

// Only admins can access this page
require_admin();

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <h1 class="mb-4">Admin Dashboard</h1>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></h5>
                <p class="text-muted mb-3">You are logged in as <strong>Administrator</strong>.</p>

                <table class="table table-sm table-borderless mb-0" style="max-width: 400px;">
                    <tr>
                        <td class="text-muted">User ID:</td>
                        <td><?= (int) $_SESSION['user_id'] ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Email:</td>
                        <td><?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Role:</td>
                        <td><span class="badge bg-danger">Admin</span></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Placeholder links for future admin features -->
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <h3>👥</h3>
                        <h6>Manage Vendors</h6>
                        <p class="small text-muted">Coming in Phase 4</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <h3>📅</h3>
                        <h6>Manage Events</h6>
                        <p class="small text-muted">Coming in Phase 4</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-green">
                    <div class="card-body">
                        <h3>📬</h3>
                        <h6>Contact Submissions</h6>
                        <p class="small text-muted">Coming in Phase 4</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
