<?php
// supplier_ledger.php - Supplier Khata Ledger & Payment Processing
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$supplier_id = (int)($_GET['id'] ?? 0);
$supplier = db_fetch_one("SELECT * FROM suppliers WHERE id = $supplier_id");

if (!$supplier) {
    die("Supplier not found! سپلائر نہیں ملا۔");
}

$msg = '';
$err = '';

// Handle Payment to Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supplier_payment') {
    verify_csrf();

    $amount = (float)($_POST['amount'] ?? 0);
    $account_id = (int)($_POST['account_id'] ?? 1);
    $pay_date = $_POST['pay_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $ref_no = trim($_POST['ref_no'] ?? ('SPAY-' . date('Ymd-His')));

    if ($amount <= 0) {
        $err = "Please enter a valid amount! درست رقم درج کریں۔";
    } else {
        $conn->begin_transaction();
        try {
            $user_id = (int)$_SESSION['user_id'];
            $new_balance = (float)$supplier['current_balance'] - $amount;

            // 1. Update supplier balance
            $conn->query("UPDATE suppliers SET current_balance = $new_balance WHERE id = $supplier_id");

            // 2. Insert into supplier_ledger (Debit)
            $n_esc = db_escape($notes);
            $r_esc = db_escape($ref_no);
            $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_type, reference_no, debit, credit, balance_after, account_id, notes, created_by) 
                          VALUES ($supplier_id, 'payment', '$r_esc', $amount, 0.00, $new_balance, $account_id, '$n_esc', $user_id)");

            // 3. Deduct from payment account (Cash/Bank)
            $conn->query("UPDATE accounts SET current_balance = current_balance - $amount WHERE id = $account_id");

            // 4. If current shift is open and cash payment, update shift cash expenses/payments
            $shift = get_active_shift();
            if ($shift && $account_id == 1) { // 1 = Cash
                $conn->query("UPDATE shifts SET cash_supplier_payments = cash_supplier_payments + $amount WHERE id = " . $shift['id']);
            }

            log_audit("Supplier Payment", "suppliers", $supplier_id, "Paid: $amount, New Balance: $new_balance");

            $conn->commit();
            $msg = "Payment recorded successfully! سپلائر کو ادائیگی کامیابی سے درج ہو گئی۔";

            // Refresh supplier
            $supplier = db_fetch_one("SELECT * FROM suppliers WHERE id = $supplier_id");
        } catch (Exception $e) {
            $conn->rollback();
            $err = "Error: " . $e->getMessage();
        }
    }
}

// Fetch ledger entries
$ledger = db_fetch_all("SELECT sl.*, a.account_name, u.full_name AS user_name 
                        FROM supplier_ledger sl 
                        LEFT JOIN accounts a ON sl.account_id = a.id 
                        LEFT JOIN users u ON sl.created_by = u.id 
                        WHERE sl.supplier_id = $supplier_id 
                        ORDER BY sl.id ASC");

$accounts = db_fetch_all("SELECT * FROM accounts WHERE status = 'active' ORDER BY account_type ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div id="report-print-area">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark">
            <i class="bi bi-journal-text text-success me-2"></i> 
            سپلائر کھاتہ لیجر (Supplier Ledger): <?php echo htmlspecialchars($supplier['name']); ?>
        </h4>
        <div class="text-muted small">
            کمپنی: <strong><?php echo htmlspecialchars($supplier['company_name'] ?: 'عام'); ?></strong> | 
            فون: <?php echo htmlspecialchars($supplier['phone'] ?: '-'); ?> | 
            پتہ: <?php echo htmlspecialchars($supplier['address'] ?: '-'); ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="bi bi-cash-stack me-1"></i> سپلائر کو ادائیگی کریں (Make Payment)
        </button>
        <button onclick="printSection('report-print-area','سپلائر کھاتہ اسٹیٹمنٹ')" class="btn btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> اسٹیٹمنٹ پرنٹ
        </button>
        <a href="suppliers.php" class="btn btn-outline-secondary">واپس</a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Current Balance Card -->
<div class="card border-0 shadow-sm rounded-3 mb-4 bg-light">
    <div class="card-body p-3 d-flex justify-content-between align-items-center">
        <div>
            <div class="small text-muted">موجودہ واجب الادا رقم (Current Payable Balance):</div>
            <h2 class="fw-bold text-danger mb-0"><?php echo format_currency($supplier['current_balance']); ?></h2>
        </div>
        <div class="text-end">
            <span class="badge bg-secondary p-2">ابتدائی بیلنس: <?php echo format_currency($supplier['opening_balance']); ?></span>
        </div>
    </div>
</div>

<!-- Ledger Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-list-columns-reverse me-2"></i> مکمل لین دین کی تفصیل (Transaction History)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">تاریخ</th>
                        <th>تفصیل / حوالہ نمبر</th>
                        <th>اکاؤنٹ / ذریعہ</th>
                        <th class="text-end text-success fw-bold">ادا شدہ (Debit)</th>
                        <th class="text-end text-danger fw-bold">خریداری بل (Credit)</th>
                        <th class="text-end fw-bold">بقایا رقم (Balance)</th>
                        <th>صارف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">ابھی تک کوئی لین دین درج نہیں ہے</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger as $l): ?>
                            <tr>
                                <td class="small text-muted"><?php echo format_date($l['created_at']); ?></td>
                                <td>
                                    <div class="fw-bold">
                                        <?php 
                                            if ($l['transaction_type'] === 'purchase') echo '<span class="badge bg-primary-subtle text-primary me-1">خریداری</span>';
                                            elseif ($l['transaction_type'] === 'payment') echo '<span class="badge bg-success-subtle text-success me-1">ادائیگی کیش</span>';
                                            elseif ($l['transaction_type'] === 'purchase_return') echo '<span class="badge bg-warning-subtle text-warning-emphasis me-1">مال واپسی</span>';
                                            else echo '<span class="badge bg-secondary-subtle text-secondary me-1">ابتدائی</span>';
                                        ?>
                                        <code><?php echo htmlspecialchars($l['reference_no']); ?></code>
                                    </div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($l['notes'] ?: '-'); ?></div>
                                </td>
                                <td class="small"><?php echo htmlspecialchars($l['account_name'] ?: '-'); ?></td>
                                <td class="text-end fw-bold text-success">
                                    <?php echo $l['debit'] > 0 ? format_currency($l['debit']) : '-'; ?>
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    <?php echo $l['credit'] > 0 ? format_currency($l['credit']) : '-'; ?>
                                </td>
                                <td class="text-end fw-bold fs-6">
                                    <?php echo format_currency($l['balance_after']); ?>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($l['user_name'] ?? 'Admin'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Make Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i> سپلائر کو ادائیگی درج کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="supplier_ledger.php?id=<?php echo $supplier_id; ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="supplier_payment">
                <div class="modal-body p-4">
                    <div class="alert alert-light border small py-2 mb-3">
                        سپلائر: <strong><?php echo htmlspecialchars($supplier['name']); ?></strong><br>
                        کل واجب الادا: <span class="fw-bold text-danger"><?php echo format_currency($supplier['current_balance']); ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">ادا کردہ رقم (Amount Paid) *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="amount" class="form-control fw-bold text-success" placeholder="0.00" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">کس اکاؤنٹ سے ادائیگی کی گئی؟ *</label>
                        <select name="account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>" <?php echo $acc['account_type'] == 'cash' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($acc['account_name']); ?> (موجودہ: <?php echo format_currency($acc['current_balance']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">تاریخ ادائیگی</label>
                            <input type="date" name="pay_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">چیک / رسید نمبر (Ref #)</label>
                            <input type="text" name="ref_no" class="form-control" placeholder="Check/Receipt #">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">تفصیل / ریمارکس</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="مثلاً: کیش ادائیگی بذریعہ ڈرائیور..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">محفوظ کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

</div><!-- #report-print-area -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

