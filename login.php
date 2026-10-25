<?php
// login.php - System Authentication
require_once __DIR__ . '/config/app.php';

$error = '';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $u_esc = db_escape($username);
        $user = db_fetch_one("SELECT * FROM users WHERE username = '$u_esc' AND status = 'active'");

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            log_audit('Login', 'users', $user['id'], 'User logged into system');

            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid username or password! / صارف کا نام یا پاس ورڈ درست نہیں ہے۔';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}

$lang = get_current_lang();
$is_rtl = is_rtl();
$store_name = $lang === 'ur' ? get_setting('store_name_ur', 'المدینہ کریانہ اسٹور') : get_setting('store_name_en', 'Al-Madina Kiryana Store');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($store_name); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if ($is_rtl): ?><link rel="stylesheet" href="assets/css/rtl.css"><?php endif; ?>
    <style>
        body {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            background: #ffffff;
            overflow: hidden;
        }
        .login-header {
            background: #166534;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
    </style>
</head>
<body dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>">

<div class="login-card">
    <div class="login-header">
        <i class="bi bi-shop fs-1 d-block mb-2 text-warning"></i>
        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($store_name); ?></h4>
        <p class="small text-white-50 mb-0"><?php echo get_setting('slogan_ur', 'معیار اور بچت کا بااعتماد مرکز'); ?></p>
    </div>

    <div class="p-4">
        <!-- Language Switcher Bar -->
        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
            <span class="small text-muted"><i class="bi bi-shield-lock me-1"></i> Sign In / لاگ ان</span>
            <div>
                <a href="?lang=ur" class="badge <?php echo $lang === 'ur' ? 'bg-success' : 'bg-light text-dark'; ?> text-decoration-none">اردو</a>
                <a href="?lang=en" class="badge <?php echo $lang === 'en' ? 'bg-success' : 'bg-light text-dark'; ?> text-decoration-none">English</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Username / صارف نام</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="admin or cashier" required autofocus id="loginUser">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Password / پاس ورڈ</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required id="loginPass">
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100 fw-bold py-2 mb-3 shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login / لاگ ان کریں
            </button>
        </form>

        <div class="border-top pt-3 text-center">
            <div class="small text-muted mb-2">Quick Demo Accounts / فوری ٹیسٹ لاگ ان:</div>
            <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="fillCredentials('admin', 'admin123')">
                    Admin (مالک)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="fillCredentials('cashier', '123456')">
                    Cashier (کیشیئر)
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function fillCredentials(u, p) {
    document.getElementById('loginUser').value = u;
    document.getElementById('loginPass').value = p;
}
</script>
</body>
</html>
