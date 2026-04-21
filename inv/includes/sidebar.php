<!-- Sidebar -->
<nav id="sidebar" class="bg-dark text-white">
    <div class="sidebar-header p-4">
        <h4 class="mb-0 text-primary fw-bold"><?php echo defined('APP_NAME') ? APP_NAME : 'Inventory'; ?></h4>
        <small class="text-muted">Management System</small>
    </div>

    <ul class="list-unstyled components p-3">
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <a href="<?php echo BASE_URL; ?>modules/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        </li>

        <?php if (!hasRole('Viewer')): ?>
        <li class="nav-label text-muted small text-uppercase fw-bold mt-3 mb-2 px-3">Inventory</li>
        <li>
            <a href="#productSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-boxes me-2"></i> Products
            </a>
            <ul class="collapse list-unstyled ps-4" id="productSubmenu">
                <li><a href="<?php echo BASE_URL; ?>modules/products/list.php">Product List</a></li>
                <?php if (hasRole(['Admin', 'Manager'])): ?>
                <li><a href="<?php echo BASE_URL; ?>modules/products/categories.php">Categories</a></li>
                <?php endif; ?>
            </ul>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/inventory/stock.php"><i class="fas fa-warehouse me-2"></i> Current Stock</a>
        </li>
        <?php endif; ?>

        <?php if (hasRole(['Admin', 'Manager'])): ?>
        <li>
            <a href="#stockSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-plus-circle me-2"></i> Stock Movement
            </a>
            <ul class="collapse list-unstyled ps-4" id="stockSubmenu">
                <li><a href="<?php echo BASE_URL; ?>modules/inventory/stock_in.php">Stock IN (Purchase)</a></li>
                <li><a href="<?php echo BASE_URL; ?>modules/inventory/stock_in_list.php">Stock IN List</a></li>
                <li><a href="<?php echo BASE_URL; ?>modules/inventory/transfer.php">Stock Transfer</a></li>
                <li><a href="<?php echo BASE_URL; ?>modules/inventory/stock_out.php">Manual OUT</a></li>
            </ul>
        </li>
        <?php endif; ?>

        <li class="nav-label text-muted small text-uppercase fw-bold mt-3 mb-2 px-3">Sales</li>
        <li>
            <a href="#salesSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-shopping-cart me-2"></i> Sales Module
            </a>
            <ul class="collapse list-unstyled ps-4" id="salesSubmenu">
                <?php if (hasRole(['Admin', 'Manager'])): ?>
                <li><a href="<?php echo BASE_URL; ?>modules/sales/create.php">New Sale</a></li>
                <?php endif; ?>
                <li><a href="<?php echo BASE_URL; ?>modules/sales/list.php">View Sales</a></li>
                <?php if (hasRole(['Admin', 'Accountant'])): ?>
                <li><a href="<?php echo BASE_URL; ?>modules/sales/pending.php">Pending Approvals</a></li>
                <?php endif; ?>
            </ul>
        </li>

        <?php if (hasRole(['Admin', 'Accountant', 'Manager'])): ?>
        <li class="nav-label text-muted small text-uppercase fw-bold mt-3 mb-2 px-3">Finance & Partners</li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/customers/list.php"><i class="fas fa-users me-2"></i> Customers</a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/suppliers/list.php"><i class="fas fa-truck me-2"></i> Suppliers</a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/finance/expenses.php"><i class="fas fa-wallet me-2"></i> Expenses</a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/finance/ledger.php"><i class="fas fa-file-invoice-dollar me-2"></i> Ledgers</a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/finance/payments.php"><i class="fas fa-money-bill-wave me-2"></i> Payments</a>
        </li>
        <?php endif; ?>

        <li class="nav-label text-muted small text-uppercase fw-bold mt-3 mb-2 px-3">Reports</li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/reports/all.php"><i class="fas fa-chart-line me-2"></i> Reports</a>
        </li>
        
        <?php if (hasRole('Admin')): ?>
        <li class="nav-label text-muted small text-uppercase fw-bold mt-3 mb-2 px-3">System</li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/branches/list.php"><i class="fas fa-code-branch me-2"></i> Branch Management</a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/users/list.php"><i class="fas fa-user-shield me-2"></i> User Management</a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/settings/config.php"><i class="fas fa-cog me-2"></i> Settings</a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>modules/system/logs.php"><i class="fas fa-history me-2"></i> Audit Logs</a>
        </li>
        <?php endif; ?>
        
        <li class="mt-5">
            <a href="<?php echo BASE_URL; ?>actions/auth.php?action=logout" class="text-danger">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </li>
    </ul>
</nav>
