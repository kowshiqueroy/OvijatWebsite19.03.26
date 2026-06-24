<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$account_id = intval($_GET['account_id'] ?? 0);
$start      = $_GET['start'] ?? date('Y-01-01');
$end        = $_GET['end']   ?? date('Y-m-d');

$all_accounts = fetch_all(
    "SELECT a.id, a.name, a.code, ag.name as group_name, ag.nature
     FROM accounts a JOIN account_groups ag ON a.group_id = ag.id
     WHERE a.isDelete = 0 AND a.is_active = 1
     ORDER BY ag.nature, a.name"
);

$account = $account_id ? fetch_one("SELECT a.*, ag.name as group_name, ag.nature FROM accounts a JOIN account_groups ag ON a.group_id=ag.id WHERE a.id=?", [$account_id]) : null;

$lines = [];
$running = 0;
if ($account) {
    // Opening balance before start
    $ob = get_account_balance($account_id, date('Y-m-d', strtotime($start . ' -1 day')));
    $running = $ob['type'] === 'Dr' ? $ob['balance'] : -$ob['balance'];

    // Journal lines in period
    $lines = fetch_all(
        "SELECT jl.*, je.date, je.entry_no, je.narration as je_narration, je.reference_type
         FROM journal_lines jl
         JOIN journal_entries je ON jl.journal_id = je.id
         WHERE jl.account_id = ? AND jl.isDelete = 0 AND je.isDelete = 0 AND je.date BETWEEN ? AND ?
         ORDER BY je.date ASC, je.id ASC",
        [$account_id, $start, $end]
    );
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="fa-solid fa-book-open me-2"></i>Account Ledger</h3>
    <?php if ($account_id): ?>
    <button onclick="window.print()" class="btn btn-dark btn-sm no-print"><i class="fa-solid fa-print me-1"></i>Print</button>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-4 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small">Account</label>
                <select name="account_id" class="form-select form-select-sm select2" required>
                    <option value="">— Select Account —</option>
                    <?php
                    $cur_nature = '';
                    foreach ($all_accounts as $a):
                        if ($a['nature'] !== $cur_nature) {
                            if ($cur_nature) echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars($a['nature']) . '">';
                            $cur_nature = $a['nature'];
                        }
                    ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $account_id==$a['id']?'selected':''; ?>>
                            <?php echo htmlspecialchars($a['name']); ?><?php echo $a['code'] ? ' ('.$a['code'].')' : ''; ?>
                        </option>
                    <?php endforeach; if ($cur_nature) echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="start" class="form-control form-control-sm" value="<?php echo $start; ?>"></div>
            <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="end" class="form-control form-control-sm" value="<?php echo $end; ?>"></div>
            <div class="col-auto"><button class="btn btn-primary btn-sm">Load</button></div>
        </form>
    </div>
</div>

<?php if (!$account): ?>
<div class="card"><div class="card-body text-center py-5 text-muted"><i class="fa-solid fa-book-open fa-2x mb-3"></i><br>Select an account to view its ledger.</div></div>
<?php else: ?>

<!-- Account header -->
<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0"><?php echo htmlspecialchars($account['name']); ?></h5>
            <small class="text-muted"><?php echo $account['group_name']; ?> &bull; <?php echo $account['nature']; ?> &bull; Code: <?php echo $account['code'] ?? 'N/A'; ?></small>
        </div>
        <?php $cur_bal = get_account_balance($account_id, $end); ?>
        <div class="text-end">
            <div class="small text-muted">Closing Balance</div>
            <div class="fw-bold fs-4 <?php echo $cur_bal['type']==='Dr' ? 'text-primary' : 'text-danger'; ?>">
                <?php echo format_currency($cur_bal['balance']); ?> <?php echo $cur_bal['type']; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-bordered mb-0" style="font-size:12px;">
            <thead class="table-dark">
                <tr><th>Date</th><th>Entry No</th><th>Narration</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th><th>Type</th></tr>
            </thead>
            <tbody>
                <!-- Opening balance row -->
                <tr class="table-light fw-bold">
                    <td><?php echo date('d M Y', strtotime($start)); ?></td>
                    <td colspan="2">Opening Balance</td>
                    <td class="text-end"><?php echo $running >= 0 ? number_format($running, 2) : '—'; ?></td>
                    <td class="text-end"><?php echo $running < 0 ? number_format(abs($running), 2) : '—'; ?></td>
                    <td class="text-end"><?php echo number_format(abs($running), 2); ?></td>
                    <td><?php echo $running >= 0 ? 'Dr' : 'Cr'; ?></td>
                </tr>
                <?php foreach ($lines as $line):
                    $running += $line['dr_amount'] - $line['cr_amount'];
                ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($line['date'])); ?></td>
                    <td><code><?php echo htmlspecialchars($line['entry_no']); ?></code></td>
                    <td>
                        <?php echo htmlspecialchars($line['narration'] ?: $line['je_narration']); ?>
                        <span class="badge bg-light text-dark ms-1" style="font-size:9px;"><?php echo $line['reference_type']; ?></span>
                    </td>
                    <td class="text-end"><?php echo $line['dr_amount'] > 0 ? number_format($line['dr_amount'], 2) : '—'; ?></td>
                    <td class="text-end"><?php echo $line['cr_amount'] > 0 ? number_format($line['cr_amount'], 2) : '—'; ?></td>
                    <td class="text-end fw-bold"><?php echo number_format(abs($running), 2); ?></td>
                    <td><span class="badge <?php echo $running >= 0 ? 'bg-primary' : 'bg-danger'; ?>"><?php echo $running >= 0 ? 'Dr' : 'Cr'; ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No transactions in this period.</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot class="table-secondary fw-bold">
                <?php $cl = get_account_balance($account_id, $end); ?>
                <tr>
                    <td colspan="3" class="text-end">Closing Balance</td>
                    <td class="text-end"><?php echo $cl['type']==='Dr' ? number_format($cl['balance'],2) : '—'; ?></td>
                    <td class="text-end"><?php echo $cl['type']==='Cr' ? number_format($cl['balance'],2) : '—'; ?></td>
                    <td class="text-end"><?php echo number_format($cl['balance'],2); ?></td>
                    <td><?php echo $cl['type']; ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

<style>@media print { .no-print{display:none!important;} }</style>
<?php require_once '../../templates/footer.php'; ?>
