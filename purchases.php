<?php
// purchases.php - Purchase Invoices History
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

// Search & Filters
$supp_id = (int)($_GET['supplier_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = "WHERE 1=1";
if ($supp_id > 0) $where .= " AND p.supplier_id = $supp_id";
if (!empty($from_date)) $where .= " AND p.purchase_date >= '$from_date'";
if (!empty($to_date)) $where .= " AND p.purchase_date <= '$to_date'";

$purchases = db_fetch_all("SELECT p.*, s.name AS supplier_name, s.company_name, u.full_name AS user_name, a.account_name 
                           FROM purchases p 
                           JOIN suppliers s ON p.supplier_id = s.id 
                           LEFT JOIN users u ON p.created_by = u.id 
                           LEFT JOIN accounts a ON p.payment_account_id = a.id 
                           $where 
                           ORDER BY p.id DESC");

$suppliers = db_fetch_all("SELECT * FROM suppliers WHERE status = 'active' ORDER BY name ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-receipt text-primary me-2"></i> خریداری بلز و انوائسز (Purchase Invoices)</h4>
        <div class="text-muted small">تمام خریداریاں، ادائیگی اور سپلائرز کے بلز</div>
    </div>
    <div class="d-flex gap-2">
        <a href="purchase_create.php" class="btn btn-primary fw-bold shadow-sm">
            <i class="bi bi-plus-circle me-1"></i> نئی خریداری درج کریں (+ Purchase)
        </a>
        <a href="suppliers.php" class="btn btn-outline-secondary">
            <i class="bi bi-truck me-1"></i> سپلائرز کھاتہ
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3">
        <form method="GET" action="purchases.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <select name="supplier_id" class="form-select">
                    <option value="0">تمام سپلائرز (All Suppliers)</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $supp_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['company_name'] ?: 'عام'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>" placeholder="From Date">
            </div>
            <div class="col-6 col-md-3">
                <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>" placeholder="To Date">
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-50 fw-semibold"><i class="bi bi-filter"></i> فلٹر</button>
                <a href="purchases.php" class="btn btn-outline-secondary w-50">ری سیٹ</a>
            </div>
        </form>
    </div>
</div>

<!-- Purchases Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>بل نمبر (Invoice #)</th>
                        <th>سپلائر کا بل #</th>
                        <th>سپلائر نام</th>
                        <th>تاریخ خریداری</th>
                        <th class="text-end">کل رقم (Total)</th>
                        <th class="text-end text-success">ادا شدہ (Paid)</th>
                        <th class="text-end text-danger">بقایا (Due)</th>
                        <th class="text-center">طریقہ</th>
                        <th>صارف</th>
                        <th class="text-center" style="width: 100px;">کارروائی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">کوئی خریداری ریکارڈ نہیں ہے</td></tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold text-primary">#<?php echo $p['invoice_no']; ?></span>
                                </td>
                                <td><code><?php echo htmlspecialchars($p['supplier_invoice_no'] ?: '-'); ?></code></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($p['supplier_name']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($p['company_name']); ?></div>
                                </td>
                                <td class="small"><?php echo format_date($p['purchase_date']); ?></td>
                                <td class="text-end fw-bold"><?php echo format_currency($p['grand_total']); ?></td>
                                <td class="text-end text-success fw-bold"><?php echo format_currency($p['paid_amount']); ?></td>
                                <td class="text-end text-danger fw-bold"><?php echo format_currency($p['balance_amount']); ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo $p['payment_type'] == 'cash' ? 'bg-success' : ($p['payment_type'] == 'credit' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                        <?php echo strtoupper($p['payment_type']); ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($p['user_name'] ?? 'Admin'); ?></td>
                                <td class="text-center">
                                    <a href="supplier_ledger.php?id=<?php echo $p['supplier_id']; ?>" class="btn btn-sm btn-outline-success" title="سپلائر کھاتہ">
                                        <i class="bi bi-journal-text"></i> کھاتہ
                                    </a>
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

