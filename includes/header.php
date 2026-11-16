<?php
/**
 * Global Header Component
 * Handles public and authenticated application layouts
 */

require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'Secure College Voting';
$siteName = get_setting('site_name', 'ApexVote');
$isDashboard = $isDashboard ?? false;
$isAdmin = $isAdmin ?? false;
$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e($siteName) ?></title>
    
    <!-- Google Fonts: Editorial & Swiss Grotesk Pairing -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Application Stylesheet with Automatic Cache Invalidation -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
    <link rel="stylesheet" href="<?= asset('css/kolkata-cube.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/kolkata-cube.css') ?: time() ?>">
    <link rel="icon" type="image/png" href="<?= asset('images/logo.png') ?>">
    
    <!-- Chart.js for Visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Three.js 3D WebGL Library (Local with CDN Fallback) -->
    <script src="<?= asset('js/vendor/three.min.js') ?>"></script>
    <script>
        if (typeof THREE === 'undefined') {
            document.write('<script src="https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js"><\/script>');
        }
    </script>
</head>
<body class="<?= $isDashboard ? 'dashboard-mode' : 'public-mode' ?>">
    <!-- Background Texture & Architectural Registration Crosshairs -->
    <div class="editorial-canvas-bg" aria-hidden="true">
        <div class="canvas-grid-crosshairs"></div>
        <div class="canvas-radial-accent"></div>
    </div>

    <!-- Notification Toast Container -->
    <div id="toast-container"></div>

    <?php if ($flash): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                showToast('<?= e($flash['type']) ?>', '<?= addslashes($flash['message']) ?>');
            });
        </script>
    <?php endif; ?>

    <?php if ($isDashboard): ?>
    <!-- ================= AUTHENTICATED DASHBOARD LAYOUT ================= -->
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar voter-sidebar" id="appSidebar">
            <div class="sidebar-header sidebar-brand">
                <a href="<?= url($isAdmin ? 'admin/dashboard.php' : 'dashboard.php') ?>" class="brand" aria-label="ApexVote Dashboard">
                    <span class="brand-symbol" aria-hidden="true">
                        <img src="<?= asset('images/logo.png') ?>" alt="ApexVote Logo" class="brand-icon" style="width: 32px; height: 32px; object-fit: contain;">
                    </span>
                    <div class="brand-text-group">
                        <span class="brand-title">Apex<span class="brand-title-accent">Vote</span></span>
                        <span class="brand-descriptor">College Electoral System</span>
                    </div>
                </a>
            </div>

            <ul class="sidebar-menu sidebar-navigation">
                <?php if ($isAdmin): ?>
                    <li class="sidebar-category">College Election Administration</li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-link <?= is_active_page('dashboard.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                            </span>
                            <span>Overview & Metrics</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/manage-elections.php') ?>" class="sidebar-link <?= is_active_page('manage-elections.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"></path><path d="M5 7c0-1.1.9-2 2-2h10a2 2 0 0 1 2 2v12H5V7Z"></path><path d="M22 19H2"></path></svg>
                            </span>
                            <span>Manage Elections</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/manage-candidates.php') ?>" class="sidebar-link <?= is_active_page('manage-candidates.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            </span>
                            <span>Manage Candidates</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/authorized-students.php') ?>" class="sidebar-link <?= is_active_page('authorized-students.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                            </span>
                            <span>Student Roster</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/manage-voters.php') ?>" class="sidebar-link <?= is_active_page('manage-voters.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
                            </span>
                            <span>Voter Registry</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/manage-results.php') ?>" class="sidebar-link <?= is_active_page('manage-results.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path></svg>
                            </span>
                            <span>Results & Certification</span>
                        </a>
                    </li>
                    
                    <li class="sidebar-category">Security & Governance</li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/security-overview.php') ?>" class="sidebar-link <?= is_active_page('security-overview.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            </span>
                            <span>Security Overview</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/security-test-suite.php') ?>" class="sidebar-link <?= is_active_page('security-test-suite.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="m9 15 2 2 4-4"></path></svg>
                            </span>
                            <span>Security Test Suite</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/audit-logs.php') ?>" class="sidebar-link <?= is_active_page('audit-logs.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            </span>
                            <span>Audit Trail Logs</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/settings.php') ?>" class="sidebar-link <?= is_active_page('settings.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                            <span>System Settings</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('dashboard.php') ?>" class="sidebar-link">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                            <span>Voter View</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="sidebar-category">Voter Navigation</li>
                    <li class="sidebar-item">
                        <a href="<?= url('dashboard.php') ?>" class="sidebar-link <?= is_active_page('dashboard.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect></svg>
                            </span>
                            <span>Voter Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('elections.php') ?>" class="sidebar-link <?= is_active_page('elections.php', 'vote.php', 'election-details.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 12 2 2 4-4"></path><path d="M5 7c0-1.1.9-2 2-2h10a2 2 0 0 1 2 2v12H5V7Z"></path><path d="M22 19H2"></path></svg>
                            </span>
                            <span>Campus Ballots</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('voting-history.php') ?>" class="sidebar-link <?= is_active_page('voting-history.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            </span>
                            <span>Voting Receipts</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="<?= url('results.php') ?>" class="sidebar-link <?= is_active_page('results.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path></svg>
                            </span>
                            <span>Certified Results</span>
                        </a>
                    </li>
                    
                    <li class="sidebar-category">Account Settings</li>
                    <li class="sidebar-item">
                        <a href="<?= url('my-profile.php') ?>" class="sidebar-link <?= is_active_page('my-profile.php') ?>">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            </span>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                    <li class="sidebar-item">
                        <a href="<?= url('admin/dashboard.php') ?>" class="sidebar-link">
                            <span class="icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
                            </span>
                            <span>Admin Control Panel</span>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer sidebar-profile">
                <div class="sidebar-profile-card user-badge">
                    <a href="<?= url($isAdmin ? 'admin/dashboard.php' : 'my-profile.php') ?>" class="user-profile-link" title="View Profile">
                        <img src="<?= asset('images/' . ($user['avatar_url'] ?? 'default-avatar.svg')) ?>" alt="Avatar" class="user-avatar">
                        <div class="user-meta">
                            <div class="user-meta-name" title="<?= e($user['full_name'] ?? 'Guest') ?>"><?= e($user['full_name'] ?? 'Guest') ?></div>
                            <div class="user-meta-role" title="College Roll No: <?= e($user['college_roll_no'] ?: ($user['voter_id_number'] ?? 'ID: N/A')) ?>">
                                <?= e($user['college_roll_no'] ?: ($user['voter_id_number'] ?? 'ID: N/A')) ?>
                            </div>
                        </div>
                    </a>
                    <a href="<?= url('logout.php') ?>" class="sidebar-signout-btn" title="Sign Out" aria-label="Sign Out">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Sidebar Mobile Backdrop Overlay -->
        <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

        <!-- Main Content Wrapper -->
        <div class="app-main voter-main-content">
            <!-- Topbar -->
            <header class="app-topbar">
                <div style="display:flex;align-items:center;gap:1rem;">
                    <button type="button" class="mobile-toggle" aria-label="Toggle navigation menu" aria-controls="appSidebar">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"></line>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <line x1="3" y1="18" x2="21" y2="18"></line>
                        </svg>
                    </button>
                    <div class="topbar-title">
                        <span><?= e($pageTitle) ?></span>
                    </div>
                </div>

                <div class="topbar-actions">
                    <span class="status-badge badge-slate topbar-badge" style="font-size:0.75rem;">
                        <span class="status-indicator-ping"></span> Live Session
                    </span>
                    <a href="<?= url($isAdmin ? 'admin/dashboard.php' : 'my-profile.php') ?>" class="btn btn-secondary btn-sm topbar-user-btn">
                        <?= e($user['role'] === 'admin' ? 'Election Officer' : 'Student Voter Portal') ?>
                    </a>
                </div>
            </header>

            <!-- Page Body Content -->
            <main class="app-content">
    <?php else: ?>
    <!-- ================= PUBLIC WEBSITE LAYOUT ================= -->
    <header class="site-header" id="siteHeader">
        <div class="container nav-wrap">
            <a href="<?= url('index.php') ?>" class="brand" aria-label="ApexVote – College Electoral Management System">
                <span class="brand-symbol" aria-hidden="true">
                    <img src="<?= asset('images/logo.png') ?>" alt="ApexVote Logo" class="brand-icon" style="width: 32px; height: 32px; object-fit: contain;">
                </span>
                <div class="brand-text-group">
                    <span class="brand-title">Apex<span class="brand-title-accent">Vote</span></span>
                    <span class="brand-descriptor">College Electoral Management System</span>
                </div>
            </a>

            <nav class="nav-desktop" aria-label="Main Navigation">
                <ul class="nav-links">
                    <li><a href="<?= url('index.php') ?>" class="<?= is_active_page('index.php') ?>">Home</a></li>
                    <li><a href="<?= url('elections.php') ?>" class="<?= is_active_page('elections.php') ?>">Elections</a></li>
                    <li><a href="<?= url('results.php') ?>" class="<?= is_active_page('results.php') ?>">Live Results</a></li>
                    <li><a href="<?= url('about.php') ?>" class="<?= is_active_page('about.php') ?>">About</a></li>
                    <li><a href="<?= url('contact.php') ?>" class="<?= is_active_page('contact.php') ?>">Contact</a></li>
                </ul>
            </nav>

            <div class="nav-actions">
                <?php if (is_logged_in()): ?>
                    <a href="<?= url($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php') ?>" class="btn btn-primary btn-sm">
                        <span>Dashboard</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                    <a href="<?= url('logout.php') ?>" class="btn btn-ghost btn-sm">Sign Out</a>
                <?php else: ?>
                    <a href="<?= url('login.php') ?>" class="btn btn-ghost btn-sm">Sign In</a>
                    <a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm btn-register">
                        <span>Register to Vote</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                <?php endif; ?>

                <!-- Accessible Mobile Hamburger Button -->
                <button type="button" class="public-nav-toggle" id="publicNavToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="publicMobileDrawer">
                    <span class="hamburger-bar"></span>
                    <span class="hamburger-bar"></span>
                    <span class="hamburger-bar"></span>
                </button>
            </div>
        </div>

        <!-- Responsive Mobile Navigation Drawer -->
        <div class="public-mobile-drawer" id="publicMobileDrawer" aria-hidden="true">
            <div class="drawer-inner">
                <div class="drawer-header">
                    <div class="drawer-brand">
                        <span class="brand-title">Apex<span class="brand-title-accent">Vote</span></span>
                        <span class="brand-edition"><?= e(get_setting('academic_year', '2026–2027')) ?></span>
                    </div>
                    <button type="button" class="drawer-close" id="drawerCloseBtn" aria-label="Close menu">&times;</button>
                </div>
                <ul class="mobile-nav-list">
                    <li><a href="<?= url('index.php') ?>" class="<?= is_active_page('index.php') ?>">Home</a></li>
                    <li><a href="<?= url('elections.php') ?>" class="<?= is_active_page('elections.php') ?>">Elections</a></li>
                    <li><a href="<?= url('results.php') ?>" class="<?= is_active_page('results.php') ?>">Live Results</a></li>
                    <li><a href="<?= url('about.php') ?>" class="<?= is_active_page('about.php') ?>">About Platform</a></li>
                    <li><a href="<?= url('contact.php') ?>" class="<?= is_active_page('contact.php') ?>">Election Committee Contact</a></li>
                </ul>
                <div class="drawer-actions">
                    <?php if (is_logged_in()): ?>
                        <a href="<?= url($user['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php') ?>" class="btn btn-primary btn-block">
                            Go to Dashboard &rarr;
                        </a>
                        <a href="<?= url('logout.php') ?>" class="btn btn-ghost btn-block">Sign Out</a>
                    <?php else: ?>
                        <a href="<?= url('register.php') ?>" class="btn btn-primary btn-block">Register as Voter &rarr;</a>
                        <a href="<?= url('login.php') ?>" class="btn btn-secondary btn-block">Sign In with Roll No / Email</a>
                    <?php endif; ?>
                </div>
                <div class="drawer-footer-meta">
                    <span><?= e(get_setting('institution_name', 'ApexVote College Demonstration Campus')) ?></span>
                </div>
            </div>
        </div>
        <div class="drawer-backdrop" id="drawerBackdrop"></div>
    </header>
    <main>
    <?php endif; ?>
