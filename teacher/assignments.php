<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'Assignments';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$my_subjects = [];
$sub_stmt = $conn->prepare("
    SELECT sub.id, sub.subject_name, sub.class_id, c.class_name, c.section
    FROM subjects sub
    INNER JOIN classes c ON c.id = sub.class_id
    WHERE sub.teacher_id = ?
    ORDER BY c.class_name ASC, sub.subject_name ASC
");
$sub_stmt->bind_param('i', $teacher_id);
$sub_stmt->execute();
$sub_res = $sub_stmt->get_result();
while ($row = $sub_res->fetch_assoc()) {
    $my_subjects[] = $row;
}
$sub_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('teacher/assignments.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $due_date = trim($_POST['due_date'] ?? '');

        $matched = null;
        foreach ($my_subjects as $s) {
            if ((int)$s['id'] === $subject_id) {
                $matched = $s;
                break;
            }
        }

        if (!$matched || $title === '' || $description === '' || $due_date === '') {
            flash_set('danger', 'Please fill all fields and select a valid subject.');
        } else {
            $class_id = (int)$matched['class_id'];
            $ins = $conn->prepare("
                INSERT INTO assignments (class_id, subject_id, teacher_id, title, description, due_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->bind_param('iiisss', $class_id, $subject_id, $teacher_id, $title, $description, $due_date);
            if ($ins->execute()) {
                $stu_stmt = $conn->prepare("SELECT user_id FROM students WHERE class_id = ? AND user_id IS NOT NULL");
                $stu_stmt->bind_param('i', $class_id);
                $stu_stmt->execute();
                $stu_res = $stu_stmt->get_result();
                while ($stu = $stu_res->fetch_assoc()) {
                    create_notification(
                        $conn,
                        (int)$stu['user_id'],
                        'New Assignment',
                        $title . ' has been assigned.',
                        'student/assignments.php'
                    );
                }
                $stu_stmt->close();
                flash_set('success', 'Assignment created successfully.');
            } else {
                flash_set('danger', 'Could not create assignment.');
            }
            $ins->close();
        }
        redirect('teacher/assignments.php');
    }

    if ($action === 'delete') {
        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
        $del = $conn->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
        $del->bind_param('ii', $assignment_id, $teacher_id);
        $del->execute();
        $affected = $del->affected_rows;
        $del->close();
        flash_set($affected > 0 ? 'success' : 'danger', $affected > 0 ? 'Assignment deleted.' : 'Assignment not found.');
        redirect('teacher/assignments.php');
    }
}

$assignments = [];
$asg_stmt = $conn->prepare("
    SELECT a.id, a.title, a.description, a.due_date, a.created_at,
           c.class_name, c.section, s.subject_name,
           (SELECT COUNT(*) FROM students st WHERE st.class_id = a.class_id) AS total_students,
           (SELECT COUNT(*) FROM assignment_submissions subm WHERE subm.assignment_id = a.id AND subm.status = 'Submitted') AS submitted_count
    FROM assignments a
    INNER JOIN classes c ON c.id = a.class_id
    INNER JOIN subjects s ON s.id = a.subject_id
    WHERE a.teacher_id = ?
    ORDER BY a.due_date ASC, a.id DESC
");
$asg_stmt->bind_param('i', $teacher_id);
$asg_stmt->execute();
$asg_res = $asg_stmt->get_result();
while ($row = $asg_res->fetch_assoc()) {
    $assignments[] = $row;
}
$asg_stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Assignments</h4>
        <p class="text-muted small mb-0">Create and track homework for your classes.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal" <?php echo count($my_subjects) === 0 ? 'disabled' : ''; ?>>
        <i class="fa-solid fa-plus me-1"></i> New Assignment
    </button>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fa-solid fa-book-open me-2" style="color:var(--primary-color);"></i>My Assignments</span>
        <span class="badge-mint"><?php echo count($assignments); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (count($assignments) === 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-book-open"></i><p class="mb-0">No assignments yet</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th class="ps-3">Title</th>
                        <th>Class / Subject</th>
                        <th>Due</th>
                        <th>Submissions</th>
                        <th class="pe-3 text-end">Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $a):
                        $total = (int)$a['total_students'];
                        $submitted = (int)$a['submitted_count'];
                        $overdue = strtotime($a['due_date']) < strtotime(date('Y-m-d'));
                    ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold"><?php echo e($a['title']); ?></div>
                                <div class="small text-muted"><?php echo e(mb_strimwidth($a['description'], 0, 70, '…')); ?></div>
                            </td>
                            <td>
                                <span class="badge-mint"><?php echo e($a['class_name'] . ' · ' . $a['section']); ?></span>
                                <div class="small text-muted mt-1"><?php echo e($a['subject_name']); ?></div>
                            </td>
                            <td>
                                <?php echo e(date('M j, Y', strtotime($a['due_date']))); ?>
                                <?php if ($overdue): ?><div class="small text-danger">Overdue</div><?php endif; ?>
                            </td>
                            <td>
                                <span class="fw-semibold"><?php echo $submitted; ?></span>
                                <span class="text-muted">/ <?php echo $total; ?></span>
                                <div class="progress mt-1" style="height:6px;max-width:100px;">
                                    <div class="progress-bar bg-success" style="width:<?php echo $total > 0 ? round(($submitted / $total) * 100) : 0; ?>%;"></div>
                                </div>
                            </td>
                            <td class="pe-3 text-end">
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this assignment?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="assignment_id" value="<?php echo (int)$a['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="createAssignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background-color:var(--sidebar-bg);color:#fff;">
                <h6 class="modal-title fw-bold"><i class="fa-solid fa-plus me-2 text-success"></i>Create Assignment</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Class &amp; Subject <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select form-select-sm" required>
                            <option value="">Select…</option>
                            <?php foreach ($my_subjects as $s): ?>
                                <option value="<?php echo (int)$s['id']; ?>">
                                    <?php echo e($s['class_name'] . ' · ' . $s['section'] . ' — ' . $s['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control form-control-sm" rows="3" required></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold small">Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="due_date" class="form-control form-control-sm" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
