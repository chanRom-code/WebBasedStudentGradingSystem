<?php
session_start();

/** @var mysqli $conn */
include 'includes/config.php'; 

date_default_timezone_set('Asia/Manila'); 

$error = "";
$email = "";
$login_success = false;
$welcome_role = "";
$redirect_url = "";
$swal_forgot = "";
$show_new_password_modal = false;

// --- 1. ADMIN RESET REQUEST LOGIC (ANTI-SPAM & INACTIVE CHECK ADDED) ---
if (isset($_POST['forgot_email'])) {
    $forgot_email = trim((string)$_POST['forgot_email']);
    // Isinama ang 'status' sa query
    $check_user = mysqli_prepare($conn, "SELECT user_id, status FROM users WHERE email = ? LIMIT 1");
    if ($check_user) {
        mysqli_stmt_bind_param($check_user, "s", $forgot_email);
        mysqli_stmt_execute($check_user);
        $user_res = mysqli_stmt_get_result($check_user);

        if ($user_res && mysqli_num_rows($user_res) > 0) {
            $user_data = mysqli_fetch_assoc($user_res);
            
            // BLOCKER PARA SA INACTIVE ACCOUNT
            if (isset($user_data['status']) && $user_data['status'] === 'inactive') {
                $swal_forgot = "Swal.fire({ icon: 'error', title: 'Account Disabled', text: 'Your account has been deactivated. You cannot request a password reset.', confirmButtonColor: '#EF4444', customClass: { popup: 'rounded-20' } });";
            } else {
                $u_id = (int)$user_data['user_id'];
                
                $check_pending = mysqli_prepare($conn, "SELECT id FROM password_resets WHERE user_id = ? AND status = 'pending' LIMIT 1");
                if ($check_pending) {
                    mysqli_stmt_bind_param($check_pending, "i", $u_id);
                    mysqli_stmt_execute($check_pending);
                    $pending_res = mysqli_stmt_get_result($check_pending);
                    
                    if ($pending_res && mysqli_num_rows($pending_res) > 0) {
                        $swal_forgot = "Swal.fire({ icon: 'warning', title: 'Already Requested', text: 'You already have a pending password reset request. Please wait for the admin to approve it.', confirmButtonColor: '#F59E0B', customClass: { popup: 'rounded-20' } });";
                    } else {
                        $stmt_req = mysqli_prepare($conn, "INSERT INTO password_resets (user_id, email, status) VALUES (?, ?, 'pending')");
                        if ($stmt_req) {
                            mysqli_stmt_bind_param($stmt_req, "is", $u_id, $forgot_email);
                            if (mysqli_stmt_execute($stmt_req)) {
                                $swal_forgot = "Swal.fire({ icon: 'success', title: 'Request Sent!', text: 'Please wait for the admin to approve your password reset request.', confirmButtonColor: '#10B981', customClass: { popup: 'rounded-20' } });";
                            }
                            mysqli_stmt_close($stmt_req);
                        }
                    }
                    mysqli_stmt_close($check_pending);
                }
            }
        } else {
            $swal_forgot = "Swal.fire({ icon: 'error', title: 'Email Not Found', text: 'This email is not registered in our system.', confirmButtonColor: '#EF4444', customClass: { popup: 'rounded-20' } });";
        }
        mysqli_stmt_close($check_user);
    }
}

// --- 2. SECURITY QUESTIONS RECOVERY LOGIC (WITH LOCKOUT & INACTIVE CHECK) ---
if (isset($_POST['submit_security'])) {
    $rec_email = trim((string)$_POST['rec_email']);
    $rec_name = trim((string)$_POST['rec_name']);       
    $rec_date = trim((string)$_POST['rec_date']);       
    
    $rec_sub1 = trim((string)$_POST['rec_sub1']); 
    $rec_sub2 = trim((string)$_POST['rec_sub2']); 
    $rec_sub3 = trim((string)$_POST['rec_sub3']); 

    // Isinama ang status sa query
    $chk_stmt = mysqli_prepare($conn, "SELECT user_id, full_name, DATE(created_at) as c_date, failed_attempts, lockout_level, lockout_time, status FROM users WHERE email = ? LIMIT 1");
    if ($chk_stmt) {
        mysqli_stmt_bind_param($chk_stmt, "s", $rec_email);
        mysqli_stmt_execute($chk_stmt);
        $res = mysqli_stmt_get_result($chk_stmt);

        if ($res && mysqli_num_rows($res) > 0) {
            $u = mysqli_fetch_assoc($res);
            
            // BLOCKER PARA SA INACTIVE ACCOUNT
            if (isset($u['status']) && $u['status'] === 'inactive') {
                $swal_forgot = "Swal.fire({ icon: 'error', title: 'Account Disabled', text: 'Your account has been deactivated. You cannot perform account recovery.', confirmButtonColor: '#EF4444', customClass: { popup: 'rounded-20' } });";
            } else {
                $is_locked = false;
                $remaining_time_msg = "";

                // CHECK KUNG LOCKED ANG ACCOUNT
                if (!empty($u['lockout_time'])) {
                    $current_time = new DateTime();
                    $lockout_time = new DateTime($u['lockout_time']);

                    if ($current_time < $lockout_time) {
                        $is_locked = true;
                        $diff = $current_time->diff($lockout_time);
                        if ($diff->h > 0) { $remaining_time_msg = $diff->h . " hour(s) and " . $diff->i . " min(s)"; } 
                        elseif ($diff->i > 0) { $remaining_time_msg = $diff->i . " minute(s) and " . $diff->s . " sec(s)"; } 
                        else { $remaining_time_msg = $diff->s . " second(s)"; }
                    }
                }

                if ($is_locked) {
                    $swal_forgot = "Swal.fire({ icon: 'warning', title: 'Account Locked', text: 'Too many operations. Please try again later in " . $remaining_time_msg . ".', confirmButtonColor: '#F59E0B', customClass: { popup: 'rounded-20' } });";
                } else {
                    // KAPAG HINDI LOCKED, I-CHECK ANG MGA SAGOT
                    $answers_correct = false;
                    $specific_error = "";

                    // Validation 1: Date Created AT Full Name
                    if (strtolower($u['full_name']) === strtolower($rec_name) && $u['c_date'] === $rec_date) {
                        
                        // Validation 2: Subject Codes Verification
                        $sub_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as valid_subs FROM subjects WHERE subject_code IN (?, ?, ?)");
                        mysqli_stmt_bind_param($sub_stmt, "sss", $rec_sub1, $rec_sub2, $rec_sub3);
                        mysqli_stmt_execute($sub_stmt);
                        $sub_res = mysqli_stmt_get_result($sub_stmt);
                        $sub_data = mysqli_fetch_assoc($sub_res);
                        
                        if ($sub_data['valid_subs'] == 3) {
                            $answers_correct = true;
                        } else {
                            $specific_error = "One or more Subject Codes you provided are incorrect.";
                        }
                        mysqli_stmt_close($sub_stmt);
                    } else {
                        $specific_error = "The Account Creation Date or Registered Full Name is incorrect.";
                    }

                    // KUNG TAMA LAHAT
                    if ($answers_correct) {
                        $reset_stmt = mysqli_prepare($conn, "UPDATE users SET failed_attempts = 0, lockout_level = 0, lockout_time = NULL WHERE user_id = ?");
                        if ($reset_stmt) {
                            mysqli_stmt_bind_param($reset_stmt, "i", $u['user_id']);
                            mysqli_stmt_execute($reset_stmt);
                            mysqli_stmt_close($reset_stmt);
                        }

                        $_SESSION['recovery_user_id'] = $u['user_id'];
                        $show_new_password_modal = true;
                    } else {
                        // KUNG MALI: Increment Failed Attempts and Lock if needed
                        $failed_attempts = (int)$u['failed_attempts'] + 1;
                        $lockout_level = (int)$u['lockout_level'];
                        $lockout_time = NULL;
                        $err_msg = "";

                        if ($failed_attempts >= 3) {
                            $lockout_level++;
                            $failed_attempts = 0; 
                            if ($lockout_level == 1) {
                                $lockout_time = date('Y-m-d H:i:s', strtotime('+1 minute'));
                                $err_msg = "Too many failed recovery attempts. Account locked for 1 minute.";
                            } elseif ($lockout_level == 2) {
                                $lockout_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                                $err_msg = "Too many failed recovery attempts. Account locked for 10 minutes.";
                            } else {
                                $lockout_time = date('Y-m-d H:i:s', strtotime('+3 hours'));
                                $err_msg = "Security risk detected. Account locked for 3 hours.";
                                $lockout_level = 3; 
                            }
                        } else {
                            $attempts_left = 3 - $failed_attempts;
                            $err_msg = $specific_error . " You have " . $attempts_left . " attempt(s) left.";
                        }

                        $update_stmt = mysqli_prepare($conn, "UPDATE users SET failed_attempts = ?, lockout_level = ?, lockout_time = ? WHERE user_id = ?");
                        if ($update_stmt) {
                            mysqli_stmt_bind_param($update_stmt, "iisi", $failed_attempts, $lockout_level, $lockout_time, $u['user_id']);
                            mysqli_stmt_execute($update_stmt);
                            mysqli_stmt_close($update_stmt);
                        }

                        $swal_forgot = "Swal.fire({ icon: 'error', title: 'Verification Failed', text: '" . addslashes($err_msg) . "', confirmButtonColor: '#EF4444', customClass: { popup: 'rounded-20' } });";
                    }
                }
            }
        } else {
            $swal_forgot = "Swal.fire({ icon: 'error', title: 'Error', text: 'Email not found in our records.', confirmButtonColor: '#EF4444', customClass: { popup: 'rounded-20' } });";
        }
        mysqli_stmt_close($chk_stmt);
    }
}

// --- 3. SAVE NEW RECOVERED PASSWORD ---
if (isset($_POST['submit_new_password']) && isset($_SESSION['recovery_user_id'])) {
    $new_pass = password_hash(trim((string)$_POST['new_password']), PASSWORD_DEFAULT);
    $uid = (int)$_SESSION['recovery_user_id'];

    $upd_stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, require_password_change = 0 WHERE user_id = ?");
    if ($upd_stmt) {
        mysqli_stmt_bind_param($upd_stmt, "si", $new_pass, $uid);
        if (mysqli_stmt_execute($upd_stmt)) {
            unset($_SESSION['recovery_user_id']); 
            
            $del_stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ?");
            if ($del_stmt) {
                mysqli_stmt_bind_param($del_stmt, "i", $uid);
                mysqli_stmt_execute($del_stmt);
                mysqli_stmt_close($del_stmt);
            }

            $swal_forgot = "Swal.fire({ icon: 'success', title: 'Password Reset Successful!', text: 'You may now log in using your new password.', confirmButtonColor: '#10B981', customClass: { popup: 'rounded-20' } });";
        }
        mysqli_stmt_close($upd_stmt);
    }
}

// --- 4. NORMAL LOGIN LOGIC WITH LOCKOUT & INACTIVE FEATURE ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['forgot_email']) && !isset($_POST['submit_security']) && !isset($_POST['submit_new_password'])) {
    $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $password = isset($_POST['password']) ? trim((string)$_POST['password']) : '';

    if ($email === '' || $password === '') {
        $error = "Please enter your email and password.";
    } else {
        // Isinama ang 'status' column sa query
        $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, password, role, require_password_change, failed_attempts, lockout_level, lockout_time, status FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $user = mysqli_fetch_assoc($result);
                
                // === INACTIVE STATUS BLOCKER ===
                if (isset($user['status']) && $user['status'] === 'inactive') {
                    $error = "Login Error. Please contact the administrator.";
                } else {
                    $is_locked = false;
                    $remaining_time_msg = "";

                    if (!empty($user['lockout_time'])) {
                        $current_time = new DateTime();
                        $lockout_time = new DateTime($user['lockout_time']);

                        if ($current_time < $lockout_time) {
                            $is_locked = true;
                            $diff = $current_time->diff($lockout_time);
                            if ($diff->h > 0) { $remaining_time_msg = $diff->h . " hour(s) and " . $diff->i . " min(s)"; } 
                            elseif ($diff->i > 0) { $remaining_time_msg = $diff->i . " minute(s) and " . $diff->s . " sec(s)"; } 
                            else { $remaining_time_msg = $diff->s . " second(s)"; }
                        }
                    }

                    if ($is_locked) {
                        $error = "Account locked. Please try again in " . $remaining_time_msg . ".";
                    } else {
                        if (password_verify($password, $user['password'])) {
                            $reset_stmt = mysqli_prepare($conn, "UPDATE users SET failed_attempts = 0, lockout_level = 0, lockout_time = NULL WHERE user_id = ?");
                            if ($reset_stmt) {
                                mysqli_stmt_bind_param($reset_stmt, "i", $user['user_id']);
                                mysqli_stmt_execute($reset_stmt);
                                mysqli_stmt_close($reset_stmt);
                            }
                            
                            if (isset($user['require_password_change']) && (int)$user['require_password_change'] === 1) {
                                $_SESSION['temp_user_id'] = $user['user_id'];
                                $_SESSION['temp_role'] = $user['role'];
                                header("Location: force_change_password.php");
                                exit;
                            } else {
                                $_SESSION['user_id'] = $user['user_id'];
                                $_SESSION['full_name'] = $user['full_name'];
                                $_SESSION['role'] = $user['role'];
                                $login_success = true;
                                $welcome_role = ucfirst($user['role']);
                                $redirect_url = ($user['role'] === 'admin') ? "admin/dashboard.php" : "professor/dashboard.php";
                            }
                        } else {
                            $failed_attempts = (int)$user['failed_attempts'] + 1;
                            $lockout_level = (int)$user['lockout_level'];
                            $lockout_time = NULL;

                            if ($failed_attempts >= 3) {
                                $lockout_level++;
                                $failed_attempts = 0; 
                                if ($lockout_level == 1) {
                                    $lockout_time = date('Y-m-d H:i:s', strtotime('+1 minute'));
                                    $error = "Too many failed attempts. Account locked for 1 minute.";
                                } elseif ($lockout_level == 2) {
                                    $lockout_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                                    $error = "Too many failed attempts. Account locked for 10 minutes.";
                                } else {
                                    $lockout_time = date('Y-m-d H:i:s', strtotime('+3 hours'));
                                    $error = "Security risk detected. Account locked for 3 hours.";
                                    $lockout_level = 3; 
                                }
                            } else {
                                $attempts_left = 3 - $failed_attempts;
                                $error = "Invalid password. You have " . $attempts_left . " attempt(s) left.";
                            }

                            $update_stmt = mysqli_prepare($conn, "UPDATE users SET failed_attempts = ?, lockout_level = ?, lockout_time = ? WHERE user_id = ?");
                            if ($update_stmt) {
                                mysqli_stmt_bind_param($update_stmt, "iisi", $failed_attempts, $lockout_level, $lockout_time, $user['user_id']);
                                mysqli_stmt_execute($update_stmt);
                                mysqli_stmt_close($update_stmt);
                            }
                        }
                    }
                } // End of inactive check block
            } else { $error = "Invalid email or password."; }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SFICS Grading System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body { 
        font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; 
        background: linear-gradient(rgba(15, 81, 50, 0.45), rgba(25, 135, 84, 0.35)), url('assets/illustration.png') center/cover no-repeat fixed; 
    }

    .login-container { 
        width: clamp(350px, 90%, 950px); min-height: 560px; border-radius: 24px; overflow: hidden; display: flex; 
        background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); 
        border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); 
    }

    .left-panel { width: 50%; position: relative; background: linear-gradient(135deg, rgba(25, 135, 84, 0.9), rgba(15, 81, 50, 0.9)); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px; }
    @media (max-width: 768px) { .left-panel { display: none; } .right-panel { width: 100% !important; } }

    .school-logo { position: absolute; top: 25px; left: 25px; width: 70px; height: 70px; object-fit: contain; border-radius: 50%; background: #fff; padding: 5px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .illustration { width: 100%; max-width: 400px; height: auto; z-index: 2; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2)); }
    .text-content { margin-top: 20px; text-align: center; color: white; z-index: 3; }
    .text-content h2 { font-size: clamp(20px, 4vw, 28px); font-weight: 700; margin-bottom: 5px; }
    .text-content p { font-size: 14px; opacity: 0.9; font-weight: 400; }

    .right-panel { width: 50%; padding: clamp(30px, 5vw, 50px); display: flex; flex-direction: column; justify-content: center; background: rgba(255, 255, 255, 0.95); }
    .brand-top { text-align: center; margin-bottom: 30px; }
    .brand-top img { width: 65px; margin-bottom: 10px; }
    .brand-top h1 { font-size: 28px; color: #198754; margin: 0; font-weight: 800; letter-spacing: -0.5px;}
    .brand-top h3 { font-size: 18px; color: #333; margin: 5px 0; font-weight: 600;}
    .brand-top p { font-size: 13px; color: #64748B; }

    .error-box { color: #EF4444; background: #FEF2F2; border: 1px solid #FECACA; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; display: flex; align-items: center; gap: 8px; }

    .form-group { margin-bottom: 20px; text-align: left; }
    .form-group label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; color: #475569; }
    
    .input-wrapper { position: relative; display: flex; align-items: center; }
    .input-wrapper i.input-icon { position: absolute; left: 15px; color: #94A3B8; font-size: 14px; transition: 0.3s; }
    .input-wrapper input, .modal-input { width: 100%; padding: 14px 15px 14px 42px; border: 1px solid #E2E8F0; border-radius: 12px; font-size: 14px; transition: 0.3s; background: #F8FAFC; color: #0F172A; font-weight: 500; }
    .modal-input { padding: 10px 15px !important; margin-bottom: 10px; }
    .input-wrapper input:focus, .modal-input:focus { border-color: #198754; background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.1); }
    .input-wrapper input:focus + i.input-icon { color: #198754; }

    .toggle-password { position: absolute; right: 15px; color: #94A3B8; cursor: pointer; transition: 0.2s; background: none; border: none; padding: 0; }
    .toggle-password:hover { color: #198754; }

    .login-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #198754, #146c43); color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; transition: 0.3s; box-shadow: 0 8px 15px rgba(25, 135, 84, 0.2); display: flex; justify-content: center; align-items: center; gap: 8px; }
    .login-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 20px rgba(25, 135, 84, 0.3); }

    .forgot-link { text-align: center; display: block; margin-top: 18px; color: #198754; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; }
    .forgot-link:hover { text-decoration: underline; color: #146c43;}
    .footer-note { margin-top: 30px; text-align: center; color: #94A3B8; font-size: 12px; }

    /* CUSTOM MODAL CSS */
    .custom-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; }
    .custom-modal-box { background: #ffffff; width: 90%; max-width: 480px; padding: 30px; border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); text-align: left; max-height: 90vh; overflow-y: auto; }
</style>

</head>
<body>
    <div class="login-container">
        <div class="left-panel">
            <div class="overlay"></div>
            <img src="assets/SFICS-LOGO.jpg" alt="SFICS Logo" class="school-logo">
            <img src="assets/illustration.png" alt="Illustration" class="illustration">
            <div class="text-content">
                <h2>Empowering Academic Excellence</h2>
                <p>Saint Francis Institute of Computer Studies</p>
            </div>
        </div>
        
        <div class="right-panel">
            <div class="brand-top">
                <img src="assets/SFICS-LOGO.jpg" alt="SFICS Logo">
                <h1>SFICS</h1>
                <h3>Web-Based Student Grading System</h3>
                <p>Sign in to continue to your account</p>
            </div>

            <?php if (!empty($error) && !$login_success): ?>
                <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error, ENT_QUOTES); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" placeholder="Enter your email" value="<?= htmlspecialchars($email, ENT_QUOTES); ?>" required>
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="toggle-password" onclick="toggleVisibility('password', this)"><i class="far fa-eye"></i></button>
                    </div>
                </div>
                
                <button type="submit" class="login-btn" id="loginBtn">Login <i class="fas fa-arrow-right ms-1" id="loginIcon"></i></button>
                <a onclick="showForgotPasswordOptions()" class="forgot-link">Forgot Password?</a>
            </form>

            <div class="footer-note">© <?= date('Y'); ?> Saint Francis Institute of Computer Studies</div>
        </div>
    </div>

    <form id="forgotPassForm" method="POST" action="login.php" style="display: none;">
        <input type="hidden" name="forgot_email" id="hidden_forgot_email">
    </form>

    <div id="securityModal" class="custom-modal-overlay">
        <div class="custom-modal-box">
            <h4 style="font-weight: 700; color: #0F172A; margin-bottom: 20px;"><i class="fas fa-user-shield text-success me-2"></i> Security Verification</h4>
            <p style="font-size: 13px; color: #64748B; margin-bottom: 20px;">Provide accurate details associated with your account to verify your identity.</p>
            
            <form action="login.php" method="POST">
                <input type="hidden" name="rec_email" id="sec_email_input">
                
                <div class="form-group mb-3">
                    <label>1. What is your exact registered Full Name?</label>
                    <input type="text" name="rec_name" placeholder="e.g. Juan Dela Cruz" required class="modal-input">
                </div>

                <div class="form-group mb-3">
                    <label>2. When was your account created?</label>
                    <input type="date" name="rec_date" required class="modal-input">
                </div>

                <div class="form-group mb-3">
                    <label>3. Enter three (3) Subject Codes you handle</label>
                    <div class="d-flex" style="gap: 10px;">
                        <input type="text" name="rec_sub1" placeholder="Subj 1 (e.g. IT101)" required class="modal-input" style="flex:1;">
                        <input type="text" name="rec_sub2" placeholder="Subj 2" required class="modal-input" style="flex:1;">
                        <input type="text" name="rec_sub3" placeholder="Subj 3" required class="modal-input" style="flex:1;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="document.getElementById('securityModal').style.display='none';" style="padding: 10px 15px; background: #F1F5F9; color: #64748B; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Cancel</button>
                    <button type="submit" name="submit_security" style="padding: 10px 15px; background: #0EA5E9; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Verify Identity</button>
                </div>
            </form>
        </div>
    </div>

    <div id="newPassModal" class="custom-modal-overlay" style="<?= $show_new_password_modal ? 'display:flex;' : 'display:none;' ?>">
        <div class="custom-modal-box">
            <h4 style="font-weight: 700; color: #0F172A; margin-bottom: 10px;">Reset Password</h4>
            <div style="background: #ECFDF5; color: #10B981; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px;">
                <i class="fas fa-check-circle me-1"></i> Identity Verified!
            </div>
            
            <form action="login.php" method="POST">
                <div class="form-group" style="margin-bottom: 25px;">
                    <label>Enter New Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="new_password_input" name="new_password" placeholder="••••••••" required class="modal-input" style="padding-left: 15px;">
                        <button type="button" class="toggle-password" onclick="toggleVisibility('new_password_input', this)"><i class="far fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" name="submit_new_password" style="width: 100%; padding: 12px; background: #10B981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Save New Password</button>
            </form>
        </div>
    </div>

    <script>
    // SUCCESS LOGIN ANIMATION
    <?php if ($login_success): ?>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: 'success', title: 'Welcome <?= htmlspecialchars($welcome_role, ENT_QUOTES) ?>!', text: 'Successfully logged in. Please wait...',
            showConfirmButton: false, timer: 2000, allowOutsideClick: false, background: '#ffffff', iconColor: '#198754'
        }).then(() => { window.location.href = '<?= $redirect_url ?>'; });
    });
    <?php endif; ?>

    // SWEETALERT POPUPS FROM PHP
    <?php if ($swal_forgot !== ''): ?>
        <?= $swal_forgot; ?>
    <?php endif; ?>

    function toggleVisibility(inputId, button) {
        const input = document.getElementById(inputId);
        const icon = button.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text'; icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password'; icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    // MULTI-OPTION FORGOT PASSWORD PROMPT
    function showForgotPasswordOptions() {
        Swal.fire({
            title: 'Account Recovery',
            text: 'How would you like to recover your account?',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-user-shield me-1"></i> Security Questions',
            denyButtonText: '<i class="fas fa-paper-plane me-1"></i> Request Admin Reset',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0EA5E9', 
            denyButtonColor: '#10B981', 
            customClass: { popup: 'rounded-20' }
        }).then((result) => {
            if (result.isConfirmed) {
                // OPTION 1: Security Questions
                Swal.fire({
                    title: 'Verify Your Email',
                    text: 'Please enter your email to proceed with security questions.',
                    input: 'email',
                    inputPlaceholder: 'e.g. prof@sfics.edu.ph',
                    showCancelButton: true,
                    confirmButtonColor: '#0EA5E9',
                    confirmButtonText: 'Proceed <i class="fas fa-arrow-right ms-1"></i>',
                    cancelButtonText: 'Cancel',
                    customClass: { popup: 'rounded-20' },
                    inputValidator: (value) => { if (!value) return 'Email is required!' },
                    
                    // PRE-CHECK VIA AJAX (If email is valid before opening modal)
                    preConfirm: (email) => {
                        return fetch(`check_email.php?email=${encodeURIComponent(email)}`)
                            .then(response => {
                                if (!response.ok) throw new Error(response.statusText)
                                return response.json()
                            })
                            .then(data => {
                                if (data.status === 'error') {
                                    Swal.showValidationMessage('This email is not registered in our system.')
                                }
                                return data;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(`Request failed: ${error}`)
                            })
                    }
                }).then((emailResult) => {
                    // IF EMAIL IS VALIDATED
                    if (emailResult.isConfirmed && emailResult.value && emailResult.value.status === 'success') {
                        document.getElementById('sec_email_input').value = emailResult.value.email;
                        document.getElementById('securityModal').style.display = 'flex';
                    }
                });
            } else if (result.isDenied) {
                // OPTION 2: Request to Admin
                Swal.fire({
                    title: 'Reset Request',
                    text: 'Enter your registered email address:',
                    input: 'email',
                    inputPlaceholder: 'e.g. prof@sfics.edu.ph',
                    showCancelButton: true,
                    confirmButtonColor: '#10B981',
                    confirmButtonText: 'Send Request',
                    cancelButtonText: 'Cancel',
                    customClass: { popup: 'rounded-20' },
                    inputValidator: (value) => { if (!value) return 'Email is required!' }
                }).then((reqResult) => {
                    if (reqResult.isConfirmed && reqResult.value) {
                        document.getElementById('hidden_forgot_email').value = reqResult.value;
                        document.getElementById('forgotPassForm').submit();
                    }
                });
            }
        });
    }

    document.getElementById("loginForm").addEventListener("submit", function() {
        <?php if (!$login_success): ?>
            const loginBtn = document.getElementById("loginBtn");
            loginBtn.classList.add("disabled");
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Authenticating...';
        <?php endif; ?>
    });
    </script>
</body>
</html>