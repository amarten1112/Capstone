<?php
/**
 * product-detail.php — Individual Product Detail Page
 * Virginia Market Square
 *
 * Phase 4, Task 4.3 (logic)
 * Phase 6, Task 6.3 (UI polish — inline styles → CSS classes)
 *
 * Layout:
 *   Left col (lg-5):   Product image
 *   Center col (lg-4): Product name, vendor link, price, description,
 *                       stock quantity, quantity selector, Add to Cart
 *   Right col (lg-3):  Product reviews + related products
 *
 * Breadcrumb: Home > Products > Category > Product Name
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// ─── Validate product ID ────────────────────────────────────────────────────
$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($product_id <= 0) {
    set_flash('error', 'Product not found.');
    redirect($base_url . '/products.php');
}

// ─── Fetch product with vendor and category ─────────────────────────────────
$stmt = $conn->prepare(
    "SELECT p.product_id, p.product_name, p.description, p.price,
            p.image_url, p.unit, p.stock_quantity, p.featured,
            p.is_available, p.category_id, p.created_date,
            v.vendor_id, v.vendor_name, v.short_bio, v.image_url AS vendor_image,
            c.category_id, c.category_name
     FROM products p
     JOIN vendors v    ON p.vendor_id  = v.vendor_id AND v.verified = 1
     JOIN categories c ON p.category_id = c.category_id
     WHERE p.product_id = ? AND p.is_available = 1"
);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    set_flash('error', 'Product not found or is no longer available.');
    redirect($base_url . '/products.php');
}

// ─── Fetch related products ─────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT p.product_id, p.product_name, p.price, p.image_url, p.unit,
            v.vendor_id, v.vendor_name
     FROM products p
     JOIN vendors v ON p.vendor_id = v.vendor_id AND v.verified = 1
     WHERE p.category_id = ?
       AND p.product_id != ?
       AND p.is_available = 1
       AND p.stock_quantity > 0
     ORDER BY p.featured DESC, RAND()
     LIMIT 4"
);
$stmt->bind_param('ii', $product['category_id'], $product_id);
$stmt->execute();
$related = $stmt->get_result();
$stmt->close();

$page_title = $product['product_name'];

include 'includes/header.php';
?>

<!-- ─── Breadcrumb ────────────────────────────────────────────────────────── -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= $base_url ?>/index.php">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= $base_url ?>/products.php">Products</a>
        </li>
        <li class="breadcrumb-item">
            <a href="<?= $base_url ?>/products.php?category=<?= (int) $product['category_id'] ?>">
                <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
            <?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>
        </li>
    </ol>
</nav>

<!-- ─── Main Product Layout ──────────────────────────────────────────────── -->
<div class="row g-4">

    <!-- ── LEFT: Product Image ───────────────────────────────────────────── -->
    <div class="col-lg-5">
        <?php if (!empty($product['image_url'])): ?>
            <img src="<?= htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                 class="img-fluid rounded shadow-sm w-100 product-detail-img"
                 alt="<?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?>">
        <?php else: ?>
            <div class="vendor-card-image-placeholder rounded product-detail-img">
                <span class="fs-5">No image available</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── CENTER: Product Info + Add to Cart ────────────────────────────── -->
    <div class="col-lg-4">

        <?php if ($product['featured']): ?>
            <span class="badge bg-warning text-dark mb-2">Featured Product</span>
        <?php endif; ?>

        <h2 class="mb-1"><?= htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') ?></h2>

        <p class="mb-2">
            by <a href="<?= $base_url ?>/vendor-detail.php?vendor_id=<?= (int) $product['vendor_id'] ?>"
                  class="text-decoration-none">
                <?= htmlspecialchars($product['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        </p>

        <a href="<?= $base_url ?>/products.php?category=<?= (int) $product['category_id'] ?>"
           class="badge bg-light text-dark border text-decoration-none mb-3">
            <?= htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8') ?>
        </a>

        <h3 class="text-success mt-3 mb-1">
            $<?= number_format((float) $product['price'], 2) ?>
            <?php if (!empty($product['unit'])): ?>
                <small class="text-muted fs-6"><?= htmlspecialchars($product['unit'], ENT_QUOTES, 'UTF-8') ?></small>
            <?php endif; ?>
        </h3>

        <hr>

        <h6>Description</h6>
        <p class="text-muted"><?= nl2br(htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>

        <?php if ($product['stock_quantity'] > 0): ?>
            <p class="mb-3">
                <span class="stock-in">In Stock</span>
                <small class="text-muted">(<?= (int) $product['stock_quantity'] ?> available)</small>
            </p>
        <?php else: ?>
            <p class="stock-out mb-3">Out of Stock</p>
        <?php endif; ?>

        <!-- ── Add to Cart Form ──────────────────────────────────────────── -->
        <?php if ($product['stock_quantity'] > 0): ?>
            <form action="<?= $base_url ?>/customer/cart-add.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">

                <div class="d-flex align-items-center gap-2 mb-3">
                    <label for="quantity" class="form-label mb-0 fw-bold">Qty:</label>
                    <input type="number" class="form-control qty-input" id="quantity" name="quantity"
                           value="1" min="1" max="<?= (int) $product['stock_quantity'] ?>">
                </div>

                <button type="submit" class="btn btn-success btn-lg w-100">
                    Add to Cart
                </button>
            </form>
        <?php endif; ?>

        <a href="<?= $base_url ?>/products.php" class="btn btn-outline-secondary btn-sm mt-3">
            &larr; Back to Products
        </a>
    </div>

    <!-- ── RIGHT: Reviews + Related Products ─────────────────────────────── -->
    <div class="col-lg-3">

        <!-- ── Reviews Section (placeholder) ─────────────────────────────── -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h6 class="card-title mb-3">Product Reviews</h6>

                <div class="text-center text-muted py-3">
                    <p class="mb-1">No reviews yet</p>
                    <small>Be the first to review this product!</small>
                </div>

                <hr>

                <?php if (is_logged_in() && get_current_user_type() === 'customer'): ?>
                    <h6 class="mb-2">Write a Review</h6>
                    <form action="<?= $base_url ?>/customer/review-add.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">

                        <div class="mb-2">
                            <label class="form-label small">Rating</label>
                            <select class="form-select form-select-sm" name="rating" required>
                                <option value="">Select...</option>
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>

                        <div class="mb-2">
                            <textarea class="form-control form-control-sm" name="review_text"
                                      rows="3" placeholder="Share your experience..."
                                      maxlength="500"></textarea>
                        </div>

                        <button type="submit" class="btn btn-outline-success btn-sm w-100">
                            Submit Review
                        </button>
                    </form>
                <?php elseif (!is_logged_in()): ?>
                    <p class="small text-muted text-center mb-0">
                        <a href="<?= $base_url ?>/login.php">Log in</a> to leave a review.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Related Products ──────────────────────────────────────────── -->
        <?php if ($related->num_rows > 0): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="card-title mb-3">Related Products</h6>

                    <?php while ($rel = $related->fetch_assoc()): ?>
                        <div class="d-flex gap-2 mb-3 pb-3 border-bottom">
                            <?php if (!empty($rel['image_url'])): ?>
                                <a href="product-detail.php?id=<?= (int) $rel['product_id'] ?>">
                                    <img src="<?= htmlspecialchars($rel['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                         class="rounded product-thumb"
                                         alt="<?= htmlspecialchars($rel['product_name'], ENT_QUOTES, 'UTF-8') ?>">
                                </a>
                            <?php endif; ?>
                            <div>
                                <a href="product-detail.php?id=<?= (int) $rel['product_id'] ?>"
                                   class="text-decoration-none text-dark fw-bold small">
                                    <?= htmlspecialchars($rel['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <br>
                                <small class="text-success fw-bold">
                                    $<?= number_format((float) $rel['price'], 2) ?>
                                </small>
                                <?php if (!empty($rel['unit'])): ?>
                                    <small class="text-muted"><?= htmlspecialchars($rel['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">
                                    by <?= htmlspecialchars($rel['vendor_name'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<?php include 'includes/footer.php'; ?>
