<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'Attendance';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$all_classes = [];
$cls_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.class_name, c.section
    FROM classes c
    INNER JOIN subjects sub ON sub.class_id = c.id
    WHERE sub.teacher_id = ?
    ORDER BY c.class_name ASC, c.section ASC
");
$cls_stmt->bind_param('i', $teacher_id);
$cls_stmt->execute();
$cls_res = $cls_stmt->get_result();
while ($c = $cls_res->fetch_assoc()) {
    $all_classes[] = $c;
}
$cls_stmt->close();

$allowed_ids = array_map(fn($c) => (int)$c['id'], $all_classes);
$selected_class_id = isset($_REQUEST['class_id']) ? (int)$_REQUEST['class_id'] : ($allowed_ids[0] ?? 0);
$selected_date = isset($_REQUEST['attendance_date']) && $_REQUEST['attendance_date'] !== ''
    ? trim($_REQUEST['attendance_date'])
    : date('Y-m-d');

if ($selected_class_id > 0 && !in_array($selected_class_id, $allowed_ids, true)) {
    flash_set('danger', 'You can only mark attendance for your assigned classes.');
    redirect('teacher/attendance.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_attendance') {
    if (!verify_csrf()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('teacher/attendance.php');
    }

    $post_class_id = (int)($_POST['class_id'] ?? 0);
    $post_date = trim($_POST['attendance_date'] ?? '');
    $statuses = $_POST['status'] ?? [];

    if (!in_array($post_class_id, $allowed_ids, true) || $post_date === '') {
        flash_set('danger', 'Invalid class or date.');
        redirect('teacher/attendance.php');
    }

    if (!is_array($statuses) || count($statuses) === 0) {
        flash_set('danger', 'No students to mark.');
        redirect('teacher/attendance.php?class_id=' . $post_class_id . '&attendance_date=' . urlencode($post_date));
    }

    $allowed_statuses = ['Present', 'Absent', 'Late', 'Leave'];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            INSERT INTO attendance (student_id, class_id, attendance_date, status)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE status = VALUES(status), class_id = VALUES(class_id)
        ");
        $saved = 0;
        foreach ($statuses as $stu_id => $stu_status) {
            $sid = (int)$stu_id;
            if ($sid > 0 && in_array($stu_status, $allowed_statuses, true)) {
                $chk = $conn->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? LIMIT 1");
                $chk->bind_param('ii', $sid, $post_class_id);
                $chk->execute();
                $ok = $chk->get_result()->num_rows > 0;
                $chk->close();
                if ($ok) {
                    $stmt->bind_param('iiss', $sid, $post_class_id, $post_date, $stu_status);
                    $stmt->execute();
                    $saved++;
                }
            }
        }
        $stmt->close();
        $conn->commit();
        flash_set('success', "Attendance saved for {$saved} students.");
    } catch (Exception $e) {
        $conn->rollback();
        flash_set('danger', 'Could not save attendance.');
    }
    redirect('teacher/attendance.php?class_id=' . $post_class_id . '&attendance_date=' . urlencode($post_date));
}

$students_list = [];
$count_present = $count_absent = $count_late = $count_leave = 0;
$is_already_marked = false;

if ($selected_class_id > 0) {
    $sheet_stmt = $conn->prepare("
        SELECT s.id, s.admission_number, s.first_name, s.last_name, s.photo,
               a.status AS date_status
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ?
        WHERE s.class_id = ?
        ORDER BY s.first_name ASC, s.last_name ASC
    ");
    $sheet_stmt->bind_param('si', $selected_date, $selected_class_id);
    $sheet_stmt->execute();
    $sheet_res = $sheet_stmt->get_result();
    while ($row = $sheet_res->fetch_assoc()) {
        if (!empty($row['date_status'])) {
            $is_already_marked = true;
            if ($row['date_status'] === 'Present') $count_present++;
            elseif ($row['date_status'] === 'Absent') $count_absent++;
            elseif ($row['date_status'] === 'Late') $count_late++;
            elseif ($row['date_status'] === 'Leave') $count_leave++;
        }
        $students_list[] = $row;
    }
    $sheet_stmt->close();
}

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Daily Attendance</h4>
        <p class="text-muted small mb-0">Mark attendance for classes you teach.</p>
    </div>
    <?php if ($is_already_marked): ?>
        <span class="badge-mint px-3 py-2"><i class="fa-solid fa-circle-check me-1"></i> Recorded for <?php echo e(date('M d, Y', strtotime($selected_date))); ?></span>
    <?php endif; ?>
</div>

<div class="card mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold small mb-1">Class</label>
                <select name="class_id" class="form-select form-select-sm" required>
                    <?php if (count($all_classes) === 0): ?>
                        <option value="0">No assigned classes</option>
                    <?php else: foreach ($all_classes as $cls): ?>
                        <option value="<?php echo (int)$cls['id']; ?>" <?php echo $selected_class_id === (int)$cls['id'] ? 'selected' : ''; ?>>
                            <?php echo e($cls['class_name'] . ' — ' . $cls['section']); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Date</label>
                <input type="date" name="attendance_date" class="form-control form-control-sm" value="<?php echo e($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary btn-sm py-2"><i class="fa-solid fa-calendar-day me-1"></i> Load sheet</button>
            </div>
        </form>
    </div>
</div>

<?php if (count($students_list) > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-success"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Present</small><h4 class="fw-bold mb-0"><?php echo $count_present; ?></h4></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-danger"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Absent</small><h4 class="fw-bold mb-0"><?php echo $count_absent; ?></h4></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-warning"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Late</small><h4 class="fw-bold mb-0"><?php echo $count_late; ?></h4></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-info"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Leave</small><h4 class="fw-bold mb-0"><?php echo $count_leave; ?></h4></div></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-clipboard-user me-2" style="color:var(--primary-color);"></i>Attendance Sheet <span class="text-muted fw-normal small">(<?php echo count($students_list); ?>)</span></span>
        <?php if (count($students_list) > 0): ?>
            <button type="button" class="btn btn-sm btn-outline-success" id="markAllPresentBtn"><i class="fa-solid fa-check-double me-1"></i> Mark all present</button>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (count($students_list) === 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-users-slash"></i><p class="mb-0">No students in this class</p></div>
        <?php else: ?>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="class_id" value="<?php echo (int)$selected_class_id; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo e($selected_date); ?>">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th class="ps-3">Adm No.</th>
                            <th>Student</th>
                            <th class="pe-3 text-end">Status</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($students_list as $stu):
                            $sid = (int)$stu['id'];
                            $full = $stu['first_name'] . ' ' . $stu['last_name'];
                            $current = !empty($stu['date_status']) ? $stu['date_status'] : 'Present';
                        ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?php echo e($stu['admission_number']); ?></td>
                                <td class="fw-semibold"><?php echo e($full); ?></td>
                                <td class="pe-3 text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <input type="radio" class="btn-check status-present-radio" name="status[<?php echo $sid; ?>]" id="pres_<?php echo $sid; ?>" value="Present" <?php echo $current === 'Present' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success" for="pres_<?php echo $sid; ?>">Present</label>
                                        <input type="radio" class="btn-check" name="status[<?php echo $sid; ?>]" id="abs_<?php echo $sid; ?>" value="Absent" <?php echo $current === 'Absent' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-danger" for="abs_<?php echo $sid; ?>">Absent</label>
                                        <input type="radio" class="btn-check" name="status[<?php echo $sid; ?>]" id="late_<?php echo $sid; ?>" value="Late" <?php echo $current === 'Late' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-warning" for="late_<?php echo $sid; ?>">Late</label>
                                        <input type="radio" class="btn-check" name="status[<?php echo $sid; ?>]" id="leave_<?php echo $sid; ?>" value="Leave" <?php echo $current === 'Leave' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-info" for="leave_<?php echo $sid; ?>">Leave</label>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 bg-light border-top text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-floppy-disk me-2"></i><?php echo $is_already_marked ? 'Update Attendance' : 'Save Attendance'; ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('markAllPresentBtn')?.addEventListener('click', function () {
    document.querySelectorAll('.status-present-radio').forEach(function (r) { r.checked = true; });
});
</script>

<?php include '../includes/footer.php'; ?>
