<?php
require_once '../../templates/header.php';
check_login();
check_role([ROLE_ADMIN, ROLE_MANAGER]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) redirect('modules/admin/targets.php', 'CSRF failed.', 'danger');

    if (isset($_POST['save_targets'])) {
        $period      = sanitize($_POST['target_period']);
        $period_type = in_array($_POST['period_type'], ['Monthly','Quarterly','Yearly']) ? $_POST['period_type'] : 'Monthly';
        $targets     = $_POST['targets'] ?? []; // targets[user_id][revenue|qty]

        foreach ($targets as $uid => $vals) {
            $uid = intval($uid);
            $rev = floatval($vals['revenue'] ?? 0);
            $qty = intval($vals['qty'] ?? 0);
            if ($rev == 0 && $qty == 0) continue;

            $existing = fetch_one("SELECT id FROM sales_targets WHERE user_id=? AND target_period=? AND target_level='SR' AND isDelete=0", [$uid, $period]);
            if ($existing) {
                db_query("UPDATE sales_targets SET target_revenue=?, target_qty=?, period_type=? WHERE id=?", [$rev, $qty, $period_type, $existing['id']]);
            } else {
                db_query("INSERT INTO sales_targets (target_level, user_id, target_period, period_type, target_revenue, target_qty) VALUES ('SR',?,?,?,?,?)", [$uid, $period, $period_type, $rev, $qty]);
            }
        }

        log_activity($_SESSION['user_id'], "Updated sales targets for period: $period");
        redirect('modules/admin/targets.php?period=' . urlencode($period), 'Targets saved successfully.');
    }
}

$period = sanitize($_GET['period'] ?? date('Y-m'));
$period_type = strlen($period) == 7 ? 'Monthly' : (str_contains($period, 'Q') ? 'Quarterly' : 'Yearly');

$sr_users = fetch_all(
    "SELECT u.*, d.name as division_name, g.name as group_name
     FROM users u
     LEFT JOIN sr_divisions d ON u.division_id = d.id
     LEFT JOIN sr_groups g ON u.group_id = g.id
     WHERE u.isDelete=0 AND u.role=? AND u.is_active=1
     ORDER BY d.name, g.name, u.username",
    [ROLE_SR]
);

// Load existing targets for this period
$existing_targets = [];
foreach (fetch_all("SELECT * FROM sales_targets WHERE target_period=? AND target_level='SR' AND isDelete=0", [$period]) as $t) {
    $existing_targets[$t['user_id']] = $t;
}

// Calculate actuals for this period
$actuals = [];
if (strlen($period) == 7) { // Monthly YYYY-MM
    $start = $period . '-01';
    $end   = date('Y-m-t', strtotime($start));
    $rows  = fetch_all("SELECT created_by, SUM(grand_total) as rev, COUNT(id) as cnt FROM sales_drafts WHERE status='Confirmed' AND isDelete=0 AND DATE(confirmed_at) BETWEEN ? AND ? GROUP BY created_by", [$start, $end]);
    foreach ($rows as $r) $actuals[$r['created_by']] = $r;
}
?>

<div class="row align-items-center mb-4">
    <div class="col">
        <h3><i class="fas fa-bullseye me-2"></i>Sales Targets</h3>
        <p class="text-muted small mb-0">Set monthly/quarterly revenue and quantity targets per SR.</p>
    </div>
</div>

<!-- Period selector -->
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Period</label>
                <input type="month" name="period" class="form-control form-control-sm" value="<?php echo $period; ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Load Period</button>
            </div>
        </form>
    </div>
</div>

<form method="POST">
    <?php csrf_field(); ?>
    <input type="hidden" name="target_period" value="<?php echo htmlspecialchars($period); ?>">
    <input type="hidden" name="period_type" value="<?php echo $period_type; ?>">

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <span>Targets for: <strong><?php echo htmlspecialchars($period); ?></strong></span>
            <button type="submit" name="save_targets" class="btn btn-success btn-sm">
                <i class="fas fa-save me-1"></i> Save All Targets
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SR Name</th>
                            <th>Group</th>
                            <th>Division</th>
                            <th style="width:160px;">Target Revenue (৳)</th>
                            <th style="width:110px;">Target Qty</th>
                            <th class="text-center">Actual Rev.</th>
                            <th class="text-center">Achievement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sr_users as $u):
                            $t   = $existing_targets[$u['id']] ?? null;
                            $act = $actuals[$u['id']] ?? null;
                            $t_rev = $t ? floatval($t['target_revenue']) : 0;
                            $a_rev = $act ? floatval($act['rev']) : 0;
                            $pct   = ($t_rev > 0) ? min(100, round(($a_rev / $t_rev) * 100)) : null;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                            <td class="small"><?php echo htmlspecialchars($u['group_name'] ?? '—'); ?></td>
                            <td class="small"><?php echo htmlspecialchars($u['division_name'] ?? '—'); ?></td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                       name="targets[<?php echo $u['id']; ?>][revenue]"
                                       class="form-control form-control-sm"
                                       value="<?php echo $t ? number_format($t['target_revenue'], 2, '.', '') : ''; ?>"
                                       placeholder="0.00">
                            </td>
                            <td>
                                <input type="number" min="0"
                                       name="targets[<?php echo $u['id']; ?>][qty]"
                                       class="form-control form-control-sm"
                                       value="<?php echo $t ? $t['target_qty'] : ''; ?>"
                                       placeholder="0">
                            </td>
                            <td class="text-center fw-bold">
                                <?php echo $act ? format_currency($a_rev) : '<span class="text-muted">—</span>'; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($pct !== null): ?>
                                    <div class="progress" style="height:20px;" title="<?php echo $pct; ?>%">
                                        <div class="progress-bar <?php echo $pct >= 100 ? 'bg-success' : ($pct >= 60 ? 'bg-warning' : 'bg-danger'); ?>"
                                             style="width:<?php echo $pct; ?>%"><?php echo $pct; ?>%</div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">No target set</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($sr_users)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No SR users found. Add users with Sales Representative role first.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<?php require_once '../../templates/footer.php'; ?>
