<?php
// units.php - Dynamic Units of Measurement with Conversion Multipliers
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';

// Handle Add / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $unit_id = (int)($_POST['unit_id'] ?? 0);
    $name_en = trim($_POST['name_en'] ?? '');
    $name_ur = trim($_POST['name_ur'] ?? '');
    $symbol = trim($_POST['symbol'] ?? '');
    $base_unit_id = (int)($_POST['base_unit_id'] ?? 0);
    $conversion_factor = (float)($_POST['conversion_factor'] ?? 1);
    $allow_decimal = isset($_POST['allow_decimal']) ? 1 : 0;
    $status = $_POST['status'] ?? 'active';

    if (empty($name_en) && empty($name_ur)) {
        $err = "Unit name is required! اکائی کا نام لازمی ہے۔";
    } elseif (empty($symbol)) {
        $err = "Unit symbol is required! علامت لازمی ہے۔";
    } else {
        $en_esc = db_escape($name_en);
        $ur_esc = db_escape($name_ur);
        $sym_esc = db_escape($symbol);
        $b_id_val = $base_unit_id > 0 ? $base_unit_id : "NULL";

        if ($unit_id > 0) {
            $sql = "UPDATE units SET name_en = '$en_esc', name_ur = '$ur_esc', symbol = '$sym_esc', 
                    base_unit_id = $b_id_val, conversion_factor = $conversion_factor, allow_decimal = $allow_decimal, 
                    status = '$status' WHERE id = $unit_id";
            db_query($sql);
            $msg = "Unit updated successfully! اکائی تبدیل کر دی گئی۔";
        } else {
            $sql = "INSERT INTO units (name_en, name_ur, symbol, base_unit_id, conversion_factor, allow_decimal, status) 
                    VALUES ('$en_esc', '$ur_esc', '$sym_esc', $b_id_val, $conversion_factor, $allow_decimal, '$status')";
            db_query($sql);
            $msg = "New unit added successfully! نئی پیمائش کی اکائی شامل ہو گئی۔";
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $chk = db_fetch_one("SELECT id FROM products WHERE unit_id = $del_id LIMIT 1");
    if ($chk) {
        $err = "Cannot delete this unit because products are using it! یہ اکائی پراڈکٹس میں استعمال ہو رہی ہے۔";
    } else {
        db_query("DELETE FROM units WHERE id = $del_id");
        $msg = "Unit deleted successfully! اکائی حذف کر دی گئی۔";
    }
}

$units = db_fetch_all("SELECT u.*, b.name_ur AS base_name_ur, b.name_en AS base_name_en, b.symbol AS base_symbol 
                       FROM units u 
                       LEFT JOIN units b ON u.base_unit_id = b.id 
                       ORDER BY u.id ASC");

$base_units = db_fetch_all("SELECT * FROM units WHERE base_unit_id IS NULL AND status = 'active' ORDER BY name_en ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-rulers text-success me-2"></i> پیمائش کی اکائیاں (Units of Measurement)</h4>
        <div class="text-muted small">کلو گرام، گرام، لیٹر، کاٹن، درجن، بوری اور ان کا باہمی تناسب</div>
    </div>
    <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#unitModal" onclick="openAddUnitModal()">
        <i class="bi bi-plus-circle me-1"></i> نئی اکائی شامل کریں (Add Unit)
    </button>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show py-2"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Unit Conversion Guide Note -->
<div class="alert alert-info py-2 small d-flex align-items-center">
    <i class="bi bi-info-circle-fill fs-5 me-2"></i>
    <div>
        <strong>مثال:</strong> چینی کا بنیادی اسٹاک <strong>کلو گرام (KG)</strong> میں ہے۔ جب گرام (G) میں 250 گرام بیچا جائے گا تو سسٹم خود بخود اس کا تناسب <code>0.001</code> لگا کر انوینٹری سے <code>0.250 KG</code> کم کر دے گا۔ اسی طرح کاٹن (Ctn) سے <code>24 Pieces</code> کم ہوں گے۔
    </div>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>اکائی کا نام (Urdu)</th>
                        <th>Name (English)</th>
                        <th>علامت (Symbol)</th>
                        <th>بنیادی اکائی (Base Unit)</th>
                        <th>تناسب (Factor)</th>
                        <th>اعشاریہ (Decimals)</th>
                        <th>حالت</th>
                        <th class="text-center" style="width: 100px;">کارروائی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units as $i => $u): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $i + 1; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($u['name_ur']); ?></td>
                            <td><?php echo htmlspecialchars($u['name_en']); ?></td>
                            <td><span class="badge bg-light text-dark border fw-bold"><?php echo htmlspecialchars($u['symbol']); ?></span></td>
                            <td>
                                <?php if ($u['base_unit_id']): ?>
                                    <span class="badge bg-primary-subtle text-primary">
                                        <?php echo htmlspecialchars($u['base_name_ur'] ?: $u['base_name_en']); ?> (<?php echo $u['base_symbol']; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">بنیادی اکائی (Master)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code>1 <?php echo $u['symbol']; ?> = <?php echo (float)$u['conversion_factor']; ?> <?php echo $u['base_symbol'] ?: $u['symbol']; ?></code>
                            </td>
                            <td>
                                <?php echo $u['allow_decimal'] ? '<span class="text-success"><i class="bi bi-check-circle"></i> جی ہاں</span>' : '<span class="text-muted">نہیں (مکمل عدد)</span>'; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $u['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $u['status'] == 'active' ? 'فعال' : 'غیر فعال'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="openEditUnitModal(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="ترمیم">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="units.php?delete=<?php echo $u['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('حذف کریں؟')" title="حذف">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Unit Modal -->
<div class="modal fade" id="unitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="unitModalTitle"><i class="bi bi-rulers me-2"></i> اکائی شامل کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="units.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="unit_id" id="modalUnitId" value="0">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">نام اکائی (اردو) *</label>
                            <input type="text" name="name_ur" id="uNameUr" class="form-control" placeholder="مثلاً: کلو گرام، گرام" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">Name (English) *</label>
                            <input type="text" name="name_en" id="uNameEn" class="form-control" placeholder="e.g. Kilogram, Gram" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-bold small">علامت / مخفف (Symbol) *</label>
                            <input type="text" name="symbol" id="uSymbol" class="form-control" placeholder="KG, G, L, Pkt" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-bold small">بنیادی اکائی (Base Unit)</label>
                            <select name="base_unit_id" id="uBaseId" class="form-select">
                                <option value="0">یہ خود بنیادی اکائی ہے (Master)</option>
                                <?php foreach ($base_units as $bu): ?>
                                    <option value="<?php echo $bu['id']; ?>"><?php echo htmlspecialchars($bu['name_ur'] . ' (' . $bu['symbol'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">تناسب ضربی (Conversion Factor)</label>
                            <input type="number" step="0.0001" name="conversion_factor" id="uFactor" class="form-control" value="1.0000" required>
                            <div class="form-text small">
                                گرام کے لیے: <code>0.001</code> (کیونکہ 1 گرام = 0.001 کلو)<br>
                                کاٹن (24 عدد) کے لیے: <code>24.0000</code><br>
                                درجن کے لیے: <code>12.0000</code>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="allow_decimal" id="uDecimal" checked>
                                <label class="form-check-label fw-semibold" for="uDecimal">اعشاریہ میں فروخت کی اجازت (0.250)</label>
                            </div>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-bold small">حالت</label>
                            <select name="status" id="uStatus" class="form-select">
                                <option value="active">فعال (Active)</option>
                                <option value="inactive">غیر فعال (Inactive)</option>
                            </select>
                        </div>
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
function openAddUnitModal() {
    document.getElementById('unitModalTitle').innerHTML = '<i class="bi bi-rulers me-2"></i> نئی اکائی شامل کریں';
    document.getElementById('modalUnitId').value = '0';
    document.getElementById('uNameUr').value = '';
    document.getElementById('uNameEn').value = '';
    document.getElementById('uSymbol').value = '';
    document.getElementById('uBaseId').value = '0';
    document.getElementById('uFactor').value = '1.0000';
    document.getElementById('uDecimal').checked = true;
    document.getElementById('uStatus').value = 'active';
}

function openEditUnitModal(unit) {
    document.getElementById('unitModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i> اکائی ترمیم کریں';
    document.getElementById('modalUnitId').value = unit.id;
    document.getElementById('uNameUr').value = unit.name_ur;
    document.getElementById('uNameEn').value = unit.name_en;
    document.getElementById('uSymbol').value = unit.symbol;
    document.getElementById('uBaseId').value = unit.base_unit_id || '0';
    document.getElementById('uFactor').value = parseFloat(unit.conversion_factor);
    document.getElementById('uDecimal').checked = parseInt(unit.allow_decimal) === 1;
    document.getElementById('uStatus').value = unit.status;

    const modal = new bootstrap.Modal(document.getElementById('unitModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

