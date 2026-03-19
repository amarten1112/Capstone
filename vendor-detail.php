<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($vendor_id <= 0) {
    set_flash('error', 'Vendor not found.');
    redirect('vendors.php');
}

// Get vendor
$stmt = $conn->prepare('SELECT * FROM vendors WHERE vendor_id = ? AND verified = 1');
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vendor) {
    set_flash('error', 'Vendor not found.');
    redirect('vendors.php');
}

// Get vendor's available products
$stmt = $conn->prepare(
    'SELECT p.*, c.category_name
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.category_id
     WHERE p.vendor_id = ? AND p.is_available = 1
     ORDER BY p.featured DESC, p.product_name ASC'
);
$stmt->bind_param('i', $vendor_id);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

$page_title = htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8');
include 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="vendors.php">Vendors</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?></li>
    </ol>
</nav>

<!-- Vendor Header -->
<div class="row mb-5">
    <div class="col-md-4 mb-4 mb-md-0">
        <?php if (!empty($vendor['image_url'])): ?>
            <img src="<?= htmlspecialchars($vendor['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                 class="img-fluid rounded shadow-sm w-100"
                 alt="<?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
            <div class="vendor-card-image-placeholder rounded" style="height:250px;">
                <span class="text-muted">No image available</span>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-8">
        <h1 class="mb-2"><?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if (!empty($vendor['category_text'])): ?>
            <span class="badge bg-success fs-6 mb-3"><?= htmlspecialchars($vendor['category_text'], ENT_QUOTES, 'UTF-8') ?></span>
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
                <li class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($vendor['phone'], ENT_QUOTES, 'UTF-8') ?></li>
            <?php endif; ?>
            <?php if (!empty($vendor['website_url'])): ?>
                <li class="mb-1"><strong>Website:</strong>
                    <a href="<?= htmlspecialchars($vendor['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <?= htmlspecialchars($vendor['website_url'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endif; ?>
            <?php if (!empty($vendor['miles_from_va'])): ?>
                <li class="mb-1"><strong>Distance:</strong> <?= number_format((float)$vendor['miles_from_va'], 1) ?> miles from Virginia, MN</li>
            <?php endif; ?>
        </ul>

        <a href="contact.php?vendor_id=<?= $vendor_id ?>" class="btn btn-success mt-2">Contact This Vendor</a>
    </div>
</div>

<!-- Products -->
<h2 class="mb-4">Products from <?= htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') ?></h2>

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
                        <h6 class="card-title"><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></h6>
                        <?php if (!empty($product['category_name'])): ?>
                            <small class="text-muted"><?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?></small><br>
                        <?php endif; ?>
                        <p class="card-text small text-muted mt-1">
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
    <div class="alert alert-info">This vendor has no products listed yet.</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
