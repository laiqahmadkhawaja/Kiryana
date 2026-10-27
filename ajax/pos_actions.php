<?php
// ajax/pos_actions.php - High speed POS backend handler
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized / لاگ ان درکار ہے']);
    exit;
}

$lang = get_current_lang();

// 1. Search Products
if ($action === 'search_products') {
    $q = trim($_GET['q'] ?? '');
    $cat_id = (int)($_GET['category_id'] ?? 0);
    $price_tier = $_GET['price_tier'] ?? 'retail'; // retail or wholesale

    $where = "WHERE p.status = 'active'";
    if (!empty($q)) {
        $q_esc = db_escape($q);
        $where .= " AND (p.barcode = '$q_esc' OR p.code LIKE '%$q_esc%' OR p.name_en LIKE '%$q_esc%' OR p.name_ur LIKE '%$q_esc%')";
    }
    if ($cat_id > 0) {
        $where .= " AND p.category_id = $cat_id";
    }

    $sql = "SELECT p.*, u.name_en AS unit_name_en, u.name_ur AS unit_name_ur, u.symbol AS unit_symbol, 
            u.conversion_factor, u.allow_decimal, u.base_unit_id,
            c.name_en AS category_name_en, c.name_ur AS category_name_ur
            FROM products p
            LEFT JOIN units u ON p.unit_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            $where
            ORDER BY p.name_en ASC LIMIT 40";

    $products = db_fetch_all($sql);
    $formatted = [];
    foreach ($products as $p) {
        $sale_price = $price_tier === 'wholesale' ? (float)$p['wholesale_price'] : (float)$p['retail_price'];
        if ($sale_price <= 0) $sale_price = (float)$p['retail_price'];

        $formatted[] = [
            'id' => (int)$p['id'],
            'code' => $p['code'],
            'barcode' => $p['barcode'],
            'name' => $lang === 'ur' ? ($p['name_ur'] ?: $p['name_en']) : $p['name_en'],
            'name_en' => $p['name_en'],
            'name_ur' => $p['name_ur'],
            'retail_price' => (float)$p['retail_price'],
            'wholesale_price' => (float)$p['wholesale_price'],
            'current_price' => $sale_price,
            'purchase_price' => (float)$p['purchase_price'],
            'min_sale_price' => (float)$p['min_sale_price'],
            'current_stock' => (float)$p['current_stock'],
            'unit_id' => (int)$p['unit_id'],
            'unit_name' => $lang === 'ur' ? ($p['unit_name_ur'] ?: $p['unit_name_en']) : $p['unit_name_en'],
            'unit_symbol' => $p['unit_symbol'],
            'allow_decimal' => (int)$p['allow_decimal'],
            'conversion_factor' => (float)$p['conversion_factor'],
            'has_batch_expiry' => (int)$p['has_batch_expiry']
        ];
    }

    echo json_encode(['success' => true, 'products' => $formatted]);
    exit;
}

// 2. Search Customers
if ($action === 'search_customers') {
    $q = trim($_GET['q'] ?? '');
    $where = "WHERE status = 'active'";
    if (!empty($q)) {
        $q_esc = db_escape($q);
        $where .= " AND (name LIKE '%$q_esc%' OR phone LIKE '%$q_esc%')";
    }
    $customers = db_fetch_all("SELECT id, name, phone, customer_type, current_balance, credit_limit FROM customers $where ORDER BY name ASC LIMIT 30");
    echo json_encode(['success' => true, 'customers' => $customers]);
    exit;
}

// 3. Complete Sale (Transaction Safe)
if ($action === 'save_sale') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $customer_id = (int)($input['customer_id'] ?? 0);
    $customer_name = trim($input['customer_name'] ?? 'Walk-in Customer');
    $customer_type = $input['customer_type'] ?? 'retail';
    $sale_type = $input['sale_type'] ?? 'cash'; // cash, credit, split
    $subtotal = (float)($input['subtotal'] ?? 0);
    $discount_amount = (float)($input['discount_amount'] ?? 0);
    $tax_amount = (float)($input['tax_amount'] ?? 0);
    $grand_total = (float)($input['grand_total'] ?? 0);
    $paid_amount = (float)($input['paid_amount'] ?? 0);
    $balance_amount = (float)($input['balance_amount'] ?? 0);
    $change_amount = (float)($input['change_amount'] ?? 0);
    $payment_account_id = (int)($input['payment_account_id'] ?? 1); // 1 = Cash in Hand
    $payment_method = $input['payment_method'] ?? 'Cash';
    $notes = trim($input['notes'] ?? '');
    $items = $input['items'] ?? [];
    $split_payments = $input['split_payments'] ?? [];

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty! برائے مہربانی اشیاء منتخب کریں۔']);
        exit;
    }

    // Disallow credit or partial payment for walk-in / unregistered customers
    if ($customer_id <= 0 && ($sale_type === 'credit' || $sale_type === 'split' || $balance_amount > 0 || $payment_method === 'Customer Credit' || $paid_amount < $grand_total)) {
        echo json_encode([
            'success' => false,
            'message' => 'Walk-in customers cannot make credit purchases! عام خریدار کو ادھار پر بل جاری نہیں کیا جا سکتا۔ برائے مہربانی کھاتہ سے گاہک منتخب کریں۔'
        ]);
        exit;
    }

    // Check active shift
    $shift = get_active_shift();
    $shift_id = $shift ? (int)$shift['id'] : 0;
    $user_id = (int)$_SESSION['user_id'];
    $invoice_no = generate_invoice_no();

    // Start DB transaction
    $conn->begin_transaction();

    try {
        // Check credit limit if credit sale
        if ($sale_type === 'credit' || ($sale_type === 'split' && $balance_amount > 0)) {
            if ($customer_id <= 0) {
                throw new Exception("Credit sale requires a registered customer! ادھار فروخت کے لیے گاہک منتخب کرنا لازمی ہے۔");
            }
            $cust = db_fetch_one("SELECT * FROM customers WHERE id = $customer_id");
            if ($cust && (float)$cust['credit_limit'] > 0) {
                $new_balance = (float)$cust['current_balance'] + $balance_amount;
                if ($new_balance > (float)$cust['credit_limit']) {
                    throw new Exception("Credit limit exceeded! گاہک کی ادھار کی حد (" . format_currency($cust['credit_limit']) . ") مکمل ہو چکی ہے۔");
                }
            }
        }

        // Insert into sales table
        $c_name_esc = db_escape($customer_name);
        $notes_esc = db_escape($notes);
        $pm_esc = db_escape($payment_method);

        $sql_sale = "INSERT INTO sales (invoice_no, shift_id, customer_id, customer_name, customer_type, sale_type, 
                     subtotal, discount_amount, tax_amount, grand_total, paid_amount, balance_amount, change_amount, 
                     payment_account_id, payment_method, status, notes, created_by, created_at) 
                     VALUES ('$invoice_no', $shift_id, $customer_id, '$c_name_esc', '$customer_type', '$sale_type', 
                     $subtotal, $discount_amount, $tax_amount, $grand_total, $paid_amount, $balance_amount, $change_amount, 
                     $payment_account_id, '$pm_esc', 'completed', '$notes_esc', $user_id, NOW())";

        if (!$conn->query($sql_sale)) {
            throw new Exception("Error creating sale invoice: " . $conn->error);
        }
        $sale_id = $conn->insert_id;

        // Process Items & Stock deduction
        foreach ($items as $item) {
            $product_id = (int)$item['product_id'];
            $p_name = db_escape($item['name']);
            $unit_name = db_escape($item['unit_symbol'] ?? '');
            $qty = (float)$item['qty'];
            $price = (float)$item['price'];
            $cost = (float)($item['cost_price'] ?? 0);
            $item_discount = (float)($item['discount'] ?? 0);
            $item_subtotal = (float)$item['subtotal'];
            $conv_factor = (float)($item['conversion_factor'] ?? 1);
            if ($conv_factor <= 0) $conv_factor = 1;

            // Base unit quantity deduction from inventory
            $stock_deduct_qty = $qty * $conv_factor;

            // Insert sale item
            $sql_item = "INSERT INTO sale_items (sale_id, product_id, product_name, unit_name, quantity, unit_price, cost_price, discount, subtotal) 
                         VALUES ($sale_id, $product_id, '$p_name', '$unit_name', $qty, $price, $cost, $item_discount, $item_subtotal)";
            if (!$conn->query($sql_item)) {
                throw new Exception("Error saving sale items: " . $conn->error);
            }

            // Deduct stock from products table
            $sql_stock = "UPDATE products SET current_stock = current_stock - $stock_deduct_qty WHERE id = $product_id";
            if (!$conn->query($sql_stock)) {
                throw new Exception("Error updating product stock: " . $conn->error);
            }

            // Fetch new stock balance
            $p_info = db_fetch_one("SELECT current_stock FROM products WHERE id = $product_id");
            $new_stock = $p_info ? (float)$p_info['current_stock'] : 0;

            // Record into Stock Ledger
            $sql_stk_ledger = "INSERT INTO stock_ledger (product_id, transaction_type, reference_id, reference_no, qty_out, balance_after, cost_price, sale_price, notes, created_by) 
                               VALUES ($product_id, 'sale', $sale_id, '$invoice_no', $stock_deduct_qty, $new_stock, $cost, $price, 'Sale POS Invoice #$invoice_no', $user_id)";
            $conn->query($sql_stk_ledger);
        }

        // Process Split Payments or Single Payment
        if ($sale_type === 'split' && !empty($split_payments)) {
            foreach ($split_payments as $sp) {
                $sp_acc_id = (int)$sp['account_id'];
                $sp_method = db_escape($sp['method']);
                $sp_amount = (float)$sp['amount'];
                $sp_note = db_escape($sp['note'] ?? '');

                if ($sp_amount > 0) {
                    $conn->query("INSERT INTO sale_payments (sale_id, account_id, payment_method, amount, note) 
                                  VALUES ($sale_id, $sp_acc_id, '$sp_method', $sp_amount, '$sp_note')");

                    // Increase account balance
                    $conn->query("UPDATE accounts SET current_balance = current_balance + $sp_amount WHERE id = $sp_acc_id");
                }
            }
        } elseif ($paid_amount > 0 && $payment_account_id > 0) {
            // Single payment - update account balance
            $net_cash_received = $paid_amount - $change_amount;
            $conn->query("UPDATE accounts SET current_balance = current_balance + $net_cash_received WHERE id = $payment_account_id");
        }

        // Update Customer Khata Ledger if Credit or Balance exists
        if ($customer_id > 0) {
            $cust = db_fetch_one("SELECT current_balance FROM customers WHERE id = $customer_id");
            $prev_cust_balance = $cust ? (float)$cust['current_balance'] : 0.00;

            // If customer paid anything, or took on credit:
            if ($balance_amount > 0) {
                // Customer took items on credit: Debit to customer ledger
                $new_cust_balance = $prev_cust_balance + $balance_amount;
                $conn->query("UPDATE customers SET current_balance = $new_cust_balance WHERE id = $customer_id");

                $conn->query("INSERT INTO customer_ledger (customer_id, transaction_type, reference_id, reference_no, debit, credit, balance_after, account_id, notes, created_by) 
                              VALUES ($customer_id, 'sale', $sale_id, '$invoice_no', $balance_amount, 0.00, $new_cust_balance, $payment_account_id, 'Grocery Sale on Credit - Inv #$invoice_no', $user_id)");
            }
        }

        // Update shift stats if shift is open and cash payment received
        if ($shift_id > 0 && $paid_amount > 0) {
            $actual_cash_added = ($payment_method === 'Cash' || $payment_account_id == 1) ? ($paid_amount - $change_amount) : 0;
            if ($actual_cash_added > 0) {
                $conn->query("UPDATE shifts SET cash_sales = cash_sales + $actual_cash_added WHERE id = $shift_id");
            }
        }

        // Audit log
        log_audit("Sale Created", "sales", $sale_id, "Invoice: $invoice_no, Total: $grand_total, Paid: $paid_amount");

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Sale completed successfully! بل کامیابی سے محفوظ ہو گیا ہے۔',
            'sale_id' => $sale_id,
            'invoice_no' => $invoice_no,
            'grand_total' => $grand_total,
            'paid_amount' => $paid_amount,
            'change_amount' => $change_amount,
            'balance_amount' => $balance_amount
        ]);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// 4. Quick Hold Bill
if ($action === 'hold_bill') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $hold_key = 'held_bill_' . time();
    if (!isset($_SESSION['held_bills'])) {
        $_SESSION['held_bills'] = [];
    }
    $_SESSION['held_bills'][$hold_key] = [
        'id' => $hold_key,
        'customer_name' => $input['customer_name'] ?? 'Walk-in',
        'customer_id' => $input['customer_id'] ?? 0,
        'items' => $input['items'] ?? [],
        'time' => date('h:i A'),
        'total' => $input['grand_total'] ?? 0
    ];
    echo json_encode(['success' => true, 'message' => 'Bill held! بل وقتی طور پر محفوظ کر دیا گیا۔', 'held_bills' => array_values($_SESSION['held_bills'])]);
    exit;
}

// 5. Get Held Bills
if ($action === 'get_held_bills') {
    $held = $_SESSION['held_bills'] ?? [];
    echo json_encode(['success' => true, 'held_bills' => array_values($held)]);
    exit;
}

// 6. Delete Held Bill
if ($action === 'delete_held_bill') {
    $id = $_REQUEST['hold_id'] ?? '';
    if (isset($_SESSION['held_bills'][$id])) {
        unset($_SESSION['held_bills'][$id]);
    }
    echo json_encode(['success' => true, 'held_bills' => array_values($_SESSION['held_bills'] ?? [])]);
    exit;
}

// 7. Quick Add Customer from POS Modal
if ($action === 'quick_add_customer') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $customer_type = $_POST['customer_type'] ?? 'retail';
    $credit_limit = (float)($_POST['credit_limit'] ?? 5000);

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Customer name is required! گاہک کا نام لکھیں۔']);
        exit;
    }

    $n_esc = db_escape($name);
    $p_esc = db_escape($phone);
    $a_esc = db_escape($address);

    $sql = "INSERT INTO customers (name, phone, address, customer_type, opening_balance, current_balance, credit_limit, status) 
            VALUES ('$n_esc', '$p_esc', '$a_esc', '$customer_type', 0.00, 0.00, $credit_limit, 'active')";
    if (db_query($sql)) {
        $new_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Customer added! نیا گاہک شامل ہو گیا۔',
            'customer' => [
                'id' => $new_id,
                'name' => $name,
                'phone' => $phone,
                'current_balance' => 0.00,
                'credit_limit' => $credit_limit
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
