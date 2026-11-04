<?php
session_start();

// Ipinaalam sa IDE kung saan galing ang $conn
/** @var mysqli $conn */
require_once '../includes/config.php';

// Strict Auth check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$success = false;
$error_msg = "";
$temp_password = "";
$user_email = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['new_password'])) {
    
    // Strict fetching and casting
    $request_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $temp_password = isset($_POST['new_password']) ? trim((string)$_POST['new_password']) : '';
    
    if (empty($temp_password) || $request_id === 0) {
        header("Location: request_forgot_pass.php?msg=error");
        exit;
    }

    // 1. Kunin ang email mula sa request GAMIT ANG PREPARED STATEMENT
    $stmt = mysqli_prepare($conn, "SELECT email FROM password_resets WHERE id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $user_email = $row['email'];
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

            // 2. Update user password at i-force ang change GAMIT ANG PREPARED STATEMENT
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, require_password_change = 1 WHERE email = ?");
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $user_email);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // 3. I-update ang status ng request GAMIT ANG PREPARED STATEMENT
                    $status_stmt = mysqli_prepare($conn, "UPDATE password_resets SET status = 'approved' WHERE id = ?");
                    if ($status_stmt) {
                        mysqli_stmt_bind_param($status_stmt, "i", $request_id);
                        mysqli_stmt_execute($status_stmt);
                        mysqli_stmt_close($status_stmt);
                    }
                    $success = true;
                } else {
                    $error_msg = "Database update failed.";
                }
                mysqli_stmt_close($update_stmt);
            } else {
                $error_msg = "Failed to prepare user update query.";
            }
        } else {
            $error_msg = "Request record not found.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_msg = "Failed to prepare select query.";
    }
} else {
    header("Location: request_forgot_pass.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Success | SFICS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #F4F7FE; font-family: 'Inter', sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .process-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); text-align: center; max-width: 500px; width: 90%; }
        .temp-pass-box { background: #F8FAFC; border: 2px dashed #CBD5E1; padding: 15px; border-radius: 12px; margin: 20px 0; position: relative; }
        .temp-pass-text { font-family: 'Courier New', monospace; font-size: 24px; font-weight: 700; color: #0F172A; letter-spacing: 2px; }
        .btn-copy { background: #0047FF; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; transition: 0.2s; font-size: 15px; }
        .btn-copy:hover { background: #0036cc; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0, 71, 255, 0.2); }
        .back-link { color: #64748B; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.2s; }
        .back-link:hover { color: #0F172A; }
    </style>
</head>
<body>

<div class="process-card">
    <?php if ($success): ?>
        <div class="mb-4">
            <i class="fas fa-check-circle text-success" style="font-size: 65px;"></i>
        </div>
        <h4 class="fw-bold text-dark">Reset Approved!</h4>
        <p class="text-muted" style="font-size: 14px;">The password for <strong><?= htmlspecialchars($user_email, ENT_QUOTES) ?></strong> has been updated. Please provide the temporary password below to the user.</p>

        <div class="temp-pass-box">
            <div class="temp-pass-text" id="passText"><?= htmlspecialchars($temp_password, ENT_QUOTES) ?></div>
            <small class="text-muted text-uppercase fw-bold" style="font-size: 10px;">Temporary Password</small>
        </div>

        <button class="btn-copy w-100 mb-4" onclick="copyPassword()">
            <i class="far fa-copy me-2"></i> Copy Password
        </button>
        
        <a href="request_forgot_pass.php" class="back-link">
            <i class="fas fa-arrow-left me-1"></i> Back to Requests
        </a>

        <script>
            // Auto trigger SweetAlert on load
            Swal.fire({
                icon: 'success',
                title: 'Approved Successfully',
                text: 'The temporary password is ready.',
                timer: 2000,
                showConfirmButton: false
            });

            function copyPassword() {
                const pass = document.getElementById('passText').innerText;
                navigator.clipboard.writeText(pass).then(() => {
                    const btn = document.querySelector('.btn-copy');
                    btn.innerHTML = '<i class="fas fa-check me-2"></i> Copied!';
                    btn.style.background = '#10B981';
                    
                    setTimeout(() => {
                        btn.innerHTML = '<i class="far fa-copy me-2"></i> Copy Password';
                        btn.style.background = '#0047FF';
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            }
        </script>

    <?php else: ?>
        <div class="mb-4">
            <i class="fas fa-times-circle text-danger" style="font-size: 65px;"></i>
        </div>
        <h4 class="fw-bold text-dark">Process Error</h4>
        <p class="text-muted mb-4"><?= htmlspecialchars($error_msg, ENT_QUOTES) ?></p>
        <a href="request_forgot_pass.php" class="btn btn-secondary rounded-3 px-4 fw-bold">Go Back</a>
    <?php endif; ?>
</div>

</body>
</html>