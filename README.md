# Kiryana Store Management System | کریانہ اسٹور مینجمنٹ سسٹم
### Complete Retail & Wholesale Grocery ERP for Pakistan

Built with **Core PHP (Procedural/Non-MVC), MySQL, HTML5, CSS3, JavaScript, AJAX**.
Fully bilingual (**English & اردو**) with complete Right-to-Left (RTL) support and thermal receipt printing.

---

## 🚀 Key Highlights & Architectural Guarantees

1. **Zero Hardcoded Parameters (100% Dynamic in Realtime)**:
   - **Units of Measurement**: Add or modify units on the fly (KG, Gram with 0.001 factor, Liter, ML, Carton of 24, Dozen of 12, Maund of 40 KG, Bori/Bag of 50 KG, Box, Piece, etc.).
   - **Financial Accounts & Digital Wallets**: Add any Cash drawer, Bank Account (Meezan, HBL, UBL, etc.), JazzCash Business Wallet, or Easypaisa Merchant Wallet in real-time.
   - **Categories & Subcategories**: Real-time management.
   - **Brands**: Real-time management.
   - **Expense Categories**: Real-time management.

2. **Lightning-Fast POS & Fast Billing**:
   - Barcode scanner auto-detect and direct cart entry.
   - Live dual search (English & Urdu).
   - Decimal quantity support (e.g., 0.250 KG sugar, 1.500 L oil).
   - Quick Kiryana frequent items grid.
   - Pricing Tier Toggle: [ پرچون (Retail) | تھوک (Wholesale) ].
   - Split Payments (e.g. Cash + JazzCash + Khata Credit).
   - Instant Change Return calculation (e.g. Tendered Rs. 2,000 for Rs. 1,530 bill = Rs. 470 Change).
   - Hold & Resume bills (F8 / Modal).
   - Hotkeys:
     - `F2`: Focus Barcode & Product Search
     - `F4`: Complete Checkout & Pay
     - `F8`: Hold Bill
     - `F9`: Clear / New Bill
     - `ESC`: Close Modals

3. **Accurate Khata System (گاہک اور سپلائر کھاتہ)**:
   - Ledger entries are computed exclusively from audited debit/credit transactions, never manually overwritten.
   - Credit limit warnings & balances tracking.
   - Printable Customer & Supplier account statements.

4. **Accurate Gross & Net Profit Calculation**:
   - $\text{Net Sales} = \text{Gross Sales} - \text{Discounts} - \text{Sales Returns}$
   - $\text{Cost of Goods Sold (COGS)} = \sum(\text{Sold Qty} \times \text{Purchase Cost}) - \sum(\text{Returned Qty} \times \text{Purchase Cost})$
   - $\text{Gross Profit} = \text{Net Sales} - \text{COGS}$
   - $\text{Net Operating Profit} = \text{Gross Profit} - \text{Operating Expenses}$

5. **Cash Register & Shift Management**:
   - Opening cash balance tracking.
   - Real-time tally of cash sales, recoveries, supplier payments, and expenses.
   - Shift closing reconciliation with physical counted cash and Shortage / Excess reporting.

6. **Receipt & Label Printing**:
   - 80 mm Thermal Roll
   - 58 mm Thermal Roll
   - A4 Invoice format
   - Barcode sticker & shelf tag generator

---

## 🔑 Default Credentials

- **URL**: `http://localhost/Kiryana`
- **Store Admin (مالک)**:
  - Username: `admin`
  - Password: `admin123`
- **Cashier (کیشیئر)**:
  - Username: `cashier`
  - Password: `123456`

---

## 📁 System Modules Map

| Page | Module | Description |
|---|---|---|
| [`index.php`](file:///c:/wamp64/www/Kiryana/index.php) | **Dashboard** | Business overview, real-time KPIs, 7-day sales chart, low stock alerts |
| [`pos.php`](file:///c:/wamp64/www/Kiryana/pos.php) | **POS Fast Billing** | High-speed billing with barcode scanner, decimal units, hotkeys |
| [`products.php`](file:///c:/wamp64/www/Kiryana/products.php) | **Products Catalog** | Urdu & English product list, pricing, stock levels |
| [`product_form.php`](file:///c:/wamp64/www/Kiryana/product_form.php) | **Add / Edit Product** | Product creation with auto-barcode generator, units, prices |
| [`inventory.php`](file:///c:/wamp64/www/Kiryana/inventory.php) | **Inventory & Stock** | Valuation at cost & retail, stock movement ledger |
| [`stock_adjust.php`](file:///c:/wamp64/www/Kiryana/stock_adjust.php) | **Stock Adjustments** | Damaged goods, expired stock, physical count audits |
| [`units.php`](file:///c:/wamp64/www/Kiryana/units.php) | **Dynamic Units** | Base units and conversion factors (KG, G, Carton, Dozen) |
| [`categories.php`](file:///c:/wamp64/www/Kiryana/categories.php) | **Categories & Brands** | Real-time categories, subcategories, and brand manager |
| [`purchases.php`](file:///c:/wamp64/www/Kiryana/purchases.php) | **Purchases History** | Supplier purchase invoices and item tracking |
| [`purchase_create.php`](file:///c:/wamp64/www/Kiryana/purchase_create.php) | **New Purchase** | Inbound inventory, supplier payable ledger updates |
| [`suppliers.php`](file:///c:/wamp64/www/Kiryana/suppliers.php) | **Suppliers** | Wholesaler directory and payables summary |
| [`supplier_ledger.php`](file:///c:/wamp64/www/Kiryana/supplier_ledger.php) | **Supplier Khata** | Supplier transaction statement and payment logging |
| [`customers.php`](file:///c:/wamp64/www/Kiryana/customers.php) | **Customers Khata** | Retail & wholesale customer credit accounts |
| [`customer_ledger.php`](file:///c:/wamp64/www/Kiryana/customer_ledger.php) | **Customer Khata** | Detailed transaction statement, payment recoveries |
| [`sales.php`](file:///c:/wamp64/www/Kiryana/sales.php) | **Sales Invoices** | All invoices history, reprint, status filter |
| [`returns.php`](file:///c:/wamp64/www/Kiryana/returns.php) | **Sales Returns** | Return invoice items, restock inventory, cash/khata refund |
| [`expenses.php`](file:///c:/wamp64/www/Kiryana/expenses.php) | **Expenses** | Shop rent, electricity, salaries, tea, packaging |
| [`shifts.php`](file:///c:/wamp64/www/Kiryana/shifts.php) | **Cash Register** | Open shift, counted cash closing, shortage/excess |
| [`barcodes.php`](file:///c:/wamp64/www/Kiryana/barcodes.php) | **Barcode Labels** | Printable stickers & shelf tags with barcode renderer |
| [`accounts.php`](file:///c:/wamp64/www/Kiryana/accounts.php) | **Financial Accounts** | Cash in Hand, Bank, JazzCash, Easypaisa, internal transfers |
| [`reports_profit.php`](file:///c:/wamp64/www/Kiryana/reports_profit.php) | **Profit & Loss** | Net Sales, COGS, Gross Profit, Expenses, Net Profit |
| [`reports_sales.php`](file:///c:/wamp64/www/Kiryana/reports_sales.php) | **Sales Analytics** | Trends, cashier performance, category breakdown |
| [`users.php`](file:///c:/wamp64/www/Kiryana/users.php) | **Users & Roles** | Role-based permissions, cashier & admin management |
| [`settings.php`](file:///c:/wamp64/www/Kiryana/settings.php) | **Store Settings** | Store name (Urdu/Eng), receipt size (58/80mm), currency |
| [`backup.php`](file:///c:/wamp64/www/Kiryana/backup.php) | **Database Backup** | One-click SQL backup download and maintenance |
| [`print_invoice.php`](file:///c:/wamp64/www/Kiryana/print_invoice.php) | **Receipt Print** | Thermal 80mm, 58mm, and A4 print view |
