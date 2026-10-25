<?php
// accounts.php - Dynamic Financial Accounts & Internal Fund Transfers
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';

// Handle Add / Edit Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_account') {
    verify_csrf();

    $acc_id = (int)($_POST['account_id'] ?? 0);
    $account_name = trim($_POST['account_name'] ?? '');
    $account_type = $_POST['account_type'] ?? 'bank';
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $initial_balance = (float)($_POST['initial_balance'] ?? 0);

    if (empty($account_name)) {
        $err = "Account name is required! اکاؤنٹ کا نام لازمی ہے۔";
    } else {
        $n_esc = db_escape($account_name);
        $num_esc = db_escape($account_number);
        $b_esc = db_escape($bank_name);

        if ($acc_id > 0) {
            $sql = "UPDATE accounts SET account_name = '$n_esc', account_type = '$account_type', 
                    account_number = '$num_esc', bank_name = '$b_esc' WHERE id = $acc_id";
            db_query($sql);
            $msg = "Account details updated! اکاؤنٹ کی معلومات تبدیل ہو گئیں۔";
        } else {
            $sql = "INSERT INTO accounts (account_name, account_type, account_number, bank_name, current_balance, status) 
                    VALUES ('$n_esc', '$account_type', '$num_esc', '$b_esc', $initial_balance, 'active')";
            db_query($sql);
            $msg = "New account added! نیا اکاؤنٹ کامیابی سے شامل ہو گیا۔";
        }
    }
}

// Handle Fund Transfer between accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer_funds') {
    verify_csrf();

    $from_acc_id = (int)$_POST['from_account_id'];
    $to_acc_id = (int)$_POST['to_account_id'];
    $amount = (float)$_POST['amount'];
    $transfer_date = $_POST['transfer_date'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');

    if ($from_acc_id === $to_acc_id) {
        $err = "Source and Destination accounts cannot be the same! دونوں اکاؤنٹس ایک جیسے نہیں ہو سکتے۔";
    } elseif ($amount <= 0) {
        $err = "Please enter a valid transfer amount!";
    } else {
        $from_acc = db_fetch_one("SELECT * FROM accounts WHERE id = $from_acc_id");
        if (!$from_acc || (float)$from_acc['current_balance'] < $amount) {
            $err = "Insufficient balance in source account! منتخب اکاؤنٹ میں ناکافی رقم ہے۔";
        } else {
            $conn->begin_transaction();
            try {
                $user_id = (int)$_SESSION['user_id'];
                $ref = 'TRF-' . date('Ymd-His');
                $note_esc = db_escape($note);

                // Deduct from sender
                $conn->query("UPDATE accounts SET current_balance = current_balance - $amount WHERE id = $from_acc_id");

                // Add to receiver
                $conn->query("UPDATE accounts SET current_balance = current_balance + $amount WHERE id = $to_acc_id");

                // Log transfer
                $conn->query("INSERT INTO account_transfers (from_account_id, to_account_id, amount, transfer_date, reference, note, created_by) 
                              VALUES ($from_acc_id, $to_acc_id, $amount, '$transfer_date', '$ref', '$note_esc', $user_id)");

                log_audit("Account Transfer", "account_transfers", $conn->insert_id, "From: $from_acc_id to $to_acc_id, Amount: $amount");

                $conn->commit();
                $msg = "Funds transferred successfully! رقم کامیابی سے منتقل کر دی گئی۔";
            } catch (Exception $e) {
                $conn->rollback();
                $err = $e->getMessage();
            }
        }
    }
}

$accounts = db_fetch_all("SELECT * FROM accounts WHERE status = 'active' ORDER BY account_type ASC, id ASC");

// Transfers history
$transfers = db_fetch_all("SELECT t.*, fa.account_name AS from_name, ta.account_name AS to_name, u.full_name AS user_name 
                          FROM account_transfers t 
                          JOIN accounts fa ON t.from_account_id = fa.id 
                          JOIN accounts ta ON t.to_account_id = ta.id 
                          LEFT JOIN users u ON t.created_by = u.id 
                          ORDER BY t.id DESC LIMIT 15");

// Total balance
$tot_bal = db_fetch_one("SELECT COALESCE(SUM(current_balance), 0) AS total FROM accounts WHERE status = 'active'");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-bank text-info me-2"></i> مالیاتی اکاؤنٹس و والٹس (Financial Accounts & Wallets)</h4>
        <div class="text-muted small">دکان کیش، بینک اکاؤنٹس، جاز کیش اور ایزی پیسہ بیلنس مینیج کریں</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#transferModal">
            <i class="bi bi-arrow-left-right me-1"></i> رقم ٹرانسفر کریں (Transfer Funds)
        </button>
        <button class="btn btn-success fw-bold shadow-sm" onclick="openAddAccountModal()">
            <i class="bi bi-plus-circle me-1"></i> نیا اکاؤنٹ شامل کریں (Add Account)
        </button>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Total Liquid Funds Banner -->
<div class="card border-0 bg-info-subtle text-info-emphasis shadow-sm mb-4">
    <div class="card-body p-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-semibold">تمام کیش و بینکس کا مجموعی بیلنس (Total Liquid Balance):</span>
            <h3 class="fw-bold mb-0 text-info"><?php echo format_currency($tot_bal['total']); ?></h3>
        </div>
        <i class="bi bi-wallet2 fs-1 text-info opacity-50"></i>
    </div>
</div>

<!-- Accounts Cards Grid -->
<div class="row g-3 mb-4">
    <?php foreach ($accounts as $acc): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100 rounded-3">
                <div class="card-body p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge <?php 
                                if ($acc['account_type'] == 'cash') echo 'bg-success';
                                elseif ($acc['account_type'] == 'wallet_jazzcash') echo 'bg-danger';
                                elseif ($acc['account_type'] == 'wallet_easypaisa') echo 'bg-success';
                                else echo 'bg-primary';
                            ?> text-uppercase">
                                <?php echo str_replace('wallet_', '', $acc['account_type']); ?>
                            </span>
                            <button class="btn btn-sm btn-light p-1" onclick="openEditAccountModal(<?php echo htmlspecialchars(json_encode($acc)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($acc['account_name']); ?></h6>
                        <div class="small text-muted mb-2">
                            <?php echo htmlspecialchars($acc['bank_name'] ?: 'Local Safe'); ?><br>
                            <code><?php echo htmlspecialchars($acc['account_number'] ?: '-'); ?></code>
                        </div>
                    </div>
                    <div class="pt-2 border-top">
                        <span class="small text-muted d-block">موجودہ بیلنس:</span>
                        <h4 class="fw-bold text-success mb-0"><?php echo format_currency($acc['current_balance']); ?></h4>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Fund Transfers History -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i> باہمی فنڈز منتقلی کی تاریخ (Internal Fund Transfers)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>تاریخ</th>
                        <th>حوالہ #</th>
                        <th>کس اکاؤنٹ سے (From)</th>
                        <th>کس اکاؤنٹ میں (To)</th>
                        <th class="text-end fw-bold">رقم (Amount)</th>
                        <th>تفصیل / نوٹ</th>
                        <th>صارف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transfers)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">ابھی تک کوئی فنڈز ٹرانسفر نہیں ہوا</td></tr>
                    <?php else: ?>
                        <?php foreach ($transfers as $t): ?>
                            <tr>
                                <td class="small text-muted"><?php echo format_date($t['transfer_date']); ?></td>
                                <td><code><?php echo $t['reference']; ?></code></td>
                                <td class="fw-semibold text-danger"><?php echo htmlspecialchars($t['from_name']); ?></td>
                                <td class="fw-semibold text-success"><?php echo htmlspecialchars($t['to_name']); ?></td>
                                <td class="text-end fw-bold fs-6 text-primary"><?php echo format_currency($t['amount']); ?></td>
                                <td class="small"><?php echo htmlspecialchars($t['note'] ?: '-'); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($t['user_name'] ?? 'Admin'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="accModalTitle">نیا اکاؤنٹ شامل کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="accounts.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_account">
                <input type="hidden" name="account_id" id="modalAccId" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">اکاؤنٹ کا نام *</label>
                        <input type="text" name="account_name" id="accName" class="form-control" placeholder="مثلاً: دکان دراز کیش، میزان بینک، جاز کیش" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">اکاؤنٹ کی قسم</label>
                            <select name="account_type" id="accType" class="form-select">
                                <option value="cash">کیش (Cash in Hand)</option>
                                <option value="bank">بینک اکاؤنٹ (Bank)</option>
                                <option value="wallet_jazzcash">جاز کیش (JazzCash)</option>
                                <option value="wallet_easypaisa">ایزی پیسہ (Easypaisa)</option>
                                <option value="other">دیگر (Other)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">ابتدائی بیلنس (اگر نیا ہے)</label>
                            <input type="number" step="0.01" name="initial_balance" id="accInitial" class="form-control" value="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">بینک کا نام / برانچ</label>
                        <input type="text" name="bank_name" id="accBank" class="form-control" placeholder="Meezan, HBL, UBL, Alfalah...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">اکاؤنٹ / IBAN نمبر یا موبائل نمبر</label>
                        <input type="text" name="account_number" id="accNum" class="form-control" placeholder="03001234567 or IBAN">
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

<!-- Fund Transfer Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i> باہمی فنڈز ٹرانسفر (Internal Transfer)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="accounts.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="transfer_funds">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">کس اکاؤنٹ سے رقم نکالنی ہے؟ (From) *</label>
                        <select name="from_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>">
                                    <?php echo htmlspecialchars($acc['account_name']); ?> (موجودہ: <?php echo format_currency($acc['current_balance']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">کس اکاؤنٹ میں جمع کرنی ہے؟ (To) *</label>
                        <select name="to_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>" <?php echo $acc['account_type'] == 'bank' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($acc['account_name']); ?> (موجودہ: <?php echo format_currency($acc['current_balance']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">منتقل کی جانے والی رقم (Amount) *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="amount" class="form-control fw-bold text-primary fs-4" placeholder="0.00" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">تاریخ منتقلی</label>
                        <input type="date" name="transfer_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">وضاحت / ریمارکس</label>
                        <input type="text" name="note" class="form-control" placeholder="مثلاً: دکان کیش سے بینک میں ڈیپازٹ کروایا...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">رقم منتقل کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddAccountModal() {
    document.getElementById('accModalTitle').innerText = 'نیا اکاؤنٹ شامل کریں';
    document.getElementById('modalAccId').value = '0';
    document.getElementById('accName').value = '';
    document.getElementById('accType').value = 'bank';
    document.getElementById('accInitial').value = '0.00';
    document.getElementById('accInitial').disabled = false;
    document.getElementById('accBank').value = '';
    document.getElementById('accNum').value = '';
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}

function openEditAccountModal(a) {
    document.getElementById('accModalTitle').innerText = 'اکاؤنٹ ترمیم کریں';
    document.getElementById('modalAccId').value = a.id;
    document.getElementById('accName').value = a.account_name;
    document.getElementById('accType').value = a.account_type;
    document.getElementById('accInitial').value = a.current_balance;
    document.getElementById('accInitial').disabled = true;
    document.getElementById('accBank').value = a.bank_name;
    document.getElementById('accNum').value = a.account_number;
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

