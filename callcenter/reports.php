<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Reports';
$activePage = 'reports';
$aid        = agentId();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to']   ?? date('Y-m-d');

// ── Calls summary ─────────────────────────────────────────────────────────────
$callSummary = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(disposition='ANSWERED')  AS answered,
        SUM(disposition='NO ANSWER') AS missed,
        SUM(disposition='BUSY')      AS busy,
        SUM(disposition='FAILED')    AS failed,
        SUM(billsec)                 AS total_talk_sec,
        AVG(billsec)                 AS avg_talk_sec,
        SUM(is_manual)               AS manual_entries
     FROM call_logs
     WHERE DATE(calldate) BETWEEN '$from' AND '$to'"
)->fetch_assoc();

// ── Calls by agent ────────────────────────────────────────────────────────────
$byAgent = $conn->query(
    "SELECT a.full_name, COUNT(*) AS total,
            SUM(cl.disposition='ANSWERED') AS answered,
            SUM(cl.disposition='NO ANSWER') AS missed,
            SUM(cl.billsec) AS talk_sec
     FROM call_logs cl JOIN agents a ON a.id=cl.agent_id
     WHERE DATE(cl.calldate) BETWEEN '$from' AND '$to' AND cl.agent_id IS NOT NULL
     GROUP BY cl.agent_id ORDER BY total DESC"
);

// ── Calls by day ──────────────────────────────────────────────────────────────
$byDay = $conn->query(
    "SELECT DATE(calldate) AS day,
            COUNT(*) AS total,
            SUM(disposition='ANSWERED') AS answered,
            SUM(disposition='NO ANSWER') AS missed
     FROM call_logs
     WHERE DATE(calldate) BETWEEN '$from' AND '$to'
     GROUP BY DATE(calldate) ORDER BY day ASC"
);

// ── By disposition ────────────────────────────────────────────────────────────
$byDisp = $conn->query(
    "SELECT disposition, COUNT(*) AS c FROM call_logs
     WHERE DATE(calldate) BETWEEN '$from' AND '$to'
     GROUP BY disposition ORDER BY c DESC"
);

// ── Top contacts ──────────────────────────────────────────────────────────────
$topContacts = $conn->query(
    "SELECT c.name, c.phone, COUNT(*) AS calls,
            SUM(cl.disposition='ANSWERED') AS answered,
            SUM(cl.billsec) AS talk_sec
     FROM call_logs cl JOIN contacts c ON c.id=cl.contact_id
     WHERE DATE(cl.calldate) BETWEEN '$from' AND '$to'
     GROUP BY cl.contact_id ORDER BY calls DESC LIMIT 15"
);

// ── Task summary ──────────────────────────────────────────────────────────────
$taskSummary = $conn->query(
    "SELECT status, COUNT(*) AS c FROM todos
     WHERE DATE(created_at) BETWEEN '$from' AND '$to'
     GROUP BY status"
)->fetch_all(MYSQLI_ASSOC);

require_once 'includes/layout.php';
?>

<!-- Filter -->
<div class="cc-card mb-4">
    <div class="cc-card-body p-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div>
                <label class="form-label small">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="d-flex gap-2 align-items-end">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-chart-bar me-1"></i>Generate</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
                <a href="<?= APP_URL ?>/custom_report.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
                   target="_blank"
                   class="btn btn-sm" style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.4);color:#a5b4fc">
                    <i class="fas fa-file-lines me-1"></i>Custom Report
                </a>
                <button type="button" class="btn btn-outline-success btn-sm" onclick="exportCSV()">
                    <i class="fas fa-file-csv me-1"></i>Export Calls CSV
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyReport()">
                    <i class="fas fa-copy me-1"></i>Copy Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary KPI cards -->
<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon bg-primary"><i class="fas fa-phone-volume"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($callSummary['total']) ?></div>
            <div class="stat-label">Total Calls</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success"><i class="fas fa-phone"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($callSummary['answered']) ?></div>
            <div class="stat-label">Answered</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-danger"><i class="fas fa-phone-slash"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($callSummary['missed']) ?></div>
            <div class="stat-label">Missed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-info"><i class="fas fa-clock"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatDuration((int)$callSummary['total_talk_sec']) ?></div>
            <div class="stat-label">Total Talk Time</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-secondary"><i class="fas fa-chart-line"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= formatDuration((int)$callSummary['avg_talk_sec']) ?></div>
            <div class="stat-label">Avg Talk Time</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning"><i class="fas fa-pen"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= number_format($callSummary['manual_entries']) ?></div>
            <div class="stat-label">Manual Entries</div>
        </div>
    </div>
</div>

<div class="row g-4" id="reportBody">

<!-- By day table -->
<div class="col-lg-6">
    <div class="cc-card h-100">
        <div class="cc-card-head"><span><i class="fas fa-calendar me-2"></i>Calls by Day</span></div>
        <div class="table-responsive">
            <table class="table cc-table mb-0" id="byDayTable">
                <thead><tr><th>Date</th><th>Total</th><th>Answered</th><th>Missed</th><th>%</th></tr></thead>
                <tbody>
                <?php while ($d = $byDay->fetch_assoc()):
                    $pct = $d['total'] ? round($d['answered']/$d['total']*100) : 0;
                ?>
                <tr>
                    <td><?= date('D, d M', strtotime($d['day'])) ?></td>
                    <td><?= $d['total'] ?></td>
                    <td class="text-success"><?= $d['answered'] ?></td>
                    <td class="text-danger"><?= $d['missed'] ?></td>
                    <td>
                        <div class="progress" style="height:6px;width:60px">
                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="small"><?= $pct ?>%</span>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- By agent -->
<div class="col-lg-6">
    <div class="cc-card h-100">
        <div class="cc-card-head"><span><i class="fas fa-users me-2"></i>Calls by Agent</span></div>
        <div class="table-responsive">
            <table class="table cc-table mb-0" id="byAgentTable">
                <thead><tr><th>Agent</th><th>Total</th><th>Answered</th><th>Missed</th><th>Talk Time</th></tr></thead>
                <tbody>
                <?php while ($a = $byAgent->fetch_assoc()): ?>
                <tr>
                    <td><?= e($a['full_name']) ?></td>
                    <td><?= $a['total'] ?></td>
                    <td class="text-success"><?= $a['answered'] ?></td>
                    <td class="text-danger"><?= $a['missed'] ?></td>
                    <td><?= formatDuration((int)$a['talk_sec']) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- By disposition -->
<div class="col-lg-4">
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-chart-pie me-2"></i>By Disposition</span></div>
        <div class="cc-card-body">
            <?php while ($d = $byDisp->fetch_assoc()):
                $pct = $callSummary['total'] ? round($d['c']/$callSummary['total']*100) : 0;
            ?>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-<?= dispositionClass($d['disposition']) ?>" style="width:90px;text-align:center">
                    <?= $d['disposition'] ?>
                </span>
                <div class="progress flex-grow-1" style="height:8px">
                    <div class="progress-bar bg-<?= dispositionClass($d['disposition']) ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="text-muted small"><?= $d['c'] ?> (<?= $pct ?>%)</span>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- Top contacts -->
<div class="col-lg-8">
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-address-book me-2"></i>Top Contacts</span></div>
        <div class="table-responsive">
            <table class="table cc-table mb-0" id="topContactsTable">
                <thead><tr><th>#</th><th>Contact</th><th>Phone</th><th>Calls</th><th>Answered</th><th>Talk Time</th></tr></thead>
                <tbody>
                <?php $i=1; while ($c = $topContacts->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted"><?= $i++ ?></td>
                    <td><?= e($c['name'] ?: '—') ?></td>
                    <td class="font-monospace small"><?= phoneLink($c['phone'] ?? '') ?></td>
                    <td><?= $c['calls'] ?></td>
                    <td><?= $c['answered'] ?></td>
                    <td><?= formatDuration((int)$c['talk_sec']) ?></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Task summary -->
<div class="col-12">
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-list-check me-2"></i>Task Summary (<?= e($from) ?> – <?= e($to) ?>)</span></div>
        <div class="cc-card-body">
            <div class="d-flex flex-wrap gap-3">
                <?php $taskColors=['pending'=>'warning','in_progress'=>'info','done'=>'success','cancelled'=>'secondary'];
                foreach ($taskSummary as $ts): ?>
                <div class="stat-card" style="min-width:120px">
                    <div class="stat-icon bg-<?= $taskColors[$ts['status']] ?? 'secondary' ?>">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?= $ts['c'] ?></div>
                        <div class="stat-label"><?= ucwords(str_replace('_',' ',$ts['status'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- /.row -->

<script>
const APP_URL = '<?= APP_URL ?>';
const FROM = '<?= $from ?>', TO = '<?= $to ?>';

function exportCSV() {
    window.location = APP_URL + '/api/calls.php?export=csv&date_from=' + FROM + '&date_to=' + TO;
}
function copyReport() {
    const tables = [...document.querySelectorAll('#reportBody table')];
    let text = 'Ovijat Call Center Report\n' + FROM + ' to ' + TO + '\n\n';
    tables.forEach(t => {
        const rows = [...t.querySelectorAll('tr')];
        text += rows.map(r => [...r.querySelectorAll('th,td')].map(c => c.innerText.trim()).join('\t')).join('\n');
        text += '\n\n';
    });
    navigator.clipboard.writeText(text).then(()=>showToast('Report copied to clipboard','success'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
