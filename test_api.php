<!DOCTYPE html>
<html>
<head>
    <title>Inventory API Test</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 1200px; 
            margin: 20px auto; 
            padding: 20px; 
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .test-section { 
            margin: 20px 0; 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
            background: #fafafa;
        }
        button { 
            padding: 10px 15px; 
            margin: 5px; 
            background: #007cba; 
            color: white; 
            border: none; 
            border-radius: 4px;
            cursor: pointer; 
        }
        button:hover {
            background: #005a87;
        }
        button.success {
            background: #28a745;
        }
        button.warning {
            background: #ffc107;
            color: #212529;
        }
        pre { 
            background: #f8f9fa; 
            padding: 15px; 
            max-height: 400px; 
            overflow: auto; 
            border-radius: 4px;
            border: 1px solid #e9ecef;
            font-size: 12px;
        }
        input, select {
            padding: 8px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .api-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Inventory System API Test Center</h1>
        <div class="api-info">
            <h3>API Endpoints Available:</h3>
            <ul>
                <li><strong>get_inventory_status.php</strong> - Get current inventory levels</li>
                <li><strong>get_recipe_ingredients.php</strong> - Check production capacity</li>
                <li><strong>consume_ingredients.php</strong> - Update inventory after production</li>
            </ul>
        </div>
    </div>

    <div class="grid">
        <div class="container">
            <div class="test-section">
                <h3>📋 Test 1: Get All Inventory</h3>
                <p>Fetch all items from both small_inventory and products tables</p>
                <button onclick="testAllInventory()">Get All Inventory</button>
                <button onclick="testSmallInventoryOnly()">Small Inventory Only</button>
                <button onclick="testProductsOnly()">Products Only</button>
                <pre id="result1">Click a button to test...</pre>
            </div>
            
            <div class="test-section">
                <h3>⚠️ Test 2: Get Low Stock Items</h3>
                <p>Check which items are running low or out of stock</p>
                <button onclick="testLowStock()" class="warning">Get Low Stock</button>
                <button onclick="testOutOfStock()" style="background: #dc3545;">Get Out of Stock</button>
                <button onclick="testSufficientStock()" class="success">Get Sufficient Stock</button>
                <pre id="result2">Click a button to test...</pre>
            </div>
        </div>

        <div class="container">
            <div class="test-section">
                <h3>🔍 Test 3: Search Inventory</h3>
                <p>Search for specific ingredients or products</p>
                <input type="text" id="searchTerm" placeholder="Search term (e.g., flour)" value="flour">
                <button onclick="testSearch()">Search Inventory</button>
                <pre id="result3">Enter search term and click Search...</pre>
            </div>
            
            <div class="test-section">
                <h3>🍞 Test 4: Check Recipe Ingredients</h3>
                <p>Check if enough ingredients to make a specific product</p>
                <input type="text" id="productId" placeholder="Product ID (e.g., PROD0001)" value="PROD0001">
                <button onclick="testRecipe()">Check Recipe</button>
                <pre id="result4">Enter product ID and click Check...</pre>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="test-section">
            <h3>🏭 Test 5: Production Consumption</h3>
            <p><strong>⚠️ Warning:</strong> This will actually update your inventory!</p>
            <div>
                <input type="text" id="prodProductId" placeholder="Product ID" value="PROD0001">
                <input type="number" id="prodQuantity" placeholder="Quantity Produced" value="1" min="1">
                <input type="text" id="prodUser" placeholder="User ID" value="test_user">
            </div>
            <button onclick="testProduction()" style="background: #dc3545;">⚠️ Consume Ingredients</button>
            <pre id="result5">This will update your database - use carefully!</pre>
        </div>
    </div>

    <div class="container">
        <div id="connectionStatus" class="status">
            Testing connection...
        </div>
    </div>

    <script>
        // Test all inventory
        function testAllInventory() {
            updateResult('result1', 'Loading...');
            fetch('get_inventory_status.php')
                .then(response => response.json())
                .then(data => {
                    updateResult('result1', JSON.stringify(data, null, 2));
                    updateConnectionStatus(true);
                })
                .catch(error => {
                    updateResult('result1', 'Error: ' + error);
                    updateConnectionStatus(false);
                });
        }

        // Test small inventory only
        function testSmallInventoryOnly() {
            updateResult('result1', 'Loading...');
            fetch('get_inventory_status.php?source=small_inventory')
                .then(response => response.json())
                .then(data => {
                    updateResult('result1', JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    updateResult('result1', 'Error: ' + error);
                });
        }

        // Test products only
        function testProductsOnly() {
            updateResult('result1', 'Loading...');
            fetch('get_inventory_status.php?source=products')
                .then(response => response.json())
                .then(data => {
                    updateResult('result1', JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    updateResult('result1', 'Error: ' + error);
                });
        }
        
        // Test low stock
        function testLowStock() {
            updateResult('result2', 'Loading...');
            fetch('get_inventory_status.php?status=low_stock')
                .then(response => response.json())
                .then(data => {
                    updateResult('result2', JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    updateResult('result2', 'Error: ' + error);
                });
        }

        // Test out of stock
        function testOutOfStock() {
            updateResult('result2', 'Loading...');
            fetch('get_inventory_status.php?status=out_of_stock')
                .then(response => response.json())
                .then(data => {
                    updateResult('result2', JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    updateResult('result2', 'Error: ' + error);
                });
        }

        // Test sufficient stock
        function testSufficientStock() {
            updateResult('result2', 'Loading...');
            fetch('get_inventory_status.php?status=sufficient')
                .then(response => response.json())
                .then(data => {
                    updateResult('result2', JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    updateResult('result2', 'Error: ' + error);
                });
        }

        // Test search
        function testSearch() {
            const searchTerm = document.getElementById('searchTerm').value;
            updateResult('result3', 'Loading...');
            fetch(`get_inventory_status.php?search=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    updateResult('result3', JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    updateResult('result3', 'Error: ' + error);
                });
        }
        
        // Test recipe
        function testRecipe() {
            const productId = document.getElementById('productId').value;
            updateResult('result4', 'Loading...');
            fetch(`get_recipe_ingredients.php?product_id=${encodeURIComponent(productId)}`)
                .then(response => response.json())
                .then(data => {
                    updateResult('result4', JSON.stringify(data, null, 2));
                })
                .catch(error => {
                    updateResult('result4', 'Error: ' + error);
                });
        }

        // Test production consumption
        function testProduction() {
            if (!confirm('This will actually update your inventory database. Are you sure?')) {
                return;
            }

            const productId = document.getElementById('prodProductId').value;
            const quantity = parseInt(document.getElementById('prodQuantity').value);
            const userId = document.getElementById('prodUser').value;

            updateResult('result5', 'Processing...');

            fetch('consume_ingredients.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity_produced: quantity,
                    user_id: userId
                })
            })
            .then(response => response.json())
            .then(data => {
                updateResult('result5', JSON.stringify(data, null, 2));
            })
            .catch(error => {
                updateResult('result5', 'Error: ' + error);
            });
        }

        // Helper functions
        function updateResult(elementId, content) {
            document.getElementById(elementId).textContent = content;
        }

        function updateConnectionStatus(connected) {
            const statusEl = document.getElementById('connectionStatus');
            if (connected) {
                statusEl.className = 'status success';
                statusEl.innerHTML = '✅ Successfully connected to Inventory API';
            } else {
                statusEl.className = 'status error';
                statusEl.innerHTML = '❌ Cannot connect to Inventory API - Check if APIs are running';
            }
        }

        // Test connection on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetch('get_inventory_status.php?type=all')
                .then(response => response.json())
                .then(data => {
                    updateConnectionStatus(true);
                    console.log('API Connection Test Successful:', data);
                })
                .catch(error => {
                    updateConnectionStatus(false);
                    console.error('API Connection Test Failed:', error);
                });
        });
    </script>
</body>
</html>