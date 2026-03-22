<?php
/**
 * vendors.php — Vendor Directory Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.4
 *
 * Changes from Phase 2/3 version:
 *   - Category filter now uses categories table via subquery instead
 *     of vendors.category_text (the legacy plain-text column)
 *   - Category dropdown populated from categories table with vendor counts
 *   - Added product count per vendor on each card
 *   - Vendor category badge derived from their most common product category
 *   - Added featured vendor badge
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Vendors';

// ─── Collect filter inputs ──────────────────────────────────────────────────
$search      = trim($_GET['search']   ?? '');
$category_id = isset($_GET['category']) ? (int) $_GET['category'] : 0;

// ─── Build WHERE clause ─────────────────────────────────────────────────────
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

// Category filter: find vendors who have at least one product in this category
// This replaces the old category_text = ? filter.
if ($category_id > 0) {
    $where[]  = 'v.vendor_id IN (
                    SELECT DISTINCT p.vendor_id
                    FROM products p
                    WHERE p.category_id = ? AND p.is_available = 1
                 )';
    $params[] = $category_id;
    $types   .= 'i';
}

$where_clause = 'WHERE ' . implode(' AND ', $where);

// ─── Fetch vendors ──────────────────────────────────────────────────────────
// Include a subquery to count each vendor's available products.
$sql = "SELECT v.*,
               (SELECT COUNT(*)
                FROM products p
                WHERE p.vendor_id = v.vendor_id
                  AND p.is_available = 1
                  AND p.stock_quantity > 0) AS product_count
        FROM vendors v
        $where_clause
        ORDER BY v.featured DESC, v.vendor_name ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$vendors = $stmt->get_result();
$stmt->close();

// ─── Get categories with vendor counts for the filter dropdown ──────────────
// Shows how many vendors sell products in each category.
$cat_sql = "SELECT c.category_id, c.category_name,
                   COUNT(DISTINCT p.vendor_id) AS vendor_count
            FROM categories c
            JOIN products p ON p.category_id = c.category_id
                            AND p.is_available = 1
            JOIN vendors v  ON p.vendor_id = v.vendor_id
                            AND v.verified = 1
            GROUP BY c.category_id, c.category_name
            ORDER BY c.sort_order ASC, c.category_name ASC";

$categories = $conn->query($cat_sql);

// ─── Preload primary category for each vendor ───────────────────────────────
// One query to get every vendor's most-common product category.
// We store the result in an associative array keyed by vendor_id.
$cat_map_sql = "SELECT p.vendor_id, c.category_name,
                       COUNT(*) AS cnt
                FROM products p
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.is_available = 1
                GROUP BY p.vendor_id, c.category_id, c.category_name
                ORDER BY p.vendor_id, cnt DESC";

$cat_map_result = $conn->query($cat_map_sql);
$vendor_categories = [];

while ($row = $cat_map_result->fetch_assoc()) {
    $vid = (int) $row['vendor_id'];
    // Only store the first row per vendor (highest count due to ORDER BY)
    if (!isset($vendor_categories[$vid])) {
        $vendor_categories[$vid] = $row['category_name'];
    }
}

$has_filters = ($search !== '' || $category_id > 0);

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Our Vendors</h1>
    <span class="text-muted">
        <?= $vendors->num_rows ?> vendor<?= $vendors->num_rows !== 1 ? 's' : '' ?> found
    </span>
</div>

<!-- Search & Category Filter -->
<form method="GET" action="vendors.php" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="text" class="form-control" name="search"
               placeholder="Search vendors..."
               value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-4">
        <select class="form-select" name="category">
            <option value="">All Categories</option>
            <?php if ($categories): while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?= (int) $cat['category_id'] ?>"
                    <?= $category_id === (int) $cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>
                    (<?= (int) $cat['vendor_count'] ?> vendor<?= (int) $cat['vendor_count'] !== 1 ? 's' : '' ?>)
                </option>
            <?php endwhile; endif; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-success w-100">Filter</button>
        <?php if ($has_filters): ?>
            <a href="vendors.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Vendor Grid -->
<?php if ($vendors->num_rows > 0): ?>
    <div class="row g-4">
        <?php while ($vendor = $vendors->fetch_assoc()): ?>
            <?php
            $vid = (int) $vendor['vendor_id'];
            // Use the preloaded category map; fall back to category_text if no products
            $cat_display = $vendor_categories[$vid] ?? $vendor['category_text'] ?? '';
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm vendor-card">

                    <?php if (!empty($vendor['image_url'])): ?>
                        <a href="vendor-detail.php?vendor_id=<?= $vid ?>">
                            <img src="<?= htmlspecialchars($vendor['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                 class="card-img-top vendor-card-image"
                                 style="height:200px;object-fit:cover;"
                                 alt="<?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>">
                        </a>
                    <?php else: ?>
                        <div class="card-img-top vendor-card-image-placeholder">
                            <span class="text-muted">No image</span>
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <h5 class="card-title mb-1">
                            <a href="vendor-detail.php?vendor_id=<?= $vid ?>"
                               class="text-decoration-none text-dark">
                                <?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h5>

                        <?php if ($cat_display !== ''): ?>
                            <span class="badge bg-success mb-1">
                                <?= htmlspecialchars($cat_display, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($vendor['featured']): ?>
                            <span class="badge bg-warning text-dark mb-1">Featured</span>
                        <?php endif; ?>

                        <!-- Product count -->
                        <small class="text-muted d-block mb-2">
                            <?= (int) $vendor['product_count'] ?> product<?= (int) $vendor['product_count'] !== 1 ? 's' : '' ?>
                        </small>

                        <p class="card-text text-muted small">
                            <?php
                            // Use short_bio if available, otherwise truncate description
                            $bio = $vendor['short_bio'] ?? $vendor['description'] ?? '';
                            $bio = htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');
                            echo strlen($bio) > 120 ? substr($bio, 0, 120) . '&hellip;' : $bio;
                            ?>
                        </p>
                    </div>

                    <div class="card-footer bg-transparent">
                        <a href="vendor-detail.php?vendor_id=<?= $vid ?>"
                           class="btn btn-sm btn-success">View Storefront</a>
                        <a href="contact.php?vendor_id=<?= $vid ?>"
                           class="btn btn-sm btn-outline-secondary">Contact</a>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        No vendors found<?= $has_filters ? ' matching your search' : '' ?>.
        <?php if ($has_filters): ?>
            <a href="vendors.php">View all vendors</a>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
