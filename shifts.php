<?php
// shifts.php - Cash Register & Shift Management
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';
$user_id = (int)$_SESSION['user_id'];
$active_shift = get_active_shift($user_id);

// 1. Open New Shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'open_shift') {
    verify_csrf();

    if ($active_shift) {
        $err = "You already have an active shift open! آپ کی ایک شفٹ پہلے سے جاری ہے۔";
    } else {
        $opening_cash = (float)($_POST['opening_cash'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $notes_esc = db_escape($notes);

        $sql = "INSERT INTO shifts (user_id, start_time, opening_cash, expected_cash, status, notes) 
                VALUES ($user_id, NOW(), $opening_cash, $opening_cash, 'open', '$notes_esc')";
        if (db_query($sql)) {
            log_audit("Open Shift", "shifts", $conn->insert_id, "Opening Cash: $opening_cash");
            $msg = "Shift started successfully! شفٹ شروع ہو گئی۔";
            $active_shift = get_active_shift($user_id);
        } else {
            $err = "Database error: " . $conn->error;
        }
    }
}

// 2. Close Shift
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_shift') {
    verify_csrf();

    $shift_id = (int)($_POST['shift_id'] ?? 0);
    $counted_cash = (float)($_POST['counted_cash'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    $shift = db_fetch_one("SELECT * FROM shifts WHERE id = $shift_id AND status = 'open'");
    if (!$shift) {
        $err = "Shift not found or already closed!";
    } else {
        $expected = (float)$shift['opening_cash'] + (float)$shift['cash_sales'] + (float)$shift['cash_recoveries'] - (float)$shift['cash_expenses'] - (float)$shift['cash_supplier_payments'];
        $difference = $counted_cash - $expected;
        $notes_esc = db_escape($notes);

        $sql = "UPDATE shifts SET 
                end_time = NOW(),
                expected_cash = $expected,
                counted_cash = $counted_cash,
                difference = $difference,
                status = 'closed',
                notes = CONCAT(COALESCE(notes, ''), ' | Closing: ', '$notes_esc')
                WHERE id = $shift_id";

        if (db_query($sql)) {
            log_audit("Close Shift", "shifts", $shift_id, "Expected: $expected, Counted: $counted_cash, Diff: $difference");
            $msg = "Shift closed successfully! گنتی فرق: " . format_currency($difference);
            $active_shift = null;
        } else {
            $err = "Database error: " . $conn->error;
        }
    }
}

// Shifts history
$shifts = db_fetch_all("SELECT s.*, u.full_name AS cashier_name 
                        FROM shifts s 
                        JOIN users u ON s.user_id = u.id 
                        ORDER BY s.id DESC LIMIT 30");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-cash-stack text-success me-2"></i> کیش رجسٹر و شفٹ مینجمنٹ (Cash Register & Shifts)</h4>
        <div class="text-muted small">کیش دراز کا حساب، اوپننگ کیش، سیلز کیش، اخراجات اور شفٹ کا کلوزنگ بیلنس</div>
    </div>
    <div>
        <?php if ($active_shift): ?>
            <button class="btn btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#closeShiftModal">
                <i class="bi bi-lock-fill me-1"></i> شفٹ بند کریں (Close Shift)
            </button>
        <?php else: ?>
            <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#openShiftModal">
                <i class="bi bi-unlock-fill me-1"></i> نئی شفٹ شروع کریں (Open Shift)
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Active Shift Live Card -->
<?php if ($active_shift): 
    $expected_live = (float)$active_shift['opening_cash'] + (float)$active_shift['cash_sales'] + (float)$active_shift['cash_recoveries'] - (float)$active_shift['cash_expenses'] - (float)$active_shift['cash_supplier_payments'];
?>
    <div class="card border-0 shadow-sm rounded-3 mb-4 border-top border-success border-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <span class="badge bg-success me-2">شفٹ جاری ہے (Active)</span>
                <span class="fw-bold">شفٹ نمبر: #<?php echo $active_shift['id']; ?></span>
                <span class="text-muted small ms-2">آغاز: <?php echo format_datetime($active_shift['start_time']); ?></span>
            </div>
            <button class="btn btn-sm btn-outline-danger fw-bold" data-bs-toggle="modal" data-bs-target="#closeShiftModal">
                شفٹ کلوز کریں &rarr;
            </button>
        </div>
        <div class="card-body p-4">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <div class="small text-muted">اوپننگ کیش</div>
                        <div class="fw-bold fs-6"><?php echo format_currency($active_shift['opening_cash']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <div class="small text-success">(+) سیلز کیش</div>
                        <div class="fw-bold fs-6 text-success">+<?php echo format_currency($active_shift['cash_sales']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <div class="small text-info">(+) کھاتہ وصولی</div>
                        <div class="fw-bold fs-6 text-info">+<?php echo format_currency($active_shift['cash_recoveries']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <div class="small text-danger">(-) دکان اخراجات</div>
                        <div class="fw-bold fs-6 text-danger">-<?php echo format_currency($active_shift['cash_expenses']); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="p-2 border rounded bg-light">
                        <div class="small text-danger">(-) سپلائر ادائیگیاں</div>
                        <div class="fw-bold fs-6 text-danger">-<?php echo format_currency($active_shift['cash_supplier_payments']); ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <div class="p-2 border rounded bg-success-subtle text-success">
                        <div class="small fw-bold text-success">متوقع دراز کیش</div>
                        <div class="fw-bold fs-5 text-success"><?php echo format_currency($expected_live); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning py-3 rounded-3 shadow-sm d-flex justify-content-between align-items-center mb-4">
        <div>
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
            <strong>کوئی شفٹ ایکٹو نہیں ہے!</strong> کاؤنٹر شروع کرنے سے پہلے شفٹ اوپن کریں۔
        </div>
        <button class="btn btn-warning fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#openShiftModal">
            ابھی شفٹ شروع کریں
        </button>
    </div>
<?php endif; ?>

<!-- Shifts History Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i> سابقہ شفٹس کی تاریخ (Shifts History)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>کیشیئر</th>
                        <th>آغاز وقت</th>
                        <th>اختتام وقت</th>
                        <th class="text-end">اوپننگ کیش</th>
                        <th class="text-end text-success">سیلز کیش</th>
                        <th class="text-end text-info">وصولیاں</th>
                        <th class="text-end text-danger">اخراجات</th>
                        <th class="text-end fw-bold">متوقع کیش</th>
                        <th class="text-end fw-bold text-primary">گنتی کیش</th>
                        <th class="text-end fw-bold">کمی / بیشی</th>
                        <th class="text-center">حالت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shifts)): ?>
                        <tr><td colspan="12" class="text-center py-5 text-muted">کوئی شفٹ ریکارڈ نہیں ملا</td></tr>
                    <?php else: ?>
                        <?php foreach ($shifts as $s): ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $s['id']; ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($s['cashier_name']); ?></td>
                                <td class="small text-muted"><?php echo format_datetime($s['start_time']); ?></td>
                                <td class="small text-muted"><?php echo format_datetime($s['end_time']); ?></td>
                                <td class="text-end"><?php echo format_currency($s['opening_cash']); ?></td>
                                <td class="text-end text-success"><?php echo format_currency($s['cash_sales']); ?></td>
                                <td class="text-end text-info"><?php echo format_currency($s['cash_recoveries']); ?></td>
                                <td class="text-end text-danger"><?php echo format_currency($s['cash_expenses']); ?></td>
                                <td class="text-end fw-bold"><?php echo format_currency($s['expected_cash']); ?></td>
                                <td class="text-end fw-bold text-primary"><?php echo $s['status'] == 'closed' ? format_currency($s['counted_cash']) : '-'; ?></td>
                                <td class="text-end fw-bold">
                                    <?php if ($s['status'] == 'closed'): ?>
                                        <span class="<?php echo $s['difference'] < 0 ? 'text-danger' : ($s['difference'] > 0 ? 'text-success' : 'text-muted'); ?>">
                                            <?php echo ($s['difference'] > 0 ? '+' : '') . format_currency($s['difference']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo $s['status'] == 'open' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $s['status'] == 'open' ? 'جاری' : 'بند'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Open Shift -->
<div class="modal fade" id="openShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-unlock-fill me-2"></i> نئی شفٹ شروع کریں (Open Shift)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="shifts.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="open_shift">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">دراز میں موجود اوپننگ کیش (Opening Cash) *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="opening_cash" class="form-control fw-bold text-success fs-4" placeholder="0.00" required autofocus>
                        </div>
                        <div class="form-text small">وہ کیش رقم جو شفٹ شروع کرتے وقت دراز میں بطور ریزگاری / بقایا موجود ہے۔</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">نوٹ / ریمارکس</label>
                        <input type="text" name="notes" class="form-control" placeholder="صبح کی شفٹ، ریزگاری وغیرہ...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">شفٹ اوپن کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Close Shift -->
<?php if ($active_shift): ?>
<div class="modal fade" id="closeShiftModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-lock-fill me-2"></i> شفٹ بند کریں (Close Shift)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="shifts.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="close_shift">
                <input type="hidden" name="shift_id" value="<?php echo $active_shift['id']; ?>">
                <div class="modal-body p-4">
                    <div class="alert alert-light border p-3 mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">متوقع کیش (Expected Cash):</span>
                            <span class="fw-bold fs-5 text-dark" id="closeExpectedCash">Rs. <?php echo number_format($expected_live, 2); ?></span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">گنتی کیا گیا اصل دراز کیش (Physical Counted Cash) *</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light fw-bold">Rs.</span>
                            <input type="number" step="0.01" name="counted_cash" id="countedCashInput" class="form-control fw-bold text-primary fs-4" placeholder="0.00" required autofocus oninput="calcShiftDiff(<?php echo $expected_live; ?>)">
                        </div>
                    </div>

                    <div class="p-3 border rounded bg-white mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">کیش میں فرق (Difference):</span>
                            <span class="fw-bold fs-5" id="diffDisplay">Rs. 0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">کلوزنگ نوٹ / وضاحت</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="اگر کوئی کمی یا بیشی ہے تو اس کی وجہ لکھیں..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">شفٹ بند کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function calcShiftDiff(expected) {
    const counted = parseFloat(document.getElementById('countedCashInput').value) || 0;
    const diff = counted - expected;
    const disp = document.getElementById('diffDisplay');
    if (diff < 0) {
        disp.className = 'fw-bold fs-5 text-danger';
        disp.innerText = 'کمی (Shortage): Rs. ' + Math.abs(diff).toFixed(2);
    } else if (diff > 0) {
        disp.className = 'fw-bold fs-5 text-success';
        disp.innerText = 'بیشی (Excess): +Rs. ' + diff.toFixed(2);
    } else {
        disp.className = 'fw-bold fs-5 text-success';
        disp.innerText = 'بالکل برابر (Balanced)';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

