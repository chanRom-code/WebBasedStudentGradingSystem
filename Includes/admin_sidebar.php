<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #F4F7FE;
    }
    
    /* DESKTOP SIDEBAR */
    .sidebar { 
        background-color: #ffffff !important; 
        height: 100vh; 
        height: 100dvh;
        position: sticky; 
        top: 0; 
        z-index: 1040;
        border-right: 1px solid #E2E8F0;
        display: flex;
        flex-direction: column;
        overflow: hidden; 
        transition: background-color 0.3s ease, border-color 0.3s ease;
    }
    
    /* Scrollable middle navigation */
    .sidebar-nav {
        flex-grow: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 4px;
    }
    .sidebar-nav::-webkit-scrollbar { width: 4px; }
    .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
    .sidebar-nav::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }

    .sidebar .nav-link { 
        color: #64748B; 
        padding: 12px 16px; 
        border-radius: 10px; 
        transition: all 0.2s ease; 
        font-weight: 500;
        font-size: 14.5px;
        margin-bottom: 4px;
    }
    .sidebar .nav-link:hover { 
        background: #F1F5F9; 
        color: #0F172A; 
    }
    .sidebar .nav-link.active { 
        background-color: #0047FF !important;
        color: #fff !important; 
        box-shadow: 0 4px 10px rgba(0, 71, 255, 0.2); 
    }
    .active-sub { color: #0047FF !important; font-weight: 600; }
    .menu-arrow { transition: transform 0.3s ease; }
    .nav-link[aria-expanded="true"] .menu-arrow { transform: rotate(90deg); }
    .hover-red:hover { background: rgba(239, 68, 68, 0.1) !important; color: #EF4444 !important; }
    .rounded-20 { border-radius: 20px !important; }

    /* =====================================
       DARK MODE STYLES PARA SA SIDEBAR
       ===================================== */
    body.dark-mode .sidebar {
        background-color: #1E293B !important;
        border-right: 1px solid #334155 !important;
    }
    body.dark-mode .sidebar .text-dark {
        color: #F8FAFC !important;
    }
    body.dark-mode .sidebar .text-muted {
        color: #94A3B8 !important;
    }
    body.dark-mode .sidebar .nav-link {
        color: #94A3B8;
    }
    body.dark-mode .sidebar .nav-link:hover {
        background: #334155 !important;
        color: #F8FAFC !important;
    }
    body.dark-mode .sidebar .nav-link.active {
        background-color: #0047FF !important;
        color: #fff !important;
    }
    body.dark-mode .sidebar .border-top {
        border-color: #334155 !important;
    }
    body.dark-mode .sidebar .bg-white {
        background-color: #1E293B !important;
    }
    body.dark-mode .sidebar-nav::-webkit-scrollbar-thumb {
        background: #475569;
    }

    /* MOBILE OFFCANVAS / RESPONSIVE OVERRIDES */
    @media (max-width: 768px) {
        .sidebar {
            position: fixed !important;
            top: 0;
            left: -280px;
            width: 280px !important;
            height: 100vh;
            height: 100dvh;
            transition: left 0.3s ease-in-out, background-color 0.3s ease;
            box-shadow: 5px 0 15px rgba(0,0,0,0.1);
        }
        .sidebar.show-mobile {
            left: 0;
        }
        /* Mobile Overlay backdrop */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
            z-index: 1035;
        }
        .sidebar-backdrop.show {
            display: block;
        }
    }
</style>

<!-- Mobile Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

<div class="col-md-2 sidebar p-3" id="adminSidebar">
    
    <!-- Sidebar Header (Fixed top) -->
    <div class="text-center mb-3 mt-2 position-relative flex-shrink-0">
        <!-- Close button para sa mobile -->
        <button type="button" class="btn-close d-md-none position-absolute top-0 end-0" aria-label="Close" onclick="toggleMobileSidebar()"></button>
        
        <img src="../assets/SFICS-LOGO.jpg" alt="SFICS Logo" width="60" class="rounded-circle shadow-sm mb-2">
        <h6 class="mb-0 fw-bold text-dark">SFICS</h6>
        <small class="text-muted fw-semibold" style="font-size: 0.7rem;">Grading System</small>
    </div>

    <!-- Scrollable Navigation Area -->
    <div class="sidebar-nav">
        <nav class="nav flex-column gap-1 mt-1">
            <?php 
                $current_page = basename($_SERVER['PHP_SELF']); 
                
                $res_count = mysqli_query($conn, "SELECT id FROM password_resets WHERE status = 'pending'");
                $p_count = $res_count ? mysqli_num_rows($res_count) : 0;

                $unlock_count_query = mysqli_query($conn, "SELECT request_id FROM unlock_requests WHERE request_status = 'Pending'");
                $u_count = $unlock_count_query ? mysqli_num_rows($unlock_count_query) : 0;

                $total_requests = $p_count + $u_count;
            ?>
            
            <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large me-3" style="width: 16px;"></i> Dashboard
            </a>
            
            <div class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center <?= ($current_page == 'users.php' || $current_page == 'students.php' || $current_page == 'add_user.php' || $current_page == 'add_student.php') ? 'active' : '' ?>" 
                   data-bs-toggle="collapse" href="#userMenu" role="button" aria-expanded="<?= ($current_page == 'users.php' || $current_page == 'students.php' || $current_page == 'add_user.php' || $current_page == 'add_student.php') ? 'true' : 'false' ?>">
                    <span><i class="fas fa-users-cog me-3" style="width: 16px;"></i> User Management</span>
                    <i class="fas fa-chevron-right menu-arrow" style="font-size: 0.7rem;"></i>
                </a>
                <div class="collapse <?= ($current_page == 'users.php' || $current_page == 'students.php' || $current_page == 'add_user.php' || $current_page == 'add_student.php') ? 'show' : '' ?>" id="userMenu">
                    <div class="nav flex-column ms-4 mt-1 border-start border-2 border-light">
                        <a href="users.php" class="nav-link py-1 ps-3 <?= ($current_page == 'users.php' || $current_page == 'add_user.php') ? 'active-sub' : 'text-muted' ?>">System Users</a>
                        <a href="students.php" class="nav-link py-1 ps-3 <?= ($current_page == 'students.php' || $current_page == 'add_student.php') ? 'active-sub' : 'text-muted' ?>">Student Records</a>
                    </div>
                </div>
            </div>

            <div class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center <?= ($current_page == 'subjects.php' || $current_page == 'assign_students.php' || $current_page == 'add_subject.php') ? 'active' : '' ?>" 
                   data-bs-toggle="collapse" href="#subjectMenu" role="button" aria-expanded="<?= ($current_page == 'subjects.php' || $current_page == 'assign_students.php' || $current_page == 'add_subject.php') ? 'true' : 'false' ?>">
                    <span><i class="fas fa-book-open me-3" style="width: 16px;"></i> Subject Mgmt</span>
                    <i class="fas fa-chevron-right menu-arrow" style="font-size: 0.7rem;"></i>
                </a>
                <div class="collapse <?= ($current_page == 'subjects.php' || $current_page == 'assign_students.php' || $current_page == 'add_subject.php') ? 'show' : '' ?>" id="subjectMenu">
                    <div class="nav flex-column ms-4 mt-1 border-start border-2 border-light">
                        <a href="subjects.php" class="nav-link py-1 ps-3 <?= ($current_page == 'subjects.php' || $current_page == 'add_subject.php') ? 'active-sub' : 'text-muted' ?>">Subject List</a>
                        <a href="assign_students.php" class="nav-link py-1 ps-3 <?= $current_page == 'assign_students.php' ? 'active-sub' : 'text-muted' ?>">Assign Students</a>
                    </div>
                </div>
            </div>
            
            <a href="grades_overview.php" class="nav-link <?= $current_page == 'grades_overview.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar me-3" style="width: 16px;"></i> Grades Overview
            </a>
            
            <div class="nav-item">
                <a class="nav-link d-flex justify-content-between align-items-center <?= ($current_page == 'unlock_requests.php' || $current_page == 'request_forgot_pass.php') ? 'active' : '' ?>" 
                   data-bs-toggle="collapse" href="#requestMenu" role="button" aria-expanded="<?= ($current_page == 'unlock_requests.php' || $current_page == 'request_forgot_pass.php') ? 'true' : 'false' ?>">
                    <span>
                        <i class="fas fa-bell me-3" style="width: 16px;"></i> Requests
                        <?php if($total_requests > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.65rem;"><?= $total_requests ?></span>
                        <?php endif; ?>
                    </span>
                    <i class="fas fa-chevron-right menu-arrow" style="font-size: 0.7rem;"></i>
                </a>
                <div class="collapse <?= ($current_page == 'unlock_requests.php' || $current_page == 'request_forgot_pass.php') ? 'show' : '' ?>" id="requestMenu">
                    <div class="nav flex-column ms-4 mt-1 border-start border-2 border-light">
                        <a href="unlock_requests.php" class="nav-link py-1 ps-3 d-flex justify-content-between align-items-center <?= $current_page == 'unlock_requests.php' ? 'active-sub' : 'text-muted' ?>">
                            Grade Unlocks
                            <?php if($u_count > 0): ?> <span class="badge rounded-pill bg-danger"><?= $u_count ?></span> <?php endif; ?>
                        </a>
                        <a href="request_forgot_pass.php" class="nav-link py-1 ps-3 d-flex justify-content-between align-items-center <?= $current_page == 'request_forgot_pass.php' ? 'active-sub' : 'text-muted' ?>">
                            Password Req.
                            <?php if($p_count > 0): ?> <span class="badge rounded-pill bg-danger"><?= $p_count ?></span> <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>

           <a href="reports.php" class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>">
                <i class="fas fa-file-alt me-3" style="width: 16px;"></i> Reports
            </a>

            <a href="profile.php" class="nav-link <?= $current_page == 'profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user-circle me-3" style="width: 16px;"></i> My Profile
            </a>

            <a href="settings.php" class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog me-3" style="width: 16px;"></i> Settings
            </a>
        </nav>
    </div>

    <!-- Sidebar Footer (Fixed bottom - Logout Button) -->
    <div class="flex-shrink-0 pt-2 pb-2 border-top bg-white">
        <a href="#" onclick="confirmLogout(event)" class="nav-link text-muted fw-bold hover-red d-flex align-items-center m-0" style="padding: 10px 16px; border-radius: 8px;">
            <i class="fas fa-sign-out-alt me-3" style="width: 16px;"></i> Log Out
        </a>
    </div>
</div>

<script>
function toggleMobileSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.toggle('show-mobile');
    backdrop.classList.toggle('show');
}

function confirmLogout(event) {
    event.preventDefault(); 

    Swal.fire({
        title: 'Sign Out?',
        text: "Are you sure you want to log out of your account?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444', 
        cancelButtonColor: '#94A3B8',  
        confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i> Yes, Log me out',
        cancelButtonText: 'Cancel',
        reverseButtons: true, 
        customClass: { popup: 'rounded-20' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../logout.php';
        }
    });
}
</script>