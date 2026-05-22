<?php
require_once '../../templates/header.php';
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$customer_id = $_GET['customer_id'] ?? 0;
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$customers = fetch_all("SELECT id, name, phone FROM customers WHERE isDelete = 0 ORDER BY name ASC");

$ledger = [];
$customer = null;

if ($customer_id) {
    $customer = fetch_one("SELECT * FROM customers WHERE id = ?", [$customer_id]);
    
    // 1. Get Invoices (Debits)
    $invoices = fetch_all("SELECT 'Invoice' as entry_type, id, grand_total as amount, created_at, 'Debit' as type, delivery_status as note, hide_from_print 
                           FROM sales_drafts 
                           WHERE customer_id = ? AND status = 'Confirmed' AND isDelete = 0 
                           AND DATE(created_at) BETWEEN ? AND ?", [$customer_id, $start_date, $end_date]);
    
    // 2. Get Transactions (Credits/Manual Debits)
    $transactions = fetch_all("SELECT 'Transaction' as entry_type, id, amount, created_at, type, description as note, hide_from_print 
                               FROM transactions 
                               WHERE customer_id = ? AND isDelete = 0 
                               AND DATE(created_at) BETWEEN ? AND ?", [$customer_id, $start_date, $end_date]);
    
    // Merge and Sort
    $ledger = array_merge($invoices, $transactions);
    usort($ledger, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
}
?>

<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Select Customer</label>
                        <select name="customer_id" class="form-select select2" required>
                            <option value="">-- Choose Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($customer_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo $c['name']; ?> (<?php echo $c['phone']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Generate Ledger</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($customer): ?>
<div class="text-end mb-3 no-print">
    <button onclick="window.print()" class="btn btn-dark"><i class="fas fa-print me-1"></i> Print Ledger</button>
</div>

<div class="ledger-print-wrap bg-white p-4 shadow-sm">
    <div class="row border-bottom pb-3 mb-4">
        <div class="col-6">
            <h2 class="text-primary"><?php echo $company['name']; ?></h2>
            <p class="mb-0"><?php echo $company['address']; ?></p>
            <p>Phone: <?php echo $company['phone']; ?></p>
        </div>
        <div class="col-6 text-end">
            <h4>CUSTOMER LEDGER</h4>
            <p class="mb-0"><strong>Customer:</strong> <?php echo $customer['name']; ?></p>
            <p class="mb-0"><strong>Period:</strong> <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></p>
            <p class="mb-0"><strong>Current Balance:</strong> <?php echo format_currency($customer['balance']); ?></p>
        </div>
    </div>

    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Reference</th>
                <th>Description / Note</th>
                <th class="text-end">Debit (+)</th>
                <th class="text-end">Credit (-)</th>
                <th class="text-end">Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $running_balance = 0; // In a real system, you'd calculate opening balance before start_date
            ?>
            <tr>
                <td colspan="6" class="text-end text-muted"><em>Opening Balance (Prior to <?php echo $start_date; ?>)</em></td>
                <td class="text-end fw-bold">---</td>
            </tr>
            <?php foreach ($ledger as $row): ?>
                <?php 
                    // Skip hidden rows in print mode
                    $hidden_class = $row['hide_from_print'] ? 'table-warning d-print-none' : '';
                ?>
                <tr class="<?php echo $hidden_class; ?>">
                    <td><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                    <td><span class="badge <?php echo ($row['entry_type'] == 'Invoice') ? 'bg-info' : 'bg-secondary'; ?>"><?php echo $row['entry_type']; ?></span></td>
                    <td>#<?php echo $row['id']; ?></td>
                    <td>
                        <?php echo $row['note']; ?>
                        <?php if($row['hide_from_print']): ?>
                            <small class="text-danger ms-2">[HIDDEN FROM PRINT]</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?php echo ($row['type'] == 'Debit') ? number_format($row['amount'], 2) : '-'; ?></td>
                    <td class="text-end"><?php echo ($row['type'] == 'Credit') ? number_format($row['amount'], 2) : '-'; ?></td>
                    <td class="text-end">
                        <?php 
                            if ($row['type'] == 'Debit') $running_balance -= $row['amount'];
                            else $running_balance += $row['amount'];
                            echo number_format($running_balance, 2);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="table-primary">
                <td colspan="4" class="text-end fw-bold">TOTALS FOR PERIOD</td>
                <td class="text-end fw-bold">
                    <?php 
                        $total_debit = array_sum(array_column(array_filter($ledger, function($r){ return $r['type'] == 'Debit'; }), 'amount'));
                        echo number_format($total_debit, 2);
                    ?>
                </td>
                <td class="text-end fw-bold">
                    <?php 
                        $total_credit = array_sum(array_column(array_filter($ledger, function($r){ return $r['type'] == 'Credit'; }), 'amount'));
                        echo number_format($total_credit, 2);
                    ?>
                </td>
                <td class="text-end fw-bold"><?php echo number_format($running_balance, 2); ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-5 pt-4 d-none d-print-block">
        <div class="row">
            <div class="col-4 text-center border-top">Customer Signature</div>
            <div class="col-4"></div>
            <div class="col-4 text-center border-top">Authorized Signature</div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    @media print {
        #sidebar-wrapper, .navbar, .no-print, .alert { display: none !important; }
        #page-content-wrapper { padding: 0 !important; width: 100% !important; margin: 0 !important; }
        .container-fluid { padding: 0 !important; }
        .ledger-print-wrap { box-shadow: none !important; padding: 0 !important; }
        .d-print-none { display: none !important; }
    }
</style>

<?php require_once '../../templates/footer.php'; ?>
