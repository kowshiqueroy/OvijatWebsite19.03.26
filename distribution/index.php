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
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eef2ff;color:#6366f1;">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value" style="color:#6366f1;"><?php echo format_currency($revenue); ?></div>
                <div class="small text-muted mt-1">Invoices in period</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#f0fdf4;color:#16a34a;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div class="stat-label">Collections</div>
                <div class="stat-value" style="color:#16a34a;"><?php echo format_currency($collection); ?></div>
                <div class="small text-muted mt-1">Payments in period</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff;color:#2563eb;">
                    <i class="fa-solid fa-warehouse"></i>
                </div>
                <div class="stat-label">Stock Value</div>
                <div class="stat-value" style="color:#2563eb;"><?php echo format_currency($stock_value); ?></div>
                <div class="small text-muted mt-1">At TP rate</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff7ed;color:#ea580c;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="stat-label">Receivables</div>
                <div class="stat-value" style="color:#ea580c;"><?php echo format_currency($total_owed); ?></div>
                <div class="small text-muted mt-1">Outstanding balance</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Logistics Monitoring -->
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-truck me-2 text-accent"></i>Active Trucks</span>
                    <span class="badge" style="background:#eef2ff;color:#6366f1;"><?php echo count($active_trucks); ?> Active</span>
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
                                        <?php $ts = $truck['status'] == 'Departed' ? ['#ede9fe','#4c1d95'] : ['#fef3c7','#92400e']; ?>
                                        <span class="badge" style="background:<?php echo $ts[0]; ?>;color:<?php echo $ts[1]; ?>;"><?php echo $truck['status']; ?></span>
                                    </td>
                                    <td class="text-center"><?php echo $truck['invoice_count']; ?></td>
                                    <td class="text-end fw-bold"><?php echo format_currency($truck['load_value']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="small fw-bold text-uppercase text-muted mt-4 mb-2">Delivery Funnel</p>
                    <div class="row g-2">
                        <?php
                        $funnel_items = [
                            'Pending'    => ['#fef3c7','#92400e'],
                            'Loading'    => ['#e0f2fe','#0c4a6e'],
                            'In Transit' => ['#ede9fe','#4c1d95'],
                            'Delivered'  => ['#dcfce7','#14532d'],
                        ];
                        foreach ($funnel_items as $label => [$bg, $color]):
                        ?>
                        <div class="col-6 col-xl-3">
                            <div class="funnel-box" style="background:<?php echo $bg; ?>;border-color:<?php echo $bg; ?>;">
                                <div class="count" style="color:<?php echo $color; ?>"><?php echo $funnel[$label] ?? 0; ?></div>
                                <div class="label" style="color:<?php echo $color; ?>"><?php echo $label; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Health -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-layer-group me-2 text-accent"></i>Inventory Health</span>
                    <a href="modules/reports/stock_status.php" class="btn btn-sm btn-outline-secondary py-0"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
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

            <div class="card">
                <div class="card-header"><i class="fa-solid fa-bolt me-2 text-accent"></i>Quick Insights</div>
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
    <!-- SR / CUSTOMER ROLE DASHBOARD -->
    <div class="row g-3">
        <?php if (($_SESSION['role'] ?? '') == ROLE_SR): ?>
        <?php $pending_drafts = fetch_one("SELECT COUNT(id) as total FROM sales_drafts WHERE status='Draft' AND created_by=? AND isDelete=0", [$_SESSION['user_id']])['total']; ?>
        <div class="col-sm-6 col-md-4">
            <div class="stat-card h-100" style="border-left:4px solid #f59e0b;">
                <div class="stat-icon" style="background:#fffbeb;color:#d97706;">
                    <i class="fa-solid fa-file-pen"></i>
                </div>
                <div class="stat-label">Pending Drafts</div>
                <div class="stat-value"><?php echo $pending_drafts; ?></div>
                <a href="modules/sales/index.php?status=Draft" class="btn btn-sm btn-warning mt-2">
                    Manage <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        <div class="col-sm-6 col-md-4">
            <div class="stat-card h-100" style="border-left:4px solid #6366f1;">
                <div class="stat-icon" style="background:#eef2ff;color:#6366f1;">
                    <i class="fa-solid fa-cash-register"></i>
                </div>
                <div class="stat-label">New Sale</div>
                <div class="stat-value" style="font-size:18px;margin-bottom:8px;">POS Terminal</div>
                <a href="modules/sales/pos.php" class="btn btn-sm btn-primary">
                    <i class="fa-solid fa-plus me-1"></i> Open POS
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($_SESSION['role'] ?? '') == ROLE_CUSTOMER): ?>
        <?php $cust = fetch_one("SELECT * FROM customers WHERE user_id = ?", [$_SESSION['user_id']]); ?>
        <div class="col-md-6 col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="fa-solid fa-circle-user me-2 text-accent"></i>My Account</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Name</span>
                        <strong><?php echo htmlspecialchars($cust['name']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Type</span>
                        <span class="badge bg-secondary"><?php echo $cust['type']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-3">
                        <span class="text-muted">Balance</span>
                        <span class="fw-bold fs-5 text-accent"><?php echo format_currency($cust['balance']); ?></span>
                    </div>
                    <a href="modules/sales/index.php" class="btn btn-primary w-100">
                        <i class="fa-solid fa-file-invoice me-2"></i>My Orders
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once 'templates/footer.php'; ?>
