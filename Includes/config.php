<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "grading_system_db";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}


function logActivity($conn, $user_id, $action) {
    // 1. Check muna kung talagang naka-login pa (Security check)
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $user_id) {
        return; // Huwag ituloy ang pag-log kung wala nang session
    }

    $page = basename($_SERVER['PHP_SELF']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $action = mysqli_real_escape_string($conn, $action);
    
    // 2. I-insert ang log
    $query = "INSERT INTO activity_logs (user_id, action, page_visited, ip_address) 
              VALUES ('$user_id', '$action', '$page', '$ip')";
    mysqli_query($conn, $query);

    // 3. I-update ang huling galaw
    mysqli_query($conn, "UPDATE users SET last_activity = CURRENT_TIMESTAMP WHERE user_id = '$user_id'");
}

?>