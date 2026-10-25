<?php
// returns.php - Sales Returns & Exchanges Processing
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$msg = '';
$err = '';
$search_invoice = trim($_GET['invoice_no'] ?? '');
$sale = null;
$sale_items = [];

if (!empty($search_invoice)) {
    $inv_esc = db_escape($search_invoice);
    $sale = db_fetch_one("SELECT s.*, c.name AS cust_name FROM sales s LEFT JOIN customers c ON s.customer_id = c.id WHERE s.invoice_no = '$inv_esc'");
    if ($sale) {
        $sale_items = db_fetch_all("SELECT * FROM sale_items WHERE sale_id = " . $sale['id']);
    } else {
        $err = "Invoice #$search_invoice not found! انوائس نمبر نہیں ملا۔";
    }
}

// Process Return Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_return') {
    verify_csrf();

    $sale_id = (int)$_POST['sale_id'];
    $return_items = $_POST['return_items'] ?? [];
    $refund_type = $_POST['refund_type'] ?? 'cash'; // cash or credit_khata
    $account_id = (int)($_POST['account_id'] ?? 1);
    $reason = trim($_POST['reason'] ?? '');

    $sale_record = db_fetch_one("SELECT * FROM sales WHERE id = $sale_id");
    if (!$sale_record) {
        $err = "Sale record missing!";
    } else {
        $total_refund = 0;
        $items_to_return = [];

        foreach ($return_items as $product_id => $item_data) {
            $ret_qty = (float)($item_data['qty'] ?? 0);
            if ($ret_qty > 0) {
                $unit_price = (float)$item_data['price'];
                $cost_price = (float)$item_data['cost'];
                $line_subtotal = $ret_qty * $unit_price;
                $total_refund += $line_subtotal;

                $items_to_return[] = [
                    'product_id' => (int)$product_id,
                    'qty' => $ret_qty,
                    'unit_price' => $unit_price,
                    'cost_price' => $cost_price,
                    'subtotal' => $line_subtotal
                ];
            }
        }

        if (empty($items_to_return)) {
            $err = "Please enter return quantity for at least one item! برائے مہربانی واپس کی جانے والی مقدار درج کریں۔";
        } else {
            $conn->begin_transaction();
            try {
                $user_id = (int)$_SESSION['user_id'];
                $return_no = 'RET-' . date('Ymd-His');
                $inv_no = $sale_record['invoice_no'];
                $cust_id = (int)$sale_record['customer_id'];
                $reason_esc = db_escape($reason);

                // 1. Insert into sales_returns
                $sql_ret = "INSERT INTO sales_returns (return_no, sale_id, invoice_no, customer_id, refund_type, account_id, total_refund, reason, created_by) 
                            VALUES ('$return_no', $sale_id, '$inv_no', $cust_id, '$refund_type', $account_id, $total_refund, '$reason_esc', $user_id)";
                $conn->query($sql_ret);
                $ret_id = $conn->insert_id;

                // 2. Process returned items, restock inventory, stock ledger
                foreach ($items_to_return as $it) {
                    $pid = $it['product_id'];
                    $rqty = $it['qty'];
                    $rprice = $it['unit_price'];
                    $rcost = $it['cost_price'];
                    $rsub = $it['subtotal'];

                    // Save return item
                    $conn->query("INSERT INTO sales_return_items (return_id, product_id, quantity, unit_price, cost_price, subtotal) 
                                  VALUES ($ret_id, $pid, $rqty, $rprice, $rcost, $rsub)");

                    // Increase stock back
                    $conn->query("UPDATE products SET current_stock = current_stock + $rqty WHERE id = $pid");

                    $p_row = db_fetch_one("SELECT current_stock FROM products WHERE id = $pid");
                    $new_stock = $p_row ? (float)$p_row['current_stock'] : 0;

                    // Record into Stock Ledger
                    $conn->query("INSERT INTO stock_ledger (product_id, transaction_type, reference_id, reference_no, qty_in, balance_after, cost_price, sale_price, notes, created_by) 
                                  VALUES ($pid, 'sale_return', $ret_id, '$return_no', $rqty, $new_stock, $rcost, $rprice, 'Sale Return from Inv #$inv_no', $user_id)");

                    // Update returned qty in sale_items
                    $conn->query("UPDATE sale_items SET returned_qty = returned_qty + $rqty WHERE sale_id = $sale_id AND product_id = $pid");
                }

                // 3. Process Refund
                if ($refund_type === 'cash') {
                    // Deduct refund money from shop cash/account
                    $conn->query("UPDATE accounts SET current_balance = current_balance - $total_refund WHERE id = $account_id");

                    // Shift cash tracking
                    $shift = get_active_shift();
                    if ($shift && $account_id == 1) {
                        $conn->query("UPDATE shifts SET cash_sales = cash_sales - $total_refund WHERE id = " . $shift['id']);
                    }
                } elseif ($refund_type === 'credit_khata' && $cust_id > 0) {
                    // Reduce customer's outstanding khata balance (Credit)
                    $cust = db_fetch_one("SELECT current_balance FROM customers WHERE id = $cust_id");
                    $new_bal = $cust ? (float)$cust['current_balance'] - $total_refund : 0;
                    $conn->query("UPDATE customers SET current_balance = $new_bal WHERE id = $cust_id");

                    $conn->query("INSERT INTO customer_ledger (customer_id, transaction_type, reference_id, reference_no, debit, credit, balance_after, notes, created_by) 
                                  VALUES ($cust_id, 'sale_return', $ret_id, '$return_no', 0.00, $total_refund, $new_bal, 'Sales Return Credit - Inv #$inv_no', $user_id)");
                }

                log_audit("Sale Return", "sales_returns", $ret_id, "Return #$return_no, Total Refund: $total_refund");

                $conn->commit();
                $msg = "Return processed successfully! کل واپسی رقم: " . format_currency($total_refund);
                $sale = null;
                $search_invoice = '';
            } catch (Exception $e) {
                $conn->rollback();
                $err = $e->getMessage();
            }
        }
    }
}

// Past Returns List
$recent_returns = db_fetch_all("SELECT sr.*, c.name AS cust_name, u.full_name AS user_name 
                               FROM sales_returns sr 
                               LEFT JOIN customers c ON sr.customer_id = c.id 
                               LEFT JOIN users u ON sr.created_by = u.id 
                               ORDER BY sr.id DESC LIMIT 20");

$accounts = db_fetch_all("SELECT * FROM accounts WHERE status = 'active' ORDER BY account_type ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-arrow-counterclockwise text-danger me-2"></i> سیلز واپسی اور تبدیلی (Sales Returns & Refunds)</h4>
        <div class="text-muted small">گاہک سے خریدا گیا مال واپس لیں، اسٹاک بحال کریں اور رقم واپس یا کھاتہ کریڈٹ کریں</div>
    </div>
    <a href="pos.php" class="btn btn-success fw-bold shadow-sm">
        <i class="bi bi-cart me-1"></i> پی او ایس بلنگ
    </a>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Invoice Search Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="GET" action="returns.php" class="row g-2 align-items-center">
            <div class="col-12 col-md-8">
                <label class="form-label fw-bold small">اصل بل نمبر درج کریں (Search Invoice #):</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-danger text-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="invoice_no" class="form-control" placeholder="مثلاً: INV-20261025-0001" value="<?php echo htmlspecialchars($search_invoice); ?>" required autofocus>
                </div>
            </div>
            <div class="col-12 col-md-4 mt-auto">
                <button type="submit" class="btn btn-danger btn-lg fw-bold w-100">
                    <i class="bi bi-arrow-right-circle me-1"></i> بل تلاش کریں (Find Bill)
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($sale): ?>
    <!-- Return Invoice Details & Item Selection -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 border-top border-danger border-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-0 text-dark">بل نمبر: #<?php echo $sale['invoice_no']; ?></h5>
                <div class="small text-muted">
                    تاریخ: <?php echo format_datetime($sale['created_at']); ?> | 
                    گاہک: <strong><?php echo htmlspecialchars($sale['customer_name'] ?: 'عام خریدار'); ?></strong> | 
                    کل بل: <strong><?php echo format_currency($sale['grand_total']); ?></strong>
                </div>
            </div>
            <span class="badge bg-success-subtle text-success fs-6">سیل تصدیق شدہ</span>
        </div>
        <div class="card-body p-4">
            <form method="POST" action="returns.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="process_return">
                <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">

                <h6 class="fw-bold mb-3">واپس کی جانے والی اشیاء اور مقدار درج کریں:</h6>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>پراڈکٹ</th>
                                <th class="text-center">فروخت مقدار</th>
                                <th class="text-center">پہلے سے واپس</th>
                                <th class="text-end">فروخت ریٹ</th>
                                <th style="width: 160px;" class="text-center">واپسی مقدار (Return Qty)</th>
                                <th class="text-end fw-bold" style="width: 140px;">واپسی رقم</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale_items as $it): 
                                $available_to_return = $it['quantity'] - $it['returned_qty'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($it['product_name']); ?></div>
                                        <div class="small text-muted"><?php echo $it['unit_name']; ?></div>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo number_format($it['quantity'], 2); ?></td>
                                    <td class="text-center text-muted"><?php echo number_format($it['returned_qty'], 2); ?></td>
                                    <td class="text-end fw-semibold">Rs. <?php echo number_format($it['unit_price'], 2); ?></td>
                                    <td>
                                        <input type="hidden" name="return_items[<?php echo $it['product_id']; ?>][price]" value="<?php echo $it['unit_price']; ?>">
                                        <input type="hidden" name="return_items[<?php echo $it['product_id']; ?>][cost]" value="<?php echo $it['cost_price']; ?>">
                                        <input type="number" step="0.01" min="0" max="<?php echo $available_to_return; ?>" 
                                               name="return_items[<?php echo $it['product_id']; ?>][qty]" 
                                               id="rqty_<?php echo $it['product_id']; ?>" 
                                               class="form-control form-control-sm text-center fw-bold" 
                                               value="0" oninput="calcReturnLine(<?php echo $it['product_id']; ?>, <?php echo $it['unit_price']; ?>)">
                                    </td>
                                    <td class="text-end fw-bold text-danger">
                                        <span id="rsub_<?php echo $it['product_id']; ?>">Rs. 0.00</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3 bg-light p-3 rounded-3 border mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold small">رقم واپسی کا طریقہ (Refund Method) *</label>
                        <select name="refund_type" class="form-select" id="refundTypeSelect">
                            <option value="cash">کیش واپسی (Cash Refund to Customer)</option>
                            <?php if ($sale['customer_id'] > 0): ?>
                                <option value="credit_khata">گاہک کے ادھار کھاتہ میں کریڈٹ / کمی (Khata Credit)</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold small">کس اکاؤنٹ سے کیش واپس ہوا؟</label>
                        <select name="account_id" class="form-select">
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['account_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold small">واپسی کی وجہ (Reason)</label>
                        <input type="text" name="reason" class="form-control" placeholder="مثلاً: گاہک کو پسند نہیں آیا، غلط چیز لے گیا...">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">کل قابل واپسی رقم (Total Refund):</span>
                        <h3 class="fw-bold text-danger mb-0" id="totalRefundDisplay">Rs. 0.00</h3>
                    </div>
                    <button type="submit" class="btn btn-danger btn-lg fw-bold px-5 shadow-sm" onclick="return confirm('Confirm Return and Restock? کیا آپ واپسی محفوظ کرنا چاہتے ہیں؟');">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> واپسی مکمل کریں (Complete Return)
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Returns History Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i> حالیہ سیلز واپسی کی تاریخ (Past Returns)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>واپسی نمبر (Return #)</th>
                        <th>اصل بل نمبر</th>
                        <th>تاریخ</th>
                        <th>گاہک</th>
                        <th>طریقہ</th>
                        <th class="text-end fw-bold">واپسی رقم</th>
                        <th>وجہ</th>
                        <th>صارف</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_returns)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">ابھی تک کوئی سیلز واپسی نہیں ہوئی</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_returns as $rr): ?>
                            <tr>
                                <td><code><?php echo $rr['return_no']; ?></code></td>
                                <td class="fw-bold text-primary">#<?php echo $rr['invoice_no']; ?></td>
                                <td class="small text-muted"><?php echo format_datetime($rr['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($rr['cust_name'] ?: 'عام خریدار'); ?></td>
                                <td>
                                    <span class="badge <?php echo $rr['refund_type'] == 'cash' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'; ?>">
                                        <?php echo $rr['refund_type'] == 'cash' ? 'کیش واپسی' : 'کھاتہ کریڈٹ'; ?>
                                    </span>
                                </td>
                                <td class="text-end fw-bold text-danger fs-6"><?php echo format_currency($rr['total_refund']); ?></td>
                                <td class="small"><?php echo htmlspecialchars($rr['reason'] ?: '-'); ?></td>
                                <td class="small text-muted"><?php echo htmlspecialchars($rr['user_name'] ?? 'Admin'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function calcReturnLine(pid, price) {
    const qty = parseFloat(document.getElementById(`rqty_${pid}`).value) || 0;
    const lineTotal = qty * price;
    document.getElementById(`rsub_${pid}`).innerText = 'Rs. ' + lineTotal.toFixed(2);

    // Sum all lines
    let total = 0;
    const inputs = document.querySelectorAll('input[id^="rqty_"]');
    inputs.forEach(inp => {
        const linePid = inp.id.replace('rqty_', '');
        const lineSubText = document.getElementById(`rsub_${linePid}`).innerText.replace('Rs. ', '');
        total += parseFloat(lineSubText) || 0;
    });

    document.getElementById('totalRefundDisplay').innerText = 'Rs. ' + total.toFixed(2);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

