<?php
// categories.php - Dynamic Categories & Brands Manager
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';

// Handle Category Add/Edit
if (isset($_POST['action']) && $_POST['action'] === 'save_category') {
    verify_csrf();
    $cat_id = (int)($_POST['category_id'] ?? 0);
    $name_en = trim($_POST['name_en'] ?? '');
    $name_ur = trim($_POST['name_ur'] ?? '');
    $parent_id = (int)($_POST['parent_id'] ?? 0);

    if (empty($name_en) && empty($name_ur)) {
        $err = "Category name is required!";
    } else {
        $en_esc = db_escape($name_en);
        $ur_esc = db_escape($name_ur);

        if ($cat_id > 0) {
            db_query("UPDATE categories SET name_en = '$en_esc', name_ur = '$ur_esc', parent_id = $parent_id WHERE id = $cat_id");
            $msg = "Category updated! کیٹیگری تبدیل ہو گئی۔";
        } else {
            db_query("INSERT INTO categories (name_en, name_ur, parent_id) VALUES ('$en_esc', '$ur_esc', $parent_id)");
            $msg = "Category added! نئی کیٹیگری شامل ہو گئی۔";
        }
    }
}

// Handle Brand Add/Edit
if (isset($_POST['action']) && $_POST['action'] === 'save_brand') {
    verify_csrf();
    $brand_id = (int)($_POST['brand_id'] ?? 0);
    $name_en = trim($_POST['brand_en'] ?? '');
    $name_ur = trim($_POST['brand_ur'] ?? '');

    if (empty($name_en) && empty($name_ur)) {
        $err = "Brand name is required!";
    } else {
        $en_esc = db_escape($name_en);
        $ur_esc = db_escape($name_ur);

        if ($brand_id > 0) {
            db_query("UPDATE brands SET name_en = '$en_esc', name_ur = '$ur_esc' WHERE id = $brand_id");
            $msg = "Brand updated! برانڈ تبدیل ہو گیا۔";
        } else {
            db_query("INSERT INTO brands (name_en, name_ur) VALUES ('$en_esc', '$ur_esc')");
            $msg = "Brand added! نیا برانڈ شامل ہو گیا۔";
        }
    }
}

// Delete category
if (isset($_GET['del_cat'])) {
    $del_id = (int)$_GET['del_cat'];
    db_query("DELETE FROM categories WHERE id = $del_id");
    $msg = "Category deleted!";
}

// Delete brand
if (isset($_GET['del_brand'])) {
    $del_id = (int)$_GET['del_brand'];
    db_query("DELETE FROM brands WHERE id = $del_id");
    $msg = "Brand deleted!";
}

$categories = db_fetch_all("SELECT c.*, p.name_ur AS parent_name_ur, p.name_en AS parent_name_en,
                           (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count 
                           FROM categories c 
                           LEFT JOIN categories p ON c.parent_id = p.id 
                           ORDER BY c.id DESC");

$parent_categories = db_fetch_all("SELECT * FROM categories WHERE parent_id = 0 ORDER BY name_en ASC");

$brands = db_fetch_all("SELECT b.*, (SELECT COUNT(*) FROM products WHERE brand_id = b.id) AS product_count 
                       FROM brands b ORDER BY b.id DESC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-tags text-success me-2"></i> کیٹیگریز اور برانڈز (Categories & Brands)</h4>
        <div class="text-muted small">اشیاء کی درجہ بندی اور معروف برانڈز مینیج کریں</div>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row g-4">
    <!-- Categories Column -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-folder2-open text-primary me-2"></i> پروڈکٹ کیٹیگریز (Categories)</h6>
                <button class="btn btn-sm btn-success fw-bold" onclick="openAddCatModal()">
                    <i class="bi bi-plus-lg"></i> نئی کیٹیگری
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>نام (Urdu)</th>
                                <th>Name (English)</th>
                                <th>پیرنٹ کیٹیگری</th>
                                <th class="text-center">اشیاء تعداد</th>
                                <th class="text-center" style="width: 80px;">کارروائی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($cat['name_ur']); ?></td>
                                    <td><?php echo htmlspecialchars($cat['name_en']); ?></td>
                                    <td class="small text-muted">
                                        <?php echo $cat['parent_id'] > 0 ? htmlspecialchars($cat['parent_name_ur'] ?: $cat['parent_name_en']) : '<span class="badge bg-light text-dark">مین کیٹیگری</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $cat['product_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="openEditCatModal(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="categories.php?del_cat=<?php echo $cat['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('حذف کریں؟')">
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
    </div>

    <!-- Brands Column -->
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-patch-check text-warning me-2"></i> برانڈز / کمپنیاں (Brands)</h6>
                <button class="btn btn-sm btn-primary fw-bold" onclick="openAddBrandModal()">
                    <i class="bi bi-plus-lg"></i> نیا برانڈ
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>برانڈ نام</th>
                                <th>English Name</th>
                                <th class="text-center">اشیاء تعداد</th>
                                <th class="text-center" style="width: 80px;">کارروائی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brands as $b): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($b['name_ur'] ?: $b['name_en']); ?></td>
                                    <td><?php echo htmlspecialchars($b['name_en']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary"><?php echo $b['product_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="openEditBrandModal(<?php echo htmlspecialchars(json_encode($b)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="categories.php?del_brand=<?php echo $b['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('حذف کریں؟')">
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
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="catModalTitle">کیٹیگری شامل کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_category">
                <input type="hidden" name="category_id" id="modalCatId" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">نام کیٹیگری (اردو) *</label>
                        <input type="text" name="name_ur" id="catNameUr" class="form-control" placeholder="مثلاً: دالیں اور چاول" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Name (English)</label>
                        <input type="text" name="name_en" id="catNameEn" class="form-control" placeholder="e.g. Pulses & Rice">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">پیرنٹ کیٹیگری (اگر سب کیٹیگری ہے)</label>
                        <select name="parent_id" id="catParentId" class="form-select">
                            <option value="0">کوئی نہیں (مین کیٹیگری)</option>
                            <?php foreach ($parent_categories as $pc): ?>
                                <option value="<?php echo $pc['id']; ?>"><?php echo htmlspecialchars($pc['name_ur'] ?: $pc['name_en']); ?></option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Brand Modal -->
<div class="modal fade" id="brandModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="brandModalTitle">برانڈ شامل کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="categories.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_brand">
                <input type="hidden" name="brand_id" id="modalBrandId" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">برانڈ نام (اردو) *</label>
                        <input type="text" name="brand_ur" id="brandNameUr" class="form-control" placeholder="مثلاً: ڈالڈا یا نیشنل" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Brand Name (English)</label>
                        <input type="text" name="brand_en" id="brandNameEn" class="form-control" placeholder="e.g. Dalda, National">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">محفوظ کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddCatModal() {
    document.getElementById('catModalTitle').innerText = 'نئی کیٹیگری شامل کریں';
    document.getElementById('modalCatId').value = '0';
    document.getElementById('catNameUr').value = '';
    document.getElementById('catNameEn').value = '';
    document.getElementById('catParentId').value = '0';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}

function openEditCatModal(cat) {
    document.getElementById('catModalTitle').innerText = 'کیٹیگری ترمیم کریں';
    document.getElementById('modalCatId').value = cat.id;
    document.getElementById('catNameUr').value = cat.name_ur;
    document.getElementById('catNameEn').value = cat.name_en;
    document.getElementById('catParentId').value = cat.parent_id || '0';
    new bootstrap.Modal(document.getElementById('catModal')).show();
}

function openAddBrandModal() {
    document.getElementById('brandModalTitle').innerText = 'نیا برانڈ شامل کریں';
    document.getElementById('modalBrandId').value = '0';
    document.getElementById('brandNameUr').value = '';
    document.getElementById('brandNameEn').value = '';
    new bootstrap.Modal(document.getElementById('brandModal')).show();
}

function openEditBrandModal(b) {
    document.getElementById('brandModalTitle').innerText = 'برانڈ ترمیم کریں';
    document.getElementById('modalBrandId').value = b.id;
    document.getElementById('brandNameUr').value = b.name_ur;
    document.getElementById('brandNameEn').value = b.name_en;
    new bootstrap.Modal(document.getElementById('brandModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

