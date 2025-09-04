<?php
/**
 * Order Synchronization Interface
 * 
 * Allows users to pull orders from 3DCart within a date range,
 * check NetSuite status, and sync orders individually or in bulk.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Laguna\Integration\Services\ThreeDCartService;
use Laguna\Integration\Services\NetSuiteService;
use Laguna\Integration\Controllers\WebhookController;
use Laguna\Integration\Utils\Logger;
use Laguna\Integration\Utils\UrlHelper;
use Laguna\Integration\Middleware\AuthMiddleware;

// Handle AJAX requests first (before authentication redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to catch any unexpected output
    ob_start();
    
    // Disable error display for AJAX requests to prevent JSON corruption
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    
    // Set JSON content type early
    header('Content-Type: application/json');
    
    // Set reasonable limits for AJAX requests
    ini_set('memory_limit', '512M');
    set_time_limit(120); // 2 minutes max
    
    // For AJAX requests, check authentication and return JSON error if not authenticated
    $auth = new AuthMiddleware();
    if (!$auth->isAuthenticated()) {
        // Clear any buffered output and send clean JSON
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required. Please log in.',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    $currentUser = $auth->getCurrentUser();
} else {
    // For regular page requests, use normal authentication with redirect
    $auth = new AuthMiddleware();
    $currentUser = $auth->requireAuth();
    if (!$currentUser) {
        exit; // Middleware handles redirect
    }
}

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize services
$threeDCartService = new ThreeDCartService();
$netSuiteService = new NetSuiteService();
$webhookController = new WebhookController();
$logger = Logger::getInstance();

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure we always return JSON, even on fatal errors
    header('Content-Type: application/json');
    
    // Capture any output that might interfere with JSON
    ob_start();
    
    // Set error handler to catch any PHP errors and return JSON
    set_error_handler(function($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'fetch_orders':
                $startDate = $_POST['start_date'] ?? '';
                $endDate = $_POST['end_date'] ?? '';
                $status = $_POST['status'] ?? '';
                
                if (empty($startDate) || empty($endDate)) {
                    throw new Exception('Start date and end date are required');
                }
                
                // Validate date format
                $start = DateTime::createFromFormat('Y-m-d', $startDate);
                $end = DateTime::createFromFormat('Y-m-d', $endDate);
                
                if (!$start || !$end) {
                    throw new Exception('Invalid date format. Use YYYY-MM-DD');
                }
                
                if ($start > $end) {
                    throw new Exception('Start date cannot be after end date');
                }
                
                // Check date range (limit to 30 days for performance)
                $daysDiff = $start->diff($end)->days;
                if ($daysDiff > 30) {
                    throw new Exception('Date range cannot exceed 30 days');
                }
                
                // Set execution time limit for large date ranges
                if ($daysDiff > 7) {
                    set_time_limit(120); // 2 minutes for large ranges
                } else {
                    set_time_limit(60); // 1 minute for small ranges
                }
                
                $logger->info('Fetching orders from 3DCart', [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status
                ]);
                
                // Fetch orders from 3DCart
                $logger->info('About to fetch orders from 3DCart');
                $orders = $threeDCartService->getOrdersByDateRange($startDate, $endDate, $status);
                $logger->info('Fetched orders from 3DCart', ['count' => count($orders)]);
                
                // Limit response size to prevent timeout/memory issues
                $originalOrderCount = count($orders);
                $wasLimited = false;
                if (count($orders) > 200) {
                    $logger->warning('Large order count detected, limiting response', [
                        'original_count' => count($orders),
                        'limited_to' => 200
                    ]);
                    $orders = array_slice($orders, 0, 200);
                    $wasLimited = true;
                }
                
                // Extract order IDs for bulk NetSuite status check
                $orderIds = array_map(function($order) {
                    return $order['OrderID'];
                }, $orders);
                
                // Check NetSuite sync status for all orders in one call
                $logger->info('About to check NetSuite sync status', ['order_ids' => $orderIds]);
                $syncStatusMap = $netSuiteService->checkOrdersSyncStatus($orderIds);
                $logger->info('Checked NetSuite sync status', ['status_count' => count($syncStatusMap)]);
                
                // Build orders with status information
                $ordersWithStatus = [];
                foreach ($orders as $order) {
                    $orderId = $order['OrderID'];
                    $syncStatus = $syncStatusMap[$orderId] ?? ['synced' => false];
                    
                    // Extract customer name with fallback options
                    $customerName = trim(($order['BillingFirstName'] ?? '') . ' ' . ($order['BillingLastName'] ?? ''));
                    if (empty($customerName) || $customerName === ' ') {
                        $customerName = $order['BillingCompany'] ?? 'Unknown Customer';
                    }
                    
                    $statusId = $order['OrderStatusID'] ?? 0;
                    $statusName = $threeDCartService->getOrderStatusName($statusId);
                    
                    $ordersWithStatus[] = [
                        'order_id' => $orderId,
                        'order_date' => $order['OrderDate'],
                        'customer_name' => $customerName,
                        'customer_company' => $order['BillingCompany'] ?? '',
                        'order_total' => $order['OrderAmount'] ?? ($order['OrderTotal'] ?? 0),
                        'order_status' => $statusId,
                        'order_status_name' => $statusName,
                        'in_netsuite' => $syncStatus['synced'],
                        'netsuite_id' => $syncStatus['netsuite_id'],
                        'netsuite_tranid' => $syncStatus['netsuite_tranid'],
                        'netsuite_status' => $syncStatus['status'],
                        'netsuite_total' => null, // Total field removed from SuiteQL
                        'sync_date' => $syncStatus['sync_date'],
                        'can_sync' => !$syncStatus['synced'], // Can only sync if not already in NetSuite
                        'sync_error' => $syncStatus['error'] ?? null
                        // Note: raw_data removed to reduce response size - will be fetched when needed for sync
                    ];
                }
                
                $response = [
                    'success' => true,
                    'orders' => $ordersWithStatus,
                    'total_count' => count($ordersWithStatus),
                    'date_range' => "$startDate to $endDate"
                ];
                
                // Add warning if orders were limited
                if ($wasLimited) {
                    $response['warning'] = "Showing first 200 of $originalOrderCount orders. Use a smaller date range to see all orders.";
                    $response['total_available'] = $originalOrderCount;
                }
                
                // Clear any buffered output and send clean JSON
                ob_clean();
                echo json_encode($response);
                exit;
                
            case 'sync_order':
                $orderId = $_POST['order_id'] ?? '';
                
                if (empty($orderId)) {
                    throw new Exception('Order ID is required');
                }
                
                $logger->info('Manual sync requested for order', ['order_id' => $orderId]);
                
                // Process the order using webhook controller
                $result = $webhookController->processOrder($orderId);
                
                if ($result['success']) {
                    // Clear any buffered output and send clean JSON
                    ob_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Order synced successfully',
                        'netsuite_order_id' => $result['netsuite_order_id'] ?? null
                    ]);
                    exit;
                } else {
                    throw new Exception($result['error'] ?? 'Failed to sync order');
                }
                
            case 'sync_multiple':
                $orderIds = $_POST['order_ids'] ?? [];
                
                if (empty($orderIds) || !is_array($orderIds)) {
                    throw new Exception('Order IDs array is required');
                }
                
                if (count($orderIds) > 10) {
                    throw new Exception('Cannot sync more than 10 orders at once');
                }
                
                $logger->info('Bulk sync requested', ['order_count' => count($orderIds)]);
                
                $results = [];
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($orderIds as $orderId) {
                    try {
                        $result = $webhookController->processOrder($orderId);
                        
                        if ($result['success']) {
                            $results[] = [
                                'order_id' => $orderId,
                                'success' => true,
                                'netsuite_order_id' => $result['netsuite_order_id'] ?? null
                            ];
                            $successCount++;
                        } else {
                            $results[] = [
                                'order_id' => $orderId,
                                'success' => false,
                                'error' => $result['error'] ?? 'Unknown error'
                            ];
                            $errorCount++;
                        }
                    } catch (Exception $e) {
                        $results[] = [
                            'order_id' => $orderId,
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                        $errorCount++;
                    }
                    
                    // Small delay between orders to avoid rate limiting
                    usleep(500000); // 0.5 seconds
                }
                
                // Clear any buffered output and send clean JSON
                ob_clean();
                echo json_encode([
                    'success' => true,
                    'results' => $results,
                    'summary' => [
                        'total' => count($orderIds),
                        'success' => $successCount,
                        'errors' => $errorCount
                    ]
                ]);
                exit;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $logger->error('Order sync interface error', [
            'action' => $_POST['action'] ?? 'unknown',
            'error' => $e->getMessage()
        ]);
        
        // Clear any output buffer to ensure clean JSON
        ob_clean();
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    } catch (Throwable $e) {
        // Catch any fatal errors or other throwables
        $logger->error('Order sync interface fatal error', [
            'action' => $_POST['action'] ?? 'unknown',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        // Clear any output buffer to ensure clean JSON
        if (ob_get_level()) {
            ob_clean();
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'A fatal error occurred: ' . $e->getMessage(),
            'debug_info' => [
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'action' => $_POST['action'] ?? 'unknown'
            ]
        ]);
        exit;
    }
    
    // Restore error handler and clean output buffer
    restore_error_handler();
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    exit;
}

// Get default date range (last 7 days)
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-7 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Synchronization - <?php echo $config['app']['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .sync-status {
            min-width: 120px;
        }
        .order-row {
            transition: background-color 0.2s;
        }
        .order-row:hover {
            background-color: #f8f9fa;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
        .progress-container {
            display: none;
            margin-top: 1rem;
        }
        .order-details {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .bulk-actions {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            display: none;
        }
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-sync-alt me-2"></i>Order Synchronization</h1>
                    <a href="<?php echo UrlHelper::url('index.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Search Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-search me-2"></i>Search Orders</h5>
                    </div>
                    <div class="card-body">
                        <form id="searchForm">
                            <div class="row">
                                <div class="col-md-3">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="start_date" 
                                           value="<?php echo $startDate; ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="end_date" 
                                           value="<?php echo $endDate; ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="orderStatus" class="form-label">Order Status</label>
                                    <select class="form-select" id="orderStatus" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="1">New</option>
                                        <option value="2">Processing</option>
                                        <option value="3">Partial</option>
                                        <option value="4">Shipped</option>
                                        <option value="5">Cancelled</option>
                                        <option value="6">Not Completed</option>
                                        <option value="7">Unpaid</option>
                                        <option value="8">Backordered</option>
                                        <option value="9">Pending Review</option>
                                        <option value="10">Partially Shipped</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-1"></i>Search Orders
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="fas fa-undo me-1"></i>Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong id="selectedCount">0</strong> orders selected
                        </div>
                        <div>
                            <button type="button" class="btn btn-success me-2" onclick="syncSelected()">
                                <i class="fas fa-sync me-1"></i>Sync Selected
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSelection()">
                                <i class="fas fa-times me-1"></i>Clear Selection
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-container" id="progressContainer">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="text-center mt-2">
                        <small id="progressText">Processing...</small>
                    </div>
                </div>

                <!-- Results -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Orders</h5>
                        <div id="resultsInfo" class="text-muted"></div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-container">
                            <table class="table table-hover mb-0" id="ordersTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="40">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Total</th>
                                        <th>3DCart Status</th>
                                        <th>NetSuite Status</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="ordersTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="fas fa-search fa-2x mb-2"></i><br>
                                            Use the search form above to find orders
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div id="loadingText">Loading orders...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Results Modal -->
    <div class="modal fade" id="syncResultsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sync Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="syncResultsBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="refreshOrders()">Refresh Orders</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentOrders = [];
        let selectedOrders = new Set();
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set max date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('endDate').max = today;
            document.getElementById('startDate').max = today;
            
            // Auto-search on page load
            // searchOrders();
        });
        
        // Search form handler
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            searchOrders();
        });
        
        // Select all checkbox handler
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                if (this.checked) {
                    selectedOrders.add(cb.value);
                } else {
                    selectedOrders.delete(cb.value);
                }
            });
            updateBulkActions();
        });
        
        function resetForm() {
            document.getElementById('searchForm').reset();
            document.getElementById('startDate').value = '<?php echo $startDate; ?>';
            document.getElementById('endDate').value = '<?php echo $endDate; ?>';
            clearResults();
        }
        
        function clearResults() {
            document.getElementById('ordersTableBody').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-search fa-2x mb-2"></i><br>
                        Use the search form above to find orders
                    </td>
                </tr>
            `;
            document.getElementById('resultsInfo').textContent = '';
            clearSelection();
        }
        
        function searchOrders() {
            const formData = new FormData(document.getElementById('searchForm'));
            formData.append('action', 'fetch_orders');
            
            showLoading('Searching orders...');
            
            fetch('order-sync.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
                
                hideLoading();
                
                if (data.success) {
                    currentOrders = data.orders;
                    displayOrders(data.orders);
                    let infoText = `Found ${data.total_count} orders (${data.date_range})`;
                    if (data.warning) {
                        infoText += ` - ${data.warning}`;
                    }
                    document.getElementById('resultsInfo').textContent = infoText;
                } else {
                    // Handle authentication errors
                    if (data.redirect) {
                        showError('Session expired. Redirecting to login...');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    } else {
                        showError('Search failed: ' + data.error);
                    }
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Search error:', error);
                showError('Network error: ' + error.message);
            });
        }
        
        function displayOrders(orders) {
            const tbody = document.getElementById('ordersTableBody');
            
            if (orders.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i><br>
                            No orders found for the selected criteria
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = orders.map(order => {
                const customerName = order.customer_name || 'Unknown';
                const customerCompany = order.customer_company ? ` (${order.customer_company})` : '';
                const orderDate = new Date(order.order_date).toLocaleDateString();
                const orderTotal = parseFloat(order.order_total).toFixed(2);
                
                let netSuiteStatus = '';
                if (order.in_netsuite) {
                    const syncDate = order.sync_date ? new Date(order.sync_date).toLocaleDateString() : '';
                    netSuiteStatus = `
                        <span class="badge bg-success status-badge">
                            <i class="fas fa-check me-1"></i>Synced
                        </span>
                        <div class="order-details small text-muted mt-1">
                            <div><strong>NS ID:</strong> ${order.netsuite_tranid || order.netsuite_id}</div>
                            ${syncDate ? `<div><strong>Date:</strong> ${syncDate}</div>` : ''}
                            ${order.netsuite_status ? `<div><strong>Status:</strong> ${order.netsuite_status}</div>` : ''}
                        </div>
                    `;
                } else if (order.sync_error) {
                    netSuiteStatus = `
                        <span class="badge bg-danger status-badge">
                            <i class="fas fa-exclamation-triangle me-1"></i>Error
                        </span>
                        <div class="order-details small text-danger mt-1">
                            ${order.sync_error}
                        </div>
                    `;
                } else {
                    netSuiteStatus = `
                        <span class="badge bg-warning status-badge">
                            <i class="fas fa-clock me-1"></i>Not Synced
                        </span>
                        <div class="order-details small text-muted mt-1">
                            Ready to sync to NetSuite
                        </div>
                    `;
                }
                
                let actions = '';
                if (order.can_sync) {
                    actions = `
                        <button class="btn btn-sm btn-primary" onclick="syncOrder('${order.order_id}')">
                            <i class="fas fa-sync me-1"></i>Sync
                        </button>
                    `;
                } else {
                    actions = `
                        <span class="text-muted small">Already synced</span>
                    `;
                }
                
                return `
                    <tr class="order-row">
                        <td>
                            ${order.can_sync ? `<input type="checkbox" class="form-check-input order-checkbox" value="${order.order_id}" onchange="updateSelection()">` : ''}
                        </td>
                        <td>
                            <strong>#${order.order_id}</strong>
                        </td>
                        <td>${orderDate}</td>
                        <td>
                            <div>${customerName}${customerCompany}</div>
                        </td>
                        <td>$${orderTotal}</td>
                        <td>
                            <span class="badge bg-info status-badge">${order.order_status_name}</span>
                        </td>
                        <td class="sync-status">${netSuiteStatus}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            }).join('');
            
            clearSelection();
        }
        
        function updateSelection() {
            selectedOrders.clear();
            document.querySelectorAll('.order-checkbox:checked').forEach(cb => {
                selectedOrders.add(cb.value);
            });
            
            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.order-checkbox');
            const checkedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            if (checkedCheckboxes.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCheckboxes.length === allCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            selectedCount.textContent = selectedOrders.size;
            
            if (selectedOrders.size > 0) {
                bulkActions.style.display = 'block';
            } else {
                bulkActions.style.display = 'none';
            }
        }
        
        function clearSelection() {
            selectedOrders.clear();
            document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('selectAll').checked = false;
            document.getElementById('selectAll').indeterminate = false;
            updateBulkActions();
        }
        
        function syncOrder(orderId) {
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Syncing...';
            button.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'sync_order');
            formData.append('order_id', orderId);
            
            fetch('order-sync.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (data.success) {
                    showSuccess(`Order #${orderId} synced successfully!`);
                    // Refresh the orders to show updated status
                    setTimeout(() => searchOrders(), 1000);
                } else {
                    // Handle authentication errors
                    if (data.redirect) {
                        showError('Session expired. Redirecting to login...');
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    } else {
                        showError(`Failed to sync order #${orderId}: ${data.error}`);
                    }
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Sync error:', error);
                showError(`Network error: ${error.message}`);
                button.innerHTML = originalContent;
                button.disabled = false;
            });
        }
        
        function syncSelected() {
            if (selectedOrders.size === 0) {
                showError('Please select orders to sync');
                return;
            }
            
            if (selectedOrders.size > 10) {
                showError('Cannot sync more than 10 orders at once');
                return;
            }
            
            const orderIds = Array.from(selectedOrders);
            const formData = new FormData();
            formData.append('action', 'sync_multiple');
            formData.append('order_ids', JSON.stringify(orderIds));
            
            showProgress(0, `Syncing ${orderIds.length} orders...`);
            
            fetch('order-sync.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress();
                
                if (data.success) {
                    showSyncResults(data.results, data.summary);
                    clearSelection();
                } else {
                    showError(`Bulk sync failed: ${data.error}`);
                }
            })
            .catch(error => {
                hideProgress();
                showError(`Network error: ${error.message}`);
            });
        }
        
        function showSyncResults(results, summary) {
            const modalBody = document.getElementById('syncResultsBody');
            
            let html = `
                <div class="alert alert-info">
                    <h6>Sync Summary</h6>
                    <ul class="mb-0">
                        <li>Total Orders: ${summary.total}</li>
                        <li class="text-success">Successfully Synced: ${summary.success}</li>
                        <li class="text-danger">Failed: ${summary.errors}</li>
                    </ul>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            results.forEach(result => {
                const statusBadge = result.success 
                    ? '<span class="badge bg-success">Success</span>'
                    : '<span class="badge bg-danger">Failed</span>';
                
                const details = result.success 
                    ? `NetSuite ID: ${result.netsuite_order_id || 'N/A'}`
                    : `Error: ${result.error}`;
                
                html += `
                    <tr>
                        <td>#${result.order_id}</td>
                        <td>${statusBadge}</td>
                        <td>${details}</td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            modalBody.innerHTML = html;
            new bootstrap.Modal(document.getElementById('syncResultsModal')).show();
        }
        
        function refreshOrders() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('syncResultsModal'));
            modal.hide();
            searchOrders();
        }
        
        function showLoading(text = 'Loading...') {
            document.getElementById('loadingText').textContent = text;
            new bootstrap.Modal(document.getElementById('loadingModal')).show();
        }
        
        function hideLoading() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('loadingModal'));
            if (modal) modal.hide();
        }
        
        function showProgress(percent, text) {
            const container = document.getElementById('progressContainer');
            const bar = container.querySelector('.progress-bar');
            const textEl = document.getElementById('progressText');
            
            container.style.display = 'block';
            bar.style.width = percent + '%';
            textEl.textContent = text;
        }
        
        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
        }
        
        function showSuccess(message) {
            showAlert(message, 'success');
        }
        
        function showError(message) {
            showAlert(message, 'danger');
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>