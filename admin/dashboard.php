<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Admin Dashboard';
require_once '../config/database.php';

$school_name = get_setting($conn, 'school_name', 'CampusFlow Academy');
$academic_year = get_setting($conn, 'academic_year', date('Y'));

$student_count = (int)$conn->query("SELECT COUNT(*) AS t FROM students")->fetch_assoc()['t'];
$teacher_count = (int)$conn->query("SELECT COUNT(*) AS t FROM teachers")->fetch_assoc()['t'];
$class_count = (int)$conn->query("SELECT COUNT(*) AS t FROM classes")->fetch_assoc()['t'];

$fee_data = $conn->query("SELECT COALESCE(SUM(amount_paid),0) AS collected, COALESCE(SUM(total_amount - amount_paid),0) AS pending FROM fee_payments")->fetch_assoc();
$total_fee_collected = (float)$fee_data['collected'];
$total_fee_pending = (float)$fee_data['pending'];

$today = date('Y-m-d');
$att_stmt = $conn->prepare("SELECT COUNT(*) AS total_marked, SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) AS total_present FROM attendance WHERE attendance_date = ?");
$att_stmt->bind_param('s', $today);
$att_stmt->execute();
$att_data = $att_stmt->get_result()->fetch_assoc();
$att_stmt->close();
$total_marked = (int)$att_data['total_marked'];
$total_present = (int)$att_data['total_present'];
$attendance_percentage = $total_marked > 0 ? round(($total_present / $total_marked) * 100, 1) : 0.0;

$pending_leaves = (int)$conn->query("SELECT COUNT(*) AS t FROM leave_requests WHERE status='Pending'")->fetch_assoc()['t'];
$unread = unread_notification_count($conn, (int)$_SESSION['user_id']);

$recent_students = $conn->query("
    SELECT s.admission_number, s.first_name, s.last_name, s.gender, s.created_at, c.class_name, c.section
    FROM students s LEFT JOIN classes c ON s.class_id = c.id
    ORDER BY s.id DESC LIMIT 5
");

$notices = $conn->query("SELECT title, description, notice_type, event_date FROM notices ORDER BY event_date ASC LIMIT 4");

$notifications = $conn->prepare("SELECT title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$uid = (int)$_SESSION['user_id'];
$notifications->bind_param('i', $uid);
$notifications->execute();
$notif_res = $notifications->get_result();

$chart_labels = [];
$chart_values = [];
$chart_res = $conn->query("
    SELECT CONCAT(c.class_name, ' - ', c.section) AS class_label, COUNT(s.id) AS student_total
    FROM classes c LEFT JOIN students s ON s.class_id = c.id
    GROUP BY c.id ORDER BY c.class_name ASC LIMIT 8
");
while ($row = $chart_res->fetch_assoc()) {
    $chart_labels[] = $row['class_label'];
    $chart_values[] = (int)$row['student_total'];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="card welcome-banner mb-4 border-0">
    <div class="card-body p-4 position-relative" style="z-index:1;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <span class="badge badge-date mb-2"><?php echo e(date('l, F j, Y')); ?></span>
                <h3 class="mb-1" style="font-family:var(--font-display);">Welcome, <?php echo e(ucfirst($_SESSION['username'])); ?></h3>
                <p class="mb-0 opacity-90"><?php echo e($school_name); ?> · Academic Year <?php echo e($academic_year); ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($unread > 0): ?>
                    <span class="badge bg-light text-dark"><i class="fa-solid fa-bell me-1"></i><?php echo $unread; ?> alerts</span>
                <?php endif; ?>
                <?php if ($pending_leaves > 0): ?>
                    <a href="<?php echo url('admin/leaves.php'); ?>" class="badge bg-warning text-dark text-decoration-none"><?php echo $pending_leaves; ?> leave pending</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon primary"><i class="fa-solid fa-user-graduate"></i></div>
            <div><div class="text-muted small">Students</div><div class="fs-4 fw-bold"><?php echo $student_count; ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon success"><i class="fa-solid fa-chalkboard-user"></i></div>
            <div><div class="text-muted small">Teachers</div><div class="fs-4 fw-bold"><?php echo $teacher_count; ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon warning"><i class="fa-solid fa-school"></i></div>
            <div><div class="text-muted small">Classes</div><div class="fs-4 fw-bold"><?php echo $class_count; ?></div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card"><div class="stat-card">
            <div class="stat-icon danger"><i class="fa-solid fa-calendar-check"></i></div>
            <div><div class="text-muted small">Today Attendance</div><div class="fs-4 fw-bold"><?php echo $attendance_percentage; ?>%</div></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between">
                <span>Fees Snapshot</span>
                <a href="<?php echo url('admin/fees.php'); ?>" class="small">Manage</a>
            </div>
            <div class="card-body d-flex justify-content-around text-center">
                <div>
                    <div class="text-muted small">Collected</div>
                    <div class="fs-5 fw-bold text-success">Rs <?php echo number_format($total_fee_collected, 0); ?></div>
                </div>
                <div>
                    <div class="text-muted small">Pending</div>
                    <div class="fs-5 fw-bold text-warning">Rs <?php echo number_format($total_fee_pending, 0); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Quick Actions</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('admin/students.php'); ?>"><i class="fa-solid fa-user-plus"></i><span>Students</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('admin/attendance.php'); ?>"><i class="fa-solid fa-clipboard-check"></i><span>Attendance</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('admin/fees.php'); ?>"><i class="fa-solid fa-receipt"></i><span>Fees</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('admin/exams.php'); ?>"><i class="fa-solid fa-award"></i><span>Exams</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('admin/notices.php'); ?>"><i class="fa-solid fa-bullhorn"></i><span>Notices</span></a></div>
                    <div class="col-4"><a class="shortcut-btn w-100" href="<?php echo url('admin/timetable.php'); ?>"><i class="fa-solid fa-table"></i><span>Timetable</span></a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Class Enrollment</div>
            <div class="card-body"><canvas id="classChart" height="220"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Notifications</span>
                <a href="<?php echo url('admin/leaves.php'); ?>" class="small">View</a>
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
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Recent Students</span>
                <a href="<?php echo url('admin/students.php'); ?>" class="small">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Admission</th><th>Name</th><th>Class</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php while ($s = $recent_students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo e($s['admission_number']); ?></td>
                                <td><?php echo e($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                <td><?php echo e(($s['class_name'] ?? '—') . ($s['section'] ? ' · ' . $s['section'] : '')); ?></td>
                                <td><?php echo e(date('M j', strtotime($s['created_at']))); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Upcoming Notices</span>
                <a href="<?php echo url('admin/notices.php'); ?>" class="small">Manage</a>
            </div>
            <div class="card-body">
                <?php while ($n = $notices->fetch_assoc()): ?>
                    <div class="d-flex justify-content-between align-items-start border-bottom py-2">
                        <div>
                            <span class="badge-mint me-1"><?php echo e($n['notice_type']); ?></span>
                            <strong><?php echo e($n['title']); ?></strong>
                            <div class="small text-muted"><?php echo e(mb_strimwidth($n['description'], 0, 90, '…')); ?></div>
                        </div>
                        <span class="small text-muted text-nowrap"><?php echo e(date('M j', strtotime($n['event_date']))); ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('classChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Students',
            data: <?php echo json_encode($chart_values); ?>,
            backgroundColor: '#0D9488',
            borderRadius: 6
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
