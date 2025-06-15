<?php
session_start();

// Redirect to login page if user is not logged in
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Bakery Staff') {
    header("Location: index.php");
    exit();
}

// Include database connection
include 'db_connection.php';

// Fetch user fullName for the welcome message
$userID = $_SESSION['userID'];
$sql = "SELECT fullName FROM users WHERE userID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userID);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $fullName = $row['fullName'];
} else {
    $fullName = "Staff"; 
}

$stmt->close();

// Fetch inventory stats
$sql_products = "SELECT COUNT(*) AS total_products FROM products";
$result_products = $conn->query($sql_products);
$row_products = $result_products->fetch_assoc();
$totalProducts = $row_products['total_products'];

// Get count of products below threshold (low stock)
$threshold_sql = "SELECT COUNT(*) as low_stock_count FROM products WHERE stock_quantity <= reorder_threshold";
$threshold_result = $conn->query($threshold_sql);
$threshold_row = $threshold_result->fetch_assoc();
$low_stock_count = $threshold_row['low_stock_count'];

// Get count of out of stock products
$outofstock_sql = "SELECT COUNT(*) as outofstock_count FROM products WHERE stock_quantity = 0";
$outofstock_result = $conn->query($outofstock_sql);
$outofstock_row = $outofstock_result->fetch_assoc();
$outofstock_count = $outofstock_row['outofstock_count'];

// Get count of suppliers
$supplier_sql = "SELECT COUNT(DISTINCT customer_name) as supplier_count FROM orders WHERE order_type = 'Purchase'";
$supplier_result = $conn->query($supplier_sql);
$supplier_row = $supplier_result->fetch_assoc();
$supplier_count = $supplier_row['supplier_count'] ?? 0;

// Fetch recent activity for the current staff member (last 5 activities)
$recent_activity_sql = "
    SELECT 
        action,
        item_id,
        action_details,
        timestamp,
        CASE 
            WHEN action = 'add_product' THEN 'fas fa-plus-circle'
            WHEN action = 'update_product' THEN 'fas fa-edit'
            WHEN action = 'delete_product' THEN 'fas fa-trash'
            WHEN action = 'stock_update' THEN 'fas fa-boxes'
            WHEN action = 'create_order' THEN 'fas fa-shopping-cart'
            WHEN action = 'update_order' THEN 'fas fa-clipboard-check'
            WHEN action = 'delete_supplier' THEN 'fas fa-truck'
            ELSE 'fas fa-info-circle'
        END as icon_class,
        CASE 
            WHEN action = 'add_product' THEN '#4CAF50'
            WHEN action = 'update_product' THEN '#2196F3'
            WHEN action = 'delete_product' THEN '#F44336'
            WHEN action = 'stock_update' THEN '#FF9800'
            WHEN action = 'create_order' THEN '#9C27B0'
            WHEN action = 'update_order' THEN '#607D8B'
            WHEN action = 'delete_supplier' THEN '#795548'
            ELSE '#757575'
        END as icon_color
    FROM inventory_logs 
    WHERE user_id = ? 
    ORDER BY timestamp DESC 
    LIMIT 5
";

$stmt = $conn->prepare($recent_activity_sql);
$stmt->bind_param("s", $userID);
$stmt->execute();
$recent_activities = $stmt->get_result();
$stmt->close();

// Fetch low stock items (items where stock_quantity <= reorder_threshold)
$low_stock_sql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.stock_quantity,
        p.reorder_threshold,
        c.category_name,
        CASE 
            WHEN p.stock_quantity = 0 THEN 'Out of Stock'
            WHEN p.stock_quantity <= p.reorder_threshold THEN 'Low Stock'
            ELSE 'Normal'
        END as status
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.category_id
    WHERE p.stock_quantity <= p.reorder_threshold
    ORDER BY p.stock_quantity ASC, p.product_name ASC
    LIMIT 10
";

$low_stock_items = $conn->query($low_stock_sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Roti Seri Bakery</title>
    <link rel="stylesheet" href="staff_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="image/icon/logo.png" alt="Roti Seri Logo" class="logo">
                <div class="company-text">
                    <span class="company-name">RotiSeri</span>
                    <span class="company-role">Staff</span>
                </div>
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
                <?php if ($low_stock_count > 0): ?>
                <span class="badge"><?php echo $low_stock_count; ?></span>
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

    <div class="main-content">
        <div class="header">
            <div class="welcome-container">
                <h1>Welcome, <?php echo htmlspecialchars($fullName); ?>!</h1>
                <p>Today is <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                <span class="user-role">Bakery Staff</span>
            </div>
        </div>

        <div class="dashboard-stats">
            
            <div class="stat-card alert">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3>Low Stock Items</h3>
                    <p><?php echo $low_stock_count; ?></p>
                </div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Out of Stock</h3>
                    <p><?php echo $outofstock_count; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-info">
                    <h3>Suppliers</h3>
                    <p><?php echo $supplier_count; ?></p>
                </div>
            </div>
        </div>

        <div class="dashboard-widgets">
            <div class="widget">
                <div class="widget-header">
                    <h3>Recent Activity</h3>
                </div>
                <div class="widget-content recent-activity">
                    <?php if ($recent_activities->num_rows > 0): ?>
                        <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background-color: <?php echo $activity['icon_color']; ?>20; color: <?php echo $activity['icon_color']; ?>;">
                                    <i class="<?php echo $activity['icon_class']; ?>"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-title">
                                        <?php 
                                        // Format action details for better readability
                                        $action_text = '';
                                        switch($activity['action']) {
                                            case 'add_product':
                                                $action_text = 'Added new product';
                                                break;
                                            case 'update_product':
                                                $action_text = 'Updated product';
                                                break;
                                            case 'delete_product':
                                                $action_text = 'Deleted product';
                                                break;
                                            case 'stock_update':
                                                $action_text = 'Updated stock for';
                                                break;
                                            case 'create_order':
                                                $action_text = 'Created order';
                                                break;
                                            case 'update_order':
                                                $action_text = 'Updated order';
                                                break;
                                            case 'delete_supplier':
                                                $action_text = 'Deleted supplier';
                                                break;
                                            default:
                                                $action_text = ucfirst(str_replace('_', ' ', $activity['action']));
                                        }
                                        echo htmlspecialchars($action_text . ' ' . $activity['item_id']);
                                        ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php 
                                        $time_diff = time() - strtotime($activity['timestamp']);
                                        if ($time_diff < 60) {
                                            echo "Just now";
                                        } elseif ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . " minutes ago";
                                        } elseif ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . " hours ago";
                                        } else {
                                            echo date('M j, Y g:i A', strtotime($activity['timestamp']));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="no-data-message">No recent activity to display.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="widget">
                <div class="widget-header">
                    <h3>Stock Overview</h3>
                </div>
                <div class="widget-content">
                    <canvas id="stockChart"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-widgets">
            <div class="widget full-width">
                <div class="widget-header">
                    <h3>Low Stock Items</h3>
                    <a href="staff_alerts.php" class="view-all">View All</a>
                </div>
                <div class="widget-content">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Threshold</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($low_stock_items->num_rows > 0): ?>
                                <?php while ($item = $low_stock_items->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_id']); ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $item['stock_quantity']; ?></td>
                                        <td><?php echo $item['reorder_threshold']; ?></td>
                                        <td>
                                            <span class="status <?php echo $item['status'] == 'Out of Stock' ? 'status-out' : 'status-low'; ?>">
                                                <?php echo $item['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn" onclick="window.location.href='staff_manageinventory.php?edit=<?php echo $item['product_id']; ?>'">
                                                <i class="fas fa-edit"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-data-message">No low stock items to display.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart data with actual database values
        const ctx = document.getElementById('stockChart').getContext('2d');
        const stockChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Normal Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [<?php echo $totalProducts - $low_stock_count; ?>, 
                          <?php echo $low_stock_count - $outofstock_count; ?>, 
                          <?php echo $outofstock_count; ?>],
                    backgroundColor: ['#4CAF50', '#FFC107', '#F44336'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    title: {
                        display: true,
                        text: 'Inventory Status Distribution'
                    }
                }
            }
        });
    </script>
</body>

</html>