<?php
session_start();

// Redirect to login page if user is not logged in
if (!isset($_SESSION['userID'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
include 'db_connection.php';

// Fetch user details
$userID = $_SESSION['userID'];
$sql = "SELECT fullName, email, userID, role, phoneNumber, address FROM users WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $fullName = $row['fullName'];
    $email = $row['email'];
    $userID = $row['userID'];
    $role = $row['role'];
    $phoneNumber = $row['phoneNumber'];
    $address = $row['address'];
} else {
    // Redirect if no user is found
    header("Location: index.php");
    exit();
}

// Add this code to get the low stock count - with error handling
$lowStockQuery = "SELECT 
                   SUM(CASE WHEN stock_quantity <= reorder_threshold AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
                   SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count
                FROM products";
$stockResult = $conn->query($lowStockQuery);

// Check if query was successful
if ($stockResult !== false) {
    $stockCounts = $stockResult->fetch_assoc();
    $lowStockCount = $stockCounts['low_stock_count'];
    $outOfStockCount = $stockCounts['out_of_stock_count'];
} else {
    // Query failed - handle the error
    error_log("Database error: " . $conn->error);
    $lowStockCount = 0;
    $outOfStockCount = 0;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>

<!-- Sidebar -->
<div class="sidebar-container">
    <div class="header-section">
        <div class="company-logo">
            <img src="image/icon/logo.png" class="logo-icon" alt="Company Logo">
            <div class="company-text">
                <span class="company-name">RotiSeri</span>
                <span class="company-name2">InventoryManager</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="staff_dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="staff_manageinventory.php" class="nav-item">
                <i class="fas fa-boxes"></i>
                <span>Manage Inventory</span>
            </a>
            <a href="staff_suppliers.php" class="nav-item">
                <i class="fas fa-truck"></i>
                <span>Manage Suppliers</span>
            </a>
            <a href="staff_alerts.php" class="nav-item">
                <i class="fas fa-bell"></i>
                <span>Alerts</span>
                <?php if ($lowStockCount + $outOfStockCount > 0): ?>
<span class="badge"><?php echo $lowStockCount + $outOfStockCount; ?></span>
<?php endif; ?>
            </a>
            <a href="staff_profile.php" class="nav-item">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="dashboard-header">
        <h1>My Profile</h1>
    </div>

    <div class="profile-container">
        <table class="profile-table">
            <tr>
                <td><strong>Full Name</strong></td>
                <td><?php echo htmlspecialchars($fullName); ?></td>
            </tr>
            <tr>
                <td><strong>Email</strong></td>
                <td><?php echo htmlspecialchars($email); ?></td>
            </tr>
            <tr>
                <td><strong>User ID</strong></td>
                <td><?php echo htmlspecialchars($userID); ?></td>
            </tr>
            <tr>
                <td><strong>Role</strong></td>
                <td><?php echo htmlspecialchars($role); ?></td>
            </tr>
            <tr>
                <td><strong>Phone Number</strong></td>
                <td><?php echo htmlspecialchars($phoneNumber); ?></td>
            </tr>
            <tr>
                <td><strong>Address</strong></td>
                <td><?php echo htmlspecialchars($address); ?></td>
            </tr>
        </table>
    </div>
</div>

</body>
</html>
