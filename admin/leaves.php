<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Leave Requests';
require_once '../config/database.php';

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        $admin_note = trim($_POST['admin_note'] ?? '');
        if (in_array($action, ['approve', 'reject'], true) && $leave_id > 0) {
            $status = $action === 'approve' ? 'Approved' : 'Rejected';
            $stmt = $conn->prepare("UPDATE leave_requests SET status=?, admin_note=? WHERE id=? AND status='Pending'");
            $stmt->bind_param('ssi', $status, $admin_note, $leave_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $q = $conn->prepare("SELECT lr.*, t.user_id, t.first_name, t.last_name FROM leave_requests lr INNER JOIN teachers t ON t.id=lr.teacher_id WHERE lr.id=?");
                $q->bind_param('i', $leave_id);
                $q->execute();
                $row = $q->get_result()->fetch_assoc();
                $q->close();
                if ($row && $row['user_id']) {
                    create_notification($conn, (int)$row['user_id'], "Leave {$status}", "Your leave from {$row['from_date']} to {$row['to_date']} was {$status}.", 'teacher/leaves.php');
                }
                // Mark related admin notifications read
                $uid = (int)$_SESSION['user_id'];
                $conn->query("UPDATE notifications SET is_read=1 WHERE user_id={$uid} AND is_read=0 AND title LIKE 'Pending Leave%'");
                $success_msg = "Leave request {$status}.";
            } else {
                $error_msg = 'Leave request could not be updated.';
            }
            $stmt->close();
        }
    }
}

$filter = $_GET['status'] ?? 'Pending';
$sql = "SELECT lr.*, t.first_name, t.last_name, t.phone
        FROM leave_requests lr
        INNER JOIN teachers t ON t.id = lr.teacher_id";
if (in_array($filter, ['Pending','Approved','Rejected'], true)) {
    $sql .= " WHERE lr.status = '" . $conn->real_escape_string($filter) . "'";
}
$sql .= " ORDER BY lr.created_at DESC";
$leaves = $conn->query($sql);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo e($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="mb-3">
    <div class="btn-group btn-group-sm">
        <?php foreach (['Pending','Approved','Rejected','All'] as $st):
            $href = $st === 'All' ? '?status=' : '?status=' . urlencode($st);
            $active = ($filter === $st || ($st==='All' && !in_array($filter,['Pending','Approved','Rejected'],true)));
        ?>
            <a class="btn btn-outline-secondary <?php echo $active?'active':''; ?>" href="<?php echo $href; ?>"><?php echo $st; ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Teacher</th><th>From</th><th>To</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php if ($leaves->num_rows === 0): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-plane"></i><p>No leave requests</p></div></td></tr>
                <?php else: while ($l = $leaves->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo e($l['first_name'].' '.$l['last_name']); ?><div class="small text-muted"><?php echo e($l['phone']); ?></div></td>
                        <td><?php echo e($l['from_date']); ?></td>
                        <td><?php echo e($l['to_date']); ?></td>
                        <td class="small"><?php echo e($l['reason']); ?><?php if ($l['admin_note']): ?><div class="text-muted">Note: <?php echo e($l['admin_note']); ?></div><?php endif; ?></td>
                        <td><span class="status-pill status-<?php echo e($l['status']); ?>"><?php echo e($l['status']); ?></span></td>
                        <td class="text-end">
                            <?php if ($l['status'] === 'Pending'): ?>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#leaveModal<?php echo (int)$l['id']; ?>" data-action="approve">Approve</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#leaveModal<?php echo (int)$l['id']; ?>" data-action="reject">Reject</button>
                                <div class="modal fade" id="leaveModal<?php echo (int)$l['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog"><div class="modal-content">
                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="leave_id" value="<?php echo (int)$l['id']; ?>">
                                            <input type="hidden" name="action" id="leaveAction<?php echo (int)$l['id']; ?>" value="approve">
                                            <div class="modal-header"><h5 class="modal-title">Review Leave</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">
                                                <label class="form-label">Admin note (optional)</label>
                                                <textarea name="admin_note" class="form-control" rows="2"></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-success" onclick="document.getElementById('leaveAction<?php echo (int)$l['id']; ?>').value='approve'">Approve</button>
                                                <button class="btn btn-danger" onclick="document.getElementById('leaveAction<?php echo (int)$l['id']; ?>').value='reject'">Reject</button>
                                            </div>
                                        </form>
                                    </div></div>
                                </div>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
