<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_auth();
require_permission('products');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = clean_input($_POST['code']);
    $barcode = clean_input($_POST['barcode']);
    $name = clean_input($_POST['name']);
    $name_ur = clean_input($_POST['name_ur']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
    $base_unit_id = (int)$_POST['base_unit_id'];
    $cost_price = (float)$_POST['cost_price'];
    $retail_price = (float)$_POST['retail_price'];
    $wholesale_price = (float)$_POST['wholesale_price'];
    $contractor_price = (float)$_POST['contractor_price'];
    $alert_quantity = (float)$_POST['alert_quantity'];
    $rack_location = clean_input($_POST['rack_location']);
    $opening_stock = (float)($_POST['opening_stock'] ?? 0);

    if (empty($code) || empty($name) || empty($base_unit_id)) {
        $error = 'Product Code, Name, and Base Unit are mandatory fields.';
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM products WHERE code = ? LIMIT 1");
        $stmt_check->bind_param("s", $code);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $error = "Product Code '$code' already exists!";
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO products (code, barcode, name, name_ur, category_id, brand_id, base_unit_id, cost_price, retail_price, wholesale_price, contractor_price, alert_quantity, rack_location) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiiiddddds", $code, $barcode, $name, $name_ur, $category_id, $brand_id, $base_unit_id, $cost_price, $retail_price, $wholesale_price, $contractor_price, $alert_quantity, $rack_location);
                $stmt->execute();
                $product_id = $conn->insert_id;

                // Create initial stock record
                $stmt_sb = $conn->prepare("INSERT INTO stock_balances (product_id, warehouse_id, quantity, average_cost) VALUES (?, 1, ?, ?)");
                $stmt_sb->bind_param("idd", $product_id, $opening_stock, $cost_price);
                $stmt_sb->execute();

                // If opening stock > 0, record movement
                if ($opening_stock > 0) {
                    $ref_no = 'OPN-' . date('Ymd') . '-' . $product_id;
                    $stmt_sm = $conn->prepare("INSERT INTO stock_movements (product_id, warehouse_id, transaction_type, reference_no, qty_in, balance_after, unit_cost, notes, user_id) 
                                               VALUES (?, 1, 'opening', ?, ?, ?, ?, 'Opening Stock on creation', ?)");
                    $uid = $_SESSION['user_id'];
                    $stmt_sm->bind_param("isdddi", $product_id, $ref_no, $opening_stock, $opening_stock, $cost_price, $uid);
                    $stmt_sm->execute();
                }

                $conn->commit();
                log_audit($conn, 'CREATE_PRODUCT', $code, "Created product $name");
                set_flash('success', 'Product registered successfully.');
                header("Location: " . BASE_URL . "/modules/products/index.php");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Error saving product: ' . $e->getMessage();
            }
        }
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$brands = $conn->query("SELECT * FROM brands ORDER BY name ASC");
$units = $conn->query("SELECT * FROM units ORDER BY name ASC");

$page_title = 'Add New Product';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Add New Product</h4>
        <small class="text-muted">Register hardware items, steel, cement, pipes, or sanitary fittings</small>
    </div>
    <a href="<?= BASE_URL ?>/modules/products/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back to Products
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= clean_input($error) ?></div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-4">
        <form method="POST" action="">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Product Code / SKU *</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. STL-G60-4S" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Barcode</label>
                    <input type="text" name="barcode" class="form-control" placeholder="Scan or enter barcode">
                </div>
                <div class="col-12 col-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Base Unit *</label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalAddUnit">
                            <i class="bi bi-plus-circle me-1"></i>New Unit
                        </button>
                    </div>
                    <select name="base_unit_id" id="base_unit_id" class="form-select" required>
                        <?php while ($u = $units->fetch_assoc()): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= clean_input($u['name']) ?> (<?= clean_input($u['name_ur']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Product Name (English) *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Master PPRC Elbow 25mm" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small fw-bold">Product Name (Urdu)</label>
                    <input type="text" name="name_ur" class="form-control text-end" placeholder="مثال: ماسٹر پی پی آر سی ایلبو">
                </div>

                <div class="col-12 col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Category</label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                            <i class="bi bi-plus-circle me-1"></i>New Category
                        </button>
                    </div>
                    <select name="category_id" id="category_id" class="form-select">
                        <option value="">-- Select Category --</option>
                        <?php while ($c = $categories->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= clean_input($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-bold mb-0">Brand / Manufacturer</label>
                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalAddBrand">
                            <i class="bi bi-plus-circle me-1"></i>New Brand
                        </button>
                    </div>
                    <select name="brand_id" id="brand_id" class="form-select">
                        <option value="">-- None / Local --</option>
                        <?php while ($b = $brands->fetch_assoc()): ?>
                            <option value="<?= $b['id'] ?>"><?= clean_input($b['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <hr class="my-3">
                <h6 class="fw-bold text-primary mb-2"><i class="bi bi-tag-fill me-1"></i> Pricing & Inventory Rules</h6>

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold">Purchase Cost Price (PKR)</label>
                    <input type="number" step="0.01" name="cost_price" class="form-control" value="0.00" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold">Retail Price (PKR)</label>
                    <input type="number" step="0.01" name="retail_price" class="form-control" value="0.00" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold">Wholesale Price (PKR)</label>
                    <input type="number" step="0.01" name="wholesale_price" class="form-control" value="0.00">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label small fw-bold">Contractor Price (PKR)</label>
                    <input type="number" step="0.01" name="contractor_price" class="form-control" value="0.00">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Initial Opening Stock</label>
                    <input type="number" step="0.01" name="opening_stock" class="form-control" value="0">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Low Stock Alert Level</label>
                    <input type="number" step="0.01" name="alert_quantity" class="form-control" value="10">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small fw-bold">Rack / Yard Location</label>
                    <input type="text" name="rack_location" class="form-control" placeholder="e.g. Shed-A, Bin 12">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-check-lg me-1"></i> Save Product
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Quick Add Unit -->
<div class="modal fade" id="modalAddUnit" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-box-seam me-1 text-primary"></i> Add New Unit of Measurement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Unit Name (English) *</label>
                    <input type="text" id="quickUnitName" class="form-control" placeholder="e.g. Bundle, Box, Meter, Kilogram" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Unit Name (Urdu)</label>
                    <input type="text" id="quickUnitNameUr" class="form-control text-end" placeholder="مثال: بنڈل، بوری، فٹ">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold">Unit Code *</label>
                        <input type="text" id="quickUnitCode" class="form-control" placeholder="e.g. bdl, pc, kg">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">Decimal Allowed</label>
                        <select id="quickUnitDecimal" class="form-select">
                            <option value="0">No (Whole numbers)</option>
                            <option value="1">Yes (Fractional, e.g. 2.5 kg)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveQuickUnit"><i class="bi bi-save me-1"></i> Save Unit</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Category -->
<div class="modal fade" id="modalAddCategory" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-grid me-1 text-primary"></i> Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Category Name (English) *</label>
                    <input type="text" id="quickCatName" class="form-control" placeholder="e.g. Electrical Cables & Switches" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Category Name (Urdu)</label>
                    <input type="text" id="quickCatNameUr" class="form-control text-end" placeholder="مثال: الیکٹریکل تاریں اور سوئچز">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveQuickCat"><i class="bi bi-save me-1"></i> Save Category</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Brand -->
<div class="modal fade" id="modalAddBrand" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-tag me-1 text-primary"></i> Add New Brand / Manufacturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Brand Name (English) *</label>
                    <input type="text" id="quickBrandName" class="form-control" placeholder="e.g. Pak Cables, Fast Cables, Diamond" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Brand Name (Urdu)</label>
                    <input type="text" id="quickBrandNameUr" class="form-control text-end" placeholder="مثال: پاک کیبلز">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveQuickBrand"><i class="bi bi-save me-1"></i> Save Brand</button>
            </div>
        </div>
    </div>
</div>

<script>
// Quick Add Unit AJAX
$('#btnSaveQuickUnit').click(function() {
    const name = $('#quickUnitName').val().trim();
    const nameUr = $('#quickUnitNameUr').val().trim();
    const code = $('#quickUnitCode').val().trim();
    const allowDecimal = $('#quickUnitDecimal').val();

    if (!name) {
        alert('Please enter unit name.');
        return;
    }

    $.post('<?= BASE_URL ?>/ajax/products.php', {
        action: 'quick_add_unit',
        name: name,
        name_ur: nameUr,
        code: code,
        allow_decimal: allowDecimal
    }, function(res) {
        if (res.success) {
            const opt = new Option(res.data.name + ' (' + res.data.name_ur + ')', res.data.id, true, true);
            $('#base_unit_id').append(opt).trigger('change');
            bootstrap.Modal.getInstance(document.getElementById('modalAddUnit')).hide();
            $('#quickUnitName').val('');
            $('#quickUnitNameUr').val('');
            $('#quickUnitCode').val('');
        } else {
            alert(res.message);
        }
    }, 'json');
});

// Quick Add Category AJAX
$('#btnSaveQuickCat').click(function() {
    const name = $('#quickCatName').val().trim();
    const nameUr = $('#quickCatNameUr').val().trim();

    if (!name) {
        alert('Please enter category name.');
        return;
    }

    $.post('<?= BASE_URL ?>/ajax/products.php', {
        action: 'quick_add_category',
        name: name,
        name_ur: nameUr
    }, function(res) {
        if (res.success) {
            const opt = new Option(res.data.name, res.data.id, true, true);
            $('#category_id').append(opt).trigger('change');
            bootstrap.Modal.getInstance(document.getElementById('modalAddCategory')).hide();
            $('#quickCatName').val('');
            $('#quickCatNameUr').val('');
        } else {
            alert(res.message);
        }
    }, 'json');
});

// Quick Add Brand AJAX
$('#btnSaveQuickBrand').click(function() {
    const name = $('#quickBrandName').val().trim();
    const nameUr = $('#quickBrandNameUr').val().trim();

    if (!name) {
        alert('Please enter brand name.');
        return;
    }

    $.post('<?= BASE_URL ?>/ajax/products.php', {
        action: 'quick_add_brand',
        name: name,
        name_ur: nameUr
    }, function(res) {
        if (res.success) {
            const opt = new Option(res.data.name, res.data.id, true, true);
            $('#brand_id').append(opt).trigger('change');
            bootstrap.Modal.getInstance(document.getElementById('modalAddBrand')).hide();
            $('#quickBrandName').val('');
            $('#quickBrandNameUr').val('');
        } else {
            alert(res.message);
        }
    }, 'json');
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
