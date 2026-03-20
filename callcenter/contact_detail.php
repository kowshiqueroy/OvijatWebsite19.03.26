<?php
require_once 'config.php';
requireLogin();

$id  = (int)($_GET['id'] ?? 0);
$aid = agentId();
if (!$id) { header('Location: contacts.php'); exit; }

/* ── Contact ─────────────────────────────────────────────────────────────── */
$stmt = $conn->prepare(
    "SELECT c.*, ct.name AS type_name, ct.color AS type_color,
            cg.name AS group_name, cg.color AS group_color,
            a.full_name AS assigned_name,
            cb.full_name AS created_by_name, ub.full_name AS updated_by_name
     FROM contacts c
     LEFT JOIN contact_types  ct ON ct.id = c.type_id
     LEFT JOIN contact_groups cg ON cg.id = c.group_id
     LEFT JOIN agents a  ON a.id  = c.assigned_to
     LEFT JOIN agents cb ON cb.id = c.created_by
     LEFT JOIN agents ub ON ub.id = c.updated_by
     WHERE c.id = ? LIMIT 1"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$contact = $stmt->get_result()->fetch_assoc();
if (!$contact) { header('Location: contacts.php'); exit; }

$phone  = $contact['phone'];
$ePhone = $conn->real_escape_string($phone);

$pageTitle   = $contact['name'] ?: $contact['phone'];
$pageSubtitle = $contact['company'] ?: '';
$activePage  = 'contacts';

/* ── All calls (contact_id OR src/dst phone) ─────────────────────────────── */
$callRows = [];
$cr = $conn->query(
    "SELECT cl.*, a.full_name AS agent_name,
            (SELECT COUNT(*) FROM call_notes cn WHERE cn.call_id=cl.id) AS note_count
     FROM call_logs cl
     LEFT JOIN agents a ON a.id = cl.agent_id
     WHERE cl.contact_id=$id OR cl.src='$ePhone' OR cl.dst='$ePhone'
     ORDER BY cl.calldate DESC"
);
while ($row = $cr->fetch_assoc()) $callRows[] = $row;

/* ── Contact notes (threaded) ────────────────────────────────────────────── */
function fetchContactNotes(mysqli $conn, int $cid, ?int $parentId = null): array {
    $pid = $parentId === null ? 'IS NULL' : "= $parentId";
    $r = $conn->query(
        "SELECT cn.*, a.full_name, a.username
         FROM contact_notes cn JOIN agents a ON a.id = cn.agent_id
         WHERE cn.contact_id=$cid AND cn.parent_id $pid
         ORDER BY cn.is_pinned DESC, cn.created_at ASC"
    );
    $nodes = [];
    while ($r && $n = $r->fetch_assoc()) {
        $n['replies'] = fetchContactNotes($conn, $cid, $n['id']);
        $nodes[] = $n;
    }
    return $nodes;
}
$notes = fetchContactNotes($conn, $id);

/* ── Tasks ───────────────────────────────────────────────────────────────── */
$taskList = [];
$tr = $conn->query(
    "SELECT t.*, a.full_name AS assigned_name, cb.full_name AS creator_name
     FROM todos t
     JOIN agents a  ON a.id  = t.assigned_to
     JOIN agents cb ON cb.id = t.created_by
     WHERE t.contact_id=$id
     ORDER BY FIELD(t.status,'in_progress','pending','done','cancelled'),
              FIELD(t.priority,'urgent','high','medium','low')"
);
while ($t = $tr->fetch_assoc()) $taskList[] = $t;

/* ── Call notes grouped by call_id (for timeline) ────────────────────────── */
$callNotesByCall = [];
if ($callRows) {
    $callIds = implode(',', array_column($callRows, 'id'));
    $cnr = $conn->query(
        "SELECT cn.*, a.full_name, a.username
         FROM call_notes cn JOIN agents a ON a.id = cn.agent_id
         WHERE cn.call_id IN ($callIds)
         ORDER BY cn.call_id ASC, cn.is_pinned DESC, cn.created_at ASC"
    );
    while ($cnr && $cn = $cnr->fetch_assoc()) {
        // build reply tree per call
        $callNotesByCall[$cn['call_id']][$cn['id']] = $cn + ['replies' => []];
    }
    // attach replies to parents within each call
    foreach ($callNotesByCall as $cid => &$cnMap) {
        foreach ($cnMap as $nid => &$n) {
            $pid = $n['parent_id'];
            if ($pid && isset($cnMap[$pid])) {
                $cnMap[$pid]['replies'][] = &$n;
                unset($cnMap[$nid]);
            }
        }
        $callNotesByCall[$cid] = array_values($cnMap);
    }
    unset($cnMap, $n);
}

/* ── Build unified timeline ───────────────────────────────────────────────── */
$tlEvents = [];
foreach ($callRows as $c) {
    $tlEvents[] = ['type'=>'call',         'date'=>$c['calldate'],        'data'=>$c, 'children'=>$callNotesByCall[$c['id']] ?? []];
}
foreach ($notes as $n) {
    $tlEvents[] = ['type'=>'contact_note', 'date'=>$n['created_at'],      'data'=>$n, 'children'=>$n['replies'] ?? []];
}
foreach ($taskList as $t) {
    $tlEvents[] = ['type'=>'task',         'date'=>$t['created_at'] ?? $t['due_date'] ?? date('Y-m-d H:i:s'), 'data'=>$t, 'children'=>[]];
}
usort($tlEvents, fn($a,$b) => strcmp($b['date'], $a['date']));

/* ── Edit history ─────────────────────────────────────────────────────────── */
$histList = [];
$hr = $conn->query(
    "SELECT eh.*, a.full_name FROM edit_history eh JOIN agents a ON a.id=eh.edited_by
     WHERE eh.entity_type='contacts' AND eh.entity_id=$id ORDER BY eh.edited_at DESC LIMIT 50"
);
while ($h = $hr->fetch_assoc()) $histList[] = $h;

$agents      = getAllAgents();
$groups_list = []; $gr  = $conn->query("SELECT * FROM contact_groups ORDER BY name"); while($g=$gr->fetch_assoc())  $groups_list[]=$g;
$types_list  = []; $tr2 = $conn->query("SELECT * FROM contact_types  ORDER BY name"); while($t=$tr2->fetch_assoc()) $types_list[]=$t;

require_once 'includes/layout.php';

/* ── Note renderer (PHP, for initial load) ────────────────────────────────── */
function renderContactNote(array $n, int $depth = 0): void {
    $colors = ['note'=>'secondary','issue'=>'danger','feedback'=>'info','resolution'=>'success',
               'query'=>'warning','followup'=>'primary','internal'=>'dark','reply'=>'secondary'];
    $color = $colors[$n['note_type']] ?? 'secondary';
    ?>
    <div class="note-item <?= $depth ? 'note-reply' : 'note-root' ?> <?= $n['is_pinned']?'note-pinned':'' ?>"
         id="cnote-<?= $n['id'] ?>">
        <div class="note-head">
            <div class="note-avatar"><i class="fas fa-user"></i></div>
            <div class="note-meta">
                <span class="note-author"><?= e($n['full_name']) ?></span>
                <span class="badge bg-<?= $color ?> note-type-badge"><?= $n['note_type'] ?></span>
                <?php if ($n['priority'] !== 'low'): ?><span class="badge bg-<?= priorityClass($n['priority']) ?>"><?= $n['priority'] ?></span><?php endif; ?>
                <?php if ($n['is_pinned']): ?><i class="fas fa-thumbtack text-warning ms-1"></i><?php endif; ?>
            </div>
            <div class="note-time ms-auto small text-muted"><?= timeAgo($n['created_at']) ?></div>
        </div>
        <div class="note-body" id="cnbody-<?= $n['id'] ?>"><?= nl2br(e($n['content'])) ?></div>
        <div id="cnedit-<?= $n['id'] ?>" style="display:none">
            <textarea class="form-control form-control-sm mb-2" id="cnetxt-<?= $n['id'] ?>" rows="3"><?= e($n['content']) ?></textarea>
            <button class="btn btn-xs btn-primary me-1" onclick="saveNoteEdit(<?= $n['id'] ?>)">Save</button>
            <button class="btn btn-xs btn-secondary" onclick="cancelNoteEdit(<?= $n['id'] ?>)">Cancel</button>
        </div>
        <div class="note-actions">
            <button class="btn-link small" onclick="replyContactNote(<?= $n['id'] ?>, '<?= e(addslashes($n['full_name'])) ?>')"><i class="fas fa-reply me-1"></i>Reply</button>
            <button class="btn-link small" onclick="inlineEditNote(<?= $n['id'] ?>)"><i class="fas fa-pen me-1"></i>Edit</button>
            <button class="btn-link small" onclick="pinContactNote(<?= $n['id'] ?>, <?= (int)$n['is_pinned'] ?>)">
                <i class="fas fa-thumbtack me-1"></i><?= $n['is_pinned']?'Unpin':'Pin' ?>
            </button>
        </div>
        <?php if (!empty($n['replies'])): ?>
        <div class="note-replies">
            <?php foreach ($n['replies'] as $r) renderContactNote($r, $depth + 1); ?>
        </div>
        <?php endif; ?>
        <div class="note-replies" id="cnreplies-<?= $n['id'] ?>"></div>
    </div>
    <?php
}
?>

<style>
/* ── Page-specific styles ────────────────────────────────────────────────── */
.cc-tabs { border-bottom: 2px solid var(--border); gap: .25rem; }
.cc-tabs .nav-link { color: var(--muted); border: none; border-bottom: 2px solid transparent;
    margin-bottom: -2px; padding: .5rem 1rem; border-radius: 0; font-size: .9rem; transition: .15s; }
.cc-tabs .nav-link:hover { color: var(--text); background: rgba(255,255,255,.04); }
.cc-tabs .nav-link.active { color: var(--accent); border-bottom-color: var(--accent); background: transparent; }

.profile-view-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px,1fr)); gap: .75rem; }
.pv-item .pv-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
.pv-item .pv-val   { font-size: .9rem; color: var(--text); margin-top: 2px; }

/* Call notes inline panel */
.call-notes-panel td { border-top: none !important; padding: 0 !important; background: var(--card2); }
.call-notes-inner { padding: 1rem 1.25rem; border-left: 3px solid var(--accent); }
.call-note-item { padding: .5rem .75rem; border-bottom: 1px solid var(--border); }
.call-note-item:last-child { border-bottom: none; }
.cn-compose { padding: .75rem 0 0; }

/* btn-xs */
.btn-xs { font-size: .72rem; padding: .2rem .5rem; line-height: 1.4; }

/* task list */
.task-row { display: flex; align-items: flex-start; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid var(--border); }
.task-row:last-child { border-bottom: none; }
.task-row.done-task { opacity: .55; }
.task-create-panel { background: var(--card2); border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; }

/* history timeline */
.hist-item { display: flex; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--border); }
.hist-item:last-child { border-bottom: none; }
.hist-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent); margin-top: 5px; flex-shrink: 0; }

/* ── Compact Activity Timeline ───────────────────────────────────────────── */
.tl-wrap { position:relative; padding:.25rem 0 .5rem; }
.tl-wrap::before { content:''; position:absolute; left:9px; top:0; bottom:0; width:2px;
    background:var(--border); border-radius:1px; z-index:0; }

.tl-node { display:flex; align-items:flex-start; gap:.5rem; margin-bottom:.5rem; }

.tl-dot { width:20px; height:20px; border-radius:50%; flex-shrink:0; z-index:1; position:relative;
    display:flex; align-items:center; justify-content:center; border:2px solid; font-size:.55rem; margin-top:3px; }
.tl-dot-in   { background:#0d2e22; border-color:#10b981; color:#10b981; }
.tl-dot-out  { background:#1a1a3a; border-color:#818cf8; color:#818cf8; }
.tl-dot-unk  { background:#1e2028; border-color:#6b7280; color:#6b7280; }
.tl-dot-note { background:#0d1f3a; border-color:#3b82f6; color:#3b82f6; }
.tl-dot-task { background:#2a2000; border-color:#eab308; color:#eab308; }

.tl-body { flex:1; min-width:0; }

/* main event card — single compact row */
.tl-card { background:var(--card2); border:1px solid var(--border); border-left:3px solid;
    border-radius:.35rem; padding:.3rem .6rem; display:flex; align-items:center;
    gap:.3rem; flex-wrap:wrap; font-size:.76rem; cursor:default; }
.tl-card-call-in  { border-left-color:#10b981; }
.tl-card-call-out { border-left-color:#818cf8; }
.tl-card-call-unk { border-left-color:#6b7280; }
.tl-card-note     { border-left-color:#3b82f6; }
.tl-card-task     { border-left-color:#eab308; }

.tl-lbl   { font-size:.67rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); white-space:nowrap; }
.tl-ts    { font-size:.68rem; color:var(--muted); white-space:nowrap; margin-left:auto; }
.tl-nums  { font-family:monospace; font-size:.74rem; color:var(--text); }
.tl-dur   { font-size:.7rem; color:var(--muted); }
.tl-pbtn  { background:none; border:none; padding:0; color:#10b981; cursor:pointer; font-size:.85rem; line-height:1; }
.tl-pbtn:hover { color:#059669; }

/* second detail line under card */
.tl-detail { font-size:.71rem; color:var(--muted); margin-top:.15rem; padding-left:.15rem;
    display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; }
.tl-txt   { color:var(--text); font-size:.75rem; margin-top:.15rem; padding-left:.15rem;
    overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }

/* children */
.tl-kids  { margin:.3rem 0 .1rem .35rem; border-left:2px solid var(--border); padding:.05rem 0 .05rem .5rem; }
.tl-kid   { font-size:.71rem; padding:.18rem 0; display:flex; gap:.25rem; align-items:flex-start; flex-wrap:wrap; }
.tl-kid + .tl-kid { border-top:1px solid var(--border); }
.tl-kid-txt { color:var(--text); flex:1; min-width:0;
    overflow:hidden; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; }

/* grandchildren */
.tl-gkids { margin:.2rem 0 0 .5rem; border-left:2px dashed var(--border); padding-left:.4rem; }
.tl-gkid  { font-size:.68rem; padding:.12rem 0; display:flex; gap:.2rem; align-items:flex-start; flex-wrap:wrap; }
.tl-gkid-txt { color:var(--muted); flex:1; min-width:0;
    overflow:hidden; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; }

.badge-xs { font-size:.62rem; padding:.1rem .3rem; }
</style>

<!-- ── Action bar ────────────────────────────────────────────────────────── -->
<div class="detail-action-bar mb-3">
    <a href="<?= APP_URL ?>/contacts.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()" id="editToggleBtn">
            <i class="fas fa-pen me-1"></i>Edit
        </button>
        <button class="btn btn-sm btn-primary" onclick="switchTab('notes');setTimeout(focusContactNote,250)">
            <i class="fas fa-comment-plus me-1"></i>Add Note
        </button>
        <button class="btn btn-sm btn-warning text-dark" onclick="switchTab('tasks');setTimeout(focusTaskForm,250)">
            <i class="fas fa-list-check me-1"></i>Add Task
        </button>
        <button class="btn btn-sm btn-outline-secondary" onclick="copyContactInfo()">
            <i class="fas fa-copy me-1"></i>Copy
        </button>
    </div>
</div>

<!-- ── Profile card ──────────────────────────────────────────────────────── -->
<div class="cc-card mb-3">

    <!-- View mode -->
    <div class="cc-card-body" id="profileView">
        <div class="contact-profile-full">
            <div class="contact-avatar-xl">
                <i class="fas fa-user"></i>
                <?php if ($contact['is_favorite']): ?><span class="fav-star">★</span><?php endif; ?>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h2 class="mb-0 me-1">
                        <?php if ($contact['is_blocked']): ?><i class="fas fa-ban text-danger me-2"></i><?php endif; ?>
                        <?= e($contact['name'] ?: '(No Name)') ?>
                    </h2>
                    <button class="btn-link p-0" onclick="quickToggleFav()" id="favBtn" title="Toggle Favorite">
                        <i class="<?= $contact['is_favorite']?'fas':'far' ?> fa-star text-warning fa-lg"></i>
                    </button>
                </div>
                <?php if ($contact['company']): ?>
                <div class="text-muted mb-1"><i class="fas fa-building me-1"></i><?= e($contact['company'] ?? '') ?></div>
                <?php endif; ?>
                <div class="d-flex gap-1 flex-wrap mb-3">
                    <?php if ($contact['type_name']): ?>
                    <span class="badge" style="background:<?= e($contact['type_color']??'#6b7280') ?>"><?= e($contact['type_name'] ?? '') ?></span>
                    <?php endif; ?>
                    <?php if ($contact['group_name']): ?>
                    <span class="badge" style="background:<?= e($contact['group_color']??'#6b7280') ?>"><?= e($contact['group_name'] ?? '') ?></span>
                    <?php endif; ?>
                    <span class="badge bg-<?= $contact['scope']==='internal'?'info':'secondary' ?>"><?= ucfirst($contact['scope']) ?></span>
                    <?php if ($contact['office_type']): ?><span class="badge bg-secondary"><?= ucwords(str_replace('_',' ',$contact['office_type'])) ?></span><?php endif; ?>
                    <?php if ($contact['is_blocked']): ?><span class="badge bg-danger">Blocked</span><?php endif; ?>
                </div>
                <div class="profile-view-grid">
                    <div class="pv-item">
                        <div class="pv-label">Phone</div>
                        <div class="pv-val fw-bold font-monospace"><?= e($contact['phone']) ?></div>
                    </div>
                    <?php if ($contact['email']): ?>
                    <div class="pv-item">
                        <div class="pv-label">Email</div>
                        <div class="pv-val"><?= e($contact['email'] ?? '') ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($contact['address']): ?>
                    <div class="pv-item" style="grid-column: span 2">
                        <div class="pv-label">Address</div>
                        <div class="pv-val"><?= e($contact['address'] ?? '') ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="pv-item">
                        <div class="pv-label">Assigned To</div>
                        <div class="pv-val"><?= e($contact['assigned_name'] ?: '—') ?></div>
                    </div>
                    <div class="pv-item">
                        <div class="pv-label">Total Calls</div>
                        <div class="pv-val fw-bold"><?= count($callRows) ?></div>
                    </div>
                    <div class="pv-item">
                        <div class="pv-label">Created By</div>
                        <div class="pv-val"><?= e($contact['created_by_name'] ?: 'System') ?></div>
                    </div>
                    <div class="pv-item">
                        <div class="pv-label">Updated By</div>
                        <div class="pv-val"><?= e($contact['updated_by_name'] ?: '—') ?></div>
                    </div>
                </div>
                <?php if ($contact['notes']): ?>
                <div class="contact-bio-box mt-3">
                    <div class="pv-label mb-1"><i class="fas fa-sticky-note me-1"></i>Bio / Notes</div>
                    <div class="small"><?= nl2br(e($contact['notes'] ?? '')) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit panel -->
    <div class="cc-card-body border-top" id="profileEdit" style="display:none">
        <h6 class="mb-3 text-muted"><i class="fas fa-pen me-2"></i>Edit Contact</h6>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label form-label-sm">Name</label>
                <input type="text" id="ef_name" class="form-control form-control-sm" value="<?= e($contact['name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Phone</label>
                <input type="text" id="ef_phone" class="form-control form-control-sm" value="<?= e($contact['phone'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Company</label>
                <input type="text" id="ef_company" class="form-control form-control-sm" value="<?= e($contact['company'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Email</label>
                <input type="email" id="ef_email" class="form-control form-control-sm" value="<?= e($contact['email'] ?? '') ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label form-label-sm">Address</label>
                <input type="text" id="ef_address" class="form-control form-control-sm" value="<?= e($contact['address'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Scope</label>
                <select id="ef_scope" class="form-select form-select-sm">
                    <?php foreach(['unknown','internal','external'] as $s): ?>
                    <option value="<?= $s ?>" <?= $contact['scope']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Office Type</label>
                <select id="ef_office_type" class="form-select form-select-sm">
                    <option value="">— None —</option>
                    <?php foreach(['head_office','branch_office','regional_office','remote'] as $o): ?>
                    <option value="<?= $o ?>" <?= $contact['office_type']===$o?'selected':'' ?>><?= ucwords(str_replace('_',' ',$o)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Type</label>
                <input type="text" id="ef_type_name" class="form-control form-control-sm" autocomplete="off"
                       list="efTypeList" placeholder="Type name…"
                       value="<?= e($contact['type_name'] ?? '') ?>">
                <datalist id="efTypeList">
                    <?php foreach($types_list as $t): ?><option value="<?= e($t['name']) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm">Group</label>
                <input type="text" id="ef_group_name" class="form-control form-control-sm" autocomplete="off"
                       list="efGroupList" placeholder="Group name…"
                       value="<?= e($contact['group_name'] ?? '') ?>">
                <datalist id="efGroupList">
                    <?php foreach($groups_list as $g): ?><option value="<?= e($g['name']) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Assigned To</label>
                <select id="ef_assigned_to" class="form-select form-select-sm">
                    <option value="">— Unassigned —</option>
                    <?php foreach($agents as $ag): ?>
                    <option value="<?= $ag['id'] ?>" <?= $contact['assigned_to']==$ag['id']?'selected':'' ?>><?= e($ag['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end gap-3 pb-1">
                <div class="form-check">
                    <input type="checkbox" id="ef_is_favorite" class="form-check-input" <?= $contact['is_favorite']?'checked':'' ?>>
                    <label class="form-check-label" for="ef_is_favorite"><i class="fas fa-star text-warning me-1"></i>Favorite</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" id="ef_is_blocked" class="form-check-input" <?= $contact['is_blocked']?'checked':'' ?>>
                    <label class="form-check-label text-danger" for="ef_is_blocked"><i class="fas fa-ban me-1"></i>Blocked</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label form-label-sm">Bio / Notes</label>
                <textarea id="ef_notes" class="form-control form-control-sm" rows="3"><?= e($contact['notes'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary btn-sm" onclick="saveContact()"><i class="fas fa-save me-1"></i>Save Changes</button>
            <button class="btn btn-secondary btn-sm" onclick="toggleEditMode()">Cancel</button>
        </div>
    </div>
</div>

<!-- ── Tab navigation ─────────────────────────────────────────────────────── -->
<ul class="nav cc-tabs mb-3" id="contactTabs">
    <li class="nav-item">
        <a class="nav-link active" href="#" id="tab-btn-timeline" onclick="switchTab('timeline');return false">
            <i class="fas fa-stream me-1"></i>Timeline
            <span class="badge bg-secondary ms-1"><?= count($tlEvents) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#" id="tab-btn-calls" onclick="switchTab('calls');return false">
            <i class="fas fa-phone me-1"></i>Calls
            <span class="badge bg-secondary ms-1"><?= count($callRows) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#" id="tab-btn-notes" onclick="switchTab('notes');return false">
            <i class="fas fa-comments me-1"></i>Notes
            <span class="badge bg-secondary ms-1"><?= count($notes) ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#" id="tab-btn-tasks" onclick="switchTab('tasks');return false">
            <i class="fas fa-list-check me-1"></i>Tasks
            <span class="badge bg-secondary ms-1"><?= count($taskList) ?></span>
        </a>
    </li>
    <?php if ($histList): ?>
    <li class="nav-item">
        <a class="nav-link" href="#" id="tab-btn-history" onclick="switchTab('history');return false">
            <i class="fas fa-clock-rotate-left me-1"></i>History
        </a>
    </li>
    <?php endif; ?>
</ul>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB: CALLS
══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-calls" style="display:none">
    <?php if (empty($callRows)): ?>
    <div class="cc-card"><div class="cc-card-body"><div class="empty-state py-5"><i class="fas fa-phone-slash"></i><br>No calls found for this contact</div></div></div>
    <?php else: ?>
    <div class="cc-card">
        <div class="cc-card-head">
            <span><i class="fas fa-phone-volume me-2"></i>Call History</span>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="callSearch" class="form-control form-control-sm" placeholder="Filter calls…" style="width:180px" oninput="filterCalls(this.value)">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table cc-table mb-0" id="callsTable">
                <thead>
                    <tr>
                        <th>Date</th><th>From</th><th>To</th>
                        <th>Dir</th><th>Status</th><th>Duration</th>
                        <th>Agent</th><th class="text-center">Notes</th>
                        <th class="text-center">Rec</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($callRows as $c): ?>
                <tr id="cr-<?= $c['id'] ?>" class="call-row">
                    <td class="small">
                        <?= formatDt($c['calldate'],'d M y') ?><br>
                        <span class="text-muted"><?= formatDt($c['calldate'],'h:i A') ?></span>
                        <?php if ($c['recordingfile']): ?><i class="fas fa-microphone text-success ms-1" title="Recording available"></i><?php endif; ?>
                    </td>
                    <td class="font-monospace small"><?= e($c['src'] ?? '') ?></td>
                    <td class="font-monospace small"><?= e($c['dst'] ?? '') ?></td>
                    <td><i class="fas <?= directionIcon($c['call_direction']) ?>" title="<?= $c['call_direction'] ?>"></i></td>
                    <td><span class="badge bg-<?= dispositionClass($c['disposition']) ?>"><?= $c['disposition'] ?></span></td>
                    <td><?= formatDuration($c['billsec']) ?></td>
                    <td class="small"><?= e($c['agent_name'] ?: '—') ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary" id="nc-<?= $c['id'] ?>"><?= $c['note_count'] ?: '0' ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($c['recordingfile']): ?>
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn-sm-icon text-success" title="Play" onclick="playRecording(<?= $c['id'] ?>, <?= j($c['recordingfile']) ?>)">
                                <i class="fas fa-play"></i>
                            </button>
                            <a class="btn-sm-icon text-muted" href="<?= APP_URL ?>/api/audio.php?id=<?= $c['id'] ?>&dl=1" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <a class="btn-sm-icon text-primary" href="<?= APP_URL ?>/call_detail.php?id=<?= $c['id'] ?>" title="View Detail" target="_blank">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <button class="btn-sm-icon" onclick="toggleCallNotes(<?= $c['id'] ?>, this)" title="Notes">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </td>
                </tr>
                <tr class="call-notes-panel" id="cnp-<?= $c['id'] ?>" style="display:none">
                    <td colspan="10">
                        <div class="call-notes-inner">
                            <div class="call-notes-list" id="cnl-<?= $c['id'] ?>">
                                <div class="text-muted small py-2"><i class="fas fa-spinner fa-spin me-1"></i>Loading notes…</div>
                            </div>
                            <div class="cn-compose">
                                <div class="d-flex gap-2 mb-1">
                                    <select id="cnatype-<?= $c['id'] ?>" class="form-select form-select-sm" style="width:130px">
                                        <option value="note">Note</option>
                                        <option value="issue">Issue</option>
                                        <option value="followup">Follow-up</option>
                                        <option value="resolution">Resolution</option>
                                        <option value="feedback">Feedback</option>
                                    </select>
                                    <select id="cnapri-<?= $c['id'] ?>" class="form-select form-select-sm" style="width:110px">
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <textarea id="cnatxt-<?= $c['id'] ?>" class="form-control form-control-sm mb-1" rows="2" placeholder="Add note to this call…"></textarea>
                                <button class="btn btn-xs btn-primary" onclick="addCallNote(<?= $c['id'] ?>)">
                                    <i class="fas fa-save me-1"></i>Save Note
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB: NOTES
══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-notes" style="display:none">
    <div class="cc-card">
        <div class="cc-card-head">
            <span><i class="fas fa-comments me-2"></i>Contact Notes &amp; Thread</span>
        </div>
        <div class="cc-card-body">
            <!-- Compose -->
            <div class="note-compose mb-4" id="contactNoteCompose">
                <div class="row g-2 mb-2">
                    <div class="col-sm-4">
                        <select id="cnType" class="form-select form-select-sm">
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
                        <select id="cnPriority" class="form-select form-select-sm">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-sm-4 d-flex align-items-center">
                        <span class="text-muted small">By: <strong><?= e(currentAgent()['full_name']) ?></strong></span>
                    </div>
                </div>
                <div id="cnReplyingTo" class="replying-to-banner" style="display:none"></div>
                <textarea id="cnContent" class="form-control" rows="3"
                          placeholder="Log feedback, issue, follow-up, or note about this contact…"></textarea>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="submitContactNote()">
                        <i class="fas fa-save me-1"></i>Save Note
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="cancelCnReply()" id="cnCancelReply" style="display:none">
                        Cancel Reply
                    </button>
                </div>
            </div>
            <!-- Notes list -->
            <div id="contactNotesList">
                <?php if (empty($notes)): ?>
                <div class="empty-state py-4"><i class="fas fa-comment-slash"></i> No notes yet.</div>
                <?php endif; ?>
                <?php foreach ($notes as $n) renderContactNote($n); ?>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB: TASKS
══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-tasks" style="display:none">
    <!-- Create form -->
    <div class="task-create-panel mb-3">
        <h6 class="mb-3"><i class="fas fa-plus me-2 text-warning"></i>New Task</h6>
        <div class="row g-2">
            <div class="col-12">
                <input type="text" id="ct_title" class="form-control form-control-sm" placeholder="Task title *">
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Assign To</label>
                <select id="ct_assign" class="form-select form-select-sm">
                    <option value="<?= $aid ?>">Me (<?= e(currentAgent()['full_name']) ?>)</option>
                    <?php foreach($agents as $ag): if($ag['id']==$aid) continue; ?>
                    <option value="<?= $ag['id'] ?>"><?= e($ag['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Priority</label>
                <select id="ct_priority" class="form-select form-select-sm">
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label form-label-sm">Due Date</label>
                <input type="datetime-local" id="ct_due" class="form-control form-control-sm">
            </div>
            <div class="col-12">
                <textarea id="ct_desc" class="form-control form-control-sm" rows="2" placeholder="Details / description…"></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-warning btn-sm text-dark" onclick="submitTask()">
                    <i class="fas fa-save me-1"></i>Create Task
                </button>
            </div>
        </div>
    </div>

    <!-- Task list -->
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-list-check me-2"></i>Tasks (<?= count($taskList) ?>)</span></div>
        <div id="taskListPanel">
            <?php if (empty($taskList)): ?>
            <div class="empty-state py-4"><i class="fas fa-list-check"></i> No tasks yet.</div>
            <?php endif; ?>
            <?php foreach($taskList as $t): ?>
            <div class="task-row <?= $t['status']==='done'?'done-task':'' ?>" id="task-<?= $t['id'] ?>">
                <div style="padding-top:3px">
                    <input type="checkbox" class="form-check-input" <?= $t['status']==='done'?'checked':'' ?>
                           onchange="updateTaskStatus(<?= $t['id'] ?>, this.checked)">
                </div>
                <div class="flex-grow-1">
                    <div class="<?= $t['status']==='done'?'text-decoration-line-through':'' ?> fw-semibold small">
                        <?= e($t['title']) ?>
                    </div>
                    <div class="text-muted small d-flex gap-2 flex-wrap mt-1">
                        <span><i class="fas fa-user me-1"></i><?= e($t['assigned_name']) ?></span>
                        <span class="badge bg-<?= priorityClass($t['priority']) ?>"><?= $t['priority'] ?></span>
                        <?php if ($t['due_date']): ?><span><i class="fas fa-calendar me-1"></i><?= formatDt($t['due_date'],'d M y') ?></span><?php endif; ?>
                        <span class="text-muted">by <?= e($t['creator_name']) ?></span>
                    </div>
                    <?php if ($t['description']): ?>
                    <div class="text-muted small mt-1"><?= e(mb_strimwidth($t['description'],0,100,'…')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-1">
                    <a href="<?= APP_URL ?>/todos.php?id=<?= $t['id'] ?>" class="btn-sm-icon" title="Open task"><i class="fas fa-external-link-alt"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB: TIMELINE
══════════════════════════════════════════════════════════════════════════ -->
<div id="tab-timeline">
<?php if (empty($tlEvents)): ?>
<div class="cc-card"><div class="cc-card-body"><div class="empty-state py-5"><i class="fas fa-timeline"></i><br>No activity yet</div></div></div>
<?php else: ?>

<div class="tl-wrap">
<?php
$nc = ['note'=>'secondary','issue'=>'danger','feedback'=>'info','resolution'=>'success','query'=>'warning','followup'=>'primary','internal'=>'dark','reply'=>'secondary'];

foreach ($tlEvents as $ev):
    $type = $ev['type'];
    $d    = $ev['data'];
    $kids = $ev['children'];

    /* ── CALL ── */
    if ($type === 'call'):
        $dir     = $d['call_direction'] ?? 'unknown';
        $dotCls  = $dir==='outbound' ? 'tl-dot-out' : ($dir==='inbound' ? 'tl-dot-in' : 'tl-dot-unk');
        $cardCls = $dir==='outbound' ? 'tl-card-call-out' : ($dir==='inbound' ? 'tl-card-call-in' : 'tl-card-call-unk');
        $dCls    = dispositionClass($d['disposition']);
?>
<div class="tl-node">
    <div class="tl-dot <?= $dotCls ?>"><i class="fas <?= directionIcon($dir) ?>"></i></div>
    <div class="tl-body">
        <div class="tl-card <?= $cardCls ?>">
            <span class="tl-lbl"><?= $dir==='outbound'?'OUT':'IN' ?></span>
            <span class="badge bg-<?= $dCls ?> badge-xs"><?= e($d['disposition']) ?></span>
            <?php if ($d['billsec']): ?><span class="tl-dur"><i class="fas fa-clock"></i> <?= formatDuration($d['billsec']) ?></span><?php endif; ?>
            <span class="tl-nums"><?= e($d['src']??'') ?> → <?= e($d['dst']??'') ?></span>
            <?php if ($d['agent_name']): ?><span class="tl-dur"><i class="fas fa-headset"></i> <?= e($d['agent_name']) ?></span><?php endif; ?>
            <?php if ($d['recordingfile']): ?><button class="tl-pbtn" onclick="playRecording(<?= $d['id'] ?>, <?= j($d['recordingfile']) ?>)" title="Play"><i class="fas fa-play-circle"></i></button><?php endif; ?>
            <span class="tl-ts"><?= formatDt($d['calldate'],'d M y, h:i A') ?></span>
            <a class="tl-pbtn" href="<?= APP_URL ?>/call_detail.php?id=<?= $d['id'] ?>" title="View Detail" target="_blank" style="color:var(--muted);margin-left:auto"><i class="fas fa-external-link-alt"></i></a>
        </div>
        <?php if ($kids): ?>
        <div class="tl-kids">
        <?php foreach ($kids as $cn): $cc = $nc[$cn['note_type']??'note']??'secondary'; ?>
            <div class="tl-kid">
                <span class="badge bg-<?= $cc ?> badge-xs"><?= $cn['note_type'] ?></span>
                <span style="font-size:.7rem;color:var(--muted)"><?= e($cn['full_name']) ?></span>
                <span class="tl-kid-txt"><?= e(mb_strimwidth($cn['content']??'',0,120,'…')) ?></span>
                <span class="tl-ts" style="margin-left:auto"><?= timeAgo($cn['created_at']) ?></span>
            </div>
            <?php if (!empty($cn['replies'])): ?>
            <div class="tl-gkids">
            <?php foreach ($cn['replies'] as $rp): ?>
                <div class="tl-gkid">
                    <i class="fas fa-reply" style="color:var(--muted);font-size:.6rem;margin-top:2px"></i>
                    <span style="font-size:.68rem;color:var(--muted)"><?= e($rp['full_name']) ?></span>
                    <span class="tl-gkid-txt"><?= e(mb_strimwidth($rp['content']??'',0,100,'…')) ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

    <?php elseif ($type === 'contact_note'):
        $cc = $nc[$d['note_type']??'note']??'secondary';
?>
<div class="tl-node">
    <div class="tl-dot tl-dot-note"><i class="fas fa-comment"></i></div>
    <div class="tl-body">
        <div class="tl-card tl-card-note">
            <span class="tl-lbl">Note</span>
            <span class="badge bg-<?= $cc ?> badge-xs"><?= $d['note_type'] ?></span>
            <?php if (!empty($d['priority']) && $d['priority']!=='low'): ?><span class="badge bg-<?= priorityClass($d['priority']) ?> badge-xs"><?= $d['priority'] ?></span><?php endif; ?>
            <?php if (!empty($d['is_pinned'])): ?><i class="fas fa-thumbtack text-warning" style="font-size:.7rem"></i><?php endif; ?>
            <span style="font-size:.7rem;color:var(--muted)"><?= e($d['full_name']) ?></span>
            <span class="tl-ts"><?= formatDt($d['created_at'],'d M y, h:i A') ?></span>
        </div>
        <div class="tl-txt"><?= e(mb_strimwidth($d['content']??'',0,160,'…')) ?></div>
        <?php if ($kids): ?>
        <div class="tl-kids">
        <?php foreach ($kids as $rp): $rc = $nc[$rp['note_type']??'reply']??'secondary'; ?>
            <div class="tl-kid">
                <i class="fas fa-reply" style="color:var(--muted);font-size:.6rem;margin-top:2px"></i>
                <span class="badge bg-<?= $rc ?> badge-xs"><?= $rp['note_type'] ?></span>
                <span style="font-size:.7rem;color:var(--muted)"><?= e($rp['full_name']) ?></span>
                <span class="tl-kid-txt"><?= e(mb_strimwidth($rp['content']??'',0,120,'…')) ?></span>
                <span class="tl-ts" style="margin-left:auto"><?= timeAgo($rp['created_at']) ?></span>
            </div>
            <?php if (!empty($rp['replies'])): ?>
            <div class="tl-gkids">
            <?php foreach ($rp['replies'] as $rp2): ?>
                <div class="tl-gkid">
                    <i class="fas fa-reply" style="color:var(--muted);font-size:.6rem;margin-top:2px"></i>
                    <span style="font-size:.68rem;color:var(--muted)"><?= e($rp2['full_name']) ?></span>
                    <span class="tl-gkid-txt"><?= e(mb_strimwidth($rp2['content']??'',0,100,'…')) ?></span>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

    <?php elseif ($type === 'task'):
        $sCls  = ['pending'=>'secondary','in_progress'=>'primary','done'=>'success','cancelled'=>'dark'][$d['status']??'pending']??'secondary';
        $sIcon = ['pending'=>'fa-clock','in_progress'=>'fa-spinner','done'=>'fa-check','cancelled'=>'fa-ban'][$d['status']??'pending']??'fa-clock';
?>
<div class="tl-node">
    <div class="tl-dot tl-dot-task"><i class="fas fa-list-check"></i></div>
    <div class="tl-body">
        <div class="tl-card tl-card-task">
            <span class="tl-lbl">Task</span>
            <span class="badge bg-<?= $sCls ?> badge-xs"><i class="fas <?= $sIcon ?> me-1"></i><?= str_replace('_',' ',ucfirst($d['status']??'pending')) ?></span>
            <span class="badge bg-<?= priorityClass($d['priority']??'medium') ?> badge-xs"><?= $d['priority']??'medium' ?></span>
            <span style="font-size:.74rem;color:var(--text);<?= ($d['status']??'')==='done'?'text-decoration:line-through':'' ?>"><?= e($d['title']??'') ?></span>
            <span class="tl-ts"><?= formatDt($ev['date'],'d M y') ?></span>
        </div>
        <div class="tl-detail">
            <i class="fas fa-user"></i> <?= e($d['assigned_name']??'') ?>
            <?php if (!empty($d['due_date'])): ?><i class="fas fa-calendar ms-1"></i> <?= formatDt($d['due_date'],'d M y') ?><?php endif; ?>
            <?php if (!empty($d['description'])): ?>· <span><?= e(mb_strimwidth($d['description'],0,80,'…')) ?></span><?php endif; ?>
        </div>
    </div>
</div>

<?php endif; endforeach; ?>
</div><!-- .tl-wrap -->
<?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     TAB: HISTORY
══════════════════════════════════════════════════════════════════════════ -->
<?php if ($histList): ?>
<div id="tab-history" style="display:none">
    <div class="cc-card">
        <div class="cc-card-head"><span><i class="fas fa-clock-rotate-left me-2"></i>Edit History</span></div>
        <div class="cc-card-body">
            <?php foreach($histList as $h): ?>
            <div class="hist-item">
                <div class="hist-dot mt-1"></div>
                <div class="flex-grow-1">
                    <div class="small">
                        <strong><?= e($h['full_name']) ?></strong>
                        changed <code><?= e($h['field_name']) ?></code>
                        <span class="text-muted ms-2"><?= timeAgo($h['edited_at']) ?></span>
                    </div>
                    <div class="small mt-1">
                        <span class="text-danger"><?= e(mb_strimwidth($h['old_value']??'(empty)',0,60,'…')) ?></span>
                        <i class="fas fa-arrow-right mx-2 text-muted"></i>
                        <span class="text-success"><?= e(mb_strimwidth($h['new_value']??'(empty)',0,60,'…')) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Audio bar ──────────────────────────────────────────────────────────── -->
<div class="audio-bar" id="audioBar" style="display:none">
    <div class="audio-info" id="audioInfo">Loading…</div>
    <audio id="audioPlayer" controls style="flex:1;min-width:0">Your browser does not support audio.</audio>
    <a id="audioDlBtn" href="#" class="btn-icon" title="Download"><i class="fas fa-download"></i></a>
    <button class="btn-icon" onclick="closeAudio()"><i class="fas fa-times"></i></button>
</div>

<script>
const APP_URL    = '<?= APP_URL ?>';
const CONTACT_ID = <?= $id ?>;
let cnReplyToId  = null;

document.addEventListener('DOMContentLoaded', () => {
    initContactSuggest(document.getElementById('ef_company'), 'company');
    initContactSuggest(document.getElementById('ef_type_name'), 'type');
    initContactSuggest(document.getElementById('ef_group_name'), 'group');
});

/* ── Tab switching ───────────────────────────────────────────────────────── */
function switchTab(name) {
    ['calls','notes','tasks','timeline','history'].forEach(t => {
        const el = document.getElementById('tab-' + t);
        const btn = document.getElementById('tab-btn-' + t);
        if (el) el.style.display = t === name ? '' : 'none';
        if (btn) btn.classList.toggle('active', t === name);
    });
}

/* ── Edit mode toggle ────────────────────────────────────────────────────── */
function toggleEditMode() {
    const view = document.getElementById('profileView');
    const edit = document.getElementById('profileEdit');
    const btn  = document.getElementById('editToggleBtn');
    const show = edit.style.display === 'none';
    edit.style.display = show ? '' : 'none';
    btn.innerHTML = show
        ? '<i class="fas fa-times me-1"></i>Cancel'
        : '<i class="fas fa-pen me-1"></i>Edit';
    if (show) edit.scrollIntoView({behavior:'smooth', block:'nearest'});
}

/* ── Save contact ─────────────────────────────────────────────────────────── */
function saveContact() {
    const data = {
        action:      'update',
        id:          CONTACT_ID,
        name:        document.getElementById('ef_name').value,
        phone:       document.getElementById('ef_phone').value,
        company:     document.getElementById('ef_company').value,
        email:       document.getElementById('ef_email').value,
        address:     document.getElementById('ef_address').value,
        scope:       document.getElementById('ef_scope').value,
        office_type: document.getElementById('ef_office_type').value,
        type_name:   document.getElementById('ef_type_name').value.trim(),
        group_name:  document.getElementById('ef_group_name').value.trim(),
        assigned_to: document.getElementById('ef_assigned_to').value || null,
        is_favorite: document.getElementById('ef_is_favorite').checked ? 1 : 0,
        is_blocked:  document.getElementById('ef_is_blocked').checked  ? 1 : 0,
        notes:       document.getElementById('ef_notes').value,
    };
    fetch(APP_URL + '/api/contacts.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    }).then(r=>r.json()).then(d => {
        if (d.ok) { showToast('Contact saved','success'); setTimeout(()=>location.reload(),700); }
        else showToast(d.error || 'Save failed','danger');
    });
}

/* ── Quick favorite toggle ────────────────────────────────────────────────── */
let isFav = <?= (int)$contact['is_favorite'] ?>;
function quickToggleFav() {
    isFav = isFav ? 0 : 1;
    const icon = document.getElementById('favBtn').querySelector('i');
    icon.className = (isFav ? 'fas' : 'far') + ' fa-star text-warning fa-lg';
    fetch(APP_URL + '/api/contacts.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'favorite', id:CONTACT_ID, is_favorite:isFav})
    }).then(r=>r.json()).then(d=>{ if(!d.ok) showToast('Failed','danger'); });
}

/* ── Call notes inline ───────────────────────────────────────────────────── */
const callNotesLoaded = {};

function toggleCallNotes(callId, btn) {
    const panel   = document.getElementById('cnp-' + callId);
    const visible = panel.style.display !== 'none';
    panel.style.display = visible ? 'none' : '';
    btn.querySelector('i').className = visible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
    if (!visible && !callNotesLoaded[callId]) loadCallNotes(callId);
}

function loadCallNotes(callId) {
    fetch(APP_URL + '/api/notes.php?action=list&entity=call&entity_id=' + callId)
        .then(r => r.json())
        .then(d => {
            callNotesLoaded[callId] = true;
            const el = document.getElementById('cnl-' + callId);
            if (!d.ok) { el.innerHTML = '<div class="text-danger small py-1">Error loading notes</div>'; return; }
            if (!d.notes.length) { el.innerHTML = '<div class="text-muted small py-1">No notes for this call yet.</div>'; return; }
            el.innerHTML = d.notes.map(n => renderCallNoteHTML(n)).join('');
        });
}

function renderCallNoteHTML(n) {
    const colors = {note:'secondary',issue:'danger',feedback:'info',resolution:'success',query:'warning',followup:'primary',internal:'dark'};
    const color = colors[n.note_type] || 'secondary';
    const pinned = n.is_pinned == 1 ? '<i class="fas fa-thumbtack text-warning ms-1"></i>' : '';
    const content = (n.content || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
    return `<div class="call-note-item">
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <span class="fw-semibold small">${escHTML(n.full_name)}</span>
            <span class="badge bg-${color}">${n.note_type}</span>${pinned}
            <span class="text-muted small ms-auto">${relativeTime(n.created_at)}</span>
        </div>
        <div class="small">${content}</div>
    </div>`;
}

function addCallNote(callId) {
    const content  = document.getElementById('cnatxt-' + callId).value.trim();
    const noteType = document.getElementById('cnatype-' + callId).value;
    const priority = document.getElementById('cnapri-' + callId).value;
    if (!content) { showToast('Note cannot be empty','warning'); return; }
    fetch(APP_URL + '/api/notes.php', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'add', entity:'call', entity_id:callId, content, note_type:noteType, priority})
    }).then(r=>r.json()).then(d => {
        if (d.ok) {
            document.getElementById('cnatxt-' + callId).value = '';
            callNotesLoaded[callId] = false;
            loadCallNotes(callId);
            const nc = document.getElementById('nc-' + callId);
            if (nc) nc.textContent = parseInt(nc.textContent || '0') + 1;
            showToast('Note added','success');
        } else showToast(d.error || 'Failed','danger');
    });
}

/* ── Contact notes ────────────────────────────────────────────────────────── */
function focusContactNote() {
    switchTab('notes');
    const el = document.getElementById('contactNoteCompose');
    el.scrollIntoView({behavior:'smooth'});
    setTimeout(() => document.getElementById('cnContent').focus(), 350);
}

function replyContactNote(parentId, name) {
    cnReplyToId = parentId;
    const b = document.getElementById('cnReplyingTo');
    b.textContent = 'Replying to ' + name;
    b.style.display = 'block';
    document.getElementById('cnCancelReply').style.display = '';
    document.getElementById('cnType').value = 'reply';
    focusContactNote();
}

function cancelCnReply() {
    cnReplyToId = null;
    document.getElementById('cnReplyingTo').style.display = 'none';
    document.getElementById('cnCancelReply').style.display = 'none';
    document.getElementById('cnType').value = 'note';
}

function submitContactNote() {
    const content = document.getElementById('cnContent').value.trim();
    if (!content) { showToast('Note cannot be empty','warning'); return; }
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'add', entity:'contact', entity_id:CONTACT_ID,
            parent_id: cnReplyToId, content,
            note_type: document.getElementById('cnType').value,
            priority:  document.getElementById('cnPriority').value
        })
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('cnContent').value = '';
            cancelCnReply();
            showToast('Note saved','success');
            reloadContactNotesList();
        } else showToast(d.error || 'Failed','danger');
    });
}

function reloadContactNotesList() {
    fetch(APP_URL + '/api/notes.php?action=list&entity=contact&entity_id=' + CONTACT_ID)
        .then(r=>r.json()).then(d=>{
            if (!d.ok) return;
            const tree = buildNoteTree(d.notes);
            const el   = document.getElementById('contactNotesList');
            el.innerHTML = tree.length
                ? tree.map(n => buildContactNoteHTML(n, 0)).join('')
                : '<div class="empty-state py-4"><i class="fas fa-comment-slash"></i> No notes yet.</div>';
        });
}

function buildNoteTree(notes) {
    const map = {}, roots = [];
    notes.forEach(n => { n.replies = []; map[n.id] = n; });
    notes.forEach(n => {
        if (n.parent_id && map[n.parent_id]) map[n.parent_id].replies.push(n);
        else if (!n.parent_id) roots.push(n);
    });
    return roots;
}

function buildContactNoteHTML(n, depth) {
    const colors = {note:'secondary',issue:'danger',feedback:'info',resolution:'success',
                    query:'warning',followup:'primary',internal:'dark',reply:'secondary'};
    const color = colors[n.note_type] || 'secondary';
    const pinIcon = n.is_pinned == 1 ? '<i class="fas fa-thumbtack text-warning ms-1"></i>' : '';
    const priB = n.priority && n.priority !== 'low' ? `<span class="badge bg-${priClass(n.priority)}">${n.priority}</span>` : '';
    const content = escHTML(n.content || '').replace(/\n/g,'<br>');
    const replies = (n.replies||[]).map(r => buildContactNoteHTML(r, depth+1)).join('');

    return `<div class="note-item ${depth?'note-reply':'note-root'} ${n.is_pinned==1?'note-pinned':''}" id="cnote-${n.id}">
        <div class="note-head">
            <div class="note-avatar"><i class="fas fa-user"></i></div>
            <div class="note-meta">
                <span class="note-author">${escHTML(n.full_name)}</span>
                <span class="badge bg-${color} note-type-badge">${n.note_type}</span>
                ${priB}${pinIcon}
            </div>
            <div class="note-time ms-auto small text-muted">${relativeTime(n.created_at)}</div>
        </div>
        <div class="note-body" id="cnbody-${n.id}">${content}</div>
        <div id="cnedit-${n.id}" style="display:none">
            <textarea class="form-control form-control-sm mb-2" id="cnetxt-${n.id}" rows="3">${escHTML(n.content||'')}</textarea>
            <button class="btn btn-xs btn-primary me-1" onclick="saveNoteEdit(${n.id})">Save</button>
            <button class="btn btn-xs btn-secondary" onclick="cancelNoteEdit(${n.id})">Cancel</button>
        </div>
        <div class="note-actions">
            <button class="btn-link small" onclick="replyContactNote(${n.id}, '${escHTML(n.full_name)}')"><i class="fas fa-reply me-1"></i>Reply</button>
            <button class="btn-link small" onclick="inlineEditNote(${n.id})"><i class="fas fa-pen me-1"></i>Edit</button>
            <button class="btn-link small" onclick="pinContactNote(${n.id}, ${n.is_pinned})">
                <i class="fas fa-thumbtack me-1"></i>${n.is_pinned==1?'Unpin':'Pin'}
            </button>
        </div>
        ${replies ? `<div class="note-replies">${replies}</div>` : ''}
        <div class="note-replies" id="cnreplies-${n.id}"></div>
    </div>`;
}

function priClass(p) {
    return {urgent:'danger',high:'warning',medium:'info',low:'secondary'}[p] || 'secondary';
}

/* ── Inline note edit ─────────────────────────────────────────────────────── */
function inlineEditNote(id) {
    document.getElementById('cnbody-' + id).style.display = 'none';
    document.getElementById('cnedit-' + id).style.display = '';
    document.getElementById('cnetxt-' + id).focus();
}
function cancelNoteEdit(id) {
    document.getElementById('cnbody-' + id).style.display = '';
    document.getElementById('cnedit-' + id).style.display = 'none';
}
function saveNoteEdit(id) {
    const content = document.getElementById('cnetxt-' + id).value.trim();
    if (!content) { showToast('Note cannot be empty','warning'); return; }
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'edit', entity:'contact', note_id:id, content})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('cnbody-' + id).innerHTML = escHTML(content).replace(/\n/g,'<br>');
            cancelNoteEdit(id);
            showToast('Note updated','success');
        } else showToast(d.error || 'Failed','danger');
    });
}
function pinContactNote(id, isPinned) {
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'pin', entity:'contact', note_id:id, is_pinned:isPinned?0:1})
    }).then(r=>r.json()).then(d=>{ if(d.ok) reloadContactNotesList(); });
}

/* ── Tasks ────────────────────────────────────────────────────────────────── */
function focusTaskForm() {
    switchTab('tasks');
    const el = document.getElementById('ct_title');
    el.scrollIntoView({behavior:'smooth'});
    setTimeout(() => el.focus(), 350);
}
function submitTask() {
    const title = document.getElementById('ct_title').value.trim();
    if (!title) { showToast('Title required','warning'); return; }
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:      'create',
            contact_id:  CONTACT_ID,
            title,
            description: document.getElementById('ct_desc').value.trim(),
            assigned_to: document.getElementById('ct_assign').value,
            priority:    document.getElementById('ct_priority').value,
            due_date:    document.getElementById('ct_due').value || null
        })
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            showToast('Task created','success');
            setTimeout(() => location.reload(), 700);
        } else showToast(d.error || 'Failed','danger');
    });
}
function updateTaskStatus(id, done) {
    const status = done ? 'done' : 'in_progress';
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'status', id, status})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            const row = document.getElementById('task-' + id);
            if (row) row.classList.toggle('done-task', done);
        } else showToast(d.error || 'Failed','danger');
    });
}

/* ── Calls filter ─────────────────────────────────────────────────────────── */
function filterCalls(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#callsTable .call-row').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        const show = !q || text.includes(q);
        tr.style.display = show ? '' : 'none';
        const panel = document.getElementById('cnp-' + tr.id.replace('cr-',''));
        if (panel && !show) panel.style.display = 'none';
    });
}

/* ── Copy contact info ────────────────────────────────────────────────────── */
function copyContactInfo() {
    const lines = [
        'Contact: <?= addslashes($contact['name'] ?: '—') ?>',
        'Phone: <?= e($contact['phone']) ?>',
        'Company: <?= addslashes($contact['company'] ?: '—') ?>',
        'Email: <?= addslashes($contact['email'] ?: '—') ?>',
        'Type: <?= addslashes($contact['type_name'] ?: '—') ?>',
        'Group: <?= addslashes($contact['group_name'] ?: '—') ?>',
        'Scope: <?= ucfirst($contact['scope']) ?>',
    ].join('\n');
    navigator.clipboard.writeText(lines).then(() => showToast('Copied!','success'));
}

/* ── Audio ────────────────────────────────────────────────────────────────── */
function playRecording(id, file) {
    const src = APP_URL + '/api/audio.php?id=' + id;
    document.getElementById('audioInfo').textContent = 'Call #' + id;
    document.getElementById('audioDlBtn').href = src + '&dl=1';
    const player = document.getElementById('audioPlayer');
    player.src = src;
    document.getElementById('audioBar').style.display = 'flex';
    player.play().catch(() => {});
}
function closeAudio() {
    document.getElementById('audioPlayer').pause();
    document.getElementById('audioBar').style.display = 'none';
}

/* ── Helpers ──────────────────────────────────────────────────────────────── */
function escHTML(s) {
    return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function relativeTime(dt) {
    if (!dt) return '';
    const diff = Math.round((Date.now() - new Date(dt).getTime()) / 1000);
    if (diff < 60)   return 'just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}
</script>

<?php require_once 'includes/footer.php'; ?>
