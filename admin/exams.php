<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Examinations';
require_once '../config/database.php';

$success_msg = $error_msg = '';
$view_exam_id = (int)($_GET['exam_id'] ?? 0);
$view_subject_id = (int)($_GET['subject_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_exam') {
            $exam_name = trim($_POST['exam_name'] ?? '');
            $class_id = (int)($_POST['class_id'] ?? 0);
            $exam_date = $_POST['exam_date'] ?? '';
            $total_marks = (float)($_POST['total_marks'] ?? 100);
            if ($exam_name === '' || $class_id <= 0 || $exam_date === '' || $total_marks <= 0) {
                $error_msg = 'Please fill all exam fields.';
            } else {
                $stmt = $conn->prepare("INSERT INTO exams (exam_name, class_id, exam_date, total_marks) VALUES (?,?,?,?)");
                $stmt->bind_param('sisd', $exam_name, $class_id, $exam_date, $total_marks);
                if ($stmt->execute()) {
                    $success_msg = 'Exam created successfully.';
                    notify_role_users($conn, 'teacher', 'New Exam Scheduled', $exam_name . ' on ' . $exam_date, 'teacher/marks.php');
                } else {
                    $error_msg = 'Failed to create exam.';
                }
                $stmt->close();
            }
        } elseif ($action === 'delete_exam') {
            $id = (int)($_POST['exam_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM exams WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Exam deleted.';
        } elseif ($action === 'save_marks') {
            $exam_id = (int)($_POST['exam_id'] ?? 0);
            $subject_id = (int)($_POST['subject_id'] ?? 0);
            $marks_map = $_POST['marks'] ?? [];
            $exam = $conn->prepare("SELECT total_marks FROM exams WHERE id=?");
            $exam->bind_param('i', $exam_id);
            $exam->execute();
            $exam_row = $exam->get_result()->fetch_assoc();
            $exam->close();
            $max = $exam_row ? (float)$exam_row['total_marks'] : 100;
            $saved = 0;
            $ups = $conn->prepare("INSERT INTO marks (exam_id, student_id, subject_id, marks_obtained) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained)");
            foreach ($marks_map as $student_id => $val) {
                $sid = (int)$student_id;
                $obtained = max(0, min($max, (float)$val));
                $ups->bind_param('iiid', $exam_id, $sid, $subject_id, $obtained);
                if ($ups->execute()) $saved++;
            }
            $ups->close();
            $success_msg = "Saved marks for {$saved} student(s).";
            $view_exam_id = $exam_id;
            $view_subject_id = $subject_id;
        }
    }
}

$classes = $conn->query("SELECT id, class_name, section FROM classes ORDER BY class_name, section");
$exams = $conn->query("SELECT e.*, c.class_name, c.section FROM exams e INNER JOIN classes c ON c.id=e.class_id ORDER BY e.exam_date DESC");

$exam_detail = null;
$subjects = [];
$students_marks = [];
if ($view_exam_id > 0) {
    $es = $conn->prepare("SELECT e.*, c.class_name, c.section FROM exams e INNER JOIN classes c ON c.id=e.class_id WHERE e.id=?");
    $es->bind_param('i', $view_exam_id);
    $es->execute();
    $exam_detail = $es->get_result()->fetch_assoc();
    $es->close();
    if ($exam_detail) {
        $cid = (int)$exam_detail['class_id'];
        $subq = $conn->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE class_id=? ORDER BY subject_name");
        $subq->bind_param('i', $cid);
        $subq->execute();
        $subjects = $subq->get_result()->fetch_all(MYSQLI_ASSOC);
        $subq->close();
        if ($view_subject_id > 0) {
            $sq = $conn->prepare("
                SELECT s.id, s.first_name, s.last_name, s.admission_number, m.marks_obtained
                FROM students s
                LEFT JOIN marks m ON m.student_id = s.id AND m.exam_id = ? AND m.subject_id = ?
                WHERE s.class_id = ? AND s.status='Active'
                ORDER BY s.first_name
            ");
            $sq->bind_param('iii', $view_exam_id, $view_subject_id, $cid);
            $sq->execute();
            $students_marks = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
            $sq->close();
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo e($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Exams</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExamModal">Add Exam</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Name</th><th>Class</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                        <?php while ($ex = $exams->fetch_assoc()): ?>
                            <tr class="<?php echo (int)$ex['id']===$view_exam_id?'table-active':''; ?>">
                                <td>
                                    <a href="?exam_id=<?php echo (int)$ex['id']; ?>" class="fw-semibold text-decoration-none"><?php echo e($ex['exam_name']); ?></a>
                                    <div class="small text-muted">Max <?php echo (float)$ex['total_marks']; ?></div>
                                </td>
                                <td><?php echo e($ex['class_name'].' · '.$ex['section']); ?></td>
                                <td><?php echo e($ex['exam_date']); ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Delete exam and all marks?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_exam">
                                        <input type="hidden" name="exam_id" value="<?php echo (int)$ex['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Enter Marks</div>
            <div class="card-body">
                <?php if (!$exam_detail): ?>
                    <div class="empty-state"><i class="fa-solid fa-award"></i><p>Select an exam to enter marks</p></div>
                <?php else: ?>
                    <h6 class="mb-3"><?php echo e($exam_detail['exam_name']); ?> — <?php echo e($exam_detail['class_name'].' · '.$exam_detail['section']); ?></h6>
                    <form method="get" class="row g-2 mb-3">
                        <input type="hidden" name="exam_id" value="<?php echo $view_exam_id; ?>">
                        <div class="col-md-8">
                            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                                <option value="">Select subject</option>
                                <?php foreach ($subjects as $sub): ?>
                                    <option value="<?php echo (int)$sub['id']; ?>" <?php echo $view_subject_id===(int)$sub['id']?'selected':''; ?>>
                                        <?php echo e($sub['subject_name'].' ('.$sub['subject_code'].')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    <?php if ($view_subject_id > 0): ?>
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="save_marks">
                            <input type="hidden" name="exam_id" value="<?php echo $view_exam_id; ?>">
                            <input type="hidden" name="subject_id" value="<?php echo $view_subject_id; ?>">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Student</th><th>Admission</th><th>Marks</th><th>%</th><th>Grade</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($students_marks as $sm):
                                        $obt = $sm['marks_obtained'] !== null ? (float)$sm['marks_obtained'] : null;
                                        $pct = $obt !== null ? round(($obt / (float)$exam_detail['total_marks']) * 100, 1) : null;
                                    ?>
                                        <tr>
                                            <td><?php echo e($sm['first_name'].' '.$sm['last_name']); ?></td>
                                            <td><?php echo e($sm['admission_number']); ?></td>
                                            <td style="max-width:110px;">
                                                <input type="number" step="0.01" min="0" max="<?php echo (float)$exam_detail['total_marks']; ?>"
                                                       name="marks[<?php echo (int)$sm['id']; ?>]" class="form-control form-control-sm"
                                                       value="<?php echo $obt !== null ? e((string)$obt) : ''; ?>">
                                            </td>
                                            <td><?php echo $pct !== null ? $pct.'%' : '—'; ?></td>
                                            <td><?php echo $pct !== null ? e(letter_grade($pct)) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-primary">Save Marks</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addExamModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_exam">
            <div class="modal-header"><h5 class="modal-title">Create Exam</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Exam Name</label><input name="exam_name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Class</label>
                    <select name="class_id" class="form-select" required>
                        <option value="">Select</option>
                        <?php mysqli_data_seek($classes, 0); while ($c = $classes->fetch_assoc()): ?>
                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['class_name'].' · '.$c['section']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Date</label><input type="date" name="exam_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="col-6"><label class="form-label">Total Marks</label><input type="number" step="0.01" name="total_marks" class="form-control" value="100" required></div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Create</button></div>
        </form>
    </div></div>
</div>

<?php include '../includes/footer.php'; ?>
