<?php
// customers.php - Customer Profiles & Khata System
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';

// Handle Add / Edit Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_customer') {
    verify_csrf();

    $cust_id = (int)($_POST['customer_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $customer_type = $_POST['customer_type'] ?? 'retail';
    $credit_limit = (float)($_POST['credit_limit'] ?? 10000);
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);

    if (empty($name)) {
        $err = "Customer name is required! گاہک کا نام لازمی ہے۔";
    } else {
        $n_esc = db_escape($name);
        $p_esc = db_escape($phone);
        $a_esc = db_escape($address);

        if ($cust_id > 0) {
            $sql = "UPDATE customers SET name = '$n_esc', phone = '$p_esc', address = '$a_esc', 
                    customer_type = '$customer_type', credit_limit = $credit_limit WHERE id = $cust_id";
            db_query($sql);
            $msg = "Customer updated successfully! گاہک کی معلومات تبدیل ہو گئیں۔";
        } else {
            $sql = "INSERT INTO customers (name, phone, address, customer_type, opening_balance, current_balance, credit_limit, status) 
                    VALUES ('$n_esc', '$p_esc', '$a_esc', '$customer_type', $opening_balance, $opening_balance, $credit_limit, 'active')";
            if (db_query($sql)) {
                $new_id = $conn->insert_id;
                if ($opening_balance > 0) {
                    db_query("INSERT INTO customer_ledger (customer_id, transaction_type, reference_no, debit, credit, balance_after, notes) 
                              VALUES ($new_id, 'opening', 'OPENING-BAL', $opening_balance, 0.00, $opening_balance, 'ابتدائی ادھار کھاتہ')");
                }
                $msg = "New customer account opened! نیا گاہک کھاتہ بن گیا۔";
            }
        }
    }
}

// Quick Payment Recovery from modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_receive_payment') {
    verify_csrf();

    $cid = (int)$_POST['pay_customer_id'];
    $amount = (float)$_POST['pay_amount'];
    $account_id = (int)$_POST['pay_account_id'];
    $notes = trim($_POST['pay_notes'] ?? '');

    if ($cid > 0 && $amount > 0) {
        $cust = db_fetch_one("SELECT * FROM customers WHERE id = $cid");
        if ($cust) {
            $conn->begin_transaction();
            try {
                $user_id = (int)$_SESSION['user_id'];
                $new_bal = (float)$cust['current_balance'] - $amount;

                // 1. Update customer balance
                $conn->query("UPDATE customers SET current_balance = $new_bal WHERE id = $cid");

                // 2. Add customer ledger entry (Credit)
                $n_esc = db_escape($notes);
                $ref = 'RCV-' . date('Ymd-His');
                $conn->query("INSERT INTO customer_ledger (customer_id, transaction_type, reference_no, debit, credit, balance_after, account_id, notes, created_by) 
                              VALUES ($cid, 'payment', '$ref', 0.00, $amount, $new_bal, $account_id, '$n_esc', $user_id)");

                // 3. Deposit in financial account
                $conn->query("UPDATE accounts SET current_balance = current_balance + $amount WHERE id = $account_id");

                // 4. Shift cash tracking
                $shift = get_active_shift();
                if ($shift && $account_id == 1) {
                    $conn->query("UPDATE shifts SET cash_recoveries = cash_recoveries + $amount WHERE id = " . $shift['id']);
                }

                $conn->commit();
                $msg = "Payment of " . format_currency($amount) . " received successfully from " . htmlspecialchars($cust['name']) . "!";
            } catch (Exception $e) {
                $conn->rollback();
                $err = $e->getMessage();
            }
        }
    }
}

// Search
$q = trim($_GET['q'] ?? '');
$filter_balance = $_GET['filter_balance'] ?? '';

$where = "WHERE status = 'active'";
if (!empty($q)) {
    $q_esc = db_escape($q);
    $where .= " AND (name LIKE '%$q_esc%' OR phone LIKE '%$q_esc%' OR address LIKE '%$q_esc%')";
}
if ($filter_balance === 'due') {
    $where .= " AND current_balance > 0";
}

$customers = db_fetch_all("SELECT c.*, 
                          (SELECT COUNT(*) FROM sales WHERE customer_id = c.id) AS sales_count 
                          FROM customers c 
                          $where 
                          ORDER BY c.current_balance DESC");

// Total outstanding
$tot_outstanding = db_fetch_one("SELECT COALESCE(SUM(current_balance), 0) AS total FROM customers WHERE status = 'active'");
$accounts = db_fetch_all("SELECT * FROM accounts WHERE status = 'active' ORDER BY account_type ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-people text-success me-2"></i> گاہک کھاتہ سسٹم (Customer Accounts & Khata)</h4>
        <div class="text-muted small">ادھار گاہک، کریڈٹ لمٹ، وصولیاں اور مکمل کھاتہ لیجر</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success fw-bold shadow-sm" onclick="openAddCustomerModal()">
            <i class="bi bi-person-plus me-1"></i> نیا گاہک شامل کریں (Add Customer)
        </button>
        <a href="pos.php" class="btn btn-primary fw-bold">
            <i class="bi bi-cart me-1"></i> پی او ایس بلنگ
        </a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Total Receivables Card -->
<div class="card border-0 bg-warning-subtle text-warning-emphasis shadow-sm mb-4">
    <div class="card-body p-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-semibold">گاہکوں سے کل وصول طلب ادھار کھاتہ (Total Outstanding Balances):</span>
            <h3 class="fw-bold mb-0 text-danger"><?php echo format_currency($tot_outstanding['total']); ?></h3>
        </div>
        <div class="d-flex gap-2">
            <a href="customers.php?filter_balance=due" class="btn btn-sm btn-outline-danger fw-bold">صرف بقایا والے گاہک</a>
            <a href="customers.php" class="btn btn-sm btn-outline-secondary">تمام گاہک</a>
        </div>
    </div>
</div>

<!-- Customers Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>گاہک کا نام</th>
                        <th>موبائل نمبر</th>
                        <th>پتہ / محلہ</th>
                        <th>قسم</th>
                        <th class="text-end">ادھار کی حد (Limit)</th>
                        <th class="text-end fw-bold">موجودہ ادھار بقایا (Balance)</th>
                        <th class="text-center" style="width: 170px;">کارروائی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">کوئی گاہک ریکارڈ موجود نہیں ہے</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $i => $c): ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="fw-bold fs-6"><?php echo htmlspecialchars($c['name']); ?></div>
                                    <div class="small text-muted"><?php echo $c['sales_count']; ?> مرتبہ خریداری کی</div>
                                </td>
                                <td>
                                    <?php if ($c['phone']): ?>
                                        <a href="tel:<?php echo $c['phone']; ?>" class="text-decoration-none">
                                            <i class="bi bi-telephone text-success me-1"></i><?php echo htmlspecialchars($c['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?php echo htmlspecialchars($c['address'] ?: '-'); ?></td>
                                <td>
                                    <span class="badge <?php echo $c['customer_type'] == 'wholesale' ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary'; ?>">
                                        <?php echo $c['customer_type'] == 'wholesale' ? 'تھوک (Wholesale)' : 'پرچون (Retail)'; ?>
                                    </span>
                                </td>
                                <td class="text-end text-muted small">
                                    <?php echo format_currency($c['credit_limit']); ?>
                                </td>
                                <td class="text-end fw-bold <?php echo $c['current_balance'] > 0 ? 'text-danger fs-6' : 'text-success'; ?>">
                                    <?php echo format_currency($c['current_balance']); ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="customer_ledger.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-success fw-bold" title="کھاتہ دیکھیں">
                                            <i class="bi bi-journal-text"></i> کھاتہ
                                        </a>
                                        <button class="btn btn-success" onclick="openReceivePaymentModal(<?php echo $c['id']; ?>, '<?php echo addslashes($c['name']); ?>', <?php echo $c['current_balance']; ?>)" title="ادائیگی وصول کریں">
                                            <i class="bi bi-cash"></i> وصولی
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="openEditCustomerModal(<?php echo htmlspecialchars(json_encode($c)); ?>)" title="ترمیم">
                                            <i class="bi bi-pencil"></i>
                                        </button>
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

<!-- Add / Edit Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="custModalTitle">نیا گاہک کھاتہ شامل کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customers.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_customer">
                <input type="hidden" name="customer_id" id="modalCustId" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">گاہک کا نام *</label>
                        <input type="text" name="name" id="custName" class="form-control" placeholder="مثلاً: چوہدری اسلم محلہ دار" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">موبائل فون نمبر</label>
                            <input type="text" name="phone" id="custPhone" class="form-control" placeholder="03001234567">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">گاہک کی قسم</label>
                            <select name="customer_type" id="custType" class="form-select">
                                <option value="retail">پرچون (Retail)</option>
                                <option value="wholesale">تھوک (Wholesale)</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">ادھار کی حد (Credit Limit)</label>
                            <input type="number" step="0.01" name="credit_limit" id="custLimit" class="form-control" value="10000.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">ابتدائی سابقہ ادھار (اگر ہے)</label>
                            <input type="number" step="0.01" name="opening_balance" id="custOpening" class="form-control" value="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">پتہ / رہائش</label>
                        <textarea name="address" id="custAddress" class="form-control" rows="2" placeholder="گھر نمبر، گلی، محلہ..."></textarea>
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

<!-- Quick Receive Payment Modal -->
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i> گاہک سے ادھار وصولی (Receive Payment)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="customers.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="quick_receive_payment">
                <input type="hidden" name="pay_customer_id" id="rcvCustId" value="0">
                <div class="modal-body p-4">
                    <div class="alert alert-light border small py-2 mb-3">
                        گاہک: <strong id="rcvCustName">-</strong><br>
                        موجودہ ادھار بقایا: <span class="fw-bold text-danger fs-6" id="rcvCustBalance">Rs. 0.00</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">وصول شدہ رقم (Received Amount) *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="pay_amount" id="rcvAmount" class="form-control fw-bold text-success fs-4" placeholder="0.00" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">کس اکاؤنٹ میں جمع ہوا؟ *</label>
                        <select name="pay_account_id" class="form-select" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>" <?php echo $acc['account_type'] == 'cash' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($acc['account_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">ریمارکس / رسید نوٹ</label>
                        <input type="text" name="pay_notes" class="form-control" placeholder="مثلاً: بقایا جات کی ادائیگی...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">وصولی درج کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddCustomerModal() {
    document.getElementById('custModalTitle').innerText = 'نیا گاہک کھاتہ شامل کریں';
    document.getElementById('modalCustId').value = '0';
    document.getElementById('custName').value = '';
    document.getElementById('custPhone').value = '';
    document.getElementById('custType').value = 'retail';
    document.getElementById('custLimit').value = '10000.00';
    document.getElementById('custOpening').value = '0.00';
    document.getElementById('custOpening').disabled = false;
    document.getElementById('custAddress').value = '';
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function openEditCustomerModal(c) {
    document.getElementById('custModalTitle').innerText = 'گاہک کی معلومات تبدیل کریں';
    document.getElementById('modalCustId').value = c.id;
    document.getElementById('custName').value = c.name;
    document.getElementById('custPhone').value = c.phone;
    document.getElementById('custType').value = c.customer_type;
    document.getElementById('custLimit').value = c.credit_limit;
    document.getElementById('custOpening').value = c.opening_balance;
    document.getElementById('custOpening').disabled = true;
    document.getElementById('custAddress').value = c.address;
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

function openReceivePaymentModal(id, name, balance) {
    document.getElementById('rcvCustId').value = id;
    document.getElementById('rcvCustName').innerText = name;
    document.getElementById('rcvCustBalance').innerText = 'Rs. ' + parseFloat(balance).toFixed(2);
    document.getElementById('rcvAmount').value = '';
    new bootstrap.Modal(document.getElementById('receiveModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

