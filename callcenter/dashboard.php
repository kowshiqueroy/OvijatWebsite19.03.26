<?php
require_once 'config.php';
requireLogin();

$pageTitle   = 'Dashboard';
$activePage  = 'dashboard';
$aid         = agentId();

// ── Stats ─────────────────────────────────────────────────────────────────────
$today = date('Y-m-d');

$stats = [
    'calls_today'    => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today'")->fetch_assoc()['c'],
    'answered_today' => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND disposition='ANSWERED'")->fetch_assoc()['c'],
    'missed_today'   => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND disposition='NO ANSWER'")->fetch_assoc()['c'],
    'my_tasks'       => (int)$conn->query("SELECT COUNT(*) AS c FROM todos WHERE assigned_to=$aid AND status IN ('pending','in_progress')")->fetch_assoc()['c'],
    'open_notes'     => (int)$conn->query("SELECT COUNT(*) AS c FROM call_notes WHERE agent_id=$aid AND log_status='open'")->fetch_assoc()['c'],
    'total_contacts' => (int)$conn->query("SELECT COUNT(*) AS c FROM contacts WHERE status='active'")->fetch_assoc()['c'],
    'calls_week'     => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE calldate >= DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch_assoc()['c'],
    'my_calls_today' => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND agent_id=$aid")->fetch_assoc()['c'],
];

// ── Missed calls (last 24h, no note, for alert) ───────────────────────────────
$missedAlert = $conn->query(
    "SELECT cl.*, c.name AS contact_name
     FROM call_logs cl
     LEFT JOIN contacts c ON c.id = cl.contact_id
     WHERE cl.disposition = 'NO ANSWER'
       AND cl.calldate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
       AND cl.call_mark = 'normal'
     ORDER BY cl.calldate DESC LIMIT 10"
);

// ── My pending tasks ──────────────────────────────────────────────────────────
$myTasks = $conn->query(
    "SELECT t.*, c.name AS contact_name, cl.src AS call_src,
            a.full_name AS assigned_by_name
     FROM todos t
     LEFT JOIN contacts c  ON c.id  = t.contact_id
     LEFT JOIN call_logs cl ON cl.id = t.call_id
     LEFT JOIN agents a     ON a.id  = t.created_by
     WHERE t.assigned_to = $aid AND t.status IN ('pending','in_progress')
     ORDER BY FIELD(t.priority,'urgent','high','medium','low'), t.due_date ASC
     LIMIT 8"
);

// ── Recent activity (my last 15 actions) ─────────────────────────────────────
$recentActivity = $conn->query(
    "SELECT al.*, a.full_name
     FROM activity_log al
     JOIN agents a ON a.id = al.agent_id
     WHERE al.agent_id = $aid
     ORDER BY al.created_at DESC LIMIT 15"
);

// ── Calls per disposition today (mini chart data) ────────────────────────────
$dispositionData = [];
$dRes = $conn->query("SELECT disposition, COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' GROUP BY disposition");
while ($d = $dRes->fetch_assoc()) $dispositionData[$d['disposition']] = $d['c'];

// ── Hourly call count today (for sparkline) ───────────────────────────────────
$hourlyData = array_fill(0, 24, 0);
$hRes = $conn->query("SELECT HOUR(calldate) AS h, COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' GROUP BY h");
while ($h = $hRes->fetch_assoc()) $hourlyData[$h['h']] = $h['c'];

// ── Open followups assigned to me ────────────────────────────────────────────
$followups = $conn->query(
    "SELECT cn.*, cl.src, cl.calldate, c.name AS contact_name
     FROM call_notes cn
     JOIN call_logs cl ON cl.id = cn.call_id
     LEFT JOIN contacts c ON c.id = cl.contact_id
     WHERE cn.agent_id = $aid AND cn.log_status IN ('open','followup') AND cn.note_type = 'followup'
     ORDER BY cn.created_at DESC LIMIT 5"
);

// ── Last fetch batch ──────────────────────────────────────────────────────────
$lastFetch = $conn->query("SELECT fb.*, a.full_name FROM fetch_batches fb JOIN agents a ON a.id=fb.fetched_by ORDER BY fb.started_at DESC LIMIT 1")->fetch_assoc();

require_once 'includes/layout.php';
?>

<!-- ── Missed calls alert banner ─────────────────────────────────────────────── -->
<?php if ($missedAlert->num_rows): ?>
<div class="alert-banner alert-missed">
    <i class="fas fa-phone-slash"></i>
    <strong><?= $missedAlert->num_rows ?> missed call<?= $missedAlert->num_rows > 1 ? 's' : '' ?></strong>
    in the last 24 hours with no follow-up action.
    <a href="<?= APP_URL ?>/calls.php?disposition=NO+ANSWER&date_from=<?= $today ?>" class="alert-link">View missed calls</a>
</div>
<?php endif; ?>

<!-- ── Stat cards ─────────────────────────────────────────────────────────────── -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary"><i class="fas fa-phone-volume"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['calls_today'] ?></div>
            <div class="stat-label">Total Calls Today</div>
        </div>
        <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>" class="stat-link">View</a>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success"><i class="fas fa-phone"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['answered_today'] ?></div>
            <div class="stat-label">Answered Today</div>
        </div>
        <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>&disposition=ANSWERED" class="stat-link">View</a>
    </div>
    <div class="stat-card <?= $stats['missed_today'] ? 'stat-alert' : '' ?>">
        <div class="stat-icon bg-danger"><i class="fas fa-phone-slash"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['missed_today'] ?></div>
            <div class="stat-label">Missed Today</div>
        </div>
        <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>&disposition=NO+ANSWER" class="stat-link">View</a>
    </div>
    <div class="stat-card <?= $stats['my_tasks'] ? 'stat-alert' : '' ?>">
        <div class="stat-icon bg-warning"><i class="fas fa-list-check"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['my_tasks'] ?></div>
            <div class="stat-label">My Pending Tasks</div>
        </div>
        <a href="<?= APP_URL ?>/todos.php" class="stat-link">View</a>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-info"><i class="fas fa-comment-dots"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['open_notes'] ?></div>
            <div class="stat-label">My Open Notes</div>
        </div>
        <a href="<?= APP_URL ?>/calls.php?my_notes=1" class="stat-link">View</a>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-secondary"><i class="fas fa-address-book"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['total_contacts'] ?></div>
            <div class="stat-label">Total Contacts</div>
        </div>
        <a href="<?= APP_URL ?>/contacts.php" class="stat-link">View</a>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple"><i class="fas fa-calendar-week"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['calls_week'] ?></div>
            <div class="stat-label">Calls This Week</div>
        </div>
        <a href="<?= APP_URL ?>/reports.php" class="stat-link">Report</a>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-teal"><i class="fas fa-headset"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $stats['my_calls_today'] ?></div>
            <div class="stat-label">My Calls Today</div>
        </div>
        <a href="<?= APP_URL ?>/calls.php?date_from=<?= $today ?>&my=1" class="stat-link">View</a>
    </div>
</div>

<!-- ── Hourly sparkline ───────────────────────────────────────────────────────── -->
<div class="cc-card mb-4">
    <div class="cc-card-head">
        <span><i class="fas fa-chart-line me-2"></i>Today's Call Volume (by hour)</span>
        <span class="text-muted small"><?= date('l, d M Y') ?></span>
    </div>
    <div class="cc-card-body p-3">
        <div class="sparkline-wrap" id="hourlyChart" data-values='<?= j($hourlyData) ?>'></div>
    </div>
</div>

<div class="row g-4">

<!-- ── Missed calls quick list ───────────────────────────────────────────────── -->
<div class="col-lg-6">
    <div class="cc-card h-100">
        <div class="cc-card-head">
            <span><i class="fas fa-phone-slash text-danger me-2"></i>Recent Missed (24h)</span>
            <a href="<?= APP_URL ?>/calls.php?disposition=NO+ANSWER" class="btn-link small">All missed</a>
        </div>
        <div class="cc-card-body p-0">
            <?php
            $missedAlert->data_seek(0);
            if ($missedAlert->num_rows):
                while ($mc = $missedAlert->fetch_assoc()):
            ?>
            <div class="quick-item">
                <div class="qi-icon text-danger"><i class="fas fa-phone-slash"></i></div>
                <div class="qi-body">
                    <div class="qi-title">
                        <?= e($mc['contact_name'] ?: $mc['src']) ?>
                        <span class="qi-num"><?= e($mc['src']) ?></span>
                    </div>
                    <div class="qi-sub"><?= formatDt($mc['calldate'], 'h:i A') ?></div>
                </div>
                <div class="qi-actions">
                    <a href="<?= APP_URL ?>/call_detail.php?id=<?= $mc['id'] ?>" class="btn-sm-icon" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button class="btn-sm-icon text-warning" title="Mark callback"
                            onclick="markCall(<?= $mc['id'] ?>,'callback')">
                        <i class="fas fa-phone-arrow-up-right"></i>
                    </button>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-state py-4"><i class="fas fa-check-circle text-success"></i> No missed calls in last 24h</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── My pending tasks ───────────────────────────────────────────────────────── -->
<div class="col-lg-6">
    <div class="cc-card h-100">
        <div class="cc-card-head">
            <span><i class="fas fa-list-check text-warning me-2"></i>My Tasks</span>
            <a href="<?= APP_URL ?>/todos.php" class="btn-link small">All tasks</a>
        </div>
        <div class="cc-card-body p-0">
            <?php if ($myTasks->num_rows): while ($t = $myTasks->fetch_assoc()): ?>
            <div class="quick-item">
                <div class="qi-icon">
                    <span class="priority-dot priority-<?= $t['priority'] ?>"></span>
                </div>
                <div class="qi-body">
                    <div class="qi-title"><?= e($t['title']) ?></div>
                    <div class="qi-sub">
                        <?php if ($t['contact_name']): ?><i class="fas fa-user me-1"></i><?= e($t['contact_name']) ?><?php endif; ?>
                        <?php if ($t['due_date']): ?>
                            &bull; Due <?= formatDt($t['due_date'], 'd M') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="qi-actions">
                    <span class="badge bg-<?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span>
                    <a href="<?= APP_URL ?>/todos.php?id=<?= $t['id'] ?>" class="btn-sm-icon"><i class="fas fa-eye"></i></a>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-state py-4"><i class="fas fa-check-circle text-success"></i> No pending tasks</div>
            <?php endif; ?>
        </div>
        <div class="cc-card-foot">
            <a href="<?= APP_URL ?>/todos.php?new=1" class="btn btn-sm btn-primary">
                <i class="fas fa-plus me-1"></i>New Task
            </a>
        </div>
    </div>
</div>

<!-- ── Open followups ─────────────────────────────────────────────────────────── -->
<div class="col-lg-6">
    <div class="cc-card">
        <div class="cc-card-head">
            <span><i class="fas fa-rotate-right text-info me-2"></i>My Open Followups</span>
        </div>
        <div class="cc-card-body p-0">
            <?php if ($followups->num_rows): while ($f = $followups->fetch_assoc()): ?>
            <div class="quick-item">
                <div class="qi-icon text-info"><i class="fas fa-comment-dots"></i></div>
                <div class="qi-body">
                    <div class="qi-title"><?= e(mb_strimwidth($f['content'], 0, 60, '…')) ?></div>
                    <div class="qi-sub">
                        <?= e($f['contact_name'] ?: $f['src']) ?> &bull; <?= timeAgo($f['created_at']) ?>
                    </div>
                </div>
                <div class="qi-actions">
                    <a href="<?= APP_URL ?>/call_detail.php?id=<?= $f['call_id'] ?>" class="btn-sm-icon">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-state py-4"><i class="fas fa-check-circle text-success"></i> No open followups</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Recent activity ───────────────────────────────────────────────────────── -->
<div class="col-lg-6">
    <div class="cc-card">
        <div class="cc-card-head">
            <span><i class="fas fa-clock-rotate-left me-2"></i>My Recent Activity</span>
        </div>
        <div class="cc-card-body p-0">
            <?php if ($recentActivity->num_rows): while ($a = $recentActivity->fetch_assoc()): ?>
            <div class="quick-item">
                <div class="qi-icon text-muted"><i class="fas fa-circle-dot" style="font-size:.5rem"></i></div>
                <div class="qi-body">
                    <div class="qi-title"><?= e(ucwords(str_replace('_',' ',$a['action']))) ?></div>
                    <div class="qi-sub">
                        <?= e(mb_strimwidth($a['details'] ?? '', 0, 70, '…')) ?>
                        &bull; <?= timeAgo($a['created_at']) ?>
                    </div>
                </div>
                <?php if ($a['entity_type'] && $a['entity_id']): ?>
                <div class="qi-actions">
                    <?php
                    $link = match($a['entity_type']) {
                        'call_logs' => "call_detail.php?id={$a['entity_id']}",
                        'contacts'  => "contact_detail.php?id={$a['entity_id']}",
                        'todos'     => "todos.php?id={$a['entity_id']}",
                        default     => ''
                    };
                    if ($link): ?>
                    <a href="<?= APP_URL ?>/<?= $link ?>" class="btn-sm-icon"><i class="fas fa-eye"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-state py-4"><i class="fas fa-inbox"></i> No recent activity</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /.row -->

<!-- ── Last fetch info ───────────────────────────────────────────────────────── -->
<?php if ($lastFetch): ?>
<div class="mt-4">
    <div class="cc-card">
        <div class="cc-card-head">
            <span><i class="fas fa-cloud-arrow-down me-2"></i>Last PBX Fetch</span>
            <a href="<?= APP_URL ?>/fetch.php" class="btn btn-sm btn-primary">
                <i class="fas fa-sync me-1"></i>Fetch Now
            </a>
        </div>
        <div class="cc-card-body">
            <div class="row text-center">
                <div class="col">
                    <div class="fw-bold"><?= $lastFetch['total_fetched'] ?></div>
                    <div class="text-muted small">Total Read</div>
                </div>
                <div class="col">
                    <div class="fw-bold text-success"><?= $lastFetch['new_records'] ?></div>
                    <div class="text-muted small">New Records</div>
                </div>
                <div class="col">
                    <div class="fw-bold text-warning"><?= $lastFetch['duplicates_skipped'] ?></div>
                    <div class="text-muted small">Duplicates Skipped</div>
                </div>
                <div class="col">
                    <div class="fw-bold text-info"><?= $lastFetch['contacts_created'] ?></div>
                    <div class="text-muted small">New Contacts</div>
                </div>
                <div class="col">
                    <div class="fw-bold"><?= e($lastFetch['full_name']) ?></div>
                    <div class="text-muted small">By</div>
                </div>
                <div class="col">
                    <div class="fw-bold"><?= timeAgo($lastFetch['started_at']) ?></div>
                    <div class="text-muted small">When</div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="mt-4 text-center">
    <a href="<?= APP_URL ?>/fetch.php" class="btn btn-primary btn-lg">
        <i class="fas fa-cloud-arrow-down me-2"></i>Configure PBX &amp; Fetch First Batch
    </a>
</div>
<?php endif; ?>

<script>
function markCall(id, mark) {
    fetch('<?= APP_URL ?>/api/calls.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'mark', id, mark})
    }).then(r => r.json()).then(d => {
        if (d.ok) { showToast('Call marked: ' + mark, 'success'); setTimeout(()=>location.reload(),800); }
        else showToast(d.error || 'Error', 'danger');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
