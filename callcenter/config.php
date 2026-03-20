<?php
/**
 * Ovijat Call Center — Core Configuration
 * Loaded by every page. Provides DB, session, auth, helpers, audit.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Database ─────────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'callcenter_db2');

define('APP_NAME',    'Ovijat Call Center');
define('APP_VERSION', '1.0.0');
define('APP_ROOT',    __DIR__);
define('APP_URL',     '/callcenter');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(503);
    die('<div style="font-family:monospace;padding:2rem;background:#1a1d27;color:#fca5a5">
         <h3>Database connection failed</h3><p>' . htmlspecialchars($conn->connect_error) . '</p>
         <p>Run <a href="setup.php" style="color:#818cf8">setup.php</a> first.</p></div>');
}
$conn->set_charset('utf8mb4');

// ── Auth helpers ──────────────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['agent_id']) && $_SESSION['agent_id'] > 0;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function currentAgent(): array {
    return [
        'id'        => $_SESSION['agent_id']   ?? 0,
        'username'  => $_SESSION['username']   ?? '',
        'full_name' => $_SESSION['full_name']  ?? '',
        'dept'      => $_SESSION['department'] ?? '',
    ];
}

function agentId(): int {
    return (int)($_SESSION['agent_id'] ?? 0);
}

// ── Audit / Activity ──────────────────────────────────────────────────────────
/**
 * Log any agent action to activity_log.
 * Call this whenever an agent creates, edits, deletes, or triggers anything.
 */
function logActivity(string $action, string $entityType = null, int $entityId = null, string $details = null): void {
    global $conn;
    $aid  = agentId();
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $conn->prepare(
        "INSERT INTO activity_log (agent_id, action, entity_type, entity_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("ississ", $aid, $action, $entityType, $entityId, $details, $ip);
    $stmt->execute();
}

/**
 * Log a field-level edit to edit_history.
 * Call once per changed field when an agent edits a record.
 */
function logEdit(string $entityType, int $entityId, string $field, $oldVal, $newVal): void {
    global $conn;
    $aid     = agentId();
    $oldStr  = is_array($oldVal) ? json_encode($oldVal) : (string)$oldVal;
    $newStr  = is_array($newVal) ? json_encode($newVal) : (string)$newVal;
    if ($oldStr === $newStr) return; // no change, skip
    $stmt = $conn->prepare(
        "INSERT INTO edit_history (entity_type, entity_id, field_name, old_value, new_value, edited_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sisssi", $entityType, $entityId, $field, $oldStr, $newStr, $aid);
    $stmt->execute();
}

/**
 * Send a notification to an agent.
 */
function notify(int $toAgentId, string $title, string $message, string $type = 'system', string $entityType = null, int $entityId = null, string $link = null): void {
    global $conn;
    $fromId = agentId();
    $stmt = $conn->prepare(
        "INSERT INTO notifications (agent_id, from_agent, title, message, type, entity_type, entity_id, link)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("iissssss", $toAgentId, $fromId, $title, $message, $type, $entityType, $entityId, $link);
    $stmt->execute();
}

// ── Query helpers ─────────────────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string {
    global $conn;
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '" . $conn->real_escape_string($key) . "' LIMIT 1");
    if ($r && $r->num_rows) return $r->fetch_assoc()['setting_value'] ?? $default;
    return $default;
}

function getAllAgents(): array {
    global $conn;
    $r = $conn->query("SELECT id, username, full_name, department, status FROM agents WHERE status='active' AND id > 1 ORDER BY full_name");
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

function getContactByPhone(string $phone): ?array {
    global $conn;
    $p = $conn->real_escape_string(normalizePhone($phone));
    $r = $conn->query("SELECT * FROM contacts WHERE phone = '$p' LIMIT 1");
    return ($r && $r->num_rows) ? $r->fetch_assoc() : null;
}

/**
 * Find or create a contact from a phone number (used during CDR fetch).
 * Returns contact id.
 */
function findOrCreateContact(string $phone, string $name = '', int $createdBy = 0): int {
    global $conn;
    $phone = normalizePhone($phone);
    if (empty($phone)) return 0;
    $ep    = $conn->real_escape_string($phone);
    $r     = $conn->query("SELECT id FROM contacts WHERE phone = '$ep' LIMIT 1");
    if ($r && $r->num_rows) return (int)$r->fetch_assoc()['id'];
    // Auto-create
    $eName = $conn->real_escape_string($name ?: $phone);
    $conn->query("INSERT INTO contacts (phone, name, scope, created_by) VALUES ('$ep', '$eName', 'unknown', $createdBy)");
    return (int)$conn->insert_id;
}

// ── Formatting helpers ────────────────────────────────────────────────────────
function normalizePhone(string $phone): string {
    $p = preg_replace('/[^0-9+]/', '', $phone);
    return substr($p, 0, 30);
}

function formatDuration(int $seconds): string {
    if ($seconds <= 0) return '0s';
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h) return "{$h}h {$m}m {$s}s";
    if ($m) return "{$m}m {$s}s";
    return "{$s}s";
}

function formatDt(string $dt, string $format = 'd M Y, h:i A'): string {
    if (!$dt || $dt === '0000-00-00 00:00:00') return '—';
    return date($format, strtotime($dt));
}

function timeAgo(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return intdiv($diff, 60) . 'm ago';
    if ($diff < 86400)  return intdiv($diff, 3600) . 'h ago';
    if ($diff < 604800) return intdiv($diff, 86400) . 'd ago';
    return date('d M Y', strtotime($dt));
}

function dispositionClass(string $d): string {
    return match(strtoupper($d)) {
        'ANSWERED'  => 'success',
        'NO ANSWER' => 'warning',
        'BUSY'      => 'info',
        'FAILED',
        'CONGESTION'=> 'danger',
        default     => 'secondary',
    };
}

function dispositionIcon(string $d): string {
    return match(strtoupper($d)) {
        'ANSWERED'   => 'fa-phone',
        'NO ANSWER'  => 'fa-phone-slash',
        'BUSY'       => 'fa-phone-volume',
        'FAILED'     => 'fa-phone-xmark',
        'CONGESTION' => 'fa-triangle-exclamation',
        default      => 'fa-phone',
    };
}

function directionIcon(string $d): string {
    return match($d) {
        'inbound'  => 'fa-phone-arrow-down-left',
        'outbound' => 'fa-phone-arrow-up-right',
        'internal' => 'fa-arrows-left-right',
        default    => 'fa-phone',
    };
}

function priorityClass(string $p): string {
    return match($p) {
        'urgent' => 'danger',
        'high'   => 'warning',
        'medium' => 'info',
        default  => 'secondary',
    };
}

function markClass(string $m): string {
    return match($m) {
        'urgent'    => 'danger',
        'escalated' => 'warning',
        'follow_up' => 'info',
        'callback'  => 'primary',
        'resolved'  => 'success',
        'no_action' => 'secondary',
        default     => 'light',
    };
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function j($v): string {
    return json_encode($v, JSON_UNESCAPED_UNICODE);
}

// ── Unread notification count ─────────────────────────────────────────────────
function unreadNotifCount(): int {
    global $conn;
    $aid = agentId();
    if (!$aid) return 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE agent_id=$aid AND is_read=0");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// ── Pending tasks for current agent ──────────────────────────────────────────
function pendingTaskCount(): int {
    global $conn;
    $aid = agentId();
    if (!$aid) return 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM todos WHERE assigned_to=$aid AND status IN ('pending','in_progress')");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}
