<?php
// index.php - CampusFlow Public Landing Page
require_once __DIR__ . '/includes/auth.php';

$is_logged_in = isset($_SESSION['user_id'], $_SESSION['role']);
$dashboard_url = $is_logged_in ? dashboard_for_role($_SESSION['role']) : 'login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampusFlow — Modern School Management System</title>
    
    <!-- Bootstrap 5 CSS for responsive grid and components -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ==========================================================================
           CampusFlow Landing Page Styles (Modern Growth & Eco Palette)
           ========================================================================== */
        :root {
            --primary-emerald: #10B981;
            --primary-hover: #059669;
            --forest-green: #065F46;
            --mint-tint: #D1FAE5;
            --slate-dark: #0F172A;
            --slate-muted: #64748B;
            --bg-offwhite: #F8FAFC;
            --border-light: #E2E8F0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-offwhite);
            color: var(--slate-dark);
            font-size: 0.925rem;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sticky Top Navbar */
        .landing-navbar {
            background-color: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-light);
            padding: 0.9rem 0;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--slate-dark);
            text-decoration: none;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            background-color: var(--primary-emerald);
            color: #FFFFFF;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .nav-link-custom {
            color: var(--slate-muted);
            font-weight: 500;
            text-decoration: none;
            padding: 0.5rem 1rem;
            transition: color 0.2s;
        }

        .nav-link-custom:hover {
            color: var(--forest-green);
        }

        /* Buttons */
        .btn-emerald {
            background-color: var(--primary-emerald);
            border: 1px solid var(--primary-emerald);
            color: #FFFFFF;
            font-weight: 600;
            padding: 0.6rem 1.35rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-emerald:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        .btn-outline-slate {
            background-color: #FFFFFF;
            border: 1px solid var(--border-light);
            color: var(--slate-dark);
            font-weight: 600;
            padding: 0.6rem 1.35rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-outline-slate:hover {
            background-color: var(--mint-tint);
            border-color: var(--primary-emerald);
            color: var(--forest-green);
        }

        /* Hero Section */
        .hero-section {
            padding: 5rem 0 4.5rem 0;
            background: radial-gradient(circle at top right, rgba(209, 250, 229, 0.6), transparent 45%),
                        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.08), transparent 40%);
        }

        .badge-pill-mint {
            background-color: var(--mint-tint);
            color: var(--forest-green);
            font-weight: 600;
            font-size: 0.78rem;
            padding: 0.4rem 0.9rem;
            border-radius: 50rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .hero-title {
            font-size: 2.75rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.2;
            color: var(--slate-dark);
        }

        .hero-title span {
            color: var(--forest-green);
        }

        /* Interactive Dashboard Preview Card in Hero */
        .preview-window {
            background-color: var(--slate-dark);
            border-radius: 14px;
            padding: 1rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.22);
            border: 1px solid #1E293B;
        }

        .preview-topbar {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0.85rem;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .preview-body {
            background-color: var(--bg-offwhite);
            border-radius: 10px;
            padding: 1.25rem;
            color: var(--slate-dark);
        }

        /* Feature Cards */
        .feature-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.75rem;
            height: 100%;
            transition: all 0.25s ease;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -5px rgba(15, 23, 42, 0.08);
            border-color: var(--primary-emerald);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background-color: var(--mint-tint);
            color: var(--forest-green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1.15rem;
        }

        /* Home Page Footer */
        .landing-footer {
            background-color: var(--slate-dark);
            color: #94A3B8;
            padding: 3rem 0 2rem 0;
            margin-top: 4rem;
        }
    </style>
</head>
<body>

    <!-- 1. Top Navigation Bar -->
    <header class="landing-navbar">
        <div class="container d-flex align-items-center justify-content-between">
            <!-- Brand Logo -->
            <a href="index.php" class="brand-logo">
                <div class="brand-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <span>CampusFlow</span>
            </a>

            <!-- Center Navigation Links (Hidden on small mobile screens) -->
            <nav class="d-none d-md-flex align-items-center">
                <a href="#features" class="nav-link-custom">Features</a>
                <a href="#modules" class="nav-link-custom">System Modules</a>
                <a href="#security" class="nav-link-custom">Architecture</a>
            </nav>

            <!-- Right Side Action Buttons (Changes dynamically if logged in!) -->
            <div class="d-flex align-items-center gap-2">
                <?php if ($is_logged_in): ?>
                    <span class="text-muted small me-2 d-none d-sm-inline">
                        Signed in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                    </span>
                    <a href="<?php echo htmlspecialchars($dashboard_url); ?>" class="btn-emerald">
                        <i class="fa-solid fa-gauge-high"></i> Go to Dashboard
                    </a>
                    <a href="logout.php" class="btn-outline-slate" title="Logout">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-emerald">
                        <i class="fa-solid fa-lock"></i> Sign In to Portal
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- 2. Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                
                <!-- Left Column: Headline & Call to Action -->
                <div class="col-lg-6">
                    <div class="badge-pill-mint mb-3">
                        <i class="fa-solid fa-sparkles"></i> Next-Generation School ERP
                    </div>
                    <h1 class="hero-title mb-3">
                        Smart Administration for <span>Modern Schools.</span>
                    </h1>
                    <p class="text-muted fs-6 mb-4" style="max-width: 520px;">
                        CampusFlow unifies student admissions, daily attendance tracking, fee collection receipts, and academic grading into one fast, secure web platform.
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3">
                        <?php if ($is_logged_in): ?>
                            <a href="<?php echo htmlspecialchars($dashboard_url); ?>" class="btn-emerald px-4 py-2">
                                Open Admin Dashboard <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn-emerald px-4 py-2">
                                Access Portal <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                        <a href="#features" class="btn-outline-slate px-4 py-2">
                            Explore Modules
                        </a>
                    </div>

                    <div class="d-flex align-items-center gap-4 mt-4 pt-2 text-muted small">
                        <div><i class="fa-solid fa-circle-check text-success me-1"></i> Role-Based Access</div>
                        <div><i class="fa-solid fa-circle-check text-success me-1"></i> Real-Time MySQL Stats</div>
                        <div><i class="fa-solid fa-circle-check text-success me-1"></i> Printable Receipts</div>
                    </div>
                </div>

                <!-- Right Column: Visual Dashboard Preview Mockup -->
                <div class="col-lg-6">
                    <div class="preview-window">
                        <div class="preview-topbar">
                            <span class="dot" style="background: #EF4444;"></span>
                            <span class="dot" style="background: #F59E0B;"></span>
                            <span class="dot" style="background: #10B981;"></span>
                            <span class="ms-2 text-white-50" style="font-size: 0.72rem;">campusflow.local/admin/dashboard.php</span>
                        </div>
                        <div class="preview-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0">CampusFlow Overview</h6>
                                    <small class="text-muted" style="font-size: 0.75rem;">Academic Year 2026–2027</small>
                                </div>
                                <span class="badge-pill-mint">System Online</span>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="p-3 bg-white rounded border">
                                        <i class="fa-solid fa-user-group text-success mb-2"></i>
                                        <h4 class="fw-bold mb-0">1,240</h4>
                                        <small class="text-muted">Active Students</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-white rounded border">
                                        <i class="fa-solid fa-book-open text-success mb-2"></i>
                                        <h4 class="fw-bold mb-0">32</h4>
                                        <small class="text-muted">Classes &amp; Sections</small>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3 bg-white rounded border">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold small">Daily Attendance Rate</span>
                                    <span class="badge-pill-mint" style="font-size: 0.7rem;">Excellent (94.5%)</span>
                                </div>
                                <div class="progress mt-2" style="height: 8px; background-color: #E2E8F0;">
                                    <div class="progress-bar" style="width: 94.5%; background-color: #10B981;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 3. Core Modules Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center max-w-2xl mx-auto mb-5">
                <span class="badge-pill-mint mb-2">Core Capabilities</span>
                <h2 class="fw-bold">Everything Needed to Run Your School</h2>
                <p class="text-muted">Designed with a clean interface for administrators, teachers, and students.</p>
            </div>

            <div id="modules" class="row g-4">
                <!-- Module 1 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Student Admissions</h6>
                        <p class="text-muted small mb-0">
                            Complete student profiles, photo uploads, unique admission numbers, class assignments, and parent contact records.
                        </p>
                    </div>
                </div>

                <!-- Module 2 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fa-solid fa-calendar-check"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Smart Attendance</h6>
                        <p class="text-muted small mb-0">
                            Mark daily class attendance (Present, Absent, Late, Leave) with duplicate prevention and automated percentage calculation.
                        </p>
                    </div>
                </div>

                <!-- Module 3 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Fee &amp; Receipts</h6>
                        <p class="text-muted small mb-0">
                            Assign fee structures, record full or partial payments, track pending balances, and print official receipts.
                        </p>
                    </div>
                </div>

                <!-- Module 4 -->
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fa-solid fa-award"></i>
                        </div>
                        <h6 class="fw-bold mb-2">Exams &amp; Report Cards</h6>
                        <p class="text-muted small mb-0">
                            Schedule examinations, enter subject-wise marks, and automatically compute percentages and letter grades.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. Security & Architecture Banner -->
    <section id="security" class="py-4">
        <div class="container">
            <div class="p-4 p-md-5 rounded-4 text-white" style="background: linear-gradient(135deg, #0F172A 0%, #065F46 100%);">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <h4 class="fw-bold mb-2"><i class="fa-solid fa-shield-halved text-success me-2"></i>Built with Modern PHP &amp; MySQL Security</h4>
                        <p class="text-white-50 small mb-0">
                            Protected with MySQLi prepared statements, Bcrypt password hashing, CSRF form tokens, and strict server-side role-based access control.
                        </p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a href="<?php echo htmlspecialchars($dashboard_url); ?>" class="btn-emerald">
                            <?php echo $is_logged_in ? 'Return to Dashboard' : 'Sign In as Administrator'; ?>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. Landing Page Footer -->
    <footer class="landing-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2 text-white fw-bold">
                <i class="fa-solid fa-graduation-cap text-success"></i>
                <span>CampusFlow</span>
                <span class="text-secondary fw-normal small ms-2">— School Management System</span>
            </div>
            <div class="small">
                &copy; <?php echo date('Y'); ?> CampusFlow. Built with PHP, MySQLi &amp; Bootstrap 5.
            </div>
        </div>
    </footer>

</body>
</html>