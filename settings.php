<?php
// settings.php - Store Settings & Business Configuration
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();
require_role(['admin']);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $settings_to_update = [
        'store_name_en' => trim($_POST['store_name_en'] ?? ''),
        'store_name_ur' => trim($_POST['store_name_ur'] ?? ''),
        'slogan_en' => trim($_POST['slogan_en'] ?? ''),
        'slogan_ur' => trim($_POST['slogan_ur'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address_en' => trim($_POST['address_en'] ?? ''),
        'address_ur' => trim($_POST['address_ur'] ?? ''),
        'currency_symbol' => trim($_POST['currency_symbol'] ?? 'Rs.'),
        'receipt_printer_type' => $_POST['receipt_printer_type'] ?? '80mm',
        'receipt_header' => trim($_POST['receipt_header'] ?? ''),
        'receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
        'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 10),
        'allow_negative_stock' => isset($_POST['allow_negative_stock']) ? '1' : '0'
    ];

    foreach ($settings_to_update as $k => $v) {
        set_setting($k, $v);
    }

    log_audit("Update Settings", "settings", 0, "Business settings updated");
    $msg = "Settings updated successfully! ترتیبات کامیابی سے محفوظ ہو گئیں۔";
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-sliders text-success me-2"></i> کاروباری ترتیبات (Store Settings & Config)</h4>
        <div class="text-muted small">اسٹور کا نام، اردو/انگریزی لیبلز، رسید پرنٹر فارمیٹ اور الرٹس سیٹنگز</div>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-4">
        <form method="POST" action="settings.php">
            <?php echo csrf_field(); ?>

            <div class="row g-3">
                <div class="col-12"><h6 class="fw-bold text-success mb-0"><i class="bi bi-shop me-1"></i> دکان کی بنیادی معلومات</h6><hr class="my-2"></div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">اسٹور کا نام (اردو) *</label>
                    <input type="text" name="store_name_ur" class="form-control form-control-lg fw-bold" value="<?php echo htmlspecialchars(get_setting('store_name_ur')); ?>" required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">Store Name (English) *</label>
                    <input type="text" name="store_name_en" class="form-control form-control-lg" value="<?php echo htmlspecialchars(get_setting('store_name_en')); ?>" required>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">سلوگن / نعرہ (اردو)</label>
                    <input type="text" name="slogan_ur" class="form-control" value="<?php echo htmlspecialchars(get_setting('slogan_ur')); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">Slogan (English)</label>
                    <input type="text" name="slogan_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('slogan_en')); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">فون نمبرز (رابطہ)</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars(get_setting('phone')); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">کرنسی کی علامت (Currency Symbol)</label>
                    <input type="text" name="currency_symbol" class="form-control fw-bold" value="<?php echo htmlspecialchars(get_setting('currency_symbol', 'Rs.')); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">پتہ (اردو)</label>
                    <input type="text" name="address_ur" class="form-control" value="<?php echo htmlspecialchars(get_setting('address_ur')); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">Address (English)</label>
                    <input type="text" name="address_en" class="form-control" value="<?php echo htmlspecialchars(get_setting('address_en')); ?>">
                </div>

                <!-- Receipt Printing Settings -->
                <div class="col-12 mt-4"><h6 class="fw-bold text-primary mb-0"><i class="bi bi-printer me-1"></i> تھرمل پرنٹر اور رسید کی ترتیبات</h6><hr class="my-2"></div>

                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">تھرمل پرنٹر سائز (Receipt Size) *</label>
                    <select name="receipt_printer_type" class="form-select fw-semibold">
                        <option value="80mm" <?php echo get_setting('receipt_printer_type') == '80mm' ? 'selected' : ''; ?>>80 mm تھرمل رول (معیاری کاؤنٹر پرنٹر)</option>
                        <option value="58mm" <?php echo get_setting('receipt_printer_type') == '58mm' ? 'selected' : ''; ?>>58 mm تھرمل رول (چھوٹا پورٹیبل پرنٹر)</option>
                        <option value="a4" <?php echo get_setting('receipt_printer_type') == 'a4' ? 'selected' : ''; ?>>A4 فل سائز لیزر / انک جیٹ انوائس</option>
                    </select>
                </div>

                <div class="col-12 col-md-8">
                    <label class="form-label fw-bold small">رسید کے اوپر تحریر (Header Greeting)</label>
                    <input type="text" name="receipt_header" class="form-control" value="<?php echo htmlspecialchars(get_setting('receipt_header')); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold small">رسید کے نیچے واپسی پالیسی (Footer Return Policy)</label>
                    <input type="text" name="receipt_footer" class="form-control" value="<?php echo htmlspecialchars(get_setting('receipt_footer')); ?>">
                </div>

                <!-- Stock Alerts & Policies -->
                <div class="col-12 mt-4"><h6 class="fw-bold text-secondary mb-0"><i class="bi bi-shield-check me-1"></i> انوینٹری الرٹس اور سسٹم پالیسی</h6><hr class="my-2"></div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">ڈیفالٹ کم از کم اسٹاک الرٹ کی حد</label>
                    <input type="number" name="low_stock_threshold" class="form-control" value="<?php echo (int)get_setting('low_stock_threshold', 10); ?>">
                </div>

                <div class="col-12 col-md-6 d-flex align-items-center">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" name="allow_negative_stock" id="negStock" <?php echo get_setting('allow_negative_stock') == '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="negStock">
                            منفی اسٹاک میں فروخت کی اجازت دیں (Allow Negative Stock Sales)
                        </label>
                    </div>
                </div>

                <div class="col-12 mt-4 text-end">
                    <button type="submit" class="btn btn-success btn-lg fw-bold px-5 shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> تمام ترتیبات محفوظ کریں
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

