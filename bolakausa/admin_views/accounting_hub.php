<?php
/**
 * Zoho Books Accounting Hub - B2B Double Entry Ledger & Financial Reports
 */
restrict_to(['admin', 'viewer', 'manager']);

$user_role = $_SESSION['user_role'];
$success = '';
$error = '';

// 1. Database Migrations / Ensure Tables Exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `accounting_journals` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `journal_date` DATE NOT NULL,
      `debit_account` VARCHAR(100) NOT NULL,
      `credit_account` VARCHAR(100) NOT NULL,
      `amount` DECIMAL(10, 2) NOT NULL,
      `description` TEXT NOT NULL,
      `user_id` INT DEFAULT NULL,
      `reference_id` VARCHAR(100) DEFAULT NULL,
      `created_by` INT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
      FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
    die("Database Initialization Failed: " . $e->getMessage());
}

// 2. Handle Manual Journal Postings (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_journal'])) {
    if ($user_role !== 'admin') {
        $error = "Access Denied: Only system Administrators can post manual journal adjustments.";
    } else {
        if (!verify_csrf_token($_POST['_csrf_token'] ?? '')) {
            die("Invalid CSRF token.");
        }
        
        $j_date = $_POST['journal_date'] ?: date('Y-m-d');
        $debit_acc = $_POST['debit_account'];
        $credit_acc = $_POST['credit_account'];
        $amount = (float)$_POST['amount'];
        $description = trim($_POST['description']);
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $ref_id = trim($_POST['reference_id'] ?? '');

        if ($debit_acc === $credit_acc) {
            $error = "Debit account and Credit account cannot be the same.";
        } elseif ($amount <= 0) {
            $error = "Amount must be a positive number greater than 0.";
        } elseif (empty($description)) {
            $error = "Please enter a description for the adjustment.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO accounting_journals (journal_date, debit_account, credit_account, amount, description, user_id, reference_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$j_date, $debit_acc, $credit_acc, $amount, $description, $user_id, $ref_id, $_SESSION['user_id']])) {
                $success = "Journal entry successfully posted to General Ledger.";
                log_action($pdo, $_SESSION['user_id'], "Manual Journal Entry Posted", "Amount: $amount, Dr: $debit_acc, Cr: $credit_acc", $description);
            } else {
                $error = "Failed to write journal entry to database.";
            }
        }
    }
}

// 3. Date Filters & Presets
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$preset = $_GET['preset'] ?? 'all';

if ($preset === 'this_month') {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-t');
} elseif ($preset === 'this_quarter') {
    $cur_month = date('n');
    $cur_year = date('Y');
    $quarter = ceil($cur_month / 3);
    $start_month = ($quarter - 1) * 3 + 1;
    $date_from = date('Y-m-d', mktime(0, 0, 0, $start_month, 1, $cur_year));
    $date_to = date('Y-m-d', mktime(0, 0, 0, $start_month + 3, 0, $cur_year));
} elseif ($preset === 'this_year') {
    $date_from = date('Y-01-01');
    $date_to = date('Y-12-31');
}

// 4. Fetch All Active Base Data
$orders = $pdo->query("
    SELECT o.*, u.username 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.status NOT IN ('Cancelled', 'Rejected') AND o.is_deleted = 0
    ORDER BY o.created_at ASC
")->fetchAll();

$wallet_topups = $pdo->query("
    SELECT w.*, u.username 
    FROM wallet_topups w
    JOIN users u ON w.user_id = u.id
    WHERE w.status = 'approved'
    ORDER BY w.processed_at ASC
")->fetchAll();

$journals = $pdo->query("
    SELECT j.*, u.username as creator_name, c.username as customer_name 
    FROM accounting_journals j 
    JOIN users u ON j.created_by = u.id 
    LEFT JOIN users c ON j.user_id = c.id 
    ORDER BY j.journal_date ASC, j.id ASC
")->fetchAll();

$customers = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('wholesale_user', 'executive') AND is_deleted = 0 ORDER BY username ASC")->fetchAll();

// 5. Chart of Accounts Structure
$accounts = [
    '1010' => ['name' => 'Cash & Bank', 'type' => 'Asset', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '1200' => ['name' => 'Accounts Receivable', 'type' => 'Asset', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '1400' => ['name' => 'Inventory Asset', 'type' => 'Asset', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '2010' => ['name' => 'Customer Wallets Liability', 'type' => 'Liability', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '2200' => ['name' => 'Sales Tax Payable', 'type' => 'Liability', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '3010' => ['name' => 'Owner Equity & Seed Capital', 'type' => 'Equity', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '4010' => ['name' => 'Sales Revenue', 'type' => 'Revenue', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '4020' => ['name' => 'Shipping Revenue', 'type' => 'Revenue', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '5010' => ['name' => 'Cost of Goods Sold', 'type' => 'COGS', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '6010' => ['name' => 'Sales Discounts', 'type' => 'Expense', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '6020' => ['name' => 'Rejection Charges Refund', 'type' => 'Expense', 'balance' => 0, 'debit' => 0, 'credit' => 0],
    '6030' => ['name' => 'General & Administrative Expenses', 'type' => 'Expense', 'balance' => 0, 'debit' => 0, 'credit' => 0]
];

// 6. Generate Double-Entry Ledger Transactions
$ledger_entries = [];

// 6a. Seeding Owner's Capital
$ledger_entries[] = [
    'date' => '2026-01-01',
    'debit_acc' => '1010',
    'credit_acc' => '3010',
    'amount' => 50000.00,
    'desc' => 'Opening Balance: Seed Capital Investment',
    'ref' => 'SYS-OPEN-001'
];
$ledger_entries[] = [
    'date' => '2026-01-01',
    'debit_acc' => '1400',
    'credit_acc' => '3010',
    'amount' => 30000.00,
    'desc' => 'Opening Balance: Warehouse Stock Starting Value',
    'ref' => 'SYS-OPEN-002'
];

// 6b. Parse B2B Orders
foreach ($orders as $o) {
    $date = date('Y-m-d', strtotime($o['created_at']));
    $subtotal = (float)$o['total_amount'] - (float)$o['tax_amount'] - (float)$o['shipping_amount'] + (float)$o['discount_amount'];
    $tax = (float)$o['tax_amount'];
    $shipping = (float)$o['shipping_amount'];
    $discount = (float)$o['discount_amount'];
    $total = (float)$o['total_amount'];

    // Product Revenue Credit
    $ledger_entries[] = [
        'date' => $date,
        'debit_acc' => null,
        'credit_acc' => '4010',
        'amount' => $subtotal,
        'desc' => "Sales Revenue: Order #{$o['id']} by @{$o['username']}",
        'ref' => "Invoice #{$o['id']}"
    ];

    // Tax Collected Credit
    if ($tax > 0) {
        $ledger_entries[] = [
            'date' => $date,
            'debit_acc' => null,
            'credit_acc' => '2200',
            'amount' => $tax,
            'desc' => "Sales Tax Collected: Order #{$o['id']}",
            'ref' => "Invoice #{$o['id']}"
        ];
    }

    // Shipping Revenue Credit
    if ($shipping > 0) {
        $ledger_entries[] = [
            'date' => $date,
            'debit_acc' => null,
            'credit_acc' => '4020',
            'amount' => $shipping,
            'desc' => "Shipping Revenue Collected: Order #{$o['id']}",
            'ref' => "Invoice #{$o['id']}"
        ];
    }

    // Discounts Applied Debit
    if ($discount > 0) {
        $ledger_entries[] = [
            'date' => $date,
            'debit_acc' => '6010',
            'credit_acc' => null,
            'amount' => $discount,
            'desc' => "Sales Discount Applied: Order #{$o['id']}",
            'ref' => "Invoice #{$o['id']}"
        ];
    }

    // Payment Settlement Debit
    $pay_method_debit = '1200'; // Accounts Receivable
    if ($o['payment_method'] === 'Wallet') {
        $pay_method_debit = '2010'; // Customer Wallet Liability debited (reduces liability)
    } elseif ($o['payment_status'] === 'Paid') {
        $pay_method_debit = '1010'; // Cash & Bank
    }

    $ledger_entries[] = [
        'date' => $date,
        'debit_acc' => $pay_method_debit,
        'credit_acc' => null,
        'amount' => $total,
        'desc' => "Payment Settlement ({$o['payment_method']}): Order #{$o['id']}",
        'ref' => "Invoice #{$o['id']}"
    ];

    // COGS estimation (65% of net product value)
    $cogs_amt = round($subtotal * 0.65, 2);
    if ($cogs_amt > 0) {
        $ledger_entries[] = [
            'date' => $date,
            'debit_acc' => '5010', // COGS
            'credit_acc' => '1400', // Inventory Asset
            'amount' => $cogs_amt,
            'desc' => "Cost of Goods Sold (65% standard estimate): Order #{$o['id']}",
            'ref' => "Invoice #{$o['id']}"
        ];
    }
}

// 6c. Parse Approved Wallet Top-ups
foreach ($wallet_topups as $w) {
    $date = date('Y-m-d', strtotime($w['processed_at'] ?: $w['created_at']));
    $ledger_entries[] = [
        'date' => $date,
        'debit_acc' => '1010', // Cash & Bank
        'credit_acc' => '2010', // Customer Wallets Liability
        'amount' => (float)$w['amount'],
        'desc' => "Client Wallet Deposit Approved: Txn {$w['transaction_id']} (@{$w['username']})",
        'ref' => "Topup #{$w['id']}"
    ];
}

// 6d. Parse Manual Journals
foreach ($journals as $j) {
    $date = $j['journal_date'];
    $ledger_entries[] = [
        'date' => $date,
        'debit_acc' => $j['debit_account'],
        'credit_acc' => $j['credit_account'],
        'amount' => (float)$j['amount'],
        'desc' => "Manual Journal adjustment: " . htmlspecialchars($j['description']) . ($j['customer_name'] ? " [Client: @{$j['customer_name']}]" : ""),
        'ref' => "Journal #{$j['id']}"
    ];
}

// 7. Accumulate Trial Balances
foreach ($ledger_entries as $entry) {
    // Apply Date Range filters
    if ($date_from && $entry['date'] < $date_from) continue;
    if ($date_to && $entry['date'] > $date_to) continue;

    $amt = $entry['amount'];
    if (!empty($entry['debit_acc'])) {
        $accounts[$entry['debit_acc']]['debit'] += $amt;
    }
    if (!empty($entry['credit_acc'])) {
        $accounts[$entry['credit_acc']]['credit'] += $amt;
    }
}

// Compute dynamic accounts final balances
foreach ($accounts as $code => &$acc) {
    if (in_array($acc['type'], ['Asset', 'COGS', 'Expense'])) {
        $acc['balance'] = $acc['debit'] - $acc['credit'];
    } else {
        $acc['balance'] = $acc['credit'] - $acc['debit'];
    }
}
unset($acc);

// 8. Financial Report Summaries
// Revenue
$total_revenue = $accounts['4010']['balance'] + $accounts['4020']['balance'];
// Gross Sales & Net Sales
$sales_discounts = $accounts['6010']['balance'];
$net_sales = $total_revenue - $sales_discounts;
// COGS
$cogs = $accounts['5010']['balance'];
// Gross Profit
$gross_profit = $net_sales - $cogs;
// Expenses
$total_expenses = $accounts['6020']['balance'] + $accounts['6030']['balance'];
// Net Income
$net_income = $gross_profit - $total_expenses;

// Assets
$cash_balance = $accounts['1010']['balance'];
$ar_balance = $accounts['1200']['balance'];
$inventory_balance = $accounts['1400']['balance'];
$total_assets = $cash_balance + $ar_balance + $inventory_balance;

// Liabilities
$wallets_balance = $accounts['2010']['balance'];
$tax_balance = $accounts['2200']['balance'];
$total_liabilities = $wallets_balance + $tax_balance;

// Equity
$owners_capital = $accounts['3010']['balance'];
$retained_earnings = $net_income;
$total_equity = $owners_capital + $retained_earnings;

$active_tab = $_GET['tab'] ?? 'dashboard';
?>

<style>
.accounting-container {
    padding: 1.5rem;
    font-family: 'Inter', sans-serif;
}
.page-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1.5rem;
}
.zoho-badge {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    color: white;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 800;
    font-size: 0.72rem;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.filters-card {
    background: white;
    border-radius: 12px;
    border: 1px solid var(--border-light);
    padding: 1.25rem;
    margin-bottom: 2rem;
    box-shadow: var(--glass-shadow);
}
.tabs-navigation {
    display: flex;
    border-bottom: 2px solid #e2e8f0;
    gap: 1rem;
    margin-bottom: 2.25rem;
    overflow-x: auto;
    padding-bottom: 2px;
}
.tab-link {
    padding: 0.85rem 1.25rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--text-muted);
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: all 0.25s ease;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.tab-link:hover {
    color: var(--accent);
}
.tab-link.active {
    color: #0284c7;
    border-bottom-color: #0284c7;
}
.acc-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}
.acc-kpi-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    box-shadow: var(--glass-shadow);
    position: relative;
    overflow: hidden;
}
.acc-kpi-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; width: 4px; height: 100%;
}
.kpi-blue::before { background: #0284c7; }
.kpi-green::before { background: #10b981; }
.kpi-rose::before { background: #f43f5e; }
.kpi-amber::before { background: #f59e0b; }
.kpi-indigo::before { background: #6366f1; }

.financial-report-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--border-light);
}
.financial-report-table th, .financial-report-table td {
    padding: 1rem 1.5rem;
    text-align: left;
}
.financial-report-table tr.header-row {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
.financial-report-table tr.section-title-row td {
    font-weight: 800;
    color: var(--secondary);
    background: #f1f5f9;
    font-size: 0.95rem;
}
.financial-report-table tr.total-row td {
    font-weight: 800;
    color: var(--secondary);
    border-top: 2px solid #e2e8f0;
    border-bottom: 2px double #cbd5e1;
    background: rgba(2, 132, 199, 0.03);
}
.financial-report-table tr.grand-total-row td {
    font-weight: 900;
    color: white;
    background: #0f172a;
    border-top: 2px solid #334155;
    font-size: 1.05rem;
}
.journal-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}
.journal-modal-content {
    background: white;
    border-radius: 20px;
    width: 100%;
    max-width: 580px;
    padding: 2.25rem;
    box-shadow: 0 20px 50px -10px rgba(0,0,0,0.3);
    position: relative;
    border: 1px solid var(--border-light);
}
.account-badge-indicator {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
}
.acc-asset { background: rgba(16,185,129,0.08); color: #047857; }
.acc-liability { background: rgba(244,63,94,0.08); color: #be123c; }
.acc-equity { background: rgba(99,102,241,0.08); color: #4338ca; }
.acc-revenue { background: rgba(2,132,199,0.08); color: #0369a1; }
.acc-cogs { background: rgba(245,158,11,0.08); color: #b45309; }
.acc-expense { background: rgba(100,116,139,0.08); color: #475569; }

.visual-progress-track {
    background: #e2e8f0;
    border-radius: 6px;
    height: 8px;
    width: 120px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
}
.visual-progress-bar {
    height: 100%;
    border-radius: 6px;
}
@media print {
    body {
        background: white !important;
        color: black !important;
    }
    .sidebar, .top-header, .filters-card, .tabs-navigation, .action-buttons-group, .btn, button, footer, .zoho-badge, .mobile-toggle, .user-badge {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .printable-report {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        box-shadow: none !important;
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .printable-report table, .printable-report td, .printable-report th {
        border: 1px solid #cbd5e1 !important;
        color: black !important;
    }
    .printable-report * {
        visibility: visible !important;
    }
}
</style>

<div class="accounting-container">
    
    <!-- Header -->
    <div class="page-header-wrap">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.35rem;">
                <span class="zoho-badge"><i class="fas fa-landmark"></i> Zoho Books Edition</span>
                <span style="font-size: 0.72rem; font-weight: 800; padding: 0.25rem 0.5rem; background: rgba(16,185,129,0.1); color: var(--primary); border-radius: 6px;">Ledger Mode: Active</span>
            </div>
            <h1 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2rem; font-weight: 900; color: var(--secondary); letter-spacing: -0.75px;">Double-Entry Accounting Hub</h1>
        </div>
        
        <?php if ($user_role === 'admin'): ?>
            <button onclick="openJournalModal()" class="btn btn-blue" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; border-radius: 10px; background: #0284c7; box-shadow: 0 4px 14px rgba(2, 132, 199, 0.25);">
                <i class="fas fa-plus"></i> Post Journal Entry
            </button>
        <?php endif; ?>
    </div>

    <!-- Notifications Messages -->
    <?php if ($success): ?>
        <div style="background: rgba(16, 185, 129, 0.08); color: #047857; padding: 1rem 1.25rem; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 2rem; font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-check-circle" style="margin-right: 6px;"></i> <?php echo e($success); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="background: rgba(244, 63, 94, 0.08); color: #be123c; padding: 1rem 1.25rem; border-radius: 8px; border: 1px solid rgba(244, 63, 94, 0.2); margin-bottom: 2rem; font-size: 0.875rem; font-weight: 600;">
            <i class="fas fa-times-circle" style="margin-right: 6px;"></i> <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <!-- Date Range & Preset Filtering Block -->
    <div class="filters-card">
        <form method="GET" action="/bolakausa/admin/accounting" style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: flex-end;">
            <input type="hidden" name="tab" value="<?php echo e($active_tab); ?>">
            
            <div style="flex: 1; min-width: 160px;">
                <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">Time Period Preset</label>
                <select name="preset" onchange="this.form.submit()" style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem; font-weight: 600;">
                    <option value="all" <?php echo $preset === 'all' ? 'selected' : ''; ?>>All Time Ledger</option>
                    <option value="this_month" <?php echo $preset === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="this_quarter" <?php echo $preset === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                    <option value="this_year" <?php echo $preset === 'this_year' ? 'selected' : ''; ?>>This Fiscal Year</option>
                </select>
            </div>
            
            <div style="flex: 1; min-width: 150px;">
                <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">From Date</label>
                <input type="date" name="date_from" value="<?php echo e($date_from); ?>" style="border-radius: 8px; padding: 0.5rem; font-size: 0.85rem;">
            </div>
            
            <div style="flex: 1; min-width: 150px;">
                <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">To Date</label>
                <input type="date" name="date_to" value="<?php echo e($date_to); ?>" style="border-radius: 8px; padding: 0.5rem; font-size: 0.85rem;">
            </div>
            
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-blue" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem;"><i class="fas fa-filter"></i> Apply Filters</button>
                <a href="/bolakausa/admin/accounting?tab=<?php echo e($active_tab); ?>&preset=all" class="btn btn-secondary" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; border: 1px solid #cbd5e1; background: #f8fafc;"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Zoho Accounting Main Navigation Tabs -->
    <div class="tabs-navigation">
        <a href="/bolakausa/admin/accounting?tab=dashboard&preset=<?php echo $preset; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="tab-link <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a href="/bolakausa/admin/accounting?tab=accounts&preset=<?php echo $preset; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="tab-link <?php echo $active_tab === 'accounts' ? 'active' : ''; ?>">
            <i class="fas fa-list-alt"></i> Chart of Accounts
        </a>
        <a href="/bolakausa/admin/accounting?tab=pl&preset=<?php echo $preset; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="tab-link <?php echo $active_tab === 'pl' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice"></i> Profit & Loss Statement
        </a>
        <a href="/bolakausa/admin/accounting?tab=balance_sheet&preset=<?php echo $preset; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="tab-link <?php echo $active_tab === 'balance_sheet' ? 'active' : ''; ?>">
            <i class="fas fa-balance-scale"></i> Balance Sheet
        </a>
        <a href="/bolakausa/admin/accounting?tab=journals&preset=<?php echo $preset; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="tab-link <?php echo $active_tab === 'journals' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Manual Adjusting Journals
        </a>
        <a href="/bolakausa/admin/accounting?tab=ledger&preset=<?php echo $preset; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="tab-link <?php echo $active_tab === 'ledger' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i> Account Transactions Ledger
        </a>
    </div>

    <!-- TAB 1: OVERVIEW DASHBOARD -->
    <?php if ($active_tab === 'dashboard'): ?>
        <div class="acc-kpi-grid">
            <div class="acc-kpi-card kpi-blue">
                <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.35rem;">Total Sales (Gross Revenue)</div>
                <div style="font-size: 1.85rem; font-weight: 900; color: var(--secondary);">$<?php echo number_format($total_revenue, 2); ?></div>
                <i class="fas fa-coins" style="position: absolute; right: 1.25rem; bottom: 1.25rem; font-size: 2rem; color: rgba(2, 132, 199, 0.08);"></i>
            </div>
            
            <div class="acc-kpi-card kpi-rose">
                <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.35rem;">Discounts Allowed</div>
                <div style="font-size: 1.85rem; font-weight: 900; color: #e11d48;">$<?php echo number_format($sales_discounts, 2); ?></div>
                <i class="fas fa-percent" style="position: absolute; right: 1.25rem; bottom: 1.25rem; font-size: 2rem; color: rgba(244, 63, 94, 0.08);"></i>
            </div>

            <div class="acc-kpi-card kpi-amber">
                <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.35rem;">Cost of Goods Sold (COGS)</div>
                <div style="font-size: 1.85rem; font-weight: 900; color: #d97706;">$<?php echo number_format($cogs, 2); ?></div>
                <i class="fas fa-warehouse" style="position: absolute; right: 1.25rem; bottom: 1.25rem; font-size: 2rem; color: rgba(245, 158, 11, 0.08);"></i>
            </div>

            <div class="acc-kpi-card kpi-green">
                <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.35rem;">Net Operating Profit</div>
                <div style="font-size: 1.85rem; font-weight: 900; color: #059669;">$<?php echo number_format($net_income, 2); ?></div>
                <i class="fas fa-dollar-sign" style="position: absolute; right: 1.25rem; bottom: 1.25rem; font-size: 2rem; color: rgba(16, 185, 129, 0.08);"></i>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 1.5rem; flex-wrap: wrap;">
            
            <!-- Asset vs Liability Balance Overview -->
            <div class="card" style="padding: 1.75rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.5rem;"><i class="fas fa-vault" style="color: #0284c7;"></i> Cash and Receivables Matrix</h3>
                
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom: 0.25rem; font-size: 0.85rem;">
                            <span style="color: var(--text-main); font-weight: 700;">Liquid Cash & Bank Account (Dr)</span>
                            <strong style="color: #0284c7;">$<?php echo number_format($cash_balance, 2); ?></strong>
                        </div>
                        <div style="background: #e2e8f0; height: 10px; border-radius: 5px; overflow:hidden;">
                            <div style="background: #0284c7; width: <?php echo min(100, max(5, ($total_assets > 0 ? ($cash_balance/$total_assets)*100 : 0))); ?>%; height: 100%;"></div>
                        </div>
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom: 0.25rem; font-size: 0.85rem;">
                            <span style="color: var(--text-main); font-weight: 700;">Outstanding B2B Accounts Receivable (Dr)</span>
                            <strong style="color: #6366f1;">$<?php echo number_format($ar_balance, 2); ?></strong>
                        </div>
                        <div style="background: #e2e8f0; height: 10px; border-radius: 5px; overflow:hidden;">
                            <div style="background: #6366f1; width: <?php echo min(100, max(5, ($total_assets > 0 ? ($ar_balance/$total_assets)*100 : 0))); ?>%; height: 100%;"></div>
                        </div>
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; margin-bottom: 0.25rem; font-size: 0.85rem;">
                            <span style="color: var(--text-main); font-weight: 700;">Valued Inventory Asset (Dr)</span>
                            <strong style="color: #10b981;">$<?php echo number_format($inventory_balance, 2); ?></strong>
                        </div>
                        <div style="background: #e2e8f0; height: 10px; border-radius: 5px; overflow:hidden;">
                            <div style="background: #10b981; width: <?php echo min(100, max(5, ($total_assets > 0 ? ($inventory_balance/$total_assets)*100 : 0))); ?>%; height: 100%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liabilities and Balances -->
            <div class="card" style="padding: 1.75rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.15rem; font-weight: 800; color: var(--secondary); margin-bottom: 1.25rem;"><i class="fas fa-landmark" style="color: #be123c;"></i> Capital & Liabilities</h3>
                
                <div style="display:flex; flex-direction:column; gap: 1rem;">
                    <div style="background: rgba(244,63,94,0.03); border: 1px solid rgba(244,63,94,0.1); border-radius: 10px; padding: 1rem; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); display:block; font-weight:800;">Customer Wallet Liabilities</span>
                            <strong style="font-size: 1.15rem; color:#be123c; font-family:'Plus Jakarta Sans',sans-serif;">$<?php echo number_format($wallets_balance, 2); ?></strong>
                        </div>
                        <i class="fas fa-wallet" style="font-size: 1.5rem; color: rgba(244,63,94,0.15);"></i>
                    </div>

                    <div style="background: rgba(99,102,241,0.03); border: 1px solid rgba(99,102,241,0.1); border-radius: 10px; padding: 1rem; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-size:0.72rem; text-transform:uppercase; color:var(--text-muted); display:block; font-weight:800;">Owner Seed Capital</span>
                            <strong style="font-size: 1.15rem; color:#4338ca; font-family:'Plus Jakarta Sans',sans-serif;">$<?php echo number_format($owners_capital, 2); ?></strong>
                        </div>
                        <i class="fas fa-crown" style="font-size: 1.5rem; color: rgba(99,102,241,0.15);"></i>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <!-- TAB 2: CHART OF ACCOUNTS -->
    <?php if ($active_tab === 'accounts'): ?>
        <div class="card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin: 0;">Bolakausa Chart of Accounts</h3>
                <div class="action-buttons-group" style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="exportTableToCSV('accountsTable', 'Chart_of_Accounts.csv')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-file-csv" style="color: #10b981;"></i> CSV</button>
                    <button type="button" onclick="copyTableToClipboard('accountsTable')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-copy" style="color: #6366f1;"></i> Copy</button>
                    <button type="button" onclick="window.print()" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-print" style="color: #0284c7;"></i> Print</button>
                </div>
            </div>
            <div class="table-wrap printable-report" style="margin: 0;">
                <table id="accountsTable">
                    <thead>
                        <tr style="background: #f8fafc;">
                            <th>Code</th>
                            <th>Account Name</th>
                            <th>Account Type</th>
                            <th style="text-align: right;">Total Debit</th>
                            <th style="text-align: right;">Total Credit</th>
                            <th style="text-align: right;">Current Balance</th>
                            <th style="text-align: center; width: 140px;">Ledger</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $code => $acc): ?>
                            <tr>
                                <td style="font-family: monospace; font-weight: 700; color: var(--text-muted);"><?php echo $code; ?></td>
                                <td><strong><?php echo htmlspecialchars($acc['name']); ?></strong></td>
                                <td><span class="account-badge-indicator acc-<?php echo strtolower(str_replace(' & ', '-', $acc['type'])); ?>"><?php echo $acc['type']; ?></span></td>
                                <td style="text-align: right; color: #047857; font-family: monospace;">$<?php echo number_format($acc['debit'], 2); ?></td>
                                <td style="text-align: right; color: #be123c; font-family: monospace;">$<?php echo number_format($acc['credit'], 2); ?></td>
                                <td style="text-align: right; font-weight: 800; font-family: monospace;">$<?php echo number_format(abs($acc['balance']), 2); ?></td>
                                <td style="text-align: center;">
                                    <a href="/bolakausa/admin/accounting?tab=ledger&account_code=<?php echo $code; ?>&preset=<?php echo $preset; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-blue" style="padding: 0.35rem 0.65rem; font-size: 0.72rem; border-radius: 6px;"><i class="fas fa-book"></i> View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB 3: PROFIT & LOSS STATEMENT -->
    <?php if ($active_tab === 'pl'): ?>
        <div class="card" style="padding: 2.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div style="text-align: left;">
                    <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--secondary);">Profit and Loss Statement</h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">
                        For period: <strong><?php echo $date_from ?: 'Seeding (2026-01-01)'; ?></strong> to <strong><?php echo $date_to ?: date('Y-m-d'); ?></strong>
                    </p>
                </div>
                <div class="action-buttons-group" style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="exportTableToCSV('plTable', 'Profit_and_Loss.csv')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-file-csv" style="color: #10b981;"></i> CSV</button>
                    <button type="button" onclick="copyTableToClipboard('plTable')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-copy" style="color: #6366f1;"></i> Copy</button>
                    <button type="button" onclick="window.print()" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-print" style="color: #0284c7;"></i> Print</button>
                </div>
            </div>

            <div class="printable-report">
                <table id="plTable" class="financial-report-table">
                <tbody>
                    <!-- Operating Revenue -->
                    <tr class="section-title-row">
                        <td colspan="2">Operating Revenue</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Product Sales Revenue (4010)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($accounts['4010']['balance'], 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Shipping Revenue (4020)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($accounts['4020']['balance'], 2); ?></td>
                    </tr>
                    <tr style="border-top: 1px solid #e2e8f0; font-weight: 700;">
                        <td style="padding-left: 3.5rem;">Gross Revenue</td>
                        <td style="text-align: right; font-family: monospace; border-top: 1px solid var(--text-main);">$<?php echo number_format($total_revenue, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem; color: #be123c;">Less: Sales Discounts & Coupons (6010)</td>
                        <td style="text-align: right; color: #be123c; font-family: monospace;">-$<?php echo number_format($sales_discounts, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Net Sales Revenue</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($net_sales, 2); ?></td>
                    </tr>

                    <!-- Cost of Goods Sold -->
                    <tr class="section-title-row">
                        <td colspan="2">Cost of Goods Sold (COGS)</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem; color: #b45309;">Estimated Cost of Goods Sold (5010)</td>
                        <td style="text-align: right; color: #b45309; font-family: monospace;">$<?php echo number_format($cogs, 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Gross Profit / Margin</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($gross_profit, 2); ?></td>
                    </tr>

                    <!-- Operating Expenses -->
                    <tr class="section-title-row">
                        <td colspan="2">Operating Expenses</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Rejection Charges & Order Cancellation Refunds (6020)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($accounts['6020']['balance'], 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">General & Administrative Adjustments (6030)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($accounts['6030']['balance'], 2); ?></td>
                    </tr>
                    <tr style="font-weight: 700; border-top: 1px solid #cbd5e1;">
                        <td style="padding-left: 3.5rem;">Total Operating Expenses</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($total_expenses, 2); ?></td>
                    </tr>

                    <!-- Net Income -->
                    <tr class="grand-total-row">
                        <td style="font-weight: 900;">NET OPERATING INCOME / PROFIT</td>
                        <td style="text-align: right; font-family: monospace; font-weight: 900;">$<?php echo number_format($net_income, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            </div> <!-- Close printable-report -->
        </div>
    <?php endif; ?>

    <!-- TAB 4: BALANCE SHEET -->
    <?php if ($active_tab === 'balance_sheet'): ?>
        <div class="card" style="padding: 2.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div style="text-align: left;">
                    <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.5rem; font-weight: 800; color: var(--secondary); margin: 0;">Balance Sheet</h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">
                        As of: <strong><?php echo $date_to ?: date('Y-m-d'); ?></strong>
                    </p>
                    <span style="display:inline-block; margin-top:0.5rem; font-size:0.72rem; font-weight:800; text-transform:uppercase; padding:4px 10px; border-radius:6px; background:rgba(16,185,129,0.1); color:#047857; border: 1px solid rgba(16,185,129,0.2);">
                        <i class="fas fa-check-double"></i> Balanced Trial (Dr = Cr)
                    </span>
                </div>
                <div class="action-buttons-group" style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="exportTableToCSV('balanceSheetTable', 'Balance_Sheet.csv')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-file-csv" style="color: #10b981;"></i> CSV</button>
                    <button type="button" onclick="copyTableToClipboard('balanceSheetTable')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-copy" style="color: #6366f1;"></i> Copy</button>
                    <button type="button" onclick="window.print()" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-print" style="color: #0284c7;"></i> Print</button>
                </div>
            </div>

            <div class="printable-report">
                <table id="balanceSheetTable" class="financial-report-table">
                <tbody>
                    <!-- ASSETS -->
                    <tr class="section-title-row">
                        <td colspan="2">Assets (Debit Balances)</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Cash & Bank Assets (1010)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($cash_balance, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Accounts Receivable (1200)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($ar_balance, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Valued Inventory Asset (1400)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($inventory_balance, 2); ?></td>
                    </tr>
                    <tr class="total-row" style="background: rgba(2, 132, 199, 0.05);">
                        <td>TOTAL ASSETS</td>
                        <td style="text-align: right; font-family: monospace; font-weight: 800;">$<?php echo number_format($total_assets, 2); ?></td>
                    </tr>

                    <!-- LIABILITIES -->
                    <tr class="section-title-row">
                        <td colspan="2">Liabilities (Credit Balances)</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Client Wallet Liabilities / Prepayments (2010)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($wallets_balance, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Sales Tax Collected Liabilities (2200)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($tax_balance, 2); ?></td>
                    </tr>
                    <tr style="font-weight: 700; border-top: 1px solid #cbd5e1;">
                        <td style="padding-left: 3.5rem;">Total Liabilities</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($total_liabilities, 2); ?></td>
                    </tr>

                    <!-- EQUITY -->
                    <tr class="section-title-row">
                        <td colspan="2">Equity (Capital Balances)</td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Owner seed capital (3010)</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($owners_capital, 2); ?></td>
                    </tr>
                    <tr>
                        <td style="padding-left: 3rem;">Retained Earnings / Net Income</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($retained_earnings, 2); ?></td>
                    </tr>
                    <tr style="font-weight: 700; border-top: 1px solid #cbd5e1;">
                        <td style="padding-left: 3.5rem;">Total Owner's Equity</td>
                        <td style="text-align: right; font-family: monospace;">$<?php echo number_format($total_equity, 2); ?></td>
                    </tr>

                    <!-- Balanced Summary row -->
                    <tr class="total-row" style="background: rgba(16, 185, 129, 0.05);">
                        <td>TOTAL LIABILITIES & EQUITY</td>
                        <td style="text-align: right; font-family: monospace; font-weight: 800;">$<?php echo number_format($total_liabilities + $total_equity, 2); ?></td>
                    </tr>
                </tbody>
            </table>
            </div> <!-- Close printable-report -->
        </div>
    <?php endif; ?>

    <!-- TAB 5: MANUAL ADJUSTING JOURNALS -->
    <?php if ($active_tab === 'journals'): ?>
        <div class="card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--secondary); margin: 0;">Manual Accounting adjustments log</h3>
                <?php if (!empty($journals)): ?>
                    <div class="action-buttons-group" style="display: flex; gap: 0.5rem;">
                        <button type="button" onclick="exportTableToCSV('journalsTable', 'Manual_Journals.csv')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-file-csv" style="color: #10b981;"></i> CSV</button>
                        <button type="button" onclick="copyTableToClipboard('journalsTable')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-copy" style="color: #6366f1;"></i> Copy</button>
                        <button type="button" onclick="window.print()" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-print" style="color: #0284c7;"></i> Print</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($journals)): ?>
                <div style="background: rgba(15,23,42,0.02); text-align: center; padding: 3rem; border-radius: 12px; border: 1px dashed var(--border-light); color: var(--text-muted); font-size: 0.95rem;">
                    <i class="fas fa-journal-whills" style="font-size: 2.5rem; color: #0284c7; margin-bottom: 1rem; display: block;"></i>
                    No manual journals posted yet. Adjustments can be recorded by system Administrators.
                </div>
            <?php else: ?>
                <div class="table-wrap printable-report" style="margin: 0;">
                    <table id="journalsTable">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Date</th>
                                <th>Journal ID</th>
                                <th>Debit Account (Dr)</th>
                                <th>Credit Account (Cr)</th>
                                <th style="text-align: right;">Adjustment Amount</th>
                                <th>Description Description</th>
                                <th>Linked Client</th>
                                <th>Reference ID</th>
                                <th>Recorded By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($journals as $j): ?>
                                <tr>
                                    <td><strong><?php echo date('M d, Y', strtotime($j['journal_date'])); ?></strong></td>
                                    <td style="font-family: monospace; font-weight: 700;">#JNL-<?php echo $j['id']; ?></td>
                                    <td>
                                        <span class="account-badge-indicator acc-<?php echo strtolower(str_replace(' & ', '-', $accounts[$j['debit_account']]['type'] ?? '')); ?>">
                                            <?php echo $j['debit_account']; ?>
                                        </span>
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px; font-weight: 600;"><?php echo htmlspecialchars($accounts[$j['debit_account']]['name'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <span class="account-badge-indicator acc-<?php echo strtolower(str_replace(' & ', '-', $accounts[$j['credit_account']]['type'] ?? '')); ?>">
                                            <?php echo $j['credit_account']; ?>
                                        </span>
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px; font-weight: 600;"><?php echo htmlspecialchars($accounts[$j['credit_account']]['name'] ?? ''); ?></div>
                                    </td>
                                    <td style="text-align: right; font-weight: 800; color: #0284c7; font-family: monospace;">$<?php echo number_format($j['amount'], 2); ?></td>
                                    <td style="font-size: 0.8rem; max-width: 200px; word-wrap: break-word;"><?php echo htmlspecialchars($j['description']); ?></td>
                                    <td>
                                        <?php if ($j['customer_name']): ?>
                                            <span style="font-weight:700; color: var(--accent);">@<?php echo htmlspecialchars($j['customer_name']); ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.75rem;">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($j['reference_id'] ?: 'N/A'); ?></td>
                                    <td><span style="font-size:0.75rem; font-weight: 700;">@<?php echo htmlspecialchars($j['creator_name']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div> <!-- Close journalsTable printable-report -->
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- TAB 6: GENERAL ACCOUNT LEDGER -->
    <?php if ($active_tab === 'ledger'): ?>
        <?php 
            $selected_code = $_GET['account_code'] ?? '1010';
            if (!isset($accounts[$selected_code])) $selected_code = '1010';
            $selected_account = $accounts[$selected_code];
            
            // Extract records for this account
            $account_ledger = [];
            $running_bal = 0;
            
            foreach ($ledger_entries as $entry) {
                // Apply Date Range filters
                if ($date_from && $entry['date'] < $date_from) continue;
                if ($date_to && $entry['date'] > $date_to) continue;

                $is_debit = ($entry['debit_acc'] === $selected_code);
                $is_credit = ($entry['credit_acc'] === $selected_code);

                if ($is_debit || $is_credit) {
                    $amt = $entry['amount'];
                    
                    if ($is_debit) {
                        $dr_val = $amt;
                        $cr_val = 0;
                        if (in_array($selected_account['type'], ['Asset', 'COGS', 'Expense'])) {
                            $running_bal += $amt;
                        } else {
                            $running_bal -= $amt;
                        }
                    } else {
                        $dr_val = 0;
                        $cr_val = $amt;
                        if (in_array($selected_account['type'], ['Asset', 'COGS', 'Expense'])) {
                            $running_bal -= $amt;
                        } else {
                            $running_bal += $amt;
                        }
                    }
                    
                    $account_ledger[] = [
                        'date' => $entry['date'],
                        'desc' => $entry['desc'],
                        'ref' => $entry['ref'],
                        'debit' => $dr_val,
                        'credit' => $cr_val,
                        'running_balance' => $running_bal
                    ];
                }
            }
        ?>
        <div class="card" style="padding: 1.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.75rem; flex-wrap:wrap; gap: 1rem;">
                <div>
                    <span style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-muted);">Account Ledger Ledger</span>
                    <h3 style="font-family:'Plus Jakarta Sans',sans-serif; font-size:1.35rem; font-weight:800; color:var(--secondary); margin-top:0.2rem; display:flex; align-items:center; gap:8px;">
                        <strong>[<?php echo $selected_code; ?>] <?php echo htmlspecialchars($selected_account['name']); ?></strong>
                        <span class="account-badge-indicator acc-<?php echo strtolower(str_replace(' & ', '-', $selected_account['type'])); ?>"><?php echo $selected_account['type']; ?></span>
                    </h3>
                </div>
                
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <?php if (!empty($account_ledger)): ?>
                        <div class="action-buttons-group" style="display: flex; gap: 0.5rem;">
                            <button type="button" onclick="exportTableToCSV('ledgerTable', 'Account_Ledger.csv')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-file-csv" style="color: #10b981;"></i> CSV</button>
                            <button type="button" onclick="copyTableToClipboard('ledgerTable')" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-copy" style="color: #6366f1;"></i> Copy</button>
                            <button type="button" onclick="window.print()" class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.775rem; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: var(--text-main); font-weight: 700; cursor: pointer;"><i class="fas fa-print" style="color: #0284c7;"></i> Print</button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="GET" action="/bolakausa/admin/accounting" style="display:flex; align-items:center; gap:0.5rem;">
                        <input type="hidden" name="tab" value="ledger">
                        <input type="hidden" name="preset" value="<?php echo e($preset); ?>">
                        <input type="hidden" name="date_from" value="<?php echo e($date_from); ?>">
                        <input type="hidden" name="date_to" value="<?php echo e($date_to); ?>">
                        <select name="account_code" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; min-width: 220px;">
                            <?php foreach ($accounts as $c => $a): ?>
                                <option value="<?php echo $c; ?>" <?php echo $c === $selected_code ? 'selected' : ''; ?>>[<?php echo $c; ?>] <?php echo htmlspecialchars($a['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <?php if (empty($account_ledger)): ?>
                <div style="background: rgba(15,23,42,0.02); text-align: center; padding: 3rem; border-radius: 12px; border: 1px dashed var(--border-light); color: var(--text-muted); font-size: 0.95rem;">
                    No transactions found for this account within the selected time period.
                </div>
            <?php else: ?>
                <div class="table-wrap printable-report" style="margin: 0;">
                    <table id="ledgerTable">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th>Date</th>
                                <th>Transaction Detail</th>
                                <th>Reference Number</th>
                                <th style="text-align: right; width: 130px;">Debit (Dr)</th>
                                <th style="text-align: right; width: 130px;">Credit (Cr)</th>
                                <th style="text-align: right; width: 150px;">Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($account_ledger as $row): ?>
                                <tr>
                                    <td><strong><?php echo date('M d, Y', strtotime($row['date'])); ?></strong></td>
                                    <td><span style="font-size:0.85rem; color:var(--text-main);"><?php echo $row['desc']; ?></span></td>
                                    <td><span style="font-family: monospace; font-size: 0.8rem; font-weight:700;"><?php echo $row['ref']; ?></span></td>
                                    <td style="text-align: right; font-family: monospace; color: <?php echo $row['debit'] > 0 ? '#047857; font-weight:700;' : 'var(--text-muted);'; ?>">
                                        <?php echo $row['debit'] > 0 ? '$' . number_format($row['debit'], 2) : '-'; ?>
                                    </td>
                                    <td style="text-align: right; font-family: monospace; color: <?php echo $row['credit'] > 0 ? '#be123c; font-weight:700;' : 'var(--text-muted);'; ?>">
                                        <?php echo $row['credit'] > 0 ? '$' . number_format($row['credit'], 2) : '-'; ?>
                                    </td>
                                    <td style="text-align: right; font-family: monospace; font-weight: 800; color: var(--secondary);">
                                        $<?php echo number_format($row['running_balance'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<!-- 9. MANUAL JOURNAL MODAL FORM (Only available to Admins) -->
<?php if ($user_role === 'admin'): ?>
    <div id="journalModal" class="journal-modal">
        <div class="journal-modal-content">
            <button onclick="closeJournalModal()" style="position: absolute; right: 1.5rem; top: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);"><i class="fas fa-times"></i></button>
            
            <h2 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; font-size: 1.35rem; color: var(--secondary); margin-bottom: 0.5rem; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-book-open" style="color: #0284c7;"></i> Post Manual Journal Entry
            </h2>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1.75rem; line-height: 1.4;">Adjust ledger accounts to record expenses, reconcile topups, or make manual adjustments. All manual postings are logged in the audit trail.</p>
            
            <form method="POST" action="/bolakausa/admin/accounting?tab=journals">
                <input type="hidden" name="post_journal" value="1">
                <input type="hidden" name="_csrf_token" value="<?php echo csrf_token(); ?>">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--secondary);">Journal Posting Date</label>
                    <input type="date" name="journal_date" value="<?php echo date('Y-m-d'); ?>" required style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #047857;">Debit Account (Dr)</label>
                        <select name="debit_account" required style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem; font-weight: 600;">
                            <?php foreach ($accounts as $c => $a): ?>
                                <option value="<?php echo $c; ?>">[<?php echo $c; ?>] <?php echo htmlspecialchars($a['name']); ?> (<?php echo $a['type']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #be123c;">Credit Account (Cr)</label>
                        <select name="credit_account" required style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem; font-weight: 600;">
                            <?php foreach ($accounts as $c => $a): ?>
                                <option value="<?php echo $c; ?>" <?php echo $c === '3010' ? 'selected' : ''; ?>>[<?php echo $c; ?>] <?php echo htmlspecialchars($a['name']); ?> (<?php echo $a['type']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--secondary);">Adjustment Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem; font-weight: 700;">
                    </div>
                    
                    <div class="form-group">
                        <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--secondary);">Reference ID (Optional)</label>
                        <input type="text" name="reference_id" placeholder="e.g. TXN-REF-1002" style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--secondary);">Linked Business Customer (Optional)</label>
                    <select name="user_id" style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem;">
                        <option value="">-- No linked customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>">@<?php echo htmlspecialchars($c['username']); ?> (<?php echo $c['role']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.75rem;">
                    <label style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: var(--secondary);">Adjustment Notes / Description</label>
                    <textarea name="description" rows="3" required placeholder="Describe the purpose of this manual ledger adjustment entry..." style="border-radius: 8px; padding: 0.55rem; font-size: 0.85rem; resize: none;"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" onclick="closeJournalModal()" class="btn btn-secondary" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; border: 1px solid #cbd5e1;">Cancel</button>
                    <button type="submit" class="btn btn-blue" style="padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem; background: #0284c7;"><i class="fas fa-check-circle"></i> Post Adjustment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openJournalModal() {
        document.getElementById('journalModal').style.display = 'flex';
    }
    function closeJournalModal() {
        document.getElementById('journalModal').style.display = 'none';
    }
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('journalModal');
        if (e.target === modal) {
            closeJournalModal();
        }
    });
    </script>
<?php endif; ?>

<script>
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    let csv = [];
    const rows = table.querySelectorAll("tr");
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].style.display === 'none') continue;
        let row = [];
        const cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            // Ignore action columns/buttons
            if (cols[j].querySelector("a.btn") || cols[j].querySelector("button")) {
                if (j === cols.length - 1 && tableId === 'accountsTable') continue;
            }
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/^\s+|\s+$/g, "");
            // Escape double quotes
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        if (row.length > 0) {
            csv.push(row.join(","));
        }
    }
    const csvString = csv.join("\n");
    const blob = new Blob([csvString], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function copyTableToClipboard(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    let txt = [];
    const rows = table.querySelectorAll("tr");
    for (let i = 0; i < rows.length; i++) {
        if (rows[i].style.display === 'none') continue;
        let row = [];
        const cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            if (cols[j].querySelector("a.btn") || cols[j].querySelector("button")) {
                if (j === cols.length - 1 && tableId === 'accountsTable') continue;
            }
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/^\s+|\s+$/g, "");
            row.push(data);
        }
        if (row.length > 0) {
            txt.push(row.join("\t"));
        }
    }
    const clipboardText = txt.join("\n");
    navigator.clipboard.writeText(clipboardText).then(() => {
        const toast = document.createElement("div");
        toast.innerText = "Table data copied to clipboard!";
        toast.style.position = "fixed";
        toast.style.bottom = "20px";
        toast.style.right = "20px";
        toast.style.background = "#10b981";
        toast.style.color = "white";
        toast.style.padding = "0.75rem 1.5rem";
        toast.style.borderRadius = "8px";
        toast.style.fontWeight = "bold";
        toast.style.zIndex = "99999";
        toast.style.boxShadow = "0 4px 12px rgba(16,185,129,0.3)";
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2500);
    }).catch(err => {
        alert("Failed to copy data: " + err);
    });
}
</script>
