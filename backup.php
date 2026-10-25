<?php
// backup.php - Database Backup & Maintenance
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();
require_role(['admin']);

// Handle SQL Backup Download
if (isset($_GET['download']) && $_GET['download'] === 'sql') {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $backup_sql = "-- Kiryana Store Management System SQL Backup\n";
    $backup_sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $backup_sql .= "-- Host: localhost | Database: kiryana_db\n\n";
    $backup_sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $create_row = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $backup_sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup_sql .= $create_row[1] . ";\n\n";

        $rows = $conn->query("SELECT * FROM `$table`");
        while ($row = $rows->fetch_assoc()) {
            $cols = array_keys($row);
            $vals = array_map(function($v) use ($conn) {
                if ($v === null) return "NULL";
                return "'" . $conn->real_escape_string($v) . "'";
            }, array_values($row));

            $backup_sql .= "INSERT INTO `$table` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n";
        }
        $backup_sql .= "\n";
    }

    $backup_sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = "kiryana_db_backup_" . date('Ymd_His') . ".sql";
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backup_sql));
    echo $backup_sql;
    exit;
}

// Table statistics
$stats = db_fetch_all("SELECT 
    table_name AS `Table`, 
    table_rows AS `Rows`, 
    ROUND((data_length + index_length) / 1024, 2) AS `Size_KB` 
    FROM information_schema.tables 
    WHERE table_schema = 'kiryana_db'");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-cloud-arrow-down text-success me-2"></i> ڈیٹا بیک اپ و بحالی (Database Backup & Maintenance)</h4>
        <div class="text-muted small">اپنے کریانہ اسٹور کا مکمل ڈیٹا محفوظ کریں تاکہ کسی بھی وقت بحال کیا جا سکے</div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-success"><i class="bi bi-download me-2"></i> مکمل ڈیٹا بیس ڈاؤن لوڈ کریں</h6>
            </div>
            <div class="card-body p-4 text-center">
                <i class="bi bi-database-check fs-1 text-success d-block mb-3"></i>
                <h5 class="fw-bold">سیلز، کسٹمر کھاتہ، اسٹاک اور سیٹنگز کا بیک اپ</h5>
                <p class="text-muted small">
                    ایک کلک سے پوری ڈیٹابیس کی مکمل <code>.sql</code> فائل اپنے کمپیوٹر یا موبائل میں ڈاؤن لوڈ کریں تاکہ کمپیوٹر خراب ہونے کی صورت میں بھی ریکارڈ محفوظ رہے۔
                </p>
                <a href="backup.php?download=sql" class="btn btn-success btn-lg fw-bold px-4 shadow-sm">
                    <i class="bi bi-cloud-download-fill me-1"></i> بیک اپ ڈاؤن لوڈ کریں (Download Backup)
                </a>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6">
        <div class="card border-0 shadow-sm rounded-3 h-100">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-primary"><i class="bi bi-arrow-repeat me-2"></i> سسٹم دوبارہ ترتیب دیں (Re-run Setup)</h6>
            </div>
            <div class="card-body p-4 text-center">
                <i class="bi bi-tools fs-1 text-primary d-block mb-3"></i>
                <h5 class="fw-bold">ڈیٹابیس ٹیبلز اور ڈیفالٹ سیڈرز</h5>
                <p class="text-muted small">
                    اگر آپ ڈیفالٹ یونٹس، کیٹیگریز، اکاؤنٹس اور سیمپل آئٹمز دوبارہ چیک کرنا چاہتے ہیں تو سیٹ اپ اسکرپٹ چلا سکتے ہیں۔
                </p>
                <a href="setup.php" class="btn btn-outline-primary fw-bold px-4" target="_blank">
                    <i class="bi bi-play-circle me-1"></i> سیٹ اپ اسکرپٹ چلائیں (Run Setup)
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Database Tables Status -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-header bg-white py-3 border-bottom">
        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-table me-2"></i> ڈیٹابیس ٹیبلز کا حجم و ریکارڈز کی تفصیل</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>ٹیبل نام (Table Name)</th>
                        <th class="text-center">ریکارڈز کی تعداد (Rows)</th>
                        <th class="text-end">سائز (Size KB)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $st): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($st['Table']); ?></code></td>
                            <td class="text-center fw-semibold"><?php echo number_format($st['Rows']); ?></td>
                            <td class="text-end text-muted small"><?php echo $st['Size_KB']; ?> KB</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

