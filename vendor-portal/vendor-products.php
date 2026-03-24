<?php
/**
 * vendor-portal/products.php — Vendor Product Management
 * Virginia Market Square
 *
 * Phase 4, Task 4.13
 *
 * Provides CRUD for the logged-in vendor's products:
 *   - GET (no action):  List all vendor's products in a table
 *   - GET ?action=add:  Show blank product form
 *   - GET ?action=edit&id=X: Show pre-filled edit form
 *   - POST action=add:  Insert new product
 *   - POST action=edit: Update existing product
 *   - POST action=toggle: Toggle is_available (activate/deactivate)
 *
 * Image upload is NOT handled here (Phase 8). The image_url field
 * accepts a text URL for now — vendors can paste an image path.
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
        $product_id    = (int) ($_POST['product_id'] ?? 0);
        $product_name  = trim($_POST['product_name']  ?? '');
        $description   = trim($_POST['description']   ?? '');
        $category_id   = (int) ($_POST['category_id'] ?? 0);
        $price         = (float) ($_POST['price']     ?? 0);
        $stock_quantity = (int) ($_POST['stock_quantity'] ?? 0);
        $unit          = trim($_POST['unit']           ?? '');
        $image_url     = trim($_POST['image_url']      ?? '');
        $is_available  = isset($_POST['is_available']) ? 1 : 0;
        $featured      = isset($_POST['featured'])     ? 1 : 0;

        // Validation
        $error = '';
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
                    <form method="POST" action="products.php">
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

                            <!-- Image URL -->
                            <div class="col-md-6">
                                <label class="form-label">Image URL <span class="text-muted">(optional)</span></label>
                                <input type="text" class="form-control" name="image_url"
                                       placeholder="images/products/my-product.webp"
                                       value="<?= htmlspecialchars($product['image_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       maxlength="255">
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
