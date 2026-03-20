<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$aid        = agentId();
$today      = date('Y-m-d');

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = [
    'calls_today'    => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today'")->fetch_assoc()['c'],
    'answered_today' => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND disposition='ANSWERED'")->fetch_assoc()['c'],
    'missed_today'   => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND disposition='NO ANSWER'")->fetch_assoc()['c'],
    'busy_today'     => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND disposition='BUSY'")->fetch_assoc()['c'],
    'my_tasks'       => (int)$conn->query("SELECT COUNT(*) AS c FROM todos WHERE assigned_to=$aid AND status IN ('pending','in_progress')")->fetch_assoc()['c'],
    'open_notes'     => (int)$conn->query("SELECT COUNT(*) AS c FROM call_notes WHERE agent_id=$aid AND log_status='open'")->fetch_assoc()['c'],
    'total_contacts' => (int)$conn->query("SELECT COUNT(*) AS c FROM contacts WHERE status='active'")->fetch_assoc()['c'],
    'calls_week'     => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE calldate >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'],
    'my_calls_today' => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND agent_id=$aid")->fetch_assoc()['c'],
];

// ── Missed / Busy calls (last 24h, no follow-up) ──────────────────────────────
$missedAlert = $conn->query(
    "SELECT cl.*, c.name AS contact_name
     FROM call_logs cl
     LEFT JOIN contacts c ON c.id = cl.contact_id
     WHERE cl.disposition IN ('NO ANSWER','BUSY')
       AND cl.calldate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
       AND cl.call_mark = 'normal'
       AND NOT EXISTS (SELECT 1 FROM call_notes cn WHERE cn.call_id = cl.id)
       AND NOT EXISTS (SELECT 1 FROM todos t WHERE t.call_id = cl.id)
     ORDER BY cl.calldate DESC LIMIT 15"
);
$missedCount = $missedAlert ? $missedAlert->num_rows : 0;

// ── My pending tasks ──────────────────────────────────────────────────────────
$myTasks = $conn->query(
    "SELECT t.*, c.name AS contact_name, cl.src AS call_src, a.full_name AS assigned_by_name
     FROM todos t
     LEFT JOIN contacts c  ON c.id  = t.contact_id
     LEFT JOIN call_logs cl ON cl.id = t.call_id
     LEFT JOIN agents a     ON a.id  = t.created_by
     WHERE t.assigned_to = $aid AND t.status IN ('pending','in_progress')
     ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.due_date ASC
     LIMIT 8"
);

// ── Recent activity ───────────────────────────────────────────────────────────
$recentActivity = $conn->query(
    "SELECT al.*, a.full_name
     FROM activity_log al
     JOIN agents a ON a.id = al.agent_id
     WHERE al.agent_id = $aid
     ORDER BY al.created_at DESC LIMIT 15"
);

// ── Hourly call count today ───────────────────────────────────────────────────
$hourlyData = array_fill(0, 24, 0);
$hRes = $conn->query("SELECT HOUR(calldate) AS h, COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' GROUP BY h");
while ($h = $hRes->fetch_assoc()) $hourlyData[$h['h']] = $h['c'];

// ── Open followups ────────────────────────────────────────────────────────────
$followups = $conn->query(
    "SELECT cn.*, cl.src, cl.calldate, c.name AS contact_name
     FROM call_notes cn
     JOIN call_logs cl ON cl.id = cn.call_id
     LEFT JOIN contacts c ON c.id = cl.contact_id
     WHERE cn.agent_id = $aid AND cn.log_status IN ('open','followup') AND cn.note_type = 'followup'
     ORDER BY cn.created_at DESC LIMIT 5"
);

// ── Last fetch ────────────────────────────────────────────────────────────────
$lastFetch = $conn->query("SELECT fb.*, a.full_name FROM fetch_batches fb JOIN agents a ON a.id=fb.fetched_by ORDER BY fb.started_at DESC LIMIT 1")->fetch_assoc();

// ── Answer rate ───────────────────────────────────────────────────────────────
$answerRate = $stats['calls_today'] > 0 ? round(($stats['answered_today'] / $stats['calls_today']) * 100) : 0;

require_once 'includes/layout.php';
?>

<style>
/* ── Dashboard specific ─────────────────────────────────────── */
.dash-priority-section {
    border-radius: 12px;
    border: 1px solid rgba(239,68,68,.35);
    background: linear-gradient(135deg, rgba(239,68,68,.08) 0%, rgba(245,158,11,.05) 100%);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
}
.dash-priority-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .75rem;
    flex-wrap: wrap;
    gap: .5rem;
}
.dash-priority-title {
    display: flex; align-items: center; gap: .5rem;
    font-weight: 700; font-size: .95rem; color: #fca5a5;
}
.pulse-dot {
    width: 10px; height: 10px; border-radius: 50%;
    background: var(--danger);
    box-shadow: 0 0 0 0 rgba(239,68,68,.6);
    animation: pulse-ring 1.5s infinite;
}
@keyframes pulse-ring {
    0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.6); }
    70%  { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
    100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
}
.missed-call-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .5rem .75rem;
    border-radius: 8px;
    background: rgba(0,0,0,.2);
    margin-bottom: .4rem;
    transition: background .15s;
}
.missed-call-row:last-child { margin-bottom: 0; }
.missed-call-row:hover { background: rgba(239,68,68,.12); }
.missed-icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: .8rem;
}
.missed-icon.no-answer { background: rgba(239,68,68,.15); color: #fca5a5; }
.missed-icon.busy      { background: rgba(245,158,11,.15); color: #fcd34d; }

/* ── Modern stat cards ──────────────────────────────────────── */
.dash-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px,1fr));
    gap: .75rem;
    margin-bottom: 1.5rem;
}
.dash-stat {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1rem 1rem .85rem;
    display: flex;
    flex-direction: column;
    gap: .4rem;
    position: relative;
    overflow: hidden;
    transition: border-color .2s, transform .15s;
    text-decoration: none;
}
.dash-stat:hover { border-color: var(--border2); transform: translateY(-1px); }
.dash-stat::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 12px 12px 0 0;
}
.dash-stat.accent-primary::before   { background: var(--accent); }
.dash-stat.accent-success::before   { background: var(--success); }
.dash-stat.accent-danger::before    { background: var(--danger); }
.dash-stat.accent-warning::before   { background: var(--warning); }
.dash-stat.accent-info::before      { background: var(--info); }
.dash-stat.accent-purple::before    { background: #8b5cf6; }
.dash-stat.accent-teal::before      { background: #14b8a6; }
.dash-stat.accent-secondary::before { background: var(--muted); }
.dash-stat.accent-danger { border-color: rgba(239,68,68,.3); }

.dash-stat-icon {
    width: 34px; height: 34px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; flex-shrink: 0;
}
.dash-stat-value {
    font-size: 1.65rem; font-weight: 800;
    line-height: 1; letter-spacing: -.5px;
    color: var(--text);
}
.dash-stat-label { font-size: .72rem; color: var(--muted); font-weight: 500; }
.dash-stat-link {
    font-size: .7rem; color: var(--accent);
    display: flex; align-items: center; gap: .2rem;
    margin-top: .15rem;
}
.dash-stat-link:hover { color: #818cf8; }

/* ── Chart card ─────────────────────────────────────────────── */
.dash-chart-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 1.5rem;
    overflow: hidden;
}
.dash-chart-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: .85rem 1.1rem;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap; gap: .5rem;
}
.dash-chart-title { font-weight: 600; font-size: .875rem; display: flex; align-items: center; gap: .4rem; }
.dash-chart-meta { font-size: .75rem; color: var(--muted); }

/* ── Answer rate ring ───────────────────────────────────────── */
.rate-ring-wrap { position: relative; width: 64px; height: 64px; flex-shrink: 0; }
.rate-ring-wrap svg { transform: rotate(-90deg); }
.rate-ring-text {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; font-weight: 700; color: var(--text);
}

/* ── Panel cards ────────────────────────────────────────────── */
.dash-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    height: 100%;
    display: flex; flex-direction: column;
}
.dash-panel-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--border);
    font-weight: 600; font-size: .8rem;
    flex-shrink: 0;
}
.dash-panel-body { flex: 1; overflow-y: auto; }
.dash-panel-foot {
    padding: .6rem 1rem;
    border-top: 1px solid var(--border);
    background: var(--card2);
    flex-shrink: 0;
}
.dash-item {
    display: flex; align-items: center; gap: .65rem;
    padding: .55rem 1rem;
    border-bottom: 1px solid var(--border);
    transition: background .1s;
}
.dash-item:last-child { border-bottom: none; }
.dash-item:hover { background: rgba(255,255,255,.02); }
.dash-item-icon {
    width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: .8rem;
}
.dash-item-body { flex: 1; min-width: 0; }
.dash-item-title { font-size: .82rem; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dash-item-sub   { font-size: .72rem; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dash-item-aside { flex-shrink: 0; display: flex; align-items: center; gap: .4rem; }

/* ── Fetch mini card ────────────────────────────────────────── */
.fetch-mini {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: .85rem 1.1rem;
    display: flex; align-items: center; gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}
.fetch-mini-stat { text-align: center; min-width: 60px; }
.fetch-mini-stat .val { font-weight: 700; font-size: 1.05rem; }
.fetch-mini-stat .lbl { font-size: .68rem; color: var(--muted); }
.fetch-divider { width: 1px; height: 36px; background: var(--border); flex-shrink: 0; }
</style>

<?php
// ── Priority section: missed + busy unactioned calls ─────────────────────────
$missedAlert->data_seek(0);
$noAnswerCount = 0; $busyCount = 0;
while ($row = $missedAlert->fetch_assoc()) {
    if ($row['disposition'] === 'NO ANSWER') $noAnswerCount++;
    else $busyCount++;
}
$missedAlert->data_seek(0);
?>

<?php if ($missedCount > 0): ?>
<!-- ── Priority: Unactioned missed/busy calls ─────────────────────────────── -->
<div class="dash-priority-section">
    <div class="dash-priority-header">
        <div class="dash-priority-title">
            <span class="pulse-dot"></span>
            <i class="fas fa-phone-slash"></i>
            Action Required — <?= $missedCount ?> unhandled call<?= $missedCount > 1 ? 's' : '' ?> (last 24h)
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($noAnswerCount): ?>
            <span class="badge" style="background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3)">
                <i class="fas fa-phone-slash me-1"></i><?= $noAnswerCount ?> Missed
            </span>
            <?php endif; ?>
            <?php if ($busyCount): ?>
            <span class="badge" style="background:rgba(245,158,11,.2);color:#fcd34d;border:1px solid rgba(245,158,11,.3)">
                <i class="fas fa-phone-volume me-1"></i><?= $busyCount ?> Busy
            </span>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/calls.php?disposition=NO+ANSWER&date_from=<?= $today ?>"
               class="btn btn-sm" style="background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);font-size:.75rem">
                View All <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <div style="max-height:260px;overflow-y:auto">
        <?php while ($mc = $missedAlert->fetch_assoc()):
            $isBusy = $mc['disposition'] === 'BUSY';
        ?>
        <div class="missed-call-row">
            <div class="missed-icon <?= $isBusy ? 'busy' : 'no-answer' ?>">
                <i class="fas fa-<?= $isBusy ? 'phone-volume' : 'phone-slash' ?>"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?php if ($mc['contact_name']): ?>
                        <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $mc['contact_id'] ?>" style="color:var(--text)"><?= e($mc['contact_name']) ?></a>
                    <?php else: ?>
                        <span class="font-monospace"><?= e($mc['src']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:.7rem;color:var(--muted)">
                    <?= e($mc['src']) ?> &middot; <?= formatDt($mc['calldate'], 'd M, h:i A') ?>
                </div>
            </div>
            <span class="badge" style="font-size:.65rem;background:<?= $isBusy ? 'rgba(245,158,11,.15)' : 'rgba(239,68,68,.15)' ?>;color:<?= $isBusy ? '#fcd34d' : '#fca5a5' ?>">
                <?= $isBusy ? 'BUSY' : 'MISSED' ?>
            </span>
            <div class="d-flex gap-1">
                <a href="<?= APP_URL ?>/call_detail.php?id=<?= $mc['id'] ?>"
                   class="btn btn-sm" style="background:rgba(255,255,255,.06);border:1px solid var(--border);color:var(--muted);padding:.2rem .45rem;font-size:.7rem"
                   title="View detail"><i class="fas fa-eye"></i></a>
                <button class="btn btn-sm" style="background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.3);color:#a5b4fc;padding:.2rem .45rem;font-size:.7rem"
                        onclick="markCall(<?= $mc['id'] ?>,'callback')" title="Mark callback">
                    <i class="fas fa-phone-arrow-up-right"></i>
                </button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Stat cards ─────────────────────────────────────────────────────────── -->
<div class="dash-stat-grid">

    <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>" class="dash-stat accent-primary">
        <div class="dash-stat-icon" style="background:rgba(99,102,241,.15);color:var(--accent)">
            <i class="fas fa-phone-volume"></i>
        </div>
        <div class="dash-stat-value"><?= $stats['calls_today'] ?></div>
        <div class="dash-stat-label">Total Calls Today</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> View logs</div>
    </a>

    <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>&disposition=ANSWERED" class="dash-stat accent-success">
        <div class="dash-stat-icon" style="background:rgba(16,185,129,.15);color:var(--success)">
            <i class="fas fa-phone"></i>
        </div>
        <div class="dash-stat-value" style="color:var(--success)"><?= $stats['answered_today'] ?></div>
        <div class="dash-stat-label">Answered Today</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> View answered</div>
    </a>

    <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>&disposition=NO+ANSWER" class="dash-stat accent-danger">
        <div class="dash-stat-icon" style="background:rgba(239,68,68,.15);color:var(--danger)">
            <i class="fas fa-phone-slash"></i>
        </div>
        <div class="dash-stat-value" style="color:<?= $stats['missed_today'] ? 'var(--danger)' : 'var(--text)' ?>"><?= $stats['missed_today'] ?></div>
        <div class="dash-stat-label">Missed Today</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> View missed</div>
    </a>

    <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>&disposition=BUSY" class="dash-stat accent-warning">
        <div class="dash-stat-icon" style="background:rgba(245,158,11,.15);color:var(--warning)">
            <i class="fas fa-phone-volume"></i>
        </div>
        <div class="dash-stat-value" style="color:<?= $stats['busy_today'] ? 'var(--warning)' : 'var(--text)' ?>"><?= $stats['busy_today'] ?></div>
        <div class="dash-stat-label">Busy / Cancelled</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> View busy</div>
    </a>

    <a href="<?= APP_URL ?>/todos.php" class="dash-stat <?= $stats['my_tasks'] ? 'accent-warning' : 'accent-secondary' ?>">
        <div class="dash-stat-icon" style="background:rgba(245,158,11,.15);color:var(--warning)">
            <i class="fas fa-list-check"></i>
        </div>
        <div class="dash-stat-value"><?= $stats['my_tasks'] ?></div>
        <div class="dash-stat-label">My Pending Tasks</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> My tasks</div>
    </a>

    <a href="<?= APP_URL ?>/contacts.php" class="dash-stat accent-secondary">
        <div class="dash-stat-icon" style="background:rgba(136,146,164,.15);color:var(--muted)">
            <i class="fas fa-address-book"></i>
        </div>
        <div class="dash-stat-value"><?= $stats['total_contacts'] ?></div>
        <div class="dash-stat-label">Total Contacts</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> View contacts</div>
    </a>

    <a href="<?= APP_URL ?>/reports.php" class="dash-stat accent-purple">
        <div class="dash-stat-icon" style="background:rgba(139,92,246,.15);color:#8b5cf6">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="dash-stat-value"><?= $stats['calls_week'] ?></div>
        <div class="dash-stat-label">Calls This Week</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> Reports</div>
    </a>

    <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>&my=1" class="dash-stat accent-teal">
        <div class="dash-stat-icon" style="background:rgba(20,184,166,.15);color:#14b8a6">
            <i class="fas fa-headset"></i>
        </div>
        <div class="dash-stat-value"><?= $stats['my_calls_today'] ?></div>
        <div class="dash-stat-label">My Calls Today</div>
        <div class="dash-stat-link"><i class="fas fa-arrow-right" style="font-size:.6rem"></i> My calls</div>
    </a>

</div>

<!-- ── Hourly chart + answer rate ─────────────────────────────────────────── -->
<div class="dash-chart-card mb-4">
    <div class="dash-chart-head">
        <div class="dash-chart-title">
            <i class="fas fa-chart-area" style="color:var(--accent)"></i>
            Call Volume — Today by Hour
        </div>
        <div class="d-flex align-items-center gap-3">
            <!-- Answer rate mini ring -->
            <div class="d-flex align-items-center gap-2">
                <div class="rate-ring-wrap">
                    <svg width="64" height="64" viewBox="0 0 64 64">
                        <circle cx="32" cy="32" r="26" fill="none" stroke="var(--border2)" stroke-width="6"/>
                        <circle cx="32" cy="32" r="26" fill="none"
                                stroke="<?= $answerRate >= 70 ? 'var(--success)' : ($answerRate >= 40 ? 'var(--warning)' : 'var(--danger)') ?>"
                                stroke-width="6"
                                stroke-dasharray="<?= round(2 * 3.14159 * 26 * $answerRate / 100, 1) ?> 163.36"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="rate-ring-text"><?= $answerRate ?>%</div>
                </div>
                <div>
                    <div style="font-size:.75rem;font-weight:600">Answer Rate</div>
                    <div style="font-size:.68rem;color:var(--muted)">Today</div>
                </div>
            </div>
            <div class="fetch-divider"></div>
            <div class="dash-chart-meta"><?= date('l, d M Y') ?></div>
        </div>
    </div>
    <div class="p-3">
        <div class="sparkline-wrap" id="hourlyChart" data-values='<?= j($hourlyData) ?>'></div>
    </div>
</div>

<!-- ── 4-panel grid ───────────────────────────────────────────────────────── -->
<div class="row g-3">

<!-- My Tasks -->
<div class="col-lg-6">
    <div class="dash-panel">
        <div class="dash-panel-head">
            <span style="display:flex;align-items:center;gap:.4rem">
                <i class="fas fa-list-check" style="color:var(--warning)"></i> My Pending Tasks
                <?php if ($myTasks->num_rows): ?>
                <span class="badge" style="background:rgba(245,158,11,.2);color:var(--warning);font-size:.65rem"><?= $myTasks->num_rows ?></span>
                <?php endif; ?>
            </span>
            <a href="<?= APP_URL ?>/todos.php" style="font-size:.75rem;color:var(--accent)">All tasks</a>
        </div>
        <div class="dash-panel-body">
            <?php if ($myTasks->num_rows): while ($t = $myTasks->fetch_assoc()):
                $pColors = ['urgent'=>'var(--danger)','high'=>'var(--warning)','medium'=>'var(--info)','low'=>'var(--muted)'];
                $pc = $pColors[$t['priority']] ?? 'var(--muted)';
            ?>
            <div class="dash-item">
                <div class="dash-item-icon" style="background:<?= $pc ?>20">
                    <i class="fas fa-circle-dot" style="color:<?= $pc ?>;font-size:.65rem"></i>
                </div>
                <div class="dash-item-body">
                    <div class="dash-item-title"><?= e($t['title']) ?></div>
                    <div class="dash-item-sub">
                        <?= $t['contact_name'] ? e($t['contact_name']) . ' · ' : '' ?>
                        <?= $t['due_date'] ? 'Due ' . formatDt($t['due_date'], 'd M') : 'No deadline' ?>
                    </div>
                </div>
                <div class="dash-item-aside">
                    <span class="badge" style="background:<?= $pc ?>20;color:<?= $pc ?>;font-size:.62rem"><?= $t['priority'] ?></span>
                    <a href="<?= APP_URL ?>/todos.php?id=<?= $t['id'] ?>" class="btn-sm-icon"><i class="fas fa-eye"></i></a>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:2.5rem 1rem;color:var(--muted)">
                <i class="fas fa-check-circle fa-2x" style="color:var(--success);opacity:.7;margin-bottom:.5rem;display:block"></i>
                No pending tasks
            </div>
            <?php endif; ?>
        </div>
        <div class="dash-panel-foot">
            <a href="<?= APP_URL ?>/todos.php?new=1" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i>New Task
            </a>
        </div>
    </div>
</div>

<!-- Open Followups -->
<div class="col-lg-6">
    <div class="dash-panel">
        <div class="dash-panel-head">
            <span style="display:flex;align-items:center;gap:.4rem">
                <i class="fas fa-rotate-right" style="color:var(--info)"></i> My Open Followups
            </span>
            <a href="<?= APP_URL ?>/calls.php?my_notes=1" style="font-size:.75rem;color:var(--accent)">View all</a>
        </div>
        <div class="dash-panel-body">
            <?php if ($followups->num_rows): while ($f = $followups->fetch_assoc()): ?>
            <div class="dash-item">
                <div class="dash-item-icon" style="background:rgba(59,130,246,.12)">
                    <i class="fas fa-comment-dots" style="color:var(--info);font-size:.8rem"></i>
                </div>
                <div class="dash-item-body">
                    <div class="dash-item-title"><?= e(mb_strimwidth($f['content'], 0, 55, '…')) ?></div>
                    <div class="dash-item-sub">
                        <?= $f['contact_name'] ? e($f['contact_name']) . ' · ' : '' ?><?= timeAgo($f['created_at']) ?>
                    </div>
                </div>
                <div class="dash-item-aside">
                    <a href="<?= APP_URL ?>/call_detail.php?id=<?= $f['call_id'] ?>" class="btn-sm-icon"><i class="fas fa-eye"></i></a>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:2.5rem 1rem;color:var(--muted)">
                <i class="fas fa-check-circle fa-2x" style="color:var(--success);opacity:.7;margin-bottom:.5rem;display:block"></i>
                No open followups
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="col-lg-6">
    <div class="dash-panel">
        <div class="dash-panel-head">
            <span style="display:flex;align-items:center;gap:.4rem">
                <i class="fas fa-clock-rotate-left" style="color:var(--muted)"></i> My Recent Activity
            </span>
        </div>
        <div class="dash-panel-body">
            <?php if ($recentActivity->num_rows): while ($a = $recentActivity->fetch_assoc()):
                $link = match($a['entity_type'] ?? '') {
                    'call_logs' => "call_detail.php?id={$a['entity_id']}",
                    'contacts'  => "contact_detail.php?id={$a['entity_id']}",
                    'todos'     => "todos.php?id={$a['entity_id']}",
                    default     => ''
                };
            ?>
            <div class="dash-item">
                <div class="dash-item-icon" style="background:rgba(255,255,255,.04)">
                    <i class="fas fa-circle-dot" style="color:var(--muted);font-size:.5rem"></i>
                </div>
                <div class="dash-item-body">
                    <div class="dash-item-title"><?= e(ucwords(str_replace('_', ' ', $a['action']))) ?></div>
                    <div class="dash-item-sub"><?= e(mb_strimwidth($a['details'] ?? '', 0, 65, '…')) ?> · <?= timeAgo($a['created_at']) ?></div>
                </div>
                <?php if ($link): ?>
                <div class="dash-item-aside">
                    <a href="<?= APP_URL ?>/<?= $link ?>" class="btn-sm-icon"><i class="fas fa-eye"></i></a>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:2.5rem 1rem;color:var(--muted)">
                <i class="fas fa-inbox fa-2x" style="opacity:.4;margin-bottom:.5rem;display:block"></i>
                No recent activity
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Last Fetch -->
<div class="col-lg-6">
    <div class="dash-panel">
        <div class="dash-panel-head">
            <span style="display:flex;align-items:center;gap:.4rem">
                <i class="fas fa-cloud-arrow-down" style="color:var(--accent)"></i> PBX Sync
            </span>
            <button class="btn btn-sm btn-primary" onclick="fetchNow(this)" style="font-size:.72rem;padding:.25rem .65rem">
                <i class="fas fa-sync me-1" id="fetchNowIcon"></i>Fetch Now
            </button>
        </div>
        <div class="dash-panel-body">
            <?php if ($lastFetch): ?>
            <div class="p-3">
                <div class="row g-2 text-center mb-3">
                    <div class="col">
                        <div style="font-size:1.2rem;font-weight:700"><?= $lastFetch['total_fetched'] ?></div>
                        <div style="font-size:.68rem;color:var(--muted)">Read</div>
                    </div>
                    <div class="col">
                        <div style="font-size:1.2rem;font-weight:700;color:var(--success)"><?= $lastFetch['new_records'] ?></div>
                        <div style="font-size:.68rem;color:var(--muted)">New</div>
                    </div>
                    <div class="col">
                        <div style="font-size:1.2rem;font-weight:700;color:var(--warning)"><?= $lastFetch['duplicates_skipped'] ?></div>
                        <div style="font-size:.68rem;color:var(--muted)">Skipped</div>
                    </div>
                    <div class="col">
                        <div style="font-size:1.2rem;font-weight:700;color:var(--info)"><?= $lastFetch['contacts_created'] ?></div>
                        <div style="font-size:.68rem;color:var(--muted)">Contacts</div>
                    </div>
                </div>
                <div style="background:var(--card2);border:1px solid var(--border);border-radius:8px;padding:.6rem .85rem;font-size:.78rem;color:var(--muted)">
                    <i class="fas fa-user me-1"></i><?= e($lastFetch['full_name']) ?>
                    <span class="mx-2">&middot;</span>
                    <i class="fas fa-clock me-1"></i><?= timeAgo($lastFetch['started_at']) ?>
                </div>
                <div id="fetchResultBox" class="mt-2" style="display:none"></div>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:2rem 1rem">
                <i class="fas fa-cloud-arrow-down fa-2x" style="color:var(--accent);opacity:.5;margin-bottom:.75rem;display:block"></i>
                <div style="font-size:.82rem;color:var(--muted);margin-bottom:.75rem">No fetch history yet</div>
                <a href="<?= APP_URL ?>/fetch.php" class="btn btn-sm btn-primary">Configure PBX</a>
            </div>
            <div id="fetchResultBox" class="m-3" style="display:none"></div>
            <?php endif; ?>
        </div>
        <div class="dash-panel-foot">
            <a href="<?= APP_URL ?>/fetch.php" style="font-size:.75rem;color:var(--accent)">
                <i class="fas fa-gear me-1"></i>PBX Settings & History
            </a>
        </div>
    </div>
</div>

</div><!-- /.row -->

<!-- Fetch loading modal -->
<div class="modal fade" id="fetchModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-body text-center py-4">
                <i class="fas fa-sync fa-spin fa-2x mb-3" style="color:var(--accent)"></i>
                <div class="fw-semibold mb-1">Fetching from PBX…</div>
                <div class="text-muted small">Last 7 days — this may take up to a minute</div>
            </div>
        </div>
    </div>
</div>

<script>
function markCall(id, mark) {
    fetch('<?= APP_URL ?>/api/calls.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'mark', id, mark})
    }).then(r=>r.json()).then(d => {
        if (d.ok) { showToast('Marked: ' + mark, 'success'); setTimeout(() => location.reload(), 800); }
        else showToast(d.error || 'Error', 'danger');
    });
}

function fetchNow(btn) {
    const modal = new bootstrap.Modal(document.getElementById('fetchModal'));
    modal.show();
    btn.disabled = true;
    document.getElementById('fetchNowIcon').className = 'fas fa-spin fa-spinner me-1';

    const dateFrom = new Date(); dateFrom.setDate(dateFrom.getDate() - 7);
    const fmt = d => d.toISOString().slice(0,10);
    fetch('<?= APP_URL ?>/api/fetch.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'run', date_from: fmt(dateFrom), date_to: fmt(new Date())})
    }).then(r => r.json()).then(d => {
        modal.hide();
        btn.disabled = false;
        document.getElementById('fetchNowIcon').className = 'fas fa-sync me-1';
        const box = document.getElementById('fetchResultBox');
        if (box) {
            box.style.display = '';
            if (d.ok || d.new_records !== undefined) {
                box.innerHTML = `<div class="alert alert-success small py-2 mb-0">
                    <i class="fas fa-check-circle me-1"></i>
                    Fetched <strong>${d.total_fetched ?? '?'}</strong> records —
                    <strong class="text-success">${d.new_records ?? 0}</strong> new,
                    ${d.duplicates_skipped ?? 0} skipped
                </div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                box.innerHTML = `<div class="alert alert-danger small py-2 mb-0">
                    <i class="fas fa-exclamation-circle me-1"></i>${d.error || 'Fetch failed'}
                </div>`;
            }
        }
    }).catch(err => {
        modal.hide();
        btn.disabled = false;
        document.getElementById('fetchNowIcon').className = 'fas fa-sync me-1';
        showToast('Network error during fetch', 'danger');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
