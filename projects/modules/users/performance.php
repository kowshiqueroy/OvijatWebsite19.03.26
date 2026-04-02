<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('admin');
$user = currentUser();

// ── Filters ────────────────────────────────────────────────
$dateFrom  = $_GET['date_from']  ?? date('Y-m-01');           // default: start of this month
$dateTo    = $_GET['date_to']    ?? date('Y-m-d');             // default: today
$projectId = (int)($_GET['project_id'] ?? 0);
$selUsers  = array_filter(array_map('intval', (array)($_GET['user_ids'] ?? [])));

// clamp dates
if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

// ── Reference data ─────────────────────────────────────────
$allUsers    = dbFetchAll("SELECT id, full_name, username FROM users WHERE is_active=1 ORDER BY full_name");
$allProjects = dbFetchAll("SELECT id, name FROM projects WHERE deleted_at IS NULL ORDER BY name");

if (empty($selUsers)) $selUsers = array_column($allUsers, 'id');

$userIds = $selUsers;
$uPhs    = implode(',', array_fill(0, count($userIds), '?'));

// ── Per-user metrics ───────────────────────────────────────
// Base task filter: date range on task creation, optional project
$projClause = $projectId ? "AND t.project_id = $projectId" : '';

// Tasks assigned within range
$assignedRows = dbFetchAll(
    "SELECT ta.user_id,
            COUNT(DISTINCT t.id)                                          AS assigned,
            SUM(t.status = 'done')                                        AS completed,
            SUM(t.status IN ('todo','in_progress','review'))              AS open,
            SUM(t.status != 'done' AND t.due_date < CURDATE()
                AND t.due_date IS NOT NULL)                               AS overdue,
            SUM(t.priority = 'high')                                      AS high_pri,
            SUM(t.priority = 'medium')                                    AS med_pri,
            SUM(t.priority = 'low')                                       AS low_pri
     FROM task_assignees ta
     JOIN tasks t ON t.id = ta.task_id
     WHERE ta.user_id IN ($uPhs)
       AND t.deleted_at IS NULL
       AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
       $projClause
     GROUP BY ta.user_id",
    array_merge($userIds, [$dateFrom, $dateTo])
);
$assignedMap = array_column($assignedRows, null, 'user_id');

// Hours logged within range
$hoursRows = dbFetchAll(
    "SELECT tl.user_id,
            ROUND(SUM(tl.hours), 1) AS logged_hours
     FROM task_time_logs tl
     JOIN tasks t ON t.id = tl.task_id
     WHERE tl.user_id IN ($uPhs)
       AND tl.logged_at BETWEEN ? AND ?
       AND t.deleted_at IS NULL
       $projClause
     GROUP BY tl.user_id",
    array_merge($userIds, [$dateFrom, $dateTo])
);
$hoursMap = array_column($hoursRows, null, 'user_id');

// Estimated hours on assigned tasks
$estRows = dbFetchAll(
    "SELECT ta.user_id,
            ROUND(SUM(COALESCE(t.estimated_hours, 0)), 1) AS est_hours
     FROM task_assignees ta
     JOIN tasks t ON t.id = ta.task_id
     WHERE ta.user_id IN ($uPhs)
       AND t.deleted_at IS NULL
       AND t.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
       $projClause
     GROUP BY ta.user_id",
    array_merge($userIds, [$dateFrom, $dateTo])
);
$estMap = array_column($estRows, null, 'user_id');

// Average days to complete a task (from task creation → status=done transition via updated_at)
$avgRows = dbFetchAll(
    "SELECT ta.user_id,
            ROUND(AVG(DATEDIFF(t.updated_at, t.created_at)), 1) AS avg_days
     FROM task_assignees ta
     JOIN tasks t ON t.id = ta.task_id
     WHERE ta.user_id IN ($uPhs)
       AND t.status = 'done'
       AND t.deleted_at IS NULL
       AND t.updated_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
       $projClause
     GROUP BY ta.user_id",
    array_merge($userIds, [$dateFrom, $dateTo])
);
$avgMap = array_column($avgRows, null, 'user_id');

// Meetings attended within range
$meetRows = dbFetchAll(
    "SELECT ma.user_id, COUNT(*) AS meetings_attended
     FROM meeting_attendees ma
     JOIN meetings m ON m.id = ma.meeting_id
     WHERE ma.user_id IN ($uPhs)
       AND ma.rsvp IN ('yes','maybe')
       AND m.status = 'done'
       AND m.meeting_date BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
       " . ($projectId ? "AND m.project_id = $projectId" : '') . "
     GROUP BY ma.user_id",
    array_merge($userIds, [$dateFrom, $dateTo])
);
$meetMap = array_column($meetRows, null, 'user_id');

// Activity updates posted within range
$actRows = dbFetchAll(
    "SELECT user_id, COUNT(*) AS updates_count
     FROM updates
     WHERE user_id IN ($uPhs)
       AND created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
       " . ($projectId ? "AND project_id = $projectId" : '') . "
     GROUP BY user_id",
    array_merge($userIds, [$dateFrom, $dateTo])
);
$actMap = array_column($actRows, null, 'user_id');

// Chat messages within range
$chatRows = dbFetchAll(
    "SELECT wc.user_id, COUNT(*) AS chat_count
     FROM worksheet_chat wc
     " . ($projectId ? "JOIN projects p ON p.id=wc.project_id AND p.id=$projectId" : '') . "
     WHERE wc.user_id IN ($uPhs)
       AND wc.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY wc.user_id",
    array_merge($userIds, [$dateFrom, $dateTo])
);
$chatMap = array_column($chatRows, null, 'user_id');

// ── Build final per-user summary ───────────────────────────
$stats = [];
foreach ($allUsers as $u) {
    if (!in_array((int)$u['id'], $userIds)) continue;
    $uid     = (int)$u['id'];
    $a       = $assignedMap[$uid] ?? [];
    $assigned    = (int)($a['assigned']   ?? 0);
    $completed   = (int)($a['completed']  ?? 0);
    $open        = (int)($a['open']       ?? 0);
    $overdue     = (int)($a['overdue']    ?? 0);
    $compRate    = $assigned > 0 ? round($completed / $assigned * 100) : 0;
    $loggedHrs   = (float)($hoursMap[$uid]['logged_hours'] ?? 0);
    $estHrs      = (float)($estMap[$uid]['est_hours']      ?? 0);
    $avgDays     = $avgMap[$uid]['avg_days'] ?? null;
    $meetings    = (int)($meetMap[$uid]['meetings_attended'] ?? 0);
    $activity    = (int)($actMap[$uid]['updates_count']      ?? 0);
    $chats       = (int)($chatMap[$uid]['chat_count']        ?? 0);
    $stats[] = compact('uid','u','assigned','completed','open','overdue','compRate',
                       'loggedHrs','estHrs','avgDays','meetings','activity','chats',
                       'a');
}

// Sort by completion rate desc
usort($stats, fn($x,$y) => $y['compRate'] <=> $x['compRate']);

$totalAssigned  = array_sum(array_column($stats, 'assigned'));
$totalCompleted = array_sum(array_column($stats, 'completed'));
$totalOverdue   = array_sum(array_column($stats, 'overdue'));
$totalHours     = round(array_sum(array_column($stats, 'loggedHrs')), 1);

layoutStart('User Performance', 'users');
?>
<style>
.perf-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:20px}
.perf-filters .form-group{margin:0}
.perf-filters label{font-size:.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:4px}
.perf-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
@media(max-width:767px){.perf-stat-grid{grid-template-columns:repeat(2,1fr)}}
.perf-stat{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px 18px}
.perf-stat-val{font-size:1.75rem;font-weight:700;color:var(--text);line-height:1}
.perf-stat-label{font-size:.78rem;color:var(--text-muted);margin-top:4px}
.perf-table th,.perf-table td{padding:10px 12px;font-size:.8375rem;vertical-align:middle;border-bottom:1px solid var(--border)}
.perf-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:700;white-space:nowrap;background:var(--bg)}
.perf-table tr:last-child td{border-bottom:none}
.perf-table tr:hover td{background:var(--bg)}
.bar-wrap{width:80px;height:7px;background:var(--border);border-radius:4px;overflow:hidden;display:inline-block;vertical-align:middle;margin-left:6px}
.bar-fill{height:100%;border-radius:4px;background:var(--accent)}
.bar-fill.danger{background:var(--danger)}
.bar-fill.success{background:var(--success)}
.rank-badge{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;font-size:.7rem;font-weight:700;background:var(--border);color:var(--text-muted)}
.rank-badge.gold{background:#FEF08A;color:#854D0E}
.rank-badge.silver{background:#E2E8F0;color:#475569}
.rank-badge.bronze{background:#FED7AA;color:#9A3412}
.user-cell{display:flex;align-items:center;gap:8px}
.sparkbar{display:inline-flex;align-items:flex-end;gap:2px;height:28px;vertical-align:middle}
.sparkbar span{width:6px;border-radius:2px 2px 0 0;background:var(--accent);opacity:.7;display:inline-block;transition:opacity .15s}
.sparkbar span:hover{opacity:1}
.section-title{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin:24px 0 10px}
.detail-card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:0;overflow:hidden;margin-bottom:20px}
.detail-card-header{padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.detail-card-title{font-weight:700;font-size:.875rem}
.metric-row{display:flex;align-items:center;justify-content:space-between;padding:9px 16px;border-bottom:1px solid var(--border);font-size:.8375rem}
.metric-row:last-child{border-bottom:none}
.metric-label{color:var(--text-muted)}
.metric-val{font-weight:600}
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">User Performance</h1>
        <p class="page-subtitle">Task completion, time logs, meetings & activity</p>
    </div>
    <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-ghost btn-sm">← Users</a>
</div>

<!-- ── Filters ─────────────────────────────────────────────── -->
<form method="GET" class="card" style="padding:16px 20px;margin-bottom:20px">
    <div class="perf-filters">
        <div class="form-group">
            <label>From</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" class="form-control" style="min-width:160px">
                <option value="">All Projects</option>
                <?php foreach ($allProjects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $projectId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="form-group">
            <label>Users</label>
            <select name="user_ids[]" class="form-control" multiple style="min-width:160px;height:36px" title="Hold Ctrl/Cmd to select multiple">
                <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>" <?= in_array((int)$u['id'], $selUsers) ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="form-group" style="align-self:flex-end">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="<?= BASE_URL ?>/modules/users/performance.php" class="btn btn-ghost" style="margin-left:6px">Reset</a>
        </div>
    </div>
    <div style="font-size:.75rem;color:var(--text-muted);margin-top:6px">
        Period: <strong><?= date('M j, Y', strtotime($dateFrom)) ?> – <?= date('M j, Y', strtotime($dateTo)) ?></strong>
        <?= $projectId ? ' · Project: <strong>' . e(array_column($allProjects,'name','id')[$projectId] ?? '') . '</strong>' : ' · All Projects' ?>
        · <?= count($stats) ?> user<?= count($stats) !== 1 ? 's' : '' ?>
    </div>
</form>

<!-- ── Summary stats ──────────────────────────────────────── -->
<div class="perf-stat-grid">
    <div class="perf-stat">
        <div class="perf-stat-val"><?= $totalAssigned ?></div>
        <div class="perf-stat-label">Tasks Assigned</div>
    </div>
    <div class="perf-stat">
        <div class="perf-stat-val" style="color:var(--success)"><?= $totalCompleted ?></div>
        <div class="perf-stat-label">Tasks Completed</div>
    </div>
    <div class="perf-stat">
        <div class="perf-stat-val" style="color:<?= $totalOverdue > 0 ? 'var(--danger)' : 'var(--text)' ?>"><?= $totalOverdue ?></div>
        <div class="perf-stat-label">Overdue Tasks</div>
    </div>
    <div class="perf-stat">
        <div class="perf-stat-val"><?= $totalHours ?>h</div>
        <div class="perf-stat-label">Hours Logged</div>
    </div>
</div>

<?php if (!$stats): ?>
<div class="empty-state"><p>No data for the selected filters.</p></div>
<?php else: ?>

<!-- ── Comparison table ───────────────────────────────────── -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <span class="card-title">Comparison — All Selected Users</span>
        <span style="font-size:.75rem;color:var(--text-muted)">Sorted by completion rate</span>
    </div>
    <div class="table-wrap" style="overflow-x:auto">
    <table class="perf-table" style="width:100%;border-collapse:collapse">
        <thead>
        <tr>
            <th>#</th>
            <th>Member</th>
            <th>Assigned</th>
            <th>Done</th>
            <th>Open</th>
            <th>Overdue</th>
            <th>Rate</th>
            <th>Est hrs</th>
            <th>Logged hrs</th>
            <th>Avg days/task</th>
            <th>Meetings</th>
            <th>Activity</th>
            <th>Chats</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($stats as $i => $s):
            $rank = $i + 1;
            $rankClass = $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : ($rank === 3 ? 'bronze' : ''));
            $rateColor = $s['compRate'] >= 75 ? 'var(--success)' : ($s['compRate'] >= 40 ? 'var(--warning)' : 'var(--danger)');
            $barClass  = $s['compRate'] >= 75 ? 'success' : ($s['compRate'] >= 40 ? '' : 'danger');
        ?>
        <tr>
            <td><span class="rank-badge <?= $rankClass ?>"><?= $rank ?></span></td>
            <td>
                <div class="user-cell">
                    <div class="avatar avatar-sm" style="background:<?= avatarColor($s['u']['full_name']) ?>"><?= userInitials($s['u']['full_name']) ?></div>
                    <div>
                        <div style="font-weight:600;font-size:.8375rem"><?= e($s['u']['full_name']) ?></div>
                        <div style="font-size:.72rem;color:var(--text-muted)">@<?= e($s['u']['username']) ?></div>
                    </div>
                </div>
            </td>
            <td style="font-weight:600"><?= $s['assigned'] ?></td>
            <td style="color:var(--success);font-weight:600"><?= $s['completed'] ?></td>
            <td><?= $s['open'] ?></td>
            <td style="color:<?= $s['overdue'] > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;font-weight:<?= $s['overdue'] > 0 ? '700' : '400' ?>"><?= $s['overdue'] ?: '—' ?></td>
            <td>
                <span style="font-weight:700;color:<?= $rateColor ?>"><?= $s['compRate'] ?>%</span>
                <span class="bar-wrap"><span class="bar-fill <?= $barClass ?>" style="width:<?= $s['compRate'] ?>%"></span></span>
            </td>
            <td style="color:var(--text-muted)"><?= $s['estHrs'] > 0 ? $s['estHrs'].'h' : '—' ?></td>
            <td style="font-weight:600"><?= $s['loggedHrs'] > 0 ? $s['loggedHrs'].'h' : '—' ?></td>
            <td style="color:var(--text-muted)"><?= $s['avgDays'] !== null ? $s['avgDays'].'d' : '—' ?></td>
            <td><?= $s['meetings'] ?: '—' ?></td>
            <td><?= $s['activity'] ?: '—' ?></td>
            <td><?= $s['chats'] ?: '—' ?></td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── Individual cards ───────────────────────────────────── -->
<div class="section-title">Individual Breakdowns</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
<?php foreach ($stats as $s):
    $rateColor = $s['compRate'] >= 75 ? 'var(--success)' : ($s['compRate'] >= 40 ? 'var(--warning)' : 'var(--danger)');
    $logVsEst  = ($s['estHrs'] > 0 && $s['loggedHrs'] > 0)
        ? round($s['loggedHrs'] / $s['estHrs'] * 100) : null;
    $hpri = (int)($s['a']['high_pri'] ?? 0);
    $mpri = (int)($s['a']['med_pri']  ?? 0);
    $lpri = (int)($s['a']['low_pri']  ?? 0);
?>
<div class="detail-card">
    <div class="detail-card-header">
        <div class="user-cell">
            <div class="avatar" style="background:<?= avatarColor($s['u']['full_name']) ?>"><?= userInitials($s['u']['full_name']) ?></div>
            <div>
                <div class="detail-card-title"><?= e($s['u']['full_name']) ?></div>
                <div style="font-size:.72rem;color:var(--text-muted)">@<?= e($s['u']['username']) ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/modules/users/activity.php?id=<?= $s['uid'] ?>" class="btn btn-ghost btn-sm" style="font-size:.72rem">Activity →</a>
    </div>

    <!-- Completion donut-style header -->
    <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px">
        <div style="font-size:1.6rem;font-weight:800;color:<?= $rateColor ?>"><?= $s['compRate'] ?>%</div>
        <div style="flex:1">
            <div style="font-size:.72rem;color:var(--text-muted);margin-bottom:4px">Completion rate · <?= $s['completed'] ?>/<?= $s['assigned'] ?> tasks</div>
            <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?= $s['compRate'] ?>%;background:<?= $rateColor ?>;border-radius:3px;transition:width .4s"></div>
            </div>
        </div>
    </div>

    <!-- Priority breakdown bar -->
    <?php if ($s['assigned'] > 0): ?>
    <div style="padding:8px 16px;border-bottom:1px solid var(--border)">
        <div style="font-size:.7rem;color:var(--text-muted);margin-bottom:5px;font-weight:600">Priority breakdown</div>
        <div style="display:flex;height:8px;border-radius:4px;overflow:hidden;gap:1px">
            <?php if ($hpri): ?><div style="flex:<?= $hpri ?>;background:var(--danger)" title="High: <?= $hpri ?>"></div><?php endif ?>
            <?php if ($mpri): ?><div style="flex:<?= $mpri ?>;background:var(--warning)" title="Med: <?= $mpri ?>"></div><?php endif ?>
            <?php if ($lpri): ?><div style="flex:<?= $lpri ?>;background:var(--success)" title="Low: <?= $lpri ?>"></div><?php endif ?>
        </div>
        <div style="display:flex;gap:10px;margin-top:4px;font-size:.7rem;color:var(--text-muted)">
            <?php if ($hpri): ?><span><span style="color:var(--danger)">●</span> High <?= $hpri ?></span><?php endif ?>
            <?php if ($mpri): ?><span><span style="color:var(--warning)">●</span> Med <?= $mpri ?></span><?php endif ?>
            <?php if ($lpri): ?><span><span style="color:var(--success)">●</span> Low <?= $lpri ?></span><?php endif ?>
        </div>
    </div>
    <?php endif ?>

    <div class="metric-row"><span class="metric-label">Open tasks</span><span class="metric-val"><?= $s['open'] ?></span></div>
    <div class="metric-row">
        <span class="metric-label">Overdue</span>
        <span class="metric-val" style="color:<?= $s['overdue'] > 0 ? 'var(--danger)' : 'inherit' ?>"><?= $s['overdue'] ?: '0' ?></span>
    </div>
    <div class="metric-row">
        <span class="metric-label">Hours logged</span>
        <span class="metric-val"><?= $s['loggedHrs'] > 0 ? $s['loggedHrs'].'h' : '—' ?>
            <?php if ($logVsEst !== null): ?>
            <span style="font-size:.7rem;color:<?= $logVsEst > 110 ? 'var(--danger)' : ($logVsEst < 60 ? 'var(--warning)' : 'var(--success)') ?>;font-weight:400;margin-left:4px"><?= $logVsEst ?>% of est.</span>
            <?php endif ?>
        </span>
    </div>
    <div class="metric-row"><span class="metric-label">Estimated hours</span><span class="metric-val"><?= $s['estHrs'] > 0 ? $s['estHrs'].'h' : '—' ?></span></div>
    <div class="metric-row"><span class="metric-label">Avg. days per task</span><span class="metric-val"><?= $s['avgDays'] !== null ? $s['avgDays'].'d' : '—' ?></span></div>
    <div class="metric-row"><span class="metric-label">Meetings attended</span><span class="metric-val"><?= $s['meetings'] ?: '—' ?></span></div>
    <div class="metric-row"><span class="metric-label">Activity updates</span><span class="metric-val"><?= $s['activity'] ?: '—' ?></span></div>
    <div class="metric-row" style="border-bottom:none"><span class="metric-label">Chat messages</span><span class="metric-val"><?= $s['chats'] ?: '—' ?></span></div>
</div>
<?php endforeach ?>
</div>

<?php endif ?>

<?php layoutEnd(); ?>
