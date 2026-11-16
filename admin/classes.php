<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Classes & Subjects';
require_once '../config/database.php';

$success_msg = $error_msg = '';
$filter_class = (int)($_GET['filter_class'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_class') {
            $class_name = trim($_POST['class_name'] ?? '');
            $section = trim($_POST['section'] ?? '');
            if ($class_name === '' || $section === '') {
                $error_msg = 'Class name and section are required.';
            } else {
                $chk = $conn->prepare("SELECT id FROM classes WHERE class_name=? AND section=?");
                $chk->bind_param('ss', $class_name, $section);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $error_msg = 'This class/section already exists.';
                } else {
                    $ins = $conn->prepare("INSERT INTO classes (class_name, section) VALUES (?,?)");
                    $ins->bind_param('ss', $class_name, $section);
                    $ins->execute();
                    $ins->close();
                    $success_msg = 'Class created.';
                }
                $chk->close();
            }
        } elseif ($action === 'edit_class') {
            $id = (int)($_POST['class_id'] ?? 0);
            $class_name = trim($_POST['class_name'] ?? '');
            $section = trim($_POST['section'] ?? '');
            $upd = $conn->prepare("UPDATE classes SET class_name=?, section=? WHERE id=?");
            $upd->bind_param('ssi', $class_name, $section, $id);
            if ($upd->execute()) $success_msg = 'Class updated.';
            else $error_msg = 'Could not update class (duplicate?).';
            $upd->close();
        } elseif ($action === 'delete_class') {
            $id = (int)($_POST['class_id'] ?? 0);
            $del = $conn->prepare("DELETE FROM classes WHERE id=?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            $success_msg = 'Class deleted.';
        } elseif ($action === 'add_subject' || $action === 'edit_subject') {
            $subject_name = trim($_POST['subject_name'] ?? '');
            $subject_code = strtoupper(trim($_POST['subject_code'] ?? ''));
            $class_id = (int)($_POST['class_id'] ?? 0);
            $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
            if ($subject_name === '' || $subject_code === '' || $class_id <= 0) {
                $error_msg = 'Subject name, code, and class are required.';
            } elseif ($action === 'add_subject') {
                if ($teacher_id === null) {
                    $ins = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, class_id, teacher_id) VALUES (?,?,?,NULL)");
                    $ins->bind_param('ssi', $subject_name, $subject_code, $class_id);
                } else {
                    $ins = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, class_id, teacher_id) VALUES (?,?,?,?)");
                    $ins->bind_param('ssii', $subject_name, $subject_code, $class_id, $teacher_id);
                }
                if ($ins->execute()) $success_msg = 'Subject added.';
                else $error_msg = 'Could not add subject (duplicate code?).';
                $ins->close();
            } else {
                $sid = (int)($_POST['subject_id'] ?? 0);
                if ($teacher_id === null) {
                    $upd = $conn->prepare("UPDATE subjects SET subject_name=?, subject_code=?, class_id=?, teacher_id=NULL WHERE id=?");
                    $upd->bind_param('ssii', $subject_name, $subject_code, $class_id, $sid);
                } else {
                    $upd = $conn->prepare("UPDATE subjects SET subject_name=?, subject_code=?, class_id=?, teacher_id=? WHERE id=?");
                    $upd->bind_param('ssiii', $subject_name, $subject_code, $class_id, $teacher_id, $sid);
                }
                if ($upd->execute()) $success_msg = 'Subject updated.';
                else $error_msg = 'Could not update subject.';
                $upd->close();
            }
        } elseif ($action === 'delete_subject') {
            $id = (int)($_POST['subject_id'] ?? 0);
            $del = $conn->prepare("DELETE FROM subjects WHERE id=?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            $success_msg = 'Subject deleted.';
        }
    }
}

$all_classes = $conn->query("
    SELECT c.*, COUNT(DISTINCT s.id) AS student_count, COUNT(DISTINCT sub.id) AS subject_count
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id
    LEFT JOIN subjects sub ON sub.class_id = c.id
    GROUP BY c.id ORDER BY c.class_name, c.section
")->fetch_all(MYSQLI_ASSOC);

$teachers = $conn->query("SELECT id, first_name, last_name FROM teachers WHERE status='Active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

if ($filter_class > 0) {
    $sub_stmt = $conn->prepare("
        SELECT sub.*, c.class_name, c.section, t.first_name AS t_fname, t.last_name AS t_lname
        FROM subjects sub
        INNER JOIN classes c ON c.id = sub.class_id
        LEFT JOIN teachers t ON t.id = sub.teacher_id
        WHERE sub.class_id = ?
        ORDER BY sub.subject_name
    ");
    $sub_stmt->bind_param('i', $filter_class);
    $sub_stmt->execute();
    $subjects = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sub_stmt->close();
} else {
    $subjects = $conn->query("
        SELECT sub.*, c.class_name, c.section, t.first_name AS t_fname, t.last_name AS t_lname
        FROM subjects sub
        INNER JOIN classes c ON c.id = sub.class_id
        LEFT JOIN teachers t ON t.id = sub.teacher_id
        ORDER BY c.class_name, sub.subject_name
    ")->fetch_all(MYSQLI_ASSOC);
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo e($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Classes</span>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">Add Class</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Class</th><th>Students</th><th>Subjects</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($all_classes as $c): ?>
                            <tr>
                                <td>
                                    <a href="?filter_class=<?php echo (int)$c['id']; ?>" class="fw-semibold text-decoration-none"><?php echo e($c['class_name']); ?></a>
                                    <div class="small text-muted"><?php echo e($c['section']); ?></div>
                                </td>
                                <td><?php echo (int)$c['student_count']; ?></td>
                                <td><?php echo (int)$c['subject_count']; ?></td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editClass<?php echo (int)$c['id']; ?>">Edit</button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete class and its subjects/timetable?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_class">
                                        <input type="hidden" name="class_id" value="<?php echo (int)$c['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <div class="modal fade" id="editClass<?php echo (int)$c['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                <form method="post"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_class">
                                    <input type="hidden" name="class_id" value="<?php echo (int)$c['id']; ?>">
                                    <div class="modal-header"><h5 class="modal-title">Edit Class</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-3"><label class="form-label">Class Name</label><input name="class_name" class="form-control" value="<?php echo e($c['class_name']); ?>" required></div>
                                        <div class="mb-3"><label class="form-label">Section</label><input name="section" class="form-control" value="<?php echo e($c['section']); ?>" required></div>
                                    </div>
                                    <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
                                </form>
                            </div></div></div>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Subjects</span>
                <div class="d-flex gap-2">
                    <form method="get">
                        <select name="filter_class" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="0">All classes</option>
                            <?php foreach ($all_classes as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_class===(int)$c['id']?'selected':''; ?>><?php echo e($c['class_name'].' · '.$c['section']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">Add Subject</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Subject</th><th>Class</th><th>Teacher</th><th></th></tr></thead>
                        <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-book"></i><p>No subjects</p></div></td></tr>
                        <?php else: foreach ($subjects as $sub): ?>
                            <tr>
                                <td><?php echo e($sub['subject_name']); ?><div class="small text-muted"><?php echo e($sub['subject_code']); ?></div></td>
                                <td><?php echo e($sub['class_name'].' · '.$sub['section']); ?></td>
                                <td><?php echo e(trim(($sub['t_fname']??'').' '.($sub['t_lname']??'')) ?: 'Unassigned'); ?></td>
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSub<?php echo (int)$sub['id']; ?>">Edit</button>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete subject?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_subject">
                                        <input type="hidden" name="subject_id" value="<?php echo (int)$sub['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <div class="modal fade" id="editSub<?php echo (int)$sub['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                <form method="post"><?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="edit_subject">
                                    <input type="hidden" name="subject_id" value="<?php echo (int)$sub['id']; ?>">
                                    <div class="modal-header"><h5 class="modal-title">Edit Subject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-3"><label class="form-label">Name</label><input name="subject_name" class="form-control" value="<?php echo e($sub['subject_name']); ?>" required></div>
                                        <div class="mb-3"><label class="form-label">Code</label><input name="subject_code" class="form-control" value="<?php echo e($sub['subject_code']); ?>" required></div>
                                        <div class="mb-3"><label class="form-label">Class</label>
                                            <select name="class_id" class="form-select"><?php foreach ($all_classes as $c): ?>
                                                <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$sub['class_id']===(int)$c['id']?'selected':''; ?>><?php echo e($c['class_name'].' · '.$c['section']); ?></option>
                                            <?php endforeach; ?></select>
                                        </div>
                                        <div class="mb-3"><label class="form-label">Teacher</label>
                                            <select name="teacher_id" class="form-select">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($teachers as $t): ?>
                                                    <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$sub['teacher_id']===(int)$t['id']?'selected':''; ?>><?php echo e($t['first_name'].' '.$t['last_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
                                </form>
                            </div></div></div>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addClassModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_class">
        <div class="modal-header"><h5 class="modal-title">Add Class</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Class Name</label><input name="class_name" class="form-control" placeholder="Grade 1" required></div>
            <div class="mb-3"><label class="form-label">Section</label><input name="section" class="form-control" placeholder="Section A" required></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Create</button></div>
    </form>
</div></div></div>

<div class="modal fade" id="addSubjectModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_subject">
        <div class="modal-header"><h5 class="modal-title">Add Subject</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Subject Name</label><input name="subject_name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Code</label><input name="subject_code" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Class</label>
                <select name="class_id" class="form-select" required>
                    <?php foreach ($all_classes as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['class_name'].' · '.$c['section']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Teacher</label>
                <select name="teacher_id" class="form-select"><option value="">Unassigned</option>
                    <?php foreach ($teachers as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo e($t['first_name'].' '.$t['last_name']); ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Add</button></div>
    </form>
</div></div></div>

<?php include '../includes/footer.php'; ?>
