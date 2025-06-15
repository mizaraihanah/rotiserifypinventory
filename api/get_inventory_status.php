<?php
// Turn off HTML error reporting to prevent breaking JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Include database connection
$db_path = '../db_connection.php';
if (!file_exists($db_path)) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection file not found at: ' . $db_path,
        'current_dir' => __DIR__
    ]);
    exit;
}

try {
    require_once $db_path;
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to include database connection: ' . $e->getMessage()
    ]);
    exit;
}

// Check database connection
if (!isset($conn) || !$conn || $conn->connect_error) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . ($conn->connect_error ?? 'Connection not available')
    ]);
    exit;
}

// Get request parameters
$item_type = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$source_table = $_GET['source'] ?? 'all';

try {
    $inventory_data = [];
    
    // Only get data from YOUR products table (your inventory system)
    $prod_query = "SELECT 
                      p.product_id as unified_id,
                      'inventory_item' as item_type,
                      p.product_name as item_name,
                      p.stock_quantity as quantity_available,
                      'units' as unit_type,
                      CASE 
                          WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
                          WHEN p.stock_quantity <= p.reorder_threshold THEN 'low_stock'
                          ELSE 'sufficient'
                      END as stock_status,
                      COALESCE(pc.category_name, 'Inventory Items') as category,
                      p.unit_price,
                      p.product_id as original_id,
                      'products' as source_table,
                      p.reorder_threshold,
                      p.last_updated
                   FROM products p
                   LEFT JOIN product_categories pc ON p.category_id = pc.category_id
                   WHERE 1=1";
    
    $params = [];
    $types = "";
    
    // Apply search filter
    if (!empty($search)) {
        $prod_query .= " AND p.product_name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    
    $prod_query .= " ORDER BY p.product_name";
    
    $stmt = $conn->prepare($prod_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Apply type filter (though all are inventory_item)
        if ($item_type !== 'all' && $row['item_type'] !== $item_type && $item_type !== 'inventory_item') continue;
        
        // Apply status filter
        if ($status_filter !== 'all' && $row['stock_status'] !== $status_filter) continue;
        
        $inventory_data[] = $row;
    }
    
    $stmt->close();
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => $inventory_data,
        'total_items' => count($inventory_data),
        'timestamp' => date('Y-m-d H:i:s'),
        'filters_applied' => [
            'type' => $item_type,
            'status' => $status_filter,
            'search' => $search,
            'source' => 'products_only'
        ],
        'database_info' => [
            'database_name' => $conn->query("SELECT DATABASE()")->fetch_row()[0],
            'table_used' => 'products',
            'note' => 'This API serves data from YOUR inventory system only'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'line' => $e->getLine(),
        'available_tables' => getAvailableTables($conn)
    ]);
}

// Helper function to see what tables exist
function getAvailableTables($conn) {
    try {
        $result = $conn->query("SHOW TABLES");
        $tables = [];
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        return $tables;
    } catch (Exception $e) {
        return ['Error getting tables: ' . $e->getMessage()];
    }
}
?>