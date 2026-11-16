<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Notices & Events';
require_once '../config/database.php';

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_notice' || $action === 'edit_notice') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $notice_type = $_POST['notice_type'] ?? 'Notice';
            $event_date = $_POST['event_date'] ?? '';
            $notify_roles = $_POST['notify_roles'] ?? [];
            if ($title === '' || $description === '' || $event_date === '' || !in_array($notice_type, ['Notice','Event','Holiday','Exam'], true)) {
                $error_msg = 'Please fill all fields correctly.';
            } elseif ($action === 'add_notice') {
                $stmt = $conn->prepare("INSERT INTO notices (title, description, notice_type, event_date) VALUES (?,?,?,?)");
                $stmt->bind_param('ssss', $title, $description, $notice_type, $event_date);
                if ($stmt->execute()) {
                    foreach (['teacher','student'] as $role) {
                        if (in_array($role, $notify_roles, true)) {
                            notify_role_users($conn, $role, $title, $description, $role . '/notices.php');
                        }
                    }
                    $success_msg = 'Notice published successfully.';
                } else {
                    $error_msg = 'Failed to save notice.';
                }
                $stmt->close();
            } else {
                $id = (int)($_POST['notice_id'] ?? 0);
                $stmt = $conn->prepare("UPDATE notices SET title=?, description=?, notice_type=?, event_date=? WHERE id=?");
                $stmt->bind_param('ssssi', $title, $description, $notice_type, $event_date, $id);
                $stmt->execute();
                $stmt->close();
                $success_msg = 'Notice updated.';
            }
        } elseif ($action === 'delete_notice') {
            $id = (int)($_POST['notice_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM notices WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Notice deleted.';
        }
    }
}

$notices = $conn->query("SELECT * FROM notices ORDER BY event_date DESC, id DESC");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="d-flex justify-content-between mb-3">
    <p class="text-muted mb-0">Publish school announcements. Optionally push dashboard notifications to teachers/students.</p>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoticeModal"><i class="fa-solid fa-plus me-1"></i> Add Notice</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Type</th><th>Title</th><th>Date</th><th>Description</th><th></th></tr></thead>
                <tbody>
                <?php if ($notices->num_rows === 0): ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-bullhorn"></i><p>No notices yet</p></div></td></tr>
                <?php else: while ($n = $notices->fetch_assoc()): ?>
                    <tr>
                        <td><span class="badge-mint"><?php echo e($n['notice_type']); ?></span></td>
                        <td class="fw-semibold"><?php echo e($n['title']); ?></td>
                        <td><?php echo e($n['event_date']); ?></td>
                        <td class="small text-muted"><?php echo e(mb_strimwidth($n['description'], 0, 80, '…')); ?></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editNotice<?php echo (int)$n['id']; ?>">Edit</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this notice?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_notice">
                                <input type="hidden" name="notice_id" value="<?php echo (int)$n['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <div class="modal fade" id="editNotice<?php echo (int)$n['id']; ?>" tabindex="-1">
                        <div class="modal-dialog"><div class="modal-content">
                            <form method="post">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="edit_notice">
                                <input type="hidden" name="notice_id" value="<?php echo (int)$n['id']; ?>">
                                <div class="modal-header"><h5 class="modal-title">Edit Notice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" value="<?php echo e($n['title']); ?>" required></div>
                                    <div class="mb-3"><label class="form-label">Type</label>
                                        <select name="notice_type" class="form-select">
                                            <?php foreach (['Notice','Event','Holiday','Exam'] as $t): ?>
                                                <option <?php echo $n['notice_type']===$t?'selected':''; ?>><?php echo $t; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3"><label class="form-label">Date</label><input type="date" name="event_date" class="form-control" value="<?php echo e($n['event_date']); ?>" required></div>
                                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" required><?php echo e($n['description']); ?></textarea></div>
                                </div>
                                <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
                            </form>
                        </div></div>
                    </div>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addNoticeModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_notice">
            <div class="modal-header"><h5 class="modal-title">Publish Notice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title *</label><input name="title" class="form-control" required></div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6"><label class="form-label">Type</label>
                        <select name="notice_type" class="form-select">
                            <option>Notice</option><option>Event</option><option>Holiday</option><option>Exam</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="event_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Description *</label><textarea name="description" class="form-control" rows="3" required></textarea></div>
                <div class="mb-0">
                    <label class="form-label">Also notify via dashboard</label>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="notify_roles[]" value="teacher" id="nt"><label class="form-check-label" for="nt">Teachers</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="notify_roles[]" value="student" id="ns"><label class="form-check-label" for="ns">Students</label></div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Publish</button></div>
        </form>
    </div></div>
</div>

<?php include '../includes/footer.php'; ?>
