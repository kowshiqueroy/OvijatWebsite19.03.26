<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Call Logs';
$activePage = 'calls';
$aid        = agentId();

// ── Filters ───────────────────────────────────────────────────────────────────
$f_date_from  = $_GET['date_from'] ?? '';
$f_date_to    = $_GET['date_to']   ?? '';
$f_disp       = $_GET['disposition'] ?? '';
$f_dir        = $_GET['direction']   ?? '';
$f_mark       = $_GET['mark']        ?? '';
$f_search     = trim($_GET['q']      ?? '');
$f_my         = isset($_GET['my'])   ? (int)$_GET['my'] : 0;
$f_my_notes   = isset($_GET['my_notes']) ? 1 : 0;
$f_agent      = (int)($_GET['agent'] ?? 0);
$f_contact    = (int)($_GET['contact'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = (int)getSetting('calls_per_page', '50');
$offset       = ($page - 1) * $perPage;

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where   = ["1=1"];
$params  = [];
$types   = '';

if ($f_date_from) { $where[] = "DATE(cl.calldate) >= ?"; $params[] = $f_date_from; $types .= 's'; }
if ($f_date_to)   { $where[] = "DATE(cl.calldate) <= ?"; $params[] = $f_date_to;   $types .= 's'; }
if ($f_disp)      { $where[] = "cl.disposition = ?";     $params[] = $f_disp;      $types .= 's'; }
if ($f_dir)       { $where[] = "cl.call_direction = ?";  $params[] = $f_dir;       $types .= 's'; }
if ($f_mark)      { $where[] = "cl.call_mark = ?";       $params[] = $f_mark;      $types .= 's'; }
if ($f_my)        { $where[] = "cl.agent_id = ?";        $params[] = $aid;         $types .= 'i'; }
if ($f_agent)     { $where[] = "cl.agent_id = ?";        $params[] = $f_agent;     $types .= 'i'; }
if ($f_contact) {
    // Match by contact_id OR by the contact's phone number (catches unlinked calls)
    $contactPhone = $conn->query("SELECT phone FROM contacts WHERE id=$f_contact LIMIT 1")->fetch_assoc()['phone'] ?? '';
    if ($contactPhone) {
        $where[]  = "(cl.contact_id = ? OR cl.src = ? OR cl.dst = ?)";
        $params[] = $f_contact; $params[] = $contactPhone; $params[] = $contactPhone;
        $types   .= 'iss';
    } else {
        $where[]  = "cl.contact_id = ?";
        $params[] = $f_contact; $types .= 'i';
    }
}
if ($f_my_notes)  { $where[] = "EXISTS(SELECT 1 FROM call_notes cn WHERE cn.call_id=cl.id AND cn.agent_id=?)"; $params[] = $aid; $types .= 'i'; }
if ($f_search) {
    $where[] = "(cl.src LIKE ? OR cl.dst LIKE ? OR cl.clid LIKE ? OR cl.cnam LIKE ? OR c.name LIKE ? OR cl.manual_notes LIKE ?)";
    $s = "%$f_search%";
    for ($i = 0; $i < 6; $i++) { $params[] = $s; $types .= 's'; }
}

$whereSQL = implode(' AND ', $where);

// ── Count ─────────────────────────────────────────────────────────────────────
$countSQL  = "SELECT COUNT(*) AS total FROM call_logs cl LEFT JOIN contacts c ON c.id=cl.contact_id WHERE $whereSQL";
$stmt      = $conn->prepare($countSQL);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total     = (int)$stmt->get_result()->fetch_assoc()['total'];
$totalPages = max(1, ceil($total / $perPage));

// ── Main query ────────────────────────────────────────────────────────────────
$sql = "SELECT cl.*,
               c.name  AS contact_name, c.company, c.scope AS contact_scope, c.is_favorite,
               a.full_name AS agent_name,
               (SELECT COUNT(*) FROM call_notes cn WHERE cn.call_id=cl.id) AS note_count,
               (SELECT COUNT(*) FROM todos t WHERE t.call_id=cl.id AND t.status NOT IN ('done','cancelled')) AS task_count
        FROM call_logs cl
        LEFT JOIN contacts c ON c.id = cl.contact_id
        LEFT JOIN agents a   ON a.id = cl.agent_id
        WHERE $whereSQL
        ORDER BY cl.calldate DESC
        LIMIT ? OFFSET ?";

$allParams  = array_merge($params, [$perPage, $offset]);
$allTypes   = $types . 'ii';
$stmt       = $conn->prepare($sql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$calls      = $stmt->get_result();

// ── Agents list for filter ────────────────────────────────────────────────────
$agents = getAllAgents();

// ── Summary counts for tab badges ─────────────────────────────────────────────
$today   = date('Y-m-d');
$tabCounts = [
    'all'       => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today'")->fetch_assoc()['c'],
    'answered'  => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND disposition='ANSWERED'")->fetch_assoc()['c'],
    'missed'    => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND disposition='NO ANSWER'")->fetch_assoc()['c'],
    'manual'    => (int)$conn->query("SELECT COUNT(*) AS c FROM call_logs WHERE DATE(calldate)='$today' AND is_manual=1")->fetch_assoc()['c'],
];

require_once 'includes/layout.php';
?>

<!-- ── Quick type tabs ────────────────────────────────────────────────────────── -->
<div class="quick-tabs mb-3">
    <?php
    $tabs = [
        ['', '',         'All Today',  $tabCounts['all'],      'secondary'],
        ['ANSWERED','',  'Answered',   $tabCounts['answered'], 'success'],
        ['NO ANSWER','', 'Missed',     $tabCounts['missed'],   'danger'],
        ['','inbound',   'Inbound',    null,                   'info'],
        ['','outbound',  'Outbound',   null,                   'primary'],
        ['','internal',  'Internal',   null,                   'secondary'],
        ['','',          'Manual',     $tabCounts['manual'],   'warning'],
    ];
    foreach ($tabs as [$d, $dir, $label, $cnt, $color]):
        $qs = http_build_query(array_filter(['date_from'=>$today,'date_to'=>$today,'disposition'=>$d,'direction'=>$dir]));
    ?>
    <a href="?<?= $qs ?>" class="qtab <?= ($f_disp===$d && $f_dir===$dir) ? 'active' : '' ?>">
        <?= $label ?>
        <?php if ($cnt !== null): ?><span class="qtab-badge bg-<?= $color ?>"><?= $cnt ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Filter bar ─────────────────────────────────────────────────────────────── -->
<div class="cc-card mb-3">
    <div class="cc-card-body p-3">
        <form method="GET" id="filterForm">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-sm-4 col-md-2">
                    <label class="form-label">From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($f_date_from) ?>">
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <label class="form-label">To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($f_date_to) ?>">
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <label class="form-label">Status</label>
                    <select name="disposition" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (['ANSWERED','NO ANSWER','BUSY','FAILED','CONGESTION'] as $d): ?>
                        <option value="<?= $d ?>" <?= $f_disp===$d?'selected':'' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <label class="form-label">Direction</label>
                    <select name="direction" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (['inbound','outbound','internal','unknown'] as $d): ?>
                        <option value="<?= $d ?>" <?= $f_dir===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <label class="form-label">Mark</label>
                    <select name="mark" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach (['normal','follow_up','callback','resolved','urgent','escalated','no_action'] as $m): ?>
                        <option value="<?= $m ?>" <?= $f_mark===$m?'selected':'' ?>><?= ucwords(str_replace('_',' ',$m)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <label class="form-label">Agent</label>
                    <select name="agent" class="form-select form-select-sm">
                        <option value="">All Agents</option>
                        <option value="<?= $aid ?>" <?= $f_agent===$aid?'selected':'' ?>>Me</option>
                        <?php foreach ($agents as $ag): ?>
                        <option value="<?= $ag['id'] ?>" <?= $f_agent===(int)$ag['id']?'selected':'' ?>><?= e($ag['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Number, name, notes…" value="<?= e($f_search) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                    <a href="calls.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
                <div class="col-auto ms-auto">
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="exportCalls()">
                        <i class="fas fa-file-csv me-1"></i>Export CSV
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyTable()">
                        <i class="fas fa-copy me-1"></i>Copy
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#quickCallModal">
                        <i class="fas fa-phone-plus me-1"></i>Log Call
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ── Results info ───────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-2">
    <span class="text-muted small">
        Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $total)) ?> of <?= number_format($total) ?> calls
    </span>
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">‹</a></li>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
            for ($p = $start; $p <= $end; $p++):
            ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">›</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- ── Calls table ───────────────────────────────────────────────────────────── -->
<div class="cc-card">
    <div class="table-responsive">
        <table class="table cc-table" id="callsTable">
            <thead>
                <tr>
                    <th width="30"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                    <th>Date / Time</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Direction</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Contact</th>
                    <th>Agent</th>
                    <th>Mark</th>
                    <th>Notes</th>
                    <th>Rec</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($calls->num_rows === 0): ?>
                <tr><td colspan="13" class="text-center py-5 text-muted">
                    <i class="fas fa-phone-slash fa-2x mb-2 d-block"></i>No calls found for this filter
                </td></tr>
            <?php endif; ?>
            <?php while ($c = $calls->fetch_assoc()):
                $dispClass = dispositionClass($c['disposition'] ?? '');
                $dispIcon  = dispositionIcon($c['disposition'] ?? '');
                $dirIcon   = directionIcon($c['call_direction'] ?? 'unknown');
                $markCls   = markClass($c['call_mark'] ?? 'normal');
            ?>
            <tr class="call-row <?= $c['disposition']==='NO ANSWER'?'row-missed':'' ?>"
                data-id="<?= $c['id'] ?>"
                data-src="<?= e($c['src']) ?>"
                data-dst="<?= e($c['dst']) ?>">
                <td><input type="checkbox" class="row-check" value="<?= $c['id'] ?>"></td>
                <td class="text-nowrap">
                    <div class="fw-medium">
                        <?= formatDt($c['calldate'], 'd M Y') ?>
                        <?php if ($c['recordingfile']): ?>
                        <i class="fas fa-microphone text-success ms-1" style="font-size:.7rem" title="Has recording"></i>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small"><?= formatDt($c['calldate'], 'h:i A') ?></div>
                    <?php if ($c['is_manual']): ?>
                    <span class="badge bg-warning text-dark" style="font-size:.65rem">Manual</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="fw-medium"><?= e($c['src'] ?: '—') ?></div>
                    <?php if ($c['cnam']): ?><div class="text-muted small"><?= e($c['cnam']) ?></div><?php endif; ?>
                </td>
                <td>
                    <div class="fw-medium"><?= e($c['dst'] ?: '—') ?></div>
                    <?php if ($c['dst_cnam']): ?><div class="text-muted small"><?= e($c['dst_cnam']) ?></div><?php endif; ?>
                </td>
                <td class="text-center">
                    <i class="fas <?= $dirIcon ?> text-<?= $dispClass ?>" title="<?= e($c['call_direction']) ?>"></i>
                </td>
                <td>
                    <span class="badge bg-<?= $dispClass ?>">
                        <i class="fas <?= $dispIcon ?> me-1"></i><?= e($c['disposition'] ?: '—') ?>
                    </span>
                </td>
                <td class="text-nowrap">
                    <span title="Total: <?= formatDuration($c['duration']) ?>">
                        <?= formatDuration($c['billsec'] ?: 0) ?>
                    </span>
                </td>
                <td>
                    <?php if ($c['contact_id']): ?>
                    <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $c['contact_id'] ?>" class="contact-link">
                        <?php if ($c['is_favorite']): ?><i class="fas fa-star text-warning me-1"></i><?php endif; ?>
                        <?= e($c['contact_name'] ?: $c['src']) ?>
                    </a>
                    <?php if ($c['company']): ?><div class="text-muted small"><?= e($c['company']) ?></div><?php endif; ?>
                    <?php else: ?>
                    <button class="btn-link text-muted small" onclick="quickCreateContact('<?= e($c['src']) ?>',<?= $c['id'] ?>)">
                        <i class="fas fa-user-plus"></i> Add contact
                    </button>
                    <?php endif; ?>
                </td>
                <td><?= $c['agent_name'] ? e($c['agent_name']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <span class="badge bg-<?= $markCls ?> call-mark-badge" style="cursor:pointer"
                          onclick="openMarkMenu(<?= $c['id'] ?>, this)"
                          title="Click to change mark">
                        <?= ucwords(str_replace('_',' ', $c['call_mark'] ?? 'normal')) ?>
                    </span>
                </td>
                <td class="text-center">
                    <?php if ($c['note_count'] > 0): ?>
                    <span class="badge bg-info"><?= $c['note_count'] ?></span>
                    <?php endif; ?>
                    <?php if ($c['task_count'] > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $c['task_count'] ?> task</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($c['recordingfile']): ?>
                    <button class="btn-sm-icon text-success" title="Play recording"
                            onclick="playRecording(<?= $c['id'] ?>, '<?= e($c['recordingfile']) ?>')">
                        <i class="fas fa-play-circle"></i>
                    </button>
                    <a href="<?= APP_URL ?>/api/audio.php?id=<?= $c['id'] ?>&dl=1" class="btn-sm-icon" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <a href="<?= APP_URL ?>/call_detail.php?id=<?= $c['id'] ?>" class="btn-sm-icon" title="View detail">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button class="btn-sm-icon" title="Add note" onclick="quickNote(<?= $c['id'] ?>)">
                        <i class="fas fa-comment-plus"></i>
                    </button>
                    <button class="btn-sm-icon" title="Add task" onclick="quickTask(<?= $c['id'] ?>,<?= $c['contact_id']??0 ?>)">
                        <i class="fas fa-list-check"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Audio player bar ───────────────────────────────────────────────────────── -->
<div class="audio-bar" id="audioBar" style="display:none">
    <div class="audio-info" id="audioInfo">Loading…</div>
    <audio id="audioPlayer" controls style="flex:1;min-width:0">
        Your browser does not support audio.
    </audio>
    <button class="btn-icon" onclick="closeAudio()"><i class="fas fa-times"></i></button>
</div>

<!-- ── Mark menu (floating) ──────────────────────────────────────────────────── -->
<div class="mark-menu" id="markMenu" style="display:none">
    <?php foreach (['normal','follow_up','callback','resolved','urgent','escalated','no_action'] as $m): ?>
    <button onclick="setMark(currentMarkCallId, '<?= $m ?>')" class="mark-opt mark-opt-<?= $m ?>">
        <?= ucwords(str_replace('_',' ', $m)) ?>
    </button>
    <?php endforeach; ?>
</div>

<!-- ── Quick note modal ───────────────────────────────────────────────────────── -->
<div class="modal fade" id="quickNoteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-comment-plus me-2"></i>Add Note</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="qnCallId">
                <div class="mb-2 row g-2">
                    <div class="col">
                        <label class="form-label small">Type</label>
                        <select id="qnType" class="form-select form-select-sm">
                            <option value="note">Note</option>
                            <option value="issue">Issue</option>
                            <option value="feedback">Feedback</option>
                            <option value="followup">Follow-up</option>
                            <option value="query">Query</option>
                            <option value="resolution">Resolution</option>
                            <option value="internal">Internal</option>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label small">Priority</label>
                        <select id="qnPriority" class="form-select form-select-sm">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <textarea id="qnContent" class="form-control" rows="4" placeholder="Your note…"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitQuickNote()">
                    <i class="fas fa-save me-1"></i>Save Note
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Quick task modal ───────────────────────────────────────────────────────── -->
<div class="modal fade" id="quickTaskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-list-check me-2"></i>Add Task</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="qtCallId">
                <input type="hidden" id="qtContactId">
                <div class="mb-2">
                    <label class="form-label small">Title <span class="text-danger">*</span></label>
                    <input type="text" id="qtTitle" class="form-control" placeholder="Task description">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col">
                        <label class="form-label small">Assign To</label>
                        <select id="qtAssign" class="form-select form-select-sm">
                            <option value="<?= $aid ?>">Me</option>
                            <?php foreach ($agents as $ag): ?>
                            <option value="<?= $ag['id'] ?>"><?= e($ag['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label small">Priority</label>
                        <select id="qtPriority" class="form-select form-select-sm">
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Due Date</label>
                    <input type="datetime-local" id="qtDue" class="form-control form-control-sm">
                </div>
                <textarea id="qtDesc" class="form-control form-control-sm" rows="2" placeholder="Details (optional)"></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitQuickTask()">
                    <i class="fas fa-save me-1"></i>Create Task
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';
let currentMarkCallId = null;

// Mark menu
function openMarkMenu(id, el) {
    currentMarkCallId = id;
    const menu = document.getElementById('markMenu');
    const rect = el.getBoundingClientRect();
    menu.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
    menu.style.left = rect.left + 'px';
    menu.style.display = 'block';
}
function setMark(id, mark) {
    document.getElementById('markMenu').style.display = 'none';
    fetch(APP_URL + '/api/calls.php', {
        method: 'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'mark', id, mark})
    }).then(r=>r.json()).then(d => {
        if (d.ok) { showToast('Marked: ' + mark.replace('_',' '), 'success'); setTimeout(()=>location.reload(),700); }
        else showToast(d.error || 'Error', 'danger');
    });
}
document.addEventListener('click', e => {
    if (!e.target.closest('#markMenu') && !e.target.closest('.call-mark-badge'))
        document.getElementById('markMenu').style.display = 'none';
});

// Quick note
function quickNote(callId) {
    document.getElementById('qnCallId').value = callId;
    document.getElementById('qnContent').value = '';
    new bootstrap.Modal(document.getElementById('quickNoteModal')).show();
    setTimeout(() => document.getElementById('qnContent').focus(), 300);
}
function submitQuickNote() {
    const callId   = document.getElementById('qnCallId').value;
    const content  = document.getElementById('qnContent').value.trim();
    const type     = document.getElementById('qnType').value;
    const priority = document.getElementById('qnPriority').value;
    if (!content) { showToast('Please enter note text', 'warning'); return; }
    fetch(APP_URL + '/api/notes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'add', entity:'call', entity_id:callId, content, note_type:type, priority})
    }).then(r=>r.json()).then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('quickNoteModal')).hide();
            showToast('Note saved', 'success');
            setTimeout(()=>location.reload(),700);
        } else showToast(d.error || 'Error', 'danger');
    });
}

// Quick task
function quickTask(callId, contactId) {
    document.getElementById('qtCallId').value    = callId;
    document.getElementById('qtContactId').value = contactId;
    document.getElementById('qtTitle').value     = '';
    document.getElementById('qtDesc').value      = '';
    new bootstrap.Modal(document.getElementById('quickTaskModal')).show();
    setTimeout(() => document.getElementById('qtTitle').focus(), 300);
}
function submitQuickTask() {
    const data = {
        action: 'create',
        call_id:    document.getElementById('qtCallId').value,
        contact_id: document.getElementById('qtContactId').value,
        title:      document.getElementById('qtTitle').value.trim(),
        description:document.getElementById('qtDesc').value.trim(),
        assigned_to:document.getElementById('qtAssign').value,
        priority:   document.getElementById('qtPriority').value,
        due_date:   document.getElementById('qtDue').value,
    };
    if (!data.title) { showToast('Title is required', 'warning'); return; }
    fetch(APP_URL + '/api/todos.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)
    }).then(r=>r.json()).then(d => {
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('quickTaskModal')).hide();
            showToast('Task created', 'success');
            setTimeout(()=>location.reload(),700);
        } else showToast(d.error || 'Error', 'danger');
    });
}

// Quick create contact
function quickCreateContact(phone, callId) {
    if (!confirm('Create contact for ' + phone + '?')) return;
    fetch(APP_URL + '/api/contacts.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'create', phone, call_id:callId})
    }).then(r=>r.json()).then(d => {
        if (d.ok) { showToast('Contact created', 'success'); setTimeout(()=>location.reload(),700); }
        else showToast(d.error || 'Error','danger');
    });
}

// Audio playback
function playRecording(id, file) {
    const bar    = document.getElementById('audioBar');
    const player = document.getElementById('audioPlayer');
    const info   = document.getElementById('audioInfo');
    info.textContent = file;
    player.src  = APP_URL + '/api/audio.php?id=' + id;
    bar.style.display = 'flex';
    player.play().catch(()=>{});
}
function closeAudio() {
    const player = document.getElementById('audioPlayer');
    player.pause();
    document.getElementById('audioBar').style.display = 'none';
}

// Export / copy
function exportCalls() {
    const qs = new URLSearchParams(window.location.search);
    qs.set('export', 'csv');
    window.location = APP_URL + '/api/calls.php?' + qs.toString();
}
function copyTable() {
    const rows = [...document.querySelectorAll('#callsTable tr')];
    const text = rows.map(r => [...r.querySelectorAll('th,td')].map(c => c.innerText.trim()).join('\t')).join('\n');
    navigator.clipboard.writeText(text).then(()=>showToast('Table copied to clipboard','success'));
}

// Select all
function toggleSelectAll(cb) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
}
</script>

<?php require_once 'includes/footer.php'; ?>
