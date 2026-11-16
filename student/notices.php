<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'Notices';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('student/notices.php');
    }
    $upd = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $upd->bind_param('i', $uid);
    $upd->execute();
    $upd->close();
    flash_set('success', 'All notifications marked as read.');
    redirect('student/notices.php');
}

$notices = [];
$nres = $conn->prepare("SELECT title, description, notice_type, event_date, created_at FROM notices ORDER BY event_date ASC, id DESC");
$nres->execute();
$nres_out = $nres->get_result();
while ($row = $nres_out->fetch_assoc()) {
    $notices[] = $row;
}
$nres->close();

$notifications = [];
$nstmt = $conn->prepare("SELECT id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
$nstmt->bind_param('i', $uid);
$nstmt->execute();
$nres2 = $nstmt->get_result();
while ($row = $nres2->fetch_assoc()) {
    $notifications[] = $row;
}
$nstmt->close();

$unread = unread_notification_count($conn, $uid);

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Notices &amp; Notifications</h4>
        <p class="text-muted small mb-0">School announcements and your personal alerts.</p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-check-double me-1"></i> Mark all as read</button>
        </form>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span><i class="fa-solid fa-bullhorn me-2" style="color:var(--primary-color);"></i>School Notices</span>
                <span class="badge-mint"><?php echo count($notices); ?></span>
            </div>
            <div class="card-body">
                <?php if (count($notices) === 0): ?>
                    <div class="empty-state py-4"><i class="fa-solid fa-bullhorn"></i><p class="mb-0">No notices posted</p></div>
                <?php else: foreach ($notices as $n): ?>
                    <div class="d-flex justify-content-between align-items-start border-bottom py-3">
                        <div>
                            <span class="badge-mint me-1"><?php echo e($n['notice_type']); ?></span>
                            <strong><?php echo e($n['title']); ?></strong>
                            <div class="small text-muted mt-1"><?php echo e($n['description']); ?></div>
                        </div>
                        <span class="small text-muted text-nowrap ms-2"><?php echo e(date('M j', strtotime($n['event_date']))); ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span><i class="fa-solid fa-bell me-2" style="color:var(--primary-color);"></i>My Notifications</span>
                <?php if ($unread > 0): ?><span class="badge bg-danger"><?php echo $unread; ?> unread</span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($notifications) === 0): ?>
                    <div class="empty-state py-4"><i class="fa-regular fa-bell"></i><p class="mb-0">No notifications yet</p></div>
                <?php else: foreach ($notifications as $n): ?>
                    <div class="notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                        <strong><?php echo e($n['title']); ?></strong>
                        <div class="small text-muted"><?php echo e($n['message']); ?></div>
                        <div class="small text-muted mt-1"><?php echo e(date('M j, Y g:i A', strtotime($n['created_at']))); ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
