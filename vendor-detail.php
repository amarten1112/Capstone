<?php
/**
 * vendor-detail.php — Vendor Storefront Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.4
 *
 * Changes from Phase 2/3 version:
 *   - Replaced category_text badge with categories JOIN
 *   - Added product count in heading
 *   - Product cards now link to product-detail.php
 *   - Added "View Details" button matching products.php card style
 *   - Breadcrumb includes Home link
 *   - Product query uses INNER JOIN categories (not LEFT JOIN)
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

$vendor_id = isset($_GET['vendor_id']) ? (int) $_GET['vendor_id'] : 0;

if ($vendor_id <= 0) {
    set_flash('error', 'Vendor not found.');
    redirect($base_url . '/vendors.php');
}

// ─── Fetch vendor profile ───────────────────────────────────────────────────
$stmt = $conn->prepare('SELECT * FROM vendors WHERE vendor_id = ? AND verified = 1');
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vendor) {
    set_flash('error', 'Vendor not found.');
    redirect($base_url . '/vendors.php');
}

// ─── Get vendor's primary category via products JOIN ────────────────────────
// This replaces the old category_text column. We find the category that this
// vendor has the most products in — that's their "primary" category for display.
$stmt = $conn->prepare(
    "SELECT c.category_name, COUNT(*) AS cnt
     FROM products p
     JOIN categories c ON p.category_id = c.category_id
     WHERE p.vendor_id = ? AND p.is_available = 1
     GROUP BY c.category_id, c.category_name
     ORDER BY cnt DESC
     LIMIT 1"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$primary_cat = $stmt->get_result()->fetch_assoc();
$stmt->close();

$vendor_category = $primary_cat['category_name'] ?? $vendor['category_text'] ?? '';

// ─── Get vendor's available products ────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT p.product_id, p.product_name, p.description, p.price,
            p.image_url, p.unit, p.featured, p.stock_quantity,
            p.category_id,
            c.category_name
     FROM products p
     JOIN categories c ON p.category_id = c.category_id
     WHERE p.vendor_id = ? AND p.is_available = 1 AND p.stock_quantity > 0
     ORDER BY p.featured DESC, p.product_name ASC"
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

$page_title = $vendor['vendor_name'];
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= $base_url ?>/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="<?= $base_url ?>/vendors.php">Vendors</a></li>
        <li class="breadcrumb-item active" aria-current="page">
            <?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
        </li>
    </ol>
</nav>

<!-- ─── Vendor Header ────────────────────────────────────────────────────── -->
<div class="row mb-5">
    <div class="col-md-4 mb-4 mb-md-0">
        <?php if (!empty($vendor['image_url'])): ?>
            <img src="<?= htmlspecialchars($vendor['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                 class="img-fluid rounded shadow-sm w-100"
                 style="max-height:300px;object-fit:cover;"
                 alt="<?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
            <div class="bg-light rounded d-flex align-items-center justify-content-center"
                 style="height:250px;">
                <span class="text-muted">No image available</span>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-8">
        <h1 class="mb-2"><?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if ($vendor_category !== ''): ?>
            <span class="badge bg-success fs-6 mb-3">
                <?= htmlspecialchars($vendor_category, ENT_QUOTES, 'UTF-8') ?>
            </span>
        <?php endif; ?>

        <?php if ($vendor['featured']): ?>
            <span class="badge bg-warning text-dark fs-6 mb-3">Featured Vendor</span>
        <?php endif; ?>

        <?php if (!empty($vendor['description'])): ?>
            <p class="lead"><?= nl2br(htmlspecialchars($vendor['description'], ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>

        <ul class="list-unstyled mt-3">
            <?php if (!empty($vendor['business_email'])): ?>
                <li class="mb-1"><strong>Email:</strong>
                    <a href="mailto:<?= htmlspecialchars($vendor['business_email'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($vendor['business_email'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (!empty($vendor['phone'])): ?>
                <li class="mb-1"><strong>Phone:</strong>
                    <?= htmlspecialchars($vendor['phone'], ENT_QUOTES, 'UTF-8') ?>
                </li>
            <?php endif; ?>
            <?php if (!empty($vendor['website_url'])): ?>
                <li class="mb-1"><strong>Website:</strong>
                    <a href="<?= htmlspecialchars($vendor['website_url'], ENT_QUOTES, 'UTF-8') ?>"
                       target="_blank" rel="noopener">
                        <?= htmlspecialchars($vendor['website_url'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (!empty($vendor['miles_from_va'])): ?>
                <li class="mb-1"><strong>Distance:</strong>
                    <?= number_format((float) $vendor['miles_from_va'], 1) ?> miles from Virginia, MN
                </li>
            <?php endif; ?>
        </ul>

        <a href="<?= $base_url ?>/contact.php?vendor_id=<?= $vendor_id ?>"
           class="btn btn-success mt-2">Contact This Vendor</a>
    </div>
</div>

<!-- ─── Vendor's Products ────────────────────────────────────────────────── -->
<h2 class="mb-4">
    Products from <?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
    <small class="text-muted fs-6">(<?= $products->num_rows ?> item<?= $products->num_rows !== 1 ? 's' : '' ?>)</small>
</h2>

<?php if ($products->num_rows > 0): ?>
    <div class="row g-4">
        <?php while ($product = $products->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 shadow-sm">

                    <?php if (!empty($product['image_url'])): ?>
                        <a href="<?= $base_url ?>/product-detail.php?id=<?= (int) $product['product_id'] ?>">
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
                            <a href="<?= $base_url ?>/product-detail.php?id=<?= (int) $product['product_id'] ?>"
                               class="text-decoration-none text-dark">
                                <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h6>

                        <?php if (!empty($product['category_name'])): ?>
                            <a href="<?= $base_url ?>/products.php?category=<?= (int) $product['category_id'] ?>"
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
                        <a href="<?= $base_url ?>/product-detail.php?id=<?= (int) $product['product_id'] ?>"
                           class="btn btn-outline-success btn-sm w-100 mt-2">
                            View Details
                        </a>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <div class="alert alert-info">This vendor has no products listed yet.</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
