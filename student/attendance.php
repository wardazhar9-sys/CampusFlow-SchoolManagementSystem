<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'My Attendance';
require_once '../config/database.php';

$student_id = current_student_id($conn);
if ($student_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>Your student profile is not linked to this account. Please contact the school administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$stats_stmt = $conn->prepare("
    SELECT COUNT(*) AS total_days,
           SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_count,
           SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
           SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS late_count,
           SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) AS leave_count,
           SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) AS good_days
    FROM attendance WHERE student_id = ?
");
$stats_stmt->bind_param('i', $student_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$total = (int)$stats['total_days'];
$good = (int)$stats['good_days'];
$pct = $total > 0 ? round(($good / $total) * 100, 1) : 0.0;

$records = [];
$rec_stmt = $conn->prepare("
    SELECT a.attendance_date, a.status, c.class_name, c.section
    FROM attendance a
    LEFT JOIN classes c ON c.id = a.class_id
    WHERE a.student_id = ?
    ORDER BY a.attendance_date DESC
    LIMIT 100
");
$rec_stmt->bind_param('i', $student_id);
$rec_stmt->execute();
$rec_res = $rec_stmt->get_result();
while ($row = $rec_res->fetch_assoc()) {
    $records[] = $row;
}
$rec_stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">My Attendance</h4>
        <p class="text-muted small mb-0">Your attendance history and overall percentage.</p>
    </div>
    <div class="text-end">
        <div class="text-muted small">Overall Rate</div>
        <div class="fs-3 fw-bold" style="color:var(--primary-color);"><?php echo $pct; ?>%</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-success"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Present</small><h4 class="fw-bold mb-0"><?php echo (int)$stats['present_count']; ?></h4></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-danger"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Absent</small><h4 class="fw-bold mb-0"><?php echo (int)$stats['absent_count']; ?></h4></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-warning"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Late</small><h4 class="fw-bold mb-0"><?php echo (int)$stats['late_count']; ?></h4></div></div>
    <div class="col-6 col-md-3"><div class="card p-3 mb-0 border-start border-4 border-info"><small class="text-muted text-uppercase fw-semibold" style="font-size:0.7rem;">Leave</small><h4 class="fw-bold mb-0"><?php echo (int)$stats['leave_count']; ?></h4></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <span class="fw-semibold">Attendance progress</span>
            <span class="small text-muted"><?php echo $good; ?> / <?php echo $total; ?> days counted</span>
        </div>
        <div class="progress" style="height:10px;">
            <div class="progress-bar bg-success" style="width:<?php echo $pct; ?>%;"></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fa-solid fa-calendar-check me-2" style="color:var(--primary-color);"></i>History</span>
        <span class="badge-mint"><?php echo count($records); ?> records</span>
    </div>
    <div class="card-body p-0">
        <?php if (count($records) === 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-calendar-xmark"></i><p class="mb-0">No attendance records yet</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th class="ps-3">Date</th>
                        <th>Class</th>
                        <th class="pe-3">Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?php echo e(date('D, M j, Y', strtotime($r['attendance_date']))); ?></td>
                            <td><?php echo e(($r['class_name'] ?? '—') . ($r['section'] ? ' · ' . $r['section'] : '')); ?></td>
                            <td class="pe-3"><span class="status-pill status-<?php echo e($r['status']); ?>"><?php echo e($r['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
