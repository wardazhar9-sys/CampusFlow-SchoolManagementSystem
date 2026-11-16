<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'My Timetable';
require_once '../config/database.php';

$student_id = current_student_id($conn);
if ($student_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>Your student profile is not linked to this account. Please contact the school administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$sstmt = $conn->prepare("
    SELECT s.class_id, c.class_name, c.section
    FROM students s
    LEFT JOIN classes c ON c.id = s.class_id
    WHERE s.id = ?
    LIMIT 1
");
$sstmt->bind_param('i', $student_id);
$sstmt->execute();
$student = $sstmt->get_result()->fetch_assoc();
$sstmt->close();

$class_id = (int)($student['class_id'] ?? 0);
$class_label = trim(($student['class_name'] ?? 'Unassigned') . ($student['section'] ? ' · ' . $student['section'] : ''));

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$by_day = [];
foreach ($days as $d) {
    $by_day[$d] = [];
}

$total = 0;
if ($class_id > 0) {
    $stmt = $conn->prepare("
        SELECT t.day_of_week, t.period_no, t.start_time, t.end_time,
               s.subject_name, s.subject_code,
               CONCAT(te.first_name, ' ', te.last_name) AS teacher_name
        FROM timetable t
        INNER JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN teachers te ON te.id = t.teacher_id
        WHERE t.class_id = ?
        ORDER BY FIELD(t.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'), t.period_no ASC
    ");
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $by_day[$row['day_of_week']][] = $row;
        $total++;
    }
    $stmt->close();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Class Timetable</h4>
        <p class="text-muted small mb-0">Weekly schedule for <?php echo e($class_label); ?>.</p>
    </div>
    <span class="badge-mint"><?php echo $total; ?> periods</span>
</div>

<?php if ($class_id <= 0): ?>
    <div class="alert alert-warning">You are not assigned to a class yet.</div>
<?php elseif ($total === 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state py-5"><i class="fa-solid fa-table"></i><p class="mb-0">No timetable published for your class yet</p></div>
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
                                <thead class="table-light"><tr><th>Period</th><th>Time</th><th>Subject</th><th>Teacher</th></tr></thead>
                                <tbody>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td><?php echo (int)$slot['period_no']; ?></td>
                                        <td class="small"><?php echo e(substr($slot['start_time'], 0, 5) . '–' . substr($slot['end_time'], 0, 5)); ?></td>
                                        <td><?php echo e($slot['subject_name']); ?></td>
                                        <td class="small"><?php echo e($slot['teacher_name'] ?: '—'); ?></td>
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
