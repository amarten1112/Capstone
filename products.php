<?php
/**
 * products.php — Product Browsing Page
 * Virginia Market Square
 *
 * Phase 4, Tasks 4.1 + 4.2:
 *   4.1 — Pagination, improved JOINs, product detail links
 *   4.2 — Sort-by dropdown, price range filter, category counts
 *
 * Filter params (all GET):
 *   search   — keyword search across product name, description, vendor name
 *   category — category_id filter
 *   sort     — price_asc, price_desc, newest, name_asc (default: featured)
 *   min_price / max_price — price range filter
 *   page     — pagination offset
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$page_title = 'Products';

// ─── Collect filter inputs ──────────────────────────────────────────────────
$search      = trim($_GET['search']    ?? '');
$category_id = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$sort        = trim($_GET['sort']      ?? '');
$min_price   = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float) $_GET['min_price'] : null;
$max_price   = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float) $_GET['max_price'] : null;
$page        = isset($_GET['page'])     ? max(1, (int) $_GET['page']) : 1;

// Products per page — 12 fits a 4-column grid evenly (3 rows of 4)
$per_page = 12;

// ─── Allowed sort options ───────────────────────────────────────────────────
// Map URL param values to SQL ORDER BY clauses.
// 'featured' is the default: featured products first, then alphabetical.
$sort_options = [
    'featured'   => ['label' => 'Featured First',     'sql' => 'p.featured DESC, p.product_name ASC'],
    'price_asc'  => ['label' => 'Price: Low to High', 'sql' => 'p.price ASC, p.product_name ASC'],
    'price_desc' => ['label' => 'Price: High to Low', 'sql' => 'p.price DESC, p.product_name ASC'],
    'newest'     => ['label' => 'Newest First',        'sql' => 'p.created_date DESC'],
    'name_asc'   => ['label' => 'Name: A–Z',          'sql' => 'p.product_name ASC'],
];

// Fall back to 'featured' if the submitted sort value is invalid
if (!isset($sort_options[$sort])) {
    $sort = 'featured';
}

$order_by = $sort_options[$sort]['sql'];

// ─── Build WHERE clause (shared by count + data queries) ────────────────────
$where  = ['p.is_available = 1', 'p.stock_quantity > 0'];
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

// Price range filters — only add if the user entered a value
if ($min_price !== null && $min_price >= 0) {
    $where[]  = 'p.price >= ?';
    $params[] = $min_price;
    $types   .= 'd';   // 'd' = double (for DECIMAL columns)
}

if ($max_price !== null && $max_price > 0) {
    $where[]  = 'p.price <= ?';
    $params[] = $max_price;
    $types   .= 'd';
}

$where_clause = 'WHERE ' . implode(' AND ', $where);

// ─── Shared FROM/JOIN fragment ──────────────────────────────────────────────
// Used by both the COUNT query and the data query to stay in sync.
$from_clause = "FROM products p
                JOIN vendors v    ON p.vendor_id  = v.vendor_id AND v.verified = 1
                JOIN categories c ON p.category_id = c.category_id";

// ─── Count total matching products (for pagination math) ────────────────────
$count_sql = "SELECT COUNT(*) AS total $from_clause $where_clause";

$stmt = $conn->prepare($count_sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_products = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Calculate pagination values
$total_pages = max(1, (int) ceil($total_products / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$range_start = $total_products > 0 ? $offset + 1 : 0;
$range_end   = min($offset + $per_page, $total_products);

// ─── Fetch the current page of products ─────────────────────────────────────
$data_sql = "SELECT p.product_id, p.product_name, p.description, p.price,
                    p.image_url, p.unit, p.featured, p.stock_quantity,
                    p.category_id,
                    v.vendor_id, v.vendor_name,
                    c.category_name
             $from_clause
             $where_clause
             ORDER BY $order_by
             LIMIT ? OFFSET ?";

$data_params = array_merge($params, [$per_page, $offset]);
$data_types  = $types . 'ii';

$stmt = $conn->prepare($data_sql);
if ($data_params) {
    $stmt->bind_param($data_types, ...$data_params);
}
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

// ─── Get categories WITH product counts for the filter dropdown ─────────────
// This query counts how many available, in-stock products exist per category
// (from verified vendors). Categories with 0 matching products are still shown
// so the user can see the full list, but the count tells them what's available.
$cat_sql = "SELECT c.category_id, c.category_name,
                   COUNT(p.product_id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.category_id
                                 AND p.is_available = 1
                                 AND p.stock_quantity > 0
            LEFT JOIN vendors v  ON p.vendor_id = v.vendor_id
                                 AND v.verified = 1
            GROUP BY c.category_id, c.category_name
            ORDER BY c.sort_order ASC, c.category_name ASC";

$categories = $conn->query($cat_sql);

// ─── Helper: build URL preserving all current filters ───────────────────────
// Every filter control (pagination, sort, category badge) needs to carry
// forward all the other active filters so clicking one doesn't reset the rest.
function build_url(array $overrides = []): string {
    // Gather current filter state from globals
    global $search, $category_id, $sort, $min_price, $max_price;

    $params = [];
    if ($search !== '')       $params['search']    = $search;
    if ($category_id > 0)     $params['category']  = $category_id;
    if ($sort !== 'featured') $params['sort']       = $sort;
    if ($min_price !== null)  $params['min_price']  = $min_price;
    if ($max_price !== null)  $params['max_price']  = $max_price;

    // Apply overrides (e.g. changing the page number)
    foreach ($overrides as $key => $val) {
        if ($val === null || $val === '' || $val === 0) {
            unset($params[$key]);
        } else {
            $params[$key] = $val;
        }
    }

    // Don't include page=1 in the URL (it's the default)
    if (isset($params['page']) && (int) $params['page'] <= 1) {
        unset($params['page']);
    }

    $qs = http_build_query($params);
    return 'products.php' . ($qs !== '' ? '?' . $qs : '');
}

// Check whether any filters are active (to show the Clear button)
$has_filters = ($search !== '' || $category_id > 0
                || $sort !== 'featured'
                || $min_price !== null || $max_price !== null);

include 'includes/header.php';
?>

<!-- Page heading with result count -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Products</h1>
    <span class="text-muted">
        <?php if ($total_products > 0): ?>
            Showing <?= $range_start ?>–<?= $range_end ?> of <?= $total_products ?> product<?= $total_products !== 1 ? 's' : '' ?>
        <?php else: ?>
            0 products found
        <?php endif; ?>
    </span>
</div>

<!-- ─── Filter Form ──────────────────────────────────────────────────────── -->
<form method="GET" action="products.php" class="mb-4">

    <!-- Row 1: Search + Category + Buttons -->
    <div class="row g-2 mb-2">
        <div class="col-md-5">
            <input type="text" class="form-control" name="search"
                   placeholder="Search products or vendors..."
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-md-4">
            <select class="form-select" name="category">
                <option value="">All Categories</option>
                <?php if ($categories): while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= (int) $cat['category_id'] ?>"
                        <?= $category_id === (int) $cat['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= (int) $cat['product_count'] ?>)
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-success flex-grow-1">Filter</button>
            <?php if ($has_filters): ?>
                <a href="products.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Row 2: Sort + Price Range -->
    <div class="row g-2">
        <div class="col-md-3">
            <select class="form-select" name="sort">
                <?php foreach ($sort_options as $key => $opt): ?>
                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opt['label'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" name="min_price"
                       placeholder="Min" step="0.01" min="0"
                       value="<?= $min_price !== null ? htmlspecialchars($min_price, ENT_QUOTES, 'UTF-8') : '' ?>">
            </div>
        </div>
        <div class="col-md-2">
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control" name="max_price"
                       placeholder="Max" step="0.01" min="0"
                       value="<?= $max_price !== null ? htmlspecialchars($max_price, ENT_QUOTES, 'UTF-8') : '' ?>">
            </div>
        </div>
    </div>
</form>

<!-- ─── Product Grid ─────────────────────────────────────────────────────── -->
<?php if ($products->num_rows > 0): ?>
    <div class="row g-4">
        <?php while ($product = $products->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm">

                    <?php if (!empty($product['image_url'])): ?>
                        <a href="product-detail.php?id=<?= (int) $product['product_id'] ?>">
                            <img src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                 class="card-img-top" style="height:180px;object-fit:cover;"
                                 alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>">
                        </a>
                    <?php endif; ?>

                    <div class="card-body">
                        <?php if ($product['featured']): ?>
                            <span class="badge bg-warning text-dark mb-1">Featured</span>
                        <?php endif; ?>

                        <h6 class="card-title mb-1">
                            <a href="product-detail.php?id=<?= (int) $product['product_id'] ?>"
                               class="text-decoration-none text-dark">
                                <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h6>

                        <small class="text-muted">
                            by <a href="vendor-detail.php?vendor_id=<?= (int) $product['vendor_id'] ?>">
                                <?= htmlspecialchars($product['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </small>

                        <?php if (!empty($product['category_name'])): ?>
                            <br>
                            <a href="<?= htmlspecialchars(build_url(['category' => (int) $product['category_id'], 'page' => null]), ENT_QUOTES, 'UTF-8') ?>"
                               class="badge bg-light text-dark border mt-1 text-decoration-none">
                                <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endif; ?>

                        <p class="card-text small text-muted mt-2">
                            <?php
                            $desc = htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8');
                            echo strlen($desc) > 80 ? substr($desc, 0, 80) . '&hellip;' : $desc;
                            ?>
                        </p>
                    </div>

                    <div class="card-footer bg-transparent">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-success">$<?= number_format((float) $product['price'], 2) ?></strong>
                            <?php if (!empty($product['unit'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </div>
                        <a href="product-detail.php?id=<?= (int) $product['product_id'] ?>"
                           class="btn btn-outline-success btn-sm w-100 mt-2">
                            View Details
                        </a>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- ─── Pagination ───────────────────────────────────────────────────── -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Product pagination" class="mt-4">
            <ul class="pagination justify-content-center">

                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= $page > 1 ? htmlspecialchars(build_url(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') : '#' ?>">
                        &laquo; Prev
                    </a>
                </li>

                <?php
                $range = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    $show = ($i === 1 || $i === $total_pages || abs($i - $page) <= $range);
                    if (!$show) {
                        if ($i === 2 || $i === $total_pages - 1 || abs($i - $page) === $range + 1) {
                            echo '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
                        }
                        continue;
                    }
                ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="<?= htmlspecialchars(build_url(['page' => $i]), ENT_QUOTES, 'UTF-8') ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link"
                       href="<?= $page < $total_pages ? htmlspecialchars(build_url(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') : '#' ?>">
                        Next &raquo;
                    </a>
                </li>

            </ul>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <div class="alert alert-info">
        No products found<?= $has_filters ? ' matching your filters' : '' ?>.
        <?php if ($has_filters): ?>
            <a href="products.php">Clear all filters</a>.
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>