<?php
require_once 'config.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$aid = agentId();

if (!$id) { header('Location: calls.php'); exit; }

// ── Fetch call ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT cl.*, c.name AS contact_name, c.company, c.scope AS contact_scope,
            c.type_id, c.group_id, c.is_favorite, c.phone AS contact_phone,
            a.full_name AS agent_name, a.username AS agent_username,
            cb.full_name AS created_by_name
     FROM call_logs cl
     LEFT JOIN contacts c ON c.id = cl.contact_id
     LEFT JOIN agents a   ON a.id = cl.agent_id
     LEFT JOIN agents cb  ON cb.id = cl.created_by
     WHERE cl.id = ? LIMIT 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$call = $stmt->get_result()->fetch_assoc();
if (!$call) { header('Location: calls.php'); exit; }

$pageTitle   = 'Call: ' . ($call['contact_name'] ?: $call['src'] ?: '#' . $id);
$pageSubtitle = formatDt($call['calldate']);
$activePage  = 'calls';

// ── Fetch threaded notes (recursive PHP build) ────────────────────────────────
function fetchNotes(mysqli $conn, int $callId, ?int $parentId = null): array {
    $pid = $parentId === null ? 'IS NULL' : "= $parentId";
    $r = $conn->query(
        "SELECT cn.*, a.full_name, a.username, a.department
         FROM call_notes cn JOIN agents a ON a.id = cn.agent_id
         WHERE cn.call_id = $callId AND cn.parent_id $pid
         ORDER BY cn.is_pinned DESC, cn.created_at ASC"
    );
    $nodes = [];
    while ($n = $r->fetch_assoc()) {
        $n['replies'] = fetchNotes($conn, $callId, $n['id']);
        $nodes[] = $n;
    }
    return $nodes;
}
$notes = fetchNotes($conn, $id);

// ── Tasks for this call ───────────────────────────────────────────────────────
$tasks = $conn->query(
    "SELECT t.*, a.full_name AS assigned_name, cb.full_name AS creator_name
     FROM todos t
     JOIN agents a  ON a.id  = t.assigned_to
     JOIN agents cb ON cb.id = t.created_by
     WHERE t.call_id = $id
     ORDER BY FIELD(t.status,'in_progress','pending','done','cancelled'),
              FIELD(t.priority,'urgent','high','medium','low')"
);

// ── Edit history for this call ────────────────────────────────────────────────
$history = $conn->query(
    "SELECT eh.*, a.full_name FROM edit_history eh JOIN agents a ON a.id = eh.edited_by
     WHERE eh.entity_type='call_logs' AND eh.entity_id=$id ORDER BY eh.edited_at DESC LIMIT 30"
);

// ── Contact call history (other calls from same contact) ──────────────────────
$contactHistory = null;
if ($call['contact_id']) {
    $cid = (int)$call['contact_id'];
    $contactHistory = $conn->query(
        "SELECT id, calldate, disposition, src, dst, call_direction, billsec, call_mark
         FROM call_logs WHERE contact_id=$cid AND id != $id
         ORDER BY calldate DESC LIMIT 10"
    );
}

// ── All agents for assign dropdown ───────────────────────────────────────────
$agents = getAllAgents();

// ── Handle mark update (inline POST) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_mark'])) {
    $mark = $_POST['call_mark'] ?? 'normal';
    $old  = $call['call_mark'];
    $conn->query("UPDATE call_logs SET call_mark='$mark', updated_by=$aid WHERE id=$id");
    logEdit('call_logs', $id, 'call_mark', $old, $mark);
    logActivity('call_marked', 'call_logs', $id, "Marked as: $mark");
    header("Location: call_detail.php?id=$id&updated=1");
    exit;
}

// ── Handle agent assignment ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_agent'])) {
    $newAgent = (int)$_POST['agent_id'];
    $old      = $call['agent_id'];
    $conn->query("UPDATE call_logs SET agent_id=$newAgent, updated_by=$aid WHERE id=$id");
    logEdit('call_logs', $id, 'agent_id', $old, $newAgent);
    logActivity('call_assigned', 'call_logs', $id, "Assigned to agent #$newAgent");
    if ($newAgent && $newAgent !== $aid) {
        $a = $conn->query("SELECT full_name FROM agents WHERE id=$newAgent")->fetch_assoc();
        notify($newAgent, 'Call Assigned', "Call from {$call['src']} assigned to you by " . currentAgent()['full_name'],
               'task_assigned', 'call_logs', $id, APP_URL . "/call_detail.php?id=$id");
    }
    header("Location: call_detail.php?id=$id&updated=1");
    exit;
}

require_once 'includes/layout.php';

// ── Recursive note renderer ───────────────────────────────────────────────────
function renderNote(array $n, int $depth = 0): void {
    $indentClass = $depth ? 'note-reply' : 'note-root';
    $typeColors  = ['note'=>'secondary','issue'=>'danger','feedback'=>'info','resolution'=>'success',
                    'query'=>'warning','followup'=>'primary','internal'=>'dark','reply'=>'secondary'];
    $color = $typeColors[$n['note_type']] ?? 'secondary';
    ?>
    <div class="note-item <?= $indentClass ?> <?= $n['is_pinned'] ? 'note-pinned' : '' ?>" id="note-<?= $n['id'] ?>">
        <div class="note-head">
            <div class="note-avatar"><i class="fas fa-user"></i></div>
            <div class="note-meta">
                <span class="note-author"><?= e($n['full_name']) ?></span>
                <span class="text-muted small"><?= e($n['department'] ?? '') ?></span>
                <span class="badge bg-<?= $color ?> note-type-badge"><?= $n['note_type'] ?></span>
                <?php if ($n['priority'] !== 'low'): ?>
                <span class="badge bg-<?= priorityClass($n['priority']) ?>"><?= $n['priority'] ?></span>
                <?php endif; ?>
                <?php if ($n['log_status'] !== 'open'): ?>
                <span class="badge bg-secondary"><?= $n['log_status'] ?></span>
                <?php endif; ?>
                <?php if ($n['is_pinned']): ?><i class="fas fa-thumbtack text-warning" title="Pinned"></i><?php endif; ?>
            </div>
            <div class="note-time ms-auto small text-muted" title="<?= e($n['created_at']) ?>">
                <?= timeAgo($n['created_at']) ?>
                <?php if ($n['edited_at']): ?>
                <em>(edited <?= timeAgo($n['edited_at']) ?> by <?= e($n['full_name']) ?>)</em>
                <?php endif; ?>
            </div>
        </div>
        <div class="note-body" id="note-body-<?= $n['id'] ?>"><?= nl2br(e($n['content'])) ?></div>
        <div class="note-actions">
            <button class="btn-link small" onclick="replyNote(<?= $n['id'] ?>, '<?= e(addslashes($n['full_name'])) ?>')">
                <i class="fas fa-reply me-1"></i>Reply
            </button>
            <button class="btn-link small" onclick="editNote(<?= $n['id'] ?>, <?= j($n['content']) ?>)">
                <i class="fas fa-pen me-1"></i>Edit
            </button>
            <button class="btn-link small" onclick="togglePinNote(<?= $n['id'] ?>, <?= $n['is_pinned'] ?>)">
                <i class="fas fa-thumbtack me-1"></i><?= $n['is_pinned'] ? 'Unpin' : 'Pin' ?>
            </button>
            <button class="btn-link small" onclick="changeNoteStatus(<?= $n['id'] ?>, '<?= $n['log_status'] ?>')">
                <i class="fas fa-circle-dot me-1"></i><?= $n['log_status'] ?>
            </button>
            <?php if (count($n['replies'])): ?>
            <span class="text-muted small ms-2"><?= count($n['replies']) ?> <?= count($n['replies'])===1?'reply':'replies' ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($n['replies'])): ?>
        <div class="note-replies">
            <?php foreach ($n['replies'] as $r) renderNote($r, $depth + 1); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<!-- ── Top action bar ─────────────────────────────────────────────────────────── -->
<div class="detail-action-bar mb-3">
    <a href="<?= APP_URL ?>/calls.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($call['recordingfile']): ?>
        <button class="btn btn-sm btn-success" onclick="playRecording(<?= $id ?>, <?= j($call['recordingfile'] ?? '') ?>)">
            <i class="fas fa-play me-1"></i>Play Recording
        </button>
        <a href="<?= APP_URL ?>/api/audio.php?id=<?= $id ?>&dl=1" class="btn btn-sm btn-outline-success">
            <i class="fas fa-download me-1"></i>Download
        </a>
        <?php endif; ?>
        <button class="btn btn-sm btn-primary" onclick="focusNote()">
            <i class="fas fa-comment-plus me-1"></i>Add Note
        </button>
        <button class="btn btn-sm btn-warning" onclick="openTaskModal()">
            <i class="fas fa-list-check me-1"></i>Add Task
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="copyCallInfo()">
            <i class="fas fa-copy me-1"></i>Copy Info
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
</div>

<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success py-2 mb-3"><i class="fas fa-check-circle me-2"></i>Updated successfully.</div>
<?php endif; ?>

<div class="row g-4">

<!-- ── Left: Call detail + notes ─────────────────────────────────────────────── -->
<div class="col-lg-8">

    <!-- Call detail card -->
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-phone-volume me-2"></i>Call Details</span>
            <span class="badge bg-<?= dispositionClass($call['disposition'] ?? '') ?>">
                <i class="fas <?= dispositionIcon($call['disposition'] ?? '') ?> me-1"></i>
                <?= e($call['disposition'] ?? 'Unknown') ?>
            </span>
        </div>
        <div class="cc-card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Date &amp; Time</div>
                    <div class="detail-value"><?= formatDt($call['calldate'], 'd M Y, h:i:s A') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">From (src)</div>
                    <div class="detail-value fw-bold">
                        <?= e($call['src'] ?: '—') ?>
                        <?php if ($call['cnam']): ?><span class="text-muted small ms-1">(<?= e($call['cnam']) ?>)</span><?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">To (dst)</div>
                    <div class="detail-value fw-bold">
                        <?= e($call['dst'] ?: '—') ?>
                        <?php if ($call['dst_cnam']): ?><span class="text-muted small ms-1">(<?= e($call['dst_cnam']) ?>)</span><?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Caller ID</div>
                    <div class="detail-value"><?= e($call['clid'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Direction</div>
                    <div class="detail-value">
                        <i class="fas <?= directionIcon($call['call_direction']) ?> me-1"></i>
                        <?= ucfirst($call['call_direction'] ?: 'unknown') ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Duration / Talk Time</div>
                    <div class="detail-value"><?= formatDuration($call['duration']) ?> / <?= formatDuration($call['billsec']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Channel</div>
                    <div class="detail-value small text-muted"><?= e($call['channel'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Dst Channel</div>
                    <div class="detail-value small text-muted"><?= e($call['dstchannel'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Last App</div>
                    <div class="detail-value small"><?= e($call['lastapp'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Context</div>
                    <div class="detail-value small"><?= e($call['dcontext'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Unique ID</div>
                    <div class="detail-value small text-muted font-monospace"><?= e($call['uniqueid'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Linked ID</div>
                    <div class="detail-value small text-muted font-monospace"><?= e($call['linkedid'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Account Code</div>
                    <div class="detail-value"><?= e($call['accountcode'] ?: '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Recording File</div>
                    <div class="detail-value small text-muted"><?= e($call['recordingfile'] ?: '—') ?></div>
                </div>
                <?php if ($call['is_manual']): ?>
                <div class="detail-item">
                    <div class="detail-label">Entered By</div>
                    <div class="detail-value"><?= e($call['created_by_name'] ?: '—') ?> <span class="badge bg-warning text-dark">Manual</span></div>
                </div>
                <?php endif; ?>
                <?php if ($call['manual_notes']): ?>
                <div class="detail-item" style="grid-column:1/-1">
                    <div class="detail-label">Manual Entry Notes</div>
                    <div class="detail-value"><?= nl2br(e($call['manual_notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Mark + Assign row -->
            <div class="detail-controls mt-3 pt-3 border-top">
                <form method="POST" class="d-inline-flex align-items-center gap-2 flex-wrap">
                    <input type="hidden" name="update_mark" value="1">
                    <label class="small text-muted">Mark:</label>
                    <select name="call_mark" class="form-select form-select-sm" style="width:auto">
                        <?php foreach (['normal','follow_up','callback','resolved','urgent','escalated','no_action'] as $m): ?>
                        <option value="<?= $m ?>" <?= $call['call_mark']===$m?'selected':'' ?>>
                            <?= ucwords(str_replace('_',' ',$m)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary" type="submit">Update Mark</button>
                </form>
                <form method="POST" class="d-inline-flex align-items-center gap-2 flex-wrap ms-3">
                    <input type="hidden" name="assign_agent" value="1">
                    <label class="small text-muted">Assign to:</label>
                    <select name="agent_id" class="form-select form-select-sm" style="width:auto">
                        <option value="">— Unassigned —</option>
                        <option value="<?= $aid ?>" <?= $call['agent_id']==$aid?'selected':'' ?>>Me</option>
                        <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $call['agent_id']==(int)$ag['id']?'selected':'' ?>><?= e($ag['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" type="submit">Assign</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Notes thread -->
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-comments me-2"></i>Notes &amp; Log Thread
                <span class="badge bg-secondary ms-1"><?= count($notes) ?></span>
            </span>
            <button class="btn btn-sm btn-primary" onclick="focusNote()">
                <i class="fas fa-plus me-1"></i>Add Note
            </button>
        </div>
        <div class="cc-card-body">

            <!-- Note compose -->
            <div class="note-compose mb-4" id="noteCompose">
                <div class="row g-2 mb-2">
                    <div class="col-sm-4">
                        <label class="form-label small">Type</label>
                        <select id="newNoteType" class="form-select form-select-sm">
                            <option value="note">Note</option>
                            <option value="issue">Issue</option>
                            <option value="feedback">Feedback</option>
                            <option value="followup">Follow-up</option>
                            <option value="query">Query</option>
                            <option value="resolution">Resolution</option>
                            <option value="internal">Internal</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small">Priority</label>
                        <select id="newNotePriority" class="form-select form-select-sm">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small">Status</label>
                        <select id="newNoteStatus" class="form-select form-select-sm">
                            <option value="open">Open</option>
                            <option value="pending">Pending</option>
                            <option value="followup">Followup</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div id="replyingTo" class="replying-to-banner" style="display:none"></div>
                <textarea id="newNoteContent" class="form-control" rows="4"
                          placeholder="Write a note, feedback, issue, or query about this call…"></textarea>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="submitNote()">
                        <i class="fas fa-save me-1"></i>Save Note
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="cancelReply()" id="cancelReplyBtn" style="display:none">
                        Cancel Reply
                    </button>
                    <span class="text-muted small ms-auto mt-1">Saved as: <strong><?= e(currentAgent()['full_name']) ?></strong></span>
                </div>
            </div>

            <!-- Notes list -->
            <div id="notesList">
                <?php if (empty($notes)): ?>
                <div class="empty-state py-4"><i class="fas fa-comment-slash"></i> No notes yet. Be the first to log something.</div>
                <?php endif; ?>
                <?php foreach ($notes as $n) renderNote($n); ?>
            </div>
        </div>
    </div>

    <!-- Edit history -->
    <?php if ($history->num_rows): ?>
    <div class="cc-card mb-4">
        <div class="cc-card-head" onclick="toggleSection('editHistory')">
            <span><i class="fas fa-clock-rotate-left me-2"></i>Edit History</span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div id="editHistory" class="cc-card-body p-0">
            <?php while ($h = $history->fetch_assoc()): ?>
            <div class="quick-item">
                <div class="qi-icon text-muted small"><i class="fas fa-pen-to-square"></i></div>
                <div class="qi-body">
                    <div class="qi-title">
                        <strong><?= e($h['full_name']) ?></strong> changed
                        <code><?= e($h['field_name']) ?></code>
                    </div>
                    <div class="qi-sub">
                        <span class="text-danger"><?= e(mb_strimwidth($h['old_value'] ?? '—', 0, 40, '…')) ?></span>
                        → <span class="text-success"><?= e(mb_strimwidth($h['new_value'] ?? '—', 0, 40, '…')) ?></span>
                        &bull; <?= timeAgo($h['edited_at']) ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Right: Contact + Tasks + Contact history ───────────────────────────────── -->
<div class="col-lg-4">

    <!-- Contact card -->
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-user me-2"></i>Contact</span>
            <?php if ($call['contact_id']): ?>
            <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $call['contact_id'] ?>" class="btn-link small">Full profile</a>
            <?php endif; ?>
        </div>
        <div class="cc-card-body">
            <?php if ($call['contact_id']): ?>
            <div class="contact-profile-mini">
                <div class="contact-avatar-lg"><i class="fas fa-user"></i></div>
                <div>
                    <div class="fw-bold">
                        <?php if ($call['is_favorite']): ?><i class="fas fa-star text-warning me-1"></i><?php endif; ?>
                        <?= e($call['contact_name'] ?: $call['src']) ?>
                    </div>
                    <?php if ($call['company']): ?><div class="text-muted small"><?= e($call['company']) ?></div><?php endif; ?>
                    <div class="text-muted small"><?= e($call['contact_phone'] ?: $call['src']) ?></div>
                    <span class="badge bg-secondary small"><?= ucfirst($call['contact_scope'] ?: 'unknown') ?></span>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-3">
                <div class="text-muted mb-2"><i class="fas fa-user-slash fa-2x"></i></div>
                <div class="text-muted small mb-2">No contact linked for <?= e($call['src']) ?></div>
                <button class="btn btn-sm btn-primary" onclick="quickCreateContact('<?= e($call['src']) ?>',<?= $id ?>)">
                    <i class="fas fa-user-plus me-1"></i>Create Contact
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tasks for this call -->
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-list-check text-warning me-2"></i>Tasks
                <span class="badge bg-secondary ms-1"><?= $tasks->num_rows ?></span>
            </span>
            <button class="btn btn-sm btn-warning btn-sm" onclick="openTaskModal()">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <div class="cc-card-body p-0">
            <?php if ($tasks->num_rows): while ($t = $tasks->fetch_assoc()): ?>
            <div class="quick-item <?= $t['status']==='done'?'opacity-50':'' ?>">
                <div class="qi-icon">
                    <input type="checkbox" class="task-check" <?= $t['status']==='done'?'checked':'' ?>
                           onchange="updateTaskStatus(<?= $t['id'] ?>, this.checked ? 'done' : 'in_progress')">
                </div>
                <div class="qi-body">
                    <div class="qi-title <?= $t['status']==='done'?'text-decoration-line-through':'' ?>">
                        <?= e($t['title']) ?>
                    </div>
                    <div class="qi-sub">
                        <i class="fas fa-user me-1"></i><?= e($t['assigned_name']) ?>
                        <span class="badge bg-<?= priorityClass($t['priority']) ?> ms-1"><?= $t['priority'] ?></span>
                        <?php if ($t['due_date']): ?>
                        &bull; Due <?= formatDt($t['due_date'], 'd M') ?>
                        <?php endif; ?>
                    </div>
                    <div class="qi-sub text-muted small">By: <?= e($t['creator_name']) ?></div>
                </div>
                <div class="qi-actions">
                    <a href="<?= APP_URL ?>/todos.php?id=<?= $t['id'] ?>" class="btn-sm-icon"><i class="fas fa-eye"></i></a>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty-state py-3"><i class="fas fa-list-check"></i> No tasks</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Contact's other calls -->
    <?php if ($contactHistory && $contactHistory->num_rows): ?>
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-history me-2"></i>Other Calls (<?= $contactHistory->num_rows ?>)</span>
            <?php if ($call['contact_id']): ?>
            <a href="<?= APP_URL ?>/calls.php?contact=<?= $call['contact_id'] ?>" class="btn-link small">All</a>
            <?php endif; ?>
        </div>
        <div class="cc-card-body p-0">
            <?php while ($ch = $contactHistory->fetch_assoc()): ?>
            <div class="quick-item">
                <div class="qi-icon">
                    <span class="badge bg-<?= dispositionClass($ch['disposition']) ?>" style="width:8px;height:8px;padding:0;border-radius:50%"></span>
                </div>
                <div class="qi-body">
                    <div class="qi-title small">
                        <i class="fas <?= directionIcon($ch['call_direction']) ?> me-1"></i>
                        <?= formatDt($ch['calldate'], 'd M, h:i A') ?>
                    </div>
                    <div class="qi-sub">
                        <span class="badge bg-<?= dispositionClass($ch['disposition']) ?> small"><?= $ch['disposition'] ?></span>
                        &bull; <?= formatDuration($ch['billsec']) ?>
                    </div>
                </div>
                <div class="qi-actions">
                    <a href="<?= APP_URL ?>/call_detail.php?id=<?= $ch['id'] ?>" class="btn-sm-icon">
                        <i class="fas fa-eye"></i>
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- FAQs quick lookup -->
    <div class="cc-card mb-4">
        <div class="cc-card-head">
            <span><i class="fas fa-circle-question me-2"></i>Quick FAQ Lookup</span>
        </div>
        <div class="cc-card-body p-3">
            <div class="input-group input-group-sm mb-2">
                <input type="text" id="faqSearch" class="form-control" placeholder="Search KB…" oninput="searchFaqs(this.value)">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
            </div>
            <div id="faqResults" class="faq-results"></div>
        </div>
    </div>

</div>
</div><!-- /.row -->

<!-- ── Audio bar ──────────────────────────────────────────────────────────────── -->
<div class="audio-bar" id="audioBar" style="display:none">
    <div class="audio-info" id="audioInfo">Recording</div>
    <audio id="audioPlayer" controls style="flex:1;min-width:0"></audio>
    <button class="btn-icon" onclick="closeAudio()"><i class="fas fa-times"></i></button>
</div>

<!-- ── Task modal ────────────────────────────────────────────────────────────── -->
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-list-check me-2"></i>Add Task to This Call</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small">Title <span class="text-danger">*</span></label>
                    <input type="text" id="tmTitle" class="form-control" placeholder="What needs to be done?">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col">
                        <label class="form-label small">Assign To</label>
                        <select id="tmAssign" class="form-select form-select-sm">
                            <option value="<?= $aid ?>">Me</option>
                            <?php foreach ($agents as $ag): ?>
                            <option value="<?= $ag['id'] ?>"><?= e($ag['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label small">Priority</label>
                        <select id="tmPriority" class="form-select form-select-sm">
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Due Date</label>
                    <input type="datetime-local" id="tmDue" class="form-control form-control-sm">
                </div>
                <textarea id="tmDesc" class="form-control form-control-sm" rows="2" placeholder="Details…"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitTask()"><i class="fas fa-save me-1"></i>Create</button>
            </div>
        </div>
    </div>
</div>

<script>
const APP_URL   = '<?= APP_URL ?>';
const CALL_ID   = <?= $id ?>;
const CONTACT_ID = <?= (int)($call['contact_id'] ?? 0) ?>;
let replyToId   = null;

// Note compose
function focusNote() {
    document.getElementById('noteCompose').scrollIntoView({behavior:'smooth'});
    setTimeout(()=>document.getElementById('newNoteContent').focus(), 400);
}
function replyNote(parentId, authorName) {
    replyToId = parentId;
    const banner = document.getElementById('replyingTo');
    banner.textContent = 'Replying to ' + authorName;
    banner.style.display = 'block';
    document.getElementById('cancelReplyBtn').style.display = '';
    document.getElementById('newNoteType').value = 'reply';
    focusNote();
}
function cancelReply() {
    replyToId = null;
    document.getElementById('replyingTo').style.display = 'none';
    document.getElementById('cancelReplyBtn').style.display = 'none';
    document.getElementById('newNoteType').value = 'note';
}
function submitNote() {
    const content = document.getElementById('newNoteContent').value.trim();
    if (!content) { showToast('Note cannot be empty', 'warning'); return; }
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:    'add',
            entity:    'call',
            entity_id: CALL_ID,
            parent_id: replyToId,
            content,
            note_type: document.getElementById('newNoteType').value,
            priority:  document.getElementById('newNotePriority').value,
            log_status:document.getElementById('newNoteStatus').value,
        })
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Note saved','success'); setTimeout(()=>location.reload(),600); }
        else showToast(d.error || 'Error','danger');
    });
}
function editNote(noteId, oldContent) {
    const newContent = prompt('Edit note:', oldContent);
    if (newContent === null || newContent.trim() === '') return;
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'edit', entity:'call', note_id:noteId, content:newContent.trim()})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Updated','success'); setTimeout(()=>location.reload(),600); }
        else showToast(d.error,'danger');
    });
}
function togglePinNote(noteId, isPinned) {
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'pin', entity:'call', note_id:noteId, is_pinned: isPinned ? 0 : 1})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) setTimeout(()=>location.reload(),400);
    });
}
function changeNoteStatus(noteId, current) {
    const statuses = ['open','pending','followup','closed'];
    const next     = statuses[(statuses.indexOf(current) + 1) % statuses.length];
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'status', entity:'call', note_id:noteId, log_status:next})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Status → ' + next,'info'); setTimeout(()=>location.reload(),600); }
    });
}

// Tasks
function openTaskModal() {
    new bootstrap.Modal(document.getElementById('taskModal')).show();
    setTimeout(()=>document.getElementById('tmTitle').focus(), 300);
}
function submitTask() {
    const title = document.getElementById('tmTitle').value.trim();
    if (!title) { showToast('Title required','warning'); return; }
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'create', call_id:CALL_ID, contact_id:CONTACT_ID,
            title, description:document.getElementById('tmDesc').value.trim(),
            assigned_to:document.getElementById('tmAssign').value,
            priority:document.getElementById('tmPriority').value,
            due_date:document.getElementById('tmDue').value,
        })
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
            showToast('Task created','success');
            setTimeout(()=>location.reload(),600);
        } else showToast(d.error,'danger');
    });
}
function updateTaskStatus(id, status) {
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'status', id, status})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) setTimeout(()=>location.reload(),400);
    });
}

// Contact create
function quickCreateContact(phone, callId) {
    if (!confirm('Create contact for ' + phone + '?')) return;
    fetch(APP_URL + '/api/contacts.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'create', phone, call_id:callId})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Contact created','success'); setTimeout(()=>location.reload(),700); }
        else showToast(d.error,'danger');
    });
}

// Audio
function playRecording(id, file) {
    const bar    = document.getElementById('audioBar');
    const player = document.getElementById('audioPlayer');
    document.getElementById('audioInfo').textContent = 'Call #' + id;
    player.src = APP_URL + '/api/audio.php?id=' + id;
    player.load();
    bar.style.display = 'flex';
    player.play().catch(() => {});
}
function closeAudio() {
    document.getElementById('audioPlayer').pause();
    document.getElementById('audioBar').style.display = 'none';
}

// Copy full call info for messaging
function copyCallInfo() {
    const info = [
        'Call Detail Report',
        '===================',
        'Date/Time : <?= addslashes(formatDt($call['calldate'], 'd M Y, h:i:s A')) ?>',
        'From      : <?= addslashes($call['src']) ?>' + (<?= j($call['cnam']) ?> ? ' ('+<?= j($call['cnam']) ?>+')' : ''),
        'To        : <?= addslashes($call['dst']) ?>',
        'Direction : <?= ucfirst($call['call_direction'] ?? 'unknown') ?>',
        'Status    : <?= $call['disposition'] ?>',
        'Duration  : <?= formatDuration($call['duration']) ?> / Talk: <?= formatDuration($call['billsec']) ?>',
        'Contact   : <?= addslashes($call['contact_name'] ?? '—') ?>',
        'Mark      : <?= ucwords(str_replace('_',' ',$call['call_mark'] ?? 'normal')) ?>',
        'Unique ID : <?= $call['uniqueid'] ?>',
    ].join('\n');
    navigator.clipboard.writeText(info).then(()=>showToast('Call info copied','success'));
}

// Section toggle
function toggleSection(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? '' : 'none';
}

// FAQ search
let faqTimer;
function searchFaqs(q) {
    clearTimeout(faqTimer);
    if (q.length < 2) { document.getElementById('faqResults').innerHTML = ''; return; }
    faqTimer = setTimeout(()=>{
        fetch(APP_URL + '/api/search.php?type=faq&q=' + encodeURIComponent(q))
            .then(r=>r.json()).then(d=>{
                const el = document.getElementById('faqResults');
                if (!d.results?.length) { el.innerHTML = '<div class="text-muted small">No results</div>'; return; }
                el.innerHTML = d.results.map(f=>`
                    <div class="faq-item" onclick="this.querySelector('.faq-answer').classList.toggle('d-none')">
                        <div class="faq-q"><i class="fas fa-q me-1 text-muted"></i>${escHtml(f.question)}</div>
                        <div class="faq-answer d-none">${escHtml(f.answer)}
                            <button class="btn btn-xs btn-outline-secondary mt-1" onclick="event.stopPropagation();copyFaq(${escHtml(JSON.stringify(f.question+': '+f.answer))})">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>`).join('');
            });
    }, 300);
}
function copyFaq(text) {
    navigator.clipboard.writeText(text).then(()=>showToast('Copied','success'));
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once 'includes/footer.php'; ?>
