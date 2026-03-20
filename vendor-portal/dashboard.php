<?php
/**
 * vendor-portal/dashboard.php - Vendor Dashboard (Placeholder)
 *
 * Phase 3 placeholder — confirms authentication is working.
 * Will be built out fully in Phase 4.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Only vendors can access this page
require_vendor();

// Get vendor details for display
$vendor_id = get_vendor_id();
$vendor = null;
if ($vendor_id) {
    $stmt = $conn->prepare('SELECT vendor_name, business_email, verified FROM vendors WHERE vendor_id = ?');
    $stmt->bind_param('i', $vendor_id);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$page_title = 'Vendor Dashboard';
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <h1 class="mb-4">Vendor Dashboard</h1>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Vendor', ENT_QUOTES, 'UTF-8') ?></h5>
                <p class="text-muted mb-3">You are logged in as <strong>Vendor</strong>.</p>

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
                        <td class="text-muted">Vendor ID:</td>
                        <td><?= (int) $vendor_id ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Business:</td>
                        <td><?= htmlspecialchars($vendor['vendor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Status:</td>
                        <td>
                            <?php if ($vendor && $vendor['verified']): ?>
                                <span class="badge bg-success">Verified</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending Approval</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Role:</td>
                        <td><span class="badge bg-success">Vendor</span></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Placeholder links for future vendor features -->
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-earth">
                    <div class="card-body">
                        <h3>📦</h3>
                        <h6>My Products</h6>
                        <p class="small text-muted">Coming in Phase 4</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-earth">
                    <div class="card-body">
                        <h3>🛒</h3>
                        <h6>Orders</h6>
                        <p class="small text-muted">Coming in Phase 4</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center h-100 card-top-accent-earth">
                    <div class="card-body">
                        <h3>👤</h3>
                        <h6>My Profile</h6>
                        <p class="small text-muted">Coming in Phase 4</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
