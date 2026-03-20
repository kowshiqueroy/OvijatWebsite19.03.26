<?php
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$tab = $_GET['tab'] ?? 'dashboard';

$stats = [];
$stats['total_calls'] = $conn->query("SELECT COUNT(*) as c FROM calls")->fetch_assoc()['c'];
$stats['today_calls'] = $conn->query("SELECT COUNT(*) as c FROM calls WHERE DATE(start_time) = CURDATE()")->fetch_assoc()['c'];
$stats['total_persons'] = $conn->query("SELECT COUNT(*) as c FROM persons")->fetch_assoc()['c'];
$stats['total_logs'] = $conn->query("SELECT COUNT(*) as c FROM logs")->fetch_assoc()['c'];
$stats['open_logs'] = $conn->query("SELECT COUNT(*) as c FROM logs WHERE log_status = 'open'")->fetch_assoc()['c'];
$stats['today_logs'] = $conn->query("SELECT COUNT(*) as c FROM logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'];
$stats['total_agents'] = $conn->query("SELECT COUNT(*) as c FROM agents")->fetch_assoc()['c'];
$stats['missed_calls'] = $conn->query("SELECT COUNT(*) as c FROM calls WHERE status LIKE '%miss%'")->fetch_assoc()['c'];
$stats['pending_tasks'] = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE status = 'pending'")->fetch_assoc()['c'];
$stats['total_faqs'] = $conn->query("SELECT COUNT(*) as c FROM faqs")->fetch_assoc()['c'];
$stats['total_feedback'] = $conn->query("SELECT COUNT(*) as c FROM logs WHERE type = 'feedback'")->fetch_assoc()['c'];

$agents = $conn->query("SELECT a.*, u.username, 
    (SELECT COUNT(*) FROM calls WHERE agent_id = a.id) as call_count,
    (SELECT COUNT(*) FROM logs WHERE agent_id = a.id) as log_count,
    (SELECT COUNT(*) FROM calls WHERE agent_id = a.id AND status LIKE '%miss%') as missed_count
    FROM agents a JOIN users u ON a.user_id = u.id WHERE a.status = 'active'");

$recentCalls = $conn->query("SELECT c.*, p.name as person_name, a.name as agent_name 
    FROM calls c LEFT JOIN persons p ON c.caller_number = p.phone LEFT JOIN agents a ON c.agent_id = a.id 
    ORDER BY c.start_time DESC LIMIT 10");

$recentLogs = $conn->query("SELECT l.*, p.name as person_name, u.username 
    FROM logs l LEFT JOIN persons p ON l.person_id = p.id JOIN users u ON l.agent_id = u.id 
    ORDER BY l.created_at DESC LIMIT 10");

$stmtPbxUser = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'pbx_username'");
$stmtPbxUser->execute();
$savedPbxUser = $stmtPbxUser->get_result()->fetch_assoc()['setting_value'] ?? '';
$stmtPbxPass = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'pbx_password'");
$stmtPbxPass->execute();
$savedPbxPass = $stmtPbxPass->get_result()->fetch_assoc()['setting_value'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - PBX Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/dark.css" rel="stylesheet">
    <style>
        .stat-card { cursor: pointer; transition: transform 0.1s; }
        .stat-card:hover { transform: scale(1.02); }
        .nav-link { white-space: nowrap; }
        @media (max-width: 768px) {
            .navbar-nav { flex-wrap: wrap; gap: 4px; }
            .nav-link { font-size: 0.85rem; padding: 4px 8px !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php"><i class="fas fa-cog me-2"></i>PBX Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto d-flex flex-row flex-wrap">
                    <a class="nav-link px-3 <?= $tab=='dashboard'?'active':'' ?>" href="admin.php?tab=dashboard"><i class="fas fa-home me-1"></i> <span class="d-none d-lg-inline">Dashboard</span></a>
                    <a class="nav-link px-3 <?= $tab=='agents'?'active':'' ?>" href="admin.php?tab=agents"><i class="fas fa-users me-1"></i> <span class="d-none d-lg-inline">Agents</span></a>
                    <a class="nav-link px-3 <?= $tab=='calls'?'active':'' ?>" href="admin.php?tab=calls"><i class="fas fa-phone me-1"></i> <span class="d-none d-lg-inline">Calls</span></a>
                    <a class="nav-link px-3 <?= $tab=='contacts'?'active':'' ?>" href="admin.php?tab=contacts"><i class="fas fa-address-book me-1"></i> <span class="d-none d-lg-inline">Contacts</span></a>
                    <a class="nav-link px-3 <?= $tab=='groups'?'active':'' ?>" href="admin.php?tab=groups"><i class="fas fa-layer-group me-1"></i> <span class="d-none d-lg-inline">Groups</span></a>
                    <a class="nav-link px-3 <?= $tab=='logs'?'active':'' ?>" href="admin.php?tab=logs"><i class="fas fa-clipboard-list me-1"></i> <span class="d-none d-lg-inline">Logs</span></a>
                    <a class="nav-link px-3 <?= $tab=='faqs'?'active':'' ?>" href="admin.php?tab=faqs"><i class="fas fa-question-circle me-1"></i> <span class="d-none d-lg-inline">FAQs</span></a>
                    <a class="nav-link px-3 <?= $tab=='feedback'?'active':'' ?>" href="admin.php?tab=feedback"><i class="fas fa-comment-dots me-1"></i> <span class="d-none d-lg-inline">Feedback</span></a>
                    <a class="nav-link px-3 <?= $tab=='reports'?'active':'' ?>" href="admin.php?tab=reports"><i class="fas fa-chart-bar me-1"></i> <span class="d-none d-lg-inline">Reports</span></a>
                    <a class="nav-link px-3 <?= $tab=='settings'?'active':'' ?>" href="admin.php?tab=settings"><i class="fas fa-cog me-1"></i> <span class="d-none d-lg-inline">Settings</span></a>
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-3">
        
        <?php if ($tab == 'dashboard'): ?>
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-4 col-lg-2" onclick="location.href='admin.php?tab=calls'">
                <div class="stat-card"><div class="stat-card-inner"><div class="number text-primary"><?= $stats['total_calls'] ?></div><div class="text-muted small">Total Calls</div></div><i class="fas fa-phone text-primary"></i></div>
            </div>
            <div class="col-6 col-md-4 col-lg-2" onclick="location.href='admin.php?tab=calls'">
                <div class="stat-card"><div class="stat-card-inner"><div class="number text-success"><?= $stats['today_calls'] ?></div><div class="text-muted small">Today</div></div><i class="fas fa-calendar-day text-success"></i></div>
            </div>
            <div class="col-6 col-md-4 col-lg-2" onclick="location.href='admin.php?tab=calls'">
                <div class="stat-card"><div class="stat-card-inner"><div class="number text-danger"><?= $stats['missed_calls'] ?></div><div class="text-muted small">Missed</div></div><i class="fas fa-phone-slash text-danger"></i></div>
            </div>
            <div class="col-6 col-md-4 col-lg-2" onclick="location.href='admin.php?tab=contacts'">
                <div class="stat-card"><div class="stat-card-inner"><div class="number"><?= $stats['total_persons'] ?></div><div class="text-muted small">Contacts</div></div><i class="fas fa-address-book"></i></div>
            </div>
            <div class="col-6 col-md-4 col-lg-2" onclick="location.href='admin.php?tab=logs'">
                <div class="stat-card"><div class="stat-card-inner"><div class="number text-warning"><?= $stats['open_logs'] ?></div><div class="text-muted small">Open Logs</div></div><i class="fas fa-clipboard text-warning"></i></div>
            </div>
            <div class="col-6 col-md-4 col-lg-2" onclick="location.href='admin.php?tab=agents'">
                <div class="stat-card"><div class="stat-card-inner"><div class="number"><?= $stats['total_agents'] ?></div><div class="text-muted small">Agents</div></div><i class="fas fa-user-tie"></i></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-phone me-2"></i>Recent Calls</h5>
                        <a href="admin.php?tab=calls" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0 table-hover">
                            <thead><tr><th>Time</th><th>Caller</th><th>Extension</th><th>Status</th><th>Agent</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php while ($c = $recentCalls->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d M H:i', strtotime($c['start_time'])) ?></td>
                                    <td><?= $c['person_name'] ?: ($c['caller_name'] ?: $c['caller_number']) ?><br><small class="text-muted"><?= $c['caller_number'] ?></small></td>
                                    <td><?= $c['extension'] ?: '-' ?></td>
                                    <td><span class="badge bg-<?= stripos($c['status'],'answer')!==false?'success':(stripos($c['status'],'miss')!==false?'danger':'secondary') ?>"><?= $c['status'] ?></span></td>
                                    <td><?= $c['agent_name'] ?: '-' ?></td>
                                    <td><?php if ($c['recording_url']): ?><a href="download_recording.php?url=<?= urlencode($c['recording_url']) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a><?php endif; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Recent Logs</h5>
                        <a href="admin.php?tab=logs" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0 table-hover">
                            <thead><tr><th>Time</th><th>Person</th><th>Type</th><th>Status</th><th>Priority</th><th>Agent</th><th>Lock</th></tr></thead>
                            <tbody>
                                <?php while ($l = $recentLogs->fetch_assoc()): ?>
                                <tr>
                                    <td><?= date('d M H:i', strtotime($l['created_at'])) ?></td>
                                    <td><?= $l['person_name'] ?: '<span class="text-muted">Unknown</span>' ?></td>
                                    <td><span class="badge type-<?= $l['type'] ?>"><?= $l['type'] ?></span></td>
                                    <td><span class="badge bg-<?= $l['log_status']=='open'?'primary':($l['log_status']=='closed'?'success':'warning') ?>"><?= $l['log_status'] ?></span></td>
                                    <td><span class="badge priority-<?= $l['priority'] ?>"><?= $l['priority'] ?></span></td>
                                    <td><?= $l['username'] ?></td>
                                    <td><?php if ($l['is_locked']): ?><i class="fas fa-lock text-muted"></i><?php else: ?><i class="fas fa-unlock text-muted"></i><?php endif; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Quick Stats</h5></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr><td>Total Logs</td><td class="text-end"><strong><?= $stats['total_logs'] ?></strong></td></tr>
                            <tr><td>Open Logs</td><td class="text-end"><strong class="text-warning"><?= $stats['open_logs'] ?></strong></td></tr>
                            <tr><td>Today's Logs</td><td class="text-end"><strong><?= $stats['today_logs'] ?></strong></td></tr>
                            <tr><td>Pending Tasks</td><td class="text-end"><strong class="text-warning"><?= $stats['pending_tasks'] ?></strong></td></tr>
                            <tr><td>FAQs</td><td class="text-end"><strong><?= $stats['total_faqs'] ?></strong></td></tr>
                            <tr><td>Feedback</td><td class="text-end"><strong><?= $stats['total_feedback'] ?></strong></td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-users me-2"></i>Top Agents</h5></div>
                    <div class="card-body p-0">
                        <?php
                        $topAgents = $conn->query("SELECT a.name, 
                            (SELECT COUNT(*) FROM calls WHERE agent_id = a.id) as calls,
                            (SELECT COUNT(*) FROM logs WHERE agent_id = a.id) as logs
                            FROM agents a WHERE a.status = 'active' ORDER BY calls DESC LIMIT 5");
                        while ($a = $topAgents->fetch_assoc()): ?>
                        <div class="d-flex justify-content-between p-2 border-bottom">
                            <span><?= $a['name'] ?></span>
                            <span><small class="text-muted"><?= $a['calls'] ?> calls, <?= $a['logs'] ?> logs</small></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'agents'): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Agents (<?= $stats['total_agents'] ?>)</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAgentModal"><i class="fas fa-plus me-1"></i> Add Agent</button>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0 table-hover">
                    <thead><tr><th>Name</th><th>Extension</th><th>Phone</th><th>Department</th><th>Calls</th><th>Missed</th><th>Logs</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while ($a = $agents->fetch_assoc()): ?>
                        <tr>
                            <td><?= $a['name'] ?><br><small class="text-muted">@<?= $a['username'] ?></small></td>
                            <td><span class="badge bg-dark"><?= $a['extension'] ?: '-' ?></span></td>
                            <td><?= $a['phone_number'] ?: '-' ?></td>
                            <td><?= $a['department'] ?: '-' ?></td>
                            <td><?= $a['call_count'] ?></td>
                            <td class="text-danger"><?= $a['missed_count'] ?></td>
                            <td><?= $a['log_count'] ?></td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm" onclick="editAgent(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name'], ENT_QUOTES) ?>', '<?= $a['extension'] ?>', '<?= $a['phone_number'] ?>', '<?= htmlspecialchars($a['department'], ENT_QUOTES) ?>')"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'calls'): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-phone me-2"></i>All Calls</h5>
                <input type="text" id="searchCalls" class="form-control form-control-sm" placeholder="Search..." style="width: 200px;">
            </div>
            <div class="card-body p-0">
                <table class="table mb-0 table-hover" id="callsTable">
                    <thead><tr><th>Date/Time</th><th>Caller</th><th>Phone</th><th>Extension</th><th>Status</th><th>Duration</th><th>Agent</th><th>Recording</th></tr></thead>
                    <tbody>
                        <?php
                        $allCalls = $conn->query("SELECT c.*, p.name as person_name, a.name as agent_name FROM calls c LEFT JOIN persons p ON c.caller_number = p.phone LEFT JOIN agents a ON c.agent_id = a.id ORDER BY c.start_time DESC LIMIT 500");
                        while ($c = $allCalls->fetch_assoc()): ?>
                        <tr data-search="<?= strtolower(($c['person_name'] ?: $c['caller_name'] ?: '').' '.$c['caller_number']) ?>">
                            <td><?= date('d M Y H:i', strtotime($c['start_time'])) ?></td>
                            <td><?= $c['person_name'] ?: ($c['caller_name'] ?: '-') ?></td>
                            <td><?= $c['caller_number'] ?: '-' ?></td>
                            <td><?= $c['extension'] ?: '-' ?></td>
                            <td><span class="badge bg-<?= stripos($c['status'],'answer')!==false?'success':(stripos($c['status'],'miss')!==false?'danger':'secondary') ?>"><?= $c['status'] ?></span></td>
                            <td><?= $c['duration'] ?: '-' ?></td>
                            <td><?= $c['agent_name'] ?: '-' ?></td>
                            <td>
                                <?php if ($c['recording_url']): ?>
                                <a href="download_recording.php?url=<?= urlencode($c['recording_url']) ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
                                <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'contacts'): ?>
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-upload me-2"></i>Import CSV</h5></div>
            <div class="card-body">
                <form id="csvImportForm">
                    <div class="row">
                        <div class="col-md-5">
                            <input type="file" id="csvFile" class="form-control" accept=".csv">
                            <small class="text-muted">Format: phone,name,type,customer,company,email</small>
                        </div>
                        <div class="col-md-3">
                            <select name="default_type" class="form-select">
                                <option value="customer">Default: Customer</option>
                                <option value="staff">Default: Staff</option>
                                <option value="vendor">Default: Vendor</option>
                                <option value="sales">Default: Sales</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i> Import</button>
                        </div>
                        <div class="col-md-2">
                            <a href="sample.csv" class="btn btn-outline-secondary"><i class="fas fa-download me-1"></i>Sample</a>
                        </div>
                    </div>
                </form>
                <div id="importResult" class="mt-2"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-address-book me-2"></i>Contacts (<?= $stats['total_persons'] ?>)</h5>
                <div class="d-flex gap-2">
                    <select id="filterType" class="form-select form-select-sm" style="width:120px">
                        <option value="">All Types</option>
                        <option value="customer">Customer</option>
                        <option value="staff">Staff</option>
                        <option value="vendor">Vendor</option>
                        <option value="sales">Sales</option>
                        <option value="other">Other</option>
                    </select>
                    <select id="filterPersonType" class="form-select form-select-sm" style="width:120px">
                        <option value="">All</option>
                        <option value="internal">Internal</option>
                        <option value="external">External</option>
                    </select>
                    <input type="text" id="searchPersons" class="form-control form-control-sm" placeholder="Search..." style="width: 200px;">
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0 table-hover" id="personsTable">
                    <thead><tr><th>Name</th><th>Phone</th><th>Type</th><th>Person Type</th><th>Group</th><th>Company</th><th>Calls</th><th>Logs</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        $allPersons = $conn->query("SELECT p.*, g.name as group_name, g.color as group_color,
                            (SELECT COUNT(*) FROM calls WHERE caller_number = p.phone) as call_count,
                            (SELECT COUNT(*) FROM logs WHERE person_id = p.id) as log_count
                            FROM persons p LEFT JOIN contact_groups g ON p.group_id = g.id WHERE p.status = 'active' ORDER BY call_count DESC LIMIT 200");
                        while ($p = $allPersons->fetch_assoc()): ?>
                        <tr data-search="<?= strtolower($p['name'].' '.$p['phone']) ?>" data-type="<?= $p['type'] ?>" data-person-type="<?= $p['person_type'] ?>">
                            <td><?= $p['name'] ?: '<span class="text-muted">Unknown</span>' ?></td>
                            <td><?= $p['phone'] ?></td>
                            <td><span class="badge badge-<?= $p['type'] ?>"><?= ucfirst($p['type']) ?></span></td>
                            <td><span class="badge bg-<?= $p['person_type'] === 'internal' ? 'info' : 'secondary' ?>"><?= $p['person_type'] ?></span></td>
                            <td><?php if ($p['group_name']): ?><span class="badge" style="background:<?= $p['group_color'] ?>"><?= $p['group_name'] ?></span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                            <td><?= $p['company'] ?: '-' ?></td>
                            <td><?= $p['call_count'] ?></td>
                            <td><?= $p['log_count'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewPerson(<?= $p['id'] ?>)"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deletePerson(<?= $p['id'] ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'groups'): ?>
        <div class="row">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add Group</h5></div>
                    <div class="card-body">
                        <form id="addGroupForm">
                            <div class="mb-3">
                                <label class="form-label">Group Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Color</label>
                                <input type="color" name="color" class="form-control form-control-color" value="#6366f1">
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-1"></i> Save Group</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Contact Groups</h5></div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead><tr><th>Name</th><th>Color</th><th>Contacts</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php
                                $groups = $conn->query("SELECT g.*, (SELECT COUNT(*) FROM persons WHERE group_id = g.id) as contact_count FROM contact_groups g ORDER BY g.name");
                                while ($g = $groups->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $g['name'] ?></td>
                                    <td><span class="badge" style="background:<?= $g['color'] ?>"><?= $g['color'] ?></span></td>
                                    <td><?= $g['contact_count'] ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="editGroup(<?= $g['id'] ?>, '<?= htmlspecialchars($g['name'], ENT_QUOTES) ?>', '<?= $g['color'] ?>')"><i class="fas fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteGroup(<?= $g['id'] ?>)"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'tasks'): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>All Tasks</h5>
                <select id="filterTaskStatus" class="form-select form-select-sm" style="width:150px">
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0 table-hover" id="tasksTable">
                    <thead><tr><th>Task</th><th>Person</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php
                        $allTasks = $conn->query("SELECT t.*, p.name as person_name, a.name as agent_name, a2.name as assigned_to_name 
                            FROM tasks t 
                            LEFT JOIN persons p ON t.person_id = p.id 
                            LEFT JOIN agents a ON t.assigned_by = a.id 
                            LEFT JOIN agents a2 ON t.assigned_to = a2.id 
                            ORDER BY t.status ASC, t.due_date ASC LIMIT 100");
                        while ($t = $allTasks->fetch_assoc()): ?>
                        <tr data-status="<?= $t['status'] ?>">
                            <td><?= $t['title'] ?></td>
                            <td><?= $t['person_name'] ?: '-' ?></td>
                            <td><?= $t['assigned_to_name'] ?: $t['agent_name'] ?: '-' ?></td>
                            <td><span class="badge priority-<?= $t['priority'] ?>"><?= $t['priority'] ?></span></td>
                            <td><?= $t['due_date'] ? date('d M H:i', strtotime($t['due_date'])) : '-' ?></td>
                            <td><span class="badge bg-<?= $t['status']=='completed'?'success':($t['status']=='pending'?'warning':'info') ?>"><?= $t['status'] ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'logs'): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>All Logs (<?= $stats['total_logs'] ?>)</h5>
                <div class="d-flex gap-2">
                    <select id="filterLogStatus" class="form-select form-select-sm" style="width:120px">
                        <option value="">All Status</option>
                        <option value="open">Open</option>
                        <option value="followup">Follow-up</option>
                        <option value="pending">Pending</option>
                        <option value="closed">Closed</option>
                    </select>
                    <select id="filterLogType" class="form-select form-select-sm" style="width:120px">
                        <option value="">All Types</option>
                        <option value="note">Note</option>
                        <option value="issue">Issue</option>
                        <option value="followup">Follow-up</option>
                        <option value="resolution">Resolution</option>
                        <option value="feedback">Feedback</option>
                        <option value="query">Query</option>
                        <option value="reply">Reply</option>
                    </select>
                    <input type="text" id="searchLogs" class="form-control form-control-sm" placeholder="Search notes..." style="width: 200px;">
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0 table-hover" id="logsTable">
                    <thead><tr><th>Time</th><th>Person</th><th>Type</th><th>Status</th><th>Priority</th><th>Notes</th><th>Agent</th><th>Lock</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        $allLogs = $conn->query("SELECT l.*, p.name as person_name, u.username FROM logs l LEFT JOIN persons p ON l.person_id = p.id JOIN users u ON l.agent_id = u.id ORDER BY l.created_at DESC LIMIT 200");
                        while ($l = $allLogs->fetch_assoc()): ?>
                        <tr data-status="<?= $l['log_status'] ?>" data-type="<?= $l['type'] ?>" data-notes="<?= strtolower($l['notes']) ?>">
                            <td><?= date('d M H:i', strtotime($l['created_at'])) ?></td>
                            <td><?= $l['person_name'] ?: '<span class="text-muted">Unknown</span>' ?></td>
                            <td><span class="badge type-<?= $l['type'] ?>"><?= $l['type'] ?></span></td>
                            <td><span class="badge bg-<?= $l['log_status']=='open'?'primary':($l['log_status']=='closed'?'success':'warning') ?>"><?= $l['log_status'] ?></span></td>
                            <td><span class="badge priority-<?= $l['priority'] ?>"><?= $l['priority'] ?></span></td>
                            <td style="max-width:200px;"><small class="text-truncate d-block"><?= substr($l['notes'], 0, 50) ?></small></td>
                            <td><?= $l['username'] ?></td>
                            <td><?php if ($l['is_locked']): ?><i class="fas fa-lock text-success" title="Locked by <?= $l['locked_by'] ?>"></i><?php else: ?><i class="fas fa-unlock text-muted"></i><?php endif; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="toggleLock(<?= $l['id'] ?>, <?= $l['is_locked'] ?>)">
                                    <i class="fas fa-<?= $l['is_locked'] ? 'unlock' : 'lock' ?>"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info" onclick="convertLogToFaq(<?= $l['id'] ?>)"><i class="fas fa-lightbulb"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'faqs'): ?>
        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add FAQ / Solution</h5></div>
            <div class="card-body">
                <form id="addFaqForm">
                    <div class="row">
                        <div class="col-md-5 mb-2">
                            <input type="text" name="question" class="form-control" placeholder="Question / Issue" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <input type="text" name="category" class="form-control" placeholder="Category (e.g., Billing)">
                        </div>
                        <div class="col-md-4 mb-2">
                            <input type="text" name="tags" class="form-control" placeholder="Tags (comma separated)">
                        </div>
                    </div>
                    <div class="mb-2">
                        <textarea name="answer" class="form-control" rows="2" placeholder="Answer / Solution" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save FAQ</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Solutions / FAQs (<?= $stats['total_faqs'] ?>)</h5>
                <input type="text" id="searchFaqs" class="form-control form-control-sm" placeholder="Search..." style="width: 200px;">
            </div>
            <div class="card-body p-0">
                <table class="table mb-0 table-hover" id="faqsTable">
                    <thead><tr><th>Question</th><th>Category</th><th>Tags</th><th>Uses</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        $faqs = $conn->query("SELECT * FROM faqs ORDER BY usage_count DESC LIMIT 100");
                        while ($faq = $faqs->fetch_assoc()): ?>
                        <tr data-search="<?= strtolower($faq['question'].' '.$faq['answer'].' '.$faq['tags']) ?>">
                            <td><?= $faq['question'] ?><br><small class="text-muted"><?= substr($faq['answer'], 0, 80) ?>...</small></td>
                            <td><?= $faq['category'] ?: '-' ?></td>
                            <td><?= $faq['tags'] ?: '-' ?></td>
                            <td><span class="badge bg-primary"><?= $faq['usage_count'] ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="editFaq(<?= $faq['id'] ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteFaq(<?= $faq['id'] ?>)"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'feedback'): ?>
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-comment-dots me-2"></i>Customer Feedback (<?= $stats['total_feedback'] ?>)</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0 table-hover">
                    <thead><tr><th>Time</th><th>Person</th><th>Priority</th><th>Notes</th><th>Agent</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php
                        $feedback = $conn->query("SELECT l.*, p.name as person_name, u.username FROM logs l LEFT JOIN persons p ON l.person_id = p.id JOIN users u ON l.agent_id = u.id WHERE l.type = 'feedback' ORDER BY l.created_at DESC LIMIT 100");
                        while ($f = $feedback->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('d M H:i', strtotime($f['created_at'])) ?></td>
                            <td><?= $f['person_name'] ?: '<span class="text-muted">Unknown</span>' ?></td>
                            <td><span class="badge priority-<?= $f['priority'] ?>"><?= $f['priority'] ?></span></td>
                            <td style="max-width:400px;"><small><?= $f['notes'] ?></small></td>
                            <td><?= $f['username'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="replyFeedback(<?= $f['id'] ?>)"><i class="fas fa-reply"></i> Reply</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'reports'): ?>
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Call Status</h5></div>
                    <div class="card-body">
                        <?php
                        $statusStats = $conn->query("SELECT status, COUNT(*) as c FROM calls GROUP BY status ORDER BY c DESC");
                        while ($s = $statusStats->fetch_assoc()):
                            $pct = $stats['total_calls'] > 0 ? round($s['c'] / $stats['total_calls'] * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1"><span><?= $s['status'] ?></span><strong><?= $s['c'] ?></strong></div>
                            <div class="progress"><div class="progress-bar bg-<?= stripos($s['status'],'answer')!==false?'success':(stripos($s['status'],'miss')!==false?'danger':'secondary') ?>" style="width:<?= $pct ?>%"><?= $pct ?>%</div></div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-clipboard me-2"></i>Log Status</h5></div>
                    <div class="card-body">
                        <?php
                        $logStatusStats = $conn->query("SELECT log_status, COUNT(*) as c FROM logs GROUP BY log_status ORDER BY c DESC");
                        while ($ls = $logStatusStats->fetch_assoc()):
                            $pct = $stats['total_logs'] > 0 ? round($ls['c'] / $stats['total_logs'] * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1"><span><?= ucfirst($ls['log_status']) ?></span><strong><?= $ls['c'] ?></strong></div>
                            <div class="progress"><div class="progress-bar bg-<?= $ls['log_status']=='open'?'primary':($ls['log_status']=='closed'?'success':'warning') ?>" style="width:<?= $pct ?>%"><?= $pct ?>%</div></div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-chart me-2"></i>Agent Performance</h5></div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <thead><tr><th>Agent</th><th>Calls</th><th>Missed</th><th>Logs</th><th>Open Logs</th></tr></thead>
                            <tbody>
                                <?php
                                $agentStats = $conn->query("SELECT a.name, 
                                    (SELECT COUNT(*) FROM calls WHERE agent_id = a.id) as calls,
                                    (SELECT COUNT(*) FROM calls WHERE agent_id = a.id AND status LIKE '%miss%') as missed,
                                    (SELECT COUNT(*) FROM logs WHERE agent_id = a.id) as logs,
                                    (SELECT COUNT(*) FROM logs WHERE agent_id = a.id AND log_status = 'open') as open_logs
                                    FROM agents a WHERE a.status = 'active'");
                                while ($a = $agentStats->fetch_assoc()): ?>
                                <tr><td><?= $a['name'] ?></td><td><?= $a['calls'] ?></td><td class="text-danger"><?= $a['missed'] ?></td><td><?= $a['logs'] ?></td><td class="text-warning"><?= $a['open_logs'] ?></td></tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Time Period</h5></div>
                    <div class="card-body">
                        <?php
                        $periods = [
                            ['label' => 'Today', 'calls' => $stats['today_calls'], 'logs' => $stats['today_logs']],
                            ['label' => 'This Week', 'calls' => $conn->query("SELECT COUNT(*) as c FROM calls WHERE start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'], 'logs' => $conn->query("SELECT COUNT(*) as c FROM logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c']],
                            ['label' => 'This Month', 'calls' => $conn->query("SELECT COUNT(*) as c FROM calls WHERE start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['c'], 'logs' => $conn->query("SELECT COUNT(*) as c FROM logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['c']]
                        ];
                        foreach ($periods as $p):
                        ?>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span><?= $p['label'] ?></span>
                            <span><?= $p['calls'] ?> calls, <?= $p['logs'] ?> logs</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-users me-2"></i>Top Contacted</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Calls</th><th>Logs</th></tr></thead>
                    <tbody>
                        <?php
                        $topPersons = $conn->query("SELECT p.name, p.phone, 
                            COUNT(*) as call_count,
                            (SELECT COUNT(*) FROM logs WHERE person_id = p.id) as log_count
                            FROM calls c JOIN persons p ON c.caller_number = p.phone GROUP BY p.id ORDER BY call_count DESC LIMIT 10");
                        $i = 1;
                        while ($tp = $topPersons->fetch_assoc()): ?>
                        <tr><td><?= $i++ ?></td><td><?= $tp['name'] ?: 'Unknown' ?></td><td><?= $tp['phone'] ?></td><td><?= $tp['call_count'] ?></td><td><?= $tp['log_count'] ?></td></tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab == 'settings'): ?>
        <div class="row">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-cloud-download-alt me-2"></i>PBX Connection</h5></div>
                    <div class="card-body">
                        <div id="fetchStatus"></div>
                        <div class="mb-3"><label class="form-label">PBX Username</label><input type="text" id="pbxUsername" class="form-control" value="<?= htmlspecialchars($savedPbxUser) ?>"></div>
                        <div class="mb-3"><label class="form-label">PBX Password</label><input type="password" id="pbxPassword" class="form-control" value="<?= htmlspecialchars($savedPbxPass) ?>"></div>
                        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="saveCredentials" checked><label class="form-check-label">Save credentials</label></div>
                        <div class="row mb-3">
                            <div class="col-6"><label class="form-label">From Date</label><input type="date" id="startDate" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-6"><label class="form-label">To Date</label><input type="date" id="endDate" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                        </div>
                        <button class="btn btn-primary w-100" onclick="fetchFromPBX()"><i class="fas fa-sync me-2"></i>Fetch Data (Manual)</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-database me-2"></i>Database Stats</h5></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr><td>Total Calls</td><td class="text-end"><strong><?= $stats['total_calls'] ?></strong></td></tr>
                            <tr><td>Contacts</td><td class="text-end"><strong><?= $stats['total_persons'] ?></strong></td></tr>
                            <tr><td>Logs</td><td class="text-end"><strong><?= $stats['total_logs'] ?></strong></td></tr>
                            <tr><td>Agents</td><td class="text-end"><strong><?= $stats['total_agents'] ?></strong></td></tr>
                            <tr><td>Tasks</td><td class="text-end"><strong><?= $stats['pending_tasks'] ?></strong></td></tr>
                            <tr><td>FAQs</td><td class="text-end"><strong><?= $stats['total_faqs'] ?></strong></td></tr>
                        </table>
                        <hr>
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Run <code>setup.php</code> to reset database</small>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5></div>
                    <div class="card-body">
                        <form id="changePasswordForm">
                            <div class="mb-2"><label class="form-label">Current Password</label><input type="password" name="current" class="form-control" required></div>
                            <div class="mb-2"><label class="form-label">New Password</label><input type="password" name="new_pass" class="form-control" required></div>
                            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-key me-1"></i> Change</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Modals -->
    <div class="modal fade" id="addAgentModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Agent</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="addAgentForm"><div class="modal-body"><div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div><div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div><div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div><div class="row"><div class="col-6 mb-3"><label class="form-label">Extension</label><input type="text" name="extension" class="form-control"></div><div class="col-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div></div><div class="mb-3"><label class="form-label">Department</label><input type="text" name="department" class="form-control"></div></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div></form></div></div></div>

    <div class="modal fade" id="editAgentModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Agent</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="editAgentForm"><div class="modal-body"><input type="hidden" name="id" id="editAgentId"><div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" id="editAgentName" class="form-control" required></div><div class="row"><div class="col-6 mb-3"><label class="form-label">Extension</label><input type="text" name="extension" id="editAgentExtension" class="form-control"></div><div class="col-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="editAgentPhone" class="form-control"></div></div><div class="mb-3"><label class="form-label">Department</label><input type="text" name="department" id="editAgentDepartment" class="form-control"></div></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Update</button></div></form></div></div></div>

    <div class="modal fade" id="editGroupModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="editGroupForm"><div class="modal-body"><input type="hidden" name="id" id="editGroupId"><div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" id="editGroupName" class="form-control" required></div><div class="mb-3"><label class="form-label">Color</label><input type="color" name="color" id="editGroupColor" class="form-control form-control-color"></div></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Update</button></div></form></div></div></div>

    <div class="modal fade" id="viewPersonModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="fas fa-user me-2"></i>Contact Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="personDetail"></div></div></div></div>

    <div class="modal fade" id="replyFeedbackModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="fas fa-reply me-2"></i>Reply to Feedback</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form id="replyFeedbackForm"><div class="modal-body"><input type="hidden" id="feedbackId"><div class="mb-3"><label class="form-label">Your Reply</label><textarea id="feedbackReply" class="form-control" rows="4" required></textarea></div></div><div class="modal-footer"><button type="submit" class="btn btn-primary">Send Reply</button></div></form></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function fetchFromPBX() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Fetching...';
            document.getElementById('fetchStatus').innerHTML = '<div class="alert alert-info">Connecting to PBX...</div>';
            fetch('api/fetch_pbx.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: document.getElementById('pbxUsername').value,
                    password: document.getElementById('pbxPassword').value,
                    save_credentials: document.getElementById('saveCredentials').checked,
                    start_date: document.getElementById('startDate').value + ' 00:00',
                    end_date: document.getElementById('endDate').value + ' 23:59'
                })
            }).then(r => r.json()).then(data => {
                if (data.status === 'success') {
                    document.getElementById('fetchStatus').innerHTML = '<div class="alert alert-success"><i class="fas fa-check me-2"></i>Fetched ' + data.total + ' calls. New: ' + data.inserted + '</div>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    document.getElementById('fetchStatus').innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>' + data.message + '</div>';
                }
            }).catch(err => {
                document.getElementById('fetchStatus').innerHTML = '<div class="alert alert-danger"><i class="fas fa-times me-2"></i>Error: ' + err.message + '</div>';
            }).finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync me-2"></i>Fetch Data (Manual)';
            });
        }

        function editAgent(id, name, extension, phone, department) {
            document.getElementById('editAgentId').value = id;
            document.getElementById('editAgentName').value = name;
            document.getElementById('editAgentExtension').value = extension;
            document.getElementById('editAgentPhone').value = phone;
            document.getElementById('editAgentDepartment').value = department;
            new bootstrap.Modal(document.getElementById('editAgentModal')).show();
        }

        function editGroup(id, name, color) {
            document.getElementById('editGroupId').value = id;
            document.getElementById('editGroupName').value = name;
            document.getElementById('editGroupColor').value = color;
            new bootstrap.Modal(document.getElementById('editGroupModal')).show();
        }

        function deleteGroup(id) {
            if (!confirm('Delete this group?')) return;
            fetch('api/groups.php?action=delete&id=' + id, {method:'POST', credentials:'include'}).then(r=>r.json()).then(d => { if(d.status==='success') location.reload(); });
        }

        function viewPerson(id) {
            fetch('api/persons.php?action=get&id=' + id, {credentials:'include'}).then(r=>r.json()).then(d => {
                const p = d.person;
                document.getElementById('personDetail').innerHTML = `
                    <table class="table table-sm">
                        <tr><td><strong>Name</strong></td><td>${p.name || 'Unknown'}</td></tr>
                        <tr><td><strong>Phone</strong></td><td>${p.phone}</td></tr>
                        <tr><td><strong>Type</strong></td><td><span class="badge badge-${p.type}">${p.type}</span></td></tr>
                        <tr><td><strong>Person Type</strong></td><td>${p.person_type}</td></tr>
                        <tr><td><strong>Company</strong></td><td>${p.company || '-'}</td></tr>
                        <tr><td><strong>Email</strong></td><td>${p.email || '-'}</td></tr>
                        <tr><td><strong>Address</strong></td><td>${p.address || '-'}</td></tr>
                        <tr><td><strong>Notes</strong></td><td>${p.notes || '-'}</td></tr>
                    </table>
                    <h6>Recent Logs</h6>
                    ${d.logs?.length ? d.logs.map(l => `<div class="border-bottom py-2"><small><strong>${l.type}</strong> - ${l.notes}</small><br><small class="text-muted">${l.username} - ${l.created_at}</small></div>`).join('') : '<p class="text-muted">No logs</p>'}
                `;
                new bootstrap.Modal(document.getElementById('viewPersonModal')).show();
            });
        }

        function deletePerson(id) {
            if (!confirm('Delete this contact?')) return;
            fetch('api/persons.php?action=delete&id=' + id, {method:'POST', credentials:'include'}).then(r=>r.json()).then(d => { if(d.status==='success') location.reload(); });
        }

        function toggleLock(id, isLocked) {
            fetch('api/logs.php?action=' + (isLocked ? 'unlock' : 'lock') + '&id=' + id, {method:'POST', credentials:'include'}).then(r=>r.json()).then(d => { if(d.status==='success') location.reload(); });
        }

        function convertLogToFaq(id) {
            if (!confirm('Convert this log to FAQ?')) return;
            fetch('api/logs.php?action=get&id=' + id, {credentials:'include'}).then(r=>r.json()).then(l => {
                fetch('api/faqs.php?action=create_from_log', {
                    method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({question: l.notes?.substring(0, 200), answer: l.notes, category: l.category})
                }).then(r=>r.json()).then(d => {
                    alert(d.status === 'success' ? 'FAQ created!' : d.error);
                    if (d.status === 'success') location.reload();
                });
            });
        }

        function replyFeedback(id) {
            document.getElementById('feedbackId').value = id;
            document.getElementById('feedbackReply').value = '';
            new bootstrap.Modal(document.getElementById('replyFeedbackModal')).show();
        }

        function editFaq(id) {
            fetch('api/faqs.php?action=get&id=' + id, {credentials:'include'}).then(r=>r.json()).then(f => {
                const q = prompt('Question:', f.question);
                const a = prompt('Answer:', f.answer);
                const c = prompt('Category:', f.category);
                if (q && a) {
                    fetch('api/faqs.php?action=update', {
                        method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({id, question: q, answer: a, category: c})
                    }).then(r=>r.json()).then(d => { if(d.status==='success') location.reload(); });
                }
            });
        }

        function deleteFaq(id) {
            if (!confirm('Delete this FAQ?')) return;
            fetch('api/faqs.php?action=delete&id=' + id, {method:'POST', credentials:'include'}).then(r=>r.json()).then(d => { if(d.status==='success') location.reload(); });
        }

        // Forms
        document.getElementById('addAgentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api/agents.php?action=add', {
                method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(Object.fromEntries(new FormData(this)))
            }).then(r => r.json()).then(data => {
                if (data.status === 'success') location.reload();
                else alert(data.error || 'Error');
            });
        });

        document.getElementById('editAgentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api/agents.php?action=update', {
                method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(Object.fromEntries(new FormData(this)))
            }).then(r => r.json()).then(data => {
                if (data.status === 'success') location.reload();
                else alert(data.error || 'Error');
            });
        });

        document.getElementById('addGroupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api/groups.php?action=add', {
                method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(Object.fromEntries(new FormData(this)))
            }).then(r => r.json()).then(data => {
                if (data.status === 'success') location.reload();
                else alert(data.error || 'Error');
            });
        });

        document.getElementById('editGroupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api/groups.php?action=update', {
                method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(Object.fromEntries(new FormData(this)))
            }).then(r => r.json()).then(data => {
                if (data.status === 'success') location.reload();
                else alert(data.error || 'Error');
            });
        });

        document.getElementById('addFaqForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api/faqs.php?action=add', {
                method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(Object.fromEntries(new FormData(this)))
            }).then(r => r.json()).then(data => {
                if (data.status === 'success') location.reload();
                else alert(data.error || 'Error');
            });
        });

        document.getElementById('replyFeedbackForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api/logs.php?action=reply', {
                method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({
                    parent_id: document.getElementById('feedbackId').value,
                    notes: document.getElementById('feedbackReply').value
                })
            }).then(r => r.json()).then(data => {
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('replyFeedbackModal')).hide();
                    alert('Reply sent!');
                    location.reload();
                } else alert(data.error || 'Error');
            });
        });

        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            fetch('api/auth.php?action=change_password', {
                method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                body: JSON.stringify(Object.fromEntries(new FormData(this)))
            }).then(r => r.json()).then(data => {
                alert(data.status === 'success' ? 'Password changed!' : data.error);
                if (data.status === 'success') this.reset();
            });
        });

        document.getElementById('csvImportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const file = document.getElementById('csvFile').files[0];
            if (!file) return;
            const type = this.querySelector('select').value;
            const reader = new FileReader();
            reader.onload = function(e) {
                const lines = e.target.result.split('\n');
                const contacts = [];
                for (let i = 1; i < lines.length; i++) {
                    const parts = lines[i].split(',');
                    if (parts[0]) contacts.push({ 
                        phone: parts[0].trim(), 
                        name: parts[1]?.trim() || '', 
                        type: parts[2]?.trim() || type, 
                        company: parts[3]?.trim() || '',
                        email: parts[4]?.trim() || ''
                    });
                }
                fetch('api/persons.php?action=import', {
                    method: 'POST', credentials:'include', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ contacts })
                }).then(r => r.json()).then(data => {
                    document.getElementById('importResult').innerHTML = '<div class="alert alert-success">Imported: ' + data.imported + ', Skipped: ' + data.skipped + '</div>';
                    setTimeout(() => location.reload(), 2000);
                });
            };
            reader.readAsText(file);
        });

        // Filters
        document.getElementById('searchCalls')?.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#callsTable tbody tr').forEach(row => row.style.display = row.dataset.search.includes(q) ? '' : 'none');
        });

        document.getElementById('searchPersons')?.addEventListener('input', filterPersons);
        document.getElementById('filterType')?.addEventListener('change', filterPersons);
        document.getElementById('filterPersonType')?.addEventListener('change', filterPersons);

        function filterPersons() {
            const q = document.getElementById('searchPersons').value.toLowerCase();
            const type = document.getElementById('filterType').value;
            const ptype = document.getElementById('filterPersonType').value;
            document.querySelectorAll('#personsTable tbody tr').forEach(row => {
                const matchQ = row.dataset.search.includes(q);
                const matchType = !type || row.dataset.type === type;
                const matchPType = !ptype || row.dataset.personType === ptype;
                row.style.display = (matchQ && matchType && matchPType) ? '' : 'none';
            });
        }

        document.getElementById('searchLogs')?.addEventListener('input', filterLogs);
        document.getElementById('filterLogStatus')?.addEventListener('change', filterLogs);
        document.getElementById('filterLogType')?.addEventListener('change', filterLogs);

        function filterLogs() {
            const q = document.getElementById('searchLogs').value.toLowerCase();
            const status = document.getElementById('filterLogStatus').value;
            const type = document.getElementById('filterLogType').value;
            document.querySelectorAll('#logsTable tbody tr').forEach(row => {
                const matchQ = row.dataset.notes.includes(q);
                const matchStatus = !status || row.dataset.status === status;
                const matchType = !type || row.dataset.type === type;
                row.style.display = (matchQ && matchStatus && matchType) ? '' : 'none';
            });
        }

        document.getElementById('filterTaskStatus')?.addEventListener('change', function() {
            const status = this.value;
            document.querySelectorAll('#tasksTable tbody tr').forEach(row => {
                row.style.display = !status || row.dataset.status === status ? '' : 'none';
            });
        });

        document.getElementById('searchFaqs')?.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#faqsTable tbody tr').forEach(row => {
                row.style.display = row.dataset.search.includes(q) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
