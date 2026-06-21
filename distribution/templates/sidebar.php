<div class="bg-dark border-right text-white" id="sidebar-wrapper">
    <div class="sidebar-heading p-3 border-bottom text-center" style="background: linear-gradient(135deg,#6366f1,#8b5cf6);">
        <strong><?php echo htmlspecialchars($company['name'] ?? APP_NAME); ?></strong>
    </div>
    <div class="list-group list-group-flush sidebar-menu" style="overflow-y:auto; max-height: calc(100vh - 60px);">
    <?php
    $valid_roles = [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER, ROLE_VIEWER];
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], $valid_roles)):
        $role = $_SESSION['role'];
        $current = basename($_SERVER['PHP_SELF']);
        $is_viewer = ($role === ROLE_VIEWER);
        function sidebar_link($url, $icon, $label, $extra_class = '') {
            $active = (strpos($_SERVER['REQUEST_URI'], $url) !== false) ? 'active' : '';
            echo "<a href=\"".BASE_URL."{$url}\" class=\"list-group-item list-group-item-action bg-dark text-white border-0 py-2 {$active} {$extra_class}\"><i class=\"fas fa-{$icon} me-2 opacity-75\"></i>{$label}</a>";
        }
        function sidebar_heading($label, $icon = '') {
            echo "<div class=\"px-3 pt-3 pb-1 text-uppercase\" style=\"font-size:.65rem;letter-spacing:.08em;color:#6366f1;font-weight:700;\">".($icon ? "<i class='fas fa-$icon me-1'></i>" : "")."$label</div>";
        }
    ?>

    <!-- DASHBOARD -->
    <?php sidebar_link('index.php', 'tachometer-alt', 'Dashboard'); ?>

    <!-- ADMIN SECTION -->
    <?php if ($role === ROLE_ADMIN): ?>
    <?php sidebar_heading('Admin', 'shield-alt'); ?>
    <?php sidebar_link('modules/users/index.php', 'users', 'User Management'); ?>
    <?php sidebar_link('modules/admin/settings.php', 'cog', 'Company Settings'); ?>
    <?php endif; ?>

    <!-- INVENTORY SECTION -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER])): ?>
    <?php sidebar_heading('Inventory', 'boxes'); ?>
    <?php sidebar_link('modules/products/index.php', 'box', 'Product List'); ?>
    <?php sidebar_link('modules/products/categories.php', 'tags', 'Categories'); ?>
    <?php sidebar_link('modules/products/damages.php', 'biohazard', 'Stock Damages'); ?>
    <?php sidebar_link('modules/expiry/index.php', 'calendar-times', 'Expiry Management'); ?>
    <?php sidebar_link('modules/purchase/index.php', 'truck-loading', 'Purchase Orders'); ?>
    <?php endif; ?>

    <!-- SUPPLIERS SECTION -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
    <?php sidebar_heading('Suppliers', 'industry'); ?>
    <?php sidebar_link('modules/suppliers/index.php', 'building', 'Supplier List'); ?>
    <?php endif; ?>

    <!-- RETURNS SECTION -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
    <?php sidebar_heading('Returns', 'undo'); ?>
    <?php sidebar_link('modules/returns/index.php', 'exchange-alt', 'Return Requests'); ?>
    <?php endif; ?>

    <!-- EXPENSES SECTION -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
    <?php sidebar_heading('Expenses', 'wallet'); ?>
    <?php sidebar_link('modules/expenses/index.php', 'money-bill-wave', 'All Expenses'); ?>
    <?php endif; ?>

    <!-- LOGISTICS -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
    <?php sidebar_heading('Logistics', 'truck'); ?>
    <?php sidebar_link('modules/delivery/index.php', 'truck', 'Truck Loads'); ?>
    <?php sidebar_link('modules/delivery/driver_sheet.php', 'file-alt', 'Driver Manifest'); ?>
    <?php endif; ?>

    <!-- SALES -->
    <?php sidebar_heading('Sales', 'cash-register'); ?>
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER])): ?>
    <?php sidebar_link('modules/sales/pos.php', 'cash-register', 'POS / New Sale'); ?>
    <?php endif; ?>
    <?php sidebar_link('modules/sales/index.php', 'file-invoice', ($role === ROLE_CUSTOMER || $role === ROLE_SR) ? 'My Sales' : 'All Sales'); ?>
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
    <?php sidebar_link('modules/sales/confirm_list.php', 'check-circle', 'Confirm Drafts'); ?>
    <?php endif; ?>

    <!-- MASTERS -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER])): ?>
    <?php sidebar_heading('Masters', 'database'); ?>
    <?php sidebar_link('modules/customers/index.php', 'user-friends', 'Customers'); ?>
    <?php if ($role === ROLE_ACCOUNTANT || $role === ROLE_VIEWER): ?>
    <?php sidebar_link('modules/products/index.php', 'box', 'Products'); ?>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ACCOUNTS (Tally-style) -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT]) || ($is_viewer && can_see_section('accounts'))): ?>
    <?php sidebar_heading('Accounts', 'book'); ?>
    <?php sidebar_link('modules/accounts/ledger.php', 'book-open', 'Customer Ledger'); ?>
    <?php sidebar_link('modules/accounts/payables.php', 'file-invoice-dollar', 'Supplier Payables'); ?>
    <?php sidebar_link('modules/accounts/collection_sheet.php', 'hand-holding-usd', 'Collection Sheet'); ?>
    <?php sidebar_link('modules/accounts/journal.php', 'journal-whills', 'Journal Entries'); ?>
    <?php sidebar_link('modules/accounts/chart_of_accounts.php', 'sitemap', 'Chart of Accounts'); ?>
    <?php sidebar_link('modules/accounts/ledger_account.php', 'list-alt', 'Account Ledger'); ?>
    <?php sidebar_link('modules/accounts/trial_balance.php', 'balance-scale', 'Trial Balance'); ?>
    <?php sidebar_link('modules/accounts/profit_loss.php', 'chart-line', 'Profit & Loss'); ?>
    <?php sidebar_link('modules/accounts/balance_sheet.php', 'landmark', 'Balance Sheet'); ?>
    <?php sidebar_link('modules/reports/transactions.php', 'history', 'All Transactions'); ?>
    <?php endif; ?>

    <!-- REPORTS -->
    <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]) ||
              ($is_viewer && (can_see_section('stock_report') || can_see_section('inventory_report') || can_see_section('comprehensive')))): ?>
    <?php sidebar_heading('Reports', 'chart-bar'); ?>
    <?php if (!$is_viewer || can_see_section('stock_report')): ?>
    <?php sidebar_link('modules/reports/stock_status.php', 'layer-group', 'Stock Status'); ?>
    <?php endif; ?>
    <?php if (!$is_viewer || can_see_section('inventory_report')): ?>
    <?php sidebar_link('modules/reports/inventory.php', 'warehouse', 'Inventory Report'); ?>
    <?php endif; ?>
    <?php if (!$is_viewer || can_see_section('comprehensive')): ?>
    <?php sidebar_link('modules/reports/comprehensive.php', 'chart-line', 'Comprehensive Reports'); ?>
    <?php endif; ?>
    <?php if (!$is_viewer): ?>
    <?php sidebar_link('viewreport.php', 'file-invoice-dollar', 'Detailed Reports'); ?>
    <?php endif; ?>
    <?php endif; ?>

    <!-- EXECUTIVE / DMD -->
    <?php if (can_access_dmd()): ?>
    <?php sidebar_heading('Executive', 'user-shield'); ?>
    <?php sidebar_link('modules/dmd_dashboard/index.php', 'user-shield', 'DMD Dashboard'); ?>
    <?php endif; ?>

    <!-- SETTINGS (always visible) -->
    <?php sidebar_heading('Settings', 'cog'); ?>
    <?php sidebar_link('profile.php', 'user-circle', 'My Profile'); ?>
    <?php sidebar_link('change_password.php', 'key', 'Change Password'); ?>

    <div class="p-3 mt-2">
        <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline-light btn-sm w-100">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>

    <?php endif; ?>
    </div>
</div>
