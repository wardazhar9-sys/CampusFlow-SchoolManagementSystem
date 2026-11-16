<?php
require_once '../includes/auth.php';
require_role('admin');
$page_title = 'Student Management';
require_once '../config/database.php';

$success_msg = $error_msg = '';

function handle_student_photo(array $file, string $existing = 'default.png'): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [$existing, null];
    }
    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return [$existing, 'Photo upload failed.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        return [$existing, 'Invalid photo format. Use JPG, PNG, or WEBP.'];
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return [$existing, 'Photo must be under 2MB.'];
    }
    $dir = '../uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'stu_' . time() . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return [$existing, 'Failed to save photo.'];
    }
    if ($existing !== 'default.png' && is_file($dir . $existing)) {
        @unlink($dir . $existing);
    }
    return [$name, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_msg = 'Security token mismatch.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_student') {
            $admission_number = trim($_POST['admission_number'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
            $parent_name = trim($_POST['parent_name'] ?? '');
            $parent_phone = trim($_POST['parent_phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($admission_number === '' || $first_name === '' || $last_name === '' || $gender === '' || $date_of_birth === '' || $parent_name === '' || $parent_phone === '' || $address === '' || $username === '' || $email === '' || $password === '') {
                $error_msg = 'Please fill all required fields including login credentials.';
            } elseif (strlen($password) < 6) {
                $error_msg = 'Password must be at least 6 characters.';
            } else {
                $chk = $conn->prepare("SELECT id FROM students WHERE admission_number=? LIMIT 1");
                $chk->bind_param('s', $admission_number);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $error_msg = 'Admission number already exists.';
                } else {
                    $uchk = $conn->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
                    $uchk->bind_param('ss', $username, $email);
                    $uchk->execute();
                    if ($uchk->get_result()->num_rows > 0) {
                        $error_msg = 'Username or email already taken.';
                    } else {
                        [$photo, $perr] = handle_student_photo($_FILES['photo'] ?? []);
                        if ($perr) {
                            $error_msg = $perr;
                        } else {
                            $conn->begin_transaction();
                            try {
                                $role = 'student';
                                $hash = password_hash($password, PASSWORD_DEFAULT);
                                $u = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?,?,?,?)");
                                $u->bind_param('ssss', $username, $email, $hash, $role);
                                $u->execute();
                                $user_id = (int)$conn->insert_id;
                                $u->close();

                                $ins = $conn->prepare("INSERT INTO students (user_id, admission_number, first_name, last_name, gender, date_of_birth, class_id, parent_name, parent_phone, address, photo) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                                $ins->bind_param('isssssissss', $user_id, $admission_number, $first_name, $last_name, $gender, $date_of_birth, $class_id, $parent_name, $parent_phone, $address, $photo);
                                $ins->execute();
                                $ins->close();
                                $conn->commit();
                                create_notification($conn, $user_id, 'Welcome to CampusFlow', 'Your student portal is ready. Sign in with your username.', 'student/dashboard.php');
                                $success_msg = 'Student <strong>' . e("$first_name $last_name") . '</strong> admitted with login <code>' . e($username) . '</code>.';
                            } catch (Throwable $ex) {
                                $conn->rollback();
                                $error_msg = 'Failed to admit student: ' . e($ex->getMessage());
                            }
                        }
                    }
                    $uchk->close();
                }
                $chk->close();
            }
        } elseif ($action === 'edit_student') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $user_id = (int)($_POST['user_id'] ?? 0);
            $admission_number = trim($_POST['admission_number'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
            $parent_name = trim($_POST['parent_name'] ?? '');
            $parent_phone = trim($_POST['parent_phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $existing_photo = $_POST['existing_photo'] ?? 'default.png';
            $status = $_POST['status'] ?? 'Active';

            if ($student_id <= 0 || $admission_number === '' || $first_name === '' || $last_name === '') {
                $error_msg = 'Invalid student data.';
            } else {
                [$photo, $perr] = handle_student_photo($_FILES['photo'] ?? [], $existing_photo);
                if ($perr) {
                    $error_msg = $perr;
                } else {
                    $upd = $conn->prepare("UPDATE students SET admission_number=?, first_name=?, last_name=?, gender=?, date_of_birth=?, class_id=?, parent_name=?, parent_phone=?, address=?, photo=?, status=? WHERE id=?");
                    $upd->bind_param('sssssisssssi', $admission_number, $first_name, $last_name, $gender, $date_of_birth, $class_id, $parent_name, $parent_phone, $address, $photo, $status, $student_id);
                    $upd->execute();
                    $upd->close();
                    if ($user_id > 0 && $email !== '') {
                        if ($password !== '') {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $uu = $conn->prepare("UPDATE users SET email=?, password=? WHERE id=?");
                            $uu->bind_param('ssi', $email, $hash, $user_id);
                        } else {
                            $uu = $conn->prepare("UPDATE users SET email=? WHERE id=?");
                            $uu->bind_param('si', $email, $user_id);
                        }
                        $uu->execute();
                        $uu->close();
                    }
                    $success_msg = 'Student updated successfully.';
                }
            }
        } elseif ($action === 'delete_student') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $user_id = (int)($_POST['user_id'] ?? 0);
            $del = $conn->prepare("DELETE FROM students WHERE id=?");
            $del->bind_param('i', $student_id);
            $del->execute();
            $del->close();
            if ($user_id > 0) {
                $du = $conn->prepare("DELETE FROM users WHERE id=? AND role='student'");
                $du->bind_param('i', $user_id);
                $du->execute();
                $du->close();
            }
            $success_msg = 'Student deleted.';
        } elseif ($action === 'create_login') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? 'password';
            $s = $conn->prepare("SELECT id, user_id, first_name FROM students WHERE id=?");
            $s->bind_param('i', $student_id);
            $s->execute();
            $stu = $s->get_result()->fetch_assoc();
            $s->close();
            if (!$stu || $stu['user_id']) {
                $error_msg = 'Student already has a login or was not found.';
            } elseif ($username === '' || $email === '') {
                $error_msg = 'Username and email are required.';
            } else {
                $role = 'student';
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $u = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?,?,?,?)");
                $u->bind_param('ssss', $username, $email, $hash, $role);
                if ($u->execute()) {
                    $uid = (int)$conn->insert_id;
                    $link = $conn->prepare("UPDATE students SET user_id=? WHERE id=?");
                    $link->bind_param('ii', $uid, $student_id);
                    $link->execute();
                    $link->close();
                    $success_msg = 'Login created for ' . e($stu['first_name']) . '.';
                } else {
                    $error_msg = 'Could not create login (username/email may be taken).';
                }
                $u->close();
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filter_class = (int)($_GET['class_id'] ?? 0);
$sql = "SELECT s.*, u.username, u.email, c.class_name, c.section
        FROM students s
        LEFT JOIN users u ON u.id = s.user_id
        LEFT JOIN classes c ON c.id = s.class_id
        WHERE 1=1";
$types = '';
$params = [];
if ($search !== '') {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_number LIKE ? OR s.parent_phone LIKE ? OR u.username LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($filter_class > 0) {
    $sql .= " AND s.class_id = ?";
    $types .= 'i';
    $params[] = $filter_class;
}
$sql .= " ORDER BY s.id DESC";
$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();

$classes = $conn->query("SELECT id, class_name, section FROM classes ORDER BY class_name, section")->fetch_all(MYSQLI_ASSOC);
$next_adm = 'ADM-' . date('Y') . '-' . str_pad((string)((int)$conn->query("SELECT COUNT(*) AS c FROM students")->fetch_assoc()['c'] + 1), 3, '0', STR_PAD_LEFT);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($success_msg): ?><div class="alert alert-success"><?php echo $success_msg; ?></div><?php endif; ?>
<?php if ($error_msg): ?><div class="alert alert-danger"><?php echo e($error_msg); ?></div><?php endif; ?>

<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <form class="d-flex gap-2 flex-wrap" method="get">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search students" value="<?php echo e($search); ?>">
        <select name="class_id" class="form-select form-select-sm" style="width:auto;">
            <option value="0">All classes</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_class===(int)$c['id']?'selected':''; ?>><?php echo e($c['class_name'].' · '.$c['section']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="fa-solid fa-plus me-1"></i> Admit Student</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th></th><th>Admission</th><th>Name</th><th>Class</th><th>Login</th><th>Parent</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php if ($students->num_rows === 0): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-user-graduate"></i><p>No students found</p></div></td></tr>
                <?php else: while ($s = $students->fetch_assoc()):
                    $photo = $s['photo'] && $s['photo'] !== 'default.png' ? '../uploads/' . $s['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($s['first_name'].' '.$s['last_name']) . '&background=CCFBF1&color=115E59';
                ?>
                    <tr>
                        <td><img src="<?php echo e($photo); ?>" class="photo-thumb" alt=""></td>
                        <td><?php echo e($s['admission_number']); ?></td>
                        <td class="fw-semibold"><?php echo e($s['first_name'].' '.$s['last_name']); ?></td>
                        <td><?php echo e(($s['class_name'] ?? '—') . (isset($s['section']) ? ' · '.$s['section'] : '')); ?></td>
                        <td><?php echo $s['username'] ? '<code>'.e($s['username']).'</code>' : '<span class="text-muted">No login</span>'; ?></td>
                        <td><?php echo e($s['parent_name']); ?><div class="small text-muted"><?php echo e($s['parent_phone']); ?></div></td>
                        <td><span class="status-pill status-<?php echo e($s['status']); ?>"><?php echo e($s['status']); ?></span></td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStudent<?php echo (int)$s['id']; ?>">Edit</button>
                            <?php if (!$s['user_id']): ?>
                                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#loginStudent<?php echo (int)$s['id']; ?>">Login</button>
                            <?php endif; ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this student?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_student">
                                <input type="hidden" name="student_id" value="<?php echo (int)$s['id']; ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int)$s['user_id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>

                    <div class="modal fade" id="editStudent<?php echo (int)$s['id']; ?>" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
                        <form method="post" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="edit_student">
                            <input type="hidden" name="student_id" value="<?php echo (int)$s['id']; ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int)$s['user_id']; ?>">
                            <input type="hidden" name="existing_photo" value="<?php echo e($s['photo']); ?>">
                            <div class="modal-header"><h5 class="modal-title">Edit Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="row g-2">
                                    <div class="col-md-4"><label class="form-label">Admission #</label><input name="admission_number" class="form-control" value="<?php echo e($s['admission_number']); ?>" required></div>
                                    <div class="col-md-4"><label class="form-label">First Name</label><input name="first_name" class="form-control" value="<?php echo e($s['first_name']); ?>" required></div>
                                    <div class="col-md-4"><label class="form-label">Last Name</label><input name="last_name" class="form-control" value="<?php echo e($s['last_name']); ?>" required></div>
                                    <div class="col-md-4"><label class="form-label">Gender</label>
                                        <select name="gender" class="form-select"><?php foreach (['Male','Female','Other'] as $g): ?><option <?php echo $s['gender']===$g?'selected':''; ?>><?php echo $g; ?></option><?php endforeach; ?></select>
                                    </div>
                                    <div class="col-md-4"><label class="form-label">DOB</label><input type="date" name="date_of_birth" class="form-control" value="<?php echo e($s['date_of_birth']); ?>" required></div>
                                    <div class="col-md-4"><label class="form-label">Class</label>
                                        <select name="class_id" class="form-select">
                                            <option value="">—</option>
                                            <?php foreach ($classes as $c): ?>
                                                <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$s['class_id']===(int)$c['id']?'selected':''; ?>><?php echo e($c['class_name'].' · '.$c['section']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">Parent Name</label><input name="parent_name" class="form-control" value="<?php echo e($s['parent_name']); ?>" required></div>
                                    <div class="col-md-6"><label class="form-label">Parent Phone</label><input name="parent_phone" class="form-control" value="<?php echo e($s['parent_phone']); ?>" required></div>
                                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2" required><?php echo e($s['address']); ?></textarea></div>
                                    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?php echo e($s['email'] ?? ''); ?>"></div>
                                    <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep"></div>
                                    <div class="col-md-4"><label class="form-label">Status</label>
                                        <select name="status" class="form-select"><option <?php echo $s['status']==='Active'?'selected':''; ?>>Active</option><option <?php echo $s['status']==='Inactive'?'selected':''; ?>>Inactive</option></select>
                                    </div>
                                    <div class="col-12"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp"></div>
                                </div>
                            </div>
                            <div class="modal-footer"><button class="btn btn-primary">Save Changes</button></div>
                        </form>
                    </div></div></div>

                    <?php if (!$s['user_id']): ?>
                    <div class="modal fade" id="loginStudent<?php echo (int)$s['id']; ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <form method="post">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="create_login">
                            <input type="hidden" name="student_id" value="<?php echo (int)$s['id']; ?>">
                            <div class="modal-header"><h5 class="modal-title">Create Login</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" value="<?php echo e(strtolower($s['first_name'])); ?>" required></div>
                                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Password</label><input name="password" class="form-control" value="password" required></div>
                            </div>
                            <div class="modal-footer"><button class="btn btn-primary">Create Login</button></div>
                        </form>
                    </div></div></div>
                    <?php endif; ?>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addStudentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add_student">
        <div class="modal-header"><h5 class="modal-title">Admit Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label">Admission # *</label><input name="admission_number" class="form-control" value="<?php echo e($next_adm); ?>" required></div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input name="first_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input name="last_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Gender *</label><select name="gender" class="form-select" required><option>Male</option><option>Female</option><option>Other</option></select></div>
                <div class="col-md-4"><label class="form-label">DOB *</label><input type="date" name="date_of_birth" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Class *</label>
                    <select name="class_id" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach ($classes as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['class_name'].' · '.$c['section']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Parent Name *</label><input name="parent_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Parent Phone *</label><input name="parent_phone" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Address *</label><textarea name="address" class="form-control" rows="2" required></textarea></div>
                <div class="col-md-4"><label class="form-label">Username *</label><input name="username" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" value="password" required></div>
                <div class="col-12"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp"></div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Admit Student</button></div>
    </form>
</div></div></div>

<?php include '../includes/footer.php'; ?>
