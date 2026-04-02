<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../flash.php';

requireRole('member');
$user = currentUser();
validateCsrf();

header('Content-Type: application/json');

function ok($data = [], string $msg = ''): never {
    echo json_encode(['success' => true, 'data' => $data, 'message' => $msg]);
    exit;
}
function fail(string $msg = 'Error', int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'data' => [], 'message' => $msg]);
    exit;
}

$action    = $_POST['action'] ?? $_GET['action'] ?? '';
$projectId = (int)($_POST['project_id'] ?? $_GET['project_id'] ?? 0);

// Validate project access
function checkProjectAccess(int $pid, array $user): array {
    if (!$pid) fail('project_id required');
    $p = dbFetch("SELECT * FROM projects WHERE id=?", [$pid]);
    if (!$p) fail('Project not found', 404);
    if ($user['role'] !== 'admin') {
        $isMember = dbFetch("SELECT id FROM project_members WHERE project_id=? AND user_id=?", [$pid, $user['id']]);
        if (!$isMember) fail('Access denied', 403);
    }
    return $p;
}

switch ($action) {

case 'get_project_data': {
    $p = checkProjectAccess($projectId, $user);
    $tasks    = getTasksData($projectId);
    $chat     = getChatData($projectId, $user['id'], 0);
    $pinnedChat = getPinnedChatData($projectId, $user['id']);
    $feed     = getFeedData($projectId, $user['id'], 0);
    $meetings = getMeetingsData($projectId, $user['id']);
    $timelog  = getTimelogData($projectId);
    $files    = getFilesData($projectId);
    $members  = dbFetchAll("SELECT u.id, u.full_name, u.username, pm.role as proj_role FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=?", [$projectId]);
    ok(['project' => $p, 'tasks' => $tasks, 'chat' => $chat, 'pinned_chat' => $pinnedChat, 'feed' => $feed, 'meetings' => $meetings, 'timelog' => $timelog, 'files' => $files, 'members' => $members]);
}

case 'get_tasks': {
    checkProjectAccess($projectId, $user);
    ok(getTasksData($projectId));
}

case 'add_task': {
    checkProjectAccess($projectId, $user);
    $title    = trim($_POST['title'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $due      = $_POST['due_date'] ?? null;
    $assign   = (int)($_POST['assignee_id'] ?? 0);
    if (!$title) fail('Title required');
    $id = dbInsert('tasks', ['project_id' => $projectId, 'title' => $title, 'priority' => $priority, 'status' => 'todo', 'due_date' => $due ?: null, 'created_by' => $user['id']]);
    if ($assign) {
        try { dbInsert('task_assignees', ['task_id' => $id, 'user_id' => $assign]); } catch (Throwable $e) {}
        if ($assign !== $user['id']) {
            try { dbInsert('notifications', ['user_id' => $assign, 'type' => 'task_assigned', 'message' => $user['full_name'].' assigned you to "'.$title.'"', 'link' => '/modules/tasks/view.php?id='.$id, 'created_at' => date('Y-m-d H:i:s')]); } catch (Throwable $e) {}
        }
    }
    dbInsert('updates', ['project_id' => $projectId, 'user_id' => $user['id'], 'type' => 'task', 'message' => "Added task \"{$title}\"."]);
    ok(getTasksData($projectId), 'Task added');
}

case 'update_task_status': {
    checkProjectAccess($projectId, $user);
    $taskId = (int)($_POST['task_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['todo','in_progress','review','done'])) fail('Invalid status');
    $task = dbFetch("SELECT * FROM tasks WHERE id=? AND project_id=?", [$taskId, $projectId]);
    if (!$task) fail('Task not found', 404);
    dbUpdate('tasks', ['status' => $status], ['id' => $taskId]);
    ok(getTasksData($projectId), 'Status updated');
}

case 'get_chat': {
    checkProjectAccess($projectId, $user);
    $since  = (int)($_POST['last_id'] ?? $_GET['last_id'] ?? 0);
    $before = (int)($_POST['before_id'] ?? $_GET['before_id'] ?? 0);
    $chat = getChatData($projectId, $user['id'], $since, $before);
    $pinned = getPinnedChatData($projectId, $user['id']);
    ok(['chat' => $chat, 'pinned_chat' => $pinned]);
}

case 'send_chat': {
    checkProjectAccess($projectId, $user);
    $body        = trim($_POST['body'] ?? '');
    $recipientId = (int)($_POST['recipient_id'] ?? 0) ?: null;
    $parentId    = (int)($_POST['parent_id'] ?? 0) ?: null;
    if (!$body) fail('Message required');
    $id = dbInsert('worksheet_chat', [
        'project_id'   => $projectId,
        'user_id'      => $user['id'],
        'recipient_id' => $recipientId,
        'parent_id'    => $parentId,
        'body'         => $body
    ]);
    // Detect @mentions → notify
    preg_match_all('/@(\w+)/', $body, $m);
    foreach (array_unique($m[1]) as $uname) {
        $mentioned = dbFetch("SELECT id FROM users WHERE username=? OR LOWER(REPLACE(full_name,' ','_'))=LOWER(?)", [$uname, $uname]);
        if ($mentioned && (int)$mentioned['id'] !== $user['id']) {
            try { dbInsert('notifications', ['user_id' => $mentioned['id'], 'type' => 'mention', 'message' => $user['full_name'].' mentioned you in a message', 'link' => '/worksheet/index.php?project_id='.$projectId, 'created_at' => date('Y-m-d H:i:s')]); } catch (Throwable $e) {}
        }
    }
    ok(['chat' => getChatData($projectId, $user['id'], 0), 'pinned_chat' => getPinnedChatData($projectId, $user['id'])], 'Sent');
}

case 'like_chat': {
    checkProjectAccess($projectId, $user);
    $chatId = (int)($_POST['chat_id'] ?? 0);
    if (!$chatId) fail('chat_id required');
    
    $exists = dbFetch("SELECT 1 FROM chat_likes WHERE chat_id=? AND user_id=?", [$chatId, $user['id']]);
    if ($exists) {
        dbQuery("DELETE FROM chat_likes WHERE chat_id=? AND user_id=?", [$chatId, $user['id']]);
        dbQuery("UPDATE worksheet_chat SET likes_count = GREATEST(0, likes_count - 1) WHERE id=?", [$chatId]);
    } else {
        dbQuery("INSERT IGNORE INTO chat_likes (chat_id, user_id) VALUES (?, ?)", [$chatId, $user['id']]);
        dbQuery("UPDATE worksheet_chat SET likes_count = likes_count + 1 WHERE id=?", [$chatId]);
    }
    ok(['chat' => getChatData($projectId, $user['id'], 0), 'pinned_chat' => getPinnedChatData($projectId, $user['id'])], 'Liked/Unliked');
}

case 'pin_chat': {
    checkProjectAccess($projectId, $user);
    $chatId = (int)($_POST['chat_id'] ?? 0);
    if (!$chatId) fail('chat_id required');
    
    $c = dbFetch("SELECT is_pinned FROM worksheet_chat WHERE id=?", [$chatId]);
    if (!$c) fail('Not found', 404);
    
    dbUpdate('worksheet_chat', ['is_pinned' => $c['is_pinned'] ? 0 : 1], ['id' => $chatId]);
    ok(['chat' => getChatData($projectId, $user['id'], 0), 'pinned_chat' => getPinnedChatData($projectId, $user['id'])], 'Pinned/Unpinned');
}

case 'get_feed': {
    checkProjectAccess($projectId, $user);
    $since = (int)($_POST['last_id'] ?? $_GET['last_id'] ?? 0);
    ok(getFeedData($projectId, $user['id'], $since));
}

case 'mark_feed_read': {
    checkProjectAccess($projectId, $user);
    $ids = $_POST['ids'] ?? '';
    foreach (explode(',', $ids) as $uid) {
        $uid = (int)$uid;
        if ($uid) try { dbInsert('update_reads', ['update_id' => $uid, 'user_id' => $user['id'], 'read_at' => date('Y-m-d H:i:s')]); } catch (\Exception $e) {}
    }
    ok([], 'Marked read');
}

case 'pin_update': {
    if ($user['role'] !== 'admin') fail('Admin only', 403);
    checkProjectAccess($projectId, $user);
    $uid = (int)($_POST['update_id'] ?? 0);
    $u = dbFetch("SELECT is_pinned FROM updates WHERE id=?", [$uid]);
    if (!$u) fail('Not found', 404);
    dbUpdate('updates', ['is_pinned' => $u['is_pinned'] ? 0 : 1], ['id' => $uid]);
    ok(getFeedData($projectId, $user['id'], 0));
}

case 'get_meetings': {
    checkProjectAccess($projectId, $user);
    ok(getMeetingsData($projectId, $user['id']));
}

case 'add_action_item': {
    checkProjectAccess($projectId, $user);
    $mid  = (int)($_POST['meeting_id'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $assign = (int)($_POST['assigned_to'] ?? 0) ?: null;
    $due    = $_POST['due_date'] ?? null;
    if (!$mid || !$desc) fail('Meeting and description required');
    dbInsert('meeting_action_items', ['meeting_id' => $mid, 'description' => $desc, 'assigned_to' => $assign, 'due_date' => $due ?: null, 'created_at' => date('Y-m-d H:i:s')]);
    ok(getMeetingsData($projectId, $user['id']), 'Action item added');
}

case 'complete_action_item': {
    checkProjectAccess($projectId, $user);
    $aid = (int)($_POST['action_id'] ?? 0);
    $ai = dbFetch("SELECT ai.* FROM meeting_action_items ai JOIN meetings m ON m.id=ai.meeting_id WHERE ai.id=? AND m.project_id=?", [$aid, $projectId]);
    if (!$ai) fail('Not found', 404);
    dbUpdate('meeting_action_items', ['is_done' => $ai['is_done'] ? 0 : 1], ['id' => $aid]);
    ok(getMeetingsData($projectId, $user['id']));
}

case 'generate_task': {
    checkProjectAccess($projectId, $user);
    $aid = (int)($_POST['action_id'] ?? 0);
    $ai = dbFetch("SELECT ai.* FROM meeting_action_items ai JOIN meetings m ON m.id=ai.meeting_id WHERE ai.id=? AND m.project_id=?", [$aid, $projectId]);
    if (!$ai) fail('Not found', 404);
    if ($ai['task_id']) fail('Task already exists');
    
    $taskId = dbInsert('tasks', [
        'project_id' => $projectId,
        'title' => $ai['description'],
        'status' => 'todo',
        'priority' => 'medium',
        'due_date' => $ai['due_date'],
        'created_by' => $user['id']
    ]);
    if ($ai['assigned_to']) {
        dbInsert('task_assignees', ['task_id' => $taskId, 'user_id' => $ai['assigned_to']]);
    }
    dbUpdate('meeting_action_items', ['task_id' => $taskId], ['id' => $aid]);
    dbInsert('updates', ['project_id' => $projectId, 'user_id' => $user['id'], 'type' => 'task', 'message' => "Generated task from meeting action item: \"{$ai['description']}\"."]);
    ok(getMeetingsData($projectId, $user['id']), 'Task generated');
}

case 'get_timelog': {
    checkProjectAccess($projectId, $user);
    ok(getTimelogData($projectId));
}

case 'add_timelog': {
    checkProjectAccess($projectId, $user);
    $taskId = (int)($_POST['task_id'] ?? 0);
    $hours  = (float)($_POST['hours'] ?? 0);
    $note   = trim($_POST['note'] ?? '');
    $date   = $_POST['logged_at'] ?? date('Y-m-d');
    if (!$taskId || $hours <= 0) fail('Task and hours required');
    $task = dbFetch("SELECT id FROM tasks WHERE id=? AND project_id=?", [$taskId, $projectId]);
    if (!$task) fail('Task not found', 404);
    dbInsert('task_time_logs', ['task_id' => $taskId, 'user_id' => $user['id'], 'hours' => $hours, 'note' => $note ?: null, 'logged_at' => $date]);
    ok(getTimelogData($projectId), 'Time logged');
}

case 'get_files': {
    checkProjectAccess($projectId, $user);
    ok(getFilesData($projectId));
}

case 'add_file': {
    checkProjectAccess($projectId, $user);
    $label  = trim($_POST['label'] ?? '');
    $url    = trim($_POST['url'] ?? '');
    $taskId = (int)($_POST['task_id'] ?? 0) ?: null;
    if (!$label || !$url) fail('Label and URL required');
    if (!$taskId) fail('Please select a task to link this file to');
    $task = dbFetch("SELECT id FROM tasks WHERE id=? AND project_id=?", [$taskId, $projectId]);
    if (!$task) fail('Task not found', 404);
    dbInsert('task_attachments', ['task_id' => $taskId, 'user_id' => $user['id'], 'label' => $label, 'url' => $url]);
    ok(getFilesData($projectId), 'File link added');
}

case 'delete_file': {
    checkProjectAccess($projectId, $user);
    $fid = (int)($_POST['file_id'] ?? 0);
    $f = dbFetch("SELECT ta.* FROM task_attachments ta JOIN tasks t ON t.id=ta.task_id WHERE ta.id=? AND t.project_id=?", [$fid, $projectId]);
    if (!$f) fail('Not found', 404);
    if ($user['role'] !== 'admin' && $f['user_id'] !== $user['id']) fail('Access denied', 403);
    dbQuery("DELETE FROM task_attachments WHERE id=?", [$fid]);
    ok(getFilesData($projectId), 'Deleted');
}

case 'get_mentions': {
    checkProjectAccess($projectId, $user);
    $username = $user['username'] ?? strtolower(str_replace(' ','_',$user['full_name']));
    $pattern = '@' . $username;
    $mentions = dbFetchAll(
        "SELECT wc.*, u.full_name, pwc.body as parent_body, pu.full_name as parent_name
         FROM worksheet_chat wc 
         JOIN users u ON u.id=wc.user_id 
         LEFT JOIN worksheet_chat pwc ON pwc.id=wc.parent_id
         LEFT JOIN users pu ON pu.id=pwc.user_id
         WHERE wc.project_id=? AND wc.body LIKE ? 
         ORDER BY wc.created_at DESC LIMIT 50",
        [$projectId, "%$pattern%"]
    );
    ok($mentions);
}

case 'update_project_details': {
    $p = checkProjectAccess($projectId, $user);
    
    // Allow if admin OR project lead
    $isLead = false;
    if ($user['role'] !== 'admin') {
        $member = dbFetch("SELECT role FROM project_members WHERE project_id=? AND user_id=?", [$projectId, $user['id']]);
        if ($member && $member['role'] === 'lead') $isLead = true;
    }
    
    if ($user['role'] !== 'admin' && !$isLead) fail('Access denied', 403);
    
    $name   = trim($_POST['name'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'planning';
    $client = trim($_POST['client_name'] ?? '');
    $start  = $_POST['start_date'] ?: null;
    $due    = $_POST['due_date'] ?: null;
    $tech   = trim($_POST['tech_notes'] ?? '');

    if (!$name) fail('Project name is required');

    $updateData = [
        'name' => $name, 
        'description' => $desc ?: null, 
        'status' => $status,
        'client_name' => $client ?: null, 
        'start_date' => $start, 
        'due_date' => $due,
        'tech_notes' => $tech ?: null,
    ];

    try {
        dbUpdate('projects', $updateData, ['id' => $projectId]);
        $updated = dbFetch("SELECT * FROM projects WHERE id=?", [$projectId]);
        ok($updated, 'Project details updated');
    } catch (\Exception $e) {
        fail('Update failed: ' . $e->getMessage());
    }
}

case 'get_task_detail': {
    checkProjectAccess($projectId, $user);
    $taskId = (int)($_POST['task_id'] ?? 0);
    $task = dbFetch(
        "SELECT t.*, p.name as project_name, u.full_name as creator_name
         FROM tasks t JOIN projects p ON p.id=t.project_id JOIN users u ON u.id=t.created_by
         WHERE t.id=? AND t.project_id=?",
        [$taskId, $projectId]
    );
    if (!$task) fail('Task not found', 404);

    $assignees = dbFetchAll("SELECT u.id, u.full_name FROM task_assignees ta JOIN users u ON u.id=ta.user_id WHERE ta.task_id=?", [$taskId]);
    $task['assignees'] = array_map(function($a) {
        $words = explode(' ', trim($a['full_name']));
        $initials = '';
        foreach ($words as $w) if (!empty($w)) $initials .= strtoupper($w[0]);
        return ['id' => $a['id'], 'full_name' => $a['full_name'], 'initials' => $initials];
    }, $assignees);

    $task['subtasks']  = dbFetchAll("SELECT * FROM tasks WHERE parent_task_id=? ORDER BY status='done', priority DESC", [$taskId]);
    $task['comments']  = dbFetchAll("SELECT tc.*, u.full_name FROM task_comments tc JOIN users u ON u.id=tc.user_id WHERE tc.task_id=? ORDER BY tc.created_at ASC", [$taskId]);
    $task['time_logs'] = dbFetchAll("SELECT tl.*, u.full_name FROM task_time_logs tl JOIN users u ON u.id=tl.user_id WHERE tl.task_id=? ORDER BY tl.logged_at DESC LIMIT 20", [$taskId]);
    ok($task);
}

case 'save_task_description': {
    checkProjectAccess($projectId, $user);
    $taskId = (int)($_POST['task_id'] ?? 0);
    $desc   = trim($_POST['description'] ?? '');
    $task   = dbFetch("SELECT id FROM tasks WHERE id=? AND project_id=?", [$taskId, $projectId]);
    if (!$task) fail('Task not found', 404);
    dbUpdate('tasks', ['description' => $desc ?: null], ['id' => $taskId]);
    ok([], 'Saved');
}

case 'add_task_comment': {
    checkProjectAccess($projectId, $user);
    $taskId = (int)($_POST['task_id'] ?? 0);
    $body   = trim($_POST['body'] ?? '');
    if (!$body) fail('Comment required');
    $task = dbFetch("SELECT id, title FROM tasks WHERE id=? AND project_id=?", [$taskId, $projectId]);
    if (!$task) fail('Task not found', 404);
    dbInsert('task_comments', ['task_id' => $taskId, 'user_id' => $user['id'], 'body' => $body, 'created_at' => date('Y-m-d H:i:s')]);
    // Notify assignees
    $others = dbFetchAll("SELECT user_id FROM task_assignees WHERE task_id=? AND user_id!=?", [$taskId, $user['id']]);
    foreach ($others as $o) {
        try { dbInsert('notifications', ['user_id' => $o['user_id'], 'type' => 'comment', 'message' => $user['full_name'].' commented on "'.$task['title'].'"', 'link' => '/modules/tasks/view.php?id='.$taskId, 'created_at' => date('Y-m-d H:i:s')]); } catch (Throwable $e) {}
    }
    $comments = dbFetchAll("SELECT tc.*, u.full_name FROM task_comments tc JOIN users u ON u.id=tc.user_id WHERE tc.task_id=? ORDER BY tc.created_at ASC", [$taskId]);
    ok($comments, 'Comment added');
}

case 'add_subtask': {
    checkProjectAccess($projectId, $user);
    $parentId = (int)($_POST['task_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    if (!$title) fail('Title required');
    $parent = dbFetch("SELECT id FROM tasks WHERE id=? AND project_id=?", [$parentId, $projectId]);
    if (!$parent) fail('Parent task not found', 404);
    dbInsert('tasks', ['project_id' => $projectId, 'parent_task_id' => $parentId, 'title' => $title, 'status' => 'todo', 'priority' => 'medium', 'created_by' => $user['id']]);
    $subtasks = dbFetchAll("SELECT * FROM tasks WHERE parent_task_id=? ORDER BY status='done', priority DESC", [$parentId]);
    ok($subtasks, 'Subtask added');
}

case 'toggle_subtask': {
    checkProjectAccess($projectId, $user);
    $taskId   = (int)($_POST['task_id'] ?? 0);
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $task = dbFetch("SELECT status FROM tasks WHERE id=? AND project_id=?", [$taskId, $projectId]);
    if (!$task) fail('Not found', 404);
    dbUpdate('tasks', ['status' => $task['status'] === 'done' ? 'todo' : 'done'], ['id' => $taskId]);
    $subtasks = dbFetchAll("SELECT * FROM tasks WHERE parent_task_id=? ORDER BY status='done', priority DESC", [$parentId]);
    ok($subtasks);
}

case 'react_chat': {
    checkProjectAccess($projectId, $user);
    $chatId = (int)($_POST['chat_id'] ?? 0);
    $emoji  = $_POST['emoji'] ?? '';
    if (!in_array($emoji, ['👍','❤️','🔥','👀','🎉','😂'])) fail('Invalid emoji');
    if (!$chatId) fail('chat_id required');
    $exists = dbFetch("SELECT 1 FROM chat_reactions WHERE chat_id=? AND user_id=? AND emoji=?", [$chatId, $user['id'], $emoji]);
    if ($exists) {
        dbQuery("DELETE FROM chat_reactions WHERE chat_id=? AND user_id=? AND emoji=?", [$chatId, $user['id'], $emoji]);
    } else {
        try { dbInsert('chat_reactions', ['chat_id' => $chatId, 'user_id' => $user['id'], 'emoji' => $emoji]); } catch (Throwable $e) {}
    }
    $reactions   = dbFetchAll("SELECT emoji, COUNT(*) as cnt FROM chat_reactions WHERE chat_id=? GROUP BY emoji ORDER BY cnt DESC", [$chatId]);
    $myReactions = array_column(dbFetchAll("SELECT emoji FROM chat_reactions WHERE chat_id=? AND user_id=?", [$chatId, $user['id']]), 'emoji');
    ok(['reactions' => $reactions, 'my_reactions' => $myReactions, 'chat_id' => $chatId]);
}

case 'rsvp_meeting': {
    checkProjectAccess($projectId, $user);
    $meetingId = (int)($_POST['meeting_id'] ?? 0);
    $rsvp      = $_POST['rsvp'] ?? '';
    if (!in_array($rsvp, ['yes','no','maybe'])) fail('Invalid RSVP');
    $meeting = dbFetch("SELECT id FROM meetings WHERE id=? AND project_id=?", [$meetingId, $projectId]);
    if (!$meeting) fail('Meeting not found', 404);
    $existing = dbFetch("SELECT id FROM meeting_attendees WHERE meeting_id=? AND user_id=?", [$meetingId, $user['id']]);
    if ($existing) {
        dbUpdate('meeting_attendees', ['rsvp' => $rsvp], ['id' => $existing['id']]);
    } else {
        dbInsert('meeting_attendees', ['meeting_id' => $meetingId, 'user_id' => $user['id'], 'rsvp' => $rsvp]);
    }
    ok(getMeetingsData($projectId, $user['id']), 'RSVP updated');
}

default:
    fail('Unknown action');
}

/* ── Data helpers ──────────────────────────────────────── */
function getTasksData(int $pid): array {
    $tasks = dbFetchAll(
        "SELECT t.*, u.full_name as creator_name,
         (SELECT COALESCE(SUM(hours),0) FROM task_time_logs WHERE task_id=t.id) as logged_hours
         FROM tasks t JOIN users u ON u.id=t.created_by
         WHERE t.project_id=? AND t.parent_task_id IS NULL
         ORDER BY t.priority DESC, t.due_date ASC",
        [$pid]
    );
    if (!$tasks) return [];
    $ids = array_column($tasks, 'id');
    $phs = implode(',', array_fill(0, count($ids), '?'));
    $assignees = dbFetchAll("SELECT ta.task_id, u.id, u.full_name FROM task_assignees ta JOIN users u ON u.id=ta.user_id WHERE ta.task_id IN ($phs)", $ids);
    $amap = [];
    foreach ($assignees as $a) {
        $words = explode(' ', trim($a['full_name']));
        $initials = '';
        foreach ($words as $w) if (!empty($w)) $initials .= strtoupper($w[0]);
        $amap[$a['task_id']][] = ['id' => $a['id'], 'full_name' => $a['full_name'], 'initials' => $initials];
    }
    // Subtask progress
    $scRows = dbFetchAll("SELECT parent_task_id, COUNT(*) as total, SUM(status='done') as done FROM tasks WHERE parent_task_id IN ($phs) GROUP BY parent_task_id", $ids);
    $scMap  = [];
    foreach ($scRows as $sc) $scMap[$sc['parent_task_id']] = $sc;
    foreach ($tasks as &$t) {
        $t['assignees']     = $amap[$t['id']] ?? [];
        $t['subtask_total'] = (int)($scMap[$t['id']]['total'] ?? 0);
        $t['subtask_done']  = (int)($scMap[$t['id']]['done'] ?? 0);
    }
    return $tasks;
}

function getChatData(int $pid, int $userId, int $since = 0, int $before = 0): array {
    try {
        $sql = "SELECT wc.*, u.full_name, u.username,
                (SELECT 1 FROM chat_likes WHERE chat_id=wc.id AND user_id=?) as is_liked,
                pwc.body as parent_body, pu.full_name as parent_name, ru.full_name as recipient_name
                FROM worksheet_chat wc 
                JOIN users u ON u.id=wc.user_id 
                LEFT JOIN worksheet_chat pwc ON pwc.id=wc.parent_id
                LEFT JOIN users pu ON pu.id=pwc.user_id
                LEFT JOIN users ru ON ru.id=wc.recipient_id
                WHERE wc.project_id=? 
                AND (wc.recipient_id IS NULL OR wc.recipient_id = ? OR wc.user_id = ?)";
        $params = [$userId, $pid, $userId, $userId];
        
        if ($since) {
            $sql .= " AND wc.id > ?";
            $params[] = $since;
        } elseif ($before) {
            $sql .= " AND wc.id < ?";
            $params[] = $before;
        }
        
        $limit = $since ? 100 : 50;
        $sql .= " ORDER BY wc.created_at " . ($before ? "DESC" : "ASC") . " LIMIT $limit";
        
        $data = dbFetchAll($sql, $params);
        $data = $before ? array_reverse($data) : ($data ?: []);
        return attachChatReactions($data, $userId);
    } catch (\Exception $e) {
        return [];
    }
}

function getPinnedChatData(int $pid, int $userId): array {
    try {
        $sql = "SELECT wc.*, u.full_name, u.username,
                (SELECT 1 FROM chat_likes WHERE chat_id=wc.id AND user_id=?) as is_liked,
                pwc.body as parent_body, pu.full_name as parent_name, ru.full_name as recipient_name
                FROM worksheet_chat wc
                JOIN users u ON u.id=wc.user_id
                LEFT JOIN worksheet_chat pwc ON pwc.id=wc.parent_id
                LEFT JOIN users pu ON pu.id=pwc.user_id
                LEFT JOIN users ru ON ru.id=wc.recipient_id
                WHERE wc.project_id=?
                AND wc.is_pinned = 1
                AND (wc.recipient_id IS NULL OR wc.recipient_id = ? OR wc.user_id = ?)";
        $params = [$userId, $pid, $userId, $userId];
        $sql .= " ORDER BY wc.created_at DESC";
        $data = dbFetchAll($sql, $params) ?: [];
        return attachChatReactions($data, $userId);
    } catch (\Exception $e) {
        return [];
    }
}

function attachChatReactions(array $messages, int $userId): array {
    if (!$messages) return [];
    $ids = array_column($messages, 'id');
    $phs = implode(',', array_fill(0, count($ids), '?'));
    $allReactions = dbFetchAll(
        "SELECT chat_id, emoji, COUNT(*) as cnt FROM chat_reactions WHERE chat_id IN ($phs) GROUP BY chat_id, emoji ORDER BY cnt DESC",
        $ids
    );
    $myReactions = dbFetchAll(
        "SELECT chat_id, emoji FROM chat_reactions WHERE chat_id IN ($phs) AND user_id=?",
        array_merge($ids, [$userId])
    );
    $reactMap = [];
    foreach ($allReactions as $r) $reactMap[$r['chat_id']][] = $r;
    $myMap = [];
    foreach ($myReactions as $r) $myMap[$r['chat_id']][] = $r['emoji'];
    foreach ($messages as &$msg) {
        $msg['reactions']    = $reactMap[$msg['id']] ?? [];
        $msg['my_reactions'] = $myMap[$msg['id']] ?? [];
    }
    return $messages;
}

function getFeedData(int $pid, int $userId, int $since): array {
    $sql = "SELECT u.*, usr.full_name, (SELECT id FROM update_reads WHERE update_id=u.id AND user_id=?) as is_read FROM updates u JOIN users usr ON usr.id=u.user_id WHERE u.project_id=?";
    $params = [$userId, $pid];
    if ($since) { $sql .= " AND u.id > ?"; $params[] = $since; }
    $sql .= " ORDER BY u.is_pinned DESC, u.created_at DESC LIMIT 50";
    return dbFetchAll($sql, $params);
}

function getMeetingsData(int $pid, int $userId = 0): array {
    $meetings = dbFetchAll(
        "SELECT m.*, (SELECT COUNT(*) FROM meeting_attendees WHERE meeting_id=m.id) as attendee_count FROM meetings m WHERE m.project_id=? ORDER BY m.meeting_date DESC LIMIT 20",
        [$pid]
    );
    if (!$meetings) return [];

    $mids = array_column($meetings, 'id');
    $phs  = implode(',', array_fill(0, count($mids), '?'));

    // Fetch all attendees for these meetings in one go
    $allAttendees = dbFetchAll("SELECT ma.meeting_id, ma.rsvp, u.id as user_id, u.full_name FROM meeting_attendees ma JOIN users u ON u.id=ma.user_id WHERE ma.meeting_id IN ($phs)", $mids);
    $attendeeMap = [];
    foreach ($allAttendees as $a) $attendeeMap[$a['meeting_id']][] = $a;

    // Fetch all action items for these meetings in one go
    $allActions = dbFetchAll("SELECT ai.id, ai.meeting_id, ai.description, ai.assigned_to, ai.due_date, ai.is_done, ai.task_id, u.full_name as assignee_name FROM meeting_action_items ai LEFT JOIN users u ON u.id=ai.assigned_to WHERE ai.meeting_id IN ($phs) ORDER BY ai.is_done, ai.created_at", $mids);
    $actionMap = [];
    foreach ($allActions as $ai) $actionMap[$ai['meeting_id']][] = $ai;

    foreach ($meetings as &$m) {
        $m['attendees']    = $attendeeMap[$m['id']] ?? [];
        $m['action_items'] = $actionMap[$m['id']] ?? [];
        if ($userId) {
            $myA = array_filter($m['attendees'], fn($a) => (int)$a['user_id'] === $userId);
            $m['my_rsvp'] = $myA ? reset($myA)['rsvp'] : 'pending';
        }
    }
    return $meetings;
}

function getTimelogData(int $pid): array {
    $logs = dbFetchAll(
        "SELECT tl.*, u.full_name, t.title as task_title FROM task_time_logs tl JOIN users u ON u.id=tl.user_id JOIN tasks t ON t.id=tl.task_id WHERE t.project_id=? ORDER BY tl.logged_at DESC LIMIT 100",
        [$pid]
    );
    $totalEst    = (float)(dbFetch("SELECT COALESCE(SUM(estimated_hours),0) s FROM tasks WHERE project_id=? AND estimated_hours IS NOT NULL", [$pid])['s'] ?? 0);
    $totalLogged = (float)(dbFetch("SELECT COALESCE(SUM(tl.hours),0) s FROM task_time_logs tl JOIN tasks t ON t.id=tl.task_id WHERE t.project_id=?", [$pid])['s'] ?? 0);
    $taskTotal   = (int)(dbFetch("SELECT COUNT(*) c FROM tasks WHERE project_id=?", [$pid])['c'] ?? 0);
    $taskDone    = (int)(dbFetch("SELECT COUNT(*) c FROM tasks WHERE project_id=? AND status='done'", [$pid])['c'] ?? 0);
    $memberBreakdown = dbFetchAll("SELECT u.full_name, COALESCE(SUM(tl.hours),0) as hours FROM task_time_logs tl JOIN users u ON u.id=tl.user_id JOIN tasks t ON t.id=tl.task_id WHERE t.project_id=? GROUP BY tl.user_id ORDER BY hours DESC", [$pid]);
    return ['logs' => $logs, 'total_estimated' => $totalEst, 'total_logged' => $totalLogged, 'task_total' => $taskTotal, 'task_done' => $taskDone, 'member_breakdown' => $memberBreakdown];
}

function getFilesData(int $pid): array {
    return dbFetchAll(
        "SELECT ta.*, u.full_name, t.title as task_title FROM task_attachments ta JOIN users u ON u.id=ta.user_id JOIN tasks t ON t.id=ta.task_id WHERE t.project_id=? ORDER BY ta.created_at DESC",
        [$pid]
    );
}
