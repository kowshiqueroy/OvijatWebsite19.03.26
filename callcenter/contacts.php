<?php
require_once 'config.php';
requireLogin();

$pageTitle  = 'Contacts';
$activePage = 'contacts';
$aid        = agentId();

// ── Filters ───────────────────────────────────────────────────────────────────
$f_search   = trim($_GET['q']       ?? '');
$f_type     = (int)($_GET['type']    ?? 0);
$f_group    = (int)($_GET['group']   ?? 0);
$f_scope    = $_GET['scope']         ?? '';
$f_fav      = isset($_GET['fav'])    ? 1 : 0;
$f_blocked  = isset($_GET['blocked'])? 1 : 0;
$f_assigned = (int)($_GET['assigned'] ?? 0);
$page       = max(1,(int)($_GET['page']??1));
$perPage    = 40;
$offset     = ($page-1)*$perPage;

$where  = ["c.status='active'"];
$params = [];
$types  = '';

if ($f_search) {
    $where[] = "(c.phone LIKE ? OR c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ?)";
    $s = "%$f_search%";
    $params = array_merge($params, [$s,$s,$s,$s]); $types .= 'ssss';
}
if ($f_type)     { $where[] = "c.type_id=?";     $params[] = $f_type;     $types .= 'i'; }
if ($f_group)    { $where[] = "c.group_id=?";    $params[] = $f_group;    $types .= 'i'; }
if ($f_scope)    { $where[] = "c.scope=?";       $params[] = $f_scope;    $types .= 's'; }
if ($f_assigned) { $where[] = "c.assigned_to=?"; $params[] = $f_assigned; $types .= 'i'; }
if ($f_fav)      { $where[] = "c.is_favorite=1"; }
if ($f_blocked)  { $where[] = "c.is_blocked=1"; }

$whereSQL = implode(' AND ', $where);

$countStmt = $conn->prepare("SELECT COUNT(*) AS t FROM contacts c WHERE $whereSQL");
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = (int)$countStmt->get_result()->fetch_assoc()['t'];
$totalPages = max(1, ceil($total / $perPage));

$stmt = $conn->prepare(
    "SELECT c.*, ct.name AS type_name, ct.color AS type_color,
            cg.name AS group_name, cg.color AS group_color,
            a.full_name AS assigned_name,
            (SELECT COUNT(*) FROM call_logs cl WHERE cl.contact_id=c.id) AS call_count,
            (SELECT MAX(cl.calldate) FROM call_logs cl WHERE cl.contact_id=c.id) AS last_call
     FROM contacts c
     LEFT JOIN contact_types  ct ON ct.id = c.type_id
     LEFT JOIN contact_groups cg ON cg.id = c.group_id
     LEFT JOIN agents a          ON a.id  = c.assigned_to
     WHERE $whereSQL
     ORDER BY c.is_favorite DESC, c.name ASC, c.created_at DESC
     LIMIT ? OFFSET ?"
);
$allP = array_merge($params, [$perPage, $offset]);
$allT = $types . 'ii';
$stmt->bind_param($allT, ...$allP);
$stmt->execute();
$contacts = $stmt->get_result();

$groups = $conn->query("SELECT * FROM contact_groups ORDER BY name");
$types_list = $conn->query("SELECT * FROM contact_types ORDER BY name");
$agents = getAllAgents();

require_once 'includes/layout.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <span class="text-muted"><?= number_format($total) ?> contacts</span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" onclick="openNewContact()">
            <i class="fas fa-user-plus me-1"></i>New Contact
        </button>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="fas fa-print me-1"></i>Print
        </button>
    </div>
</div>

<!-- Filter bar -->
<div class="cc-card mb-3">
    <div class="cc-card-body p-3">
        <form method="GET">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-3">
                    <input type="text" name="q" class="form-control form-control-sm"
                           placeholder="Search name, phone, company…" value="<?= e($f_search) ?>">
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <?php $types_list->data_seek(0); while ($t = $types_list->fetch_assoc()): ?>
                        <option value="<?= $t['id'] ?>" <?= $f_type===$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <select name="group" class="form-select form-select-sm">
                        <option value="">All Groups</option>
                        <?php $groups->data_seek(0); while ($g = $groups->fetch_assoc()): ?>
                        <option value="<?= $g['id'] ?>" <?= $f_group===$g['id']?'selected':'' ?>><?= e($g['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-6 col-sm-3 col-md-2">
                    <select name="scope" class="form-select form-select-sm">
                        <option value="">All Scopes</option>
                        <option value="internal"  <?= $f_scope==='internal'?'selected':'' ?>>Internal</option>
                        <option value="external"  <?= $f_scope==='external'?'selected':'' ?>>External</option>
                        <option value="unknown"   <?= $f_scope==='unknown'?'selected':'' ?>>Unknown</option>
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <select name="assigned" class="form-select form-select-sm">
                        <option value="0" <?= $f_assigned===0?'selected':'' ?>>All Agents</option>
                        <option value="<?= $aid ?>" <?= $f_assigned===$aid?'selected':'' ?>>My Contacts</option>
                        <?php foreach ($agents as $ag): if ($ag['id'] == $aid) continue; ?>
                        <option value="<?= $ag['id'] ?>" <?= $f_assigned===$ag['id']?'selected':'' ?>><?= e($ag['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="fav" class="form-check-input" id="favCheck" <?= $f_fav?'checked':'' ?>>
                        <label class="form-check-label small" for="favCheck"><i class="fas fa-star text-warning"></i> Fav</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="contacts.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Contacts grid / table toggle -->
<div class="cc-card">
    <div class="table-responsive">
        <table class="table cc-table" id="contactsTable">
            <thead>
                <tr>
                    <th style="width:32px"><input type="checkbox" id="selectAll" class="form-check-input" title="Select all"></th>
                    <th>Contact</th>
                    <th>Phone</th>
                    <th>Type</th>
                    <th>Group</th>
                    <th>Scope</th>
                    <th>Company</th>
                    <th>Assigned</th>
                    <th>Calls</th>
                    <th>Last Call</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$contacts->num_rows): ?>
                <tr><td colspan="10" class="text-center py-5 text-muted">
                    <i class="fas fa-address-book fa-2x mb-2 d-block"></i>No contacts found
                </td></tr>
            <?php endif; ?>
            <?php while ($c = $contacts->fetch_assoc()): ?>
            <tr class="<?= $c['is_blocked']?'contact-blocked':'' ?>">
                <td><input type="checkbox" class="form-check-input row-check" data-id="<?= $c['id'] ?>" onchange="updateBulkBar()"></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="contact-avatar-sm"><i class="fas fa-user"></i></div>
                        <div>
                            <div class="fw-medium">
                                <?php if ($c['is_favorite']): ?><i class="fas fa-star text-warning me-1 small"></i><?php endif; ?>
                                <?php if ($c['is_blocked']): ?><i class="fas fa-ban text-danger me-1 small"></i><?php endif; ?>
                                <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $c['id'] ?>" class="contact-link">
                                    <?= e($c['name'] ?: '(no name)') ?>
                                </a>
                            </div>
                            <?php if ($c['email']): ?><div class="text-muted small"><?= e($c['email']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="font-monospace"><?= e($c['phone']) ?></td>
                <td>
                    <?php if ($c['type_name']): ?>
                    <span class="badge" style="background:<?= e($c['type_color']) ?>"><?= e($c['type_name']) ?></span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <?php if ($c['group_name']): ?>
                    <span class="badge" style="background:<?= e($c['group_color']) ?>"><?= e($c['group_name']) ?></span>
                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-<?= $c['scope']==='internal'?'info':($c['scope']==='external'?'secondary':'light text-dark') ?>">
                        <?= ucfirst($c['scope']) ?>
                    </span>
                </td>
                <td><?= e($c['company'] ?: '—') ?></td>
                <td><?= $c['assigned_name'] ? e($c['assigned_name']) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-center">
                    <?php if ($c['call_count']): ?>
                    <a href="<?= APP_URL ?>/calls.php?contact=<?= $c['id'] ?>" class="badge bg-info">
                        <?= $c['call_count'] ?>
                    </a>
                    <?php else: ?><span class="text-muted">0</span><?php endif; ?>
                </td>
                <td class="text-muted small"><?= $c['last_call'] ? timeAgo($c['last_call']) : '—' ?></td>
                <td class="text-nowrap">
                    <a href="<?= APP_URL ?>/contact_detail.php?id=<?= $c['id'] ?>" class="btn-sm-icon" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <button class="btn-sm-icon" title="Edit" onclick="editContact(<?= $c['id'] ?>)">
                        <i class="fas fa-pen"></i>
                    </button>
                    <button class="btn-sm-icon" title="Toggle Favorite"
                            onclick="toggleFavorite(<?= $c['id'] ?>, <?= $c['is_favorite'] ?>)">
                        <i class="fas fa-star <?= $c['is_favorite']?'text-warning':'' ?>"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="p-3 d-flex justify-content-center">
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">‹</a></li><?php endif; ?>
                <?php for ($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?>
                <li class="page-item <?= $p===$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a></li>
                <?php endfor; ?>
                <?php if ($page<$totalPages): ?><li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">›</a></li><?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Bulk action bar -->
<div id="bulkBar" style="display:none;position:fixed;bottom:0;left:var(--sidebar-w);right:0;z-index:210;background:var(--card2);border-top:2px solid var(--primary);padding:.5rem 1rem;box-shadow:0 -4px 12px rgba(0,0,0,.2)">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-primary" id="bulkCount">0</span>
        <span class="text-muted small">selected — update:</span>
        <input type="text" id="bulkType" class="form-control form-control-sm" style="width:130px" placeholder="Type…" autocomplete="off" list="cmTypeList">
        <input type="text" id="bulkGroup" class="form-control form-control-sm" style="width:130px" placeholder="Group…" autocomplete="off" list="cmGroupList">
        <select id="bulkScope" class="form-select form-select-sm" style="width:110px">
            <option value="">Scope…</option>
            <option value="internal">Internal</option>
            <option value="external">External</option>
            <option value="unknown">Unknown</option>
        </select>
        <input type="text" id="bulkCompany" class="form-control form-control-sm" style="width:140px" placeholder="Company…">
        <select id="bulkAssigned" class="form-select form-select-sm" style="width:140px">
            <option value="">Assigned to…</option>
            <option value="<?= $aid ?>">Me</option>
            <?php foreach ($agents as $ag): ?>
            <option value="<?= $ag['id'] ?>"><?= e($ag['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" onclick="applyBulk()"><i class="fas fa-check me-1"></i>Apply</button>
        <button class="btn btn-outline-secondary btn-sm" onclick="clearBulk()"><i class="fas fa-times me-1"></i>Clear</button>
    </div>
</div>

<!-- New / Edit Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user me-2"></i><span id="contactModalTitle">New Contact</span></h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cmId" value="0">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="cmPhone" class="form-control" placeholder="01XXXXXXXXX" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" id="cmName" class="form-control" placeholder="Name">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Company</label>
                        <input type="text" id="cmCompany" class="form-control" placeholder="Company name">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label">Email</label>
                        <input type="email" id="cmEmail" class="form-control" placeholder="email@example.com">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Type</label>
                        <input type="text" id="cmTypeName" class="form-control" placeholder="Type name…" autocomplete="off"
                               list="cmTypeList">
                        <datalist id="cmTypeList">
                            <?php $types_list->data_seek(0); while ($t = $types_list->fetch_assoc()): ?>
                            <option value="<?= e($t['name']) ?>">
                            <?php endwhile; ?>
                        </datalist>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Group</label>
                        <input type="text" id="cmGroupName" class="form-control" placeholder="Group name…" autocomplete="off"
                               list="cmGroupList">
                        <datalist id="cmGroupList">
                            <?php $groups->data_seek(0); while ($g = $groups->fetch_assoc()): ?>
                            <option value="<?= e($g['name']) ?>">
                            <?php endwhile; ?>
                        </datalist>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Scope</label>
                        <select id="cmScope" class="form-select">
                            <option value="unknown">Unknown</option>
                            <option value="internal">Internal</option>
                            <option value="external">External</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Office Type</label>
                        <select id="cmOffice" class="form-select">
                            <option value="">— None —</option>
                            <option value="head_office">Head Office</option>
                            <option value="branch">Branch</option>
                            <option value="field">Field</option>
                            <option value="remote">Remote</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Assign To</label>
                        <select id="cmAssign" class="form-select">
                            <option value="">— Unassigned —</option>
                            <option value="<?= $aid ?>">Me</option>
                            <?php foreach ($agents as $ag): ?>
                            <option value="<?= $ag['id'] ?>"><?= e($ag['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4 d-flex align-items-end gap-3">
                        <div class="form-check">
                            <input type="checkbox" id="cmFav" class="form-check-input">
                            <label class="form-check-label">Favorite</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="cmBlocked" class="form-check-input">
                            <label class="form-check-label">Blocked</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" id="cmAddress" class="form-control" placeholder="Address">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Quick Notes / Bio</label>
                        <textarea id="cmNotes" class="form-control" rows="3"
                                  placeholder="Key info visible during calls — who they are, preferences, history…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveContact()">
                    <i class="fas fa-save me-1"></i>Save Contact
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const APP_URL = '<?= APP_URL ?>';
document.addEventListener('DOMContentLoaded', () => {
    initContactSuggest(document.getElementById('cmCompany'), 'company');
});

function openNewContact() {
    document.getElementById('contactModalTitle').textContent = 'New Contact';
    document.getElementById('cmId').value = '0';
    ['cmPhone','cmName','cmCompany','cmEmail','cmAddress','cmNotes','cmTypeName','cmGroupName'].forEach(f=>document.getElementById(f).value='');
    document.getElementById('cmScope').value = 'unknown';
    document.getElementById('cmOffice').value = '';
    document.getElementById('cmAssign').value = '';
    document.getElementById('cmFav').checked = false;
    document.getElementById('cmBlocked').checked = false;
    new bootstrap.Modal(document.getElementById('contactModal')).show();
    setTimeout(()=>document.getElementById('cmPhone').focus(),300);
}

function editContact(id) {
    fetch(APP_URL + '/api/contacts.php?action=get&id=' + id)
        .then(r=>r.json()).then(c=>{
            document.getElementById('contactModalTitle').textContent = 'Edit Contact';
            document.getElementById('cmId').value          = c.id;
            document.getElementById('cmPhone').value       = c.phone || '';
            document.getElementById('cmName').value        = c.name || '';
            document.getElementById('cmCompany').value     = c.company || '';
            document.getElementById('cmEmail').value       = c.email || '';
            document.getElementById('cmAddress').value     = c.address || '';
            document.getElementById('cmNotes').value       = c.notes || '';
            document.getElementById('cmTypeName').value    = c.type_name || '';
            document.getElementById('cmGroupName').value   = c.group_name || '';
            document.getElementById('cmScope').value       = c.scope || 'unknown';
            document.getElementById('cmOffice').value      = c.office_type || '';
            document.getElementById('cmAssign').value      = c.assigned_to || '';
            document.getElementById('cmFav').checked       = !!c.is_favorite;
            document.getElementById('cmBlocked').checked   = !!c.is_blocked;
            new bootstrap.Modal(document.getElementById('contactModal')).show();
        });
}

function saveContact() {
    const id = document.getElementById('cmId').value;
    const data = {
        action:      id === '0' ? 'create' : 'update',
        id,
        phone:       document.getElementById('cmPhone').value.trim(),
        name:        document.getElementById('cmName').value.trim(),
        company:     document.getElementById('cmCompany').value.trim(),
        email:       document.getElementById('cmEmail').value.trim(),
        address:     document.getElementById('cmAddress').value.trim(),
        notes:       document.getElementById('cmNotes').value.trim(),
        type_name:   document.getElementById('cmTypeName').value.trim(),
        group_name:  document.getElementById('cmGroupName').value.trim(),
        scope:       document.getElementById('cmScope').value,
        office_type: document.getElementById('cmOffice').value,
        assigned_to: document.getElementById('cmAssign').value,
        is_favorite: document.getElementById('cmFav').checked ? 1 : 0,
        is_blocked:  document.getElementById('cmBlocked').checked ? 1 : 0,
    };
    if (!data.phone) { showToast('Phone is required','warning'); return; }
    fetch(APP_URL + '/api/contacts.php', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
            showToast(id === '0' ? 'Contact created' : 'Contact updated', 'success');
            setTimeout(()=>location.reload(), 700);
        } else showToast(d.error || 'Error','danger');
    });
}

function toggleFavorite(id, current) {
    fetch(APP_URL + '/api/contacts.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'favorite', id, is_favorite: current ? 0 : 1})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) setTimeout(()=>location.reload(), 400);
    });
}

/* ── Bulk select ──────────────────────────────────────────────── */
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});
function updateBulkBar() {
    const sel = document.querySelectorAll('.row-check:checked');
    const bar = document.getElementById('bulkBar');
    document.getElementById('bulkCount').textContent = sel.length + ' selected';
    bar.style.display = sel.length ? 'block' : 'none';
    const all = document.querySelectorAll('.row-check');
    document.getElementById('selectAll').indeterminate = sel.length > 0 && sel.length < all.length;
    document.getElementById('selectAll').checked = sel.length === all.length && all.length > 0;
}
function clearBulk() {
    document.querySelectorAll('.row-check, #selectAll').forEach(cb => { cb.checked = false; cb.indeterminate = false; });
    document.getElementById('bulkBar').style.display = 'none';
    ['bulkType','bulkGroup','bulkScope','bulkCompany','bulkAssigned'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('bulkCompany').value = '';
}
function applyBulk() {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(cb => +cb.dataset.id);
    if (!ids.length) return;
    const updates = {};
    const t = document.getElementById('bulkType').value;
    const g = document.getElementById('bulkGroup').value;
    const s = document.getElementById('bulkScope').value;
    const co= document.getElementById('bulkCompany').value.trim();
    const a = document.getElementById('bulkAssigned').value;
    if (t)  updates.type_name  = t;
    if (g)  updates.group_name = g;
    if (s)  updates.scope       = s;
    if (co) updates.company     = co;
    if (a)  updates.assigned_to = a;
    if (!Object.keys(updates).length) { showToast('Select at least one field to update','warning'); return; }
    fetch(APP_URL + '/api/contacts.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'bulk_update', ids, updates})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) { showToast('Updated ' + ids.length + ' contacts','success'); setTimeout(()=>location.reload(), 700); }
        else showToast(d.error || 'Error','danger');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
