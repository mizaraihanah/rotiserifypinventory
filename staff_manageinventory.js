/**
 * Staff Manage Inventory JavaScript - Syntax Fixed Version
 * Handles functionality for the inventory management interface
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Staff Manage Inventory script loaded');
    
    // Initialize notification dropdown
    initNotifications();
    
    // Close modals when clicking outside content
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Add event listener to edit button in view details modal
    const viewEditBtn = document.getElementById('view_edit_btn');
    if (viewEditBtn) {
        viewEditBtn.addEventListener('click', function() {
            console.log('Edit button clicked from view modal');
            const productIdElement = document.getElementById('view_product_id');
            
            if (!productIdElement) {
                console.error('Product ID element not found');
                return;
            }
            
            const productId = productIdElement.textContent;
            console.log('Product ID:', productId);
            
            // Find the product data from the page
            const productRows = document.querySelectorAll('.inventory-table tbody tr');
            
            productRows.forEach(row => {
                const firstCell = row.querySelector('td:first-child');
                if (firstCell) {
                    const rowProductId = firstCell.textContent;
                    if (rowProductId === productId) {
                        // Trigger the edit button click for this row
                        const editBtn = row.querySelector('.edit-btn');
                        if (editBtn) {
                            editBtn.click();
                        }
                    }
                }
            });
            
            closeModal('viewItemModal');
        });
    }
    
    // Add event listener to status update button in view order modal
    const updateStatusBtn = document.getElementById('updateStatusBtn');
    if (updateStatusBtn) {
        updateStatusBtn.addEventListener('click', function() {
            console.log('Update status button clicked');
            
            // Get the order ID from the modal content
            const orderDetailsContainer = document.getElementById('orderDetailsContainer');
            if (!orderDetailsContainer) {
                console.error('Order details container not found');
                return;
            }
            
            // Try to find order ID and status from the displayed content
            const orderIdElement = orderDetailsContainer.querySelector('[data-order-id]');
            const statusElement = orderDetailsContainer.querySelector('[data-order-status]');
            
            let orderId = null;
            let currentStatus = null;
            
            if (orderIdElement) {
                orderId = orderIdElement.getAttribute('data-order-id');
            }
            if (statusElement) {
                currentStatus = statusElement.getAttribute('data-order-status');
            }
            
            // Alternative approach: look for the data in text content
            if (!orderId || !currentStatus) {
                const orderInfoElements = orderDetailsContainer.querySelectorAll('p');
                orderInfoElements.forEach(p => {
                    const text = p.textContent;
                    if (text.includes('Order ID:') && !orderId) {
                        orderId = text.split('Order ID:')[1].trim();
                    }
                    if (text.includes('Status:') && !currentStatus) {
                        currentStatus = text.split('Status:')[1].trim();
                    }
                });
            }
            
            if (orderId && currentStatus) {
                showUpdateStatusModal(orderId, currentStatus);
                closeModal('viewOrderModal');
            } else {
                console.error('Could not find order ID or status');
                showToast('Error: Could not get order information', 'error');
            }
        });
    }
});

/**
 * Helper function to safely set text content
 * @param {string} elementId - The ID of the element
 * @param {string|number} content - The content to set
 */
function safeSetTextContent(elementId, content) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = content || '';
        return true;
    } else {
        console.warn(`Element with ID '${elementId}' not found`);
        return false;
    }
}

/**
 * Helper function to safely set element value
 * @param {string} elementId - The ID of the element
 * @param {string|number} value - The value to set
 */
function safeSetValue(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.value = value || '';
        return true;
    } else {
        console.warn(`Element with ID '${elementId}' not found`);
        return false;
    }
}

/**
 * Helper function to safely set innerHTML
 * @param {string} elementId - The ID of the element
 * @param {string} content - The HTML content to set
 */
function safeSetInnerHTML(elementId, content) {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = content || '';
        return true;
    } else {
        console.warn(`Element with ID '${elementId}' not found`);
        return false;
    }
}

/**
 * Initialize notification dropdown functionality
 */
function initNotifications() {
    const bell = document.querySelector('.notification-bell');
    if (bell) {
        bell.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        });
        
        // Prevent clicks inside dropdown from closing it
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    }
}

/**
 * Toggle the notification dropdown
 */
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

/**
 * Close a specific modal by ID
 * @param {string} modalId - The ID of the modal to close
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Close all open modals
 */
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

/**
 * Show the Add Item modal
 */
function showAddModal() {
    const form = document.getElementById('addItemForm');
    const modal = document.getElementById('addItemModal');
    
    if (form) {
        form.reset();
    }
    
    if (modal) {
        modal.style.display = 'block';
    }
}

/**
 * Show the Edit Item modal with pre-filled data
 * @param {Object} product - The product data to pre-fill
 */
function showEditModal(product) {
    console.log('showEditModal called with:', product);
    
    try {
        // Fill form fields with product data using safe functions
        const fieldUpdates = [
            ['edit_product_id', product.product_id],
            ['edit_product_name', product.product_name],
            ['edit_product_description', product.description || ''],
            ['edit_category_id', product.category_id],
            ['edit_stock_quantity', product.stock_quantity],
            ['edit_reorder_threshold', product.reorder_threshold],
            ['edit_unit_price', parseFloat(product.unit_price).toFixed(2)]
        ];
        
        let allFieldsFound = true;
        fieldUpdates.forEach(([fieldId, value]) => {
            if (!safeSetValue(fieldId, value)) {
                allFieldsFound = false;
            }
        });
        
        if (!allFieldsFound) {
            console.warn('Some form fields were not found');
        }
        
        // Show the modal
        const modal = document.getElementById('editItemModal');
        if (modal) {
            modal.style.display = 'block';
        } else {
            console.error('Edit modal not found');
            showToast('Error: Edit form not available', 'error');
        }
        
    } catch (error) {
        console.error('Error in showEditModal:', error);
        showToast('Error opening edit form', 'error');
    }
}

/**
 * Show the Delete Confirmation modal
 * @param {string} productId - The ID of the product to delete
 * @param {string} productName - The name of the product to delete
 */
function confirmDelete(productId, productName) {
    console.log('confirmDelete called:', productId, productName);
    
    safeSetValue('delete_product_id', productId);
    safeSetTextContent('delete_item_name', productName);
    
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.style.display = 'block';
    } else {
        console.error('Delete confirmation modal not found');
    }
}

/**
 * View detailed information about an inventory item
 * @param {Object|string} productData - Product data object or JSON string
 */
function viewItemDetails(productData) {
    console.log('viewItemDetails called with:', productData);
    
    try {
        // Handle both object and JSON string inputs
        let product;
        if (typeof productData === 'string') {
            product = JSON.parse(productData);
        } else if (typeof productData === 'object' && productData !== null) {
            product = productData;
        } else {
            throw new Error('Invalid product data type');
        }
        
        console.log('Processed product:', product);
        
        // Fill the details using safe functions
        const detailUpdates = [
            ['view_product_id', product.product_id],
            ['view_product_name', product.product_name],
            ['view_product_description', product.description || 'No description available'],
            ['view_category_name', product.category_name],
            ['view_stock_quantity', product.stock_quantity],
            ['view_reorder_threshold', product.reorder_threshold],
            ['view_unit_price', `RM ${parseFloat(product.unit_price).toFixed(2)}`],
            ['view_last_updated', product.last_updated]
        ];
        
        detailUpdates.forEach(([elementId, value]) => {
            safeSetTextContent(elementId, value);
        });
        
        // Set status
        let statusText = 'Normal';
        let statusClass = 'normal';
        if (product.stock_quantity == 0) {
            statusText = 'Out of Stock';
            statusClass = 'out';
        } else if (product.stock_quantity <= product.reorder_threshold) {
            statusText = 'Low Stock';
            statusClass = 'low';
        }
        
        const statusElement = document.getElementById('view_status');
        if (statusElement) {
            statusElement.textContent = statusText;
            statusElement.className = `detail-value status ${statusClass}`;
        }
        
        // Show the modal
        const modal = document.getElementById('viewItemModal');
        if (modal) {
            modal.style.display = 'block';
            console.log('View modal displayed');
        } else {
            console.error('View modal not found');
            showToast('Error: View modal not available', 'error');
        }
        
        // If this is a low stock or out of stock item, show a notification
        if (statusClass !== 'normal') {
            const message = statusClass === 'out' ? 
                'This item is out of stock. Consider placing a purchase order soon.' : 
                `This item has low stock (${product.stock_quantity}/${product.reorder_threshold}). Consider restocking.`;
            
            showToast(message, 'warning');
        }
        
    } catch (error) {
        console.error('Error in viewItemDetails:', error);
        showToast('Error displaying item details', 'error');
    }
}

/**
 * Show the Update Order Status modal
 * @param {string} orderId - The ID of the order
 * @param {string} currentStatus - The current status of the order
 */
function showUpdateStatusModal(orderId, currentStatus) {
    console.log('showUpdateStatusModal called:', orderId, currentStatus);
    
    safeSetValue('update_order_id', orderId);
    safeSetValue('order_status', currentStatus);
    
    const modal = document.getElementById('updateOrderStatusModal');
    if (modal) {
        modal.style.display = 'block';
    } else {
        console.error('Update order status modal not found');
    }
}

/**
 * View order details in the modal
 * @param {string} orderId - The ID of the order to view
 */
function viewOrderDetails(orderId) {
    console.log('viewOrderDetails called with:', orderId);
    
    // Show loading state
    const orderDetailsContainer = document.getElementById('orderDetailsContainer');
    if (orderDetailsContainer) {
        orderDetailsContainer.innerHTML = '<div class="loading-spinner"><div class="spinner"></div></div>';
    }
    
    // Show the modal first
    const modal = document.getElementById('viewOrderModal');
    if (modal) {
        modal.style.display = 'block';
    } else {
        console.error('View order modal not found');
        return;
    }
    
    // Fetch order details using AJAX
    fetch(`get_order_details.php?id=${encodeURIComponent(orderId)}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Order details received:', data);
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Format the order date
            const orderDate = new Date(data.order.order_date);
            const formattedDate = orderDate.toLocaleDateString('en-MY', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Build the order details HTML directly instead of trying to set individual elements
            const orderDetailsHTML = `
                <div class="order-header">
                    <div class="order-info">
                        <p><strong>Order ID:</strong> <span data-order-id="${data.order.order_id}">${data.order.order_id}</span></p>
                        <p><strong>Type:</strong> ${data.order.order_type}</p>
                        <p><strong>Customer/Supplier:</strong> ${data.order.customer_name}</p>
                    </div>
                    <div class="order-info">
                        <p><strong>Date:</strong> ${formattedDate}</p>
                        <p><strong>Status:</strong> <span data-order-status="${data.order.status}" class="status ${data.order.status.toLowerCase()}">${data.order.status}</span></p>
                        <p><strong>Created By:</strong> ${data.order.created_by_name || data.order.created_by}</p>
                    </div>
                </div>
                
                <h3>Order Items</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="orderItemsTable">
                            ${data.items && data.items.length > 0 ? 
                                data.items.map(item => {
                                    const subtotal = item.quantity * item.unit_price;
                                    return `
                                        <tr>
                                            <td>${item.product_id || 'N/A'}</td>
                                            <td>${item.product_name || 'Unknown Product'}</td>
                                            <td>${item.quantity}</td>
                                            <td>RM ${parseFloat(item.unit_price).toFixed(2)}</td>
                                            <td>RM ${subtotal.toFixed(2)}</td>
                                        </tr>
                                    `;
                                }).join('') :
                                '<tr><td colspan="5" class="no-data">No items found for this order</td></tr>'
                            }
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-right"><strong>Total:</strong></td>
                                <td><strong>RM ${parseFloat(data.order.total_amount).toFixed(2)}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;
            
            // Set the complete HTML
            if (orderDetailsContainer) {
                orderDetailsContainer.innerHTML = orderDetailsHTML;
            }
            
        })
        .catch(error => {
            console.error('Error fetching order details:', error);
            
            if (orderDetailsContainer) {
                orderDetailsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        Error loading order details: ${error.message}
                        <br><small>Please check the browser console for more details.</small>
                    </div>
                `;
            }
            
            showToast('Error loading order details', 'error');
        });
}

/**
 * Show a toast notification
 * @param {string} message - Message to display
 * @param {string} type - Type of notification (success, error, or warning)
 */
function showToast(message, type = 'success') {
    console.log('showToast called:', message, type);
    
    // Check if a toast already exists and remove it
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create the toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    
    let icon = 'check-circle';
    if (type === 'error') {
        icon = 'exclamation-circle';
    } else if (type === 'warning') {
        icon = 'exclamation-triangle';
    }
    
    toast.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove();">&times;</button>
    `;
    
    // Add to the document
    document.body.appendChild(toast);
    
    // Show toast
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Hide toast after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }, 5000);
}