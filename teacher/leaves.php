<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'Leave Requests';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$tstmt = $conn->prepare("SELECT first_name, last_name FROM teachers WHERE id = ? LIMIT 1");
$tstmt->bind_param('i', $teacher_id);
$tstmt->execute();
$teacher = $tstmt->get_result()->fetch_assoc();
$tstmt->close();
$teacher_name = trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? 'Teacher'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_leave') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('teacher/leaves.php');
    }

    $from_date = trim($_POST['from_date'] ?? '');
    $to_date = trim($_POST['to_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($from_date === '' || $to_date === '' || $reason === '') {
        flash_set('danger', 'Please fill all leave request fields.');
    } elseif ($to_date < $from_date) {
        flash_set('danger', 'End date cannot be before start date.');
    } else {
        $ins = $conn->prepare("INSERT INTO leave_requests (teacher_id, from_date, to_date, reason) VALUES (?, ?, ?, ?)");
        $ins->bind_param('isss', $teacher_id, $from_date, $to_date, $reason);
        if ($ins->execute()) {
            notify_role_users(
                $conn,
                'admin',
                'Pending Leave Request',
                'Teacher ' . $teacher_name . ' requested leave from ' . $from_date . ' to ' . $to_date . '.',
                'admin/leaves.php'
            );
            flash_set('success', 'Leave request submitted.');
        } else {
            flash_set('danger', 'Could not submit leave request.');
        }
        $ins->close();
    }
    redirect('teacher/leaves.php');
}

$leaves = [];
$stmt = $conn->prepare("
    SELECT id, from_date, to_date, reason, status, admin_note, created_at
    FROM leave_requests
    WHERE teacher_id = ?
    ORDER BY id DESC
");
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $leaves[] = $row;
}
$stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Leave Requests</h4>
        <p class="text-muted small mb-0">Request time off and track approval status.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fa-solid fa-plane-departure me-2" style="color:var(--primary-color);"></i>Request Leave</div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="request_leave">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">From Date <span class="text-danger">*</span></label>
                        <input type="date" name="from_date" class="form-control form-control-sm" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">To Date <span class="text-danger">*</span></label>
                        <input type="date" name="to_date" class="form-control form-control-sm" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-paper-plane me-1"></i> Submit Request</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>My Requests</span>
                <span class="badge-mint"><?php echo count($leaves); ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (count($leaves) === 0): ?>
                    <div class="empty-state py-5"><i class="fa-solid fa-plane-departure"></i><p class="mb-0">No leave requests yet</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr>
                                <th class="ps-3">Dates</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th class="pe-3">Admin Note</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($leaves as $lv): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold"><?php echo e(date('M j', strtotime($lv['from_date'])) . ' – ' . date('M j, Y', strtotime($lv['to_date']))); ?></div>
                                        <div class="small text-muted">Requested <?php echo e(date('M j', strtotime($lv['created_at']))); ?></div>
                                    </td>
                                    <td><?php echo e($lv['reason']); ?></td>
                                    <td><span class="status-pill status-<?php echo e($lv['status']); ?>"><?php echo e($lv['status']); ?></span></td>
                                    <td class="pe-3 text-muted small"><?php echo e($lv['admin_note'] ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
