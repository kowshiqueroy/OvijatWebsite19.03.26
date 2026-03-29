<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/includes/layout.php';

requireRole('member');
$user = currentUser();

$filterProject = (int)($_GET['project_id'] ?? 0);

$projects = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name, start_date, due_date FROM projects WHERE status != 'archived' ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name, p.start_date, p.due_date FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? AND p.status != 'archived' ORDER BY p.name", [$user['id']]);

$project = $tasks = $milestones = [];
$minTs = $maxTs = $totalDays = $timelineW = $pxPerDay = 0;
$weekMarkers = $monthMarkers = [];
$todayX = null;

if ($filterProject) {
    $project = dbFetch("SELECT * FROM projects WHERE id=?", [$filterProject]);

    // Use start_date if column exists, else fallback to created_at
    $hasStartDate = false;
    try {
        $check = db()->query("SHOW COLUMNS FROM tasks LIKE 'start_date'");
        $hasStartDate = (bool)$check->fetch();
    } catch (\Exception $e) {}

    $startExpr = $hasStartDate ? "COALESCE(t.start_date, DATE(t.created_at))" : "DATE(t.created_at)";

    $tasks = dbFetchAll(
        "SELECT t.id, t.title, t.status, t.priority, t.due_date,
                $startExpr as bar_start,
                t.due_date as bar_end,
                m.title as milestone_name, m.id as milestone_id
         FROM tasks t
         LEFT JOIN milestones m ON m.id = t.milestone_id
         WHERE t.project_id = ? AND t.due_date IS NOT NULL
         ORDER BY m.id IS NULL, m.id, $startExpr",
        [$filterProject]
    );

    $milestones = dbFetchAll(
        "SELECT id, title, due_date FROM milestones WHERE project_id=? ORDER BY ISNULL(due_date), due_date",
        [$filterProject]
    );
}

if ($tasks || $project) {
    $tsArr = [];
    foreach ($tasks as $t) {
        if ($t['bar_start']) $tsArr[] = strtotime($t['bar_start']);
        if ($t['bar_end'])   $tsArr[] = strtotime($t['bar_end']);
    }
    if (!empty($project['start_date'])) $tsArr[] = strtotime($project['start_date']);
    if (!empty($project['due_date']))   $tsArr[] = strtotime($project['due_date']);
    if (!$tsArr) $tsArr = [strtotime('first day of this month'), strtotime('last day of next month')];

    $minTs     = min($tsArr) - 3 * 86400;
    $maxTs     = max($tsArr) + 8 * 86400;
    $totalDays = max(14, (int)ceil(($maxTs - $minTs) / 86400));
    // px per day — scale to keep timeline readable
    $pxPerDay  = (int)max(8, min(40, round(1400 / $totalDays)));
    $timelineW = $totalDays * $pxPerDay;

    // Week markers
    $d = strtotime('last sunday', $minTs + 86400);
    while ($d <= $maxTs) {
        $x = ($d - $minTs) / 86400 * $pxPerDay;
        $weekMarkers[] = ['x' => $x, 'label' => date('M j', $d)];
        $d += 7 * 86400;
    }

    // Month markers (for longer timelines)
    if ($totalDays > 60) {
        $m = mktime(0,0,0, (int)date('n',$minTs), 1, (int)date('Y',$minTs));
        while ($m <= $maxTs) {
            $x = ($m - $minTs) / 86400 * $pxPerDay;
            $monthMarkers[] = ['x' => $x, 'label' => date('M Y', $m)];
            $m = mktime(0,0,0, (int)date('n',$m)+1, 1, (int)date('Y',$m));
        }
    }

    $todayTs = strtotime(date('Y-m-d'));
    if ($todayTs >= $minTs && $todayTs <= $maxTs) {
        $todayX = ($todayTs - $minTs) / 86400 * $pxPerDay;
    }
}

// Group tasks by milestone
$grouped = [];
foreach ($tasks as $t) {
    $key = $t['milestone_id'] ? 'ms_'.$t['milestone_id'] : 'none';
    $grouped[$key][] = $t;
}

$statusColors = [
    'todo'        => ['bg' => '#E5E7EB', 'color' => '#6B7280'],
    'in_progress' => ['bg' => '#FEF3C7', 'color' => '#92400E'],
    'review'      => ['bg' => '#EDE9FE', 'color' => '#5B21B6'],
    'done'        => ['bg' => '#D1FAE5', 'color' => '#065F46'],
];

layoutStart('Timeline', 'tasks');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Timeline</h1>
        <?php if ($project): ?>
        <p class="page-subtitle"><?= e($project['name']) ?></p>
        <?php endif ?>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select onchange="location='?project_id='+this.value" class="form-control" style="width:auto">
            <option value="">— Select project —</option>
            <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $filterProject === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach ?>
        </select>
        <?php if ($filterProject): ?>
        <a href="<?= BASE_URL ?>/modules/tasks/index.php?project_id=<?= $filterProject ?>" class="btn btn-secondary btn-sm">List</a>
        <a href="<?= BASE_URL ?>/modules/tasks/board.php?project_id=<?= $filterProject ?>" class="btn btn-secondary btn-sm">Board</a>
        <?php endif ?>
    </div>
</div>

<?php if (!$filterProject): ?>
<div class="empty-state card" style="padding:48px">
    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;opacity:.3"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
    <p>Select a project to view its timeline.</p>
</div>
<?php elseif (!$tasks): ?>
<div class="empty-state card" style="padding:48px">
    <p>No tasks with due dates found for this project.</p>
    <a href="<?= BASE_URL ?>/modules/tasks/create.php?project_id=<?= $filterProject ?>" class="btn btn-primary" style="margin-top:12px">Add Task</a>
</div>
<?php else: ?>

<!-- Legend -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;font-size:.8125rem;color:var(--text-muted)">
    <?php foreach ($statusColors as $s => $c): ?>
    <span style="display:flex;align-items:center;gap:5px">
        <span style="width:12px;height:12px;border-radius:3px;background:<?= $c['bg'] ?>;display:inline-block"></span>
        <?= ucwords(str_replace('_',' ',$s)) ?>
    </span>
    <?php endforeach ?>
    <span style="display:flex;align-items:center;gap:5px">
        <span style="width:2px;height:14px;background:var(--accent);display:inline-block"></span>
        Today
    </span>
</div>

<div class="card" style="overflow:hidden">
<div class="gantt-outer">
<div style="min-width:<?= 200 + $timelineW ?>px">

    <!-- Header row -->
    <div class="gantt-row gantt-header-row">
        <div class="gantt-label-cell" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted)">Task</div>
        <div class="gantt-tl-cell" style="position:relative;height:36px;width:<?= $timelineW ?>px">
            <!-- vertical week lines in header -->
            <?php foreach ($weekMarkers as $wm): ?>
            <div style="position:absolute;top:0;bottom:0;left:<?= round($wm['x']) ?>px;width:1px;background:var(--border)"></div>
            <div style="position:absolute;top:8px;left:<?= round($wm['x']) + 4 ?>px;font-size:.65rem;color:var(--text-muted);white-space:nowrap"><?= $wm['label'] ?></div>
            <?php endforeach ?>
            <?php if ($todayX !== null): ?>
            <div style="position:absolute;top:0;bottom:0;left:<?= round($todayX) ?>px;width:2px;background:var(--accent);z-index:2"></div>
            <?php endif ?>
        </div>
    </div>

    <?php
    // Project bar (if has dates)
    if (!empty($project['start_date']) && !empty($project['due_date'])):
        $ps = strtotime($project['start_date']);
        $pe = strtotime($project['due_date']);
        $pl = max(0, ($ps - $minTs) / 86400 * $pxPerDay);
        $pw = max(4, ($pe - $ps) / 86400 * $pxPerDay);
    ?>
    <div class="gantt-row">
        <div class="gantt-label-cell" style="font-weight:700;font-size:.8125rem"><?= e($project['name']) ?></div>
        <div class="gantt-tl-cell" style="position:relative;height:36px;width:<?= $timelineW ?>px">
            <?php foreach ($weekMarkers as $wm): ?><div class="gantt-vline" style="left:<?= round($wm['x']) ?>px"></div><?php endforeach ?>
            <?php if ($todayX !== null): ?><div class="gantt-today" style="left:<?= round($todayX) ?>px"></div><?php endif ?>
            <a href="<?= BASE_URL ?>/modules/projects/view.php?id=<?= $filterProject ?>"
               style="position:absolute;top:8px;left:<?= round($pl) ?>px;width:<?= round($pw) ?>px;height:20px;
                      background:var(--accent);border-radius:4px;display:flex;align-items:center;
                      padding:0 8px;font-size:.7rem;color:#fff;font-weight:600;overflow:hidden;
                      text-overflow:ellipsis;white-space:nowrap;text-decoration:none">
                <?= e($project['name']) ?>
            </a>
        </div>
    </div>
    <?php endif ?>

    <?php
    // Milestone groups
    $milestoneIndex = [];
    foreach ($milestones as $ms) $milestoneIndex[$ms['id']] = $ms;

    foreach ($grouped as $key => $groupTasks):
        $isMilestone = str_starts_with($key, 'ms_');
        $msData      = $isMilestone ? ($milestoneIndex[(int)substr($key,3)] ?? null) : null;
    ?>

    <?php if ($isMilestone && $msData): ?>
    <!-- Milestone header row -->
    <div class="gantt-row" style="background:var(--bg)">
        <div class="gantt-label-cell" style="font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em">
            📍 <?= e($msData['title']) ?>
        </div>
        <div class="gantt-tl-cell" style="position:relative;height:32px;width:<?= $timelineW ?>px">
            <?php foreach ($weekMarkers as $wm): ?><div class="gantt-vline" style="left:<?= round($wm['x']) ?>px"></div><?php endforeach ?>
            <?php if ($todayX !== null): ?><div class="gantt-today" style="left:<?= round($todayX) ?>px"></div><?php endif ?>
            <?php if ($msData['due_date']):
                $mx = ($ms = strtotime($msData['due_date']) - $minTs) / 86400 * $pxPerDay; ?>
            <div style="position:absolute;top:0;bottom:0;left:<?= round($mx) ?>px;width:2px;background:var(--warning);opacity:.6"></div>
            <div style="position:absolute;top:8px;left:<?= round($mx)+4 ?>px;font-size:.65rem;color:var(--warning);white-space:nowrap;font-weight:600">
                <?= date('M j', strtotime($msData['due_date'])) ?>
            </div>
            <?php endif ?>
        </div>
    </div>
    <?php endif ?>

    <?php foreach ($groupTasks as $t):
        $c       = $statusColors[$t['status']] ?? $statusColors['todo'];
        $startTs = $t['bar_start'] ? strtotime($t['bar_start']) : $minTs;
        $endTs   = $t['bar_end']   ? strtotime($t['bar_end'])   : $startTs + 86400;
        $barL    = max(0, ($startTs - $minTs) / 86400 * $pxPerDay);
        $barW    = max(6, ($endTs   - $startTs) / 86400 * $pxPerDay);
        $barL    = min($barL, $timelineW - 6);
        $barW    = min($barW, $timelineW - $barL);
        $isOver  = $t['bar_end'] < date('Y-m-d') && $t['status'] !== 'done';
    ?>
    <div class="gantt-row">
        <div class="gantt-label-cell">
            <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>"
               style="color:var(--text);font-size:.8125rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block"
               title="<?= e($t['title']) ?>"><?= e($t['title']) ?></a>
        </div>
        <div class="gantt-tl-cell" style="position:relative;height:40px;width:<?= $timelineW ?>px">
            <?php foreach ($weekMarkers as $wm): ?><div class="gantt-vline" style="left:<?= round($wm['x']) ?>px"></div><?php endforeach ?>
            <?php if ($todayX !== null): ?><div class="gantt-today" style="left:<?= round($todayX) ?>px"></div><?php endif ?>
            <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>"
               style="position:absolute;top:8px;
                      left:<?= round($barL) ?>px;
                      width:<?= round($barW) ?>px;
                      height:24px;
                      background:<?= $isOver ? '#FEE2E2' : $c['bg'] ?>;
                      color:<?= $isOver ? '#991B1B' : $c['color'] ?>;
                      border-radius:4px;display:flex;align-items:center;
                      padding:0 6px;font-size:.7rem;font-weight:500;
                      overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
                      text-decoration:none;border:1px solid <?= $isOver ? '#FECACA' : 'transparent' ?>"
               title="<?= e($t['title']) ?> · <?= $t['bar_start'] ?> → <?= $t['bar_end'] ?>">
                <?= $barW > 40 ? e($t['title']) : '' ?>
            </a>
        </div>
    </div>
    <?php endforeach ?>
    <?php endforeach ?>

</div>
</div>
</div>

<style>
.gantt-outer { overflow-x: auto; }
.gantt-row {
  display: flex; border-bottom: 1px solid var(--border);
  align-items: stretch;
}
.gantt-header-row { background: var(--bg); }
.gantt-label-cell {
  width: 200px; min-width: 200px; flex-shrink: 0;
  padding: 8px 12px; display: flex; align-items: center;
  border-right: 2px solid var(--border);
  position: sticky; left: 0; z-index: 3;
  background: inherit;
}
.gantt-header-row .gantt-label-cell { background: var(--bg); }
.gantt-tl-cell { flex-shrink: 0; }
.gantt-vline {
  position: absolute; top: 0; bottom: 0;
  width: 1px; background: var(--border);
}
.gantt-today {
  position: absolute; top: 0; bottom: 0;
  width: 2px; background: var(--accent); z-index: 2;
}
</style>
<?php endif ?>

<?php layoutEnd(); ?>
