<?php
ini_set('display_errors', 0);
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? 'run';
$aid    = agentId();

define('PBX_BASE', 'https://ovijatgroup.pbx.com.bd');

/* ── MIGRATION: add local_recording column if missing ──────────────── */
$colExists = $conn->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='call_logs' AND column_name='local_recording'")->fetch_row();
if (!$colExists) @$conn->query("ALTER TABLE call_logs ADD COLUMN local_recording VARCHAR(500) DEFAULT NULL AFTER recordingfile");

/* ── helpers ─────────────────────────────────────────────────────────────── */

function pbxRequest(string $url, ?array $post = null, string $cookieFile = ''): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    ]);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if ($post) {
        curl_setopt($ch, CURLOPT_POST,       true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);
    if ($err)        return ['error' => $err];
    if ($code !== 200) return ['error' => "HTTP $code from PBX"];
    return ['body' => $body];
}

function parseDuration(string $str): int {
    $str = trim($str);
    if (preg_match('/^(\d+):(\d+):(\d+)$/', $str, $m)) return $m[1]*3600 + $m[2]*60 + (int)$m[3];
    if (preg_match('/^(\d+):(\d+)$/',       $str, $m)) return $m[1]*60 + (int)$m[2];
    return (int)$str;
}

function mapDisposition(string $s): string {
    $s = strtolower($s);
    if (str_contains($s, 'answer'))     return 'ANSWERED';
    if (str_contains($s, 'busy'))       return 'BUSY';
    if (str_contains($s, 'no answer'))  return 'NO ANSWER';
    if (str_contains($s, 'failed'))     return 'FAILED';
    if (str_contains($s, 'congestion')) return 'CONGESTION';
    return strtoupper($s) ?: 'NO ANSWER';
}

function mapDirection(string $d): string {
    $d = strtolower($d);
    if (str_contains($d, 'inbound'))                               return 'inbound';
    if (str_contains($d, 'outbound'))                              return 'outbound';
    if (str_contains($d, 'internal') || str_contains($d, 'local')) return 'internal';
    return 'unknown';
}

/** Ensure one pbx_settings row exists for the PBX host (needed for fetch_batches FK). */
function ensurePbxSettingsRow(mysqli $conn, int $aid): int {
    $h = PBX_BASE;
    $r = $conn->query("SELECT id FROM pbx_settings WHERE pbx_host='$h' LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) return (int)$row['id'];
    $conn->query("INSERT INTO pbx_settings (name,pbx_host,db_host,db_username,db_password,created_by)
                  VALUES ('Ovijat PBX','$h','localhost','','',$aid)");
    return (int)$conn->insert_id;
}

/* ── load credentials ────────────────────────────────────────────────────── */

$username = getSetting('pbx_username');
$password = getSetting('pbx_password');

if (!$username || !$password) {
    echo json_encode(['ok' => false, 'error' => 'PBX credentials not configured. Save them on the Fetch page first.']);
    exit;
}

$cookieFile = sys_get_temp_dir() . '/pbx_cc_' . md5($username) . '.txt';

/* ── TEST ────────────────────────────────────────────────────────────────── */

if ($action === 'test') {
    $r = pbxRequest(PBX_BASE . '/core/user_settings/user_dashboard.php',
                    ['username' => $username, 'password' => $password], $cookieFile);
    if (file_exists($cookieFile)) unlink($cookieFile);
    if (isset($r['error'])) { echo json_encode(['ok' => false, 'error' => $r['error']]); exit; }
    $body = $r['body'];
    if (stripos($body, 'logout') !== false || stripos($body, 'user_dashboard') !== false) {
        echo json_encode(['ok' => true, 'message' => 'Connected to ' . PBX_BASE . ' successfully']);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Login failed — check username / password']);
    }
    exit;
}

/* ── RUN FETCH ───────────────────────────────────────────────────────────── */

if ($action === 'run') {
    $dateFrom = $data['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo   = $data['date_to']   ?? date('Y-m-d');
    $limit    = min((int)($data['limit'] ?? 5000), 20000);

    $pbxSettingId = ensurePbxSettingsRow($conn, $aid);

    $df = $conn->real_escape_string($dateFrom);
    $dt = $conn->real_escape_string($dateTo);
    $conn->query("INSERT INTO fetch_batches (pbx_setting_id,fetched_by,date_from,date_to,status)
                  VALUES ($pbxSettingId,$aid,'$df','$dt','running')");
    $batchId = (int)$conn->insert_id;

    try {
        // 1. Login
        $r = pbxRequest(PBX_BASE . '/core/user_settings/user_dashboard.php',
                        ['username' => $username, 'password' => $password], $cookieFile);
        if (isset($r['error'])) throw new Exception('Login failed: ' . $r['error']);

        // 2. Fetch CDR HTML
        $cdrUrl = PBX_BASE . '/app/xml_cdr/xml_cdr.php'
                . '?start_stamp_begin=' . urlencode("$dateFrom 00:00")
                . '&start_stamp_end='   . urlencode("$dateTo 23:59");
        $r = pbxRequest($cdrUrl, null, $cookieFile);
        if (isset($r['error'])) throw new Exception('CDR fetch failed: ' . $r['error']);

        $body = $r['body'] ?? '';
        if (strlen($body) < 100) throw new Exception('Empty or too-short response from PBX CDR page');

        // 3. Parse HTML table
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($body);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // Parse header row
        $headers = [];
        foreach ($xpath->query('//tr[@class="list-header"]/th') as $i => $th) {
            $text      = trim(preg_replace('/\s+/', ' ', $th->nodeValue));
            $headers[] = ($i === 0 && empty($text)) ? 'Direction' : ($text ?: 'Col_' . $i);
        }

        // Snapshot contact count to calculate new ones at the end
        $contactsBefore = (int)$conn->query("SELECT COUNT(*) c FROM contacts")->fetch_assoc()['c'];

        // Load agent numbers for direction guessing (extensions + official numbers)
        $agentNumbers = [];
        $anr = $conn->query("SELECT number FROM agent_numbers");
        while ($anr && $an = $anr->fetch_assoc()) {
            $agentNumbers[] = normalizePhone($an['number']);
        }
        // Also pull inline extensions/official phones from agents table if columns exist
        $anr2 = $conn->query("SHOW COLUMNS FROM agents LIKE 'extension'");
        if ($anr2 && $anr2->num_rows) {
            $ar = $conn->query("SELECT extension FROM agents WHERE extension IS NOT NULL AND extension != ''");
            while ($ar && $row = $ar->fetch_assoc()) $agentNumbers[] = normalizePhone($row['extension']);
        }
        $agentNumbers = array_unique(array_filter($agentNumbers));

        $totalRead = 0;
        $inserted  = 0;
        $skipped   = 0;

        foreach ($xpath->query('//tr[contains(@class,"list-row") and @href]') as $row) {
            if ($totalRead >= $limit) break;

            // Build record array from table cells
            $rec = [];
            foreach ($xpath->query('td', $row) as $ci => $col) {
                $hdr = $headers[$ci] ?? 'Col_' . $ci;
                $val = trim(preg_replace('/\s+/', ' ', $col->nodeValue));
                // Direction column uses an image title
                if ($ci === 0) {
                    $img = $xpath->query('.//img', $col)->item(0);
                    if ($img) $val = $img->getAttribute('title');
                }
                // Recording column: extract download URL
                if (stripos($hdr, 'recording') !== false) {
                    $link = $xpath->query('.//a[contains(@href,"download.php")]', $col)->item(0);
                    $val  = $link
                        ? PBX_BASE . '/app/xml_cdr/' . ltrim($link->getAttribute('href'), '/')
                        : '';
                }
                $rec[$hdr] = $val;
            }
            $totalRead++;

            // Map fields (handle FreePBX header name variations)
            $dateStr    = $rec['Date']          ?? $rec['Start Date'] ?? '';
            $timeStr    = $rec['Time']          ?? $rec['Start Time'] ?? '';
            $caller     = $rec['Caller Number'] ?? $rec['Caller']     ?? $rec['Source']      ?? '';
            $callerName = $rec['Caller Name']   ?? $rec['Name']       ?? '';
            $dest       = $rec['Destination']   ?? $rec['Destination Number'] ?? $rec['Dest'] ?? '';
            $ext        = $rec['Extension']     ?? '';
            $status     = $rec['Status']        ?? $rec['Disposition'] ?? '';
            $durStr     = $rec['Duration']      ?? '0';
            $recUrl     = $rec['Recording']     ?? '';
            $dirStr     = $rec['Direction']     ?? '';

            $calldate = ($dateStr && $timeStr)
                ? date('Y-m-d H:i:s', strtotime("$dateStr $timeStr"))
                : date('Y-m-d H:i:s');
            $duration = parseDuration($durStr);
            $disp     = mapDisposition($status);
            $dir      = mapDirection($dirStr);
            $billsec  = ($disp === 'ANSWERED') ? $duration : 0;

            // Guess direction when PBX didn't provide one
            if ($dir === 'unknown') {
                $srcN = normalizePhone($caller);
                $dstN = normalizePhone($dest);
                if ($dstN === '800' || $dstN === '8000') {
                    // Destination is voicemail — caller rang in, got voicemail
                    $dir = 'inbound';
                } elseif (in_array($dstN, $agentNumbers)) {
                    // Someone calling an agent's number → inbound
                    $dir = 'inbound';
                } elseif (in_array($srcN, $agentNumbers)) {
                    // Agent is the caller → outbound
                    $dir = 'outbound';
                } else {
                    // No agent match on either side → treat as inbound (external caller)
                    $dir = 'inbound';
                }
            }

            // Stable uniqueid for deduplication
            $uid  = 'WEB-' . md5($caller . '|' . $dest . '|' . $calldate . '|' . $ext);
            $uidE = $conn->real_escape_string($uid);

            // Skip if already imported
            $chk = $conn->query("SELECT id FROM call_logs WHERE uniqueid='$uidE' LIMIT 1");
            if ($chk && $chk->num_rows > 0) { $skipped++; continue; }

            // Auto-find or create contact from caller number
            $contactId = $caller ? findOrCreateContact($caller, $callerName, $aid) : 0;
            $cid       = $contactId ?: null;

            $callerE     = $conn->real_escape_string($caller);
            $callerNameE = $conn->real_escape_string($callerName);
            $destE       = $conn->real_escape_string($dest);
            $extE        = $conn->real_escape_string($ext);
            $dispE       = $conn->real_escape_string($disp);
            $recUrlE     = $conn->real_escape_string($recUrl);
            $calldateE   = $conn->real_escape_string($calldate);
            $clidE       = $conn->real_escape_string($callerName ? "$callerName <$caller>" : $caller);
            $cidSql      = $cid ? (int)$cid : 'NULL';

            $conn->query("INSERT INTO call_logs
                (uniqueid, calldate, clid, src, dst, cnum, cnam, dstchannel,
                 duration, billsec, disposition, recordingfile,
                 call_direction, contact_id, fetch_batch_id, created_by)
                VALUES
                ('$uidE','$calldateE','$clidE','$callerE','$destE','$callerE','$callerNameE','$extE',
                 $duration,$billsec,'$dispE','$recUrlE',
                 '$dir',$cidSql,$batchId,$aid)");
            $inserted++;
        }

        if (file_exists($cookieFile)) unlink($cookieFile);

        $contactsCreated = max(0,
            (int)$conn->query("SELECT COUNT(*) c FROM contacts")->fetch_assoc()['c'] - $contactsBefore
        );

        $conn->query("UPDATE fetch_batches SET
            status='completed', total_fetched=$totalRead, new_records=$inserted,
            duplicates_skipped=$skipped, contacts_created=$contactsCreated, completed_at=NOW()
            WHERE id=$batchId");

        logActivity('fetch_completed', 'fetch_batches', $batchId,
            "Fetched $inserted new, $skipped dupes, $contactsCreated contacts");

        echo json_encode([
            'ok'                 => true,
            'total_fetched'      => $totalRead,
            'new_records'        => $inserted,
            'duplicates_skipped' => $skipped,
            'contacts_created'   => $contactsCreated,
        ]);

    } catch (Exception $e) {
        if (file_exists($cookieFile)) unlink($cookieFile);
        $msg = $conn->real_escape_string($e->getMessage());
        $conn->query("UPDATE fetch_batches SET status='failed',error_message='$msg',completed_at=NOW() WHERE id=$batchId");
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* ── RE-DETECT DIRECTION for existing unknown records ────────────────────── */

if ($action === 'redetect') {
    $agentNumbers = [];
    $anr = $conn->query("SELECT number FROM agent_numbers");
    while ($anr && $an = $anr->fetch_assoc()) $agentNumbers[] = normalizePhone($an['number']);
    $agentNumbers = array_unique(array_filter($agentNumbers));

    $unknowns = $conn->query("SELECT id, src, dst FROM call_logs WHERE call_direction='unknown'");
    $fixed = 0;
    while ($unknowns && $row = $unknowns->fetch_assoc()) {
        $srcN = normalizePhone($row['src']);
        $dstN = normalizePhone($row['dst']);
        if ($dstN === '800' || $dstN === '8000') {
            $newDir = 'inbound';
        } elseif (in_array($dstN, $agentNumbers)) {
            $newDir = 'inbound';
        } elseif (in_array($srcN, $agentNumbers)) {
            $newDir = 'outbound';
        } else {
            $newDir = 'inbound';
        }
        $conn->query("UPDATE call_logs SET call_direction='$newDir', updated_by=$aid WHERE id={$row['id']}");
        $fixed++;
    }
    logActivity('direction_redetected', 'call_logs', null, "Re-detected direction for $fixed unknown records");
    echo json_encode(['ok' => true, 'fixed' => $fixed]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);

/* ── FETCH RECORDING ─────────────────────────────────────────────── */
/* Fetch a single recording from PBX and save it locally as GSM      */

if ($action === 'fetch_recording') {
    $callId = (int)($data['call_id'] ?? 0);
    if (!$callId) { echo json_encode(['ok'=>false, 'error'=>'Missing call_id']); exit; }

    $call = $conn->query("SELECT id, recordingfile, uniqueid, local_recording FROM call_logs WHERE id=$callId LIMIT 1")->fetch_assoc();
    if (!$call) { echo json_encode(['ok'=>false, 'error'=>'Call not found']); exit; }

    // Already fetched?
    if (!empty($call['local_recording']) && file_exists($call['local_recording'])) {
        echo json_encode(['ok'=>true, 'local'=>$call['local_recording'], 'size'=>filesize($call['local_recording']), 'already'=>true]);
        exit;
    }

    $pbxUrl = $call['recordingfile'] ?? '';
    if (empty($pbxUrl) || (!str_starts_with($pbxUrl, 'http://') && !str_starts_with($pbxUrl, 'https://'))) {
        echo json_encode(['ok'=>false, 'error'=>'No PBX recording URL for this call']);
        exit;
    }

    // Build local save path: recordings/YYYY-MM/{id}_{uniqueid_hash}.gsm
    $callRow = $conn->query("SELECT calldate FROM call_logs WHERE id=$callId")->fetch_assoc();
    $ym = date('Y-m', strtotime($callRow['calldate'] ?? 'now'));
    $recDir = dirname(__DIR__) . '/recordings/' . $ym;
    if (!is_dir($recDir)) mkdir($recDir, 0755, true);

    $hash = substr(md5($call['uniqueid']), 0, 8);
    $localFile = $recDir . '/rec_' . $callId . '_' . $hash . '.gsm';

    // Fetch from PBX
    $username = getSetting('pbx_username');
    $password = getSetting('pbx_password');
    $cookieFile = sys_get_temp_dir() . '/pbx_cc_' . md5($username) . '.txt';

    if (!file_exists($cookieFile) || (time() - filemtime($cookieFile)) > 3600) {
        $ch = curl_init(PBX_BASE . '/core/user_settings/user_dashboard.php');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>30,
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['username'=>$username,'password'=>$password]),
            CURLOPT_COOKIEJAR=>$cookieFile, CURLOPT_COOKIEFILE=>$cookieFile,
            CURLOPT_USERAGENT=>'Mozilla/5.0',
        ]);
        curl_exec($ch);
        curl_close($ch);
        touch($cookieFile);
    }

    $ch = curl_init($pbxUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_TIMEOUT=>120,
        CURLOPT_COOKIEFILE=>$cookieFile,
        CURLOPT_USERAGENT=>'Mozilla/5.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || strlen($body) < 8) {
        echo json_encode(['ok'=>false, 'error'=>'PBX returned HTTP ' . $code . ' or empty body']);
        exit;
    }

    $written = file_put_contents($localFile, $body);
    if ($written === false) {
        echo json_encode(['ok'=>false, 'error'=>'Failed to write file']);
        exit;
    }

    // Save local path in DB
    $localE = $conn->real_escape_string($localFile);
    $conn->query("UPDATE call_logs SET local_recording='$localE' WHERE id=$callId");

    logActivity('recording_fetched', 'call_logs', $callId, "Saved to: $localFile ($written bytes)");

    echo json_encode([
        'ok'=>true, 'local'=>$localFile, 'size'=>$written, 'already'=>false,
        'url'=> APP_URL . '/recordings/' . $ym . '/rec_' . $callId . '_' . $hash . '.gsm',
    ]);
    exit;
}

/* ── FETCH RECORDING BATCH ────────────────────────────────────────── */

if ($action === 'fetch_recordings_batch') {
    $ids = $data['call_ids'] ?? [];
    if (!$ids) { echo json_encode(['ok'=>false, 'error'=>'No call_ids provided']); exit; }

    $results = [];
    foreach ($ids as $cid) {
        $cid = (int)$cid;
        $call = $conn->query("SELECT id, recordingfile, uniqueid, local_recording, calldate FROM call_logs WHERE id=$cid AND recordingfile IS NOT NULL AND recordingfile!='' LIMIT 1")->fetch_assoc();
        if (!$call) { $results[$cid] = ['ok'=>false, 'error'=>'Not found or no recording']; continue; }

        if (!empty($call['local_recording']) && file_exists($call['local_recording'])) {
            $results[$cid] = ['ok'=>true, 'local'=>$call['local_recording'], 'size'=>filesize($call['local_recording']), 'already'=>true];
            continue;
        }

        $pbxUrl = $call['recordingfile'];
        $ym = date('Y-m', strtotime($call['calldate'] ?? 'now'));
        $recDir = dirname(__DIR__) . '/recordings/' . $ym;
        if (!is_dir($recDir)) mkdir($recDir, 0755, true);

        $hash = substr(md5($call['uniqueid']), 0, 8);
        $localFile = $recDir . '/rec_' . $cid . '_' . $hash . '.gsm';

        $username = getSetting('pbx_username');
        $password = getSetting('pbx_password');
        $cookieFile = sys_get_temp_dir() . '/pbx_cc_' . md5($username) . '.txt';

        if (!file_exists($cookieFile) || (time() - filemtime($cookieFile)) > 3600) {
            $ch = curl_init(PBX_BASE . '/core/user_settings/user_dashboard.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
                CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>30,
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['username'=>$username,'password'=>$password]),
                CURLOPT_COOKIEJAR=>$cookieFile, CURLOPT_COOKIEFILE=>$cookieFile, CURLOPT_USERAGENT=>'Mozilla/5.0',
            ]);
            curl_exec($ch);
            curl_close($ch);
            touch($cookieFile);
        }

        $ch = curl_init($pbxUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_TIMEOUT=>120,
            CURLOPT_COOKIEFILE=>$cookieFile, CURLOPT_USERAGENT=>'Mozilla/5.0',
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || strlen($body) < 8) {
            $results[$cid] = ['ok'=>false, 'error'=>'PBX HTTP ' . $code];
            continue;
        }

        $written = file_put_contents($localFile, $body);
        if ($written === false) {
            $results[$cid] = ['ok'=>false, 'error'=>'Write failed'];
            continue;
        }

        $localE = $conn->real_escape_string($localFile);
        $conn->query("UPDATE call_logs SET local_recording='$localE' WHERE id=$cid");
        $results[$cid] = ['ok'=>true, 'local'=>$localFile, 'size'=>$written, 'already'=>false];
    }

    $success = count(array_filter($results, fn($r)=>($r['ok']??false)));
    echo json_encode(['ok'=>true, 'results'=>$results, 'success'=>$success, 'total'=>count($results)]);
    exit;
}
