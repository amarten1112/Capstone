<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Vendors';

// Search/filter
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');

// Build query
$where  = ['v.verified = 1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(v.vendor_name LIKE ? OR v.description LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($category !== '') {
    $where[]  = 'v.category_text = ?';
    $params[] = $category;
    $types   .= 's';
}

$sql = 'SELECT * FROM vendors v WHERE ' . implode(' AND ', $where) . ' ORDER BY v.vendor_name ASC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$vendors = $stmt->get_result();
$stmt->close();

// Get distinct categories for filter dropdown
$cat_result = $conn->query("SELECT DISTINCT category_text FROM vendors WHERE verified = 1 AND category_text IS NOT NULL ORDER BY category_text ASC");

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Our Vendors</h1>
    <span class="text-muted"><?= $vendors->num_rows ?> vendor<?= $vendors->num_rows !== 1 ? 's' : '' ?> found</span>
</div>

<!-- Search & Filter -->
<form method="GET" action="vendors.php" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="text" class="form-control" name="search" placeholder="Search vendors..."
               value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-4">
        <select class="form-select" name="category">
            <option value="">All Categories</option>
            <?php while ($cat = $cat_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($cat['category_text'], ENT_QUOTES, 'UTF-8') ?>"
                    <?= $category === $cat['category_text'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_text'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-success w-100">Filter</button>
        <?php if ($search || $category): ?>
            <a href="vendors.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Vendor Grid -->
<?php if ($vendors->num_rows > 0): ?>
    <div class="row g-4">
        <?php while ($vendor = $vendors->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm vendor-card">
                    <?php if (!empty($vendor['image_url'])): ?>
                        <img src="<?= htmlspecialchars($vendor['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                             class="card-img-top vendor-card-image"
                             alt="<?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                        <div class="card-img-top vendor-card-image-placeholder">
                            <span class="text-muted">No image</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <?php if (!empty($vendor['category_text'])): ?>
                            <span class="badge bg-success mb-2"><?= htmlspecialchars($vendor['category_text'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <p class="card-text text-muted small">
                            <?php
                            $desc = htmlspecialchars($vendor['description'] ?? '', ENT_QUOTES, 'UTF-8');
                            echo strlen($desc) > 120 ? substr($desc, 0, 120) . '...' : $desc;
                            ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="vendor-detail.php?vendor_id=<?= (int)$vendor['vendor_id'] ?>" class="btn btn-sm btn-success">View Details</a>
                        <a href="contact.php?vendor_id=<?= (int)$vendor['vendor_id'] ?>" class="btn btn-sm btn-outline-secondary">Contact</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        No vendors found<?= $search || $category ? ' matching your search' : '' ?>.
        <?php if ($search || $category): ?>
            <a href="vendors.php">View all vendors</a>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
