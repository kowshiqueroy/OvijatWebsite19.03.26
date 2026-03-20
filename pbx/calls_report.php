<?php
require_once 'config.php';
require_once 'functions.php';

requireLogin();

$user = getUser();
$agent_id = $_SESSION['agent_id'];
$is_admin = $user['role'] === 'admin';

$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : explode(',', $_GET['status'])) : [];
$mark_filter = isset($_GET['mark']) ? (is_array($_GET['mark']) ? $_GET['mark'] : explode(',', $_GET['mark'])) : [];
$show_logs_screen = isset($_GET['show_logs']) && $_GET['show_logs'] == '1';
$show_replies = isset($_GET['show_replies']) && $_GET['show_replies'] == '1';
$search = trim($_GET['search'] ?? '');
$per_page = intval($_GET['per_page'] ?? 100);
$page = intval($_GET['page'] ?? 0);

$params = [];
$types = "";
$where_base = $is_admin ? "1=1" : "c.agent_id = $agent_id";

if ($date_from) { $where_base .= " AND DATE(c.start_time) >= ?"; $params[] = $date_from; $types .= "s"; }
if ($date_to) { $where_base .= " AND DATE(c.start_time) <= ?"; $params[] = $date_to; $types .= "s"; }

$count_query = "SELECT COUNT(*) as total FROM calls c WHERE $where_base";
$stmt_count = $conn->prepare($count_query);
if ($params) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_calls = $stmt_count->get_result()->fetch_assoc()['total'];

$offset = $page * $per_page;
$query = "SELECT c.*, p.name as person_name, p.type as person_type, p.address as person_address, u.username as agent_name
    FROM calls c LEFT JOIN persons p ON c.caller_number = p.phone LEFT JOIN users u ON c.agent_id = u.id
    WHERE $where_base ORDER BY c.start_time DESC LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$calls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stats = ['total' => 0, 'answered' => 0, 'missed' => 0, 'successful' => 0, 'problem' => 0, 'need_action' => 0, 'urgent' => 0, 'failed' => 0];

foreach ($calls as &$call) {
    $stats['total']++;
    if (stripos($call['status'], 'answer') !== false) $stats['answered']++; else $stats['missed']++;
    $mark = $call['call_mark'] ?? '';
    if ($mark && isset($stats[$mark])) $stats[$mark]++;
    
    if ($show_logs_screen) {
        $logs = $conn->query("SELECT l.*, u.username FROM logs l JOIN users u ON l.agent_id = u.id WHERE l.call_id = {$call['id']} AND l.status = 'active' ORDER BY l.created_at");
        $call['logs'] = [];
        while ($log = $logs->fetch_assoc()) {
            if ($log['parent_id']) {
                foreach ($call['logs'] as &$pl) { if ($pl['id'] == $log['parent_id']) { $pl['replies'][] = $log; break; } }
            } else { $log['replies'] = []; $call['logs'][] = $log; }
        }
    }
}
unset($call);

$status_options = ['answered' => 'Answered', 'missed' => 'Missed'];
$mark_options = ['successful' => 'Successful', 'problem' => 'Problem', 'need_action' => 'Need Action', 'urgent' => 'Urgent', 'failed' => 'Failed'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Call Report - PBX Manager</title>
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
        
        .nav-links { display: flex; gap: 8px; }
        .nav-links a { padding: 8px 14px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 8px; font-size: 13px; text-decoration: none; }
        .nav-links a:hover { background: var(--bg-card-hover); color: var(--text-primary); }
        
        .filters-bar { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; margin: 15px 20px; }
        .filters-bar form { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; min-width: 140px; }
        .filter-group label { font-size: 10px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-group input, .filter-group select { padding: 8px 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 12px; }
        .filter-group select[multiple] { height: 65px; }
        
        .filters-row { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; align-items: center; }
        .filters-row label { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text-secondary); cursor: pointer; }
        .filters-row input[type="checkbox"] { accent-color: var(--accent-primary); }
        
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
        
        .table-container { margin: 0 20px 20px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .data-table th { background: var(--bg-secondary); padding: 12px 14px; text-align: left; font-size: 10px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        .data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-color); font-size: 12px; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tbody tr:hover { background: rgba(99, 102, 241, 0.05); }
        
        .dir-in { color: var(--accent-success); font-weight: 600; }
        .dir-out { color: var(--accent-danger); font-weight: 600; }
        .contact-name { font-weight: 600; }
        .contact-phone { font-family: 'Consolas', monospace; color: var(--text-secondary); font-size: 12px; }
        .num { font-weight: 600; text-align: center; }
        .num-s { color: var(--accent-success); }
        .num-w { color: var(--accent-warning); }
        .num-d { color: var(--accent-danger); }
        
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 500; }
        .badge-a { background: rgba(16, 185, 129, 0.15); color: var(--accent-success); }
        .badge-m { background: rgba(239, 68, 68, 0.15); color: var(--accent-danger); }
        .badge-s { background: rgba(16, 185, 129, 0.15); color: var(--accent-success); }
        .badge-p { background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); }
        .badge-n { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
        .badge-u { background: rgba(239, 68, 68, 0.15); color: var(--accent-danger); }
        
        .log-preview { cursor: pointer; color: var(--accent-info); font-size: 11px; }
        .log-preview:hover { text-decoration: underline; }
        .expanded-logs { background: var(--bg-secondary); padding: 10px 15px; margin-top: 5px; border-radius: 6px; display: none; }
        .expanded-logs.show { display: block; }
        .log-item { background: var(--bg-card); border: 1px solid var(--border-color); border-left: 3px solid var(--accent-info); border-radius: 6px; padding: 8px 10px; margin-bottom: 6px; font-size: 11px; }
        .log-item.closed { border-left-color: var(--accent-success); }
        .log-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .log-agent { font-weight: 600; color: var(--accent-info); }
        .log-status { font-size: 9px; padding: 2px 6px; border-radius: 4px; background: var(--bg-secondary); color: var(--text-secondary); }
        .log-notes { color: var(--text-secondary); margin-top: 4px; line-height: 1.4; }
        .reply-item { background: var(--bg-secondary); padding: 5px 8px; margin-top: 4px; margin-left: 10px; border-radius: 4px; font-size: 10px; border-left: 2px solid var(--border-color); }
        
        .action-btn { padding: 5px 10px; border-radius: 6px; font-size: 11px; background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .action-btn:hover { background: rgba(245, 158, 11, 0.25); }
        
        .pagination { display: flex; justify-content: center; gap: 5px; padding: 20px; }
        .pagination a { padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-secondary); text-decoration: none; font-size: 12px; }
        .pagination a:hover { background: var(--bg-card-hover); }
        .pagination a.active { background: var(--accent-primary); color: white; border-color: var(--accent-primary); }
        
        .no-data { text-align: center; padding: 50px; color: var(--text-muted); }
        
        @media print {
            @page { size: A4 landscape; margin: 10px; }
            body { background: white; color: black; font-size: 9px; }
            *, *::before, *::after { background: transparent !important; color: black !important; border-color: #ccc !important; box-shadow: none !important; }
            .no-print, .fixed-header, .filters-bar, .toolbar, .selection-bar, .pagination, .action-btn, .nav-links, .header-actions, .btn, .modal, input, select, textarea, .badge, .expanded-logs, .log-preview { display: none !important; }
            .table-container { border: none; margin: 0; padding: 0; }
            .data-table { width: 100%; min-width: auto; }
            .data-table th { background: #eee !important; color: black !important; padding: 4px 6px; border: 1px solid #ccc; font-weight: bold; }
            .data-table td { padding: 4px 6px; border: 1px solid #ccc; color: black !important; }
            .stat-row, .stat-item { display: inline-block; margin-right: 15px; }
            h1, .contact-name, .contact-phone, .dir-in, .dir-out, .num { color: black !important; }
        }
    </style>
</head>
<body>
    <div class="fixed-header">
        <div class="header-top">
            <h1><i class="fas fa-file-alt"></i> Call Report <span style="font-size:12px;color:var(--text-muted);font-weight:400;"><?= $date_from ?> to <?= $date_to ?></span></h1>
            <div class="header-actions">
                <div class="nav-links">
                    <a href="agent.php">&#8592; Dashboard</a>
                    <a href="contacts.php">Contacts</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="filters-bar no-print">
        <form method="GET">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?= $date_from ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?= $date_to ?>">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status[]" multiple>
                    <?php foreach ($status_options as $val => $label): ?>
                    <option value="<?= $val ?>" <?= in_array($val, $status_filter) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Marking</label>
                <select name="mark[]" multiple>
                    <?php foreach ($mark_options as $val => $label): ?>
                    <option value="<?= $val ?>" <?= in_array($val, $mark_filter) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Name, phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label>Per Page</label>
                <select name="per_page">
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= $per_page == 200 ? 'selected' : '' ?>>200</option>
                    <option value="500" <?= $per_page == 500 ? 'selected' : '' ?>>500</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </div>
            <div class="filters-row">
                <label><input type="checkbox" name="show_logs" value="1" <?= $show_logs_screen ? 'checked' : '' ?>> Show Logs</label>
                <label><input type="checkbox" name="show_replies" value="1" <?= $show_replies ? 'checked' : '' ?>> Show Replies</label>
            </div>
        </form>
    </div>
    
    <div class="stats-row no-print">
        <div class="stat-item"><div class="label">Total</div><div class="value"><?= $stats['total'] ?></div></div>
        <div class="stat-item"><div class="label">Ans</div><div class="value s"><?= $stats['answered'] ?></div></div>
        <div class="stat-item"><div class="label">Miss</div><div class="value d"><?= $stats['missed'] ?></div></div>
        <div class="stat-item"><div class="label">Succ</div><div class="value s"><?= $stats['successful'] ?></div></div>
        <div class="stat-item"><div class="label">Prob</div><div class="value w"><?= $stats['problem'] ?></div></div>
        <div class="stat-item"><div class="label">Urgent</div><div class="value d"><?= $stats['urgent'] ?></div></div>
    </div>
    
    <div class="toolbar no-print">
        <div class="toolbar-left">
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            <button class="btn btn-secondary btn-sm" onclick="copySelected()"><i class="fas fa-copy"></i> Copy</button>
            <select id="csvExport" onchange="exportCSV(this.value); this.value='';">
                <option value="">Export CSV...</option>
                <option value="basic">Basic (Date, Contact, Status)</option>
                <option value="full">Full Details</option>
            </select>
        </div>
        <div class="toolbar-right">
            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="width:16px;height:16px;accent-color:var(--accent-primary);">
            <span style="font-size:12px;color:var(--text-muted);">Select All (<span id="selCount">0</span>)</span>
        </div>
    </div>

    <?php if (empty($calls)): ?>
        <div class="table-container"><div class="no-data">No calls found.</div></div>
    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:30px;" class="no-print"><input type="checkbox" onchange="toggleAll(this)"></th>
                        <th>Date/Time</th>
                        <th>Dir</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Contact</th>
                        <th style="text-align:center;">Dur</th>
                        <th>Status</th>
                        <th>Mark</th>
                        <?php if ($is_admin): ?>
                        <th>Agent</th>
                        <?php endif; ?>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calls as $call):
                        $is_outbound = strtolower($call['direction']) === 'outbound';
                        $is_answered = stripos($call['status'], 'answer') !== false;
                        $is_unknown = empty($call['person_name']) || $call['person_name'] === $call['caller_number'];
                        
                        if ($search && !stripos($call['person_name'] ?? '', $search) && !stripos($call['caller_number'], $search)) continue;
                        if (!empty($status_filter)) {
                            $match = false;
                            foreach ($status_filter as $sf) { if ($sf === 'answered' && $is_answered) $match = true; if ($sf === 'missed' && !$is_answered) $match = true; }
                            if (!$match) continue;
                        }
                        if (!empty($mark_filter) && !in_array($call['call_mark'], $mark_filter)) continue;
                        
                        $has_logs = $show_logs_screen && !empty($call['logs']);
                    ?>
                    <tr data-id="<?= $call['id'] ?>">
                        <td class="no-print"><input type="checkbox" class="row-check" onchange="updateSel()"></td>
                        <td><?= date('d M, H:i', strtotime($call['start_time'])) ?></td>
                        <td><?= $is_outbound ? '<span class="dir-out">OUT</span>' : '<span class="dir-in">IN</span>' ?></td>
                        <td class="contact-phone"><?= htmlspecialchars($call['caller_number']) ?></td>
                        <td class="contact-phone"><?= htmlspecialchars($call['destination'] ?: ($is_outbound ? $call['caller_number'] : '-')) ?></td>
                        <td class="contact-name"><?= $is_unknown ? '<span style="color:var(--text-muted)">Unknown</span>' : htmlspecialchars($call['person_name']) ?></td>
                        <td class="num"><?= htmlspecialchars($call['duration'] ?: '-') ?></td>
                        <td><span class="badge badge-<?= $is_answered ? 'a' : 'm' ?>"><?= htmlspecialchars($call['status']) ?></span></td>
                        <td><?php if ($call['call_mark']): $mk = substr($call['call_mark'], 0, 1); ?><span class="badge badge-<?= $mk ?>"><?= ucwords(str_replace('_', ' ', $call['call_mark'])) ?></span><?php endif; ?></td>
                        <?php if ($is_admin): ?>
                        <td><?= htmlspecialchars(substr($call['agent_name'] ?? '-', 0, 10)) ?></td>
                        <?php endif; ?>
                        <td class="no-print">
                            <?php if ($has_logs): ?>
                            <span class="log-preview" onclick="toggleLogs(<?= $call['id'] ?>)">Logs</span>
                            <?php endif; ?>
                            <a href="agent.php?phone=<?= urlencode($call['caller_number']) ?>" class="action-btn"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                    <?php if ($has_logs): ?>
                    <tr class="no-print">
                        <td colspan="<?= $is_admin ? 11 : 10 ?>" style="padding:0 14px;">
                            <div class="expanded-logs" id="logs-<?= $call['id'] ?>">
                                <?php foreach ($call['logs'] as $log): $ls = $log['log_status'] ?? 'open'; ?>
                                <div class="log-item <?= $ls ?>">
                                    <div class="log-header">
                                        <span class="log-agent"><?= htmlspecialchars($log['username']) ?></span>
                                        <span class="log-status"><?= strtoupper($ls) ?></span>
                                    </div>
                                    <?php if ($log['notes']): ?>
                                    <div class="log-notes"><?= nl2br(htmlspecialchars($log['notes'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($show_replies && !empty($log['replies'])): ?>
                                    <?php foreach ($log['replies'] as $reply): ?>
                                    <div class="reply-item">
                                        <strong><?= htmlspecialchars($reply['username']) ?></strong>: <?= htmlspecialchars(substr($reply['notes'], 0, 80)) ?>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="pagination no-print">
            <?php if ($page > 0): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
            <?php endif; ?>
            <?php for ($p = max(0, $page - 2); $p <= min(ceil($total_calls / $per_page) - 1, $page + 2); $p++): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="<?= $p == $page ? 'active' : '' ?>"><?= $p + 1 ?></a>
            <?php endfor; ?>
            <?php if (($page + 1) * $per_page < $total_calls): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
    const stats = <?= json_encode($stats) ?>;
    const dateRange = '<?= $date_from ?> to <?= $date_to ?>';
    
    function toggleAll(cb) {
        document.querySelectorAll('.row-check').forEach(c => c.checked = cb.checked);
        document.getElementById('selectAll').checked = cb.checked;
        updateSel();
    }
    
    function updateSel() {
        const checked = document.querySelectorAll('.row-check:checked');
        document.getElementById('selCount').textContent = checked.length;
    }
    
    function toggleLogs(id) {
        document.getElementById('logs-' + id).classList.toggle('show');
    }
    
    function copySelected() {
        const rows = document.querySelectorAll('.row-check:checked');
        if (!rows.length) { alert('Select calls first'); return; }
        
        let text = `CALL REPORT - ${dateRange}\n`;
        text += `Total: ${stats.total} | Ans: ${stats.answered} | Miss: ${stats.missed}\n`;
        text += '='.repeat(40) + '\n';
        
        rows.forEach(cb => {
            const row = cb.closest('tr');
            const cells = row.querySelectorAll('td');
            const datetime = cells[1].innerText;
            const dir = cells[2].innerText;
            const from = cells[3].innerText;
            const to = cells[4].innerText;
            const contact = cells[5].innerText;
            const status = cells[7].innerText;
            const mark = cells[8].innerText;
            text += `${datetime} | ${dir} | ${from} | ${contact}\n`;
        });
        
        navigator.clipboard.writeText(text);
    }
    
    function exportCSV(opt) {
        if (!opt) return;
        const rows = document.querySelectorAll('tr[data-id]');
        let csv = opt === 'basic' ? 'DateTime,Dir,From,To,Contact,Status\n' : 'DateTime,Dir,From,To,Contact,Dur,Status,Mark,Agent\n';
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (opt === 'basic') {
                csv += `"${cells[1].innerText}","${cells[2].innerText}","${cells[3].innerText}","${cells[4].innerText}","${cells[5].innerText}","${cells[7].innerText}"\n`;
            } else {
                csv += `"${cells[1].innerText}","${cells[2].innerText}","${cells[3].innerText}","${cells[4].innerText}","${cells[5].innerText}","${cells[6].innerText}","${cells[7].innerText}","${cells[8].innerText}","${cells[9]?.innerText || ''}"\n`;
            }
        });
        
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv'}));
        a.download = 'calls_<?= $date_from ?>.csv';
        a.click();
    }
    </script>
</body>
</html>
