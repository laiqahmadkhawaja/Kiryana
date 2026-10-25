<?php
// pos.php - Point of Sale Fast Billing Interface
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

// Fetch dynamic categories for tabs
$categories = db_fetch_all("SELECT * FROM categories WHERE status = 'active' ORDER BY name_en ASC");

// Fetch dynamic payment accounts for checkout modal
$accounts = db_fetch_all("SELECT * FROM accounts WHERE status = 'active' ORDER BY account_type ASC");

// Fetch held bills count
$held_count = isset($_SESSION['held_bills']) ? count($_SESSION['held_bills']) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="pos-container">
    <!-- Left Column: Products & Search -->
    <div class="pos-products-col">
        <!-- Search and Filter Bar -->
        <div class="row g-2 mb-3">
            <div class="col-12 col-md-7">
                <div class="input-group">
                    <span class="input-group-text bg-success text-white"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="posProductSearch" class="form-control form-control-lg shadow-none" 
                           placeholder="بارکوڈ اسکین کریں یا نام / کوڈ سے تلاش کریں... (F2)" autofocus>
                    <button class="btn btn-outline-success fw-bold px-3" type="button" onclick="openCameraScanner()" title="کیمرہ سے بارکوڈ اسکین کریں (Scan Barcode with Camera)">
                        <i class="bi bi-camera-fill me-1"></i> <span class="d-none d-sm-inline">کیمرہ</span>
                    </button>
                    <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('posProductSearch').value=''; searchProducts('');">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Pricing Tier (Retail / Wholesale) -->
            <div class="col-12 col-md-5 d-flex justify-content-between align-items-center">
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="pricing_tier" id="tierRetail" value="retail" checked autocomplete="off">
                    <label class="btn btn-outline-success fw-bold" for="tierRetail">
                        <i class="bi bi-tag-fill me-1"></i> پرچون (Retail)
                    </label>

                    <input type="radio" class="btn-check" name="pricing_tier" id="tierWholesale" value="wholesale" autocomplete="off">
                    <label class="btn btn-outline-primary fw-bold" for="tierWholesale">
                        <i class="bi bi-box2-heart-fill me-1"></i> تھوک (Wholesale)
                    </label>
                </div>
            </div>
        </div>

        <!-- Dynamic Category Filter Pills -->
        <div class="d-flex gap-2 overflow-auto pb-2 mb-2" id="posCategoryPills" style="white-space: nowrap;">
            <button class="btn btn-sm btn-dark rounded-pill px-3 active" onclick="searchProducts('', 0)">
                تمام اشیاء (All)
            </button>
            <?php foreach ($categories as $cat): ?>
                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="searchProducts('', <?php echo $cat['id']; ?>)">
                    <?php echo htmlspecialchars($lang === 'ur' ? ($cat['name_ur'] ?: $cat['name_en']) : $cat['name_en']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Products Grid -->
        <div class="product-grid" id="posProductGrid">
            <!-- Rendered dynamically by pos.js -->
        </div>
    </div>

    <!-- Right Column: Current Bill & Fast Checkout -->
    <div class="pos-cart-col">
        <!-- Top Bill Header & Customer Select -->
        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold fs-6 text-dark">
                    <i class="bi bi-receipt text-success me-1"></i> موجودہ بل (Current Bill)
                </span>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary position-relative px-2 py-0" onclick="showHeldBillsModal()" title="روکے گئے بل (Held Bills)">
                        <i class="bi bi-pause-circle"></i> ہولڈ بلز
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="heldBillsBadge" style="<?php echo $held_count > 0 ? '' : 'display:none;'; ?>">
                            <?php echo $held_count; ?>
                        </span>
                    </button>
                    <button class="btn btn-sm btn-outline-danger px-2 py-0" onclick="resetBill()" title="نیا بل (F9)">
                        <i class="bi bi-arrow-repeat"></i> ری سیٹ
                    </button>
                </div>
            </div>

            <!-- Customer Selection -->
            <div class="input-group input-group-sm mb-1">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <select id="posCustomerSelect" class="form-select fw-medium">
                    <option value="0">عام خریدار (Walk-in Cash Customer)</option>
                    <!-- Populated via AJAX -->
                </select>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#quickCustomerModal" title="نیا گاہک کھاتہ شامل کریں">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
            <div id="posCustomerInfoBox"></div>
        </div>

        <!-- Cart Table -->
        <div class="cart-table-wrap">
            <div id="emptyCartNotice" class="text-center text-muted py-5">
                <i class="bi bi-cart3 fs-1 d-block text-secondary opacity-50"></i>
                کوئی چیز منتخب نہیں کی گئی<br>
                <small class="text-muted">بارکوڈ اسکین کریں یا پراڈکٹ پر کلک کریں</small>
            </div>
            <table class="cart-table table table-hover">
                <thead>
                    <tr>
                        <th>آئٹم</th>
                        <th style="width: 105px;">مقدار</th>
                        <th class="text-end" style="width: 80px;">رقم</th>
                        <th style="width: 30px;"></th>
                    </tr>
                </thead>
                <tbody id="cartTableBody">
                    <!-- Populated by pos.js -->
                </tbody>
            </table>
        </div>

        <!-- Cart Totals & Fast Payment -->
        <div class="cart-summary mt-auto">
            <div class="cart-total-row">
                <span class="text-muted">اشیاء کی تعداد:</span>
                <span class="fw-bold" id="cartItemsCount">0</span>
            </div>
            <div class="cart-total-row">
                <span class="text-muted">میزان (Subtotal):</span>
                <span class="fw-bold" id="posSubtotal">Rs. 0.00</span>
            </div>
            <div class="cart-total-row align-items-center">
                <span class="text-muted">رعایت (Discount Rs):</span>
                <input type="number" id="billDiscountInput" class="form-control form-control-sm text-end" style="width: 90px;" value="0" min="0">
            </div>

            <div class="cart-total-row cart-grand-total">
                <span>کل قابل ادا رقم:</span>
                <span id="posGrandTotal">Rs. 0.00</span>
            </div>

            <!-- Quick Cash Tendered & Change Return -->
            <div class="row g-2 mt-2 pt-2 border-top">
                <div class="col-6">
                    <label class="form-label small mb-1 fw-bold">وصول کیش (Tendered):</label>
                    <input type="number" id="cashTenderedInput" class="form-control form-control-sm fw-bold text-success fs-6" placeholder="0">
                </div>
                <div class="col-6">
                    <label class="form-label small mb-1 fw-bold">واپسی بقایا (Change):</label>
                    <div class="form-control form-control-sm fw-bold text-danger fs-6 bg-light" id="changeReturnAmount">Rs. 0.00</div>
                </div>
            </div>

            <!-- Checkout Buttons Grid -->
            <div class="d-grid gap-2 mt-3">
                <button type="button" class="btn btn-success btn-lg fw-bold shadow-sm py-2" onclick="triggerCheckout()">
                    <i class="bi bi-check-circle-fill me-1"></i> بل مکمل کریں (Checkout) <span class="badge bg-light text-success float-end">F4</span>
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-warning btn-sm w-50 fw-semibold" onclick="holdCurrentBill()">
                        <i class="bi bi-pause-fill"></i> ہولڈ بل (F8)
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-50 fw-semibold" onclick="resetBill()">
                        <i class="bi bi-x-circle"></i> نیا بل (F9)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Complete Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i> ادائیگی و بل کی تکمیل (Payment)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3 p-3 bg-light rounded-3 border">
                    <div class="small text-muted">کل واجب الادا رقم (Grand Total)</div>
                    <h2 class="fw-bold text-success mb-0" id="modalTotalAmount">Rs. 0.00</h2>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">وصول شدہ رقم (Received Amount):</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-light fw-bold">Rs.</span>
                        <input type="number" id="modalPaidAmount" class="form-control fw-bold text-success fs-4" oninput="updateModalChange()">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white">
                            <span class="small text-muted d-block">گاہک کو واپسی (Change):</span>
                            <span class="fw-bold text-primary fs-5" id="modalChangeAmount">Rs. 0.00</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-white">
                            <span class="small text-muted d-block">ادھار کھاتہ (Balance/Credit):</span>
                            <span class="fw-bold text-danger fs-5" id="modalBalanceAmount">Rs. 0.00</span>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">طریقہ ادائیگی (Payment Method):</label>
                    <select id="modalPaymentMethod" class="form-select">
                        <option value="Cash" selected>کیش (Cash)</option>
                        <option value="JazzCash">جاز کیش (JazzCash)</option>
                        <option value="Easypaisa">ایزی پیسہ (Easypaisa)</option>
                        <option value="Bank Transfer">بینک ٹرانسفر (Bank Transfer)</option>
                        <option value="Debit Card">ڈیبٹ / کریڈٹ کارڈ (Card)</option>
                        <option value="Customer Credit">ادھار کھاتہ (Customer Credit)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">جمع کا اکاؤنٹ (Deposit Account):</label>
                    <select id="modalPaymentAccount" class="form-select">
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>" <?php echo $acc['account_type'] == 'cash' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($acc['account_name']); ?> (موجودہ: <?php echo format_currency($acc['current_balance']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">منسوخ</button>
                <button type="button" class="btn btn-success fw-bold px-4" onclick="processSale()">
                    <i class="bi bi-printer-fill me-1"></i> محفوظ اور پرنٹ کریں (F4)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Add Customer Modal -->
<div class="modal fade" id="quickCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-plus me-1"></i> نیا گاہک شامل کریں (Add Customer)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="quickCustomerForm" onsubmit="event.preventDefault(); submitQuickCustomer();">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">نام گاہک *</label>
                        <input type="text" id="qcName" class="form-control" required placeholder="مثلاً: چوہدری اسلم">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">موبائل نمبر</label>
                        <input type="text" id="qcPhone" class="form-control" placeholder="03001234567">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">پتہ / محلہ</label>
                        <input type="text" id="qcAddress" class="form-control" placeholder="محلہ، گلی نمبر...">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold">گاہک کی قسم</label>
                            <select id="qcType" class="form-select">
                                <option value="retail">پرچون (Retail)</option>
                                <option value="wholesale">تھوک (Wholesale)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">ادھار کی حد (Credit Limit)</label>
                            <input type="number" id="qcCreditLimit" class="form-control" value="10000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">بند کریں</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold">محفوظ کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Held Bills Modal -->
<div class="modal fade" id="heldBillsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-pause-circle me-1"></i> روکے گئے بلز (Held Bills)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="heldBillsList">
                <!-- Rendered dynamically -->
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">بند کریں</button>
            </div>
        </div>
    </div>
</div>

<!-- Camera Barcode Scanner Modal -->
<div class="modal fade" id="cameraScannerModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-camera-fill me-2"></i> کیمرہ بارکوڈ اسکینر (Camera Barcode Scanner)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="stopCameraScanner()"></button>
            </div>
            <div class="modal-body p-3 text-center">
                <div class="mb-2 text-muted small">
                    <i class="bi bi-info-circle me-1"></i> کیمرہ کے سامنے بارکوڈ لائیں، سسٹم خود بخود اسکین کر لے گا۔
                </div>
                <div id="camera-reader" style="width: 100%; min-height: 280px; border-radius: 8px; overflow: hidden; background: #0f172a; display: flex; align-items: center; justify-content: center;" class="shadow-sm">
                    <div class="text-white small p-3">
                        <div class="spinner-border spinner-border-sm text-success me-2" role="status"></div>
                        کیمرہ آن ہو رہا ہے... (Starting Camera...)
                    </div>
                </div>
                <div id="camera-error" class="alert alert-danger mt-2 py-1 small" style="display: none;"></div>
                
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                    <div class="form-check form-switch text-start">
                        <input class="form-check-input" type="checkbox" id="cameraContinuousScan" checked>
                        <label class="form-check-label small fw-semibold" for="cameraContinuousScan">مسلسل اسکین کریں (Continuous Scan)</label>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" onclick="stopCameraScanner()">
                        بند کریں (Close)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- HTML5-QRCode Scanner Library CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- Include POS Core JS -->
<script src="assets/js/pos.js"></script>

<script>
function submitQuickCustomer() {
    const name = document.getElementById('qcName').value.trim();
    const phone = document.getElementById('qcPhone').value.trim();
    const address = document.getElementById('qcAddress').value.trim();
    const type = document.getElementById('qcType').value;
    const creditLimit = document.getElementById('qcCreditLimit').value;

    if (!name) return;

    const fd = new FormData();
    fd.append('action', 'quick_add_customer');
    fd.append('name', name);
    fd.append('phone', phone);
    fd.append('address', address);
    fd.append('customer_type', type);
    fd.append('credit_limit', creditLimit);

    fetch('ajax/pos_actions.php', {
        method: 'POST',
        body: fd
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Add to dropdown and select
            const select = document.getElementById('posCustomerSelect');
            const opt = document.createElement('option');
            opt.value = data.customer.id;
            opt.innerText = `${data.customer.name} (${data.customer.phone || ''}) - بقایا: Rs. 0.00`;
            opt.selected = true;
            select.appendChild(opt);

            selectedCustomer = data.customer;
            customersList.push(data.customer);
            updateCustomerBadge();

            const modal = bootstrap.Modal.getInstance(document.getElementById('quickCustomerModal'));
            if (modal) modal.hide();

            document.getElementById('quickCustomerForm').reset();
        } else {
            showToast(data.message, 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

