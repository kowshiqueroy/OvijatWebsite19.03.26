<?php
require_once 'config.php';
require_once 'functions.php';
requireLogin();

$user = getUser();
$is_admin = $user['role'] === 'admin';
$agent_id = $_SESSION['agent_id'] ?? 0;

$tab = $_GET['tab'] ?? 'dashboard';
$view = $_GET['view'] ?? 'contacts';

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search_q = trim($_GET['q'] ?? '');
$initial_type = $_GET['type'] ?? 'contacts';

$groups = $conn->query("SELECT * FROM contact_groups ORDER BY name");
$types = $conn->query("SELECT * FROM contact_types ORDER BY name");
$tags = $conn->query("SELECT * FROM tags ORDER BY name");
$agents = $conn->query("SELECT a.*, u.username FROM agents a JOIN users u ON a.user_id = u.id WHERE a.status = 'active'");

$my_stats = [
    'calls_today' => $conn->query("SELECT COUNT(*) as c FROM calls WHERE DATE(start_time) = CURDATE() AND agent_id = $agent_id")->fetch_assoc()['c'],
    'logs_open' => $conn->query("SELECT COUNT(*) as c FROM logs WHERE log_status = 'open' AND agent_id = $agent_id")->fetch_assoc()['c'],
    'tasks_pending' => $conn->query("SELECT COUNT(*) as c FROM tasks WHERE status = 'pending' AND assigned_to = $agent_id")->fetch_assoc()['c'],
    'total_contacts' => $conn->query("SELECT COUNT(*) as c FROM persons")->fetch_assoc()['c'],
];

$recent_activity = $conn->query("SELECT a.*, ag.name as agent_name FROM activity_log a JOIN agents ag ON a.agent_id = ag.id WHERE a.agent_id = $agent_id ORDER BY a.created_at DESC LIMIT 10");
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = {$_SESSION['user_id']} ORDER BY created_at DESC LIMIT 5");

$notification_count = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = {$_SESSION['user_id']} AND is_read = 0")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ovijat Call Center</title>
    <link rel="stylesheet" href="assets/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar collapsed" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-headset"></i>
                    <span>Ovijat</span>
                </div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <a href="app.php" class="nav-item <?= $tab === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="contacts.php" class="nav-item">
                    <i class="fas fa-address-book"></i>
                    <span>Contacts</span>
                </a>
                <a href="calls_report.php" class="nav-item">
                    <i class="fas fa-phone-alt"></i>
                    <span>Calls</span>
                </a>
                <a href="?tab=logs" class="nav-item <?= $tab === 'logs' ? 'active' : '' ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Logs</span>
                </a>
                <a href="?tab=tasks" class="nav-item <?= $tab === 'tasks' ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i>
                    <span>Tasks</span>
                </a>
                <a href="?tab=faqs" class="nav-item <?= $tab === 'faqs' ? 'active' : '' ?>">
                    <i class="fas fa-question-circle"></i>
                    <span>FAQs</span>
                </a>
                <?php if ($is_admin): ?>
                <div class="nav-divider"></div>
                <a href="?tab=agents" class="nav-item <?= $tab === 'agents' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Agents</span>
                </a>
                <a href="?tab=settings" class="nav-item <?= $tab === 'settings' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="?tab=tags" class="nav-item <?= $tab === 'tags' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i>
                    <span>Tags</span>
                </a>
                <?php endif; ?>
            </nav>
            
        </aside>
        
        <main class="main-content sidebar-collapsed">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="globalSearch" placeholder="Search contacts, calls, logs... (Ctrl+K)" autocomplete="off">
                        <div class="search-results" id="searchResults"></div>
                    </div>
                </div>
                <div class="top-bar-right">
                    <div class="stats-mini">
                        <span class="stat"><i class="fas fa-phone"></i> <?= $my_stats['calls_today'] ?></span>
                        <span class="stat"><i class="fas fa-exclamation-circle"></i> <?= $my_stats['logs_open'] ?></span>
                        <span class="stat"><i class="fas fa-tasks"></i> <?= $my_stats['tasks_pending'] ?></span>
                    </div>
                    <button class="icon-btn" id="notificationBtn" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                        <span class="badge"><?= $notification_count ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notifications-dropdown" id="notificationsDropdown">
                        <h4>Notifications</h4>
                        <?php while ($n = $notifications->fetch_assoc()): ?>
                        <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                            <i class="fas fa-<?= $n['type'] === 'call' ? 'phone' : 'info' ?>"></i>
                            <div>
                                <strong><?= htmlspecialchars($n['title']) ?></strong>
                                <p><?= htmlspecialchars(substr($n['message'], 0, 50)) ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="user-menu" id="userMenu">
                        <button class="user-btn" onclick="toggleUserMenu()">
                            <div class="avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                            <span><?= htmlspecialchars($user['username']) ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="user-dropdown" id="userDropdown">
                            <a href="?tab=profile"><i class="fas fa-user"></i> Profile</a>
                            <?php if ($is_admin): ?>
                            <a href="?tab=settings"><i class="fas fa-cog"></i> Settings</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="content-area">
                <?php if ($tab === 'dashboard'): ?>
                <div class="three-col-layout">
                    <div class="col-search">
                        <div class="col-header">
                            <button class="btn btn-accent btn-sm" onclick="fetchFromPBX()">
                                <i class="fas fa-sync"></i> Fetch PBX
                            </button>
                        </div>
                        
                        <div class="col1-tabs">
                            <button class="col1-tab-btn active" onclick="switchCol1Tab('search')">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button class="col1-tab-btn" onclick="switchCol1Tab('activity')">
                                <i class="fas fa-history"></i> Recent
                            </button>
                        </div>
                        
                        <div class="col1-content" id="col1Search">
                            <div class="filter-section">
                                <div class="filter-row">
                                    <label>Date Range</label>
                                    <div class="date-range">
                                        <input type="date" id="dateFrom" value="<?= $date_from ?>">
                                        <span>to</span>
                                        <input type="date" id="dateTo" value="<?= $date_to ?>">
                                    </div>
                                </div>
                                <div class="filter-row">
                                    <select id="filterType">
                                        <option value="">All Types</option>
                                        <?php while ($t = $types->fetch_assoc()): ?>
                                        <option value="<?= $t['name'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="filter-row">
                                    <select id="filterGroup">
                                        <option value="">All Groups</option>
                                        <?php $groups->data_seek(0); while ($g = $groups->fetch_assoc()): ?>
                                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="filter-row">
                                    <select id="filterIntExt">
                                        <option value="">All</option>
                                        <option value="internal">Internal</option>
                                        <option value="external">External</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="result-tabs">
                                <button class="tab-btn active" data-tab="contacts" onclick="switchTab('contacts')">
                                    <i class="fas fa-address-book"></i> Contacts
                                </button>
                                <button class="tab-btn" data-tab="calls" onclick="switchTab('calls')">
                                    <i class="fas fa-phone"></i> Calls
                                </button>
                                <button class="tab-btn" data-tab="logs" onclick="switchTab('logs')">
                                    <i class="fas fa-clipboard"></i> Logs
                                </button>
                                <button class="tab-btn" data-tab="tasks" onclick="switchTab('tasks')">
                                    <i class="fas fa-tasks"></i> Tasks
                                </button>
                            </div>
                            
                            <div class="result-list" id="resultList">
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <p>Search or select filters to see results</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col1-content hidden" id="col1Activity">
                            <div class="activity-list-full" id="activityListFull">
                                <?php
                                $recent_all = $conn->query("SELECT a.*, ag.name as agent_name, p.name as person_name 
                                    FROM activity_log a 
                                    LEFT JOIN agents ag ON a.agent_id = ag.id 
                                    LEFT JOIN persons p ON a.person_id = p.id 
                                    ORDER BY a.created_at DESC LIMIT 50");
                                while ($act = $recent_all->fetch_assoc()):
                                ?>
                                <div class="activity-item-full" onclick="openActivityItem('<?= $act['action'] ?>', <?= $act['record_id'] ?? 0 ?>)">
                                    <div class="activity-icon-full">
                                        <i class="fas fa-<?= $act['action'] === 'call' ? 'phone' : ($act['action'] === 'log' ? 'comment' : ($act['action'] === 'task' ? 'tasks' : 'info')) ?>"></i>
                                    </div>
                                    <div class="activity-info-full">
                                        <strong><?= htmlspecialchars($act['agent_name']) ?></strong>
                                        <span><?= htmlspecialchars($act['details']) ?></span>
                                        <small><?= date('d M H:i', strtotime($act['created_at'])) ?></small>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-contact">
                        <div class="col-header">
                            <h3><i class="fas fa-user"></i> Contact</h3>
                        </div>
                        <div class="contact-info" id="contactInfo">
                            <div class="empty-state">
                                <i class="fas fa-hand-pointer"></i>
                                <p>Select a contact from the list</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-details">
                        <div class="col-header">
                            <div class="detail-tabs-header">
                                <button class="detail-tab-btn active" onclick="switchDetailTab('call')">
                                    <i class="fas fa-phone"></i> New Call
                                </button>
                                <button class="detail-tab-btn" onclick="switchDetailTab('log')">
                                    <i class="fas fa-comment"></i> New Log
                                </button>
                                <button class="detail-tab-btn" onclick="switchDetailTab('task')">
                                    <i class="fas fa-tasks"></i> New Task
                                </button>
                                <button class="detail-tab-btn" onclick="openFloatingPanel('faq')">
                                    <i class="fas fa-question"></i> FAQ
                                </button>
                            </div>
                        </div>
                        <div class="details-content" id="detailsContent">
                            <div class="detail-form-section" id="newCallForm">
                                <h4><i class="fas fa-phone"></i> New Call</h4>
                                <div class="contact-toggle">
                                    <label class="toggle-label">
                                        <input type="checkbox" id="useNewPhone" onchange="toggleNewPhone()">
                                        <span class="toggle-text">Use opened contact</span>
                                    </label>
                                </div>
                                <form onsubmit="createNewCall(event)" id="callFormNew">
                                    <div class="form-group" id="phoneInputGroup">
                                        <label>Phone *</label>
                                        <input type="text" name="phone" id="newCallPhone" placeholder="01XXXXXXXXX">
                                    </div>
                                    <div class="form-row-2">
                                        <div class="form-group">
                                            <label>Direction</label>
                                            <select name="direction">
                                                <option value="Outbound">Outbound</option>
                                                <option value="Inbound">Inbound</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select name="status">
                                                <option value="Completed">Completed</option>
                                                <option value="Missed">Missed</option>
                                                <option value="No Answer">No Answer</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Duration (seconds)</label>
                                        <input type="number" name="duration" placeholder="60">
                                    </div>
                                    <div class="form-group">
                                        <label>Date & Time</label>
                                        <input type="datetime-local" name="date">
                                    </div>
                                    <div class="form-group">
                                        <label>Notes</label>
                                        <textarea name="notes" rows="3" placeholder="Call notes..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Mark</label>
                                        <select name="call_mark">
                                            <option value="">-- Select --</option>
                                            <option value="successful">Successful</option>
                                            <option value="problem">Problem</option>
                                            <option value="need_action">Need Action</option>
                                            <option value="urgent">Urgent</option>
                                            <option value="failed">Failed</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save"></i> Save Call
                                    </button>
                                </form>
                            </div>
                            
                            <div class="detail-form-section" id="newLogForm">
                                <h4><i class="fas fa-comment"></i> New Log</h4>
                                <div id="logFormContent">
                                    <div class="no-contact-warning" id="noContactWarning" style="display: none;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <p>Select a contact first to add a log</p>
                                    </div>
                                    <form onsubmit="createNewLog(event)" id="logFormActual" style="display: none;">
                                        <input type="hidden" name="person_id" id="logPersonId">
                                        <div class="selected-contact-info" id="selectedContactInfo">
                                            <i class="fas fa-user"></i>
                                            <span id="selectedContactName"></span>
                                        </div>
                                        <div class="form-row-2">
                                            <div class="form-group">
                                                <label>Type</label>
                                                <select name="type">
                                                    <option value="note">Note</option>
                                                    <option value="issue">Issue</option>
                                                    <option value="followup">Follow-up</option>
                                                    <option value="resolution">Resolution</option>
                                                    <option value="feedback">Feedback</option>
                                                    <option value="query">Query</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select name="log_status">
                                                    <option value="open">Open</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="followup">Follow-up</option>
                                                    <option value="closed">Closed</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Priority</label>
                                            <select name="priority">
                                                <option value="low">Low</option>
                                                <option value="medium">Medium</option>
                                                <option value="high">High</option>
                                                <option value="urgent">Urgent</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Notes *</label>
                                            <textarea name="notes" rows="4" placeholder="Log notes..." required></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Drive Link</label>
                                            <input type="url" name="drive_link" placeholder="https://drive.google.com/...">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-save"></i> Save Log
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="detail-form-section hidden" id="newTaskForm">
                                <h4><i class="fas fa-tasks"></i> New Task</h4>
                                <form onsubmit="createNewTask(event)">
                                    <input type="hidden" name="person_id" id="taskPersonId">
                                    <div class="form-group">
                                        <label>Contact</label>
                                        <select id="taskContactSelect" onchange="document.getElementById('taskPersonId').value = this.value">
                                            <option value="">-- Select Contact --</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Title *</label>
                                        <input type="text" name="title" placeholder="Task title..." required>
                                    </div>
                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description" rows="3" placeholder="Task details..."></textarea>
                                    </div>
                                    <div class="form-row-2">
                                        <div class="form-group">
                                            <label>Assign To</label>
                                            <select name="assigned_to">
                                                <?php while ($a = $agents->fetch_assoc()): ?>
                                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Priority</label>
                                            <select name="priority">
                                                <option value="low">Low</option>
                                                <option value="medium" selected>Medium</option>
                                                <option value="high">High</option>
                                                <option value="urgent">Urgent</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Due Date</label>
                                        <input type="datetime-local" name="due_date">
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-save"></i> Create Task
                                    </button>
                                </form>
                            </div>
                            
                            <div class="detail-item-view hidden" id="itemDetailView">
                                <div class="detail-section">
                                    <div class="detail-header-row">
                                        <h4 id="itemDetailTitle"><i class="fas fa-info-circle"></i> Item Details</h4>
                                        <button class="btn-icon" onclick="closeItemDetail()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div id="itemDetailContent"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php elseif ($tab === 'faqs'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-question-circle"></i> FAQs</h1>
                    <button class="btn btn-primary" onclick="openFaqModal()">
                        <i class="fas fa-plus"></i> Add FAQ
                    </button>
                </div>
                <div class="faq-list">
                    <?php
                    $faqs = $conn->query("SELECT f.*, a.name as created_by_name FROM faqs f LEFT JOIN agents a ON f.created_by = a.id ORDER BY f.usage_count DESC");
                    while ($faq = $faqs->fetch_assoc()):
                    ?>
                    <div class="faq-item">
                        <div class="faq-question">
                            <i class="fas fa-question"></i>
                            <?= htmlspecialchars($faq['question']) ?>
                            <?php if ($faq['category']): ?>
                            <span class="faq-category"><?= htmlspecialchars($faq['category']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="faq-answer">
                            <i class="fas fa-answer"></i>
                            <?= htmlspecialchars($faq['answer']) ?>
                        </div>
                        <div class="faq-meta">
                            <span><i class="fas fa-chart-bar"></i> Used <?= $faq['usage_count'] ?> times</span>
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($faq['created_by_name'] ?? 'System') ?></span>
                            <button class="btn-icon" onclick="useFaq(<?= $faq['id'] ?>)"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php elseif ($tab === 'agents' && $is_admin): ?>
                <div class="page-header">
                    <h1><i class="fas fa-users-cog"></i> Agents</h1>
                    <button class="btn btn-primary" onclick="openAgentModal()">
                        <i class="fas fa-plus"></i> Add Agent
                    </button>
                </div>
                <div class="card-grid">
                    <?php
                    $agents_list = $conn->query("SELECT a.*, u.username FROM agents a JOIN users u ON a.user_id = u.id");
                    while ($agent = $agents_list->fetch_assoc()):
                        $agent_calls = $conn->query("SELECT COUNT(*) as c FROM calls WHERE agent_id = {$agent['id']}")->fetch_assoc()['c'];
                        $agent_logs = $conn->query("SELECT COUNT(*) as c FROM logs WHERE agent_id = {$agent['id']}")->fetch_assoc()['c'];
                    ?>
                    <div class="card agent-card">
                        <div class="card-header">
                            <div class="avatar lg"><?= strtoupper(substr($agent['name'], 0, 1)) ?></div>
                            <div>
                                <h3><?= htmlspecialchars($agent['name']) ?></h3>
                                <p>@<?= htmlspecialchars($agent['username']) ?></p>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="stat-row">
                                <span><i class="fas fa-phone"></i> <?= $agent_calls ?> calls</span>
                                <span><i class="fas fa-comment"></i> <?= $agent_logs ?> logs</span>
                            </div>
                            <div class="info-row">
                                <span><i class="fas fa-hashtag"></i> Ext: <?= htmlspecialchars($agent['extension'] ?? '-') ?></span>
                                <span><i class="fas fa-phone-alt"></i> <?= htmlspecialchars($agent['phone_number'] ?? '-') ?></span>
                            </div>
                            <div class="info-row">
                                <span><i class="fas fa-building"></i> <?= htmlspecialchars($agent['department'] ?? '-') ?></span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-sm" onclick="editAgent(<?= $agent['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
                            <span class="badge <?= $agent['status'] === 'active' ? 'success' : 'muted' ?>"><?= ucfirst($agent['status']) ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php elseif ($tab === 'settings' && $is_admin): ?>
                <div class="page-header">
                    <h1><i class="fas fa-cog"></i> Settings</h1>
                </div>
                <div class="settings-sections">
                    <div class="settings-card">
                        <h3><i class="fas fa-server"></i> PBX Configuration</h3>
                        <form id="pbxSettingsForm" onsubmit="savePbxSettings(event)">
                            <div class="form-group">
                                <label>PBX Username</label>
                                <input type="text" id="pbxUsername" placeholder="PBX portal username">
                            </div>
                            <div class="form-group">
                                <label>PBX Password</label>
                                <input type="password" id="pbxPassword" placeholder="PBX portal password">
                            </div>
                            <div class="form-group">
                                <label>Auto Fetch</label>
                                <label class="switch">
                                    <input type="checkbox" id="autoFetch">
                                    <span class="slider"></span>
                                </label>
                                <small>Automatically fetch calls from PBX</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                    <div class="settings-card">
                        <h3><i class="fas fa-building"></i> Company</h3>
                        <form id="companyForm" onsubmit="saveCompanySettings(event)">
                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" id="companyName" placeholder="Your Company Name">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </form>
                    </div>
                    <div class="settings-card">
                        <h3><i class="fas fa-lock"></i> Change Password</h3>
                        <form id="passwordForm" onsubmit="changePassword(event)">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" id="currentPass" required>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" id="newPass" required>
                            </div>
                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" id="confirmPass" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
                <?php elseif ($tab === 'profile'): ?>
                <div class="page-header">
                    <h1><i class="fas fa-user"></i> Profile</h1>
                </div>
                <div class="profile-section">
                    <div class="profile-avatar">
                        <div class="avatar xl"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                        <h2><?= htmlspecialchars($user['username']) ?></h2>
                        <span class="badge <?= $is_admin ? 'accent' : 'primary' ?>"><?= ucfirst($user['role']) ?></span>
                    </div>
                    <div class="profile-stats">
                        <div class="stat-card">
                            <i class="fas fa-phone"></i>
                            <span class="value"><?= $my_stats['calls_today'] ?></span>
                            <span class="label">Today's Calls</span>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="value"><?= $my_stats['logs_open'] ?></span>
                            <span class="label">Open Logs</span>
                        </div>
                        <div class="stat-card">
                            <i class="fas fa-tasks"></i>
                            <span class="value"><?= $my_stats['tasks_pending'] ?></span>
                            <span class="label">Pending Tasks</span>
                        </div>
                    </div>
                </div>
                <?php elseif ($tab === 'tags' && $is_admin): ?>
                <div class="page-header">
                    <h1><i class="fas fa-tags"></i> Call Tags</h1>
                    <button class="btn btn-primary" onclick="openTagModal()">
                        <i class="fas fa-plus"></i> Add Tag
                    </button>
                </div>
                <div class="tag-list">
                    <?php
                    $tags_list = $conn->query("SELECT * FROM tags ORDER BY name");
                    while ($tag = $tags_list->fetch_assoc()):
                    ?>
                    <div class="tag-item" style="--tag-color: <?= htmlspecialchars($tag['color']) ?>">
                        <span class="tag-name"><?= htmlspecialchars($tag['name']) ?></span>
                        <div class="tag-actions">
                            <button class="btn-icon" onclick="editTag(<?= $tag['id'] ?>)"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon text-danger" onclick="deleteTag(<?= $tag['id'] ?>)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <div class="floating-panel" id="floatingPanel">
        <div class="floating-header">
            <h3 id="floatingTitle">Panel</h3>
            <button class="close-btn" onclick="closeFloatingPanel()"><i class="fas fa-times"></i></button>
        </div>
        <div class="floating-content" id="floatingContent"></div>
    </div>
    
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Modal</h3>
                <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>
    
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
    let currentTab = '<?= $initial_type ?>';
    let selectedItem = null;
    let selectedContact = null;
    let sidebarOpen = true;
    
    function toggleSidebar() {
        sidebarOpen = !sidebarOpen;
        document.getElementById('sidebar').classList.toggle('collapsed', !sidebarOpen);
        document.querySelector('.main-content').classList.toggle('sidebar-collapsed', !sidebarOpen);
    }
    
    function toggleNotifications() {
        document.getElementById('notificationsDropdown').classList.toggle('show');
    }
    
    function toggleUserMenu() {
        document.getElementById('userDropdown').classList.toggle('show');
    }
    
    function switchTab(tab) {
        currentTab = tab;
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });
        loadResults();
    }
    
    function loadResults() {
        const list = document.getElementById('resultList');
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        const type = document.getElementById('filterType').value;
        const group = document.getElementById('filterGroup').value;
        const intExt = document.getElementById('filterIntExt').value;
        const q = document.getElementById('globalSearch').value;
        
        const params = new URLSearchParams({
            action: 'search_' + currentTab,
            date_from: dateFrom,
            date_to: dateTo,
            type: type,
            group: group,
            int_ext: intExt,
            q: q
        });
        
        fetch('api/search.php?' + params.toString())
            .then(r => r.json())
            .then(data => {
                if (data.length === 0) {
                    list.innerHTML = '<div class="empty-state"><i class="fas fa-search"></i><p>No results found</p></div>';
                    return;
                }
                list.innerHTML = data.map(item => renderResultItem(item)).join('');
            });
    }
    
    function renderResultItem(item) {
        const icon = currentTab === 'contacts' ? 'user' : (currentTab === 'calls' ? 'phone' : (currentTab === 'logs' ? 'comment' : 'tasks'));
        const sub = currentTab === 'contacts' ? item.phone : (currentTab === 'calls' ? (item.direction + ' - ' + item.status) : item.notes?.substring(0, 50));
        return `
            <div class="result-item" onclick="selectItem('${currentTab}', ${item.id})">
                <div class="result-icon"><i class="fas fa-${icon}"></i></div>
                <div class="result-info">
                    <strong>${item.name || item.phone || item.title || 'Item'}</strong>
                    <span>${sub || ''}</span>
                </div>
                <i class="fas fa-chevron-right"></i>
            </div>
        `;
    }
    
    function selectItem(type, id) {
        selectedItem = { type, id };
        
        document.querySelectorAll('.result-item').forEach(el => el.classList.remove('active'));
        event.target.closest('.result-item')?.classList.add('active');
        
        fetch('api/search.php?action=get_' + type + '&id=' + id)
            .then(r => r.json())
            .then(data => {
                if (type === 'contacts') {
                    renderContactInfo(data);
                    selectedContact = data;
                }
                renderDetails(type, data);
            });
    }
    
    function switchCol1Tab(tab) {
        document.querySelectorAll('.col1-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.innerHTML.includes(tab === 'search' ? 'Search' : 'Recent'));
        });
        document.getElementById('col1Search').classList.toggle('hidden', tab !== 'search');
        document.getElementById('col1Activity').classList.toggle('hidden', tab !== 'activity');
    }
    
    function switchDetailTab(tab) {
        if (tab === 'log' && !selectedContact) {
            showToast('Select a contact first');
            return;
        }
        
        document.querySelectorAll('.detail-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.innerHTML.includes(tab === 'call' ? 'Call' : (tab === 'log' ? 'Log' : 'Task')));
        });
        document.querySelectorAll('.detail-form-section').forEach(el => el.classList.add('hidden'));
        
        if (tab === 'call') {
            document.getElementById('newCallForm').classList.remove('hidden');
            initCallForm();
        } else if (tab === 'log') {
            document.getElementById('newLogForm').classList.remove('hidden');
            initLogForm();
        } else if (tab === 'task') {
            document.getElementById('newTaskForm').classList.remove('hidden');
            initTaskForm();
        }
    }
    
    function toggleNewPhone() {
        const useNew = document.getElementById('useNewPhone').checked;
        const phoneGroup = document.getElementById('phoneInputGroup');
        const phoneInput = document.getElementById('newCallPhone');
        
        if (useNew) {
            phoneGroup.style.display = 'none';
            phoneInput.removeAttribute('required');
            phoneInput.value = '';
        } else {
            phoneGroup.style.display = 'block';
            phoneInput.setAttribute('required', 'required');
            phoneInput.value = '';
        }
    }
    
    function initCallForm() {
        const useNew = document.getElementById('useNewPhone').checked;
        if (!useNew && selectedContact) {
            document.getElementById('newCallPhone').value = selectedContact.phone || '';
        }
    }
    
    function initLogForm() {
        if (selectedContact) {
            document.getElementById('noContactWarning').style.display = 'none';
            document.getElementById('logFormActual').style.display = 'block';
            document.getElementById('logPersonId').value = selectedContact.id;
            document.getElementById('selectedContactName').textContent = selectedContact.name || selectedContact.phone;
        } else {
            document.getElementById('noContactWarning').style.display = 'flex';
            document.getElementById('logFormActual').style.display = 'none';
        }
    }
    
    function initTaskForm() {
        if (selectedContact) {
            document.getElementById('taskPersonId').value = selectedContact.id;
        } else {
            document.getElementById('taskPersonId').value = '';
        }
    }
    
    function openActivityItem(action, recordId) {
        if (recordId && action) {
            selectItem(action === 'task' ? 'tasks' : (action === 'log' ? 'logs' : (action === 'call' ? 'calls' : 'contacts')), recordId);
        }
    }
    
    function toggleSection(section) {
        const content = document.getElementById(section + 'Content');
        const icon = document.getElementById(section + 'Icon');
        content.classList.toggle('show');
        icon.classList.toggle('rotated');
    }
    
    function switchDetailTab(tab) {
        document.querySelectorAll('.detail-tab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.closest('.detail-tab-btn').classList.add('active');
        
        document.querySelectorAll('.detail-form-section, .detail-item-view').forEach(el => el.classList.add('hidden'));
        
        if (tab === 'call') document.getElementById('newCallForm').classList.remove('hidden');
        else if (tab === 'log') document.getElementById('newLogForm').classList.remove('hidden');
        else if (tab === 'task') document.getElementById('newTaskForm').classList.remove('hidden');
    }
    
    function closeItemDetail() {
        document.querySelectorAll('.detail-form-section').forEach(el => el.classList.remove('hidden'));
        document.getElementById('itemDetailView').classList.add('hidden');
    }
    
    function showItemDetail(type, data) {
        document.querySelectorAll('.detail-form-section').forEach(el => el.classList.add('hidden'));
        document.getElementById('itemDetailView').classList.remove('hidden');
        
        const title = document.getElementById('itemDetailTitle');
        const content = document.getElementById('itemDetailContent');
        
        if (type === 'call') {
            title.innerHTML = '<i class="fas fa-phone"></i> Call Details';
            content.innerHTML = renderCallDetail(data);
        } else if (type === 'log') {
            title.innerHTML = '<i class="fas fa-comment"></i> Log Details';
            content.innerHTML = renderLogDetail(data);
        } else if (type === 'task') {
            title.innerHTML = '<i class="fas fa-tasks"></i> Task Details';
            content.innerHTML = renderTaskDetail(data);
        }
    }
    
    function renderCallDetail(data) {
        return `
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Direction</label>
                    <span class="${data.direction === 'Inbound' ? 'text-success' : 'text-danger'}">${data.direction || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span>${data.status || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Duration</label>
                    <span>${data.duration || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Date/Time</label>
                    <span>${data.start_time || '-'}</span>
                </div>
            </div>
            <div class="mark-section">
                <label>Mark:</label>
                <div class="mark-buttons">
                    <button class="mark-btn ${data.call_mark === 'successful' ? 'active' : ''}" onclick="markCall(${data.id}, 'successful')">
                        <i class="fas fa-check"></i> Success
                    </button>
                    <button class="mark-btn mark-problem ${data.call_mark === 'problem' ? 'active' : ''}" onclick="markCall(${data.id}, 'problem')">
                        <i class="fas fa-exclamation"></i> Problem
                    </button>
                    <button class="mark-btn mark-action ${data.call_mark === 'need_action' ? 'active' : ''}" onclick="markCall(${data.id}, 'need_action')">
                        <i class="fas fa-tasks"></i> Action
                    </button>
                    <button class="mark-btn mark-urgent ${data.call_mark === 'urgent' ? 'active' : ''}" onclick="markCall(${data.id}, 'urgent')">
                        <i class="fas fa-fire"></i> Urgent
                    </button>
                    <button class="mark-btn mark-failed ${data.call_mark === 'failed' ? 'active' : ''}" onclick="markCall(${data.id}, 'failed')">
                        <i class="fas fa-times"></i> Failed
                    </button>
                </div>
            </div>
            <div class="drive-link-section">
                <label>Drive Link</label>
                <div class="input-with-btn">
                    <input type="url" id="driveLink_${data.id}" value="${data.drive_link || ''}" placeholder="https://drive.google.com/...">
                    <button onclick="saveDriveLink(${data.id})"><i class="fas fa-save"></i></button>
                </div>
            </div>
            ${data.recording_url ? `<a href="${data.recording_url}" target="_blank" class="btn btn-secondary"><i class="fas fa-play"></i> Recording</a>` : ''}
            <div class="logs-section">
                <h5><i class="fas fa-comment"></i> Logs for this Call</h5>
                <div class="logs-list" id="callLogs">
                    ${(data.logs || []).map(log => renderLogItem(log)).join('') || '<p class="text-muted">No logs yet</p>'}
                </div>
                <form class="add-log-form compact" onsubmit="addLogToCall(event, ${data.id})">
                    <textarea name="notes" placeholder="Add a log..." required></textarea>
                    <div class="form-row-2">
                        <select name="type">
                            <option value="note">Note</option>
                            <option value="issue">Issue</option>
                            <option value="followup">Follow-up</option>
                            <option value="resolution">Resolution</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button>
                    </div>
                </form>
            </div>
        `;
    }
    
    function renderLogDetail(data) {
        return `
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Type</label>
                    <span class="badge badge-${data.type}">${data.type}</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span class="badge badge-status-${data.log_status}">${data.log_status}</span>
                </div>
                <div class="detail-item">
                    <label>Priority</label>
                    <span class="badge badge-priority-${data.priority}">${data.priority}</span>
                </div>
                <div class="detail-item">
                    <label>Agent</label>
                    <span>${data.agent_name || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Created</label>
                    <span>${data.created_at || '-'}</span>
                </div>
            </div>
            <div class="log-notes-box">
                <label>Notes</label>
                <p>${data.notes || '-'}</p>
            </div>
            ${data.drive_link ? `<div class="drive-link"><a href="${data.drive_link}" target="_blank"><i class="fas fa-link"></i> ${data.drive_link}</a></div>` : ''}
            <div class="replies-section">
                <h5><i class="fas fa-reply"></i> Replies</h5>
                ${data.replies?.length ? data.replies.map(reply => `
                    <div class="reply-item">
                        <div class="reply-header">
                            <strong>${reply.agent_name || 'Agent'}</strong>
                            <span>${reply.created_at || ''}</span>
                        </div>
                        <p>${reply.notes || ''}</p>
                    </div>
                `).join('') : '<p class="text-muted">No replies yet</p>'}
            </div>
            <form class="add-reply-form" onsubmit="addReply(event, ${data.id})">
                <textarea name="notes" placeholder="Write a reply..." required></textarea>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-reply"></i> Reply</button>
            </form>
        `;
    }
    
    function renderTaskDetail(data) {
        return `
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <label>Title</label>
                    <span>${data.title || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span class="badge badge-status-${data.status}">${data.status}</span>
                </div>
                <div class="detail-item">
                    <label>Priority</label>
                    <span class="badge badge-priority-${data.priority}">${data.priority}</span>
                </div>
                <div class="detail-item">
                    <label>Assigned To</label>
                    <span>${data.assigned_to_name || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Due Date</label>
                    <span>${data.due_date || '-'}</span>
                </div>
            </div>
            ${data.description ? `<div class="task-desc"><label>Description</label><p>${data.description}</p></div>` : ''}
            <div class="task-actions">
                <button class="btn btn-success btn-sm" onclick="toggleTask(${data.id}, 'completed')">
                    <i class="fas fa-check"></i> Complete
                </button>
                <button class="btn btn-secondary btn-sm" onclick="toggleTask(${data.id}, 'in_progress')">
                    <i class="fas fa-play"></i> In Progress
                </button>
                <button class="btn btn-secondary btn-sm" onclick="toggleTask(${data.id}, 'pending')">
                    <i class="fas fa-clock"></i> Pending
                </button>
            </div>
        `;
    }
    
    let currentContactCalls = [];
    let currentContactLogs = [];
    let currentContactTasks = [];
    let currentContactTab = 'calls';
    
    function renderContactInfo(contact) {
        const el = document.getElementById('contactInfo');
        selectedContact = contact;
        
        document.getElementById('logPersonId').value = contact.id;
        document.getElementById('taskPersonId').value = contact.id;
        
        fetch('api/search.php?action=get_contact_calls&id=' + contact.id)
            .then(r => r.json())
            .then(calls => { currentContactCalls = calls; renderContactTabs(); });
        
        fetch('api/search.php?action=get_contact_logs&id=' + contact.id)
            .then(r => r.json())
            .then(logs => { currentContactLogs = logs; renderContactTabs(); });
        
        fetch('api/search.php?action=get_contact_tasks&id=' + contact.id)
            .then(r => r.json())
            .then(tasks => { currentContactTasks = tasks; renderContactTabs(); });
        
        el.innerHTML = `
            <div class="contact-header">
                <div class="avatar lg">${(contact.name || contact.phone || 'U')[0].toUpperCase()}</div>
                <div class="contact-main">
                    <h3>${contact.name || 'Unknown'}</h3>
                    <p><i class="fas fa-phone"></i> ${contact.phone}</p>
                </div>
                <button class="btn-icon ${contact.is_favorite ? 'active' : ''}" onclick="toggleFavorite(${contact.id})">
                    <i class="fas fa-star"></i>
                </button>
            </div>
            
            <div class="collapsible-section">
                <button class="collapsible-header" onclick="toggleSection('contactEdit')">
                    <span><i class="fas fa-edit"></i> Edit Contact</span>
                    <i class="fas fa-chevron-down" id="contactEditIcon"></i>
                </button>
                <div class="collapsible-content" id="contactEditContent">
                    <form id="contactEditForm" class="contact-form">
                        <input type="hidden" name="id" value="${contact.id}">
                        <div class="form-row">
                            <label>Name</label>
                            <input type="text" name="name" value="${contact.name || ''}">
                        </div>
                        <div class="form-row">
                            <label>Phone</label>
                            <input type="text" value="${contact.phone || ''}" readonly>
                        </div>
                        <div class="form-row">
                            <label>Type</label>
                            <select name="type">
                                <option value="">Select Type</option>
                                <?php $types->data_seek(0); while ($t = $types->fetch_assoc()): ?>
                                <option value="<?= $t['name'] ?>" ${contact.type === '<?= $t['name'] ?>' ? 'selected' : ''}><?= $t['name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Group</label>
                            <select name="group_id">
                                <option value="0">No Group</option>
                                <?php $groups->data_seek(0); while ($g = $groups->fetch_assoc()): ?>
                                <option value="<?= $g['id'] ?>" ${contact.group_id == <?= $g['id'] ?> ? 'selected' : ''}><?= $g['name'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Internal/External</label>
                            <select name="internal_external">
                                <option value="">Not Set</option>
                                <option value="internal" ${contact.internal_external === 'internal' ? 'selected' : ''}>Internal</option>
                                <option value="external" ${contact.internal_external === 'external' ? 'selected' : ''}>External</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Company</label>
                            <input type="text" name="company" value="${contact.company || ''}">
                        </div>
                        <div class="form-row">
                            <label>Email</label>
                            <input type="email" name="email" value="${contact.email || ''}">
                        </div>
                        <div class="form-row">
                            <label>Address</label>
                            <textarea name="address" rows="2">${contact.address || ''}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Save
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="collapsible-section">
                <button class="collapsible-header" onclick="toggleSection('contactSummary')">
                    <span><i class="fas fa-chart-bar"></i> Summary</span>
                    <i class="fas fa-chevron-down" id="contactSummaryIcon"></i>
                </button>
                <div class="collapsible-content" id="contactSummaryContent">
                    <div class="contact-stats">
                        <div class="mini-stat">
                            <span class="value">${contact.total_calls || 0}</span>
                            <span class="label">Calls</span>
                        </div>
                        <div class="mini-stat">
                            <span class="value">${contact.total_talk_time || 0}</span>
                            <span class="label">Talk (s)</span>
                        </div>
                        <div class="mini-stat">
                            <span class="value">${contact.logs_count || 0}</span>
                            <span class="label">Logs</span>
                        </div>
                        <div class="mini-stat">
                            <span class="value">${contact.tasks_count || 0}</span>
                            <span class="label">Tasks</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="contact-items-tabs">
                <button class="contact-tab-btn active" onclick="switchContactTab('calls')">
                    <i class="fas fa-phone"></i> Calls
                </button>
                <button class="contact-tab-btn" onclick="switchContactTab('logs')">
                    <i class="fas fa-comment"></i> Logs
                </button>
                <button class="contact-tab-btn" onclick="switchContactTab('tasks')">
                    <i class="fas fa-tasks"></i> Tasks
                </button>
            </div>
            
            <div class="contact-items-list" id="contactItemsList"></div>
        `;
        
        document.getElementById('contactEditForm').addEventListener('submit', saveContact);
        
        loadContactsForSelects();
    }
    
    function switchContactTab(tab) {
        currentContactTab = tab;
        document.querySelectorAll('.contact-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.innerHTML.includes(tab.charAt(0).toUpperCase() + tab.slice(1)));
        });
        renderContactTabs();
    }
    
    function renderContactTabs() {
        const list = document.getElementById('contactItemsList');
        let items = [];
        let icon = '';
        
        if (currentContactTab === 'calls') {
            items = currentContactCalls;
            icon = 'fa-phone';
        } else if (currentContactTab === 'logs') {
            items = currentContactLogs;
            icon = 'fa-comment';
        } else {
            items = currentContactTasks;
            icon = 'fa-tasks';
        }
        
        if (items.length === 0) {
            list.innerHTML = '<div class="empty-state-sm"><p>No ' + currentContactTab + ' found</p></div>';
            return;
        }
        
        list.innerHTML = items.map(item => {
            let title = '';
            let sub = '';
            
            if (currentContactTab === 'calls') {
                title = item.direction + ' - ' + item.status;
                sub = item.start_time + ' | ' + item.duration;
            } else if (currentContactTab === 'logs') {
                title = item.type + ' (' + item.log_status + ')';
                sub = (item.notes || '').substring(0, 50) + '...';
            } else {
                title = item.title;
                sub = item.status + ' | ' + (item.due_date || 'No due');
            }
            
            return `
                <div class="contact-item" onclick="showItemDetail('${currentContactTab}', ${JSON.stringify(item).replace(/"/g, '&quot;')})">
                    <i class="fas ${icon}"></i>
                    <div class="item-info">
                        <strong>${title}</strong>
                        <span>${sub}</span>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </div>
            `;
        }).join('');
    }
    
    function loadContactsForSelects() {
        fetch('api/persons.php?action=list')
            .then(r => r.json())
            .then(data => {
                const options = data.map(p => `<option value="${p.id}" data-phone="${p.phone}">${p.name || p.phone}</option>`).join('');
                document.getElementById('newCallContact').innerHTML = '<option value="">-- Select Contact --</option>' + options;
                document.getElementById('logContactSelect').innerHTML = '<option value="">-- Select Contact --</option>' + options;
                document.getElementById('taskContactSelect').innerHTML = '<option value="">-- Select Contact --</option>' + options;
            });
    }
    
    function renderDetails(type, data) {
        const content = document.getElementById('detailsContent');
        document.getElementById('detailsTitle').innerHTML = '<i class="fas fa-info-circle"></i> ' + type.charAt(0).toUpperCase() + type.slice(1) + ' Details';
        
        if (type === 'calls') {
            content.innerHTML = `
                <div class="detail-section">
                    <h4><i class="fas fa-phone"></i> Call Info</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Direction</label>
                            <span class="${data.direction === 'Inbound' ? 'text-success' : 'text-danger'}">
                                <i class="fas fa-${data.direction === 'Inbound' ? 'arrow-down' : 'arrow-up'}"></i> ${data.direction}
                            </span>
                        </div>
                        <div class="detail-item">
                            <label>Status</label>
                            <span>${data.status || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Duration</label>
                            <span>${data.duration || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Talk Time</label>
                            <span>${data.talk_time || 0}s</span>
                        </div>
                        <div class="detail-item">
                            <label>Date/Time</label>
                            <span>${data.start_time || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Extension</label>
                            <span>${data.extension || '-'}</span>
                        </div>
                    </div>
                    <div class="mark-section">
                        <label>Mark as:</label>
                        <div class="mark-buttons">
                            <button class="mark-btn ${data.call_mark === 'successful' ? 'active' : ''}" onclick="markCall(${data.id}, 'successful')">
                                <i class="fas fa-check"></i> Success
                            </button>
                            <button class="mark-btn mark-problem ${data.call_mark === 'problem' ? 'active' : ''}" onclick="markCall(${data.id}, 'problem')">
                                <i class="fas fa-exclamation"></i> Problem
                            </button>
                            <button class="mark-btn mark-action ${data.call_mark === 'need_action' ? 'active' : ''}" onclick="markCall(${data.id}, 'need_action')">
                                <i class="fas fa-tasks"></i> Action
                            </button>
                            <button class="mark-btn mark-urgent ${data.call_mark === 'urgent' ? 'active' : ''}" onclick="markCall(${data.id}, 'urgent')">
                                <i class="fas fa-fire"></i> Urgent
                            </button>
                            <button class="mark-btn mark-failed ${data.call_mark === 'failed' ? 'active' : ''}" onclick="markCall(${data.id}, 'failed')">
                                <i class="fas fa-times"></i> Failed
                            </button>
                        </div>
                    </div>
                    <div class="drive-link-section">
                        <label>Drive Link</label>
                        <div class="input-with-btn">
                            <input type="url" id="driveLink_${data.id}" value="${data.drive_link || ''}" placeholder="https://drive.google.com/...">
                            <button onclick="saveDriveLink(${data.id})"><i class="fas fa-save"></i></button>
                        </div>
                    </div>
                    ${data.recording_url ? `<a href="${data.recording_url}" target="_blank" class="btn btn-secondary"><i class="fas fa-play"></i> Play Recording</a>` : ''}
                </div>
                <div class="detail-section">
                    <h4><i class="fas fa-comment"></i> Logs</h4>
                    <div class="logs-list" id="callLogs">
                        ${(data.logs || []).map(log => renderLogItem(log)).join('') || '<p class="text-muted">No logs yet</p>'}
                    </div>
                    <form class="add-log-form" onsubmit="addLogToCall(event, ${data.id})">
                        <textarea name="notes" placeholder="Add a note..." required></textarea>
                        <select name="type">
                            <option value="note">Note</option>
                            <option value="issue">Issue</option>
                            <option value="followup">Follow-up</option>
                            <option value="resolution">Resolution</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Log</button>
                    </form>
                </div>
            `;
        } else if (type === 'logs') {
            content.innerHTML = `
                <div class="detail-section">
                    <h4><i class="fas fa-comment"></i> Log Details</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Type</label>
                            <span class="badge badge-${data.type}">${data.type}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status</label>
                            <span class="badge badge-status-${data.log_status}">${data.log_status}</span>
                        </div>
                        <div class="detail-item">
                            <label>Priority</label>
                            <span class="badge badge-priority-${data.priority}">${data.priority}</span>
                        </div>
                        <div class="detail-item">
                            <label>Agent</label>
                            <span>${data.agent_name || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Created</label>
                            <span>${data.created_at || '-'}</span>
                        </div>
                    </div>
                    <div class="log-notes">
                        <label>Notes</label>
                        <p>${data.notes || '-'}</p>
                    </div>
                    ${data.drive_link ? `<div class="drive-link"><label>Drive Link</label><a href="${data.drive_link}" target="_blank">${data.drive_link}</a></div>` : ''}
                </div>
                ${data.replies?.length ? `
                <div class="detail-section">
                    <h4><i class="fas fa-reply"></i> Replies</h4>
                    <div class="replies-list">
                        ${data.replies.map(reply => `
                            <div class="reply-item">
                                <div class="reply-header">
                                    <strong>${reply.agent_name || 'Agent'}</strong>
                                    <span>${reply.created_at || ''}</span>
                                </div>
                                <p>${reply.notes || ''}</p>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                <div class="detail-section">
                    <h4><i class="fas fa-reply"></i> Add Reply</h4>
                    <form onsubmit="addReply(event, ${data.id})">
                        <textarea name="notes" placeholder="Your reply..." required></textarea>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-reply"></i> Reply</button>
                    </form>
                </div>
            `;
        } else if (type === 'tasks') {
            content.innerHTML = `
                <div class="detail-section">
                    <h4><i class="fas fa-tasks"></i> Task Details</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Title</label>
                            <span>${data.title || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status</label>
                            <span class="badge badge-status-${data.status}">${data.status}</span>
                        </div>
                        <div class="detail-item">
                            <label>Priority</label>
                            <span class="badge badge-priority-${data.priority}">${data.priority}</span>
                        </div>
                        <div class="detail-item">
                            <label>Assigned To</label>
                            <span>${data.assigned_to_name || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Due Date</label>
                            <span>${data.due_date || '-'}</span>
                        </div>
                    </div>
                    ${data.description ? `<div class="task-desc"><label>Description</label><p>${data.description}</p></div>` : ''}
                    <div class="task-actions">
                        <button class="btn btn-success btn-sm" onclick="toggleTask(${data.id}, 'completed')">
                            <i class="fas fa-check"></i> Mark Complete
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="toggleTask(${data.id}, 'in_progress')">
                            <i class="fas fa-play"></i> In Progress
                        </button>
                    </div>
                </div>
            `;
        }
    }
    
    function renderLogItem(log) {
        return `
            <div class="log-item">
                <div class="log-header">
                    <span class="log-agent">${log.agent_name || 'Agent'}</span>
                    <span class="log-type badge badge-${log.type}">${log.type}</span>
                    <span class="log-status badge badge-status-${log.log_status}">${log.log_status}</span>
                </div>
                <p class="log-notes">${log.notes || ''}</p>
                <div class="log-footer">
                    <span class="log-time">${log.created_at || ''}</span>
                    <button class="btn-icon btn-sm" onclick="replyToLog(${log.id})"><i class="fas fa-reply"></i></button>
                </div>
            </div>
        `;
    }
    
    function saveContact(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        
        fetch('api/persons.php?action=update', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Contact updated!' : 'Error');
            if (d.status === 'success') loadResults();
        });
    }
    
    function toggleFavorite(id) {
        fetch('api/persons.php?action=toggle_favorite&id=' + id)
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    const btn = document.querySelector('.contact-header .btn-icon');
                    btn.classList.toggle('active');
                }
            });
    }
    
    function markCall(id, mark) {
        fetch('api/calls.php?action=mark&id=' + id + '&mark=' + mark)
            .then(r => r.json())
            .then(d => {
                showToast(d.status === 'success' ? 'Marked!' : 'Error');
                document.querySelectorAll('.mark-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelector(`.mark-btn[onclick*="${mark}"]`)?.classList.add('active');
            });
    }
    
    function saveDriveLink(id) {
        const link = document.getElementById('driveLink_' + id).value;
        fetch('api/calls.php?action=update_drive_link&id=' + id, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ drive_link: link })
        })
        .then(r => r.json())
        .then(d => showToast(d.status === 'success' ? 'Link saved!' : 'Error'));
    }
    
    function addLogToCall(e, callId) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        data.call_id = callId;
        data.person_id = selectedContact?.id;
        
        fetch('api/logs.php?action=create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Log added!' : 'Error');
            if (d.status === 'success') {
                form.reset();
                selectItem('calls', callId);
            }
        });
    }
    
    function addReply(e, logId) {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(e.target));
        data.parent_id = logId;
        
        fetch('api/logs.php?action=reply', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Reply sent!' : 'Error');
            if (d.status === 'success') {
                e.target.reset();
                selectItem('logs', logId);
            }
        });
    }
    
    function replyToLog(id) {
        const notes = prompt('Enter your reply:');
        if (!notes) return;
        fetch('api/logs.php?action=reply', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ parent_id: id, notes })
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Reply sent!' : 'Error');
            if (selectedItem?.type === 'calls') selectItem('calls', selectedItem.id);
            else selectItem('logs', id);
        });
    }
    
    function toggleTask(id, status) {
        fetch('api/tasks.php?action=update_status&id=' + id + '&status=' + status)
            .then(r => r.json())
            .then(d => {
                showToast(d.status === 'success' ? 'Task updated!' : 'Error');
                if (selectedItem?.type === 'tasks') selectItem('tasks', id);
                loadResults();
            });
    }
    
    function openFloatingPanel(type) {
        const panel = document.getElementById('floatingPanel');
        const title = document.getElementById('floatingTitle');
        const content = document.getElementById('floatingContent');
        
        if (type === 'faq') {
            title.innerHTML = '<i class="fas fa-question"></i> Search FAQs';
            content.innerHTML = `
                <input type="text" id="faqSearch" placeholder="Search FAQs..." oninput="searchFaqs(this.value)">
                <div id="faqResults" class="faq-results"></div>
            `;
        } else if (type === 'task') {
            title.innerHTML = '<i class="fas fa-plus"></i> Create Task';
            content.innerHTML = `
                <form id="quickTaskForm" onsubmit="createQuickTask(event)">
                    <input type="hidden" name="person_id" value="${selectedContact?.id || ''}">
                    <input type="hidden" name="call_id" value="${selectedItem?.type === 'calls' ? selectedItem.id : ''}">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Assign To</label>
                        <select name="assigned_to">
                            <option value="<?= $agent_id ?>">Me</option>
                            <?php while ($a = $agents->fetch_assoc()): ?>
                            <option value="<?= $a['id'] ?>"><?= $a['name'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="datetime-local" name="due_date">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Create Task</button>
                </form>
            `;
        }
        
        panel.classList.add('show');
    }
    
    function closeFloatingPanel() {
        document.getElementById('floatingPanel').classList.remove('show');
    }
    
    function searchFaqs(q) {
        fetch('api/faqs.php?action=search&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                document.getElementById('faqResults').innerHTML = data.map(faq => `
                    <div class="faq-result" onclick="useFaq(${faq.id})">
                        <strong>${faq.question}</strong>
                        <p>${faq.answer}</p>
                    </div>
                `).join('') || '<p class="text-muted">No FAQs found</p>';
            });
    }
    
    function useFaq(id) {
        fetch('api/faqs.php?action=use&id=' + id)
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    showToast('FAQ copied to clipboard!');
                    closeFloatingPanel();
                }
            });
    }
    
    function createQuickTask(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        
        fetch('api/tasks.php?action=create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Task created!' : 'Error');
            if (d.status === 'success') {
                closeFloatingPanel();
                loadResults();
            }
        });
    }
    
    function createNewCall(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        
        const useNewPhone = document.getElementById('useNewPhone').checked;
        
        if (!useNewPhone && selectedContact) {
            data.phone = selectedContact.phone;
            data.person_id = selectedContact.id;
        } else if (!data.phone) {
            showToast('Please enter a phone number');
            return;
        }
        
        fetch('api/calls.php?action=manual', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'success') {
                showToast('Call saved!');
                form.reset();
                
                if (useNewPhone && data.phone) {
                    fetch('api/persons.php?action=search&q=' + encodeURIComponent(data.phone))
                        .then(r => r.json())
                        .then(contacts => {
                            if (contacts.length > 0) {
                                const contact = contacts.find(c => c.phone === data.phone) || contacts[0];
                                fetch('api/search.php?action=get_contact&id=' + contact.id)
                                    .then(r => r.json())
                                    .then(contactData => {
                                        selectedContact = contactData;
                                        renderContactInfo(contactData);
                                        selectItem('contacts', contact.id);
                                    });
                            }
                        });
                }
                
                loadResults();
            } else {
                showToast(d.error || 'Error');
            }
        });
    }
    
    function createNewLog(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        
        fetch('api/logs.php?action=create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Log saved!' : d.error || 'Error');
            if (d.status === 'success') {
                form.reset();
                if (selectedContact) renderContactInfo(selectedContact);
                loadResults();
            }
        });
    }
    
    function createNewTask(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        
        fetch('api/tasks.php?action=create', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Task created!' : d.error || 'Error');
            if (d.status === 'success') {
                form.reset();
                if (selectedContact) renderContactInfo(selectedContact);
                loadResults();
            }
        });
    }
    
    function fetchFromPBX() {
        showToast('Fetching from PBX...');
        fetch('api/fetch_pbx.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({})
        })
        .then(r => r.json())
        .then(d => {
            showToast(d.status === 'success' ? 'Fetched ' + (d.count || 0) + ' calls!' : d.message || 'Error');
            loadResults();
        });
    }
    
    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('show');
    }
    
    function showToast(message) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = '<i class="fas fa-info"></i> ' + message;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    let searchTimeout;
    document.getElementById('globalSearch').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            document.getElementById('filterType').value = '';
            loadResults();
        }, 300);
    });
    
    ['dateFrom', 'dateTo', 'filterType', 'filterGroup', 'filterIntExt'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', loadResults);
    });
    
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('globalSearch').focus();
        }
        if (e.key === 'Escape') {
            closeFloatingPanel();
            closeModal();
        }
    });
    
    document.addEventListener('click', e => {
        if (!e.target.closest('.notifications-dropdown') && !e.target.closest('#notificationBtn')) {
            document.getElementById('notificationsDropdown')?.classList.remove('show');
        }
        if (!e.target.closest('.user-dropdown') && !e.target.closest('.user-btn')) {
            document.getElementById('userDropdown')?.classList.remove('show');
        }
    });
    
    loadResults();
    </script>
</body>
</html>
