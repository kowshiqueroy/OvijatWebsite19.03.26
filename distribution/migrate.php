<?php
/**
 * Ovijat Food Distribution — Database Migration Script
 * Adds all new tables/columns for the upgraded system.
 * SAFE: Uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS — never drops anything.
 * Run ONCE after importing the production SQL dump.
 */
$required_pin = "5877";

if (!isset($_POST['pin']) || $_POST['pin'] !== $required_pin) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Migration — Ovijat Food</title>
        <style>
            body { font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #0f172a; }
            .box { background: #1e293b; padding: 36px; border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,0.4); width: 100%; max-width: 380px; text-align: center; }
            h3 { color: #f1f5f9; margin-bottom: 6px; font-size: 1.3rem; }
            p { color: #94a3b8; font-size: .85rem; margin-bottom: 20px; }
            input { padding: 12px; margin-bottom: 16px; width: 100%; border: 1px solid #334155; border-radius: 8px; box-sizing: border-box; font-size: 16px; background: #0f172a; color: #f1f5f9; }
            button { padding: 12px; width: 100%; cursor: pointer; background: linear-gradient(135deg,#6366f1,#8b5cf6); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
            .err { color: #f87171; margin-bottom: 12px; font-size: .85rem; }
        </style>
    </head>
    <body>
        <div class="box">
            <h3>🚀 Database Migration</h3>
            <p>Ovijat Food Distribution System — Schema Upgrade</p>
            <?php if (isset($_POST['pin'])): ?><div class="err">Invalid PIN. Try again.</div><?php endif; ?>
            <form method="POST">
                <input type="password" name="pin" placeholder="Enter Setup PIN" required autofocus>
                <button type="submit">Run Migration</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once 'config/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create DB if not exists (handles fresh install)
$conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db(DB_NAME);
$conn->set_charset("utf8mb4");

$results = [];

function run_sql($conn, $label, $sql) {
    global $results;
    if ($conn->query($sql) === TRUE) {
        $results[] = ['label' => $label, 'status' => 'success', 'msg' => 'OK'];
    } else {
        $results[] = ['label' => $label, 'status' => 'error', 'msg' => $conn->error];
    }
}

// =============================================================================
// STEP 1: ALTER existing tables — ADD new columns (safe, IF NOT EXISTS)
// =============================================================================

// Products — market type, SKU, barcode, unit, threshold
$alter_products = [
    "ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `market_type` ENUM('Local','Export','Custom') DEFAULT 'Local'",
    "ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `sku` VARCHAR(50) NULL",
    "ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `barcode` VARCHAR(100) NULL",
    "ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `unit` VARCHAR(50) DEFAULT 'pcs'",
    "ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `low_stock_threshold` INT DEFAULT 10",
];
foreach ($alter_products as $sql) run_sql($conn, "products ALTER", $sql);

// Stock entries — batch, expiry, purchase link
$alter_stock = [
    "ALTER TABLE `stock_entries` ADD COLUMN IF NOT EXISTS `batch_no` VARCHAR(50) NULL",
    "ALTER TABLE `stock_entries` ADD COLUMN IF NOT EXISTS `expiry_date` DATE NULL",
    "ALTER TABLE `stock_entries` ADD COLUMN IF NOT EXISTS `purchase_id` INT NULL",
    "ALTER TABLE `stock_entries` ADD COLUMN IF NOT EXISTS `notes` TEXT NULL",
];
foreach ($alter_stock as $sql) run_sql($conn, "stock_entries ALTER", $sql);

// Truck loads — driver phone, expected delivery
$alter_truck = [
    "ALTER TABLE `truck_loads` ADD COLUMN IF NOT EXISTS `driver_phone` VARCHAR(20) NULL",
    "ALTER TABLE `truck_loads` ADD COLUMN IF NOT EXISTS `expected_delivery` DATE NULL",
];
foreach ($alter_truck as $sql) run_sql($conn, "truck_loads ALTER", $sql);

// =============================================================================
// STEP 2: CREATE new tables
// =============================================================================

$new_tables = [

"suppliers" => "CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `balance` DECIMAL(15,2) DEFAULT 0.00,
  `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"purchase_orders" => "CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT DEFAULT NULL,
  `invoice_no` VARCHAR(50) DEFAULT NULL,
  `total_amount` DECIMAL(15,2) DEFAULT 0.00,
  `paid_amount` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('Draft','Received','Partial','Paid') DEFAULT 'Draft',
  `notes` TEXT DEFAULT NULL,
  `received_by` INT DEFAULT NULL,
  `received_at` DATETIME DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `supplier_id` (`supplier_id`),
  KEY `received_by` (`received_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"purchase_items" => "CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT DEFAULT NULL,
  `product_id` INT DEFAULT NULL,
  `batch_no` VARCHAR(50) DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `quantity` INT NOT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(15,2) DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  KEY `purchase_id` (`purchase_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"product_batches" => "CREATE TABLE IF NOT EXISTS `product_batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT DEFAULT NULL,
  `batch_no` VARCHAR(50) NOT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `quantity_in` INT DEFAULT 0,
  `quantity_remaining` INT DEFAULT 0,
  `purchase_id` INT DEFAULT NULL,
  `source` ENUM('Purchase','Manual') DEFAULT 'Manual',
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `product_id` (`product_id`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"supplier_transactions" => "CREATE TABLE IF NOT EXISTS `supplier_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT DEFAULT NULL,
  `purchase_id` INT DEFAULT NULL,
  `type` ENUM('Payable','Payment') NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"sales_returns" => "CREATE TABLE IF NOT EXISTS `sales_returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT DEFAULT NULL,
  `customer_id` INT DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `total_amount` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
  `restock` TINYINT(1) DEFAULT 1,
  `processed_by` INT DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `sale_id` (`sale_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"sales_return_items" => "CREATE TABLE IF NOT EXISTS `sales_return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT DEFAULT NULL,
  `product_id` INT DEFAULT NULL,
  `quantity` INT NOT NULL,
  `unit_rate` DECIMAL(10,2) DEFAULT NULL,
  `total` DECIMAL(15,2) DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"expenses" => "CREATE TABLE IF NOT EXISTS `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `account_id` INT DEFAULT NULL,
  `recorded_by` INT DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `expense_date` (`expense_date`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"user_view_permissions" => "CREATE TABLE IF NOT EXISTS `user_view_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `show_local` TINYINT(1) DEFAULT 1,
  `show_export` TINYINT(1) DEFAULT 1,
  `show_custom` TINYINT(1) DEFAULT 1,
  `show_sales_kpis` TINYINT(1) DEFAULT 1,
  `show_inventory_section` TINYINT(1) DEFAULT 1,
  `show_delivery_section` TINYINT(1) DEFAULT 1,
  `show_accounts_section` TINYINT(1) DEFAULT 0,
  `can_see_stock_report` TINYINT(1) DEFAULT 1,
  `can_see_inventory_report` TINYINT(1) DEFAULT 1,
  `can_see_comprehensive_report` TINYINT(1) DEFAULT 1,
  `can_see_transactions` TINYINT(1) DEFAULT 0,
  `can_see_dmd_dashboard` TINYINT(1) DEFAULT 0,
  `show_rates` TINYINT(1) DEFAULT 1,
  `show_customer_balances` TINYINT(1) DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"account_groups" => "CREATE TABLE IF NOT EXISTS `account_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `nature` ENUM('Assets','Liabilities','Income','Expense','Equity') NOT NULL,
  `is_system` TINYINT(1) DEFAULT 0,
  `isDelete` TINYINT(1) DEFAULT 0,
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"accounts" => "CREATE TABLE IF NOT EXISTS `accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(20) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
  `opening_balance_type` ENUM('Dr','Cr') DEFAULT 'Dr',
  `is_system` TINYINT(1) DEFAULT 0,
  `entity_type` ENUM('Customer','Supplier','General') DEFAULT 'General',
  `entity_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `group_id` (`group_id`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"journal_entries" => "CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `entry_no` VARCHAR(30) NOT NULL,
  `date` DATE NOT NULL,
  `narration` TEXT DEFAULT NULL,
  `reference_type` ENUM('Invoice','Payment','Purchase','Expense','Return','Adjustment','Opening') DEFAULT 'Adjustment',
  `reference_id` INT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `is_posted` TINYINT(1) DEFAULT 1,
  `is_verified` TINYINT(1) DEFAULT 0,
  `verified_by` INT DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `entry_no` (`entry_no`),
  KEY `date` (`date`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"journal_lines" => "CREATE TABLE IF NOT EXISTS `journal_lines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `journal_id` INT NOT NULL,
  `account_id` INT NOT NULL,
  `dr_amount` DECIMAL(15,2) DEFAULT 0.00,
  `cr_amount` DECIMAL(15,2) DEFAULT 0.00,
  `narration` TEXT DEFAULT NULL,
  `isDelete` TINYINT(1) DEFAULT 0,
  KEY `journal_id` (`journal_id`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

foreach ($new_tables as $name => $sql) {
    run_sql($conn, "CREATE TABLE: $name", $sql);
}

// =============================================================================
// STEP 3: Auto-classify existing products by market type
// =============================================================================
run_sql($conn, "Classify Export products (Exp prefix)", "UPDATE `products` SET `market_type` = 'Export' WHERE `name` LIKE 'Exp %' AND `market_type` = 'Local'");
run_sql($conn, "Classify Export products (Biswas prefix)", "UPDATE `products` SET `market_type` = 'Export' WHERE `name` LIKE 'Biswas%' AND `market_type` = 'Local'");
run_sql($conn, "Classify Custom (Khoil Oil Cake category)", "UPDATE `products` SET `market_type` = 'Custom' WHERE `category_id` = 10");
run_sql($conn, "All others remain Local", "SELECT COUNT(*) as cnt FROM `products` WHERE `market_type` = 'Local'");

// =============================================================================
// STEP 4: Seed account groups (INSERT IGNORE)
// =============================================================================
$group_seeds = [
    "(1, 'Assets', NULL, 'Assets', 1)",
    "(2, 'Liabilities', NULL, 'Liabilities', 1)",
    "(3, 'Income', NULL, 'Income', 1)",
    "(4, 'Expense', NULL, 'Expense', 1)",
    "(5, 'Equity', NULL, 'Equity', 1)",
    "(6, 'Cash & Bank', 1, 'Assets', 1)",
    "(7, 'Accounts Receivable', 1, 'Assets', 1)",
    "(8, 'Inventory', 1, 'Assets', 1)",
    "(9, 'Fixed Assets', 1, 'Assets', 1)",
    "(10, 'Accounts Payable', 2, 'Liabilities', 1)",
    "(11, 'Loans & Borrowings', 2, 'Liabilities', 1)",
    "(12, 'Sales Revenue', 3, 'Income', 1)",
    "(13, 'Other Income', 3, 'Income', 1)",
    "(14, 'Cost of Goods Sold', 4, 'Expense', 1)",
    "(15, 'Operating Expenses', 4, 'Expense', 1)",
    "(16, 'Salaries & Wages', 15, 'Expense', 1)",
    "(17, 'Transport & Logistics', 15, 'Expense', 1)",
    "(18, 'Office Expenses', 15, 'Expense', 1)",
    "(19, 'Owner Capital', 5, 'Equity', 1)",
    "(20, 'Retained Earnings', 5, 'Equity', 1)",
];
$seed_sql = "INSERT IGNORE INTO `account_groups` (`id`, `name`, `parent_id`, `nature`, `is_system`) VALUES " . implode(',', $group_seeds);
run_sql($conn, "Seed account_groups", $seed_sql);

// =============================================================================
// STEP 5: Seed default system accounts
// =============================================================================
$account_seeds = [
    "(1, 6, 'Cash in Hand', 'CASH', NULL, 0.00, 'Dr', 1, 'General', NULL)",
    "(2, 6, 'Bank Account', 'BANK', NULL, 0.00, 'Dr', 1, 'General', NULL)",
    "(3, 8, 'Stock Inventory', 'STK', NULL, 0.00, 'Dr', 1, 'General', NULL)",
    "(4, 12, 'Sales Revenue', 'SALES', NULL, 0.00, 'Cr', 1, 'General', NULL)",
    "(5, 14, 'Cost of Goods Sold', 'COGS', NULL, 0.00, 'Dr', 1, 'General', NULL)",
    "(6, 15, 'Miscellaneous Expense', 'MISC', NULL, 0.00, 'Dr', 1, 'General', NULL)",
    "(7, 16, 'Salaries', 'SAL', NULL, 0.00, 'Dr', 1, 'General', NULL)",
    "(8, 17, 'Transport', 'TRN', NULL, 0.00, 'Dr', 1, 'General', NULL)",
    "(9, 18, 'Office Expense', 'OFF', NULL, 0.00, 'Dr', 1, 'General', NULL)",
];
$acc_sql = "INSERT IGNORE INTO `accounts` (`id`, `group_id`, `name`, `code`, `description`, `opening_balance`, `opening_balance_type`, `is_system`, `entity_type`, `entity_id`) VALUES " . implode(',', $account_seeds);
run_sql($conn, "Seed system accounts", $acc_sql);

// =============================================================================
// STEP 6: Auto-create AR accounts for existing customers
// =============================================================================
$ar_sql = "INSERT INTO `accounts` (`group_id`, `name`, `is_system`, `entity_type`, `entity_id`, `opening_balance`, `opening_balance_type`)
SELECT 
    7,
    CONCAT('AR - ', c.name),
    1,
    'Customer',
    c.id,
    ABS(c.balance),
    CASE WHEN c.balance <= 0 THEN 'Cr' ELSE 'Dr' END
FROM `customers` c 
WHERE c.isDelete = 0
AND NOT EXISTS (
    SELECT 1 FROM `accounts` a WHERE a.entity_type = 'Customer' AND a.entity_id = c.id
)";
run_sql($conn, "Auto-create AR accounts for customers", $ar_sql);

// =============================================================================
// STEP 7: Configure default viewer permission profiles
// =============================================================================
// DMD viewer (user_id=3): Full access including DMD dashboard
$dmd_sql = "INSERT IGNORE INTO `user_view_permissions`
    (user_id, show_local, show_export, show_custom, show_sales_kpis, show_inventory_section,
     show_delivery_section, show_accounts_section, can_see_stock_report, can_see_inventory_report,
     can_see_comprehensive_report, can_see_transactions, can_see_dmd_dashboard, show_rates, show_customer_balances)
    VALUES (3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)";
run_sql($conn, "DMD viewer permissions (user 3)", $dmd_sql);

// gmovijat viewer (user_id=96): Local only, no rates
$gmovijat_sql = "INSERT IGNORE INTO `user_view_permissions`
    (user_id, show_local, show_export, show_custom, show_sales_kpis, show_inventory_section,
     show_delivery_section, show_accounts_section, can_see_stock_report, can_see_inventory_report,
     can_see_comprehensive_report, can_see_transactions, can_see_dmd_dashboard, show_rates, show_customer_balances)
    VALUES (96, 1, 0, 0, 1, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0)";
run_sql($conn, "gmovijat viewer permissions (user 96)", $gmovijat_sql);

// salesmanager viewer (user_id=128): Sales + stock report
$sm_sql = "INSERT IGNORE INTO `user_view_permissions`
    (user_id, show_local, show_export, show_custom, show_sales_kpis, show_inventory_section,
     show_delivery_section, show_accounts_section, can_see_stock_report, can_see_inventory_report,
     can_see_comprehensive_report, can_see_transactions, can_see_dmd_dashboard, show_rates, show_customer_balances)
    VALUES (128, 1, 0, 0, 1, 0, 0, 0, 1, 0, 1, 0, 0, 1, 0)";
run_sql($conn, "salesmanager viewer permissions (user 128)", $sm_sql);

// =============================================================================
// REPORT
// =============================================================================
$success = count(array_filter($results, fn($r) => $r['status'] === 'success'));
$errors  = count(array_filter($results, fn($r) => $r['status'] === 'error'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Results — Ovijat Food</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 24px; }
        .container { max-width: 900px; margin: auto; }
        h1 { font-size: 1.5rem; color: #a78bfa; border-bottom: 1px solid #334155; padding-bottom: 12px; }
        .summary { display: flex; gap: 16px; margin: 20px 0; }
        .stat { background: #1e293b; padding: 16px 24px; border-radius: 10px; text-align: center; flex: 1; }
        .stat .num { font-size: 2rem; font-weight: 700; }
        .stat.ok .num { color: #4ade80; }
        .stat.err .num { color: #f87171; }
        .stat .label { font-size: .8rem; color: #94a3b8; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 10px; overflow: hidden; }
        th { background: #334155; padding: 10px 14px; text-align: left; font-size: .8rem; text-transform: uppercase; color: #94a3b8; }
        td { padding: 9px 14px; border-bottom: 1px solid #1e293b; font-size: .85rem; }
        tr:hover td { background: #1a2540; }
        .success { color: #4ade80; font-weight: 600; }
        .error { color: #f87171; font-weight: 600; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 28px; background: linear-gradient(135deg,#6366f1,#8b5cf6); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .warn { background: #451a03; border: 1px solid #92400e; color: #fcd34d; padding: 12px 16px; border-radius: 8px; margin: 16px 0; font-size: .85rem; }
    </style>
</head>
<body>
<div class="container">
    <h1>🚀 Migration Results — Ovijat Food Distribution</h1>
    <div class="summary">
        <div class="stat ok"><div class="num"><?= $success ?></div><div class="label">Successful</div></div>
        <div class="stat err"><div class="num"><?= $errors ?></div><div class="label">Errors</div></div>
        <div class="stat"><div class="num"><?= count($results) ?></div><div class="label">Total Steps</div></div>
    </div>
    <?php if ($errors > 0): ?>
    <div class="warn">⚠️ Some steps had errors. Review the table below. Errors on existing columns/tables are usually safe to ignore.</div>
    <?php endif; ?>
    <table>
        <thead><tr><th>Step</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['label']) ?></td>
                <td class="<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></td>
                <td><?= htmlspecialchars($r['msg']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <br>
    <a href="login.php" class="btn">✅ Go to Login</a>
</div>
</body>
</html>
