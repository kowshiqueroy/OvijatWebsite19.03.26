<?php
require_once '../../includes/functions.php';
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$company = get_company_settings();
$customer_id = intval($_GET['customer_id'] ?? 0);
$start_date  = $_GET['start_date'] ?? date('Y-m-01');
$end_date    = $_GET['end_date']   ?? date('Y-m-d');

$customers = fetch_all("SELECT id, name, phone, balance FROM customers WHERE isDelete = 0 ORDER BY name ASC");

$ledger   = [];
$customer = null;
$opening_dr = 0;
$opening_cr = 0;

if ($customer_id) {
    $customer = fetch_one("SELECT * FROM customers WHERE id = ?", [$customer_id]);

    // Opening balance: sum of all confirmed invoices + all transactions BEFORE start_date
    $ob_invoices = fetch_one(
        "SELECT COALESCE(SUM(grand_total),0) as total FROM sales_drafts
         WHERE customer_id=? AND status='Confirmed' AND isDelete=0 AND DATE(created_at) < ?",
        [$customer_id, $start_date]
    );
    $ob_credits = fetch_one(
        "SELECT COALESCE(SUM(amount),0) as total FROM transactions
         WHERE customer_id=? AND type='Credit' AND isDelete=0 AND DATE(created_at) < ?",
        [$customer_id, $start_date]
    );
    $ob_debits = fetch_one(
        "SELECT COALESCE(SUM(amount),0) as total FROM transactions
         WHERE customer_id=? AND type='Debit' AND isDelete=0 AND DATE(created_at) < ?",
        [$customer_id, $start_date]
    );

    $opening_dr = floatval($ob_invoices['total']) + floatval($ob_debits['total']) + floatval($customer['opening_balance']);
    $opening_cr = floatval($ob_credits['total']);

    // Period: invoices (debit)
    $invoices = fetch_all(
        "SELECT 'Invoice' as entry_type, id, grand_total as amount, created_at,
                'Debit' as type, CONCAT('Invoice #',id) as note, hide_from_print
         FROM sales_drafts
         WHERE customer_id=? AND status='Confirmed' AND isDelete=0
         AND DATE(created_at) BETWEEN ? AND ?
         ORDER BY created_at ASC",
        [$customer_id, $start_date, $end_date]
    );

    // Period: transactions (credit/debit)
    $txns = fetch_all(
        "SELECT 'Transaction' as entry_type, id, amount, created_at, type, description as note, hide_from_print
         FROM transactions
         WHERE customer_id=? AND isDelete=0
         AND DATE(created_at) BETWEEN ? AND ?
         ORDER BY created_at ASC",
        [$customer_id, $start_date, $end_date]
    );

    // Period: approved returns (credit)
    $returns = fetch_all(
        "SELECT 'Return' as entry_type, id, total_amount as amount, processed_at as created_at,
                'Credit' as type, CONCAT('Return #',id) as note, 0 as hide_from_print
         FROM sales_returns
         WHERE customer_id=? AND status='Approved' AND isDelete=0
         AND DATE(processed_at) BETWEEN ? AND ?",
        [$customer_id, $start_date, $end_date]
    );

    $ledger = array_merge($invoices, $txns, $returns);
    usort($ledger, fn($a,$b) => strtotime($a['created_at']) - strtotime($b['created_at']));

    // Fetch products for all invoice and return entries to show inside ledger list
    $invoice_ids = [];
    $return_ids = [];
    foreach ($ledger as $row) {
        if ($row['entry_type'] === 'Invoice') {
            $invoice_ids[] = intval($row['id']);
        } elseif ($row['entry_type'] === 'Return') {
            $return_ids[] = intval($row['id']);
        }
    }

    $invoice_products = [];
    if (!empty($invoice_ids)) {
        $id_list = implode(',', $invoice_ids);
        $items_raw = fetch_all("
            SELECT si.draft_id, p.name as product_name, si.billed_qty, si.free_qty 
            FROM sales_items si 
            JOIN products p ON si.product_id = p.id 
            WHERE si.draft_id IN ($id_list) AND si.isDelete = 0 AND p.isDelete = 0
        ");
        foreach ($items_raw as $item) {
            $qty_desc = $item['billed_qty'];
            if ($item['free_qty'] > 0) {
                $qty_desc .= " + " . $item['free_qty'] . " Free";
            }
            $invoice_products[$item['draft_id']][] = $item['product_name'] . " (" . $qty_desc . ")";
        }
    }

    $return_products = [];
    if (!empty($return_ids)) {
        $id_list = implode(',', $return_ids);
        $items_raw = fetch_all("
            SELECT sri.return_id, p.name as product_name, sri.quantity 
            FROM sales_return_items sri 
            JOIN products p ON sri.product_id = p.id 
            WHERE sri.return_id IN ($id_list) AND sri.isDelete = 0 AND p.isDelete = 0
        ");
        foreach ($items_raw as $item) {
            $return_products[$item['return_id']][] = $item['product_name'] . " (" . $item['quantity'] . ")";
        }
    }
}

include '../../templates/header.php';
?>

<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="fas fa-book-open me-2 text-primary"></i>Customer Ledger</h5>
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Customer</label>
                        <select name="customer_id" class="form-select select2">
                            <option value="">-- Select Customer --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $customer_id == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?> (<?= $c['phone'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">From Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">To Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> View</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($customer): ?>
<div class="text-end mb-3 no-print">
    <button onclick="window.print()" class="btn btn-dark me-2"><i class="fas fa-print me-1"></i> Print</button>
    <a href="?customer_id=<?= $customer_id ?>&start_date=<?= date('Y-01-01') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Full Year</a>
</div>

<div class="ledger-print-wrap bg-white p-4 shadow-sm rounded">
    <!-- Company Header -->
    <div class="row border-bottom pb-3 mb-4">
        <div class="col-7">
            <h3 class="text-primary mb-0 fw-bold"><?= htmlspecialchars($company['name'] ?? '') ?></h3>
            <p class="text-muted mb-0 small"><?= htmlspecialchars($company['address'] ?? '') ?></p>
            <p class="text-muted small">Phone: <?= htmlspecialchars($company['phone'] ?? '') ?></p>
        </div>
        <div class="col-5 text-end">
            <h5 class="fw-bold text-dark">CUSTOMER LEDGER</h5>
            <p class="mb-0 small">Period: <strong><?= date('d M Y', strtotime($start_date)) ?></strong> to <strong><?= date('d M Y', strtotime($end_date)) ?></strong></p>
            <p class="mb-0 small">Printed: <?= date('d M Y H:i') ?></p>
        </div>
    </div>

    <!-- Customer Info -->
    <div class="row mb-4 p-3 rounded" style="background:#f8f9fa">
        <div class="col-md-6">
            <h5 class="mb-1 fw-bold"><?= htmlspecialchars($customer['name']) ?></h5>
            <p class="mb-0 text-muted"><?= htmlspecialchars($customer['address'] ?? '') ?></p>
            <p class="mb-0">📞 <?= htmlspecialchars($customer['phone']) ?> &nbsp;|&nbsp; Type: <span class="badge bg-primary"><?= $customer['type'] ?></span></p>
        </div>
        <div class="col-md-6 text-md-end">
            <p class="mb-1">Opening Balance (before period):</p>
            <?php $ob_net = $opening_dr - $opening_cr; ?>
            <h5 class="fw-bold <?= $ob_net > 0 ? 'text-danger' : ($ob_net < 0 ? 'text-success' : '') ?>">
                <?= format_currency(abs($ob_net)) ?>
                <small class="fs-6"><?= $ob_net > 0 ? '(DR)' : ($ob_net < 0 ? '(CR)' : '') ?></small>
            </h5>
        </div>
    </div>

    <!-- Ledger Table -->
    <table class="table table-bordered table-sm ledger-table">
        <thead class="table-dark">
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th class="text-end">Debit (৳)</th>
                <th class="text-end">Credit (৳)</th>
                <th class="text-end">Balance (৳)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Opening Row -->
            <tr class="table-secondary fw-semibold">
                <td><?= date('d M Y', strtotime($start_date)) ?></td>
                <td>Opening Balance</td>
                <td>—</td>
                <td class="text-end"><?= $ob_net > 0 ? number_format($ob_net, 2) : '—' ?></td>
                <td class="text-end"><?= $ob_net < 0 ? number_format(abs($ob_net), 2) : '—' ?></td>
                <td class="text-end fw-bold"><?= number_format(abs($ob_net), 2) ?> <?= $ob_net > 0 ? 'Dr' : ($ob_net < 0 ? 'Cr' : '') ?></td>
            </tr>

            <?php
            $running = $ob_net; // positive = Dr (customer owes)
            $total_dr = 0;
            $total_cr = 0;
            foreach ($ledger as $row):
                $is_debit = $row['type'] === 'Debit' || $row['entry_type'] === 'Invoice';
                $amt = floatval($row['amount']);
                if ($is_debit) {
                    $running += $amt;
                    $total_dr += $amt;
                } else {
                    $running -= $amt;
                    $total_cr += $amt;
                }
                $bal_type = $running > 0 ? 'Dr' : ($running < 0 ? 'Cr' : '');
            ?>
            <tr>
                <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                <td>
                    <span class="badge <?= $is_debit ? 'bg-danger' : 'bg-success' ?>">
                        <?= $row['entry_type'] ?>
                    </span>
                </td>
                <td class="small text-muted text-wrap" style="max-width: 300px;">
                    <?= htmlspecialchars($row['note'] ?? '') ?>
                    <?php if ($row['entry_type'] === 'Invoice' && isset($invoice_products[$row['id']])): ?>
                        <div class="text-primary mt-1" style="font-size: 10px; font-weight: 500;">
                            <i class="fas fa-box me-1"></i> <?= htmlspecialchars(implode(', ', $invoice_products[$row['id']])) ?>
                        </div>
                    <?php elseif ($row['entry_type'] === 'Return' && isset($return_products[$row['id']])): ?>
                        <div class="text-success mt-1" style="font-size: 10px; font-weight: 500;">
                            <i class="fas fa-rotate-left me-1"></i> <?= htmlspecialchars(implode(', ', $return_products[$row['id']])) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="text-end <?= $is_debit ? 'text-danger fw-semibold' : 'text-muted' ?>">
                    <?= $is_debit ? number_format($amt, 2) : '—' ?>
                </td>
                <td class="text-end <?= !$is_debit ? 'text-success fw-semibold' : 'text-muted' ?>">
                    <?= !$is_debit ? number_format($amt, 2) : '—' ?>
                </td>
                <td class="text-end fw-semibold <?= $running > 0 ? 'text-danger' : ($running < 0 ? 'text-success' : '') ?>">
                    <?= number_format(abs($running), 2) ?> <?= $bal_type ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <!-- Totals Row -->
            <tr class="table-dark fw-bold">
                <td colspan="3">Total for Period</td>
                <td class="text-end text-danger"><?= number_format($total_dr, 2) ?></td>
                <td class="text-end text-success"><?= number_format($total_cr, 2) ?></td>
                <td class="text-end <?= $running > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= number_format(abs($running), 2) ?> <?= $running > 0 ? 'Dr' : 'Cr' ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-4">
        <div class="col-md-6"></div>
        <div class="col-md-6">
            <table class="table table-bordered table-sm">
                <tr class="table-light"><th>Current Balance (System)</th>
                    <td class="text-end fw-bold <?= $customer['balance'] < 0 ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency(abs($customer['balance'])) ?> <?= $customer['balance'] < 0 ? 'Due' : 'Advance' ?>
                    </td>
                </tr>
                <tr class="table-light"><th>Closing Balance (Ledger)</th>
                    <td class="text-end fw-bold <?= $running > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency(abs($running)) ?> <?= $running > 0 ? 'Due' : 'Advance' ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="text-center py-5 text-muted">
    <i class="fas fa-book fa-3x mb-3 opacity-25"></i>
    <p>Select a customer and date range to view their ledger.</p>
</div>
<?php endif; ?>

<?php include '../../templates/footer.php'; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .sidebar-wrapper, #menu-toggle { display: none !important; }
    .page-content-wrapper { margin-left: 0 !important; }
    body { background: white; }
    .ledger-print-wrap { box-shadow: none !important; }
}
.ledger-table td, .ledger-table th { font-size: .85rem; padding: 6px 10px; }
</style>
