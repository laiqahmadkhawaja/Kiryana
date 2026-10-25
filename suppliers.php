<?php
// suppliers.php - Suppliers & Wholesalers Management
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';

// Handle Add / Edit Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_supplier') {
    verify_csrf();

    $supp_id = (int)($_POST['supplier_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);
    $credit_limit = (float)($_POST['credit_limit'] ?? 0);

    if (empty($name)) {
        $err = "Supplier name is required! سپلائر کا نام لازمی ہے۔";
    } else {
        $n_esc = db_escape($name);
        $c_esc = db_escape($company_name);
        $p_esc = db_escape($phone);
        $a_esc = db_escape($address);

        if ($supp_id > 0) {
            $sql = "UPDATE suppliers SET name = '$n_esc', company_name = '$c_esc', phone = '$p_esc', 
                    address = '$a_esc', credit_limit = $credit_limit WHERE id = $supp_id";
            db_query($sql);
            $msg = "Supplier updated! سپلائر کی معلومات تبدیل ہو گئیں۔";
        } else {
            $sql = "INSERT INTO suppliers (name, company_name, phone, address, opening_balance, current_balance, credit_limit, status) 
                    VALUES ('$n_esc', '$c_esc', '$p_esc', '$a_esc', $opening_balance, $opening_balance, $credit_limit, 'active')";
            if (db_query($sql)) {
                $new_id = $conn->insert_id;
                if ($opening_balance > 0) {
                    db_query("INSERT INTO supplier_ledger (supplier_id, transaction_type, reference_no, credit, balance_after, notes) 
                              VALUES ($new_id, 'opening', 'OPENING-BAL', $opening_balance, $opening_balance, 'ابتدائی بقایا کھاتہ')");
                }
                $msg = "New supplier registered! نیا سپلائر شامل ہو گیا۔";
            }
        }
    }
}

// Search
$q = trim($_GET['q'] ?? '');
$where = "WHERE status = 'active'";
if (!empty($q)) {
    $q_esc = db_escape($q);
    $where .= " AND (name LIKE '%$q_esc%' OR company_name LIKE '%$q_esc%' OR phone LIKE '%$q_esc%')";
}

$suppliers = db_fetch_all("SELECT s.*, 
                          (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) AS total_purchases_count 
                          FROM suppliers s 
                          $where 
                          ORDER BY s.current_balance DESC");

// Total payables
$tot_payables = db_fetch_one("SELECT COALESCE(SUM(current_balance), 0) AS total FROM suppliers WHERE status = 'active'");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-truck text-success me-2"></i> سپلائرز و ڈسٹری بیوٹرز (Suppliers & Wholesalers)</h4>
        <div class="text-muted small">ہول سیل ڈیلرز، خریداری اور سپلائر کا ادھار کھاتہ</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success fw-bold shadow-sm" onclick="openAddSupplierModal()">
            <i class="bi bi-plus-circle me-1"></i> نیا سپلائر شامل کریں (Add Supplier)
        </button>
        <a href="purchase_create.php" class="btn btn-primary fw-bold shadow-sm">
            <i class="bi bi-bag-plus me-1"></i> نئی خریداری (Purchase)
        </a>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Total Payables Banner -->
<div class="card border-0 bg-danger-subtle text-danger shadow-sm mb-4">
    <div class="card-body p-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="fw-semibold">سپلائرز کے کل واجب الادا بقایا جات (Total Payables):</span>
            <h3 class="fw-bold mb-0 text-danger"><?php echo format_currency($tot_payables['total']); ?></h3>
        </div>
        <i class="bi bi-wallet-fill fs-1 text-danger opacity-50"></i>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>سپلائر نام (Dealer)</th>
                        <th>کمپنی / ایجنسی</th>
                        <th>فون نمبر</th>
                        <th>پتہ / مارکیٹ</th>
                        <th class="text-center">کل خریداری</th>
                        <th class="text-end fw-bold">واجب الادا رقم (Payable)</th>
                        <th class="text-center" style="width: 140px;">کارروائی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">کوئی سپلائر موجود نہیں</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $i => $s): ?>
                            <tr>
                                <td class="text-muted small"><?php echo $i + 1; ?></td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($s['name']); ?></div>
                                </td>
                                <td class="small fw-semibold"><?php echo htmlspecialchars($s['company_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($s['phone'] ?: '-'); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($s['address'] ?: '-'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?php echo $s['total_purchases_count']; ?> بلز</span>
                                </td>
                                <td class="text-end fw-bold <?php echo $s['current_balance'] > 0 ? 'text-danger fs-6' : 'text-success'; ?>">
                                    <?php echo format_currency($s['current_balance']); ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="supplier_ledger.php?id=<?php echo $s['id']; ?>" class="btn btn-outline-success fw-bold" title="کھاتہ و ادائیگی">
                                            <i class="bi bi-journal-text"></i> کھاتہ
                                        </a>
                                        <button class="btn btn-outline-primary" onclick="openEditSupplierModal(<?php echo htmlspecialchars(json_encode($s)); ?>)" title="ترمیم">
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

<!-- Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="suppModalTitle">سپلائر شامل کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="suppliers.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_supplier">
                <input type="hidden" name="supplier_id" id="modalSuppId" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">ڈیلر / سپلائر کا نام *</label>
                        <input type="text" name="name" id="suppName" class="form-control" placeholder="مثلاً: حاجی عبد الرحمان" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">کمپنی / ایجنسی کا نام</label>
                        <input type="text" name="company_name" id="suppCompany" class="form-control" placeholder="مثلاً: رحمان شوگر اینڈ فلور ملز">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">فون نمبر</label>
                            <input type="text" name="phone" id="suppPhone" class="form-control" placeholder="03001234567">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">ابتدائی بقایا (اگر پہلے سے واجب الادا ہے)</label>
                            <input type="number" step="0.01" name="opening_balance" id="suppOpening" class="form-control" value="0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">پتہ / مارکیٹ</label>
                        <textarea name="address" id="suppAddress" class="form-control" rows="2" placeholder="اکبری منڈی، گودام نمبر..."></textarea>
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

<script>
function openAddSupplierModal() {
    document.getElementById('suppModalTitle').innerText = 'نیا سپلائر شامل کریں';
    document.getElementById('modalSuppId').value = '0';
    document.getElementById('suppName').value = '';
    document.getElementById('suppCompany').value = '';
    document.getElementById('suppPhone').value = '';
    document.getElementById('suppOpening').value = '0.00';
    document.getElementById('suppOpening').disabled = false;
    document.getElementById('suppAddress').value = '';
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
}

function openEditSupplierModal(s) {
    document.getElementById('suppModalTitle').innerText = 'سپلائر کی تفصیلات تبدیل کریں';
    document.getElementById('modalSuppId').value = s.id;
    document.getElementById('suppName').value = s.name;
    document.getElementById('suppCompany').value = s.company_name;
    document.getElementById('suppPhone').value = s.phone;
    document.getElementById('suppOpening').value = s.opening_balance;
    document.getElementById('suppOpening').disabled = true; // disable editing opening balance after creation
    document.getElementById('suppAddress').value = s.address;
    new bootstrap.Modal(document.getElementById('supplierModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

