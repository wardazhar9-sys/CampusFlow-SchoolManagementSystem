<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    redirect(dashboard_for_role($_SESSION['role']));
}

$error_message = '';
$logged_out = isset($_GET['logged_out']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error_message = 'Invalid form submission. Please refresh and try again.';
    } else {
        $login_input = trim($_POST['login_input'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($login_input === '' || $password === '') {
            $error_message = 'Please enter both your username/email and password.';
        } else {
            $sql = "SELECT id, username, email, password, role, status FROM users WHERE username = ? OR email = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ss', $login_input, $login_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if ($user['status'] !== 'Active') {
                    $error_message = 'Your account is inactive. Contact the administrator.';
                } elseif (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    redirect(dashboard_for_role($user['role']));
                } else {
                    $error_message = 'Invalid username/email or password.';
                }
            } else {
                $error_message = 'Invalid username/email or password.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — CampusFlow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0D9488;
            --primary-hover: #0F766E;
            --forest: #115E59;
            --slate: #0F172A;
        }
        body {
            font-family: 'DM Sans', system-ui, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(ellipse 70% 50% at 10% 10%, rgba(13,148,136,0.18), transparent),
                radial-gradient(ellipse 50% 40% at 90% 90%, rgba(15,23,42,0.08), transparent),
                #F1F5F9;
            padding: 1.5rem;
        }
        .login-wrapper {
            width: 100%;
            max-width: 920px;
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 24px 48px -20px rgba(15,23,42,0.25);
            border: 1px solid #E2E8F0;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
        }
        .login-aside {
            background: linear-gradient(160deg, #115E59 0%, #0F766E 50%, #14B8A6 100%);
            color: #fff;
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .login-aside::after {
            content: '';
            position: absolute;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            right: -60px; bottom: -40px;
        }
        .brand {
            display: flex; align-items: center; gap: 0.75rem;
            font-family: 'Fraunces', Georgia, serif;
            font-size: 1.4rem; font-weight: 700;
        }
        .brand-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
        }
        .login-form-pane { padding: 2.5rem; }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(13,148,136,0.15);
        }
        .btn-login {
            background: var(--primary); border: none; color: #fff;
            font-weight: 600; padding: 0.7rem;
            border-radius: 10px;
        }
        .btn-login:hover { background: var(--primary-hover); color: #fff; }
        .demo-box {
            background: #F8FAFC; border: 1px dashed #CBD5E1;
            border-radius: 10px; padding: 0.85rem 1rem; font-size: 0.8rem;
        }
        @media (max-width: 768px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-aside { min-height: 180px; padding: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <aside class="login-aside">
        <div>
            <div class="brand mb-4">
                <div class="brand-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                CampusFlow
            </div>
            <h2 style="font-family:'Fraunces',Georgia,serif;font-size:1.75rem;line-height:1.25;">
                School operations,<br>beautifully organized.
            </h2>
            <p class="mt-3 mb-0" style="opacity:0.9;font-size:0.95rem;">
                Sign in to access your admin, teacher, or student portal.
            </p>
        </div>
        <div style="font-size:0.8rem;opacity:0.75;position:relative;z-index:1;">
            &copy; <?php echo date('Y'); ?> CampusFlow Academy
        </div>
    </aside>
    <div class="login-form-pane">
        <h3 class="mb-1" style="font-family:'Fraunces',Georgia,serif;">Welcome back</h3>
        <p class="text-muted mb-4">Enter your credentials to continue</p>

        <?php if ($logged_out): ?>
            <div class="alert alert-success py-2">You have been logged out successfully.</div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger py-2"><?php echo e($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <input type="text" name="login_input" class="form-control form-control-lg" required autofocus
                       value="<?php echo e($_POST['login_input'] ?? ''); ?>" placeholder="admin">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control form-control-lg" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-login w-100 mb-3">Sign In</button>
        </form>

        <div class="demo-box">
            <strong>Demo accounts</strong> (password: <code>password</code>)<br>
            Admin: <code>admin</code> · Teacher: <code>wardah</code> · Student: <code>abdullah</code>
        </div>
        <div class="text-center mt-3">
            <a href="index.php" class="text-decoration-none" style="color:var(--primary);font-size:0.875rem;">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to home
            </a>
        </div>
    </div>
</div>
</body>
</html>
