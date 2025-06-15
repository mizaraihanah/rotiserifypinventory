<?php
session_start();

// Include database connection
include('db_connection.php');

// Redirect to login page if user is not logged in or not an Inventory Manager
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Inventory Manager') {
    header("Location: index.php");
    exit();
}

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
    $fullName = "Inventory Manager";
}

$stmt->close();

// Get inventory statistics
// 1. Total products count
$productsSql = "SELECT COUNT(*) as total FROM products";
$productsResult = $conn->query($productsSql);
$productsRow = $productsResult->fetch_assoc();
$totalProducts = $productsRow['total'];

// 2. Low stock items count
$lowStockSql = "SELECT COUNT(*) as total FROM products WHERE stock_quantity <= reorder_threshold";
$lowStockResult = $conn->query($lowStockSql);
$lowStockRow = $lowStockResult->fetch_assoc();
$lowStockCount = $lowStockRow['total'];

// 3. Categories count
$categoriesSql = "SELECT COUNT(*) as total FROM product_categories";
$categoriesResult = $conn->query($categoriesSql);
$categoriesRow = $categoriesResult->fetch_assoc();
$totalCategories = $categoriesRow['total'];

// 4. Recent orders
$recentOrdersSql = "SELECT COUNT(*) as total FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$recentOrdersResult = $conn->query($recentOrdersSql);
$recentOrdersRow = $recentOrdersResult->fetch_assoc();
$recentOrders = $recentOrdersRow['total'];

// 5. Get category distribution data for pie chart
$categoryDistSql = "SELECT c.category_name, COUNT(p.product_id) as product_count 
                    FROM product_categories c 
                    LEFT JOIN products p ON c.category_id = p.category_id 
                    GROUP BY c.category_id 
                    ORDER BY product_count DESC";
$categoryDistResult = $conn->query($categoryDistSql);
$categoryLabels = [];
$categoryData = [];
while ($row = $categoryDistResult->fetch_assoc()) {
    $categoryLabels[] = $row['category_name'];
    $categoryData[] = $row['product_count'];
}

// 6. Get top 5 products with lowest stock relative to threshold
$criticalStockSql = "SELECT product_name, stock_quantity, reorder_threshold, 
                     (stock_quantity - reorder_threshold) as stock_margin 
                     FROM products 
                     ORDER BY stock_margin ASC 
                     LIMIT 5";
$criticalStockResult = $conn->query($criticalStockSql);
$criticalStockProducts = [];
while ($row = $criticalStockResult->fetch_assoc()) {
    $criticalStockProducts[] = $row;
}

// 7. Get recent inventory activities
$recentActivitySql = "SELECT l.action, l.action_details, l.timestamp, u.fullName 
                       FROM inventory_logs l
                       JOIN users u ON l.user_id = u.userID
                       ORDER BY l.timestamp DESC
                       LIMIT 5";
$recentActivityResult = $conn->query($recentActivitySql);
$recentActivities = [];
while ($row = $recentActivityResult->fetch_assoc()) {
    $recentActivities[] = $row;
}

// 8. Get inventory value
$inventoryValueSql = "SELECT SUM(stock_quantity * unit_price) as total_value FROM products";
$inventoryValueResult = $conn->query($inventoryValueSql);
$inventoryValueRow = $inventoryValueResult->fetch_assoc();
$inventoryValue = number_format($inventoryValueRow['total_value'], 2);

// 9. Get monthly purchase and sales data for chart
$monthlySalesSql = "SELECT DATE_FORMAT(order_date, '%b') as month, 
                    SUM(IF(order_type='Sales', total_amount, 0)) as sales_amount,
                    SUM(IF(order_type='Purchase', total_amount, 0)) as purchase_amount
                    FROM orders 
                    WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    GROUP BY MONTH(order_date)
                    ORDER BY order_date";
$monthlySalesResult = $conn->query($monthlySalesSql);
$monthLabels = [];
$salesData = [];
$purchaseData = [];
while ($row = $monthlySalesResult->fetch_assoc()) {
    $monthLabels[] = $row['month'];
    $salesData[] = $row['sales_amount'];
    $purchaseData[] = $row['purchase_amount'];
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager Dashboard</title>
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Additional Dashboard Styles */
        .dashboard-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding: 20px;
            flex: 1;
            min-width: 200px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: #f0f4ff;
            border-radius: 50%;
            color: #0561FC;
            font-size: 24px;
        }
        
        .stat-info h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #666;
        }
        
        .stat-info p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .dashboard-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            padding: 20px;
            flex: 1;
            min-width: 300px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .alert-badge {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            border-radius: 50px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .inventory-table th, .inventory-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .inventory-table th {
            font-weight: 600;
            color: #555;
        }
        
        .activity-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9f7fe;
            color: #0561FC;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
        }
        
        .activity-info p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .activity-time {
            font-size: 12px;
            color: #999;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .text-warning {
            color: #ffc107;
        }
        
        .text-success {
            color: #28a745;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-button {
            padding: 10px 15px;
            background: #0561FC;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-button:hover {
            background: #0450c1;
        }
        
        @media (max-width: 992px) {
            .dashboard-row {
                flex-direction: column;
            }
            
            .dashboard-card {
                min-width: 100%;
            }
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
                    <span class="company-name2">InventoryManager</span>
                </div>
            </div>

            <nav class="nav-container" role="navigation">
                <a href="imanager_dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <div class="nav-text">Home</div>
                </a>
                <a href="imanager_invmanagement.php" class="nav-item">
                    <i class="fas fa-boxes"></i>
                    <div class="nav-text">Manage Inventory</div>
                </a>
                <a href="imanager_supplierpurchase.php" class="nav-item">
                    <i class="fas fa-truck-loading"></i>
                    <div class="nav-text">View Supplier Purchases</div>
                </a>
                <a href="imanager_salesreport.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <div class="nav-text">Sales Report</div>
                </a>
                <a href="imanager_profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <div class="nav-text">My Profile</div>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <div class="nav-text">Log Out</div>
                </a>
            </nav>
        </div>

        <div class="footer-section"></div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Inventory Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($fullName); ?>!</p>
        </div>

        <!-- Key Metrics -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Products</h3>
                    <p><?php echo $totalProducts; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3>Low Stock Items</h3>
                    <p class="<?php echo ($lowStockCount > 0) ? 'text-warning' : ''; ?>">
                        <?php echo $lowStockCount; ?>
                    </p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-info">
                    <h3>Categories</h3>
                    <p><?php echo $totalCategories; ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>Inventory Value</h3>
                    <p>RM <?php echo $inventoryValue; ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="imanager_invmanagement.php" class="action-button">
                <i class="fas fa-plus"></i> Add Product
            </a>
            <a href="imanager_invmanagement.php?tab=orders" class="action-button">
                <i class="fas fa-shopping-cart"></i> Create Order
            </a>
            <a href="imanager_exportreport.php" class="action-button">
                <i class="fas fa-file-export"></i> Export Report
            </a>
        </div>

        <!-- Charts and Tables Row -->
        <div class="dashboard-row">
            <!-- Monthly Sales/Purchase Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Monthly Sales & Purchases</h2>
                </div>
                <div class="chart-container">
                    <canvas id="salesPurchaseChart"></canvas>
                </div>
            </div>
            
            <!-- Category Distribution -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Category Distribution</h2>
                </div>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Stock Status and Activity Row -->
        <div class="dashboard-row">
            <!-- Critical Stock Table -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-exclamation-circle"></i> Critical Stock Items</h2>
                    <?php if (count($criticalStockProducts) > 0): ?>
                    <span class="alert-badge">Needs Attention</span>
                    <?php endif; ?>
                </div>
                
                <table class="inventory-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Current Stock</th>
                            <th>Threshold</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($criticalStockProducts)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No critical stock items</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($criticalStockProducts as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo $product['stock_quantity']; ?></td>
                                    <td><?php echo $product['reorder_threshold']; ?></td>
                                    <td>
                                        <?php if ($product['stock_quantity'] == 0): ?>
                                            <span class="text-danger">Out of Stock</span>
                                        <?php elseif ($product['stock_quantity'] <= $product['reorder_threshold']): ?>
                                            <span class="text-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="text-success">Good</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div style="text-align: right; margin-top: 15px;">
                    <a href="imanager_invmanagement.php?tab=stock-levels" style="color: #0561FC; text-decoration: none; font-size: 14px;">
                        View All Stock Levels <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Activities</h2>
                </div>
                
                <?php if (empty($recentActivities)): ?>
                    <p style="text-align: center;">No recent activities</p>
                <?php else: ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php 
                                $icon = 'fas fa-sync-alt'; // default icon
                                
                                if (strpos($activity['action'], 'add') !== false) {
                                    $icon = 'fas fa-plus';
                                } elseif (strpos($activity['action'], 'update') !== false) {
                                    $icon = 'fas fa-edit';
                                } elseif (strpos($activity['action'], 'delete') !== false) {
                                    $icon = 'fas fa-trash';
                                } elseif (strpos($activity['action'], 'stock') !== false) {
                                    $icon = 'fas fa-cubes';
                                } elseif (strpos($activity['action'], 'order') !== false) {
                                    $icon = 'fas fa-shopping-cart';
                                }
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-info">
                                <h4><?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?></h4>
                                <p><?php echo htmlspecialchars($activity['action_details']); ?></p>
                                <span class="activity-time">
                                    <?php echo date('M d, Y h:i A', strtotime($activity['timestamp'])); ?> 
                                    by <?php echo htmlspecialchars($activity['fullName']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div style="text-align: right; margin-top: 15px;">
                    <a href="imanager_invmanagement.php?tab=logs" style="color: #0561FC; text-decoration: none; font-size: 14px;">
                        View All Activities <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart.js implementation
        document.addEventListener('DOMContentLoaded', function() {
            // Sales and Purchase Chart
            const salesPurchaseCtx = document.getElementById('salesPurchaseChart').getContext('2d');
            new Chart(salesPurchaseCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($monthLabels); ?>,
                    datasets: [
                        {
                            label: 'Sales (RM)',
                            data: <?php echo json_encode($salesData); ?>,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Purchases (RM)',
                            data: <?php echo json_encode($purchaseData); ?>,
                            backgroundColor: 'rgba(5, 97, 252, 0.7)',
                            borderColor: 'rgba(5, 97, 252, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'RM ' + value;
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': RM ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            });
            
            // Category Distribution Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($categoryLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($categoryData); ?>,
                        backgroundColor: [
                            'rgba(5, 97, 252, 0.7)',
                            'rgba(220, 53, 69, 0.7)',
                            'rgba(40, 167, 69, 0.7)',
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(111, 66, 193, 0.7)',
                            'rgba(23, 162, 184, 0.7)'
                        ],
                        borderColor: [
                            'rgba(5, 97, 252, 1)',
                            'rgba(220, 53, 69, 1)',
                            'rgba(40, 167, 69, 1)',
                            'rgba(255, 193, 7, 1)',
                            'rgba(111, 66, 193, 1)',
                            'rgba(23, 162, 184, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' products';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>