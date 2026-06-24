<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER, ROLE_VIEWER]);

$period       = sanitize($_GET['period'] ?? date('Y-m'));
$filter_div   = intval($_GET['division_id'] ?? 0);
$filter_group = intval($_GET['group_id'] ?? 0);
$filter_sr    = intval($_GET['sr_id'] ?? 0);

$divisions = fetch_all("SELECT * FROM sr_divisions WHERE isDelete=0 ORDER BY name");
$groups    = fetch_all("SELECT g.*, d.name as division_name FROM sr_groups g LEFT JOIN sr_divisions d ON g.division_id=d.id WHERE g.isDelete=0 ORDER BY g.name");

// Date range for the period
$period_start = $period . '-01';
$period_end   = date('Y-m-t', strtotime($period_start));

// Build SR filter
$sr_where  = "u.isDelete=0 AND u.role=?";
$sr_params = [ROLE_SR];
if ($filter_div) { $sr_where .= " AND u.division_id=?"; $sr_params[] = $filter_div; }
if ($filter_group) { $sr_where .= " AND u.group_id=?"; $sr_params[] = $filter_group; }
if ($filter_sr) { $sr_where .= " AND u.id=?"; $sr_params[] = $filter_sr; }

$sr_users = fetch_all(
    "SELECT u.*, d.name as division_name, g.name as group_name
     FROM users u
     LEFT JOIN sr_divisions d ON u.division_id = d.id
     LEFT JOIN sr_groups g ON u.group_id = g.id
     WHERE $sr_where ORDER BY d.name, g.name, u.username",
    $sr_params
);

// Targets for this period
$target_map = [];
foreach (fetch_all("SELECT * FROM sales_targets WHERE target_period=? AND target_level='SR' AND isDelete=0", [$period]) as $t) {
    $target_map[$t['user_id']] = $t;
}

// Actuals: confirmed sales by SR in period
$actual_map = [];
$actual_rows = fetch_all(
    "SELECT created_by, SUM(grand_total) as revenue, COUNT(id) as invoice_count
     FROM sales_drafts
     WHERE status='Confirmed' AND isDelete=0 AND DATE(confirmed_at) BETWEEN ? AND ?
     GROUP BY created_by",
    [$period_start, $period_end]
);
foreach ($actual_rows as $r) $actual_map[$r['created_by']] = $r;

// Group and division rollups
$group_rollup = [];
$div_rollup   = [];
$grand_target = 0;
$grand_actual = 0;

foreach ($sr_users as $u) {
    $t_rev = floatval($target_map[$u['id']]['target_revenue'] ?? 0);
    $a_rev = floatval($actual_map[$u['id']]['revenue'] ?? 0);

    $grand_target += $t_rev;
    $grand_actual += $a_rev;

    if ($u['group_id']) {
        $group_rollup[$u['group_id']]['target'] = ($group_rollup[$u['group_id']]['target'] ?? 0) + $t_rev;
        $group_rollup[$u['group_id']]['actual'] = ($group_rollup[$u['group_id']]['actual'] ?? 0) + $a_rev;
        $group_rollup[$u['group_id']]['name']   = $u['group_name'];
    }
    if ($u['division_id']) {
        $div_rollup[$u['division_id']]['target'] = ($div_rollup[$u['division_id']]['target'] ?? 0) + $t_rev;
        $div_rollup[$u['division_id']]['actual'] = ($div_rollup[$u['division_id']]['actual'] ?? 0) + $a_rev;
        $div_rollup[$u['division_id']]['name']   = $u['division_name'];
    }
}
?>

<div class="row align-items-center mb-4 no-print">
    <div class="col"><h3><i class="fa-solid fa-chart-bar me-2"></i>SR Performance</h3></div>
    <div class="col-auto d-flex gap-2">
        <button onclick="copySRWhatsApp(this)" class="btn btn-outline-success btn-sm"><i class="fa-brands fa-whatsapp me-1"></i>WhatsApp</button>
        <button onclick="downloadTableCSV('sr_performance_<?php echo $period; ?>.csv','#sr-table')" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-file-csv me-1"></i>CSV</button>
        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Period</label>
                <input type="month" name="period" class="form-control form-control-sm" value="<?php echo $period; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Division</label>
                <select name="division_id" class="form-select form-select-sm">
                    <option value="">All Divisions</option>
                    <?php foreach ($divisions as $d): ?><option value="<?php echo $d['id']; ?>" <?php echo $filter_div==$d['id']?'selected':''; ?>><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Group</label>
                <select name="group_id" class="form-select form-select-sm">
                    <option value="">All Groups</option>
                    <?php foreach ($groups as $g): ?><option value="<?php echo $g['id']; ?>" <?php echo $filter_group==$g['id']?'selected':''; ?>><?php echo htmlspecialchars($g['name']); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">SR</label>
                <select name="sr_id" class="form-select form-select-sm select2">
                    <option value="">All SRs</option>
                    <?php foreach (fetch_all("SELECT id,username FROM users WHERE role=? AND isDelete=0", [ROLE_SR]) as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_sr==$u['id']?'selected':''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <a href="sr_performance.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
    <?php
    $overall_pct = $grand_target > 0 ? round(($grand_actual / $grand_target) * 100) : 0;
    $kpi_color   = $overall_pct >= 100 ? 'success' : ($overall_pct >= 60 ? 'warning' : 'danger');
    ?>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body"><div class="small text-uppercase opacity-75">Total Target</div><h3><?php echo format_currency($grand_target); ?></h3></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-<?php echo $kpi_color; ?> text-white">
            <div class="card-body"><div class="small text-uppercase opacity-75">Total Achieved</div><h3><?php echo format_currency($grand_actual); ?></h3></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-dark text-white">
            <div class="card-body"><div class="small text-uppercase opacity-75">Overall Achievement</div><h3><?php echo $overall_pct; ?>%</h3></div>
        </div>
    </div>
</div>

<!-- SR Performance Table -->
<div class="card shadow-sm mb-4">
    <div class="card-header"><strong>SR-Level Performance — <?php echo htmlspecialchars($period); ?></strong></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 export-table" id="sr-table">
                <thead class="table-dark">
                    <tr>
                        <th>SR</th>
                        <th>Group</th>
                        <th>Division</th>
                        <th class="text-end">Target</th>
                        <th class="text-end">Actual</th>
                        <th class="text-end">Variance</th>
                        <th data-no-export style="width:180px;">Achievement</th>
                        <th class="text-center">Invoices</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sr_users as $u):
                        $t_rev = floatval($target_map[$u['id']]['target_revenue'] ?? 0);
                        $a_rev = floatval($actual_map[$u['id']]['revenue'] ?? 0);
                        $inv   = intval($actual_map[$u['id']]['invoice_count'] ?? 0);
                        $var   = $a_rev - $t_rev;
                        $pct   = $t_rev > 0 ? min(150, round(($a_rev / $t_rev) * 100)) : null;
                        $bar_color = ($pct === null) ? 'secondary' : ($pct >= 100 ? 'success' : ($pct >= 60 ? 'warning' : 'danger'));
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                        <td class="small"><?php echo htmlspecialchars($u['group_name'] ?? '—'); ?></td>
                        <td class="small"><?php echo htmlspecialchars($u['division_name'] ?? '—'); ?></td>
                        <td class="text-end"><?php echo $t_rev > 0 ? format_currency($t_rev) : '<span class="text-muted">—</span>'; ?></td>
                        <td class="text-end fw-bold"><?php echo format_currency($a_rev); ?></td>
                        <td class="text-end <?php echo $var >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($var >= 0 ? '+' : '') . format_currency($var); ?>
                        </td>
                        <td>
                            <?php if ($pct !== null): ?>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:16px;">
                                    <div class="progress-bar bg-<?php echo $bar_color; ?>" style="width:<?php echo min(100,$pct); ?>%"></div>
                                </div>
                                <small class="fw-bold"><?php echo $pct; ?>%</small>
                            </div>
                            <?php else: ?><span class="text-muted small">No target</span><?php endif; ?>
                        </td>
                        <td class="text-center"><span class="badge bg-info"><?php echo $inv; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($sr_users)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No SR data found.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($div_rollup)): ?>
                <tfoot class="table-warning fw-bold">
                    <?php foreach ($div_rollup as $div):
                        $pct = $div['target'] > 0 ? round(($div['actual'] / $div['target']) * 100) : null;
                    ?>
                    <tr>
                        <td colspan="3"><i class="fas fa-layer-group me-1"></i>Division: <?php echo htmlspecialchars($div['name']); ?></td>
                        <td class="text-end"><?php echo format_currency($div['target']); ?></td>
                        <td class="text-end"><?php echo format_currency($div['actual']); ?></td>
                        <td class="text-end <?php echo ($div['actual']-$div['target']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php $v = $div['actual']-$div['target']; echo ($v>=0?'+':'') . format_currency($v); ?>
                        </td>
                        <td><?php echo $pct !== null ? $pct.'%' : '—'; ?></td>
                        <td></td>
                    </tr>
                    <?php endforeach; ?>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<script>
const SR_DATA = <?php echo json_encode(array_map(fn($u) => [
    'name'     => $u['username'],
    'group'    => $u['group_name'] ?? '—',
    'division' => $u['division_name'] ?? '—',
    'target'   => $target_map[$u['id']]['target_revenue'] ?? 0,
    'actual'   => $actual_map[$u['id']]['revenue'] ?? 0,
], $sr_users)); ?>;
const SR_PERIOD = '<?php echo htmlspecialchars($period); ?>';

function copySRWhatsApp(btn) {
    let text = '*SR Performance — ' + SR_PERIOD + '*\n' + '─'.repeat(32) + '\n';
    SR_DATA.forEach(r => {
        const target = parseFloat(r.target) || 0;
        const actual = parseFloat(r.actual) || 0;
        const pct    = target > 0 ? Math.round((actual / target) * 100) : null;
        const icon   = pct === null ? '⬜' : (pct >= 100 ? '✅' : (pct >= 60 ? '🟡' : '🔴'));
        text += icon + ' ' + r.name;
        if (r.division !== '—') text += ' (' + r.division + ')';
        text += ': ৳' + actual.toLocaleString();
        if (target > 0) text += ' / ৳' + target.toLocaleString() + ' (' + (pct ?? '—') + '%)';
        text += '\n';
    });
    const totalTarget = SR_DATA.reduce((s, r) => s + (parseFloat(r.target) || 0), 0);
    const totalActual = SR_DATA.reduce((s, r) => s + (parseFloat(r.actual) || 0), 0);
    const overallPct  = totalTarget > 0 ? Math.round((totalActual / totalTarget) * 100) : 0;
    text += '─'.repeat(32) + '\n';
    text += '*Total: ৳' + totalActual.toLocaleString() + ' / ৳' + totalTarget.toLocaleString() + ' (' + overallPct + '%)*';
    copyText(text, btn);
}
</script>
<?php require_once '../../templates/footer.php'; ?>
