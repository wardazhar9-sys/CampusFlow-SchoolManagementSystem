<?php
require_once '../includes/auth.php';
require_role('student');
$page_title = 'My Fees';
require_once '../config/database.php';

$student_id = current_student_id($conn);
if ($student_id === null) {
    include '../includes/header.php';
    include '../includes/sidebar.php';
    echo '<div class="alert alert-warning"><i class="fa-solid fa-circle-info me-2"></i>Your student profile is not linked to this account. Please contact the school administrator.</div>';
    include '../includes/footer.php';
    exit;
}

$fees = [];
$total_due = 0.0;
$total_paid = 0.0;
$stmt = $conn->prepare("
    SELECT receipt_no, fee_title, total_amount, amount_paid, payment_status, payment_date, created_at
    FROM fee_payments
    WHERE student_id = ?
    ORDER BY payment_date DESC, id DESC
");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $fees[] = $row;
    $total_due += (float)$row['total_amount'];
    $total_paid += (float)$row['amount_paid'];
}
$stmt->close();
$balance = max(0, $total_due - $total_paid);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Fee Payments</h4>
        <p class="text-muted small mb-0">Your fee receipts and payment status.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card"><div class="stat-card">
            <div class="stat-icon primary"><i class="fa-solid fa-file-invoice"></i></div>
            <div><div class="text-muted small">Total Billed</div><div class="fs-5 fw-bold">Rs <?php echo number_format($total_due, 0); ?></div></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="stat-card">
            <div class="stat-icon success"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="text-muted small">Paid</div><div class="fs-5 fw-bold">Rs <?php echo number_format($total_paid, 0); ?></div></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="stat-card">
            <div class="stat-icon warning"><i class="fa-solid fa-clock"></i></div>
            <div><div class="text-muted small">Balance</div><div class="fs-5 fw-bold">Rs <?php echo number_format($balance, 0); ?></div></div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><i class="fa-solid fa-receipt me-2" style="color:var(--primary-color);"></i>Payment History</span>
        <span class="badge-mint"><?php echo count($fees); ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (count($fees) === 0): ?>
            <div class="empty-state py-5"><i class="fa-solid fa-file-invoice-dollar"></i><p class="mb-0">No fee records found</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                        <th class="ps-3">Receipt</th>
                        <th>Title</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Date</th>
                        <th class="pe-3">Status</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($fees as $f): ?>
                        <tr>
                            <td class="ps-3 fw-semibold"><?php echo e($f['receipt_no']); ?></td>
                            <td><?php echo e($f['fee_title']); ?></td>
                            <td>Rs <?php echo number_format((float)$f['total_amount'], 0); ?></td>
                            <td>Rs <?php echo number_format((float)$f['amount_paid'], 0); ?></td>
                            <td><?php echo e(date('M j, Y', strtotime($f['payment_date']))); ?></td>
                            <td class="pe-3"><span class="status-pill status-<?php echo e($f['payment_status']); ?>"><?php echo e($f['payment_status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
