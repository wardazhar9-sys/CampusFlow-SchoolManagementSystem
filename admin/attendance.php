<?php
// admin/attendance.php - Daily Student Attendance Register & Percentage Analytics

// Line 1-2: Enforce Admin Authentication
require_once '../includes/auth.php';
require_role('admin');

// Line 3: Set Page Title for header.php and sidebar.php
$page_title = "Attendance Management";

// Line 4: Include MySQLi Database Connection ($conn)
require_once '../config/database.php';

// Line 5-6: Initialize feedback message variables
$success_msg = "";
$error_msg   = "";

// =========================================================================
// PART 1: FETCH ALL CLASSES FOR THE TOP SELECTOR DROPDOWN
// =========================================================================
$all_classes = [];
$classes_res = $conn->query("SELECT id, class_name, section FROM classes ORDER BY class_name ASC, section ASC");
while ($c = $classes_res->fetch_assoc()) {
    $all_classes[] = $c;
}

// =========================================================================
// PART 2: DETERMINE SELECTED CLASS & SELECTED DATE
// (Defaults to the first available class and today's date if not specified)
// =========================================================================
$selected_class_id = isset($_REQUEST['class_id']) ? (int)$_REQUEST['class_id'] : (count($all_classes) > 0 ? (int)$all_classes[0]['id'] : 0);
$selected_date     = isset($_REQUEST['attendance_date']) && !empty($_REQUEST['attendance_date']) ? trim($_REQUEST['attendance_date']) : date('Y-m-d');

// =========================================================================
// PART 3: HANDLE ATTENDANCE SHEET SUBMISSION (BATCH SAVE / UPDATE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {

    // 1. Verify CSRF Security Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_msg = "Security token mismatch. Please refresh the page and try again.";
    } else {
        $post_class_id = (int)($_POST['class_id'] ?? 0);
        $post_date     = trim($_POST['attendance_date'] ?? '');
        $statuses      = $_POST['status'] ?? []; // Associative array: [student_id => 'Present']

        if ($post_class_id <= 0 || empty($post_date)) {
            $error_msg = "Please select a valid class and date.";
        } elseif (empty($statuses) || !is_array($statuses)) {
            $error_msg = "No students found to mark attendance for.";
        } else {
            // Start a database transaction so all students' attendance saves together
            $conn->begin_transaction();

            try {
                // Prepare the UPSERT query (Inserts new row, or updates status if student+date already exists)
                $upsert_sql = "
                    INSERT INTO attendance (student_id, class_id, attendance_date, status)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), class_id = VALUES(class_id)
                ";
                $stmt = $conn->prepare($upsert_sql);

                $allowed_statuses = ['Present', 'Absent', 'Late', 'Leave'];
                $saved_count = 0;

                // Loop through each student submitted from the attendance table
                foreach ($statuses as $stu_id => $stu_status) {
                    $clean_stu_id = (int)$stu_id;
                    // Validate that the status is one of the 4 allowed ENUM values
                    if ($clean_stu_id > 0 && in_array($stu_status, $allowed_statuses, true)) {
                        // "iiss" = integer (student_id), integer (class_id), string (date), string (status)
                        $stmt->bind_param("iiss", $clean_stu_id, $post_class_id, $post_date, $stu_status);
                        $stmt->execute();
                        $saved_count++;
                    }
                }

                $stmt->close();
                $conn->commit();

                $formatted_date = date('F j, Y', strtotime($post_date));
                $success_msg = "Attendance register for <strong>$saved_count students</strong> on <strong>$formatted_date</strong> has been saved!";

            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Database error while saving attendance: " . $e->getMessage();
            }
        }
    }
}

// =========================================================================
// PART 4: FETCH ENROLLED STUDENTS, THEIR STATUS FOR SELECTED DATE,
//         AND THEIR OVERALL ATTENDANCE PERCENTAGE
// =========================================================================
$students_list = [];
$count_present = 0;
$count_absent  = 0;
$count_late    = 0;
$count_leave   = 0;
$is_already_marked = false;

if ($selected_class_id > 0) {
    /*
     * This query fetches every student in the selected class,
     * LEFT JOINs their attendance record for the specific $selected_date,
     * and uses subqueries to count their total days marked & total days present across the term.
     */
    $sheet_sql = "
        SELECT 
            s.id, s.admission_number, s.first_name, s.last_name, s.photo,
            a.status AS date_status,
            (SELECT COUNT(*) FROM attendance att WHERE att.student_id = s.id) AS total_days,
            (SELECT COUNT(*) FROM attendance att WHERE att.student_id = s.id AND att.status IN ('Present', 'Late')) AS present_days
        FROM students s
        LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ?
        WHERE s.class_id = ?
        ORDER BY s.first_name ASC, s.last_name ASC
    ";

    $sheet_stmt = $conn->prepare($sheet_sql);
    $sheet_stmt->bind_param("si", $selected_date, $selected_class_id);
    $sheet_stmt->execute();
    $sheet_res = $sheet_stmt->get_result();

    while ($row = $sheet_res->fetch_assoc()) {
        // Check if attendance was already saved for this date
        if (!empty($row['date_status'])) {
            $is_already_marked = true;
            if ($row['date_status'] === 'Present') $count_present++;
            elseif ($row['date_status'] === 'Absent') $count_absent++;
            elseif ($row['date_status'] === 'Late') $count_late++;
            elseif ($row['date_status'] === 'Leave') $count_leave++;
        }

        // Calculate overall term attendance percentage for this student
        $total_d   = (int)$row['total_days'];
        $present_d = (int)$row['present_days'];
        $row['overall_percent'] = ($total_d > 0) ? round(($present_d / $total_d) * 100, 1) : 100.0;

        $students_list[] = $row;
    }
    $sheet_stmt->close();
}

// Include Shared Header and Sidebar
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Daily Attendance Register</h4>
        <p class="text-muted small mb-0">Record daily student attendance and track term attendance percentages.</p>
    </div>
    <div>
        <?php if ($is_already_marked): ?>
            <span class="badge-mint px-3 py-2">
                <i class="fa-solid fa-circle-check me-1"></i> Recorded for <?php echo date('M d, Y', strtotime($selected_date)); ?>
            </span>
        <?php else: ?>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                <i class="fa-regular fa-clock me-1"></i> Not Marked Yet for <?php echo date('M d, Y', strtotime($selected_date)); ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- Feedback Alert Messages -->
<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center py-2 px-3 small" role="alert">
        <i class="fa-solid fa-circle-check me-2 fs-6"></i>
        <div><?php echo $success_msg; ?></div>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center py-2 px-3 small" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2 fs-6"></i>
        <div><?php echo htmlspecialchars($error_msg); ?></div>
        <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Top Filter Bar: Select Class & Date -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form action="attendance.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold small mb-1">Select Class &amp; Section</label>
                <select name="class_id" class="form-select form-select-sm" required>
                    <?php if (count($all_classes) === 0): ?>
                        <option value="0">No classes available</option>
                    <?php else: ?>
                        <?php foreach ($all_classes as $cls): ?>
                            <option value="<?php echo (int)$cls['id']; ?>" <?php echo ($selected_class_id === (int)$cls['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cls['class_name'] . ' — ' . $cls['section']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold small mb-1">Attendance Date</label>
                <input type="date" name="attendance_date" class="form-control form-control-sm" 
                       value="<?php echo htmlspecialchars($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="col-md-3 d-grid">
                <button type="submit" class="btn btn-primary btn-sm py-2">
                    <i class="fa-solid fa-calendar-day me-1"></i> Load Attendance Sheet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Mini Summary Strip for Selected Date (Shown when attendance is marked) -->
<?php if (count($students_list) > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card p-3 mb-0 border-start border-4 border-success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Present</small>
                    <h4 class="fw-bold text-dark mb-0"><?php echo $count_present; ?></h4>
                </div>
                <i class="fa-solid fa-user-check text-success fs-4 opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 mb-0 border-start border-4 border-danger">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Absent</small>
                    <h4 class="fw-bold text-dark mb-0"><?php echo $count_absent; ?></h4>
                </div>
                <i class="fa-solid fa-user-xmark text-danger fs-4 opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 mb-0 border-start border-4 border-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">Late Arrival</small>
                    <h4 class="fw-bold text-dark mb-0"><?php echo $count_late; ?></h4>
                </div>
                <i class="fa-solid fa-clock text-warning fs-4 opacity-75"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card p-3 mb-0 border-start border-4 border-info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.7rem;">On Leave</small>
                    <h4 class="fw-bold text-dark mb-0"><?php echo $count_leave; ?></h4>
                </div>
                <i class="fa-solid fa-envelope-open-text text-info fs-4 opacity-75"></i>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Attendance Register Card -->
<div class="card">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
        <span class="fw-bold text-dark">
            <i class="fa-solid fa-clipboard-user me-2" style="color: var(--primary-color);"></i>Student Attendance Sheet
            <span class="text-muted fw-normal small ms-1">(<?php echo count($students_list); ?> Students)</span>
        </span>

        <?php if (count($students_list) > 0): ?>
            <button type="button" class="btn btn-sm btn-outline-success" id="markAllPresentBtn">
                <i class="fa-solid fa-check-double me-1"></i> Mark All Present
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if (count($students_list) > 0): ?>
            <form action="attendance.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="class_id" value="<?php echo (int)$selected_class_id; ?>">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light" style="font-size: 0.75rem; text-transform: uppercase;">
                            <tr>
                                <th class="ps-3">Adm No.</th>
                                <th>Student Name</th>
                                <th style="width: 220px;">Overall Term Rate</th>
                                <th class="pe-3 text-end">Mark Status (<?php echo date('M d', strtotime($selected_date)); ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_list as $stu): ?>
                                <?php
                                $stu_id    = (int)$stu['id'];
                                $full_name = $stu['first_name'] . ' ' . $stu['last_name'];
                                // Default to 'Present' if not yet marked today
                                $current_status = !empty($stu['date_status']) ? $stu['date_status'] : 'Present';
                                $overall_pct    = $stu['overall_percent'];

                                if (!empty($stu['photo']) && $stu['photo'] !== 'default.png' && file_exists("../uploads/" . $stu['photo'])) {
                                    $avatar_url = "../uploads/" . htmlspecialchars($stu['photo']);
                                } else {
                                    $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($full_name) . "&background=D1FAE5&color=065F46&bold=true";
                                }
                                ?>
                                <tr>
                                    <!-- 1. Admission Number -->
                                    <td class="ps-3 fw-semibold text-success">
                                        <?php echo htmlspecialchars($stu['admission_number']); ?>
                                    </td>

                                    <!-- 2. Student Avatar & Name -->
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?php echo $avatar_url; ?>" alt="Student" 
                                                 class="rounded-circle border" style="width: 34px; height: 34px; object-fit: cover;">
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($full_name); ?></span>
                                        </div>
                                    </td>

                                    <!-- 3. Overall Term Attendance Rate Progress Bar -->
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="fw-semibold text-dark" style="font-size: 0.75rem;"><?php echo $overall_pct; ?>%</small>
                                            <small class="text-muted" style="font-size: 0.7rem;">
                                                <?php echo (int)$stu['present_days']; ?>/<?php echo (int)$stu['total_days']; ?> days
                                            </small>
                                        </div>
                                        <div class="progress" style="height: 6px; background-color: #E2E8F0;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $overall_pct; ?>%; background-color: <?php echo ($overall_pct >= 75) ? '#10B981' : '#F59E0B'; ?>;">
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 4. Status Radio Button Group -->
                                    <td class="pe-3 text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- Present -->
                                            <input type="radio" class="btn-check status-present-radio" 
                                                   name="status[<?php echo $stu_id; ?>]" 
                                                   id="pres_<?php echo $stu_id; ?>" value="Present"
                                                   <?php echo ($current_status === 'Present') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-success" for="pres_<?php echo $stu_id; ?>">Present</label>

                                            <!-- Absent -->
                                            <input type="radio" class="btn-check" 
                                                   name="status[<?php echo $stu_id; ?>]" 
                                                   id="abs_<?php echo $stu_id; ?>" value="Absent"
                                                   <?php echo ($current_status === 'Absent') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-danger" for="abs_<?php echo $stu_id; ?>">Absent</label>

                                            <!-- Late -->
                                            <input type="radio" class="btn-check" 
                                                   name="status[<?php echo $stu_id; ?>]" 
                                                   id="late_<?php echo $stu_id; ?>" value="Late"
                                                   <?php echo ($current_status === 'Late') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-warning" for="late_<?php echo $stu_id; ?>">Late</label>

                                            <!-- Leave -->
                                            <input type="radio" class="btn-check" 
                                                   name="status[<?php echo $stu_id; ?>]" 
                                                   id="leave_<?php echo $stu_id; ?>" value="Leave"
                                                   <?php echo ($current_status === 'Leave') ? 'checked' : ''; ?>>
                                            <label class="btn btn-outline-info" for="leave_<?php echo $stu_id; ?>">Leave</label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sticky Save Bar at Bottom of Card -->
                <div class="p-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        <i class="fa-solid fa-circle-info me-1 text-success"></i>
                        Submitting again for the same date safely updates existing records without creating duplicates.
                    </span>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-floppy-disk me-2"></i>
                        <?php echo $is_already_marked ? 'Update Attendance Register' : 'Save Attendance Register'; ?>
                    </button>
                </div>
            </form>
        <?php else: ?>
            <!-- Clean Empty State when selected class has 0 students -->
            <div class="text-center py-5 px-3">
                <div class="mb-2 text-muted" style="font-size: 2.3rem;">
                    <i class="fa-solid fa-users-slash opacity-50"></i>
                </div>
                <h6 class="fw-semibold text-dark mb-1">No Students Enrolled in This Class</h6>
                <p class="text-muted small mb-3">
                    Select another class above or admit students into this class to mark daily attendance.
                </p>
                <a href="students.php" class="btn btn-primary btn-sm px-3">
                    <i class="fa-solid fa-user-plus me-1"></i> Go to Student Admissions
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const markAllBtn = document.getElementById('markAllPresentBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            document.querySelectorAll('.status-present-radio').forEach(function (radio) {
                radio.checked = true;
            });
        });
    }
});
</script>
<?php include '../includes/footer.php'; ?>