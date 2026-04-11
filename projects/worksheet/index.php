<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../flash.php';
require_once __DIR__ . '/../includes/layout.php';

requireRole('member');
$user = currentUser();

$projects = $user['role'] === 'admin'
    ? dbFetchAll("SELECT id, name, status, due_date FROM projects WHERE status != 'archived' ORDER BY name")
    : dbFetchAll("SELECT p.id, p.name, p.status, p.due_date FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? AND p.status != 'archived' ORDER BY p.name", [$user['id']]);

$preProjectId = (int)($_GET['project_id'] ?? 0);

layoutStart('Worksheet', 'worksheet');
$apiUrl = BASE_URL . '/worksheet/api.php';
$userJson = json_encode(['id' => $user['id'], 'full_name' => $user['full_name'], 'role' => $user['role'], 'username' => $user['username'] ?? strtolower(str_replace(' ','_',$user['full_name']))]);
?>
<div class="worksheet-wrap">

<!-- Top bar -->
<div class="worksheet-topbar">
    <select id="projectSel" class="form-control" style="max-width:240px">
        <option value="">— Select a project —</option>
        <?php foreach ($projects as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $preProjectId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach ?>
    </select>
    <div id="projectMeta" style="display:none;align-items:center;gap:10px;flex-wrap:wrap;font-size:.8125rem">
        <span id="projectBadge"></span>
        <span id="projectDue" style="color:var(--text-muted)"></span>
        <div class="progress" style="width:100px;flex-shrink:0"><div class="progress-bar" id="projectProgress" style="width:0%"></div></div>
        <span id="projectProgressPct" style="color:var(--text-muted)"></span>
    </div>
    <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
        <div class="sync-bar" id="syncBar">Last updated: just now</div>
        <button id="refreshAllBtn" onclick="ws_refreshAll()" class="btn btn-ghost btn-sm btn-icon" title="Refresh now" style="flex-shrink:0">
            <svg id="refreshIcon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </button>
        <a href="<?= BASE_URL ?>/modules/projects/view.php?id=0" id="projectLink" style="display:none" class="btn btn-ghost btn-sm">View Project</a>
    </div>
</div>

<div id="reconnectBar" class="reconnect-bar" style="display:none">Reconnecting…</div>

<!-- New Fixed Split Layout -->
<div class="worksheet-split" id="wsSplit">
    <!-- Panel A: Alternating Panels -->
    <div class="ws-panel" id="panelA" style="flex: 1;">
        <div class="ws-picker" id="pickerA"></div>
        <div class="ws-panel-header" id="headerA"></div>
        <div id="contentA" class="ws-panel-content"></div>
    </div>
    
    <!-- Divider -->
    <div class="ws-divider" id="wsDivider"></div>
    
    <!-- Panel B: Fixed Chat Panel -->
    <div class="ws-panel" id="panelB" style="width: 400px; flex: none;">
        <div class="ws-panel-header" id="headerB">
            <span class="ws-panel-title">Team Chat</span>
            <div id="chatHeaderTools" style="display:flex;gap:4px"></div>
        </div>
        <div id="contentB" class="ws-panel-content chat-panel">
            <div class="empty-state"><p>Select a project to view chat.</p></div>
        </div>
    </div>
</div>

</div><!-- /worksheet-wrap -->

<!-- ── Task Drawer ─────────────────────────────────────── -->
<div id="taskDrawer-overlay" class="drawer-overlay"></div>
<div id="taskDrawer" class="drawer drawer-right" style="width:min(680px,100vw)">
    <div class="drawer-header" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);flex-shrink:0">
        <div>
            <div id="drawerProjectName" style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px"></div>
            <div id="drawerTaskTitle" style="font-weight:600;font-size:.9375rem"></div>
        </div>
        <div style="display:flex;gap:6px;align-items:center">
            <a id="drawerFullLink" href="#" target="_blank" class="btn btn-ghost btn-sm" style="font-size:.72rem">Full View →</a>
            <button onclick="closeDrawer('taskDrawer')" class="btn btn-ghost btn-sm btn-icon">✕</button>
        </div>
    </div>
    <div id="drawerBody" class="drawer-body" style="overflow-y:auto;flex:1;padding:18px"></div>
</div>

<style>
/* Chat UI Improvements - Theme Aware */
.chat-panel { display: flex; flex-direction: column; background: var(--surface); height: 100%; }
.chat-messages { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 12px; scroll-behavior: smooth; }
.chat-bubble { max-width: 85%; padding: 10px 14px; border-radius: 18px; font-size: 0.875rem; line-height: 1.4; position: relative; transition: transform 0.2s, box-shadow 0.2s; box-shadow: var(--shadow); }
.chat-bubble:hover { transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); }

.chat-bubble.mine { align-self: flex-end; background: var(--accent); color: #fff; border-bottom-right-radius: 4px; }
.chat-bubble.others { align-self: flex-start; background: var(--bg); color: var(--text); border-bottom-left-radius: 4px; border: 1px solid var(--border); }
.chat-bubble.mention { border: 2px solid var(--warning); background: rgba(245, 158, 11, 0.1); color: var(--text); }
.chat-bubble.private { border: 1px dashed var(--accent); background: rgba(79, 107, 237, 0.05); }

.chat-bubble.highlight-flash { animation: flash-highlight 2s ease-out; }
@keyframes flash-highlight {
    0% { background-color: var(--warning); color: #fff; }
    100% { }
}

.chat-meta-likes {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: rgba(0,0,0,0.05);
    padding: 0 5px;
    border-radius: 10px;
    margin-left: 4px;
    transition: transform 0.1s;
}
.chat-meta-likes:hover { transform: scale(1.1); background: rgba(0,0,0,0.1); }
.mine .chat-meta-likes { background: rgba(255,255,255,0.15); }
.mine .chat-meta-likes:hover { background: rgba(255,255,255,0.25); }

.chat-meta { font-size: 0.7rem; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
.mine .chat-meta { justify-content: flex-end; color: rgba(255,255,255,0.8); }
.others .chat-meta { color: var(--text-muted); }

.chat-actions { display: flex; gap: 10px; margin-top: 6px; opacity: 0; transition: 0.2s; font-size: 0.75rem; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 4px; }
.mine .chat-actions { border-top-color: rgba(255,255,255,0.1); }
.chat-bubble:hover .chat-actions { opacity: 1; }
.mine .chat-actions { justify-content: flex-end; }
.chat-action-btn { cursor: pointer; color: inherit; opacity: 0.7; display: flex; align-items: center; gap: 3px; }
.chat-action-btn:hover { opacity: 1; }

.chat-reply-ref { 
    font-size: 0.75rem; padding: 6px 10px; background: rgba(0,0,0,0.05); border-radius: 8px; margin-bottom: 6px; 
    border-left: 3px solid var(--accent); cursor: pointer; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    transition: background 0.2s;
}
.chat-reply-ref:hover { background: rgba(0,0,0,0.1); }
.mine .chat-reply-ref { background: rgba(255,255,255,0.15); border-left-color: rgba(255,255,255,0.6); color: #fff; }
.mine .chat-reply-ref:hover { background: rgba(255,255,255,0.25); }

.pinned-bar {
    padding: 8px 12px;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.8rem;
    color: var(--text);
    position: relative;
    z-index: 10;
}
.pinned-bar-content { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
.pinned-bar-nav { display: flex; gap: 5px; align-items: center; }
.pinned-bar-nav button { background: none; border: none; padding: 2px; color: var(--text-muted); cursor: pointer; }
.pinned-bar-nav button:hover { color: var(--text); }

.chat-input-area { padding: 15px; border-top: 1px solid var(--border); background: var(--surface); }
.reply-bar { padding: 8px 12px; background: var(--bg); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items:center; font-size: 0.75rem; color: var(--text-muted); }

.load-more-chat { text-align: center; padding: 10px; }
.load-more-chat button { font-size: 0.75rem; color: var(--accent); background: none; border: none; cursor: pointer; font-weight: 600; }
.load-more-chat button:hover { text-decoration: underline; }

.mention { font-weight: 700; color: var(--warning); background: rgba(245, 158, 11, 0.1); padding: 0 2px; border-radius: 2px; }

/* Refresh icon spin animation */
@keyframes ws-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
#refreshIcon.spinning { animation: ws-spin 0.8s linear infinite; }

/* Details Panel Styling */
.details-form { padding: 20px; max-width: 800px; }
.details-form .form-group { margin-bottom: 15px; }
.details-form label { font-weight: 600; font-size: 0.8rem; color: var(--text-muted); display: block; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.02em; }

/* Emoji reactions */
.reaction-bar { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.reaction-badge { background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 2px 8px; font-size: .75rem; cursor: pointer; transition: all .15s; display: inline-flex; align-items: center; gap: 3px; }
.reaction-badge:hover { border-color: var(--accent); background: rgba(79,107,237,.08); }
.reaction-badge.active { background: rgba(79,107,237,.15); border-color: var(--accent); font-weight: 600; }
.mine .reaction-badge { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.3); color: #fff; }
.mine .reaction-badge.active { background: rgba(255,255,255,.3); }
.emoji-picker { display: flex; gap: 4px; padding: 6px 8px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; box-shadow: var(--shadow); margin-top: 4px; }
.emoji-picker button { background: none; border: none; font-size: 1.1rem; cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: background .15s; }
.emoji-picker button:hover { background: var(--bg); }
</style>

<script>
(function(){
const API = <?= json_encode($apiUrl) ?>;
const ME  = <?= $userJson ?>;
const panels = ['tasks','feed','meetings','timelog','files','details','mentions'];
const panelLabels = {tasks:'Tasks',feed:'Feed',meetings:'Meetings',timelog:'Time',files:'Files',details:'Details',mentions:'@ Mentions'};

let state = {
    projectId: 0,
    project: null,
    tasks: [],
    chat: [],
    pinnedChat: [],
    feed: [],
    meetings: [],
    mentions: [],
    timelog: {},
    files: [],
    members: [],
    panelA: 'tasks',
    taskFilter: 'mine',
    lastChatId: 0,
    firstChatId: 0,
    lastFeedId: 0,
    replyTo: null,
    recipientId: null,
    pinnedIndex: 0,
    pollTimer: null,
    syncTime: Date.now(),
    dragging: false,
};

/* ── Init ─────────────────────────────────────────────── */
function init() {
    state.taskFilter = localStorage.getItem('ws_taskFilter') || 'mine';
    const stored = tryParse(localStorage.getItem('ws_panels_'+state.projectId));
    if (stored) { state.panelA = stored.a || 'tasks'; }

    document.getElementById('projectSel').addEventListener('change', function(){
        selectProject(parseInt(this.value)||0);
    });

    const pid = parseInt(document.getElementById('projectSel').value)||0;
    if (pid) selectProject(pid);

    setupDivider();
    startSyncTimer();
}

function tryParse(s) { try { return JSON.parse(s); } catch(e) { return null; } }

/* ── Project selection ────────────────────────────────── */
function selectProject(pid) {
    state.projectId = pid;
    if (!pid) { clearPanels(); return; }
    const stored = tryParse(localStorage.getItem('ws_panels_'+pid));
    if (stored) { state.panelA = stored.a || 'tasks'; }
    loadProject();
}

async function loadProject() {
    if (!state.projectId) return;
    try {
        const d = await post({action:'get_project_data', project_id: state.projectId});
        if (!d.success) return;
        state.project  = d.data.project;
        state.tasks    = d.data.tasks;
        state.chat     = d.data.chat || [];
        state.pinnedChat = d.data.pinned_chat || [];
        state.feed     = d.data.feed || [];
        state.meetings = d.data.meetings || [];
        state.timelog  = d.data.timelog || {};
        state.files    = d.data.files || [];
        state.members  = d.data.members || [];
        state.lastChatId = maxId(state.chat);
        state.firstChatId = minId(state.chat);
        state.lastFeedId = maxId(state.feed);
        updateProjectMeta();
        renderPanel('A');
        renderChatPanel();
        document.getElementById('reconnectBar').style.display='none';
    } catch(e) { showReconnecting(); }
}

function maxId(arr) { return arr.reduce((m,x)=>Math.max(m,parseInt(x.id)||0),0); }
function minId(arr) { return arr.length ? arr.reduce((m,x)=>Math.min(m,parseInt(x.id)||Infinity),Infinity) : 0; }

function updateProjectMeta() {
    const p = state.project;
    if (!p) return;
    const meta = document.getElementById('projectMeta');
    meta.style.display = 'flex';
    document.getElementById('projectBadge').innerHTML = `<span class="badge badge-${p.status}">${ucfirst(p.status.replace('_',' '))}</span>`;
    document.getElementById('projectDue').textContent = p.due_date ? 'Due '+formatDate(p.due_date) : '';
    const total = state.timelog.task_total||0, done = state.timelog.task_done||0;
    const pct = total ? Math.round(done/total*100) : 0;
    document.getElementById('projectProgress').style.width = pct+'%';
    document.getElementById('projectProgressPct').textContent = pct+'% done';
    const link = document.getElementById('projectLink');
    link.style.display = '';
    link.href = <?= json_encode(BASE_URL) ?> + '/modules/projects/view.php?id=' + p.id;
}

function clearPanels() {
    document.getElementById('pickerA').innerHTML = '';
    document.getElementById('headerA').innerHTML = '';
    document.getElementById('contentA').innerHTML = '<div class="empty-state"><p>Select a project to get started.</p></div>';
    document.getElementById('contentB').innerHTML = '<div class="empty-state"><p>Select a project to view chat.</p></div>';
    document.getElementById('projectMeta').style.display = 'none';
    document.getElementById('projectLink').style.display = 'none';
}

/* ── Panel rendering ──────────────────────────────────── */
function renderPanel(side) {
    if (side === 'B') { renderChatPanel(); return; }
    renderPicker(side);
    renderContent(side, state.panelA);
}

function renderPicker(side) {
    const el = document.getElementById('picker'+side);
    el.innerHTML = panels.map(p =>
        `<button class="ws-picker-btn ${state.panelA===p?'active':''}" onclick="ws_switchPanel('${side}','${p}')">${panelLabels[p]}</button>`
    ).join('');
}

window.ws_switchPanel = async function(side, panel) {
    state.panelA = panel;
    localStorage.setItem('ws_panels_'+state.projectId, JSON.stringify({a:state.panelA}));
    if (panel === 'mentions') await refreshPanel('mentions');
    renderPanel(side);
};

function renderContent(side, panel) {
    const header = document.getElementById('header'+side);
    const content = document.getElementById('content'+side);

    const refreshBtn = `<button class="btn btn-ghost btn-sm btn-icon" onclick="ws_refresh('${side}')" title="Refresh">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
    </button>`;

    switch(panel) {
        case 'tasks':    renderTasks(side, header, content, refreshBtn); break;
        case 'feed':     renderFeed(side, header, content, refreshBtn); break;
        case 'meetings': renderMeetings(side, header, content, refreshBtn); break;
        case 'timelog':  renderTimelog(side, header, content, refreshBtn); break;
        case 'files':    renderFiles(side, header, content, refreshBtn); break;
        case 'details':  renderDetails(side, header, content, refreshBtn); break;
        case 'mentions': renderMentionsPanel(side, header, content, refreshBtn); break;
    }
}

window.ws_refresh = async function(side) {
    const panel = side === 'B' ? 'chat' : state.panelA;
    await refreshPanel(panel);
    if (side === 'B') renderChatPanel(); else renderContent(side, panel);
};

async function refreshPanel(panel) {
    if (!state.projectId) return;
    try {
        if (panel === 'tasks') {
            const d = await post({action:'get_tasks', project_id: state.projectId});
            if (d.success) state.tasks = d.data;
        } else if (panel === 'chat') {
            const d = await post({action:'get_chat', project_id: state.projectId, last_id: 0});
            if (d.success) { 
                state.chat = d.data.chat; 
                state.pinnedChat = d.data.pinned_chat;
                state.lastChatId = maxId(state.chat); 
                state.firstChatId = minId(state.chat); 
            }
        } else if (panel === 'feed') {
            const d = await post({action:'get_feed', project_id: state.projectId, last_id: 0});
            if (d.success) { state.feed = d.data; state.lastFeedId = maxId(state.feed); }
        } else if (panel === 'meetings') {
            const d = await post({action:'get_meetings', project_id: state.projectId});
            if (d.success) state.meetings = d.data;
        } else if (panel === 'timelog') {
            const d = await post({action:'get_timelog', project_id: state.projectId});
            if (d.success) state.timelog = d.data;
        } else if (panel === 'files') {
            const d = await post({action:'get_files', project_id: state.projectId});
            if (d.success) state.files = d.data;
        } else if (panel === 'details') {
            const d = await post({action:'get_project_data', project_id: state.projectId});
            if (d.success) state.project = d.data.project;
        } else if (panel === 'mentions') {
            const d = await post({action:'get_mentions', project_id: state.projectId});
            if (d.success) state.mentions = d.data;
        }
    } catch(e) {}
}

/* ── Panel: Tasks ─────────────────────── */
function renderTasks(side, header, content, refreshBtn) {
    header.innerHTML = `
        <span class="ws-panel-title">Tasks</span>
        <div style="display:flex;gap:8px;align-items:center">
            <div style="display:flex;background:var(--bg);border-radius:6px;padding:2px;gap:2px">
                <button class="btn btn-sm ${state.taskFilter==='mine'?'btn-primary':'btn-ghost'}" style="font-size:.75rem;padding:3px 12px" onclick="ws_taskFilter('mine')">My Tasks</button>
                <button class="btn btn-sm ${state.taskFilter==='all'?'btn-primary':'btn-ghost'}" style="font-size:.75rem;padding:3px 12px" onclick="ws_taskFilter('all')">All Tasks</button>
            </div>
            ${refreshBtn}
        </div>`;
    
    const showAll = state.taskFilter === 'all';
    const tsFiltered = (state.tasks||[]).filter(t => {
        if (showAll) return true;
        // Ensure t.assignees exists and check for current user ID match
        return (t.assignees||[]).some(a => parseInt(a.id) === ME.id);
    });
    
    const cols = {todo:[],in_progress:[],review:[],done:[]};
    tsFiltered.forEach(t => { if(cols[t.status]) cols[t.status].push(t); });
    const colLabels = {todo:'To Do',in_progress:'In Progress',review:'Review',done:'Done'};
    let html = '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:14px;min-width:600px">';
    for (const [st, ts] of Object.entries(cols)) {
        html += `<div style="background:var(--bg);border-radius:8px;padding:10px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;padding-bottom:6px;border-bottom:2px solid var(--border)">
                <span style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted)">${colLabels[st]}</span>
                <span style="font-size:.7rem;font-weight:700;background:var(--border);color:var(--text-muted);width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center">${ts.length}</span>
            </div>`;
        ts.forEach(t => {
            const overdue = t.due_date && t.due_date < new Date().toISOString().slice(0,10) && t.status!=='done';
            const assigneeHtml = (t.assignees||[]).slice(0,3).map(a=>`<span class="avatar avatar-sm" style="background:${getAvatarColor(a.full_name)}" title="${esc(a.full_name)}">${a.initials}</span>`).join('');
            const subTotal = parseInt(t.subtask_total)||0;
            const subDone  = parseInt(t.subtask_done)||0;
            const subtaskBar = subTotal > 0 ? `
                <div style="display:flex;align-items:center;gap:6px;margin-top:5px">
                    <div style="flex:1;height:3px;background:var(--border);border-radius:2px">
                        <div style="height:100%;background:var(--success);border-radius:2px;width:${Math.round(subDone/subTotal*100)}%"></div>
                    </div>
                    <span style="font-size:.65rem;color:var(--text-muted)">${subDone}/${subTotal}</span>
                </div>` : '';
            html += `<div class="task-card" onclick="ws_openTask(${t.id})">
                <div class="task-card-title">${esc(t.title)}</div>
                <div class="task-card-meta">
                    <span class="badge badge-${t.priority}" style="font-size:.65rem">${t.priority}</span>
                    ${t.due_date?`<span class="${overdue?'due-overdue':''}" style="font-size:.7rem">${overdue?'⚠ ':''} ${formatDate(t.due_date)}</span>`:''}
                </div>
                ${subtaskBar}
                <div class="avatar-stack" style="margin-top:6px">${assigneeHtml}</div>
                <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap">
                    ${['todo','in_progress','review','done'].filter(s=>s!==t.status).map(s=>`<button class="btn btn-ghost btn-sm" style="font-size:.65rem;padding:2px 6px" onclick="event.stopPropagation();ws_setStatus(${t.id},'${s}')">${s.replace('_',' ')}</button>`).join('')}
                </div>
            </div>`;
        });
        html += `<div id="addTask_${st}" style="margin-top:4px"><button class="kanban-add" onclick="ws_showAddTask('${st}','${side}')">+ Add task</button></div></div>`;
    }
    html += '</div>';
    content.style.overflow = 'auto';
    content.innerHTML = html;
}

window.ws_taskFilter = function(filter) {
    state.taskFilter = filter;
    localStorage.setItem('ws_taskFilter', filter);
    if (state.panelA === 'tasks') renderContent('A', 'tasks');
};

/* ── Task Drawer ─────────────────────────────────── */
window.ws_openTask = function(id) {
    openDrawer('taskDrawer');
    document.getElementById('drawerBody').innerHTML = '<div class="empty-state"><p>Loading…</p></div>';
    ws_loadTaskDrawer(id);
};

window.ws_loadTaskDrawer = async function(taskId) {
    const d = await post({action:'get_task_detail', project_id: state.projectId, task_id: taskId});
    if (!d.success) { window.showToast('Failed to load task','error'); return; }
    const t = d.data;
    document.getElementById('drawerProjectName').textContent = t.project_name || '';
    document.getElementById('drawerTaskTitle').textContent   = t.title || '';
    document.getElementById('drawerFullLink').href = <?= json_encode(BASE_URL) ?> + '/modules/tasks/view.php?id=' + taskId;
    document.getElementById('drawerBody').innerHTML = renderDrawerHTML(t);
    // Init mention autocomplete in comment input
    const ci = document.getElementById('drawerCommentInput');
    if (ci && typeof initMentionInput === 'function') {
        initMentionInput(ci, (state.members||[]).map(m=>({id:m.id,username:m.username||m.full_name.toLowerCase().replace(/\s+/g,'_'),name:m.full_name,initials:getInitials(m.full_name)})));
    }
    // auto-resize comment textarea
    ci?.addEventListener('input', () => { ci.style.height='auto'; ci.style.height=ci.scrollHeight+'px'; });
};

function renderDrawerHTML(t) {
    const overdue = t.due_date && t.due_date < new Date().toISOString().slice(0,10) && t.status !== 'done';
    const subtasks = t.subtasks || [];
    const stTotal = subtasks.length;
    const stDone  = subtasks.filter(s => s.status === 'done').length;
    const comments = t.comments || [];
    const timeLogs = t.time_logs || [];

    const assigneesHtml = (t.assignees||[]).map(a =>
        `<span class="avatar avatar-sm" style="background:${getAvatarColor(a.full_name)}" title="${esc(a.full_name)}">${a.initials}</span>`
    ).join('');

    const statusBtns = ['todo','in_progress','review','done'].map(s =>
        `<button class="btn btn-sm ${t.status===s?'btn-primary':'btn-ghost'}" style="font-size:.7rem;padding:3px 10px" onclick="ws_drawerSetStatus(${t.id},'${s}')">${s.replace('_',' ')}</button>`
    ).join('');

    const subtasksHtml = subtasks.map(s => `
        <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--border)">
            <button onclick="ws_toggleSubtask(${s.id},${t.id})" style="width:18px;height:18px;border-radius:4px;border:2px solid var(--border);background:${s.status==='done'?'var(--success)':'transparent'};cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px">${s.status==='done'?'✓':''}</button>
            <span style="flex:1;font-size:.8125rem;${s.status==='done'?'text-decoration:line-through;color:var(--text-muted)':''}">${esc(s.title)}</span>
            <span class="badge badge-${s.priority}" style="font-size:.6rem">${s.priority}</span>
        </div>`).join('');

    const commentsHtml = comments.map(c => `
        <div style="display:flex;gap:10px;margin-bottom:12px">
            <span class="avatar avatar-sm" style="background:${getAvatarColor(c.full_name)};flex-shrink:0">${getInitials(c.full_name)}</span>
            <div style="flex:1">
                <div style="font-size:.75rem;margin-bottom:3px"><strong style="color:${getAvatarColor(c.full_name)}">${esc(c.full_name)}</strong> <span style="color:var(--text-muted)">${formatTs(c.created_at)}</span></div>
                <div style="font-size:.8125rem;line-height:1.5;white-space:pre-wrap">${esc(c.body)}</div>
            </div>
        </div>`).join('');

    const logsHtml = timeLogs.slice(0,5).map(l => `
        <div style="display:flex;align-items:center;gap:8px;font-size:.75rem;padding:4px 0;border-bottom:1px solid var(--border)">
            <span class="avatar" style="width:22px;height:22px;font-size:.6rem;background:${getAvatarColor(l.full_name)}">${getInitials(l.full_name)}</span>
            <span style="flex:1;color:var(--text-muted)">${esc(l.task_title||t.title)}</span>
            <strong>${parseFloat(l.hours).toFixed(1)}h</strong>
            <span style="color:var(--text-muted)">${l.logged_at}</span>
        </div>`).join('');

    return `
    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
        ${statusBtns}
        <span class="badge badge-${t.priority}" style="margin-left:auto">${t.priority}</span>
        ${t.due_date ? `<span style="font-size:.75rem;${overdue?'color:var(--danger);font-weight:600':''}">${overdue?'⚠ Overdue ':'Due '}${formatDate(t.due_date)}</span>` : ''}
    </div>
    ${assigneesHtml ? `<div class="avatar-stack" style="margin-bottom:16px">${assigneesHtml}</div>` : ''}

    <!-- Description -->
    <div style="margin-bottom:20px">
        <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:6px">Description</div>
        <textarea id="drawerDesc" class="form-control" rows="3" placeholder="Add a description…" style="resize:vertical;font-size:.8125rem">${esc(t.description||'')}</textarea>
        <button class="btn btn-ghost btn-sm" style="margin-top:4px;font-size:.72rem" onclick="ws_saveDesc(${t.id})">Save</button>
    </div>

    <!-- Subtasks -->
    <div style="margin-bottom:20px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted)">Subtasks ${stTotal>0?`<span style="font-weight:400">(${stDone}/${stTotal})</span>`:''}</div>
        </div>
        ${stTotal > 0 ? `<div style="height:4px;background:var(--border);border-radius:2px;margin-bottom:8px"><div style="height:100%;background:var(--success);border-radius:2px;width:${Math.round(stDone/stTotal*100)}%"></div></div>` : ''}
        ${subtasksHtml}
        <div style="margin-top:8px;display:flex;gap:6px">
            <input id="newSubtaskInput_${t.id}" class="form-control" placeholder="New subtask…" style="font-size:.8rem;flex:1" onkeydown="if(event.key==='Enter'){ws_addSubtask(${t.id});event.preventDefault();}">
            <button class="btn btn-ghost btn-sm" onclick="ws_addSubtask(${t.id})">Add</button>
        </div>
    </div>

    <!-- Comments -->
    <div style="margin-bottom:20px">
        <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:10px">Comments (${comments.length})</div>
        ${commentsHtml || '<div style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px">No comments yet.</div>'}
        <div style="display:flex;gap:8px;align-items:flex-start">
            <span class="avatar avatar-sm" style="background:${getAvatarColor(ME.full_name)};flex-shrink:0">${getInitials(ME.full_name)}</span>
            <div style="flex:1">
                <textarea id="drawerCommentInput" class="form-control" rows="2" placeholder="Write a comment… @mention teammates" style="resize:none;font-size:.8125rem;margin-bottom:6px"></textarea>
                <button class="btn btn-primary btn-sm" style="font-size:.75rem" onclick="ws_addComment(${t.id})">Comment</button>
            </div>
        </div>
    </div>

    <!-- Time Logs -->
    ${timeLogs.length ? `<div>
        <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:8px">Time Logged</div>
        ${logsHtml}
    </div>` : ''}`;
}

window.ws_drawerSetStatus = async function(taskId, status) {
    const d = await post({action:'update_task_status', project_id: state.projectId, task_id: taskId, status});
    if (d.success) { state.tasks = d.data; rerenderPanel('tasks'); ws_loadTaskDrawer(taskId); }
};
window.ws_saveDesc = async function(taskId) {
    const desc = document.getElementById('drawerDesc')?.value || '';
    await post({action:'save_task_description', project_id: state.projectId, task_id: taskId, description: desc});
    window.showToast('Description saved','success');
};
window.ws_addComment = async function(taskId) {
    const inp = document.getElementById('drawerCommentInput');
    const body = inp?.value?.trim();
    if (!body) return;
    const d = await post({action:'add_task_comment', project_id: state.projectId, task_id: taskId, body});
    if (d.success) { inp.value = ''; ws_loadTaskDrawer(taskId); }
};
window.ws_addSubtask = async function(taskId) {
    const inp = document.getElementById('newSubtaskInput_'+taskId);
    const title = inp?.value?.trim();
    if (!title) return;
    const d = await post({action:'add_subtask', project_id: state.projectId, task_id: taskId, title});
    if (d.success) { inp.value = ''; ws_loadTaskDrawer(taskId); state.tasks = await post({action:'get_tasks',project_id:state.projectId}).then(r=>r.success?r.data:state.tasks); rerenderPanel('tasks'); }
};
window.ws_toggleSubtask = async function(subtaskId, parentId) {
    const d = await post({action:'toggle_subtask', project_id: state.projectId, task_id: subtaskId, parent_id: parentId});
    if (d.success) { ws_loadTaskDrawer(parentId); state.tasks = await post({action:'get_tasks',project_id:state.projectId}).then(r=>r.success?r.data:state.tasks); rerenderPanel('tasks'); }
};
window.ws_setStatus = async function(taskId, status) {
    const d = await post({action:'update_task_status', project_id: state.projectId, task_id: taskId, status});
    if (d.success) { state.tasks = d.data; rerenderPanel('tasks'); }
};
window.ws_showAddTask = function(status, side) {
    const members = state.members || [];
    const memberOpts = members.map(m=>`<option value="${m.id}">${esc(m.full_name)}</option>`).join('');
    const el = document.getElementById('addTask_'+status);
    el.innerHTML = `<div class="inline-form">
        <input id="newTaskTitle_${status}" class="form-control" placeholder="Task title" style="margin-bottom:6px">
        <div style="display:flex;gap:6px;margin-bottom:6px">
            <select id="newTaskPrio_${status}" class="form-control" style="flex:1"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option></select>
            <select id="newTaskAssignee_${status}" class="form-control" style="flex:1"><option value="">Unassigned</option>${memberOpts}</select>
        </div>
        <div style="display:flex;gap:6px">
            <button class="btn btn-primary btn-sm" onclick="ws_addTask('${status}','${side}')">Add</button>
            <button class="btn btn-secondary btn-sm" onclick="ws_refresh('${side}')">Cancel</button>
        </div>
    </div>`;
    document.getElementById('newTaskTitle_'+status).focus();
};
window.ws_addTask = async function(status, side) {
    const title = document.getElementById('newTaskTitle_'+status)?.value?.trim();
    const priority = document.getElementById('newTaskPrio_'+status)?.value;
    const assignee = document.getElementById('newTaskAssignee_'+status)?.value;
    if (!title) return;
    const d = await post({action:'add_task', project_id: state.projectId, title, priority, assignee_id: assignee||0});
    if (d.success) { state.tasks = d.data; rerenderPanel('tasks'); }
};

/* ── Panel: Chat ─────────────────────────── */
function renderChatPanel() {
    const headerTools = document.getElementById('chatHeaderTools');
    headerTools.innerHTML = `
        <button class="btn btn-ghost btn-sm btn-icon" onclick="ws_refresh('B')" title="Refresh">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        </button>`;

    const content = document.getElementById('contentB');
    content.innerHTML = `
        <div id="pinnedBar" class="pinned-bar" style="display:none"></div>
        <div class="chat-messages" id="chatMsgs">
            ${state.firstChatId > 1 ? `<div class="load-more-chat"><button onclick="ws_loadMoreChat()">Load older messages</button></div>` : ''}
            <div id="chatList"></div>
        </div>
        <div id="replyBar" class="reply-bar" style="display:none"></div>
        <div class="chat-input-area">
            <div style="display:flex;gap:6px;margin-bottom:8px">
                <select id="chatRecipient" class="form-control" style="font-size:0.75rem;padding:2px 6px;height:auto;width:auto">
                    <option value="">Everyone</option>
                    ${(state.members||[]).filter(m=>m.id!=ME.id).map(m=>`<option value="${m.id}">Private: ${esc(m.full_name)}</option>`).join('')}
                </select>
            </div>
            <div style="display:flex;gap:8px">
                <textarea id="chatInput" class="form-control" rows="1" placeholder="Message… @mention teammates" style="resize:none;min-height:38px;max-height:120px"></textarea>
                <button class="btn btn-primary btn-sm" onclick="ws_sendChat()">Send</button>
            </div>
        </div>`;

    renderChatList();

    const inp = document.getElementById('chatInput');
    inp.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ws_sendChat(); } });
    if (typeof initMentionInput === 'function') {
        initMentionInput(inp, (state.members||[]).map(m=>({id:m.id,username:m.username||m.full_name.toLowerCase().replace(/\s+/g,'_'),name:m.full_name,initials:getInitials(m.full_name)})));
    }
}

function renderPinnedBar() {
    const bar = document.getElementById('pinnedBar');
    const pinned = state.pinnedChat || [];
    
    if (!pinned.length) {
        bar.style.display = 'none';
        return;
    }
    
    if (state.pinnedIndex >= pinned.length) state.pinnedIndex = 0;
    const current = pinned[state.pinnedIndex];
    
    bar.style.display = 'flex';
    bar.innerHTML = `
        <span style="color:var(--accent)">📌</span>
        <div class="pinned-bar-content" onclick="ws_scrollToMessage(${current.id})">
            <strong>${esc(current.full_name)}:</strong> ${esc(current.body)}
        </div>
        <div class="pinned-bar-nav">
            <span style="font-size:0.7rem;margin-right:5px">${state.pinnedIndex + 1}/${pinned.length}</span>
            <button onclick="ws_navPinned(-1)" title="Previous">&larr;</button>
            <button onclick="ws_navPinned(1)" title="Next">&rarr;</button>
            <button onclick="ws_pinChat(${current.id})" title="Unpin" style="margin-left:5px">×</button>
        </div>
    `;
}

window.ws_navPinned = function(dir) {
    const pinned = state.pinnedChat || [];
    if (!pinned.length) return;
    state.pinnedIndex = (state.pinnedIndex + dir + pinned.length) % pinned.length;
    renderPinnedBar();
};

function renderChatList() {
    const list = document.getElementById('chatList');
    if (!list) return;
    
    renderPinnedBar();
    
    list.innerHTML = state.chat.map(m => {
        const isMine      = parseInt(m.user_id) === ME.id;
        const isMentioned = m.body.includes('@'+ME.username) || m.body.includes('@'+ME.full_name.replace(/\s+/g,'_'));
        const isPrivate   = m.recipient_id !== null;
        const isPinned    = parseInt(m.is_pinned) === 1;
        const reactions   = m.reactions || [];
        const myReactions = m.my_reactions || [];
        const reactionBar = reactions.length ? `<div class="reaction-bar">${reactions.map(r=>`<button class="reaction-badge ${myReactions.includes(r.emoji)?'active':''}" onclick="ws_reactChat(${m.id},'${r.emoji}')">${r.emoji} ${r.cnt}</button>`).join('')}</div>` : '';
        const emojiPicker = `<div class="emoji-picker" id="ep-${m.id}" style="display:none">
            ${ ['👍','❤️','🔥','👀','🎉','😂'].map(e=>`<button onclick="ws_reactChat(${m.id},'${e}');document.getElementById('ep-${m.id}').style.display='none'">${e}</button>`).join('') }
        </div>`;

        return `
        <div class="chat-bubble ${isMine?'mine':'others'} ${isMentioned?'mention':''} ${isPrivate?'private':''}" id="chat-${m.id}">
            <div class="chat-meta">
                ${!isMine ? `<strong style="color:${getAvatarColor(m.full_name)}">${esc(m.full_name)}</strong>` : ''}
                ${isPrivate ? `<span title="Private to ${esc(isMine?m.recipient_name:'Me')}">🔒</span>` : ''}
                <span>${formatTs(m.created_at)}</span>
                ${isPinned ? '<span title="Pinned Message">📌</span>' : ''}
            </div>
            ${m.parent_id ? `<div class="chat-reply-ref" onclick="ws_scrollToMessage(${m.parent_id})">Replying to ${esc(m.parent_name)}: ${esc(m.parent_body.substring(0,30))}...</div>` : ''}
            <div class="chat-body">${renderMentions(m.body)}</div>
            ${reactionBar}
            <div class="chat-actions">
                <span class="chat-action-btn" onclick="document.getElementById('ep-${m.id}').style.display=document.getElementById('ep-${m.id}').style.display==='none'?'flex':'none'">😊 React</span>
                <span class="chat-action-btn" onclick="ws_replyTo(${m.id},'${esc(m.full_name)}')">💬 Reply</span>
                <span class="chat-action-btn" onclick="ws_pinChat(${m.id})">📌 ${isPinned?'Unpin':'Pin'}</span>
            </div>
            ${emojiPicker}
        </div>`;
    }).join('');

    const msgs = document.getElementById('chatMsgs');
    msgs.scrollTop = msgs.scrollHeight;
}

window.ws_scrollToMessage = function(id) {
    const el = document.getElementById('chat-'+id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('highlight-flash');
        setTimeout(() => el.classList.remove('highlight-flash'), 2000);
    } else {
        window.showToast("Original message not loaded in this view.", "warning");
    }
};

window.ws_loadMoreChat = async function() {
    const d = await post({action:'get_chat', project_id: state.projectId, before_id: state.firstChatId});
    if (d.success && d.data.chat.length) {
        state.chat = d.data.chat.concat(state.chat);
        state.pinnedChat = d.data.pinned_chat;
        state.firstChatId = minId(state.chat);
        renderChatList();
    }
};

window.ws_replyTo = function(id, name) {
    state.replyTo = id;
    const bar = document.getElementById('replyBar');
    bar.innerHTML = `Replying to <strong>${name}</strong> <span onclick="ws_cancelReply()" style="cursor:pointer;margin-left:10px">×</span>`;
    bar.style.display = 'flex';
    document.getElementById('chatInput').focus();
};

window.ws_cancelReply = function() {
    state.replyTo = null;
    document.getElementById('replyBar').style.display = 'none';
};

window.ws_sendChat = async function() {
    const inp = document.getElementById('chatInput');
    const body = inp?.value?.trim();
    if (!body) return;
    
    const recipientId = document.getElementById('chatRecipient').value;
    const d = await post({
        action: 'send_chat', 
        project_id: state.projectId, 
        body: body,
        recipient_id: recipientId,
        parent_id: state.replyTo
    });
    
    if (d.success) {
        state.chat = d.data.chat;
        state.pinnedChat = d.data.pinned_chat;
        state.lastChatId = maxId(state.chat);
        state.firstChatId = minId(state.chat);
        inp.value = '';
        ws_cancelReply();
        renderChatList();
    }
};

window.ws_likeChat = async function(id) {
    const d = await post({action:'like_chat', project_id: state.projectId, chat_id: id});
    if (d.success) { 
        state.chat = d.data.chat; 
        state.pinnedChat = d.data.pinned_chat;
        renderChatList(); 
    }
};

window.ws_pinChat = async function(id) {
    const d = await post({action:'pin_chat', project_id: state.projectId, chat_id: id});
    if (d.success) {
        state.chat = d.data.chat;
        state.pinnedChat = d.data.pinned_chat;
        renderChatList();
    }
};

window.ws_reactChat = async function(chatId, emoji) {
    const d = await post({action:'react_chat', project_id: state.projectId, chat_id: chatId, emoji});
    if (d.success) {
        // API returns {reactions, my_reactions, chat_id} — patch the affected message in state
        for (const list of [state.chat, state.pinnedChat]) {
            const msg = list.find(m => parseInt(m.id) === parseInt(d.data.chat_id));
            if (msg) { msg.reactions = d.data.reactions; msg.my_reactions = d.data.my_reactions; }
        }
        renderChatList();
    }
};

window.ws_rsvpMeeting = async function(meetingId, rsvp) {
    const d = await post({action:'rsvp_meeting', project_id: state.projectId, meeting_id: meetingId, rsvp});
    if (d.success) { state.meetings = d.data; rerenderPanel('meetings'); }
};

/* ── Panel: Feed, Meetings, Timelog, Files ── */
function renderFeed(side, header, content, refreshBtn) {
    const typeIcons = {task:'✅',meeting:'📅',project:'📁',general:'💬'};
    header.innerHTML = `<span class="ws-panel-title">Activity Feed</span><div style="display:flex;gap:4px">
        <button class="btn btn-ghost btn-sm" onclick="ws_markAllRead()" style="font-size:.75rem">Mark all read</button>
        ${refreshBtn}
    </div>`;
    let html = (state.feed || []).map(u => {
        const unread = !u.is_read && parseInt(u.user_id) !== ME.id;
        return `<div class="update-item ${unread?'unread':''} ${u.is_pinned?'pinned':''}" id="feedItem_${u.id}">
            <div class="update-icon">${typeIcons[u.type]||'•'}</div>
            <div class="update-content">
                ${u.is_pinned?'<span style="font-size:.7rem;font-weight:700;color:var(--accent)">📌 PINNED</span><br>':''}
                <div class="update-message">${esc(u.message)}</div>
                <div class="update-meta"><strong>${esc(u.full_name)}</strong> · ${formatTs(u.created_at)}</div>
            </div>
            <div style="display:flex;gap:4px;flex-direction:column;align-items:flex-end">
                ${unread?`<button onclick="ws_markRead(${u.id})" class="btn btn-ghost btn-sm" style="font-size:.7rem">✓ Read</button>`:''}
                ${ME.role==='admin'?`<button onclick="ws_pinUpdate(${u.id})" class="btn btn-ghost btn-sm" style="font-size:.7rem">📌</button>`:''}
            </div>
        </div>`;
    }).join('');
    content.innerHTML = html || '<div class="empty-state"><p>No updates yet.</p></div>';
}
window.ws_markRead = async function(id) {
    await post({action:'mark_feed_read', project_id: state.projectId, ids: String(id)});
    const u = state.feed.find(x=>parseInt(x.id)===id); if (u) u.is_read = 1; rerenderPanel('feed');
};
window.ws_markAllRead = async function() {
    const ids = (state.feed||[]).filter(u=>!u.is_read && parseInt(u.user_id)!==ME.id).map(u=>u.id).join(',');
    if (!ids) return;
    await post({action:'mark_feed_read', project_id: state.projectId, ids});
    state.feed.forEach(u => u.is_read = 1); rerenderPanel('feed');
};
window.ws_pinUpdate = async function(id) {
    const d = await post({action:'pin_update', project_id: state.projectId, update_id: id});
    if (d.success) { state.feed = d.data; rerenderPanel('feed'); }
};

function renderMeetings(side, header, content, refreshBtn) {
    header.innerHTML = `<span class="ws-panel-title">Meetings</span><div style="display:flex;gap:4px"><a href="${<?= json_encode(BASE_URL) ?>}/modules/meetings/create.php?project_id=${state.projectId}" target="_blank" class="btn btn-primary btn-sm" style="font-size:.75rem">+ New</a>${refreshBtn}</div>`;
    const meetings = state.meetings || [];
    const users = state.members || [];
    const userOpts = users.map(u=>`<option value="${u.id}">${esc(u.full_name)}</option>`).join('');
    let html = meetings.map(m => {
        const statusCls = m.status === 'done' ? 'done-meet' : m.status;
        const actionsHtml = (m.action_items||[]).map(ai => `<div class="action-item ${ai.is_done?'done':''}">
            <button onclick="ws_completeAction(${ai.id})" style="width:18px;height:18px;border-radius:50%;border:2px solid var(--border);background:${ai.is_done?'var(--success)':'#fff'};cursor:pointer;flex-shrink:0"></button>
            <div style="flex:1">
                <div class="action-item-text" style="font-size:.8125rem">${esc(ai.description)}</div>
                <div style="font-size:.75rem;color:var(--text-muted)">${ai.assignee_name?esc(ai.assignee_name):'Unassigned'}${ai.due_date?' · Due '+formatDate(ai.due_date):''} ${ai.task_id ? ` · <a href="${<?= json_encode(BASE_URL) ?>}/modules/tasks/view.php?id=${ai.task_id}" target="_blank" style="color:var(--accent)">Linked Task</a>` : ''}</div>
            </div>
            ${(!ai.task_id) ? `<button onclick="ws_generateTask(${ai.id})" class="btn btn-ghost btn-sm" style="font-size:.7rem" title="Generate Task">⚙ Task</button>` : ''}
        </div>`).join('');
        
        return `
        <details style="border-bottom:1px solid var(--border);padding:10px 0" id="meet_${m.id}">
            <summary style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:2px 0;list-style:none">
                <span style="flex:1;font-weight:500;font-size:.875rem">${esc(m.title)}</span>
                <span style="font-size:.75rem;color:var(--text-muted)">${formatDate(m.meeting_date)}</span>
                <span class="badge badge-${statusCls}">${m.status}</span>
            </summary>
            <div style="padding:12px 10px 4px;background:var(--bg);border-radius:8px;margin-top:8px">
                <div style="display:flex;gap:15px;margin-bottom:12px;font-size:.75rem;color:var(--text-muted)">
                    <span>🕒 ${new Date(m.meeting_date).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</span>
                    ${m.location_or_link ? `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">📍 ${esc(m.location_or_link)}</span>` : ''}
                </div>
                ${m.agenda ? `<div style="margin-bottom:12px"><div style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);font-weight:700">Agenda</div><div style="font-size:.8125rem">${esc(m.agenda)}</div></div>` : ''}
                ${m.notes ? `<div style="margin-bottom:12px"><div style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);font-weight:700">Notes</div><div style="font-size:.8125rem;white-space:pre-wrap">${esc(m.notes)}</div></div>` : ''}
                <div style="display:flex;gap:6px;align-items:center;margin-bottom:12px">
                    <span style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;flex-shrink:0">RSVP:</span>
                    ${['yes','no','maybe'].map(r=>`<button class="btn btn-sm ${(m.my_rsvp||null)===r?'btn-primary':'btn-ghost'}" style="font-size:.72rem;padding:2px 10px" onclick="ws_rsvpMeeting(${m.id},'${r}')">${r.charAt(0).toUpperCase()+r.slice(1)}</button>`).join('')}
                </div>
                <div style="margin-bottom:8px">
                    <div style="font-size:.7rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;margin-bottom:4px">Action Items</div>
                    ${actionsHtml || '<div style="font-size:.75rem;font-style:italic;color:var(--text-muted)">No action items.</div>'}
                </div>
                <div id="addAction_${m.id}" style="display:none;margin-top:10px">
                    <div class="inline-form">
                        <input id="aiDesc_${m.id}" class="form-control" placeholder="New action item..." style="margin-bottom:6px">
                        <div style="display:flex;gap:6px;margin-bottom:6px">
                            <select id="aiAssign_${m.id}" class="form-control" style="flex:1"><option value="">Unassigned</option>${userOpts}</select>
                            <input id="aiDue_${m.id}" type="date" class="form-control" style="flex:1">
                        </div>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-primary btn-sm" onclick="ws_addAction(${m.id})">Add</button>
                            <button class="btn btn-secondary btn-sm" onclick="document.getElementById('addAction_${m.id}').style.display='none'">Cancel</button>
                        </div>
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px solid var(--border)">
                    <button onclick="document.getElementById('addAction_${m.id}').style.display='block'" class="btn btn-ghost btn-sm" style="font-size:.75rem">+ Add Action</button>
                    <a href="${<?= json_encode(BASE_URL) ?>}/modules/meetings/view.php?id=${m.id}" target="_blank" class="btn btn-ghost btn-sm" style="font-size:.75rem">Full View &rarr;</a>
                </div>
            </div>
        </details>`;
    }).join('');
    content.innerHTML = html || '<div class="empty-state"><p>No meetings linked.</p></div>';
}
window.ws_completeAction = async function(aid) { const d = await post({action:'complete_action_item', project_id: state.projectId, action_id: aid}); if (d.success) { state.meetings = d.data; rerenderPanel('meetings'); } };
window.ws_generateTask = async function(aid) { const d = await post({action:'generate_task', project_id: state.projectId, action_id: aid}); if (d.success) { state.meetings = d.data; rerenderPanel('meetings'); } };
window.ws_addAction = async function(mid) {
    const desc = document.getElementById('aiDesc_'+mid)?.value?.trim();
    if (!desc) return;
    const d = await post({action:'add_action_item', project_id: state.projectId, meeting_id: mid, description: desc, assigned_to: document.getElementById('aiAssign_'+mid)?.value||0, due_date: document.getElementById('aiDue_'+mid)?.value||''});
    if (d.success) { state.meetings = d.data; rerenderPanel('meetings'); }
};

function renderTimelog(side, header, content, refreshBtn) {
    header.innerHTML = `<span class="ws-panel-title">Time Log</span><div>${refreshBtn}</div>`;
    const tl = state.timelog || {};
    const logs = tl.logs || [];
    const breakdown = tl.member_breakdown || [];

    let logsHtml = '';
    if (logs.length) {
        logsHtml = `<div style="margin-top:14px">
            <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:6px">Recent Entries</div>
            <table style="width:100%;font-size:.8rem;border-collapse:collapse">
                <thead><tr style="font-size:.7rem;color:var(--text-muted);text-align:left">
                    <th style="padding:4px 6px;font-weight:600">Task</th>
                    <th style="padding:4px 6px;font-weight:600">Who</th>
                    <th style="padding:4px 6px;font-weight:600;text-align:right">Hrs</th>
                    <th style="padding:4px 6px;font-weight:600">Date</th>
                </tr></thead>
                <tbody>${logs.slice(0,20).map(l=>`
                <tr style="border-top:1px solid var(--border)">
                    <td style="padding:5px 6px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${esc(l.task_title)}">${esc(l.task_title)}</td>
                    <td style="padding:5px 6px"><span class="avatar avatar-sm" style="background:${getAvatarColor(l.full_name)};font-size:.6rem" title="${esc(l.full_name)}">${getInitials(l.full_name)}</span></td>
                    <td style="padding:5px 6px;text-align:right;font-weight:600">${parseFloat(l.hours).toFixed(1)}</td>
                    <td style="padding:5px 6px;color:var(--text-muted)">${l.logged_at}</td>
                </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
    }

    let breakdownHtml = '';
    if (breakdown.length) {
        breakdownHtml = `<div style="margin-top:14px">
            <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:6px">By Member</div>
            ${breakdown.map(b=>`
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                <span class="avatar avatar-sm" style="background:${getAvatarColor(b.full_name)};font-size:.6rem">${getInitials(b.full_name)}</span>
                <span style="flex:1;font-size:.8rem">${esc(b.full_name)}</span>
                <strong style="font-size:.8rem">${parseFloat(b.hours).toFixed(1)}h</strong>
            </div>`).join('')}
        </div>`;
    }

    content.innerHTML = `<div style="padding:14px">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:0">
            <div class="stat-card" style="box-shadow:none"><div class="stat-value">${(parseFloat(tl.total_logged)||0).toFixed(1)}h</div><div class="stat-label">Logged</div></div>
            <div class="stat-card" style="box-shadow:none"><div class="stat-value">${tl.task_done||0}/${tl.task_total||0}</div><div class="stat-label">Tasks done</div></div>
        </div>
        ${breakdownHtml}
        <div class="inline-form" style="margin-top:14px">
            <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:6px">Log Time</div>
            <select id="tl_task" class="form-control" style="margin-bottom:6px"><option value="">Select task…</option>${(state.tasks||[]).map(t=>`<option value="${t.id}">${esc(t.title)}</option>`).join('')}</select>
            <div style="display:flex;gap:6px;margin-bottom:6px">
                <input id="tl_hours" type="number" step="0.5" min="0.5" class="form-control" placeholder="Hours" style="flex:1">
                <input id="tl_date" type="date" class="form-control" value="${new Date().toISOString().slice(0,10)}" style="flex:1">
            </div>
            <input id="tl_note" class="form-control" placeholder="Note (optional)" style="margin-bottom:6px">
            <button class="btn btn-primary btn-sm" onclick="ws_addTimelog()">Log Time</button>
        </div>
        ${logsHtml}
    </div>`;
}
window.ws_addTimelog = async function() {
    const taskId = document.getElementById('tl_task')?.value;
    const hours = document.getElementById('tl_hours')?.value;
    if (!taskId || !hours) return;
    const d = await post({action:'add_timelog', project_id: state.projectId, task_id: taskId, hours, logged_at: document.getElementById('tl_date')?.value, note: document.getElementById('tl_note')?.value||''});
    if (d.success) { state.timelog = d.data; rerenderPanel('timelog'); }
};

function renderFiles(side, header, content, refreshBtn) {
    header.innerHTML = `<span class="ws-panel-title">Files & Links</span><div>${refreshBtn}</div>`;
    const files = state.files || [];
    const taskOpts = (state.tasks||[]).map(t=>`<option value="${t.id}">${esc(t.title)}</option>`).join('');

    let tableHtml = '';
    if (files.length) {
        tableHtml = `<table style="width:100%;font-size:.8125rem;border-collapse:collapse"><tbody>` +
        files.map(f => `
        <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:8px 6px">
                <a href="${esc(f.url)}" target="_blank" rel="noopener" style="font-weight:500">${esc(f.label)}</a>
                <div style="font-size:.7rem;color:var(--text-muted)">${esc(f.task_title)} · ${esc(f.full_name)}</div>
            </td>
            <td style="padding:8px 6px;text-align:right;white-space:nowrap">
                <button onclick="ws_deleteFile(${f.id})" class="btn btn-ghost btn-sm btn-icon" title="Remove" style="font-size:.75rem;color:var(--danger)">✕</button>
            </td>
        </tr>`).join('') + `</tbody></table>`;
    } else {
        tableHtml = '<div class="empty-state" style="padding:20px"><p>No file links yet.</p></div>';
    }

    content.innerHTML = `<div style="padding:14px">
        <div class="inline-form" style="margin-bottom:14px">
            <div style="font-size:.7rem;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:6px">Add File / Link</div>
            <input id="fl_label" class="form-control" placeholder="Label (e.g. Design Mockup)" style="margin-bottom:6px">
            <input id="fl_url" class="form-control" placeholder="URL (https://...)" style="margin-bottom:6px">
            <select id="fl_task" class="form-control" style="margin-bottom:6px"><option value="">Link to task (optional)</option>${taskOpts}</select>
            <button class="btn btn-primary btn-sm" onclick="ws_addFile()">Add Link</button>
        </div>
        ${tableHtml}
    </div>`;
}
window.ws_addFile = async function() {
    const label = document.getElementById('fl_label')?.value?.trim();
    const url   = document.getElementById('fl_url')?.value?.trim();
    const taskId = document.getElementById('fl_task')?.value;
    if (!label || !url) { window.showToast('Label and URL required','error'); return; }
    const d = await post({action:'add_file', project_id: state.projectId, label, url, task_id: taskId||0});
    if (d.success) { state.files = d.data; rerenderPanel('files'); }
};
window.ws_deleteFile = async function(id) {
    if (!confirm('Remove this file link?')) return;
    const d = await post({action:'delete_file', project_id: state.projectId, file_id: id});
    if (d.success) { state.files = d.data; rerenderPanel('files'); }
};

/* ── Panel: Details ── */
function renderDetails(side, header, content, refreshBtn) {
    header.innerHTML = `<span class="ws-panel-title">Project Details</span><div>${refreshBtn}</div>`;
    const p = state.project || {};
    const myProjRole = (state.members.find(u=>parseInt(u.id)===ME.id)||{}).proj_role;
    const canEdit = ME.role === 'admin' || myProjRole === 'lead';
    
    let html = `<div class="details-form">
        <form id="detailsForm">
            <div class="form-group">
                <label>Project Name</label>
                <input name="name" class="form-control" value="${esc(p.name)}" ${!canEdit?'disabled':''}>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" ${!canEdit?'disabled':''}>
                    ${['planning','active','on_hold','completed','archived'].map(s=>`<option value="${s}" ${p.status===s?'selected':''}>${ucfirst(s.replace('_',' '))}</option>`).join('')}
                </select>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" ${!canEdit?'disabled':''}>${esc(p.description)}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px">
                <div class="form-group">
                    <label>Start Date</label>
                    <input name="start_date" type="date" class="form-control" value="${p.start_date||''}" ${!canEdit?'disabled':''}>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input name="due_date" type="date" class="form-control" value="${p.due_date||''}" ${!canEdit?'disabled':''}>
                </div>
            </div>
            <div class="form-group">
                <label>Client Name</label>
                <input name="client_name" class="form-control" value="${esc(p.client_name)}" ${!canEdit?'disabled':''}>
            </div>
            <div class="form-group">
                <label>Tech Notes</label>
                <textarea name="tech_notes" class="form-control" rows="5" ${!canEdit?'disabled':''}>${esc(p.tech_notes)}</textarea>
            </div>
            ${canEdit ? `<button type="button" class="btn btn-primary" onclick="ws_saveDetails()">Save Changes</button>` : ''}
        </form>
    </div>`;
    content.innerHTML = html;
}

window.ws_saveDetails = async function() {
    const form = document.getElementById('detailsForm');
    if (!form) return;
    
    // Create data object from form
    const data = { action: 'update_project_details', project_id: state.projectId };
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.name) data[input.name] = input.value;
    });
    
    try {
        const d = await post(data);
        if (d.success) {
            state.project = d.data;
            updateProjectMeta();
            window.showToast("Project updated successfully.", "success");
            renderPanel('A'); 
        } else {
            window.showToast(d.message || "Update failed", "error");
        }
    } catch (e) {
        window.showToast("Connection error: " + e.message, "error");
    }
};

function renderMentionsPanel(side, header, content, refreshBtn) {
    header.innerHTML = `<span class="ws-panel-title">@ Mentions</span><div>${refreshBtn}</div>`;
    const mentions = state.mentions || [];
    if (!mentions.length) {
        content.innerHTML = '<div class="empty-state"><p>No mentions found for you in this project.</p></div>';
        return;
    }
    let html = '<div style="display:flex;flex-direction:column">';
    mentions.forEach(m => {
        const initials = getInitials(m.full_name);
        const color = getAvatarColor(m.full_name);
        html += `
        <div class="mention-list-item" onclick="ws_scrollToChat(${m.id})" style="display:flex;gap:10px;align-items:flex-start;padding:10px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .15s" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
            <span class="avatar avatar-sm" style="background:${color};flex-shrink:0">${initials}</span>
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">
                    <strong style="font-size:.8125rem;color:${color}">${esc(m.full_name)}</strong>
                    <span style="font-size:.7rem;color:var(--text-muted)">${formatTs(m.created_at)}</span>
                </div>
                ${m.parent_id ? `<div style="font-size:.7rem;color:var(--text-muted);margin-bottom:3px;padding-left:6px;border-left:2px solid var(--border)">↩ ${esc(m.parent_name)}: ${esc((m.parent_body||'').substring(0,40))}…</div>` : ''}
                <div style="font-size:.8125rem;line-height:1.4;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${renderMentions(m.body)}</div>
            </div>
            <span style="font-size:.65rem;color:var(--accent);flex-shrink:0;align-self:center">→</span>
        </div>`;
    });
    html += '</div>';
    content.innerHTML = html;
}

window.ws_scrollToChat = function(id) {
    // Jump to chat panel
    ws_scrollToMessage(id);
};


/* ── Helpers ──────────────────────────────────────────── */
function rerenderPanel(panel) {
    if (panel === 'chat') renderChatPanel();
    else if (state.panelA === panel) renderContent('A', panel);
}

function getInitials(name) {
    return name.split(' ').filter(w => w.length > 0).map(w => w[0].toUpperCase()).join('') || '?';
}

function getAvatarColor(name) {
    const initials = getInitials(name);
    const colors = ['#4F6BED','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4','#F97316'];
    let hash = 0;
    for (let i = 0; i < initials.length; i++) hash += initials.charCodeAt(i);
    return colors[hash % colors.length];
}

function renderMentions(text) { return text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/@(\w+)/g,'<span class="mention">@$1</span>'); }
function esc(s) { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function ucfirst(s) { return s ? s.charAt(0).toUpperCase()+s.slice(1) : ''; }
function formatDate(d) { if (!d) return ''; return new Date(d+'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric'}); }
function formatTs(ts) { const d = new Date(ts.replace(' ','T')); return d.toLocaleString('en-US',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}); }
async function post(data) {
    const form = new FormData();
    Object.entries(data).forEach(([k,v]) => { if(v!==null) form.append(k, v); });
    form.append('csrf_token', <?= json_encode(csrfToken()) ?>);
    try {
        const r = await fetch(API, {method:'POST', body: form});
        if (!r.ok) {
            const text = await r.text();
            throw new Error(`HTTP ${r.status}: ${text}`);
        }
        return r.json();
    } catch (e) {
        console.error("API Error:", e);
        throw e;
    }
}

/* ── Polling ──────────────────────────────────────────── */
function startSyncTimer() {
    setInterval(() => {
        const secs = Math.round((Date.now()-state.syncTime)/1000);
        const el = document.getElementById('syncBar');
        if (el) el.textContent = secs < 5 ? 'Last updated: just now' : `Last updated: ${secs}s ago`;
    }, 1000);

    // Auto-refresh every 10 seconds
    setInterval(async () => {
        if (!state.projectId) return;
        try {
            await doFullRefresh();
        } catch(e) {
            showReconnecting();
        }
    }, 30000);
}

async function doFullRefresh() {
    const c = await post({action:'get_chat', project_id: state.projectId, last_id: state.lastChatId});
    if (c.success) {
        if (c.data.chat && c.data.chat.length) {
            state.chat = state.chat.concat(c.data.chat);
            state.lastChatId = maxId(state.chat);
        }
        state.pinnedChat = c.data.pinned_chat || [];
        renderChatList();
    }
    const f = await post({action:'get_feed', project_id: state.projectId, last_id: state.lastFeedId});
    if (f.success && f.data.length) {
        state.feed = f.data.concat(state.feed);
        state.lastFeedId = maxId(state.feed);
        rerenderPanel('feed');
    }
    await refreshPanel(state.panelA);
    rerenderPanel(state.panelA);
    state.syncTime = Date.now();
    document.getElementById('reconnectBar').style.display = 'none';
}

window.ws_refreshAll = async function() {
    if (!state.projectId) return;
    const icon = document.getElementById('refreshIcon');
    if (icon) icon.classList.add('spinning');
    try {
        await doFullRefresh();
    } catch(e) {
        showReconnecting();
    } finally {
        if (icon) icon.classList.remove('spinning');
    }
};

function showReconnecting() { document.getElementById('reconnectBar').style.display = 'block'; }

function setupDivider() {
    const divider = document.getElementById('wsDivider'), split = document.getElementById('wsSplit');
    let dragging = false, startX = 0, startW = 0;
    divider.addEventListener('mousedown', e => { dragging = true; startX = e.clientX; startW = document.getElementById('panelA').offsetWidth; });
    document.addEventListener('mousemove', e => {
        if (!dragging) return;
        const dx = e.clientX - startX;
        document.getElementById('panelA').style.width = Math.max(200, startW+dx)+'px';
    });
    document.addEventListener('mouseup', () => dragging = false);
}

/* ── Keyboard shortcuts ───────────────────────────────── */
document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === 'Escape') { closeDrawer('taskDrawer'); }
    else if (e.key === 'r' || e.key === 'R') { ws_refreshAll(); }
    else if (e.key === 't' || e.key === 'T') { if (state.projectId) ws_switchPanel('A', 'tasks'); }
    else if (e.key === 'c' || e.key === 'C') { document.getElementById('chatInput')?.focus(); }
    else if (e.key === '?') { window.showToast('Shortcuts: R=Refresh  T=Tasks  C=Chat (focus)  Esc=Close drawer', 'info', 5000); }
});

init();
})();
</script>
<?php layoutEnd(); ?>
