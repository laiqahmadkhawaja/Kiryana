<?php
// reports_profit.php - Accurate Business Profit & Loss Calculations
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

// Period Filter
$period = $_GET['period'] ?? 'this_month'; // today, this_week, this_month, this_year, custom
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

if ($period === 'today') {
    $from_date = date('Y-m-d');
    $to_date = date('Y-m-d');
} elseif ($period === 'this_week') {
    $from_date = date('Y-m-d', strtotime('monday this week'));
    $to_date = date('Y-m-d');
} elseif ($period === 'this_month') {
    $from_date = date('Y-m-01');
    $to_date = date('Y-m-d');
} elseif ($period === 'this_year') {
    $from_date = date('Y-01-01');
    $to_date = date('Y-m-d');
} else {
    if (empty($from_date)) $from_date = date('Y-m-01');
    if (!empty($to_date)) $to_date = date('Y-m-d');
}

// 1. Gross Sales & Discounts
$sales_stat = db_fetch_one("SELECT 
    COALESCE(SUM(subtotal), 0) AS gross_sales,
    COALESCE(SUM(discount_amount), 0) AS total_discounts,
    COALESCE(SUM(grand_total), 0) AS billed_sales,
    COALESCE(SUM(paid_amount), 0) AS cash_received,
    COALESCE(SUM(balance_amount), 0) AS credit_sales 
    FROM sales 
    WHERE DATE(created_at) >= '$from_date' AND DATE(created_at) <= '$to_date' AND status = 'completed'");

$gross_sales = (float)$sales_stat['gross_sales'];
$discounts = (float)$sales_stat['total_discounts'];

// 2. Sales Returns
$returns_stat = db_fetch_one("SELECT COALESCE(SUM(total_refund), 0) AS total_returns 
                             FROM sales_returns 
                             WHERE DATE(created_at) >= '$from_date' AND DATE(created_at) <= '$to_date'");
$sales_returns = (float)$returns_stat['total_returns'];

// Net Sales = Gross Sales - Discounts - Returns
$net_sales = $gross_sales - $discounts - $sales_returns;

// 3. Cost of Goods Sold (COGS)
// Cost of sold items minus cost of returned items
$cogs_stat = db_fetch_one("SELECT COALESCE(SUM(si.quantity * si.cost_price), 0) AS sold_cogs 
                          FROM sale_items si 
                          JOIN sales s ON si.sale_id = s.id 
                          WHERE DATE(s.created_at) >= '$from_date' AND DATE(s.created_at) <= '$to_date' AND s.status = 'completed'");

$return_cogs_stat = db_fetch_one("SELECT COALESCE(SUM(sri.quantity * sri.cost_price), 0) AS ret_cogs 
                                 FROM sales_return_items sri 
                                 JOIN sales_returns sr ON sri.return_id = sr.id 
                                 WHERE DATE(sr.created_at) >= '$from_date' AND DATE(sr.created_at) <= '$to_date'");

$cogs = (float)$cogs_stat['sold_cogs'] - (float)$return_cogs_stat['ret_cogs'];

// 4. Gross Profit = Net Sales - COGS
$gross_profit = $net_sales - $cogs;
$gross_margin_pct = $net_sales > 0 ? ($gross_profit / $net_sales) * 100 : 0;

// 5. Operating Expenses
$exp_stat = db_fetch_one("SELECT COALESCE(SUM(amount), 0) AS total_exp 
                         FROM expenses 
                         WHERE expense_date >= '$from_date' AND expense_date <= '$to_date'");
$operating_expenses = (float)$exp_stat['total_exp'];

// 6. Net Operating Profit = Gross Profit - Operating Expenses
$net_profit = $gross_profit - $operating_expenses;
$net_margin_pct = $net_sales > 0 ? ($net_profit / $net_sales) * 100 : 0;

// 7. Product-wise Profitability Breakdown
$item_profits = db_fetch_all("SELECT 
    si.product_id,
    si.product_name,
    SUM(si.quantity) AS sold_qty,
    SUM(si.subtotal) AS total_revenue,
    SUM(si.quantity * si.cost_price) AS total_cost,
    SUM(si.subtotal) - SUM(si.quantity * si.cost_price) AS item_profit 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    WHERE DATE(s.created_at) >= '$from_date' AND DATE(s.created_at) <= '$to_date' AND s.status = 'completed'
    GROUP BY si.product_id, si.product_name 
    ORDER BY item_profit DESC LIMIT 40");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div id="report-print-area">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-graph-up-arrow text-success me-2"></i> کاروباری منافع و نقصان رپورٹ (Profit & Loss Statement)</h4>
        <div class="text-muted small">خالص سیلز، مال کی لاگت (COGS)، مجموعی نفع اور دکان کے اخراجات منہا کرنے کے بعد خالص منافع</div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="printSection('report-print-area','منافع و نقصان رپورٹ')" class="btn btn-outline-secondary fw-semibold">
            <i class="bi bi-printer me-1"></i> پی اینڈ ایل اسٹیٹمنٹ پرنٹ
        </button>
    </div>
</div>

<!-- Date Filter Ribbon -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="reports_profit.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="btn-group w-100" role="group">
                    <a href="reports_profit.php?period=today" class="btn btn-sm <?php echo $period == 'today' ? 'btn-success' : 'btn-outline-secondary'; ?>">آج (Today)</a>
                    <a href="reports_profit.php?period=this_week" class="btn btn-sm <?php echo $period == 'this_week' ? 'btn-success' : 'btn-outline-secondary'; ?>">اس ہفتے (Week)</a>
                    <a href="reports_profit.php?period=this_month" class="btn btn-sm <?php echo $period == 'this_month' ? 'btn-success' : 'btn-outline-secondary'; ?>">اس مہینے (Month)</a>
                    <a href="reports_profit.php?period=this_year" class="btn btn-sm <?php echo $period == 'this_year' ? 'btn-success' : 'btn-outline-secondary'; ?>">اس سال (Year)</a>
                </div>
            </div>
            <div class="col-5 col-md-3">
                <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($from_date); ?>">
            </div>
            <div class="col-5 col-md-3">
                <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
            <div class="col-2 col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- Profit Summary KPI Cards -->
<div class="row g-3 mb-4">
    <!-- Net Sales -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">خالص فروخت (Net Sales)</div>
                <div class="kpi-value text-dark"><?php echo format_currency($net_sales); ?></div>
                <div class="small text-muted mt-1">ڈسکاؤنٹ و واپسی منہا کر کے</div>
            </div>
            <div class="kpi-icon bg-secondary-subtle text-dark"><i class="bi bi-cart-check"></i></div>
        </div>
    </div>

    <!-- COGS -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">فروخت شدہ مال کی لاگت (COGS)</div>
                <div class="kpi-value text-secondary"><?php echo format_currency($cogs); ?></div>
                <div class="small text-muted mt-1">خرید قیمت پر لاگت</div>
            </div>
            <div class="kpi-icon bg-light text-secondary"><i class="bi bi-box-seam"></i></div>
        </div>
    </div>

    <!-- Gross Profit -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">مجموعی منافع (Gross Profit)</div>
                <div class="kpi-value text-primary"><?php echo format_currency($gross_profit); ?></div>
                <div class="small text-muted mt-1">
                    مارجن: <strong><?php echo round($gross_margin_pct, 1); ?>%</strong>
                </div>
            </div>
            <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-graph-up"></i></div>
        </div>
    </div>

    <!-- Net Operating Profit -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card border border-2 <?php echo $net_profit >= 0 ? 'border-success' : 'border-danger'; ?>">
            <div>
                <div class="kpi-title fw-bold">خالص منافع (Net Profit)</div>
                <div class="kpi-value <?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo format_currency($net_profit); ?>
                </div>
                <div class="small text-muted mt-1">
                    اخراجات بعد خالص مارجن: <strong><?php echo round($net_margin_pct, 1); ?>%</strong>
                </div>
            </div>
            <div class="kpi-icon <?php echo $net_profit >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>">
                <i class="bi bi-cash-coin"></i>
            </div>
        </div>
    </div>
</div>

<!-- Formal Profit & Loss Statement Table -->
<div class="card border-0 shadow-sm rounded-3 mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-text me-2"></i> باضابطہ نفع و نقصان گوشوارہ (Income Statement)</h6>
        <span class="badge bg-light text-secondary border">مدت: <?php echo format_date($from_date); ?> تا <?php echo format_date($to_date); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" style="font-size: 0.95rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70%;">تفصیلات (Particulars)</th>
                        <th class="text-end" style="width: 30%;">رقم (Amount PKR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ps-4">مجموعی فروخت (Gross Sales Revenue)</td>
                        <td class="text-end fw-bold"><?php echo format_currency($gross_sales); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">منہا: سیلز پر رعایت (Less: Sales Discounts)</td>
                        <td class="text-end text-danger">- <?php echo format_currency($discounts); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">منہا: گاہک سے واپسی مال (Less: Sales Returns)</td>
                        <td class="text-end text-danger">- <?php echo format_currency($sales_returns); ?></td>
                    </tr>
                    <tr class="table-light fw-bold">
                        <td class="ps-4 text-primary">خالص فروخت (Net Sales)</td>
                        <td class="text-end text-primary fs-6"><?php echo format_currency($net_sales); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">منہا: فروخت شدہ مال کی لاگت خرید (Less: Cost of Goods Sold - COGS)</td>
                        <td class="text-end text-danger">- <?php echo format_currency($cogs); ?></td>
                    </tr>
                    <tr class="table-success fw-bold">
                        <td class="ps-4 text-success fs-6">مجموعی منافع (Gross Profit = Net Sales - COGS)</td>
                        <td class="text-end text-success fs-5"><?php echo format_currency($gross_profit); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4 text-muted">منہا: دکان کے تمام اخراجات (Less: Total Operating Expenses)</td>
                        <td class="text-end text-danger">- <?php echo format_currency($operating_expenses); ?></td>
                    </tr>
                    <tr class="<?php echo $net_profit >= 0 ? 'table-success' : 'table-danger'; ?> fw-bold">
                        <td class="ps-4 fs-5 <?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                            خالص منافع / بچت (Net Operating Profit)
                        </td>
                        <td class="text-end fs-4 <?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo format_currency($net_profit); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Product-wise Profit Breakdown -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-warning me-2"></i> پراڈکٹ کے لحاظ سے منافع کی درجہ بندی (Product-wise Profitability)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>پراڈکٹ کا نام</th>
                        <th class="text-center">فروخت مقدار</th>
                        <th class="text-end">آمدنی (Revenue)</th>
                        <th class="text-end">خرید لاگت (Cost)</th>
                        <th class="text-end fw-bold text-success">منافع (Profit)</th>
                        <th class="text-end">مارجن %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($item_profits)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">اس مدت میں کوئی فروخت ریکارڈ نہیں ہے</td></tr>
                    <?php else: ?>
                        <?php foreach ($item_profits as $i => $ip): 
                            $m_pct = $ip['total_revenue'] > 0 ? ($ip['item_profit'] / $ip['total_revenue']) * 100 : 0;
                        ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($ip['product_name']); ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?php echo number_format($ip['sold_qty'], 2); ?></span></td>
                                <td class="text-end"><?php echo format_currency($ip['total_revenue']); ?></td>
                                <td class="text-end text-muted small"><?php echo format_currency($ip['total_cost']); ?></td>
                                <td class="text-end fw-bold text-success fs-6"><?php echo format_currency($ip['item_profit']); ?></td>
                                <td class="text-end small fw-semibold <?php echo $m_pct >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo round($m_pct, 1); ?>%
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

