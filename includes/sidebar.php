<?php
$role = $_SESSION['role'] ?? 'admin';
$username = $_SESSION['username'] ?? 'User';
$email = $_SESSION['email'] ?? '';
$unread = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
    $unread = unread_notification_count($conn, (int)$_SESSION['user_id']);
}
$logout_url = url('logout.php');
$avatar = 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=10B981&color=fff';
?>
<nav class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="fa-solid fa-graduation-cap"></i></div>
        <span>CampusFlow</span>
    </div>

    <ul class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <li class="menu-header">Main</li>
            <li class="<?php echo active_page('dashboard.php'); ?>">
                <a href="<?php echo url('admin/dashboard.php'); ?>"><i class="fa-solid fa-house"></i> Dashboard</a>
            </li>
            <li class="menu-header">People</li>
            <li class="<?php echo active_page('students.php'); ?>">
                <a href="<?php echo url('admin/students.php'); ?>"><i class="fa-solid fa-user-graduate"></i> Students</a>
            </li>
            <li class="<?php echo active_page('teachers.php'); ?>">
                <a href="<?php echo url('admin/teachers.php'); ?>"><i class="fa-solid fa-chalkboard-user"></i> Teachers</a>
            </li>
            <li class="menu-header">Academics</li>
            <li class="<?php echo active_page('classes.php'); ?>">
                <a href="<?php echo url('admin/classes.php'); ?>"><i class="fa-solid fa-school"></i> Classes & Subjects</a>
            </li>
            <li class="<?php echo active_page('attendance.php'); ?>">
                <a href="<?php echo url('admin/attendance.php'); ?>"><i class="fa-solid fa-calendar-check"></i> Attendance</a>
            </li>
            <li class="<?php echo active_page('timetable.php'); ?>">
                <a href="<?php echo url('admin/timetable.php'); ?>"><i class="fa-solid fa-table"></i> Timetable</a>
            </li>
            <li class="<?php echo active_page('exams.php'); ?>">
                <a href="<?php echo url('admin/exams.php'); ?>"><i class="fa-solid fa-award"></i> Examinations</a>
            </li>
            <li class="<?php echo active_page('assignments.php'); ?>">
                <a href="<?php echo url('admin/assignments.php'); ?>"><i class="fa-solid fa-book-open"></i> Assignments</a>
            </li>
            <li class="menu-header">Operations</li>
            <li class="<?php echo active_page('fees.php'); ?>">
                <a href="<?php echo url('admin/fees.php'); ?>"><i class="fa-solid fa-file-invoice-dollar"></i> Fee Management</a>
            </li>
            <li class="<?php echo active_page('notices.php'); ?>">
                <a href="<?php echo url('admin/notices.php'); ?>"><i class="fa-solid fa-bullhorn"></i> Notices</a>
            </li>
            <li class="<?php echo active_page('leaves.php'); ?>">
                <a href="<?php echo url('admin/leaves.php'); ?>"><i class="fa-solid fa-plane-departure"></i> Leave Requests</a>
            </li>
            <li class="menu-header">System</li>
            <li class="<?php echo active_page('settings.php'); ?>">
                <a href="<?php echo url('admin/settings.php'); ?>"><i class="fa-solid fa-gear"></i> Settings</a>
            </li>

        <?php elseif ($role === 'teacher'): ?>
            <li class="menu-header">Main</li>
            <li class="<?php echo active_page('dashboard.php'); ?>">
                <a href="<?php echo url('teacher/dashboard.php'); ?>"><i class="fa-solid fa-house"></i> Dashboard</a>
            </li>
            <li class="menu-header">Teaching</li>
            <li class="<?php echo active_page('classes.php'); ?>">
                <a href="<?php echo url('teacher/classes.php'); ?>"><i class="fa-solid fa-users"></i> My Classes</a>
            </li>
            <li class="<?php echo active_page('attendance.php'); ?>">
                <a href="<?php echo url('teacher/attendance.php'); ?>"><i class="fa-solid fa-calendar-check"></i> Attendance</a>
            </li>
            <li class="<?php echo active_page('marks.php'); ?>">
                <a href="<?php echo url('teacher/marks.php'); ?>"><i class="fa-solid fa-pen-to-square"></i> Enter Marks</a>
            </li>
            <li class="<?php echo active_page('timetable.php'); ?>">
                <a href="<?php echo url('teacher/timetable.php'); ?>"><i class="fa-solid fa-table"></i> Timetable</a>
            </li>
            <li class="<?php echo active_page('assignments.php'); ?>">
                <a href="<?php echo url('teacher/assignments.php'); ?>"><i class="fa-solid fa-book-open"></i> Assignments</a>
            </li>
            <li class="menu-header">Account</li>
            <li class="<?php echo active_page('leaves.php'); ?>">
                <a href="<?php echo url('teacher/leaves.php'); ?>"><i class="fa-solid fa-plane-departure"></i> Leave</a>
            </li>
            <li class="<?php echo active_page('notices.php'); ?>">
                <a href="<?php echo url('teacher/notices.php'); ?>"><i class="fa-solid fa-bullhorn"></i> Notices
                    <?php if ($unread > 0): ?><span class="badge bg-danger ms-auto"><?php echo $unread; ?></span><?php endif; ?>
                </a>
            </li>
            <li class="<?php echo active_page('profile.php'); ?>">
                <a href="<?php echo url('teacher/profile.php'); ?>"><i class="fa-solid fa-user"></i> Profile</a>
            </li>

        <?php else: ?>
            <li class="menu-header">Main</li>
            <li class="<?php echo active_page('dashboard.php'); ?>">
                <a href="<?php echo url('student/dashboard.php'); ?>"><i class="fa-solid fa-house"></i> Dashboard</a>
            </li>
            <li class="menu-header">My School</li>
            <li class="<?php echo active_page('attendance.php'); ?>">
                <a href="<?php echo url('student/attendance.php'); ?>"><i class="fa-solid fa-calendar-check"></i> Attendance</a>
            </li>
            <li class="<?php echo active_page('timetable.php'); ?>">
                <a href="<?php echo url('student/timetable.php'); ?>"><i class="fa-solid fa-table"></i> Timetable</a>
            </li>
            <li class="<?php echo active_page('assignments.php'); ?>">
                <a href="<?php echo url('student/assignments.php'); ?>"><i class="fa-solid fa-book-open"></i> Assignments</a>
            </li>
            <li class="<?php echo active_page('results.php'); ?>">
                <a href="<?php echo url('student/results.php'); ?>"><i class="fa-solid fa-award"></i> Results</a>
            </li>
            <li class="<?php echo active_page('fees.php'); ?>">
                <a href="<?php echo url('student/fees.php'); ?>"><i class="fa-solid fa-file-invoice-dollar"></i> Fees</a>
            </li>
            <li class="menu-header">Account</li>
            <li class="<?php echo active_page('notices.php'); ?>">
                <a href="<?php echo url('student/notices.php'); ?>"><i class="fa-solid fa-bullhorn"></i> Notices
                    <?php if ($unread > 0): ?><span class="badge bg-danger ms-auto"><?php echo $unread; ?></span><?php endif; ?>
                </a>
            </li>
            <li class="<?php echo active_page('profile.php'); ?>">
                <a href="<?php echo url('student/profile.php'); ?>"><i class="fa-solid fa-user"></i> Profile</a>
            </li>
        <?php endif; ?>

        <li class="menu-header">Session</li>
        <li>
            <a href="<?php echo $logout_url; ?>"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </li>
    </ul>
</nav>

<div class="app-main">
    <header class="app-navbar">
        <div class="d-flex align-items-center gap-3">
            <button type="button" class="btn btn-sm btn-light border" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h5 class="mb-0 fw-semibold text-dark page-heading"><?php echo e($page_title); ?></h5>
        </div>

        <div class="d-flex align-items-center gap-3">
            <?php if ($unread > 0 && $role === 'admin'): ?>
                <a href="<?php echo url('admin/leaves.php'); ?>" class="btn btn-sm btn-light border position-relative" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $unread; ?></span>
                </a>
            <?php endif; ?>
            <div class="dropdown navbar-user-dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="<?php echo e($avatar); ?>" alt="User">
                    <div class="d-none d-md-block text-start">
                        <span class="d-block fw-semibold text-dark" style="font-size:0.825rem;"><?php echo e(ucfirst($username)); ?></span>
                        <span class="text-muted" style="font-size:0.725rem;"><?php echo e(ucfirst($role)); ?></span>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <?php if ($role === 'teacher'): ?>
                        <li><a class="dropdown-item" href="<?php echo url('teacher/profile.php'); ?>"><i class="fa-solid fa-user me-2 text-muted"></i> Profile</a></li>
                    <?php elseif ($role === 'student'): ?>
                        <li><a class="dropdown-item" href="<?php echo url('student/profile.php'); ?>"><i class="fa-solid fa-user me-2 text-muted"></i> Profile</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="<?php echo url('admin/settings.php'); ?>"><i class="fa-solid fa-gear me-2 text-muted"></i> Settings</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo $logout_url; ?>"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="app-content">
