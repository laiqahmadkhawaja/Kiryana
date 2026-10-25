<?php
// products.php - Product Catalog & Management
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

// Handle Delete
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Check if sales exist
    $sales_check = db_fetch_one("SELECT id FROM sale_items WHERE product_id = $del_id LIMIT 1");
    if ($sales_check) {
        $msg_error = "Cannot delete product because it has sales history! Instead, set status to Inactive. اس پراڈکٹ کی سیلز موجود ہیں، اسے غیر فعال کریں۔";
    } else {
        db_query("DELETE FROM products WHERE id = $del_id");
        log_audit("Delete Product", "products", $del_id);
        $msg_success = "Product deleted successfully! پراڈکٹ حذف کر دی گئی۔";
    }
}

// Filters & Search
$q = trim($_GET['q'] ?? '');
$cat_id = (int)($_GET['category_id'] ?? 0);
$brand_id = (int)($_GET['brand_id'] ?? 0);
$stock_status = $_GET['stock_status'] ?? '';

$where = "WHERE 1=1";
if (!empty($q)) {
    $q_esc = db_escape($q);
    $where .= " AND (p.barcode LIKE '%$q_esc%' OR p.code LIKE '%$q_esc%' OR p.name_en LIKE '%$q_esc%' OR p.name_ur LIKE '%$q_esc%')";
}
if ($cat_id > 0) {
    $where .= " AND p.category_id = $cat_id";
}
if ($brand_id > 0) {
    $where .= " AND p.brand_id = $brand_id";
}
if ($stock_status === 'low') {
    $where .= " AND p.current_stock <= p.alert_quantity AND p.current_stock > 0";
} elseif ($stock_status === 'out') {
    $where .= " AND p.current_stock <= 0";
}

$sql = "SELECT p.*, c.name_en AS cat_en, c.name_ur AS cat_ur, b.name_en AS brand_en, u.symbol AS unit_symbol 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN brands b ON p.brand_id = b.id 
        LEFT JOIN units u ON p.unit_id = u.id 
        $where 
        ORDER BY p.id DESC";
$products = db_fetch_all($sql);

$categories = db_fetch_all("SELECT * FROM categories WHERE status = 'active' ORDER BY name_en ASC");
$brands = db_fetch_all("SELECT * FROM brands WHERE status = 'active' ORDER BY name_en ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-box-seam text-success me-2"></i> اشیاء و پراڈکٹس (Products Catalog)</h4>
        <div class="text-muted small">تمام اشیاء، ریٹس اور موجودہ اسٹاک کی تفصیل</div>
    </div>
    <div class="d-flex gap-2">
        <a href="product_form.php" class="btn btn-success fw-bold shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> نئی پراڈکٹ شامل کریں (Add Product)
        </a>
        <a href="barcodes.php" class="btn btn-outline-secondary fw-semibold">
            <i class="bi bi-upc-scan me-1"></i> بارکوڈ پرنٹ
        </a>
    </div>
</div>

<?php if (!empty($msg_success)): ?>
    <div class="alert alert-success alert-dismissible fade show py-2"><?php echo $msg_success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (!empty($msg_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2"><?php echo $msg_error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form method="GET" action="products.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="نام، کوڈ یا بارکوڈ تلاش کریں..." value="<?php echo htmlspecialchars($q); ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="category_id" class="form-select">
                    <option value="0">تمام کیٹیگریز (All Categories)</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $cat_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lang === 'ur' ? ($cat['name_ur'] ?: $cat['name_en']) : $cat['name_en']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="stock_status" class="form-select">
                    <option value="">تمام اسٹاک حالت</option>
                    <option value="low" <?php echo $stock_status == 'low' ? 'selected' : ''; ?>>کم اسٹاک (Low)</option>
                    <option value="out" <?php echo $stock_status == 'out' ? 'selected' : ''; ?>>ختم شدہ (Out)</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-50 fw-semibold"><i class="bi bi-filter"></i> فلٹر</button>
                <a href="products.php" class="btn btn-outline-secondary w-50">ری سیٹ</a>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>نام پراڈکٹ (Urdu / English)</th>
                        <th>کوڈ / بارکوڈ</th>
                        <th>کیٹیگری</th>
                        <th class="text-end">قیمت خرید (Cost)</th>
                        <th class="text-end">پرچون ریٹ (Retail)</th>
                        <th class="text-end">تھوک ریٹ (Wholesale)</th>
                        <th class="text-center">موجودہ اسٹاک</th>
                        <th class="text-center">حالت</th>
                        <th class="text-center" style="width: 120px;">کارروائی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-5">کوئی پراڈکٹ نہیں ملی</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $i => $p): 
                            $margin = $p['retail_price'] > 0 ? (($p['retail_price'] - $p['purchase_price']) / $p['retail_price']) * 100 : 0;
                        ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($p['name_ur'] ?: $p['name_en']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($p['name_en']); ?></div>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($p['barcode'] ?: $p['code']); ?></code>
                                </td>
                                <td class="small">
                                    <?php echo htmlspecialchars($lang === 'ur' ? ($p['cat_ur'] ?: $p['cat_en']) : $p['cat_en']); ?>
                                </td>
                                <td class="text-end text-muted small">
                                    <?php echo format_currency($p['purchase_price']); ?>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    <?php echo format_currency($p['retail_price']); ?>
                                    <div class="text-muted" style="font-size: 0.7rem;">منافع: <?php echo round($margin, 1); ?>%</div>
                                </td>
                                <td class="text-end fw-semibold text-primary">
                                    <?php echo format_currency($p['wholesale_price']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold <?php echo $p['current_stock'] <= 0 ? 'text-danger' : ($p['current_stock'] <= $p['alert_quantity'] ? 'text-warning-emphasis' : 'text-dark'); ?>">
                                        <?php echo number_format($p['current_stock'], 2) . ' ' . $p['unit_symbol']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">ختم (Out)</span>
                                    <?php elseif ($p['current_stock'] <= $p['alert_quantity']): ?>
                                        <span class="badge bg-warning text-dark">کم اسٹاک</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">موجود</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="product_form.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-primary" title="ترمیم کریں">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="inventory.php?product_id=<?php echo $p['id']; ?>" class="btn btn-outline-info" title="اسٹاک کھاتہ">
                                            <i class="bi bi-clock-history"></i>
                                        </a>
                                        <a href="products.php?delete=<?php echo $p['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Delete this product? کیا آپ حذف کرنا چاہتے ہیں؟');" title="حذف کریں">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

