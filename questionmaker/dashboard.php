<?php
require_once 'includes/auth.php';
require_once 'includes/config.php';
$user = requireLogin();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — <?= APP_NAME ?> by <?= APP_BRAND ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/css/app.css">
<style>
/* ===== Brand header ===== */
.dash-header{display:flex;align-items:center;justify-content:space-between;padding:14px 24px;background:var(--bg-1);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.brand{display:flex;align-items:center;gap:10px;}
.brand-badge{width:36px;height:36px;background:linear-gradient(135deg,var(--accent),#8b5cf6);border-radius:10px;display:grid;place-items:center;font-weight:800;font-size:14px;color:#fff;letter-spacing:-.5px;}
.brand-text .name{font-size:17px;font-weight:700;color:var(--text-0);line-height:1;}
.brand-text .sub{font-size:10px;color:var(--text-3);letter-spacing:.08em;text-transform:uppercase;}
.dash-user{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text-2);}
.avatar{width:32px;height:32px;border-radius:50%;background:var(--accent);color:#fff;display:grid;place-items:center;font-weight:700;font-size:13px;}
/* ===== Body ===== */
.dash-body{max-width:1100px;margin:0 auto;padding:28px 20px;}
.section-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.section-hd h2{font-size:17px;font-weight:600;color:var(--text-0);margin:0;}
.search-bar{position:relative;}
.search-bar input{width:240px;padding:7px 10px 7px 32px;background:var(--bg-2);border:1px solid var(--border);border-radius:9px;color:var(--text-0);font-size:13px;}
.search-bar::before{content:'🔍';position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:12px;pointer-events:none;}
/* ===== Stats ===== */
.stats-row{display:flex;gap:14px;margin-bottom:26px;flex-wrap:wrap;}
.stat-card{background:var(--bg-1);border:1px solid var(--border);border-radius:12px;padding:14px 18px;flex:1;min-width:130px;}
.stat-card .sv{font-size:26px;font-weight:700;color:var(--accent);}
.stat-card .sl{font-size:11px;color:var(--text-3);margin-top:2px;}
/* ===== Tabs ===== */
.tabs{display:flex;gap:4px;background:var(--bg-2);padding:4px;border-radius:10px;margin-bottom:20px;width:fit-content;}
.tab-btn{padding:7px 18px;border-radius:7px;border:none;background:transparent;color:var(--text-2);font-size:13px;font-weight:500;cursor:pointer;transition:.15s;}
.tab-btn.active{background:var(--bg-1);color:var(--text-0);box-shadow:0 1px 4px rgba(0,0,0,.3);}
/* ===== Paper cards ===== */
.papers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:40px;}
.paper-card{background:var(--bg-1);border:1px solid var(--border);border-radius:14px;padding:20px;cursor:pointer;transition:border-color .2s,transform .15s;position:relative;}
.paper-card:hover{border-color:var(--accent);transform:translateY(-2px);}
.pc-title{font-size:15px;font-weight:600;color:var(--text-0);margin:0 0 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-right:32px;}
.pc-meta{font-size:12px;color:var(--text-3);display:flex;gap:12px;flex-wrap:wrap;}
.pc-actions{position:absolute;top:12px;right:12px;display:flex;gap:5px;opacity:0;transition:opacity .2s;}
.paper-card:hover .pc-actions{opacity:1;}
.icon-btn{width:26px;height:26px;border-radius:7px;border:1px solid var(--border);background:var(--bg-2);color:var(--text-2);cursor:pointer;display:grid;place-items:center;font-size:12px;transition:.12s;}
.icon-btn:hover.edit{background:var(--accent);color:#fff;border-color:var(--accent);}
.icon-btn:hover.del{background:var(--danger);color:#fff;border-color:var(--danger);}
.icon-btn:hover.dup{background:var(--success);color:#fff;border-color:var(--success);}
.empty-state{text-align:center;padding:60px 20px;color:var(--text-3);}
.empty-state .es-icon{font-size:52px;margin-bottom:16px;}
/* ===== Settings modal tabs ===== */
.stab-row{display:flex;border-bottom:1px solid var(--border);margin-bottom:20px;}
.stab{padding:9px 16px;border:none;background:transparent;color:var(--text-2);font-size:13px;cursor:pointer;border-bottom:2px solid transparent;transition:.15s;font-family:inherit;}
.stab.active{color:var(--accent);border-bottom-color:var(--accent);}
.stab-pane{display:none;}
.stab-pane.active{display:block;}
/* Provider key row */
.provider-row{display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center;margin-bottom:10px;}
.provider-label{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--text-1);}
.provider-icon{width:28px;height:28px;border-radius:7px;display:grid;place-items:center;font-size:14px;}
.prov-claude{background:rgba(108,99,255,.15);color:var(--accent);}
.prov-openai{background:rgba(16,163,127,.15);color:#10a37f;}
.prov-gemini{background:rgba(66,133,244,.15);color:#4285f4;}
.key-status{font-size:11px;padding:2px 7px;border-radius:5px;font-weight:500;}
.key-status.set{background:rgba(34,197,94,.1);color:var(--success);border:1px solid rgba(34,197,94,.2);}
.key-status.unset{background:rgba(239,68,68,.1);color:var(--danger);border:1px solid rgba(239,68,68,.2);}
/* Bank */
.bank-item{background:var(--bg-1);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:10px;}
</style>
</head>
<body>
<!-- ===== Header ===== -->
<header class="dash-header">
  <div class="brand">
    <div class="brand-badge">QM</div>
    <div class="brand-text">
      <div class="name"><?= APP_NAME ?></div>
      <div class="sub"><?= APP_BRAND ?> · <?= APP_DOMAIN ?></div>
    </div>
  </div>
  <div class="dash-user">
    <span style="color:var(--text-2);">Hello, <strong style="color:var(--text-0);"><?= htmlspecialchars($user['username']) ?></strong></span>
    <div class="avatar"><?= strtoupper(substr($user['username'],0,1)) ?></div>
    <button class="btn btn-ghost btn-sm" onclick="openSettings()">⚙ Settings</button>
    <button class="btn btn-ghost btn-sm" onclick="logout()">Sign Out</button>
  </div>
</header>

<!-- ===== Body ===== -->
<div class="dash-body">
  <div class="stats-row" id="statsRow">
    <div class="stat-card"><div class="sv" id="statPapers">—</div><div class="sl">Question Papers</div></div>
    <div class="stat-card"><div class="sv" id="statBank">—</div><div class="sl">Question Bank Items</div></div>
    <div class="stat-card"><div class="sv" id="statAI">—</div><div class="sl">AI Providers Configured</div></div>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="switchTab('papers',this)">📄 My Papers</button>
    <button class="tab-btn" onclick="switchTab('bank',this)">🗄 Question Bank</button>
  </div>

  <!-- Papers Tab -->
  <div id="tabPapers">
    <div class="section-hd">
      <h2>Recent Papers</h2>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div class="search-bar"><input type="text" id="paperSearch" placeholder="Search papers…" oninput="filterPapers()"></div>
        <a href="editor.php" class="btn btn-primary btn-sm">+ New Paper</a>
      </div>
    </div>
    <div class="papers-grid" id="papersGrid"></div>
  </div>

  <!-- Bank Tab -->
  <div id="tabBank" style="display:none;">
    <div class="section-hd">
      <h2>Question Bank</h2>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <select class="field-input" id="bankFilter" style="width:140px;" onchange="loadBank()">
          <option value="">All Types</option>
          <option value="mcq">MCQ</option>
          <option value="short">Short</option>
          <option value="creative">Creative</option>
          <option value="fill-blank">Fill Blank</option>
          <option value="true-false">True/False</option>
        </select>
        <input class="field-input" type="text" id="bankSearch" placeholder="Search…" oninput="loadBank()" style="width:200px;">
      </div>
    </div>
    <div id="bankList"></div>
  </div>
</div>

<!-- ============================================================
     SETTINGS MODAL — multi-tab
     ============================================================ -->
<div class="modal-overlay" id="settingsModal" style="display:none;">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3>⚙ Settings</h3>
      <button class="modal-close" onclick="closeSettings()">✕</button>
    </div>
    <div class="modal-body" style="padding-top:0;">

      <!-- Setting tabs -->
      <div class="stab-row">
        <button class="stab active" onclick="switchStab('aikeys',this)">🤖 AI Keys</button>
        <button class="stab" onclick="switchStab('account',this)">👤 Account</button>
        <button class="stab" onclick="switchStab('about',this)">ℹ About</button>
      </div>

      <!-- ---- AI Keys Tab ---- -->
      <div class="stab-pane active" id="stabAikeys">
        <p style="font-size:12px;color:var(--text-3);margin-bottom:16px;">Add API keys for one or more AI providers. All keys are stored per-user on your server only.</p>

        <!-- Claude -->
        <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div class="provider-label">
              <div class="provider-icon prov-claude">✦</div>
              <div>
                <div style="font-weight:600;">Anthropic Claude</div>
                <div style="font-size:11px;color:var(--text-3);">claude-sonnet-4-6 · <a href="https://console.anthropic.com" target="_blank" style="color:var(--accent);">Get key ↗</a></div>
              </div>
            </div>
            <span class="key-status" id="claudeStatus">Checking…</span>
          </div>
          <input class="field-input" type="password" id="keyClaude" placeholder="sk-ant-…" autocomplete="off">
        </div>

        <!-- OpenAI -->
        <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:12px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div class="provider-label">
              <div class="provider-icon prov-openai">⬡</div>
              <div>
                <div style="font-weight:600;">OpenAI GPT-4o</div>
                <div style="font-size:11px;color:var(--text-3);">gpt-4o · <a href="https://platform.openai.com/api-keys" target="_blank" style="color:var(--accent);">Get key ↗</a></div>
              </div>
            </div>
            <span class="key-status" id="openaiStatus">Checking…</span>
          </div>
          <input class="field-input" type="password" id="keyOpenAI" placeholder="sk-…" autocomplete="off">
        </div>

        <!-- Gemini -->
        <div style="background:var(--bg-2);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:16px;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <div class="provider-label">
              <div class="provider-icon prov-gemini">✦</div>
              <div>
                <div style="font-weight:600;">Google Gemini 1.5 Pro</div>
                <div style="font-size:11px;color:var(--text-3);">gemini-1.5-pro · <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color:var(--accent);">Get key ↗</a></div>
              </div>
            </div>
            <span class="key-status" id="geminiStatus">Checking…</span>
          </div>
          <input class="field-input" type="password" id="keyGemini" placeholder="AIza…" autocomplete="off">
        </div>

        <!-- Preferred -->
        <div class="field-group">
          <label class="field-label">Default AI Provider</label>
          <select class="field-input" id="keyPreferred">
            <option value="claude">Anthropic Claude</option>
            <option value="openai">OpenAI GPT-4o</option>
            <option value="gemini">Google Gemini</option>
            <option value="manual">Manual (Copy-Paste)</option>
          </select>
        </div>

        <!-- Manual note -->
        <div style="background:rgba(108,99,255,.08);border:1px solid rgba(108,99,255,.2);border-radius:10px;padding:12px;font-size:12px;color:var(--text-2);">
          <strong style="color:var(--accent);">✦ No API key?</strong> Use <strong>Manual mode</strong> — QMaker generates a ready-to-paste prompt you can give to ChatGPT, Gemini, or any AI, then paste the result back.
        </div>
      </div>

      <!-- ---- Account Tab ---- -->
      <div class="stab-pane" id="stabAccount">
        <div class="field-group">
          <label class="field-label">Username</label>
          <input class="field-input" type="text" id="accUsername" readonly>
        </div>
        <div class="field-group">
          <label class="field-label">Change Password</label>
          <input class="field-input" type="password" id="accPass1" placeholder="New password (min 4 chars)">
          <input class="field-input" type="password" id="accPass2" placeholder="Confirm password" style="margin-top:6px;">
        </div>
        <button class="btn btn-ghost btn-sm" onclick="changePassword()">Update Password</button>
        <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;">
        <div class="field-group">
          <label class="field-label">App Cache</label>
          <p style="font-size:11px;color:var(--text-3);margin-bottom:8px;text-transform:none;">Clear local browser storage, cached pages, and force a reload.</p>
          <button class="btn btn-danger btn-sm" onclick="clearCache()">Clear Cache &amp; Reload</button>
        </div>
      </div>

      <!-- ---- About Tab ---- -->
      <div class="stab-pane" id="stabAbout">
        <div style="text-align:center;padding:10px 0 20px;">
          <div style="font-size:40px;margin-bottom:10px;">📝</div>
          <div style="font-size:20px;font-weight:700;color:var(--accent);"><?= APP_NAME ?></div>
          <div style="font-size:13px;color:var(--text-3);margin:4px 0;"><?= APP_TAGLINE ?></div>
          <div style="font-size:12px;color:var(--text-3);">v<?= APP_VERSION ?> · <a href="https://<?= APP_DOMAIN ?>" target="_blank" style="color:var(--accent);"><?= APP_DOMAIN ?></a></div>
          <div style="margin-top:16px;font-size:12px;color:var(--text-3);">Powered by <strong style="color:var(--text-1);"><?= APP_BRAND ?></strong></div>
        </div>
      </div>

    </div>
    <div class="modal-footer">
      <div></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ghost btn-sm" onclick="closeSettings()">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveSettings()">Save Settings</button>
      </div>
    </div>
  </div>
</div>

<div id="toastContainer"></div>
<script src="assets/js/toast.js"></script>
<script>
let allPapers = [];

async function loadStats() {
  const [pr, bn, me] = await Promise.all([
    fetch('api/paper.php?action=list').then(r=>r.json()),
    fetch('api/qbank.php?action=list').then(r=>r.json()),
    fetch('api/auth.php?action=me').then(r=>r.json()),
  ]);
  allPapers = pr.papers || [];
  const aiKeys = (me.user && me.user.ai_keys) || {};
  const provCount = ['claude','openai','gemini'].filter(k => aiKeys[k] && aiKeys[k].trim()).length;
  document.getElementById('statPapers').textContent = allPapers.length;
  document.getElementById('statBank').textContent   = (bn.questions||[]).length;
  document.getElementById('statAI').textContent     = provCount + ' / 3';
  renderPapers(allPapers);
}

function renderPapers(papers) {
  const g = document.getElementById('papersGrid');
  if (!papers.length) {
    g.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
      <div class="es-icon">📄</div>
      <p>No question papers yet.</p>
      <a href="editor.php" class="btn btn-primary">+ New Paper</a>
    </div>`; return;
  }
  g.innerHTML = papers.map(p => `
    <div class="paper-card" onclick="openPaper(${p.id})">
      <div class="pc-actions">
        <button class="icon-btn dup" title="Duplicate" onclick="dupPaper(event,${p.id})">⧉</button>
        <button class="icon-btn edit" title="Edit" onclick="event.stopPropagation();openPaper(${p.id})">✏</button>
        <button class="icon-btn del" title="Delete" onclick="delPaper(event,${p.id})">🗑</button>
      </div>
      <div class="pc-title">${esc(p.title)}</div>
      <div class="pc-meta">
        ${p.institution?`<span>🏫 ${esc(p.institution)}</span>`:''}
        ${p.total_marks?`<span>📊 ${p.total_marks} marks</span>`:''}
        <span>🕐 ${relTime(p.updated_at)}</span>
      </div>
    </div>`).join('');
}

function filterPapers() {
  const q = document.getElementById('paperSearch').value.toLowerCase();
  renderPapers(q ? allPapers.filter(p=>(p.title+p.institution).toLowerCase().includes(q)) : allPapers);
}

function openPaper(id) { window.location.href = `editor.php?id=${id}`; }
async function delPaper(e,id) { e.stopPropagation(); if(!confirm('Delete this paper permanently?')) return; await fetch(`api/paper.php?action=delete&id=${id}`); loadStats(); }
async function dupPaper(e,id) { e.stopPropagation(); await fetch(`api/paper.php?action=duplicate&id=${id}`); loadStats(); showToast('Duplicated!','success'); }

async function loadBank() {
  const type = document.getElementById('bankFilter').value;
  const srch = document.getElementById('bankSearch').value;
  const data = await fetch(`api/qbank.php?action=list&type=${type}&search=${encodeURIComponent(srch)}`).then(r=>r.json());
  const list = document.getElementById('bankList');
  const qs = data.questions || [];
  if (!qs.length) { list.innerHTML='<div class="empty-state"><div class="es-icon">🗄</div><p>No questions in bank yet.</p></div>'; return; }
  list.innerHTML = qs.map(q => `
    <div class="bank-item">
      <div style="flex:1;min-width:0;">
        <div style="font-size:11px;color:var(--accent);text-transform:uppercase;font-weight:600;margin-bottom:3px;">${q.question_type}${q.subject?' · '+esc(q.subject):''}</div>
        <div style="font-size:13px;color:var(--text-0);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(String(q.content?.question||q.content?.statement||q.content?.stimulus||q.content?.template||'(no preview)').slice(0,120))}</div>
        ${q.tags?`<div style="margin-top:5px;">${q.tags.split(',').map(t=>`<span style="background:var(--bg-2);border:1px solid var(--border);border-radius:4px;padding:1px 6px;font-size:11px;margin-right:3px;">${esc(t.trim())}</span>`).join('')}</div>`:''}
      </div>
      <button class="icon-btn del" onclick="delBank(${q.id})">🗑</button>
    </div>`).join('');
}

async function delBank(id) { if(!confirm('Remove?')) return; await fetch(`api/qbank.php?action=delete&id=${id}`); loadBank(); }

function switchTab(tab, btn) {
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active')); btn.classList.add('active');
  document.getElementById('tabPapers').style.display = tab==='papers' ? '' : 'none';
  document.getElementById('tabBank').style.display   = tab==='bank'   ? '' : 'none';
  if (tab === 'bank') loadBank();
}

/* ===== Settings ===== */
async function openSettings() {
  const r = await fetch('api/auth.php?action=me').then(r=>r.json());
  if (r.user) {
    const k = r.user.ai_keys || {};
    document.getElementById('keyClaude').value    = k.claude    || '';
    document.getElementById('keyOpenAI').value    = k.openai    || '';
    document.getElementById('keyGemini').value    = k.gemini    || '';
    document.getElementById('keyPreferred').value = k.preferred || 'claude';
    document.getElementById('accUsername').value  = r.user.username;
    _updateKeyStatus('claudeStatus',  k.claude);
    _updateKeyStatus('openaiStatus',  k.openai);
    _updateKeyStatus('geminiStatus',  k.gemini);
  }
  document.getElementById('settingsModal').style.display = 'flex';
}

function _updateKeyStatus(id, val) {
  const el = document.getElementById(id);
  if (val && val.trim()) { el.textContent='✓ Set'; el.className='key-status set'; }
  else { el.textContent='Not set'; el.className='key-status unset'; }
}

function closeSettings() { document.getElementById('settingsModal').style.display = 'none'; }

async function saveSettings() {
  const fd = new FormData();
  fd.append('action',    'update_ai_keys');
  fd.append('claude',    document.getElementById('keyClaude').value.trim());
  fd.append('openai',    document.getElementById('keyOpenAI').value.trim());
  fd.append('gemini',    document.getElementById('keyGemini').value.trim());
  fd.append('preferred', document.getElementById('keyPreferred').value);
  const r = await fetch('api/auth.php', {method:'POST', body:fd}).then(r=>r.json());
  if (r.success) { closeSettings(); showToast('Settings saved!','success'); loadStats(); }
  else showToast(r.error||'Save failed','error');
}

async function changePassword() {
  const p1 = document.getElementById('accPass1').value;
  const p2 = document.getElementById('accPass2').value;
  if (!p1) return showToast('Enter a new password','error');
  if (p1 !== p2) return showToast('Passwords do not match','error');
  if (p1.length < 4) return showToast('Password must be at least 4 characters','error');
  const fd = new FormData();
  fd.append('action','change_password');
  fd.append('password', p1);
  fd.append('confirm', p2);
  const r = await fetch('api/auth.php',{method:'POST',body:fd}).then(r=>r.json());
  if (r.success) {
    showToast('Password changed successfully!','success');
    document.getElementById('accPass1').value = '';
    document.getElementById('accPass2').value = '';
  } else {
    showToast(r.error||'Failed to change password','error');
  }
}

async function clearCache() {
  if ('caches' in window) {
    try {
      const keys = await caches.keys();
      await Promise.all(keys.map(k => caches.delete(k)));
    } catch(e) {}
  }
  localStorage.clear();
  sessionStorage.clear();
  showToast('Cache cleared! Reloading...','success');
  setTimeout(() => window.location.reload(), 1200);
}

function switchStab(tab, btn) {
  document.querySelectorAll('.stab').forEach(b=>b.classList.remove('active')); btn.classList.add('active');
  document.querySelectorAll('.stab-pane').forEach(p=>p.classList.remove('active'));
  document.getElementById('stab'+tab.charAt(0).toUpperCase()+tab.slice(1)).classList.add('active');
}

async function logout() { await fetch('api/auth.php?action=logout'); window.location.href='index.php'; }

function esc(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function relTime(d){const s=Math.round((Date.now()-new Date(d))/1000);if(s<60)return 'just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';return Math.floor(s/86400)+'d ago';}

document.getElementById('settingsModal').addEventListener('click', e=>{if(e.target===e.currentTarget)closeSettings();});
loadStats();
</script>
</body>
</html>
