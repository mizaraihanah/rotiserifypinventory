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

// Fetch logs
$sql = "SELECT l.*, u.fullName 
        FROM inventory_logs l 
        JOIN users u ON l.user_id = u.userID 
        ORDER BY l.timestamp DESC";
$result = $conn->query($sql);

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Export as HTML (printable)
if ($format === 'pdf' || $format === 'html') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Inventory Activity Logs</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                font-size: 14px;
            }
            h1 {
                color: #333;
                text-align: center;
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
            .footer {
                margin-top: 20px;
                text-align: right;
                font-size: 12px;
                color: #666;
            }
            @media print {
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()">Print Report</button>
            <button onclick="window.close()">Close</button>
        </div>
        
        <h1>Inventory Activity Logs</h1>
        
        <table>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Item ID</th>
                <th>Details</th>
                <th>IP Address</th>
            </tr>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                    <td><?php echo htmlspecialchars($log['fullName']); ?></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo htmlspecialchars($log['item_id']); ?></td>
                    <td><?php echo htmlspecialchars($log['action_details']); ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="footer">
            Generated on: <?php echo date('Y-m-d H:i:s'); ?> by <?php echo htmlspecialchars($userName); ?>
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
    header('Content-Disposition: attachment; filename="inventory_activity_logs.csv"');
    
    // Create a file pointer
    $output = fopen('php://output', 'w');
    
    // Set column headers 
    fputcsv($output, [
        'Timestamp', 
        'User', 
        'Action', 
        'Item ID', 
        'Details', 
        'IP Address'
    ]);
    
    // Output each row of data
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['timestamp'],
            $log['fullName'],
            $log['action'],
            $log['item_id'],
            $log['action_details'],
            $log['ip_address']
        ]);
    }
    
    // Add generation info at the end
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s') . ' by ' . $userName]);
    
    // Close the file pointer
    fclose($output);
    exit();
}

$conn->close();
?>