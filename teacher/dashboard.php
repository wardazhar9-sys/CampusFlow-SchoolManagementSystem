<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'Teacher Dashboard';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$school_name = get_setting($conn, 'school_name', 'CampusFlow Academy');
$academic_year = get_setting($conn, 'academic_year', date('Y'));
$uid = (int)$_SESSION['user_id'];
$today = date('Y-m-d');
$day_name = date('l');

$tstmt = $conn->prepare("SELECT first_name, last_name FROM teachers WHERE id = ? LIMIT 1");
$tstmt->bind_param('i', $teacher_id);
$tstmt->execute();
$teacher = $tstmt->get_result()->fetch_assoc();
$tstmt->close();
$display_name = trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? ''));
if ($display_name === '') {
    $display_name = ucfirst($_SESSION['username'] ?? 'Teacher');
}

$subj_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM subjects WHERE teacher_id = ?");
$subj_stmt->bind_param('i', $teacher_id);
$subj_stmt->execute();
$subject_count = (int)$subj_stmt->get_result()->fetch_assoc()['c'];
$subj_stmt->close();

$class_stmt = $conn->prepare("SELECT COUNT(DISTINCT class_id) AS c FROM subjects WHERE teacher_id = ?");
$class_stmt->bind_param('i', $teacher_id);
$class_stmt->execute();
$class_count = (int)$class_stmt->get_result()->fetch_assoc()['c'];
$class_stmt->close();

$stu_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT st.id) AS c
    FROM students st
    INNER JOIN subjects sub ON sub.class_id = st.class_id
    WHERE sub.teacher_id = ?
");
$stu_stmt->bind_param('i', $teacher_id);
$stu_stmt->execute();
$student_count = (int)$stu_stmt->get_result()->fetch_assoc()['c'];
$stu_stmt->close();

$asg_stmt = $conn->prepare("SELECT COUNT(*) AS c FROM assignments WHERE teacher_id = ?");
$asg_stmt->bind_param('i', $teacher_id);
$asg_stmt->execute();
$assignment_count = (int)$asg_stmt->get_result()->fetch_assoc()['c'];
$asg_stmt->close();

$unread = unread_notification_count($conn, $uid);

$leave_stmt = $conn->prepare("SELECT status, from_date, to_date FROM leave_requests WHERE teacher_id = ? ORDER BY id DESC LIMIT 1");
$leave_stmt->bind_param('i', $teacher_id);
$leave_stmt->execute();
$pending_leave = $leave_stmt->get_result()->fetch_assoc();
$leave_stmt->close();

$tt_stmt = $conn->prepare("
    SELECT t.period_no, t.start_time, t.end_time, t.day_of_week,
           c.class_name, c.section, s.subject_name
    FROM timetable t
    INNER JOIN classes c ON c.id = t.class_id
    INNER JOIN subjects s ON s.id = t.subject_id
    WHERE t.teacher_id = ? AND t.day_of_week = ?
    ORDER BY t.period_no ASC
");
$tt_stmt->bind_param('is', $teacher_id, $day_name);
$tt_stmt->execute();
$today_tt = $tt_stmt->get_result();

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
                <p class="mb-0 opacity-90"><?php echo e($school_name); ?> · Academic Year <?php echo e($academic_year); ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($unread > 0): ?>
                    <a href="<?php echo url('teacher/notices.php'); ?>" class="badge bg-light text-dark text-decoration-none">
                        <i class="fa-solid fa-bell me-1"></i><?php echo $unread; ?> alerts
                    </a>
                <?php endif; ?>
                <?php if ($pending_leave): ?>
                    <a href="<?php echo url('teacher/leaves.php'); ?>" class="badge bg-warning text-dark text-decoration-none">
                        Leave: <?php echo e($pending_leave['status']); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon primary"><i class="fa-solid fa-book"></i></div>
            <div><div class="text-muted small">Subjects</div><div class="fs-4 fw-bold"><?php echo $subject_count; ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon success"><i class="fa-solid fa-school"></i></div>
            <div><div class="text-muted small">Classes</div><div class="fs-4 fw-bold"><?php echo $class_count; ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon warning"><i class="fa-solid fa-user-graduate"></i></div>
            <div><div class="text-muted small">Students</div><div class="fs-4 fw-bold"><?php echo $student_count; ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon danger"><i class="fa-solid fa-book-open"></i></div>
            <div><div class="text-muted small">Assignments</div><div class="fs-4 fw-bold"><?php echo $assignment_count; ?></div></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Quick Links</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('teacher/attendance.php'); ?>"><i class="fa-solid fa-clipboard-check"></i><span>Attendance</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('teacher/marks.php'); ?>"><i class="fa-solid fa-pen-to-square"></i><span>Marks</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('teacher/assignments.php'); ?>"><i class="fa-solid fa-book-open"></i><span>Assignments</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('teacher/timetable.php'); ?>"><i class="fa-solid fa-table"></i><span>Timetable</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('teacher/leaves.php'); ?>"><i class="fa-solid fa-plane-departure"></i><span>Leave</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('teacher/classes.php'); ?>"><i class="fa-solid fa-users"></i><span>Classes</span></a></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span>Today's Timetable</span>
                <a href="<?php echo url('teacher/timetable.php'); ?>" class="small">Full week</a>
            </div>
            <div class="card-body p-0">
                <?php if ($today_tt->num_rows === 0): ?>
                    <div class="empty-state py-4"><i class="fa-regular fa-calendar"></i><p class="mb-0">No periods scheduled for today</p></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Period</th><th>Time</th><th>Class</th><th>Subject</th></tr></thead>
                            <tbody>
                            <?php while ($row = $today_tt->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo (int)$row['period_no']; ?></td>
                                    <td><?php echo e(substr($row['start_time'], 0, 5) . '–' . substr($row['end_time'], 0, 5)); ?></td>
                                    <td><?php echo e($row['class_name'] . ' · ' . $row['section']); ?></td>
                                    <td><?php echo e($row['subject_name']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Notifications</span>
                <a href="<?php echo url('teacher/notices.php'); ?>" class="small">View all</a>
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
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">Leave Status</div>
            <div class="card-body">
                <?php if (!$pending_leave): ?>
                    <div class="empty-state py-3"><i class="fa-solid fa-plane-departure"></i><p class="mb-0">No leave requests yet</p></div>
                <?php else:
                    $status = $pending_leave['status'];
                ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?php echo e(date('M j', strtotime($pending_leave['from_date'])) . ' – ' . date('M j, Y', strtotime($pending_leave['to_date']))); ?></div>
                            <div class="small text-muted">Most recent request</div>
                        </div>
                        <span class="status-pill status-<?php echo e($status); ?>"><?php echo e($status); ?></span>
                    </div>
                    <a href="<?php echo url('teacher/leaves.php'); ?>" class="btn btn-sm btn-outline-primary mt-3">Manage leave</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$tt_stmt->close();
$notif_stmt->close();
include '../includes/footer.php';
?>
