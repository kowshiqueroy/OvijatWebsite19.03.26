<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../flash.php';
require_once __DIR__ . '/../../includes/layout.php';

requireRole('member');
$user = currentUser();
$preProjectId = (int)($_GET['project_id'] ?? 0);

$projects = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name FROM projects WHERE status != 'archived' ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? AND p.status != 'archived' ORDER BY p.name", [$user['id']]);
$allUsers = dbFetchAll("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projId    = (int)($_POST['project_id'] ?? 0) ?: null;
    $title     = trim($_POST['title'] ?? '');
    $agenda    = trim($_POST['agenda'] ?? '');
    $date      = $_POST['meeting_date'] ?? '';
    $duration  = (int)($_POST['duration_minutes'] ?? 0) ?: null;
    $location  = trim($_POST['location_or_link'] ?? '');
    $status    = $_POST['status'] ?? 'scheduled';
    $recur     = $_POST['recurrence'] ?? 'none';
    $notes     = trim($_POST['notes'] ?? '');
    $attendees = array_map('intval', $_POST['attendees'] ?? []);

    if (!$title || !$date) { flash('error', 'Title and date are required.'); redirect(BASE_URL . '/modules/meetings/create.php'); }

    $mid = dbInsert('meetings', [
        'project_id' => $projId, 'title' => $title, 'agenda' => $agenda ?: null,
        'meeting_date' => $date, 'duration_minutes' => $duration,
        'location_or_link' => $location ?: null, 'status' => $status,
        'recurrence' => $recur, 'notes' => $notes ?: null, 'created_by' => $user['id'],
    ]);
    foreach ($attendees as $uid) {
        try { dbInsert('meeting_attendees', ['meeting_id' => $mid, 'user_id' => $uid, 'rsvp' => 'pending']); } catch (\Exception $e) {}
    }
    if ($projId) dbInsert('updates', ['project_id' => $projId, 'user_id' => $user['id'], 'type' => 'meeting', 'message' => "Scheduled meeting \"{$title}\"."]);
    flash('success', 'Meeting created.');
    redirect(BASE_URL . '/modules/meetings/view.php?id='.$mid);
}

layoutStart('New Meeting', 'meetings');
?>
<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/modules/meetings/index.php" style="font-size:.875rem;color:var(--text-muted)">&larr; Meetings</a>
        <h1 class="page-title" style="margin-top:4px">New Meeting</h1>
    </div>
</div>

<div class="card" style="max-width:680px">
<div class="card-body">
<form method="POST">
    <div class="form-group">
        <label class="form-label">Title *</label>
        <input name="title" class="form-control" value="<?= e($_POST['title'] ?? '') ?>" required autofocus>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Project (optional)</label>
            <select name="project_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ((int)($_POST['project_id'] ?? $preProjectId)) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php foreach (['scheduled','done','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['status'] ?? 'scheduled') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label">Date & Time *</label>
            <input name="meeting_date" type="datetime-local" class="form-control" value="<?= e($_POST['meeting_date'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Duration (minutes)</label>
            <input name="duration_minutes" type="number" min="5" class="form-control" value="<?= e($_POST['duration_minutes'] ?? '') ?>" placeholder="60">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Location / Link</label>
        <input name="location_or_link" class="form-control" value="<?= e($_POST['location_or_link'] ?? '') ?>" placeholder="Zoom link, room name, etc.">
    </div>
    <div class="form-group">
        <label class="form-label">Recurrence</label>
        <select name="recurrence" class="form-control">
            <?php foreach (['none','daily','weekly','biweekly','monthly'] as $r): ?>
            <option value="<?= $r ?>" <?= ($_POST['recurrence'] ?? 'none') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Agenda</label>
        <textarea name="agenda" class="form-control" rows="3"><?= e($_POST['agenda'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3"><?= e($_POST['notes'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label">Attendees</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;padding:10px;border:1px solid var(--border);border-radius:6px">
        <?php foreach ($allUsers as $u): ?>
        <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;cursor:pointer;padding:4px 8px;border-radius:4px;background:var(--bg)">
            <input type="checkbox" name="attendees[]" value="<?= $u['id'] ?>" <?= in_array($u['id'], $_POST['attendees'] ?? [$user['id']]) ? 'checked' : '' ?>>
            <?= e($u['full_name']) ?>
        </label>
        <?php endforeach ?>
        </div>
    </div>
    <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">Create Meeting</button>
        <a href="<?= BASE_URL ?>/modules/meetings/index.php" class="btn btn-secondary">Cancel</a>
    </div>
</form>
</div>
</div>
<?php layoutEnd(); ?>
