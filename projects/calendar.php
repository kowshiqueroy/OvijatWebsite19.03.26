<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/includes/layout.php';

requireRole('member');
$user = currentUser();

$today = date('Y-m-d');
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));

// Clamp
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$firstTs     = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstTs);
$startDow    = (int)date('w', $firstTs);   // 0 = Sunday
$monthStart  = date('Y-m-d', $firstTs);
$monthEnd    = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$prevUrl = $month === 1
    ? '?year='.($year-1).'&month=12'
    : '?year='.$year.'&month='.($month-1);
$nextUrl = $month === 12
    ? '?year='.($year+1).'&month=1'
    : '?year='.$year.'&month='.($month+1);

// Tasks due this month
if ($user['role'] === 'admin') {
    $tasks = dbFetchAll(
        "SELECT t.id, t.title, t.status, t.priority, t.due_date, p.name as project_name
         FROM tasks t JOIN projects p ON p.id=t.project_id
         WHERE t.due_date BETWEEN ? AND ?
         ORDER BY FIELD(t.priority,'high','medium','low'), t.title",
        [$monthStart, $monthEnd]
    );
} else {
    $tasks = dbFetchAll(
        "SELECT DISTINCT t.id, t.title, t.status, t.priority, t.due_date, p.name as project_name
         FROM tasks t JOIN projects p ON p.id=t.project_id
         LEFT JOIN task_assignees ta ON ta.task_id=t.id
         WHERE t.due_date BETWEEN ? AND ?
           AND (t.created_by=? OR ta.user_id=?)
         ORDER BY FIELD(t.priority,'high','medium','low'), t.title",
        [$monthStart, $monthEnd, $user['id'], $user['id']]
    );
}

// Meetings this month
if ($user['role'] === 'admin') {
    $meetings = dbFetchAll(
        "SELECT m.id, m.title, m.status, DATE(m.meeting_date) as day,
                DATE_FORMAT(m.meeting_date,'%H:%i') as mtime, p.name as project_name
         FROM meetings m JOIN projects p ON p.id=m.project_id
         WHERE DATE(m.meeting_date) BETWEEN ? AND ?
         ORDER BY m.meeting_date",
        [$monthStart, $monthEnd]
    );
} else {
    $meetings = dbFetchAll(
        "SELECT DISTINCT m.id, m.title, m.status, DATE(m.meeting_date) as day,
                DATE_FORMAT(m.meeting_date,'%H:%i') as mtime, p.name as project_name
         FROM meetings m JOIN projects p ON p.id=m.project_id
         JOIN project_members pm ON pm.project_id=m.project_id
         WHERE pm.user_id=? AND DATE(m.meeting_date) BETWEEN ? AND ?
         ORDER BY m.meeting_date",
        [$user['id'], $monthStart, $monthEnd]
    );
}

// Group by date
$tasksByDay    = [];
$meetingsByDay = [];
foreach ($tasks   as $t) $tasksByDay[$t['due_date']][] = $t;
foreach ($meetings as $m) $meetingsByDay[$m['day']][]   = $m;

// Total cells (always full weeks)
$totalCells = (int)ceil(($startDow + $daysInMonth) / 7) * 7;

layoutStart('Calendar', 'calendar');
?>
<div class="page-header">
    <div style="display:flex;align-items:center;gap:12px">
        <a href="<?= $prevUrl ?>" class="btn btn-secondary btn-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <h1 class="page-title" style="min-width:180px;text-align:center"><?= date('F Y', $firstTs) ?></h1>
        <a href="<?= $nextUrl ?>" class="btn btn-secondary btn-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
        <?php if ("$year-$month" !== date('Y-n')): ?>
        <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn btn-ghost btn-sm">Today</a>
        <?php endif ?>
    </div>
    <div style="display:flex;align-items:center;gap:12px;font-size:.8125rem;color:var(--text-muted)">
        <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:3px;background:#EDE9FE;display:inline-block"></span> Task</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:3px;background:#DBEAFE;display:inline-block"></span> Meeting</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:10px;height:10px;border-radius:3px;background:#FEE2E2;display:inline-block"></span> Overdue</span>
    </div>
</div>

<div class="card" style="overflow:hidden">
<div class="cal-grid">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
    <div class="cal-dow"><?= $dow ?></div>
    <?php endforeach ?>

    <?php for ($i = 0; $i < $totalCells; $i++):
        $offset = $i - $startDow;
        $dayNum = $offset + 1;

        if ($offset < 0) {
            // Previous month filler
            $fillerDay = date('j', mktime(0,0,0,$month,0,$year)) + $offset + 1;
            echo '<div class="cal-day other-month"><div class="cal-day-num"><span>'.$fillerDay.'</span></div></div>';
            continue;
        }
        if ($dayNum > $daysInMonth) {
            // Next month filler
            echo '<div class="cal-day other-month"><div class="cal-day-num"><span>'.($dayNum - $daysInMonth).'</span></div></div>';
            continue;
        }

        $date      = sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
        $isToday   = $date === $today;
        $dayTasks  = $tasksByDay[$date]    ?? [];
        $dayMeets  = $meetingsByDay[$date] ?? [];
        $hasItems  = !empty($dayTasks) || !empty($dayMeets);
        $allItems  = array_merge(
            array_map(fn($t) => ['type' => 'task',    'data' => $t], $dayTasks),
            array_map(fn($m) => ['type' => 'meeting', 'data' => $m], $dayMeets)
        );
        $visible   = array_slice($allItems, 0, 3);
        $overflow  = count($allItems) - count($visible);
    ?>
    <div class="cal-day <?= $isToday ? 'today' : '' ?> <?= $hasItems ? 'has-items' : '' ?>">
        <div class="cal-day-num"><span><?= $dayNum ?></span></div>
        <?php foreach ($visible as $item):
            if ($item['type'] === 'task'):
                $t   = $item['data'];
                $cls = $t['status'] === 'done' ? 'done' : ($t['due_date'] < $today && $t['status'] !== 'done' ? 'overdue' : '');
        ?>
        <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>" class="cal-item cal-item-task <?= $cls ?>"
           title="<?= e($t['title']) ?> · <?= e($t['project_name']) ?>">
            <?= e($t['title']) ?>
        </a>
        <?php else:
            $m = $item['data'];
        ?>
        <a href="<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>" class="cal-item cal-item-meeting"
           title="<?= e($m['mtime']) ?> · <?= e($m['title']) ?>">
            <?= $m['mtime'] ?> <?= e($m['title']) ?>
        </a>
        <?php endif; endforeach ?>
        <?php if ($overflow > 0): ?>
        <span class="cal-more">+<?= $overflow ?> more</span>
        <?php endif ?>
    </div>
    <?php endfor ?>
</div>
</div>

<style>
.cal-grid {
  display: grid; grid-template-columns: repeat(7, 1fr);
  border-left: 1px solid var(--border); border-top: 1px solid var(--border);
}
.cal-dow {
  padding: 8px 10px; font-size: .7rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted);
  border-right: 1px solid var(--border); border-bottom: 1px solid var(--border);
  background: var(--bg); text-align: center;
}
.cal-day {
  min-height: 110px; padding: 6px 8px;
  border-right: 1px solid var(--border); border-bottom: 1px solid var(--border);
  vertical-align: top;
}
.cal-day.other-month { background: var(--bg); }
.cal-day.other-month .cal-day-num span { color: #CBD5E1; }
.cal-day.today { background: rgba(79,107,237,.04); }
.cal-day-num {
  font-size: .8125rem; font-weight: 600; color: var(--text-muted);
  margin-bottom: 4px; display: flex; justify-content: flex-end;
}
.cal-day.today .cal-day-num span {
  background: var(--accent); color: #fff;
  width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: .75rem; font-weight: 700;
}
.cal-item {
  display: block; font-size: .7rem; padding: 2px 6px; border-radius: 4px;
  margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  text-decoration: none; transition: opacity .15s;
}
.cal-item:hover { opacity: .8; }
.cal-item-task         { background: #EDE9FE; color: #5B21B6; }
.cal-item-task.overdue { background: #FEE2E2; color: #991B1B; }
.cal-item-task.done    { background: #D1FAE5; color: #065F46; }
.cal-item-meeting      { background: #DBEAFE; color: #1E40AF; }
.cal-more { font-size: .7rem; color: var(--text-muted); padding: 2px 6px; display: block; }

@media (max-width: 767px) {
  .cal-day { min-height: 52px; padding: 4px 4px 2px; }
  .cal-item { display: none; }
  .cal-day.has-items .cal-day-num::before {
    content: '•'; color: var(--accent); font-size: 1rem; line-height: 1;
    margin-right: 2px; align-self: center;
  }
  .cal-more { display: none; }
  .cal-dow { padding: 6px 4px; font-size: .65rem; }
}
</style>
<?php layoutEnd(); ?>
