<?php
/**
 * vendor-portal/products.php — Vendor Product Management
 * Virginia Market Square
 *
 * Provides CRUD for the logged-in vendor's products:
 *   - GET (no action):      List all vendor's products in a table
 *   - GET ?action=add:      Show blank product form
 *   - GET ?action=edit&id=X Show pre-filled edit form
 *   - POST action=add:      Insert new product
 *   - POST action=edit:     Update existing product
 *   - POST action=toggle:   Toggle is_available (activate/deactivate)
 *
 * Image upload: accepts JPEG/PNG, resizes to 800×800 max, converts to WebP.
 * Files saved to uploads/products/. Requires GD with WebP support.
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

require_vendor();

$page_title = 'Manage Products';

$vendor_id = get_vendor_id();

if (!$vendor_id) {
    set_flash('error', 'Vendor profile not found.');
    redirect($base_url . '/index.php');
}

$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

// ─── Image upload helper ─────────────────────────────────────────────────────
/**
 * Validates, resizes (max 800px), and saves an uploaded image as WebP.
 * Returns the relative path on success, or sets $error and returns null.
 */
function process_product_image(array $file, int $vendor_id, string &$error): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded — caller keeps existing image_url
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
        return null;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $error = 'Image must be under 5 MB.';
        return null;
    }

    // Verify actual MIME type — never trust the extension
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $src = match($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        default      => null,
    };

    if (!$src) {
        $error = 'Only JPEG and PNG images are accepted.';
        return null;
    }

    // Resize to max 800×800, preserving aspect ratio
    $orig_w = imagesx($src);
    $orig_h = imagesy($src);
    $max    = 800;

    if ($orig_w > $max || $orig_h > $max) {
        $ratio = min($max / $orig_w, $max / $orig_h);
        $new_w = (int) round($orig_w * $ratio);
        $new_h = (int) round($orig_h * $ratio);
    } else {
        $new_w = $orig_w;
        $new_h = $orig_h;
    }

    $dst = imagecreatetruecolor($new_w, $new_h);

    // Preserve PNG transparency as white background for WebP
    if ($mime === 'image/png') {
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
    imagedestroy($src);

    // Save as WebP at quality 85
    $filename  = $vendor_id . '_' . uniqid() . '.webp';
    $rel_path  = 'uploads/products/' . $filename;
    $full_path = dirname(__DIR__) . '/' . $rel_path;

    if (!imagewebp($dst, $full_path, 85)) {
        imagedestroy($dst);
        $error = 'Could not save image. Please try again.';
        return null;
    }

    imagedestroy($dst);
    return $rel_path;
}

// ─── Get categories for the form dropdown ───────────────────────────────────
$categories = $conn->query(
    'SELECT category_id, category_name FROM categories ORDER BY sort_order ASC, category_name ASC'
);

// ─── Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Invalid form submission. Please try again.');
        redirect($base_url . '/vendor-portal/products.php');
    }

    // ── Toggle availability ─────────────────────────────────────────────
    if ($action === 'toggle') {
        $product_id = (int) ($_POST['product_id'] ?? 0);

        // Verify this product belongs to the vendor
        $stmt = $conn->prepare(
            'UPDATE products SET is_available = NOT is_available
             WHERE product_id = ? AND vendor_id = ?'
        );
        $stmt->bind_param('ii', $product_id, $vendor_id);
        $stmt->execute();
        $stmt->close();

        set_flash('success', 'Product status updated.');
        redirect($base_url . '/vendor-portal/products.php');
    }

    // ── Add or Edit product ─────────────────────────────────────────────
    if ($action === 'add' || $action === 'edit') {
        $product_id     = (int) ($_POST['product_id'] ?? 0);
        $product_name   = trim($_POST['product_name']  ?? '');
        $description    = trim($_POST['description']   ?? '');
        $category_id    = (int) ($_POST['category_id'] ?? 0);
        $price          = (float) ($_POST['price']     ?? 0);
        $stock_quantity = (int) ($_POST['stock_quantity'] ?? 0);
        $unit           = trim($_POST['unit']           ?? '');
        $is_available   = isset($_POST['is_available']) ? 1 : 0;
        $featured       = isset($_POST['featured'])     ? 1 : 0;

        // ── Image upload ────────────────────────────────────────────────────
        $error     = '';
        $image_url = null; // resolved below

        $uploaded_path = process_product_image($_FILES['product_image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $vendor_id, $error);

        if ($error) {
            // Image validation failed — redirect back to form
            if ($action === 'edit' && $product_id > 0) {
                redirect($base_url . '/vendor-portal/products.php?action=edit&id=' . $product_id);
            } else {
                redirect($base_url . '/vendor-portal/products.php?action=add');
            }
        }

        if ($uploaded_path !== null) {
            // New image uploaded — use it and delete the old one if editing
            $image_url = $uploaded_path;

            if ($action === 'edit' && $product_id > 0) {
                $stmt = $conn->prepare('SELECT image_url FROM products WHERE product_id = ? AND vendor_id = ?');
                $stmt->bind_param('ii', $product_id, $vendor_id);
                $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!empty($old['image_url']) && str_starts_with($old['image_url'], 'uploads/')) {
                    $old_path = dirname(__DIR__) . '/' . $old['image_url'];
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
            }
        } else {
            // No new file — keep existing image_url for edits, empty for add
            if ($action === 'edit' && $product_id > 0) {
                $stmt = $conn->prepare('SELECT image_url FROM products WHERE product_id = ? AND vendor_id = ?');
                $stmt->bind_param('ii', $product_id, $vendor_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $image_url = $existing['image_url'] ?? '';
            } else {
                $image_url = '';
            }
        }

        // Validation
        if ($product_name === '') {
            $error = 'Product name is required.';
        } elseif ($category_id <= 0) {
            $error = 'Please select a category.';
        } elseif ($price <= 0) {
            $error = 'Price must be greater than zero.';
        } elseif ($stock_quantity < 0) {
            $error = 'Stock quantity cannot be negative.';
        }

        if ($error) {
            set_flash('error', $error);
            // Redirect back to the form with the action
            if ($action === 'edit' && $product_id > 0) {
                redirect($base_url . '/vendor-portal/products.php?action=edit&id=' . $product_id);
            } else {
                redirect($base_url . '/vendor-portal/products.php?action=add');
            }
        }

        if ($action === 'add') {
            $stmt = $conn->prepare(
                "INSERT INTO products
                    (vendor_id, category_id, product_name, description, price,
                     stock_quantity, unit, image_url, is_available, featured)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('iissdissii',
                $vendor_id, $category_id, $product_name, $description, $price,
                $stock_quantity, $unit, $image_url, $is_available, $featured
            );

            if ($stmt->execute()) {
                set_flash('success', 'Product added successfully!');
            } else {
                set_flash('error', 'Failed to add product. Please try again.');
            }
            $stmt->close();

        } elseif ($action === 'edit' && $product_id > 0) {
            // Verify ownership before updating
            $stmt = $conn->prepare(
                "UPDATE products
                 SET category_id = ?, product_name = ?, description = ?, price = ?,
                     stock_quantity = ?, unit = ?, image_url = ?,
                     is_available = ?, featured = ?
                 WHERE product_id = ? AND vendor_id = ?"
            );
            $stmt->bind_param('issdiissiii',
                $category_id, $product_name, $description, $price,
                $stock_quantity, $unit, $image_url,
                $is_available, $featured,
                $product_id, $vendor_id
            );

            if ($stmt->execute()) {
                set_flash('success', 'Product updated successfully!');
            } else {
                set_flash('error', 'Failed to update product. Please try again.');
            }
            $stmt->close();
        }

        redirect($base_url . '/vendor-portal/products.php');
    }
}

// ─── GET: Show Add or Edit form ─────────────────────────────────────────────
if ($action === 'add' || $action === 'edit') {
    $product = null;

    if ($action === 'edit') {
        $edit_id = (int) ($_GET['id'] ?? 0);
        if ($edit_id > 0) {
            // Fetch product — verify it belongs to this vendor
            $stmt = $conn->prepare(
                'SELECT * FROM products WHERE product_id = ? AND vendor_id = ?'
            );
            $stmt->bind_param('ii', $edit_id, $vendor_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$product) {
            set_flash('error', 'Product not found.');
            redirect($base_url . '/vendor-portal/products.php');
        }

        $page_title = 'Edit Product';
    } else {
        $page_title = 'Add New Product';
    }

    include '../includes/header.php';
    ?>

    <div class="row justify-content-center">
        <div class="col-md-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?= $action === 'edit' ? 'Edit Product' : 'Add New Product' ?></h2>
                <a href="<?= $base_url ?>/vendor-portal/products.php"
                   class="btn btn-outline-secondary btn-sm">&larr; Back to Products</a>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="products.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="<?= $action ?>">
                        <?php if ($product): ?>
                            <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <!-- Product name -->
                            <div class="col-12">
                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="product_name"
                                       value="<?= htmlspecialchars($product['product_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       required maxlength="255">
                            </div>

                            <!-- Category -->
                            <div class="col-md-6">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select category...</option>
                                    <?php
                                    // Reset the categories result pointer
                                    $categories->data_seek(0);
                                    while ($cat = $categories->fetch_assoc()):
                                        $selected = ($product && (int) $product['category_id'] === (int) $cat['category_id'])
                                                    ? 'selected' : '';
                                    ?>
                                        <option value="<?= (int) $cat['category_id'] ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Price -->
                            <div class="col-md-3">
                                <label class="form-label">Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="price"
                                           step="0.01" min="0.01"
                                           value="<?= $product ? number_format((float) $product['price'], 2, '.', '') : '' ?>"
                                           required>
                                </div>
                            </div>

                            <!-- Stock -->
                            <div class="col-md-3">
                                <label class="form-label">Stock Qty <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="stock_quantity"
                                       min="0"
                                       value="<?= $product ? (int) $product['stock_quantity'] : '0' ?>"
                                       required>
                            </div>

                            <!-- Unit -->
                            <div class="col-md-6">
                                <label class="form-label">Unit <span class="text-muted">(e.g. per lb, each, per dozen)</span></label>
                                <input type="text" class="form-control" name="unit"
                                       value="<?= htmlspecialchars($product['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       maxlength="50">
                            </div>

                            <!-- Product image upload -->
                            <div class="col-12">
                                <label class="form-label">Product Image <span class="text-muted">(JPEG or PNG, max 5 MB)</span></label>

                                <?php if (!empty($product['image_url'])): ?>
                                <div class="mb-2">
                                    <img id="image-preview"
                                         src="<?= $base_url . '/' . htmlspecialchars($product['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                         alt="Current image"
                                         style="width:100px;height:100px;object-fit:cover;"
                                         class="rounded border">
                                    <div class="small text-muted mt-1">Current image — upload a new file to replace it.</div>
                                </div>
                                <?php else: ?>
                                <img id="image-preview" src="" alt=""
                                     style="display:none;width:100px;height:100px;object-fit:cover;"
                                     class="rounded border mb-2">
                                <?php endif; ?>

                                <input type="file" class="form-control" name="product_image"
                                       id="product_image" accept="image/jpeg,image/png">
                                <div class="form-text">Image will be resized to 800×800 px and converted to WebP automatically.</div>
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4"
                                          maxlength="2000"><?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <!-- Checkboxes -->
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_available"
                                           id="is_available" value="1"
                                           <?= (!$product || $product['is_available']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_available">
                                        Available for sale
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="featured"
                                           id="featured" value="1"
                                           <?= ($product && $product['featured']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="featured">
                                        Featured product
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= $base_url ?>/vendor-portal/products.php"
                               class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <?= $action === 'edit' ? 'Save Changes' : 'Add Product' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('product_image').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const preview = document.getElementById('image-preview');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    });
    </script>

    <?php
    include '../includes/footer.php';
    exit; // Don't fall through to the list view
}

// ─── GET: List all vendor's products ────────────────────────────────────────
$filter = trim($_GET['filter'] ?? '');

$where  = ['p.vendor_id = ?'];
$params = [$vendor_id];
$types  = 'i';

if ($filter === 'active') {
    $where[] = 'p.is_available = 1';
} elseif ($filter === 'inactive') {
    $where[] = 'p.is_available = 0';
}

$where_clause = 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare(
    "SELECT p.product_id, p.product_name, p.price, p.stock_quantity,
            p.unit, p.is_available, p.featured,
            c.category_name
     FROM products p
     JOIN categories c ON p.category_id = c.category_id
     $where_clause
     ORDER BY p.is_available DESC, p.featured DESC, p.product_name ASC"
);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Manage Products</h1>
    <a href="<?= $base_url ?>/vendor-portal/products.php?action=add"
       class="btn btn-success">+ Add New Product</a>
</div>

<!-- Filter tabs -->
<div class="mb-4">
    <div class="btn-group">
        <a href="<?= $base_url ?>/vendor-portal/products.php"
           class="btn btn-sm <?= $filter === '' ? 'btn-success' : 'btn-outline-success' ?>">
            All (<?= $products->num_rows ?>)
        </a>
        <a href="<?= $base_url ?>/vendor-portal/products.php?filter=active"
           class="btn btn-sm <?= $filter === 'active' ? 'btn-success' : 'btn-outline-success' ?>">
            Active
        </a>
        <a href="<?= $base_url ?>/vendor-portal/products.php?filter=inactive"
           class="btn btn-sm <?= $filter === 'inactive' ? 'btn-success' : 'btn-outline-success' ?>">
            Inactive
        </a>
    </div>
</div>

<?php if ($products->num_rows > 0): ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($p = $products->fetch_assoc()): ?>
                        <tr class="<?= !$p['is_available'] ? 'table-light' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($p['product_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($p['featured']): ?>
                                    <span class="badge bg-warning text-dark ms-1">Featured</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['category_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                $<?= number_format((float) $p['price'], 2) ?>
                                <?php if (!empty($p['unit'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($p['unit'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $p['stock_quantity'] <= 0): ?>
                                    <span class="text-danger fw-bold">0</span>
                                <?php elseif ((int) $p['stock_quantity'] <= 5): ?>
                                    <span class="text-warning fw-bold"><?= (int) $p['stock_quantity'] ?></span>
                                <?php else: ?>
                                    <?= (int) $p['stock_quantity'] ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['is_available']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?= $base_url ?>/vendor-portal/products.php?action=edit&id=<?= (int) $p['product_id'] ?>"
                                       class="btn btn-outline-success btn-sm">Edit</a>

                                    <!-- Toggle availability -->
                                    <form method="POST" action="products.php" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                                        <button type="submit"
                                                class="btn btn-sm <?= $p['is_available'] ? 'btn-outline-warning' : 'btn-outline-primary' ?>">
                                            <?= $p['is_available'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <a href="<?= $base_url ?>/product-detail.php?id=<?= (int) $p['product_id'] ?>"
                                       class="btn btn-outline-secondary btn-sm" target="_blank">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <?php if ($filter): ?>
            No <?= htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') ?> products.
            <a href="<?= $base_url ?>/vendor-portal/products.php">View all</a>.
        <?php else: ?>
            You haven't added any products yet.
            <a href="<?= $base_url ?>/vendor-portal/products.php?action=add">Add your first product</a>!
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Back to dashboard -->
<div class="mt-3">
    <a href="<?= $base_url ?>/vendor-portal/dashboard.php" class="btn btn-outline-secondary btn-sm">
        &larr; Back to Dashboard
    </a>
</div>

<?php include '../includes/footer.php'; ?>
