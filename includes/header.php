<?php
// includes/header.php
if (!defined('APP_INIT')) {
    require_once __DIR__ . '/../config/app.php';
}

$current_user = $_SESSION['user_full_name'] ?? 'User';
$current_role = $_SESSION['user_role'] ?? 'cashier';
$lang = get_current_lang();
$is_rtl = is_rtl();
$active_shift = get_active_shift();
$store_name = $lang === 'ur' ? get_setting('store_name_ur', 'المدینہ کریانہ اسٹور') : get_setting('store_name_en', 'Al-Madina Kiryana Store');
$currency = get_setting('currency_symbol', 'Rs.');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store_name); ?> - Kiryana POS & Management</title>
    <!-- Google Fonts: Inter & Noto Nastaliq Urdu -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- App Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if ($is_rtl): ?>
    <link rel="stylesheet" href="assets/css/rtl.css">
    <?php endif; ?>
</head>
<body dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">

<!-- Top Navigation Bar -->
<header class="top-navbar px-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-md-none" type="button" id="sidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a href="index.php" class="text-decoration-none d-flex align-items-center gap-2">
            <span class="fs-4 text-success"><i class="bi bi-shop"></i></span>
            <span class="brand-text"><?php echo htmlspecialchars($store_name); ?></span>
        </a>
    </div>

    <!-- Middle Info: Live Clock & Shift Status -->
    <div class="d-none d-md-flex align-items-center gap-3">
        <?php if ($active_shift): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                <i class="bi bi-clock-history me-1"></i> 
                <?php echo __('shifts'); ?>: #<?php echo $active_shift['id']; ?> (<?php echo __('active'); ?>)
            </span>
        <?php else: ?>
            <a href="shifts.php" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill text-decoration-none">
                <i class="bi bi-exclamation-triangle me-1"></i> <?php echo __('open_shift'); ?>
            </a>
        <?php endif; ?>

        <div class="text-muted small">
            <i class="bi bi-calendar3 me-1"></i>
            <span id="liveClock"><?php echo date('d M Y, h:i A'); ?></span>
        </div>
    </div>

    <!-- Right Controls -->
    <div class="d-flex align-items-center gap-2">
        <!-- POS Quick Button -->
        <a href="pos.php" class="btn btn-success btn-sm fw-bold px-3 shadow-sm d-flex align-items-center gap-1">
            <i class="bi bi-calculator"></i>
            <span class="d-none d-sm-inline"><?php echo __('pos'); ?></span>
        </a>

        <!-- Language Toggle -->
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-translate"></i> <?php echo $lang === 'ur' ? 'اردو' : 'English'; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item <?php echo $lang === 'ur' ? 'active' : ''; ?>" href="?lang=ur">اردو (Urdu)</a></li>
                <li><a class="dropdown-item <?php echo $lang === 'en' ? 'active' : ''; ?>" href="?lang=en">English</a></li>
            </ul>
        </div>

        <!-- User Menu -->
        <div class="dropdown">
            <button class="btn btn-light btn-sm rounded-circle border" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle fs-5 text-secondary"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-bold"><?php echo htmlspecialchars($current_user); ?></div>
                    <div class="text-muted small text-capitalize"><?php echo htmlspecialchars($current_role); ?></div>
                </li>
                <li><a class="dropdown-item py-2" href="settings.php"><i class="bi bi-gear me-2"></i> <?php echo __('settings'); ?></a></li>
                <li><a class="dropdown-item py-2" href="backup.php"><i class="bi bi-database-down me-2"></i> <?php echo __('backup'); ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> <?php echo __('logout'); ?></a></li>
            </ul>
        </div>
    </div>
</header>

<div class="app-wrapper">
