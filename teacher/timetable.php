<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'My Timetable';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$by_day = [];
foreach ($days as $d) {
    $by_day[$d] = [];
}

$stmt = $conn->prepare("
    SELECT t.day_of_week, t.period_no, t.start_time, t.end_time,
           c.class_name, c.section, s.subject_name, s.subject_code
    FROM timetable t
    INNER JOIN classes c ON c.id = t.class_id
    INNER JOIN subjects s ON s.id = t.subject_id
    WHERE t.teacher_id = ?
    ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.period_no ASC
");
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
$total = 0;
while ($row = $res->fetch_assoc()) {
    $by_day[$row['day_of_week']][] = $row;
    $total++;
}
$stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Weekly Timetable</h4>
        <p class="text-muted small mb-0">Your teaching schedule across the week.</p>
    </div>
    <span class="badge-mint"><?php echo $total; ?> periods</span>
</div>

<?php if ($total === 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state py-5"><i class="fa-solid fa-table"></i><p class="mb-0">No timetable entries assigned to you yet</p></div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($days as $day):
            $slots = $by_day[$day];
            if (count($slots) === 0) continue;
            $is_today = date('l') === $day;
        ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 <?php echo $is_today ? 'border-success' : ''; ?>">
                    <div class="card-header d-flex justify-content-between">
                        <span><?php echo e($day); ?></span>
                        <?php if ($is_today): ?><span class="badge bg-success">Today</span><?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light"><tr><th>Period</th><th>Time</th><th>Class</th><th>Subject</th></tr></thead>
                                <tbody>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td><?php echo (int)$slot['period_no']; ?></td>
                                        <td class="small"><?php echo e(substr($slot['start_time'], 0, 5) . '–' . substr($slot['end_time'], 0, 5)); ?></td>
                                        <td><?php echo e($slot['class_name'] . ' · ' . $slot['section']); ?></td>
                                        <td><?php echo e($slot['subject_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
