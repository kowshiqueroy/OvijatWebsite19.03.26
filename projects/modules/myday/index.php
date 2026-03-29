<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();
$today = date('Y-m-d');

// ── Urgent tasks: due today, overdue, or in_progress ──────────
$urgentTasks = dbFetchAll(
    "SELECT DISTINCT t.id, t.title, t.priority, t.status, t.due_date, p.name as project_name
     FROM tasks t
     JOIN projects p ON p.id = t.project_id
     LEFT JOIN task_assignees ta ON ta.task_id = t.id
     WHERE (t.created_by = ? OR ta.user_id = ?)
       AND t.status != 'done'
       AND (t.status = 'in_progress' OR t.due_date <= ?)
     ORDER BY FIELD(t.priority,'high','medium','low'), t.due_date ASC
     LIMIT 20",
    [$user['id'], $user['id'], $today]
);

// ── Today's meetings ──────────────────────────────────────────
$todayMeetings = dbFetchAll(
    "SELECT m.id, m.title, m.meeting_date, m.location_or_link, p.name as project_name,
            ma.rsvp as my_rsvp
     FROM meetings m
     JOIN projects p ON p.id = m.project_id
     JOIN meeting_attendees ma ON ma.meeting_id = m.id AND ma.user_id = ?
     WHERE DATE(m.meeting_date) = ?
     ORDER BY m.meeting_date ASC",
    [$user['id'], $today]
);

// ── Unread notifications ──────────────────────────────────────
$notifications = dbFetchAll(
    "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 20",
    [$user['id']]
);
$notifCount = count($notifications);

// ── Recent @mentions in chat ──────────────────────────────────
$username = $user['username'] ?? strtolower(str_replace(' ', '_', $user['full_name']));
$mentions = dbFetchAll(
    "SELECT wc.id, wc.body, wc.created_at, u.full_name, p.name as project_name
     FROM worksheet_chat wc
     JOIN users u ON u.id = wc.user_id
     JOIN projects p ON p.id = wc.project_id
     WHERE (wc.body LIKE ? OR wc.body LIKE ?)
       AND wc.project_id IN (
           SELECT project_id FROM project_members WHERE user_id = ?
           UNION SELECT id FROM projects WHERE status != 'archived'
       )
     ORDER BY wc.created_at DESC LIMIT 10",
    ['%@'.$username.'%', '%@'.str_replace(' ', '_', $user['full_name']).'%', $user['id']]
);

layoutStart('My Day', 'myday');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Day</h1>
        <p class="page-subtitle"><?= date('l, F j, Y') ?></p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="myday-grid">

<!-- ── Urgent Tasks ──────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Focus Tasks</span>
        <span class="badge" style="background:var(--danger);color:#fff"><?= count($urgentTasks) ?></span>
    </div>
    <?php if ($urgentTasks): ?>
    <div style="padding:0">
    <?php foreach ($urgentTasks as $t):
        $overdue  = $t['due_date'] && $t['due_date'] < $today;
        $dueToday = $t['due_date'] === $today;
        $daysDiff = $t['due_date'] ? (int)ceil((strtotime($t['due_date']) - time()) / 86400) : null;
    ?>
    <a href="<?= BASE_URL ?>/modules/tasks/view.php?id=<?= $t['id'] ?>"
       style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);color:var(--text);text-decoration:none">
        <span class="badge badge-<?= $t['priority'] ?>" style="flex-shrink:0"><?= ucfirst($t['priority']) ?></span>
        <div style="flex:1;min-width:0">
            <div style="font-size:.875rem;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($t['title']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($t['project_name']) ?> · <span class="badge badge-<?= $t['status'] ?>" style="font-size:.65rem"><?= str_replace('_',' ',$t['status']) ?></span></div>
        </div>
        <span style="font-size:.75rem;font-weight:600;white-space:nowrap;<?= $overdue?'color:var(--danger)':($dueToday?'color:var(--warning)':'color:var(--text-muted)') ?>">
            <?php if ($overdue): echo abs($daysDiff).'d overdue';
            elseif ($dueToday): echo 'Due today';
            elseif ($daysDiff !== null): echo 'Due in '.$daysDiff.'d';
            else: echo 'In Progress'; endif ?>
        </span>
    </a>
    <?php endforeach ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:28px">
        <p style="color:var(--success)">✓ Nothing urgent today!</p>
    </div>
    <?php endif ?>
</div>

<!-- ── Today's Meetings ──────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Today's Meetings</span>
        <span class="badge" style="background:var(--accent);color:#fff"><?= count($todayMeetings) ?></span>
    </div>
    <?php if ($todayMeetings): ?>
    <div style="padding:0">
    <?php foreach ($todayMeetings as $m):
        $time = date('g:i A', strtotime($m['meeting_date']));
        $rsvp = $m['my_rsvp'] ?? null;
        $rsvpColors = ['confirmed'=>'var(--success)','declined'=>'var(--danger)','pending'=>'var(--warning)','yes'=>'var(--success)','no'=>'var(--danger)','maybe'=>'var(--warning)'];
    ?>
    <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border)">
        <div style="text-align:center;min-width:44px;padding:6px 4px;background:var(--accent);border-radius:8px;color:#fff">
            <div style="font-size:.6rem;text-transform:uppercase">Today</div>
            <div style="font-size:.875rem;font-weight:700"><?= date('g:i', strtotime($m['meeting_date'])) ?></div>
            <div style="font-size:.55rem"><?= date('A', strtotime($m['meeting_date'])) ?></div>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:.875rem;font-weight:500"><?= e($m['title']) ?></div>
            <div style="font-size:.75rem;color:var(--text-muted)"><?= e($m['project_name']) ?><?= $m['location_or_link'] ? ' · '.e($m['location_or_link']) : '' ?></div>
            <?php if ($rsvp): ?>
            <span style="font-size:.7rem;font-weight:600;color:<?= $rsvpColors[$rsvp] ?? 'var(--text-muted)' ?>">RSVP: <?= ucfirst($rsvp) ?></span>
            <?php endif ?>
        </div>
        <a href="<?= BASE_URL ?>/modules/meetings/view.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm" style="font-size:.72rem;flex-shrink:0">View</a>
    </div>
    <?php endforeach ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:28px"><p>No meetings today.</p></div>
    <?php endif ?>
</div>

<!-- ── Unread Notifications ──────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Notifications</span>
        <?php if ($notifCount): ?>
        <form method="POST" action="<?= BASE_URL ?>/notifications.php" style="margin:0">
            <input type="hidden" name="action" value="mark_read">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="font-size:.75rem">Mark all read</button>
        </form>
        <?php endif ?>
    </div>
    <?php if ($notifications): ?>
    <div style="padding:0">
    <?php foreach ($notifications as $n):
        $typeIcon = ['mention'=>'@','task_assigned'=>'✅','comment'=>'💬','meeting'=>'📅'][$n['type']] ?? '•';
    ?>
    <div style="display:flex;gap:10px;align-items:flex-start;padding:10px 16px;border-bottom:1px solid var(--border)">
        <span style="font-size:1rem;flex-shrink:0;margin-top:2px"><?= $typeIcon ?></span>
        <div style="flex:1">
            <div style="font-size:.8125rem"><?= e($n['message']) ?></div>
            <div style="font-size:.7rem;color:var(--text-muted)"><?= date('M j g:i A', strtotime($n['created_at'])) ?></div>
        </div>
        <?php if ($n['link']): ?>
        <a href="<?= e($n['link']) ?>" class="btn btn-ghost btn-sm" style="font-size:.72rem;flex-shrink:0">View</a>
        <?php endif ?>
    </div>
    <?php endforeach ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:28px"><p>No unread notifications.</p></div>
    <?php endif ?>
</div>

<!-- ── Recent @Mentions ──────────────────────────────────── -->
<div class="card">
    <div class="card-header"><span class="card-title">Recent @Mentions</span></div>
    <?php if ($mentions): ?>
    <div style="padding:0">
    <?php foreach ($mentions as $m): ?>
    <div style="display:flex;gap:10px;align-items:flex-start;padding:10px 16px;border-bottom:1px solid var(--border)">
        <span class="avatar avatar-sm" style="background:<?= avatarColor($m['full_name']) ?>;flex-shrink:0"><?= userInitials($m['full_name']) ?></span>
        <div style="flex:1;min-width:0">
            <div style="display:flex;gap:6px;align-items:center;margin-bottom:2px">
                <strong style="font-size:.8125rem;color:<?= avatarColor($m['full_name']) ?>"><?= e($m['full_name']) ?></strong>
                <span style="font-size:.7rem;color:var(--text-muted)"><?= e($m['project_name']) ?></span>
                <span style="font-size:.7rem;color:var(--text-muted);margin-left:auto"><?= date('M j g:i A', strtotime($m['created_at'])) ?></span>
            </div>
            <div style="font-size:.8125rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text)"><?= e($m['body']) ?></div>
        </div>
    </div>
    <?php endforeach ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:28px"><p>No recent mentions.</p></div>
    <?php endif ?>
</div>

</div><!-- /myday-grid -->

<style>
@media (max-width: 767px) { .myday-grid { grid-template-columns: 1fr !important; } }
</style>

<?php layoutEnd(); ?>
