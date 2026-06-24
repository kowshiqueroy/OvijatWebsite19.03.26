<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$as_of = $_GET['as_of'] ?? date('Y-m-d');

// Get all accounts with their balances
$accounts = fetch_all(
    "SELECT a.*, ag.name as group_name, ag.nature
     FROM accounts a
     JOIN account_groups ag ON a.group_id = ag.id
     WHERE a.isDelete = 0 AND a.is_active = 1
     ORDER BY ag.nature, ag.name, a.name"
);

$total_dr = 0;
$total_cr = 0;
$rows = [];

foreach ($accounts as $a) {
    $bal = get_account_balance($a['id'], $as_of);
    if ($bal['dr'] == 0 && $bal['cr'] == 0) continue; // skip zero-balance accounts

    $rows[] = [
        'name'       => $a['name'],
        'code'       => $a['code'],
        'group'      => $a['group_name'],
        'nature'     => $a['nature'],
        'dr'         => $bal['dr'],
        'cr'         => $bal['cr'],
        'balance'    => $bal['balance'],
        'bal_type'   => $bal['type'],
    ];
    $total_dr += $bal['dr'];
    $total_cr += $bal['cr'];
}

// Group by nature for display
$grouped = [];
foreach ($rows as $r) {
    $grouped[$r['nature']][] = $r;
}
$nature_order = ['Assets','Liabilities','Equity','Income','Expense'];
$is_balanced  = round($total_dr, 2) === round($total_cr, 2);
?>

<style>
    @media print { .no-print { display:none!important; } body { font-size:11px; } }
</style>

<div class="row align-items-center mb-4 no-print">
    <div class="col"><h3><i class="fa-solid fa-scale-balanced me-2"></i>Trial Balance</h3></div>
    <div class="col-auto d-flex gap-2 flex-wrap">
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="as_of" class="form-control form-control-sm" value="<?php echo $as_of; ?>">
            <button type="submit" class="btn btn-primary btn-sm">Load</button>
        </form>
        <button onclick="downloadTableCSV('trial_balance_<?php echo $as_of; ?>.csv','#tb-table')" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
</div>

<div class="text-center mb-3">
    <h5><?php echo htmlspecialchars($company['name'] ?? ''); ?></h5>
    <p class="text-muted mb-0">Trial Balance — As of <?php echo date('d M Y', strtotime($as_of)); ?></p>
</div>

<?php if (!$is_balanced): ?>
<div class="alert alert-danger no-print"><i class="fas fa-exclamation-triangle me-2"></i>Trial Balance does not balance! Dr = <?php echo format_currency($total_dr); ?>, Cr = <?php echo format_currency($total_cr); ?>. Check for unposted or incomplete journal entries.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-bordered mb-0 export-table" id="tb-table">
            <thead class="table-dark">
                <tr>
                    <th>Account Name</th>
                    <th>Code</th>
                    <th>Group</th>
                    <th class="text-end">Debit (Dr)</th>
                    <th class="text-end">Credit (Cr)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($nature_order as $nature):
                if (empty($grouped[$nature])) continue;
                $nat_dr = array_sum(array_column($grouped[$nature], 'dr'));
                $nat_cr = array_sum(array_column($grouped[$nature], 'cr'));
            ?>
                <tr class="table-secondary fw-bold">
                    <td colspan="3"><?php echo $nature; ?></td>
                    <td class="text-end"><?php echo number_format($nat_dr, 2); ?></td>
                    <td class="text-end"><?php echo number_format($nat_cr, 2); ?></td>
                </tr>
                <?php foreach ($grouped[$nature] as $r): ?>
                <tr>
                    <td class="ps-4"><?php echo htmlspecialchars($r['name']); ?></td>
                    <td><small class="text-muted"><?php echo htmlspecialchars($r['code'] ?? ''); ?></small></td>
                    <td><small><?php echo htmlspecialchars($r['group']); ?></small></td>
                    <td class="text-end"><?php echo $r['dr'] > 0 ? number_format($r['dr'], 2) : '—'; ?></td>
                    <td class="text-end"><?php echo $r['cr'] > 0 ? number_format($r['cr'], 2) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="table-dark fw-bold">
                <tr>
                    <td colspan="3">TOTAL</td>
                    <td class="text-end"><?php echo number_format($total_dr, 2); ?></td>
                    <td class="text-end"><?php echo number_format($total_cr, 2); ?></td>
                </tr>
                <?php if ($is_balanced): ?>
                <tr>
                    <td colspan="5" class="text-center text-success">✓ Trial Balance is BALANCED</td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
