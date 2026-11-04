<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$current_page = basename($_SERVER['PHP_SELF']);

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .content-wrapper { padding: 0 30px 30px 30px; background-color: var(--bg-color, #F4F7FE); min-height: 100vh; transition: 0.3s; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    
    .saas-card { background: var(--card-bg, #ffffff); border-radius: 16px; border: 1px solid var(--border-color, #E2E8F0); padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 20px; transition: 0.3s;}
    
    .switch { position: relative; display: inline-block; width: 60px; height: 32px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    
    .slider { 
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; 
        background-color: #CBD5E1; transition: .4s; border-radius: 34px; 
    }

    .slider:before {
        position: absolute;
        content: "\f185";
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
        color: #F59E0B;
        font-size: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    input:checked + .slider { background-color: #0047FF; }

    input:checked + .slider:before {
        transform: translateX(28px);
        content: "\f186";
        color: #1E293B;
    }

    .setting-row { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid var(--border-color, #F1F5F9); gap: 15px; }
    .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
    
    .setting-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-right: 15px; flex-shrink: 0;}
    
    .form-select-custom { border-radius: 8px; border: 1px solid var(--border-color, #E2E8F0); padding: 8px 15px; font-size: 13px; color: var(--text-color, #334155); background-color: var(--input-bg, #F8FAFC); cursor: pointer; }
    .form-select-custom:focus { border-color: #0047FF; outline: none; }

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

    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        
        .setting-row {
            flex-direction: column;
            align-items: flex-start !important;
        }
        
        .setting-row > div:first-child {
            width: 100%;
        }

        .setting-row .switch, 
        .setting-row > div:last-child {
            width: 100%;
            display: flex;
            justify-content: flex-end;
        }

        .form-select-custom {
            width: 100%;
        }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="col-md-10 w-100-mobile">
            <div class="content-wrapper" id="mainContent">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-light d-md-none me-2 border shadow-sm" onclick="toggleMobileSidebar()" style="border-radius: 8px;">
                            <i class="fas fa-bars text-dark"></i>
                        </button>
                        <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                            Home <span class="mx-1">></span> <strong class="text-dark">System Settings</strong>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-end me-2 d-none d-md-block">
                            <h6 class="mb-0 fw-bold text-dark" style="font-size: 14px;"><?= $full_name_session ?></h6>
                            <small class="text-muted" style="font-size: 11px;">Administrator</small>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white shadow-sm" style="width: 40px; height: 40px; font-weight: bold; font-size: 16px;">
                            <?= strtoupper(substr($full_name_session, 0, 1)) ?>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h3 class="fw-bold mb-1 text-dark">Settings</h3>
                    <p class="text-muted mb-0" style="font-size: 14px;">Manage system appearance, grading periods, and global preferences.</p>
                </div>

                <div class="row">
                    <div class="col-md-7 col-12">
                        
                        <div class="saas-card">
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-paint-brush text-primary me-2"></i> Appearance</h6>
                            
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
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-sliders-h text-primary me-2"></i> System Preferences</h6>
                            
                            <div class="setting-row">
                                <div class="d-flex align-items-center">
                                    <div class="setting-icon bg-light text-primary border"><i class="fas fa-calendar-check"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 14.5px;">Active Grading Period</div>
                                        <div class="text-muted" style="font-size: 12px;">Set the default grading period for all professors.</div>
                                    </div>
                                </div>
                                <div>
                                    <select class="form-select-custom" onchange="saveSettingMsg()">
                                        <option value="Prelim">Prelim</option>
                                        <option value="Midterm" selected>Midterm</option>
                                        <option value="Prefinal">Prefinal</option>
                                        <option value="Final">Final</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="saas-card">
                            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-bell text-primary me-2"></i> Notifications</h6>
                            
                            <div class="setting-row">
                                <div class="d-flex align-items-center">
                                    <div class="setting-icon" style="background: #FEF2F2; color: #EF4444;"><i class="fas fa-envelope-open-text"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 14.5px;">Email Alerts</div>
                                        <div class="text-muted" style="font-size: 12px;">Receive an email when a professor requests a password reset.</div>
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