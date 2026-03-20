<?php
require_once 'config.php';
requireLogin();
$aid = agentId();

$exists = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='call_logs' AND column_name='local_recording'")->fetch_row();
if (!$exists) @$conn->query("ALTER TABLE call_logs ADD COLUMN local_recording VARCHAR(500) DEFAULT NULL AFTER recordingfile");

$highlight = (int)($_GET['highlight'] ?? 0);
$dateFrom  = $_GET['from']  ?? date('Y-m-01');
$dateTo    = $_GET['to']    ?? date('Y-m-d');
$status    = $_GET['status'] ?? 'all';
$direction = $_GET['dir']   ?? 'all';
$search    = trim($_GET['q'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 50;
$offset    = ($page - 1) * $perPage;

$where = ["cl.recordingfile IS NOT NULL AND cl.recordingfile != ''"];
$params = [];
$types = '';

if ($dateFrom) { $where[] = "DATE(cl.calldate) >= ?"; $params[] = $dateFrom; $types .= 's'; }
if ($dateTo)   { $where[] = "DATE(cl.calldate) <= ?"; $params[] = $dateTo;   $types .= 's'; }
if ($status === 'local')  { $where[] = "cl.local_recording IS NOT NULL AND cl.local_recording != ''"; }
if ($status === 'remote')  { $where[] = "(cl.local_recording IS NULL OR cl.local_recording = '')"; }
if ($direction !== 'all') { $where[] = "cl.call_direction = ?"; $params[] = $direction; $types .= 's'; }
if ($search) {
    $where[] = "(c.name LIKE ? OR c.phone LIKE ? OR cl.src LIKE ? OR cl.dst LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= 'ssss';
}

$whereSql = implode(' AND ', $where);

// Use prepared statement for COUNT too
$countSql = "SELECT COUNT(*) as c FROM call_logs cl LEFT JOIN contacts c ON c.id=cl.contact_id WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$countRes = $countStmt->get_result()->fetch_assoc();
$total = (int)$countRes['c'];
$countStmt->close();

$rows = [];
$stmt = $conn->prepare(
    "SELECT cl.id, cl.calldate, cl.src, cl.dst, cl.disposition, cl.call_direction,
            cl.duration, cl.recordingfile, cl.local_recording,
            c.name AS contact_name, c.phone AS contact_phone,
            a.full_name AS agent_name
     FROM call_logs cl
     LEFT JOIN contacts c ON c.id = cl.contact_id
     LEFT JOIN agents a ON a.id = cl.agent_id
     WHERE $whereSql
     ORDER BY cl.calldate DESC
     LIMIT $perPage OFFSET $offset"
);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

$localCount  = (int)$conn->query(
    "SELECT COUNT(*) c FROM call_logs cl WHERE recordingfile IS NOT NULL AND recordingfile != '' AND local_recording IS NOT NULL AND local_recording != ''"
)->fetch_assoc()['c'];
$remoteCount  = (int)$conn->query(
    "SELECT COUNT(*) c FROM call_logs cl WHERE recordingfile IS NOT NULL AND recordingfile != '' AND (local_recording IS NULL OR local_recording = '')"
)->fetch_assoc()['c'];
$totalCount  = $localCount + $remoteCount;

$totalPages = ceil($total / $perPage);

$pageTitle = 'Recordings';
$isRecordingsPage = true;
?>
<?php include 'includes/layout.php'; ?>

<div class="page-header">
    <div>
        <h5 class="page-title"><i class="fas fa-headphones me-2"></i>Recordings</h5>
        <p class="page-subtitle text-muted mb-0">
            <?= number_format($totalCount) ?> total recordings —
            <?= number_format($localCount) ?> saved locally —
            <?= number_format($remoteCount) ?> remote only
        </p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-primary" onclick="fetchAllVisible()" id="fetchAllBtn" disabled>
            <i class="fas fa-download me-1"></i>Fetch Selected (<span id="selCount">0</span>)
        </button>
        <button class="btn btn-sm btn-outline-success" onclick="fetchAllRemote()" id="fetchAllRemoteBtn">
            <i class="fas fa-cloud-download-alt me-1"></i>Fetch All Remote
        </button>
    </div>
</div>

<?php if ($highlight): ?>
<?php
$hl = $conn->query("SELECT cl.id, cl.calldate, cl.src, cl.dst, cl.disposition, cl.local_recording,
        c.name AS contact_name, c.phone AS contact_phone
        FROM call_logs cl LEFT JOIN contacts c ON c.id=cl.contact_id WHERE cl.id=$highlight LIMIT 1")->fetch_assoc();
?>
<?php if ($hl): ?>
<?php
$hasLocal = !empty($hl['local_recording']) && file_exists($hl['local_recording']);
$contactLabel = $hl['contact_name'] ? e($hl['contact_name']) . ' (' . e($hl['contact_phone']) . ')' : e($hl['contact_phone'] ?: $hl['src'] ?: $hl['dst']);
?>
<div class="alert alert-dismissible mb-3" style="background:#1a2a3a;border:1px solid #0dcaf0;color:#e0f4ff">
    <div class="d-flex align-items-start gap-3">
        <i class="fas fa-info-circle mt-1" style="color:#0dcaf0;flex-shrink:0"></i>
        <div class="flex-grow-1">
            <strong>Call #<?= $highlight ?></strong>
            — <?= formatDt($hl['calldate'], 'd M Y, h:i A') ?>
            — <?= e($hl['disposition']) ?>
            <?php if ($contactLabel): ?>
            — <span class="text-light"><?= $contactLabel ?></span>
            <?php endif; ?>
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <?php if ($hasLocal): ?>
                    <a href="<?= APP_URL ?>/api/audio.php?id=<?= $highlight ?>&dl=1" class="btn btn-sm btn-success">
                        <i class="fas fa-download me-1"></i>Download Recording
                    </a>
                <?php else: ?>
                    <button class="btn btn-sm btn-warning" onclick="downloadRecording(<?= $highlight ?>)">
                        <i class="fas fa-cloud-download-alt me-1"></i>Fetch &amp; Download from PBX
                    </button>
                <?php endif; ?>
            </div>
            <div class="mt-2 small" style="color:#9ac8e8">
                <i class="fas fa-tv me-1"></i>
                <strong>To play GSM recordings:</strong>
                Download the file, then open it with
                <strong>VLC Media Player</strong>
                (<a href="https://www.videolan.org/vlc/" target="_blank" style="color:#0dcaf0">free download</a>).
                Other players like Windows Media Player may not support GSM format.
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="filter-bar mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label small text-muted mb-1">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-1">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="all" <?= $status==='all'?'selected':'' ?>>All</option>
                <option value="local" <?= $status==='local'?'selected':'' ?>>Saved Locally</option>
                <option value="remote" <?= $status==='remote'?'selected':'' ?>>Remote Only</option>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label small text-muted mb-1">Direction</label>
            <select name="dir" class="form-select form-select-sm">
                <option value="all" <?= $direction==='all'?'selected':'' ?>>All</option>
                <option value="inbound" <?= $direction==='inbound'?'selected':'' ?>>Inbound</option>
                <option value="outbound" <?= $direction==='outbound'?'selected':'' ?>>Outbound</option>
            </select>
        </div>
        <div class="col">
            <label class="form-label small text-muted mb-1">Search</label>
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, phone, src, dst…" value="<?= e($search) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
            <a href="recordings.php" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></a>
        </div>
    </form>
</div>

<div class="cc-table-wrap mb-3">
    <table class="cc-table" id="recTable">
        <thead>
            <tr>
                <th style="width:36px">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)">
                </th>
                <th>Date / Time</th>
                <th>Direction</th>
                <th>Contact</th>
                <th>Party</th>
                <th>Duration</th>
                <th>Disposition</th>
                <th>Status</th>
                <th style="width:160px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No recordings found</td></tr>
        <?php else: foreach ($rows as $r): ?>
            <?php
                $hasLocal  = !empty($r['local_recording']) && file_exists($r['local_recording']);
                $localSize = $hasLocal ? filesize($r['local_recording']) : 0;
                $contact   = $r['contact_name'] ?: $r['contact_phone'] ?: '—';
                $contactId = '';
                if ($r['contact_phone']) {
                    $cr = $conn->query("SELECT id FROM contacts WHERE phone='" . $conn->real_escape_string($r['contact_phone']) . "' LIMIT 1");
                    $cr = $cr ? $cr->fetch_assoc() : null;
                    if ($cr) $contactId = $cr['id'];
                }
                $dirIcon  = $r['call_direction'] === 'inbound' ? 'fa-arrow-left text-info' : 'fa-arrow-right text-primary';
                $dirLabel = ucfirst($r['call_direction'] ?? '—');
                $dispCls  = $r['disposition'] === 'ANSWERED' ? 'success' : ($r['disposition'] === 'BUSY' ? 'warning' : 'secondary');
                $srcDisp  = e($r['src'] ?: '');
                $dstDisp  = e($r['dst'] ?: '');
                $durDisp  = $r['duration'] ? formatDuration($r['duration']) : '—';
            ?>
            <tr data-id="<?= $r['id'] ?>" data-local="<?= $hasLocal ? '1' : '0' ?>">
                <td>
                    <input type="checkbox" class="row-check" value="<?= $r['id'] ?>"
                        <?= $hasLocal ? 'checked' : '' ?>
                        onchange="updateSelCount()">
                </td>
                <td>
                    <div class="small"><?= formatDt($r['calldate'], 'd M y') ?></div>
                    <div class="text-muted" style="font-size:.72rem"><?= formatDt($r['calldate'], 'h:i A') ?></div>
                </td>
                <td>
                    <i class="fas <?= $dirIcon ?> me-1"></i><?= $dirLabel ?>
                </td>
                <td>
                    <?php if ($contactId): ?>
                        <a href="contact_detail.php?id=<?= $contactId ?>" class="text-decoration-none">
                            <div class="small fw-medium"><?= e($r['contact_name'] ?: $r['contact_phone']) ?></div>
                            <?php if ($r['contact_name']): ?><div class="text-muted" style="font-size:.72rem"><?= phoneLink($r['contact_phone'] ?? '') ?></div><?php endif; ?>
                        </a>
                    <?php else: ?>
                        <div class="small"><?= phoneLink($r['contact_phone'] ?? '', $r['contact_name'] ?: null) ?: '—' ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['src']): ?><div class="small"><?= phoneLink($r['src']) ?></div><?php endif; ?>
                    <?php if ($r['dst']): ?><div class="small"><?= phoneLink($r['dst']) ?></div><?php endif; ?>
                </td>
                <td class="small"><?= $durDisp ?></td>
                <td><span class="badge bg-<?= $dispCls ?> badge-xs"><?= e($r['disposition']) ?></span></td>
                <td>
                    <?php if ($hasLocal): ?>
                        <span class="badge bg-success badge-xs"><i class="fas fa-check-circle me-1"></i>Local</span>
                        <div class="text-muted" style="font-size:.68rem"><?= number_format($localSize) ?> bytes</div>
                    <?php else: ?>
                        <span class="badge bg-secondary badge-xs"><i class="fas fa-cloud me-1"></i>Remote</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-nowrap">
                        <?php if ($hasLocal): ?>
                            <a href="<?= APP_URL ?>/api/audio.php?id=<?= $r['id'] ?>&dl=1" class="btn-sm-icon text-success" title="Download (saved locally)">
                                <i class="fas fa-download"></i>
                            </a>
                        <?php else: ?>
                            <button class="btn-sm-icon text-warning" title="Download / Fetch from PBX"
                                onclick="downloadRecording(<?= $r['id'] ?>)">
                                <i class="fas fa-cloud-download-alt"></i>
                            </button>
                        <?php endif; ?>
                        <?php if ($contactId): ?>
                            <a href="call_detail.php?id=<?= $r['id'] ?>" class="btn-sm-icon" title="Call detail">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
<nav>
    <ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php
                $qs = http_build_query(array_filter([
                    'from'=>$dateFrom, 'to'=>$dateTo,
                    'status'=>$status!=='all'?$status:null,
                    'dir'=>$direction!=='all'?$direction:null,
                    'q'=>$search, 'page'=>$i
                ]));
            ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="recordings.php?<?= $qs ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<script>
// Mutex to prevent concurrent fetches
let _fetching = false;
let _pendingHighlight = null;

async function fetchRecording(id, btn) {
    if (!btn) btn = document.querySelector(`tr[data-id="${id}"] .btn-sm-icon`);
    const icon = btn.querySelector('i');
    btn.disabled = true;
    icon.className = 'fas fa-spin fa-spinner';

    try {
        const r = await fetch(APP_URL + '/api/fetch.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'fetch_recording', call_id: id})
        });
        const text = await r.text();

        // Try parsing — handle concatenated responses by finding first complete JSON object
        let d = null;
        const trimmed = text.trim();
        for (let i = 1; i <= trimmed.length; i++) {
            try { d = JSON.parse(trimmed.substring(0, i)); break; } catch {}
        }
        if (!d) { showToast('Server returned invalid response', 'danger'); icon.className = 'fas fa-download text-warning'; btn.disabled = false; return; }

        if (d.ok) {
            icon.className = 'fas fa-check text-success';
            const row = btn.closest('tr');
            row.dataset.local = '1';
            row.cells[7].innerHTML = `<span class="badge bg-success badge-xs"><i class="fas fa-check-circle me-1"></i>Local</span>` +
                (d.size ? `<div class="text-muted" style="font-size:.68rem">${d.size} bytes</div>` : '');
            row.cells[8].innerHTML = `
                <a href="${APP_URL}/api/audio.php?id=${id}&dl=1" class="btn-sm-icon text-success" title="Download">
                    <i class="fas fa-download"></i>
                </a>
                <a href="${APP_URL}/call_detail.php?id=${id}" class="btn-sm-icon" title="Call detail">
                    <i class="fas fa-external-link-alt"></i>
                </a>`;
            const kb = d.size ? Math.round(d.size/1024) : 0;
            showToast(d.already ? 'Already saved (' + kb + ' KB)' : 'Fetched: ' + kb + ' KB', 'success');
            // Resolve any pending highlight wait
            if (_pendingHighlight === id) { _pendingHighlight = null; }
        } else {
            icon.className = 'fas fa-download text-warning';
            btn.disabled = false;
            showToast(d.error || 'Failed to fetch', 'danger');
        }
    } catch (e) {
        icon.className = 'fas fa-download text-warning';
        btn.disabled = false;
        showToast('Network error: ' + e.message, 'danger');
    }
}

async function doBatchFetch(ids, btn, label) {
    if (!ids.length) { showToast('No recordings to fetch', 'warning'); return; }
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spin fa-spinner me-1"></i>Fetching ' + ids.length + '…';

    try {
        const r = await fetch(APP_URL + '/api/fetch.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'fetch_recordings_batch', call_ids: ids})
        });
        const text = await r.text();

        // Handle concatenated responses — find first complete JSON object
        let d = null;
        const trimmed = text.trim();
        for (let i = 1; i <= trimmed.length; i++) {
            try { d = JSON.parse(trimmed.substring(0, i)); break; } catch {}
        }
        if (!d) { showToast('Server returned invalid response', 'danger'); btn.disabled = false; btn.innerHTML = orig; return; }

        btn.disabled = false;
        btn.innerHTML = orig;

        let fetched = 0, failed = 0, failedErrors = [];
        for (const [cid, result] of Object.entries(d.results || {})) {
            const row = document.querySelector(`tr[data-id="${cid}"]`);
            if (!row) continue;
            if (result.ok) {
                fetched++;
                row.dataset.local = '1';
                row.cells[7].innerHTML = `<span class="badge bg-success badge-xs"><i class="fas fa-check-circle me-1"></i>Local</span>` +
                    (result.size ? `<div class="text-muted" style="font-size:.68rem">${result.size} bytes</div>` : '');
                const dlBtn = row.cells[8].querySelector('button');
                if (dlBtn) {
                    dlBtn.className = 'btn-sm-icon text-success';
                    dlBtn.title = 'Download (saved locally)';
                    dlBtn.onclick = () => downloadRecording(parseInt(cid));
                    dlBtn.innerHTML = '<i class="fas fa-download"></i>';
                }
            } else {
                failed++;
                if (failedErrors.length < 3) failedErrors.push(result.error || 'Failed');
            }
        }
        if (fetched > 0 && failed > 0) {
            showToast('Fetched: ' + fetched + ' ok, ' + failed + ' failed — ' + failedErrors.join(', '), 'warning');
        } else if (fetched > 0) {
            showToast('Fetched ' + fetched + ' recording(s) — ' + label, 'success');
        } else if (failed > 0) {
            showToast('Failed: ' + failed + ' — ' + failedErrors.join(', '), 'danger');
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = orig;
        showToast('Network error: ' + e.message, 'danger');
    }
}

function fetchAllVisible() {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(c => parseInt(c.value));
    doBatchFetch(ids, document.getElementById('fetchAllBtn'), 'selected');
}

function fetchAllRemote() {
    const hId = <?= $highlight ?: 0 ?>;
    const ids = [...document.querySelectorAll('.row-check:not(:checked)')].map(c => parseInt(c.value)).filter(id => id !== hId);
    if (!ids.length) { showToast('All visible recordings are already local', 'success'); return; }
    doBatchFetch(ids, document.getElementById('fetchAllRemoteBtn'), 'remote');
}

function toggleSelectAll(checked) {
    document.querySelectorAll('.row-check').forEach(c => c.checked = checked);
    updateSelCount();
}

function updateSelCount() {
    const count = document.querySelectorAll('.row-check:checked').length;
    document.getElementById('selCount').textContent = count;
    document.getElementById('fetchAllBtn').disabled = count === 0;
}

// Init
document.getElementById('selectAll').addEventListener('change', e => toggleSelectAll(e.target.checked));
updateSelCount();

// Auto-fetch highlighted recording if remote — queue it, batch waits for it
const highlightId = <?= $highlight ?: 0 ?>;
if (highlightId) {
    const row = document.querySelector(`tr[data-id="${highlightId}"]`);
    if (row && row.dataset.local === '0') {
        const cb = row.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = false;
        updateSelCount();
        _pendingHighlight = highlightId;
        const btn = row.querySelector('.btn-sm-icon');
        if (btn) fetchRecording(highlightId, btn);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
