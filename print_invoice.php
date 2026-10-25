<?php
// print_invoice.php - Thermal Receipt & A4 Invoice Printing
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();

$sale_id = (int)($_GET['id'] ?? 0);
$auto_print = isset($_GET['auto_print']);
$format = $_GET['format'] ?? get_setting('receipt_printer_type', '80mm'); // 80mm, 58mm, a4

$sale = db_fetch_one("SELECT s.*, u.full_name AS cashier_name, c.name AS cust_name, c.phone AS cust_phone, c.current_balance AS cust_current_balance 
    FROM sales s 
    LEFT JOIN users u ON s.created_by = u.id 
    LEFT JOIN customers c ON s.customer_id = c.id 
    WHERE s.id = $sale_id");

if (!$sale) {
    die("Invoice not found! بل نہیں ملا۔");
}

$items = db_fetch_all("SELECT * FROM sale_items WHERE sale_id = $sale_id");

$store_name = get_setting('store_name_ur', 'المدینہ کریانہ اسٹور') . '<br><small>' . get_setting('store_name_en', 'Al-Madina Kiryana Store') . '</small>';
$store_phone = get_setting('phone', '0300-1234567');
$store_address = get_setting('address_ur', 'دکان نمبر 12، مین بازار، پاکستان');
$footer_note = get_setting('receipt_footer', 'تبدیلی یا واپسی 3 دن کے اندر بل کے ساتھ ممکن ہے۔ شکریہ!');
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $sale['invoice_no']; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', 'Courier New', Courier, monospace, Tahoma, Arial;
            line-height: 1.6;
        }
        body {
            background-color: #f1f5f9;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .no-print {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #166534;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-outline {
            background: white;
            color: #166534;
            border: 1px solid #166534;
        }
        .receipt-container {
            background: white;
            padding: 15px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        /* 80mm roll */
        .width-80mm {
            width: 78mm;
            font-size: 12px;
        }
        /* 58mm roll */
        .width-58mm {
            width: 56mm;
            font-size: 10.5px;
        }
        /* A4 invoice */
        .width-a4 {
            width: 210mm;
            min-height: 297mm;
            padding: 40px;
            font-size: 14px;
        }

        .text-center { text-align: center; }
        .text-start { text-align: right; }
        .text-end { text-align: left; }
        .fw-bold { font-weight: bold; }
        
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .store-title {
            font-size: 1.25em;
            font-weight: bold;
            line-height: 1.3;
        }
        .meta-table, .items-table, .total-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .items-table th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 2px;
            text-align: right;
        }
        .items-table td {
            padding: 4px 2px;
            vertical-align: top;
        }
        .total-table td {
            padding: 2px 0;
        }
        .grand-row {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            font-size: 1.15em;
            font-weight: bold;
        }
        .footer {
            border-top: 1px dashed #000;
            padding-top: 8px;
            text-align: center;
            font-size: 0.9em;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn">🖨️ پرنٹ کریں (Print)</button>
    <a href="?id=<?php echo $sale_id; ?>&format=80mm" class="btn <?php echo $format == '80mm' ? '' : 'btn-outline'; ?>">80mm تھرمل</a>
    <a href="?id=<?php echo $sale_id; ?>&format=58mm" class="btn <?php echo $format == '58mm' ? '' : 'btn-outline'; ?>">58mm تھرمل</a>
    <a href="?id=<?php echo $sale_id; ?>&format=a4" class="btn <?php echo $format == 'a4' ? '' : 'btn-outline'; ?>">A4 انوائس</a>
    <button onclick="window.close()" class="btn btn-outline">بند کریں (Close)</button>
</div>

<div class="receipt-container width-<?php echo $format; ?>" id="receiptArea">
    <!-- Header -->
    <div class="header">
        <div class="store-title"><?php echo $store_name; ?></div>
        <div><?php echo htmlspecialchars($store_address); ?></div>
        <div>فون: <?php echo htmlspecialchars($store_phone); ?></div>
    </div>

    <!-- Invoice Meta -->
    <table class="meta-table">
        <tr>
            <td class="text-start">بل نمبر: <strong><?php echo $sale['invoice_no']; ?></strong></td>
            <td class="text-end"><?php echo date('d-m-Y h:i A', strtotime($sale['created_at'])); ?></td>
        </tr>
        <tr>
            <td class="text-start">گاہک: <strong><?php echo htmlspecialchars($sale['customer_name'] ?: 'عام خریدار'); ?></strong></td>
            <td class="text-end">کیشیئر: <?php echo htmlspecialchars($sale['cashier_name'] ?? 'Admin'); ?></td>
        </tr>
        <?php if (!empty($sale['cust_phone'])): ?>
        <tr>
            <td colspan="2" class="text-start">فون: <?php echo htmlspecialchars($sale['cust_phone']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">آئٹم</th>
                <th class="text-center" style="width: 15%;">تعداد</th>
                <th class="text-center" style="width: 20%;">ریٹ</th>
                <th class="text-end" style="width: 20%;">رقم</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?php echo htmlspecialchars($it['product_name']); ?></div>
                    </td>
                    <td class="text-center"><?php echo number_format($it['quantity'], 2); ?> <?php echo $it['unit_name']; ?></td>
                    <td class="text-center"><?php echo number_format($it['unit_price'], 2); ?></td>
                    <td class="text-end"><?php echo number_format($it['subtotal'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals Table -->
    <table class="total-table">
        <tr>
            <td>میزان (Subtotal):</td>
            <td class="text-end">Rs. <?php echo number_format($sale['subtotal'], 2); ?></td>
        </tr>
        <?php if ($sale['discount_amount'] > 0): ?>
        <tr>
            <td>رعایت (Discount):</td>
            <td class="text-end">- Rs. <?php echo number_format($sale['discount_amount'], 2); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="grand-row">
            <td><strong>کل رقم (Grand Total):</strong></td>
            <td class="text-end"><strong>Rs. <?php echo number_format($sale['grand_total'], 2); ?></strong></td>
        </tr>
        <tr>
            <td>وصول شدہ (Paid Cash):</td>
            <td class="text-end">Rs. <?php echo number_format($sale['paid_amount'], 2); ?></td>
        </tr>
        <?php if ($sale['change_amount'] > 0): ?>
        <tr>
            <td>واپسی بقایا (Change):</td>
            <td class="text-end fw-bold">Rs. <?php echo number_format($sale['change_amount'], 2); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($sale['balance_amount'] > 0): ?>
        <tr>
            <td style="color: red;">ادھار کھاتہ (Balance Due):</td>
            <td class="text-end fw-bold" style="color: red;">Rs. <?php echo number_format($sale['balance_amount'], 2); ?></td>
        </tr>
        <?php if ($sale['customer_id'] > 0): ?>
        <tr>
            <td>موجودہ کل کھاتہ بقایا:</td>
            <td class="text-end fw-bold">Rs. <?php echo number_format($sale['cust_current_balance'], 2); ?></td>
        </tr>
        <?php endif; ?>
        <?php endif; ?>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p><?php echo htmlspecialchars($footer_note); ?></p>
        <p style="font-size: 0.8em; margin-top: 4px;">Software by Kiryana Store Management System</p>
    </div>
</div>

<?php if ($auto_print): ?>
<script>
window.onload = function() {
    window.print();
};
</script>
<?php endif; ?>

</body>
</html>

