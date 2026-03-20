<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$user = getUser();
$agent_id = $_SESSION['agent_id'];
$is_admin = $user['role'] === 'admin';

$search = trim($_GET['search'] ?? '');
$type_filter = $_GET['type'] ?? '';
$group_filter = isset($_GET['group']) ? (is_array($_GET['group']) ? $_GET['group'] : explode(',', $_GET['group'])) : [];
$internal_filter = $_GET['internal'] ?? '';
$page = intval($_GET['page'] ?? 0);
$per_page = intval($_GET['per_page'] ?? 50);
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'bulk_update') {
        $ids_str = $_POST['ids'] ?? '';
        $bulk_type = $_POST['bulk_type'] ?? '';
        $bulk_group = $_POST['bulk_group'] ?? '';
        $bulk_internal = $_POST['bulk_internal'] ?? '';
        
        if (!empty($ids_str)) {
            $id_list = implode(',', array_map('intval', explode(',', $ids_str)));
            if ($bulk_type !== '') {
                $conn->query("UPDATE persons SET type = '" . $conn->real_escape_string($bulk_type) . "' WHERE id IN ($id_list)");
            }
            if ($bulk_group !== '') {
                $conn->query("UPDATE persons SET group_id = " . intval($bulk_group) . " WHERE id IN ($id_list)");
            }
            if ($bulk_internal !== '') {
                $conn->query("UPDATE persons SET internal_external = '" . $conn->real_escape_string($bulk_internal) . "' WHERE id IN ($id_list)");
            }
        }
        header('Location: contacts.php?saved=1');
        exit;
    }
    
    if ($action === 'add' || $action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $group_id = intval($_POST['group_id'] ?? 0);
        $internal_external = trim($_POST['internal_external'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if ($phone) {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO persons (name, phone, type, group_id, internal_external, address) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssiss", $name, $phone, $type, $group_id, $internal_external, $address);
            } else {
                $stmt = $conn->prepare("UPDATE persons SET name=?, phone=?, type=?, group_id=?, internal_external=?, address=? WHERE id=?");
                $stmt->bind_param("sssissi", $name, $phone, $type, $group_id, $internal_external, $address, $id);
            }
            $stmt->execute();
        }
        header('Location: contacts.php?saved=1');
        exit;
    }
    
    if ($action === 'add_group') {
        $name = trim($_POST['group_name'] ?? '');
        if ($name) {
            $conn->query("INSERT INTO contact_groups (name) VALUES ('" . $conn->real_escape_string($name) . "')");
        }
        header('Location: contacts.php');
        exit;
    }
    
    if ($action === 'edit_group') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id && $name) {
            $conn->query("UPDATE contact_groups SET name = '" . $conn->real_escape_string($name) . "' WHERE id = $id");
        }
        header('Location: contacts.php');
        exit;
    }
    
    if ($action === 'delete_group') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $conn->query("DELETE FROM contact_groups WHERE id = $id");
            $conn->query("UPDATE persons SET group_id = 0 WHERE group_id = $id");
        }
        header('Location: contacts.php');
        exit;
    }
    
    if ($action === 'add_type' || $action === 'edit_type') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['type_name'] ?? '');
        if ($name) {
            $exists = $conn->query("SELECT id FROM contact_types WHERE name = '" . $conn->real_escape_string($name) . "'" . ($id ? " AND id != $id" : ""))->num_rows > 0;
            if (!$exists) {
                if ($action === 'add_type') {
                    $conn->query("INSERT INTO contact_types (name) VALUES ('" . $conn->real_escape_string($name) . "')");
                } else {
                    $conn->query("UPDATE contact_types SET name = '" . $conn->real_escape_string($name) . "' WHERE id = $id");
                }
            }
        }
        header('Location: contacts.php');
        exit;
    }
    
    if ($action === 'delete_type') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            $type_result = $conn->query("SELECT name FROM contact_types WHERE id = $id");
            if ($type_row = $type_result->fetch_assoc()) {
                $type_name = $conn->real_escape_string($type_row['name']);
                $conn->query("UPDATE persons SET type = '' WHERE type = '$type_name'");
            }
            $conn->query("DELETE FROM contact_types WHERE id = $id");
        }
        header('Location: contacts.php');
        exit;
    }
}

$groups_result = $conn->query("SELECT * FROM contact_groups ORDER BY name");
$groups = [];
while ($g = $groups_result->fetch_assoc()) $groups[] = $g;

$types_result = $conn->query("SELECT * FROM contact_types ORDER BY name");
$types = [];
while ($t = $types_result->fetch_assoc()) $types[] = $t;

$where = "1=1";
$params = [];
if ($search) {
    $where .= " AND (p.name LIKE ? OR p.phone LIKE ? OR p.address LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}
if ($type_filter) {
    $where .= " AND p.type = ?";
    $params[] = $type_filter;
}
if (!empty($group_filter)) {
    $gids = implode(',', array_map('intval', $group_filter));
    $where .= " AND p.group_id IN ($gids)";
}
if ($internal_filter) {
    $where .= " AND p.internal_external = ?";
    $params[] = $internal_filter;
}

$count_query = "SELECT COUNT(*) as total FROM persons p WHERE $where";
$stmt_count = $conn->prepare($count_query);
if ($params) {
    $types_str = str_repeat('s', count($params));
    $stmt_count->bind_param($types_str, ...$params);
}
$stmt_count->execute();
$total = $stmt_count->get_result()->fetch_assoc()['total'];

$offset = $page * $per_page;
$query = "SELECT p.*, g.name as group_name FROM persons p LEFT JOIN contact_groups g ON p.group_id = g.id WHERE $where ORDER BY p.name ASC LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($query);
if ($params) {
    $types_str = str_repeat('s', count($params));
    $stmt->bind_param($types_str, ...$params);
}
$stmt->execute();
$persons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($persons as &$p) {
    $phone = $conn->real_escape_string($p['phone']);
    $calls_data = $conn->query("SELECT 
        SUM(CASE WHEN direction = 'Inbound' THEN 1 ELSE 0 END) as calls_in,
        SUM(CASE WHEN direction = 'Outbound' THEN 1 ELSE 0 END) as calls_out,
        SUM(CASE WHEN status LIKE '%answer%' THEN 1 ELSE 0 END) as calls_answered,
        SUM(CASE WHEN status LIKE '%miss%' THEN 1 ELSE 0 END) as calls_missed
        FROM calls WHERE caller_number = '$phone'")->fetch_assoc();
    $p['calls_in'] = intval($calls_data['calls_in'] ?? 0);
    $p['calls_out'] = intval($calls_data['calls_out'] ?? 0);
    $p['calls_answered'] = intval($calls_data['calls_answered'] ?? 0);
    $p['calls_missed'] = intval($calls_data['calls_missed'] ?? 0);
    $p['total_calls'] = $p['calls_in'] + $p['calls_out'];
    
    $logs_data = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN log_status = 'open' THEN 1 ELSE 0 END) as open_logs FROM logs WHERE person_id = " . intval($p['id']))->fetch_assoc();
    $p['logs_count'] = intval($logs_data['total'] ?? 0);
    $p['logs_open'] = intval($logs_data['open_logs'] ?? 0);
    
    $marks_data = $conn->query("SELECT 
        SUM(CASE WHEN call_mark = 'successful' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN call_mark = 'problem' THEN 1 ELSE 0 END) as problem,
        SUM(CASE WHEN call_mark = 'urgent' THEN 1 ELSE 0 END) as urgent,
        SUM(CASE WHEN call_mark = 'need_action' THEN 1 ELSE 0 END) as need_action
        FROM calls WHERE caller_number = '$phone' AND call_mark != ''")->fetch_assoc();
    $p['mark_successful'] = intval($marks_data['successful'] ?? 0);
    $p['mark_problem'] = intval($marks_data['problem'] ?? 0);
    $p['mark_urgent'] = intval($marks_data['urgent'] ?? 0);
    $p['mark_need_action'] = intval($marks_data['need_action'] ?? 0);
}
unset($p);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Contacts - PBX Manager</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/dark.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: var(--bg-primary); color: var(--text-primary); font-size: 13px; }
        
        .fixed-header { position: sticky; top: 0; z-index: 100; background: var(--bg-secondary); border-bottom: 1px solid var(--border-color); padding: 15px 20px; }
        
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .header-top h1 { font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .header-top h1 i { color: var(--accent-primary); }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        
        .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; border: none; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
        .btn-primary { background: var(--accent-primary); color: white; }
        .btn-primary:hover { background: var(--accent-primary-hover); }
        .btn-success { background: var(--accent-success); color: white; }
        .btn-secondary { background: var(--bg-card-hover); color: var(--text-primary); border: 1px solid var(--border-color); }
        .btn-secondary:hover { background: var(--bg-secondary); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-outline { background: transparent; color: var(--text-secondary); border: 1px solid var(--border-color); }
        
        .nav-links { display: flex; gap: 8px; }
        .nav-links a { padding: 8px 14px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 8px; font-size: 13px; text-decoration: none; }
        .nav-links a:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        
        .filters-bar { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; margin: 15px 20px; }
        .filters-bar form { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 150px; }
        .filter-group label { font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group input, .filter-group select { padding: 8px 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 12px; }
        .filter-group input::placeholder { color: var(--text-muted); }
        .filter-group select[multiple] { height: 65px; }
        
        .stats-row { display: flex; flex-wrap: wrap; gap: 10px; padding: 0 20px 15px; }
        .stat-item { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 18px; text-align: center; min-width: 80px; }
        .stat-item .label { font-size: 9px; color: var(--text-muted); text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-item .value { font-size: 18px; font-weight: 700; }
        .stat-item .value.s { color: var(--accent-success); }
        .stat-item .value.w { color: var(--accent-warning); }
        .stat-item .value.d { color: var(--accent-danger); }
        
        .toolbar { display: flex; gap: 10px; padding: 0 20px 15px; flex-wrap: wrap; align-items: center; }
        .toolbar select { padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 12px; }
        .toolbar-left { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .toolbar-right { display: flex; gap: 8px; align-items: center; margin-left: auto; }
        
        .selection-bar { display: flex; align-items: center; gap: 10px; padding: 8px 15px; background: rgba(99, 102, 241, 0.1); border: 1px solid var(--accent-primary); border-radius: 8px; margin: 0 20px 15px; display: none; }
        .selection-bar.show { display: flex; }
        .selection-bar span { font-size: 12px; color: var(--text-secondary); }
        .selection-bar .count { background: var(--accent-primary); color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        
        .bulk-bar { display: none; gap: 8px; padding: 8px 15px; background: rgba(245, 158, 11, 0.1); border: 1px solid var(--accent-warning); border-radius: 8px; margin: 0 20px 15px; flex-wrap: wrap; align-items: center; }
        .bulk-bar.show { display: flex; }
        .bulk-bar span { font-size: 12px; color: var(--accent-warning); font-weight: 600; margin-right: 5px; }
        .bulk-bar select { padding: 6px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: 11px; }
        
        .table-container { margin: 0 20px 20px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .data-table th { background: var(--bg-secondary); padding: 12px 14px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        .data-table td { padding: 12px 14px; border-bottom: 1px solid var(--border-color); font-size: 12px; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: rgba(99, 102, 241, 0.05); }
        
        .contact-name { font-weight: 600; color: var(--text-primary); }
        .contact-phone { font-family: 'Consolas', monospace; color: var(--text-secondary); font-size: 12px; }
        .num { font-weight: 600; text-align: center; }
        .num-s { color: var(--accent-success); }
        .num-w { color: var(--accent-warning); }
        .num-d { color: var(--accent-danger); }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 500; margin-right: 4px; }
        .badge-type { background: var(--bg-secondary); color: var(--text-secondary); }
        .badge-group { background: rgba(16, 185, 129, 0.15); color: var(--accent-success); }
        .badge-int { background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); }
        .badge-ext { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
        
        .action-btns { display: flex; gap: 6px; }
        .action-btn { padding: 5px 10px; border-radius: 6px; font-size: 11px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .action-btn.log { background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); }
        .action-btn.edit { background: rgba(59, 130, 246, 0.15); color: var(--accent-info); }
        
        .pagination { display: flex; justify-content: center; gap: 5px; padding: 20px; }
        .pagination a { padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-secondary); text-decoration: none; font-size: 12px; }
        .pagination a:hover { background: var(--bg-card-hover); }
        .pagination a.active { background: var(--accent-primary); color: white; border-color: var(--accent-primary); }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 200; justify-content: center; align-items: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 25px; width: 90%; max-width: 420px; }
        .modal-box h2 { font-size: 1rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .modal-box h2 i { color: var(--accent-primary); }
        .modal-form .form-group { margin-bottom: 15px; }
        .modal-form label { display: block; font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; }
        .modal-form input, .modal-form select, .modal-form textarea { width: 100%; padding: 10px 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 13px; }
        .modal-form textarea { height: 70px; resize: vertical; }
        .modal-btns { display: flex; gap: 10px; margin-top: 20px; }
        
        .no-data { text-align: center; padding: 50px; color: var(--text-muted); }
        
        @media print {
            @page { size: A4 landscape; margin: 10px; }
            body { background: white; color: black; font-size: 9px; }
            *, *::before, *::after { background: transparent !important; color: black !important; border-color: #ccc !important; box-shadow: none !important; }
            .no-print, .fixed-header, .filters-bar, .toolbar, .selection-bar, .bulk-bar, .pagination, .action-btns, .nav-links, .header-actions, .btn, .modal, input, select, textarea, .badge { display: none !important; }
            .table-container { border: none; margin: 0; padding: 0; }
            .data-table { width: 100%; min-width: auto; }
            .data-table th { background: #eee !important; color: black !important; padding: 4px 6px; border: 1px solid #ccc; font-weight: bold; }
            .data-table td { padding: 4px 6px; border: 1px solid #ccc; color: black !important; }
            .stat-row, .stat-item { display: inline-block; margin-right: 15px; }
            h1, h2, .contact-name, .contact-phone, .num { color: black !important; }
        }
    </style>
</head>
<body>
    <div class="fixed-header">
        <div class="header-top">
            <h1><i class="fas fa-address-book"></i> Contacts <span style="font-size:12px;color:var(--text-muted);font-weight:400;">(<?= $total ?>)</span></h1>
            <div class="header-actions">
                <div class="nav-links">
                    <a href="agent.php">&#8592; Dashboard</a>
                    <a href="calls_report.php">Call Report</a>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="openManageModal()"><i class="fas fa-cog"></i> Manage</button>
                <button class="btn btn-success btn-sm" onclick="openModal('add')"><i class="fas fa-plus"></i> Add</button>
            </div>
        </div>
    </div>
    
    <div class="filters-bar no-print">
        <form method="GET">
            <div class="filter-group" style="flex: 1; min-width: 200px;">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, phone, address..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label>Type</label>
                <select name="type">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                    <option value="<?= htmlspecialchars($t['name']) ?>" <?= $type_filter === $t['name'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Group</label>
                <select name="group[]" multiple>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= in_array($g['id'], $group_filter) ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Internal/External</label>
                <select name="internal">
                    <option value="">All</option>
                    <option value="internal" <?= $internal_filter === 'internal' ? 'selected' : '' ?>>Internal</option>
                    <option value="external" <?= $internal_filter === 'external' ? 'selected' : '' ?>>External</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Per Page</label>
                <select name="per_page">
                    <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </div>
        </form>
    </div>
    
    <?php
    $total_in = 0; $total_out = 0; $total_ans = 0; $total_miss = 0; $total_logs = 0;
    foreach ($persons as $p) { $total_in += $p['calls_in']; $total_out += $p['calls_out']; $total_ans += $p['calls_answered']; $total_miss += $p['calls_missed']; $total_logs += $p['logs_count']; }
    ?>
    <div class="stats-row no-print">
        <div class="stat-item"><div class="label">Contacts</div><div class="value"><?= $total ?></div></div>
        <div class="stat-item"><div class="label">In</div><div class="value s"><?= $total_in ?></div></div>
        <div class="stat-item"><div class="label">Out</div><div class="value"><?= $total_out ?></div></div>
        <div class="stat-item"><div class="label">Ans</div><div class="value s"><?= $total_ans ?></div></div>
        <div class="stat-item"><div class="label">Miss</div><div class="value d"><?= $total_miss ?></div></div>
        <div class="stat-item"><div class="label">Logs</div><div class="value w"><?= $total_logs ?></div></div>
    </div>
    
    <div class="toolbar no-print">
        <div class="toolbar-left">
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            <select id="csvExport" onchange="exportCSV(this.value); this.value='';">
                <option value="">Export CSV...</option>
                <option value="basic">Name + Phone</option>
                <option value="full">All Fields</option>
            </select>
            <button class="btn btn-secondary btn-sm" onclick="copyAll()"><i class="fas fa-copy"></i> Copy</button>
        </div>
        <div class="toolbar-right">
            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="width:16px;height:16px;accent-color:var(--accent-primary);">
            <span style="font-size:12px;color:var(--text-muted);">Select All (<span id="selCount">0</span>)</span>
        </div>
    </div>
    
    <div class="bulk-bar no-print" id="bulkBar">
        <span><i class="fas fa-edit"></i> Bulk Update:</span>
        <span id="bulkCount" style="font-size:11px;color:var(--text-muted);margin-right:5px;">(Select items to enable)</span>
        <select id="bulkType" onchange="bulkUpdate('type', this.value); this.value='';">
            <option value="">Type...</option>
            <?php foreach ($types as $t): ?>
            <option value="<?= htmlspecialchars($t['name']) ?>"><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="bulkGroup" onchange="bulkUpdate('group', this.value); this.value='';">
            <option value="">Group...</option>
            <option value="0">No Group</option>
            <?php foreach ($groups as $g): ?>
            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="bulkInt" onchange="bulkUpdate('internal', this.value); this.value='';">
            <option value="">Int/Ext...</option>
            <option value="internal">Internal</option>
            <option value="external">External</option>
        </select>
    </div>

    <?php if (empty($persons)): ?>
        <div class="table-container"><div class="no-data">No contacts found.</div></div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:35px;" class="no-print"><input type="checkbox" onchange="toggleAll(this)"></th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Group</th>
                        <th>Int/Ext</th>
                        <th style="text-align:center;">In</th>
                        <th style="text-align:center;">Out</th>
                        <th style="text-align:center;">Ans</th>
                        <th style="text-align:center;">Miss</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Logs</th>
                        <th style="text-align:center;">Succ</th>
                        <th style="text-align:center;">Prob</th>
                        <th style="text-align:center;">Urgent</th>
                        <th style="text-align:center;">NA</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($persons as $p): ?>
                    <tr data-id="<?= $p['id'] ?>">
                        <td class="no-print"><input type="checkbox" class="row-check" onchange="updateSel()"></td>
                        <td class="contact-name"><?= htmlspecialchars($p['name'] ?: '-') ?></td>
                        <td class="contact-phone"><?= htmlspecialchars($p['phone']) ?></td>
                        <td><?= $p['type'] ? '<span class="badge badge-type">'.htmlspecialchars($p['type']).'</span>' : '' ?></td>
                        <td><?= $p['group_name'] ? '<span class="badge badge-group">'.htmlspecialchars($p['group_name']).'</span>' : '' ?></td>
                        <td><?= $p['internal_external'] ? '<span class="badge badge-'.($p['internal_external'] === 'internal' ? 'int' : 'ext').'">'.ucfirst($p['internal_external']).'</span>' : '' ?></td>
                        <td class="num"><?= $p['calls_in'] ?></td>
                        <td class="num"><?= $p['calls_out'] ?></td>
                        <td class="num num-s"><?= $p['calls_answered'] ?></td>
                        <td class="num num-d"><?= $p['calls_missed'] ?></td>
                        <td class="num"><strong><?= $p['total_calls'] ?></strong></td>
                        <td class="num <?= $p['logs_open'] > 0 ? 'num-w' : '' ?>"><?= $p['logs_count'] ?></td>
                        <td class="num num-s"><?= $p['mark_successful'] ?></td>
                        <td class="num <?= $p['mark_problem'] > 0 ? 'num-w' : '' ?>"><?= $p['mark_problem'] ?></td>
                        <td class="num <?= $p['mark_urgent'] > 0 ? 'num-d' : '' ?>"><?= $p['mark_urgent'] ?></td>
                        <td class="num"><?= $p['mark_need_action'] ?></td>
                        <td class="action-btns no-print">
                            <a href="agent.php?phone=<?= urlencode($p['phone']) ?>" class="action-btn log"><i class="fas fa-comment"></i></a>
                            <button class="action-btn edit" onclick="editContact(<?= $p['id'] ?>)"><i class="fas fa-edit"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="pagination no-print">
            <?php if ($page > 0): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = max(0, $page - 2); $p <= min(ceil($total / $per_page) - 1, $page + 2); $p++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p + 1 ?></a>
            <?php endfor; ?>
            <?php if (($page + 1) * $per_page < $total): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="modal-overlay" id="contactModal">
        <div class="modal-box">
            <h2 id="modalTitle"><i class="fas fa-user-plus"></i> Add Contact</h2>
            <form method="POST" id="contactForm">
                <input type="hidden" name="id" id="contactId">
                <input type="hidden" name="action" id="formAction" value="add">
                <div class="modal-form">
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="text" name="phone" id="contactPhone" required>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" id="contactName">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <input type="text" name="type" id="contactType" list="typeList" placeholder="Client, Staff...">
                        <datalist id="typeList">
                            <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t['name']) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label>Group</label>
                        <select name="group_id" id="contactGroup">
                            <option value="0">No Group</option>
                            <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Internal/External</label>
                        <select name="internal_external" id="contactInternal">
                            <option value="">Not Set</option>
                            <option value="internal">Internal</option>
                            <option value="external">External</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" id="contactAddress"></textarea>
                    </div>
                </div>
                <div class="modal-btns">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="modal-overlay" id="manageModal">
        <div class="modal-box" style="max-width:500px;">
            <h2><i class="fas fa-cog"></i> Manage Types & Groups</h2>
            
            <div style="display:flex;gap:15px;margin-bottom:15px;">
                <button class="btn btn-sm" id="tabTypes" onclick="showTab('types')" style="background:var(--accent-primary);color:white;">Types</button>
                <button class="btn btn-sm btn-secondary" id="tabGroups" onclick="showTab('groups')">Groups</button>
            </div>
            
            <div id="tabContentTypes">
                <form method="POST" style="margin-bottom:15px;">
                    <input type="hidden" name="action" id="typeAction" value="add_type">
                    <input type="hidden" name="id" id="typeId">
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="type_name" id="typeName" placeholder="New type name" required style="flex:1;">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
                <div style="max-height:200px;overflow-y:auto;">
                    <?php foreach ($types as $t): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-color);">
                        <span style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($t['name']) ?></span>
                        <div style="display:flex;gap:5px;">
                            <button class="btn btn-sm btn-secondary" onclick="editType(<?= $t['id'] ?>,'<?= htmlspecialchars($t['name']) ?>')"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this type?');">
                                <input type="hidden" name="action" value="delete_type">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background:rgba(239,68,68,0.15);color:var(--accent-danger);"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div id="tabContentGroups" style="display:none;">
                <form method="POST" style="margin-bottom:15px;">
                    <input type="hidden" name="action" id="groupAction" value="add_group">
                    <input type="hidden" name="id" id="groupId">
                    <div style="display:flex;gap:8px;">
                        <input type="text" name="group_name" id="groupName" placeholder="New group name" required style="flex:1;">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
                <div style="max-height:200px;overflow-y:auto;">
                    <?php foreach ($groups as $g): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-color);">
                        <span style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($g['name']) ?></span>
                        <div style="display:flex;gap:5px;">
                            <button class="btn btn-sm btn-secondary" onclick="editGroup(<?= $g['id'] ?>,'<?= htmlspecialchars($g['name']) ?>')"><i class="fas fa-edit"></i></button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this group?');">
                                <input type="hidden" name="action" value="delete_group">
                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                <button type="submit" class="btn btn-sm" style="background:rgba(239,68,68,0.15);color:var(--accent-danger);"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="modal-btns">
                <button type="button" class="btn btn-secondary" onclick="closeManageModal()">Close</button>
            </div>
        </div>
    </div>

    <form method="POST" id="bulkForm" style="display:none;">
        <input type="hidden" name="action" value="bulk_update">
        <input type="hidden" name="ids" id="bulkIds">
        <input type="hidden" name="bulk_type" id="bulkTypeVal">
        <input type="hidden" name="bulk_group" id="bulkGroupVal">
        <input type="hidden" name="bulk_internal" id="bulkInternalVal">
    </form>

    <script>
    const data = <?= json_encode(array_column($persons, null, 'id')) ?>;
    
    function toggleAll(cb) {
        document.querySelectorAll('input.row-check').forEach(c => c.checked = cb.checked);
        document.getElementById('selectAll').checked = cb.checked;
        updateSel();
    }
    
    function updateSel() {
        const checked = document.querySelectorAll('input.row-check:checked');
        const count = checked.length;
        document.getElementById('selCount').textContent = count;
        var bulkBar = document.getElementById('bulkBar');
        if(bulkBar) {
            if(count > 0) {
                bulkBar.style.display = 'flex';
                bulkBar.classList.add('show');
            } else {
                bulkBar.style.display = 'none';
                bulkBar.classList.remove('show');
            }
        }
        if(document.getElementById('bulkCount')) {
            document.getElementById('bulkCount').textContent = count > 0 ? count + ' selected' : '(Select items to enable)';
        }
        
        const total = document.querySelectorAll('input.row-check').length;
        document.getElementById('selectAll').checked = total > 0 && count === total;
    }
    
    function bulkUpdate(field, value) {
        if (!value) return;
        const checked = document.querySelectorAll('input.row-check:checked');
        if (!checked.length) { alert('Please select contacts first'); return; }
        
        document.getElementById('bulkIds').value = Array.from(checked).map(c => c.closest('tr').dataset.id).join(',');
        if (field === 'type') document.getElementById('bulkTypeVal').value = value;
        if (field === 'group') document.getElementById('bulkGroupVal').value = value;
        if (field === 'internal') document.getElementById('bulkInternalVal').value = value;
        document.getElementById('bulkForm').submit();
    }
    
    function editContact(id) {
        const c = data[id];
        if (!c) return;
        document.getElementById('formAction').value = 'update';
        document.getElementById('contactId').value = c.id;
        document.getElementById('contactPhone').value = c.phone || '';
        document.getElementById('contactName').value = c.name || '';
        document.getElementById('contactType').value = c.type || '';
        document.getElementById('contactGroup').value = c.group_id || '0';
        document.getElementById('contactInternal').value = c.internal_external || '';
        document.getElementById('contactAddress').value = c.address || '';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Contact';
        document.getElementById('contactModal').classList.add('show');
    }
    
    function openModal() {
        document.getElementById('formAction').value = 'add';
        document.getElementById('contactId').value = '';
        document.getElementById('contactPhone').value = '';
        document.getElementById('contactName').value = '';
        document.getElementById('contactType').value = '';
        document.getElementById('contactGroup').value = '0';
        document.getElementById('contactInternal').value = '';
        document.getElementById('contactAddress').value = '';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Contact';
        document.getElementById('contactModal').classList.add('show');
    }
    
    function closeModal() { document.getElementById('contactModal').classList.remove('show'); }
    function openManageModal() { document.getElementById('manageModal').classList.add('show'); }
    function closeManageModal() { document.getElementById('manageModal').classList.remove('show'); }
    
    function showTab(tab) {
        document.getElementById('tabContentTypes').style.display = tab === 'types' ? 'block' : 'none';
        document.getElementById('tabContentGroups').style.display = tab === 'groups' ? 'block' : 'none';
        document.getElementById('tabTypes').style.background = tab === 'types' ? 'var(--accent-primary)' : '';
        document.getElementById('tabTypes').style.color = tab === 'types' ? 'white' : '';
        document.getElementById('tabGroups').style.background = tab === 'groups' ? 'var(--accent-primary)' : '';
        document.getElementById('tabGroups').style.color = tab === 'groups' ? 'white' : '';
    }
    
    function editType(id, name) {
        document.getElementById('typeAction').value = 'edit_type';
        document.getElementById('typeId').value = id;
        document.getElementById('typeName').value = name;
        document.getElementById('typeName').focus();
    }
    
    function editGroup(id, name) {
        document.getElementById('groupAction').value = 'edit_group';
        document.getElementById('groupId').value = id;
        document.getElementById('groupName').value = name;
        document.getElementById('groupName').focus();
    }
    
    function exportCSV(opt) {
        if (!opt) return;
        const rows = document.querySelectorAll('tr[data-id]');
        let csv = opt === 'basic' ? 'Name,Phone\n' : 'Name,Phone,Type,Group,Int/Ext,In,Out,Ans,Miss,Total,Logs,Succ,Prob,Urgent,NA\n';
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (opt === 'basic') {
                csv += `"${cells[1].innerText}","${cells[2].innerText}"\n`;
            } else {
                csv += `"${cells[1].innerText}","${cells[2].innerText}","${cells[3].innerText}","${cells[4].innerText}","${cells[5].innerText}",${cells[6].innerText},${cells[7].innerText},${cells[8].innerText},${cells[9].innerText},${cells[10].innerText},${cells[11].innerText},${cells[12].innerText},${cells[13].innerText},${cells[14].innerText},${cells[15].innerText}\n`;
            }
        });
        
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'}));
        a.download = 'contacts_<?= date("Y-m-d") ?>_' + opt + '.csv';
        a.click();
    }
    
    function copyAll() {
        const rows = document.querySelectorAll('tr[data-id]');
        let text = 'CONTACTS\n' + '='.repeat(30) + '\n';
        rows.forEach(row => {
            const name = row.querySelector('.contact-name').innerText;
            const phone = row.querySelector('.contact-phone').innerText;
            text += `${name} | ${phone}\n`;
        });
        navigator.clipboard.writeText(text);
    }
    
    window.onclick = e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); };
    </script>
</body>
</html>

