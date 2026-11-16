<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Fee Management';
require_once '../config/database.php';

$success_msg = $error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_fee') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $fee_title = trim($_POST['fee_title'] ?? '');
            $total_amount = (float)($_POST['total_amount'] ?? 0);
            $amount_paid = (float)($_POST['amount_paid'] ?? 0);
            $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));

            if ($student_id <= 0 || $fee_title === '' || $total_amount <= 0) {
                $error_msg = 'Please select a student, enter a fee title, and a valid total amount.';
            } elseif ($amount_paid > $total_amount) {
                $error_msg = 'Amount paid cannot exceed total amount.';
            } else {
                $receipt_no = 'RCP-' . date('ymd') . '-' . strtoupper(substr(uniqid(), -5));
                $status = fee_status_from_amounts($total_amount, $amount_paid);
                $stmt = $conn->prepare("INSERT INTO fee_payments (student_id, receipt_no, fee_title, total_amount, amount_paid, payment_status, payment_date) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('issddss', $student_id, $receipt_no, $fee_title, $total_amount, $amount_paid, $status, $payment_date);
                if ($stmt->execute()) {
                    $stu = $conn->prepare("SELECT user_id, first_name, last_name FROM students WHERE id = ?");
                    $stu->bind_param('i', $student_id);
                    $stu->execute();
                    $srow = $stu->get_result()->fetch_assoc();
                    $stu->close();
                    if ($srow && $srow['user_id']) {
                        create_notification($conn, (int)$srow['user_id'], 'Fee Invoice Created', "Fee '{$fee_title}' ({$status}) — Receipt {$receipt_no}", 'student/fees.php');
                    }
                    $success_msg = "Fee invoice <strong>" . e($receipt_no) . "</strong> created successfully.";
                } else {
                    $error_msg = 'Failed to save fee: ' . e($conn->error);
                }
                $stmt->close();
            }
        } elseif ($action === 'update_payment') {
            $fee_id = (int)($_POST['fee_id'] ?? 0);
            $amount_paid = (float)($_POST['amount_paid'] ?? 0);
            $row = $conn->prepare("SELECT total_amount, student_id, fee_title, receipt_no FROM fee_payments WHERE id = ?");
            $row->bind_param('i', $fee_id);
            $row->execute();
            $fee = $row->get_result()->fetch_assoc();
            $row->close();
            if (!$fee) {
                $error_msg = 'Fee record not found.';
            } elseif ($amount_paid < 0 || $amount_paid > (float)$fee['total_amount']) {
                $error_msg = 'Invalid payment amount.';
            } else {
                $status = fee_status_from_amounts((float)$fee['total_amount'], $amount_paid);
                $upd = $conn->prepare("UPDATE fee_payments SET amount_paid = ?, payment_status = ?, payment_date = CURDATE() WHERE id = ?");
                $upd->bind_param('dsi', $amount_paid, $status, $fee_id);
                if ($upd->execute()) {
                    $stu = $conn->prepare("SELECT user_id FROM students WHERE id = ?");
                    $sid = (int)$fee['student_id'];
                    $stu->bind_param('i', $sid);
                    $stu->execute();
                    $srow = $stu->get_result()->fetch_assoc();
                    $stu->close();
                    if ($srow && $srow['user_id']) {
                        create_notification($conn, (int)$srow['user_id'], 'Fee Payment Updated', "Payment for '{$fee['fee_title']}' is now {$status}.", 'student/fees.php');
                    }
                    $success_msg = 'Payment updated successfully.';
                } else {
                    $error_msg = 'Update failed.';
                }
                $upd->close();
            }
        } elseif ($action === 'delete_fee') {
            $fee_id = (int)($_POST['fee_id'] ?? 0);
            $del = $conn->prepare("DELETE FROM fee_payments WHERE id = ?");
            $del->bind_param('i', $fee_id);
            $del->execute();
            $del->close();
            $success_msg = 'Fee record deleted.';
        }
    }
}

$filter_status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT f.*, s.first_name, s.last_name, s.admission_number
        FROM fee_payments f
        INNER JOIN students s ON s.id = f.student_id
        WHERE 1=1";
$types = '';
$params = [];
if ($filter_status !== '' && in_array($filter_status, ['Paid','Partial','Unpaid'], true)) {
    $sql .= " AND f.payment_status = ?";
    $types .= 's';
    $params[] = $filter_status;
}
if ($search !== '') {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR f.receipt_no LIKE ? OR s.admission_number LIKE ?)";
    $types .= 'ssss';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= " ORDER BY f.id DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$fees = $stmt->get_result();

$students = $conn->query("SELECT id, first_name, last_name, admission_number FROM students WHERE status='Active' ORDER BY first_name");

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo $error_msg; ?></div><?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form class="d-flex gap-2 flex-wrap" method="get">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search student / receipt" value="<?php echo e($search); ?>">
        <select name="status" class="form-select form-select-sm" style="width:auto;">
            <option value="">All statuses</option>
            <?php foreach (['Paid','Partial','Unpaid'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $filter_status === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFeeModal"><i class="fa-solid fa-plus me-1"></i> New Invoice</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Receipt</th><th>Student</th><th>Title</th><th>Total</th><th>Paid</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php if ($fees->num_rows === 0): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-file-invoice"></i><p>No fee records found</p></div></td></tr>
                <?php else: while ($f = $fees->fetch_assoc()): ?>
                    <tr>
                        <td><code><?php echo e($f['receipt_no']); ?></code></td>
                        <td><?php echo e($f['first_name'].' '.$f['last_name']); ?><div class="small text-muted"><?php echo e($f['admission_number']); ?></div></td>
                        <td><?php echo e($f['fee_title']); ?></td>
                        <td>Rs <?php echo number_format((float)$f['total_amount'], 2); ?></td>
                        <td>Rs <?php echo number_format((float)$f['amount_paid'], 2); ?></td>
                        <td><span class="status-pill status-<?php echo e($f['payment_status']); ?>"><?php echo e($f['payment_status']); ?></span></td>
                        <td><?php echo e($f['payment_date']); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#payModal<?php echo (int)$f['id']; ?>">Pay</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this fee record?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_fee">
                                <input type="hidden" name="fee_id" value="<?php echo (int)$f['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <div class="modal fade" id="payModal<?php echo (int)$f['id']; ?>" tabindex="-1">
                        <div class="modal-dialog"><div class="modal-content">
                            <form method="post">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="update_payment">
                                <input type="hidden" name="fee_id" value="<?php echo (int)$f['id']; ?>">
                                <div class="modal-header"><h5 class="modal-title">Update Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                <div class="modal-body">
                                    <p class="small text-muted">Total: Rs <?php echo number_format((float)$f['total_amount'], 2); ?></p>
                                    <label class="form-label">Amount Paid</label>
                                    <input type="number" step="0.01" min="0" max="<?php echo (float)$f['total_amount']; ?>" name="amount_paid" class="form-control" value="<?php echo (float)$f['amount_paid']; ?>" required>
                                </div>
                                <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
                            </form>
                        </div></div>
                    </div>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_fee">
            <div class="modal-header"><h5 class="modal-title">Create Fee Invoice</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Student *</label>
                    <select name="student_id" class="form-select" required>
                        <option value="">Select student</option>
                        <?php while ($s = $students->fetch_assoc()): ?>
                            <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['first_name'].' '.$s['last_name'].' ('.$s['admission_number'].')'); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Fee Title *</label><input type="text" name="fee_title" class="form-control" required placeholder="Tuition Fee - November"></div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label">Total Amount *</label><input type="number" step="0.01" min="1" name="total_amount" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Amount Paid</label><input type="number" step="0.01" min="0" name="amount_paid" class="form-control" value="0"></div>
                </div>
                <div class="mt-3"><label class="form-label">Payment Date</label><input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Create Invoice</button></div>
        </form>
    </div></div>
</div>

<?php include '../includes/footer.php'; ?>
