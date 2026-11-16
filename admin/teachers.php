<?php
// admin/teachers.php - Complete Teacher Management Module (CRUD + Linked User Accounts)

// Line 1-2: Enforce Admin Authentication
require_once '../includes/auth.php';
require_role('admin');

// Line 3: Set Page Title
$page_title = "Teacher Management";

// Line 4: Include MySQLi Database Connection ($conn)
require_once '../config/database.php';

// Line 5-6: Initialize feedback message variables
$success_msg = "";
$error_msg   = "";

// =========================================================================
// PART 1: HANDLE FORM SUBMISSIONS (ADD, EDIT, OR DELETE TEACHER)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify CSRF Security Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_msg = "Security token mismatch. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // -----------------------------------------------------------------
        // ACTION A: REGISTER NEW TEACHER (WITH MYSQLI TRANSACTION)
        // -----------------------------------------------------------------
        if ($action === 'add_teacher') {
            $first_name   = trim($_POST['first_name'] ?? '');
            $last_name    = trim($_POST['last_name'] ?? '');
            $username     = trim($_POST['username'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $password     = $_POST['password'] ?? '';
            $phone        = trim($_POST['phone'] ?? '');
            $gender       = trim($_POST['gender'] ?? '');
            $joining_date = trim($_POST['joining_date'] ?? '');
            $address      = trim($_POST['address'] ?? '');

            // Validate required fields
            if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($phone) || empty($gender) || empty($joining_date) || empty($address)) {
                $error_msg = "Please fill in all required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_msg = "Please enter a valid email address.";
            } else {
                // Check if username or email already exists in the `users` table
                $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $check_stmt->bind_param("ss", $username, $email);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();

                if ($check_res->num_rows > 0) {
                    $error_msg = "That username or email is already registered in the system.";
                } else {
                    // Start a Database Transaction so both tables (`users` and `teachers`) succeed together
                    $conn->begin_transaction();

                    try {
                        // Step 1: Hash password and insert into `users` table with role = 'teacher'
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $role = 'teacher';

                        $user_stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                        $user_stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
                        if (!$user_stmt->execute()) {
                            throw new Exception("Failed to create user login account.");
                        }

                        // Grab the newly created user ID from the `users` table
                        $new_user_id = $conn->insert_id;
                        $user_stmt->close();

                        // Step 2: Insert profile details into `teachers` table using $new_user_id
                        $teacher_stmt = $conn->prepare("
                            INSERT INTO teachers (user_id, first_name, last_name, phone, gender, address, joining_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        // "issssss" = 1 integer (user_id) + 6 strings
                        $teacher_stmt->bind_param(
                            "issssss",
                            $new_user_id,
                            $first_name,
                            $last_name,
                            $phone,
                            $gender,
                            $address,
                            $joining_date
                        );

                        if (!$teacher_stmt->execute()) {
                            throw new Exception("Failed to save teacher profile record.");
                        }
                        $teacher_stmt->close();

                        // Both queries succeeded! Commit the changes permanently to MySQL
                        $conn->commit();
                        $success_msg = "Teacher <strong>" . htmlspecialchars("$first_name $last_name") . "</strong> has been registered with login username <code>" . htmlspecialchars($username) . "</code>!";

                    } catch (Exception $e) {
                        // If anything failed, undo (rollback) any partial database changes
                        $conn->rollback();
                        $error_msg = "Registration Error: " . $e->getMessage();
                    }
                }
                $check_stmt->close();
            }
        }

        // -----------------------------------------------------------------
        // ACTION B: EDIT EXISTING TEACHER (UPDATE)
        // -----------------------------------------------------------------
        elseif ($action === 'edit_teacher') {
            $teacher_id   = (int)($_POST['teacher_id'] ?? 0);
            $user_id      = (int)($_POST['user_id'] ?? 0);
            $first_name   = trim($_POST['first_name'] ?? '');
            $last_name    = trim($_POST['last_name'] ?? '');
            $email        = trim($_POST['email'] ?? '');
            $phone        = trim($_POST['phone'] ?? '');
            $gender       = trim($_POST['gender'] ?? '');
            $joining_date = trim($_POST['joining_date'] ?? '');
            $address      = trim($_POST['address'] ?? '');
            $new_password = $_POST['new_password'] ?? '';

            if ($teacher_id <= 0 || empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($gender) || empty($joining_date) || empty($address)) {
                $error_msg = "Please fill in all required fields when updating a teacher.";
            } else {
                $conn->begin_transaction();

                try {
                    // 1. Update teacher profile in `teachers` table
                    $upd_teacher = $conn->prepare("
                        UPDATE teachers 
                        SET first_name = ?, last_name = ?, phone = ?, gender = ?, joining_date = ?, address = ?
                        WHERE id = ?
                    ");
                    $upd_teacher->bind_param("ssssssi", $first_name, $last_name, $phone, $gender, $joining_date, $address, $teacher_id);
                    $upd_teacher->execute();
                    $upd_teacher->close();

                    // 2. If teacher has a linked user account, update their email (and password if a new one was typed)
                    if ($user_id > 0) {
                        if (!empty($new_password)) {
                            $hashed_pw = password_hash($new_password, PASSWORD_DEFAULT);
                            $upd_user = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                            $upd_user->bind_param("ssi", $email, $hashed_pw, $user_id);
                        } else {
                            $upd_user = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
                            $upd_user->bind_param("si", $email, $user_id);
                        }
                        $upd_user->execute();
                        $upd_user->close();
                    }

                    $conn->commit();
                    $success_msg = "Teacher <strong>" . htmlspecialchars("$first_name $last_name") . "</strong>'s record has been updated!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Error updating teacher: " . $e->getMessage();
                }
            }
        }

        // -----------------------------------------------------------------
        // ACTION C: DELETE TEACHER
        // -----------------------------------------------------------------
        elseif ($action === 'delete_teacher') {
            $teacher_id = (int)($_POST['teacher_id'] ?? 0);
            $user_id    = (int)($_POST['user_id'] ?? 0);

            if ($teacher_id > 0) {
                $conn->begin_transaction();
                try {
                    // Delete from teachers table
                    $del_t = $conn->prepare("DELETE FROM teachers WHERE id = ?");
                    $del_t->bind_param("i", $teacher_id);
                    $del_t->execute();
                    $del_t->close();

                    // Also delete their login account from users table if linked
                    if ($user_id > 0) {
                        $del_u = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
                        $del_u->bind_param("i", $user_id);
                        $del_u->execute();
                        $del_u->close();
                    }

                    $conn->commit();
                    $success_msg = "Teacher record and associated login account have been removed.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Unable to delete teacher record.";
                }
            }
        }
    }
}

// =========================================================================
// PART 2: FETCH TEACHERS LIST WITH SEARCH & USER ACCOUNT INFO (LEFT JOIN)
// =========================================================================
$search_query = trim($_GET['search'] ?? '');

$teachers_sql = "
    SELECT t.*, u.username, u.email
    FROM teachers t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE 1=1
";
$params = [];
$types  = "";

if (!empty($search_query)) {
    $teachers_sql .= " AND (
        t.first_name LIKE ? 
        OR t.last_name LIKE ? 
        OR CONCAT(t.first_name, ' ', t.last_name) LIKE ?
        OR t.phone LIKE ? 
        OR u.email LIKE ? 
        OR u.username LIKE ?
    )";
    $like_term = "%" . $search_query . "%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $like_term;
    }
    $types .= "ssssss";
}

$teachers_sql .= " ORDER BY t.id DESC";

$stmt = $conn->prepare($teachers_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$teachers_result = $stmt->get_result();

// Include Shared Header and Sidebar
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- Page Header & Add Teacher Button -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">Teacher &amp; Faculty Directory</h4>
        <p class="text-muted small mb-0">Manage faculty profiles, contact details, joining dates, and portal login accounts.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary px-3 py-2" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            <i class="fa-solid fa-chalkboard-user me-2"></i>Add New Teacher
        </button>
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

<!-- Search Bar Card -->
<div class="card mb-4">
    <div class="card-body p-3">
        <form action="teachers.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="search" class="form-control border-start-0" 
                           placeholder="Search teacher by full name, username, email, or phone number..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Search Faculty
                </button>
                <?php if (!empty($search_query)): ?>
                    <a href="teachers.php" class="btn btn-outline-secondary btn-sm" title="Reset Search">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Teachers Directory Table Card -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark">
            <i class="fa-solid fa-chalkboard-user me-2" style="color: var(--primary-color);"></i>Registered Faculty Members
        </span>
        <span class="badge-mint"><?php echo $teachers_result->num_rows; ?> Teachers</span>
    </div>
    <div class="card-body p-0">
        <?php if ($teachers_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light" style="font-size: 0.76rem; text-transform: uppercase;">
                        <tr>
                            <th class="ps-3">Teacher Name</th>
                            <th>Portal Login / Email</th>
                            <th>Phone Number</th>
                            <th>Gender</th>
                            <th>Joining Date</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($t = $teachers_result->fetch_assoc()): ?>
                            <?php
                            $full_name  = $t['first_name'] . ' ' . $t['last_name'];
                            $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($full_name) . "&background=065F46&color=fff&bold=true";
                            $username_display = !empty($t['username']) ? $t['username'] : 'N/A';
                            $email_display    = !empty($t['email']) ? $t['email'] : 'No email linked';
                            ?>
                            <tr>
                                <!-- 1. Teacher Avatar & Full Name -->
                                <td class="ps-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo $avatar_url; ?>" alt="Teacher" 
                                             class="rounded-circle border" style="width: 38px; height: 38px; object-fit: cover;">
                                        <div>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($full_name); ?></div>
                                            <small class="text-muted" style="font-size: 0.74rem;">
                                                Staff ID: #TCH-<?php echo str_pad($t['id'], 3, '0', STR_PAD_LEFT); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <!-- 2. Portal Username & Email -->
                                <td>
                                    <div class="fw-medium text-dark">
                                        <i class="fa-regular fa-envelope me-1 text-muted"></i><?php echo htmlspecialchars($email_display); ?>
                                    </div>
                                    <small class="text-success fw-semibold" style="font-size: 0.75rem;">
                                        <i class="fa-solid fa-user-shield me-1"></i>@<?php echo htmlspecialchars($username_display); ?>
                                    </small>
                                </td>

                                <!-- 3. Phone -->
                                <td>
                                    <span class="text-dark fw-medium">
                                        <i class="fa-solid fa-phone me-1 text-success"></i><?php echo htmlspecialchars($t['phone']); ?>
                                    </span>
                                </td>

                                <!-- 4. Gender -->
                                <td>
                                    <span class="badge-mint"><?php echo htmlspecialchars($t['gender']); ?></span>
                                </td>

                                <!-- 5. Joining Date -->
                                <td class="text-muted small">
                                    <i class="fa-regular fa-calendar-check me-1"></i><?php echo date('M d, Y', strtotime($t['joining_date'])); ?>
                                </td>

                                <!-- 6. Actions (View, Edit, Delete) -->
                                <td class="pe-3 text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- View Teacher Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-success view-teacher-btn"
                                                title="View Profile"
                                                data-bs-toggle="modal" data-bs-target="#viewTeacherModal"
                                                data-avatar="<?php echo htmlspecialchars($avatar_url); ?>"
                                                data-staffid="#TCH-<?php echo str_pad($t['id'], 3, '0', STR_PAD_LEFT); ?>"
                                                data-name="<?php echo htmlspecialchars($full_name); ?>"
                                                data-username="@<?php echo htmlspecialchars($username_display); ?>"
                                                data-email="<?php echo htmlspecialchars($email_display); ?>"
                                                data-phone="<?php echo htmlspecialchars($t['phone']); ?>"
                                                data-gender="<?php echo htmlspecialchars($t['gender']); ?>"
                                                data-joining="<?php echo date('F j, Y', strtotime($t['joining_date'])); ?>"
                                                data-address="<?php echo htmlspecialchars($t['address']); ?>">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>

                                        <!-- Edit Teacher Modal Button -->
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-teacher-btn"
                                                title="Edit Teacher"
                                                data-bs-toggle="modal" data-bs-target="#editTeacherModal"
                                                data-id="<?php echo (int)$t['id']; ?>"
                                                data-userid="<?php echo (int)$t['user_id']; ?>"
                                                data-fname="<?php echo htmlspecialchars($t['first_name']); ?>"
                                                data-lname="<?php echo htmlspecialchars($t['last_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($t['email'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($t['phone']); ?>"
                                                data-gender="<?php echo htmlspecialchars($t['gender']); ?>"
                                                data-joining="<?php echo htmlspecialchars($t['joining_date']); ?>"
                                                data-address="<?php echo htmlspecialchars($t['address']); ?>">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                        </button>

                                        <!-- Delete Teacher Form -->
                                        <form action="teachers.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('Are you sure you want to remove teacher <?php echo htmlspecialchars(addslashes($full_name)); ?> and their login account?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="action" value="delete_teacher">
                                            <input type="hidden" name="teacher_id" value="<?php echo (int)$t['id']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$t['user_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Teacher">
                                                <i class="fa-regular fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- Clean Empty State -->
            <div class="text-center py-5 px-3">
                <div class="mb-2 text-muted" style="font-size: 2.5rem;">
                    <i class="fa-solid fa-chalkboard-user opacity-50"></i>
                </div>
                <h6 class="fw-semibold text-dark mb-1">No Faculty Records Found</h6>
                <p class="text-muted small mb-3">
                    <?php echo !empty($search_query) 
                        ? "No teachers matched your search query." 
                        : "Start building your school faculty directory by registering your first teacher."; ?>
                </p>
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fa-solid fa-plus me-1"></i> Add First Teacher
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- =========================================================================
     MODAL 1: ADD NEW TEACHER
     ========================================================================= -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background-color: var(--sidebar-bg); color: #FFFFFF;">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-chalkboard-user me-2 text-success"></i>Register New Teacher
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="teachers.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="add_teacher">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control form-control-sm" placeholder="e.g. Kamran" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control form-control-sm" placeholder="e.g. Siddiqui" required>
                        </div>

                        <!-- Login Credentials Section -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Portal Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control form-control-sm" placeholder="e.g. kamran.t" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control form-control-sm" placeholder="e.g. kamran@school.com" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Initial Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="Create login password" required>
                        </div>

                        <!-- Employment & Contact Info -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control form-control-sm" placeholder="e.g. 0321-9876543" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select form-select-sm" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" name="joining_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Residential Address <span class="text-danger">*</span></label>
                            <textarea name="address" rows="2" class="form-control form-control-sm" placeholder="Enter teacher's full residential address..." required></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4">
                        <i class="fa-solid fa-check me-1"></i> Register Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 2: VIEW TEACHER PROFILE CARD
     ========================================================================= -->
<div class="modal fade" id="viewTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background: linear-gradient(135deg, #065F46 0%, #10B981 100%); color: #FFFFFF;">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-id-badge me-2"></i>Faculty Profile Card
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <img id="view_t_avatar" src="" alt="Teacher" 
                         class="rounded-circle border border-3 border-success shadow-sm mb-2" 
                         style="width: 80px; height: 80px; object-fit: cover;">
                    <h5 id="view_t_name" class="fw-bold text-dark mb-1"></h5>
                    <div class="d-flex justify-content-center align-items-center gap-2">
                        <span id="view_t_staffid" class="badge bg-dark text-white"></span>
                        <span id="view_t_username" class="badge-mint"></span>
                    </div>
                </div>

                <div class="p-3 rounded border" style="background-color: var(--body-bg);">
                    <div class="row g-2 small">
                        <div class="col-5 text-muted fw-semibold">Email Address:</div>
                        <div class="col-7 text-dark fw-medium" id="view_t_email"></div>

                        <div class="col-5 text-muted fw-semibold">Phone Number:</div>
                        <div class="col-7 text-success fw-bold" id="view_t_phone"></div>

                        <div class="col-5 text-muted fw-semibold">Gender:</div>
                        <div class="col-7 text-dark fw-medium" id="view_t_gender"></div>

                        <div class="col-5 text-muted fw-semibold">Joining Date:</div>
                        <div class="col-7 text-dark fw-medium" id="view_t_joining"></div>

                        <div class="col-12"><hr class="my-2"></div>

                        <div class="col-12 text-muted fw-semibold">Residential Address:</div>
                        <div class="col-12 text-dark" id="view_t_address"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================================
     MODAL 3: EDIT TEACHER INFORMATION
     ========================================================================= -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header" style="background-color: var(--sidebar-bg); color: #FFFFFF;">
                <h6 class="modal-title fw-bold">
                    <i class="fa-solid fa-pen-to-square me-2 text-success"></i>Edit Teacher Information
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="teachers.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="edit_teacher">
                <input type="hidden" name="teacher_id" id="edit_t_id">
                <input type="hidden" name="user_id" id="edit_t_userid">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" id="edit_t_fname" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" id="edit_t_lname" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_t_email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Reset Password <span class="text-muted fw-normal">(Leave blank to keep current)</span></label>
                            <input type="password" name="new_password" class="form-control form-control-sm" placeholder="Enter new password only if changing">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="edit_t_phone" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Gender <span class="text-danger">*</span></label>
                            <select name="gender" id="edit_t_gender" class="form-select form-select-sm" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Joining Date <span class="text-danger">*</span></label>
                            <input type="date" name="joining_date" id="edit_t_joining" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small">Residential Address <span class="text-danger">*</span></label>
                            <textarea name="address" id="edit_t_address" rows="2" class="form-control form-control-sm" required></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.view-teacher-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('view_t_avatar').src = this.getAttribute('data-avatar');
            document.getElementById('view_t_staffid').textContent = this.getAttribute('data-staffid');
            document.getElementById('view_t_name').textContent = this.getAttribute('data-name');
            document.getElementById('view_t_username').textContent = this.getAttribute('data-username');
            document.getElementById('view_t_email').textContent = this.getAttribute('data-email');
            document.getElementById('view_t_phone').textContent = this.getAttribute('data-phone');
            document.getElementById('view_t_gender').textContent = this.getAttribute('data-gender');
            document.getElementById('view_t_joining').textContent = this.getAttribute('data-joining');
            document.getElementById('view_t_address').textContent = this.getAttribute('data-address');
        });
    });

    document.querySelectorAll('.edit-teacher-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_t_id').value = this.getAttribute('data-id');
            document.getElementById('edit_t_userid').value = this.getAttribute('data-userid');
            document.getElementById('edit_t_fname').value = this.getAttribute('data-fname');
            document.getElementById('edit_t_lname').value = this.getAttribute('data-lname');
            document.getElementById('edit_t_email').value = this.getAttribute('data-email');
            document.getElementById('edit_t_phone').value = this.getAttribute('data-phone');
            document.getElementById('edit_t_gender').value = this.getAttribute('data-gender');
            document.getElementById('edit_t_joining').value = this.getAttribute('data-joining');
            document.getElementById('edit_t_address').value = this.getAttribute('data-address');
        });
    });
});
</script>
<?php include '../includes/footer.php'; ?>