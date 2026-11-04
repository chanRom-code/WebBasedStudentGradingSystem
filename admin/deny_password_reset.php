<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $request_id = (int)$_POST['id'];

    // Burahin lang yung pending request, walang gagalawin sa password
    $stmt = mysqli_prepare($conn, "DELETE FROM password_resets WHERE id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header('Location: request_forgot_pass.php?msg=denied');
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

// Kapag may error
header('Location: request_forgot_pass.php?msg=error');
exit;
?>