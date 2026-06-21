<?php
require_once 'templates/header.php';
check_login();

// Management Roles that see the modern dashboard
$mgmt_roles = [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER];
$is_mgmt = in_array($_SESSION['role'] ?? '', $mgmt_roles);

// Filters for Management
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch basic summary data
$user_count = fetch_one("SELECT COUNT(id) as total FROM users WHERE isDelete = 0")['total'];
$customer_count = fetch_one("SELECT COUNT(id) as total FROM customers WHERE isDelete = 0")['total'];
$product_count = fetch_one("SELECT COUNT(id) as total FROM products WHERE isDelete = 0")['total'];
$draft_count = fetch_one("SELECT COUNT(id) as total FROM sales_drafts WHERE status = 'Draft' AND isDelete = 0")['total'];

// Role-specific data fetching
if ($is_mgmt) {
    // Financial Metrics (Period Specific)
    $revenue = fetch_one("SELECT SUM(grand_total) as total FROM sales_drafts WHERE status = 'Confirmed' AND isDelete = 0 AND DATE(confirmed_at) BETWEEN ? AND ?", [$start_date, $end_date])['total'] ?? 0;
    $collection = fetch_one("SELECT SUM(amount) as total FROM transactions WHERE type = 'Credit' AND isDelete = 0 AND DATE(created_at) BETWEEN ? AND ?", [$start_date, $end_date])['total'] ?? 0;
    
    // Global Health Metrics
    $stock_value = fetch_one("SELECT SUM(stock_qty * tp_rate) as total FROM products WHERE isDelete = 0")['total'] ?? 0;
    $total_owed = fetch_one("SELECT SUM(balance) as total FROM customers WHERE isDelete = 0")['total'] ?? 0;
    
    // Delivery Funnel
    $funnel_raw = fetch_all("SELECT delivery_status, COUNT(id) as count FROM sales_drafts WHERE isDelete = 0 AND status = 'Confirmed' GROUP BY delivery_status");
    $funnel = ['Pending' => 0, 'Loading' => 0, 'In Transit' => 0, 'Delivered' => 0, 'Failed' => 0, 'Returned' => 0];
    foreach($funnel_raw as $fr) { $funnel[$fr['delivery_status']] = $fr['count']; }

    // Active Trucks
    $active_trucks = fetch_all("SELECT tl.*, 
        (SELECT SUM(s.grand_total) FROM truck_load_items tli JOIN sales_drafts s ON tli.invoice_id = s.id WHERE tli.truck_load_id = tl.id AND tli.isDelete = 0) as load_value, 
        (SELECT COUNT(tli.id) FROM truck_load_items tli WHERE tli.truck_load_id = tl.id AND tli.isDelete = 0) as invoice_count 
        FROM truck_loads tl WHERE tl.status IN ('Loaded', 'Departed') AND tl.isDelete = 0");

    // Inventory Health
    $low_stock = fetch_all("SELECT name, stock_qty FROM products WHERE stock_qty <= 10 AND isDelete = 0 ORDER BY stock_qty ASC LIMIT 5");
}
?>

<div class="row align-items-center mb-4">
    <div class="col-md-5">
        <h2 class="mb-0">Dashboard</h2>
        <p class="text-muted">Welcome back, distribution insight at a glance.</p>
    </div>
    <?php if ($is_mgmt): ?>
    <div class="col-md-7">
        <div class="d-flex flex-wrap gap-2 justify-content-end align-items-end">
            <form method="GET" class="d-flex gap-2 align-items-end mb-0">
                <div class="col-auto">
                    <label class="small fw-bold">From</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-auto">
                    <label class="small fw-bold">To</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync"></i></a>
                </div>
            </form>
            <div class="col-auto">
                <a href="viewreport.php" class="btn btn-dark btn-sm"><i class="fas fa-file-invoice-dollar me-1"></i> Detailed Reports</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($is_mgmt): ?>
    <!-- MANAGEMENT DASHBOARD -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-uppercase small mb-0">Total Revenue</h6>
                        <i class="fas fa-chart-line opacity-50"></i>
                    </div>
                    <h3 class="mb-1"><?php echo format_currency($revenue); ?></h3>
                    <p class="small mb-0 opacity-75">Invoices in period</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-uppercase small mb-0">Total Collection</h6>
                        <i class="fas fa-hand-holding-usd opacity-50"></i>
                    </div>
                    <h3 class="mb-1"><?php echo format_currency($collection); ?></h3>
                    <p class="small mb-0 opacity-75">Payments in period</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-uppercase small mb-0">Stock Valuation</h6>
                        <i class="fas fa-warehouse opacity-50"></i>
                    </div>
                    <h3 class="mb-1"><?php echo format_currency($stock_value); ?></h3>
                    <p class="small mb-0 opacity-75">Current market value</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-uppercase small mb-0">Receivables</h6>
                        <i class="fas fa-users-cog opacity-50"></i>
                    </div>
                    <h3 class="mb-1"><?php echo format_currency($total_owed); ?></h3>
                    <p class="small mb-0 opacity-75">Current debt on market</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Logistics Monitoring -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Logistics & Active Trucks</h5>
                    <span class="badge bg-soft-primary text-primary"><?php echo count($active_trucks); ?> Active</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Truck Info</th>
                                    <th>Status</th>
                                    <th class="text-center">Invoices</th>
                                    <th class="text-end">Load Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($active_trucks)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">No active trucks on road.</td></tr>
                                <?php endif; ?>
                                <?php foreach($active_trucks as $truck): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo $truck['truck_no']; ?></div>
                                        <div class="small text-muted"><?php echo $truck['driver_name']; ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $truck['status'] == 'Departed' ? 'primary' : 'warning'; ?>-soft text-<?php echo $truck['status'] == 'Departed' ? 'primary' : 'warning'; ?> text-uppercase" style="font-size: 10px;">
                                            <?php echo $truck['status']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo $truck['invoice_count']; ?></td>
                                    <td class="text-end fw-bold"><?php echo format_currency($truck['load_value']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mt-4 mb-3 small fw-bold text-uppercase text-muted">Delivery Funnel (Current)</h6>
                    <div class="row text-center g-2">
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <div class="h4 mb-0"><?php echo $funnel['Pending']; ?></div>
                                <div class="small text-muted">Pending</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-light">
                                <div class="h4 mb-0"><?php echo $funnel['Loading']; ?></div>
                                <div class="small text-muted">Loading</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-primary text-white">
                                <div class="h4 mb-0"><?php echo $funnel['In Transit']; ?></div>
                                <div class="small">In Transit</div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="p-2 border rounded bg-success text-white">
                                <div class="h4 mb-0"><?php echo $funnel['Delivered']; ?></div>
                                <div class="small">Delivered</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Health -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Inventory Health</h5>
                    <a href="modules/reports/inventory.php" class="btn btn-link btn-sm p-0"><i class="fas fa-expand"></i></a>
                </div>
                <div class="card-body">
                    <label class="small text-muted mb-2">Low Stock Alerts (<= 10)</label>
                    <ul class="list-group list-group-flush">
                        <?php foreach($low_stock as $ls): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span><?php echo $ls['name']; ?></span>
                            <span class="badge bg-danger rounded-pill"><?php echo $ls['stock_qty']; ?></span>
                        </li>
                        <?php endforeach; ?>
                        <?php if(empty($low_stock)): ?>
                            <li class="list-group-item text-center text-success py-3">All products well stocked.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0">Quick Insights</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Active Customers</span>
                        <strong><?php echo $customer_count; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Products</span>
                        <strong><?php echo $product_count; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Sales Reps</span>
                        <strong><?php echo fetch_one("SELECT COUNT(id) as total FROM users WHERE role = 'Sales Representative' AND isDelete = 0")['total']; ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- DASHBOARD FOR OTHER ROLES (SR, CUSTOMER) -->
    <div class="row g-3">
        <?php if (($_SESSION['role'] ?? '') == ROLE_SR): ?>
        <div class="col-md-4">
            <div class="card bg-warning text-dark shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title text-uppercase small">My Pending Drafts</h6>
                    <h2 class="mb-0"><?php echo fetch_one("SELECT COUNT(id) as total FROM sales_drafts WHERE status = 'Draft' AND created_by = ? AND isDelete = 0", [$_SESSION['user_id']])['total']; ?></h2>
                </div>
                <div class="card-footer bg-transparent border-0 text-end">
                    <a href="modules/sales/index.php" class="text-dark text-decoration-none small">Manage Drafts <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title text-uppercase small">Create New Sale</h6>
                    <p class="small mb-2">Start a new distribution order</p>
                    <a href="modules/sales/pos.php" class="btn btn-light btn-sm mt-2">Open POS</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($_SESSION['role'] ?? '') == ROLE_CUSTOMER): ?>
        <div class="col-md-6">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3"><strong>My Account Summary</strong></div>
                <div class="card-body">
                    <?php
                    $cust = fetch_one("SELECT * FROM customers WHERE user_id = ?", [$_SESSION['user_id']]);
                    ?>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Account Name:</span>
                        <strong><?php echo $cust['name']; ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Customer Type:</span>
                        <span class="badge bg-secondary"><?php echo $cust['type']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Current Balance:</span>
                        <h3 class="text-primary mb-0"><?php echo format_currency($cust['balance']); ?></h3>
                    </div>
                    <hr>
                    <a href="modules/sales/index.php" class="btn btn-outline-primary w-100">View My Invoices</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
    /* Modern Dashboard Styling */
    .bg-soft-primary { background-color: rgba(13, 110, 253, 0.1); }
    .text-primary { color: #0d6efd !important; }
    .bg-primary-soft { background-color: rgba(13, 110, 253, 0.1); }
    .text-primary { color: #0d6efd; }
    .bg-warning-soft { background-color: rgba(255, 193, 7, 0.1); }
    .text-warning { color: #ffc107; }
    .card { transition: transform 0.2s; border-radius: 12px; }
    .card:hover { transform: translateY(-3px); }
</style>

<?php require_once 'templates/footer.php'; ?>
