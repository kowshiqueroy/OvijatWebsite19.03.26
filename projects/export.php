<?php
/**
 * CSV Export endpoint.
 * Usage:
 *   /export.php?type=tasks[&project_id=N&status=X&priority=X&q=X]
 *   /export.php?type=timelogs[&project_id=N&user_id=N]
 *   /export.php?type=meetings[&project_id=N]
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireRole('member');
$user = currentUser();

$type = $_GET['type'] ?? '';

function csvRow(array $cols): string {
    return implode(',', array_map(function ($v) {
        $v = str_replace('"', '""', $v ?? '');
        return '"' . $v . '"';
    }, $cols)) . "\r\n";
}

function sendCsv(string $filename, array $headers, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
    echo csvRow($headers);
    foreach ($rows as $r) echo csvRow($r);
    exit;
}

/* ── Tasks ────────────────────────────────────────────── */
if ($type === 'tasks') {
    $filterStatus   = $_GET['status'] ?? '';
    $filterPriority = $_GET['priority'] ?? '';
    $filterProject  = (int)($_GET['project_id'] ?? 0);
    $search         = trim($_GET['q'] ?? '');

    $where  = ['1=1'];
    $params = [];

    if ($user['role'] !== 'admin') {
        $where[]  = '(ta.user_id=? OR t.created_by=?)';
        $params[] = $user['id']; $params[] = $user['id'];
    }
    if ($filterStatus)   { $where[] = 't.status=?';      $params[] = $filterStatus; }
    if ($filterPriority) { $where[] = 't.priority=?';    $params[] = $filterPriority; }
    if ($filterProject)  { $where[] = 't.project_id=?';  $params[] = $filterProject; }
    if ($search)         { $where[] = 't.title LIKE ?';  $params[] = "%$search%"; }

    $tasks = dbFetchAll(
        "SELECT DISTINCT t.id, t.title, p.name as project, t.status, t.priority,
                t.due_date, t.estimated_hours, u.full_name as created_by, t.created_at
         FROM tasks t
         LEFT JOIN task_assignees ta ON ta.task_id=t.id
         JOIN projects p ON p.id=t.project_id
         JOIN users u ON u.id=t.created_by
         WHERE " . implode(' AND ', $where) . "
         ORDER BY t.project_id, t.status, t.priority DESC",
        $params
    );

    // Assignees per task
    $ids = array_column($tasks, 'id');
    $assigneeMap = [];
    if ($ids) {
        $phs  = implode(',', array_fill(0, count($ids), '?'));
        $rows = dbFetchAll("SELECT ta.task_id, u.full_name FROM task_assignees ta JOIN users u ON u.id=ta.user_id WHERE ta.task_id IN ($phs)", $ids);
        foreach ($rows as $r) $assigneeMap[$r['task_id']][] = $r['full_name'];
    }

    $rows = [];
    foreach ($tasks as $t) {
        $rows[] = [
            $t['id'],
            $t['title'],
            $t['project'],
            ucwords(str_replace('_', ' ', $t['status'])),
            ucfirst($t['priority']),
            $t['due_date'] ?? '',
            $t['estimated_hours'] ?? '',
            implode('; ', $assigneeMap[$t['id']] ?? []),
            $t['created_by'],
            $t['created_at'],
        ];
    }

    sendCsv('tasks_' . date('Y-m-d') . '.csv',
        ['ID', 'Title', 'Project', 'Status', 'Priority', 'Due Date', 'Est. Hours', 'Assignees', 'Created By', 'Created At'],
        $rows
    );
}

/* ── Time Logs ────────────────────────────────────────── */
if ($type === 'timelogs') {
    $filterProject = (int)($_GET['project_id'] ?? 0);
    $filterUser    = (int)($_GET['user_id'] ?? 0);

    $where  = ['1=1'];
    $params = [];

    if ($user['role'] !== 'admin') {
        $where[]  = 'tl.user_id=?';
        $params[] = $user['id'];
    }
    if ($filterProject) { $where[] = 't.project_id=?'; $params[] = $filterProject; }
    if ($filterUser)    { $where[] = 'tl.user_id=?';   $params[] = $filterUser; }

    $logs = dbFetchAll(
        "SELECT tl.logged_at, u.full_name as user, p.name as project,
                t.title as task, tl.hours, tl.note
         FROM task_time_logs tl
         JOIN tasks t ON t.id=tl.task_id
         JOIN projects p ON p.id=t.project_id
         JOIN users u ON u.id=tl.user_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY tl.logged_at DESC",
        $params
    );

    $rows = [];
    foreach ($logs as $l) {
        $rows[] = [$l['logged_at'], $l['user'], $l['project'], $l['task'], $l['hours'], $l['note'] ?? ''];
    }

    sendCsv('timelogs_' . date('Y-m-d') . '.csv',
        ['Date', 'User', 'Project', 'Task', 'Hours', 'Note'],
        $rows
    );
}

/* ── Meetings ─────────────────────────────────────────── */
if ($type === 'meetings') {
    $filterProject = (int)($_GET['project_id'] ?? 0);

    $where  = ['1=1'];
    $params = [];

    if ($user['role'] !== 'admin') {
        $where[]  = 'EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id=m.project_id AND pm.user_id=?)';
        $params[] = $user['id'];
    }
    if ($filterProject) { $where[] = 'm.project_id=?'; $params[] = $filterProject; }

    $meetings = dbFetchAll(
        "SELECT m.meeting_date, m.title, p.name as project, m.status,
                m.location, m.description
         FROM meetings m
         JOIN projects p ON p.id=m.project_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY m.meeting_date DESC",
        $params
    );

    $rows = [];
    foreach ($meetings as $m) {
        $rows[] = [
            $m['meeting_date'], $m['title'], $m['project'],
            ucfirst($m['status']), $m['location'] ?? '', $m['description'] ?? '',
        ];
    }

    sendCsv('meetings_' . date('Y-m-d') . '.csv',
        ['Date', 'Title', 'Project', 'Status', 'Location', 'Description'],
        $rows
    );
}

// Invalid type
http_response_code(400);
echo 'Invalid export type. Use ?type=tasks, ?type=timelogs, or ?type=meetings';
