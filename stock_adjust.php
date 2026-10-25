<?php
// stock_adjust.php - Stock Adjustments (Damage, Expired, Physical Audit)
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';
$pre_product_id = (int)($_GET['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $product_id = (int)($_POST['product_id'] ?? 0);
    $adjust_type = $_POST['adjust_type'] ?? 'adjustment_damage'; // adjustment_damage, adjustment_expired, adjustment_physical
    $mode = $_POST['mode'] ?? 'decrease'; // decrease or increase
    $qty = (float)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($product_id <= 0 || $qty <= 0) {
        $err = "Please select a product and enter a valid quantity! درست پراڈکٹ اور مقدار درج کریں۔";
    } else {
        $product = db_fetch_one("SELECT * FROM products WHERE id = $product_id");
        if (!$product) {
            $err = "Product not found!";
        } else {
            $user_id = (int)$_SESSION['user_id'];
            $cost_price = (float)$product['purchase_price'];

            if ($mode === 'increase') {
                $qty_in = $qty;
                $qty_out = 0;
                $new_stock = (float)$product['current_stock'] + $qty;
            } else {
                $qty_in = 0;
                $qty_out = $qty;
                $new_stock = (float)$product['current_stock'] - $qty;
            }

            // Update product current stock
            $sql_upd = "UPDATE products SET current_stock = $new_stock WHERE id = $product_id";
            if (db_query($sql_upd)) {
                $ref_no = 'ADJ-' . date('Ymd-His');
                $notes_esc = db_escape($notes);

                // Record in Stock Ledger
                $sql_ledger = "INSERT INTO stock_ledger (product_id, transaction_type, reference_no, qty_in, qty_out, balance_after, cost_price, notes, created_by) 
                               VALUES ($product_id, '$adjust_type', '$ref_no', $qty_in, $qty_out, $new_stock, $cost_price, '$notes_esc', $user_id)";
                db_query($sql_ledger);

                log_audit("Stock Adjustment", "products", $product_id, "Type: $adjust_type, Qty: $qty, Mode: $mode, New Stock: $new_stock");
                $msg = "Stock adjustment saved successfully! اسٹاک ایڈجسٹمنٹ کامیابی سے درج ہو گئی۔";
            } else {
                $err = "Database error: " . $conn->error;
            }
        }
    }
}

$products = db_fetch_all("SELECT p.*, u.symbol AS unit_symbol FROM products p LEFT JOIN units u ON p.unit_id = u.id WHERE p.status = 'active' ORDER BY p.name_en ASC");

// Recent adjustments
$recent_adjustments = db_fetch_all("SELECT sl.*, p.name_ur, p.name_en, u.full_name AS user_name, un.symbol AS unit_symbol 
                                   FROM stock_ledger sl 
                                   JOIN products p ON sl.product_id = p.id 
                                   LEFT JOIN units un ON p.unit_id = un.id 
                                   LEFT JOIN users u ON sl.created_by = u.id 
                                   WHERE sl.transaction_type LIKE 'adjustment_%' 
                                   ORDER BY sl.id DESC LIMIT 20");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-arrow-left-right text-warning me-2"></i> اسٹاک ایڈجسٹمنٹ (Stock Adjustment & Damage)</h4>
        <div class="text-muted small">خراب شدہ مال، ایکسپائر اشیاء یا فزیکل گنتی کے فرق کا اندراج کریں</div>
    </div>
    <a href="inventory.php" class="btn btn-outline-secondary">
        <i class="bi bi-boxes me-1"></i> اسٹاک پر واپس جائیں
    </a>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-4">
    <!-- Adjustment Form -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-warning text-dark py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i> نئی اسٹاک ایڈجسٹمنٹ درج کریں</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="stock_adjust.php">
                    <?php echo csrf_field(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">پراڈکٹ منتخب کریں *</label>
                        <select name="product_id" id="adjProductSelect" class="form-select form-select-lg" required onchange="updateStockHint()">
                            <option value="">منتخب کریں...</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?php echo $p['id']; ?>" data-stock="<?php echo $p['current_stock']; ?>" data-unit="<?php echo $p['unit_symbol']; ?>" <?php echo $pre_product_id == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lang === 'ur' ? ($p['name_ur'] ?: $p['name_en']) : $p['name_en']); ?> (موجودہ: <?php echo number_format($p['current_stock'], 2) . ' ' . $p['unit_symbol']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small" id="adjStockHint"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">ایڈجسٹمنٹ کی قسم (Reason / Type) *</label>
                        <select name="adjust_type" class="form-select" id="adjTypeSelect" onchange="autoSelectMode()">
                            <option value="adjustment_damage">خراب / ٹوٹ پھوٹ شدہ مال (Damaged Goods)</option>
                            <option value="adjustment_expired">ایکسپائر شدہ مال (Expired Stock)</option>
                            <option value="adjustment_physical">فزیکل آڈٹ / گنتی کا فرق (Physical Inventory Discrepancy)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">اسٹاک میں اثر (Impact) *</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="mode" id="modeDec" value="decrease" checked>
                            <label class="btn btn-outline-danger fw-bold" for="modeDec">
                                <i class="bi bi-dash-circle me-1"></i> اسٹاک کم کریں (نقصان / کمی)
                            </label>

                            <input type="radio" class="btn-check" name="mode" id="modeInc" value="increase">
                            <label class="btn btn-outline-success fw-bold" for="modeInc">
                                <i class="bi bi-plus-circle me-1"></i> اسٹاک بڑھائیں (اضافی مال)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">مقدار (Quantity) *</label>
                        <input type="number" step="0.01" min="0.01" name="quantity" class="form-control form-control-lg fw-bold" placeholder="0.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">وجہ / تفصیلات (Notes)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="مثلاً: پیکٹ پھٹ گیا تھا، پانی لگنے سے خراب ہوا..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> ایڈجسٹمنٹ محفوظ کریں
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Adjustments List -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i> حالیہ ایڈجسٹمنٹس اور نقصانات (Recent History)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>تاریخ</th>
                                <th>پراڈکٹ</th>
                                <th>قسم</th>
                                <th class="text-center">مقدار</th>
                                <th>وجہ</th>
                                <th>صارف</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_adjustments)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">کوئی ایڈجسٹمنٹ ریکارڈ نہیں ہے</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_adjustments as $ra): ?>
                                    <tr>
                                        <td class="small text-muted"><?php echo format_datetime($ra['created_at']); ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($ra['name_ur'] ?: $ra['name_en']); ?></td>
                                        <td>
                                            <?php if ($ra['transaction_type'] == 'adjustment_damage'): ?>
                                                <span class="badge bg-danger-subtle text-danger">خراب مال</span>
                                            <?php elseif ($ra['transaction_type'] == 'adjustment_expired'): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis">ایکسپائری</span>
                                            <?php else: ?>
                                                <span class="badge bg-info-subtle text-info">فزیکل آڈٹ</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold <?php echo $ra['qty_in'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $ra['qty_in'] > 0 ? ('+' . number_format($ra['qty_in'], 2)) : ('-' . number_format($ra['qty_out'], 2)); ?>
                                            <?php echo $ra['unit_symbol']; ?>
                                        </td>
                                        <td class="small"><?php echo htmlspecialchars($ra['notes'] ?: '-'); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($ra['user_name'] ?? 'Admin'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateStockHint() {
    const sel = document.getElementById('adjProductSelect');
    const opt = sel.options[sel.selectedIndex];
    const hint = document.getElementById('adjStockHint');
    if (opt && opt.dataset.stock) {
        hint.innerHTML = `<span class="text-muted">موجودہ اسٹاک: <strong>${opt.dataset.stock} ${opt.dataset.unit}</strong></span>`;
    } else {
        hint.innerHTML = '';
    }
}
updateStockHint();

function autoSelectMode() {
    const type = document.getElementById('adjTypeSelect').value;
    if (type === 'adjustment_damage' || type === 'adjustment_expired') {
        document.getElementById('modeDec').checked = true;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

