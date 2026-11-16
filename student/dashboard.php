<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'Student Dashboard';
require_once '../config/database.php';

$student_id = current_student_id($conn);
if ($student_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>Your student profile is not linked to this account. Please contact the school administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$school_name = get_setting($conn, 'school_name', 'CampusFlow Academy');
$academic_year = get_setting($conn, 'academic_year', date('Y'));
$uid = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

$sstmt = $conn->prepare("
    SELECT s.first_name, s.last_name, s.admission_number, s.class_id,
           c.class_name, c.section
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    WHERE s.id = ?
    LIMIT 1
");
$sstmt->bind_param('i', $student_id);
$sstmt->execute();
$student = $sstmt->get_result()->fetch_assoc();
$sstmt->close();

$display_name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$class_id = (int)($student['class_id'] ?? 0);
$class_label = trim(($student['class_name'] ?? '—') . ($student['section'] ? ' · ' . $student['section'] : ''));

$att_stmt = $conn->prepare("
    SELECT COUNT(*) AS total_days,
           SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) AS present_days
    FROM attendance WHERE student_id = ?
");
$att_stmt->bind_param('i', $student_id);
$att_stmt->execute();
$att = $att_stmt->get_result()->fetch_assoc();
$att_stmt->close();
$total_days = (int)$att['total_days'];
$present_days = (int)$att['present_days'];
$attendance_pct = $total_days > 0 ? round(($present_days / $total_days) * 100, 1) : 0.0;

$unread = unread_notification_count($conn, $uid);

$fee_stmt = $conn->prepare("
    SELECT COUNT(*) AS c FROM fee_payments
    WHERE student_id = ? AND payment_status IN ('Unpaid','Partial')
");
$fee_stmt->bind_param('i', $student_id);
$fee_stmt->execute();
$pending_fees = (int)$fee_stmt->get_result()->fetch_assoc()['c'];
$fee_stmt->close();

$exams = [];
if ($class_id > 0) {
    $ex_stmt = $conn->prepare("
        SELECT exam_name, exam_date, total_marks
        FROM exams
        WHERE class_id = ? AND exam_date >= ?
        ORDER BY exam_date ASC LIMIT 5
    ");
    $ex_stmt->bind_param('is', $class_id, $today);
    $ex_stmt->execute();
    $ex_res = $ex_stmt->get_result();
    while ($row = $ex_res->fetch_assoc()) {
        $exams[] = $row;
    }
    $ex_stmt->close();
}

$assignments = [];
if ($class_id > 0) {
    $asg_stmt = $conn->prepare("
        SELECT a.id, a.title, a.due_date, s.subject_name,
               subm.status AS submission_status
        FROM assignments a
        INNER JOIN subjects s ON s.id = a.subject_id
        LEFT JOIN assignment_submissions subm ON subm.assignment_id = a.id AND subm.student_id = ?
        WHERE a.class_id = ? AND a.due_date >= ?
        ORDER BY a.due_date ASC LIMIT 5
    ");
    $asg_stmt->bind_param('iis', $student_id, $class_id, $today);
    $asg_stmt->execute();
    $asg_res = $asg_stmt->get_result();
    while ($row = $asg_res->fetch_assoc()) {
        $assignments[] = $row;
    }
    $asg_stmt->close();
}

$notif_stmt = $conn->prepare("SELECT title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$notif_stmt->bind_param('i', $uid);
$notif_stmt->execute();
$notif_res = $notif_stmt->get_result();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="card welcome-banner mb-4 border-0">
    <div class="card-body p-4 position-relative" style="z-index:1;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <span class="badge badge-date mb-2"><?php echo e(date('l, F j, Y')); ?></span>
                <h3 class="mb-1" style="font-family:var(--font-display);">Welcome, <?php echo e($display_name); ?></h3>
                <p class="mb-0 opacity-90"><?php echo e($school_name); ?> · <?php echo e($class_label); ?> · <?php echo e($academic_year); ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($unread > 0): ?>
                    <a href="<?php echo url('student/notices.php'); ?>" class="badge bg-light text-dark text-decoration-none">
                        <i class="fa-solid fa-bell me-1"></i><?php echo $unread; ?> alerts
                    </a>
                <?php endif; ?>
                <?php if ($pending_fees > 0): ?>
                    <a href="<?php echo url('student/fees.php'); ?>" class="badge bg-warning text-dark text-decoration-none">
                        <?php echo $pending_fees; ?> fee due
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon success"><i class="fa-solid fa-calendar-check"></i></div>
            <div><div class="text-muted small">Attendance</div><div class="fs-4 fw-bold"><?php echo $attendance_pct; ?>%</div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon primary"><i class="fa-solid fa-award"></i></div>
            <div><div class="text-muted small">Upcoming Exams</div><div class="fs-4 fw-bold"><?php echo count($exams); ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon warning"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <div><div class="text-muted small">Pending Fees</div><div class="fs-4 fw-bold"><?php echo $pending_fees; ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon danger"><i class="fa-solid fa-bell"></i></div>
            <div><div class="text-muted small">Unread</div><div class="fs-4 fw-bold"><?php echo $unread; ?></div></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Upcoming Exams</span>
                <a href="<?php echo url('student/results.php'); ?>" class="small">Results</a>
            </div>
            <div class="card-body">
                <?php if (count($exams) === 0): ?>
                    <div class="empty-state py-3"><i class="fa-solid fa-award"></i><p class="mb-0">No upcoming exams</p></div>
                <?php else: foreach ($exams as $ex): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong><?php echo e($ex['exam_name']); ?></strong>
                            <div class="small text-muted"><?php echo e(number_format((float)$ex['total_marks'], 0)); ?> marks</div>
                        </div>
                        <span class="small text-muted"><?php echo e(date('M j', strtotime($ex['exam_date']))); ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Upcoming Assignments</span>
                <a href="<?php echo url('student/assignments.php'); ?>" class="small">View all</a>
            </div>
            <div class="card-body">
                <?php if (count($assignments) === 0): ?>
                    <div class="empty-state py-3"><i class="fa-solid fa-book-open"></i><p class="mb-0">No upcoming assignments</p></div>
                <?php else: foreach ($assignments as $a):
                    $done = ($a['submission_status'] ?? '') === 'Submitted';
                ?>
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                        <div>
                            <strong><?php echo e($a['title']); ?></strong>
                            <div class="small text-muted"><?php echo e($a['subject_name']); ?> · Due <?php echo e(date('M j', strtotime($a['due_date']))); ?></div>
                        </div>
                        <span class="status-pill <?php echo $done ? 'status-Submitted' : 'status-Pending'; ?>"><?php echo $done ? 'Submitted' : 'Pending'; ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Quick Links</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('student/attendance.php'); ?>"><i class="fa-solid fa-calendar-check"></i><span>Attendance</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('student/timetable.php'); ?>"><i class="fa-solid fa-table"></i><span>Timetable</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('student/assignments.php'); ?>"><i class="fa-solid fa-book-open"></i><span>Homework</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('student/results.php'); ?>"><i class="fa-solid fa-award"></i><span>Results</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('student/fees.php'); ?>"><i class="fa-solid fa-receipt"></i><span>Fees</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('student/notices.php'); ?>"><i class="fa-solid fa-bullhorn"></i><span>Notices</span></a></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Notifications</span>
                <a href="<?php echo url('student/notices.php'); ?>" class="small">View all</a>
            </div>
            <div class="card-body">
                <?php if ($notif_res->num_rows === 0): ?>
                    <div class="empty-state py-3"><i class="fa-regular fa-bell"></i><p class="mb-0">No notifications yet</p></div>
                <?php else: while ($n = $notif_res->fetch_assoc()): ?>
                    <div class="notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                        <strong><?php echo e($n['title']); ?></strong>
                        <div class="small text-muted"><?php echo e($n['message']); ?></div>
                    </div>
                <?php endwhile; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$notif_stmt->close();
include '../includes/footer.php';
?>
