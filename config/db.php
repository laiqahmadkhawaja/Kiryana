<?php
// config/db.php - Database connection & query helper
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'kiryana_db';
$db_port = 3306;

// Disable mysqli default exception throwing to handle connection gracefully
mysqli_report(MYSQLI_REPORT_OFF);

// Direct connection to kiryana_db (lazy create only on first run if missing)
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_errno === 1049) {
    $setup_conn = new mysqli($db_host, $db_user, $db_pass, '', $db_port);
    if (!$setup_conn->connect_error) {
        $setup_conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $setup_conn->close();
    }
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
}

if ($conn->connect_error) {
    die("Database Connection Error: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/**
 * Safe query runner
 */
function db_query($sql) {
    global $conn;
    $result = $conn->query($sql);
    if (!$result) {
        error_log("DB Query Error: " . $conn->error . " in SQL: " . $sql);
    }
    return $result;
}

/**
 * Safe escape string
 */
function db_escape($str) {
    global $conn;
    return $conn->real_escape_string((string)$str);
}

/**
 * Fetch all rows
 */
function db_fetch_all($sql) {
    $res = db_query($sql);
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Fetch single row
 */
function db_fetch_one($sql) {
    $res = db_query($sql);
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    return null;
}

/**
 * Get setting value
 */
function get_setting($key, $default = '') {
    static $settings_cache = null;
    if ($settings_cache === null) {
        $settings_cache = [];
        $rows = db_fetch_all("SELECT setting_key, setting_value FROM settings");
        if ($rows) {
            foreach ($rows as $r) {
                $settings_cache[$r['setting_key']] = $r['setting_value'];
            }
        }
    }
    return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
}

/**
 * Set setting value
 */
function set_setting($key, $value) {
    global $conn;
    $key_esc = db_escape($key);
    $val_esc = db_escape($value);
    return db_query("INSERT INTO settings (setting_key, setting_value) VALUES ('$key_esc', '$val_esc') 
                     ON DUPLICATE KEY UPDATE setting_value = '$val_esc'");
}

/**
 * Log audit trail
 */
function log_audit($action, $table_name = '', $record_id = 0, $details = '') {
    global $conn;
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $act_esc = db_escape($action);
    $tbl_esc = db_escape($table_name);
    $rec_id = (int)$record_id;
    $det_esc = db_escape($details);
    $ip = db_escape($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

    db_query("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address, created_at) 
              VALUES ($user_id, '$act_esc', '$tbl_esc', $rec_id, '$det_esc', '$ip', NOW())");
}

/**
 * Format currency
 */
function format_currency($amount, $with_symbol = true) {
    $sym = $with_symbol ? (get_setting('currency_symbol', 'Rs. ') . ' ') : '';
    return $sym . number_format((float)$amount, 2);
}

/**
 * Format date
 */
function format_datetime($dt) {
    if (!$dt || $dt == '0000-00-00 00:00:00') return '-';
    return date("d M Y, h:i A", strtotime($dt));
}
function format_date($d) {
    if (!$d || $d == '0000-00-00') return '-';
    return date("d M Y", strtotime($d));
}
