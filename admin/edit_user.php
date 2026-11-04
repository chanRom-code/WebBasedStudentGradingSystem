<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = htmlspecialchars($_SESSION['full_name'] ?? 'Administrator');
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = '';

// KUNIN ANG USER ID MULA SA URL
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($edit_id <= 0) {
    header("Location: users.php?msg=error");
    exit;
}

// KUNIN ANG DATA NG INE-EDIT NA USER
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $edit_id);
mysqli_stmt_execute($stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user_data) {
    header("Location: users.php?msg=error");
    exit;
}

// UPDATE LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $role = trim($_POST['role'] ?? 'professor');
    $new_password = $_POST['password'] ?? ''; 

    // Kung nag-type ng bagong password, i-validate. Kung blanko, wag pansinin.
    $strongPasswordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,}$/';
    $password_error = false;

    if (!empty($new_password) && !preg_match($strongPasswordRegex, $new_password)) {
        $password_error = true;
    }

    if ($full_name === '' || $email === '' || $gender === '' || $role === '') {
        $message = 'Full Name, Email, Gender, and Role are required.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } elseif ($password_error) {
        $message = 'New password must be at least 8 characters, with 1 uppercase, 1 lowercase, and 1 number.';
        $message_type = 'danger';
    } elseif (!in_array($role, ['admin', 'professor'])) {
        $message = 'Invalid role selected.';
        $message_type = 'danger';
    } else {
        // Check duplicate email (EXCEPT the current user being edited)
        $check_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "si", $email, $edit_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $message = 'Email is already taken by another user.';
                $message_type = 'warning';
            } else {
                
                // KUNG WALANG TINYPE NA PASSWORD, WAG I-UPDATE ANG PASSWORD COLUMN
                if (empty($new_password)) {
                    $update_stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, gender = ?, phone = ?, address = ?, role = ? WHERE user_id = ?");
                    mysqli_stmt_bind_param($update_stmt, "ssssssi", $full_name, $email, $gender, $phone, $address, $role, $edit_id);
                } else {
                    // KUNG MAY TINYPE, I-HASH AT ISAMA SA UPDATE
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, gender = ?, phone = ?, address = ?, role = ?, password = ? WHERE user_id = ?");
                    mysqli_stmt_bind_param($update_stmt, "sssssssi", $full_name, $email, $gender, $phone, $address, $role, $hashed_password, $edit_id);
                }

                if ($update_stmt && mysqli_stmt_execute($update_stmt)) {
                    // Kung sarili niya ang inedit niya, i-update ang session name
                    if ($edit_id === (int) $_SESSION['user_id']) {
                        $_SESSION['full_name'] = $full_name;
                    }
                    mysqli_stmt_close($update_stmt);
                    mysqli_stmt_close($check_stmt);
                    header("Location: users.php?msg=edited");
                    exit;
                } else {
                    $message = 'Failed to update user record.';
                    $message_type = 'danger';
                }
            }
            mysqli_stmt_close($check_stmt);
        }
    }
}

include '../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* CONTENT & TOP NAVBAR (SAAS STYLE) */
    .content-wrapper { padding: 0 30px 30px 30px; background-color: #F4F7FE; min-height: 100vh; }
    .top-navbar { padding: 15px 0; border-bottom: 1px solid transparent; margin-bottom: 20px; }

    /* CARDS */
    .saas-card { background: #ffffff; border-radius: 16px; border: 1px solid #E2E8F0; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 24px; }
    
    /* FORMS */
    .form-label-custom { font-size: 12.5px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .form-control-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; }
    .form-control-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    .form-select-custom { border-radius: 8px; border: 1px solid #E2E8F0; padding: 12px 15px; font-size: 14px; color: #334155; background-color: #F8FAFC; transition: 0.2s; cursor: pointer;}
    .form-select-custom:focus { border-color: #0047FF; background-color: #fff; box-shadow: 0 0 0 3px rgba(0, 71, 255, 0.1); outline: none; }
    
    /* BUTTONS */
    .btn-save { background-color: #0047FF; color: white; border-radius: 8px; font-weight: 500; font-size: 14px; padding: 10px 24px; border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0, 71, 255, 0.2); display: inline-flex; align-items: center; justify-content: center; }
    .btn-save:hover { background-color: #0036cc; color: white; transform: translateY(-2px); box-shadow: 0 6px 8px rgba(0, 71, 255, 0.3); }
    
    .btn-cancel { background-color: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; border-radius: 8px; font-weight: 600; font-size: 14px; padding: 10px 24px; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;}
    .btn-cancel:hover { background-color: #E2E8F0; color: #0F172A; }

    .alert-custom { border-radius: 12px; font-size: 14px; font-weight: 500; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
      
     <?php include '../includes/admin_sidebar.php'; ?>

        <div class="col-md-10">
            <div class="content-wrapper">
                
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <div class="text-muted" style="font-size: 13px; font-weight: 500;">
                        Home <span class="mx-1">></span> User Management <span class="mx-1">></span> System Users <span class="mx-1">></span> <strong class="text-dark">Edit User</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Edit User Details</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Update the profile or security credentials of this account.</p>
                    </div>
                    <a href="users.php" class="btn-cancel shadow-sm"><i class="fas fa-arrow-left me-2"></i> Back to Users</a>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= htmlspecialchars($message_type); ?> alert-custom shadow-sm border-0 border-start border-4 border-<?= htmlspecialchars($message_type); ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                        <?= htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="saas-card">
                    <h6 class="fw-bold text-dark mb-4"><i class="fas fa-user-edit text-primary me-2"></i> Update Information</h6>
                    
                    <form method="POST" id="editUserForm">
                        <div class="row g-4">
                            
                            <div class="col-md-4">
                                <label class="form-label-custom">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control-custom w-100" value="<?= htmlspecialchars($user_data['full_name']); ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control-custom w-100" value="<?= htmlspecialchars($user_data['email']); ?>" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select-custom w-100" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male" <?= ($user_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?= ($user_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?= ($user_data['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other / Prefer not to say</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">Phone Number <span class="text-muted text-lowercase" style="font-size: 10px; font-weight: 400;">(Optional)</span></label>
                                <input type="text" name="phone" class="form-control-custom w-100" value="<?= htmlspecialchars($user_data['phone'] ?? ''); ?>" placeholder="e.g. 09123456789">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">Home Address <span class="text-muted text-lowercase" style="font-size: 10px; font-weight: 400;">(Optional)</span></label>
                                <input type="text" name="address" class="form-control-custom w-100" value="<?= htmlspecialchars($user_data['address'] ?? ''); ?>" placeholder="City, Province">
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label-custom">New Password <span class="text-muted text-lowercase" style="font-size: 10px; font-weight: 400;">(Leave blank to keep current)</span></label>
                                <input type="password" name="password" id="password" class="form-control-custom w-100" placeholder="••••••••">
                                <div id="passwordHelp" class="mt-2" style="font-size: 12px; color: #64748B;">
                                    <i class="fas fa-info-circle me-1"></i> Type a new password only if you want to change it.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">System Role <span class="text-danger">*</span></label>
                                <select name="role" class="form-select-custom w-100" required>
                                    <option value="professor" <?= $user_data['role'] === 'professor' ? 'selected' : ''; ?>>Professor</option>
                                    <option value="admin" <?= $user_data['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4" style="border-color: #E2E8F0;">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="users.php" class="btn-cancel">Cancel</a>
                            <button type="submit" name="submit" id="saveUserBtn" class="btn-save">
                                <i class="fas fa-save me-2" id="saveUserIcon"></i> Update User
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("editUserForm");
    const passwordInput = document.getElementById("password");
    const helpText = document.getElementById("passwordHelp");
    const saveUserBtn = document.getElementById("saveUserBtn");
    const saveUserIcon = document.getElementById("saveUserIcon");

    const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,}$/;

    // Live Password Validation (Only triggers if they type something)
    passwordInput.addEventListener("input", function() {
        const passwordValue = passwordInput.value;

        if (passwordValue.length === 0) {
            passwordInput.style.borderColor = "#E2E8F0";
            helpText.innerHTML = '<i class="fas fa-info-circle me-1"></i> Type a new password only if you want to change it.';
            helpText.style.color = "#64748B";
            return;
        }

        if (strongPasswordRegex.test(passwordValue)) {
            passwordInput.style.borderColor = "#10B981"; 
            helpText.innerHTML = '<i class="fas fa-check-circle me-1"></i> Strong password!';
            helpText.style.color = "#10B981";
        } else {
            passwordInput.style.borderColor = "#EF4444"; 
            helpText.innerHTML = '<i class="fas fa-times-circle me-1"></i> Must be at least 8 characters, with 1 uppercase, 1 lowercase, and 1 number.';
            helpText.style.color = "#EF4444";
        }
    });

    // Form Submission Check
    form.addEventListener("submit", function(event) {
        // I-check lang ang password length kung hindi ito blanko
        if (passwordInput.value.length > 0 && !strongPasswordRegex.test(passwordInput.value)) {
            event.preventDefault(); 
            passwordInput.style.borderColor = "#EF4444";
            helpText.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Cannot save! Please fix the new password first.';
            helpText.style.color = "#EF4444";
            passwordInput.focus();
        } else {
            saveUserBtn.classList.add("disabled");
            saveUserIcon.classList.remove("fa-save");
            saveUserIcon.classList.add("fa-spinner", "fa-spin");
            saveUserBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>