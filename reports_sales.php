<?php
// reports_sales.php - Sales Analytics & Trends
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

// Summary Totals
$totals = db_fetch_one("SELECT 
    COUNT(*) AS total_invoices,
    COALESCE(SUM(grand_total), 0) AS total_sales,
    COALESCE(SUM(paid_amount), 0) AS total_paid,
    COALESCE(SUM(balance_amount), 0) AS total_credit,
    COALESCE(SUM(discount_amount), 0) AS total_discounts 
    FROM sales 
    WHERE DATE(created_at) >= '$from_date' AND DATE(created_at) <= '$to_date' AND status = 'completed'");

// Payment Method Breakdown
$payments_breakdown = db_fetch_all("SELECT 
    sale_type,
    COUNT(*) AS count,
    SUM(grand_total) AS total 
    FROM sales 
    WHERE DATE(created_at) >= '$from_date' AND DATE(created_at) <= '$to_date' AND status = 'completed'
    GROUP BY sale_type");

// Cashier-wise Performance
$cashier_perf = db_fetch_all("SELECT 
    u.full_name,
    COUNT(s.id) AS invoices_count,
    SUM(s.grand_total) AS total_sales 
    FROM sales s 
    LEFT JOIN users u ON s.created_by = u.id 
    WHERE DATE(s.created_at) >= '$from_date' AND DATE(s.created_at) <= '$to_date' AND s.status = 'completed'
    GROUP BY s.created_by, u.full_name 
    ORDER BY total_sales DESC");

// Category-wise Sales
$category_sales = db_fetch_all("SELECT 
    c.name_ur,
    c.name_en,
    SUM(si.quantity) AS total_qty,
    SUM(si.subtotal) AS total_amount 
    FROM sale_items si 
    JOIN sales s ON si.sale_id = s.id 
    JOIN products p ON si.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE DATE(s.created_at) >= '$from_date' AND DATE(s.created_at) <= '$to_date' AND s.status = 'completed'
    GROUP BY c.id, c.name_ur, c.name_en 
    ORDER BY total_amount DESC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div id="report-print-area">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-bar-chart text-success me-2"></i> سیلز رپورٹس اور تجزیات (Sales Reports & Analytics)</h4>
        <div class="text-muted small">فروخت کی تاریخ، کیش بنام ادھار اور کیٹیگری و کیشیئر کارکردگی</div>
    </div>
    <button onclick="printSection('report-print-area','سیلز رپورٹ')" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-1"></i> پرنٹ رپورٹ
    </button>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="reports_sales.php" class="row g-2 align-items-center">
            <div class="col-5 col-md-4">
                <label class="form-label small fw-bold">تاریخ سے (From Date):</label>
                <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
            </div>
            <div class="col-5 col-md-4">
                <label class="form-label small fw-bold">تاریخ تک (To Date):</label>
                <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
            <div class="col-2 col-md-4 mt-auto">
                <button type="submit" class="btn btn-primary fw-bold px-4">رپورٹ دیکھیں</button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">کل سیلز بلز (Invoices)</div>
                <div class="kpi-value"><?php echo $totals['total_invoices']; ?></div>
            </div>
            <div class="kpi-icon bg-light text-dark"><i class="bi bi-receipt"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">کل فروخت (Grand Total)</div>
                <div class="kpi-value text-success"><?php echo format_currency($totals['total_sales']); ?></div>
            </div>
            <div class="kpi-icon bg-success-subtle text-success"><i class="bi bi-cart-check"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">نقد وصولی (Cash Paid)</div>
                <div class="kpi-value text-primary"><?php echo format_currency($totals['total_paid']); ?></div>
            </div>
            <div class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-cash"></i></div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="kpi-card">
            <div>
                <div class="kpi-title">ادھار فروخت (Credit Due)</div>
                <div class="kpi-value text-danger"><?php echo format_currency($totals['total_credit']); ?></div>
            </div>
            <div class="kpi-icon bg-danger-subtle text-danger"><i class="bi bi-credit-card"></i></div>
        </div>
    </div>
</div>

<!-- Category & Cashier Performance Row -->
<div class="row g-4">
    <!-- Category-wise Sales -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-tags text-primary me-2"></i> کیٹیگری کے لحاظ سے فروخت (Category-wise Sales)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>کیٹیگری</th>
                                <th class="text-center">فروخت مقدار</th>
                                <th class="text-end">کل رقم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($category_sales)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">کوئی ریکارڈ نہیں ملا</td></tr>
                            <?php else: ?>
                                <?php foreach ($category_sales as $cs): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($lang === 'ur' ? ($cs['name_ur'] ?: $cs['name_en']) : $cs['name_en']); ?></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border"><?php echo number_format($cs['total_qty'], 2); ?></span></td>
                                        <td class="text-end fw-bold"><?php echo format_currency($cs['total_amount']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Cashier-wise Performance -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-person-badge text-success me-2"></i> کیشیئر کارکردگی (Cashier Sales)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>کیشیئر نام</th>
                                <th class="text-center">بلز تعداد</th>
                                <th class="text-end">کل سیلز</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cashier_perf)): ?>
                                <tr><td colspan="3" class="text-center py-4 text-muted">کوئی ریکارڈ نہیں ملا</td></tr>
                            <?php else: ?>
                                <?php foreach ($cashier_perf as $cp): ?>
                                    <tr>
                                        <td class="fw-bold"><?php echo htmlspecialchars($cp['full_name'] ?: 'Admin'); ?></td>
                                        <td class="text-center"><span class="badge bg-success-subtle text-success"><?php echo $cp['invoices_count']; ?></span></td>
                                        <td class="text-end fw-bold"><?php echo format_currency($cp['total_sales']); ?></td>
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

</div><!-- #report-print-area -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

