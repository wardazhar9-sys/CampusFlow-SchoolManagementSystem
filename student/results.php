<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'My Results';
require_once '../config/database.php';

$student_id = current_student_id($conn);
if ($student_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>Your student profile is not linked to this account. Please contact the school administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$results = [];
$stmt = $conn->prepare("
    SELECT m.marks_obtained, e.exam_name, e.exam_date, e.total_marks,
           s.subject_name, s.subject_code
    FROM marks m
    INNER JOIN exams e ON e.id = m.exam_id
    INNER JOIN subjects s ON s.id = m.subject_id
    WHERE m.student_id = ?
    ORDER BY e.exam_date DESC, s.subject_name ASC
");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $total = (float)$row['total_marks'];
    $obtained = (float)$row['marks_obtained'];
    $pct = $total > 0 ? round(($obtained / $total) * 100, 1) : 0.0;
    $row['percent'] = $pct;
    $row['grade'] = letter_grade($pct);
    $results[] = $row;
}
$stmt->close();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Exam Results</h4>
        <p class="text-muted small mb-0">Marks obtained across exams and subjects.</p>
    </div>
    <span class="badge-mint"><?php echo count($results); ?> records</span>
</div>

<div class="card">
    <div class="card-header">
        <i class="fa-solid fa-award me-2" style="color:var(--primary-color);"></i>Marks Sheet
    </div>
    <div class="card-body p-0">
        <?php if (count($results) === 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-award"></i><p class="mb-0">No results published yet</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th class="ps-3">Exam</th>
                        <th>Subject</th>
                        <th>Date</th>
                        <th>Obtained</th>
                        <th>Total</th>
                        <th>%</th>
                        <th class="pe-3">Grade</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?php echo e($r['exam_name']); ?></td>
                            <td>
                                <?php echo e($r['subject_name']); ?>
                                <div class="small text-muted"><?php echo e($r['subject_code']); ?></div>
                            </td>
                            <td><?php echo e(date('M j, Y', strtotime($r['exam_date']))); ?></td>
                            <td><?php echo e(number_format((float)$r['marks_obtained'], 1)); ?></td>
                            <td><?php echo e(number_format((float)$r['total_marks'], 0)); ?></td>
                            <td><?php echo e((string)$r['percent']); ?>%</td>
                            <td class="pe-3">
                                <span class="status-pill <?php echo $r['percent'] >= 50 ? 'status-Approved' : 'status-Rejected'; ?>"><?php echo e($r['grade']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
