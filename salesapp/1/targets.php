<?php
$pageTitle = 'Targets';
include 'header.php';
if (!$is_manager) { header("Location: my_target.php"); exit; }

$cid = (int)$_SESSION['company_id'];
$success = $error = '';

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type      = $_POST['target_type'] ?? 'sr';
    $entity_id = (int)$_POST['target_entity_id'];
    $month     = (int)$_POST['month'];
    $year      = (int)$_POST['year'];
    $amount    = (float)str_replace(',', '', $_POST['target_amount'] ?? 0);

    if (!$entity_id || !$month || !$year || $amount <= 0) {
        $error = 'All fields are required and amount must be > 0.';
    } elseif (isset($_POST['save_target'])) {
        /* Upsert */
        $stmt = $conn->prepare(
            "INSERT INTO targets (company_id, target_type, target_entity_id, month, year, target_amount, created_by)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE target_amount=VALUES(target_amount), updated_by=VALUES(created_by), updated_at=NOW()"
        );
        $stmt->bind_param("isiiidi", $cid, $type, $entity_id, $month, $year, $amount, $_SESSION['user_id']);
        $stmt->execute(); $stmt->close();
        header("Location: targets.php?month=$month&year=$year&msg=saved"); exit;
    }
}

/* ── Delete ── */
if (isset($_GET['delete'])) {
    $tid  = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM targets WHERE id=? AND company_id=?");
    $stmt->bind_param("ii", $tid, $cid); $stmt->execute(); $stmt->close();
    header("Location: targets.php?msg=deleted"); exit;
}

/* ── Filter ── */
$f_month = (int)($_GET['month'] ?? date('n'));
$f_year  = (int)($_GET['year']  ?? date('Y'));
$f_type  = $_GET['type'] ?? 'sr';

/* ── Dropdowns ── */
$srs_q   = $conn->query("SELECT id, username FROM users WHERE company_id=$cid AND role IN (2,3) AND status=1 ORDER BY username");
$grps_q  = $conn->query("SELECT sg.id, CONCAT(d.name,' › ',sg.name) AS label FROM sales_groups sg JOIN divisions d ON d.id=sg.division_id WHERE sg.company_id=$cid AND sg.status=1 ORDER BY d.name, sg.name");
$divs_q  = $conn->query("SELECT id, name FROM divisions WHERE company_id=$cid AND status=1 ORDER BY name");

/* ── Targets list with achievement ── */
$from = sprintf('%04d-%02d-01', $f_year, $f_month);
$to   = date('Y-m-t', strtotime($from));

$targets_q = $conn->query(
    "SELECT t.*,
     CASE t.target_type
       WHEN 'sr'       THEN u.username
       WHEN 'group'    THEN CONCAT(d.name,' › ',sg.name)
       WHEN 'division' THEN dv.name
     END AS entity_name,
     COALESCE((
       SELECT SUM(oi.quantity*oi.price)
       FROM truck_loads tl
       JOIN truck_load_orders tlo ON tlo.truck_load_id=tl.id AND tlo.is_active=1
       JOIN order_items oi ON oi.order_id=tlo.order_id
       WHERE tl.company_id=$cid AND tl.status='delivered'
         AND MONTH(tl.delivered_at)=$f_month AND YEAR(tl.delivered_at)=$f_year
         AND (
           (t.target_type='sr'       AND tl.assigned_sr_id=t.target_entity_id) OR
           (t.target_type='group'    AND tl.assigned_sr_id IN (SELECT user_id FROM user_group_assignments WHERE group_id=t.target_entity_id AND is_active=1)) OR
           (t.target_type='division' AND tl.assigned_sr_id IN (SELECT uga.user_id FROM user_group_assignments uga JOIN sales_groups sg2 ON sg2.id=uga.group_id AND sg2.division_id=t.target_entity_id AND uga.is_active=1))
         )
     ), 0) AS achieved
     FROM targets t
     LEFT JOIN users u ON u.id=t.target_entity_id AND t.target_type='sr'
     LEFT JOIN sales_groups sg ON sg.id=t.target_entity_id AND t.target_type='group'
     LEFT JOIN divisions d ON d.id=sg.division_id
     LEFT JOIN divisions dv ON dv.id=t.target_entity_id AND t.target_type='division'
     WHERE t.company_id=$cid AND t.month=$f_month AND t.year=$f_year
     ORDER BY t.target_type, entity_name"
);

if (isset($_GET['msg'])) $success = ['saved'=>'Target saved.','deleted'=>'Target deleted.'][$_GET['msg']] ?? '';
?>

<div class="page-header">
    <div><div class="page-title">Targets</div><div class="page-subtitle">Set monthly delivery targets for SRs, groups, and divisions</div></div>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Set / Update Target -->
<div class="card">
    <div class="card-header"><span class="card-title">Set / Update Target</span></div>
    <form method="POST">
        <?= csrf_field() ?>
        <div class="grid-layout md-3">
            <div class="form-group">
                <label>Target Type <span style="color:var(--danger)">*</span></label>
                <select name="target_type" id="target_type" onchange="switchEntity(this.value)" required>
                    <option value="sr"       <?= $f_type==='sr'?'selected':'' ?>>Individual SR</option>
                    <option value="group"    <?= $f_type==='group'?'selected':'' ?>>Group</option>
                    <option value="division" <?= $f_type==='division'?'selected':'' ?>>Division</option>
                </select>
            </div>
            <div class="form-group" id="entity_sr">
                <label>Sales Rep <span style="color:var(--danger)">*</span></label>
                <select name="target_entity_id">
                    <option value="">Select SR</option>
                    <?php if ($srs_q) while ($sr = $srs_q->fetch_assoc()): ?>
                        <option value="<?= $sr['id'] ?>"><?= htmlspecialchars($sr['username']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group hidden" id="entity_group">
                <label>Group</label>
                <select name="target_entity_id_group">
                    <option value="">Select Group</option>
                    <?php if ($grps_q) while ($g = $grps_q->fetch_assoc()): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['label']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group hidden" id="entity_division">
                <label>Division</label>
                <select name="target_entity_id_div">
                    <option value="">Select Division</option>
                    <?php if ($divs_q) while ($dv = $divs_q->fetch_assoc()): ?>
                        <option value="<?= $dv['id'] ?>"><?= htmlspecialchars($dv['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Month <span style="color:var(--danger)">*</span></label>
                <select name="month" required>
                    <?php for ($m=1;$m<=12;$m++): ?>
                        <option value="<?=$m?>" <?=$m==$f_month?'selected':''?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Year <span style="color:var(--danger)">*</span></label>
                <select name="year" required>
                    <?php for ($y=date('Y');$y>=date('Y')-2;$y--): ?>
                        <option value="<?=$y?>" <?=$y==$f_year?'selected':''?>><?=$y?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Target Amount (BDT) <span style="color:var(--danger)">*</span></label>
                <input type="number" name="target_amount" placeholder="e.g. 500000" min="1" step="1000" required>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" name="save_target" class="btn btn-primary"><i class="fa-solid fa-bullseye"></i> Save Target</button>
        </div>
    </form>
</div>

<!-- Filter -->
<form method="GET" style="margin-bottom:12px">
    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group" style="min-width:100px">
            <label>Month</label>
            <select name="month">
                <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?=$m?>" <?=$m==$f_month?'selected':''?>><?= date('M', mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:80px">
            <label>Year</label>
            <select name="year">
                <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?>
                    <option value="<?=$y?>" <?=$y==$f_year?'selected':''?>><?=$y?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> View</button>
    </div>
</form>

<!-- Targets table -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Targets &mdash; <?= date('F Y', mktime(0,0,0,$f_month,1,$f_year)) ?></span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Type</th><th>Name</th><th>Target (BDT)</th><th>Achieved</th><th>Progress</th><th>%</th><th>Action</th></tr></thead>
            <tbody>
                <?php if ($targets_q && $targets_q->num_rows > 0): ?>
                    <?php while ($row = $targets_q->fetch_assoc()):
                        $pct = $row['target_amount'] > 0 ? min(110, round($row['achieved']/$row['target_amount']*100)) : 0;
                        $bar = $pct>=100?'gold':($pct>=80?'':($pct>=50?'warn':'danger'));
                    ?>
                    <tr>
                        <td><span class="badge <?= ['sr'=>'badge-green','group'=>'badge-blue','division'=>'badge-purple'][$row['target_type']] ?>"><?= $row['target_type'] ?></span></td>
                        <td class="fw-600"><?= htmlspecialchars($row['entity_name'] ?? '—') ?></td>
                        <td><?= number_format($row['target_amount'], 0) ?></td>
                        <td class="fw-600 <?= $pct>=100?'text-green':($pct>=50?'text-yellow':'text-red') ?>"><?= number_format($row['achieved'], 0) ?></td>
                        <td style="min-width:100px">
                            <div class="progress-bar-wrap">
                                <div class="progress-bar-fill <?= $bar ?>" style="width:<?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td class="fw-700 <?= $pct>=100?'text-green':($pct>=50?'text-yellow':'text-red') ?>"><?= $pct ?>%</td>
                        <td>
                            <a href="targets.php?delete=<?= $row['id'] ?>&month=<?=$f_month?>&year=<?=$f_year?>"
                               onclick="return confirm('Delete this target?')"
                               class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No targets set for <?= date('F Y', mktime(0,0,0,$f_month,1,$f_year)) ?>.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function switchEntity(type) {
    document.getElementById('entity_sr').classList.toggle('hidden', type !== 'sr');
    document.getElementById('entity_group').classList.toggle('hidden', type !== 'group');
    document.getElementById('entity_division').classList.toggle('hidden', type !== 'division');
    /* Copy value to the shared name field */
    document.addEventListener('change', function(e) {
        var active = document.querySelector('#entity_' + type + ' select');
        if (active) active.name = 'target_entity_id';
    });
}
/* On form submit, set the right entity_id */
document.querySelector('form').addEventListener('submit', function() {
    var type = document.getElementById('target_type').value;
    var srcId = type === 'sr' ? 'entity_sr' : (type === 'group' ? 'entity_group' : 'entity_division');
    var srcSel = document.querySelector('#' + srcId + ' select');
    if (srcSel) srcSel.name = 'target_entity_id';
});
switchEntity(document.getElementById('target_type').value);
</script>

<?php include 'footer.php'; ?>
