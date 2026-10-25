<?php
// purchase_create.php - New Purchase Invoice Entry
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $supplier_inv_no = trim($_POST['supplier_inv_no'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? date('Y-m-d');
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $discount = (float)($_POST['discount'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $payment_account_id = (int)($_POST['payment_account_id'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    if ($supplier_id <= 0) {
        $msg_error = "Please select a supplier! سپلائر منتخب کریں۔";
    } elseif (empty($items)) {
        $msg_error = "Please add at least one product to purchase! خریداری کے لیے اشیاء شامل کریں۔";
    } else {
        $balance_amount = max(0, $grand_total - $paid_amount);
        $payment_type = $paid_amount >= $grand_total ? 'cash' : ($paid_amount > 0 ? 'partial' : 'credit');
        $invoice_no = generate_purchase_no();
        $user_id = (int)$_SESSION['user_id'];

        $conn->begin_transaction();
        try {
            $s_inv_esc = db_escape($supplier_inv_no);
            $notes_esc = db_escape($notes);

            // 1. Insert Purchase Record
            $sql_p = "INSERT INTO purchases (invoice_no, supplier_id, supplier_invoice_no, purchase_date, 
                      subtotal, discount, grand_total, paid_amount, balance_amount, payment_account_id, payment_type, notes, created_by) 
                      VALUES ('$invoice_no', $supplier_id, '$s_inv_esc', '$purchase_date', 
                      $subtotal, $discount, $grand_total, $paid_amount, $balance_amount, $payment_account_id, '$payment_type', '$notes_esc', $user_id)";
            if (!$conn->query($sql_p)) {
                throw new Exception("Error creating purchase invoice: " . $conn->error);
            }
            $purchase_id = $conn->insert_id;

            // 2. Process Items & Update Stock
            foreach ($items as $item) {
                $product_id = (int)$item['product_id'];
                $qty = (float)$item['qty'];
                $unit_price = (float)$item['unit_price'];
                $item_total = $qty * $unit_price;

                if ($product_id > 0 && $qty > 0) {
                    $conn->query("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_price, subtotal) 
                                  VALUES ($purchase_id, $product_id, $qty, $unit_price, $item_total)");

                    // Increase stock & update latest purchase price
                    $conn->query("UPDATE products SET current_stock = current_stock + $qty, purchase_price = $unit_price WHERE id = $product_id");

                    // Fetch new balance
                    $p_row = db_fetch_one("SELECT current_stock FROM products WHERE id = $product_id");
                    $new_stock = $p_row ? (float)$p_row['current_stock'] : $qty;

                    // Write to Stock Ledger
                    $conn->query("INSERT INTO stock_ledger (product_id, transaction_type, reference_id, reference_no, qty_in, balance_after, cost_price, notes, created_by) 
                                  VALUES ($product_id, 'purchase', $purchase_id, '$invoice_no', $qty, $new_stock, $unit_price, 'Purchase Invoice #$invoice_no', $user_id)");
                }
            }

            // 3. Deduct paid amount from financial account (if paid)
            if ($paid_amount > 0) {
                $conn->query("UPDATE accounts SET current_balance = current_balance - $paid_amount WHERE id = $payment_account_id");

                // Shift cash tracking
                $shift = get_active_shift();
                if ($shift && $payment_account_id == 1) {
                    $conn->query("UPDATE shifts SET cash_supplier_payments = cash_supplier_payments + $paid_amount WHERE id = " . $shift['id']);
                }
            }

            // 4. Update Supplier Payable Balance & Ledger
            $supp = db_fetch_one("SELECT current_balance FROM suppliers WHERE id = $supplier_id");
            $prev_supp_balance = $supp ? (float)$supp['current_balance'] : 0.00;

            // Purchase bill adds to payable (Credit)
            $new_supp_balance = $prev_supp_balance + $grand_total;
            $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_type, reference_id, reference_no, debit, credit, balance_after, notes, created_by) 
                          VALUES ($supplier_id, 'purchase', $purchase_id, '$invoice_no', 0.00, $grand_total, $new_supp_balance, 'Purchase Bill #$invoice_no', $user_id)");

            // If paid anything now, record payment (Debit)
            if ($paid_amount > 0) {
                $new_supp_balance -= $paid_amount;
                $conn->query("INSERT INTO supplier_ledger (supplier_id, transaction_type, reference_id, reference_no, debit, credit, balance_after, account_id, notes, created_by) 
                              VALUES ($supplier_id, 'payment', $purchase_id, 'PAY-$invoice_no', $paid_amount, 0.00, $new_supp_balance, $payment_account_id, 'Payment for Purchase #$invoice_no', $user_id)");
            }

            // Update supplier final balance
            $conn->query("UPDATE suppliers SET current_balance = $new_supp_balance WHERE id = $supplier_id");

            log_audit("Create Purchase", "purchases", $purchase_id, "Invoice: $invoice_no, Total: $grand_total, Supplier: $supplier_id");

            $conn->commit();
            header("Location: purchases.php?msg=created");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $msg_error = $e->getMessage();
        }
    }
}

$suppliers = db_fetch_all("SELECT * FROM suppliers WHERE status = 'active' ORDER BY name ASC");
$products = db_fetch_all("SELECT p.*, u.symbol AS unit_symbol FROM products p LEFT JOIN units u ON p.unit_id = u.id WHERE p.status = 'active' ORDER BY p.name_en ASC");
$accounts = db_fetch_all("SELECT * FROM accounts WHERE status = 'active' ORDER BY account_type ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-bag-plus text-primary me-2"></i> نئی خریداری درج کریں (New Purchase Entry)</h4>
        <div class="text-muted small">ہول سیلر سے خریدا گیا مال اسٹاک میں شامل کریں اور کھاتہ اپ ڈیٹ کریں</div>
    </div>
    <a href="purchases.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> خریداری لسٹ پر جائیں
    </a>
</div>

<?php if ($msg_error): ?>
    <div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $msg_error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<form method="POST" action="purchase_create.php" id="purchaseForm">
    <?php echo csrf_field(); ?>

    <!-- Header Details -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold small">سپلائر منتخب کریں *</label>
                    <select name="supplier_id" id="suppSelect" class="form-select form-select-lg" required onchange="showSupplierBalance()">
                        <option value="">منتخب کریں...</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?php echo $s['id']; ?>" data-balance="<?php echo $s['current_balance']; ?>">
                                <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['company_name'] ?: 'عام'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="small mt-1" id="suppBalBadge"></div>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold small">سپلائر کا انوائس / بل نمبر</label>
                    <input type="text" name="supplier_inv_no" class="form-control" placeholder="Supplier Bill #">
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label fw-bold small">تاریخ خریداری</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold small">ادائیگی کا اکاؤنٹ (Account)</label>
                    <select name="payment_account_id" class="form-select">
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?php echo $acc['id']; ?>" <?php echo $acc['account_type'] == 'cash' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($acc['account_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-cart-plus me-2"></i> خریدی جانے والی اشیاء (Items)</h6>
            <button type="button" class="btn btn-sm btn-success fw-bold" onclick="addPurchaseRow()">
                <i class="bi bi-plus-lg"></i> مزید پراڈکٹ شامل کریں
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" id="purchaseTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">پراڈکٹ منتخب کریں</th>
                            <th style="width: 15%;">موجودہ اسٹاک</th>
                            <th style="width: 15%;">خرید مقدار (Qty)</th>
                            <th style="width: 15%;">فی اکائی خرید ریٹ (Cost)</th>
                            <th style="width: 15%;" class="text-end">میزان (Total)</th>
                            <th style="width: 30px;"></th>
                        </tr>
                    </thead>
                    <tbody id="purchaseItemsBody">
                        <!-- Default 1 row -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Totals & Payment Summary -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-bold small">ریمارکس / رسیپٹ نوٹ</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="بل کے بارے میں کوئی ضروری نوٹ..."></textarea>
                </div>

                <div class="col-12 col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">کل مالیت (Subtotal):</span>
                            <span class="fw-bold" id="pSubtotal">Rs. 0.00</span>
                            <input type="hidden" name="subtotal" id="inputSubtotal" value="0">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">ڈسکاؤنٹ / رعایت:</span>
                            <input type="number" step="0.01" name="discount" id="pDiscount" class="form-control form-control-sm text-end" style="width: 110px;" value="0.00" oninput="calcPurchaseTotals()">
                        </div>

                        <div class="d-flex justify-content-between border-top pt-2 mb-3">
                            <span class="fw-bold fs-5 text-dark">کل بل (Grand Total):</span>
                            <span class="fw-bold fs-5 text-primary" id="pGrandTotal">Rs. 0.00</span>
                            <input type="hidden" name="grand_total" id="inputGrandTotal" value="0">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-success">نقد ادا کی گئی رقم (Paid Now):</span>
                            <input type="number" step="0.01" name="paid_amount" id="pPaidAmount" class="form-control form-control-sm text-end fw-bold text-success fs-6" style="width: 130px;" value="0.00" oninput="calcPurchaseTotals()">
                        </div>

                        <div class="d-flex justify-content-between border-top pt-2">
                            <span class="fw-bold text-danger">سپلائر کھاتہ میں بقایا (Payable Due):</span>
                            <span class="fw-bold text-danger fs-6" id="pBalanceAmount">Rs. 0.00</span>
                        </div>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-success btn-lg fw-bold px-5 shadow-sm">
                            <i class="bi bi-check-circle-fill me-1"></i> خریداری محفوظ کریں (Save Purchase)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const productsData = <?php echo json_encode($products); ?>;
let rowCount = 0;

function addPurchaseRow() {
    rowCount++;
    const tbody = document.getElementById('purchaseItemsBody');
    const tr = document.createElement('tr');
    tr.id = `row_${rowCount}`;

    let options = '<option value="">پراڈکٹ منتخب کریں...</option>';
    productsData.forEach(p => {
        options += `<option value="${p.id}" data-cost="${p.purchase_price}" data-unit="${p.unit_symbol}" data-stock="${p.current_stock}">${p.name_ur || p.name_en} (${p.unit_symbol})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="items[${rowCount}][product_id]" class="form-select form-select-sm" required onchange="onProductSelect(${rowCount}, this)">
                ${options}
            </select>
        </td>
        <td>
            <span class="text-muted small" id="stock_${rowCount}">-</span>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" step="0.01" min="0.01" name="items[${rowCount}][qty]" id="qty_${rowCount}" class="form-control fw-bold" value="1" required oninput="calcRow(${rowCount})">
                <span class="input-group-text small" id="unit_${rowCount}">-</span>
            </div>
        </td>
        <td>
            <input type="number" step="0.01" min="0" name="items[${rowCount}][unit_price]" id="cost_${rowCount}" class="form-control form-control-sm fw-semibold" value="0.00" required oninput="calcRow(${rowCount})">
        </td>
        <td class="text-end fw-bold">
            <span id="sub_${rowCount}">Rs. 0.00</span>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm text-danger p-0 border-0" onclick="removePurchaseRow(${rowCount})">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removePurchaseRow(id) {
    const row = document.getElementById(`row_${id}`);
    if (row) row.remove();
    calcPurchaseTotals();
}

function onProductSelect(id, selectEl) {
    const opt = selectEl.options[selectEl.selectedIndex];
    if (opt && opt.value) {
        document.getElementById(`cost_${id}`).value = parseFloat(opt.dataset.cost).toFixed(2);
        document.getElementById(`stock_${id}`).innerText = `${opt.dataset.stock} ${opt.dataset.unit}`;
        document.getElementById(`unit_${id}`).innerText = opt.dataset.unit;
        calcRow(id);
    }
}

function calcRow(id) {
    const qty = parseFloat(document.getElementById(`qty_${id}`).value) || 0;
    const cost = parseFloat(document.getElementById(`cost_${id}`).value) || 0;
    const total = qty * cost;
    document.getElementById(`sub_${id}`).innerText = 'Rs. ' + total.toFixed(2);
    calcPurchaseTotals();
}

function calcPurchaseTotals() {
    let subtotal = 0;
    for (let i = 1; i <= rowCount; i++) {
        const qtyEl = document.getElementById(`qty_${i}`);
        const costEl = document.getElementById(`cost_${i}`);
        if (qtyEl && costEl) {
            subtotal += (parseFloat(qtyEl.value) || 0) * (parseFloat(costEl.value) || 0);
        }
    }

    const discount = parseFloat(document.getElementById('pDiscount').value) || 0;
    const grandTotal = Math.max(0, subtotal - discount);
    const paid = parseFloat(document.getElementById('pPaidAmount').value) || 0;
    const balance = Math.max(0, grandTotal - paid);

    document.getElementById('pSubtotal').innerText = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('inputSubtotal').value = subtotal.toFixed(2);

    document.getElementById('pGrandTotal').innerText = 'Rs. ' + grandTotal.toFixed(2);
    document.getElementById('inputGrandTotal').value = grandTotal.toFixed(2);

    document.getElementById('pBalanceAmount').innerText = 'Rs. ' + balance.toFixed(2);
}

function showSupplierBalance() {
    const sel = document.getElementById('suppSelect');
    const opt = sel.options[sel.selectedIndex];
    const badge = document.getElementById('suppBalBadge');
    if (opt && opt.dataset.balance) {
        badge.innerHTML = `<span class="badge bg-danger-subtle text-danger">سابقہ واجب الادا: Rs. ${parseFloat(opt.dataset.balance).toFixed(2)}</span>`;
    } else {
        badge.innerHTML = '';
    }
}

// Add first row on load
document.addEventListener("DOMContentLoaded", function() {
    addPurchaseRow();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

