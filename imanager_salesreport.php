<?php
session_start();

// Redirect to login page if user is not logged in or not an Inventory Manager
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Inventory Manager') {
    header("Location: index.php");
    exit();
}

// Include database connections
include 'db_connection.php'; // Your inventory system connection

// CONNECT TO REAL SALES DATABASE
$sales_conn = new mysqli("localhost", "root", "", "sales_db"); // Adjust credentials as needed
if ($sales_conn->connect_error) {
    die("Sales database connection failed: " . $sales_conn->connect_error);
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

// Set default filter values
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$productFilter = isset($_GET['product']) ? $_GET['product'] : '';
$paymentMethodFilter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// =============================================
// DAILY SALES SUMMARY - FROM REAL SALES DATA
// =============================================
$query = "SELECT 
            DATE(o.order_date) as sale_date,
            COUNT(DISTINCT o.id) as transactions,
            SUM(oi.quantity) as total_items,
            SUM(oi.subtotal) as total_sales,
            GROUP_CONCAT(DISTINCT o.payment_method) as payment_methods
          FROM orders o
          JOIN order_items oi ON o.id = oi.order_id
          WHERE o.status = 'completed' AND o.payment_status = 'paid'";

$params = [];
$types = "";

if (!empty($dateFrom)) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if (!empty($dateTo)) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

if (!empty($productFilter)) {
    $query .= " AND oi.product_id = ?";
    $params[] = $productFilter;
    $types .= "i";
}

if (!empty($paymentMethodFilter)) {
    $query .= " AND o.payment_method = ?";
    $params[] = $paymentMethodFilter;
    $types .= "s";
}

$query .= " GROUP BY DATE(o.order_date) ORDER BY sale_date DESC";

$stmt = $sales_conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// =============================================
// DETAILED SALES DATA - FROM REAL SALES DATA
// =============================================
$detailedQuery = "SELECT 
                    DATE(o.order_date) as sale_date,
                    inv.id as product_id,
                    inv.product_name,
                    SUM(oi.quantity) as quantity_sold,
                    AVG(oi.unit_price) as unit_price,
                    SUM(oi.subtotal) as total_amount,
                    o.payment_method
                  FROM orders o
                  JOIN order_items oi ON o.id = oi.order_id
                  JOIN inventory inv ON oi.product_id = inv.id
                  WHERE o.status = 'completed' AND o.payment_status = 'paid'";

$detailedParams = [];
$detailedTypes = "";

if (!empty($dateFrom)) {
    $detailedQuery .= " AND DATE(o.order_date) >= ?";
    $detailedParams[] = $dateFrom;
    $detailedTypes .= "s";
}

if (!empty($dateTo)) {
    $detailedQuery .= " AND DATE(o.order_date) <= ?";
    $detailedParams[] = $dateTo;
    $detailedTypes .= "s";
}

if (!empty($productFilter)) {
    $detailedQuery .= " AND oi.product_id = ?";
    $detailedParams[] = $productFilter;
    $detailedTypes .= "i";
}

if (!empty($paymentMethodFilter)) {
    $detailedQuery .= " AND o.payment_method = ?";
    $detailedParams[] = $paymentMethodFilter;
    $detailedTypes .= "s";
}

$detailedQuery .= " GROUP BY inv.id, DATE(o.order_date) 
                   ORDER BY sale_date DESC, total_amount DESC";

$detailedStmt = $sales_conn->prepare($detailedQuery);
if (!empty($detailedParams)) {
    $detailedStmt->bind_param($detailedTypes, ...$detailedParams);
}
$detailedStmt->execute();
$detailedResult = $detailedStmt->get_result();

// =============================================
// GET PRODUCTS FOR FILTER - FROM REAL SALES DATA
// =============================================
$productQuery = "SELECT id, product_name FROM inventory WHERE status = 'active' ORDER BY product_name";
$productResult = $sales_conn->query($productQuery);

// =============================================
// SUMMARY STATISTICS - FROM REAL SALES DATA
// =============================================
$summaryQuery = "SELECT 
                    COUNT(DISTINCT o.id) as total_transactions,
                    SUM(oi.quantity) as total_items_sold,
                    SUM(oi.subtotal) as total_revenue,
                    COUNT(DISTINCT DATE(o.order_date)) as total_days,
                    AVG(oi.subtotal) as avg_sale_amount,
                    MAX(oi.subtotal) as max_sale_amount,
                    COUNT(DISTINCT oi.product_id) as unique_products
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                WHERE o.status = 'completed' AND o.payment_status = 'paid'";

$summaryParams = [];
$summaryTypes = "";

if (!empty($dateFrom)) {
    $summaryQuery .= " AND DATE(o.order_date) >= ?";
    $summaryParams[] = $dateFrom;
    $summaryTypes .= "s";
}

if (!empty($dateTo)) {
    $summaryQuery .= " AND DATE(o.order_date) <= ?";
    $summaryParams[] = $dateTo;
    $summaryTypes .= "s";
}

if (!empty($productFilter)) {
    $summaryQuery .= " AND oi.product_id = ?";
    $summaryParams[] = $productFilter;
    $summaryTypes .= "i";
}

if (!empty($paymentMethodFilter)) {
    $summaryQuery .= " AND o.payment_method = ?";
    $summaryParams[] = $paymentMethodFilter;
    $summaryTypes .= "s";
}

$summaryStmt = $sales_conn->prepare($summaryQuery);
if (!empty($summaryParams)) {
    $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
}
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();
$summaryData = $summaryResult->fetch_assoc();

// =============================================
// PAYMENT METHOD BREAKDOWN - FROM REAL SALES DATA
// =============================================
$paymentMethodQuery = "SELECT 
                           o.payment_method,
                           COUNT(DISTINCT o.id) as transaction_count,
                           SUM(oi.subtotal) as total_amount
                       FROM orders o
                       JOIN order_items oi ON o.id = oi.order_id
                       WHERE o.status = 'completed' AND o.payment_status = 'paid'";

$paymentParams = [];
$paymentTypes = "";

if (!empty($dateFrom)) {
    $paymentMethodQuery .= " AND DATE(o.order_date) >= ?";
    $paymentParams[] = $dateFrom;
    $paymentTypes .= "s";
}

if (!empty($dateTo)) {
    $paymentMethodQuery .= " AND DATE(o.order_date) <= ?";
    $paymentParams[] = $dateTo;
    $paymentTypes .= "s";
}

if (!empty($productFilter)) {
    $paymentMethodQuery .= " AND oi.product_id = ?";
    $paymentParams[] = $productFilter;
    $paymentTypes .= "i";
}

$paymentMethodQuery .= " GROUP BY o.payment_method";

$paymentStmt = $sales_conn->prepare($paymentMethodQuery);
if (!empty($paymentParams)) {
    $paymentStmt->bind_param($paymentTypes, ...$paymentParams);
}
$paymentStmt->execute();
$paymentResult = $paymentStmt->get_result();

// =============================================
// TOP 5 SELLING PRODUCTS - FROM REAL SALES DATA
// =============================================
$topProductsQuery = "SELECT 
                        inv.id as product_id,
                        inv.product_name,
                        SUM(oi.quantity) as total_quantity,
                        SUM(oi.subtotal) as total_amount
                     FROM orders o
                     JOIN order_items oi ON o.id = oi.order_id
                     JOIN inventory inv ON oi.product_id = inv.id
                     WHERE o.status = 'completed' AND o.payment_status = 'paid'";

$topProductsParams = [];
$topProductsTypes = "";

if (!empty($dateFrom)) {
    $topProductsQuery .= " AND DATE(o.order_date) >= ?";
    $topProductsParams[] = $dateFrom;
    $topProductsTypes .= "s";
}

if (!empty($dateTo)) {
    $topProductsQuery .= " AND DATE(o.order_date) <= ?";
    $topProductsParams[] = $dateTo;
    $topProductsTypes .= "s";
}

if (!empty($paymentMethodFilter)) {
    $topProductsQuery .= " AND o.payment_method = ?";
    $topProductsParams[] = $paymentMethodFilter;
    $topProductsTypes .= "s";
}

$topProductsQuery .= " GROUP BY inv.id, inv.product_name
                      ORDER BY total_amount DESC
                      LIMIT 5";

$topProductsStmt = $sales_conn->prepare($topProductsQuery);
if (!empty($topProductsParams)) {
    $topProductsStmt->bind_param($topProductsTypes, ...$topProductsParams);
}
$topProductsStmt->execute();
$topProductsResult = $topProductsStmt->get_result();

// =============================================
// PREPARE DATA FOR CHARTS
// =============================================
$dailySalesData = [];
while ($row = $result->fetch_assoc()) {
    $dailySalesData[] = [
        'date' => $row['sale_date'],
        'sales' => floatval($row['total_sales']),
        'items' => intval($row['total_items']),
        'transactions' => intval($row['transactions'])
    ];
}

// Payment method data for charts
$paymentLabels = [];
$paymentAmounts = [];
$paymentResult->data_seek(0);
while ($row = $paymentResult->fetch_assoc()) {
    $paymentLabels[] = $row['payment_method'];
    $paymentAmounts[] = floatval($row['total_amount']);
}

// Top products data for charts
$productLabels = [];
$productAmounts = [];
$topProductsResult->data_seek(0);
while ($row = $topProductsResult->fetch_assoc()) {
    $productLabels[] = $row['product_name'];
    $productAmounts[] = floatval($row['total_amount']);
}

// Close sales database connection
$sales_conn->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Roti Seri Bakery (Real Data)</title>
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="imanager_salesreport.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="imanager_dashboard.php" class="nav-item">
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
                <a href="imanager_salesreport.php" class="nav-item active">
                    <i class="fas fa-chart-line"></i>
                    <div class="nav-text">Sales Report</div>
                </a>
                <a href="imanager_profile.php" class="nav-item">
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
            <h1>Sales Report (Real Sales Data)</h1>
            <p>Welcome, <?php echo htmlspecialchars($fullName); ?>!</p>
            <small style="color: #666;">Data source: Live Sales System</small>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h2><i class="fas fa-filter"></i> Filter Sales Data</h2>
            <form method="GET" action="imanager_salesreport.php" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="date_from">Date From:</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo $dateFrom; ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date_to">Date To:</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                </div>

                <div class="filter-row">
                    <div class="filter-group">
                        <label for="product">Product:</label>
                        <select name="product" id="product">
                            <option value="">All Products</option>
                            <?php 
                            while ($product = $productResult->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $product['id']; ?>" 
                                    <?php echo ($productFilter == $product['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="payment_method">Payment Method:</label>
                        <select name="payment_method" id="payment_method">
                            <option value="">All Methods</option>
                            <option value="cash" <?php echo ($paymentMethodFilter == 'cash') ? 'selected' : ''; ?>>Cash</option>
                            <option value="card" <?php echo ($paymentMethodFilter == 'card') ? 'selected' : ''; ?>>Card</option>
                            <option value="online" <?php echo ($paymentMethodFilter == 'online') ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="filter-btn apply-btn"><i class="fas fa-search"></i> Apply Filters</button>
                    <button type="button" class="filter-btn reset-btn" id="resetFilters"><i class="fas fa-sync"></i> Reset Filters</button>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="stat-details">
                    <h3>Total Sales</h3>
                    <p class="stat-value">RM <?php echo number_format($summaryData['total_revenue'] ?? 0, 2); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <div class="stat-details">
                    <h3>Items Sold</h3>
                    <p class="stat-value"><?php echo number_format($summaryData['total_items_sold'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-details">
                    <h3>Transactions</h3>
                    <p class="stat-value"><?php echo number_format($summaryData['total_transactions'] ?? 0); ?></p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-details">
                    <h3>Avg. Transaction</h3>
                    <p class="stat-value">RM <?php echo number_format($summaryData['avg_sale_amount'] ?? 0, 2); ?></p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-container full-width">
                <h2><i class="fas fa-chart-line"></i> Daily Sales Trend</h2>
                <div class="chart-box">
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h2><i class="fas fa-chart-bar"></i> Top 5 Revenue Distribution of Products</h2>
                <div class="chart-box">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h2><i class="fas fa-wallet"></i> Payment Methods</h2>
                <div class="chart-box">
                    <canvas id="paymentMethodsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Sales Table -->
        <div class="table-section">
            <div class="table-header">
                <h2><i class="fas fa-table"></i> Detailed Sales Report</h2>
                <div class="period-info">
                    Period: <?php echo date('d M Y', strtotime($dateFrom)); ?> - <?php echo date('d M Y', strtotime($dateTo)); ?>
                </div>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($detailedResult->num_rows > 0): ?>
                            <?php while ($row = $detailedResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['sale_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                    <td><?php echo $row['quantity_sold']; ?></td>
                                    <td>RM <?php echo number_format($row['unit_price'], 2); ?></td>
                                    <td>RM <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td><?php echo ucfirst($row['payment_method']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data">No sales data found for the selected criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="imanager_salesreport.js"></script>
    <script>
        // JavaScript to handle the sales report functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize event listeners
            initEventListeners();
            
            // Initialize charts
            initializeCharts();
        });

        // Daily sales data from PHP (REAL DATA)
        const dailySalesData = <?php echo json_encode($dailySalesData); ?>;
        
        // Payment methods data from PHP (REAL DATA)
        const paymentLabels = <?php echo json_encode($paymentLabels); ?>;
        const paymentAmounts = <?php echo json_encode($paymentAmounts); ?>;
        
        // Top products data from PHP (REAL DATA)
        const productLabels = <?php echo json_encode($productLabels); ?>;
        const productAmounts = <?php echo json_encode($productAmounts); ?>;
    </script>
</body>
</html>