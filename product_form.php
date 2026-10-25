<?php
// product_form.php - Add / Edit Product
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$product = null;
$msg_error = '';

if ($id > 0) {
    $product = db_fetch_one("SELECT * FROM products WHERE id = $id");
    if (!$product) {
        die("Product not found! پراڈکٹ نہیں ملی۔");
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $code = trim($_POST['code'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $name_ur = trim($_POST['name_ur'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $brand_id = (int)($_POST['brand_id'] ?? 0);
    $unit_id = (int)($_POST['unit_id'] ?? 1);
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    $retail_price = (float)($_POST['retail_price'] ?? 0);
    $wholesale_price = (float)($_POST['wholesale_price'] ?? 0);
    $min_sale_price = (float)($_POST['min_sale_price'] ?? 0);
    $current_stock = (float)($_POST['current_stock'] ?? 0);
    $alert_quantity = (float)($_POST['alert_quantity'] ?? 5);
    $has_batch_expiry = isset($_POST['has_batch_expiry']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';

    if (empty($name_en) && empty($name_ur)) {
        $msg_error = "Product name in English or Urdu is required! نام لازمی ہے۔";
    } elseif (empty($code)) {
        $msg_error = "Product code is required! کوڈ لازمی ہے۔";
    } else {
        $code_esc = db_escape($code);
        $bar_esc = db_escape($barcode);
        $nen_esc = db_escape($name_en);
        $nur_esc = db_escape($name_ur);

        // Check unique code
        $code_check = db_fetch_one("SELECT id FROM products WHERE code = '$code_esc' AND id != $id");
        if ($code_check) {
            $msg_error = "Product Code already in use! یہ کوڈ پہلے سے موجود ہے۔";
        } else {
            if ($id > 0) {
                // Update
                $sql = "UPDATE products SET 
                        code = '$code_esc',
                        barcode = '$bar_esc',
                        name_en = '$nen_esc',
                        name_ur = '$nur_esc',
                        category_id = $category_id,
                        brand_id = $brand_id,
                        unit_id = $unit_id,
                        purchase_price = $purchase_price,
                        retail_price = $retail_price,
                        wholesale_price = $wholesale_price,
                        min_sale_price = $min_sale_price,
                        alert_quantity = $alert_quantity,
                        has_batch_expiry = $has_batch_expiry,
                        status = '$status'
                        WHERE id = $id";
                if (db_query($sql)) {
                    log_audit("Update Product", "products", $id);
                    header("Location: products.php?msg=updated");
                    exit;
                } else {
                    $msg_error = "Database Error: " . $conn->error;
                }
            } else {
                // Insert New
                $sql = "INSERT INTO products (code, barcode, name_en, name_ur, category_id, brand_id, unit_id, 
                        purchase_price, retail_price, wholesale_price, min_sale_price, current_stock, alert_quantity, 
                        has_batch_expiry, status) 
                        VALUES ('$code_esc', '$bar_esc', '$nen_esc', '$nur_esc', $category_id, $brand_id, $unit_id, 
                        $purchase_price, $retail_price, $wholesale_price, $min_sale_price, $current_stock, $alert_quantity, 
                        $has_batch_expiry, '$status')";
                if (db_query($sql)) {
                    $new_id = $conn->insert_id;
                    // Add opening stock ledger if stock > 0
                    if ($current_stock > 0) {
                        db_query("INSERT INTO stock_ledger (product_id, transaction_type, reference_no, qty_in, balance_after, cost_price, notes) 
                                  VALUES ($new_id, 'opening', 'OPENING-STOCK', $current_stock, $current_stock, $purchase_price, 'Initial Stock')");
                    }
                    log_audit("Create Product", "products", $new_id);
                    header("Location: products.php?msg=created");
                    exit;
                } else {
                    $msg_error = "Database Error: " . $conn->error;
                }
            }
        }
    }
}

// Fetch categories, brands, units
$categories = db_fetch_all("SELECT * FROM categories WHERE status = 'active' ORDER BY name_en ASC");
$brands = db_fetch_all("SELECT * FROM brands WHERE status = 'active' ORDER BY name_en ASC");
$units = db_fetch_all("SELECT * FROM units WHERE status = 'active' ORDER BY name_en ASC");

// Auto generated code for new
$suggested_code = $product ? $product['code'] : ('PRD-' . date('ymd') . rand(10, 99));

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1 text-dark">
            <i class="bi bi-box-seam text-success me-2"></i>
            <?php echo $id > 0 ? 'پراڈکٹ کی تفصیلات تبدیل کریں (Edit Product)' : 'نئی پراڈکٹ شامل کریں (Add New Product)'; ?>
        </h4>
        <div class="text-muted small">انگریزی و اردو نام، بارکوڈ، کیٹیگری، یونٹ اور قیمتیں درج کریں</div>
    </div>
    <a href="products.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> واپس لسٹ پر جائیں
    </a>
</div>

<?php if ($msg_error): ?>
    <div class="alert alert-danger py-2"><?php echo $msg_error; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <form method="POST" action="product_form.php<?php echo $id > 0 ? '?id='.$id : ''; ?>">
            <?php echo csrf_field(); ?>

            <div class="row g-3">
                <!-- Names -->
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">نام پراڈکٹ (اردو) <span class="text-danger">*</span></label>
                    <input type="text" name="name_ur" class="form-control form-control-lg fw-bold" placeholder="مثلاً: چینی سفید یا سپر باسمتی چاول" 
                           value="<?php echo htmlspecialchars($product['name_ur'] ?? ''); ?>" required autofocus>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold">Product Name (English)</label>
                    <input type="text" name="name_en" class="form-control form-control-lg" placeholder="e.g. Sugar White or Basmati Rice" 
                           value="<?php echo htmlspecialchars($product['name_en'] ?? ''); ?>">
                </div>

                <!-- Codes & Barcode -->
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">پراڈکٹ کوڈ (Product Code / SKU) *</label>
                    <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars($suggested_code); ?>" required>
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label fw-bold small">بارکوڈ نمبر (Barcode)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-upc"></i></span>
                        <input type="text" name="barcode" id="productBarcode" class="form-control" placeholder="بارکوڈ اسکین کریں یا بٹن سے بنائیں" 
                               value="<?php echo htmlspecialchars($product['barcode'] ?? ''); ?>">
                        <button class="btn btn-outline-primary" type="button" onclick="generateBarcode()">
                            <i class="bi bi-magic"></i> خودکار بارکوڈ بنائیں
                        </button>
                    </div>
                </div>

                <!-- Category, Brand, Unit -->
                <div class="col-12 col-md-4">
                    <div class="d-flex justify-content-between">
                        <label class="form-label fw-bold small">کیٹیگری (Category)</label>
                        <a href="categories.php" target="_blank" class="small text-decoration-none">+ نئی کیٹیگری</a>
                    </div>
                    <select name="category_id" class="form-select">
                        <option value="0">منتخب کریں...</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($product && $product['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang === 'ur' ? ($c['name_ur'] ?: $c['name_en']) : $c['name_en']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <div class="d-flex justify-content-between">
                        <label class="form-label fw-bold small">برانڈ / کمپنی (Brand)</label>
                        <a href="categories.php" target="_blank" class="small text-decoration-none">+ نیا برانڈ</a>
                    </div>
                    <select name="brand_id" class="form-select">
                        <option value="0">عام / کھلا مال (Open/None)</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($product && $product['brand_id'] == $b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang === 'ur' ? ($b['name_ur'] ?: $b['name_en']) : $b['name_en']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <div class="d-flex justify-content-between">
                        <label class="form-label fw-bold small">پیمائش کی اکائی (Unit of Measure) *</label>
                        <a href="units.php" target="_blank" class="small text-decoration-none">+ نئی اکائی</a>
                    </div>
                    <select name="unit_id" class="form-select fw-semibold" required>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo ($product && $product['unit_id'] == $u['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lang === 'ur' ? ($u['name_ur'] . ' (' . $u['symbol'] . ')') : ($u['name_en'] . ' (' . $u['symbol'] . ')')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Pricing Details -->
                <div class="col-12"><hr class="my-2"></div>
                <h6 class="fw-bold text-success mb-0"><i class="bi bi-cash me-1"></i> قیمتیں اور منافع (Pricing Structure)</h6>

                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold small">قیمت خرید (Purchase Cost) *</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" name="purchase_price" id="costPrice" class="form-control fw-semibold" 
                               value="<?php echo $product ? $product['purchase_price'] : '0.00'; ?>" required oninput="calcMargins()">
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold small">پرچون فروخت ریٹ (Retail Price) *</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" name="retail_price" id="retailPrice" class="form-control fw-bold text-success" 
                               value="<?php echo $product ? $product['retail_price'] : '0.00'; ?>" required oninput="calcMargins()">
                    </div>
                    <div class="small text-muted mt-1" id="marginDisplay">منافع مارجن: 0%</div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold small">تھوک ریٹ (Wholesale Price)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" name="wholesale_price" class="form-control fw-semibold text-primary" 
                               value="<?php echo $product ? $product['wholesale_price'] : '0.00'; ?>">
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold small">کم از کم فروخت ریٹ (Min Price)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" step="0.01" name="min_sale_price" class="form-control" 
                               value="<?php echo $product ? $product['min_sale_price'] : '0.00'; ?>">
                    </div>
                </div>

                <!-- Stock & Expiry -->
                <div class="col-12"><hr class="my-2"></div>
                <h6 class="fw-bold text-secondary mb-0"><i class="bi bi-box me-1"></i> اسٹاک و الرٹ سیٹنگز</h6>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">ابتدائی اسٹاک (Opening Stock)</label>
                    <input type="number" step="0.01" name="current_stock" class="form-control" 
                           value="<?php echo $product ? $product['current_stock'] : '0'; ?>" <?php echo $id > 0 ? 'readonly title="اسٹاک تبدیل کرنے کے لیے اسٹاک ایڈجسٹمنٹ استعمال کریں"' : ''; ?>>
                    <?php if ($id > 0): ?>
                        <small class="text-muted"><a href="stock_adjust.php?product_id=<?php echo $id; ?>">اسٹاک ایڈجسٹمنٹ کریں &rarr;</a></small>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">کم اسٹاک الرٹ کی حد (Low Stock Alert)</label>
                    <input type="number" step="0.01" name="alert_quantity" class="form-control" 
                           value="<?php echo $product ? $product['alert_quantity'] : '10'; ?>">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">پراڈکٹ کی حالت (Status)</label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo (!$product || $product['status'] == 'active') ? 'selected' : ''; ?>>فعال (Active)</option>
                        <option value="inactive" <?php echo ($product && $product['status'] == 'inactive') ? 'selected' : ''; ?>>غیر فعال (Inactive)</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="has_batch_expiry" id="batchCheck" 
                               <?php echo ($product && $product['has_batch_expiry']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="batchCheck">
                            اس پراڈکٹ کے لیے بیج نمبر اور ایکسپائری تاریخ مینیج کریں (Enable Batch & Expiry Management)
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="col-12 mt-4 text-end">
                    <a href="products.php" class="btn btn-secondary px-4 me-2">منسوخ</a>
                    <button type="submit" class="btn btn-success fw-bold px-5 shadow-sm">
                        <i class="bi bi-check-lg me-1"></i> محفوظ کریں (Save Product)
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function generateBarcode() {
    // Generate 12-digit Kiryana barcode
    const rand = '896' + Math.floor(10000000 + Math.random() * 90000000);
    document.getElementById('productBarcode').value = rand;
}

function calcMargins() {
    const cost = parseFloat(document.getElementById('costPrice').value) || 0;
    const retail = parseFloat(document.getElementById('retailPrice').value) || 0;
    const display = document.getElementById('marginDisplay');

    if (retail > 0 && cost > 0) {
        const profit = retail - cost;
        const marginPct = (profit / retail) * 100;
        display.innerHTML = `منافع فی اکائی: <strong>Rs. ${profit.toFixed(2)}</strong> (${marginPct.toFixed(1)}%)`;
        display.className = marginPct >= 0 ? 'small text-success mt-1' : 'small text-danger mt-1';
    } else {
        display.innerText = 'منافع مارجن: 0%';
    }
}
calcMargins();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

