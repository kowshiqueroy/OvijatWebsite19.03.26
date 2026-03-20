<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();

$callId   = (int)($_GET['id'] ?? 0);
$download = isset($_GET['dl']) && $_GET['dl'] == '1';

if (!$callId) { http_response_code(400); exit('Missing call id'); }

$call = $conn->query(
    "SELECT cl.recordingfile, cl.src, cl.dst, cl.calldate, cl.call_direction,
            c.phone AS contact_phone, c.name AS contact_name,
            ps.recording_base_url, ps.recording_base_path, ps.db_username, ps.db_password
     FROM call_logs cl
     LEFT JOIN contacts c        ON c.id = cl.contact_id
     LEFT JOIN fetch_batches fb  ON fb.id = cl.fetch_batch_id
     LEFT JOIN pbx_settings ps   ON ps.id = fb.pbx_setting_id
     WHERE cl.id=$callId LIMIT 1"
)->fetch_assoc();

if (!$call || !$call['recordingfile']) {
    http_response_code(404); exit('No recording for this call');
}

logActivity('recording_played', 'call_logs', $callId,
    ($download ? 'Downloaded' : 'Played') . ' recording: ' . $call['recordingfile']);

$file = $call['recordingfile'];
$urlPath = parse_url($file, PHP_URL_PATH) ?: $file;
$ext  = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION)) ?: 'wav';
$audioExts = ['wav','mp3','ogg','opus','aac','gsm'];
// If URL points to a PHP script (e.g. download.php?id=...), use wav as default
if (!in_array($ext, $audioExts)) { $ext = 'wav'; }
$mimes = ['wav'=>'audio/wav','mp3'=>'audio/mpeg','ogg'=>'audio/ogg',
          'opus'=>'audio/opus','aac'=>'audio/aac','gsm'=>'audio/x-gsm'];
$mime = $mimes[$ext] ?? 'audio/wav';

// Build clean filename: rec-{number}-{name}-{calldate}.ext
$number = $call['contact_phone']
    ?: ($call['call_direction'] === 'inbound' ? $call['src'] : $call['dst'])
    ?: 'unknown';
$cname  = $call['contact_name'] ?: '';
$cdate  = $call['calldate'] ? date('Ymd-His', strtotime($call['calldate'])) : date('Ymd');
// sanitize for filename
$sanitize = fn($s) => preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $s));
$parts = array_filter(['rec', $sanitize($number), $sanitize($cname), $cdate]);
$basename = implode('-', $parts) . '.' . $ext;

// Fallback: if URL is a plain audio file, use its original name
$rawBase = basename($urlPath);
if (in_array(strtolower(pathinfo($rawBase, PATHINFO_EXTENSION)), $audioExts) && !$call['contact_name'] && !$call['contact_phone']) {
    $basename = $rawBase;
}
if ($download) {
    header('Content-Disposition: attachment; filename="' . $basename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $basename . '"');
}
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');

// ── Case 1: full URL stored (web-scraped fetch) ───────────────────────────────
if (str_starts_with($file, 'http://') || str_starts_with($file, 'https://')) {
    // Reuse cached cookie from last fetch session, or login fresh
    $username   = getSetting('pbx_username');
    $password   = getSetting('pbx_password');
    $cookieFile = sys_get_temp_dir() . '/pbx_cc_' . md5($username) . '.txt';

    // If no cookie cached, login first
    if (!file_exists($cookieFile) || (time() - filemtime($cookieFile)) > 3600) {
        $ch = curl_init('https://ovijatgroup.pbx.com.bd/core/user_settings/user_dashboard.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['username'=>$username,'password'=>$password]),
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // Stream recording with session cookie
    $ch = curl_init($file);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_COOKIEFILE     => $cookieFile,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
        CURLOPT_WRITEFUNCTION  => function($ch, $data) { echo $data; return strlen($data); },
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        http_response_code(502);
        exit('Could not retrieve recording from PBX (HTTP ' . $code . ')');
    }
    exit;
}

// ── Case 2: local file path ───────────────────────────────────────────────────
$localBase = $call['recording_base_path'] ?? '';
$localPath = $localBase ? rtrim($localBase, '/') . '/' . ltrim($file, '/') : $file;

if ($localBase && file_exists($localPath)) {
    header('Content-Length: ' . filesize($localPath));
    readfile($localPath);
    exit;
}

// ── Case 3: remote base URL + file (HTTP Basic Auth) ─────────────────────────
if ($call['recording_base_url']) {
    $remoteUrl = rtrim($call['recording_base_url'], '/') . '/' . ltrim($file, '/');
    $ch = curl_init($remoteUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERPWD        => $call['db_username'] . ':' . $call['db_password'],
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_WRITEFUNCTION  => function($ch, $data) { echo $data; return strlen($data); },
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) { http_response_code(404); exit('Recording not accessible (HTTP ' . $code . ')'); }
    exit;
}

http_response_code(404);
exit('Recording not found');
