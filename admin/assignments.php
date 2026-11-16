<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Assignments';
require_once '../config/database.php';

$success_msg = $error_msg = '';
$filter_class = (int)($_GET['class_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_assignment') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $id = (int)($_POST['assignment_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM assignments WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $success_msg = 'Assignment deleted.';
    }
}

$sql = "
    SELECT a.*, c.class_name, c.section, sub.subject_name,
           t.first_name, t.last_name,
           (SELECT COUNT(*) FROM students st WHERE st.class_id = a.class_id AND st.status='Active') AS student_total,
           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status='Submitted') AS submitted_total
    FROM assignments a
    INNER JOIN classes c ON c.id = a.class_id
    INNER JOIN subjects sub ON sub.id = a.subject_id
    INNER JOIN teachers t ON t.id = a.teacher_id
    WHERE 1=1
";
if ($filter_class > 0) {
    $sql .= " AND a.class_id = " . (int)$filter_class;
}
$sql .= " ORDER BY a.due_date DESC, a.id DESC";
$list = $conn->query($sql);
$classes = $conn->query("SELECT id, class_name, section FROM classes ORDER BY class_name");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo e($success_msg); ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="d-flex justify-content-between mb-3">
    <form method="get" class="d-flex gap-2">
        <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0">All classes</option>
            <?php while ($c = $classes->fetch_assoc()): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_class===(int)$c['id']?'selected':''; ?>>
                    <?php echo e($c['class_name'].' · '.$c['section']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>
    <span class="text-muted small align-self-center">Teachers create assignments from their portal</span>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Title</th><th>Class</th><th>Subject</th><th>Teacher</th><th>Due</th><th>Submissions</th><th></th></tr></thead>
                <tbody>
                <?php if ($list->num_rows === 0): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="fa-solid fa-book-open"></i><p>No assignments found</p></div></td></tr>
                <?php else: while ($a = $list->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo e($a['title']); ?></div>
                            <div class="small text-muted"><?php echo e(mb_strimwidth($a['description'], 0, 70, '…')); ?></div>
                        </td>
                        <td><?php echo e($a['class_name'].' · '.$a['section']); ?></td>
                        <td><?php echo e($a['subject_name']); ?></td>
                        <td><?php echo e($a['first_name'].' '.$a['last_name']); ?></td>
                        <td><?php echo e($a['due_date']); ?></td>
                        <td><span class="badge-mint"><?php echo (int)$a['submitted_total']; ?> / <?php echo (int)$a['student_total']; ?></span></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete assignment?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_assignment">
                                <input type="hidden" name="assignment_id" value="<?php echo (int)$a['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
