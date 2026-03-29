<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/includes/layout.php';

requireRole('member');
$user    = currentUser();
$isAdmin = $user['role'] === 'admin';

// ── Summary stats ─────────────────────────────────────────────
if ($isAdmin) {
    $totalProjects = (int)dbFetch("SELECT COUNT(*) c FROM projects WHERE status != 'archived'")['c'];
    $totalTasks    = (int)dbFetch("SELECT COUNT(*) c FROM tasks")['c'];
    $doneTasks     = (int)dbFetch("SELECT COUNT(*) c FROM tasks WHERE status='done' AND MONTH(updated_at)=MONTH(NOW()) AND YEAR(updated_at)=YEAR(NOW())")['c'];
    $overdueTasks  = (int)dbFetch("SELECT COUNT(*) c FROM tasks WHERE status != 'done' AND due_date < CURDATE()")['c'];
    $totalHours    = (float)(dbFetch("SELECT COALESCE(SUM(hours),0) s FROM task_time_logs")['s']);
} else {
    $totalProjects = (int)dbFetch("SELECT COUNT(*) c FROM project_members WHERE user_id=?", [$user['id']])['c'];
    $totalTasks    = (int)dbFetch("SELECT COUNT(DISTINCT t.id) c FROM tasks t LEFT JOIN task_assignees ta ON ta.task_id=t.id WHERE t.created_by=? OR ta.user_id=?", [$user['id'], $user['id']])['c'];
    $doneTasks     = (int)dbFetch("SELECT COUNT(DISTINCT t.id) c FROM tasks t LEFT JOIN task_assignees ta ON ta.task_id=t.id WHERE (t.created_by=? OR ta.user_id=?) AND t.status='done' AND MONTH(t.updated_at)=MONTH(NOW()) AND YEAR(t.updated_at)=YEAR(NOW())", [$user['id'], $user['id']])['c'];
    $overdueTasks  = (int)dbFetch("SELECT COUNT(DISTINCT t.id) c FROM tasks t LEFT JOIN task_assignees ta ON ta.task_id=t.id WHERE (t.created_by=? OR ta.user_id=?) AND t.status != 'done' AND t.due_date < CURDATE()", [$user['id'], $user['id']])['c'];
    $totalHours    = (float)(dbFetch("SELECT COALESCE(SUM(hours),0) s FROM task_time_logs WHERE user_id=?", [$user['id']])['s']);
}

// ── Task by status ─────────────────────────────────────────────
if ($isAdmin) {
    $byStatus = dbFetchAll("SELECT status, COUNT(*) n FROM tasks GROUP BY status");
} else {
    $byStatus = dbFetchAll("SELECT t.status, COUNT(DISTINCT t.id) n FROM tasks t LEFT JOIN task_assignees ta ON ta.task_id=t.id WHERE t.created_by=? OR ta.user_id=? GROUP BY t.status", [$user['id'], $user['id']]);
}
$statusMap = ['todo' => 0, 'in_progress' => 0, 'review' => 0, 'done' => 0];
foreach ($byStatus as $r) $statusMap[$r['status']] = (int)$r['n'];
$statusTotal = array_sum($statusMap) ?: 1;

// ── Task by priority ───────────────────────────────────────────
if ($isAdmin) {
    $byPriority = dbFetchAll("SELECT priority, COUNT(*) n FROM tasks GROUP BY priority");
} else {
    $byPriority = dbFetchAll("SELECT t.priority, COUNT(DISTINCT t.id) n FROM tasks t LEFT JOIN task_assignees ta ON ta.task_id=t.id WHERE t.created_by=? OR ta.user_id=? GROUP BY t.priority", [$user['id'], $user['id']]);
}
$priorityMap = ['high' => 0, 'medium' => 0, 'low' => 0];
foreach ($byPriority as $r) $priorityMap[$r['priority']] = (int)$r['n'];
$priorityTotal = array_sum($priorityMap) ?: 1;

// ── Project health ─────────────────────────────────────────────
if ($isAdmin) {
    $projectHealth = dbFetchAll(
        "SELECT p.id, p.name, p.status, p.due_date,
                COUNT(t.id) as total,
                SUM(t.status='done') as done_n,
                SUM(t.status != 'done' AND t.due_date < CURDATE()) as overdue_n,
                COALESCE(SUM(tl.hours),0) as hours
         FROM projects p
         LEFT JOIN tasks t ON t.project_id=p.id
         LEFT JOIN task_time_logs tl ON tl.task_id=t.id
         WHERE p.status != 'archived'
         GROUP BY p.id ORDER BY p.status, p.name"
    );
} else {
    $projectHealth = dbFetchAll(
        "SELECT p.id, p.name, p.status, p.due_date,
                COUNT(DISTINCT t.id) as total,
                SUM(t.status='done') as done_n,
                SUM(t.status != 'done' AND t.due_date < CURDATE()) as overdue_n,
                COALESCE(SUM(tl.hours),0) as hours
         FROM projects p
         JOIN project_members pm ON pm.project_id=p.id
         LEFT JOIN tasks t ON t.project_id=p.id
         LEFT JOIN task_time_logs tl ON tl.task_id=t.id AND tl.user_id=?
         WHERE pm.user_id=? AND p.status != 'archived'
         GROUP BY p.id ORDER BY p.status, p.name",
        [$user['id'], $user['id']]
    );
}

// ── Hours by member (admin) / personal breakdown (member) ─────
if ($isAdmin) {
    $hoursByMember = dbFetchAll(
        "SELECT u.full_name, COALESCE(SUM(tl.hours),0) as hours, COUNT(tl.id) as entries
         FROM users u
         LEFT JOIN task_time_logs tl ON tl.user_id=u.id
         WHERE u.is_active=1
         GROUP BY u.id ORDER BY hours DESC LIMIT 15"
    );
} else {
    $hoursByMember = dbFetchAll(
        "SELECT p.name as full_name, COALESCE(SUM(tl.hours),0) as hours, COUNT(tl.id) as entries
         FROM projects p
         JOIN project_members pm ON pm.project_id=p.id
         LEFT JOIN tasks t ON t.project_id=p.id
         LEFT JOIN task_time_logs tl ON tl.task_id=t.id AND tl.user_id=?
         WHERE pm.user_id=?
         GROUP BY p.id ORDER BY hours DESC LIMIT 15",
        [$user['id'], $user['id']]
    );
}
$maxHours = max(array_column($hoursByMember, 'hours') ?: [1]);

// ── Weekly tasks created — last 8 weeks ───────────────────────
$weeklyData = [];
for ($w = 7; $w >= 0; $w--) {
    $start = date('Y-m-d', strtotime("-$w weeks monday this week"));
    $end   = date('Y-m-d', strtotime("-$w weeks sunday this week"));
    if ($isAdmin) {
        $n = (int)dbFetch("SELECT COUNT(*) c FROM tasks WHERE DATE(created_at) BETWEEN ? AND ?", [$start, $end])['c'];
    } else {
        $n = (int)dbFetch("SELECT COUNT(DISTINCT t.id) c FROM tasks t LEFT JOIN task_assignees ta ON ta.task_id=t.id WHERE (t.created_by=? OR ta.user_id=?) AND DATE(t.created_at) BETWEEN ? AND ?", [$user['id'], $user['id'], $start, $end])['c'];
    }
    $weeklyData[] = ['label' => date('M j', strtotime($start)), 'n' => $n];
}
$maxWeekly = max(array_column($weeklyData, 'n') ?: [1]);

// ── Time-log heatmap — last 52 weeks ──────────────────────────
$heatmapData = [];
$heatStart = date('Y-m-d', strtotime('-364 days'));
if ($isAdmin) {
    $heatRows = dbFetchAll("SELECT DATE(logged_at) as d, SUM(hours) as h FROM task_time_logs WHERE logged_at >= ? GROUP BY DATE(logged_at)", [$heatStart]);
} else {
    $heatRows = dbFetchAll("SELECT DATE(logged_at) as d, SUM(hours) as h FROM task_time_logs WHERE user_id=? AND logged_at >= ? GROUP BY DATE(logged_at)", [$user['id'], $heatStart]);
}
foreach ($heatRows as $r) { $heatmapData[$r['d']] = (float)$r['h']; }

// ── Overdue task list ──────────────────────────────────────────
if ($isAdmin) {
    $overdueList = dbFetchAll(
        "SELECT t.id, t.title, t.priority, t.due_date, p.name as project_name, u.full_name as creator
         FROM tasks t JOIN projects p ON p.id=t.project_id JOIN users u ON u.id=t.created_by
         WHERE t.status != 'done' AND t.due_date < CURDATE()
         ORDER BY t.due_date ASC LIMIT 10"
    );
} else {
    $overdueList = dbFetchAll(
        "SELECT DISTINCT t.id, t.title, t.priority, t.due_date, p.name as project_name
         FROM tasks t JOIN projects p ON p.id=t.project_id
         LEFT JOIN task_assignees ta ON ta.task_id=t.id
         WHERE (t.created_by=? OR ta.user_id=?) AND t.status != 'done' AND t.due_date < CURDATE()
         ORDER BY t.due_date ASC LIMIT 10",
        [$user['id'], $user['id']]
    );
}

layoutStart('Reports', 'reports');

// Helpers
function pct($n, $total): int { return $total > 0 ? (int)round($n / $total * 100) : 0; }

$statusColors  = ['todo'=>'#9CA3AF','in_progress'=>'#F59E0B','review'=>'#8B5CF6','done'=>'#10B981'];
$priorityColors = ['high'=>'#EF4444','medium'=>'#F59E0B','low'=>'#10B981'];
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle"><?= $isAdmin ? 'Organisation overview' : 'Your personal stats' ?></p>
    </div>
    <a href="<?= BASE_URL ?>/export.php?type=timelogs" class="btn btn-secondary btn-sm">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Time Logs
    </a>
</div>

<!-- ── Summary Stats ─────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px">
    <div class="stat-card">
        <div class="stat-value"><?= $totalProjects ?></div>
        <div class="stat-label"><?= $isAdmin ? 'Active Projects' : 'My Projects' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $totalTasks ?></div>
        <div class="stat-label">Total Tasks</div>
    </div>
    <div class="stat-card" style="border-color:var(--success);background:#F0FDF4">
        <div class="stat-value" style="color:var(--success)"><?= $doneTasks ?></div>
        <div class="stat-label">Done This Month</div>
    </div>
    <div class="stat-card" style="<?= $overdueTasks > 0 ? 'border-color:var(--danger);background:#FEF2F2' : '' ?>">
        <div class="stat-value" style="color:<?= $overdueTasks > 0 ? 'var(--danger)' : 'var(--text)' ?>"><?= $overdueTasks ?></div>
        <div class="stat-label" style="<?= $overdueTasks > 0 ? 'color:var(--danger)' : '' ?>">Overdue</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totalHours, 1) ?>h</div>
        <div class="stat-label"><?= $isAdmin ? 'Total Hours' : 'Hours Logged' ?></div>
    </div>
</div>

<!-- ── Status + Priority ─────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="rpt-grid-2">

<div class="card">
    <div class="card-header"><span class="card-title">Tasks by Status</span></div>
    <div class="card-body">
    <?php
    $statusLabels = ['todo'=>'To Do','in_progress'=>'In Progress','review'=>'Review','done'=>'Done'];
    foreach ($statusMap as $s => $n): $p = pct($n, $statusTotal); ?>
    <div class="rpt-bar-row">
        <div class="rpt-bar-label"><?= $statusLabels[$s] ?></div>
        <div class="rpt-bar-track">
            <div class="rpt-bar-fill" style="width:<?= $p ?>%;background:<?= $statusColors[$s] ?>"></div>
        </div>
        <div class="rpt-bar-val"><?= $n ?> <span style="color:var(--text-muted)">(<?= $p ?>%)</span></div>
    </div>
    <?php endforeach ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Tasks by Priority</span></div>
    <div class="card-body">
    <?php foreach ($priorityMap as $pr => $n): $p = pct($n, $priorityTotal); ?>
    <div class="rpt-bar-row">
        <div class="rpt-bar-label"><?= ucfirst($pr) ?></div>
        <div class="rpt-bar-track">
            <div class="rpt-bar-fill" style="width:<?= $p ?>%;background:<?= $priorityColors[$pr] ?>"></div>
        </div>
        <div class="rpt-bar-val"><?= $n ?> <span style="color:var(--text-muted)">(<?= $p ?>%)</span></div>
    </div>
    <?php endforeach ?>
    </div>
</div>

</div>

<!-- ── Weekly trend ───────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">Tasks Created — Last 8 Weeks</span></div>
    <div class="card-body" style="padding-top:8px">
    <?php
    $svgH = 140; $svgW = 600; $bars = count($weeklyData);
    $slotW = $svgW / $bars; $barW = $slotW * 0.55; $maxH = 90;
    echo '<svg viewBox="0 0 '.$svgW.' '.($svgH).'" style="width:100%;display:block">';
    foreach ($weeklyData as $i => $wd) {
        $bh  = $maxWeekly > 0 ? round($wd['n'] / $maxWeekly * $maxH) : 0;
        $x   = $i * $slotW + ($slotW - $barW) / 2;
        $y   = $svgH - 30 - $bh;
        $cx  = $i * $slotW + $slotW / 2;
        if ($bh > 0) {
            echo '<rect x="'.round($x).'" y="'.$y.'" width="'.round($barW).'" height="'.$bh.'" rx="3" fill="#4F6BED" opacity=".85"/>';
            echo '<text x="'.$cx.'" y="'.($y - 4).'" text-anchor="middle" font-size="11" fill="#6B7280">'.$wd['n'].'</text>';
        }
        echo '<text x="'.$cx.'" y="'.($svgH - 8).'" text-anchor="middle" font-size="10" fill="#9CA3AF">'.$wd['label'].'</text>';
    }
    // baseline
    echo '<line x1="0" y1="'.($svgH-28).'" x2="'.$svgW.'" y2="'.($svgH-28).'" stroke="#E2E6EA" stroke-width="1"/>';
    echo '</svg>';
    ?>
    </div>
</div>

<!-- ── Project Health ────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <span class="card-title">Project Health</span>
    </div>
    <div class="table-wrap"><table>
    <thead><tr>
        <th>Project</th><th>Status</th><th>Progress</th><th>Tasks</th>
        <th style="color:var(--danger)">Overdue</th><th>Hours</th>
        <th>Due</th>
    </tr></thead>
    <tbody>
    <?php foreach ($projectHealth as $ph):
        $pp = $ph['total'] > 0 ? round($ph['done_n'] / $ph['total'] * 100) : 0;
        $isOverProj = $ph['due_date'] && $ph['due_date'] < date('Y-m-d') && $ph['status'] !== 'completed';
    ?>
    <tr>
        <td><a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $ph['id'] ?>" style="font-weight:500;color:var(--text)"><?= e($ph['name']) ?></a></td>
        <td><span class="badge badge-<?= $ph['status'] ?>"><?= ucwords(str_replace('_',' ',$ph['status'])) ?></span></td>
        <td style="min-width:120px">
            <div style="display:flex;align-items:center;gap:8px">
                <div class="progress" style="flex:1;height:6px">
                    <div class="progress-bar" style="width:<?= $pp ?>%;<?= $pp>=100 ? 'background:var(--success)' : '' ?>"></div>
                </div>
                <span style="font-size:.75rem;color:var(--text-muted);white-space:nowrap"><?= $pp ?>%</span>
            </div>
        </td>
        <td style="font-size:.875rem"><?= (int)$ph['done_n'] ?>/<?= (int)$ph['total'] ?></td>
        <td style="font-size:.875rem;color:<?= $ph['overdue_n'] > 0 ? 'var(--danger)' : 'var(--text-muted)' ?>;font-weight:<?= $ph['overdue_n'] > 0 ? '600' : '400' ?>">
            <?= (int)$ph['overdue_n'] ?: '—' ?>
        </td>
        <td style="font-size:.875rem"><?= number_format((float)$ph['hours'], 1) ?>h</td>
        <td style="font-size:.8125rem;color:<?= $isOverProj ? 'var(--danger)' : 'var(--text-muted)' ?>">
            <?= $ph['due_date'] ? date('M j, Y', strtotime($ph['due_date'])) : '—' ?>
        </td>
    </tr>
    <?php endforeach ?>
    <?php if (!$projectHealth): ?>
    <tr><td colspan="7"><div class="empty-state" style="padding:20px"><p>No projects.</p></div></td></tr>
    <?php endif ?>
    </tbody>
    </table></div>
</div>

<!-- ── Hours + Overdue ───────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="rpt-grid-2">

<!-- Hours by member / by project -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><?= $isAdmin ? 'Hours by Member' : 'Hours by Project' ?></span>
        <span style="font-size:.75rem;color:var(--text-muted)"><?= number_format($totalHours,1) ?>h total</span>
    </div>
    <div class="card-body">
    <?php if ($hoursByMember): foreach ($hoursByMember as $hm):
        $hp = $maxHours > 0 ? round($hm['hours'] / $maxHours * 100) : 0; ?>
    <div class="rpt-bar-row">
        <div class="rpt-bar-label"><?= e($hm['full_name']) ?></div>
        <div class="rpt-bar-track">
            <div class="rpt-bar-fill" style="width:<?= $hp ?>%;background:var(--accent)"></div>
        </div>
        <div class="rpt-bar-val"><?= number_format((float)$hm['hours'], 1) ?>h</div>
    </div>
    <?php endforeach; else: ?>
    <div class="empty-state" style="padding:20px"><p>No time logs yet.</p></div>
    <?php endif ?>
    </div>
</div>

<!-- Overdue list -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Overdue Tasks</span>
        <?php if ($overdueTasks > 10): ?>
        <span style="font-size:.75rem;color:var(--danger)"><?= $overdueTasks ?> total</span>
        <?php endif ?>
    </div>
    <?php if ($overdueList): ?>
    <div style="padding:0">
    <?php foreach ($overdueList as $ot):
        $days = (int)ceil((time() - strtotime($ot['due_date'])) / 86400);
    ?>
    <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $ot['id'] ?>"
       style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);color:var(--text);text-decoration:none">
        <span class="badge badge-<?= $ot['priority'] ?>" style="flex-shrink:0"><?= ucfirst($ot['priority']) ?></span>
        <div style="flex:1;min-width:0">
            <div style="font-size:.875rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($ot['title']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($ot['project_name']) ?></div>
        </div>
        <span style="font-size:.75rem;color:var(--danger);white-space:nowrap;font-weight:600"><?= $days ?>d ago</span>
    </a>
    <?php endforeach ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:28px">
        <p style="color:var(--success)">✓ No overdue tasks!</p>
    </div>
    <?php endif ?>
</div>

</div>

<!-- ── Time Log Heatmap ───────────────────────────────── -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><span class="card-title">Time Log Activity — Last 52 Weeks</span></div>
    <div class="card-body" style="overflow-x:auto">
    <?php
    $cellSize = 12; $gap = 2; $cols = 53;
    $dayNames = ['S','M','T','W','T','F','S'];
    $months = [];
    // Build grid: each column = 1 week (Sun–Sat), 53 weeks
    $today = new DateTime();
    $today->setTime(0,0,0);
    // Start on the Sunday of 52 weeks ago
    $start = clone $today;
    $start->modify('-364 days');
    $dow = (int)$start->format('w'); // 0=Sun
    if ($dow > 0) $start->modify("-{$dow} days");

    $maxH = $heatmapData ? max(array_values($heatmapData)) : 1;
    $maxH = max($maxH, 0.1);

    $svgW = ($cellSize + $gap) * $cols + 30;
    $svgH = ($cellSize + $gap) * 7 + 26;
    echo '<svg viewBox="0 0 '.$svgW.' '.$svgH.'" style="min-width:'.($svgW).'px;display:block;font-family:inherit">';
    // Day labels
    foreach ($dayNames as $di => $dl) {
        if ($di % 2 === 1) { // Mon, Wed, Fri
            echo '<text x="0" y="'.((($cellSize+$gap)*$di)+$cellSize+20).'" font-size="8" fill="#9CA3AF">'.$dl.'</text>';
        }
    }

    $col = 0;
    $cur = clone $start;
    $lastMonth = '';
    while ($cur <= $today) {
        $x = 30 + $col * ($cellSize + $gap);
        $month = $cur->format('M');
        if ($month !== $lastMonth) {
            echo '<text x="'.$x.'" y="10" font-size="9" fill="#6B7280">'.$month.'</text>';
            $lastMonth = $month;
        }
        // fill week column
        $dayInCol = (int)$cur->format('w'); // should be 0 (Sun)
        for ($d = 0; $d < 7; $d++) {
            $dayDate = clone $cur;
            $dayDate->modify("+{$d} days");
            if ($dayDate > $today) break;
            $ds  = $dayDate->format('Y-m-d');
            $hrs = $heatmapData[$ds] ?? 0;
            $y   = 16 + $d * ($cellSize + $gap);
            if ($hrs > 0) {
                $intensity = min(1.0, $hrs / $maxH);
                $alpha     = round(0.15 + $intensity * 0.85, 2);
                $color     = "rgba(79,107,237,{$alpha})";
            } else {
                $color = 'var(--border)';
            }
            $title = $ds.($hrs > 0 ? ': '.number_format($hrs,1).'h' : ': no logs');
            echo '<rect x="'.$x.'" y="'.$y.'" width="'.$cellSize.'" height="'.$cellSize.'" rx="2" fill="'.$color.'"><title>'.$title.'</title></rect>';
        }
        $col++;
        $cur->modify('+7 days');
    }
    // Legend
    $lx = $svgW - 100;
    $ly = $svgH - 14;
    echo '<text x="'.$lx.'" y="'.$ly.'" font-size="8" fill="#9CA3AF">Less</text>';
    for ($li = 0; $li < 5; $li++) {
        $alpha = round(0.15 + ($li / 4) * 0.85, 2);
        echo '<rect x="'.($lx+28+$li*14).'" y="'.($ly-9).'" width="10" height="10" rx="2" fill="rgba(79,107,237,'.$alpha.')"/>';
    }
    echo '<text x="'.($lx+28+5*14+2).'" y="'.$ly.'" font-size="8" fill="#9CA3AF">More</text>';
    echo '</svg>';
    ?>
    </div>
</div>

<style>
.rpt-bar-row {
  display: flex; align-items: center; gap: 10px; margin-bottom: 12px;
}
.rpt-bar-label {
  width: 100px; min-width: 100px; font-size: .8125rem;
  color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.rpt-bar-track {
  flex: 1; height: 8px; background: var(--border); border-radius: 4px; overflow: hidden;
}
.rpt-bar-fill { height: 100%; border-radius: 4px; transition: width .4s; min-width: 3px; }
.rpt-bar-val { font-size: .8125rem; color: var(--text); white-space: nowrap; min-width: 60px; text-align: right; }
@media (max-width: 767px) { .rpt-grid-2 { grid-template-columns: 1fr !important; } }
@media (max-width: 900px) { .stats-grid { grid-template-columns: repeat(3,1fr) !important; } }
</style>

<?php layoutEnd(); ?>
