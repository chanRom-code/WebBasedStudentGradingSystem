<?php
session_start();

/** @var mysqli $conn */
include '../includes/config.php';

date_default_timezone_set('Asia/Manila'); 

// 1. Security Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$full_name = isset($_SESSION['full_name']) ? htmlspecialchars((string)$_SESSION['full_name'], ENT_QUOTES) : 'Administrator';

function timeAgo(string $timestamp): string {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $seconds = $current_time - $time_ago;
    if ($seconds <= 2) return "Just now"; 
    if ($seconds < 60) return $seconds . " secs ago";
    $minutes = floor($seconds / 60);
    if ($minutes < 60) return ($minutes == 1) ? "1m ago" : $minutes . "m ago";
    $hours = floor($seconds / 3600);
    if ($hours < 24) return ($hours == 1) ? "1h ago" : $hours . "h ago";
    return date('M d', $time_ago);
}

// 2. Counts
function getCount(mysqli $conn, string $sql): int {
    $result = mysqli_query($conn, $sql);
    if (!$result) return 0;
    $row = mysqli_fetch_assoc($result);
    return $row ? (int) ($row['total'] ?? 0) : 0;
}

$professors_count = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'professor'");
$students_count = getCount($conn, "SELECT COUNT(*) AS total FROM students");
$subjects_count = getCount($conn, "SELECT COUNT(*) AS total FROM subjects");

$unlock_pending = getCount($conn, "SELECT COUNT(*) AS total FROM unlock_requests WHERE request_status = 'Pending'");
$password_pending = getCount($conn, "SELECT COUNT(*) AS total FROM password_resets WHERE status = 'Pending'"); 
$pending_requests_count = $unlock_pending + $password_pending;

// Fetch notifications specifically for the dropdown menu
$notif_unlocks = mysqli_query($conn, "SELECT ur.request_id, u.full_name FROM unlock_requests ur JOIN users u ON ur.professor_id = u.user_id WHERE ur.request_status = 'Pending' LIMIT 3");
$notif_passwords = mysqli_query($conn, "SELECT id, email FROM password_resets WHERE status = 'pending' LIMIT 3");

// 3. Main Logs
$log_query = "SELECT a.*, u.full_name, u.role 
              FROM activity_logs a 
              JOIN users u ON a.user_id = u.user_id 
              ORDER BY a.created_at DESC LIMIT 15";
$logs_result = mysqli_query($conn, $log_query);

// 4. Fetch Professors for Faculty Directory List
$prof_list_query = "SELECT full_name, email FROM users WHERE role = 'professor' ORDER BY full_name ASC LIMIT 5";
$prof_list_result = mysqli_query($conn, $prof_list_query);

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .content-wrapper { padding: 0 30px 30px 30px; background-color: var(--bg-color, #F4F7FE); min-height: 100vh; }
    
    /* TOP NAVBAR */
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    .search-bar { background-color: var(--card-bg, #ffffff); border-radius: 8px; border: 1px solid var(--border-color, #E2E8F0); padding: 6px 15px; width: 350px; }
    .search-bar input { border: none; outline: none; background: transparent; width: 100%; font-size: 14px; margin-left: 10px; color: var(--text-color, #334155); }
    .btn-create { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 8px 20px; border: none; text-decoration: none; transition: 0.2s; white-space: nowrap; }
    .btn-create:hover { background-color: #0036cc; color: white; }
    
    /* NOTIFICATION DROPDOWN */
    .dropdown-menu-custom { border-radius: 12px; border: 1px solid var(--border-color, #E2E8F0); box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 320px; padding: 0; overflow: hidden; display: none; position: absolute; right: 0; top: 45px; background: var(--card-bg, #fff); z-index: 1000;}
    .dropdown-menu-custom.show { display: block; animation: fadeIn 0.2s ease; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .dropdown-header-custom { background: var(--input-bg, #F8FAFC); padding: 12px 15px; font-weight: 700; color: var(--text-dark, #0F172A); border-bottom: 1px solid var(--border-color, #E2E8F0); }
    .dropdown-footer-custom { background: #F8FAFC; border-top: 1px solid var(--border-color, #E2E8F0); }
    .notif-item { padding: 15px; border-bottom: 1px solid var(--border-color, #F1F5F9); text-decoration: none; display: block; transition: 0.2s; }
    .notif-item:hover { background-color: var(--input-bg, #F8FAFC); }
    .notif-icon-box { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 15px; flex-shrink: 0; }

    /* UNREAD DOT BADGE */
    .unread-badge { width: 8px; height: 8px; right: 15px; box-shadow: 0 0 0 2px #fff; transition: 0.2s; }

    /* CARDS */
    .saas-card { background: var(--card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #E2E8F0); padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
    .card-title-text { color: var(--text-muted, #64748B); font-size: 13px; font-weight: 500; margin-bottom: 5px; }
    .card-value { font-size: 28px; font-weight: 700; color: var(--text-dark, #0F172A); line-height: 1.2; }
    .card-icon-box { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .trend-up { color: #10B981; font-size: 12px; font-weight: 600; }
    .trend-neutral { color: var(--text-muted, #94A3B8); font-size: 12px; font-weight: 500; }

    /* TABLE */
    .table-saas { width: 100%; border-collapse: collapse; }
    .table-saas th { color: var(--text-muted, #64748B); font-size: 11px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px; border-bottom: 1px solid var(--border-color, #E2E8F0); }
    .table-saas td { padding: 15px; border-bottom: 1px solid var(--border-color, #F1F5F9); vertical-align: middle; font-size: 14px; color: var(--text-color, #334155); font-weight: 500; }
    
    /* BADGES PARA SA ACTIVE AT INACTIVE */
    .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;}
    .badge-active { background: #ECFDF5; color: #10B981; border: 1px solid #A7F3D0; }
    .badge-inactive { background: #F1F5F9; color: #64748B; border: 1px solid #E2E8F0; }

    /* RIGHT PANEL LIST */
    .teacher-item { display: flex; align-items: center; margin-bottom: 15px; text-decoration: none; transition: 0.2s; padding: 6px; border-radius: 8px;}
    .teacher-item:hover { background-color: var(--input-bg, #F8FAFC); transform: translateX(3px); }
    .teacher-avatar { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 12px; font-size: 14px; color: #fff; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
    .teacher-info h6 { font-size: 14px; font-weight: 600; margin: 0; color: var(--text-dark, #0F172A); }
    .teacher-info p { font-size: 12px; color: var(--text-muted, #64748B); margin: 0; }

    /* =====================================
       DARK MODE FIXES PARA SA NOTIFICATION DROPDOWN
       ===================================== */
    body.dark-mode .dropdown-menu-custom {
        background-color: #1E293B !important;
        border-color: #334155 !important;
    }
    body.dark-mode .dropdown-header-custom {
        background-color: #0F172A !important;
        color: #F8FAFC !important;
        border-bottom: 1px solid #334155 !important;
    }
    body.dark-mode .dropdown-footer-custom {
        background-color: #0F172A !important;
        border-top: 1px solid #334155 !important;
    }
    body.dark-mode .notif-item {
        border-bottom: 1px solid #334155 !important;
    }
    body.dark-mode .notif-item:hover {
        background-color: #334155 !important;
    }
    body.dark-mode .notif-item p.text-dark {
        color: #F8FAFC !important;
    }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        .search-bar { display: none !important; } 
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        .btn-create { font-size: 13px; padding: 6px 14px; }
        
        #notifDropdown {
            position: static !important;
        }
        .dropdown-menu-custom {
            position: fixed !important;
            top: 65px !important;
            left: 15px !important;
            right: 15px !important;
            width: auto !important;
            max-width: none !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
        }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <div class="col-md-10 w-100-mobile">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-light d-md-none me-2 border shadow-sm" onclick="toggleMobileSidebar()" style="border-radius: 8px;">
                            <i class="fas fa-bars text-dark"></i>
                        </button>
                        <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                            Home <span class="mx-1">></span> Dashboard <span class="mx-1">></span> <strong class="text-dark">Overview</strong>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2 gap-md-3">
                        <div class="search-bar d-none d-md-flex align-items-center">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" placeholder="Search students, staff...">
                        </div>
                        
                        <a href="add_student.php" class="btn-create"><i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">Quick Create</span><span class="d-inline d-sm-none">Create</span></a>
                        
                        <div class="dropdown ms-1 position-relative" id="notifDropdown">
                            <a href="#" class="text-muted position-relative d-flex align-items-center justify-content-center text-decoration-none" id="notifDropdownToggle" style="width: 35px; height: 35px;">
                                <i class="far fa-bell fs-5"></i>
                                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" id="mainNotifDot" style="display: <?= $pending_requests_count > 0 ? 'block' : 'none' ?>;"></span>
                            </a>
                            
                            <ul class="dropdown-menu-custom mt-2" id="notifDropdownMenu">
                                <div class="dropdown-header-custom d-flex justify-content-between align-items-center">
                                    <span>Notifications</span>
                                    <span class="badge bg-primary rounded-pill" id="notifCountBadge" style="display: <?= $pending_requests_count > 0 ? 'inline-block' : 'none' ?>;">
                                        <?= $pending_requests_count ?> New
                                    </span>
                                </div>
                                
                                <div style="max-height: 320px; overflow-y: auto;">
                                    <?php if($pending_requests_count === 0): ?>
                                        <div class="p-4 text-center text-muted small">
                                            <i class="fas fa-check-circle text-success mb-2 fs-4"></i><br>
                                            You're all caught up!
                                        </div>
                                    <?php else: ?>
                                        
                                        <?php while($notif_unlocks && $nu = mysqli_fetch_assoc($notif_unlocks)): 
                                            $prof_initial = strtoupper(substr((string)$nu['full_name'], 0, 1));
                                            $req_id = (int)$nu['request_id'];
                                        ?>
                                            <a href="unlock_requests.php" class="notif-item position-relative pe-5" onclick="markAsRead('unlock', <?= $req_id ?>)">
                                                <div class="d-flex align-items-center">
                                                    <div class="notif-icon-box bg-primary text-white shadow-sm fw-bold">
                                                        <?= $prof_initial ?>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-dark" style="font-size: 13px; line-height: 1.4;">
                                                            Professor <strong><?= htmlspecialchars((string)$nu['full_name'], ENT_QUOTES) ?></strong> requested to unlock a grade.
                                                        </p>
                                                    </div>
                                                </div>
                                                <span class="position-absolute top-50 translate-middle-y bg-danger rounded-circle unread-badge" id="badge-unlock-<?= $req_id ?>"></span>
                                            </a>
                                        <?php endwhile; ?>
                                        
                                        <?php while($notif_passwords && $np = mysqli_fetch_assoc($notif_passwords)): 
                                            $email_initial = strtoupper(substr((string)$np['email'], 0, 1));
                                            $pass_id = (int)$np['id'];
                                        ?>
                                            <a href="request_forgot_pass.php" class="notif-item position-relative pe-5" onclick="markAsRead('pass', <?= $pass_id ?>)">
                                                <div class="d-flex align-items-center">
                                                    <div class="notif-icon-box bg-danger text-white shadow-sm fw-bold">
                                                        <?= $email_initial ?>
                                                    </div>
                                                    <div>
                                                        <p class="mb-0 text-dark" style="font-size: 13px; line-height: 1.4;">
                                                            <strong><?= htmlspecialchars((string)$np['email'], ENT_QUOTES) ?></strong> requested a password reset.
                                                        </p>
                                                    </div>
                                                </div>
                                                <span class="position-absolute top-50 translate-middle-y bg-danger rounded-circle unread-badge" id="badge-pass-<?= $pass_id ?>"></span>
                                            </a>
                                        <?php endwhile; ?>
                                        
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-center p-2 dropdown-footer-custom">
                                    <a href="unlock_requests.php" class="text-primary text-decoration-none fw-bold" style="font-size: 12px;">View All Requests</a>
                                </div>
                            </ul>
                        </div>
                        
                        <a href="users.php" class="rounded-circle ms-1 text-decoration-none d-flex align-items-center justify-content-center bg-primary text-white" style="width: 35px; height: 35px; border: 2px solid #fff; box-shadow: 0 0 0 2px #E2E8F0; font-size: 14px; font-weight: bold; flex-shrink: 0;">
                            <?= strtoupper(substr($full_name, 0, 1)) ?>
                        </a>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-2">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: var(--text-dark, #0F172A);">Overview</h3>
                        <p class="text-muted mb-0" style="font-size: 14.5px;">Welcome back, Admin. Here's what's happening today.</p>
                    </div>
                    <div class="text-muted fw-medium" style="font-size: 14px;">
                        <i class="far fa-calendar-alt me-2"></i> <?= date('F d, Y') ?>
                    </div>
                </div>

                <!-- CARDS GRID -->
                <div class="row g-3 g-md-4 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="saas-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="card-title-text">Total Students</div>
                                    <div class="card-value"><?= number_format($students_count); ?></div>
                                </div>
                                <div class="card-icon-box" style="background: #EFF6FF; color: #0047FF;">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                            </div>
                            <div class="trend-up"><i class="fas fa-arrow-trend-up me-1"></i> Active</div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-md-3">
                        <div class="saas-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="card-title-text">Total Faculty</div>
                                    <div class="card-value"><?= number_format($professors_count); ?></div>
                                </div>
                                <div class="card-icon-box" style="background: #F5F3FF; color: #4F46E5;">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                            </div>
                            <div class="trend-neutral">Fully staffed</div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-md-3">
                        <div class="saas-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="card-title-text">Total Subjects</div>
                                    <div class="card-value"><?= number_format($subjects_count); ?></div>
                                </div>
                                <div class="card-icon-box" style="background: #ECFDF5; color: #10B981;">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                            <div class="trend-up text-success"><i class="fas fa-check-circle me-1"></i> Running</div>
                            <div class="mt-2 bg-light rounded-pill" style="height: 6px;"><div class="bg-success rounded-pill" style="width: 100%; height: 100%;"></div></div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-md-3">
                        <div class="saas-card h-100">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <div class="card-title-text">Pending Requests</div>
                                    <div class="card-value"><?= number_format($pending_requests_count); ?></div>
                                </div>
                                <div class="card-icon-box" style="background: #FFFBEB; color: #F59E0B;">
                                    <i class="fas fa-bell"></i>
                                </div>
                            </div>
                            <?php if($pending_requests_count > 0): ?>
                                <div class="trend-neutral text-warning"><i class="fas fa-circle-exclamation me-1"></i> Needs Attention</div>
                                <div class="mt-2 bg-light rounded-pill" style="height: 6px;"><div class="bg-warning rounded-pill" style="width: 60%; height: 100%;"></div></div>
                            <?php else: ?>
                                <div class="trend-neutral"><i class="fas fa-check text-success me-1"></i> All clear</div>
                                <div class="mt-2 bg-light rounded-pill" style="height: 6px;"><div class="bg-success rounded-pill" style="width: 100%; height: 100%;"></div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-lg-8">
                        <div class="saas-card h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-clipboard-list text-primary me-2"></i> Activity Monitor</h6>
                            </div>
                            
                            <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                                <table class="table-saas">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%;">USER</th>
                                            <th style="width: 40%;">ACTION</th>
                                            <th>PAGE</th>
                                            <th>TIME</th>
                                            <th>STATUS</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($logs_result && mysqli_num_rows($logs_result) > 0): ?>
                                            <?php 
                                            $user_status_tracker = [];
                                            
                                            while($logs_result && $log = mysqli_fetch_assoc($logs_result)): 
                                                $uid = (int)$log['user_id'];
                                                $action_lower = strtolower((string)$log['action']);
                                                $log_time = strtotime((string)$log['created_at']);
                                                
                                                if (!isset($user_status_tracker[$uid])) {
                                                    $is_logout = (strpos($action_lower, 'out') !== false);
                                                    $is_recent = ((time() - $log_time) < 3600); 
                                                    
                                                    if (!$is_logout && $is_recent) {
                                                        $badge_class = 'badge-active';
                                                        $badge_text = '<i class="fas fa-circle me-1" style="font-size: 7px;"></i> Active';
                                                    } else {
                                                        $badge_class = 'badge-inactive';
                                                        $badge_text = '<i class="fas fa-circle me-1" style="font-size: 7px;"></i> Inactive';
                                                    }
                                                    
                                                    $user_status_tracker[$uid] = true;
                                                } else {
                                                    $badge_class = 'badge-inactive';
                                                    $badge_text = '<i class="fas fa-circle me-1" style="font-size: 7px;"></i> Inactive';
                                                }
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="text-dark fw-bold text-truncate" style="max-width: 150px;"><?= htmlspecialchars((string)$log['full_name'], ENT_QUOTES) ?></div>
                                                        <div class="text-muted" style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars((string)$log['role'], ENT_QUOTES) ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="text-dark" style="font-size: 13px; font-weight: 500; line-height: 1.3;"><?= htmlspecialchars((string)$log['action'], ENT_QUOTES) ?></div>
                                                    </td>
                                                    <td class="text-muted" style="font-family: monospace; font-size: 12px;"><?= htmlspecialchars((string)$log['page_visited'], ENT_QUOTES) ?></td>
                                                    <td>
                                                        <div class="text-dark fw-medium" style="font-size: 13px;"><?= date('M d', $log_time) ?></div>
                                                        <div class="text-muted" style="font-size: 11px;"><?= date('h:i A', $log_time) ?></div>
                                                    </td>
                                                    <td><span class="badge-status <?= $badge_class ?>"><?= $badge_text ?></span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="text-center py-5 text-muted">No recent activity.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <div class="saas-card h-100">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-chalkboard-teacher text-primary me-2"></i> Faculty Directory</h6>
                                <a href="users.php" class="text-primary fw-medium" style="font-size: 13px; text-decoration: none;">View All</a>
                            </div>

                            <div class="mt-3" style="max-height: 350px; overflow-y: auto;">
                                <?php if ($prof_list_result && mysqli_num_rows($prof_list_result) > 0): ?>
                                    <?php 
                                    $avatar_colors = ['bg-primary', 'bg-success', 'bg-warning text-dark', 'bg-danger', 'bg-info'];
                                    $color_index = 0;

                                    while($prof_list_result && $prof = mysqli_fetch_assoc($prof_list_result)): 
                                        $initial = strtoupper(substr((string)$prof['full_name'], 0, 1));
                                        $bg_class = $avatar_colors[$color_index % count($avatar_colors)];
                                        $color_index++;
                                    ?>
                                        <a href="users.php" class="teacher-item">
                                            <div class="teacher-avatar <?= $bg_class ?> shadow-sm">
                                                <?= $initial ?>
                                            </div>
                                            <div class="teacher-info w-100">
                                                <h6><?= htmlspecialchars((string)$prof['full_name'], ENT_QUOTES) ?></h6>
                                                <p><?= htmlspecialchars((string)$prof['email'], ENT_QUOTES) ?></p>
                                            </div>
                                        </a>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-5 text-muted">
                                        <i class="fas fa-users-slash fs-2 mb-2"></i>
                                        <p class="mb-0" style="font-size: 13px;">No professors found.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-center mt-4 pt-3 border-top">
                                <a href="add_user.php" class="text-primary fw-medium" style="font-size: 13.5px; text-decoration: none;"><i class="fas fa-user-plus me-1"></i> Add New Professor</a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
let totalUnreadCount = <?= (int)$pending_requests_count ?>;

document.addEventListener("DOMContentLoaded", function() {
    
    // --- 1. NOTIFICATION DROPDOWN TOGGLE ---
    const notifToggle = document.getElementById("notifDropdownToggle");
    const notifMenu = document.getElementById("notifDropdownMenu");

    if (notifToggle && notifMenu) {
        notifToggle.addEventListener("click", function(e) {
            e.preventDefault();
            notifMenu.classList.toggle("show");
        });

        document.addEventListener("click", function(e) {
            if (!document.getElementById("notifDropdown").contains(e.target)) {
                notifMenu.classList.remove("show");
            }
        });
    }

    // --- 2. UPDATE NOTIFICATION COUNTS ON LOAD ---
    const unreadBadges = document.querySelectorAll('.unread-badge');
    
    unreadBadges.forEach(badge => {
        if (localStorage.getItem(badge.id)) {
            badge.style.display = 'none'; 
            totalUnreadCount--; 
        }
    });

    updateNotificationUI(totalUnreadCount);
});

// --- 3. FUNCTION PARA MA-MARK AS READ ANG NOTIFICATION ---
function markAsRead(type, id) {
    const badgeId = 'badge-' + type + '-' + id;
    
    if (!localStorage.getItem(badgeId)) {
        localStorage.setItem(badgeId, 'true');
        
        const badge = document.getElementById(badgeId);
        if (badge) badge.style.display = 'none';

        totalUnreadCount--;
        updateNotificationUI(totalUnreadCount);
    }
}

// --- 4. FUNCTION PARA I-UPDATE ANG BELL AT ANG "X NEW" BADGE ---
function updateNotificationUI(count) {
    const mainDot = document.getElementById('mainNotifDot');
    const countBadge = document.getElementById('notifCountBadge');

    if (count > 0) {
        if (mainDot) mainDot.style.display = 'block';
        if (countBadge) {
            countBadge.style.display = 'inline-block';
            countBadge.innerText = count + ' New';
            countBadge.classList.replace('bg-secondary', 'bg-primary');
        }
    } else {
        if (mainDot) mainDot.style.display = 'none'; 
        if (countBadge) {
            countBadge.innerText = '0 New';
            countBadge.classList.replace('bg-primary', 'bg-secondary'); 
        }
    }
}
</script>

<?php include '../includes/footer.php'; ?>