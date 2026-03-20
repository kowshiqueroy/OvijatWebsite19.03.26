<?php
require_once 'config.php';
requireLogin();

$aid = agentId();
$pageTitle = 'Notifications';
$activePage = 'notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $nid = (int)($_POST['mark_read']);
    $conn->query("UPDATE notifications SET is_read=1 WHERE id=$nid AND agent_id=$aid");
    header('Location: notifications.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all'])) {
    $conn->query("UPDATE notifications SET is_read=1 WHERE agent_id=$aid");
    header('Location: notifications.php');
    exit;
}

$all = $conn->query(
    "SELECT n.*, a.full_name AS from_name, a.username AS from_username
     FROM notifications n
     LEFT JOIN agents a ON a.id = n.from_agent
     WHERE n.agent_id = $aid
     ORDER BY n.created_at DESC
     LIMIT 100"
);

$unread = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE agent_id=$aid AND is_read=0")->fetch_assoc()['c'];

require_once 'includes/layout.php';

$typeLabels = [
    'task_assigned' => ['fa-list-check', 'Task Assigned', 'primary'],
    'followup_due'  => ['fa-calendar-exclamation', 'Follow-up Due', 'warning'],
    'note_reply'    => ['fa-comment', 'Note Reply', 'info'],
    'missed_calls'  => ['fa-phone-slash', 'Missed Call', 'danger'],
    'fetch_done'    => ['fa-cloud-arrow-down', 'Fetch Done', 'success'],
    'system'        => ['fa-bell', 'System', 'secondary'],
];
?>
<style>
.notif-page-item {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .75rem 1rem;
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}
.notif-page-item:hover { background: rgba(255,255,255,.03); }
.notif-page-item:last-child { border-bottom: none; }
.notif-page-item.unread { background: rgba(99,102,241,.06); }
.notif-page-item.unread:hover { background: rgba(99,102,241,.10); }
.notif-icon-lg {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: .9rem;
}
.notif-icon-task  { background: rgba(99,102,241,.15); color: #818cf8; }
.notif-icon-note  { background: rgba(59,130,246,.15); color: #3b82f6; }
.notif-icon-miss  { background: rgba(239,68,68,.15); color: #ef4444; }
.notif-icon-fetch { background: rgba(16,185,129,.15); color: #10b981; }
.notif-icon-sys   { background: rgba(107,114,128,.15); color: #6b7280; }
.notif-icon-followup { background: rgba(234,179,8,.15); color: #eab308; }

.notif-body { flex: 1; min-width: 0; }
.notif-title-lg { font-size: .9rem; color: var(--text); }
.notif-msg-lg { font-size: .8rem; color: var(--muted); margin-top: 2px; }
.notif-meta-lg { font-size: .72rem; color: var(--muted); margin-top: 4px; }
.notif-link { text-decoration: none; }
.notif-link:hover .notif-title-lg { color: var(--accent); }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h5>
        <?php if ($unread): ?><span class="badge bg-primary"><?= $unread ?> unread</span><?php endif; ?>
    </div>
    <?php if ($unread): ?>
    <form method="POST" class="d-inline">
        <button type="submit" name="mark_all" value="1" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-check-double me-1"></i>Mark all read
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="cc-card">
    <?php if (!$all || !$all->num_rows): ?>
    <div class="cc-card-body">
        <div class="empty-state py-5">
            <i class="fas fa-bell-slash"></i><br>No notifications
        </div>
    </div>
    <?php else: ?>
    <?php while ($n = $all->fetch_assoc()):
        [$icon, $label, $color] = $typeLabels[$n['type']] ?? ['fa-bell', 'Notification', 'secondary'];
        $iconClass = ['primary'=>'notif-icon-task','info'=>'notif-icon-note','danger'=>'notif-icon-miss',
                       'success'=>'notif-icon-fetch','secondary'=>'notif-icon-sys','warning'=>'notif-icon-followup'][$color] ?? 'notif-icon-sys';
        $link = e($n['link'] ?? '#');
    ?>
    <div class="notif-page-item <?= $n['is_read'] ? '' : 'unread' ?>">
        <div class="notif-icon-lg <?= $iconClass ?>">
            <i class="fas <?= $icon ?>"></i>
        </div>
        <div class="notif-body">
            <?php if ($link !== '#' && $link !== ''): ?>
            <a href="<?= $link ?>" class="notif-link">
                <div class="notif-title-lg"><?= e($n['title']) ?></div>
            </a>
            <?php else: ?>
            <div class="notif-title-lg"><?= e($n['title']) ?></div>
            <?php endif; ?>
            <?php if ($n['message']): ?>
            <div class="notif-msg-lg"><?= nl2br(e($n['message'])) ?></div>
            <?php endif; ?>
            <div class="notif-meta-lg">
                <?php if ($n['from_name']): ?>
                <i class="fas fa-user me-1"></i><?= e($n['from_name']) ?>
                <?php else: ?>
                <i class="fas fa-robot me-1"></i>System
                <?php endif; ?>
                &bull;
                <i class="fas fa-clock me-1"></i><?= timeAgo($n['created_at']) ?>
                <span class="ms-2 text-nowrap">
                    <?= formatDt($n['created_at'], 'd M Y, h:i A') ?>
                </span>
            </div>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <?php if (!$n['is_read']): ?>
            <form method="POST">
                <button type="submit" name="mark_read" value="<?= $n['id'] ?>"
                        class="btn btn-xs btn-outline-secondary" title="Mark as read">
                    <i class="fas fa-check"></i>
                </button>
            </form>
            <?php endif; ?>
            <span class="badge bg-<?= $color ?> badge-xs"><?= $label ?></span>
        </div>
    </div>
    <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
