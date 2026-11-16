<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'Enter Marks';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$exams = [];
$ex_stmt = $conn->prepare("
    SELECT DISTINCT e.id, e.exam_name, e.exam_date, e.total_marks, e.class_id,
           c.class_name, c.section
    FROM exams e
    INNER JOIN classes c ON c.id = e.class_id
    INNER JOIN subjects sub ON sub.class_id = e.class_id AND sub.teacher_id = ?
    ORDER BY e.exam_date DESC, e.exam_name ASC
");
$ex_stmt->bind_param('i', $teacher_id);
$ex_stmt->execute();
$ex_res = $ex_stmt->get_result();
while ($row = $ex_res->fetch_assoc()) {
    $exams[] = $row;
}
$ex_stmt->close();

$selected_exam_id = isset($_REQUEST['exam_id']) ? (int)$_REQUEST['exam_id'] : ($exams[0]['id'] ?? 0);
$selected_exam = null;
foreach ($exams as $ex) {
    if ((int)$ex['id'] === $selected_exam_id) {
        $selected_exam = $ex;
        break;
    }
}

$subjects = [];
if ($selected_exam) {
    $sub_stmt = $conn->prepare("
        SELECT id, subject_name, subject_code
        FROM subjects
        WHERE teacher_id = ? AND class_id = ?
        ORDER BY subject_name ASC
    ");
    $class_id = (int)$selected_exam['class_id'];
    $sub_stmt->bind_param('ii', $teacher_id, $class_id);
    $sub_stmt->execute();
    $sub_res = $sub_stmt->get_result();
    while ($row = $sub_res->fetch_assoc()) {
        $subjects[] = $row;
    }
    $sub_stmt->close();
}

$selected_subject_id = isset($_REQUEST['subject_id']) ? (int)$_REQUEST['subject_id'] : ($subjects[0]['id'] ?? 0);
$subject_ids = array_map(fn($s) => (int)$s['id'], $subjects);
if ($selected_subject_id > 0 && !in_array($selected_subject_id, $subject_ids, true) && count($subject_ids) > 0) {
    $selected_subject_id = $subject_ids[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_marks') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('teacher/marks.php');
    }

    $exam_id = (int)($_POST['exam_id'] ?? 0);
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $marks_input = $_POST['marks'] ?? [];

    $exam_ok = false;
    $exam_class = 0;
    $total_marks = 100.0;
    foreach ($exams as $ex) {
        if ((int)$ex['id'] === $exam_id) {
            $exam_ok = true;
            $exam_class = (int)$ex['class_id'];
            $total_marks = (float)$ex['total_marks'];
            break;
        }
    }

    $sub_ok = false;
    $chk = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ? AND class_id = ? LIMIT 1");
    $chk->bind_param('iii', $subject_id, $teacher_id, $exam_class);
    $chk->execute();
    $sub_ok = $chk->get_result()->num_rows > 0;
    $chk->close();

    if (!$exam_ok || !$sub_ok || !is_array($marks_input)) {
        flash_set('danger', 'Invalid exam or subject selection.');
        redirect('teacher/marks.php');
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            INSERT INTO marks (exam_id, student_id, subject_id, marks_obtained)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained)
        ");
        $saved = 0;
        foreach ($marks_input as $stu_id => $val) {
            $sid = (int)$stu_id;
            if ($sid <= 0) continue;
            $stu_chk = $conn->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? LIMIT 1");
            $stu_chk->bind_param('ii', $sid, $exam_class);
            $stu_chk->execute();
            $ok = $stu_chk->get_result()->num_rows > 0;
            $stu_chk->close();
            if (!$ok) continue;

            $marks_obtained = max(0, min($total_marks, (float)$val));
            $stmt->bind_param('iiid', $exam_id, $sid, $subject_id, $marks_obtained);
            $stmt->execute();
            $saved++;
        }
        $stmt->close();
        $conn->commit();
        flash_set('success', "Marks saved for {$saved} students.");
    } catch (Exception $e) {
        $conn->rollback();
        flash_set('danger', 'Could not save marks.');
    }
    redirect('teacher/marks.php?exam_id=' . $exam_id . '&subject_id=' . $subject_id);
}

$students = [];
$total_marks = $selected_exam ? (float)$selected_exam['total_marks'] : 100.0;
if ($selected_exam && $selected_subject_id > 0) {
    $class_id = (int)$selected_exam['class_id'];
    $st_stmt = $conn->prepare("
        SELECT s.id, s.admission_number, s.first_name, s.last_name,
               m.marks_obtained
        FROM students s
        LEFT JOIN marks m ON m.student_id = s.id AND m.exam_id = ? AND m.subject_id = ?
        WHERE s.class_id = ?
        ORDER BY s.first_name ASC, s.last_name ASC
    ");
    $st_stmt->bind_param('iii', $selected_exam_id, $selected_subject_id, $class_id);
    $st_stmt->execute();
    $st_res = $st_stmt->get_result();
    while ($row = $st_res->fetch_assoc()) {
        $students[] = $row;
    }
    $st_stmt->close();
}

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Enter Marks</h4>
        <p class="text-muted small mb-0">Record exam scores for subjects you teach.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold small mb-1">Exam</label>
                <select name="exam_id" class="form-select form-select-sm" onchange="this.form.submit()" required>
                    <?php if (count($exams) === 0): ?>
                        <option value="0">No exams available</option>
                    <?php else: foreach ($exams as $ex): ?>
                        <option value="<?php echo (int)$ex['id']; ?>" <?php echo $selected_exam_id === (int)$ex['id'] ? 'selected' : ''; ?>>
                            <?php echo e($ex['exam_name'] . ' — ' . $ex['class_name'] . ' · ' . $ex['section'] . ' (' . date('M j', strtotime($ex['exam_date'])) . ')'); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Subject</label>
                <select name="subject_id" class="form-select form-select-sm" required>
                    <?php if (count($subjects) === 0): ?>
                        <option value="0">No subjects</option>
                    <?php else: foreach ($subjects as $sub): ?>
                        <option value="<?php echo (int)$sub['id']; ?>" <?php echo $selected_subject_id === (int)$sub['id'] ? 'selected' : ''; ?>>
                            <?php echo e($sub['subject_name'] . ' (' . $sub['subject_code'] . ')'); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary btn-sm py-2"><i class="fa-solid fa-list me-1"></i> Load students</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fa-solid fa-pen-to-square me-2" style="color:var(--primary-color);"></i>Marks Entry</span>
        <?php if ($selected_exam): ?>
            <span class="text-muted small">Total: <?php echo e(number_format($total_marks, 0)); ?> marks</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (count($students) === 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-award"></i><p class="mb-0">Select an exam and subject to enter marks</p></div>
        <?php else: ?>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_marks">
                <input type="hidden" name="exam_id" value="<?php echo (int)$selected_exam_id; ?>">
                <input type="hidden" name="subject_id" value="<?php echo (int)$selected_subject_id; ?>">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th class="ps-3">Admission</th>
                            <th>Student</th>
                            <th>Marks</th>
                            <th>%</th>
                            <th class="pe-3">Grade</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($students as $stu):
                            $obtained = $stu['marks_obtained'] !== null ? (float)$stu['marks_obtained'] : null;
                            $pct = $obtained !== null && $total_marks > 0 ? round(($obtained / $total_marks) * 100, 1) : null;
                            $grade = $pct !== null ? letter_grade($pct) : '—';
                        ?>
                            <tr>
                                <td class="ps-3"><?php echo e($stu['admission_number']); ?></td>
                                <td class="fw-semibold"><?php echo e($stu['first_name'] . ' ' . $stu['last_name']); ?></td>
                                <td style="max-width:120px;">
                                    <input type="number" step="0.01" min="0" max="<?php echo e($total_marks); ?>"
                                           class="form-control form-control-sm marks-input"
                                           name="marks[<?php echo (int)$stu['id']; ?>]"
                                           value="<?php echo $obtained !== null ? e((string)$obtained) : ''; ?>"
                                           data-total="<?php echo e($total_marks); ?>" required>
                                </td>
                                <td class="pct-cell"><?php echo $pct !== null ? e((string)$pct) . '%' : '—'; ?></td>
                                <td class="pe-3 grade-cell"><span class="status-pill <?php echo $pct !== null && $pct >= 50 ? 'status-Approved' : 'status-Rejected'; ?>"><?php echo e($grade); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light border-top text-end">
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-2"></i>Save Marks</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.marks-input').forEach(function (input) {
    input.addEventListener('input', function () {
        var total = parseFloat(this.dataset.total) || 100;
        var val = parseFloat(this.value);
        var row = this.closest('tr');
        var pctCell = row.querySelector('.pct-cell');
        var gradeCell = row.querySelector('.grade-cell');
        if (isNaN(val)) {
            pctCell.textContent = '—';
            gradeCell.innerHTML = '<span class="status-pill status-Pending">—</span>';
            return;
        }
        var pct = Math.round((val / total) * 1000) / 10;
        var grade = pct >= 90 ? 'A+' : pct >= 80 ? 'A' : pct >= 70 ? 'B' : pct >= 60 ? 'C' : pct >= 50 ? 'D' : 'F';
        pctCell.textContent = pct + '%';
        gradeCell.innerHTML = '<span class="status-pill ' + (pct >= 50 ? 'status-Approved' : 'status-Rejected') + '">' + grade + '</span>';
    });
});
</script>

<?php include '../includes/footer.php'; ?>
