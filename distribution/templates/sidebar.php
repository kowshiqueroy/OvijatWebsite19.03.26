<?php
$valid_roles = [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_SR, ROLE_CUSTOMER, ROLE_VIEWER];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $valid_roles)) return;

$role      = $_SESSION['role'];
$is_viewer = ($role === ROLE_VIEWER);
$uri       = $_SERVER['REQUEST_URI'] ?? '';
$_sec_id   = 0; // incremented per section

/**
 * Render a nav link inside a section.
 */
if (!function_exists('sl')) {
    function sl($url, $icon, $label) {
        global $uri;
        $active = (strpos($uri, $url) !== false) ? ' active' : '';
        echo '<a href="' . BASE_URL . $url . '" class="sidebar-link' . $active . '" title="' . htmlspecialchars($label) . '">'
           . '<span class="sl-icon"><i class="fa-solid fa-' . $icon . '"></i></span>'
           . '<span class="sl-label">' . htmlspecialchars($label) . '</span>'
           . '</a>';
    }
}

/**
 * Open a collapsible section. Call sh_end() to close it.
 */
if (!function_exists('sh')) {
    function sh($label, $icon = 'circle') {
        global $_sec_id;
        $_sec_id++;
        $sid = 'sb-sec-' . $_sec_id;
        // Default: all sections open
        echo '<div class="sb-section">';
        echo '<button class="sb-section-toggle" data-target="' . $sid . '" title="' . htmlspecialchars($label) . '">'
           . '<span class="sb-section-icon"><i class="fa-solid fa-' . $icon . '"></i></span>'
           . '<span class="sb-section-label">' . htmlspecialchars($label) . '</span>'
           . '<i class="fa-solid fa-chevron-down sb-chevron"></i>'
           . '</button>';
        echo '<div class="sb-section-body" id="' . $sid . '">';
    }
}

if (!function_exists('sh_end')) {
    function sh_end() {
        echo '</div></div>'; // close .sb-section-body + .sb-section
    }
}
?>

<div id="sidebar-wrapper">

    <!-- Brand -->
    <a href="<?php echo BASE_URL; ?>index.php" class="sidebar-brand" title="<?php echo htmlspecialchars($company['name'] ?? APP_NAME); ?>">
        <div class="sidebar-brand-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
        <span class="sidebar-brand-text"><?php echo htmlspecialchars($company['name'] ?? APP_NAME); ?></span>
    </a>

    <!-- Scrollable nav -->
    <nav class="sidebar-menu" id="sidebar-nav">

        <div class="sb-standalone">
            <?php sl('index.php', 'gauge-high', 'Dashboard'); ?>
        </div>

        <?php if ($role === ROLE_ADMIN): ?>
            <?php sh('Administration', 'shield-halved'); ?>
            <?php sl('modules/users/index.php',              'users',        'User Management'); ?>
            <?php sl('modules/admin/settings.php',           'gear',         'Company Settings'); ?>
            <?php sl('modules/admin/product_visibility.php', 'eye-slash',    'Product Visibility'); ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER])): ?>
            <?php sh('Inventory', 'boxes-stacked'); ?>
            <?php sl('modules/products/index.php',     'box',              'Products'); ?>
            <?php sl('modules/products/categories.php','tags',             'Categories'); ?>
            <?php sl('modules/products/batches.php',   'barcode',          'Batch Tracker'); ?>
            <?php sl('modules/products/damages.php',   'triangle-exclamation', 'Stock Damages'); ?>
            <?php sl('modules/purchase/index.php',     'truck-ramp-box',   'Purchase Orders'); ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
            <?php sh('Supply Chain', 'industry'); ?>
            <?php sl('modules/suppliers/index.php', 'building',    'Suppliers'); ?>
            <?php sl('modules/returns/index.php',   'rotate-left', 'Returns'); ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER])): ?>
            <?php sh('SR Management', 'user-tie'); ?>
            <?php sl('modules/admin/divisions.php', 'map-location-dot', 'Divisions'); ?>
            <?php sl('modules/admin/sr_groups.php', 'people-group',     'Groups'); ?>
            <?php sl('modules/admin/targets.php',   'bullseye',         'Targets'); ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
            <?php sh('Logistics', 'truck'); ?>
            <?php sl('modules/delivery/index.php',        'truck',       'Truck Loads'); ?>
            <?php sl('modules/delivery/driver_sheet.php', 'file-lines',  'Driver Sheet'); ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php sh('Sales', 'cash-register'); ?>
        <?php if (!$is_viewer && $role !== ROLE_CUSTOMER): ?>
            <?php sl('modules/sales/pos.php', 'plus-circle', 'New Sale (POS)'); ?>
        <?php endif; ?>
        <?php sl('modules/sales/index.php', 'file-invoice',
            ($role === ROLE_CUSTOMER || $role === ROLE_SR) ? 'My Orders' : 'All Sales'); ?>
        <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
            <?php sl('modules/sales/confirm_list.php', 'circle-check', 'Confirm Drafts'); ?>
        <?php endif; ?>
        <?php sh_end(); ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT, ROLE_VIEWER])): ?>
            <?php sh('Customers', 'users'); ?>
            <?php sl('modules/customers/index.php', 'user-group', 'Customer List'); ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT]) || ($is_viewer && can_see_section('accounts'))): ?>
            <?php sh('Accounts', 'book-open'); ?>
            <?php sl('modules/accounts/ledger.php',           'book-bookmark', 'Customer Ledger'); ?>
            <?php sl('modules/accounts/collection_sheet.php', 'hand-holding-dollar', 'Collection Sheet'); ?>
            <?php sl('modules/accounts/journal.php',          'scroll',        'Journal Entries'); ?>
            <?php sl('modules/accounts/chart_of_accounts.php','sitemap',       'Chart of Accounts'); ?>
            <?php sl('modules/accounts/ledger_account.php',   'list',          'Account Ledger'); ?>
            <?php sl('modules/accounts/trial_balance.php',    'scale-balanced','Trial Balance'); ?>
            <?php sl('modules/accounts/profit_loss.php',      'chart-line',    'Profit & Loss'); ?>
            <?php sl('modules/accounts/balance_sheet.php',    'landmark',      'Balance Sheet'); ?>
            <?php sl('modules/accounts/ar_aging.php',         'hourglass-half','AR Aging'); ?>
            <?php sl('modules/accounts/payables.php',         'file-invoice-dollar', 'Payables'); ?>
            <?php if (in_array($role, [ROLE_ADMIN, ROLE_ACCOUNTANT])): ?>
                <?php sl('modules/accounts/expense.php', 'money-bill-wave', 'Expenses'); ?>
            <?php endif; ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php if (
            in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT]) ||
            ($is_viewer && (can_see_section('stock_report') || can_see_section('inventory_report') || can_see_section('comprehensive')))
        ): ?>
            <?php sh('Reports', 'chart-bar'); ?>
            <?php if (!$is_viewer || can_see_section('stock_report')): ?>
                <?php sl('modules/reports/stock_status.php', 'layer-group', 'Stock Status'); ?>
            <?php endif; ?>
            <?php if (!$is_viewer || can_see_section('inventory_report')): ?>
                <?php sl('modules/reports/inventory.php', 'warehouse', 'Inventory'); ?>
            <?php endif; ?>
            <?php if (!$is_viewer || can_see_section('comprehensive')): ?>
                <?php sl('modules/reports/comprehensive.php', 'chart-pie', 'Comprehensive'); ?>
            <?php endif; ?>
            <?php if (!$is_viewer): ?>
                <?php sl('modules/reports/sr_performance.php', 'trophy', 'SR Performance'); ?>
                <?php sl('viewreport.php', 'file-chart-column', 'Detailed Reports'); ?>
            <?php endif; ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php if (can_access_dmd()): ?>
            <?php sh('Executive', 'crown'); ?>
            <?php sl('modules/dmd_dashboard/index.php', 'chart-column', 'DMD Dashboard'); ?>
            <?php sh_end(); ?>
        <?php endif; ?>

        <?php sh('My Account', 'circle-user'); ?>
        <?php sl('profile.php',         'id-card', 'My Profile'); ?>
        <?php sl('change_password.php', 'key',      'Change Password'); ?>
        <?php sh_end(); ?>

    </nav><!-- .sidebar-menu -->

    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-logout" title="Logout">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="sl-label">Logout</span>
        </a>
    </div>

</div><!-- #sidebar-wrapper -->
