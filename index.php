<?php
// index.php - Dashboard & Business Overview
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$today = date('Y-m-d');

// --- Real-time KPIs Calculations ---

// 1. Today's Sales
$sales_row = db_fetch_one("SELECT 
    COALESCE(SUM(grand_total), 0) AS total_sales,
    COALESCE(SUM(paid_amount), 0) AS total_paid,
    COALESCE(SUM(CASE WHEN sale_type = 'cash' THEN grand_total ELSE 0 END), 0) AS cash_sales,
    COALESCE(SUM(CASE WHEN sale_type = 'credit' OR (sale_type = 'split' AND balance_amount > 0) THEN balance_amount ELSE 0 END), 0) AS credit_sales
    FROM sales 
    WHERE DATE(created_at) = '$today' AND status = 'completed'");

$today_sales = (float)$sales_row['total_sales'];
$today_cash_sales = (float)$sales_row['cash_sales'];
$today_credit_sales = (float)$sales_row['credit_sales'];

// 2. Today's Purchases
$purchases_row = db_fetch_one("SELECT COALESCE(SUM(grand_total), 0) AS total_purchases FROM purchases WHERE purchase_date = '$today'");
$today_purchases = (float)$purchases_row['total_purchases'];

// 3. Today's Cost of Goods Sold (COGS) & Gross Profit
$cogs_row = db_fetch_one("SELECT COALESCE(SUM(si.quantity * si.cost_price), 0) AS total_cogs 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    WHERE DATE(s.created_at) = '$today' AND s.status = 'completed'");
$today_cogs = (float)$cogs_row['total_cogs'];
$today_gross_profit = $today_sales - $today_cogs;

// 4. Today's Expenses
$expenses_row = db_fetch_one("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM expenses WHERE expense_date = '$today'");
$today_expenses = (float)$expenses_row['total_expenses'];

// 5. Today's Net Profit = Gross Profit - Expenses
$today_net_profit = $today_gross_profit - $today_expenses;

// 6. Cash in Hand & Accounts Balance
$cash_row = db_fetch_one("SELECT COALESCE(SUM(current_balance), 0) AS cash_balance FROM accounts WHERE account_type = 'cash' AND status = 'active'");
$cash_in_hand = (float)$cash_row['cash_balance'];

$bank_row = db_fetch_one("SELECT COALESCE(SUM(current_balance), 0) AS bank_balance FROM accounts WHERE account_type != 'cash' AND status = 'active'");
$bank_balance = (float)$bank_row['bank_balance'];

// 7. Customer Receivables & Supplier Payables
$cust_row = db_fetch_one("SELECT COALESCE(SUM(current_balance), 0) AS total_receivable FROM customers WHERE status = 'active'");
$customer_receivables = (float)$cust_row['total_receivable'];

$supp_row = db_fetch_one("SELECT COALESCE(SUM(current_balance), 0) AS total_payable FROM suppliers WHERE status = 'active'");
$supplier_payables = (float)$supp_row['total_payable'];

// 8. Inventory Value & Stock Alerts
$stock_row = db_fetch_one("SELECT 
    COALESCE(SUM(current_stock * purchase_price), 0) AS stock_cost_value,
    COALESCE(SUM(current_stock * retail_price), 0) AS stock_retail_value,
    COALESCE(SUM(CASE WHEN current_stock <= alert_quantity AND current_stock > 0 THEN 1 ELSE 0 END), 0) AS low_stock_count,
    COALESCE(SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END), 0) AS out_stock_count
    FROM products WHERE status = 'active'");
$stock_value = (float)$stock_row['stock_cost_value'];
$low_stock_count = (int)$stock_row['low_stock_count'];
$out_stock_count = (int)$stock_row['out_stock_count'];

// 9. Last 7 Days Sales Trend for Chart (Single Query)
$start_7d = date('Y-m-d', strtotime('-6 days'));
$trend_rows = db_fetch_all("SELECT DATE(created_at) AS sale_date, COALESCE(SUM(grand_total), 0) AS total 
                           FROM sales 
                           WHERE DATE(created_at) >= '$start_7d' AND status = 'completed' 
                           GROUP BY DATE(created_at)");
$sales_by_date = array_column($trend_rows, 'total', 'sale_date');

$days_sales = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $days_sales[] = [
        'day' => date('D (d M)', strtotime("-$i days")),
        'amount' => (float)($sales_by_date[$d] ?? 0)
    ];
}

// 10. Top 5 Best Selling Items This Month
$first_day_month = date('Y-m-01');
$top_items = db_fetch_all("SELECT si.product_name, SUM(si.quantity) AS total_qty, SUM(si.subtotal) AS total_amount 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    WHERE DATE(s.created_at) >= '$first_day_month' AND s.status = 'completed'
    GROUP BY si.product_id, si.product_name 
    ORDER BY total_qty DESC LIMIT 5");

// 11. Low stock products list
$low_stock_items = db_fetch_all("SELECT p.*, u.symbol AS unit_symbol, c.name_ur AS cat_ur, c.name_en AS cat_en 
    FROM products p 
    LEFT JOIN units u ON p.unit_id = u.id 
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.current_stock <= p.alert_quantity AND p.status = 'active' 
    ORDER BY p.current_stock ASC LIMIT 6");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><?php echo __('dashboard'); ?> | کاروباری خلاصہ</h4>
        <div class="text-muted small">
            <?php echo __('store_title'); ?> - <?php echo format_date(date('Y-m-d')); ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="pos.php" class="btn btn-success fw-bold px-3 shadow-sm">
            <i class="bi bi-cart-plus me-1"></i> <?php echo __('fast_billing'); ?> (POS)
        </a>
        <a href="purchase_create.php" class="btn btn-primary fw-medium px-3 shadow-sm">
            <i class="bi bi-bag-plus me-1"></i> + <?php echo __('purchases'); ?>
        </a>
    </div>
</div>

<!-- Primary KPIs Grid (Row 1) -->
<div class="row g-3 mb-4">
    <!-- Today's Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?php echo __('today_sales'); ?></div>
                <div class="kpi-value text-success"><?php echo format_currency($today_sales); ?></div>
                <div class="small text-muted mt-1">
                    <span class="text-success"><i class="bi bi-cash"></i> <?php echo format_currency($today_cash_sales, false); ?> کیش</span> | 
                    <span class="text-danger"><i class="bi bi-credit-card"></i> <?php echo format_currency($today_credit_sales, false); ?> ادھار</span>
                </div>
            </div>
            <div class="kpi-icon bg-success-subtle text-success">
                <i class="bi bi-cart-check"></i>
            </div>
        </div>
    </div>

    <!-- Today's Gross Profit -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?php echo __('gross_profit'); ?> (آج کا نفع)</div>
                <div class="kpi-value text-primary"><?php echo format_currency($today_gross_profit); ?></div>
                <div class="small text-muted mt-1">
                    COGS: <?php echo format_currency($today_cogs, false); ?>
                </div>
            </div>
            <div class="kpi-icon bg-primary-subtle text-primary">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
        </div>
    </div>

    <!-- Today's Net Profit -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?php echo __('net_profit'); ?> (خالص منافع)</div>
                <div class="kpi-value <?php echo $today_net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo format_currency($today_net_profit); ?>
                </div>
                <div class="small text-muted mt-1">
                    اخراجات منہا کرنے کے بعد
                </div>
            </div>
            <div class="kpi-icon bg-info-subtle text-info">
                <i class="bi bi-wallet-fill"></i>
            </div>
        </div>
    </div>

    <!-- Today's Expenses -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?php echo __('expenses_today'); ?></div>
                <div class="kpi-value text-danger"><?php echo format_currency($today_expenses); ?></div>
                <div class="small text-muted mt-1">
                    <a href="expenses.php" class="text-decoration-none">تفصیلات دیکھیں &rarr;</a>
                </div>
            </div>
            <div class="kpi-icon bg-danger-subtle text-danger">
                <i class="bi bi-receipt"></i>
            </div>
        </div>
    </div>
</div>

<!-- Secondary KPIs Grid (Row 2 - Balances, Khata, Inventory) -->
<div class="row g-3 mb-4">
    <!-- Cash in Hand -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?php echo __('cash_in_hand'); ?></div>
                <div class="kpi-value"><?php echo format_currency($cash_in_hand); ?></div>
                <div class="small text-muted mt-1">دکان کیش در دست</div>
            </div>
            <div class="kpi-icon bg-secondary-subtle text-secondary">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>

    <!-- Bank & Digital Wallets -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">بینک و والٹس (JazzCash/EP)</div>
                <div class="kpi-value text-info"><?php echo format_currency($bank_balance); ?></div>
                <div class="small text-muted mt-1">تمام کھاتہ جات</div>
            </div>
            <div class="kpi-icon bg-info-subtle text-info">
                <i class="bi bi-bank"></i>
            </div>
        </div>
    </div>

    <!-- Customer Khata Receivables -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?php echo __('receivables'); ?></div>
                <div class="kpi-value text-warning-emphasis"><?php echo format_currency($customer_receivables); ?></div>
                <div class="small text-muted mt-1"><a href="customers.php" class="text-decoration-none">وصول طلب ادھار &rarr;</a></div>
            </div>
            <div class="kpi-icon bg-warning-subtle text-warning">
                <i class="bi bi-people"></i>
            </div>
        </div>
    </div>

    <!-- Total Stock Value -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title"><?php echo __('stock_value'); ?></div>
                <div class="kpi-value text-dark"><?php echo format_currency($stock_value); ?></div>
                <div class="small text-muted mt-1">خرید قیمت کی بنیاد پر</div>
            </div>
            <div class="kpi-icon bg-dark-subtle text-dark">
                <i class="bi bi-boxes"></i>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Tables Row -->
<div class="row g-4 mb-4">
    <!-- 7 Days Sales Trend Chart -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm h-100 rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-bar-chart-line text-success me-2"></i> پچھلے 7 دن کی فروخت کا رجحان (7-Day Sales Trend)</h6>
                <span class="badge bg-light text-secondary">PKR</span>
            </div>
            <div class="card-body">
                <canvas id="salesTrendChart" height="120"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Selling Products -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100 rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-warning me-2"></i> سب سے زیادہ فروخت اشیاء (Top Items)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>پراڈکٹ</th>
                                <th class="text-center">فروخت مقدار</th>
                                <th class="text-end">کل رقم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_items)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">ابھی تک کوئی فروخت ریکارڈ نہیں ہوئی</td></tr>
                            <?php else: ?>
                                <?php foreach ($top_items as $ti): ?>
                                    <tr>
                                        <td class="fw-medium small"><?php echo htmlspecialchars($ti['product_name']); ?></td>
                                        <td class="text-center"><span class="badge bg-success-subtle text-success"><?php echo number_format($ti['total_qty'], 2); ?></span></td>
                                        <td class="text-end fw-bold small"><?php echo format_currency($ti['total_amount']); ?></td>
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

<!-- Low Stock Alerts & Quick Shortcuts Row -->
<div class="row g-4">
    <!-- Low Stock Items Warning -->
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> کم اسٹاک کی اشیاء (Low Stock Alert)</h6>
                <a href="inventory.php" class="btn btn-sm btn-outline-danger">تمام اسٹاک دیکھیں</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>کوڈ / بارکوڈ</th>
                                <th>نام پراڈکٹ</th>
                                <th>کیٹیگری</th>
                                <th>موجود اسٹاک</th>
                                <th>کم از کم حد</th>
                                <th>حالت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($low_stock_items)): ?>
                                <tr><td colspan="6" class="text-center text-success py-4"><i class="bi bi-check-circle me-1"></i> تمام اشیاء کا اسٹاک تسلی بخش ہے!</td></tr>
                            <?php else: ?>
                                <?php foreach ($low_stock_items as $it): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($it['barcode'] ?: $it['code']); ?></code></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($lang === 'ur' ? ($it['name_ur'] ?: $it['name_en']) : $it['name_en']); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($lang === 'ur' ? ($it['cat_ur'] ?: $it['cat_en']) : $it['cat_en']); ?></td>
                                        <td class="fw-bold text-danger"><?php echo number_format($it['current_stock'], 2) . ' ' . $it['unit_symbol']; ?></td>
                                        <td class="text-muted small"><?php echo number_format($it['alert_quantity'], 2); ?></td>
                                        <td>
                                            <?php if ($it['current_stock'] <= 0): ?>
                                                <span class="badge bg-danger">ختم ہو گیا (Out)</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">کم اسٹاک (Low)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts & Info Box -->
    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm rounded-3 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-lightning-charge-fill text-warning me-2"></i> فوری شارٹ کٹس (Quick Actions)</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="pos.php" class="btn btn-outline-success text-start py-2">
                        <i class="bi bi-cart-plus me-2"></i> نیا بل بنائیں (New Bill) <span class="badge bg-light text-dark float-end">F9</span>
                    </a>
                    <a href="product_form.php" class="btn btn-outline-primary text-start py-2">
                        <i class="bi bi-plus-circle me-2"></i> نئی پراڈکٹ شامل کریں (Add Product)
                    </a>
                    <a href="customers.php" class="btn btn-outline-warning text-start py-2 text-dark">
                        <i class="bi bi-person-plus me-2"></i> گاہک کا کھاتہ کھولیں (New Customer Khata)
                    </a>
                    <a href="expenses.php" class="btn btn-outline-danger text-start py-2">
                        <i class="bi bi-wallet2 me-2"></i> نیا خرچہ درج کریں (Record Expense)
                    </a>
                    <a href="shifts.php" class="btn btn-outline-secondary text-start py-2">
                        <i class="bi bi-cash-coin me-2"></i> کیش رجسٹر بند / چیک کریں (Shift Closing)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    const labels = <?php echo json_encode(array_column($days_sales, 'day')); ?>;
    const data = <?php echo json_encode(array_column($days_sales, 'amount')); ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'سیلز (Sales PKR)',
                data: data,
                borderColor: '#166534',
                backgroundColor: 'rgba(22, 101, 52, 0.1)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: '#166534'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'Rs. ' + value; }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

