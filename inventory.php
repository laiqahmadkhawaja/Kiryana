<?php
// inventory.php - Stock Inventory Overview & Stock Movement Ledger
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$product_id = (int)($_GET['product_id'] ?? 0);
$cat_id = (int)($_GET['category_id'] ?? 0);
$filter = $_GET['filter'] ?? 'all'; // all, low, out

// Total Valuation
$val = db_fetch_one("SELECT 
    COUNT(*) AS total_items,
    COALESCE(SUM(current_stock * purchase_price), 0) AS total_cost_value,
    COALESCE(SUM(current_stock * retail_price), 0) AS total_retail_value,
    COALESCE(SUM(CASE WHEN current_stock <= alert_quantity AND current_stock > 0 THEN 1 ELSE 0 END), 0) AS low_count,
    COALESCE(SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END), 0) AS out_count
    FROM products WHERE status = 'active'");

// Stock List Query
$where = "WHERE p.status = 'active'";
if ($cat_id > 0) $where .= " AND p.category_id = $cat_id";
if ($filter === 'low') $where .= " AND p.current_stock <= p.alert_quantity AND p.current_stock > 0";
if ($filter === 'out') $where .= " AND p.current_stock <= 0";
if ($product_id > 0) $where .= " AND p.id = $product_id";

$products = db_fetch_all("SELECT p.*, c.name_en AS cat_en, c.name_ur AS cat_ur, u.symbol AS unit_symbol 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          LEFT JOIN units u ON p.unit_id = u.id 
                          $where 
                          ORDER BY p.current_stock ASC");

// If specific product ledger is requested
$ledger_items = [];
$selected_product = null;
if ($product_id > 0) {
    $selected_product = db_fetch_one("SELECT p.*, u.symbol AS unit_symbol FROM products p LEFT JOIN units u ON p.unit_id = u.id WHERE p.id = $product_id");
    if ($selected_product) {
        $ledger_items = db_fetch_all("SELECT sl.*, u.full_name AS user_name 
                                     FROM stock_ledger sl 
                                     LEFT JOIN users u ON sl.created_by = u.id 
                                     WHERE sl.product_id = $product_id 
                                     ORDER BY sl.id DESC LIMIT 100");
    }
}

$categories = db_fetch_all("SELECT * FROM categories WHERE status = 'active' ORDER BY name_en ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div id="report-print-area">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-boxes text-success me-2"></i> اسٹاک و انوینٹری مانیٹرنگ (Inventory & Stock)</h4>
        <div class="text-muted small">موجودہ اسٹاک، گودام کی مالیت اور اسٹاک موومنٹ کھاتہ</div>
    </div>
    <div class="d-flex gap-2">
        <a href="stock_adjust.php" class="btn btn-warning fw-bold">
            <i class="bi bi-arrow-left-right me-1"></i> اسٹاک ایڈجسٹمنٹ (Damage/Audit)
        </a>
        <button onclick="printSection('report-print-area','انوینٹری رپورٹ')" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> پرنٹ رپورٹ
        </button>
    </div>
</div>

<!-- Valuation KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">اسٹاک کی کل خرید مالیت (Cost Value)</div>
                <div class="kpi-value text-primary"><?php echo format_currency($val['total_cost_value']); ?></div>
                <div class="small text-muted mt-1">خرید قیمت پر سرمایہ</div>
            </div>
            <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-cash-stack"></i></div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">متوقع فروخت مالیت (Retail Value)</div>
                <div class="kpi-value text-success"><?php echo format_currency($val['total_retail_value']); ?></div>
                <div class="small text-muted mt-1">
                    متوقع کل منافع: <?php echo format_currency($val['total_retail_value'] - $val['total_cost_value']); ?>
                </div>
            </div>
            <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-graph-up"></i></div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">کم اسٹاک اشیاء (Low Stock)</div>
                <div class="kpi-value text-warning-emphasis"><?php echo $val['low_count']; ?></div>
                <div class="small text-muted mt-1"><a href="inventory.php?filter=low" class="text-decoration-none">فہرست دیکھیں &rarr;</a></div>
            </div>
            <div class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">ختم شدہ اشیاء (Out of Stock)</div>
                <div class="kpi-value text-danger"><?php echo $val['out_count']; ?></div>
                <div class="small text-muted mt-1"><a href="inventory.php?filter=out" class="text-decoration-none">فہرست دیکھیں &rarr;</a></div>
            </div>
            <div class="kpi-icon bg-danger-subtle text-danger"><i class="bi bi-x-octagon"></i></div>
        </div>
    </div>
</div>

<?php if ($selected_product): ?>
    <!-- Product Specific Stock Movement Ledger -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-success border-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-success">
                    <i class="bi bi-clock-history me-1"></i> اسٹاک لیجر (Stock Movement Ledger): 
                    <?php echo htmlspecialchars($selected_product['name_ur'] ?: $selected_product['name_en']); ?>
                </h5>
                <div class="small text-muted">
                    کوڈ: <code><?php echo $selected_product['code']; ?></code> | 
                    موجودہ اسٹاک: <strong><?php echo number_format($selected_product['current_stock'], 2) . ' ' . $selected_product['unit_symbol']; ?></strong>
                </div>
            </div>
            <a href="inventory.php" class="btn btn-sm btn-outline-secondary">تمام انوینٹری پر واپس جائیں</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>تاریخ و وقت</th>
                            <th>قسم ٹرانزیکشن</th>
                            <th>انوائس / حوالہ</th>
                            <th class="text-center text-success">آمد اسٹاک (Qty In)</th>
                            <th class="text-center text-danger">نکاسی اسٹاک (Qty Out)</th>
                            <th class="text-center fw-bold">بقایا اسٹاک</th>
                            <th>تفصیل / وجہ</th>
                            <th>صارف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ledger_items)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">اس پراڈکٹ کی کوئی موومنٹ نہیں ہے</td></tr>
                        <?php else: ?>
                            <?php foreach ($ledger_items as $li): ?>
                                <tr>
                                    <td class="small text-muted"><?php echo format_datetime($li['created_at']); ?></td>
                                    <td>
                                        <span class="badge <?php echo in_array($li['transaction_type'], ['purchase', 'opening', 'sale_return']) ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                                            <?php echo strtoupper($li['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($li['reference_no']); ?></code></td>
                                    <td class="text-center fw-bold text-success">
                                        <?php echo $li['qty_in'] > 0 ? ('+' . number_format($li['qty_in'], 2)) : '-'; ?>
                                    </td>
                                    <td class="text-center fw-bold text-danger">
                                        <?php echo $li['qty_out'] > 0 ? ('-' . number_format($li['qty_out'], 2)) : '-'; ?>
                                    </td>
                                    <td class="text-center fw-bold">
                                        <?php echo number_format($li['balance_after'], 2); ?>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($li['notes']); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($li['user_name'] ?? 'Admin'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Main Inventory Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-check me-2"></i> تفصیلی اسٹاک لسٹ (Stock Inventory Status)</h6>
        <div class="d-flex gap-2">
            <a href="inventory.php?filter=all" class="btn btn-sm <?php echo $filter == 'all' ? 'btn-success' : 'btn-outline-secondary'; ?>">تمام</a>
            <a href="inventory.php?filter=low" class="btn btn-sm <?php echo $filter == 'low' ? 'btn-warning' : 'btn-outline-secondary'; ?>">کم اسٹاک</a>
            <a href="inventory.php?filter=out" class="btn btn-sm <?php echo $filter == 'out' ? 'btn-danger' : 'btn-outline-secondary'; ?>">ختم شدہ</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>نام پراڈکٹ</th>
                        <th>کوڈ / بارکوڈ</th>
                        <th>کیٹیگری</th>
                        <th class="text-end">خرید قیمت</th>
                        <th class="text-end">پرچون ریٹ</th>
                        <th class="text-center">موجودہ اسٹاک</th>
                        <th class="text-end">کل خرید مالیت</th>
                        <th class="text-center">حالت</th>
                        <th class="text-center" style="width: 130px;">کارروائی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">کوئی ریکارڈ موجود نہیں</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $i => $p): 
                            $item_val = $p['current_stock'] * $p['purchase_price'];
                        ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($p['name_ur'] ?: $p['name_en']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($p['name_en']); ?></div>
                                </td>
                                <td><code><?php echo htmlspecialchars($p['barcode'] ?: $p['code']); ?></code></td>
                                <td class="small"><?php echo htmlspecialchars($lang === 'ur' ? ($p['cat_ur'] ?: $p['cat_en']) : $p['cat_en']); ?></td>
                                <td class="text-end text-muted small"><?php echo format_currency($p['purchase_price']); ?></td>
                                <td class="text-end fw-semibold text-success"><?php echo format_currency($p['retail_price']); ?></td>
                                <td class="text-center">
                                    <span class="fw-bold <?php echo $p['current_stock'] <= 0 ? 'text-danger' : ($p['current_stock'] <= $p['alert_quantity'] ? 'text-warning-emphasis' : 'text-dark'); ?>">
                                        <?php echo number_format($p['current_stock'], 2) . ' ' . $p['unit_symbol']; ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold"><?php echo format_currency($item_val); ?></td>
                                <td class="text-center">
                                    <?php if ($p['current_stock'] <= 0): ?>
                                        <span class="badge bg-danger">ختم (Out)</span>
                                    <?php elseif ($p['current_stock'] <= $p['alert_quantity']): ?>
                                        <span class="badge bg-warning text-dark">کم اسٹاک</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success">تسلی بخش</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="inventory.php?product_id=<?php echo $p['id']; ?>" class="btn btn-outline-info" title="اسٹاک کھاتہ دیکھیں">
                                            <i class="bi bi-clock-history"></i> لیجر
                                        </a>
                                        <a href="stock_adjust.php?product_id=<?php echo $p['id']; ?>" class="btn btn-outline-warning" title="ایڈجسٹمنٹ">
                                            <i class="bi bi-sliders"></i>
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

</div><!-- #report-print-area -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

