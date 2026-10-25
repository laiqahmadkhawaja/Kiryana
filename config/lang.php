<?php
// config/lang.php - Bilingual Language Manager (English & Urdu)

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ur'; // Default to Urdu for Kiryana store, or can be 'en'
}

// Allow changing language via GET param
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ur'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

function get_current_lang() {
    return $_SESSION['lang'] ?? 'ur';
}

function is_rtl() {
    return get_current_lang() === 'ur';
}

$translations = [
    // General
    'store_title' => ['en' => 'Kiryana Store Management', 'ur' => 'کریانہ اسٹور مینجمنٹ سسٹم'],
    'dashboard' => ['en' => 'Dashboard', 'ur' => 'ڈیش بورڈ'],
    'pos' => ['en' => 'Point of Sale (POS)', 'ur' => 'پوائنٹ آف سیل (بلنگ)'],
    'products' => ['en' => 'Products', 'ur' => 'مصنوعات / اشیاء'],
    'inventory' => ['en' => 'Inventory & Stock', 'ur' => 'اسٹاک و انوینٹری'],
    'categories' => ['en' => 'Categories', 'ur' => 'کیٹیگریز'],
    'units' => ['en' => 'Units of Measure', 'ur' => 'پیمائش کی اکائیاں'],
    'brands' => ['en' => 'Brands', 'ur' => 'برانڈز'],
    'purchases' => ['en' => 'Purchases', 'ur' => 'خریداری (پرچیز)'],
    'suppliers' => ['en' => 'Suppliers', 'ur' => 'سپلائرز'],
    'customers' => ['en' => 'Customers (Khata)', 'ur' => 'گاہک کھاتہ'],
    'expenses' => ['en' => 'Expenses', 'ur' => 'اخراجات'],
    'shifts' => ['en' => 'Cash Register & Shifts', 'ur' => 'کیش رجسٹر و شفٹ'],
    'returns' => ['en' => 'Returns & Damage', 'ur' => 'واپسی و نقصان'],
    'barcodes' => ['en' => 'Barcode Labels', 'ur' => 'بارکوڈ لیبلز'],
    'accounts' => ['en' => 'Financial Accounts', 'ur' => 'مالیاتی اکاؤنٹس / بینک'],
    'reports' => ['en' => 'Reports & Analytics', 'ur' => 'رپورٹس و تجزیات'],
    'settings' => ['en' => 'Settings', 'ur' => 'ترتیبات (سیٹنگز)'],
    'users' => ['en' => 'Users & Roles', 'ur' => 'صارفین اور اختیارات'],
    'logout' => ['en' => 'Logout', 'ur' => 'لاگ آؤٹ'],
    'login' => ['en' => 'Login', 'ur' => 'لاگ ان'],
    'backup' => ['en' => 'Database Backup', 'ur' => 'ڈیٹا بیک اپ'],

    // Actions
    'save' => ['en' => 'Save', 'ur' => 'محفوظ کریں'],
    'cancel' => ['en' => 'Cancel', 'ur' => 'منسوخ'],
    'delete' => ['en' => 'Delete', 'ur' => 'حذف کریں'],
    'edit' => ['en' => 'Edit', 'ur' => 'تبدیل کریں'],
    'view' => ['en' => 'View', 'ur' => 'دیکھیں'],
    'print' => ['en' => 'Print', 'ur' => 'پرنٹ کریں'],
    'add_new' => ['en' => 'Add New', 'ur' => 'نیا شامل کریں'],
    'search' => ['en' => 'Search...', 'ur' => 'تلاش کریں...'],
    'filter' => ['en' => 'Filter', 'ur' => 'فلٹر'],
    'reset' => ['en' => 'Reset', 'ur' => 'ری سیٹ'],
    'export' => ['en' => 'Export', 'ur' => 'ایکسپورٹ'],
    'import' => ['en' => 'Import', 'ur' => 'امپورٹ'],
    'actions' => ['en' => 'Actions', 'ur' => 'کارروائی'],
    'status' => ['en' => 'Status', 'ur' => 'حالت'],
    'active' => ['en' => 'Active', 'ur' => 'فعال'],
    'inactive' => ['en' => 'Inactive', 'ur' => 'غیر فعال'],

    // POS
    'fast_billing' => ['en' => 'Fast Billing', 'ur' => 'تیز رفتار بلنگ'],
    'search_product' => ['en' => 'Search product by name, barcode, code (F2)', 'ur' => 'نام یا بارکوڈ سے تلاش کریں (F2)'],
    'quick_items' => ['en' => 'Quick Items', 'ur' => 'عام فروخت ہونے والی اشیاء'],
    'cart' => ['en' => 'Current Bill', 'ur' => 'موجودہ بل'],
    'item' => ['en' => 'Item', 'ur' => 'چیز'],
    'price' => ['en' => 'Price', 'ur' => 'قیمت'],
    'qty' => ['en' => 'Qty', 'ur' => 'مقدار'],
    'unit' => ['en' => 'Unit', 'ur' => 'اکائی'],
    'discount' => ['en' => 'Discount', 'ur' => 'رعایت'],
    'subtotal' => ['en' => 'Subtotal', 'ur' => 'میزان'],
    'tax' => ['en' => 'Tax', 'ur' => 'ٹیکس'],
    'grand_total' => ['en' => 'Grand Total', 'ur' => 'کل رقم'],
    'paid' => ['en' => 'Paid Amount', 'ur' => 'وصول شدہ رقم'],
    'change' => ['en' => 'Change Return', 'ur' => 'واپسی بقایا'],
    'customer' => ['en' => 'Customer', 'ur' => 'گاہک'],
    'walk_in_customer' => ['en' => 'Walk-in Customer (Cash)', 'ur' => 'عام خریدار (کیش)'],
    'payment_method' => ['en' => 'Payment Method', 'ur' => 'طریقہ ادائیگی'],
    'pay_checkout' => ['en' => 'Complete Sale (F4)', 'ur' => 'بل مکمل کریں (F4)'],
    'hold_bill' => ['en' => 'Hold Bill (F8)', 'ur' => 'بل روکو (Hold)'],
    'new_bill' => ['en' => 'New Bill (F9)', 'ur' => 'نیا بل (F9)'],
    'held_bills' => ['en' => 'Held Bills', 'ur' => 'روکے گئے بل'],
    'invoice_no' => ['en' => 'Invoice #', 'ur' => 'انوائس نمبر'],
    'date' => ['en' => 'Date', 'ur' => 'تاریخ'],
    'retail' => ['en' => 'Retail', 'ur' => 'پرچون'],
    'wholesale' => ['en' => 'Wholesale', 'ur' => 'تھوک'],
    'price_type' => ['en' => 'Pricing Tier', 'ur' => 'قیمت کا درجہ'],

    // KPIs
    'today_sales' => ['en' => "Today's Sales", 'ur' => 'آج کی فروخت'],
    'today_purchases' => ['en' => "Today's Purchases", 'ur' => 'آج کی خریداری'],
    'gross_profit' => ['en' => 'Gross Profit', 'ur' => 'مجموعی منافع'],
    'net_profit' => ['en' => 'Net Profit', 'ur' => 'خالص منافع'],
    'cash_in_hand' => ['en' => 'Cash in Hand', 'ur' => 'موجود کیش'],
    'bank_balance' => ['en' => 'Bank / Wallets', 'ur' => 'بینک اور والٹس'],
    'receivables' => ['en' => 'Customer Outstanding', 'ur' => 'گاہکوں کا بقایا'],
    'payables' => ['en' => 'Supplier Payables', 'ur' => 'سپلائرز کے واجبات'],
    'stock_value' => ['en' => 'Total Stock Value', 'ur' => 'اسٹاک کی کل مالیت'],
    'low_stock' => ['en' => 'Low Stock Items', 'ur' => 'کم اسٹاک کی اشیاء'],
    'expired_items' => ['en' => 'Near Expiry', 'ur' => 'ختم ہونے والی معیاد'],
    'expenses_today' => ['en' => "Today's Expenses", 'ur' => 'آج کے اخراجات'],

    // Customer & Supplier Khata
    'customer_khata' => ['en' => 'Customer Khata (Credit)', 'ur' => 'گاہک کھاتہ'],
    'supplier_khata' => ['en' => 'Supplier Khata', 'ur' => 'سپلائر کھاتہ'],
    'debit' => ['en' => 'Debit (Sale/Bill)', 'ur' => 'بنام (ڈیبٹ)'],
    'credit' => ['en' => 'Credit (Payment)', 'ur' => 'جمع (کریڈٹ)'],
    'balance' => ['en' => 'Balance', 'ur' => 'بقایا رقم'],
    'credit_limit' => ['en' => 'Credit Limit', 'ur' => 'ادھار کی حد'],
    'receive_payment' => ['en' => 'Receive Payment', 'ur' => 'ادائیگی وصول کریں'],
    'make_payment' => ['en' => 'Make Payment', 'ur' => 'رقم ادا کریں'],
    'statement' => ['en' => 'Ledger Statement', 'ur' => 'کھاتہ کی تفصیل (اسٹیٹمنٹ)'],

    // Inventory & Units
    'stock' => ['en' => 'Current Stock', 'ur' => 'موجود اسٹاک'],
    'purchase_price' => ['en' => 'Purchase Price', 'ur' => 'قیمتِ خرید'],
    'retail_price' => ['en' => 'Retail Sale Price', 'ur' => 'قیمتِ فروخت (پرچون)'],
    'wholesale_price' => ['en' => 'Wholesale Price', 'ur' => 'تھوک قیمت'],
    'min_stock_alert' => ['en' => 'Low Stock Alert Level', 'ur' => 'کم اسٹاک الرٹ کی حد'],
    'adjust_stock' => ['en' => 'Stock Adjustment', 'ur' => 'اسٹاک ایڈجسٹمنٹ'],
    'damage_stock' => ['en' => 'Damaged Goods', 'ur' => 'خراب / ضائع شدہ مال'],
    'unit_conversion' => ['en' => 'Unit Multiplier', 'ur' => 'اکائی کا تناسب'],

    // Shifts
    'open_shift' => ['en' => 'Open Shift', 'ur' => 'شفٹ شروع کریں'],
    'close_shift' => ['en' => 'Close Shift', 'ur' => 'شفٹ بند کریں'],
    'expected_cash' => ['en' => 'Expected Cash', 'ur' => 'متوقع کیش'],
    'actual_cash' => ['en' => 'Actual Counted Cash', 'ur' => 'گنتی کیا گیا کیش'],
    'cash_difference' => ['en' => 'Difference (Short/Excess)', 'ur' => 'فرق (کمی یا بیشی)'],
];

function __($key) {
    global $translations;
    $lang = get_current_lang();
    if (isset($translations[$key][$lang])) {
        return $translations[$key][$lang];
    }
    if (isset($translations[$key]['en'])) {
        return $translations[$key]['en'];
    }
    return ucwords(str_replace('_', ' ', $key));
}
