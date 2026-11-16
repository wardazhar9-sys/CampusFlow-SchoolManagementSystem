<?php
require_once '../includes/auth.php';
require_role('teacher');
$page_title = 'My Classes';
require_once '../config/database.php';

$teacher_id = current_teacher_id($conn);
if ($teacher_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-danger">Your teacher profile is not linked. Contact the administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$view_class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

$subjects = [];
$sub_stmt = $conn->prepare("
    SELECT sub.id, sub.subject_name, sub.subject_code, sub.class_id,
           c.class_name, c.section,
           (SELECT COUNT(*) FROM students st WHERE st.class_id = sub.class_id) AS student_count
    FROM subjects sub
    INNER JOIN classes c ON c.id = sub.class_id
    WHERE sub.teacher_id = ?
    ORDER BY c.class_name ASC, sub.subject_name ASC
");
$sub_stmt->bind_param('i', $teacher_id);
$sub_stmt->execute();
$sub_res = $sub_stmt->get_result();
while ($row = $sub_res->fetch_assoc()) {
    $subjects[] = $row;
}
$sub_stmt->close();

$allowed_class_ids = array_unique(array_map(fn($s) => (int)$s['class_id'], $subjects));
$students = [];
$view_class = null;

if ($view_class_id > 0 && in_array($view_class_id, $allowed_class_ids, true)) {
    $cstmt = $conn->prepare("SELECT id, class_name, section FROM classes WHERE id = ? LIMIT 1");
    $cstmt->bind_param('i', $view_class_id);
    $cstmt->execute();
    $view_class = $cstmt->get_result()->fetch_assoc();
    $cstmt->close();

    $st_stmt = $conn->prepare("
        SELECT id, admission_number, first_name, last_name, gender, status, photo
        FROM students
        WHERE class_id = ?
        ORDER BY first_name ASC, last_name ASC
    ");
    $st_stmt->bind_param('i', $view_class_id);
    $st_stmt->execute();
    $st_res = $st_stmt->get_result();
    while ($row = $st_res->fetch_assoc()) {
        $students[] = $row;
    }
    $st_stmt->close();
} elseif ($view_class_id > 0) {
    flash_set('danger', 'You are not assigned to that class.');
    redirect('teacher/classes.php');
}

include '../includes/header.php';
include '../includes/sidebar.php';
flash_render();
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">My Classes &amp; Subjects</h4>
        <p class="text-muted small mb-0">Subjects assigned to you with class rosters.</p>
    </div>
    <?php if ($view_class): ?>
        <a href="<?php echo url('teacher/classes.php'); ?>" class="btn btn-sm btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to subjects
        </a>
    <?php endif; ?>
</div>

<?php if ($view_class): ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="fa-solid fa-users me-2" style="color:var(--primary-color);"></i>
                Roster — <?php echo e($view_class['class_name'] . ' · ' . $view_class['section']); ?>
            </span>
            <span class="badge-mint"><?php echo count($students); ?> students</span>
        </div>
        <div class="card-body p-0">
            <?php if (count($students) === 0): ?>
                <div class="empty-state py-5"><i class="fa-solid fa-users-slash"></i><p class="mb-0">No students enrolled in this class</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th class="ps-3">Admission</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th class="pe-3">Status</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($students as $stu):
                            $full = $stu['first_name'] . ' ' . $stu['last_name'];
                            if (!empty($stu['photo']) && $stu['photo'] !== 'default.png' && file_exists('../uploads/' . $stu['photo'])) {
                                $avatar = '../uploads/' . e($stu['photo']);
                            } else {
                                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($full) . '&background=D1FAE5&color=065F46';
                            }
                        ?>
                            <tr>
                                <td class="ps-3 fw-semibold"><?php echo e($stu['admission_number']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo $avatar; ?>" alt="" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                                        <span class="fw-semibold"><?php echo e($full); ?></span>
                                    </div>
                                </td>
                                <td><?php echo e($stu['gender']); ?></td>
                                <td class="pe-3"><span class="status-pill status-<?php echo e($stu['status']); ?>"><?php echo e($stu['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span><i class="fa-solid fa-book-open-reader me-2" style="color:var(--primary-color);"></i>Assigned Subjects</span>
            <span class="badge-mint"><?php echo count($subjects); ?> subjects</span>
        </div>
        <div class="card-body p-0">
            <?php if (count($subjects) === 0): ?>
                <div class="empty-state py-5"><i class="fa-solid fa-book"></i><p class="mb-0">No subjects assigned yet</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr>
                            <th class="ps-3">Code</th>
                            <th>Subject</th>
                            <th>Class</th>
                            <th>Students</th>
                            <th class="pe-3 text-end">Roster</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td class="ps-3"><span class="badge bg-dark"><?php echo e($sub['subject_code']); ?></span></td>
                                <td class="fw-semibold"><?php echo e($sub['subject_name']); ?></td>
                                <td><span class="badge-mint"><?php echo e($sub['class_name'] . ' · ' . $sub['section']); ?></span></td>
                                <td><?php echo (int)$sub['student_count']; ?></td>
                                <td class="pe-3 text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo url('teacher/classes.php?class_id=' . (int)$sub['class_id']); ?>">
                                        <i class="fa-solid fa-users me-1"></i> View students
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
