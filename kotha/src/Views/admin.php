<?php
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\");

// Split recordings by type up-front for PHP rendering
$liveAudio  = [];
$liveVideo  = [];
$finalAudio = [];
$finalVideo = [];
foreach ($recordings as $rec) {
    $isLive  = empty($rec['recording_file']);
    $isAudio = (($rec['call_type'] ?? 'audio') === 'audio');
    if ($isLive) { if ($isAudio) $liveAudio[] = $rec; else $liveVideo[] = $rec; }
    else         { if ($isAudio) $finalAudio[] = $rec; else $finalVideo[] = $rec; }
}
$pendingCount = count(array_filter($upgradeRequests, fn($r) => $r['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Console — Kotha</title>
<link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/public/img/icon.svg">
<link rel="shortcut icon" href="<?= $baseUrl ?>/public/img/icon.svg">
<meta name="theme-color" content="#00f2fe">
<link rel="stylesheet" href="<?= $baseUrl ?>/public/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Reset & base ───────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; font-family: 'Outfit', sans-serif; background: var(--bg-main, #0b141a); color: var(--text-primary, #e9edef); min-height: 100dvh; }

/* ── Top navigation bar ─────────────────────────────────────── */
.adm-nav {
    position: sticky; top: 0; z-index: 200;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 16px; height: 56px;
    background: var(--bg-header, #202c33);
    border-bottom: 1px solid var(--border-color, rgba(255,255,255,.08));
    gap: 12px;
}
.adm-nav-brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem; color: var(--accent, #00f2fe); white-space: nowrap; }
.adm-nav-brand i { font-size: 1.2rem; }
.adm-nav-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.adm-nav-user { font-size: 0.78rem; color: var(--text-secondary, #8696a0); white-space: nowrap; display: none; }
@media (min-width: 480px) { .adm-nav-user { display: block; } }
.adm-nav-btn { background: none; border: 1px solid rgba(255,255,255,.1); border-radius: 8px; color: var(--text-primary); padding: 6px 10px; cursor: pointer; font-size: 0.75rem; display: flex; align-items: center; gap: 6px; transition: background .2s; }
.adm-nav-btn:hover { background: rgba(255,255,255,.07); }
.adm-nav-btn i { font-size: 0.85rem; }

/* Live refresh pulse */
.adm-live-dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; animation: blink 1.8s infinite; flex-shrink: 0; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }

/* ── Scrollable tab bar ─────────────────────────────────────── */
.adm-tabs {
    display: flex; overflow-x: auto; gap: 4px; padding: 8px 12px;
    background: var(--bg-panel, #1f2c34);
    border-bottom: 1px solid var(--border-color, rgba(255,255,255,.08));
    scrollbar-width: none; position: sticky; top: 56px; z-index: 100;
}
.adm-tabs::-webkit-scrollbar { display: none; }
.adm-tab {
    flex-shrink: 0; padding: 7px 14px; border-radius: 8px; border: none;
    background: none; color: var(--text-secondary, #8696a0); cursor: pointer;
    font-family: 'Outfit', sans-serif; font-size: 0.8rem; font-weight: 500;
    white-space: nowrap; transition: background .2s, color .2s; display: flex; align-items: center; gap: 6px;
}
.adm-tab:hover  { background: rgba(255,255,255,.05); color: var(--text-primary); }
.adm-tab.active { background: rgba(0,242,254,.12); color: var(--accent, #00f2fe); font-weight: 600; }
.adm-tab .tab-badge { background: #ef4444; color: #fff; border-radius: 10px; padding: 1px 6px; font-size: 0.65rem; font-weight: 700; }
.adm-tab .tab-live  { color: #ef4444; font-size: 0.65rem; animation: blink 1s infinite; }

/* ── Content wrapper ────────────────────────────────────────── */
.adm-content { padding: 16px; max-width: 1200px; margin: 0 auto; }
.adm-panel   { display: none; }
.adm-panel.active { display: block; }

/* ── Section header ─────────────────────────────────────────── */
.adm-section-head { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--accent); font-size: 0.9rem; margin-bottom: 14px; margin-top: 20px; }
.adm-section-head:first-child { margin-top: 0; }
.adm-section-head i { opacity: .8; }

/* ── Card grid ──────────────────────────────────────────────── */
.adm-card-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
@media (min-width: 600px)  { .adm-card-grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 900px)  { .adm-card-grid { grid-template-columns: repeat(3, 1fr); } }

/* ── User card ──────────────────────────────────────────────── */
.adm-user-card {
    background: var(--bg-panel, #1f2c34);
    border: 1px solid var(--border-color, rgba(255,255,255,.08));
    border-radius: 12px; padding: 14px; display: flex; align-items: flex-start; gap: 12px;
    transition: border-color .2s;
}
.adm-user-card:hover { border-color: rgba(0,242,254,.25); }
.adm-avatar {
    width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center;
    justify-content: center; font-weight: 700; font-size: 1rem; color: #fff; flex-shrink: 0; letter-spacing: .5px;
}
.adm-user-info { flex: 1; min-width: 0; }
.adm-user-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.adm-user-meta { font-size: 0.72rem; color: var(--text-secondary); margin-top: 2px; line-height: 1.5; }
.adm-user-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }

/* ── Status badges ──────────────────────────────────────────── */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.badge-pending  { background: rgba(245,158,11,.15); color: #f59e0b; border: 1px solid rgba(245,158,11,.3); }
.badge-approved { background: rgba(34,197,94,.15);  color: #22c55e; border: 1px solid rgba(34,197,94,.3); }
.badge-blocked  { background: rgba(239,68,68,.15);  color: #ef4444; border: 1px solid rgba(239,68,68,.3); }

/* ── Action buttons ─────────────────────────────────────────── */
.adm-btn { border: none; border-radius: 7px; padding: 5px 12px; font-family: 'Outfit', sans-serif; font-size: 0.75rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: opacity .2s; }
.adm-btn:hover { opacity: .82; }
.adm-btn-approve { background: linear-gradient(135deg,#22c55e,#15803d); color: #fff; }
.adm-btn-block   { background: linear-gradient(135deg,#f59e0b,#b45309); color: #fff; }
.adm-btn-delete  { background: linear-gradient(135deg,#ef4444,#b91c1c); color: #fff; }
.adm-btn-play    { background: linear-gradient(135deg,#3b82f6,#1d4ed8); color: #fff; }
.adm-btn-live    { background: linear-gradient(135deg,#ef4444,#b91c1c); color: #fff; }
.adm-btn-save    { background: linear-gradient(135deg,#00f2fe,#4facfe); color: #0b141a; }
.adm-btn-outline { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); color: var(--text-primary); }

/* ── Responsive table ───────────────────────────────────────── */
.adm-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border-color); }
.adm-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.adm-table th { background: var(--bg-active, #2a3942); padding: 10px 12px; text-align: left; font-weight: 600; color: var(--text-secondary); white-space: nowrap; }
.adm-table td { padding: 10px 12px; border-top: 1px solid var(--border-color); vertical-align: middle; }
.adm-table tr:hover td { background: rgba(255,255,255,.02); }

/* ── Call monitoring cards ──────────────────────────────────── */
.call-card {
    background: var(--bg-panel); border: 1px solid var(--border-color);
    border-radius: 12px; padding: 14px; display: flex; align-items: center; gap: 12px;
}
.call-card.live { border-color: rgba(239,68,68,.4); }
.call-card-info { flex: 1; min-width: 0; }
.call-card-name { font-weight: 600; font-size: 0.88rem; }
.call-card-meta { font-size: 0.72rem; color: var(--text-secondary); margin-top: 3px; }
.call-card-time { font-size: 0.7rem; color: var(--text-muted, #667781); margin-top: 2px; }
.live-pulse { display: inline-flex; align-items: center; gap: 5px; font-size: 0.68rem; font-weight: 700; color: #ef4444; }
.live-pulse::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #ef4444; animation: blink 1s infinite; }

/* ── Chat sniffer ───────────────────────────────────────────── */
.sniff-wrap { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 700px) { .sniff-wrap { grid-template-columns: 260px 1fr; height: 520px; } }
.sniff-list { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow-y: auto; }
.sniff-list-head { padding: 12px 14px; border-bottom: 1px solid var(--border-color); font-size: 0.78rem; font-weight: 700; color: var(--accent); }
.sniff-item { padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,.04); cursor: pointer; transition: background .15s; }
.sniff-item:hover { background: rgba(255,255,255,.04); }
.sniff-item.active-sniff { background: rgba(0,242,254,.08); border-left: 3px solid var(--accent); }
.sniff-item-title { font-size: 0.82rem; font-weight: 600; }
.sniff-item-id    { font-size: 0.68rem; color: var(--text-secondary); margin-top: 2px; }
.sniff-viewer { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; }
.sniff-viewer-head { padding: 12px 16px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.sniff-viewer-head h4 { margin: 0; font-size: 0.88rem; }
.sniff-messages { flex: 1; overflow-y: auto; padding: 12px; display: flex; flex-direction: column; gap: 8px; min-height: 200px; }
.sniff-msg { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); border-radius: 8px; padding: 10px 12px; }
.sniff-msg-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.sniff-msg-sender { font-size: 0.78rem; font-weight: 600; color: var(--accent); }
.sniff-msg-time   { font-size: 0.68rem; color: var(--text-secondary); }

/* ── Notification composer ──────────────────────────────────── */
.notif-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
@media (min-width: 700px) { .notif-grid { grid-template-columns: 1fr 1.6fr; } }
.notif-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; padding: 18px; }
.adm-form-group { margin-bottom: 12px; }
.adm-form-group label { display: block; font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 4px; }
.adm-input, .adm-select, .adm-textarea {
    width: 100%; box-sizing: border-box; padding: 8px 12px;
    background: var(--bg-header, #202c33); border: 1px solid rgba(255,255,255,.12);
    border-radius: 8px; color: var(--text-primary); font-family: 'Outfit', sans-serif; font-size: 0.83rem;
    transition: border-color .2s;
}
.adm-input:focus, .adm-select:focus, .adm-textarea:focus { outline: none; border-color: rgba(0,242,254,.4); }
.adm-textarea { resize: vertical; }
.notif-history { display: flex; flex-direction: column; gap: 10px; max-height: 480px; overflow-y: auto; }
.notif-hist-item { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px; }
.notif-hist-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.notif-hist-title { font-size: 0.85rem; font-weight: 600; }
.notif-hist-tag { font-size: 0.65rem; background: rgba(0,242,254,.1); color: var(--accent); padding: 2px 7px; border-radius: 10px; }
.notif-hist-body { font-size: 0.8rem; color: var(--text-secondary); line-height: 1.5; margin-bottom: 4px; }
.notif-hist-date { font-size: 0.68rem; color: var(--text-muted, #667781); }

/* ── Plan panels ────────────────────────────────────────────── */
.plan-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
@media (min-width: 500px) { .plan-grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 900px) { .plan-grid { grid-template-columns: repeat(3, 1fr); } }
.plan-tpl-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; }
.plan-tpl-title { font-weight: 700; font-size: 0.92rem; margin-bottom: 14px; }

/* ── Playback modal ─────────────────────────────────────────── */
.playback-overlay {
    display: none; position: fixed; inset: 0; z-index: 9000;
    background: rgba(0,0,0,.8); backdrop-filter: blur(6px);
    align-items: center; justify-content: center; padding: 16px;
}
.playback-overlay.show { display: flex; }
.playback-card {
    background: var(--bg-panel); border: 1px solid rgba(255,255,255,.12);
    border-radius: 16px; width: 100%; max-width: 680px;
    display: flex; flex-direction: column; overflow: hidden; max-height: 90dvh;
}
.playback-head { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); }
.playback-head h3 { margin: 0; font-size: 0.92rem; }
.playback-body { padding: 16px; flex: 1; overflow-y: auto; }
.playback-video { width: 100%; border-radius: 10px; background: #000; max-height: 360px; }
.playback-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
#liveStreamStatus { display: none; padding: 8px 14px; background: rgba(0,242,254,.08); border: 1px solid rgba(0,242,254,.2); border-radius: 8px; font-size: 0.78rem; color: var(--accent); text-align: center; margin-top: 10px; }

/* ── Set-plan modal ─────────────────────────────────────────── */
.spm-overlay { display: none; position: fixed; inset: 0; z-index: 8000; background: rgba(0,0,0,.65); align-items: center; justify-content: center; padding: 16px; }
.spm-overlay.show { display: flex; }
.spm-card { background: var(--bg-panel); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; padding: 22px; width: 100%; max-width: 420px; }
.spm-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.spm-head h3 { margin: 0; font-size: 0.95rem; }

/* ── Upgrade requests ───────────────────────────────────────── */
.req-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; padding: 14px; display: flex; gap: 12px; align-items: flex-start; }
.req-body { flex: 1; min-width: 0; }
.req-name  { font-weight: 600; font-size: 0.88rem; }
.req-meta  { font-size: 0.73rem; color: var(--text-secondary); margin-top: 2px; }
.req-msg   { font-size: 0.78rem; color: var(--text-secondary); margin-top: 6px; border-left: 2px solid var(--border-color); padding-left: 8px; }
.req-actions { display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap; }

/* ── Toast ──────────────────────────────────────────────────── */
@keyframes aIn  { from{opacity:0;transform:translateX(60px)} to{opacity:1;transform:translateX(0)} }
@keyframes aOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(60px)} }
#aToastBox { position:fixed;bottom:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;max-width:320px;pointer-events:none; }
.a-toast { background:var(--bg-panel);border-radius:10px;padding:12px 16px;color:var(--text-primary);font-family:'Outfit',sans-serif;
    font-size:0.83rem;display:flex;align-items:center;gap:10px;box-shadow:0 4px 20px rgba(0,0,0,.5);
    animation:aIn .3s ease;pointer-events:all;cursor:pointer;border-left:4px solid #3b82f6; }
.a-toast.t-success{border-left-color:#22c55e}.a-toast.t-error{border-left-color:#ef4444}
.a-toast.t-warning{border-left-color:#f59e0b}.a-toast.removing{animation:aOut .25s ease forwards}

/* ── Empty state ────────────────────────────────────────────── */
.adm-empty { text-align: center; padding: 40px 20px; color: var(--text-muted, #667781); font-size: 0.85rem; }
.adm-empty i { font-size: 2.5rem; display: block; margin-bottom: 12px; opacity: .35; }
</style>
</head>
<body>

<!-- ─── Top navbar ─────────────────────────────────────────── -->
<nav class="adm-nav">
    <div class="adm-nav-brand">
        <img src="<?= $baseUrl ?>/public/img/icon.svg"
             alt="Kotha"
             style="width:30px;height:30px;flex-shrink:0;">
        <span>Kotha</span>
        <span style="font-size:0.6rem;font-weight:600;background:rgba(0,242,254,.12);color:var(--accent);border:1px solid rgba(0,242,254,.25);border-radius:4px;padding:1px 6px;letter-spacing:.4px;text-transform:uppercase;">Admin</span>
    </div>
    <div class="adm-nav-right">
        <div class="adm-live-dot" title="Auto-refreshing every 20s"></div>
        <span class="adm-nav-user">Welcome, <strong><?= e($userName) ?></strong></span>
        <a href="<?= $baseUrl ?>/dashboard" class="adm-nav-btn"><i class="fa-solid fa-chart-line"></i> <span style="display:none;" class="d-sm-inline">App</span></a>
        <button class="adm-nav-btn" onclick="triggerManualRefresh()"><i class="fa-solid fa-rotate-right"></i></button>
        <a href="<?= $baseUrl ?>/logout" class="adm-nav-btn" style="border-color:rgba(239,68,68,.3);color:#ef4444;"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</nav>

<!-- ─── Tab bar ────────────────────────────────────────────── -->
<div class="adm-tabs" id="admTabBar">
    <button class="adm-tab active" onclick="switchTab('tabUsers', this)">
        <i class="fa-solid fa-users"></i> Users
    </button>
    <button class="adm-tab" onclick="switchTab('tabPlans', this)">
        <i class="fa-solid fa-layer-group"></i> Plans
    </button>
    <button class="adm-tab" onclick="switchTab('tabRequests', this)">
        <i class="fa-solid fa-arrow-up-right-dots"></i> Requests
        <?php if ($pendingCount > 0): ?>
            <span class="tab-badge"><?= $pendingCount ?></span>
        <?php endif; ?>
    </button>
    <button class="adm-tab" onclick="switchTab('tabNotif', this)">
        <i class="fa-solid fa-bell"></i> Notifications
    </button>
    <button class="adm-tab" onclick="switchTab('tabSniff', this)">
        <i class="fa-solid fa-user-secret"></i> Chat Sniff
    </button>
    <button class="adm-tab" onclick="switchTab('tabLiveAudio', this)" id="btnTabLiveAudio">
        <i class="fa-solid fa-phone"></i> Live Audio
        <?php if (!empty($liveAudio)): ?>
            <span class="tab-live"><i class="fa-solid fa-circle-dot"></i></span>
        <?php endif; ?>
        <span id="cntLiveAudio"><?= count($liveAudio) ?></span>
    </button>
    <button class="adm-tab" onclick="switchTab('tabLiveVideo', this)" id="btnTabLiveVideo">
        <i class="fa-solid fa-video"></i> Live Video
        <?php if (!empty($liveVideo)): ?>
            <span class="tab-live"><i class="fa-solid fa-circle-dot"></i></span>
        <?php endif; ?>
        <span id="cntLiveVideo"><?= count($liveVideo) ?></span>
    </button>
    <button class="adm-tab" onclick="switchTab('tabRecordings', this)">
        <i class="fa-solid fa-film"></i> Recordings
        (<span id="cntFinalAll"><?= count($finalAudio) + count($finalVideo) ?></span>)
    </button>
    <button class="adm-tab" onclick="switchTab('tabStorage', this); loadStorageTab();" id="btnTabStorage">
        <i class="fa-solid fa-hard-drive"></i> Storage
    </button>
    <button class="adm-tab" onclick="switchTab('tabLocations', this); loadLocations();" id="btnTabLocations">
        <i class="fa-solid fa-location-dot"></i> Locations
    </button>
</div>

<!-- ─── Main content ────────────────────────────────────────── -->
<div class="adm-content">

    <!-- ══ TAB: USERS ════════════════════════════════════════ -->
    <section class="adm-panel active" id="tabUsers">

        <!-- Auto-approve toggle card -->
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;
                    padding:14px 18px;margin-bottom:18px;
                    background:var(--bg-panel);border:1px solid var(--border-color);
                    border-radius:12px;">
            <!-- Icon -->
            <div style="width:40px;height:40px;border-radius:10px;flex-shrink:0;
                        background:<?= $autoApprove ? 'rgba(34,197,94,.12)' : 'rgba(255,255,255,.04)' ?>;
                        border:1px solid <?= $autoApprove ? 'rgba(34,197,94,.25)' : 'rgba(255,255,255,.08)' ?>;
                        display:flex;align-items:center;justify-content:center;"
                 id="autoApproveIconWrap">
                <i class="fa-solid fa-user-check"
                   style="color:<?= $autoApprove ? '#22c55e' : 'rgba(255,255,255,.3)' ?>;font-size:.95rem;"
                   id="autoApproveIcon"></i>
            </div>
            <!-- Text -->
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:.88rem;color:#fff;margin-bottom:2px;">
                    Auto-Approve New Registrations
                </div>
                <div style="font-size:.73rem;color:var(--text-secondary);line-height:1.5;" id="autoApproveDesc">
                    <?php if ($autoApprove): ?>
                        <span style="color:#22c55e;font-weight:600;">Enabled</span> — New users are approved instantly on registration.
                    <?php else: ?>
                        <span style="color:var(--text-muted);">Disabled</span> — New users require manual approval before they can sign in.
                    <?php endif; ?>
                </div>
            </div>
            <!-- Toggle switch -->
            <button onclick="toggleAutoApprove()"
                    id="autoApproveToggle"
                    style="flex-shrink:0;position:relative;width:48px;height:26px;
                           background:<?= $autoApprove ? 'linear-gradient(135deg,#22c55e,#15803d)' : 'rgba(255,255,255,.08)' ?>;
                           border:1px solid <?= $autoApprove ? 'rgba(34,197,94,.4)' : 'rgba(255,255,255,.12)' ?>;
                           border-radius:13px;cursor:pointer;transition:all .3s;
                           box-shadow:<?= $autoApprove ? '0 0 12px rgba(34,197,94,.3)' : 'none' ?>;"
                    aria-label="Toggle auto-approve"
                    aria-pressed="<?= $autoApprove ? 'true' : 'false' ?>">
                <span id="autoApproveKnob"
                      style="position:absolute;top:3px;
                             left:<?= $autoApprove ? '25px' : '3px' ?>;
                             width:18px;height:18px;border-radius:50%;
                             background:#fff;
                             transition:left .25s cubic-bezier(.4,0,.2,1);
                             box-shadow:0 1px 4px rgba(0,0,0,.35);"></span>
            </button>
        </div>

        <div class="adm-section-head"><i class="fa-solid fa-users"></i> User Management</div>
        <div class="adm-card-grid" id="usersGrid">
        <?php foreach ($users as $user):
            if ($user['is_admin']) continue;
            $avatarColors = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1'];
            $avatarBg = $avatarColors[$user['id'] % count($avatarColors)];
            $initials = strtoupper(mb_substr($user['full_name'], 0, 1));
            $status = $user['is_approved'] == 0 ? 'pending' : ($user['is_approved'] == 1 ? 'approved' : 'blocked');
            $statusLabel = ucfirst($status);
        ?>
        <div class="adm-user-card" data-user-id="<?= intval($user['id']) ?>">
            <div class="adm-avatar" style="background:<?= $avatarBg ?>;"><?= $initials ?></div>
            <div class="adm-user-info">
                <div class="adm-user-name"><?= e($user['full_name']) ?></div>
                <div class="adm-user-meta">
                    <div><?= e($user['email']) ?></div>
                    <div><?= e($user['phone']) ?> &middot; <?= e($user['institute']) ?></div>
                    <div style="margin-top:4px;">
                        <span class="badge badge-<?= $status ?>"><?= $statusLabel ?></span>
                    </div>
                </div>
                <div class="adm-user-actions user-action-group">
                    <?php if ($user['is_approved'] != 1): ?>
                        <button class="adm-btn adm-btn-approve" onclick="userAction(<?= intval($user['id']) ?>,'approve')"><i class="fa-solid fa-check"></i> Approve</button>
                    <?php endif; ?>
                    <?php if ($user['is_approved'] == 1): ?>
                        <button class="adm-btn adm-btn-block" onclick="userAction(<?= intval($user['id']) ?>,'block')"><i class="fa-solid fa-ban"></i> Block</button>
                    <?php endif; ?>
                    <?php if ($user['is_approved'] == 2): ?>
                        <button class="adm-btn adm-btn-approve" onclick="userAction(<?= intval($user['id']) ?>,'approve')"><i class="fa-solid fa-unlock"></i> Unblock</button>
                    <?php endif; ?>
                    <button class="adm-btn adm-btn-delete" onclick="deleteUser(<?= intval($user['id']) ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty(array_filter($users, fn($u) => !$u['is_admin']))): ?>
            <div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-users-slash"></i>No users registered yet.</div>
        <?php endif; ?>
        </div>
    </section>

    <!-- ══ TAB: PLANS ════════════════════════════════════════ -->
    <section class="adm-panel" id="tabPlans">
        <div class="adm-section-head"><i class="fa-solid fa-layer-group"></i> User Plans &amp; Today's Usage</div>
        <div class="adm-table-wrap" style="margin-bottom:24px;">
            <table class="adm-table">
                <thead>
                    <tr><th>User</th><th>Plan</th><th>Expires</th><th>Txt</th><th>Img</th><th>Vid</th><th>Aud</th><th>A.Call</th><th>V.Call</th><th></th></tr>
                </thead>
                <tbody id="plansTableBody">
                <?php
                $planColors = ['trial'=>'#f59e0b','heavy'=>'#3b82f6','unlimited'=>'#22c55e'];
                foreach ($usersWithPlans as $u):
                    $pc  = $planColors[$u['plan_name']] ?? '#8696a0';
                    $exp = $u['expires_at'] ? date('d M y', $u['expires_at']) : '—';
                ?>
                <tr data-uid="<?= intval($u['id']) ?>">
                    <td>
                        <strong style="font-size:0.83rem;"><?= e($u['full_name']) ?></strong>
                        <div style="font-size:0.7rem;color:var(--text-secondary);"><?= e($u['email']) ?></div>
                    </td>
                    <td><span class="badge" style="background:<?= $pc ?>20;color:<?= $pc ?>;border:1px solid <?= $pc ?>40;"><?= ucfirst(e($u['plan_name'])) ?></span></td>
                    <td style="font-size:0.78rem;white-space:nowrap;"><?= e($exp) ?></td>
                    <td style="font-size:0.78rem;"><?= $u['text_count'] ?></td>
                    <td style="font-size:0.78rem;"><?= $u['image_count'] ?></td>
                    <td style="font-size:0.78rem;"><?= $u['video_count'] ?></td>
                    <td style="font-size:0.78rem;"><?= $u['audio_count'] ?></td>
                    <td style="font-size:0.78rem;"><?= $u['audio_call_minutes'] ?>m</td>
                    <td style="font-size:0.78rem;"><?= $u['video_call_minutes'] ?>m</td>
                    <td><button class="adm-btn adm-btn-save" style="font-size:0.7rem;padding:4px 10px;" onclick="openSetPlan(<?= intval($u['id']) ?>,'<?= e($u['full_name']) ?>','<?= e($u['plan_name']) ?>')"><i class="fa-solid fa-pen"></i> Set</button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="adm-section-head"><i class="fa-solid fa-sliders"></i> Plan Template Defaults</div>
        <div class="plan-grid">
        <?php
        $tplColors = ['trial'=>'#f59e0b','heavy'=>'#3b82f6','unlimited'=>'#22c55e'];
        foreach ($planTemplates as $tpl):
            $tc = $tplColors[$tpl['plan_name']] ?? '#8696a0';
        ?>
        <div class="plan-tpl-card">
            <div class="plan-tpl-title" style="color:<?= $tc ?>;"><?= e($tpl['label']) ?></div>
            <form onsubmit="savePlanTemplate(event,'<?= e($tpl['plan_name']) ?>',this)">
                <input type="hidden" name="plan_name" value="<?= e($tpl['plan_name']) ?>">
                <?php foreach ([
                    'limit_text'=>'Text msg/day','limit_image'=>'Images/day','limit_video'=>'Videos/day',
                    'limit_audio'=>'Audios/day','limit_audio_call_minutes'=>'Audio call min','limit_video_call_minutes'=>'Video call min'
                ] as $fn => $fl): $val = $tpl[$fn] !== null ? $tpl[$fn] : ''; ?>
                <div class="adm-form-group">
                    <label><?= $fl ?></label>
                    <input type="number" name="<?= $fn ?>" value="<?= e($val) ?>" placeholder="unlimited" min="0" class="adm-input">
                </div>
                <?php endforeach; ?>
                <div class="adm-form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact_number" value="<?= e($tpl['contact_number'] ?? '') ?>" class="adm-input">
                </div>
                <div class="adm-form-group">
                    <label>Contact Text</label>
                    <textarea name="contact_text" rows="2" class="adm-textarea"><?= e($tpl['contact_text'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="adm-btn adm-btn-save" style="width:100%;justify-content:center;padding:8px;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Template
                </button>
            </form>
        </div>
        <?php endforeach; ?>
        </div>
    </section>

    <!-- ══ TAB: UPGRADE REQUESTS ══════════════════════════════ -->
    <section class="adm-panel" id="tabRequests">
        <div class="adm-section-head"><i class="fa-solid fa-arrow-up-right-dots"></i> Upgrade Requests</div>
        <div style="display:flex;flex-direction:column;gap:10px;" id="upgradeRequestsList">
        <?php
        $reqColors = ['pending'=>'#f59e0b','approved'=>'#22c55e','rejected'=>'#ef4444'];
        foreach ($upgradeRequests as $req):
            $rc = $reqColors[$req['status']] ?? '#8696a0';
        ?>
        <div class="req-card" data-req-id="<?= intval($req['id']) ?>">
            <div class="adm-avatar" style="background:<?= $avatarColors[$req['user_id'] % 8] ?? '#3b82f6' ?>;width:40px;height:40px;font-size:0.9rem;">
                <?= strtoupper(mb_substr($req['full_name'], 0, 1)) ?>
            </div>
            <div class="req-body">
                <div class="req-name"><?= e($req['full_name']) ?></div>
                <div class="req-meta">
                    <?= e($req['email']) ?> &middot;
                    <span style="color:var(--text-secondary);">Current: <strong><?= ucfirst(e($req['current_plan'])) ?></strong></span> →
                    <span style="color:#00f2fe;font-weight:600;"><?= ucfirst(e($req['requested_plan'])) ?></span>
                    &middot; <span style="color:<?= $rc ?>;font-weight:600;"><?= ucfirst(e($req['status'])) ?></span>
                </div>
                <?php if (!empty($req['message'])): ?>
                <div class="req-msg"><?= e($req['message']) ?></div>
                <?php endif; ?>
                <?php if ($req['status'] === 'pending'): ?>
                <div class="req-actions">
                    <button class="adm-btn adm-btn-approve" onclick="handleRequest(<?= intval($req['id']) ?>,'approve')"><i class="fa-solid fa-check"></i> Approve</button>
                    <button class="adm-btn adm-btn-block"   onclick="handleRequest(<?= intval($req['id']) ?>,'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
                </div>
                <?php elseif (!empty($req['admin_note'])): ?>
                <div style="font-size:0.73rem;color:var(--text-muted);margin-top:6px;">Note: <?= e($req['admin_note']) ?></div>
                <?php endif; ?>
            </div>
            <div style="font-size:0.68rem;color:var(--text-muted);white-space:nowrap;align-self:flex-start;"><?= e(substr(toDhaka($req['created_at']), 0, 10)) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($upgradeRequests)): ?>
            <div class="adm-empty"><i class="fa-solid fa-inbox"></i>No upgrade requests yet.</div>
        <?php endif; ?>
        </div>
    </section>

    <!-- ══ TAB: NOTIFICATIONS ════════════════════════════════ -->
    <section class="adm-panel" id="tabNotif">
        <div class="notif-grid">
            <div>
                <div class="adm-section-head"><i class="fa-solid fa-paper-plane"></i> Send Notification</div>
                <div class="notif-card">
                    <div class="adm-form-group">
                        <label>Title</label>
                        <input id="notifTitle" type="text" class="adm-input" placeholder="Notification title...">
                    </div>
                    <div class="adm-form-group">
                        <label>Body</label>
                        <textarea id="notifBody" class="adm-textarea" rows="4" placeholder="Notification content..."></textarea>
                    </div>
                    <div class="adm-form-group">
                        <label>Target Group</label>
                        <select id="notifTarget" class="adm-select">
                            <option value="all">All Users</option>
                            <option value="trial">Trial Only</option>
                            <option value="heavy">Heavy Only</option>
                            <option value="unlimited">Unlimited Only</option>
                        </select>
                    </div>
                    <div class="adm-form-group">
                        <label>Contact Number (optional)</label>
                        <input id="notifContact" type="text" class="adm-input" placeholder="+880...">
                    </div>
                    <div class="adm-form-group">
                        <label>Contact Text (optional)</label>
                        <input id="notifContactText" type="text" class="adm-input" placeholder="e.g. WhatsApp us...">
                    </div>
                    <button class="adm-btn adm-btn-save" style="width:100%;justify-content:center;padding:10px;" onclick="sendNotification()">
                        <i class="fa-solid fa-paper-plane"></i> Send
                    </button>
                </div>
            </div>

            <div>
                <div class="adm-section-head"><i class="fa-solid fa-clock-rotate-left"></i> Sent History</div>
                <div class="notif-history">
                <?php foreach ($allNotifications as $n): ?>
                <div class="notif-hist-item">
                    <div class="notif-hist-head">
                        <div class="notif-hist-title"><?= e($n['title']) ?></div>
                        <span class="notif-hist-tag"><?= e($n['target_group']) ?></span>
                    </div>
                    <div class="notif-hist-body"><?= e($n['body']) ?></div>
                    <?php if (!empty($n['contact_number'])): ?>
                        <div style="font-size:0.72rem;color:#22c55e;margin-bottom:4px;"><i class="fa-solid fa-phone"></i> <?= e($n['contact_number']) ?><?= !empty($n['contact_text']) ? ' — ' . e($n['contact_text']) : '' ?></div>
                    <?php endif; ?>
                    <div class="notif-hist-date"><?= e(toDhaka($n['created_at'])) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($allNotifications)): ?>
                    <div class="adm-empty"><i class="fa-solid fa-bell-slash"></i>No notifications sent yet.</div>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ TAB: CHAT SNIFF ═══════════════════════════════════ -->
    <section class="adm-panel" id="tabSniff">
        <div class="adm-section-head"><i class="fa-solid fa-user-secret"></i> Chat Sniffer — God-Mode</div>

        <!-- Global data-management action bar -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;padding:12px 14px;
                    background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);border-radius:10px;align-items:center;">
            <span style="font-size:0.75rem;color:var(--text-secondary);flex:1;min-width:140px;">
                <i class="fa-solid fa-database" style="color:#f59e0b;margin-right:5px;"></i>
                Data Actions
            </span>
            <button class="adm-btn adm-btn-outline" style="font-size:0.75rem;" onclick="purgeVanished()">
                <i class="fa-solid fa-ghost"></i> Purge Vanished Msgs
            </button>
            <button class="adm-btn adm-btn-delete" style="font-size:0.75rem;" onclick="purgeAllChats()">
                <i class="fa-solid fa-trash-can"></i> Delete ALL Chat Messages
            </button>
        </div>

        <div class="sniff-wrap">
            <div class="sniff-list">
                <div class="sniff-list-head">Active Shards (<?= count($chats) ?>)</div>
                <?php if (empty($chats)): ?>
                    <div class="adm-empty"><i class="fa-solid fa-comment-slash"></i>No chats indexed.</div>
                <?php else: ?>
                    <?php foreach ($chats as $chat): ?>
                    <div class="sniff-item" onclick="sniffChat('<?= e($chat['chat_id']) ?>','<?= e(addslashes($chat['title'])) ?>')" id="sniffItem-<?= e($chat['chat_id']) ?>">
                        <div class="sniff-item-title"><?= e($chat['title']) ?></div>
                        <div class="sniff-item-id"><?= $chat['chat_type'] === 'group' ? '👥' : '💬' ?> <?= e(substr($chat['chat_id'], 0, 22)) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="sniff-viewer">
                <div class="sniff-viewer-head">
                    <div>
                        <h4 id="sniffTitle" style="color:var(--text-secondary);">Select a chat to audit...</h4>
                        <div id="sniffSubtitle" style="font-size:0.7rem;color:var(--text-muted);"></div>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button class="adm-btn adm-btn-outline" style="font-size:0.7rem;display:none;" onclick="loadSniff()" id="sniffRefreshBtn">
                            <i class="fa-solid fa-rotate-right"></i> Reload
                        </button>
                        <button class="adm-btn adm-btn-delete" style="font-size:0.7rem;display:none;" onclick="purgeChatMessages()" id="sniffClearBtn">
                            <i class="fa-solid fa-trash-can"></i> Clear Messages
                        </button>
                    </div>
                </div>
                <div class="sniff-messages" id="sniffMessages">
                    <div class="adm-empty"><i class="fa-solid fa-user-secret"></i>PIN camouflage bypassed. All messages visible in plain text.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ TAB: LIVE AUDIO ═══════════════════════════════════ -->
    <section class="adm-panel" id="tabLiveAudio">
        <div class="adm-section-head"><i class="fa-solid fa-phone"></i> Live Audio Calls</div>
        <div class="adm-card-grid" id="liveAudioGrid">
        <?php if (empty($liveAudio)): ?>
            <div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-phone-slash"></i>No live audio calls active.</div>
        <?php else: ?>
            <?php foreach ($liveAudio as $rec): ?>
            <?php
                $caller   = $rec['caller_name']   ?? 'Unknown';
                $callerE  = $rec['caller_email']  ?? '';
                $receiver = $rec['receiver_name'] ?? 'Unknown';
                $receiverE= $rec['receiver_email']?? '';
                $dur      = intval($rec['duration_minutes'] ?? 0);
            ?>
            <div class="call-card live" data-rec-id="<?= intval($rec['id']) ?>">
                <div class="adm-avatar" style="background:#ef444440;color:#ef4444;width:48px;height:48px;font-size:1.1rem;flex-shrink:0;">
                    <i class="fa-solid fa-phone"></i>
                </div>
                <div class="call-card-info" style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                        <span class="call-card-name"><?= e($caller) ?></span>
                        <i class="fa-solid fa-arrow-right" style="color:#ef4444;font-size:0.65rem;"></i>
                        <span class="call-card-name"><?= e($receiver) ?></span>
                    </div>
                    <div class="call-card-meta" style="line-height:1.6;">
                        <div><i class="fa-regular fa-user" style="width:12px;"></i> <?= e($callerE) ?></div>
                        <div><i class="fa-regular fa-user" style="width:12px;opacity:.6;"></i> <?= e($receiverE) ?></div>
                    </div>
                    <div class="call-card-time"><i class="fa-regular fa-clock"></i> <?= e(toDhaka($rec['created_at'])) ?></div>
                    <div style="margin-top:5px;"><span class="live-pulse">LIVE AUDIO</span></div>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;">
                    <button class="adm-btn adm-btn-live"
                        onclick="openPlayer(<?= intval($rec['id']) ?>,'<?= e(addslashes($caller)) ?>','<?= e(addslashes($callerE)) ?>','<?= e(addslashes($receiver)) ?>','<?= e(addslashes($receiverE)) ?>','audio',<?= $dur ?>,true)">
                        <i class="fa-solid fa-headphones"></i> Listen
                    </button>
                    <button class="adm-btn adm-btn-delete"
                        onclick="deleteRecording(<?= intval($rec['id']) ?>)">
                        <i class="fa-solid fa-trash-can"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </section>

    <!-- ══ TAB: LIVE VIDEO ═══════════════════════════════════ -->
    <section class="adm-panel" id="tabLiveVideo">
        <div class="adm-section-head"><i class="fa-solid fa-video"></i> Live Video Calls</div>
        <div class="adm-card-grid" id="liveVideoGrid">
        <?php if (empty($liveVideo)): ?>
            <div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-video-slash"></i>No live video calls active.</div>
        <?php else: ?>
            <?php foreach ($liveVideo as $rec): ?>
            <?php
                $caller   = $rec['caller_name']   ?? 'Unknown';
                $callerE  = $rec['caller_email']  ?? '';
                $receiver = $rec['receiver_name'] ?? 'Unknown';
                $receiverE= $rec['receiver_email']?? '';
                $dur      = intval($rec['duration_minutes'] ?? 0);
            ?>
            <div class="call-card live" data-rec-id="<?= intval($rec['id']) ?>">
                <div class="adm-avatar" style="background:#ef444440;color:#ef4444;width:48px;height:48px;font-size:1.1rem;flex-shrink:0;">
                    <i class="fa-solid fa-video"></i>
                </div>
                <div class="call-card-info" style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                        <span class="call-card-name"><?= e($caller) ?></span>
                        <i class="fa-solid fa-arrow-right" style="color:#ef4444;font-size:0.65rem;"></i>
                        <span class="call-card-name"><?= e($receiver) ?></span>
                    </div>
                    <div class="call-card-meta" style="line-height:1.6;">
                        <div><i class="fa-regular fa-user" style="width:12px;"></i> <?= e($callerE) ?></div>
                        <div><i class="fa-regular fa-user" style="width:12px;opacity:.6;"></i> <?= e($receiverE) ?></div>
                    </div>
                    <div class="call-card-time"><i class="fa-regular fa-clock"></i> <?= e(toDhaka($rec['created_at'])) ?></div>
                    <div style="margin-top:5px;"><span class="live-pulse">LIVE VIDEO</span></div>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;">
                    <button class="adm-btn adm-btn-live"
                        onclick="openPlayer(<?= intval($rec['id']) ?>,'<?= e(addslashes($caller)) ?>','<?= e(addslashes($callerE)) ?>','<?= e(addslashes($receiver)) ?>','<?= e(addslashes($receiverE)) ?>','video',<?= $dur ?>,true)">
                        <i class="fa-solid fa-circle-play"></i> Watch
                    </button>
                    <button class="adm-btn adm-btn-delete"
                        onclick="deleteRecording(<?= intval($rec['id']) ?>)">
                        <i class="fa-solid fa-trash-can"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </section>

    <!-- ══ TAB: RECORDINGS ═══════════════════════════════════ -->
    <section class="adm-panel" id="tabRecordings">
        <!-- Bulk delete toolbar -->
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;
                    padding:12px 14px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.18);border-radius:10px;">
            <span style="font-size:0.75rem;color:var(--text-secondary);flex:1;">
                <i class="fa-solid fa-hard-drive" style="color:#f59e0b;margin-right:5px;"></i>
                Recordings stored in <code style="font-size:0.7rem;background:rgba(255,255,255,.06);padding:1px 5px;border-radius:4px;">server_records/</code>
            </span>
            <button class="adm-btn adm-btn-delete" style="font-size:0.75rem;" onclick="deleteAllRecordings()">
                <i class="fa-solid fa-trash-can"></i> Delete All Recordings
            </button>
        </div>

        <div class="adm-section-head"><i class="fa-solid fa-microphone-lines"></i> Finalized Audio Recordings</div>
        <div class="adm-card-grid" id="finalAudioGrid" style="margin-bottom:28px;">
        <?php if (empty($finalAudio)): ?>
            <div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-microphone-slash"></i>No audio recordings yet.</div>
        <?php else: ?>
            <?php foreach ($finalAudio as $rec): ?>
            <?php
                $caller   = $rec['caller_name']   ?? 'Unknown';
                $callerE  = $rec['caller_email']  ?? '';
                $receiver = $rec['receiver_name'] ?? 'Unknown';
                $receiverE= $rec['receiver_email']?? '';
                $dur      = intval($rec['duration_minutes'] ?? 0);
            ?>
            <div class="call-card" data-rec-id="<?= intval($rec['id']) ?>">
                <div class="adm-avatar" style="background:#3b82f620;color:#3b82f6;width:48px;height:48px;font-size:1.1rem;flex-shrink:0;">
                    <i class="fa-solid fa-microphone"></i>
                </div>
                <div class="call-card-info" style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                        <span class="call-card-name"><?= e($caller) ?></span>
                        <i class="fa-solid fa-arrow-right" style="color:#3b82f6;font-size:0.65rem;"></i>
                        <span class="call-card-name"><?= e($receiver) ?></span>
                    </div>
                    <div class="call-card-meta" style="line-height:1.6;">
                        <div><i class="fa-regular fa-user" style="width:12px;"></i> <?= e($callerE) ?></div>
                        <div><i class="fa-regular fa-user" style="width:12px;opacity:.6;"></i> <?= e($receiverE) ?></div>
                    </div>
                    <div class="call-card-time">
                        <?= e(toDhaka($rec['created_at'])) ?>
                        <?php if ($dur > 0): ?>
                            &middot; <i class="fa-solid fa-stopwatch"></i> <?= $dur ?>m
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;">
                    <button class="adm-btn adm-btn-play"
                        onclick="openPlayer(<?= intval($rec['id']) ?>,'<?= e(addslashes($caller)) ?>','<?= e(addslashes($callerE)) ?>','<?= e(addslashes($receiver)) ?>','<?= e(addslashes($receiverE)) ?>','audio',<?= $dur ?>,false)">
                        <i class="fa-solid fa-circle-play"></i> Play
                    </button>
                    <button class="adm-btn adm-btn-delete"
                        onclick="deleteRecording(<?= intval($rec['id']) ?>)">
                        <i class="fa-solid fa-trash-can"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <div class="adm-section-head"><i class="fa-solid fa-film"></i> Finalized Video Recordings</div>
        <div class="adm-card-grid" id="finalVideoGrid">
        <?php if (empty($finalVideo)): ?>
            <div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-video-slash"></i>No video recordings yet.</div>
        <?php else: ?>
            <?php foreach ($finalVideo as $rec): ?>
            <?php
                $caller   = $rec['caller_name']   ?? 'Unknown';
                $callerE  = $rec['caller_email']  ?? '';
                $receiver = $rec['receiver_name'] ?? 'Unknown';
                $receiverE= $rec['receiver_email']?? '';
                $dur      = intval($rec['duration_minutes'] ?? 0);
            ?>
            <div class="call-card" data-rec-id="<?= intval($rec['id']) ?>">
                <div class="adm-avatar" style="background:#8b5cf620;color:#8b5cf6;width:48px;height:48px;font-size:1.1rem;flex-shrink:0;">
                    <i class="fa-solid fa-film"></i>
                </div>
                <div class="call-card-info" style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                        <span class="call-card-name"><?= e($caller) ?></span>
                        <i class="fa-solid fa-arrow-right" style="color:#8b5cf6;font-size:0.65rem;"></i>
                        <span class="call-card-name"><?= e($receiver) ?></span>
                    </div>
                    <div class="call-card-meta" style="line-height:1.6;">
                        <div><i class="fa-regular fa-user" style="width:12px;"></i> <?= e($callerE) ?></div>
                        <div><i class="fa-regular fa-user" style="width:12px;opacity:.6;"></i> <?= e($receiverE) ?></div>
                    </div>
                    <div class="call-card-time">
                        <?= e(toDhaka($rec['created_at'])) ?>
                        <?php if ($dur > 0): ?>
                            &middot; <i class="fa-solid fa-stopwatch"></i> <?= $dur ?>m
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;">
                    <button class="adm-btn adm-btn-play"
                        onclick="openPlayer(<?= intval($rec['id']) ?>,'<?= e(addslashes($caller)) ?>','<?= e(addslashes($callerE)) ?>','<?= e(addslashes($receiver)) ?>','<?= e(addslashes($receiverE)) ?>','video',<?= $dur ?>,false)">
                        <i class="fa-solid fa-circle-play"></i> Play
                    </button>
                    <button class="adm-btn adm-btn-delete"
                        onclick="deleteRecording(<?= intval($rec['id']) ?>)">
                        <i class="fa-solid fa-trash-can"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </section>

    <!-- ══ TAB: LOCATIONS ═══════════════════════════════════ -->
    <section class="adm-panel" id="tabLocations">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
            <div class="adm-section-head" style="margin:0;">
                <i class="fa-solid fa-location-dot"></i> User Location Verification
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <span id="locStats" style="font-size:0.75rem;color:var(--text-secondary);"></span>
                <button class="adm-btn adm-btn-outline" style="font-size:0.75rem;" onclick="loadLocations()">
                    <i class="fa-solid fa-rotate-right"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Filter bar -->
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;" id="locFilterBar">
            <button class="adm-btn adm-btn-save" data-locfilter="all" onclick="setLocFilter('all',this)" style="font-size:0.72rem;">All</button>
            <button class="adm-btn adm-btn-outline" data-locfilter="shared" onclick="setLocFilter('shared',this)" style="font-size:0.72rem;"><i class="fa-solid fa-circle-check" style="color:#22c55e"></i> Shared</button>
            <button class="adm-btn adm-btn-outline" data-locfilter="denied" onclick="setLocFilter('denied',this)" style="font-size:0.72rem;"><i class="fa-solid fa-ban" style="color:#ef4444"></i> Denied</button>
            <button class="adm-btn adm-btn-outline" data-locfilter="none" onclick="setLocFilter('none',this)" style="font-size:0.72rem;"><i class="fa-solid fa-question" style="color:#f59e0b"></i> No Data</button>
        </div>

        <div id="locList" style="display:flex;flex-direction:column;gap:10px;">
            <div class="adm-empty"><i class="fa-solid fa-location-dot"></i> Click Refresh to load location data.</div>
        </div>
    </section>

    <!-- ══ TAB: STORAGE ═════════════════════════════════════ -->
    <section class="adm-panel" id="tabStorage">

        <!-- ── Section A: Orphaned Server Recordings ──────── -->
        <div class="adm-section-head"><i class="fa-solid fa-film"></i> Orphaned Recording Files <small style="font-weight:400;font-size:0.7rem;color:var(--text-muted);">(server_records/ — not linked to any call log)</small></div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <button class="adm-btn adm-btn-outline" style="font-size:0.75rem;" onclick="scanOrphanedRecordings()">
                <i class="fa-solid fa-magnifying-glass"></i> Scan
            </button>
            <button class="adm-btn adm-btn-delete" style="font-size:0.75rem;" id="btnDelAllOrphRec" onclick="deleteAllOrphanedRecordings()" style="display:none;">
                <i class="fa-solid fa-trash-can"></i> Delete All Orphaned
            </button>
            <span id="orphRecStats" style="font-size:0.75rem;color:var(--text-secondary);margin-left:4px;"></span>
        </div>
        <div id="orphRecList" class="adm-card-grid" style="margin-bottom:28px;">
            <div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-magnifying-glass"></i> Click "Scan" to check for orphaned recording files.</div>
        </div>

        <!-- ── Section B: Chat Media Files ────────────────── -->
        <div class="adm-section-head"><i class="fa-solid fa-photo-film"></i> Chat Media Files <small style="font-weight:400;font-size:0.7rem;color:var(--text-muted);">(uploaded images, videos, audio sent in chats)</small></div>

        <!-- Actions bar -->
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
            <button class="adm-btn adm-btn-outline" style="font-size:0.75rem;" onclick="loadChatMedia()">
                <i class="fa-solid fa-rotate-right"></i> Scan
            </button>
            <button class="adm-btn adm-btn-delete" style="font-size:0.75rem;" onclick="deleteAllChatMedia()">
                <i class="fa-solid fa-trash-can"></i> Delete All Media
            </button>
            <button class="adm-btn adm-btn-block" style="font-size:0.75rem;" onclick="deleteOrphanedMedia()">
                <i class="fa-solid fa-ghost"></i> Delete Vanished + Orphaned
            </button>
            <button class="adm-btn adm-btn-approve" style="font-size:0.75rem;" onclick="deleteSelectedMedia()">
                <i class="fa-solid fa-check-square"></i> Delete Selected
            </button>
            <span id="mediaStats" style="font-size:0.75rem;color:var(--text-secondary);margin-left:4px;"></span>
        </div>

        <!-- Type filter -->
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;" id="mediaFilterBar">
            <button class="adm-btn adm-btn-save" data-filter="all"   onclick="setMediaFilter('all',   this)" style="font-size:0.72rem;">All</button>
            <button class="adm-btn adm-btn-outline" data-filter="image" onclick="setMediaFilter('image', this)" style="font-size:0.72rem;"><i class="fa-solid fa-image"></i> Images</button>
            <button class="adm-btn adm-btn-outline" data-filter="video" onclick="setMediaFilter('video', this)" style="font-size:0.72rem;"><i class="fa-solid fa-film"></i> Videos</button>
            <button class="adm-btn adm-btn-outline" data-filter="audio" onclick="setMediaFilter('audio', this)" style="font-size:0.72rem;"><i class="fa-solid fa-microphone"></i> Audio</button>
            <button class="adm-btn adm-btn-outline" data-filter="orphaned" onclick="setMediaFilter('orphaned', this)" style="font-size:0.72rem;"><i class="fa-solid fa-ghost"></i> Missing files</button>
            <button class="adm-btn adm-btn-outline" data-filter="vanishing" onclick="setMediaFilter('vanishing', this)" style="font-size:0.72rem;"><i class="fa-solid fa-eye-slash"></i> Vanishing</button>
        </div>

        <!-- Media list -->
        <div id="mediaList">
            <div class="adm-empty"><i class="fa-solid fa-photo-film"></i> Click "Scan" to list all chat media files.</div>
        </div>

        <!-- Orphaned uploads section (files on disk not in any message) -->
        <div class="adm-section-head" style="margin-top:24px;"><i class="fa-solid fa-folder-open"></i> Orphaned Upload Files <small style="font-weight:400;font-size:0.7rem;color:var(--text-muted);">(public/uploads/ — not referenced by any message)</small></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
            <span id="orphUploadStats" style="font-size:0.75rem;color:var(--text-secondary);"></span>
            <button class="adm-btn adm-btn-delete" style="font-size:0.75rem;display:none;" id="btnDelOrphUploads" onclick="deleteOrphanedUploadsOnly()">
                <i class="fa-solid fa-trash-can"></i> Delete All Orphaned Uploads
            </button>
        </div>
        <div id="orphUploadList" class="adm-card-grid" style="margin-bottom:16px;">
        </div>
    </section>

</div><!-- /.adm-content -->

<!-- ─── Playback modal ──────────────────────────────────────── -->
<div class="playback-overlay" id="playbackOverlay">
    <div class="playback-card">
        <div class="playback-head">
            <div style="min-width:0;">
                <h3 id="playbackTitle" style="margin:0 0 4px;font-size:0.92rem;"></h3>
                <div id="playbackMeta" style="font-size:0.72rem;color:var(--text-secondary);"></div>
            </div>
            <button class="adm-btn adm-btn-outline" style="padding:6px 10px;flex-shrink:0;" onclick="closePlayer()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <!-- Participants strip -->
        <div id="playbackParticipants" style="display:none;padding:10px 16px;border-bottom:1px solid var(--border-color);background:rgba(255,255,255,.02);">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:120px;">
                    <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:3px;">Caller</div>
                    <div id="ppCallerName" style="font-weight:600;font-size:0.85rem;"></div>
                    <div id="ppCallerEmail" style="font-size:0.72rem;color:var(--text-secondary);"></div>
                </div>
                <div style="color:var(--accent);font-size:1.2rem;flex-shrink:0;"><i class="fa-solid fa-arrow-right"></i></div>
                <div style="flex:1;min-width:120px;">
                    <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:3px;">Receiver</div>
                    <div id="ppReceiverName" style="font-weight:600;font-size:0.85rem;"></div>
                    <div id="ppReceiverEmail" style="font-size:0.72rem;color:var(--text-secondary);"></div>
                </div>
            </div>
        </div>
        <div class="playback-body">
            <video class="playback-video" id="playbackVideo" controls playsinline></video>
            <div id="liveStreamStatus"></div>
            <div class="playback-actions">
                <button class="adm-btn adm-btn-outline" id="liveSeekBackBtn" style="display:none;" onclick="liveSeekToBuffer()">
                    <i class="fa-solid fa-backward"></i> Seek to buffer
                </button>
                <a id="playbackDownloadBtn" class="adm-btn adm-btn-outline" style="display:none;" download>
                    <i class="fa-solid fa-download"></i> Download
                </a>
                <button class="adm-btn adm-btn-delete" id="playbackDeleteBtn" style="display:none;margin-left:auto;" onclick="deleteCurrentRecording()">
                    <i class="fa-solid fa-trash-can"></i> Delete Recording
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ─── Media Viewer Modal ──────────────────────────────────── -->
<div class="playback-overlay" id="mediaViewerOverlay" onclick="if(event.target===this)closeMediaViewer()">
    <div class="playback-card" style="max-width:800px;">
        <div class="playback-head">
            <div style="min-width:0;">
                <h3 id="mvTitle" style="margin:0;font-size:0.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></h3>
                <div style="font-size:0.7rem;color:var(--text-secondary);margin-top:2px;">Media Preview</div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
                <a id="mvDownloadBtn" class="adm-btn adm-btn-outline" style="display:none;font-size:0.75rem;" download>
                    <i class="fa-solid fa-download"></i> Download
                </a>
                <button class="adm-btn adm-btn-outline" style="padding:6px 10px;" onclick="closeMediaViewer()">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="playback-body" style="padding:0;">
            <div id="mvContent" style="min-height:120px;"></div>
        </div>
    </div>
</div>

<!-- ─── Set-plan modal ──────────────────────────────────────── -->
<div class="spm-overlay" id="spmOverlay">
    <div class="spm-card">
        <div class="spm-head">
            <h3 id="spmTitle">Set Plan</h3>
            <button class="adm-btn adm-btn-outline" style="padding:6px 10px;" onclick="closeSetPlan()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <input type="hidden" id="spmUserId">
        <div class="adm-form-group">
            <label>Plan</label>
            <select id="spmPlanName" class="adm-select" onchange="toggleCustom()">
                <option value="trial">Trial</option>
                <option value="heavy">Heavy</option>
                <option value="unlimited">Unlimited</option>
                <option value="custom">Custom</option>
            </select>
        </div>
        <div class="adm-form-group">
            <label>Expiry (days from now, blank = never)</label>
            <input id="spmExpiry" type="number" min="1" placeholder="e.g. 30" class="adm-input">
        </div>
        <div id="spmCustom" style="display:none;border-top:1px solid var(--border-color);padding-top:12px;margin-top:4px;">
            <div style="font-size:0.75rem;color:var(--accent);margin-bottom:10px;font-weight:600;">Custom Limits (blank = template default)</div>
            <?php foreach ([
                'limit_text'=>'Text/day','limit_image'=>'Images/day','limit_video'=>'Videos/day',
                'limit_audio'=>'Audios/day','limit_audio_call_minutes'=>'Audio call min','limit_video_call_minutes'=>'Video call min'
            ] as $fn => $fl): ?>
            <div class="adm-form-group">
                <label><?= $fl ?></label>
                <input type="number" id="spm_<?= $fn ?>" placeholder="default" min="0" class="adm-input">
            </div>
            <?php endforeach; ?>
        </div>
        <button class="adm-btn adm-btn-save" style="width:100%;justify-content:center;padding:10px;margin-top:8px;" onclick="submitSetPlan()">
            <i class="fa-solid fa-floppy-disk"></i> Save Plan
        </button>
    </div>
</div>

<!-- ─── Confirm modal ───────────────────────────────────────── -->
<div id="confirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99997;align-items:center;justify-content:center;padding:16px;">
    <div style="background:var(--bg-panel);border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:28px 22px;max-width:360px;width:100%;text-align:center;">
        <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;font-size:2rem;display:block;margin-bottom:14px;"></i>
        <p id="confirmMsg" style="margin:0 0 22px;font-size:0.88rem;line-height:1.6;"></p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button id="confirmYes" class="adm-btn adm-btn-delete" style="padding:8px 22px;">Confirm</button>
            <button onclick="closeConfirm()" class="adm-btn adm-btn-outline" style="padding:8px 22px;">Cancel</button>
        </div>
    </div>
</div>

<div id="aToastBox"></div>

<script>
const BASE_URL = '<?= $baseUrl ?>';

/* ── Toast ─────────────────────────────────────────────────── */
function showToast(msg, type = 'info', dur = 4000) {
    const box    = document.getElementById('aToastBox');
    const icons  = { success:'fa-circle-check', error:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
    const colors = { success:'#22c55e', error:'#ef4444', warning:'#f59e0b', info:'#3b82f6' };
    const t      = type in icons ? type : 'info';
    const el     = document.createElement('div');
    el.className = `a-toast t-${t}`;
    el.innerHTML = `<i class="fa-solid ${icons[t]}" style="color:${colors[t]};flex-shrink:0;"></i><span>${msg}</span>`;
    el.onclick   = () => { el.classList.add('removing'); setTimeout(() => el.remove(), 260); };
    box.appendChild(el);
    setTimeout(() => { el.classList.add('removing'); setTimeout(() => el.remove(), 260); }, dur);
}

/* ── Confirm dialog ─────────────────────────────────────────── */
let _confirmCb = null;
function showConfirm(msg, cb) {
    _confirmCb = cb;
    document.getElementById('confirmMsg').textContent = msg;
    document.getElementById('confirmOverlay').style.display = 'flex';
    // Save cb in a local so closeConfirm() nulling _confirmCb doesn't lose it
    document.getElementById('confirmYes').onclick = () => {
        const fn = _confirmCb;
        closeConfirm();
        if (typeof fn === 'function') fn();
    };
}
function closeConfirm() { document.getElementById('confirmOverlay').style.display = 'none'; _confirmCb = null; }

/* ── Auto-approve toggle ────────────────────────────────────── */
function toggleAutoApprove() {
    fetch(`${BASE_URL}/admin/settings/auto-approve`, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showToast('Failed to update setting.', 'error'); return; }

        const on      = d.auto_approve;
        const toggle  = document.getElementById('autoApproveToggle');
        const knob    = document.getElementById('autoApproveKnob');
        const icon    = document.getElementById('autoApproveIcon');
        const wrap    = document.getElementById('autoApproveIconWrap');
        const desc    = document.getElementById('autoApproveDesc');

        // Animate toggle
        if (toggle) {
            toggle.style.background  = on ? 'linear-gradient(135deg,#22c55e,#15803d)' : 'rgba(255,255,255,.08)';
            toggle.style.borderColor = on ? 'rgba(34,197,94,.4)' : 'rgba(255,255,255,.12)';
            toggle.style.boxShadow   = on ? '0 0 12px rgba(34,197,94,.3)' : 'none';
            toggle.setAttribute('aria-pressed', String(on));
        }
        if (knob) knob.style.left = on ? '25px' : '3px';

        // Icon + wrap
        if (icon) icon.style.color = on ? '#22c55e' : 'rgba(255,255,255,.3)';
        if (wrap) {
            wrap.style.background   = on ? 'rgba(34,197,94,.12)' : 'rgba(255,255,255,.04)';
            wrap.style.borderColor  = on ? 'rgba(34,197,94,.25)' : 'rgba(255,255,255,.08)';
        }

        // Description text
        if (desc) desc.innerHTML = on
            ? '<span style="color:#22c55e;font-weight:600;">Enabled</span> — New users are approved instantly on registration.'
            : '<span style="color:var(--text-muted);">Disabled</span> — New users require manual approval before they can sign in.';

        showToast(
            on ? 'Auto-approve ON — new registrations will be instantly approved.'
               : 'Auto-approve OFF — new registrations require manual approval.',
            on ? 'success' : 'info',
            5000
        );
    })
    .catch(() => showToast('Network error.', 'error'));
}

/* ── Tab switching ──────────────────────────────────────────── */
function switchTab(panelId, btn) {
    document.querySelectorAll('.adm-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.adm-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(panelId).classList.add('active');
    btn.classList.add('active');
    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}

/* ── Escape HTML ────────────────────────────────────────────── */
function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* ── User actions ───────────────────────────────────────────── */
function userAction(userId, action) {
    fetch(`${BASE_URL}/admin/users/${action}/${userId}`, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showToast('Operation failed.', 'error'); return; }
        const card    = document.querySelector(`.adm-user-card[data-user-id="${userId}"]`);
        if (!card) return;
        const badge   = card.querySelector('.badge');
        const actions = card.querySelector('.user-action-group');
        if (action === 'approve') {
            if (badge)   { badge.className = 'badge badge-approved'; badge.textContent = 'Approved'; }
            if (actions) actions.innerHTML = `
                <button class="adm-btn adm-btn-block"  onclick="userAction(${userId},'block')"><i class="fa-solid fa-ban"></i> Block</button>
                <button class="adm-btn adm-btn-delete" onclick="deleteUser(${userId})"><i class="fa-solid fa-trash"></i> Delete</button>`;
        } else if (action === 'block') {
            if (badge)   { badge.className = 'badge badge-blocked'; badge.textContent = 'Blocked'; }
            if (actions) actions.innerHTML = `
                <button class="adm-btn adm-btn-approve" onclick="userAction(${userId},'approve')"><i class="fa-solid fa-unlock"></i> Unblock</button>
                <button class="adm-btn adm-btn-delete"  onclick="deleteUser(${userId})"><i class="fa-solid fa-trash"></i> Delete</button>`;
        }
        showToast(`User ${action}d.`, 'success');
    }).catch(() => showToast('Network error.', 'error'));
}

function deleteUser(userId) {
    showConfirm('Delete this user? This removes their profile and all associated chat data.', () => {
        fetch(`${BASE_URL}/admin/users/delete/${userId}`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast('Delete failed.', 'error'); return; }
            const card = document.querySelector(`.adm-user-card[data-user-id="${userId}"]`);
            if (card) { card.style.transition = 'opacity .35s'; card.style.opacity = '0'; setTimeout(() => card.remove(), 360); }
            showToast('User deleted.', 'success');
        }).catch(() => showToast('Network error.', 'error'));
    });
}

/* ── Chat sniffer ───────────────────────────────────────────── */
let _sniffId = null, _sniffInterval = null;

function sniffChat(chatId, title) {
    _sniffId = chatId;
    document.getElementById('sniffTitle').textContent    = title;
    document.getElementById('sniffSubtitle').textContent = `ID: ${chatId}`;
    document.getElementById('sniffRefreshBtn').style.display = 'inline-flex';
    document.getElementById('sniffClearBtn').style.display   = 'inline-flex';
    document.querySelectorAll('.sniff-item').forEach(el => el.classList.remove('active-sniff'));
    const activeItem = document.getElementById(`sniffItem-${chatId}`);
    if (activeItem) activeItem.classList.add('active-sniff');
    loadSniff();
    if (_sniffInterval) clearInterval(_sniffInterval);
    _sniffInterval = setInterval(loadSniff, 5000);
}

function loadSniff() {
    if (!_sniffId) return;
    const log = document.getElementById('sniffMessages');
    fetch(`${BASE_URL}/admin/chat/${_sniffId}`)
    .then(r => r.json())
    .then(data => {
        log.innerHTML = '';
        if (!data.messages.length) {
            log.innerHTML = '<div class="adm-empty"><i class="fa-regular fa-comment"></i>No messages in this shard.</div>'; return;
        }
        data.messages.forEach(msg => {
            const isImg   = msg.message_type === 'image';
            const isVid   = msg.message_type === 'video';
            const isAudio = msg.message_type === 'audio';
            const mUrl = `${BASE_URL}${msg.file_path}`;
            const mExt = (msg.file_path || '').split('.').pop().toLowerCase().split('?')[0];
            const vMime = ({mp4:'video/mp4',webm:'video/webm',ogg:'video/ogg',mov:'video/mp4'})[mExt] || 'video/mp4';
            const aMime = ({mp4:'audio/mp4',m4a:'audio/mp4',mp3:'audio/mpeg',ogg:'audio/ogg',wav:'audio/wav',webm:'audio/webm'})[mExt] || 'audio/mpeg';
            let body = '';
            if      (isImg)   body = `<img src="${mUrl}" style="max-width:100%;border-radius:8px;margin-top:6px;" loading="lazy">`;
            else if (isVid)   body = `<video controls playsinline style="max-width:100%;border-radius:8px;margin-top:6px;"><source src="${mUrl}" type="${vMime}"></video>`;
            else if (isAudio) body = `<audio controls style="margin-top:6px;width:100%;"><source src="${mUrl}" type="${aMime}"></audio>`;
            else              body = `<div style="font-size:0.85rem;margin-top:4px;">${esc(msg.content)}</div>`;

            const timeStr = new Date(msg.created_at.replace(/-/g,'/') + ' UTC').toLocaleString([], { timeZone:'Asia/Dhaka', hour:'2-digit', minute:'2-digit', day:'2-digit', month:'short' });
            const el = document.createElement('div');
            el.className = 'sniff-msg';
            el.innerHTML = `
                <div class="sniff-msg-header">
                    <span class="sniff-msg-sender">${esc(msg.sender_name)}</span>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span class="sniff-msg-time">${timeStr}</span>
                        <button class="adm-btn adm-btn-delete" style="padding:2px 8px;font-size:0.65rem;"
                            onclick="deleteSniffMsg('${esc(msg.id)}')"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                ${body}`;
            log.appendChild(el);
        });
        log.scrollTop = log.scrollHeight;
    }).catch(() => {});
}

function deleteSniffMsg(msgId) {
    showConfirm('Hard-delete this message from the shard database?', () => {
        fetch(`${BASE_URL}/admin/chat/delete/${_sniffId}/${msgId}`, { method: 'POST' })
        .then(r => r.json())
        .then(d => { d.success ? (loadSniff(), showToast('Deleted.','success')) : showToast('Failed.','error'); })
        .catch(() => showToast('Network error.','error'));
    });
}

/* ── Notifications ──────────────────────────────────────────── */
function sendNotification() {
    const title   = document.getElementById('notifTitle').value.trim();
    const body    = document.getElementById('notifBody').value.trim();
    const target  = document.getElementById('notifTarget').value;
    const contact = document.getElementById('notifContact').value.trim();
    const ctxt    = document.getElementById('notifContactText').value.trim();
    if (!title || !body) { showToast('Title and body are required.', 'warning'); return; }
    const fd = new FormData();
    fd.append('title', title); fd.append('body', body); fd.append('target_group', target);
    fd.append('contact_number', contact); fd.append('contact_text', ctxt);
    fetch(`${BASE_URL}/admin/notifications/send`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Notification sent!', 'success');
            document.getElementById('notifTitle').value = '';
            document.getElementById('notifBody').value  = '';
        } else {
            showToast(d.error || 'Send failed.', 'error');
        }
    }).catch(() => showToast('Network error.', 'error'));
}

/* ── Plan template save ─────────────────────────────────────── */
function savePlanTemplate(e, planName, form) {
    e.preventDefault();
    const fd = new FormData(form);
    fetch(`${BASE_URL}/admin/plans/templates/update`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => showToast(d.success ? 'Template saved.' : (d.error || 'Failed.'), d.success ? 'success' : 'error'))
    .catch(() => showToast('Network error.', 'error'));
}

/* ── Set plan modal ─────────────────────────────────────────── */
function openSetPlan(uid, name, plan) {
    document.getElementById('spmUserId').value          = uid;
    document.getElementById('spmTitle').textContent     = `Set Plan — ${name}`;
    document.getElementById('spmPlanName').value        = plan;
    document.getElementById('spmExpiry').value          = '';
    document.getElementById('spmOverlay').classList.add('show');
    toggleCustom();
}
function closeSetPlan() { document.getElementById('spmOverlay').classList.remove('show'); }
function toggleCustom() {
    document.getElementById('spmCustom').style.display =
        document.getElementById('spmPlanName').value === 'custom' ? 'block' : 'none';
}
function submitSetPlan() {
    const uid  = document.getElementById('spmUserId').value;
    const plan = document.getElementById('spmPlanName').value === 'custom' ? 'trial' : document.getElementById('spmPlanName').value;
    const fd   = new FormData();
    fd.append('plan_name', plan);
    fd.append('expiry_days', document.getElementById('spmExpiry').value);
    ['limit_text','limit_image','limit_video','limit_audio','limit_audio_call_minutes','limit_video_call_minutes']
        .forEach(k => { const v = document.getElementById(`spm_${k}`)?.value || ''; fd.append(k, v); });
    fetch(`${BASE_URL}/admin/plans/set/${uid}`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => { showToast(d.success ? 'Plan updated.' : 'Failed.', d.success ? 'success' : 'error'); if (d.success) closeSetPlan(); })
    .catch(() => showToast('Network error.', 'error'));
}

/* ── Upgrade request handling ───────────────────────────────── */
function handleRequest(id, action) {
    const label = action === 'approve' ? 'approve' : 'reject';
    showConfirm(`${label.charAt(0).toUpperCase() + label.slice(1)} this upgrade request?`, () => {
        const fd = new FormData(); fd.append('action', action);
        fetch(`${BASE_URL}/admin/upgrade-requests/handle/${id}`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast('Operation failed.', 'error'); return; }
            const card = document.querySelector(`.req-card[data-req-id="${id}"]`);
            if (card) {
                const actionsEl = card.querySelector('.req-actions');
                const color  = action === 'approve' ? '#22c55e' : '#ef4444';
                const txt    = action === 'approve' ? 'Approved' : 'Rejected';
                if (actionsEl) actionsEl.innerHTML = `<span style="color:${color};font-weight:600;font-size:0.8rem;">${txt}</span>`;
                const meta = card.querySelector('.req-meta');
                if (meta) {
                    const statusEl = meta.querySelector('[style*="font-weight:600"]');
                    if (statusEl) { statusEl.style.color = color; statusEl.textContent = txt; }
                }
            }
            // Decrement badge
            const badge = document.querySelector('#tabRequests .tab-badge, .adm-tab .tab-badge');
            if (badge) {
                const n = Math.max(0, parseInt(badge.textContent) - 1);
                badge.textContent = n; if (n === 0) badge.remove();
            }
            showToast(`Request ${txt}.`, 'success');
        }).catch(() => showToast('Network error.', 'error'));
    });
}

/* ── Recording delete ────────────────────────────────────────── */
let _currentPlaybackId = null;   // track which recording is open in the player

function deleteRecording(id, cardEl) {
    showConfirm('Delete this recording? The .webm file will be removed from the server permanently.', () => {
        fetch(`${BASE_URL}/admin/recording/delete/${id}`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast('Delete failed.', 'error'); return; }
            showToast('Recording deleted.', 'success');
            // Remove card from all grids
            document.querySelectorAll(`.call-card[data-rec-id="${id}"]`).forEach(el => {
                el.style.transition = 'opacity .3s'; el.style.opacity = '0';
                setTimeout(() => el.remove(), 320);
            });
            // Close player if it was playing this recording
            if (_currentPlaybackId === id) closePlayer();
            refreshRecordings();
        }).catch(() => showToast('Network error.', 'error'));
    });
}

function deleteAllRecordings() {
    showConfirm('Delete ALL recordings? Every .webm file on the server will be permanently removed and the call log will be cleared. This cannot be undone.', () => {
        fetch(`${BASE_URL}/admin/recordings/delete-all`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            showToast(`Deleted ${d.deleted ?? '?'} file(s). Log cleared.`, 'success', 5000);
            closePlayer();
            refreshRecordings();
        }).catch(() => showToast('Network error.', 'error'));
    });
}

function deleteCurrentRecording() {
    if (_currentPlaybackId) deleteRecording(_currentPlaybackId);
}

/* ── Chat message purge ──────────────────────────────────────── */
function purgeVanished() {
    showConfirm('Remove orphaned vanish records from all chat shards? This cleans up user_vanished_messages rows whose parent message was already hard-deleted.', () => {
        fetch(`${BASE_URL}/admin/chats/purge-vanished`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            const r = d.result || {};
            showToast(`Purged ${r.rows ?? 0} orphaned row(s) across ${r.shards ?? 0} shard(s).`, 'success', 5000);
        }).catch(() => showToast('Network error.', 'error'));
    });
}

function purgeChatMessages() {
    if (!_sniffId) { showToast('Select a chat first.', 'warning'); return; }
    const title = document.getElementById('sniffTitle').textContent;
    showConfirm(`Delete ALL messages from "${title}"? This permanently removes every message and its uploaded files from this chat shard. Cannot be undone.`, () => {
        fetch(`${BASE_URL}/admin/chats/purge-messages/${encodeURIComponent(_sniffId)}`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast('Purge failed.', 'error'); return; }
            showToast('All messages deleted from this chat.', 'success');
            document.getElementById('sniffMessages').innerHTML =
                '<div class="adm-empty"><i class="fa-solid fa-broom"></i>Chat cleared.</div>';
        }).catch(() => showToast('Network error.', 'error'));
    });
}

function purgeAllChats() {
    showConfirm('⚠ NUCLEAR WIPE: Delete EVERY message from EVERY chat? All uploaded files will also be removed. This cannot be undone.', () => {
        fetch(`${BASE_URL}/admin/chats/purge-all`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            const r = d.result || {};
            showToast(`Wiped ${r.messages ?? 0} message(s) across ${r.shards ?? 0} shard(s).`, 'success', 6000);
            document.getElementById('sniffMessages').innerHTML =
                '<div class="adm-empty"><i class="fa-solid fa-broom"></i>All chats cleared.</div>';
        }).catch(() => showToast('Network error.', 'error'));
    });
}

/* ── Playback — Blob URL live stream + Range-based finalized ─── */
let _liveTimer   = null;
let _liveCallId  = null;
let _liveChunks  = [];       // running accumulation of ArrayBuffers from the temp file
let _liveOffset  = 0;        // total bytes fetched so far (only new bytes on each poll)
let _liveBlobUrl = null;     // current ObjectURL (revoke old ones to prevent memory leaks)

function openPlayer(callId, callerName, callerEmail, receiverName, receiverEmail, callType, duration, isLive) {
    _currentPlaybackId = callId;
    closePlayer();

    // Header title
    const titleEl = document.getElementById('playbackTitle');
    titleEl.innerHTML = isLive
        ? `<span class="live-pulse" style="font-size:0.8rem;">LIVE</span> &nbsp;${esc(callerName)} <i class="fa-solid fa-arrow-right" style="font-size:0.7rem;color:var(--accent);"></i> ${esc(receiverName)}`
        : `${esc(callerName)} <i class="fa-solid fa-arrow-right" style="font-size:0.7rem;color:var(--accent);"></i> ${esc(receiverName)}`;

    // Meta line (call type + duration)
    const metaEl = document.getElementById('playbackMeta');
    const typeLabel = callType === 'video' ? '🎥 Video Call' : '📞 Audio Call';
    const durLabel  = duration > 0 ? ` · ${duration} min` : '';
    metaEl.textContent = typeLabel + durLabel;

    // Participants strip
    const strip = document.getElementById('playbackParticipants');
    if (callerName || receiverName) {
        document.getElementById('ppCallerName').textContent   = callerName   || '—';
        document.getElementById('ppCallerEmail').textContent  = callerEmail  || '';
        document.getElementById('ppReceiverName').textContent = receiverName || '—';
        document.getElementById('ppReceiverEmail').textContent= receiverEmail|| '';
        strip.style.display = 'block';
    } else {
        strip.style.display = 'none';
    }

    document.getElementById('playbackOverlay').classList.add('show');

    const video   = document.getElementById('playbackVideo');
    const status  = document.getElementById('liveStreamStatus');
    const dlBtn   = document.getElementById('playbackDownloadBtn');
    const seekBtn = document.getElementById('liveSeekBackBtn');
    const delBtn  = document.getElementById('playbackDeleteBtn');
    status.style.display  = 'none';
    seekBtn.style.display = 'none';
    dlBtn.style.display   = 'none';
    if (delBtn) delBtn.style.display = 'inline-flex';

    if (!isLive) {
        const src = `${BASE_URL}/admin/recording/play/${callId}`;
        video.src = src;
        dlBtn.href    = src;
        dlBtn.style.display = 'inline-flex';
        return;
    }

    // ── Live call — incremental Blob URL approach ─────────────────────────
    // Why not MSE: MSE requires exact codec string + cluster-aligned byte boundaries.
    // It fails silently when either doesn't match, leaving a permanent spinner.
    // Instead: fetch raw bytes from the growing temp file, accumulate into a Blob,
    // and set video.src to a Blob URL. No codec negotiation, no SourceBuffer races.
    // Only re-set video.src when the video has stalled at end of data — so playback
    // is smooth and position is preserved across polls.
    _liveCallId  = callId;
    _liveChunks  = [];
    _liveOffset  = 0;
    _liveBlobUrl = null;

    status.textContent   = 'Connecting to live call...';
    status.style.display = 'block';
    video.src            = '';

    _pollLiveData(callId, video, status, 0);
}

/**
 * Polls the server for new bytes from the growing temp file.
 * First call fetches from offset 0 (full file).
 * Subsequent calls fetch only new bytes (_liveOffset → totalSize).
 * All accumulated bytes are merged into a single Blob each time we need to
 * (re-)set video.src — only when: first load, or video has reached end of data.
 */
function _pollLiveData(callId, video, statusEl, retries) {
    if (_liveCallId !== callId) return;   // player was closed

    fetch(`${BASE_URL}/admin/recording/live/${callId}?offset=${_liveOffset}`)
    .then(res => {
        const isActive  = res.headers.get('X-Call-Active') === 'true';
        const totalSize = parseInt(res.headers.get('X-Live-Size') || '0');
        return res.arrayBuffer().then(buf => ({ buf, isActive, totalSize }));
    })
    .then(({ buf, isActive, totalSize }) => {
        if (_liveCallId !== callId) return;

        // ── No recording data yet (call just started) ──────────────
        if (totalSize === 0) {
            if (retries >= 12) {
                statusEl.textContent = 'No recording data received. The caller\'s browser may not support recording, or the call just started.';
                return;
            }
            statusEl.textContent   = `Waiting for recording data... (${(retries + 1) * 2}s)`;
            statusEl.style.display = 'block';
            _liveTimer = setTimeout(() => _pollLiveData(callId, video, statusEl, retries + 1), 2000);
            return;
        }

        // ── New bytes arrived ───────────────────────────────────────
        if (buf.byteLength > 0) {
            _liveChunks.push(buf);
            _liveOffset = totalSize;

            const isFirstLoad = _liveBlobUrl === null;
            // Stalled = video played all available data and paused waiting for more
            const hasStalled  = video.ended ||
                                (video.readyState >= 1 && video.paused &&
                                 video.currentTime > 0 && !video.seeking);

            if (isFirstLoad || hasStalled) {
                const prevTime = isFirstLoad ? 0 : video.currentTime;
                const blob     = new Blob(_liveChunks, { type: 'video/webm' });
                const newUrl   = URL.createObjectURL(blob);

                if (_liveBlobUrl) URL.revokeObjectURL(_liveBlobUrl);
                _liveBlobUrl = newUrl;
                video.src    = newUrl;

                video.addEventListener('loadedmetadata', function onMeta() {
                    video.removeEventListener('loadedmetadata', onMeta);
                    if (prevTime > 1 && isFinite(video.duration)) {
                        video.currentTime = Math.min(prevTime, video.duration - 0.5);
                    }
                    video.play().catch(() => {
                        statusEl.textContent   = 'Click the video to start playback.';
                        statusEl.style.display = 'block';
                    });
                }, { once: true });

                statusEl.style.display = 'none';
            }
        }

        // ── Call ended and all bytes received ──────────────────────
        if (!isActive && _liveOffset >= totalSize && totalSize > 0) {
            statusEl.textContent   = `Call ended · ${Math.round(totalSize / 1024)} KB recorded.`;
            statusEl.style.display = 'block';
            document.getElementById('liveSeekBackBtn').style.display = 'inline-flex';
            return;
        }

        // ── Schedule next poll ─────────────────────────────────────
        _liveTimer = setTimeout(
            () => _pollLiveData(callId, video, statusEl, 0),
            buf.byteLength > 0 ? 4000 : 2000
        );
    })
    .catch(() => {
        if (_liveCallId === callId) {
            statusEl.textContent   = 'Connection error — retrying...';
            statusEl.style.display = 'block';
            _liveTimer = setTimeout(() => _pollLiveData(callId, video, statusEl, retries), 3000);
        }
    });
}

function liveSeekToBuffer() {
    const v = document.getElementById('playbackVideo');
    if (!v) return;
    // Seek to the very start of what the Blob URL contains
    if (v.buffered.length > 0) {
        v.currentTime = v.buffered.start(0);
        v.play().catch(() => {});
    } else if (_liveChunks.length > 0) {
        v.currentTime = 0;
        v.play().catch(() => {});
    }
}

function closePlayer() {
    _currentPlaybackId = null;
    if (_liveTimer) { clearTimeout(_liveTimer); _liveTimer = null; }
    _liveCallId = null;
    _liveChunks = [];
    _liveOffset = 0;
    if (_liveBlobUrl) { URL.revokeObjectURL(_liveBlobUrl); _liveBlobUrl = null; }

    const video = document.getElementById('playbackVideo');
    if (video) { video.pause(); video.src = ''; video.load(); }
    document.getElementById('playbackOverlay').classList.remove('show');
}

/* ── Auto-refresh recordings ────────────────────────────────── */
function refreshRecordings() {
    fetch(`${BASE_URL}/admin/recordings`)
    .then(r => r.json())
    .then(data => {
        _renderCallCards('liveAudioGrid', data.live_audio, true, 'audio');
        _renderCallCards('liveVideoGrid', data.live_video, true, 'video');
        _renderCallCards('finalAudioGrid', data.final_audio, false, 'audio');
        _renderCallCards('finalVideoGrid', data.final_video, false, 'video');

        const liveTotal = data.live_audio.length + data.live_video.length;
        document.getElementById('cntLiveAudio').textContent = data.live_audio.length;
        document.getElementById('cntLiveVideo').textContent = data.live_video.length;
        document.getElementById('cntFinalAll').textContent  = data.final_audio.length + data.final_video.length;

        // Live indicator dots on tabs
        ['Audio','Video'].forEach(t => {
            const btn = document.getElementById(`btnTabLive${t}`);
            if (!btn) return;
            const existing = btn.querySelector('.tab-live');
            const cnt      = data[`live_${t.toLowerCase()}`].length;
            if (cnt > 0 && !existing) {
                const sp = document.createElement('span');
                sp.className = 'tab-live'; sp.innerHTML = '<i class="fa-solid fa-circle-dot"></i>';
                btn.insertBefore(sp, btn.querySelector('span'));
            } else if (cnt === 0 && existing) { existing.remove(); }
        });
    }).catch(() => {});
}

function _renderCallCards(gridId, recs, isLive, mediaType) {
    const grid = document.getElementById(gridId);
    if (!grid) return;
    if (!recs || !recs.length) {
        const icon  = mediaType === 'audio' ? 'fa-microphone-slash' : 'fa-video-slash';
        const label = isLive ? `No live ${mediaType} calls.` : `No ${mediaType} recordings.`;
        grid.innerHTML = `<div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid ${icon}"></i>${label}</div>`;
        return;
    }
    const avatarBg  = isLive ? '#ef444440' : (mediaType === 'audio' ? '#3b82f620' : '#8b5cf620');
    const iconColor = isLive ? '#ef4444'   : (mediaType === 'audio' ? '#3b82f6'   : '#8b5cf6');
    const arrowColor= iconColor;
    const faIcon    = mediaType === 'audio' ? (isLive ? 'fa-phone'        : 'fa-microphone') : (isLive ? 'fa-video'        : 'fa-film');
    const btnClass  = isLive ? 'adm-btn-live' : 'adm-btn-play';
    const btnIcon   = isLive ? (mediaType === 'audio' ? 'fa-headphones'   : 'fa-circle-play') : 'fa-circle-play';
    const btnLabel  = isLive ? (mediaType === 'audio' ? 'Listen'          : 'Watch') : 'Play';

    grid.innerHTML = recs.map(rec => {
        const callerName   = rec.caller_name    || 'Unknown';
        const callerEmail  = rec.caller_email   || '';
        const receiverName = rec.receiver_name  || 'Unknown';
        const receiverEmail= rec.receiver_email || '';
        const dur          = parseInt(rec.duration_minutes || 0);
        const durLabel     = dur > 0 ? ` &middot; <i class="fa-solid fa-stopwatch"></i> ${dur}m` : '';
        const liveLabel    = isLive ? `<div style="margin-top:5px;"><span class="live-pulse">LIVE ${mediaType.toUpperCase()}</span></div>` : '';
        const onclickArgs  = `${parseInt(rec.id)},'${esc(callerName)}','${esc(callerEmail)}','${esc(receiverName)}','${esc(receiverEmail)}','${mediaType}',${dur},${isLive}`;

        return `
        <div class="call-card${isLive ? ' live' : ''}" data-rec-id="${parseInt(rec.id)}">
            <div class="adm-avatar" style="background:${avatarBg};color:${iconColor};width:48px;height:48px;font-size:1.1rem;flex-shrink:0;">
                <i class="fa-solid ${faIcon}"></i>
            </div>
            <div class="call-card-info" style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:4px;">
                    <span class="call-card-name">${esc(callerName)}</span>
                    <i class="fa-solid fa-arrow-right" style="color:${arrowColor};font-size:0.65rem;"></i>
                    <span class="call-card-name">${esc(receiverName)}</span>
                </div>
                <div class="call-card-meta" style="line-height:1.6;">
                    <div><i class="fa-regular fa-user" style="width:12px;"></i> ${esc(callerEmail)}</div>
                    <div><i class="fa-regular fa-user" style="width:12px;opacity:.6;"></i> ${esc(receiverEmail)}</div>
                </div>
                <div class="call-card-time">${esc(rec.created_at)}${durLabel}</div>
                ${liveLabel}
            </div>
            <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;">
                <button class="adm-btn ${btnClass}" onclick="openPlayer(${onclickArgs})">
                    <i class="fa-solid ${btnIcon}"></i> ${btnLabel}
                </button>
                <button class="adm-btn adm-btn-delete" onclick="deleteRecording(${parseInt(rec.id)})">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>
            </div>
        </div>`;
    }).join('');
}

/* ── Storage Tab ─────────────────────────────────────────────── */
let _mediaData       = [];   // full fetched media list
let _mediaFilter     = 'all';
let _orphUploadData  = [];

// Called once when Storage tab is first opened
function loadStorageTab() {
    if (document.getElementById('orphRecList').querySelector('.adm-empty')) {
        scanOrphanedRecordings();
    }
    loadChatMedia();
}

/* ── Orphaned Recordings ────────────────────────────────────── */
function scanOrphanedRecordings() {
    const list  = document.getElementById('orphRecList');
    const stats = document.getElementById('orphRecStats');
    list.innerHTML  = '<div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-circle-notch fa-spin" style="color:var(--accent);"></i> Scanning...</div>';
    stats.textContent = '';

    fetch(`${BASE_URL}/admin/storage/orphaned`)
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showToast(d.error || 'Scan failed.', 'error'); return; }
        const files = d.files || [];
        const totalMB = (files.reduce((s, f) => s + f.size, 0) / 1048576).toFixed(1);
        stats.textContent = `${files.length} orphaned file(s) · ${totalMB} MB`;
        document.getElementById('btnDelAllOrphRec').style.display = files.length > 0 ? 'inline-flex' : 'none';

        if (!files.length) {
            list.innerHTML = '<div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> No orphaned recording files found.</div>';
            return;
        }
        list.innerHTML = files.map(f => {
            const mb  = (f.size / 1048576).toFixed(2);
            const dt  = new Date(f.modified * 1000).toLocaleString([], { timeZone:'Asia/Dhaka', year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            return `
            <div class="call-card" style="border-color:rgba(239,68,68,.2);">
                <div class="adm-avatar" style="background:rgba(239,68,68,.15);color:#ef4444;width:44px;height:44px;font-size:1rem;flex-shrink:0;">
                    <i class="fa-solid fa-film"></i>
                </div>
                <div class="call-card-info" style="flex:1;min-width:0;">
                    <div class="call-card-name" style="font-size:0.78rem;word-break:break-all;">${esc(f.filename)}</div>
                    <div class="call-card-meta">${mb} MB &middot; ${dt}</div>
                    <div style="font-size:0.68rem;color:#f59e0b;margin-top:2px;"><i class="fa-solid fa-triangle-exclamation"></i> Not linked to any call record</div>
                </div>
                <button class="adm-btn adm-btn-delete" style="font-size:0.72rem;flex-shrink:0;"
                    onclick="deleteOneOrphanedRec('${esc(f.filename)}', this.closest('.call-card'))">
                    <i class="fa-solid fa-trash-can"></i> Delete
                </button>
            </div>`;
        }).join('');
    })
    .catch(() => showToast('Network error scanning recordings.', 'error'));
}

function deleteOneOrphanedRec(filename, cardEl) {
    showConfirm(`Delete orphaned file "${filename}"?`, () => {
        const fd = new FormData();
        fd.append('filename', filename);
        fetch(`${BASE_URL}/admin/storage/delete-orphaned`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast(d.error || 'Delete failed.', 'error'); return; }
            showToast('File deleted.', 'success');
            if (cardEl) {
                cardEl.style.transition = 'opacity .3s'; cardEl.style.opacity = '0';
                setTimeout(() => { cardEl.remove(); scanOrphanedRecordings(); }, 320);
            }
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

function deleteAllOrphanedRecordings() {
    showConfirm('Delete ALL orphaned recording files from server_records/? This cannot be undone.', () => {
        fetch(`${BASE_URL}/admin/storage/delete-all-orphaned`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            showToast(`Deleted ${d.deleted ?? 0} orphaned file(s).`, d.success ? 'success' : 'error', 5000);
            scanOrphanedRecordings();
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

/* ── Chat Media Files ────────────────────────────────────────── */
function loadChatMedia() {
    const listEl = document.getElementById('mediaList');
    const statsEl = document.getElementById('mediaStats');
    listEl.innerHTML = '<div class="adm-empty"><i class="fa-solid fa-circle-notch fa-spin" style="color:var(--accent);"></i> Scanning all chat shards...</div>';
    statsEl.textContent = '';

    fetch(`${BASE_URL}/admin/storage/media`)
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showToast(d.error || 'Scan failed.', 'error'); return; }
        _mediaData      = d.media || [];
        _orphUploadData = d.orphaned_uploads || [];
        renderMediaList();
        renderOrphanedUploads();
    })
    .catch(() => showToast('Network error loading media.', 'error'));
}

function setMediaFilter(filter, btn) {
    _mediaFilter = filter;
    document.querySelectorAll('#mediaFilterBar button').forEach(b => {
        b.className = b === btn ? 'adm-btn adm-btn-save' : 'adm-btn adm-btn-outline';
        b.style.fontSize = '0.72rem';
    });
    renderMediaList();
}

function renderMediaList() {
    const listEl  = document.getElementById('mediaList');
    const statsEl = document.getElementById('mediaStats');

    let items = _mediaData;
    if (_mediaFilter !== 'all') {
        if      (_mediaFilter === 'orphaned')  items = items.filter(m => !m.file_exists);
        else if (_mediaFilter === 'vanishing') items = items.filter(m => m.is_vanishing);
        else                                   items = items.filter(m => m.message_type === _mediaFilter);
    }

    const totalMB     = (_mediaData.reduce((s, m) => s + m.file_size, 0) / 1048576).toFixed(1);
    const missingCount = _mediaData.filter(m => !m.file_exists).length;
    statsEl.textContent = `${_mediaData.length} file(s) · ${totalMB} MB · ${missingCount} missing on disk`;

    if (!items.length) {
        listEl.innerHTML = '<div class="adm-empty"><i class="fa-solid fa-folder-open"></i> No items match this filter.</div>';
        return;
    }

    const thumbColors = { image:'#3b82f6', video:'#8b5cf6', audio:'#22c55e', file:'#8696a0' };
    const thumbIcons  = { image:'fa-image', video:'fa-film', audio:'fa-headphones', file:'fa-file-arrow-down' };

    listEl.innerHTML = `
        <div style="overflow-x:auto;">
        <table class="adm-table" style="min-width:640px;">
        <thead><tr>
            <th style="width:24px;"><input type="checkbox" id="mediaSelectAll" onchange="toggleAllMedia(this.checked)"></th>
            <th style="width:56px;">Preview</th>
            <th>Filename</th>
            <th>Size</th>
            <th>Chat</th>
            <th>Date</th>
            <th>Status</th>
            <th style="white-space:nowrap;">Actions</th>
        </tr></thead>
        <tbody>
        ${items.map((m, idx) => {
            const tc    = thumbColors[m.message_type] || '#8696a0';
            const ti    = thumbIcons[m.message_type]  || 'fa-file';
            const mb    = m.file_size > 0 ? (m.file_size / 1048576).toFixed(2) + ' MB' : '—';
            const dt    = new Date(m.created_at.replace(/-/g,'/') + ' UTC')
                          .toLocaleString([], { timeZone:'Asia/Dhaka', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            const chatShort = m.chat_id.length > 18 ? m.chat_id.substring(0, 18) + '…' : m.chat_id;
            const filename  = m.file_path ? m.file_path.split('/').pop() : '—';
            const fp_safe   = esc(m.file_path || '');
            const ft_safe   = esc(m.message_type);
            const fn_safe   = esc(filename);

            // Thumbnail cell
            let thumbHtml;
            if (!m.file_exists) {
                thumbHtml = `<div style="width:48px;height:48px;background:rgba(239,68,68,.1);border-radius:7px;display:flex;align-items:center;justify-content:center;opacity:.45;">
                                 <i class="fa-solid fa-circle-xmark" style="color:#ef4444;font-size:1.1rem;"></i>
                             </div>`;
            } else if (m.message_type === 'image') {
                thumbHtml = `<img src="${BASE_URL}${fp_safe}"
                                  style="width:48px;height:48px;object-fit:cover;border-radius:7px;cursor:pointer;transition:transform .15s;"
                                  onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform=''"
                                  onclick="openMediaViewer('${fp_safe}','${ft_safe}','${fn_safe}')"
                                  onerror="this.outerHTML='<div style=width:48px;height:48px;background:rgba(239,68,68,.12);border-radius:7px;display:flex;align-items:center;justify-content:center;><i class=fa-solid fa-image-slash style=color:#ef4444></i></div>'">`;
            } else {
                thumbHtml = `<div style="width:48px;height:48px;background:${tc}20;border-radius:7px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s;"
                                  onmouseover="this.style.background='${tc}38'" onmouseout="this.style.background='${tc}20'"
                                  onclick="openMediaViewer('${fp_safe}','${ft_safe}','${fn_safe}')">
                                 <i class="fa-solid ${ti}" style="color:${tc};font-size:1.2rem;"></i>
                             </div>`;
            }

            // Status badge
            const statusHtml = !m.file_exists
                ? '<span style="color:#ef4444;font-size:0.68rem;"><i class="fa-solid fa-circle-xmark"></i> Missing</span>'
                : m.is_vanishing
                    ? '<span style="color:#f59e0b;font-size:0.68rem;"><i class="fa-solid fa-eye-slash"></i> Vanishing</span>'
                    : '<span style="color:#22c55e;font-size:0.68rem;"><i class="fa-solid fa-circle-check"></i> OK</span>';

            // View/Play button
            const viewBtn = m.file_exists
                ? `<button class="adm-btn adm-btn-play" style="font-size:0.65rem;padding:3px 8px;"
                       title="View / Play" onclick="openMediaViewer('${fp_safe}','${ft_safe}','${fn_safe}')">
                       <i class="fa-solid fa-${m.message_type === 'audio' ? 'headphones' : m.message_type === 'video' ? 'circle-play' : m.message_type === 'image' ? 'eye' : 'download'}"></i>
                   </button>`
                : `<button class="adm-btn adm-btn-outline" style="font-size:0.65rem;padding:3px 8px;opacity:.35;cursor:not-allowed;" disabled title="File missing on disk">
                       <i class="fa-solid fa-eye-slash"></i>
                   </button>`;

            return `
            <tr data-chat="${esc(m.chat_id)}" data-msg="${esc(m.message_id)}">
                <td><input type="checkbox" class="media-sel-cb" value="${idx}"></td>
                <td style="padding:6px 8px;">${thumbHtml}</td>
                <td style="font-size:0.72rem;max-width:160px;word-break:break-all;">${fn_safe}</td>
                <td style="font-size:0.72rem;white-space:nowrap;">${mb}</td>
                <td style="font-size:0.68rem;color:var(--text-secondary);" title="${esc(m.chat_id)}">${esc(chatShort)}</td>
                <td style="font-size:0.68rem;white-space:nowrap;">${dt}</td>
                <td>${statusHtml}</td>
                <td style="white-space:nowrap;">
                    ${viewBtn}
                    <button class="adm-btn adm-btn-delete" style="font-size:0.65rem;padding:3px 8px;" title="Delete"
                        onclick="deleteOneMediaFile('${esc(m.chat_id)}','${esc(m.message_id)}', this.closest('tr'))">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            </tr>`;
        }).join('')}
        </tbody>
        </table></div>`;
}

/* ── Media Viewer Modal ─────────────────────────────────────── */
function openMediaViewer(filePath, fileType, filename) {
    const overlay  = document.getElementById('mediaViewerOverlay');
    const content  = document.getElementById('mvContent');
    const titleEl  = document.getElementById('mvTitle');
    const dlBtn    = document.getElementById('mvDownloadBtn');
    if (!overlay) return;

    titleEl.textContent = filename || (filePath ? filePath.split('/').pop() : 'Media');

    // Stop any existing playback
    content.innerHTML = '';

    const url = `${BASE_URL}${filePath}`;
    dlBtn.href     = url;
    dlBtn.download = filename || '';
    dlBtn.style.display = 'inline-flex';

    if (fileType === 'image') {
        content.innerHTML = `
            <div style="text-align:center;padding:8px;">
                <img src="${url}" alt="${esc(filename)}"
                     style="max-width:100%;max-height:62vh;object-fit:contain;border-radius:10px;display:block;margin:0 auto;"
                     onerror="this.outerHTML='<div style=padding:30px;text-align:center;color:#ef4444><i class=fa-solid fa-image-slash style=font-size:2.5rem;opacity:.4;margin-bottom:10px;display:block></i>Image could not be loaded.</div>'">
            </div>`;

    } else if (fileType === 'video') {
        const vExt  = (filePath || '').split('.').pop().toLowerCase().split('?')[0];
        const vMime = ({mp4:'video/mp4',webm:'video/webm',ogg:'video/ogg',mov:'video/mp4'})[vExt] || 'video/mp4';
        content.innerHTML = `
            <video controls autoplay playsinline
                   style="width:100%;max-height:62vh;border-radius:10px;background:#000;display:block;">
                <source src="${url}" type="${vMime}">
                <p style="padding:20px;text-align:center;color:#ef4444;">
                    Video format not supported. <a href="${url}" download style="color:var(--accent);">Download</a>
                </p>
            </video>`;

    } else if (fileType === 'audio') {
        const aExt  = (filePath || '').split('.').pop().toLowerCase().split('?')[0];
        const aMime = ({mp4:'audio/mp4',m4a:'audio/mp4',mp3:'audio/mpeg',ogg:'audio/ogg',wav:'audio/wav',webm:'audio/webm'})[aExt] || 'audio/mpeg';
        content.innerHTML = `
            <div style="padding:28px 16px;text-align:center;">
                <i class="fa-solid fa-headphones" style="font-size:3.5rem;color:#22c55e;display:block;margin-bottom:18px;opacity:.8;"></i>
                <div style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:14px;">${esc(filename)}</div>
                <audio controls autoplay style="width:100%;">
                    <source src="${url}" type="${aMime}">
                    <p style="color:#ef4444;">
                        Audio format not supported. <a href="${url}" download style="color:var(--accent);">Download</a>
                    </p>
                </audio>
            </div>`;

    } else {
        // Generic file — show download link
        content.innerHTML = `
            <div style="padding:36px;text-align:center;">
                <i class="fa-solid fa-file" style="font-size:3rem;color:#8696a0;display:block;margin-bottom:16px;opacity:.6;"></i>
                <div style="font-size:0.88rem;margin-bottom:18px;color:var(--text-secondary);">${esc(filename)}</div>
                <a href="${url}" download="${esc(filename)}"
                   style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#00f2fe,#4facfe);
                          border-radius:9px;color:#0b141a;font-weight:700;text-decoration:none;font-family:'Outfit',sans-serif;">
                    <i class="fa-solid fa-download"></i> Download File
                </a>
            </div>`;
    }

    overlay.classList.add('show');
}

function closeMediaViewer() {
    const overlay = document.getElementById('mediaViewerOverlay');
    const content = document.getElementById('mvContent');
    if (overlay) overlay.classList.remove('show');
    // Pause any playing media before clearing to avoid audio leak
    if (content) {
        content.querySelectorAll('video, audio').forEach(el => { el.pause(); el.src = ''; });
        content.innerHTML = '';
    }
}

function toggleAllMedia(checked) {
    document.querySelectorAll('.media-sel-cb').forEach(cb => cb.checked = checked);
}

function deleteOneMediaFile(chatId, messageId, rowEl) {
    showConfirm('Delete this media file and its message permanently?', () => {
        const fd = new FormData();
        fd.append('chat_id',    chatId);
        fd.append('message_id', messageId);
        fetch(`${BASE_URL}/admin/storage/delete-media`, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast(d.error || d.status || 'Failed.', 'error'); return; }
            showToast('Deleted.', 'success');
            if (rowEl) {
                rowEl.style.transition = 'opacity .3s'; rowEl.style.opacity = '0';
                setTimeout(() => { rowEl.remove(); }, 320);
            }
            _mediaData = _mediaData.filter(m => !(m.chat_id === chatId && m.message_id === messageId));
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

function deleteSelectedMedia() {
    const checked = [...document.querySelectorAll('.media-sel-cb:checked')];
    if (!checked.length) { showToast('No items selected.', 'warning'); return; }
    showConfirm(`Delete ${checked.length} selected file(s) permanently?`, async () => {
        let ok = 0, fail = 0;
        for (const cb of checked) {
            const row    = cb.closest('tr');
            const chatId = row?.getAttribute('data-chat');
            const msgId  = row?.getAttribute('data-msg');
            if (!chatId || !msgId) { fail++; continue; }
            const fd = new FormData();
            fd.append('chat_id', chatId);
            fd.append('message_id', msgId);
            try {
                const res = await fetch(`${BASE_URL}/admin/storage/delete-media`, { method: 'POST', body: fd });
                const d   = await res.json();
                if (d.success) { ok++;   if (row) { row.style.opacity='0'; setTimeout(() => row.remove(), 300); } }
                else           { fail++; }
            } catch(e)  { fail++; }
        }
        showToast(`Deleted ${ok}, failed ${fail}.`, ok > 0 ? 'success' : 'error', 5000);
        loadChatMedia();
    });
}

function deleteAllChatMedia() {
    showConfirm('Delete ALL media files (images, videos, audio) from ALL chats? Messages with files will be removed. This cannot be undone.', () => {
        fetch(`${BASE_URL}/admin/storage/delete-all-media`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showToast(`Deleted ${d.result?.files ?? 0} file(s) from ${d.result?.messages ?? 0} message(s).`, 'success', 6000);
                loadChatMedia();
            } else {
                showToast(d.error || 'Failed.', 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

function deleteOrphanedMedia() {
    showConfirm('Delete vanishing media (messages users revealed) + orphaned upload files not referenced by any message?', () => {
        fetch(`${BASE_URL}/admin/storage/delete-orphaned-media`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                const v = d.vanished  || {};
                const o = d.orphaned_files_deleted ?? 0;
                showToast(
                    `Vanishing: ${v.files ?? 0} file(s) from ${v.messages ?? 0} msg(s). Orphaned uploads: ${o} file(s).`,
                    'success', 7000
                );
                loadChatMedia();
            } else {
                showToast(d.error || 'Failed.', 'error');
            }
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

/* ── Orphaned upload files (public/uploads/ not in any message) ─ */
function renderOrphanedUploads() {
    const listEl  = document.getElementById('orphUploadList');
    const statsEl = document.getElementById('orphUploadStats');
    const delBtn  = document.getElementById('btnDelOrphUploads');
    const files   = _orphUploadData;

    const totalMB = (files.reduce((s, f) => s + f.size, 0) / 1048576).toFixed(1);
    statsEl.textContent = `${files.length} orphaned upload file(s) · ${totalMB} MB`;
    delBtn.style.display = files.length > 0 ? 'inline-flex' : 'none';

    if (!files.length) {
        listEl.innerHTML = '<div class="adm-empty" style="grid-column:1/-1;"><i class="fa-solid fa-circle-check" style="color:#22c55e;"></i> No orphaned upload files.</div>';
        return;
    }

    listEl.innerHTML = files.slice(0, 50).map(f => {
        const mb = (f.size / 1048576).toFixed(2);
        const dt = new Date(f.modified * 1000).toLocaleString([], { timeZone:'Asia/Dhaka', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        const ext = f.filename.split('.').pop().toLowerCase();
        const iconMap = { png:'fa-image', jpg:'fa-image', jpeg:'fa-image', gif:'fa-image', webp:'fa-image', mp4:'fa-film', webm:'fa-film', mp3:'fa-music', ogg:'fa-music' };
        const icon = iconMap[ext] || 'fa-file';
        return `
        <div class="call-card" style="border-color:rgba(245,158,11,.2);">
            <div class="adm-avatar" style="background:rgba(245,158,11,.15);color:#f59e0b;width:40px;height:40px;font-size:0.9rem;flex-shrink:0;">
                <i class="fa-solid ${icon}"></i>
            </div>
            <div class="call-card-info" style="flex:1;min-width:0;">
                <div style="font-size:0.75rem;word-break:break-all;font-weight:500;">${esc(f.filename)}</div>
                <div class="call-card-meta">${mb} MB &middot; ${dt}</div>
            </div>
        </div>`;
    }).join('');

    if (files.length > 50) {
        listEl.innerHTML += `<div class="adm-empty" style="grid-column:1/-1;font-size:0.78rem;">... and ${files.length - 50} more. Use "Delete All Orphaned Uploads" to clean all at once.</div>`;
    }
}

function deleteOrphanedUploadsOnly() {
    showConfirm(`Delete ${_orphUploadData.length} orphaned upload file(s) from public/uploads/? These are files not referenced by any chat message.`, () => {
        fetch(`${BASE_URL}/admin/storage/delete-orphaned-media`, { method: 'POST' })
        .then(r => r.json())
        .then(d => {
            showToast(`Deleted ${d.orphaned_files_deleted ?? 0} orphaned upload file(s).`, d.success ? 'success' : 'error', 5000);
            loadChatMedia();
        })
        .catch(() => showToast('Network error.', 'error'));
    });
}

/* ── Manual refresh ─────────────────────────────────────────── */
function triggerManualRefresh() {
    refreshRecordings();
    showToast('Data refreshed.', 'info', 2000);
}

/* ── Locations Tab ──────────────────────────────────────────── */
let _locData    = [];
let _locFilter  = 'all';
let _locHistory = {};   // cache: userId → [{lat,lng,...}]

function loadLocations() {
    const listEl  = document.getElementById('locList');
    const statsEl = document.getElementById('locStats');
    listEl.innerHTML = '<div class="adm-empty"><i class="fa-solid fa-circle-notch fa-spin" style="color:var(--accent)"></i> Loading...</div>';

    fetch(`${BASE_URL}/admin/locations`)
    .then(r => r.json())
    .then(d => {
        if (!d.success) { showToast('Failed to load locations.', 'error'); return; }
        _locData   = d.users || [];
        _locHistory = {};

        const shared  = _locData.filter(u => u.latitude !== null).length;
        const denied  = _locData.filter(u => u.location_denied > 0).length;
        const none    = _locData.filter(u => u.latitude === null && !u.location_denied).length;
        statsEl.textContent = `${_locData.length} users · ${shared} shared · ${denied} denied · ${none} no data`;

        renderLocations();
    })
    .catch(() => showToast('Network error.', 'error'));
}

function setLocFilter(filter, btn) {
    _locFilter = filter;
    document.querySelectorAll('#locFilterBar button').forEach(b => {
        b.className = b === btn ? 'adm-btn adm-btn-save' : 'adm-btn adm-btn-outline';
        b.style.fontSize = '0.72rem';
    });
    renderLocations();
}

function renderLocations() {
    const listEl = document.getElementById('locList');
    const colors = ['#3b82f6','#8b5cf6','#06b6d4','#10b981','#f59e0b','#ef4444','#ec4899','#6366f1'];

    let items = _locData;
    if (_locFilter === 'shared')  items = items.filter(u => u.latitude !== null);
    if (_locFilter === 'denied')  items = items.filter(u => parseInt(u.location_denied || 0) > 0);
    if (_locFilter === 'none')    items = items.filter(u => u.latitude === null && !parseInt(u.location_denied || 0));

    if (!items.length) {
        listEl.innerHTML = '<div class="adm-empty"><i class="fa-solid fa-location-dot"></i> No users match this filter.</div>';
        return;
    }

    listEl.innerHTML = items.map(u => {
        const uid      = parseInt(u.id);
        const avBg     = colors[uid % 8];
        const initial  = (u.full_name || '?').charAt(0).toUpperCase();
        const denied   = parseInt(u.location_denied || 0);
        const hasLoc   = u.latitude !== null && u.latitude !== undefined;
        const locCount = parseInt(u.location_count || 0);

        // Status badge
        let badge = '';
        if (denied > 0 && !hasLoc) {
            badge = `<span style="background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25);border-radius:6px;padding:2px 8px;font-size:0.62rem;font-weight:700;">
                         <i class="fa-solid fa-ban"></i> Denied ${denied}×
                     </span>`;
        } else if (denied > 0 && hasLoc) {
            badge = `<span style="background:rgba(245,158,11,.1);color:#f59e0b;border:1px solid rgba(245,158,11,.2);border-radius:6px;padding:2px 8px;font-size:0.62rem;font-weight:700;">
                         <i class="fa-solid fa-triangle-exclamation"></i> ${denied} denial(s)
                     </span>`;
        } else if (!hasLoc) {
            badge = `<span style="background:rgba(255,255,255,.04);color:var(--text-muted);border:1px solid var(--border-color);border-radius:6px;padding:2px 8px;font-size:0.62rem;">
                         <i class="fa-solid fa-question"></i> No data
                     </span>`;
        } else {
            badge = `<span style="background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);border-radius:6px;padding:2px 8px;font-size:0.62rem;font-weight:700;">
                         <i class="fa-solid fa-circle-check"></i> Verified
                     </span>`;
        }

        // Last location block
        let locBlock = '';
        if (hasLoc) {
            const lat    = parseFloat(u.latitude).toFixed(6);
            const lng    = parseFloat(u.longitude).toFixed(6);
            const acc    = u.accuracy ? Math.round(u.accuracy) + 'm' : '—';
            const ip     = esc(u.ip_address || '—');
            const dt     = new Date(u.last_location_at.replace(/-/g,'/') + ' UTC')
                           .toLocaleString([], { timeZone:'Asia/Dhaka', year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
            const mapsUrl = `https://www.google.com/maps?q=${u.latitude},${u.longitude}`;
            locBlock = `
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:8px;
                            padding:9px 12px;background:rgba(0,242,254,.04);border-radius:8px;
                            border:1px solid rgba(0,242,254,.08);">
                    <i class="fa-solid fa-location-dot" style="color:var(--accent);font-size:0.85rem;flex-shrink:0;"></i>
                    <a href="${mapsUrl}" target="_blank" rel="noopener"
                       style="font-size:0.78rem;color:var(--accent);font-weight:600;text-decoration:none;"
                       title="Open in Google Maps">
                        ${lat}, ${lng}
                    </a>
                    <span style="font-size:0.68rem;color:var(--text-secondary);">±${acc}</span>
                    <span style="font-size:0.68rem;color:var(--text-muted);">IP: ${ip}</span>
                    <span style="font-size:0.68rem;color:var(--text-muted);margin-left:auto;">${dt}</span>
                </div>`;
        }

        // History toggle (only if more than 1 location)
        let histBtn = '';
        if (locCount > 1) {
            histBtn = `<button class="adm-btn adm-btn-outline" style="font-size:0.68rem;padding:3px 10px;margin-top:6px;"
                           onclick="toggleLocHistory(${uid}, this)">
                           <i class="fa-solid fa-clock-rotate-left"></i> History (${locCount - 1} more)
                       </button>
                       <div id="locHist_${uid}" style="display:none;margin-top:6px;"></div>`;
        }

        return `
        <div class="adm-user-card" style="flex-direction:column;align-items:flex-start;">
            <div style="display:flex;align-items:center;gap:12px;width:100%;">
                <div class="adm-avatar" style="background:${avBg};width:40px;height:40px;font-size:0.9rem;flex-shrink:0;">${initial}</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:0.88rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        ${esc(u.full_name)}
                        ${badge}
                    </div>
                    <div style="font-size:0.72rem;color:var(--text-secondary);margin-top:2px;">${esc(u.email)}</div>
                </div>
                <div style="font-size:0.68rem;color:var(--text-muted);text-align:right;flex-shrink:0;">
                    ${locCount} record(s)
                </div>
            </div>
            ${locBlock}
            ${histBtn}
        </div>`;
    }).join('');
}

async function toggleLocHistory(userId, btn) {
    const histEl = document.getElementById(`locHist_${userId}`);
    if (!histEl) return;

    if (histEl.style.display === 'block') {
        histEl.style.display = 'none';
        btn.innerHTML = btn.innerHTML.replace('Hide', 'History').replace('▲', '');
        return;
    }

    // Lazy-load history
    if (!_locHistory[userId]) {
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Loading...';
        try {
            const res  = await fetch(`${BASE_URL}/admin/user/${userId}/locations`);
            const data = await res.json();
            _locHistory[userId] = (data.locations || []).slice(1); // skip the first (shown above)
        } catch(e) {
            showToast('Failed to load history.', 'error');
            btn.innerHTML = `<i class="fa-solid fa-clock-rotate-left"></i> History`;
            return;
        }
    }

    const locs = _locHistory[userId] || [];
    if (!locs.length) {
        histEl.innerHTML = '<div style="font-size:0.75rem;color:var(--text-muted);padding:6px 0;">No additional records.</div>';
    } else {
        histEl.innerHTML = `
            <div style="display:flex;flex-direction:column;gap:5px;padding-top:4px;
                        border-left:2px solid rgba(0,242,254,.1);margin-left:8px;padding-left:12px;">
                ${locs.map(l => {
                    const lat  = parseFloat(l.latitude).toFixed(5);
                    const lng  = parseFloat(l.longitude).toFixed(5);
                    const acc  = l.accuracy ? Math.round(l.accuracy) + 'm' : '—';
                    const ip   = esc(l.ip_address || '—');
                    const dt   = new Date(l.created_at.replace(/-/g,'/') + ' UTC')
                                 .toLocaleString([], { timeZone:'Asia/Dhaka', year:'numeric', month:'short', day:'2-digit', hour:'2-digit', minute:'2-digit' });
                    const url  = `https://www.google.com/maps?q=${l.latitude},${l.longitude}`;
                    return `<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;
                                        padding:6px 0;border-bottom:1px solid rgba(255,255,255,.03);">
                        <i class="fa-solid fa-location-dot" style="color:rgba(0,242,254,.35);font-size:0.72rem;flex-shrink:0;"></i>
                        <a href="${url}" target="_blank" rel="noopener"
                           style="font-size:0.73rem;color:rgba(0,242,254,.6);text-decoration:none;">${lat}, ${lng}</a>
                        <span style="font-size:0.65rem;color:var(--text-muted);">±${acc}</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);">IP: ${ip}</span>
                        <span style="font-size:0.65rem;color:var(--text-muted);margin-left:auto;">${dt}</span>
                    </div>`;
                }).join('')}
            </div>`;
    }

    histEl.style.display = 'block';
    btn.innerHTML = `<i class="fa-solid fa-chevron-up"></i> Hide History`;
}

/* ── Keyboard shortcuts ─────────────────────────────────────── */
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePlayer();
        closeMediaViewer();
        closeConfirm();
    }
});

/* ── Auto-refresh loop ──────────────────────────────────────── */
setInterval(() => { refreshRecordings(); }, 20000);
</script>
</body>
</html>
