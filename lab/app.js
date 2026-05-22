/* ─── STATE ─────────────────────────────────────── */
const S = {
    user: null,
    isTeacher: false,
    online: [],
    academicLb: [],
    arcadePodium: [],
    matches: [],
    defending: null,
    clashMatch: null,
    sprintMatch: null,
    heartbeatInterval: null,
    pushedTask: null,
    mcqTaskId: 0,
    mcqCorrectCount: 0,
    sseReconnects: 0,
    sprintQuestionsStarted: false,
    wasBlocked: false,
    sprintCancelHandler: null,
    clashCancelHandler: null,
};

/* ─── DOM REFS ──────────────────────────────────── */
const $ = (id) => document.getElementById(id) || document.getElementById(id.replace(/([A-Z])/g,'-$1').toLowerCase());
const _ = (sel) => document.querySelector(sel);
const __ = (sel) => document.querySelectorAll(sel);

const DOM = {};
['loginWrapper','usernameInput','stepPin','pinInput','loginBtn',
 'stepCreatePin','createPinInput','registerBtn','loginLoader','loginError',
 'step1','step2',
 'teacherDashboard','studentDashboard','userGreeting','studentPoints',
 'logoutBtn','logoutBtnStudent','podium','academicLeaderboard','onlineList',
 'sprintGame','defendGame','clashGame','car1','car2','sprintQuestion',
 'sprintAnswer','sprintStatus','defendCanvas','defendInput','defendScore',
 'defendLives','clashBoard','clashStatus','lockOverlay','unlockBtn',
 'mcqModal','mcqBody','studentGrid','warningsList','aiJsonInput',
 'importTasksBtn','importStatus','onlineStudentsPanel','warningsPanel',
  'cancelSprint','cancelClash','sseStatus','sseStatusText','studentResults',
   'sprintName1','sprintName2','speed1','speed2','boost1','boost2','sprintStreak',
   'opponentSelect','oppBack','oppTitle','oppAuto','oppList',
   'defendConfig','defendBack','defendStart','defendTimer','defendTimerValue',
   'defendStatus','cancelDefend','defendTitle'].forEach(id => DOM[id] = $(id));

/* ─── API HELPERS ──────────────────────────────── */
async function api(url, data) {
    try {
        const body = typeof data === 'string' ? data : new URLSearchParams(data);
        const r = await fetch(url, { method: 'POST', body });
        return await r.json();
    } catch (err) {
        showToast('🌐 Network error: ' + err.message, 'error');
        return { error: err.message };
    }
}
async function apiGet(url) {
    try {
        const r = await fetch(url);
        return await r.json();
    } catch (err) {
        showToast('🌐 Network error: ' + err.message, 'error');
        return { error: err.message };
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function showToast(msg, type) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = msg;
    el.className = 'toast ' + (type || 'info');
    el.classList.remove('hidden');
    clearTimeout(el._timer);
    el._timer = setTimeout(() => el.classList.add('hidden'), 4000);
}

function setLoading(btn, loading) {
    if (!btn) return;
    if (loading) {
        btn.classList.add('btn-loading');
        btn._origHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner"></span> ' + (btn.textContent || '');
    } else {
        btn.classList.remove('btn-loading');
        if (btn._origHtml) { btn.innerHTML = btn._origHtml; delete btn._origHtml; }
    }
}

/* ─── AUTH ──────────────────────────────────────── */
DOM.usernameInput.addEventListener('input', async function () {
    const val = this.value.trim();
    DOM.stepPin.classList.add('hidden');
    DOM.stepCreatePin.classList.add('hidden');
    DOM.loginError.classList.add('hidden');
    if (val.length < 2) return;
    DOM.loginLoader.textContent = '⏳ Checking...';
    DOM.loginLoader.classList.remove('hidden');
    const res = await api('auth.php', { action: 'check-user', username: val });
    DOM.loginLoader.classList.add('hidden');
    if (!res.success) return;
    if (res.exists) {
        DOM.stepPin.classList.remove('hidden');
        DOM.stepCreatePin.classList.add('hidden');
        DOM.pinInput.value = '';
        DOM.pinInput.focus();
        DOM.step1.className = 'step active';
        DOM.step2.className = 'step active';
    } else {
        DOM.stepCreatePin.classList.remove('hidden');
        DOM.stepPin.classList.add('hidden');
        DOM.createPinInput.value = '';
        DOM.createPinInput.focus();
        DOM.step1.className = 'step active';
        DOM.step2.className = 'step active';
    }
});

async function doLogin() {
    const username = DOM.usernameInput.value.trim();
    const pin = DOM.pinInput.value.trim();
    if (!username || !pin) return;
    setLoading(DOM.loginBtn, true);
    const res = await api('auth.php', { action: 'login', username, pin });
    setLoading(DOM.loginBtn, false);
    if (res.success) { onLogin(res.user); DOM.loginError.classList.add('hidden'); }
    else { DOM.loginError.textContent = res.error || 'Login failed'; DOM.loginError.classList.remove('hidden'); }
}

async function doRegister() {
    const username = DOM.usernameInput.value.trim();
    const pin = DOM.createPinInput.value.trim();
    if (!username || pin.length < 4) {
        DOM.loginError.textContent = 'PIN must be 4+ characters';
        DOM.loginError.classList.remove('hidden');
        return;
    }
    setLoading(DOM.registerBtn, true);
    const res = await api('auth.php', { action: 'register', username, pin });
    setLoading(DOM.registerBtn, false);
    if (res.success) { onLogin(res.user); DOM.loginError.classList.add('hidden'); }
    else { DOM.loginError.textContent = res.error || 'Registration failed'; DOM.loginError.classList.remove('hidden'); }
}

DOM.loginBtn.addEventListener('click', doLogin);
DOM.registerBtn.addEventListener('click', doRegister);
[DOM.pinInput, DOM.createPinInput].forEach(el => el.addEventListener('keydown', e => {
    if (e.key === 'Enter') el === DOM.pinInput ? doLogin() : doRegister();
}));

async function doLogout() {
    await api('auth.php', { action: 'logout' });
    location.reload();
}
DOM.logoutBtn.addEventListener('click', doLogout);
DOM.logoutBtnStudent.addEventListener('click', doLogout);

function onLogin(user) {
    S.user = user;
    S.isTeacher = user.is_teacher;
    DOM.loginWrapper.classList.add('hidden');
    if (user.is_teacher) {
        DOM.teacherDashboard.classList.remove('hidden');
        startHeartbeat();
        connectSSE();
    } else {
        DOM.studentDashboard.classList.remove('hidden');
        DOM.userGreeting.textContent = `Welcome, ${user.username}`;
        updatePointsDisplay();
        startHeartbeat();
        connectSSE();
    }
}

function updatePointsDisplay() {
    if (S.user) DOM.studentPoints.textContent = `⭐ ${S.user.points_academic} | 🎮 ${S.user.points_arcade}`;
}

/* ─── SESSION CHECK ────────────────────────────── */
async function checkSession() {
    const res = await api('auth.php', { action: 'check-session' });
    if (res.active) onLogin(res.user);
}
checkSession();

/* ─── HEARTBEAT ────────────────────────────────── */
function startHeartbeat() {
    if (S.heartbeatInterval) clearInterval(S.heartbeatInterval);
    S.heartbeatInterval = setInterval(() => {
        api('api.php', { action: 'heartbeat' });
    }, 15000);
}

/* ─── SSE ───────────────────────────────────────── */
function connectSSE() {
    const evtSource = new EventSource('stream.php');
    S.sseReconnects = 0;

    evtSource.onopen = function () {
        S.sseReconnects = 0;
        if (DOM.sseStatus) DOM.sseStatus.className = '';
        if (DOM.sseStatusText) DOM.sseStatusText.textContent = 'Live';
    };

    evtSource.onmessage = function (e) {
        try {
            const d = JSON.parse(e.data);
            S.online = d.online || [];
            S.academicLb = d.academic_lb || [];
            S.arcadePodium = d.arcade_podium || [];
            S.matches = d.matches || [];
            renderAll();

            // Blocked student enforcement
            if (S.user && !S.isTeacher) {
                const me = S.online.find(s => s.id === S.user.id);
                if (me && me.is_blocked && !S.wasBlocked) {
                    S.wasBlocked = true;
                    showToast('🔒 You have been blocked by the teacher!', 'error');
                    setTimeout(() => doLogout(), 2000);
                }
                if (me && !me.is_blocked) S.wasBlocked = false;
            }
        } catch (err) { /* ignore parse errors */ }
    };

    evtSource.onerror = function () {
        S.sseReconnects++;
        if (DOM.sseStatus) DOM.sseStatus.className = 'reconnecting';
        if (DOM.sseStatusText) DOM.sseStatusText.textContent = 'Reconnecting...';
    };
}

/* ─── RENDER ALL ────────────────────────────────── */
function renderAll() {
    if (S.isTeacher) {
        renderStudentGrid();
        renderWarnings();
    } else {
        renderPodium();
        renderLeaderboard();
        renderOnlineList();
        handleMatches();
        checkPushedTask();
    }
}

/* ─── PODIUM ────────────────────────────────────── */
function renderPodium() {
    const data = S.arcadePodium;
    const container = DOM.podium;
    container.innerHTML = '';
    if (!data.length) { container.innerHTML = '<p style="color:var(--text-dim);">No arcade points yet</p>'; return; }
    // Order: rank2, rank1, rank3
    const ordered = [];
    const map = { 2: 0, 1: 1, 3: 2 };
    const emojis = ['🥇','🥈','🥉'];
    for (let i = 0; i < 3; i++) {
        ordered.push(data[i] || null);
    }
    const display = [ordered[1], ordered[0], ordered[2]]; // rank2(1) left, rank1(0) center, rank3(2) right
    const classes = ['podium-rank2', 'podium-rank1', 'podium-rank3'];
    const medals = ['🥈','🥇','🥉'];
    display.forEach((item, idx) => {
        const div = document.createElement('div');
        div.className = `podium-item ${classes[idx]}`;
        if (!item) {
            div.innerHTML = `<span class="podium-emoji">${medals[idx]}</span><span class="podium-name">---</span>`;
        } else {
            div.innerHTML = `
                <span class="podium-emoji">${medals[idx]}</span>
                <span class="podium-name">${escapeHtml(item.username)}</span>
                <span class="podium-score">${item.total_arcade_points}</span>
            `;
        }
        container.appendChild(div);
    });
}

/* ─── LEADERBOARD ───────────────────────────────── */
function renderLeaderboard() {
    const container = DOM.academicLeaderboard;
    container.innerHTML = '';
    const data = S.academicLb;
    if (!data.length) { container.innerHTML = '<p style="color:var(--text-dim);">No data yet</p>'; return; }
    data.forEach((s, i) => {
        const row = document.createElement('div');
        row.className = 'lb-row';
        row.innerHTML = `
            <span class="lb-rank">#${i + 1}</span>
            <span class="lb-name">${escapeHtml(s.username)}</span>
            <span class="lb-points">${s.total_academic_points}</span>
        `;
        container.appendChild(row);
    });
}

/* ─── ONLINE LIST ──────────────────────────────── */
function renderOnlineList() {
    const container = DOM.onlineList;
    container.innerHTML = '';
    const me = S.user ? S.user.id : 0;
    const others = S.online.filter(s => s.id !== me);
    if (!others.length) { container.innerHTML = '<p style="color:var(--text-dim);">No other students online</p>'; return; }
    others.forEach(s => {
        const div = document.createElement('div');
        div.className = 'online-item' + (s.is_blocked ? ' blocked' : '');
        div.innerHTML = `
            <span><span class="online-indicator"></span> ${escapeHtml(s.username)}</span>
            <span style="font-size:0.8rem;color:var(--text-dim);">${s.current_screen || 'dashboard'}</span>
        `;
        container.appendChild(div);
    });
}

/* ─── MATCHES ──────────────────────────────────── */
function handleMatches() {
    if (!S.user) return;
    const myMatches = S.matches.filter(m => m.player1_id === S.user.id || m.player2_id === S.user.id);
    myMatches.forEach(m => {
        const isP1 = m.player1_id === S.user.id;
        if (m.game_type === 'sprint') {
            if (DOM.sprintGame.classList.contains('hidden')) {
                DOM.sprintGame.classList.remove('hidden');
                enterFullscreen(DOM.sprintGame);
                const sp1 = m.player1_id === S.user.id;
                DOM.sprintName1.textContent = sp1 ? '🚗 You' : `🚗 ${m.p1_name || 'Opp'}`;
                DOM.sprintName2.textContent = sp1 ? `🚀 ${m.p2_name || 'Opp'}` : '🚀 You';
                DOM.sprintStatus.textContent = '🎮 Match found! Answer to move!';
                S.sprintMatch = m.id;
                DOM.cancelSprint.style.display = 'inline-flex';
                if (!S.sprintQuestionsStarted) {
                    S.sprintQuestionsStarted = true;
                    startSprintQuestions();
                }
            }
            updateSprint(m);
        }
        if (m.game_type === 'tictactoe') {
            if (DOM.clashGame.classList.contains('hidden')) {
                DOM.clashGame.classList.remove('hidden');
                enterFullscreen(DOM.clashGame);
                DOM.clashStatus.textContent = '🎮 Match found!';
                S.clashMatch = m;
                DOM.cancelClash.style.display = 'inline-flex';
            }
            S.clashMatch = m;
            updateClash(m);
        }
    });
}

/* ─── PUSHED TASK CHECK ────────────────────────── */
let lastPushedTaskId = 0;
async function checkPushedTask() {
    const res = await api('api.php', { action: 'get-pushed-task' });
    if (res.task && res.task.id !== lastPushedTaskId) {
        lastPushedTaskId = res.task.id;
        S.pushedTask = res.task;
        showPushedTask(res.task);
    }
}

function showPushedTask(task) {
    const label = task.type === 'mcq' ? '📝 Quiz' : '⌨️ Typing Test';
    showToast(`📬 Teacher pushed a ${label}!`, 'info');
    setTimeout(() => {
        if (task.type === 'mcq') {
            openMCQModal(task, true);
        } else if (task.type === 'typing') {
            startTypingTest(task, true);
        }
    }, 600);
}

/* ─── TEACHER: STUDENT GRID ──────────────────── */
function renderStudentGrid() {
    const container = DOM.studentGrid;
    container.innerHTML = '';
    S.online.forEach(s => {
        const card = document.createElement('div');
        card.className = 'student-card';
        card.innerHTML = `
            <span class="online-indicator"></span>
            <span class="name">${escapeHtml(s.username)}</span>
            <span class="points" style="font-size:0.75rem;color:var(--text-dim);">
                📚${s.total_academic_points} 🎮${s.total_arcade_points}
            </span>
            <div class="actions">
                ${s.is_blocked
                    ? `<button class="btn btn-sm btn-primary" data-action="unblock" data-id="${s.id}">Unblock</button>`
                    : `<button class="btn btn-sm btn-danger" data-action="block" data-id="${s.id}">Block</button>`
                }
                <button class="btn btn-sm" data-action="push-mcq" data-id="${s.id}">📝 MCQ</button>
                <button class="btn btn-sm" data-action="push-type" data-id="${s.id}">⌨️ Typing</button>
            </div>
        `;
        container.appendChild(card);
    });
    container.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', async function () {
            const action = this.dataset.action;
            const sid = parseInt(this.dataset.id);
            if (action === 'block') {
                await api('api.php', { action: 'block-student', student_id: sid });
            } else if (action === 'unblock') {
                await api('api.php', { action: 'unblock-student', student_id: sid });
            } else if (action === 'push-mcq' || action === 'push-type') {
                const type = action === 'push-mcq' ? 'mcq' : 'typing';
                const tasksRes = await apiGet('api.php?action=get-tasks&type=' + type);
                const tasks = tasksRes.tasks || [];
                if (!tasks.length) {
                    alert('No ' + type + ' tasks available. Import some first!');
                    return;
                }
                const taskId = tasks[0].id;
                await api('api.php', { action: 'push-task', student_id: sid, task_id: taskId });
                alert('Task pushed to student');
            }
        });
    });
}

/* ─── TEACHER: WARNINGS ───────────────────────── */
async function renderWarnings() {
    const res = await api('api.php', { action: 'get-warnings' });
    const warnings = res.warnings || [];
    const container = DOM.warningsList;
    container.innerHTML = '';
    if (!warnings.length) {
        container.innerHTML = '<p style="color:var(--text-dim);font-size:0.85rem;">No warnings logged</p>';
        return;
    }
    warnings.slice(0, 20).forEach(w => {
        const div = document.createElement('div');
        div.className = 'warning-item';
        div.innerHTML = `
            <span><span class="warning-student">${escapeHtml(w.username)}</span> — ${escapeHtml(w.warning_type)}</span>
            <span style="color:var(--text-dim);font-size:0.75rem;">${w.created_at}</span>
        `;
        container.appendChild(div);
    });
}

/* ─── AI TASK IMPORTER ──────────────────────────── */
DOM.importTasksBtn.addEventListener('click', async function () {
    const raw = DOM.aiJsonInput.value.trim();
    if (!raw) { DOM.importStatus.textContent = 'Paste JSON first!'; return; }
    let tasks;
    try { tasks = JSON.parse(raw); } catch (e) {
        DOM.importStatus.textContent = '❌ Invalid JSON: ' + e.message;
        return;
    }
    if (!Array.isArray(tasks)) tasks = [tasks];
    const normalized = tasks.map(t => ({
        type: t.type || 'mcq',
        content: t.content || t,
        time_limit: t.time_limit || 0
    }));
    setLoading(DOM.importTasksBtn, true);
    const res = await api('api.php', { action: 'import-tasks', tasks: JSON.stringify(normalized) });
    setLoading(DOM.importTasksBtn, false);
    DOM.importStatus.textContent = res.success ? `✅ Imported ${res.imported} tasks` : '❌ ' + (res.error || 'Failed');
    if (res.success) { DOM.aiJsonInput.value = ''; showToast(`✅ ${res.imported} tasks imported`, 'success'); }
});

/* ─── GAME LAUNCHERS ────────────────────────────── */
function enterFullscreen(el) {
    el.classList.add('fullscreen');
}
function exitFullscreen(el) {
    el.classList.remove('fullscreen');
}

let selectedGame = null;

document.querySelectorAll('.game-lobby-card').forEach(btn => {
    btn.addEventListener('click', function () {
        const game = this.dataset.game;
        if (game === 'defend') {
            hideAllGames();
            showDefendConfig();
            return;
        }
        selectedGame = game;
        showOpponentSelect();
    });
});

function hideAllGames() {
    stopSprintAnimation();
    DOM.sprintGame.classList.add('hidden');
    DOM.defendGame.classList.add('hidden');
    DOM.clashGame.classList.add('hidden');
    DOM.opponentSelect.classList.add('hidden');
    DOM.defendConfig.classList.add('hidden');
    exitFullscreen(DOM.sprintGame);
    exitFullscreen(DOM.defendGame);
    exitFullscreen(DOM.clashGame);
    exitFullscreen(DOM.opponentSelect);
    exitFullscreen(DOM.defendConfig);
    S.sprintMatch = null;
    S.sprintQuestionsStarted = false;
    S.clashMatch = null;
    DOM.cancelSprint.style.display = 'none';
    DOM.cancelClash.style.display = 'none';
    DOM.cancelDefend.style.display = 'none';
}

function showOpponentSelect() {
    hideAllGames();
    const titles = { sprint: '🏎️ Select Sprint Opponent', clash: '🧠 Select Clash Opponent' };
    DOM.oppTitle.textContent = titles[selectedGame] || '🎮 Select Opponent';
    renderOpponentList();
    DOM.opponentSelect.classList.remove('hidden');
}

function renderOpponentList() {
    const online = S.online.filter(u => u.id !== S.user.id && !u.is_teacher);
    if (online.length === 0) {
        DOM.oppList.innerHTML = '<div class="opp-empty">😴 No other students online. Try Auto Match to wait for someone.</div>';
        return;
    }
    DOM.oppList.innerHTML = online.map(u => `
        <div class="opp-item" data-opponent-id="${u.id}">
            <div class="opp-info">
                <span class="opp-avatar">👤</span>
                <div>
                    <div class="opp-name">${u.username}</div>
                    <div class="opp-points">⭐ ${u.total_arcade_points || 0} pts</div>
                </div>
            </div>
            <button class="btn btn-sm btn-primary opp-challenge-btn">Challenge</button>
        </div>
    `).join('');

    DOM.oppList.querySelectorAll('.opp-challenge-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const item = this.closest('.opp-item');
            const opponentId = parseInt(item.dataset.opponentId);
            launchMultiplayer(opponentId);
        });
    });
}

// Back button
DOM.oppBack.addEventListener('click', () => {
    hideAllGames();
    selectedGame = null;
});

// Auto Match button
DOM.oppAuto.addEventListener('click', () => {
    launchMultiplayer(0);
});

async function launchMultiplayer(opponentId) {
    hideAllGames();
    const gameEl = selectedGame === 'sprint' ? DOM.sprintGame : DOM.clashGame;
    const cancelBtn = selectedGame === 'sprint' ? DOM.cancelSprint : DOM.cancelClash;

    gameEl.classList.remove('hidden');
    enterFullscreen(gameEl);

    if (selectedGame === 'sprint') {
        DOM.sprintStatus.textContent = opponentId ? '🎯 Challenging opponent...' : '⏳ Finding opponent...';
        DOM.sprintAnswer.value = '';
        DOM.sprintStreak.textContent = '';
        DOM.speed1.classList.remove('visible');
        DOM.speed2.classList.remove('visible');
    } else {
        DOM.clashStatus.textContent = opponentId ? '🎯 Challenging opponent...' : '⏳ Finding opponent...';
    }

    cancelBtn.style.display = 'inline-flex';

    const params = { action: 'create-match', game_type: selectedGame === 'sprint' ? 'sprint' : 'tictactoe' };
    if (opponentId) params.opponent_id = opponentId;

    const res = await api('api.php', params);
    if (!res.success) {
        if (selectedGame === 'sprint') {
            DOM.sprintStatus.textContent = res.error === 'No opponent available' ? '⏳ Waiting for someone to join...' : '❌ ' + (res.error || 'Failed');
        } else {
            DOM.clashStatus.textContent = res.error === 'No opponent available' ? '⏳ Waiting for someone to join...' : '❌ ' + (res.error || 'Failed');
        }
        return;
    }

    let p1Name = 'You', p2Name = 'Opp';
    if (res.match_id && res.opponent) {
        p2Name = res.opponent.username;
    } else if (res.match && res.is_opponent) {
        p1Name = res.match.p2_name || 'You';
        p2Name = res.match.p1_name || 'Opp';
    }

    if (selectedGame === 'sprint') {
        S.sprintMatch = res.match_id || res.match?.id;
        DOM.sprintName1.textContent = `🚗 ${p1Name}`;
        DOM.sprintName2.textContent = `🚀 ${p2Name}`;
        DOM.sprintStatus.textContent = '🎮 Answer to go!';
        startSprintQuestions();
    } else {
        S.clashMatch = res.match_id || res.match?.id;
        DOM.clashStatus.textContent = '🎮 Match found! Your turn.';
    }
}

/* ─── DEFEND CONFIG ──────────────────────────── */
let defendType = 'mixed';
let defendMode = 'lives';

function showDefendConfig() {
    DOM.defendConfig.classList.remove('hidden');
    enterFullscreen(DOM.defendConfig);
}

// Pill toggles for Defend config
DOM.defendConfig.querySelectorAll('.defend-pills').forEach(group => {
    group.querySelectorAll('.pill').forEach(pill => {
        pill.addEventListener('click', function () {
            group.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
        });
    });
});

DOM.defendBack.addEventListener('click', () => {
    hideAllGames();
});

DOM.defendStart.addEventListener('click', () => {
    defendType = DOM.defendConfig.querySelector('#defend-type .pill.active')?.dataset.value || 'mixed';
    defendMode = DOM.defendConfig.querySelector('#defend-mode .pill.active')?.dataset.value || 'lives';
    hideAllGames();
    launchDefend();
});

DOM.cancelDefend.addEventListener('click', () => {
    if (S.defending) {
        if (S.defending.animId) cancelAnimationFrame(S.defending.animId);
        S.defending.running = false; S.defending = null;
    }
    hideAllGames();
});

/* ─── SPRINT (RACING) ───────────────────────────── */
let sprintQuestionsAnswered = 0;
let sprintStreak = 0;
const sprintTotalQuestions = 10;
let sprintAnimId = null;

function startSprintQuestions() {
    sprintQuestionsAnswered = 0;
    sprintStreak = 0;
    // Activate racing visuals
    document.querySelector('.sprint-track')?.classList.add('racing');
    DOM.car1?.classList.add('racing');
    DOM.car2?.classList.add('racing');
    DOM.speed1?.classList.add('visible');
    DOM.speed2?.classList.add('visible');
    DOM.speed1.textContent = '0 km/h';
    DOM.speed2.textContent = '0 km/h';
    askSprintQuestion();
    startSprintAnimation();
}

function startSprintAnimation() {
    if (sprintAnimId) cancelAnimationFrame(sprintAnimId);
    function animate() {
        if (!S.sprintMatch) return;
        // Find which speed element is the opponent's
        const match = S.matches.find(m => m.id === S.sprintMatch);
        if (match) {
            const isP1 = match.player1_id === S.user.id;
            const oppSpeedEl = isP1 ? DOM.speed2 : DOM.speed1;
            const mySpeedEl = isP1 ? DOM.speed1 : DOM.speed2;
            // Gentle speed shimmer for opponent
            if (oppSpeedEl && oppSpeedEl.classList.contains('visible')) {
                const cur = parseInt(oppSpeedEl.textContent) || 0;
                if (cur > 0 && Math.random() < 0.04) {
                    oppSpeedEl.textContent = `${Math.max(5, cur + Math.floor(Math.random() * 8) - 3)} km/h`;
                }
            }
            // Slight speed decay for player when idle
            if (mySpeedEl && mySpeedEl.classList.contains('visible') && Math.random() < 0.01) {
                const cur = parseInt(mySpeedEl.textContent) || 0;
                if (cur > 5) mySpeedEl.textContent = `${cur - 1} km/h`;
            }
        }
        sprintAnimId = requestAnimationFrame(animate);
    }
    animate();
}

function stopSprintAnimation() {
    if (sprintAnimId) { cancelAnimationFrame(sprintAnimId); sprintAnimId = null; }
    document.querySelector('.sprint-track')?.classList.remove('racing');
    DOM.car1?.classList.remove('racing');
    DOM.car2?.classList.remove('racing');
}

function askSprintQuestion() {
    if (sprintQuestionsAnswered >= sprintTotalQuestions) return;
    const a = Math.floor(Math.random() * 15) + 1;
    const b = Math.floor(Math.random() * 15) + 1;
    const op = Math.random() > 0.5 ? '+' : '-';
    const ans = op === '+' ? a + b : Math.max(a, b) - Math.min(a, b);
    const text = op === '+' ? `${a} + ${b}` : `${Math.max(a,b)} - ${Math.min(a,b)}`;
    DOM.sprintQuestion.innerHTML = `${text} = ? <span class="hint">Type answer, press Enter ⏎</span>`;
    DOM.sprintQuestion.dataset.answer = ans;
    DOM.sprintAnswer.value = '';
    DOM.sprintAnswer.focus();
}

function showBoost(isP1) {
    const el = isP1 ? DOM.boost1 : DOM.boost2;
    el.classList.remove('show');
    void el.offsetWidth;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 700);
    const car = isP1 ? DOM.car1 : DOM.car2;
    car.classList.remove('boosting');
    void car.offsetWidth;
    car.classList.add('boosting');
    setTimeout(() => car.classList.remove('boosting'), 300);
    // Speed jump
    const speedEl = isP1 ? DOM.speed1 : DOM.speed2;
    const cur = parseInt(speedEl.textContent) || 0;
    speedEl.textContent = `${Math.min(100, cur + 8)} km/h`;
}

function updateSpeedDisplay(isP1, streak) {
    const el = isP1 ? DOM.speed1 : DOM.speed2;
    const speed = Math.min(100, 20 + streak * 8);
    el.textContent = `${speed} km/h`;
}

DOM.sprintAnswer.addEventListener('keydown', async function (e) {
    if (e.key !== 'Enter') return;
    const val = parseInt(this.value.trim());
    const correct = parseInt(DOM.sprintQuestion.dataset.answer);
    if (isNaN(val)) return;

    const isP1 = S.matches.some(m => m.id === S.sprintMatch && m.player1_id === S.user.id);

    if (val === correct) {
        sprintQuestionsAnswered++;
        sprintStreak++;
        sprintQuestionsAnswered = Math.min(sprintQuestionsAnswered, sprintTotalQuestions);
        const pct = Math.round((sprintQuestionsAnswered / sprintTotalQuestions) * 100);
        const stateKey1 = isP1 ? 'p1_dist' : 'p2_dist';
        const stateKey2 = isP1 ? 'p2_dist' : 'p1_dist';
        const curMatch = S.matches.find(m => m.id === S.sprintMatch);
        const oppDist = curMatch && curMatch.state ? (isP1 ? (curMatch.state.p2_dist || 0) : (curMatch.state.p1_dist || 0)) : 0;

        if (isP1) {
            DOM.car1.style.left = `calc(${pct}% - 30px)`;
            DOM.car2.style.left = `calc(${oppDist}% - 30px)`;
        } else {
            DOM.car2.style.left = `calc(${pct}% - 30px)`;
            DOM.car1.style.left = `calc(${oppDist}% - 30px)`;
        }

        showBoost(isP1);
        updateSpeedDisplay(isP1, sprintStreak);

        DOM.sprintStreak.textContent = sprintStreak >= 3 ? `🔥 ${sprintStreak}x streak!` : `✅ +${sprintStreak}`;
        DOM.sprintStreak.className = 'sprint-streak' + (sprintStreak >= 3 ? ' hot' : '');

        if (sprintQuestionsAnswered >= sprintTotalQuestions) {
            DOM.sprintStatus.textContent = '🏆 You reached the finish!';
            DOM.sprintStreak.textContent = '';
            stopSprintAnimation();
            const s = {};
            s[stateKey1] = 100; s[stateKey2] = oppDist;
            s.finished = true; s.winner = S.user.id;
            await api('api.php', { action: 'update-match', match_id: S.sprintMatch, state: JSON.stringify(s) });
            await api('api.php', { action: 'end-match', match_id: S.sprintMatch, winner_id: S.user.id, points: 15 });
            DOM.cancelSprint.style.display = 'none';
            return;
        }
        const state = {};
        state[stateKey1] = pct; state[stateKey2] = oppDist;
        state.finished = false; state.winner = 0;
        await api('api.php', { action: 'update-match', match_id: S.sprintMatch, state: JSON.stringify(state) });
        askSprintQuestion();
    } else {
        sprintStreak = 0;
        DOM.sprintStreak.textContent = '❌ Streak broken!';
        DOM.sprintStreak.className = 'sprint-streak';
        this.style.borderColor = 'var(--red)';
        setTimeout(() => { this.style.borderColor = ''; }, 300);
        // Reduce speed on wrong answer
        const speedEl = isP1 ? DOM.speed1 : DOM.speed2;
        const cur = parseInt(speedEl.textContent) || 0;
        speedEl.textContent = `${Math.max(0, cur - 10)} km/h`;
    }
    this.value = '';
});

function updateSprint(match) {
    if (!match.state) return;
    const s = match.state;
    const isP1 = match.player1_id === S.user.id;
    const myDist = isP1 ? (s.p1_dist || 0) : (s.p2_dist || 0);
    const oppDist = isP1 ? (s.p2_dist || 0) : (s.p1_dist || 0);
    if (isP1) {
        DOM.car1.style.left = `calc(${myDist}% - 30px)`;
        DOM.car2.style.left = `calc(${oppDist}% - 30px)`;
    } else {
        DOM.car2.style.left = `calc(${myDist}% - 30px)`;
        DOM.car1.style.left = `calc(${oppDist}% - 30px)`;
    }
    if (s.finished) {
        DOM.sprintStatus.textContent = s.winner === S.user.id ? '🏆 You win!' : s.winner > 0 ? '😢 You lost!' : '🤝 Draw!';
        DOM.sprintStreak.textContent = '';
        stopSprintAnimation();
    }
}

/* ─── CANCEL MATCH ──────────────────────────────── */
async function cancelMatch(type) {
    const matchId = type === 'sprint' ? S.sprintMatch : (S.clashMatch ? S.clashMatch.id : 0);
    if (!matchId) return;
    await api('api.php', { action: 'end-match', match_id: matchId, winner_id: 0, points: 0 });
    if (type === 'sprint') {
        stopSprintAnimation();
        S.sprintMatch = null;
        S.sprintQuestionsStarted = false;
        DOM.cancelSprint.style.display = 'none';
        exitFullscreen(DOM.sprintGame);
        DOM.sprintGame.classList.add('hidden');
    } else {
        S.clashMatch = null;
        DOM.cancelClash.style.display = 'none';
        exitFullscreen(DOM.clashGame);
        DOM.clashGame.classList.add('hidden');
    }
    showToast('Match cancelled', 'info');
}
DOM.cancelSprint.addEventListener('click', () => cancelMatch('sprint'));
DOM.cancelClash.addEventListener('click', () => cancelMatch('clash'));

/* ─── DEFEND THE LAB (SHOOTER) ───────────────────── */
function launchDefend() {
    DOM.defendGame.classList.remove('hidden');
    DOM.defendTimer.classList.add('hidden');
    DOM.defendStatus.textContent = '';
    DOM.cancelDefend.style.display = 'inline-flex';
    const titleSuffix = { mixed: '⚔️', math: '🔢', words: '📖' }[defendType] || '🛡️';
    DOM.defendTitle.textContent = `${titleSuffix} Defend the Lab ${defendMode === 'timed' ? '(⏱️ Timed)' : ''}`;

    const canvas = DOM.defendCanvas;
    const ctx = canvas.getContext('2d');
    canvas.width = 600;
    canvas.height = 400;

    const wordBank = [
        { q: 'Legs on a dog', a: 4 }, { q: 'Sides of a square', a: 4 },
        { q: 'Fingers on one hand', a: 5 }, { q: 'Days in a week', a: 7 },
        { q: 'Toes on two feet', a: 10 }, { q: 'Wheels on a car', a: 4 },
        { q: 'Zero plus zero', a: 0 }, { q: 'Continents on Earth', a: 7 },
        { q: 'Players in a soccer team', a: 11 }, { q: 'Planets in solar system', a: 8 },
        { q: 'Colors in a rainbow', a: 7 }, { q: 'Hours in a day', a: 24 }
    ];

    const game = {
        enemies: [],
        particles: [],
        lasers: [],
        score: 0,
        lives: 3,
        timeLeft: 60,
        running: true,
        spawnTimer: 0,
        spawnRate: 90,
        animId: null,
        mode: defendMode,
        type: defendType,

        generateProblem() {
            const type = this.type;
            const useWord = type === 'words' ? true : type === 'mixed' ? Math.random() < 0.5 : false;
            if (useWord) {
                const wp = wordBank[Math.floor(Math.random() * wordBank.length)];
                return { text: wp.q, answer: wp.a, isWord: true };
            }
            const a = Math.floor(Math.random() * 10) + 1;
            const b = Math.floor(Math.random() * 10) + 1;
            const op = Math.random() > 0.5 ? '+' : '-';
            const ans = op === '+' ? a + b : Math.max(a, b) - Math.min(a, b);
            return { text: `${op === '+' ? a + ' + ' + b : Math.max(a,b) + ' - ' + Math.min(a,b)}`, answer: ans, isWord: false };
        },

        spawn() {
            if (!this.running || this.enemies.length >= 5) return;
            const prob = this.generateProblem();
            this.enemies.push({
                x: 40 + Math.random() * (canvas.width - 80),
                y: -30,
                r: 24,
                prob,
                speed: 0.2 + Math.random() * 0.3,
                hue: Math.random() * 360
            });
        },

        update() {
            for (let i = this.enemies.length - 1; i >= 0; i--) {
                const e = this.enemies[i];
                e.y += e.speed;
                if (e.y - e.r > canvas.height) {
                    this.enemies.splice(i, 1);
                    if (this.mode === 'lives') {
                        this.lives--;
                        DOM.defendLives.textContent = '❤️'.repeat(Math.max(0, this.lives));
                        if (this.lives <= 0) { this.running = false; this.gameOver(); }
                    }
                }
            }
            for (let i = this.particles.length - 1; i >= 0; i--) {
                const p = this.particles[i];
                p.x += p.vx; p.y += p.vy;
                p.life--;
                if (p.life <= 0) this.particles.splice(i, 1);
            }
            for (let i = this.lasers.length - 1; i >= 0; i--) {
                this.lasers[i].life--;
                if (this.lasers[i].life <= 0) this.lasers.splice(i, 1);
            }
            this.spawnTimer++;
            if (this.spawnTimer >= this.spawnRate) {
                this.spawnTimer = 0;
                this.spawn();
            }
        },

        draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            for (const e of this.enemies) {
                const grad = ctx.createRadialGradient(e.x, e.y, 0, e.x, e.y, e.r);
                grad.addColorStop(0, `hsla(${e.hue},90%,70%,0.35)`);
                grad.addColorStop(1, `hsla(${e.hue},90%,50%,0.1)`);
                ctx.beginPath();
                ctx.arc(e.x, e.y, e.r, 0, Math.PI * 2);
                ctx.fillStyle = grad; ctx.fill();
                ctx.strokeStyle = `hsl(${e.hue},80%,60%)`;
                ctx.lineWidth = 2; ctx.stroke();
                ctx.fillStyle = e.prob.isWord ? '#facc15' : '#fff';
                ctx.font = e.prob.isWord ? '10px monospace' : '13px monospace';
                ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                const label = e.prob.isWord ? e.prob.text : e.prob.text + '=?';
                ctx.fillText(label, e.x, e.y);
                if (e.prob.isWord) {
                    ctx.fillStyle = '#facc15';
                    ctx.font = '9px sans-serif';
                    ctx.fillText('(think...)', e.x, e.y + 18);
                }
            }

            for (const p of this.particles) {
                ctx.beginPath(); ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctx.fillStyle = p.color; ctx.fill();
            }
            for (const l of this.lasers) {
                ctx.beginPath(); ctx.moveTo(l.x1, l.y1); ctx.lineTo(l.x2, l.y2);
                ctx.shadowBlur = 25; ctx.shadowColor = '#0ff';
                ctx.strokeStyle = `rgba(0,255,255,${l.life / 12})`;
                ctx.lineWidth = 3; ctx.stroke(); ctx.shadowBlur = 0;
            }

            ctx.fillStyle = '#fff';
            ctx.font = '16px monospace';
            ctx.textAlign = 'left';
            ctx.fillText(`⭐ ${this.score}`, 10, 28);
            if (this.mode === 'timed') {
                ctx.textAlign = 'center';
                ctx.fillText(`⏱️ ${Math.ceil(this.timeLeft)}s`, canvas.width / 2, 28);
            } else {
                ctx.textAlign = 'right';
                ctx.fillText('❤️'.repeat(Math.max(0, this.lives)), canvas.width - 10, 28);
            }
        },

        explode(x, y, hue) {
            const colors = [`hsl(${hue},90%,60%)`, `hsl(${hue},90%,80%)`, '#fff'];
            for (let i = 0; i < 20; i++) {
                const angle = Math.random() * 2 * Math.PI;
                const speed = 1 + Math.random() * 4;
                this.particles.push({ x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, size: 1.5 + Math.random() * 4, color: colors[Math.floor(Math.random() * colors.length)], life: 15 + Math.random() * 20 });
            }
        },

        shoot(answer) {
            const ans = parseInt(answer);
            if (isNaN(ans)) return false;
            let best = null, bestDist = Infinity;
            for (const e of this.enemies) {
                if (e.prob.answer === ans && e.y < bestDist) { bestDist = e.y; best = e; }
            }
            if (best) {
                this.lasers.push({ x1: canvas.width / 2, y1: canvas.height - 10, x2: best.x, y2: best.y, life: 12 });
                this.explode(best.x, best.y, best.hue);
                const idx = this.enemies.indexOf(best);
                this.enemies.splice(idx, 1);
                this.score++;
                DOM.defendScore.textContent = `⭐ ${this.score}`;
                return true;
            }
            return false;
        },

        gameOver() {
            this.running = false;
            const pts = Math.floor(this.score / 2);
            api('api.php', { action: 'submit-mcq', task_id: 0, score: this.score, total: 0, points: pts });
            if (S.user) { S.user.points_academic = (S.user.points_academic || 0) + pts; updatePointsDisplay(); }
            if (this.animId) cancelAnimationFrame(this.animId);
            DOM.defendStatus.textContent = `💥 Final Score: ${this.score} (+${pts} pts)`;
            DOM.cancelDefend.style.display = 'none';
            showToast(`💥 Defend the Lab — Score: ${this.score} (+${pts} pts)`, 'info');
            setTimeout(() => {
                exitFullscreen(DOM.defendGame);
                DOM.defendGame.classList.add('hidden');
                DOM.defendTimer.classList.add('hidden');
                if (S.defending) { S.defending = null; }
            }, 300);
        },

        loop() {
            if (!this.running) return;
            if (this.mode === 'timed') {
                this.timeLeft -= 1 / 60;
                DOM.defendTimerValue.textContent = Math.ceil(this.timeLeft);
                if (this.timeLeft <= 0) { this.running = false; this.gameOver(); return; }
            }
            this.update();
            this.draw();
            this.animId = requestAnimationFrame(() => this.loop());
        },

        start() {
            this.running = true;
            this.enemies = [];
            this.particles = [];
            this.lasers = [];
            this.score = 0;
            this.lives = 3;
            this.spawnTimer = 0;
            DOM.defendScore.textContent = '⭐ 0';
            DOM.defendLives.textContent = '❤️❤️❤️';
            if (this.mode === 'timed') {
                DOM.defendTimer.classList.remove('hidden');
                DOM.defendTimerValue.textContent = '60';
            }
            DOM.defendInput.value = '';
            DOM.defendInput.focus();
            for (let i = 0; i < 2; i++) setTimeout(() => this.spawn(), i * 800);
            this.loop();
        }
    };

    S.defending = game;

    // Replace input handler to avoid stacking
    const handler = (e) => {
        if (e.key === 'Enter') {
            const val = e.target.value.trim();
            if (!val) return;
            const hit = game.shoot(val);
            if (!hit) {
                e.target.style.borderColor = 'var(--red)';
                setTimeout(() => { e.target.style.borderColor = ''; }, 250);
            }
            e.target.value = '';
        }
    };
    DOM.defendInput.removeEventListener('keydown', DOM.defendInput._defendHandler);
    DOM.defendInput.addEventListener('keydown', handler);
    DOM.defendInput._defendHandler = handler;

    DOM.defendLives.textContent = '❤️❤️❤️';
    game.start();
}

/* ─── KNOWLEDGE CLASH (TIC-TAC-TOE) ───────────── */
function updateClash(match) {
    if (!match.state) return;
    const state = match.state;
    const isP1 = match.player1_id === S.user.id;
    const myMark = isP1 ? 'X' : 'O';
    const oppMark = isP1 ? 'O' : 'X';
    const myTurn = state.turn === S.user.id;
    const board = state.board || ['','','','','','','','',''];

    DOM.clashStatus.textContent = state.over
        ? (state.winner === S.user.id ? '🏆 You win!' : state.winner > 0 ? '😢 You lost' : '🤝 Draw')
        : (myTurn ? 'Your turn! Click a cell.' : `Waiting for ${isP1 ? match.p2_name : match.p1_name}...`);

    DOM.clashBoard.innerHTML = '';
    board.forEach((cell, idx) => {
        const div = document.createElement('div');
        div.className = 'ttt-cell' + (cell ? ' taken' : '');
        div.textContent = cell === 'X' ? '❌' : cell === 'O' ? '⭕' : '';
        if (state.winLine && state.winLine.includes(idx)) div.classList.add('win');
        if (!cell && myTurn && !state.over) {
            div.addEventListener('click', () => clashCellClick(idx, match, board, myMark));
        }
        DOM.clashBoard.appendChild(div);
    });
}

const clashMCQs = [
    { q: "What is the capital of France?", o: ["London","Paris","Berlin","Madrid"], a: 1 },
    { q: "2 + 2 = ?", o: ["3","4","5","6"], a: 1 },
    { q: "What color is the sky on a clear day?", o: ["Red","Green","Blue","Yellow"], a: 2 },
    { q: "How many legs does a dog have?", o: ["2","4","6","8"], a: 1 },
    { q: "What is H2O?", o: ["Salt","Water","Oxygen","Hydrogen"], a: 1 },
    { q: "Which planet is known as the Red Planet?", o: ["Venus","Jupiter","Mars","Saturn"], a: 2 },
    { q: "What is 10 × 10?", o: ["50","100","200","1000"], a: 1 },
    { q: "How many days in a week?", o: ["5","6","7","8"], a: 2 },
    { q: "What sound does a cat make?", o: ["Woof","Meow","Moo","Roar"], a: 1 },
    { q: "Which is largest ocean?", o: ["Atlantic","Indian","Arctic","Pacific"], a: 3 },
];

function clashCellClick(idx, match, board, myMark) {
    // Pick random MCQ
    const mcq = clashMCQs[Math.floor(Math.random() * clashMCQs.length)];
    DOM.mcqBody.innerHTML = `
        <div class="mcq-question">${mcq.q}</div>
        ${mcq.o.map((opt, i) => `<button class="mcq-option" data-idx="${i}">${opt}</button>`).join('')}
        <div id="mcq-result"></div>
    `;
    DOM.mcqModal.classList.remove('hidden');
    DOM.mcqModal.dataset.cellIdx = idx;
    DOM.mcqModal.dataset.matchId = match.id;
    DOM.mcqModal.dataset.myMark = myMark;
    DOM.mcqModal.dataset.answer = mcq.a;
    DOM.mcqModal.dataset.board = JSON.stringify(board);

    DOM.mcqBody.querySelectorAll('.mcq-option').forEach(btn => {
        btn.addEventListener('click', async function () {
            const selected = parseInt(this.dataset.idx);
            const correct = parseInt(DOM.mcqModal.dataset.answer);
            const resultDiv = document.getElementById('mcq-result');
            DOM.mcqBody.querySelectorAll('.mcq-option').forEach(b => b.style.pointerEvents = 'none');
            if (selected === correct) {
                this.classList.add('correct');
                resultDiv.innerHTML = '✅ Correct!';
                // Update board
                const cellIdx = parseInt(DOM.mcqModal.dataset.cellIdx);
                const mark = DOM.mcqModal.dataset.myMark;
                const matchId = parseInt(DOM.mcqModal.dataset.matchId);
                const b = JSON.parse(DOM.mcqModal.dataset.board);
                b[cellIdx] = mark === 'X' ? 'X' : 'O';
                // Check win
                const winLines = [
                    [0,1,2],[3,4,5],[6,7,8],
                    [0,3,6],[1,4,7],[2,5,8],
                    [0,4,8],[2,4,6]
                ];
                let winner = 0;
                let winLine = null;
                for (const line of winLines) {
                    if (b[line[0]] && b[line[0]] === b[line[1]] && b[line[1]] === b[line[2]]) {
                        winner = S.user.id;
                        winLine = line;
                        break;
                    }
                }
                let boardFilled = b.every(c => c !== '');
                const over = winner > 0 || boardFilled;
                const matchRes = await api('api.php', { action: 'update-match', match_id: matchId,
                    state: JSON.stringify({ board: b, turn: match.player1_id === S.user.id ? match.player2_id : match.player1_id, winLine, over, winner })
                });
                if (over) {
                    await api('api.php', { action: 'end-match', match_id: matchId, winner_id: winner, points: winner > 0 ? 10 : 5 });
                }
                setTimeout(() => DOM.mcqModal.classList.add('hidden'), 800);
            } else {
                this.classList.add('wrong');
                resultDiv.innerHTML = '❌ Wrong! Try another cell.';
                setTimeout(() => {
                    DOM.mcqBody.querySelectorAll('.mcq-option').forEach(b => { b.classList.remove('wrong'); b.style.pointerEvents = 'auto'; });
                    resultDiv.innerHTML = '';
                }, 1000);
            }
        });
    });
}

/* ─── MCQ MODAL (from pushed task) ─────────────── */
function openMCQModal(task, isPushed) {
    const content = task.content;
    S.mcqTaskId = task.id;
    S.mcqCorrectCount = 0;
    let questions = [];
    if (Array.isArray(content)) questions = content;
    else if (content.questions) questions = content.questions;
    else questions = [content];

    const timeLimit = task.time_limit || 0;
    let timeRemaining = timeLimit;
    let timerInterval = null;

    let currentQ = 0;
    function showQuestion() {
        if (currentQ >= questions.length) {
            if (timerInterval) clearInterval(timerInterval);
            const totalP = Math.min(questions.length, S.mcqCorrectCount);
            DOM.mcqBody.innerHTML = `
                <p>Quiz complete! Score: ${S.mcqCorrectCount}/${questions.length}</p>
                <div id="mcq-result">
                    <button class="btn btn-primary" id="mcq-close-btn">Close</button>
                </div>
            `;
            document.getElementById('mcq-close-btn').addEventListener('click', () => {
                DOM.mcqModal.classList.add('hidden');
                if (isPushed) api('api.php', { action: 'dismiss-pushed-task' });
            });
            api('api.php', { action: 'submit-mcq', task_id: S.mcqTaskId, score: S.mcqCorrectCount, total: questions.length, points: S.mcqCorrectCount });
            if (S.user) { S.user.points_academic = (S.user.points_academic || 0) + S.mcqCorrectCount; updatePointsDisplay(); }
            return;
        }
        const q = questions[currentQ];
        const qText = q.question || q.q || 'Question';
        const opts = q.options || q.o || [];
        const ansIdx = q.answer !== undefined ? q.answer : (q.a !== undefined ? q.a : 0);

        let timerHtml = '';
        if (timeLimit > 0) {
            const pct = (timeRemaining / timeLimit) * 100;
            const cls = pct < 25 ? 'danger' : pct < 50 ? 'warning' : '';
            timerHtml = `
                <div class="timer-text">⏱️ ${timeRemaining}s remaining</div>
                <div class="timer-bar"><div class="timer-bar-fill ${cls}" id="timer-fill" style="width:${pct}%"></div></div>
            `;
        }

        DOM.mcqBody.innerHTML = timerHtml + `
            <div class="mcq-question">${qText}</div>
            ${opts.map((opt, i) => `<button class="mcq-option" data-idx="${i}">${opt}</button>`).join('')}
            <div id="mcq-result"></div>
        `;
        DOM.mcqBody.querySelectorAll('.mcq-option').forEach(btn => {
            btn.addEventListener('click', function () {
                const sel = parseInt(this.dataset.idx);
                DOM.mcqBody.querySelectorAll('.mcq-option').forEach(b => b.style.pointerEvents = 'none');
                const rd = document.getElementById('mcq-result');
                if (sel === ansIdx) {
                    this.classList.add('correct');
                    rd.innerHTML = '✅ Correct!';
                    S.mcqCorrectCount++;
                } else {
                    this.classList.add('wrong');
                    rd.innerHTML = `❌ Wrong. Answer: ${opts[ansIdx]}`;
                }
                currentQ++;
                // Reset timer per question if timed
                if (timeLimit > 0) { timeRemaining = timeLimit; }
                setTimeout(showQuestion, 1000);
            });
        });
    }

    if (timeLimit > 0) {
        timerInterval = setInterval(() => {
            timeRemaining--;
            const fill = document.getElementById('timer-fill');
            const txt = document.querySelector('.timer-text');
            if (fill) {
                const pct = Math.max(0, (timeRemaining / timeLimit) * 100);
                fill.style.width = pct + '%';
                fill.className = 'timer-bar-fill' + (pct < 25 ? ' danger' : pct < 50 ? ' warning' : '');
            }
            if (txt) txt.textContent = `⏱️ ${timeRemaining}s remaining`;
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                // Auto-submit what we have
                currentQ = questions.length;
                showQuestion();
            }
        }, 1000);
    }

    DOM.mcqModal.classList.remove('hidden');
    showQuestion();
}

/* ─── TYPING TEST ───────────────────────────────── */
const typingPassages = [
    "The quick brown fox jumps over the lazy dog near the bank of the river. The sun is shining bright in the clear blue sky and the birds are singing sweetly in the trees.",
    "Computer science is the study of computers and computational systems. It involves understanding how computers work and how to solve problems using technology and logical thinking.",
    "Practice makes perfect when it comes to typing. The more you type the faster and more accurate you will become. Focus on each word and try not to look at the keyboard."
];

function startTypingTest(task, isPushed) {
    // Use teacher-pushed content if available, otherwise random default
    let passage;
    if (task && task.content) {
        if (typeof task.content === 'string') passage = task.content;
        else if (task.content.text) passage = task.content.text;
        else if (Array.isArray(task.content)) passage = task.content.join(' ');
        else passage = task.content.passage || typingPassages[0];
    } else {
        passage = typingPassages[Math.floor(Math.random() * typingPassages.length)];
    }
    const chars = passage.split('');
    let currentIdx = 0;
    let errors = 0;
    let startTime = null;

    // Show typing UI in a modal or replace content
    DOM.mcqBody.innerHTML = `
        <h3 style="margin-bottom:12px;">⌨️ Typing Test</h3>
        <div class="typing-text" id="typing-display">
            ${chars.map((c, i) => `<span class="char ${i === 0 ? 'current' : ''}" data-idx="${i}">${c === ' ' ? ' ' : escapeHtml(c)}</span>`).join('')}
        </div>
        <textarea id="typing-input" placeholder="Start typing here..." autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"></textarea>
        <div id="typing-stats">
            <span>WPM: <strong id="typing-wpm">0</strong></span>
            <span>Accuracy: <strong id="typing-acc">100%</strong></span>
        </div>
    `;
    DOM.mcqModal.classList.remove('hidden');

    const input = document.getElementById('typing-input');
    const display = document.getElementById('typing-display');
    const wpmEl = document.getElementById('typing-wpm');
    const accEl = document.getElementById('typing-acc');

    input.addEventListener('input', function () {
        if (!startTime) startTime = Date.now();
        const typed = this.value;
        const spanEls = display.querySelectorAll('.char');

        // Reset
        spanEls.forEach(s => { s.classList.remove('correct', 'incorrect', 'current'); });

        let correctCount = 0;
        let totalTyped = 0;
        for (let i = 0; i < typed.length && i < chars.length; i++) {
            totalTyped++;
            if (typed[i] === chars[i]) {
                spanEls[i].classList.add('correct');
                correctCount++;
            } else {
                spanEls[i].classList.add('incorrect');
            }
        }
        if (typed.length < chars.length) {
            spanEls[typed.length].classList.add('current');
        }

        // Calculate stats
        const elapsed = (Date.now() - startTime) / 1000 / 60; // minutes
        const wpm = elapsed > 0 ? Math.round((correctCount / 5) / elapsed) : 0;
        const accuracy = totalTyped > 0 ? Math.round((correctCount / totalTyped) * 100) : 100;
        wpmEl.textContent = wpm;
        accEl.textContent = accuracy + '%';

        // Check completion
        if (typed.length >= chars.length) {
            input.disabled = true;
            const points = Math.max(1, Math.round(wpm * accuracy / 100));
            api('api.php', { action: 'submit-typing', task_id: task ? task.id : 0, wpm, accuracy });
            if (S.user) { S.user.points_academic += points; updatePointsDisplay(); }
            setTimeout(() => {
                if (isPushed) api('api.php', { action: 'dismiss-pushed-task' });
                alert(`✅ Done! WPM: ${wpm}, Accuracy: ${accuracy}%`);
                DOM.mcqModal.classList.add('hidden');
            }, 500);
        }
    });
}

/* ─── ANTI-CHEAT: TAB SWITCH DETECTION ─────────── */
document.addEventListener('visibilitychange', function () {
    if (document.hidden && S.user && !S.isTeacher) {
        DOM.lockOverlay.classList.remove('hidden');
        api('api.php', { action: 'log-warning', type: 'tab_switch', details: 'Student switched tabs' });
    }
});

DOM.unlockBtn.addEventListener('click', function () {
    DOM.lockOverlay.classList.add('hidden');
});

/* ─── UTILITY ───────────────────────────────────── */
function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
