<?php
// users.php - User Accounts & Role Permissions
if (!defined('APP_INIT')) define('APP_INIT', true);
require_once __DIR__ . '/config/app.php';
require_login();
require_role(['admin', 'manager']);

$msg = '';
$err = '';

// Handle Add / Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_user') {
    verify_csrf();

    $uid = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'cashier';
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? 'active';

    if (empty($username) || empty($full_name)) {
        $err = "Username and Full Name are required!";
    } else {
        $u_esc = db_escape($username);
        $fn_esc = db_escape($full_name);
        $p_esc = db_escape($phone);

        // Check unique username
        $chk = db_fetch_one("SELECT id FROM users WHERE username = '$u_esc' AND id != $uid");
        if ($chk) {
            $err = "Username already taken! یہ صارف نام پہلے سے موجود ہے۔";
        } else {
            if ($uid > 0) {
                // Update
                $sql = "UPDATE users SET username = '$u_esc', full_name = '$fn_esc', role = '$role', phone = '$p_esc', status = '$status'";
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql .= ", password_hash = '$hash'";
                }
                $sql .= " WHERE id = $uid";
                db_query($sql);
                $msg = "User account updated! صارف اکاؤنٹ تبدیل ہو گیا۔";
            } else {
                // Insert
                if (empty($password)) {
                    $err = "Password is required for new users!";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (username, password_hash, full_name, role, phone, status) 
                            VALUES ('$u_esc', '$hash', '$fn_esc', '$role', '$p_esc', '$status')";
                    db_query($sql);
                    $msg = "New user registered! نیا صارف اکاؤنٹ بن گیا۔";
                }
            }
        }
    }
}

$users = db_fetch_all("SELECT * FROM users ORDER BY id ASC");

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-shield-lock text-success me-2"></i> صارفین اور اختیارات (Users & Permissions)</h4>
        <div class="text-muted small">اسٹور مالک، مینیجر اور کیشیئر اکاؤنٹس مینیج کریں</div>
    </div>
    <button class="btn btn-success fw-bold shadow-sm" onclick="openAddUserModal()">
        <i class="bi bi-person-plus me-1"></i> نیا صارف شامل کریں (Add User)
    </button>
</div>

<?php if ($msg): ?><div class="alert alert-success py-2 alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2 alert-dismissible fade show"><?php echo $err; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Users Table -->
<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>صارف نام (Username)</th>
                        <th>پورا نام</th>
                        <th>عہدہ / اختیارات (Role)</th>
                        <th>فون نمبر</th>
                        <th>حالت</th>
                        <th>شمولیت تاریخ</th>
                        <th class="text-center" style="width: 100px;">کارروائی</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td class="text-muted small"><?php echo $i + 1; ?></td>
                            <td><code><?php echo htmlspecialchars($u['username']); ?></code></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    if ($u['role'] == 'admin') echo 'bg-danger';
                                    elseif ($u['role'] == 'manager') echo 'bg-primary';
                                    elseif ($u['role'] == 'accountant') echo 'bg-info';
                                    else echo 'bg-success';
                                ?> text-uppercase">
                                    <?php echo $u['role']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($u['phone'] ?: '-'); ?></td>
                            <td>
                                <span class="badge <?php echo $u['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $u['status'] == 'active' ? 'فعال' : 'غیر فعال'; ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?php echo format_date($u['created_at']); ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary" onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="ترمیم">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="userModalTitle">نیا صارف شامل کریں</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="user_id" id="modalUserId" value="0">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">صارف نام (Username) *</label>
                        <input type="text" name="username" id="usrName" class="form-control" placeholder="e.g. ali_cashier" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">پورا نام (Full Name) *</label>
                        <input type="text" name="full_name" id="usrFullName" class="form-control" placeholder="محمد علی" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">عہدہ / کردار (Role)</label>
                            <select name="role" id="usrRole" class="form-select">
                                <option value="cashier">کیشیئر (Cashier)</option>
                                <option value="manager">اسٹور مینیجر (Manager)</option>
                                <option value="accountant">اکاؤنٹنٹ (Accountant)</option>
                                <option value="admin">سپر ایڈمن (Store Owner)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">حالت</label>
                            <select name="status" id="usrStatus" class="form-select">
                                <option value="active">فعال (Active)</option>
                                <option value="inactive">غیر فعال (Inactive)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">پاس ورڈ (Password)</label>
                        <input type="password" name="password" id="usrPass" class="form-control" placeholder="پاس ورڈ لکھیں...">
                        <div class="form-text small" id="passHint">صارف تبدیل کرتے وقت نیا پاس ورڈ لکھنے کی ضرورت نہیں جب تک تبدیل نہ کرنا ہو۔</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">فون نمبر</label>
                        <input type="text" name="phone" id="usrPhone" class="form-control" placeholder="03001234567">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">منسوخ</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">محفوظ کریں</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddUserModal() {
    document.getElementById('userModalTitle').innerText = 'نیا صارف شامل کریں';
    document.getElementById('modalUserId').value = '0';
    document.getElementById('usrName').value = '';
    document.getElementById('usrFullName').value = '';
    document.getElementById('usrRole').value = 'cashier';
    document.getElementById('usrStatus').value = 'active';
    document.getElementById('usrPass').value = '';
    document.getElementById('usrPass').required = true;
    document.getElementById('usrPhone').value = '';
    document.getElementById('passHint').innerText = '';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

function openEditUserModal(u) {
    document.getElementById('userModalTitle').innerText = 'صارف اکاؤنٹ ترمیم کریں';
    document.getElementById('modalUserId').value = u.id;
    document.getElementById('usrName').value = u.username;
    document.getElementById('usrFullName').value = u.full_name;
    document.getElementById('usrRole').value = u.role;
    document.getElementById('usrStatus').value = u.status;
    document.getElementById('usrPass').value = '';
    document.getElementById('usrPass').required = false;
    document.getElementById('usrPhone').value = u.phone;
    document.getElementById('passHint').innerText = 'اگر پاس ورڈ تبدیل نہیں کرنا تو اسے خالی چھوڑ دیں۔';
    new bootstrap.Modal(document.getElementById('userModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

