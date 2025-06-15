<?php
session_start();

// Check if user is logged in
if (isset($_SESSION['userID'])) {
    // Include database connection
    include 'db_connection.php';
    
    // Log the logout action
    $userID = $_SESSION['userID'];
    $action = "logout";
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    $logSQL = "INSERT INTO admin_logs (admin_id, action, action_details, ip_address) 
              VALUES (?, ?, 'User logged out', ?)";
    $stmt = $conn->prepare($logSQL);
    $stmt->bind_param("sss", $userID, $action, $ipAddress);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    // Destroy the session
    session_destroy();
}

// Redirect to login page
header("Location: index.php");
exit();
?>