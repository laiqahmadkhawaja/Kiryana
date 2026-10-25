<?php
// setup.php - Database installer and initial seeder for Kiryana Store Management System
require_once __DIR__ . '/config/db.php';

echo "<h3>Initializing Kiryana Store Management System Database...</h3>\n";

// Table schemas
$queries = [
    // Settings Table
    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Users Table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'manager', 'cashier', 'accountant') DEFAULT 'cashier',
        phone VARCHAR(30) DEFAULT '',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Units of Measurement Table (Dynamic, base units and conversions)
    "CREATE TABLE IF NOT EXISTS units (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name_en VARCHAR(50) NOT NULL,
        name_ur VARCHAR(50) NOT NULL,
        symbol VARCHAR(20) NOT NULL,
        base_unit_id INT DEFAULT NULL,
        conversion_factor DECIMAL(12,4) DEFAULT 1.0000,
        allow_decimal TINYINT(1) DEFAULT 1,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Categories Table
    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name_en VARCHAR(100) NOT NULL,
        name_ur VARCHAR(100) NOT NULL,
        parent_id INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Brands Table
    "CREATE TABLE IF NOT EXISTS brands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name_en VARCHAR(100) NOT NULL,
        name_ur VARCHAR(100) DEFAULT '',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Financial Accounts (Cash, Bank, Wallets)
    "CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_name VARCHAR(100) NOT NULL,
        account_type ENUM('cash', 'bank', 'wallet_jazzcash', 'wallet_easypaisa', 'other') DEFAULT 'cash',
        account_number VARCHAR(50) DEFAULT '',
        bank_name VARCHAR(100) DEFAULT '',
        current_balance DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Products Table
    "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        barcode VARCHAR(50) DEFAULT '',
        name_en VARCHAR(150) NOT NULL,
        name_ur VARCHAR(150) DEFAULT '',
        category_id INT DEFAULT 0,
        brand_id INT DEFAULT 0,
        unit_id INT NOT NULL,
        purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        retail_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        wholesale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        min_sale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        current_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000,
        alert_quantity DECIMAL(12,3) NOT NULL DEFAULT 5.000,
        has_batch_expiry TINYINT(1) DEFAULT 0,
        image_path VARCHAR(255) DEFAULT '',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (barcode),
        INDEX (name_en),
        INDEX (name_ur),
        INDEX (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Product Batches (Optional expiry management)
    "CREATE TABLE IF NOT EXISTS product_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        batch_no VARCHAR(50) NOT NULL,
        expiry_date DATE,
        quantity DECIMAL(12,3) NOT NULL DEFAULT 0.000,
        purchase_price DECIMAL(12,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (product_id),
        INDEX (expiry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Stock Movement Ledger
    "CREATE TABLE IF NOT EXISTS stock_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        transaction_type ENUM('opening', 'sale', 'purchase', 'sale_return', 'purchase_return', 'adjustment_damage', 'adjustment_expired', 'adjustment_physical') NOT NULL,
        reference_id INT DEFAULT 0,
        reference_no VARCHAR(50) DEFAULT '',
        qty_in DECIMAL(12,3) DEFAULT 0.000,
        qty_out DECIMAL(12,3) DEFAULT 0.000,
        balance_after DECIMAL(12,3) NOT NULL,
        cost_price DECIMAL(12,2) DEFAULT 0.00,
        sale_price DECIMAL(12,2) DEFAULT 0.00,
        notes TEXT,
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (product_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Customers Table
    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        phone VARCHAR(30) DEFAULT '',
        address TEXT,
        customer_type ENUM('retail', 'wholesale') DEFAULT 'retail',
        opening_balance DECIMAL(15,2) DEFAULT 0.00,
        current_balance DECIMAL(15,2) DEFAULT 0.00,
        credit_limit DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (phone),
        INDEX (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Customer Khata Ledger
    "CREATE TABLE IF NOT EXISTS customer_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        transaction_type ENUM('opening', 'sale', 'sale_return', 'payment', 'adjustment') NOT NULL,
        reference_id INT DEFAULT 0,
        reference_no VARCHAR(50) DEFAULT '',
        debit DECIMAL(15,2) DEFAULT 0.00,
        credit DECIMAL(15,2) DEFAULT 0.00,
        balance_after DECIMAL(15,2) NOT NULL,
        account_id INT DEFAULT 0,
        notes TEXT,
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (customer_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Suppliers Table
    "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        company_name VARCHAR(150) DEFAULT '',
        phone VARCHAR(30) DEFAULT '',
        address TEXT,
        opening_balance DECIMAL(15,2) DEFAULT 0.00,
        current_balance DECIMAL(15,2) DEFAULT 0.00,
        credit_limit DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (phone),
        INDEX (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Supplier Ledger
    "CREATE TABLE IF NOT EXISTS supplier_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        transaction_type ENUM('opening', 'purchase', 'purchase_return', 'payment', 'adjustment') NOT NULL,
        reference_id INT DEFAULT 0,
        reference_no VARCHAR(50) DEFAULT '',
        debit DECIMAL(15,2) DEFAULT 0.00,
        credit DECIMAL(15,2) DEFAULT 0.00,
        balance_after DECIMAL(15,2) NOT NULL,
        account_id INT DEFAULT 0,
        notes TEXT,
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (supplier_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Shifts / Cash Register
    "CREATE TABLE IF NOT EXISTS shifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME DEFAULT NULL,
        opening_cash DECIMAL(12,2) DEFAULT 0.00,
        cash_sales DECIMAL(12,2) DEFAULT 0.00,
        cash_recoveries DECIMAL(12,2) DEFAULT 0.00,
        cash_expenses DECIMAL(12,2) DEFAULT 0.00,
        cash_supplier_payments DECIMAL(12,2) DEFAULT 0.00,
        expected_cash DECIMAL(12,2) DEFAULT 0.00,
        counted_cash DECIMAL(12,2) DEFAULT 0.00,
        difference DECIMAL(12,2) DEFAULT 0.00,
        status ENUM('open', 'closed') DEFAULT 'open',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Sales Table
    "CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50) UNIQUE NOT NULL,
        shift_id INT DEFAULT 0,
        customer_id INT DEFAULT 0,
        customer_name VARCHAR(120) DEFAULT 'Walk-in Customer',
        customer_type ENUM('retail', 'wholesale') DEFAULT 'retail',
        sale_type ENUM('cash', 'credit', 'split') DEFAULT 'cash',
        subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        change_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        payment_account_id INT DEFAULT 0,
        payment_method VARCHAR(50) DEFAULT 'Cash',
        status ENUM('completed', 'held', 'draft', 'returned', 'cancelled') DEFAULT 'completed',
        notes TEXT,
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (invoice_no),
        INDEX (customer_id),
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Sale Items Table
    "CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(150) NOT NULL,
        unit_name VARCHAR(50) DEFAULT '',
        quantity DECIMAL(12,3) NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        discount DECIMAL(12,2) DEFAULT 0.00,
        subtotal DECIMAL(12,2) NOT NULL,
        returned_qty DECIMAL(12,3) DEFAULT 0.000,
        INDEX (sale_id),
        INDEX (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Sale Split Payments
    "CREATE TABLE IF NOT EXISTS sale_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        account_id INT NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(150) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (sale_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Sales Returns
    "CREATE TABLE IF NOT EXISTS sales_returns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_no VARCHAR(50) UNIQUE NOT NULL,
        sale_id INT NOT NULL,
        invoice_no VARCHAR(50) NOT NULL,
        customer_id INT DEFAULT 0,
        refund_type ENUM('cash', 'credit_khata') DEFAULT 'cash',
        account_id INT DEFAULT 0,
        total_refund DECIMAL(12,2) NOT NULL,
        reason TEXT,
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (sale_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Sales Return Items
    "CREATE TABLE IF NOT EXISTS sales_return_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        return_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity DECIMAL(12,3) NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        cost_price DECIMAL(12,2) NOT NULL,
        subtotal DECIMAL(12,2) NOT NULL,
        INDEX (return_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Purchases Table
    "CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50) UNIQUE NOT NULL,
        supplier_id INT NOT NULL,
        supplier_invoice_no VARCHAR(50) DEFAULT '',
        purchase_date DATE NOT NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        discount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        grand_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        balance_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        payment_account_id INT DEFAULT 0,
        payment_type ENUM('cash', 'credit', 'partial') DEFAULT 'cash',
        notes TEXT,
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (invoice_no),
        INDEX (supplier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Purchase Items Table
    "CREATE TABLE IF NOT EXISTS purchase_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity DECIMAL(12,3) NOT NULL,
        unit_price DECIMAL(12,2) NOT NULL,
        subtotal DECIMAL(15,2) NOT NULL,
        batch_no VARCHAR(50) DEFAULT '',
        expiry_date DATE DEFAULT NULL,
        INDEX (purchase_id),
        INDEX (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Expense Categories
    "CREATE TABLE IF NOT EXISTS expense_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name_en VARCHAR(100) NOT NULL,
        name_ur VARCHAR(100) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Expenses Table
    "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        account_id INT NOT NULL,
        shift_id INT DEFAULT 0,
        amount DECIMAL(12,2) NOT NULL,
        expense_date DATE NOT NULL,
        description TEXT,
        reference_no VARCHAR(50) DEFAULT '',
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (category_id),
        INDEX (expense_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Account Transfers (e.g. Cash in Hand to Bank)
    "CREATE TABLE IF NOT EXISTS account_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_account_id INT NOT NULL,
        to_account_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        transfer_date DATE NOT NULL,
        reference VARCHAR(50) DEFAULT '',
        note TEXT,
        created_by INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Audit Logs
    "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 0,
        action VARCHAR(100) NOT NULL,
        table_name VARCHAR(50) DEFAULT '',
        record_id INT DEFAULT 0,
        details TEXT,
        ip_address VARCHAR(50) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $q) {
    if (!$conn->query($q)) {
        echo "<div style='color:red;'>Query failed: " . $conn->error . "</div>";
    }
}
echo "<div style='color:green;'>Tables created successfully!</div>\n";

// --- Seed Default Settings ---
$default_settings = [
    'store_name_en' => 'Al-Madina Kiryana & General Store',
    'store_name_ur' => 'المدینہ کریانہ اینڈ جنرل اسٹور',
    'slogan_en' => 'Quality Groceries & Best Wholesale Rates',
    'slogan_ur' => 'بہترین معیار اور مناسب ترین ریٹ',
    'address_en' => 'Shop # 12, Main Bazar, Lahore, Pakistan',
    'address_ur' => 'دکان نمبر 12، مین بازار، لاہور، پاکستان',
    'phone' => '0300-1234567 / 0321-7654321',
    'currency_symbol' => 'Rs.',
    'currency_code' => 'PKR',
    'default_language' => 'ur',
    'receipt_printer_type' => '80mm', // 58mm, 80mm, A4
    'receipt_header' => 'Welcome to Al-Madina Kiryana Store!',
    'receipt_footer' => 'تبدیلی یا واپسی 3 دن کے اندر بل کے ساتھ ممکن ہے۔ شکریہ!',
    'low_stock_threshold' => '10',
    'allow_negative_stock' => '0'
];

foreach ($default_settings as $k => $v) {
    set_setting($k, $v);
}
echo "<div>Settings seeded!</div>\n";

// --- Seed Default Users ---
$admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
$cashier_pass = password_hash('123456', PASSWORD_DEFAULT);

$conn->query("INSERT IGNORE INTO users (id, username, password_hash, full_name, role, phone, status) VALUES
(1, 'admin', '$admin_pass', 'Store Admin / مالک', 'admin', '03001234567', 'active'),
(2, 'cashier', '$cashier_pass', 'Muhammad Ali (Cashier)', 'cashier', '03217654321', 'active')");
echo "<div>Users seeded (admin/admin123, cashier/123456)!</div>\n";

// --- Seed Dynamic Units of Measurement with conversions ---
// 1 KG is base unit. Gram is 0.001 KG. Carton is 24 Pieces. Dozen is 12 Pieces.
$conn->query("INSERT IGNORE INTO units (id, name_en, name_ur, symbol, base_unit_id, conversion_factor, allow_decimal) VALUES
(1, 'Kilogram', 'کلو گرام', 'KG', NULL, 1.0000, 1),
(2, 'Gram', 'گرام', 'G', 1, 0.0010, 1),
(3, 'Liter', 'لیٹر', 'L', NULL, 1.0000, 1),
(4, 'Milliliter', 'ملی لیٹر', 'ML', 3, 0.0010, 1),
(5, 'Piece', 'عدد', 'Pc', NULL, 1.0000, 0),
(6, 'Packet', 'پیکٹ', 'Pkt', NULL, 1.0000, 0),
(7, 'Carton', 'کاٹن (24 عدد)', 'Ctn', 5, 24.0000, 0),
(8, 'Dozen', 'درجن (12 عدد)', 'Dzn', 5, 12.0000, 0),
(9, 'Bag / Bori', 'بوری (50 کلو)', 'Bag', 1, 50.0000, 1),
(10, 'Box', 'ڈبہ', 'Box', NULL, 1.0000, 0),
(11, 'Bottle', 'بوتل', 'Btl', NULL, 1.0000, 0)");
echo "<div>Dynamic Units seeded!</div>\n";

// --- Seed Categories ---
$conn->query("INSERT IGNORE INTO categories (id, name_en, name_ur, parent_id) VALUES
(1, 'Flour & Atta', 'آٹا و میدہ', 0),
(2, 'Rice & Pulses (Daalain)', 'چاول اور دالیں', 0),
(3, 'Sugar & Sweeteners', 'چینی و میٹھا', 0),
(4, 'Cooking Oil & Ghee', 'کوکنگ آئل و گھی', 0),
(5, 'Tea & Beverages', 'چائے و مشروبات', 0),
(6, 'Spices & Masala', 'مصالحہ جات', 0),
(7, 'Biscuits & Snacks', 'بسکٹ و سنیکس', 0),
(8, 'Dairy & Eggs', 'دودھ و ڈیری', 0),
(9, 'Soaps & Detergents', 'صابن اور سرف', 0),
(10, 'Personal Care', 'ذاتی نگہداشت', 0)");
echo "<div>Categories seeded!</div>\n";

// --- Seed Brands ---
$conn->query("INSERT IGNORE INTO brands (id, name_en, name_ur) VALUES
(1, 'Dalda', 'ڈالڈا'),
(2, 'Mezan', 'میزان'),
(3, 'Tapal', 'ٹپال'),
(4, 'National Foods', 'نیشنل فوڈز'),
(5, 'Shan', 'شان'),
(6, 'Olper\'s', 'اولپرز'),
(7, 'Surf Excel', 'سرف ایکسل'),
(8, 'Lux', 'لکس'),
(9, 'Peek Freans', 'پیک فرینز'),
(10, 'General / Open', 'کھلا مال')");
echo "<div>Brands seeded!</div>\n";

// --- Seed Financial Accounts ---
$conn->query("INSERT IGNORE INTO accounts (id, account_name, account_type, account_number, bank_name, current_balance) VALUES
(1, 'Cash in Hand (دکان کا کیش)', 'cash', 'CASH-01', 'Shop Safe', 50000.00),
(2, 'Meezan Bank Current A/C', 'bank', '02010103456789', 'Meezan Bank Ltd', 150000.00),
(3, 'JazzCash Business Wallet', 'wallet_jazzcash', '03001234567', 'JazzCash', 25000.00),
(4, 'Easypaisa Merchant Wallet', 'wallet_easypaisa', '03217654321', 'Easypaisa', 15000.00)");
echo "<div>Accounts seeded!</div>\n";

// --- Seed Expense Categories ---
$conn->query("INSERT IGNORE INTO expense_categories (id, name_en, name_ur) VALUES
(1, 'Shop Rent', 'دکان کا کرایہ'),
(2, 'Electricity Bill', 'بجلی کا بل'),
(3, 'Staff Salaries', 'ملازمین کی تنخواہ'),
(4, 'Freight & Loading', 'کرایہ مال و لوڈنگ'),
(5, 'Tea & Refreshments', 'چائے و پانی'),
(6, 'Packaging Bags (Shoppers)', 'شاپر و پیکنگ میٹریل'),
(7, 'Repairs & Maintenance', 'مرمت و دیکھ بھال'),
(8, 'Miscellaneous', 'متفرق اخراجات')");
echo "<div>Expense categories seeded!</div>\n";

// --- Seed Suppliers ---
$conn->query("INSERT IGNORE INTO suppliers (id, name, company_name, phone, address, opening_balance, current_balance, credit_limit) VALUES
(1, 'Haji Abdul Rehman', 'Rehman Sugar & Flour Agency', '03009876543', 'Akbari Mandi, Lahore', 0.00, 15000.00, 100000.00),
(2, 'Tariq Mehmood', 'Mezan & Dalda Ghee Distributors', '03214567890', 'Circular Road, Lahore', 0.00, 25000.00, 200000.00),
(3, 'Sheikh Zahid', 'National & Shan Spices Wholesale', '03335551234', 'Shah Alam Market, Lahore', 0.00, 8000.00, 50000.00)");
echo "<div>Suppliers seeded!</div>\n";

// --- Seed Customers with Khata ---
$conn->query("INSERT IGNORE INTO customers (id, name, phone, address, customer_type, opening_balance, current_balance, credit_limit) VALUES
(1, 'Chaudhry Aslam (محلہ دار)', '03011122334', 'House # 45, Street 2', 'retail', 0.00, 3500.00, 10000.00),
(2, 'Master Tariq School Teacher', '03224455667', 'Near Govt High School', 'retail', 0.00, 1850.00, 8000.00),
(3, 'Malik Hotel & Tea Stall', '03456677889', 'Main Chowk', 'wholesale', 0.00, 12400.00, 30000.00)");
echo "<div>Customers seeded!</div>\n";

// --- Seed Sample Kiryana Products with realistic weights, barcodes, and rates ---
$products = [
    // code, barcode, name_en, name_ur, cat_id, brand_id, unit_id, cost, retail, wholesale, min_price, stock, alert
    ['PRD-001', '89640001001', 'Sugar (White Chini)', 'چینی سفید', 3, 10, 1, 140.00, 150.00, 146.00, 145.00, 250.000, 30.000],
    ['PRD-002', '89640001002', 'Basmati Rice Super Karnal', 'سپر کرنل باسمتی چاول', 2, 10, 1, 310.00, 350.00, 335.00, 330.00, 180.000, 25.000],
    ['PRD-003', '89640001003', 'Chakki Atta (Whole Wheat)', 'چکی کا خالص آٹا', 1, 10, 1, 115.00, 130.00, 124.00, 122.00, 400.000, 50.000],
    ['PRD-004', '89640001004', 'Dalda Cooking Oil 1 Liter Pouch', 'ڈالڈا کوکنگ آئل 1 لیٹر پاؤچ', 4, 1, 3, 510.00, 550.00, 535.00, 530.00, 60.000, 12.000],
    ['PRD-005', '89640001005', 'Dalda Banaspati Ghee 1 KG', 'ڈالڈا بناسپتی گھی 1 کلو', 4, 1, 1, 500.00, 540.00, 525.00, 520.00, 75.000, 15.000],
    ['PRD-006', '89640001006', 'Tapal Danedar Tea 430g Packet', 'ٹپال دانے دار چائے 430 گرام', 5, 3, 6, 620.00, 680.00, 660.00, 650.00, 45.000, 10.000],
    ['PRD-007', '89640001007', 'Daal Chana Special', 'دال چنا اسپیشل', 2, 10, 1, 230.00, 260.00, 245.00, 240.00, 95.000, 20.000],
    ['PRD-008', '89640001008', 'Daal Moong Dhooli', 'دال مونگ دھلی ہوئی', 2, 10, 1, 280.00, 320.00, 305.00, 300.00, 70.000, 15.000],
    ['PRD-009', '89640001009', 'National Lal Mirch Powder 200g', 'نیشنل لال مرچ پاؤڈر 200 گرام', 6, 4, 6, 210.00, 240.00, 230.00, 225.00, 50.000, 10.000],
    ['PRD-010', '89640001010', 'National Haldi Powder 200g', 'نیشنل ہلدی پاؤڈر 200 گرام', 6, 4, 6, 170.00, 200.00, 190.00, 185.00, 40.000, 10.000],
    ['PRD-011', '89640001011', 'Olper\'s Milk 1 Liter Tetra Pak', 'اولپرز دودھ 1 لیٹر ٹیٹرا پیک', 8, 6, 11, 260.00, 280.00, 272.00, 270.00, 120.000, 24.000],
    ['PRD-012', '89640001012', 'Surf Excel 1 KG Washing Powder', 'سرف ایکسل 1 کلو واشنگ پاؤڈر', 9, 7, 6, 480.00, 530.00, 510.00, 500.00, 35.000, 8.000],
    ['PRD-013', '89640001013', 'Lux Beauty Soap 140g', 'لکس صابن 140 گرام', 10, 8, 5, 120.00, 140.00, 132.00, 130.00, 90.000, 15.000],
    ['PRD-014', '89640001014', 'Peek Freans Sooper Biscuit Family Pack', 'سوپر بسکٹ فیملی پیک', 7, 9, 6, 115.00, 130.00, 123.00, 120.00, 80.000, 15.000],
    ['PRD-015', '89640001015', 'Rooh Afza 800ml Bottle', 'روح افزا بوتل 800 ملی لیٹر', 5, 10, 11, 390.00, 430.00, 415.00, 410.00, 55.000, 10.000],
];

foreach ($products as $p) {
    $code = $p[0];
    $barcode = $p[1];
    $name_en = db_escape($p[2]);
    $name_ur = db_escape($p[3]);
    $cat_id = (int)$p[4];
    $brand_id = (int)$p[5];
    $unit_id = (int)$p[6];
    $cost = $p[7];
    $retail = $p[8];
    $wholesale = $p[9];
    $min_price = $p[10];
    $stock = $p[11];
    $alert = $p[12];

    $check = db_fetch_one("SELECT id FROM products WHERE code = '$code'");
    if (!$check) {
        $conn->query("INSERT INTO products (code, barcode, name_en, name_ur, category_id, brand_id, unit_id, purchase_price, retail_price, wholesale_price, min_sale_price, current_stock, alert_quantity) 
                      VALUES ('$code', '$barcode', '$name_en', '$name_ur', $cat_id, $brand_id, $unit_id, $cost, $retail, $wholesale, $min_price, $stock, $alert)");
        $pid = $conn->insert_id;

        // Add opening stock ledger
        $conn->query("INSERT INTO stock_ledger (product_id, transaction_type, reference_no, qty_in, balance_after, cost_price, notes) 
                      VALUES ($pid, 'opening', 'INIT-STOCK', $stock, $stock, $cost, 'Opening stock initialization')");
    }
}
echo "<div>15 Common Pakistani Kiryana products seeded with barcodes & stock!</div>\n";

// Add sample initial customer ledger entries
$c1 = db_fetch_one("SELECT id, current_balance FROM customers WHERE id = 1");
if ($c1 && !db_fetch_one("SELECT id FROM customer_ledger WHERE customer_id = 1")) {
    $conn->query("INSERT INTO customer_ledger (customer_id, transaction_type, reference_no, debit, credit, balance_after, notes) 
                  VALUES (1, 'opening', 'OPENING-01', 3500.00, 0.00, 3500.00, 'Initial Khata balance')");
}
$c2 = db_fetch_one("SELECT id, current_balance FROM customers WHERE id = 2");
if ($c2 && !db_fetch_one("SELECT id FROM customer_ledger WHERE customer_id = 2")) {
    $conn->query("INSERT INTO customer_ledger (customer_id, transaction_type, reference_no, debit, credit, balance_after, notes) 
                  VALUES (2, 'opening', 'OPENING-02', 1850.00, 0.00, 1850.00, 'Initial Khata balance')");
}

echo "<h4 style='color:green;'>Setup Completed Successfully! System is Ready.</h4>";
echo "<p><a href='index.php'>Go to Dashboard</a> | <a href='login.php'>Go to Login</a> | <a href='pos.php'>Go to POS Billing</a></p>";
