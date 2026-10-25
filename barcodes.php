<?php
// barcodes.php - Barcode Generator & Printable Product Labels
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$selected_product_id = (int)($_GET['product_id'] ?? 0);
$qty_to_print = (int)($_GET['qty'] ?? 12);
$label_size = $_GET['size'] ?? 'standard'; // standard or shelf

$selected_product = null;
if ($selected_product_id > 0) {
    $selected_product = db_fetch_one("SELECT p.*, u.symbol AS unit_symbol FROM products p LEFT JOIN units u ON p.unit_id = u.id WHERE p.id = $selected_product_id");
}

$products = db_fetch_all("SELECT p.*, u.symbol AS unit_symbol FROM products p LEFT JOIN units u ON p.unit_id = u.id WHERE p.status = 'active' ORDER BY p.name_en ASC");

$store_name = get_setting('store_name_ur', 'المدینہ کریانہ اسٹور');

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2 no-print">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-upc-scan text-success me-2"></i> بارکوڈ لیبل پرنٹنگ (Barcode Label Generator)</h4>
        <div class="text-muted small">اسٹیکرز اور شیلف ٹیگز پرنٹ کریں تاکہ کاؤنٹر پر تیزی سے اسکین ہو سکیں</div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($selected_product): ?>
            <button onclick="printSection('barcode-print-area','بارکوڈ لیبلز')" class="btn btn-success fw-bold">
                <i class="bi bi-printer me-1"></i> لیبلز پرنٹ کریں (Print Labels)
            </button>
        <?php endif; ?>
        <a href="products.php" class="btn btn-outline-secondary">واپس</a>
    </div>
</div>

<!-- Select Product Bar -->
<div class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body p-3">
        <form method="GET" action="barcodes.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-bold">پراڈکٹ منتخب کریں:</label>
                <select name="product_id" class="form-select" onchange="this.form.submit()">
                    <option value="">منتخب کریں...</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $selected_product_id == $p['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lang === 'ur' ? ($p['name_ur'] ?: $p['name_en']) : $p['name_en']); ?> (بارکوڈ: <?php echo $p['barcode'] ?: $p['code']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold">کتنے لیبل پرنٹ کرنے ہیں؟</label>
                <input type="number" name="qty" class="form-control" value="<?php echo $qty_to_print; ?>" min="1" max="100">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold">لیبل کی قسم:</label>
                <select name="size" class="form-select">
                    <option value="standard" <?php echo $label_size == 'standard' ? 'selected' : ''; ?>>بارکوڈ اسٹیکر (Sticker)</option>
                    <option value="shelf" <?php echo $label_size == 'shelf' ? 'selected' : ''; ?>>شیلف لیبل (Shelf Tag)</option>
                </select>
            </div>
            <div class="col-12 col-md-2 mt-auto">
                <button type="submit" class="btn btn-primary w-100 fw-bold">تیار کریں</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_product): 
    $barcode_val = $selected_product['barcode'] ?: $selected_product['code'];
?>
    <div class="printable-labels-container" id="barcode-print-area">
        <div class="row g-3 justify-content-center">
            <?php for ($i = 0; $i < $qty_to_print; $i++): ?>
                <div class="col-auto">
                    <div class="barcode-sticker border text-center p-2 bg-white shadow-sm rounded">
                        <div class="small fw-bold text-dark text-truncate" style="max-width: 170px; font-size: 11px;">
                            <?php echo htmlspecialchars($store_name); ?>
                        </div>
                        <div class="fw-bold text-truncate" style="max-width: 170px; font-size: 13px;">
                            <?php echo htmlspecialchars($selected_product['name_ur'] ?: $selected_product['name_en']); ?>
                        </div>
                        
                        <!-- Barcode SVG Rendered by JsBarcode -->
                        <svg class="barcode-svg" jsbarcode-value="<?php echo htmlspecialchars($barcode_val); ?>" jsbarcode-height="35" jsbarcode-width="1.4" jsbarcode-fontSize="11"></svg>

                        <div class="fw-bold text-success fs-6 mt-1">
                            Rs. <?php echo number_format($selected_product['retail_price'], 2); ?>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php else: ?>
    <div class="text-center py-5 bg-white rounded-3 border no-print">
        <i class="bi bi-upc-scan fs-1 text-secondary opacity-50 d-block mb-2"></i>
        <h5 class="fw-bold text-muted">بارکوڈ لیبل پرنٹ کرنے کے لیے اوپر سے پراڈکٹ منتخب کریں</h5>
    </div>
<?php endif; ?>

<!-- Print Styles -->
<style>
.barcode-sticker {
    width: 50mm;
    min-height: 32mm;
    page-break-inside: avoid;
}
@media print {
    body {
        background: white;
        padding: 0;
    }
    .no-print, .top-navbar, .sidebar {
        display: none !important;
    }
    .main-content {
        padding: 0 !important;
        margin: 0 !important;
    }
    .barcode-sticker {
        box-shadow: none !important;
        border: 1px dashed #ccc !important;
        margin: 2mm;
    }
}
</style>

<!-- JsBarcode CDN -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    try {
        JsBarcode(".barcode-svg").init();
    } catch (e) {
        console.error(e);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

