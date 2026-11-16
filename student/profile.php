<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'My Profile';
require_once '../config/database.php';

$student_id = current_student_id($conn);
if ($student_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>Your student profile is not linked to this account. Please contact the school administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('student/profile.php');
    }

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
    redirect('student/profile.php');
}

$stmt = $conn->prepare("
    SELECT s.*, c.class_name, c.section, u.username, u.email
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    LEFT JOIN users u ON u.id = s.user_id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$full = ($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '');
if (!empty($profile['photo']) && $profile['photo'] !== 'default.png' && file_exists('../uploads/' . $profile['photo'])) {
    $photo_url = '../uploads/' . e($profile['photo']);
} else {
    $photo_url = 'https://ui-avatars.com/api/?name=' . urlencode($full) . '&background=10B981&color=fff&size=128';
}

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">My Profile</h4>
        <p class="text-muted small mb-0">View your student details and update password.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body text-center py-4">
                <img src="<?php echo $photo_url; ?>" alt="" class="rounded-circle mb-3 border" style="width:110px;height:110px;object-fit:cover;">
                <h5 class="fw-bold mb-1"><?php echo e($full); ?></h5>
                <div class="text-muted small mb-2"><?php echo e($profile['admission_number'] ?? ''); ?></div>
                <span class="badge-mint"><?php echo e(($profile['class_name'] ?? '—') . ($profile['section'] ? ' · ' . $profile['section'] : '')); ?></span>
                <span class="status-pill status-<?php echo e($profile['status'] ?? ''); ?> ms-1"><?php echo e($profile['status'] ?? ''); ?></span>
            </div>
            <div class="card-body border-top">
                <div class="row g-3 small">
                    <div class="col-6">
                        <div class="text-muted">Gender</div>
                        <div class="fw-semibold"><?php echo e($profile['gender'] ?? '—'); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Date of Birth</div>
                        <div class="fw-semibold"><?php echo e(date('M j, Y', strtotime($profile['date_of_birth']))); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Username</div>
                        <div class="fw-semibold"><?php echo e($profile['username'] ?? '—'); ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">Email</div>
                        <div class="fw-semibold"><?php echo e($profile['email'] ?? '—'); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted">Parent / Guardian</div>
                        <div class="fw-semibold"><?php echo e($profile['parent_name'] ?? '—'); ?></div>
                        <div class="text-muted"><?php echo e($profile['parent_phone'] ?? ''); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted">Address</div>
                        <div class="fw-semibold"><?php echo e($profile['address'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
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
                    <button type="submit" class="btn btn-primary btn-sm px-4 mt-3"><i class="fa-solid fa-key me-1"></i> Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
