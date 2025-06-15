<?php
session_start();

// Redirect to login page if user is not logged in
if (!isset($_SESSION['userID'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
include 'db_connection.php';

// Fetch user fullName
$userID = $_SESSION['userID'];
$sql = "SELECT fullName FROM users WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $fullName = $row['fullName'];
} else {
    $fullName = "Admin"; 
}

$stmt->close();

// Fetch total users
$sql_users = "SELECT COUNT(*) AS totalUsers FROM users";
$result_users = $conn->query($sql_users);
$row_users = $result_users->fetch_assoc();
$totalUsers = $row_users['totalUsers'];

// Fetch total logs (combined admin and inventory logs)
$sql_logs = "SELECT 
            (SELECT COUNT(*) FROM admin_logs) + 
            (SELECT COUNT(*) FROM inventory_logs) AS totalLogs";
$result_logs = $conn->query($sql_logs);
$row_logs = $result_logs->fetch_assoc();
$totalLogs = $row_logs['totalLogs'];

// Get top actions data
$top_actions_query = "SELECT action, COUNT(*) as count FROM (
                        SELECT action FROM admin_logs
                        UNION ALL
                        SELECT action FROM inventory_logs
                     ) as combined_logs 
                     GROUP BY action 
                     ORDER BY count DESC 
                     LIMIT 5";
$top_actions_result = $conn->query($top_actions_query);

// Get summary statistics for additional cards
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM admin_logs WHERE action = 'login') as total_logins,
                (SELECT COUNT(*) FROM users WHERE role = 'Administrator') as total_admins,
                (SELECT COUNT(*) FROM users WHERE role = 'Inventory Manager') as total_managers,
                (SELECT COUNT(*) FROM users WHERE role = 'Bakery Staff') as total_staff";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Prepare data arrays for chart
$action_names = [];
$action_counts = [];
$chart_colors = ["#4e73df", "#1cc88a", "#36b9cc", "#f6c23e", "#e74a3b", "#fd7e14", "#6f42c1"];

// Get top actions for chart
if ($top_actions_result && $top_actions_result->num_rows > 0) {
    $i = 0;
    while ($row = $top_actions_result->fetch_assoc()) {
        $action_names[] = ucwords(str_replace('_', ' ', $row['action']));
        $action_counts[] = (int)$row['count'];
        $i++;
    }
} else {
    // Default data
    $action_names = ["Login", "Password Change", "User Update", "Logout", "User Addition"];
    $action_counts = [5, 4, 3, 2, 1];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        .chart-container {
            position: relative; 
            height: 350px;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <div class="sidebar-container">
        <div class="header-section">
            <div class="company-logo">
                <img src="image/icon/logo.png" class="logo-icon" alt="Company Logo">
                <div class="company-text">
                    <span class="company-name">RotiSeri</span>
                    <span class="company-name2">Admin</span>
                </div>
            </div>
            <nav class="nav-container">
                <a href="admin_dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <div class="nav-text">Home</div>
                </a>
                <a href="admin_usermanagement.php" class="nav-item">
                    <i class="fa fa-user nav-icon"></i>
                    <div class="nav-text">User Management</div>
                </a>
                <a href="admin_logsdisplay.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <div class="nav-text">Logs</div>
                </a>
                <a href="admin_passmanagement.php" class="nav-item">
                    <i class="fas fa-key"></i>
                    <div class="nav-text">Password Management</div>
                </a>
                <a href="admin_profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <div class="nav-text">My Profile</div>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <div class="nav-text">Log Out</div>
                </a>
            </nav>
        </div>
        <div class="footer-section"></div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($fullName); ?>!</h1>
        </div>

        <div class="dashboard-container">
            <div class="dashboard-card">
                <i class="fas fa-users fa-2x"></i>
                <h3>Total Users</h3>
                <p id="totalUsers"><?php echo $totalUsers; ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-file-alt fa-2x"></i>
                <h3>Total Logs</h3>
                <p id="totalLogs"><?php echo $totalLogs; ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-sign-in-alt fa-2x"></i>
                <h3>Total Logins</h3>
                <p><?php echo $stats['total_logins']; ?></p>
            </div>
        </div>

        <div class="dashboard-charts">
            <h3><i class="fas fa-chart-pie"></i> Top System Actions</h3>
            <div class="chart-container">
                <canvas id="actionsChart"></canvas>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart data from PHP
        const actionLabels = <?php echo json_encode($action_names); ?>;
        const actionData = <?php echo json_encode($action_counts); ?>;
        const chartColors = <?php echo json_encode($chart_colors); ?>;
        
        console.log('Labels:', actionLabels);
        console.log('Data:', actionData);
        
        // Get the chart canvas
        const ctx = document.getElementById('actionsChart').getContext('2d');
        
        // Create the chart
        const actionsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: actionLabels,
                datasets: [{
                    data: actionData,
                    backgroundColor: chartColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, data) => acc + data, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    });
    </script>
</body>
</html>