<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'Assignments';
require_once '../config/database.php';

$student_id = current_student_id($conn);
if ($student_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>Your student profile is not linked to this account. Please contact the school administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$sstmt = $conn->prepare("SELECT class_id FROM students WHERE id = ? LIMIT 1");
$sstmt->bind_param('i', $student_id);
$sstmt->execute();
$student = $sstmt->get_result()->fetch_assoc();
$sstmt->close();
$class_id = (int)($student['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('student/assignments.php');
    }

    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    if ($assignment_id <= 0 || $class_id <= 0) {
        flash_set('danger', 'Invalid assignment.');
        redirect('student/assignments.php');
    }

    $chk = $conn->prepare("SELECT id FROM assignments WHERE id = ? AND class_id = ? LIMIT 1");
    $chk->bind_param('ii', $assignment_id, $class_id);
    $chk->execute();
    $ok = $chk->get_result()->num_rows > 0;
    $chk->close();

    if (!$ok) {
        flash_set('danger', 'Assignment not found for your class.');
        redirect('student/assignments.php');
    }

    $status = 'Submitted';
    $submitted_at = date('Y-m-d H:i:s');
    $ins = $conn->prepare("
        INSERT INTO assignment_submissions (assignment_id, student_id, status, submitted_at)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), submitted_at = VALUES(submitted_at)
    ");
    $ins->bind_param('iiss', $assignment_id, $student_id, $status, $submitted_at);
    if ($ins->execute()) {
        flash_set('success', 'Assignment marked as submitted.');
    } else {
        flash_set('danger', 'Could not submit assignment.');
    }
    $ins->close();
    redirect('student/assignments.php');
}

$assignments = [];
if ($class_id > 0) {
    $stmt = $conn->prepare("
        SELECT a.id, a.title, a.description, a.due_date,
               s.subject_name, s.subject_code,
               subm.status AS submission_status, subm.submitted_at
        FROM assignments a
        INNER JOIN subjects s ON s.id = a.subject_id
        LEFT JOIN assignment_submissions subm ON subm.assignment_id = a.id AND subm.student_id = ?
        WHERE a.class_id = ?
        ORDER BY a.due_date ASC, a.id DESC
    ");
    $stmt->bind_param('ii', $student_id, $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Assignments</h4>
        <p class="text-muted small mb-0">Homework for your class — mark as submitted when done.</p>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fa-solid fa-book-open me-2" style="color:var(--primary-color);"></i>Class Assignments</span>
        <span class="badge-mint"><?php echo count($assignments); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if ($class_id <= 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-school"></i><p class="mb-0">You are not assigned to a class</p></div>
        <?php elseif (count($assignments) === 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-book-open"></i><p class="mb-0">No assignments yet</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th class="ps-3">Title</th>
                        <th>Subject</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th class="pe-3 text-end">Action</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $a):
                        $submitted = ($a['submission_status'] ?? '') === 'Submitted';
                        $overdue = !$submitted && strtotime($a['due_date']) < strtotime(date('Y-m-d'));
                    ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold"><?php echo e($a['title']); ?></div>
                                <div class="small text-muted"><?php echo e(mb_strimwidth($a['description'], 0, 80, '…')); ?></div>
                            </td>
                            <td><?php echo e($a['subject_name']); ?></td>
                            <td>
                                <?php echo e(date('M j, Y', strtotime($a['due_date']))); ?>
                                <?php if ($overdue): ?><div class="small text-danger">Overdue</div><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($submitted): ?>
                                    <span class="status-pill status-Submitted">Submitted</span>
                                    <?php if ($a['submitted_at']): ?>
                                        <div class="small text-muted"><?php echo e(date('M j, g:i A', strtotime($a['submitted_at']))); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-pill status-Pending">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-3 text-end">
                                <?php if (!$submitted): ?>
                                    <form method="POST" action="" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="submit">
                                        <input type="hidden" name="assignment_id" value="<?php echo (int)$a['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="fa-solid fa-paper-plane me-1"></i> Mark Submitted
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="fa-solid fa-check"></i> Done</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
