<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();

// Mark read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $uid = (int)$_POST['update_id'];
    try { dbInsert('update_reads', ['update_id' => $uid, 'user_id' => $user['id'], 'read_at' => date('Y-m-d H:i:s')]); } catch (\Exception $e) {}
    echo json_encode(['ok' => true]); exit;
}
// Pin/unpin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_pin']) && $user['role'] === 'admin') {
    $uid = (int)$_POST['update_id'];
    $u = dbFetch("SELECT is_pinned FROM updates WHERE id=?", [$uid]);
    if ($u) dbUpdate('updates', ['is_pinned' => $u['is_pinned'] ? 0 : 1], ['id' => $uid]);
    redirect(BASE_URL . '/modules/updates/index.php');
}
// Add general update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_update'])) {
    $msg   = trim($_POST['message'] ?? '');
    $projId = (int)($_POST['project_id'] ?? 0) ?: null;
    if ($msg) {
        dbInsert('updates', ['project_id' => $projId, 'user_id' => $user['id'], 'type' => 'general', 'message' => $msg]);
        flash('success', 'Update posted.');
    }
    redirect(BASE_URL . '/modules/updates/index.php');
}

// Auto-mark all unread (by others) as read on page visit
dbQuery(
    "INSERT IGNORE INTO update_reads (update_id, user_id, read_at)
     SELECT id, ?, NOW() FROM updates WHERE user_id != ?",
    [$user['id'], $user['id']]
);

$filterType    = $_GET['type'] ?? '';
$filterProject = (int)($_GET['project_id'] ?? 0);

$where  = ['1=1'];
$params = [];
if ($filterType)    { $where[] = 'u.type=?'; $params[] = $filterType; }
if ($filterProject) { $where[] = 'u.project_id=?'; $params[] = $filterProject; }

$updates = dbFetchAll(
    "SELECT u.*, usr.full_name, p.name as project_name,
     (SELECT id FROM update_reads WHERE update_id=u.id AND user_id=?) as is_read
     FROM updates u
     JOIN users usr ON usr.id=u.user_id
     LEFT JOIN projects p ON p.id=u.project_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY u.is_pinned DESC, u.created_at DESC",
    array_merge([$user['id']], $params)
);

$projects = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name FROM projects ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? ORDER BY p.name", [$user['id']]);

layoutStart('Updates', 'updates');
$typeIcons = ['task' => '✅', 'meeting' => '📅', 'project' => '📁', 'general' => '💬'];
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Updates</h1>
        <p class="page-subtitle">Activity feed</p>
    </div>
    <button onclick="openModal('postUpdateModal')" class="btn btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Post Update
    </button>
</div>

<!-- Filters -->
<div class="tabs" style="margin-bottom:20px">
    <a href="?" class="tab <?= !$filterType ? 'active' : '' ?>">All</a>
    <?php foreach (['task','meeting','project','general'] as $t): ?>
    <a href="?type=<?= $t ?><?= $filterProject ? '&project_id='.$filterProject : '' ?>" class="tab <?= $filterType === $t ? 'active' : '' ?>"><?= $typeIcons[$t] ?> <?= ucfirst($t) ?></a>
    <?php endforeach ?>
</div>

<div class="card" id="updatesFeed">
<?php if ($updates): foreach ($updates as $upd):
    $unread = !$upd['is_read'] && $upd['user_id'] !== $user['id'];
?>
<div class="update-item <?= $unread ? 'unread' : '' ?> <?= $upd['is_pinned'] ? 'pinned' : '' ?>" id="update-<?= $upd['id'] ?>">
    <div class="update-icon"><?= $typeIcons[$upd['type']] ?? '•' ?></div>
    <div class="update-content">
        <?php if ($upd['is_pinned']): ?><span style="font-size:.7rem;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.04em">📌 Pinned &nbsp;</span><?php endif ?>
        <div class="update-message"><?= nl2br(e($upd['message'])) ?></div>
        <div class="update-meta">
            <strong><?= e($upd['full_name']) ?></strong>
            <?php if ($upd['project_name']): ?> · <?= e($upd['project_name']) ?><?php endif ?>
            · <?= date('M j, g:i A', strtotime($upd['created_at'])) ?>
        </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-end;flex-shrink:0">
        <?php if ($unread): ?>
        <button onclick="markRead(<?= $upd['id'] ?>)" class="btn btn-ghost btn-sm" style="font-size:.75rem">Mark read</button>
        <?php endif ?>
        <?php if ($user['role'] === 'admin'): ?>
        <form method="POST" style="margin:0">
            <input type="hidden" name="toggle_pin" value="1">
            <input type="hidden" name="update_id" value="<?= $upd['id'] ?>">
            <button class="btn btn-ghost btn-sm btn-icon" title="<?= $upd['is_pinned'] ? 'Unpin' : 'Pin' ?>">📌</button>
        </form>
        <?php endif ?>
    </div>
</div>
<?php endforeach; else: ?>
<div class="empty-state"><p>No updates yet.</p></div>
<?php endif ?>
</div>

<!-- Post Update Modal -->
<div class="modal-overlay" id="postUpdateModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Post Update</span>
            <button class="modal-close" onclick="closeModal('postUpdateModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="post_update" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Project (optional)</label>
                    <select name="project_id" class="form-control">
                        <option value="">— General —</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="What's happening?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('postUpdateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Post</button>
            </div>
        </form>
    </div>
</div>

<script>
function markRead(id) {
    fetch('', {method:'POST',body:new URLSearchParams({mark_read:1,update_id:id})})
        .then(() => {
            const el = document.getElementById('update-'+id);
            if (el) {
                el.classList.remove('unread');
                el.querySelector('[onclick*="markRead"]')?.remove();
            }
        });
}
</script>
<?php layoutEnd(); ?>
