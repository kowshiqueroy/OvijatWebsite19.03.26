<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();
$user = getUser();
$agent = $conn->query("SELECT * FROM agents WHERE user_id = {$_SESSION['user_id']}")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Ovijat Call Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/dark.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0a0a0f;
            --bg-card: #12121a;
            --bg-hover: #1a1a25;
            --border: #1e1e2e;
            --text: #e2e8f0;
            --text-muted: #64748b;
            --accent: #6366f1;
            --accent-hover: #818cf8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            background: var(--bg-dark); 
            color: var(--text); 
            font-family: 'Inter', -apple-system, sans-serif;
            min-height: 100vh;
        }
        .navbar {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 10px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .mobile-menu-btn {
            background: none;
            border: none;
            color: var(--text);
            font-size: 18px;
            cursor: pointer;
        }
        .main-grid {
            display: grid;
            grid-template-columns: 320px 1fr 280px;
            height: calc(100vh - 56px);
        }
        @media (max-width: 1200px) {
            .main-grid { grid-template-columns: 1fr 280px; }
            .panel-left { display: none; }
            .panel-left.show { display: block; position: fixed; left: 0; top: 56px; bottom: 0; width: 320px; z-index: 99; background: var(--bg-dark); }
        }
        @media (max-width: 768px) {
            .main-grid { grid-template-columns: 1fr; }
            .panel-right { display: none; }
            .panel-right.show { display: block; position: fixed; right: 0; top: 56px; bottom: 0; width: 280px; z-index: 99; background: var(--bg-dark); }
            .panel-center { padding-bottom: 60px; }
        }
        .panel {
            border-right: 1px solid var(--border);
            overflow-y: auto;
            height: calc(100vh - 56px);
        }
        .panel:last-child { border-right: none; }
        .panel-header {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            background: var(--bg-card);
            z-index: 10;
        }
        .panel-content { padding: 10px; }
        .search-box { position: relative; }
        .search-box input {
            width: 100%;
            padding: 8px 10px 8px 34px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 12px;
        }
        .search-box input:focus { border-color: var(--accent); outline: none; }
        .search-box i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 11px; }
        .search-results-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 4px;
            max-height: 250px;
            overflow-y: auto;
            display: none;
            z-index: 50;
        }
        .search-results-dropdown.show { display: block; }
        .search-result-item {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
            font-size: 12px;
        }
        .search-result-item:hover { background: var(--bg-hover); }
        .search-result-item:last-child { border-bottom: none; }
        .filter-row {
            display: flex;
            gap: 4px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .filter-row select, .filter-row input {
            padding: 5px 8px;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 5px;
            color: var(--text);
            font-size: 10px;
        }
        .stat-mini {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 4px;
            margin-top: 8px;
        }
        .stat-mini-item {
            background: var(--bg-dark);
            border-radius: 6px;
            padding: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .stat-mini-item:hover, .stat-mini-item.active { background: var(--bg-hover); border-color: var(--accent); }
        .stat-mini-item .num { font-size: 14px; font-weight: 600; }
        .stat-mini-item .label { font-size: 8px; color: var(--text-muted); text-transform: uppercase; }
        .tab-bar {
            display: flex;
            gap: 3px;
            padding: 3px;
            background: var(--bg-dark);
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .tab-item {
            flex: 1;
            padding: 6px 2px;
            text-align: center;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-muted);
        }
        .tab-item:hover { color: var(--text); }
        .tab-item.active { background: var(--accent); color: white; }
        .list-item {
            padding: 10px;
            background: var(--bg-card);
            border-radius: 8px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .list-item:hover { background: var(--bg-hover); }
        .list-item.active { border-color: var(--accent); background: var(--bg-hover); }
        .list-item-name { font-weight: 500; font-size: 12px; margin-bottom: 3px; display: flex; align-items: center; gap: 5px; }
        .list-item-meta { font-size: 10px; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 4px; }
        .badge-custom { padding: 2px 5px; border-radius: 4px; font-size: 9px; font-weight: 500; }
        .detail-header {
            padding: 14px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-dark) 100%);
        }
        .detail-avatar {
            width: 44px; height: 44px; border-radius: 10px;
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 600; margin-bottom: 8px;
        }
        .detail-name { font-size: 16px; font-weight: 600; margin-bottom: 2px; }
        .detail-phone { color: var(--text-muted); font-size: 12px; }
        .detail-actions { display: flex; gap: 5px; margin-top: 10px; flex-wrap: wrap; }
        .btn-action {
            padding: 6px 10px; border-radius: 6px; border: none;
            font-size: 11px; font-weight: 500; cursor: pointer;
            transition: all 0.2s; display: flex; align-items: center; gap: 4px;
        }
        .btn-action.primary { background: var(--accent); color: white; }
        .btn-action.primary:hover { background: var(--accent-hover); }
        .btn-action.outline { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .btn-action.outline:hover { background: var(--bg-hover); }
        .btn-action.success { background: #25D366; color: white; }
        .btn-action.danger { background: var(--danger); color: white; }
        .detail-section { padding: 12px 14px; border-bottom: 1px solid var(--border); }
        .section-title {
            font-size: 10px; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; margin-bottom: 8px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .info-item label { font-size: 9px; color: var(--text-muted); display: block; margin-bottom: 1px; }
        .info-item .value { font-size: 12px; font-weight: 500; }
        .log-entry {
            background: var(--bg-dark); border-radius: 6px; padding: 10px;
            margin-bottom: 6px; border-left: 3px solid var(--accent);
        }
        .log-entry.type-issue { border-left-color: var(--danger); }
        .log-entry.type-followup { border-left-color: var(--warning); }
        .log-entry.type-resolution { border-left-color: var(--success); }
        .log-entry.type-feedback { border-left-color: #3b82f6; }
        .log-entry-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .log-entry-time { font-size: 9px; color: var(--text-muted); }
        .log-entry-text { font-size: 11px; line-height: 1.4; }
        .log-reply {
            margin-left: 10px; padding: 6px 8px; background: var(--bg-card);
            border-radius: 5px; margin-top: 5px; font-size: 10px;
            border-left: 2px solid var(--success);
        }
        .quick-add-btn {
            position: fixed; bottom: 16px; right: 16px;
            width: 48px; height: 48px; border-radius: 12px;
            background: linear-gradient(135deg, var(--accent) 0%, #8b5cf6 100%);
            border: none; color: white; font-size: 20px; cursor: pointer;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.4);
            transition: all 0.3s; z-index: 100;
        }
        .quick-add-btn:hover { transform: scale(1.1); }
        .dropdown-menu-custom {
            position: absolute; bottom: 56px; right: 0;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 10px; padding: 5px; min-width: 150px; display: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
        }
        .dropdown-menu-custom.show { display: block; }
        .dropdown-item-custom {
            padding: 8px 10px; border-radius: 6px; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            transition: background 0.2s; font-size: 12px;
        }
        .dropdown-item-custom:hover { background: var(--bg-hover); }
        .empty-state { text-align: center; padding: 30px 16px; color: var(--text-muted); font-size: 12px; }
        .empty-state i { font-size: 32px; opacity: 0.3; margin-bottom: 8px; }
        .toast {
            position: fixed; bottom: 70px; left: 50%; transform: translateX(-50%) translateY(100px);
            background: var(--success); color: white; padding: 8px 16px;
            border-radius: 6px; font-weight: 500; opacity: 0;
            transition: all 0.3s; z-index: 1000; font-size: 12px;
        }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.8); display: none;
            align-items: center; justify-content: center; z-index: 200;
        }
        .modal-overlay.show { display: flex; }
        .modal-content {
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; width: 95%; max-width: 450px; max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header { padding: 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 14px; }
        .modal-footer { padding: 10px 14px; border-top: 1px solid var(--border); display: flex; gap: 6px; justify-content: flex-end; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 10px; font-weight: 500; color: var(--text-muted); margin-bottom: 3px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 8px; background: var(--bg-dark);
            border: 1px solid var(--border); border-radius: 6px;
            color: var(--text); font-size: 12px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--accent); }
        .activity-item { padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 11px; cursor: pointer; transition: background 0.2s; padding: 8px; border-radius: 6px; }
        .activity-item:hover { background: var(--bg-hover); }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .time { font-size: 9px; color: var(--text-muted); }
        .load-more { text-align: center; padding: 10px; }
        .load-more button { padding: 6px 16px; background: var(--bg-hover); border: 1px solid var(--border); border-radius: 5px; color: var(--text); cursor: pointer; font-size: 11px; }
        .load-more button:hover { background: var(--accent); color: white; }
        .close-panel-btn { display: none; position: absolute; top: 8px; right: 8px; background: var(--bg-hover); border: none; color: var(--text); padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 11px; }
        @media (max-width: 768px) {
            .close-panel-btn { display: block; }
            .panel-left, .panel-right { padding-top: 35px; }
        }
        .status-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; margin-right: 4px; }
        .status-open { background: var(--accent); }
        .status-followup { background: #3b82f6; }
        .status-pending { background: var(--warning); }
        .status-closed { background: var(--success); }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <button class="mobile-menu-btn" onclick="togglePanel('left')"><i class="fas fa-bars"></i></button>
                <i class="fas fa-headset" style="font-size: 18px; color: var(--accent);"></i>
                <span style="font-weight: 600; font-size: 13px;">Ovijat</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="badge-custom" style="background: rgba(16, 185, 129, 0.2); color: var(--success);"><i class="fas fa-circle" style="font-size: 5px; margin-right: 2px;"></i> ONLINE</span>
                <span style="color: var(--text-muted); font-size: 12px;"><?= $_SESSION['username'] ?></span>
                <a href="logout.php" style="color: var(--text-muted);"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </nav>

    <div class="main-grid">
        <!-- Left Panel -->
        <div class="panel panel-left" id="leftPanel">
            <button class="close-panel-btn" onclick="togglePanel('left')">Close</button>
            <div class="panel-header">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="mainSearch" placeholder="Search..." autocomplete="off">
                    <div class="search-results-dropdown" id="searchResults"></div>
                </div>
                <div class="filter-row">
                    <select id="filterType"><option value="">All</option><option value="customer">Customer</option><option value="staff">Staff</option><option value="vendor">Vendor</option><option value="sales">Sales</option></select>
                    <select id="callFilter"><option value="today">Today</option><option value="all">All</option><option value="answered">Answered</option><option value="missed">Missed</option></select>
                </div>
                <div class="filter-row">
                    <input type="date" id="dateFrom" style="width: 48%;"><input type="date" id="dateTo" style="width: 48%;">
                </div>
                <div class="stat-mini">
                    <div class="stat-mini-item" onclick="setFilter('today')"><div class="num" id="statToday">0</div><div class="label">Today</div></div>
                    <div class="stat-mini-item" onclick="setFilter('answered')"><div class="num text-success" id="statAnswered">0</div><div class="label">Answer</div></div>
                    <div class="stat-mini-item" onclick="setFilter('missed')"><div class="num text-danger" id="statMissed">0</div><div class="label">Missed</div></div>
                    <div class="stat-mini-item"><div class="num text-warning" id="statOpen">0</div><div class="label">Open</div></div>
                </div>
            </div>
            <div class="panel-content">
                <div class="tab-bar">
                    <div class="tab-item active" data-tab="calls" onclick="switchTab('calls')">Calls</div>
                    <div class="tab-item" data-tab="contacts" onclick="switchTab('contacts')">Contacts</div>
                    <div class="tab-item" data-tab="logs" onclick="switchTab('logs')">Logs</div>
                    <div class="tab-item" data-tab="tasks" onclick="switchTab('tasks')">Tasks</div>
                </div>
                <div id="listContainer"></div>
            </div>
        </div>

        <!-- Center Panel -->
        <div class="panel panel-center" id="centerPanel">
            <div id="detailContent">
                <div class="empty-state">
                    <i class="fas fa-hand-pointer"></i>
                    <p>Select a contact or call</p>
                </div>
            </div>
        </div>

        <!-- Right Panel -->
        <div class="panel panel-right" id="rightPanel">
            <button class="close-panel-btn" onclick="togglePanel('right')">Close</button>
            <div class="panel-header">
                <h6 style="font-size: 12px; font-weight: 600;">Tools</h6>
            </div>
            <div class="panel-content">
                <div class="detail-section">
                    <button class="btn-action primary w-100 mb-2" onclick="location.href='calls_report.php'">
                        <i class="fas fa-table"></i> Call Report
                    </button>
                    <button class="btn-action outline w-100 mb-2" onclick="openModal('faq')">
                        <i class="fas fa-lightbulb"></i> Add FAQ
                    </button>
                    <button class="btn-action outline w-100 mb-2" onclick="fetchPbxCalls()">
                        <i class="fas fa-sync"></i> Fetch PBX
                    </button>
                </div>
                
                <div class="detail-section">
                    <div class="section-title"><span>Recent Activity</span></div>
                    <div id="recentActivity"></div>
                </div>
            </div>
        </div>
    </div>

    <button class="quick-add-btn" onclick="toggleQuickMenu()">
        <i class="fas fa-plus"></i>
    </button>
    <div class="dropdown-menu-custom" id="quickMenu">
        <div class="dropdown-item-custom" onclick="location.href='calls_report.php'"><i class="fas fa-table" style="color: var(--accent);"></i> Call Report</div>
        <div class="dropdown-item-custom" onclick="openModal('faq')"><i class="fas fa-lightbulb" style="color: var(--success);"></i> Add FAQ</div>
    </div>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="modalTitle" style="font-size: 13px; font-weight: 600;"></h5>
                <button onclick="closeModal()" style="background: none; border: none; color: var(--text-muted); font-size: 16px; cursor: pointer;"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button class="btn-action outline" onclick="closeModal()">Cancel</button>
                <button class="btn-action primary" id="modalSubmit">Save</button>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTab = 'calls';
        let currentItem = null;
        let currentItemType = null;
        let showLogs = true;
        let showReplies = true;
        let callsPage = 0;
        let contactsPage = 0;
        let logsPage = 0;

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function toggleQuickMenu() { document.getElementById('quickMenu').classList.toggle('show'); }
        function togglePanel(panel) { document.getElementById('panel-' + panel)?.classList.toggle('show'); }
        function toggleShowLogs() { showLogs = document.getElementById('showLogs').checked; refreshDetail(); }
        function toggleShowReplies() { showReplies = document.getElementById('showReplies').checked; refreshDetail(); }
        function refreshDetail() { if (currentItemType === 'contact' && currentItem) showContactDetail(currentItem); else if (currentItemType === 'call' && currentItem) showCallDetail(currentItem); else if (currentItemType === 'log' && currentItem) showLogDetail(currentItem); }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.quick-add-btn') && !e.target.closest('.dropdown-menu-custom')) document.getElementById('quickMenu').classList.remove('show');
        });

        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
            document.querySelector(`.tab-item[data-tab="${tab}"]`).classList.add('active');
            callsPage = contactsPage = logsPage = 0;
            if (tab === 'calls') loadCalls();
            else if (tab === 'contacts') loadContacts();
            else if (tab === 'logs') loadLogs();
            else if (tab === 'tasks') loadTasks();
        }

        function setFilter(filter) {
            document.getElementById('callFilter').value = filter;
            callsPage = 0;
            loadCalls();
        }

        function formatDate(dt) {
            if (!dt) return '-';
            const d = new Date(dt);
            const now = new Date();
            if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit'});
            return d.toLocaleDateString('en-GB', {day:'numeric', month:'short'}) + ' ' + d.toLocaleTimeString('en-GB', {hour:'2-digit', minute:'2-digit'});
        }

        function loadStats() {
            fetch('api/stats.php?action=my_stats', {credentials:'include'}).then(r=>r.json()).then(data=>{
                document.getElementById('statToday').textContent = (parseInt(data.answered) + parseInt(data.missed)) || 0;
                document.getElementById('statAnswered').textContent = data.answered || 0;
                document.getElementById('statMissed').textContent = data.missed || 0;
                document.getElementById('statOpen').textContent = data.open || 0;
            });
        }

        function loadCalls() {
            const filter = document.getElementById('callFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            let url = `api/calls.php?action=list&page=${callsPage}&filter=${filter}`;
            if (dateFrom) url += `&date_from=${dateFrom}`;
            if (dateTo) url += `&date_to=${dateTo}`;
            document.getElementById('listContainer').innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';
            fetch(url, {credentials:'include'}).then(r=>r.json()).then(data=>{
                let html = '';
                if (!data.calls?.length) html = '<div class="empty-state"><i class="fas fa-phone-slash"></i><p>No calls</p></div>';
                else {
                    data.calls.forEach(c => {
                        const name = c.person_name || c.caller_name || c.caller_number || 'Unknown';
                        const status = c.status?.toLowerCase().includes('answer') ? 'answered' : 'missed';
                        const markBadge = c.call_mark ? `<span class="badge-custom" style="background: var(--bg-hover);">${c.call_mark.replace('_', ' ')}</span>` : '';
                        html += `<div class="list-item" onclick="showCallDetail(${c.id})">
                            <div class="list-item-name">
                                <i class="fas fa-${status === 'answered' ? 'phone' : 'phone-slash'}" style="color: var(--${status === 'answered' ? 'success' : 'danger'}); font-size: 10px;"></i>
                                ${name}
                            </div>
                            <div class="list-item-meta">
                                <span>${c.caller_number || '-'}</span> • ${formatDate(c.start_time)}
                                ${markBadge}
                            </div>
                        </div>`;
                    });
                    if (data.has_more) html += `<div class="load-more"><button onclick="callsPage++; loadCalls()">Load More</button></div>`;
                }
                document.getElementById('listContainer').innerHTML = html;
            });
        }

        function loadContacts() {
            const typeFilter = document.getElementById('filterType').value;
            let url = `api/persons.php?action=list&page=${contactsPage}&type=${typeFilter}`;
            if (contactsPage === 0) document.getElementById('listContainer').innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';
            fetch(url, {credentials:'include'}).then(r=>r.json()).then(data=>{
                let html = '';
                if (!data.length) { if (contactsPage === 0) html = '<div class="empty-state"><i class="fas fa-users"></i><p>No contacts</p></div>'; }
                else {
                    if (contactsPage === 0) html = '';
                    data.forEach(p => {
                        const typeColors = {customer: 'var(--success)', staff: 'var(--accent)', vendor: '#8b5cf6', sales: '#ec4899'};
                        html += `<div class="list-item" onclick="showContactDetail(${p.id})">
                            <div class="list-item-name">
                                <span style="width: 7px; height: 7px; border-radius: 50%; background: ${typeColors[p.type] || 'var(--text-muted)'};"></span>
                                ${p.name || 'Unknown'}
                            </div>
                            <div class="list-item-meta">
                                <span>${p.phone}</span> • ${p.call_count || 0} calls
                            </div>
                        </div>`;
                    });
                    if (data.length >= 20) html += `<div class="load-more"><button onclick="contactsPage++; loadContacts()">Load More</button></div>`;
                }
                if (contactsPage === 0) document.getElementById('listContainer').innerHTML = html;
                else document.getElementById('listContainer').innerHTML += html;
            });
        }

        function loadLogs() {
            let url = `api/logs.php?action=my_logs&page=${logsPage}`;
            if (logsPage === 0) document.getElementById('listContainer').innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i></div>';
            fetch(url, {credentials:'include'}).then(r=>r.json()).then(data=>{
                let html = '';
                if (!data.length) { if (logsPage === 0) html = '<div class="empty-state"><i class="fas fa-clipboard"></i><p>No logs</p></div>'; }
                else {
                    if (logsPage === 0) html = '';
                    data.forEach(l => {
                        const statusColor = {open: 'var(--accent)', followup: '#3b82f6', pending: 'var(--warning)', closed: 'var(--success)'};
                        const typeColors = {note: 'var(--accent)', issue: 'var(--danger)', followup: 'var(--warning)', resolution: 'var(--success)', feedback: '#3b82f6'};
                        html += `<div class="list-item" onclick="showLogDetail(${l.id})">
                            <div class="list-item-name">
                                <span style="width: 7px; height: 7px; border-radius: 50%; background: ${typeColors[l.type] || 'var(--text-muted)'};"></span>
                                ${(l.notes || '').substring(0, 30)}${(l.notes || '').length > 30 ? '...' : ''}
                            </div>
                            <div class="list-item-meta">
                                <span>${l.person_name || 'Unknown'}</span> • ${formatDate(l.created_at)}
                                <span class="badge-custom" style="background: ${statusColor[l.log_status] || 'var(--bg-hover)'}; color: white;">${l.log_status}</span>
                            </div>
                        </div>`;
                    });
                    if (data.length >= 20) html += `<div class="load-more"><button onclick="logsPage++; loadLogs()">Load More</button></div>`;
                }
                if (logsPage === 0) document.getElementById('listContainer').innerHTML = html;
                else document.getElementById('listContainer').innerHTML += html;
            });
        }

        function loadTasks() {
            fetch('api/tasks.php?action=my_tasks', {credentials:'include'}).then(r=>r.json()).then(data=>{
                let html = '';
                if (!data.length) html = '<div class="empty-state"><i class="fas fa-tasks"></i><p>No tasks</p></div>';
                else data.forEach(t => {
                    const priorityColors = {urgent: 'var(--danger)', high: 'var(--warning)', medium: 'var(--accent)', low: 'var(--text-muted)'};
                    html += `<div class="list-item" onclick="toggleTask(${t.id})">
                        <div class="list-item-name">
                            <i class="fas fa-${t.status === 'completed' ? 'check-circle text-success' : 'circle'}" style="font-size: 10px;"></i>
                            <span style="${t.status === 'completed' ? 'text-decoration: line-through; opacity: 0.5;' : ''}">${t.title}</span>
                        </div>
                        <div class="list-item-meta">
                            ${t.person_name ? t.person_name + ' • ' : ''}${formatDate(t.due_date)}
                            <span class="badge-custom" style="background: ${priorityColors[t.priority]}; color: white;">${t.priority}</span>
                        </div>
                    </div>`;
                });
                document.getElementById('listContainer').innerHTML = html;
            });
        }

        function loadFaqs(q = '') {
            fetch(`api/faqs.php?action=search&q=${encodeURIComponent(q)}`, {credentials:'include'}).then(r=>r.json()).then(data=>{
                let html = '';
                data.slice(0, 8).forEach(f => {
                    html += `<div class="activity-item" onclick="useFaq('${f.answer.replace(/'/g, "\\'")}')">
                        <div><strong>${f.question}</strong></div>
                        <div style="color: var(--text-muted); font-size: 10px;">${f.answer?.substring(0, 40)}...</div>
                    </div>`;
                });
                document.getElementById('faqsList')?.classList && (document.getElementById('faqsList').innerHTML = html || '<div style="color: var(--text-muted); font-size: 11px; text-align: center;">No solutions</div>');
            });
        }

        function loadRecentActivity() {
            fetch('api/activity.php?action=recent&limit=15', {credentials:'include'}).then(r=>r.json()).then(data=>{
                let html = '';
                data.forEach(a => {
                    html += `<div class="activity-item" onclick="showContactFromActivity('${a.person_id}', '${a.person_name || 'Unknown'}')">
                        <div>${a.text}</div>
                        <div class="time">${formatDate(a.created_at)}</div>
                    </div>`;
                });
                document.getElementById('recentActivity').innerHTML = html || '<div style="color: var(--text-muted); font-size: 11px; text-align: center;">No activity</div>';
            });
        }

        function showContactFromActivity(personId, personName) {
            if (personId && personId !== 'null') showContactDetail(parseInt(personId));
            else showToast('Contact not found');
        }

        function useFaq(answer) {
            document.getElementById('logNotes')?.value ? document.getElementById('logNotes').value = answer : null;
            showToast('Solution copied!');
        }

        function fetchPbxCalls() {
            showToast('Fetching PBX calls...');
            fetch('api/fetch_pbx.php', {method: 'POST', credentials: 'include', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({agent_fetch: true})})
            .then(r=>r.json()).then(data=>{
                if (data.status === 'success') showToast(`Fetched ${data.inserted} calls!`);
                else showToast(data.message || 'Failed');
                loadCalls();
                loadStats();
            }).catch(() => showToast('Fetch failed'));
        }

        function showContactDetail(id) {
            currentItem = id; currentItemType = 'contact';
            fetch(`api/persons.php?action=get&id=${id}`, {credentials:'include'}).then(r=>r.json()).then(data=>{
                if (data.error) return;
                const p = data.person;
                const typeColors = {customer: 'var(--success)', staff: 'var(--accent)', vendor: '#8b5cf6', sales: '#ec4899'};
                let logsHtml = '';
                if (showLogs && data.logs?.length) {
                    data.logs.forEach(l => {
                        let repliesHtml = '';
                        if (showReplies && l.replies?.length) {
                            l.replies.forEach(r => repliesHtml += `<div class="log-reply"><strong>${r.username}:</strong> ${r.notes}</div>`);
                        }
                        logsHtml += `<div class="log-entry type-${l.type}">
                            <div class="log-entry-header">
                                <span class="badge-custom" style="background: var(--bg-hover);">${l.type}</span>
                                <span class="badge-custom" style="background: ${l.log_status === 'open' ? 'var(--accent)' : l.log_status === 'closed' ? 'var(--success)' : 'var(--warning)'}; color: white;">${l.log_status}</span>
                                <span class="log-entry-time">${l.username} • ${formatDate(l.created_at)}</span>
                            </div>
                            <div class="log-entry-text">${l.notes}</div>
                            ${repliesHtml}
                            <button class="btn-action outline" style="padding: 3px 6px; font-size: 9px; margin-top: 5px;" onclick="replyToLog(${l.id})"><i class="fas fa-reply"></i> Reply</button>
                        </div>`;
                    });
                }
                document.getElementById('detailContent').innerHTML = `
                    <div class="detail-header">
                        <div class="detail-avatar" style="background: linear-gradient(135deg, ${typeColors[p.type] || 'var(--accent)'} 0%, #6366f1 100%);">${(p.name || 'U').charAt(0).toUpperCase()}</div>
                        <div class="detail-name">${p.name || 'Unknown'}</div>
                        <div class="detail-phone">${p.phone}</div>
                        <div class="detail-actions">
                            <button class="btn-action primary" onclick="editContact(${id})"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn-action outline" onclick="openModal('log', ${id})"><i class="fas fa-plus"></i> Log</button>
                            <button class="btn-action success" onclick="window.open('https://wa.me/${p.phone.replace(/\\D/g,'')}', '_blank')"><i class="fab fa-whatsapp"></i></button>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="info-grid">
                            <div class="info-item"><label>Type</label><div class="value">${p.type || '-'}</div></div>
                            <div class="info-item"><label>Company</label><div class="value">${p.company || '-'}</div></div>
                            <div class="info-item"><label>Email</label><div class="value">${p.email || '-'}</div></div>
                            <div class="info-item"><label>Calls</label><div class="value">${p.call_count || 0}</div></div>
                        </div>
                    </div>
                    ${showLogs ? `<div class="detail-section">
                        <div class="section-title">
                            <span>Logs & History (${data.logs?.length || 0})</span>
                            <button onclick="openModal('log', ${id})" style="background: none; border: none; color: var(--accent); cursor: pointer; font-size: 10px;">+ Add</button>
                        </div>
                        ${logsHtml || '<div style="color: var(--text-muted); font-size: 11px;">No logs yet</div>'}
                    </div>` : ''}
                `;
            });
        }

        function showCallDetail(id) {
            currentItem = id; currentItemType = 'call';
            fetch(`api/calls.php?action=get&id=${id}`, {credentials:'include'}).then(r=>r.json()).then(data=>{
                if (data.error) return;
                const c = data.call;
                const name = c.person_name || c.caller_name || 'Unknown';
                const markingOptions = ['successful', 'problem', 'need_action', 'urgent', 'failed'].map(m => `<option value="${m}" ${c.call_mark === m ? 'selected' : ''}>${m.replace('_', ' ')}</option>`).join('');
                let logsHtml = '';
                if (showLogs && data.logs?.length) {
                    data.logs.forEach(l => {
                        let repliesHtml = '';
                        if (showReplies && l.replies?.length) {
                            l.replies.forEach(r => repliesHtml += `<div class="log-reply"><strong>${r.username}:</strong> ${r.notes}</div>`);
                        }
                        logsHtml += `<div class="log-entry type-${l.type}">
                            <div class="log-entry-header">
                                <span class="badge-custom" style="background: var(--bg-hover);">${l.type}</span>
                                <span class="log-entry-time">${l.username} • ${formatDate(l.created_at)}</span>
                            </div>
                            <div class="log-entry-text">${l.notes}</div>
                            ${repliesHtml}
                        </div>`;
                    });
                }
                document.getElementById('detailContent').innerHTML = `
                    <div class="detail-header">
                        <div class="detail-avatar">${name.charAt(0).toUpperCase()}</div>
                        <div class="detail-name">${name}</div>
                        <div class="detail-phone">${c.caller_number || '-'}</div>
                        <div class="detail-actions">
                            <button class="btn-action primary" onclick="openModal('log', ${c.person_id}, ${id})"><i class="fas fa-plus"></i> Add Log</button>
                            <button class="btn-action outline" onclick="openModal('task', ${c.person_id})"><i class="fas fa-tasks"></i> Task</button>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="info-grid">
                            <div class="info-item"><label>Date</label><div class="value">${formatDate(c.start_time)}</div></div>
                            <div class="info-item"><label>Duration</label><div class="value">${c.duration || '-'}</div></div>
                            <div class="info-item"><label>Direction</label><div class="value">${c.direction || '-'}</div></div>
                            <div class="info-item"><label>Status</label><div class="value"><span class="badge-custom" style="background: ${c.status?.toLowerCase().includes('answer') ? 'var(--success)' : 'var(--danger)'}; color: white;">${c.status || '-'}</span></div></div>
                        </div>
                        <div style="margin-top: 10px;">
                            <label style="font-size: 10px; color: var(--text-muted);">Mark Call</label>
                            <select class="form-group" style="padding: 6px;" onchange="markCall(${id}, this.value)">
                                <option value="">Select...</option>
                                ${markingOptions}
                            </select>
                        </div>
                    </div>
                    ${c.recording_url ? `<div class="detail-section"><a href="${c.recording_url.startsWith('http') ? c.recording_url : 'download_recording.php?url=' + encodeURIComponent(c.recording_url)}" target="_blank" class="btn-action outline"><i class="fas fa-headphones"></i> Recording</a></div>` : ''}
                    ${showLogs ? `<div class="detail-section">
                        <div class="section-title">Call Notes (${data.logs?.length || 0})</div>
                        ${logsHtml || '<div style="color: var(--text-muted); font-size: 11px;">No notes</div>'}
                    </div>` : ''}
                `;
            });
        }

        function showLogDetail(id) {
            currentItem = id; currentItemType = 'log';
            fetch(`api/logs.php?action=get&id=${id}`, {credentials:'include'}).then(r=>r.json()).then(data=>{
                if (data.error) return;
                const l = data;
                document.getElementById('detailContent').innerHTML = `
                    <div class="detail-header">
                        <div class="detail-avatar" style="background: linear-gradient(135deg, var(--warning) 0%, #6366f1 100%);">${l.type?.charAt(0).toUpperCase()}</div>
                        <div class="detail-name">${l.type?.toUpperCase()}</div>
                        <div class="detail-phone">${l.person_name || 'Unknown'}</div>
                        <div class="detail-actions">
                            <select class="btn-action outline" onchange="updateLogStatus(${id}, this.value)" style="padding: 6px;">
                                <option value="open" ${l.log_status === 'open' ? 'selected' : ''}>Open</option>
                                <option value="followup" ${l.log_status === 'followup' ? 'selected' : ''}>Follow-up</option>
                                <option value="pending" ${l.log_status === 'pending' ? 'selected' : ''}>Pending</option>
                                <option value="closed" ${l.log_status === 'closed' ? 'selected' : ''}>Closed</option>
                            </select>
                            <button class="btn-action outline" onclick="replyToLog(${id})"><i class="fas fa-reply"></i> Reply</button>
                            <button class="btn-action outline" onclick="openModal('task', ${l.person_id}, null, ${id})"><i class="fas fa-tasks"></i></button>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="info-grid">
                            <div class="info-item"><label>Created</label><div class="value">${formatDate(l.created_at)}</div></div>
                            <div class="info-item"><label>Agent</label><div class="value">${l.username}</div></div>
                            <div class="info-item"><label>Priority</label><div class="value"><span class="badge-custom" style="background: ${l.priority === 'urgent' ? 'var(--danger)' : l.priority === 'high' ? 'var(--warning)' : 'var(--accent)'}; color: white;">${l.priority}</span></div></div>
                            <div class="info-item"><label>Category</label><div class="value">${l.category || '-'}</div></div>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="section-title">Notes</div>
                        <div class="log-entry type-${l.type}" style="border-left-width: 4px;">
                            <div class="log-entry-text">${l.notes}</div>
                        </div>
                    </div>
                `;
            });
        }

        function markCall(id, mark) {
            fetch(`api/calls.php?action=mark&id=${id}&mark=${mark}`, {credentials:'include'}).then(r=>r.json()).then(d => showToast(d.status === 'success' ? 'Marked!' : 'Error'));
        }

        function updateLogStatus(id, status) {
            fetch('api/logs.php?action=update', {method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id, log_status: status})})
            .then(r=>r.json()).then(d => { showToast(d.status === 'success' ? 'Status updated!' : 'Error'); loadLogs(); loadStats(); });
        }

        function toggleTask(id) { fetch(`api/tasks.php?action=toggle&id=${id}`, {credentials:'include'}).then(() => { loadTasks(); loadStats(); }); }

        function replyToLog(id) {
            const notes = prompt('Enter your reply:');
            if (!notes) return;
            fetch('api/logs.php?action=reply', {method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({parent_id: id, notes})})
            .then(r=>r.json()).then(d => { showToast(d.status === 'success' ? 'Reply sent!' : 'Error'); refreshDetail(); });
        }

        function editContact(id) { fetch(`api/persons.php?action=get&id=${id}`, {credentials:'include'}).then(r=>r.json()).then(data => openModal('contact_edit', null, null, null, data)); }

        function openModal(type, personId = null, callId = null, logId = null, data = null) {
            let title = '', body = '';
            if (type === 'contact' || type === 'contact_edit') {
                title = type === 'contact_edit' ? 'Edit Contact' : 'New Contact';
                const p = data?.person || {};
                body = `<input type="hidden" id="pId" value="${p.id || ''}">
                    <div class="form-group"><label>Phone *</label><input type="text" id="pPhone" value="${p.phone || ''}" ${type === 'contact_edit' ? 'readonly' : ''}></div>
                    <div class="form-group"><label>Name</label><input type="text" id="pName" value="${p.name || ''}"></div>
                    <div class="form-group"><label>Type</label><select id="pType"><option value="customer" ${p.type === 'customer' ? 'selected' : ''}>Customer</option><option value="staff" ${p.type === 'staff' ? 'selected' : ''}>Staff</option><option value="vendor" ${p.type === 'vendor' ? 'selected' : ''}>Vendor</option><option value="sales" ${p.type === 'sales' ? 'selected' : ''}>Sales</option></select></div>
                    <div class="form-group"><label>Company</label><input type="text" id="pCompany" value="${p.company || ''}"></div>
                    <div class="form-group"><label>Email</label><input type="email" id="pEmail" value="${p.email || ''}"></div>
                    <div class="form-group"><label>Address</label><textarea id="pAddress" rows="2">${p.address || ''}</textarea></div>`;
                document.getElementById('modalSubmit').onclick = () => saveContact(p.id);
            } else if (type === 'log') {
                if (!personId) { showToast('Select a contact first'); return; }
                title = 'Add Log';
                body = `<input type="hidden" id="logPersonId" value="${personId}"><input type="hidden" id="logCallId" value="${callId || ''}">
                    <div class="form-group"><label>Type</label><select id="logType"><option value="note">Note</option><option value="issue">Issue</option><option value="followup">Follow-up</option><option value="resolution">Resolution</option><option value="feedback">Feedback</option></select></div>
                    <div class="form-group"><label>Status</label><select id="logStatus"><option value="open">Open</option><option value="followup">Follow-up</option><option value="pending">Pending</option><option value="closed">Closed</option></select></div>
                    <div class="form-group"><label>Priority</label><select id="logPriority"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                    <div class="form-group"><label>Notes *</label><textarea id="logNotes" rows="3" required></textarea></div>`;
                document.getElementById('modalSubmit').onclick = saveLog;
            } else if (type === 'task') {
                title = 'Create Task';
                body = `<input type="hidden" id="taskPersonId" value="${personId || ''}"><input type="hidden" id="taskLogId" value="${logId || ''}">
                    <div class="form-group"><label>Title *</label><input type="text" id="taskTitle" required></div>
                    <div class="form-group"><label>Description</label><textarea id="taskDesc" rows="2"></textarea></div>
                    <div class="form-group"><label>Priority</label><select id="taskPriority"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
                    <div class="form-group"><label>Due Date</label><input type="datetime-local" id="taskDue"></div>`;
                document.getElementById('modalSubmit').onclick = saveTask;
            } else if (type === 'faq') {
                title = 'Add FAQ';
                body = `<div class="form-group"><label>Question *</label><input type="text" id="faqQuestion"></div>
                    <div class="form-group"><label>Answer *</label><textarea id="faqAnswer" rows="3"></textarea></div>
                    <div class="form-group"><label>Category</label><input type="text" id="faqCategory" placeholder="e.g., Billing"></div>`;
                document.getElementById('modalSubmit').onclick = saveFaq;
            }
            document.getElementById('modalTitle').innerHTML = title;
            document.getElementById('modalBody').innerHTML = body;
            document.getElementById('modalOverlay').classList.add('show');
        }

        function closeModal() { document.getElementById('modalOverlay').classList.remove('show'); }

        function saveContact(editId = null) {
            const d = {id: editId, phone: document.getElementById('pPhone').value, name: document.getElementById('pName').value, type: document.getElementById('pType').value, company: document.getElementById('pCompany').value, email: document.getElementById('pEmail').value, address: document.getElementById('pAddress').value};
            fetch(`api/persons.php?action=${editId ? 'update' : 'add'}`, {method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify(d)})
            .then(r=>r.json()).then(d => {
                closeModal();
                showToast(editId ? 'Updated!' : 'Saved!');
                if (editId) showContactDetail(editId);
                else loadContacts();
            });
        }

        function saveLog() {
            fetch('api/logs.php?action=create', {method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
                person_id: document.getElementById('logPersonId').value, call_id: document.getElementById('logCallId').value || null,
                type: document.getElementById('logType').value, log_status: document.getElementById('logStatus').value,
                priority: document.getElementById('logPriority').value, notes: document.getElementById('logNotes').value
            })}).then(r=>r.json()).then(d => {
                closeModal();
                showToast('Log saved!');
                loadLogs();
                loadStats();
                loadRecentActivity();
                if (currentItemType === 'contact') showContactDetail(currentItem);
            });
        }

        function saveTask() {
            fetch('api/tasks.php?action=create', {method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
                person_id: document.getElementById('taskPersonId').value, log_id: document.getElementById('taskLogId').value || null,
                title: document.getElementById('taskTitle').value, description: document.getElementById('taskDesc').value,
                priority: document.getElementById('taskPriority').value, due_date: document.getElementById('taskDue').value
            })}).then(r=>r.json()).then(d => {
                closeModal();
                showToast('Task created!');
                loadTasks();
                loadStats();
            });
        }

        function saveFaq() {
            fetch('api/faqs.php?action=add', {method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
                question: document.getElementById('faqQuestion').value, answer: document.getElementById('faqAnswer').value, category: document.getElementById('faqCategory').value
            })}).then(r=>r.json()).then(d => { closeModal(); showToast('FAQ added!'); });
        }

        let searchTimer;
        document.getElementById('mainSearch').addEventListener('input', function() {
            clearTimeout(searchTimer);
            const q = this.value.trim();
            const results = document.getElementById('searchResults');
            if (q.length < 2) { results.classList.remove('show'); return; }
            searchTimer = setTimeout(() => {
                fetch(`api/persons.php?action=search&q=${encodeURIComponent(q)}`, {credentials:'include'}).then(r=>r.json()).then(data=>{
                    let html = '';
                    data.slice(0, 6).forEach(p => {
                        html += `<div class="search-result-item" onclick="document.getElementById('mainSearch').value=''; results.classList.remove('show'); showContactDetail(${p.id})">
                            <strong>${p.name || 'Unknown'}</strong><br><small style="color: var(--text-muted)">${p.phone}</small>
                        </div>`;
                    });
                    results.innerHTML = html || '<div class="search-result-item" style="color: var(--text-muted)">No results</div>';
                    results.classList.add('show');
                });
            }, 300);
        });

        document.addEventListener('click', (e) => { if (!e.target.closest('.search-box')) document.getElementById('searchResults').classList.remove('show'); });
        document.getElementById('filterType').addEventListener('change', () => { contactsPage = 0; loadContacts(); });
        document.getElementById('callFilter').addEventListener('change', () => { callsPage = 0; loadCalls(); });
        document.getElementById('dateFrom').addEventListener('change', () => { callsPage = 0; loadCalls(); });
        document.getElementById('dateTo').addEventListener('change', () => { callsPage = 0; loadCalls(); });

        loadStats(); loadCalls(); loadRecentActivity();
    </script>
</body>
</html>
