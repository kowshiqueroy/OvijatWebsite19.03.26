<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$start = $_GET['start'] ?? date('Y-01-01');
$end   = $_GET['end']   ?? date('Y-m-d');

// Income accounts (nature = Income)
$income_groups = fetch_all("SELECT ag.id, ag.name FROM account_groups ag WHERE ag.nature='Income' AND ag.isDelete=0 ORDER BY ag.name");
$expense_groups = fetch_all("SELECT ag.id, ag.name FROM account_groups ag WHERE ag.nature='Expense' AND ag.isDelete=0 ORDER BY ag.name");

function get_group_total($group_id, $start, $end) {
    $accounts = fetch_all("SELECT id FROM accounts WHERE group_id=? AND isDelete=0", [$group_id]);
    $total = 0;
    foreach ($accounts as $a) {
        // Sum journal lines in this date range
        $r = fetch_one(
            "SELECT COALESCE(SUM(jl.cr_amount),0) - COALESCE(SUM(jl.dr_amount),0) as net
             FROM journal_lines jl JOIN journal_entries je ON jl.journal_id = je.id
             WHERE jl.account_id=? AND jl.isDelete=0 AND je.isDelete=0 AND je.date BETWEEN ? AND ?",
            [$a['id'], $start, $end]
        );
        $total += floatval($r['net'] ?? 0);
    }
    return $total;
}

$total_income = 0;
$income_rows = [];
foreach ($income_groups as $g) {
    $t = get_group_total($g['id'], $start, $end);
    $income_rows[] = ['name' => $g['name'], 'amount' => $t];
    $total_income += $t;
}

$total_expense = 0;
$expense_rows = [];
foreach ($expense_groups as $g) {
    $t = -get_group_total($g['id'], $start, $end); // expenses are typically Dr-heavy
    $expense_rows[] = ['name' => $g['name'], 'amount' => $t];
    $total_expense += $t;
}

$net_profit = $total_income - $total_expense;
?>

<style>@media print { .no-print { display:none!important; } }</style>

<div class="row align-items-center mb-4 no-print">
    <div class="col"><h3><i class="fas fa-chart-line me-2"></i>Profit & Loss Statement</h3></div>
    <div class="col-auto d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="start" class="form-control form-control-sm" value="<?php echo $start; ?>">
            <input type="date" name="end"   class="form-control form-control-sm" value="<?php echo $end; ?>">
            <button type="submit" class="btn btn-primary btn-sm">Load</button>
        </form>
        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fas fa-print me-1"></i> Print</button>
    </div>
</div>

<div class="text-center mb-3">
    <h5><?php echo htmlspecialchars($company['name'] ?? ''); ?></h5>
    <p class="text-muted mb-0">Profit & Loss — <?php echo date('d M Y', strtotime($start)); ?> to <?php echo date('d M Y', strtotime($end)); ?></p>
</div>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-bordered mb-0">
                    <thead class="table-success"><tr><th colspan="2">INCOME</th></tr></thead>
                    <tbody>
                        <?php foreach ($income_rows as $r): ?>
                        <tr><td class="ps-4"><?php echo htmlspecialchars($r['name']); ?></td><td class="text-end fw-bold"><?php echo number_format($r['amount'], 2); ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="table-success fw-bold"><td>Total Income</td><td class="text-end"><?php echo number_format($total_income, 2); ?></td></tr>
                    </tbody>
                    <thead class="table-danger"><tr><th colspan="2">EXPENSES</th></tr></thead>
                    <tbody>
                        <?php foreach ($expense_rows as $r): ?>
                        <tr><td class="ps-4"><?php echo htmlspecialchars($r['name']); ?></td><td class="text-end"><?php echo number_format($r['amount'], 2); ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="table-danger fw-bold"><td>Total Expenses</td><td class="text-end"><?php echo number_format($total_expense, 2); ?></td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="table-<?php echo $net_profit >= 0 ? 'primary' : 'warning'; ?> fw-bold fs-5">
                            <td><?php echo $net_profit >= 0 ? 'NET PROFIT' : 'NET LOSS'; ?></td>
                            <td class="text-end"><?php echo format_currency(abs($net_profit)); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
