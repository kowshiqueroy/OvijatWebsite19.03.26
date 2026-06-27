<?php
/**
 * Viewer Permission Helpers
 * Loaded by header.php — provides per-user visibility controls for Viewer role.
 */

/**
 * Get view permissions for a specific user (cached in session).
 */
function get_view_permissions($user_id = null) {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    
    // Cache in session
    if (!isset($_SESSION['view_perms_' . $user_id])) {
        $perms = fetch_one("SELECT * FROM user_view_permissions WHERE user_id = ? SKIP_ISDELETE_FILTER", [$user_id]);
        if (!$perms) {
            // Default: show everything
            $perms = [
                'show_local' => 1, 'show_export' => 1, 'show_custom' => 1,
                'show_sales_kpis' => 1, 'show_inventory_section' => 1,
                'show_delivery_section' => 1, 'show_accounts_section' => 1,
                'can_see_stock_report' => 1, 'can_see_inventory_report' => 1,
                'can_see_comprehensive_report' => 1, 'can_see_transactions' => 1,
                'can_see_dmd_dashboard' => 0, 'show_rates' => 1,
                'show_customer_balances' => 1,
            ];
        }
        $_SESSION['view_perms_' . $user_id] = $perms;
    }
    return $_SESSION['view_perms_' . $user_id];
}

/**
 * Clear cached permissions (call after saving new permissions).
 */
function clear_view_permissions_cache($user_id) {
    unset($_SESSION['view_perms_' . $user_id]);
}

/**
 * Check if current user can see a specific market type.
 * Non-Viewer roles always return true.
 */
function can_see_market_type($type) {
    $role = $_SESSION['role'] ?? '';
    if ($role !== ROLE_VIEWER) return true;
    
    $perms = get_view_permissions();
    $key = 'show_' . strtolower($type); // show_local, show_export, show_custom
    return isset($perms[$key]) ? (bool)$perms[$key] : true;
}

/**
 * Returns a SQL WHERE clause fragment for market_type filtering.
 * For Viewer roles: filters based on their permissions.
 * For other roles: returns empty string (no filter).
 * Usage: $where_extra = get_market_type_filter('p');
 *        $sql .= " AND $where_extra"; // only if non-empty
 */
function get_market_type_filter($table_alias = 'p') {
    $role = $_SESSION['role'] ?? '';
    if ($role !== ROLE_VIEWER) return '';
    
    $perms = get_view_permissions();
    $allowed = [];
    if (!empty($perms['show_local']))   $allowed[] = "'Local'";
    if (!empty($perms['show_export']))  $allowed[] = "'Export'";
    if (!empty($perms['show_custom']))  $allowed[] = "'Custom'";
    
    if (empty($allowed)) return "1=0"; // No access at all
    return "`{$table_alias}`.`market_type` IN (" . implode(',', $allowed) . ")";
}

/**
 * Returns array of allowed market types for current user.
 */
function get_visible_market_types() {
    $role = $_SESSION['role'] ?? '';
    if ($role !== ROLE_VIEWER) return ['Local', 'Export', 'Custom'];
    
    $perms = get_view_permissions();
    $types = [];
    if (!empty($perms['show_local']))   $types[] = 'Local';
    if (!empty($perms['show_export']))  $types[] = 'Export';
    if (!empty($perms['show_custom']))  $types[] = 'Custom';
    return $types;
}

/**
 * Check if current Viewer can see a dashboard section.
 */
function can_see_section($section) {
    $role = $_SESSION['role'] ?? '';
    if ($role !== ROLE_VIEWER) return true;
    
    $perms = get_view_permissions();
    $map = [
        'sales_kpis'       => 'show_sales_kpis',
        'inventory'        => 'show_inventory_section',
        'delivery'         => 'show_delivery_section',
        'accounts'         => 'show_accounts_section',
        'stock_report'     => 'can_see_stock_report',
        'inventory_report' => 'can_see_inventory_report',
        'comprehensive'    => 'can_see_comprehensive_report',
        'transactions'     => 'can_see_transactions',
        'dmd_dashboard'    => 'can_see_dmd_dashboard',
        'rates'            => 'show_rates',
        'balances'         => 'show_customer_balances',
    ];
    
    $key = $map[$section] ?? null;
    if (!$key) return true;
    return isset($perms[$key]) ? (bool)$perms[$key] : true;
}

/**
 * Check if DMD dashboard is accessible to current user.
 * Replaces the old username-based hack.
 */
function can_access_dmd() {
    $role = $_SESSION['role'] ?? '';
    if (in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_ACCOUNTANT])) return true;
    if ($role === ROLE_VIEWER) return can_see_section('dmd_dashboard');
    return false;
}

/**
 * Returns a SQL WHERE clause fragment for order_type filtering on sales_drafts.
 * DMD Viewer (can_see_dmd_dashboard=1) sees all types.
 * Standard Viewer: filtered by show_local/export/custom.
 * Non-Viewer roles: returns empty string (no filter applied).
 * Usage: $filter = get_order_type_filter('s');
 *        if ($filter) $sql .= " AND $filter";
 */
function get_order_type_filter($table_alias = 's') {
    $role = $_SESSION['role'] ?? '';
    if ($role !== ROLE_VIEWER) return '';

    $perms = get_view_permissions();
    // DMD viewers see everything
    if (!empty($perms['can_see_dmd_dashboard'])) return '';

    $allowed = [];
    if (!empty($perms['show_local']))  $allowed[] = "'Local'";
    if (!empty($perms['show_export'])) $allowed[] = "'Export'";
    if (!empty($perms['show_custom'])) $allowed[] = "'Custom'";

    if (empty($allowed)) return "1=0";
    return "`{$table_alias}`.`order_type` IN (" . implode(',', $allowed) . ")";
}

/**
 * Returns an array of product IDs that are hidden from the given user.
 * Context: 'ui' (hide from POS/edit) or 'reports' (hide from reports).
 */
function get_hidden_product_ids($user_id = null, $context = 'ui') {
    if ($user_id === null) $user_id = $_SESSION['user_id'] ?? 0;
    $col = $context === 'reports' ? 'hide_from_reports' : 'hide_from_ui';
    $rows = fetch_all("SELECT product_id FROM product_visibility_rules WHERE user_id = ? AND {$col} = 1", [$user_id]);
    return array_column($rows, 'product_id');
}

/**
 * Check if a specific product is hidden for the current user.
 */
function is_product_hidden($product_id, $user_id = null, $context = 'ui') {
    $hidden = get_hidden_product_ids($user_id, $context);
    return in_array($product_id, $hidden);
}

/**
 * Get system account ID by code (e.g., 'CASH', 'BANK', 'AR', 'AP', 'SALES').
 */
function get_system_account_id($code) {
    $acc = fetch_one("SELECT id FROM accounts WHERE code = ? AND is_system = 1 AND isDelete = 0 LIMIT 1", [$code]);
    return $acc ? (int)$acc['id'] : null;
}

/**
 * Get the company default cash or bank account ID.
 */
function get_default_account_id($type = 'cash') {
    $col = $type === 'bank' ? 'default_bank_account_id' : 'default_cash_account_id';
    $company = fetch_one("SELECT {$col} as acc_id FROM company_settings LIMIT 1");
    return $company ? (int)$company['acc_id'] : ($type === 'bank' ? 2 : 1);
}

/**
 * Generate a next journal entry number.
 * Format: JV-YYYY-NNNN
 */
function next_journal_no() {
    $year = date('Y');
    $last = fetch_one("SELECT entry_no FROM journal_entries WHERE entry_no LIKE ? ORDER BY id DESC LIMIT 1", ["JV-{$year}-%"]);
    if ($last) {
        $num = (int)substr($last['entry_no'], -4) + 1;
    } else {
        $num = 1;
    }
    return "JV-{$year}-" . str_pad($num, 4, '0', STR_PAD_LEFT);
}

/**
 * Auto-post a journal entry for a given business event.
 * Returns journal_entry id or false on failure.
 */
function post_journal($date, $narration, $reference_type, $reference_id, $lines) {
    $conn = get_db_connection();
    $entry_no = next_journal_no();
    $user_id  = $_SESSION['user_id'] ?? 1;
    
    try {
        $conn->begin_transaction();
        
        // Validate balanced
        $total_dr = array_sum(array_column($lines, 'dr'));
        $total_cr = array_sum(array_column($lines, 'cr'));
        if (round($total_dr, 2) !== round($total_cr, 2)) {
            throw new Exception("Journal not balanced: Dr={$total_dr}, Cr={$total_cr}");
        }
        
        $stmt = $conn->prepare("INSERT INTO journal_entries (entry_no, date, narration, reference_type, reference_id, created_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssssii", $entry_no, $date, $narration, $reference_type, $reference_id, $user_id);
        $stmt->execute();
        $journal_id = $conn->insert_id;
        
        foreach ($lines as $line) {
            $stmt2 = $conn->prepare("INSERT INTO journal_lines (journal_id, account_id, dr_amount, cr_amount, narration) VALUES (?,?,?,?,?)");
            $line_dr   = $line['dr'];
            $line_cr   = $line['cr'];
            $line_note = $line['note'] ?? '';
            $stmt2->bind_param("iidds", $journal_id, $line['account_id'], $line_dr, $line_cr, $line_note);
            $stmt2->execute();
        }
        
        $conn->commit();
        return $journal_id;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Journal post error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get account ID for a customer's AR account.
 */
function get_customer_ar_account($customer_id) {
    $acc = fetch_one("SELECT id FROM accounts WHERE entity_type = 'Customer' AND entity_id = ? AND isDelete = 0 LIMIT 1", [$customer_id]);
    return $acc ? $acc['id'] : null;
}

/**
 * Get account ID for a supplier's AP account.
 */
function get_supplier_ap_account($supplier_id) {
    $acc = fetch_one("SELECT id FROM accounts WHERE entity_type = 'Supplier' AND entity_id = ? AND isDelete = 0 LIMIT 1", [$supplier_id]);
    return $acc ? $acc['id'] : null;
}

/**
 * Get running balance for an account up to a given date.
 * Returns array ['dr' => total, 'cr' => total, 'balance' => net, 'type' => Dr/Cr]
 */
function get_account_balance($account_id, $until_date = null) {
    $acc = fetch_one("SELECT opening_balance, opening_balance_type FROM accounts WHERE id = ?", [$account_id]);
    if (!$acc) return ['dr' => 0, 'cr' => 0, 'balance' => 0, 'type' => 'Dr'];
    
    $sql = "SELECT COALESCE(SUM(jl.dr_amount),0) as total_dr, COALESCE(SUM(jl.cr_amount),0) as total_cr
            FROM journal_lines jl
            JOIN journal_entries je ON jl.journal_id = je.id
            WHERE jl.account_id = ? AND jl.isDelete = 0 AND je.isDelete = 0";
    $params = [$account_id];
    
    if ($until_date) {
        $sql .= " AND je.date <= ?";
        $params[] = $until_date;
    }
    
    $totals = fetch_one($sql, $params);
    $dr = $totals['total_dr'] + ($acc['opening_balance_type'] === 'Dr' ? $acc['opening_balance'] : 0);
    $cr = $totals['total_cr'] + ($acc['opening_balance_type'] === 'Cr' ? $acc['opening_balance'] : 0);
    $balance = abs($dr - $cr);
    $type = $dr >= $cr ? 'Dr' : 'Cr';
    
    return ['dr' => $dr, 'cr' => $cr, 'balance' => $balance, 'type' => $type];
}
