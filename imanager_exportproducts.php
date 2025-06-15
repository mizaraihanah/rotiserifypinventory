<?php
session_start();
include 'db_connection.php';

if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Inventory Manager') {
    header("Location: index.php");
    exit();
}

// Get the user's full name
$userName = isset($_SESSION['fullName']) ? $_SESSION['fullName'] : 'Inventory Manager';

$format = isset($_GET['format']) ? $_GET['format'] : 'html';
$reportType = isset($_GET['type']) ? $_GET['type'] : 'all';
$orderId = isset($_GET['id']) ? $_GET['id'] : '';

// Initialize arrays to store data
$products = [];
$categories = [];
$orders = [];
$orderItems = [];
$lowStockProducts = [];

// Fetch products with categories
$productsSql = "SELECT p.product_id, p.product_name, c.category_name, p.description, 
        p.stock_quantity, p.reorder_threshold, p.unit_price, p.last_updated 
        FROM products p 
        LEFT JOIN product_categories c ON p.category_id = c.category_id 
        ORDER BY p.product_name";
$productsResult = $conn->query($productsSql);

while ($row = $productsResult->fetch_assoc()) {
    $products[] = $row;
    // Add to low stock products if stock is at or below threshold
    if ($row['stock_quantity'] <= $row['reorder_threshold']) {
        $lowStockProducts[] = $row;
    }
}

// Fetch categories
$categoriesSql = "SELECT * FROM product_categories ORDER BY category_name";
$categoriesResult = $conn->query($categoriesSql);

while ($row = $categoriesResult->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch orders
$ordersSql = "SELECT o.*, u.fullName FROM orders o 
              LEFT JOIN users u ON o.created_by = u.userID 
              ORDER BY o.order_date DESC";
$ordersResult = $conn->query($ordersSql);

while ($row = $ordersResult->fetch_assoc()) {
    $orders[] = $row;
    
    // Fetch order items for each order
    $itemsSql = "SELECT oi.*, p.product_name 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = ?";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param("s", $row['order_id']);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $items = [];
    while ($itemRow = $itemsResult->fetch_assoc()) {
        $items[] = $itemRow;
    }
    
    $orderItems[$row['order_id']] = $items;
}

// Export as HTML (printable)
if ($format === 'pdf' || $format === 'html') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Comprehensive Inventory Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                font-size: 14px;
            }
            h1, h2 {
                color: #333;
                text-align: center;
            }
            .section {
                margin-bottom: 40px;
                page-break-inside: avoid;
            }
            .section h2 {
                color: #0561FC;
                border-bottom: 2px solid #0561FC;
                padding-bottom: 5px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                padding: 10px;
                border: 1px solid #ddd;
                text-align: left;
            }
            th {
                background-color: #0561FC;
                color: white;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .low-stock {
                background-color: #fff3cd;
            }
            .out-of-stock {
                background-color: #f8d7da;
            }
            .footer {
                margin-top: 20px;
                text-align: right;
                font-size: 12px;
                color: #666;
            }
            .order-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
            }
            .order-info {
                flex: 1;
            }
            .total-row {
                font-weight: bold;
            }
            .status-badge {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                text-align: center;
            }
            .status-pending {
                background-color: #fff3cd;
                color: #856404;
            }
            .status-processing {
                background-color: #cce5ff;
                color: #004085;
            }
            .status-completed {
                background-color: #d4edda;
                color: #155724;
            }
            .status-cancelled {
                background-color: #f8d7da;
                color: #721c24;
            }
            .page-header {
                text-align: center;
                margin-bottom: 30px;
            }
            .page-header img {
                max-width: 150px;
                height: auto;
            }
            .page-header h1 {
                margin: 10px 0;
            }
            .page-header p {
                font-size: 16px;
                color: #666;
            }
            @media print {
                .no-print {
                    display: none;
                }
                .section {
                    page-break-after: always;
                }
                .section:last-child {
                    page-break-after: avoid;
                }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()">Print Report</button>
            <button onclick="window.close()">Close</button>
        </div>
        
        <div class="page-header">
            <!-- You can uncomment and update this if you have a logo -->
            <!-- <img src="image/icon/logo.png" alt="RotiSeri Logo"> -->
            <h1>RotiSeri Bakery</h1>
            <p>Comprehensive Inventory Report</p>
            <p><?php echo date('F d, Y'); ?></p>
        </div>
        
        <!-- Products Section -->
        <div class="section">
            <h2>Products Inventory</h2>
            <table>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Stock Quantity</th>
                    <th>Reorder Level</th>
                    <th>Unit Price (RM)</th>
                    <th>Last Updated</th>
                </tr>
                <?php if(empty($products)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No products found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): 
                        $rowClass = '';
                        if ($product['stock_quantity'] <= $product['reorder_threshold']) {
                            $rowClass = 'low-stock';
                        }
                        if ($product['stock_quantity'] == 0) {
                            $rowClass = 'out-of-stock';
                        }
                    ?>
                        <tr class="<?php echo $rowClass; ?>">
                            <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                            <td><?php echo htmlspecialchars($product['reorder_threshold']); ?></td>
                            <td>RM <?php echo number_format($product['unit_price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($product['last_updated']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Categories Section -->
        <div class="section">
            <h2>Product Categories</h2>
            <table>
                <tr>
                    <th>Category ID</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th>Products Count</th>
                </tr>
                <?php if(empty($categories)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">No categories found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): 
                        // Count products in this category
                        $productCount = 0;
                        foreach ($products as $product) {
                            if ($product['category_name'] == $category['category_name']) {
                                $productCount++;
                            }
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['category_id']); ?></td>
                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($category['description']); ?></td>
                            <td><?php echo $productCount; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Low Stock Products Section -->
        <div class="section">
            <h2>Low Stock Items</h2>
            <table>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Reorder Threshold</th>
                    <th>Status</th>
                </tr>
                <?php if(empty($lowStockProducts)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No low stock items found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lowStockProducts as $product): 
                        $status = $product['stock_quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
                        $statusClass = $product['stock_quantity'] == 0 ? 'status-cancelled' : 'status-pending';
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
                            <td><?php echo htmlspecialchars($product['reorder_threshold']); ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Orders Section -->
        <div class="section">
            <h2>Orders Summary</h2>
            <table>
                <tr>
                    <th>Order ID</th>
                    <th>Type</th>
                    <th>Customer/Supplier</th>
                    <th>Date</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Created By</th>
                </tr>
                <?php if(empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No orders found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($order['order_type']); ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                            <td>RM <?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($order['fullName']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Order Details Section -->
        <div class="section">
            <h2>Order Details</h2>
            <?php if(empty($orders)): ?>
                <p style="text-align: center;">No orders found</p>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div style="margin-bottom: 30px; border: 1px solid #ddd; border-radius: 5px; padding: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                            <div>
                                <h3 style="margin-top: 0;"><?php echo htmlspecialchars($order['order_id']); ?> - <?php echo htmlspecialchars($order['order_type']); ?> Order</h3>
                                <p><strong>Customer/Supplier:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            </div>
                            <div>
                                <p><strong>Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Created By:</strong> <?php echo htmlspecialchars($order['fullName']); ?></p>
                            </div>
                        </div>
                        
                        <table>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                            <?php if(empty($orderItems[$order['order_id']])): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No items found for this order</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $totalAmount = 0;
                                foreach ($orderItems[$order['order_id']] as $item): 
                                    $subtotal = $item['quantity'] * $item['unit_price'];
                                    $totalAmount += $subtotal;
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['product_id']); ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td>RM <?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td>RM <?php echo number_format($subtotal, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="4" style="text-align: right;">Total:</td>
                                    <td>RM <?php echo number_format($totalAmount, 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?> by <?php echo htmlspecialchars($userName); ?></p>
            <p>RotiSeri Bakery Inventory Management System</p>
        </div>
    </body>
    </html>
    <?php
    exit();
}
// Export as CSV
else if ($format === 'excel' || $format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="comprehensive_inventory_report.csv"');
    
    // Create a file pointer
    $output = fopen('php://output', 'w');
    
    // --- PRODUCTS SECTION ---
    fputcsv($output, ['PRODUCTS INVENTORY REPORT']);
    fputcsv($output, [
        'Product ID', 
        'Product Name', 
        'Category', 
        'Description', 
        'Stock Quantity', 
        'Reorder Level', 
        'Unit Price (RM)', 
        'Last Updated'
    ]);
    
    // Output products data
    foreach ($products as $product) {
        fputcsv($output, [
            $product['product_id'],
            $product['product_name'],
            $product['category_name'],
            $product['description'],
            $product['stock_quantity'],
            $product['reorder_threshold'],
            $product['unit_price'],
            $product['last_updated']
        ]);
    }
    
    // Add spacer
    fputcsv($output, []); 
    fputcsv($output, []);
    
    // --- CATEGORIES SECTION ---
    fputcsv($output, ['PRODUCT CATEGORIES']);
    fputcsv($output, [
        'Category ID', 
        'Category Name', 
        'Description',
        'Products Count'
    ]);
    
    // Output categories data
    foreach ($categories as $category) {
        // Count products in this category
        $productCount = 0;
        foreach ($products as $product) {
            if ($product['category_name'] == $category['category_name']) {
                $productCount++;
            }
        }
        
        fputcsv($output, [
            $category['category_id'],
            $category['category_name'],
            $category['description'],
            $productCount
        ]);
    }
    
    // Add spacer
    fputcsv($output, []); 
    fputcsv($output, []);
    
    // --- LOW STOCK SECTION ---
    fputcsv($output, ['LOW STOCK ITEMS']);
    fputcsv($output, [
        'Product ID', 
        'Product Name', 
        'Category', 
        'Current Stock', 
        'Reorder Threshold', 
        'Status'
    ]);
    
    // Output low stock items data
    foreach ($lowStockProducts as $product) {
        $status = $product['stock_quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
        
        fputcsv($output, [
            $product['product_id'],
            $product['product_name'],
            $product['category_name'],
            $product['stock_quantity'],
            $product['reorder_threshold'],
            $status
        ]);
    }
    
    // Add spacer
    fputcsv($output, []); 
    fputcsv($output, []);
    
    // --- ORDERS SUMMARY SECTION ---
    fputcsv($output, ['ORDERS SUMMARY']);
    fputcsv($output, [
        'Order ID', 
        'Type', 
        'Customer/Supplier', 
        'Date', 
        'Total Amount (RM)', 
        'Status', 
        'Created By'
    ]);
    
    // Output orders data
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['order_id'],
            $order['order_type'],
            $order['customer_name'],
            $order['order_date'],
            $order['total_amount'],
            $order['status'],
            $order['fullName']
        ]);
    }
    
    // Add spacer
    fputcsv($output, []); 
    fputcsv($output, []);
    
    // --- ORDER DETAILS SECTION ---
    fputcsv($output, ['ORDER DETAILS']);
    
    // Output order details for each order
    foreach ($orders as $order) {
        fputcsv($output, ['Order ID', $order['order_id']]);
        fputcsv($output, ['Type', $order['order_type']]);
        fputcsv($output, ['Customer/Supplier', $order['customer_name']]);
        fputcsv($output, ['Date', $order['order_date']]);
        fputcsv($output, ['Status', $order['status']]);
        fputcsv($output, ['Created By', $order['fullName']]);
        fputcsv($output, []);
        
        fputcsv($output, ['Product ID', 'Product Name', 'Quantity', 'Unit Price (RM)', 'Subtotal (RM)']);
        
        $totalAmount = 0;
        if (!empty($orderItems[$order['order_id']])) {
            foreach ($orderItems[$order['order_id']] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $totalAmount += $subtotal;
                
                fputcsv($output, [
                    $item['product_id'],
                    $item['product_name'],
                    $item['quantity'],
                    $item['unit_price'],
                    $subtotal
                ]);
            }
        } else {
            fputcsv($output, ['No items found for this order']);
        }
        
        fputcsv($output, ['', '', '', 'Total:', $totalAmount]);
        fputcsv($output, []);
        fputcsv($output, ['---']);
        fputcsv($output, []);
    }
    
    // Add generation info at the end
    fputcsv($output, []); 
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s') . ' by ' . $userName]);
    fputcsv($output, ['RotiSeri Bakery Inventory Management System']);
    
    // Close the file pointer
    fclose($output);
    exit();
}

$conn->close();
?>