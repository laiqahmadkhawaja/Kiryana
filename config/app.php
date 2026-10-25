<?php
// config/app.php - Core application bootstrap & helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

// Check login status
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

// Require login
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

// User role check
function current_user_role() {
    return $_SESSION['user_role'] ?? 'cashier';
}

function has_role($roles = []) {
    if (empty($roles)) return true;
    if (is_string($roles)) $roles = [$roles];
    return in_array(current_user_role(), $roles);
}

function require_role($roles = []) {
    require_login();
    if (!has_role($roles)) {
        echo "<div style='font-family:sans-serif; text-align:center; padding:50px;'>
                <h2>Access Denied / غیر مجاز رسائی</h2>
                <p>You do not have permission to view this page.</p>
                <a href='index.php'>Go to Dashboard</a>
              </div>";
        exit;
    }
}

// Get active shift for current user or store
function get_active_shift($user_id = null) {
    if (!$user_id && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    $uid = (int)$user_id;
    return db_fetch_one("SELECT * FROM shifts WHERE user_id = $uid AND status = 'open' ORDER BY id DESC LIMIT 1");
}

// Generic document sequence number generator
function generate_doc_no($table, $prefix = 'DOC-', $column = 'invoice_no') {
    $today = date('Ymd');
    $last = db_fetch_one("SELECT `$column` FROM `$table` WHERE `$column` LIKE '$prefix$today%' ORDER BY id DESC LIMIT 1");
    $seq = 1;
    if ($last && !empty($last[$column])) {
        $parts = explode('-', $last[$column]);
        $seq = isset($parts[2]) ? (int)$parts[2] + 1 : 1;
    }
    return sprintf("%s%s-%04d", $prefix, $today, $seq);
}

function generate_invoice_no($prefix = 'INV-') {
    return generate_doc_no('sales', $prefix);
}

function generate_purchase_no($prefix = 'PO-') {
    return generate_doc_no('purchases', $prefix);
}

// CSRF token helpers
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
            die("Security Error: Invalid CSRF Token.");
        }
    }
}
