<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$current_page = basename($_SERVER['PHP_SELF']);
$swal_script = ''; 

// NOTE: Inalis na natin ang UPDATE PROFILE INFORMATION block dito dahil view-only na lang ito.

// UPDATE PASSWORD (SMART VERIFICATION) - Pwede pa ring magpalit ng password ang professor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $pass_stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($pass_stmt, "i", $user_id);
    mysqli_stmt_execute($pass_stmt);
    $pass_res = mysqli_stmt_get_result($pass_stmt);
    $user_data = mysqli_fetch_assoc($pass_res);
    mysqli_stmt_close($pass_stmt);

    $is_valid_password = false;
    
    // SMART CHECK (Bcrypt, MD5, Plaintext)
    if (password_verify($current_password, $user_data['password'])) {
        $is_valid_password = true;
    } elseif (md5($current_password) === $user_data['password']) {
        $is_valid_password = true;
    } elseif ($current_password === $user_data['password']) {
        $is_valid_password = true;
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
        mysqli_stmt_bind_param($upd_pass_stmt, "si", $new_hash, $user_id);
        if (mysqli_stmt_execute($upd_pass_stmt)) {
            $swal_script = "Swal.fire({ icon: 'success', title: 'Password Changed!', text: 'Your password has been updated successfully.', showConfirmButton: false, timer: 2000 });";
        } else {
            $swal_script = "Swal.fire({ icon: 'error', title: 'Error', text: 'Could not update password in database.' });";
        }
        mysqli_stmt_close($upd_pass_stmt);
    }
}

// FETCH CURRENT USER DATA
$user_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);

// Kukunin ang date kung kailan ginawa ang account
$display_created_at = isset($user_info['created_at']) ? date('F d, Y', strtotime($user_info['created_at'])) : 'N/A';

$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Professor');

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; }
    
    .profile-avatar-lg { width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 36px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); background: linear-gradient(135deg, #10B981 0%, #059669 100%); margin: 0 auto; }
    
    .form-label-custom { font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .form-control-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; }
    .form-control-custom:focus { border-color: #10B981; background-color: #fff; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); outline: none; }
    
    /* READ ONLY STYLE PARA SA PROFESSOR INFO */
    .form-control-readonly { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; color: #64748B; background-color: #F1F5F9; cursor: not-allowed; font-weight: 500; }
    
    .btn-password { background-color: #0F172A; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 10px 20px; border: none; transition: 0.2s; width: 100%; display: flex; justify-content: center; align-items: center; }
    .btn-password:hover { background-color: #1E293B; color: white; transform: translateY(-2px); }

    .custom-hr { border-color: #E2E8F0; margin: 30px 0; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        
        <?php include '../includes/professor_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> Account <span class="mx-1">></span> <strong class="text-dark">My Profile</strong>
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

                <div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">My Profile</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">View your personal information and manage your account security.</p>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="saas-card text-center text-md-start">
                            <div class="d-flex flex-column align-items-center mb-4">
                                <div class="profile-avatar-lg mb-3">
                                    <?= strtoupper(substr($user_info['full_name'], 0, 1)) ?>
                                </div>
                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($user_info['full_name']) ?></h5>
                                <span class="badge bg-success bg-opacity-10 text-success px-3 py-1" style="border-radius: 20px; font-weight: 600; font-size: 12px; letter-spacing: 0.5px;">
                                    <i class="fas fa-chalkboard-teacher me-1"></i> Professor
                                </span>
                            </div>
                            
                            <hr class="custom-hr my-3">
                            
                            <div class="text-start">
                                <p class="mb-2 text-muted" style="font-size: 13px;"><i class="fas fa-envelope me-2" style="width: 15px;"></i> <?= htmlspecialchars($user_info['email']) ?></p>
                                <?php if(!empty($user_info['phone'])): ?>
                                    <p class="mb-2 text-muted" style="font-size: 13px;"><i class="fas fa-phone me-2" style="width: 15px;"></i> <?= htmlspecialchars($user_info['phone']) ?></p>
                                <?php endif; ?>
                                <?php if(!empty($user_info['address'])): ?>
                                    <p class="mb-0 text-muted" style="font-size: 13px;"><i class="fas fa-map-marker-alt me-2" style="width: 15px;"></i> <?= htmlspecialchars($user_info['address']) ?></p>
                                <?php endif; ?>
                                
                                <hr class="custom-hr my-3">
                                <div>
                                    <label class="text-muted mb-1" style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Account Created</label>
                                    <p class="mb-0 fw-bold text-dark" style="font-size: 13px;">
                                        <i class="far fa-calendar-alt text-success me-2" style="width: 15px;"></i> 
                                        <?= $display_created_at ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="saas-card">
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-dark mb-0"><i class="fas fa-id-card text-success me-2"></i> Personal Information</h6>
                                <span class="badge bg-light text-muted border"><i class="fas fa-lock me-1"></i> Read-Only</span>
                            </div>
                            
                            <div class="alert alert-light border shadow-sm mb-4 d-flex align-items-start" style="font-size: 13px; border-radius: 10px;">
                                <i class="fas fa-info-circle text-primary fs-5 me-3 mt-1"></i>
                                <div>
                                    Your personal details are managed by the administration. If you need to correct or update any of this information, please contact the system administrator.
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-custom">Full Name</label>
                                    <input type="text" class="form-control-readonly w-100" value="<?= htmlspecialchars($user_info['full_name']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">Email Address</label>
                                    <input type="email" class="form-control-readonly w-100" value="<?= htmlspecialchars($user_info['email']) ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label-custom">Gender</label>
                                    <input type="text" class="form-control-readonly w-100" value="<?= htmlspecialchars($user_info['gender'] ?? 'Not Specified') ?>" readonly>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label-custom">Phone Number</label>
                                    <input type="text" class="form-control-readonly w-100" value="<?= htmlspecialchars($user_info['phone'] ?? 'Not Provided') ?>" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label-custom">Address</label>
                                    <input type="text" class="form-control-readonly w-100" value="<?= htmlspecialchars($user_info['address'] ?? 'Not Provided') ?>" readonly>
                                </div>
                            </div>

                            <hr class="custom-hr">

                            <h6 class="fw-bold text-dark mb-4"><i class="fas fa-shield-alt text-dark me-2"></i> Account Security</h6>
                            <form method="POST" id="passwordForm">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Current Password <span class="text-danger">*</span></label>
                                        <input type="password" name="current_password" class="form-control-custom w-100" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">New Password <span class="text-danger">*</span></label>
                                        <input type="password" name="new_password" class="form-control-custom w-100" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label-custom">Confirm Password <span class="text-danger">*</span></label>
                                        <input type="password" name="confirm_password" class="form-control-custom w-100" placeholder="••••••••" required>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" name="update_password" id="savePassBtn" class="btn-password" style="width: auto;">
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
    <?= $swal_script; ?>

    document.addEventListener("DOMContentLoaded", function() {
        const passwordForm = document.getElementById("passwordForm");
        const savePassBtn = document.getElementById("savePassBtn");
        const savePassIcon = document.getElementById("savePassIcon");

        if (passwordForm) {
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