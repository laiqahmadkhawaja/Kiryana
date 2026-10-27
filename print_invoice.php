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
    die("Invoice not found! Ø¨Ù„ Ù†ÛÛŒÚº Ù…Ù„Ø§Û”");
}

$items = db_fetch_all("SELECT * FROM sale_items WHERE sale_id = $sale_id");

$store_name = get_setting('store_name_ur', 'Ø§Ù„Ù…Ø¯ÛŒÙ†Û Ú©Ø±ÛŒØ§Ù†Û Ø§Ø³Ù¹ÙˆØ±') . '<br><small>' . get_setting('store_name_en', 'Al-Madina Kiryana Store') . '</small>';
$store_phone = get_setting('phone', '0300-1234567');
$store_address = get_setting('address_ur', 'Ø¯Ú©Ø§Ù† Ù†Ù…Ø¨Ø± 12ØŒ Ù…ÛŒÙ† Ø¨Ø§Ø²Ø§Ø±ØŒ Ù¾Ø§Ú©Ø³ØªØ§Ù†');
$footer_note = get_setting('receipt_footer', 'ØªØ¨Ø¯ÛŒÙ„ÛŒ ÛŒØ§ ÙˆØ§Ù¾Ø³ÛŒ 3 Ø¯Ù† Ú©Û’ Ø§Ù†Ø¯Ø± Ø¨Ù„ Ú©Û’ Ø³Ø§ØªÚ¾ Ù…Ù…Ú©Ù† ÛÛ’Û” Ø´Ú©Ø±ÛŒÛ!');
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
            html, body {
                background: #ffffff !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
                width: 100% !important;
                height: auto !important;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none !important;
                padding: 2px !important;
                margin: 0 auto !important;
                display: block !important;
                visibility: visible !important;
                background: #ffffff !important;
            }
            .width-80mm {
                width: 78mm !important;
                max-width: 100% !important;
            }
            .width-58mm {
                width: 56mm !important;
                max-width: 100% !important;
            }
            .width-a4 {
                width: 100% !important;
                max-width: 210mm !important;
                padding: 10mm !important;
            }
            @page {
                margin: 0;
                size: auto;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn">ðŸ–¨ï¸ Ù¾Ø±Ù†Ù¹ Ú©Ø±ÛŒÚº (Print)</button>
    <a href="?id=<?php echo $sale_id; ?>&format=80mm" class="btn <?php echo $format == '80mm' ? '' : 'btn-outline'; ?>">80mm ØªÚ¾Ø±Ù…Ù„</a>
    <a href="?id=<?php echo $sale_id; ?>&format=58mm" class="btn <?php echo $format == '58mm' ? '' : 'btn-outline'; ?>">58mm ØªÚ¾Ø±Ù…Ù„</a>
    <a href="?id=<?php echo $sale_id; ?>&format=a4" class="btn <?php echo $format == 'a4' ? '' : 'btn-outline'; ?>">A4 Ø§Ù†ÙˆØ§Ø¦Ø³</a>
    <button onclick="window.close()" class="btn btn-outline">Ø¨Ù†Ø¯ Ú©Ø±ÛŒÚº (Close)</button>
</div>

<div class="receipt-container width-<?php echo $format; ?>" id="receiptArea">
    <!-- Header -->
    <div class="header">
        <div class="store-title"><?php echo $store_name; ?></div>
        <div><?php echo htmlspecialchars($store_address); ?></div>
        <div>ÙÙˆÙ†: <?php echo htmlspecialchars($store_phone); ?></div>
    </div>

    <!-- Invoice Meta -->
    <table class="meta-table">
        <tr>
            <td class="text-start">Ø¨Ù„ Ù†Ù…Ø¨Ø±: <strong><?php echo $sale['invoice_no']; ?></strong></td>
            <td class="text-end"><?php echo date('d-m-Y h:i A', strtotime($sale['created_at'])); ?></td>
        </tr>
        <tr>
            <td class="text-start">Ú¯Ø§ÛÚ©: <strong><?php echo htmlspecialchars($sale['customer_name'] ?: 'Ø¹Ø§Ù… Ø®Ø±ÛŒØ¯Ø§Ø±'); ?></strong></td>
            <td class="text-end">Ú©ÛŒØ´ÛŒØ¦Ø±: <?php echo htmlspecialchars($sale['cashier_name'] ?? 'Admin'); ?></td>
        </tr>
        <?php if (!empty($sale['cust_phone'])): ?>
        <tr>
            <td colspan="2" class="text-start">ÙÙˆÙ†: <?php echo htmlspecialchars($sale['cust_phone']); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Ø¢Ø¦Ù¹Ù…</th>
                <th class="text-center" style="width: 15%;">ØªØ¹Ø¯Ø§Ø¯</th>
                <th class="text-center" style="width: 20%;">Ø±ÛŒÙ¹</th>
                <th class="text-end" style="width: 20%;">Ø±Ù‚Ù…</th>
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
            <td>Ù…ÛŒØ²Ø§Ù† (Subtotal):</td>
            <td class="text-end">Rs. <?php echo number_format($sale['subtotal'], 2); ?></td>
        </tr>
        <?php if ($sale['discount_amount'] > 0): ?>
        <tr>
            <td>Ø±Ø¹Ø§ÛŒØª (Discount):</td>
            <td class="text-end">- Rs. <?php echo number_format($sale['discount_amount'], 2); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="grand-row">
            <td><strong>Ú©Ù„ Ø±Ù‚Ù… (Grand Total):</strong></td>
            <td class="text-end"><strong>Rs. <?php echo number_format($sale['grand_total'], 2); ?></strong></td>
        </tr>
        <tr>
            <td>ÙˆØµÙˆÙ„ Ø´Ø¯Û (Paid Cash):</td>
            <td class="text-end">Rs. <?php echo number_format($sale['paid_amount'], 2); ?></td>
        </tr>
        <?php if ($sale['change_amount'] > 0): ?>
        <tr>
            <td>ÙˆØ§Ù¾Ø³ÛŒ Ø¨Ù‚Ø§ÛŒØ§ (Change):</td>
            <td class="text-end fw-bold">Rs. <?php echo number_format($sale['change_amount'], 2); ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($sale['balance_amount'] > 0): ?>
        <tr>
            <td style="color: red;">Ø§Ø¯Ú¾Ø§Ø± Ú©Ú¾Ø§ØªÛ (Balance Due):</td>
            <td class="text-end fw-bold" style="color: red;">Rs. <?php echo number_format($sale['balance_amount'], 2); ?></td>
        </tr>
        <?php if ($sale['customer_id'] > 0): ?>
        <tr>
            <td>Ù…ÙˆØ¬ÙˆØ¯Û Ú©Ù„ Ú©Ú¾Ø§ØªÛ Ø¨Ù‚Ø§ÛŒØ§:</td>
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
window.addEventListener('load', function() {
    // Small delay ensures external font & CSS layout are fully computed before print dialog triggers
    setTimeout(function() {
        window.print();
    }, 350);
});
</script>
<?php endif; ?>

</body>
</html>

