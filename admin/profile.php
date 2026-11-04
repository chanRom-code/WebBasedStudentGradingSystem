<?php
session_start();

// Nilagyan natin ng DocBlock para alam ng IDE (VS Code) na nag-eexist ang $conn
/** @var mysqli $conn */
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$swal_script = ''; 

// 1. UPDATE PROFILE INFORMATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssi", $full_name, $email, $phone, $address, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['full_name'] = $full_name; 
            $swal_script = "Swal.fire({ icon: 'success', title: 'Profile Updated!', text: 'Your information has been saved successfully.', showConfirmButton: false, timer: 2000 });";
        } else {
            $swal_script = "Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong while updating your profile.' });";
        }
        mysqli_stmt_close($stmt);
    }
}

// 2. UPDATE PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    
    $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    $pass_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
    if ($pass_stmt) {
        mysqli_stmt_bind_param($pass_stmt, "i", $user_id);
        mysqli_stmt_execute($pass_stmt);
        $pass_res = mysqli_stmt_get_result($pass_stmt);
        
        $db_password = '';
        if ($pass_res && $user_data = mysqli_fetch_assoc($pass_res)) {
            $db_password = $user_data['password'];
        }
        mysqli_stmt_close($pass_stmt);

        $is_valid_password = false;
        
        if ($db_password !== '') {
            if (password_verify($current_password, $db_password)) {
                $is_valid_password = true; 
            } elseif (md5($current_password) === $db_password) {
                $is_valid_password = true; 
            } elseif ($current_password === $db_password) {
                $is_valid_password = true; 
            }
        }

        if (!$is_valid_password) {
             $swal_script = "Swal.fire({ icon: 'error', title: 'Incorrect Password', text: 'The current password you entered is wrong.' });";
        } elseif ($new_password !== $confirm_password) {
             $swal_script = "Swal.fire({ icon: 'warning', title: 'Password Mismatch', text: 'New password and confirmation do not match.' });";
        } elseif (strlen($new_password) < 6) {
             $swal_script = "Swal.fire({ icon: 'warning', title: 'Weak Password', text: 'Password must be at least 6 characters long.' });";
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT); 
            $upd_pass_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
            if ($upd_pass_stmt) {
                mysqli_stmt_bind_param($upd_pass_stmt, "si", $new_hash, $user_id);
                if (mysqli_stmt_execute($upd_pass_stmt)) {
                    $swal_script = "Swal.fire({ icon: 'success', title: 'Password Changed!', text: 'Your password has been updated successfully.', showConfirmButton: false, timer: 2000 });";
                } else {
                    $swal_script = "Swal.fire({ icon: 'error', title: 'Error', text: 'Could not update password in database.' });";
                }
                mysqli_stmt_close($upd_pass_stmt);
            }
        }
    }
}

// 3. FETCH CURRENT USER DATA
$display_name = 'Administrator';
$display_email = 'admin@sfics.edu.ph';
$display_phone = '';
$display_address = '';
$display_created_at = 'N/A';

$user_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
if ($user_stmt) {
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_res = mysqli_stmt_get_result($user_stmt);
    
    if ($user_res && $user_info = mysqli_fetch_assoc($user_res)) {
        $display_name = $user_info['full_name'];
        $display_email = $user_info['email'];
        $display_phone = isset($user_info['phone']) ? $user_info['phone'] : '';
        $display_address = isset($user_info['address']) ? $user_info['address'] : '';
        $display_created_at = isset($user_info['created_at']) ? date('F d, Y', strtotime($user_info['created_at'])) : 'N/A';
    }
    mysqli_stmt_close($user_stmt);
}

$full_name_session = isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Administrator';

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* CONTENT & TOP NAVBAR (SAAS STYLE) */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }

    /* CARDS */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; }
    
    /* AVATAR */
    .profile-avatar-lg { width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 36px; box-shadow: 0 4px 10px rgba(0, 71, 255, 0.2); background: linear-gradient(135deg, #0047FF 0%, #00b4db 100%); margin: 0 auto; }
    
    /* FORMS */
    .form-label-custom { font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .form-control-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; }
    .form-control-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    
    /* BUTTONS */
    .btn-save { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 10px 20px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); width: auto; display: flex; justify-content: center; align-items: center; }
    .btn-save:hover { background-color: #0036cc; color: white; transform: translateY(-2px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    
    .btn-password { background-color: #0F172A; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 10px 20px; border: none; transition: 0.2s; width: auto; display: flex; justify-content: center; align-items: center; }
    .btn-password:hover { background-color: #1E293B; color: white; transform: translateY(-2px); }

    /* DIVIDER */
    .custom-hr { border-color: #E2E8F0; margin: 30px 0; }

    /* --- MOBILE RESPONSIVE MEDIA QUERIES --- */
    @media (max-width: 768px) {
        .content-wrapper { padding: 0 15px 20px 15px !important; }
        .top-navbar { flex-wrap: wrap; gap: 10px; }
        .btn-save, .btn-password { width: 100% !important; justify-content: center !important; }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/admin_sidebar.php'; ?>

        <div class="col-md-10 w-100-mobile">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <!-- Mobile Hamburger Button -->
                        <button class="btn btn-light d-md-none me-2 border shadow-sm" onclick="toggleMobileSidebar()" style="border-radius: 8px;">
                            <i class="fas fa-bars text-dark"></i>
                        </button>
                        <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                            Home <span class="mx-1">></span> Account <span class="mx-1">></span> <strong class="text-dark">My Profile</strong>
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

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">My Profile</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Manage your personal information and account security.</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="saas-card text-center text-md-start">
                            <div class="d-flex flex-column align-items-center mb-4">
                                <div class="profile-avatar-lg mb-3">
                                    <?= strtoupper(substr($display_name, 0, 1)) ?>
                                </div>
                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($display_name) ?></h5>
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1" style="border-radius: 20px; font-weight: 600; font-size: 12px; letter-spacing: 0.5px;">
                                    <i class="fas fa-shield-alt me-1"></i> Administrator
                                </span>
                            </div>
                            
                            <hr class="custom-hr my-3">
                            
                            <div class="text-start">
                                <p class="mb-2 text-muted text-truncate" style="font-size: 13px;"><i class="fas fa-envelope me-2" style="width: 15px;"></i> <?= htmlspecialchars($display_email) ?></p>
                                <?php if($display_phone !== ''): ?>
                                    <p class="mb-2 text-muted text-truncate" style="font-size: 13px;"><i class="fas fa-phone me-2" style="width: 15px;"></i> <?= htmlspecialchars($display_phone) ?></p>
                                <?php endif; ?>
                                <?php if($display_address !== ''): ?>
                                    <p class="mb-3 text-muted text-truncate" style="font-size: 13px;"><i class="fas fa-map-marker-alt me-2" style="width: 15px;"></i> <?= htmlspecialchars($display_address) ?></p>
                                <?php endif; ?>

                                <hr class="custom-hr my-3">
                                <div>
                                    <label class="text-muted mb-1" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Account Created</label>
                                    <p class="mb-0 fw-bold text-dark" style="font-size: 13px;">
                                        <i class="far fa-calendar-alt text-primary me-2" style="width: 15px;"></i> 
                                        <?= $display_created_at ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="saas-card">
                            
                            <h6 class="fw-bold text-dark mb-4"><i class="fas fa-user-edit text-primary me-2"></i> Personal Information</h6>
                            <form method="POST" id="profileForm">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Full Name</label>
                                        <input type="text" name="full_name" class="form-control-custom w-100" value="<?= htmlspecialchars($display_name) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Email Address</label>
                                        <input type="email" name="email" class="form-control-custom w-100" value="<?= htmlspecialchars($display_email) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Phone Number</label>
                                        <input type="text" name="phone" class="form-control-custom w-100" value="<?= htmlspecialchars($display_phone) ?>" placeholder="e.g. 09123456789">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Address</label>
                                        <input type="text" name="address" class="form-control-custom w-100" value="<?= htmlspecialchars($display_address) ?>" placeholder="City, Province">
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" name="update_profile" id="saveProfileBtn" class="btn-save">
                                            <i class="fas fa-save me-2" id="saveProfileIcon"></i> Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <hr class="custom-hr">

                            <h6 class="fw-bold text-dark mb-4"><i class="fas fa-lock text-dark me-2"></i> Change Password</h6>
                            <form method="POST" id="passwordForm">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Current Password</label>
                                        <input type="password" name="current_password" class="form-control-custom w-100" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">New Password</label>
                                        <input type="password" name="new_password" class="form-control-custom w-100" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Confirm Password</label>
                                        <input type="password" name="confirm_password" class="form-control-custom w-100" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" name="update_password" id="savePassBtn" class="btn-password">
                                            <i class="fas fa-key me-2" id="savePassIcon"></i> Update Password
                                        </button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Trigger Success/Error Messages from backend safely
    <?php if ($swal_script !== ''): ?>
        <?= $swal_script; ?>
    <?php endif; ?>

    document.addEventListener("DOMContentLoaded", function() {
        
        // Profile Update Loading Effect
        const profileForm = document.getElementById("profileForm");
        const saveProfileBtn = document.getElementById("saveProfileBtn");
        const saveProfileIcon = document.getElementById("saveProfileIcon");

        if (profileForm && saveProfileBtn && saveProfileIcon) {
            profileForm.addEventListener("submit", function() {
                saveProfileBtn.classList.add("disabled"); 
                saveProfileIcon.classList.remove("fa-save"); 
                saveProfileIcon.classList.add("fa-spinner", "fa-spin"); 
                saveProfileBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
            });
        }

        // Password Update Loading Effect
        const passwordForm = document.getElementById("passwordForm");
        const savePassBtn = document.getElementById("savePassBtn");
        const savePassIcon = document.getElementById("savePassIcon");

        if (passwordForm && savePassBtn && savePassIcon) {
            passwordForm.addEventListener("submit", function() {
                savePassBtn.classList.add("disabled"); 
                savePassIcon.classList.remove("fa-key"); 
                savePassIcon.classList.add("fa-spinner", "fa-spin"); 
                savePassBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>