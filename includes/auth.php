<?php
// Session management & role-based access control

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_login(): void {
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        redirect('login.php');
    }
}

function require_role($allowed_role): void {
    require_login();

    $allowed = is_array($allowed_role) ? $allowed_role : [$allowed_role];
    if (!in_array($_SESSION['role'], $allowed, true)) {
        http_response_code(403);
        $login = url('login.php');
        echo "<!DOCTYPE html><html><head><title>Access Denied</title>
              <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'></head>
              <body class='bg-light'><div class='container py-5 text-center'>
              <h2 class='mb-3'>403 — Access Denied</h2>
              <p class='text-muted'>You do not have permission to view this page.</p>
              <a class='btn btn-primary' href='{$login}'>Return to Login</a>
              </div></body></html>";
        exit();
    }
}

function dashboard_for_role(string $role): string {
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
        default => 'login.php',
    };
}
