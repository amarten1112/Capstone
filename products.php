<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Products';

$search      = trim($_GET['search']      ?? '');
$category_id = isset($_GET['category'])  ? (int)$_GET['category'] : 0;

// Build query
$where  = ['p.is_available = 1'];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(p.product_name LIKE ? OR p.description LIKE ? OR v.vendor_name LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}
if ($category_id > 0) {
    $where[]  = 'p.category_id = ?';
    $params[] = $category_id;
    $types   .= 'i';
}

$sql = 'SELECT p.*, v.vendor_name, v.vendor_id, c.category_name
        FROM products p
        JOIN vendors v ON p.vendor_id = v.vendor_id AND v.verified = 1
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY p.featured DESC, p.product_name ASC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// Get categories for filter
$categories = $conn->query('SELECT * FROM categories ORDER BY sort_order ASC, category_name ASC');

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Products</h1>
    <span class="text-muted"><?= $products->num_rows ?> product<?= $products->num_rows !== 1 ? 's' : '' ?> found</span>
</div>

<!-- Search & Filter -->
<form method="GET" action="products.php" class="row g-2 mb-4">
    <div class="col-md-6">
        <input type="text" class="form-control" name="search" placeholder="Search products or vendors..."
               value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-md-4">
        <select class="form-select" name="category">
            <option value="">All Categories</option>
            <?php if ($categories): while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?= (int)$cat['category_id'] ?>"
                    <?= $category_id === (int)$cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endwhile; endif; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-success w-100">Filter</button>
        <?php if ($search || $category_id): ?>
            <a href="products.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($products->num_rows > 0): ?>
    <div class="row g-4">
        <?php while ($product = $products->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                             class="card-img-top" style="height:180px;object-fit:cover;"
                             alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <?php if ($product['featured']): ?>
                            <span class="badge bg-warning text-dark mb-1">Featured</span>
                        <?php endif; ?>
                        <h6 class="card-title mb-1"><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></h6>
                        <small class="text-muted">
                            by <a href="vendor-detail.php?vendor_id=<?= (int)$product['vendor_id'] ?>">
                                <?= htmlspecialchars($product['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </small>
                        <?php if (!empty($product['category_name'])): ?>
                            <br><span class="badge bg-light text-dark border mt-1"><?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <p class="card-text small text-muted mt-2">
                            <?php
                            $desc = htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8');
                            echo strlen($desc) > 80 ? substr($desc, 0, 80) . '...' : $desc;
                            ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                        <strong class="text-success">$<?= number_format((float)$product['price'], 2) ?></strong>
                        <?php if (!empty($product['unit'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        No products found<?= $search || $category_id ? ' matching your search' : '' ?>.
        <?php if ($search || $category_id): ?>
            <a href="products.php">View all products</a>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
