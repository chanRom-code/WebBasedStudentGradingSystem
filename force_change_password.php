<?php
session_start();
include 'includes/config.php';

// Kapag wala siyang temporary session, ibalik sa login
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_role'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['temp_user_id'];
$role = $_SESSION['temp_role'];
$message = '';
$message_type = '';
$swal_script = ''; // Para sa SweetAlert Success Message

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $strongPasswordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,}$/';

    if ($new_password === '' || $confirm_password === '') {
        $message = 'Please fill out all fields.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } elseif (!preg_match($strongPasswordRegex, $new_password)) {
        $message = 'Password must be at least 8 characters, with 1 uppercase, 1 lowercase, and 1 number.';
        $message_type = 'danger';
    } else {
        // I-save ang bagong password at patayin ang password flag
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, require_password_change = 0 WHERE user_id = ?");
        
        if ($update_stmt) {
            mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);
            if (mysqli_stmt_execute($update_stmt)) {
                // ILIPAT ANG TEMP SESSION SA TOTOONG SESSION (LOGGED IN NA SIYA)
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = $role;

                // KUNIN ANG PANGALAN PARA SA SESSION
                $name_stmt = mysqli_query($conn, "SELECT full_name FROM users WHERE user_id = $user_id");
                if ($row = mysqli_fetch_assoc($name_stmt)) {
                    $_SESSION['full_name'] = $row['full_name'];
                }

                // TANGGALIN NA ANG TEMP SESSIONS
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_role']);

                // I-SET ANG REDIRECT URL AT TRIGGER ANG SWEETALERT
                $redirect_url = ($role === 'admin') ? 'admin/dashboard.php' : 'professor/dashboard.php';
                
                $swal_script = "
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Secured!',
                        text: 'Your password has been changed successfully. Entering dashboard...',
                        showConfirmButton: false,
                        timer: 2500,
                        allowOutsideClick: false,
                        background: '#ffffff',
                        color: '#0F172A',
                        iconColor: '#10B981'
                    }).then(() => {
                        window.location.href = '$redirect_url';
                    });
                ";
            } else {
                $message = 'Failed to update password. Please try again.';
                $message_type = 'danger';
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | SFICS Grading System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: linear-gradient(rgba(15, 81, 50, 0.65), rgba(25, 135, 84, 0.55)), 
                        url('assets/illustration.png') center/cover no-repeat fixed; 
        }

        .auth-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(20px); 
            border-radius: 24px; 
            border: 1px solid rgba(255, 255, 255, 0.5); 
            padding: 45px 40px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.25); 
            width: 100%; 
            max-width: 420px; 
            position: relative;
            overflow: hidden;
        }

        /* Top Accent Line */
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, #198754, #20c997);
        }

        .logo-container { text-align: center; margin-bottom: 25px; }
        .logo-container img { width: 70px; height: 70px; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.1); padding: 4px; background: white; margin-bottom: 15px;}
        .logo-container h4 { font-weight: 800; color: #0F172A; letter-spacing: -0.5px; margin-bottom: 5px; }
        .logo-container p { font-size: 13px; color: #64748B; line-height: 1.5; }

        .form-group { position: relative; margin-bottom: 20px; }
        .form-label-custom { display: block; font-size: 12px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        
        .input-wrapper { position: relative; }
        .input-wrapper i.fa-lock { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 14px; transition: 0.3s; }
        
        .form-control-custom { 
            width: 100%; 
            padding: 14px 15px 14px 40px; 
            border-radius: 12px; 
            border: 1px solid #E2E8F0; 
            font-size: 14px; 
            background-color: #F8FAFC; 
            transition: all 0.3s ease; 
            color: #0F172A;
            font-weight: 500;
        }

        .form-control-custom:focus { 
            border-color: #10B981; 
            background-color: #fff; 
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); 
            outline: none; 
        }

        .form-control-custom:focus + i.fa-lock { color: #10B981; }

        /* Eye Toggle Button */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            cursor: pointer;
            transition: 0.2s;
            background: none;
            border: none;
            padding: 0;
        }
        .toggle-password:hover { color: #10B981; }

        .btn-save { 
            background: linear-gradient(135deg, #198754, #146c43); 
            color: white; 
            border-radius: 12px; 
            font-weight: 600; 
            font-size: 15px; 
            padding: 14px; 
            border: none; 
            width: 100%; 
            transition: all 0.3s ease; 
            box-shadow: 0 8px 15px rgba(25, 135, 84, 0.25); 
            margin-top: 10px;
        }
        .btn-save:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 12px 20px rgba(25, 135, 84, 0.35); 
            background: linear-gradient(135deg, #146c43, #0f5132); 
        }

        .password-hint { font-size: 11.5px; color: #64748B; margin-top: 8px; display: flex; align-items: start; gap: 6px; }
        .password-hint i { margin-top: 2px; color: #10B981;}

    </style>
</head>
<body>

<div class="auth-card">
    <div class="logo-container">
        <img src="assets/SFICS-LOGO.jpg" alt="SFICS Logo">
        <h4>Setup New Password</h4>
        <p>Welcome! For your security, please replace your temporary password with a new one.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type; ?> shadow-sm border-0 d-flex align-items-center" style="font-size: 13px; border-radius: 10px; background-color: #FEF2F2; color: #EF4444;">
            <i class="fas fa-exclamation-circle fs-5 me-2"></i> <?= $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="forcePassForm">
        
        <div class="form-group">
            <label class="form-label-custom">New Password</label>
            <div class="input-wrapper">
                <input type="password" name="new_password" id="new_password" class="form-control-custom" placeholder="Create strong password" required>
                <i class="fas fa-lock"></i>
                <button type="button" class="toggle-password" onclick="toggleVisibility('new_password', this)">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            <div class="password-hint">
                <i class="fas fa-shield-check"></i>
                <span>Must be at least 8 characters long, including an uppercase letter, a lowercase letter, and a number.</span>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label-custom">Confirm Password</label>
            <div class="input-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control-custom" placeholder="Re-type new password" required>
                <i class="fas fa-lock"></i>
                <button type="button" class="toggle-password" onclick="toggleVisibility('confirm_password', this)">
                    <i class="far fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" name="change_password" id="saveBtn" class="btn-save">
            <i class="fas fa-check-circle me-2" id="saveIcon"></i> Save & Continue
        </button>
    </form>
</div>

<script>
// Logic para sa pag-view/hide ng password
function toggleVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("forcePassForm");
    const saveBtn = document.getElementById("saveBtn");
    const saveIcon = document.getElementById("saveIcon");

    // I-run ang SweetAlert kung success
    <?= $swal_script; ?>

    form.addEventListener("submit", function() {
        saveBtn.classList.add("disabled");
        saveIcon.classList.remove("fa-check-circle");
        saveIcon.classList.add("fa-spinner", "fa-spin");
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Securing Account...';
    });
});
</script>

</body>
</html>