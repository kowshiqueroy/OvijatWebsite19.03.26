<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_ACCOUNTANT]);

$as_of = $_GET['as_of'] ?? date('Y-m-d');

function get_nature_total($nature, $as_of) {
    $groups = fetch_all("SELECT ag.id FROM account_groups ag WHERE ag.nature=? AND ag.isDelete=0", [$nature]);
    $total = 0;
    foreach ($groups as $g) {
        $accounts = fetch_all("SELECT id, opening_balance, opening_balance_type FROM accounts WHERE group_id=? AND isDelete=0", [$g['id']]);
        foreach ($accounts as $a) {
            $b = get_account_balance($a['id'], $as_of);
            $total += ($b['type'] === 'Dr') ? $b['balance'] : -$b['balance'];
        }
    }
    return $total;
}

function get_nature_breakdown($nature, $as_of) {
    $rows = [];
    $groups = fetch_all("SELECT ag.id, ag.name FROM account_groups ag WHERE ag.nature=? AND ag.isDelete=0 ORDER BY ag.name", [$nature]);
    foreach ($groups as $g) {
        $accounts = fetch_all("SELECT id, name, code FROM accounts WHERE group_id=? AND isDelete=0 AND is_active=1", [$g['id']]);
        $group_total = 0;
        $items = [];
        foreach ($accounts as $a) {
            $b = get_account_balance($a['id'], $as_of);
            $val = ($b['type'] === 'Dr') ? $b['balance'] : -$b['balance'];
            if ($val == 0) continue;
            $items[] = ['name' => $a['name'], 'value' => $val];
            $group_total += $val;
        }
        if ($group_total != 0) {
            $rows[] = ['group' => $g['name'], 'items' => $items, 'total' => $group_total];
        }
    }
    return $rows;
}

$assets      = get_nature_breakdown('Assets', $as_of);
$liabilities = get_nature_breakdown('Liabilities', $as_of);
$equity      = get_nature_breakdown('Equity', $as_of);

$total_assets      = array_sum(array_column($assets, 'total'));
$total_liabilities = array_sum(array_column($liabilities, 'total'));
$total_equity      = array_sum(array_column($equity, 'total'));
$total_le          = $total_liabilities + $total_equity;
$is_balanced       = round($total_assets, 2) === round($total_le, 2);
?>

<style>@media print { .no-print { display:none!important; } }</style>

<div class="row align-items-center mb-4 no-print">
    <div class="col"><h3><i class="fas fa-file-invoice me-2"></i>Balance Sheet</h3></div>
    <div class="col-auto d-flex gap-2">
        <form method="GET" class="d-flex gap-2">
            <input type="date" name="as_of" class="form-control form-control-sm" value="<?php echo $as_of; ?>">
            <button type="submit" class="btn btn-primary btn-sm">Load</button>
        </form>
        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fas fa-print me-1"></i> Print</button>
    </div>
</div>

<div class="text-center mb-3">
    <h5><?php echo htmlspecialchars($company['name'] ?? ''); ?></h5>
    <p class="text-muted mb-0">Balance Sheet — As of <?php echo date('d M Y', strtotime($as_of)); ?></p>
</div>

<?php if (!$is_balanced): ?>
<div class="alert alert-danger no-print"><i class="fas fa-exclamation-triangle me-2"></i>Balance Sheet does not balance! Assets = <?php echo format_currency($total_assets); ?> | Liabilities + Equity = <?php echo format_currency($total_le); ?>.</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Assets -->
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white fw-bold">ASSETS</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($assets as $group): ?>
                        <tr class="table-light"><td colspan="2" class="fw-bold small text-uppercase"><?php echo htmlspecialchars($group['group']); ?></td></tr>
                        <?php foreach ($group['items'] as $item): ?>
                        <tr><td class="ps-4 small"><?php echo htmlspecialchars($item['name']); ?></td><td class="text-end small"><?php echo number_format($item['value'], 2); ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold"><td class="ps-3">Sub-Total</td><td class="text-end"><?php echo number_format($group['total'], 2); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-primary fw-bold">
                        <tr><td>TOTAL ASSETS</td><td class="text-end"><?php echo number_format($total_assets, 2); ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Liabilities + Equity -->
    <div class="col-md-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-danger text-white fw-bold">LIABILITIES</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($liabilities as $group): ?>
                        <tr class="table-light"><td colspan="2" class="fw-bold small text-uppercase"><?php echo htmlspecialchars($group['group']); ?></td></tr>
                        <?php foreach ($group['items'] as $item): ?>
                        <tr><td class="ps-4 small"><?php echo htmlspecialchars($item['name']); ?></td><td class="text-end small"><?php echo number_format(abs($item['value']), 2); ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold"><td class="ps-3">Sub-Total</td><td class="text-end"><?php echo number_format(abs($group['total']), 2); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-danger fw-bold">
                        <tr><td>TOTAL LIABILITIES</td><td class="text-end"><?php echo number_format(abs($total_liabilities), 2); ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-success text-white fw-bold">EQUITY</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($equity as $group): ?>
                        <?php foreach ($group['items'] as $item): ?>
                        <tr><td class="ps-4 small"><?php echo htmlspecialchars($item['name']); ?></td><td class="text-end small"><?php echo number_format($item['value'], 2); ?></td></tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-success fw-bold">
                        <tr><td>TOTAL EQUITY</td><td class="text-end"><?php echo number_format($total_equity, 2); ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card shadow-sm mt-3 <?php echo $is_balanced ? 'border-primary' : 'border-warning'; ?>">
            <div class="card-body py-2 fw-bold d-flex justify-content-between">
                <span>TOTAL LIABILITIES + EQUITY</span>
                <span><?php echo number_format($total_le, 2); ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../templates/footer.php'; ?>
