<?php
// Shared helper functions for CampusFlow

if (!function_exists('app_base_path')) {
    /**
     * Detect project URL base path (e.g. /school-management-system_)
     */
    function app_base_path(): string {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        // Strip known portal folders and root PHP files
        $base = preg_replace('#/(admin|teacher|student)(/.*)?$#', '', $script);
        $base = preg_replace('#/(index|login|logout)\.php$#', '', $base);
        $base = rtrim($base ?? '', '/');
        if ($base === '' || $base === false) {
            $base = '';
        }
        return $base;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        $path = ltrim($path, '/');
        $base = app_base_path();
        return ($base === '' ? '' : $base) . '/' . $path;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void {
        header('Location: ' . url($path));
        exit();
    }
}

if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        $token = $_SESSION['csrf_token'] ?? '';
        return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): bool {
        return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $type, string $message): void {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_render')) {
    function flash_render(): void {
        if (empty($_SESSION['flash'])) {
            return;
        }
        $type = $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger';
        $icon = $type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
            . '<i class="fa-solid ' . $icon . ' me-2"></i>'
            . $_SESSION['flash']['message']
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        unset($_SESSION['flash']);
    }
}

if (!function_exists('create_notification')) {
    function create_notification(mysqli $conn, int $user_id, string $title, string $message, ?string $link = null): void {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $message, $link);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('notify_role_users')) {
    function notify_role_users(mysqli $conn, string $role, string $title, string $message, ?string $link = null): void {
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = ? AND status = 'Active'");
        $stmt->bind_param("s", $role);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            create_notification($conn, (int)$row['id'], $title, $message, $link);
        }
        $stmt->close();
    }
}

if (!function_exists('unread_notification_count')) {
    function unread_notification_count(mysqli $conn, int $user_id): int {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        return $count;
    }
}

if (!function_exists('letter_grade')) {
    function letter_grade(float $percent): string {
        if ($percent >= 90) return 'A+';
        if ($percent >= 80) return 'A';
        if ($percent >= 70) return 'B';
        if ($percent >= 60) return 'C';
        if ($percent >= 50) return 'D';
        return 'F';
    }
}

if (!function_exists('get_setting')) {
    function get_setting(mysqli $conn, string $key, string $default = ''): string {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ? (string)$row['setting_value'] : $default;
    }
}

if (!function_exists('set_setting')) {
    function set_setting(mysqli $conn, string $key, string $value): void {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param("ss", $key, $value);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('current_teacher_id')) {
    function current_teacher_id(mysqli $conn): ?int {
        if (empty($_SESSION['user_id'])) return null;
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }
}

if (!function_exists('current_student_id')) {
    function current_student_id(mysqli $conn): ?int {
        if (empty($_SESSION['user_id'])) return null;
        $uid = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }
}

if (!function_exists('fee_status_from_amounts')) {
    function fee_status_from_amounts(float $total, float $paid): string {
        if ($paid <= 0) return 'Unpaid';
        if ($paid + 0.001 >= $total) return 'Paid';
        return 'Partial';
    }
}

if (!function_exists('active_page')) {
    function active_page(string $file): string {
        return basename($_SERVER['PHP_SELF'] ?? '') === $file ? 'active' : '';
    }
}
