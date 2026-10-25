<?php
// sales.php - Sales Invoices History & Reprint
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

// Search & Filters
$q = trim($_GET['q'] ?? '');
$sale_type = $_GET['sale_type'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = "WHERE 1=1";
if (!empty($q)) {
    $q_esc = db_escape($q);
    $where .= " AND (s.invoice_no LIKE '%$q_esc%' OR s.customer_name LIKE '%$q_esc%')";
}
if (!empty($sale_type)) {
    $where .= " AND s.sale_type = '$sale_type'";
}
if (!empty($from_date)) {
    $where .= " AND DATE(s.created_at) >= '$from_date'";
}
if (!empty($to_date)) {
    $where .= " AND DATE(s.created_at) <= '$to_date'";
}

$sales = db_fetch_all("SELECT s.*, u.full_name AS cashier_name, a.account_name 
                      FROM sales s 
                      LEFT JOIN users u ON s.created_by = u.id 
                      LEFT JOIN accounts a ON s.payment_account_id = a.id 
                      $where 
                      ORDER BY s.id DESC LIMIT 100");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-receipt-cutoff text-success me-2"></i> سیلز انوائسز اور بلز (Sales Invoices)</h4>
        <div class="text-muted small">تمام فروخت شدہ بلز، کیش، ادھار اور رسید ری پرنٹ</div>
    </div>
    <div class="d-flex gap-2">
        <a href="pos.php" class="btn btn-success fw-bold shadow-sm">
            <i class="bi bi-cart-plus me-1"></i> نیا بل بنائیں (POS)
        </a>
        <a href="returns.php" class="btn btn-outline-danger fw-semibold">
            <i class="bi bi-arrow-counterclockwise me-1"></i> سیلز واپسی (Returns)
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form method="GET" action="sales.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <input type="text" name="q" class="form-control" placeholder="بل نمبر یا گاہک کا نام تلاش کریں..." value="<?php echo htmlspecialchars($q); ?>">
            </div>
            <div class="col-6 col-md-2">
                <select name="sale_type" class="form-select">
                    <option value="">تمام بل اقسام</option>
                    <option value="cash" <?php echo $sale_type == 'cash' ? 'selected' : ''; ?>>صرف نقد (Cash)</option>
                    <option value="credit" <?php echo $sale_type == 'credit' ? 'selected' : ''; ?>>صرف ادھار (Credit)</option>
                    <option value="split" <?php echo $sale_type == 'split' ? 'selected' : ''; ?>>مخلوط (Split)</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-50 fw-semibold"><i class="bi bi-filter"></i> فلٹر</button>
                <a href="sales.php" class="btn btn-outline-secondary w-50">ری سیٹ</a>
            </div>
        </form>
    </div>
</div>

<!-- Sales Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>بل نمبر (Invoice #)</th>
                        <th>تاریخ و وقت</th>
                        <th>گاہک کا نام</th>
                        <th>طریقہ بلنگ</th>
                        <th class="text-end">میزان (Subtotal)</th>
                        <th class="text-end">رعایت (Disc)</th>
                        <th class="text-end fw-bold">کل بل (Grand Total)</th>
                        <th class="text-end text-success fw-bold">وصول (Paid)</th>
                        <th class="text-end text-danger fw-bold">ادھار (Due)</th>
                        <th>کیشیئر</th>
                        <th class="text-center" style="width: 130px;">رسید پرنٹ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr><td colspan="11" class="text-center py-5 text-muted">کوئی سیلز بل نہیں ملا</td></tr>
                    <?php else: ?>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-success">#<?php echo $s['invoice_no']; ?></span>
                                </td>
                                <td class="small text-muted"><?php echo format_datetime($s['created_at']); ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($s['customer_name'] ?: 'عام خریدار'); ?></div>
                                </td>
                                <td>
                                    <?php if ($s['sale_type'] == 'cash'): ?>
                                        <span class="badge bg-success-subtle text-success">نقد (Cash)</span>
                                    <?php elseif ($s['sale_type'] == 'credit'): ?>
                                        <span class="badge bg-danger-subtle text-danger">ادھار (Credit)</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">مخلوط (Split)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-muted small"><?php echo format_currency($s['subtotal']); ?></td>
                                <td class="text-end text-muted small"><?php echo $s['discount_amount'] > 0 ? format_currency($s['discount_amount']) : '-'; ?></td>
                                <td class="text-end fw-bold"><?php echo format_currency($s['grand_total']); ?></td>
                                <td class="text-end text-success fw-bold"><?php echo format_currency($s['paid_amount']); ?></td>
                                <td class="text-end text-danger fw-bold"><?php echo $s['balance_amount'] > 0 ? format_currency($s['balance_amount']) : '-'; ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($s['cashier_name'] ?? 'Admin'); ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="print_invoice.php?id=<?php echo $s['id']; ?>&format=80mm" target="_blank" class="btn btn-outline-success" title="تھرمل رسید پرنٹ">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <a href="print_invoice.php?id=<?php echo $s['id']; ?>&format=a4" target="_blank" class="btn btn-outline-primary" title="A4 انوائس">
                                            <i class="bi bi-file-earmark-text"></i>
                                        </a>
                                        <a href="returns.php?invoice_no=<?php echo $s['invoice_no']; ?>" class="btn btn-outline-danger" title="واپسی کریں">
                                            <i class="bi bi-arrow-counterclockwise"></i>
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

