<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Professor');
$current_page = basename($_SERVER['PHP_SELF']);

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .content-wrapper { padding: 0 30px 30px 30px; background-color: var(--bg-color, #F4F7FE); min-height: 100vh; transition: 0.3s; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    
    .saas-card { background: var(--card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #E2E8F0); padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 20px; transition: 0.3s;}
    
    /* =====================================
       CUSTOM SWITCH WITH SUN & MOON ICONS
       ===================================== */
    .switch { position: relative; display: inline-block; width: 60px; height: 32px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    
    .slider { 
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; 
        background-color: #CBD5E1; transition: .4s; border-radius: 34px; 
    }

    /* Ang bilog na may Icon */
    .slider:before {
        position: absolute;
        content: "\f185"; /* Sun Icon Unicode */
        font-family: "Font Awesome 6 Free";
        font-weight: 900;
        height: 24px;
        width: 24px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #F59E0B; /* Sun color */
        font-size: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    input:checked + .slider { background-color: #10B981; } /* Green color kapag professor side */

    /* Paglipat sa Moon */
    input:checked + .slider:before {
        transform: translateX(28px);
        content: "\f186"; /* Moon Icon Unicode */
        color: #1E293B; /* Moon color */
    }

    .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid var(--border-color, #F1F5F9); }
    .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
    
    .setting-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-right: 15px;}

    /* DARK MODE VARIABLES */
    body.dark-mode {
        --bg-color: #0F172A;
        --card-bg: #1E293B;
        --border-color: #334155;
        --text-color: #F8FAFC;
        --text-muted: #94A3B8;
        --input-bg: #0F172A;
    }
    body.dark-mode .text-dark { color: #F8FAFC !important; }
    body.dark-mode .text-muted { color: #94A3B8 !important; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper" id="mainContent">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> <strong class="text-dark">My Settings</strong>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end me-2 d-none d-md-block">
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px;"><?= $full_name_session ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Professor</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success text-white shadow-sm" style="width: 40px; height: 40px; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($full_name_session, 0, 1)) ?>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="fw-bold mb-1 text-dark">Settings</h3>
                    <p class="text-muted mb-0" style="font-size: 14px;">Personalize your dashboard appearance and notification preferences.</p>
                </div>

                <div class="row">
                    <div class="col-md-7">
                        
                        <div class="saas-card">
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-paint-brush text-success me-2"></i> Appearance</h6>
                            
                            <div class="setting-row">
                                <div class="d-flex align-items-center">
                                    <div class="setting-icon bg-dark text-white shadow-sm"><i class="fas fa-circle-half-stroke"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 14.5px;">Theme Interface</div>
                                        <div class="text-muted" style="font-size: 12px;">Switch between light (sun) and dark (moon) themes.</div>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" id="darkModeToggle">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="saas-card">
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-bell text-success me-2"></i> Notifications</h6>
                            
                            <div class="setting-row">
                                <div class="d-flex align-items-center">
                                    <div class="setting-icon" style="background: #ECFDF5; color: #10B981;"><i class="fas fa-envelope-open-text"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 14.5px;">Unlock Updates</div>
                                        <div class="text-muted" style="font-size: 12px;">Notify me when the Admin approves or denies my unlock request.</div>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked onchange="saveSettingMsg()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="setting-row">
                                <div class="d-flex align-items-center">
                                    <div class="setting-icon" style="background: #EFF6FF; color: #3B82F6;"><i class="fas fa-bullhorn"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 14.5px;">System Announcements</div>
                                        <div class="text-muted" style="font-size: 12px;">Receive alerts about system maintenance and new features.</div>
                                    </div>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked onchange="saveSettingMsg()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    const darkModeToggle = document.getElementById('darkModeToggle');
    
    // Check initial storage
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.add('dark-mode');
        document.body.classList.add('dark-mode');
        darkModeToggle.checked = true;
    } else {
        document.documentElement.classList.remove('dark-mode');
        document.body.classList.remove('dark-mode');
        darkModeToggle.checked = false;
    }

    darkModeToggle.addEventListener('change', function() {
        if (this.checked) {
            document.documentElement.classList.add('dark-mode');
            document.body.classList.add('dark-mode');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark-mode');
            document.body.classList.remove('dark-mode');
            localStorage.setItem('theme', 'light');
        }
    });

    function saveSettingMsg() {
        Swal.fire({
            toast: true, position: 'bottom-end', showConfirmButton: false, timer: 1500,
            icon: 'success', title: 'Setting saved automatically',
            customClass: { popup: 'rounded-20' }
        });
    }
</script>
<?php include '../includes/footer.php'; ?>