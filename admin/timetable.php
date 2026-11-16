<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Timetable';
require_once '../config/database.php';

$success_msg = $error_msg = '';
$filter_class = (int)($_GET['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_slot') {
            $class_id = (int)($_POST['class_id'] ?? 0);
            $day = $_POST['day_of_week'] ?? '';
            $period_no = (int)($_POST['period_no'] ?? 0);
            $subject_id = (int)($_POST['subject_id'] ?? 0);
            $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';
            $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            if ($class_id <= 0 || !in_array($day, $days, true) || $period_no <= 0 || $subject_id <= 0 || $start_time === '' || $end_time === '') {
                $error_msg = 'Please fill all timetable fields.';
            } else {
                if ($teacher_id === null) {
                    $stmt = $conn->prepare("INSERT INTO timetable (class_id, day_of_week, period_no, subject_id, teacher_id, start_time, end_time) VALUES (?,?,?,?,NULL,?,?)");
                    $stmt->bind_param('isiiss', $class_id, $day, $period_no, $subject_id, $start_time, $end_time);
                } else {
                    $stmt = $conn->prepare("INSERT INTO timetable (class_id, day_of_week, period_no, subject_id, teacher_id, start_time, end_time) VALUES (?,?,?,?,?,?,?)");
                    $stmt->bind_param('isiisss', $class_id, $day, $period_no, $subject_id, $teacher_id, $start_time, $end_time);
                }
                if ($stmt->execute()) {
                    $success_msg = 'Timetable slot added.';
                    $filter_class = $class_id;
                } else {
                    $error_msg = 'Could not add slot (duplicate period for that day?).';
                }
                $stmt->close();
            }
        } elseif ($action === 'delete_slot') {
            $id = (int)($_POST['slot_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM timetable WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Slot removed.';
        }
    }
}

$classes = $conn->query("SELECT id, class_name, section FROM classes ORDER BY class_name, section")->fetch_all(MYSQLI_ASSOC);
if ($filter_class <= 0 && !empty($classes)) {
    $filter_class = (int)$classes[0]['id'];
}

$subjects = [];
$teachers = $conn->query("SELECT id, first_name, last_name FROM teachers WHERE status='Active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
if ($filter_class > 0) {
    $ss = $conn->prepare("SELECT id, subject_name, subject_code, teacher_id FROM subjects WHERE class_id=? ORDER BY subject_name");
    $ss->bind_param('i', $filter_class);
    $ss->execute();
    $subjects = $ss->get_result()->fetch_all(MYSQLI_ASSOC);
    $ss->close();
}

$slots = [];
if ($filter_class > 0) {
    $tq = $conn->prepare("
        SELECT tt.*, sub.subject_name, t.first_name, t.last_name
        FROM timetable tt
        INNER JOIN subjects sub ON sub.id = tt.subject_id
        LEFT JOIN teachers t ON t.id = tt.teacher_id
        WHERE tt.class_id = ?
        ORDER BY FIELD(tt.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), tt.period_no
    ");
    $tq->bind_param('i', $filter_class);
    $tq->execute();
    $slots = $tq->get_result()->fetch_all(MYSQLI_ASSOC);
    $tq->close();
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo e($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <form method="get" class="d-flex gap-2">
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php foreach ($classes as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_class===(int)$c['id']?'selected':''; ?>>
                    <?php echo e($c['class_name'].' · '.$c['section']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSlotModal">Add Period</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Day</th><th>Period</th><th>Time</th><th>Subject</th><th>Teacher</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($slots)): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-table"></i><p>No timetable slots for this class</p></div></td></tr>
                <?php else: foreach ($slots as $sl): ?>
                    <tr>
                        <td><?php echo e($sl['day_of_week']); ?></td>
                        <td><?php echo (int)$sl['period_no']; ?></td>
                        <td><?php echo e(substr($sl['start_time'],0,5).' – '.substr($sl['end_time'],0,5)); ?></td>
                        <td><?php echo e($sl['subject_name']); ?></td>
                        <td><?php echo e(trim(($sl['first_name']??'').' '.($sl['last_name']??'')) ?: '—'); ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Remove this slot?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_slot">
                                <input type="hidden" name="slot_id" value="<?php echo (int)$sl['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addSlotModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_slot">
            <div class="modal-header"><h5 class="modal-title">Add Timetable Period</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Class</label>
                    <select name="class_id" class="form-select" required>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_class===(int)$c['id']?'selected':''; ?>><?php echo e($c['class_name'].' · '.$c['section']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="form-label">Day</label>
                        <select name="day_of_week" class="form-select"><?php foreach ($days as $d): ?><option><?php echo $d; ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-6"><label class="form-label">Period No</label><input type="number" min="1" max="12" name="period_no" class="form-control" value="1" required></div>
                </div>
                <div class="mb-3"><label class="form-label">Subject</label>
                    <select name="subject_id" class="form-select" required>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?php echo (int)$sub['id']; ?>"><?php echo e($sub['subject_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Teacher (optional)</label>
                    <select name="teacher_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>"><?php echo e($t['first_name'].' '.$t['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Start</label><input type="time" name="start_time" class="form-control" value="08:00" required></div>
                    <div class="col-6"><label class="form-label">End</label><input type="time" name="end_time" class="form-control" value="08:45" required></div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Add</button></div>
        </form>
    </div></div>
</div>

<?php include '../includes/footer.php'; ?>
