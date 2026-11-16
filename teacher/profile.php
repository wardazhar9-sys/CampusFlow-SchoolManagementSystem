<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'My Profile';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('teacher/profile.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($phone === '' || $address === '') {
            flash_set('danger', 'Phone and address are required.');
        } else {
            $upd = $conn->prepare("UPDATE teachers SET phone = ?, address = ? WHERE id = ?");
            $upd->bind_param('ssi', $phone, $address, $teacher_id);
            if ($upd->execute()) {
                flash_set('success', 'Profile updated successfully.');
            } else {
                flash_set('danger', 'Could not update profile.');
            }
            $upd->close();
        }
        redirect('teacher/profile.php');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $ustmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $ustmt->bind_param('i', $uid);
        $ustmt->execute();
        $user = $ustmt->get_result()->fetch_assoc();
        $ustmt->close();

        if (!$user || !password_verify($current, $user['password'])) {
            flash_set('danger', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash_set('danger', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash_set('danger', 'New password and confirmation do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param('si', $hash, $uid);
            if ($upd->execute()) {
                flash_set('success', 'Password changed successfully.');
            } else {
                flash_set('danger', 'Could not change password.');
            }
            $upd->close();
        }
        redirect('teacher/profile.php');
    }
}

$stmt = $conn->prepare("
    SELECT t.*, u.username, u.email
    FROM teachers t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">My Profile</h4>
        <p class="text-muted small mb-0">Update contact details and password.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center py-4">
                <?php
                $full = ($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '');
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($full) . '&background=10B981&color=fff&size=128';
                ?>
                <img src="<?php echo e($avatar); ?>" alt="" class="rounded-circle mb-3" style="width:96px;height:96px;">
                <h5 class="fw-bold mb-1"><?php echo e($full); ?></h5>
                <div class="text-muted small mb-2">@<?php echo e($profile['username'] ?? ''); ?></div>
                <span class="status-pill status-<?php echo e($profile['status'] ?? 'Active'); ?>"><?php echo e($profile['status'] ?? 'Active'); ?></span>
                <hr>
                <div class="text-start small">
                    <div class="mb-2"><span class="text-muted">Email</span><div class="fw-semibold"><?php echo e($profile['email'] ?? '—'); ?></div></div>
                    <div class="mb-2"><span class="text-muted">Gender</span><div class="fw-semibold"><?php echo e($profile['gender'] ?? '—'); ?></div></div>
                    <div class="mb-0"><span class="text-muted">Joined</span><div class="fw-semibold"><?php echo e(date('M j, Y', strtotime($profile['joining_date']))); ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">Contact Details</div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control form-control-sm" value="<?php echo e($profile['phone'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Address <span class="text-danger">*</span></label>
                        <textarea name="address" class="form-control form-control-sm" rows="3" required><?php echo e($profile['address'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Profile</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Current Password <span class="text-danger">*</span></label>
                        <input type="password" name="current_password" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control form-control-sm" minlength="6" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control form-control-sm" minlength="6" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm px-4 mt-3"><i class="fa-solid fa-key me-1"></i> Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
