<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Call Work Queue';
$activePage = 'marks';
$aid        = agentId();

$f_mark  = $_GET['mark']  ?? '';
$f_my    = isset($_GET['my']) ? 1 : 0;
$f_agent = (int)($_GET['agent'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;
$offset  = ($page - 1) * $perPage;

$marks = ['follow_up','callback','urgent','escalated','normal'];
$markLabels = [
    'follow_up' => 'Follow Up',
    'callback'  => 'Callback',
    'urgent'    => 'Urgent',
    'escalated' => 'Escalated',
    'normal'    => 'Normal',
];
$markColors = [
    'follow_up' => 'info',
    'callback'  => 'primary',
    'urgent'    => 'danger',
    'escalated' => 'warning',
    'normal'    => 'secondary',
];

// Count per mark
$markCounts = [];
foreach ($marks as $m) {
    $extra = $f_my ? " AND cl.agent_id=$aid" : ($f_agent ? " AND cl.agent_id=$f_agent" : '');
    $markCounts[$m] = (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs cl WHERE cl.call_mark='$m'$extra")->fetch_assoc()['c'];
}

// Build query for selected mark (default: all non-normal)
$where = ["cl.call_mark != 'normal'"];
$params = [];
$types  = '';
if ($f_mark) { $where = ["cl.call_mark = ?"]; $params[] = $f_mark; $types .= 's'; }
if ($f_my)    { $where[] = "cl.agent_id = ?";  $params[] = $aid;     $types .= 'i'; }
if ($f_agent) { $where[] = "cl.agent_id = ?";  $params[] = $f_agent; $types .= 'i'; }

$whereSQL = implode(' AND ', $where);

$countStmt = $conn->prepare("SELECT COUNT(*) AS t FROM call_logs cl WHERE $whereSQL");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['t'];
$totalPages = max(1, ceil($total / $perPage));

$stmt = $conn->prepare(
    "SELECT cl.*, c.name AS contact_name, c.phone AS contact_phone,
            c.company AS contact_company,
            a.full_name AS agent_name,
            (SELECT COUNT(*) FROM call_notes cn WHERE cn.call_id=cl.id) AS note_count
     FROM call_logs cl
     LEFT JOIN contacts c ON c.id = cl.contact_id
     LEFT JOIN agents a   ON a.id = cl.agent_id
     WHERE $whereSQL
     ORDER BY FIELD(cl.call_mark,'urgent','escalated','follow_up','callback','normal'), cl.calldate DESC
     LIMIT ? OFFSET ?"
);
$allP = array_merge($params, [$perPage, $offset]);
$allT = $types . 'ii';
$stmt->bind_param($allT, ...$allP);
$stmt->execute();
$calls = $stmt->get_result();

$allAgents = getAllAgents();

require_once 'includes/layout.php';
?>

<style>
.mark-stat { display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-radius:8px;cursor:pointer;border:2px solid transparent;transition:.15s;text-decoration:none;color:inherit }
.mark-stat:hover,.mark-stat.active { border-color:var(--accent);background:rgba(99,102,241,.06) }
.mark-stat-count { font-size:1.4rem;font-weight:700;line-height:1 }
.mark-stat-label { font-size:.72rem;color:var(--muted) }
.mark-row-urgent   { border-left:3px solid #dc3545 }
.mark-row-escalated{ border-left:3px solid #fd7e14 }
.mark-row-follow_up{ border-left:3px solid #0dcaf0 }
.mark-row-callback { border-left:3px solid #0d6efd }
.mark-row-normal   { border-left:3px solid #6c757d }
</style>

<!-- Mark stat pills -->
<div class="d-flex gap-2 flex-wrap mb-3">
    <a href="marks.php?<?= http_build_query(array_merge($_GET,['mark'=>'','page'=>1])) ?>"
       class="mark-stat cc-card <?= $f_mark===''?'active':'' ?>">
        <div>
            <div class="mark-stat-count"><?= array_sum($markCounts) - $markCounts['normal'] ?></div>
            <div class="mark-stat-label">All Pending</div>
        </div>
    </a>
    <?php foreach ($marks as $m): if ($m === 'normal') continue; ?>
    <a href="marks.php?<?= http_build_query(array_merge($_GET,['mark'=>$m,'page'=>1])) ?>"
       class="mark-stat cc-card <?= $f_mark===$m?'active':'' ?>">
        <span class="badge bg-<?= $markColors[$m] ?> fs-6 px-2"><?= $markCounts[$m] ?></span>
        <div>
            <div class="mark-stat-label"><?= $markLabels[$m] ?></div>
        </div>
    </a>
    <?php endforeach; ?>
    <a href="marks.php?<?= http_build_query(array_merge($_GET,['mark'=>'normal','page'=>1])) ?>"
       class="mark-stat cc-card <?= $f_mark==='normal'?'active':'' ?>">
        <span class="badge bg-secondary fs-6 px-2"><?= $markCounts['normal'] ?></span>
        <div><div class="mark-stat-label">Normal</div></div>
    </a>
</div>

<!-- Filters -->
<div class="cc-card mb-3">
    <div class="cc-card-body p-3">
        <form method="GET">
            <?php if ($f_mark): ?><input type="hidden" name="mark" value="<?= e($f_mark) ?>"><?php endif; ?>
            <div class="row g-2 align-items-end">
                <div class="col-6 col-sm-3 col-md-2">
                    <select name="agent" class="form-select form-select-sm">
                        <option value="">All Agents</option>
                        <option value="<?= $aid ?>" <?= !$f_agent && $f_my ? 'selected' : '' ?>>My Calls</option>
                        <?php foreach ($allAgents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $f_agent===$ag['id']?'selected':'' ?>><?= e($ag['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="marks.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="cc-card">
    <div class="cc-card-head d-flex justify-content-between align-items-center">
        <span><?= number_format($total) ?> calls <?= $f_mark ? '— ' . $markLabels[$f_mark] : '(non-normal marks)' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table cc-table">
            <thead>
                <tr>
                    <th>Date / Time</th>
                    <th>Mark</th>
                    <th>Contact</th>
                    <th>From → To</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Agent</th>
                    <th class="text-center">Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$calls->num_rows): ?>
                <tr><td colspan="9" class="text-center py-5 text-muted">
                    <i class="fas fa-phone-slash fa-2x mb-2 d-block"></i>No calls found
                </td></tr>
            <?php endif; ?>
            <?php while ($c = $calls->fetch_assoc()):
                $mark = $c['call_mark'] ?? 'normal';
            ?>
            <tr class="mark-row-<?= $mark ?>">
                <td class="small">
                    <?= formatDt($c['calldate'],'d M y') ?><br>
                    <span class="text-muted"><?= formatDt($c['calldate'],'h:i A') ?></span>
                </td>
                <td>
                    <span class="badge bg-<?= $markColors[$mark] ?? 'secondary' ?>">
                        <?= $markLabels[$mark] ?? $mark ?>
                    </span>
                </td>
                <td>
                    <?php if ($c['contact_name']): ?>
                    <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $c['contact_id'] ?>" class="contact-link fw-medium">
                        <?= e($c['contact_name']) ?>
                    </a>
                    <?php if ($c['contact_company']): ?><div class="text-muted small"><?= e($c['contact_company']) ?></div><?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted font-monospace"><?= phoneLink($c['src'] ?? '') ?></span>
                    <?php endif; ?>
                </td>
                <td class="font-monospace small"><?= phoneLink($c['src'] ?? '') ?> → <?= phoneLink($c['dst'] ?? '') ?></td>
                <td><span class="badge bg-<?= dispositionClass($c['disposition']) ?>"><?= $c['disposition'] ?></span></td>
                <td><?= formatDuration($c['billsec']) ?></td>
                <td class="small"><?= e($c['agent_name'] ?: '—') ?></td>
                <td class="text-center">
                    <span class="badge bg-secondary"><?= $c['note_count'] ?: '0' ?></span>
                </td>
                <td class="text-nowrap">
                    <a href="<?= APP_URL ?>/call_detail.php?id=<?= $c['id'] ?>" class="btn-sm-icon text-primary" title="View Detail">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <?php if ($c['recordingfile']): ?>
                    <?php
                        $hasLocal = !empty($c['local_recording']) && file_exists($c['local_recording']);
                        $btnCls = $hasLocal ? 'text-success' : 'text-warning';
                        $btnIcon = $hasLocal ? 'fa-download' : 'fa-cloud-download-alt';
                    ?>
                    <button class="btn-sm-icon <?= $btnCls ?>" title="Download recording"
                            onclick="downloadRecording(<?= $c['id'] ?>)">
                        <i class="fas <?= $btnIcon ?>"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="p-3 d-flex justify-content-center">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">‹</a></li><?php endif; ?>
            <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a></li>
            <?php endfor; ?>
            <?php if ($page<$totalPages): ?><li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>



<script>
const APP_URL = '<?= APP_URL ?>';
</script>

<?php require_once 'includes/footer.php'; ?>
