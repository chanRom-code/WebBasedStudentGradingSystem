<?php
include 'includes/config.php';

header('Content-Type: application/json');

if (isset($_GET['email'])) {
    $email = trim($_GET['email']);
    
    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        echo json_encode(['status' => 'success', 'email' => $email]);
    } else {
        echo json_encode(['status' => 'error']);
    }
    
    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['status' => 'error']);
}
?>