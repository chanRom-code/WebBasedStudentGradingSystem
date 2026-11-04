<?php
session_start();

/** @var mysqli $conn */
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$full_name_session = isset($_SESSION['full_name']) ? htmlspecialchars((string)$_SESSION['full_name'], ENT_QUOTES) : 'Administrator';
$current_page = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = '';

$full_name = '';
$email = '';
$gender = '';
$phone = '';
$address = '';
$role = 'professor';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    
    // Strict fetching para walang red lines sa IDE
    $full_name = isset($_POST['full_name']) ? trim((string)$_POST['full_name']) : '';
    $email     = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
    $gender    = isset($_POST['gender']) ? trim((string)$_POST['gender']) : '';
    $phone     = isset($_POST['phone']) ? trim((string)$_POST['phone']) : '';
    $address   = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
    $password  = isset($_POST['password']) ? trim((string)$_POST['password']) : '';
    $role      = isset($_POST['role']) ? trim((string)$_POST['role']) : 'professor';

    // REGEX FOR STRONG PASSWORD (Backend Validation)
    $strongPasswordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,}$/';

    if ($full_name === '' || $email === '' || $gender === '' || $password === '' || $role === '') {
        $message = 'Full Name, Email, Gender, Password, and Role are required.';
        $message_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'danger';
    } elseif (!preg_match($strongPasswordRegex, $password)) {
        // I-BLOCK SA PHP KUNG WEAK ANG PASSWORD
        $message = 'Password must be at least 8 characters, with 1 uppercase, 1 lowercase, and 1 number.';
        $message_type = 'danger';
    } elseif (!in_array($role, ['admin', 'professor'], true)) {
        $message = 'Invalid role selected.';
        $message_type = 'danger';
    } else {
        // Check if email already exists
        $check_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");

        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "s", $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);

            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $message = 'Email already exists in the system.';
                $message_type = 'warning';
            } else {
                // Password hashing
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // DITO BINAGO: Isinama natin ang require_password_change = 1
                $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, gender, phone, address, password, role, require_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");

                if ($insert_stmt) {
                    mysqli_stmt_bind_param($insert_stmt, "sssssss", $full_name, $email, $gender, $phone, $address, $hashed_password, $role);

                    if (mysqli_stmt_execute($insert_stmt)) {
                        mysqli_stmt_close($insert_stmt);
                        mysqli_stmt_close($check_stmt);
                        header("Location: users.php?msg=added");
                        exit;
                    } else {
                        $message = 'Failed to save user.';
                        $message_type = 'danger';
                    }
                } else {
                    $message = 'Failed to prepare insert query.';
                    $message_type = 'danger';
                }
            }

            mysqli_stmt_close($check_stmt);
        } else {
            $message = 'Failed to prepare email check query.';
            $message_type = 'danger';
        }
    }
}

include '../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                        Home <span class="mx-1">></span> User Management <span class="mx-1">></span> <strong class="text-dark">Add User</strong>
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
                        <h3 class="fw-bold mb-1" style="color: #0F172A;">Add New User</h3>
                        <p class="text-muted mb-0" style="font-size: 14px;">Create a new administrator or professor account.</p>
                    </div>
                    <a href="users.php" class="btn-cancel shadow-sm"><i class="fas fa-arrow-left me-2"></i> Back to Users</a>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES); ?> alert-custom shadow-sm border-0 border-start border-4 border-<?= htmlspecialchars($message_type, ENT_QUOTES); ?>">
                        <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> me-2"></i>
                        <?= htmlspecialchars($message, ENT_QUOTES); ?>
                    </div>
                <?php endif; ?>

                <div class="saas-card">
                    <h6 class="fw-bold text-dark mb-4"><i class="fas fa-user-plus text-primary me-2"></i> Account Details</h6>
                    
                    <form method="POST" id="addUserForm">
                        <div class="row g-4">
                            
                            <div class="col-md-4">
                                <label class="form-label-custom">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" id="fullNameInput" class="form-control-custom w-100" value="<?= htmlspecialchars($full_name, ENT_QUOTES); ?>" placeholder="e.g. Juan Dela Cruz" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control-custom w-100" value="<?= htmlspecialchars($email, ENT_QUOTES); ?>" placeholder="e.g. juan@sfics.edu.ph" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label-custom">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select-custom w-100" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male" <?= $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?= $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?= $gender === 'Other' ? 'selected' : ''; ?>>Other / Prefer not to say</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">Phone Number <span class="text-muted text-lowercase" style="font-size: 10px; font-weight: 400;">(Optional)</span></label>
                                <input type="text" name="phone" class="form-control-custom w-100" value="<?= htmlspecialchars($phone, ENT_QUOTES); ?>" placeholder="e.g. 09123456789">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">Home Address <span class="text-muted text-lowercase" style="font-size: 10px; font-weight: 400;">(Optional)</span></label>
                                <input type="text" name="address" class="form-control-custom w-100" value="<?= htmlspecialchars($address, ENT_QUOTES); ?>" placeholder="City, Province">
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label-custom">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" id="password" class="form-control-custom w-100" placeholder="••••••••" required>
                                <div id="passwordHelp" class="mt-2" style="font-size: 12px; color: #64748B;">
                                    <i class="fas fa-info-circle me-1"></i> Use a strong password for better security.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label-custom">System Role <span class="text-danger">*</span></label>
                                <select name="role" id="roleInput" class="form-select-custom w-100" required>
                                    <option value="professor" <?= $role === 'professor' ? 'selected' : ''; ?>>Professor</option>
                                    <option value="admin" <?= $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4" style="border-color: #E2E8F0;">

                        <button type="submit" name="submit" id="hiddenSubmitBtn" style="display: none;"></button>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="users.php" class="btn-cancel">Cancel</a>
                            <button type="button" onclick="confirmSaveUser()" id="saveUserBtn" class="btn-save">
                                <i class="fas fa-save me-2" id="saveUserIcon"></i> Save User
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    // Regex para sa Strong Password
    const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,}$/;
    
    // VARIABLES PARA SA SWEETALERT
    const passwordInput = document.getElementById("password");
    const helpText = document.getElementById("passwordHelp");
    const saveUserBtn = document.getElementById("saveUserBtn");
    const saveUserIcon = document.getElementById("saveUserIcon");
    const form = document.getElementById("addUserForm");
    const hiddenSubmitBtn = document.getElementById("hiddenSubmitBtn");

document.addEventListener("DOMContentLoaded", function() {

    // Live Validation as they type
    if (passwordInput && helpText) {
        passwordInput.addEventListener("input", function() {
            const passwordValue = passwordInput.value;

            if (passwordValue.length === 0) {
                passwordInput.style.borderColor = "#E2E8F0";
                helpText.innerHTML = '<i class="fas fa-info-circle me-1"></i> Use a strong password for better security.';
                helpText.style.color = "#64748B";
                return;
            }

            if (strongPasswordRegex.test(passwordValue)) {
                passwordInput.style.borderColor = "#10B981"; // Success Green
                helpText.innerHTML = '<i class="fas fa-check-circle me-1"></i> Strong password!';
                helpText.style.color = "#10B981";
            } else {
                passwordInput.style.borderColor = "#EF4444"; // Danger Red
                helpText.innerHTML = '<i class="fas fa-times-circle me-1"></i> Must be at least 8 characters, with 1 uppercase, 1 lowercase, and 1 number.';
                helpText.style.color = "#EF4444";
            }
        });
    }
});

// --- BAGONG FIX: SweetAlert Confirmation BAGO mag-submit ---
function confirmSaveUser() {
    // 1. I-check muna kung tama at kumpleto lahat ng required fields bago magpakita ng popup
    if (!form.checkValidity()) {
        form.reportValidity(); // Papalabasin yung default error ng HTML5 (e.g. "Please fill out this field")
        return;
    }

    // 2. I-check kung malakas yung password
    if (!strongPasswordRegex.test(passwordInput.value)) {
        passwordInput.style.borderColor = "#EF4444";
        helpText.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i> Cannot save! Please fix your password first.';
        helpText.style.color = "#EF4444";
        passwordInput.focus();
        return;
    }

    // 3. Kung okay ang lahat, ipakita ang confirmation popup
    let enteredName = document.getElementById("fullNameInput").value;
    let selectedRole = document.getElementById("roleInput").value;
    // Pataasin ang unang letra ng role (Admin/Professor)
    let capitalizedRole = selectedRole.charAt(0).toUpperCase() + selectedRole.slice(1);

    Swal.fire({
        title: 'Save New User?',
        text: `Are you sure you want to create a new ${capitalizedRole} account for ${enteredName}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0047FF', 
        cancelButtonColor: '#94A3B8', 
        confirmButtonText: '<i class="fas fa-save me-1"></i> Yes, save new user',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        customClass: { popup: 'rounded-20' }
    }).then((result) => {
        if (result.isConfirmed) {
            // Loading effect
            saveUserBtn.classList.add("disabled");
            saveUserIcon.classList.remove("fa-save");
            saveUserIcon.classList.add("fa-spinner", "fa-spin");
            saveUserBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';

            Swal.fire({
                title: 'Saving...',
                text: 'Please wait.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            // I-trigger yung hidden submit button para tumuloy na sa PHP!
            hiddenSubmitBtn.click();
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>