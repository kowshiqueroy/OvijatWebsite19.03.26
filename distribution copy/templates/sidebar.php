<div class="bg-dark border-right text-white" id="sidebar-wrapper">
    <div class="sidebar-heading p-3 border-bottom bg-primary text-center">
        <strong><?php echo $company['name']; ?></strong>
    </div>
    <div class="list-group list-group-flush sidebar-menu">
        <?php 
        $valid_roles = [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER, ROLE_VIEWER];
        if (isset($_SESSION['role']) && in_array($_SESSION['role'], $valid_roles)): 
        ?>
        <a href="<?php echo BASE_URL; ?>index.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-3">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>

        <?php if (in_array($_SESSION['role'], [ROLE_ADMIN])): ?>
        <div class="p-2 text-muted small text-uppercase">Admin</div>
        <a href="<?php echo BASE_URL; ?>modules/users/index.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-users me-2"></i> User Management
        </a>
        <a href="<?php echo BASE_URL; ?>modules/admin/settings.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-cog me-2"></i> Company Settings
        </a>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER])): ?>
        <div class="p-2 text-muted small text-uppercase">Masters</div>
        <a href="<?php echo BASE_URL; ?>modules/products/categories.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-tags me-2"></i> Categories
        </a>
        <a href="<?php echo BASE_URL; ?>modules/products/index.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-box me-2"></i> Products
        </a>
        <a href="<?php echo BASE_URL; ?>modules/customers/index.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-user-friends me-2"></i> Customers
        </a>
        <?php endif; ?>

        <div class="p-2 text-muted small text-uppercase">Sales</div>
        <?php if ($_SESSION['role'] != ROLE_VIEWER): ?>
        <a href="<?php echo BASE_URL; ?>modules/sales/pos.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-cash-register me-2"></i> POS / New Sale
        </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>modules/sales/index.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-file-invoice me-2"></i> All Sales
        </a>

        <?php if (in_array($_SESSION['role'], [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
        <a href="<?php echo BASE_URL; ?>modules/sales/confirm_list.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-check-circle me-2"></i> Confirm Drafts
        </a>
        <?php endif; ?>

        <div class="p-2 text-muted small text-uppercase">Accounts</div>
        <a href="<?php echo BASE_URL; ?>modules/reports/comprehensive.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-chart-line me-2"></i> Comprehensive Reports
        </a>
        <a href="<?php echo BASE_URL; ?>modules/reports/transactions.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-history me-2"></i> Transactions
        </a>

        <div class="p-2 text-muted small text-uppercase">My Account</div>
        <a href="<?php echo BASE_URL; ?>profile.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-user-circle me-2"></i> My Profile
        </a>
        <a href="<?php echo BASE_URL; ?>change_password.php" class="list-group-item list-group-item-action bg-dark text-white border-0 py-2">
            <i class="fas fa-key me-2"></i> Change Password
        </a>

        <div class="p-3 mt-4">
             <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline-light btn-sm w-100">Logout</a>
        </div>
        <?php endif; ?>
    </div>
</div>
