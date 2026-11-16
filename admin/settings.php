<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Settings';
require_once '../config/database.php';

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_school') {
            set_setting($conn, 'school_name', trim($_POST['school_name'] ?? ''));
            set_setting($conn, 'academic_year', trim($_POST['academic_year'] ?? ''));
            set_setting($conn, 'school_address', trim($_POST['school_address'] ?? ''));
            set_setting($conn, 'school_phone', trim($_POST['school_phone'] ?? ''));
            $success_msg = 'School settings saved.';
        } elseif ($action === 'change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            $uid = (int)$_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
            $stmt->close();
            if (!password_verify($current, $hash)) {
                $error_msg = 'Current password is incorrect.';
            } elseif (strlen($new) < 6) {
                $error_msg = 'New password must be at least 6 characters.';
            } elseif ($new !== $confirm) {
                $error_msg = 'New passwords do not match.';
            } else {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $upd->bind_param('si', $hashed, $uid);
                $upd->execute();
                $upd->close();
                $success_msg = 'Password updated successfully.';
            }
        }
    }
}

$school_name = get_setting($conn, 'school_name', 'CampusFlow Academy');
$academic_year = get_setting($conn, 'academic_year', '2025-2026');
$school_address = get_setting($conn, 'school_address', '');
$school_phone = get_setting($conn, 'school_phone', '');

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo e($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">School Profile</div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_school">
                    <div class="mb-3"><label class="form-label">School Name</label><input name="school_name" class="form-control" value="<?php echo e($school_name); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Academic Year</label><input name="academic_year" class="form-control" value="<?php echo e($academic_year); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea name="school_address" class="form-control" rows="2"><?php echo e($school_address); ?></textarea></div>
                    <div class="mb-3"><label class="form-label">Phone</label><input name="school_phone" class="form-control" value="<?php echo e($school_phone); ?>"></div>
                    <button class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Change Admin Password</div>
            <div class="card-body">
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
                    <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>
                    <button class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Demo Logins</div>
            <div class="card-body small">
                <p class="mb-1">Password for all seed accounts: <code>password</code></p>
                <ul class="mb-0">
                    <li>Admin: <code>admin</code></li>
                    <li>Teacher: <code>wardah</code>, <code>rameesa</code></li>
                    <li>Student: <code>abdullah</code>, <code>sara</code>, <code>ahsan</code></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
