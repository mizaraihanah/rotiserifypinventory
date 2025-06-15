<?php
session_start();

// Check if user is logged in and authorized
if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Bakery Staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Include database connection
include 'db_connection.php';

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit();
}

$orderId = $_GET['id'];

try {
    // Fetch order details
    $orderQuery = "SELECT o.*, u.fullName as created_by_name 
                   FROM orders o 
                   LEFT JOIN users u ON o.created_by = u.userID 
                   WHERE o.order_id = ?";
    
    $stmt = $conn->prepare($orderQuery);
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $orderResult = $stmt->get_result();
    
    if ($orderResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit();
    }
    
    $order = $orderResult->fetch_assoc();
    $stmt->close();
    
    // Fetch order items
    $itemsQuery = "SELECT oi.*, p.product_name 
                   FROM order_items oi 
                   LEFT JOIN products p ON oi.product_id = p.product_id 
                   WHERE oi.order_id = ? 
                   ORDER BY oi.item_id";
    
    $stmt = $conn->prepare($itemsQuery);
    $stmt->bind_param("s", $orderId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
    }
    $stmt->close();
    
    // Return response as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

$conn->close();
?>