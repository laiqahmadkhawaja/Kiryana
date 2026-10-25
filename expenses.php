<?php
// expenses.php - Operating Expenses Management & Categories
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_expense') {
    verify_csrf();

    $category_id = (int)($_POST['category_id'] ?? 0);
    $account_id = (int)($_POST['account_id'] ?? 1);
    $amount = (float)($_POST['amount'] ?? 0);
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $description = trim($_POST['description'] ?? '');
    $reference_no = trim($_POST['reference_no'] ?? ('EXP-' . date('Ymd-His')));

    if ($category_id <= 0 || $amount <= 0) {
        $err = "Please select category and enter valid amount! کیٹیگری اور رقم درست لکھیں۔";
    } else {
        $conn->begin_transaction();
        try {
            $user_id = (int)$_SESSION['user_id'];
            $shift = get_active_shift();
            $shift_id = $shift ? (int)$shift['id'] : 0;
            $desc_esc = db_escape($description);
            $ref_esc = db_escape($reference_no);

            // 1. Insert expense
            $conn->query("INSERT INTO expenses (category_id, account_id, shift_id, amount, expense_date, description, reference_no, created_by) 
                          VALUES ($category_id, $account_id, $shift_id, $amount, '$expense_date', '$desc_esc', '$ref_esc', $user_id)");

            // 2. Deduct from payment account
            $conn->query("UPDATE accounts SET current_balance = current_balance - $amount WHERE id = $account_id");

            // 3. Update shift expenses if cash
            if ($shift_id > 0 && $account_id == 1) {
                $conn->query("UPDATE shifts SET cash_expenses = cash_expenses + $amount WHERE id = $shift_id");
            }

            log_audit("Add Expense", "expenses", $conn->insert_id, "Amount: $amount, Cat: $category_id");

            $conn->commit();
            $msg = "Expense of " . format_currency($amount) . " recorded successfully! خرچہ درج ہو گیا۔";
        } catch (Exception $e) {
            $conn->rollback();
            $err = $e->getMessage();
        }
    }
}

// Handle Add Expense Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_expense_category') {
    verify_csrf();
    $name_ur = trim($_POST['cat_name_ur'] ?? '');
    $name_en = trim($_POST['cat_name_en'] ?? '');

    if (empty($name_ur) && empty($name_en)) {
        $err = "Expense category name is required!";
    } else {
        $u_esc = db_escape($name_ur);
        $e_esc = db_escape($name_en);
        db_query("INSERT INTO expense_categories (name_en, name_ur) VALUES ('$e_esc', '$u_esc')");
        $msg = "New expense category added! نئی اخراجات کی کیٹیگری شامل ہو گئی۔";
    }
}

// Search & Filter
$filter_cat = (int)($_GET['category_id'] ?? 0);
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

$where = "WHERE 1=1";
if ($filter_cat > 0) $where .= " AND e.category_id = $filter_cat";
if (!empty($from_date)) $where .= " AND e.expense_date >= '$from_date'";
if (!empty($to_date)) $where .= " AND e.expense_date <= '$to_date'";

$expenses = db_fetch_all("SELECT e.*, ec.name_ur AS cat_ur, ec.name_en AS cat_en, a.account_name, u.full_name AS user_name 
                          FROM expenses e 
                          JOIN expense_categories ec ON e.category_id = ec.id 
                          LEFT JOIN accounts a ON e.account_id = a.id 
                          LEFT JOIN users u ON e.created_by = u.id 
                          $where 
                          ORDER BY e.expense_date DESC, e.id DESC");

$total_period_expense = db_fetch_one("SELECT COALESCE(SUM(e.amount), 0) AS total 
                                     FROM expenses e 
                                     $where");

$categories = db_fetch_all("SELECT * FROM expense_categories WHERE status = 'active' ORDER BY name_en ASC");
$accounts = db_fetch_all("SELECT * FROM accounts WHERE status = 'active' ORDER BY account_type ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-wallet2 text-danger me-2"></i> دکان کے اخراجات (Operating Expenses)</h4>
        <div class="text-muted small">کرایہ، بجلی، تنخواہیں، ٹرانسپورٹ، شاپر اور متفرق اخراجات</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bi bi-plus-circle me-1"></i> نیا خرچہ درج کریں (Record Expense)
        </button>
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addExpenseCatModal">
            <i class="bi bi-folder-plus me-1"></i> نئی کیٹیگری
        </button>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Filter & Summary Bar -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3">
                <form method="GET" action="expenses.php" class="row g-2 align-items-center">
                    <div class="col-12 col-md-4">
                        <select name="category_id" class="form-select">
                            <option value="0">تمام اخراجات کیٹیگریز</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $filter_cat == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lang === 'ur' ? ($c['name_ur'] ?: $c['name_en']) : $c['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-1">
                        <button type="submit" class="btn btn-primary w-50 fw-semibold">فلٹر</button>
                        <a href="expenses.php" class="btn btn-outline-secondary w-50">ری سیٹ</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4">
        <div class="kpi-card h-100 bg-danger-subtle text-danger border-0">
            <div>
                <div class="kpi-title text-danger">منتخب مدت کے کل اخراجات</div>
                <div class="kpi-value text-danger"><?php echo format_currency($total_period_expense['total']); ?></div>
            </div>
            <div class="kpi-icon bg-danger text-white">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
    </div>
</div>

<!-- Expenses Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>تاریخ</th>
                        <th>کیٹیگری خرچہ</th>
                        <th>تفصیل / وجہ</th>
                        <th>ادائیگی ذریعہ (Account)</th>
                        <th>حوالہ #</th>
                        <th class="text-end fw-bold">رقم خرچ</th>
                        <th>صارف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">اس مدت میں کوئی خرچہ درج نہیں ہوا</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $i => $e): ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td class="small fw-semibold"><?php echo format_date($e['expense_date']); ?></td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary fs-6">
                                        <?php echo htmlspecialchars($lang === 'ur' ? ($e['cat_ur'] ?: $e['cat_en']) : $e['cat_en']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($e['description'] ?: '-'); ?></td>
                                <td class="small"><?php echo htmlspecialchars($e['account_name'] ?: 'Cash'); ?></td>
                                <td><code><?php echo htmlspecialchars($e['reference_no'] ?: '-'); ?></code></td>
                                <td class="text-end fw-bold text-danger fs-6"><?php echo format_currency($e['amount']); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($e['user_name'] ?? 'Admin'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Expense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i> نیا خرچہ درج کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="expenses.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_expense">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">اخراجات کیٹیگری *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">منتخب کریں...</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($lang === 'ur' ? ($c['name_ur'] ?: $c['name_en']) : $c['name_en']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">خرچ کی رقم (Amount) *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="amount" class="form-control fw-bold text-danger fs-4" placeholder="0.00" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">کس اکاؤنٹ سے ادا کیا؟ *</label>
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
                            <label class="form-label fw-bold small">تاریخ خرچہ</label>
                            <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">رسید یا واؤچر #</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="Voucher #">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">تفصیل / وضاحت</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="مثلاً: چائے اور بسکٹ مہمانوں کے لیے، شاپر 5 کلو..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">محفوظ کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add Expense Category -->
<div class="modal fade" id="addExpenseCatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-folder-plus me-1"></i> نئی خرچہ کیٹیگری شامل کریں</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="expenses.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_expense_category">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">کیٹیگری نام (اردو) *</label>
                        <input type="text" name="cat_name_ur" class="form-control" placeholder="مثلاً: دکان صفائی، ملازمین کھانا" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Name (English)</label>
                        <input type="text" name="cat_name_en" class="form-control" placeholder="e.g. Shop Cleaning, Staff Meal">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-sm btn-dark fw-bold">محفوظ کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

