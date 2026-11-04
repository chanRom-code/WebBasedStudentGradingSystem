<?php
session_start();

/** @var mysqli $conn */
include 'Includes/config.php';

if (isset($_SESSION['user_id'])) {
    $u_id = (int)$_SESSION['user_id'];

    // I-log ang logout
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : 'Unknown';
    $action = "Logged out from the system";
    $page = basename($_SERVER['PHP_SELF']);
    
    // Strict-mode DB Insert gamit ang Prepared Statements
    $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, action, page_visited, ip_address) VALUES (?, ?, ?, ?)");
    if ($log_stmt) {
        mysqli_stmt_bind_param($log_stmt, "isss", $u_id, $action, $page, $ip);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    }

    // I-set ang activity sa 10 minutes ago para mag-Gray (Inactive) agad sa dashboard
    $inactive_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $update_stmt = mysqli_prepare($conn, "UPDATE users SET last_activity = ? WHERE user_id = ?");
    if ($update_stmt) {
        mysqli_stmt_bind_param($update_stmt, "si", $inactive_time, $u_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy session cookies
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Tuluyang sirain ang session
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging out... | SFICS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { 
            background-color: #F4F7FE; 
            font-family: 'Inter', sans-serif; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        /* Para bilog at maganda yung SweetAlert na lalabas */
        .rounded-20 { border-radius: 20px !important; }
    </style>
</head>
<body>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: 'success',
            title: 'Logged Out',
            text: 'You have been securely logged out from the system.',
            showConfirmButton: false,
            timer: 2000,
            allowOutsideClick: false,
            background: '#ffffff',
            iconColor: '#198754',
            customClass: { popup: 'rounded-20' }
        }).then(() => {
            // Dito na siya ididiretso pabalik sa login page pagkatapos ng animation
            window.location.href = 'login.php';
        });
    });
</script>

</body>
</html>