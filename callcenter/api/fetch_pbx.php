<?php
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once '../config.php';
require_once '../functions.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$agentFetch = !empty($input['agent_fetch']);

if ($agentFetch) {
    $agent_id = $_SESSION['agent_id'];
    
    $stmtAgent = $conn->prepare("SELECT a.*, u.username FROM agents a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmtAgent->bind_param("i", $agent_id);
    $stmtAgent->execute();
    $agent = $stmtAgent->get_result()->fetch_assoc();
    
    if (!$agent) {
        echo json_encode(["status" => "error", "message" => "Agent not found"]);
        exit;
    }
    
    $stmtSettings = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'pbx_username'");
    $stmtSettings->execute();
    $savedUsername = $stmtSettings->get_result()->fetch_assoc()['setting_value'] ?? '';
    
    $stmtSettings = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'pbx_password'");
    $stmtSettings->execute();
    $savedPassword = $stmtSettings->get_result()->fetch_assoc()['setting_value'] ?? '';
    
    if (empty($savedUsername) || empty($savedPassword)) {
        echo json_encode(["status" => "error", "message" => "PBX credentials not configured"]);
        exit;
    }
    
    $recordingsDir = dirname(__DIR__) . '/recordings';
    if (!is_dir($recordingsDir)) {
        mkdir($recordingsDir, 0755, true);
    }
    
    try {
        $baseUrl = "https://ovijatgroup.pbx.com.bd";
        $loginUrl = $baseUrl . "/core/user_settings/user_dashboard.php";
        $cookieFile = sys_get_temp_dir() . '/pbx_cookie_' . md5($savedUsername) . '.txt';
        
        function makeRequest($url, $postData = null, $cookieFile = null) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            if ($cookieFile) {
                curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
            }
            if ($postData) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            }
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($error) return ["error" => $error];
            if ($httpCode !== 200) return ["error" => "HTTP Error: $httpCode", "body" => $response];
            return ["body" => $response, "httpCode" => $httpCode];
        }
        
        $loginResponse = makeRequest($loginUrl, ["username" => $savedUsername, "password" => $savedPassword], $cookieFile);
        if (isset($loginResponse['error'])) {
            echo json_encode(["status" => "error", "message" => "Login failed: " . $loginResponse['error']]);
            exit;
        }
        
        $today = date('Y-m-d');
        $cdrUrl = $baseUrl . "/app/xml_cdr/xml_cdr.php?start_stamp_begin=" . urlencode("$today 00:00") . "&start_stamp_end=" . urlencode("$today 23:59");
        
        $cdrResponse = makeRequest($cdrUrl, null, $cookieFile);
        if (isset($cdrResponse['error'])) {
            echo json_encode(["status" => "error", "message" => "CDR fetch failed: " . $cdrResponse['error']]);
            exit;
        }
        
        $body = $cdrResponse['body'];
        if (empty($body) || strlen($body) < 100) {
            echo json_encode(["status" => "error", "message" => "Empty response from PBX"]);
            exit;
        }
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($body);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        
        $headers = [];
        $headerNodes = $xpath->query('//tr[@class="list-header"]/th');
        foreach ($headerNodes as $index => $th) {
            $headerText = trim(preg_replace('/\s+/', ' ', $th->nodeValue));
            if ($index === 0 && empty($headerText)) {
                $headers[] = "Direction";
            } else {
                $headers[] = $headerText ?: "Col_" . $index;
            }
        }
        
        $dataRows = $xpath->query('//tr[contains(@class, "list-row") and @href]');
        $records = [];
        $inserted = 0;
        $answeredCalls = [];
        $missedCalls = [];
        
        foreach ($dataRows as $row) {
            $cols = $xpath->query('td', $row);
            $record = [];
            
            foreach ($cols as $colIndex => $col) {
                $headerName = $headers[$colIndex] ?? "Col_" . $colIndex;
                $cellValue = trim(preg_replace('/\s+/', ' ', $col->nodeValue));
                
                if ($colIndex === 0) {
                    $img = $xpath->query('.//img', $col)->item(0);
                    if ($img) {
                        $cellValue = $img->getAttribute('title');
                    }
                }
                
                if (strtolower($headerName) === 'recording' || strpos($cellValue, 'download') !== false) {
                    $link = $xpath->query('.//a[contains(@href, "download.php")]', $col)->item(0);
                    if ($link) {
                        $href = $link->getAttribute('href');
                        $cellValue = $baseUrl . "/app/xml_cdr/" . ltrim($href, '/');
                    } else {
                        $cellValue = "";
                    }
                }
                
                $record[$headerName] = $cellValue;
            }
            
            $extension = $record['Extension'] ?? '';
            $status = $record['Status'] ?? '';
            $direction = $record['Direction'] ?? '';
            
            if ($extension === $agent['extension']) {
                $records[] = $record;
                if (stripos($status, 'answer') !== false) {
                    $answeredCalls[] = $record;
                } else {
                    $missedCalls[] = $record;
                }
            }
        }
        
        $answeredCalls = array_slice($answeredCalls, 0, 50);
        $missedCalls = array_slice($missedCalls, 0, 50);
        $allCalls = array_merge($answeredCalls, $missedCalls);
        
        $saved = 0;
        foreach ($allCalls as $record) {
            $caller_number = $record['Caller Number'] ?? '';
            $caller_name = $record['Caller Name'] ?? '';
            $destination = $record['Destination'] ?? '';
            $direction = $record['Direction'] ?? '';
            $status = $record['Status'] ?? '';
            $duration = $record['Duration'] ?? '';
            $recording = $record['Recording'] ?? '';
            $date = $record['Date'] ?? '';
            $time = $record['Time'] ?? '';
            $start_time = !empty($date) && !empty($time) ? date('Y-m-d H:i:s', strtotime("$date $time")) : date('Y-m-d H:i:s');
            
            // CRUCIAL: Normalize BEFORE generating pbx_id to prevent duplicates
            $caller_norm = normalizePhone($caller_number);
            $dest_norm = normalizePhone($destination);
            $record['Caller Number'] = $caller_norm;
            $record['Destination'] = $dest_norm;
            $pbx_id = generatePbxId($record);
            
            $stmt = $conn->prepare("SELECT id FROM calls WHERE pbx_id = ?");
            $stmt->bind_param("s", $pbx_id);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows === 0) {
                $stmtInsert = $conn->prepare("INSERT INTO calls (pbx_id, caller_number, caller_number_normalized, caller_name, destination, direction, status, duration, recording_url, start_time, agent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtInsert->bind_param("ssssssssssi", $pbx_id, $caller_number, $caller_norm, $caller_name, $destination, $direction, $status, $duration, $recording, $start_time, $agent_id);
                $stmtInsert->execute();
                $saved++;
                
                if (!empty($caller_number)) {
                    findOrCreatePerson($caller_number);
                }
            }
            $stmt->close();
        }
        
        if (file_exists($cookieFile)) unlink($cookieFile);
        
        echo json_encode([
            "status" => "success",
            "total" => count($allCalls),
            "inserted" => $saved,
            "answered" => count($answeredCalls),
            "missed" => count($missedCalls)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Admin access required"]);
    exit;
}

$recordingsDir = dirname(__DIR__) . '/recordings';
if (!is_dir($recordingsDir)) {
    mkdir($recordingsDir, 0755, true);
}

try {
    $savedUsername = '';
    $savedPassword = '';
    $resultSettings = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('pbx_username', 'pbx_password')");
    while ($row = $resultSettings->fetch_assoc()) {
        if ($row['setting_key'] === 'pbx_username') $savedUsername = $row['setting_value'];
        if ($row['setting_key'] === 'pbx_password') $savedPassword = $row['setting_value'];
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = $_POST['username'] ?? $input['username'] ?? $savedUsername;
    $password = $_POST['password'] ?? $input['password'] ?? $savedPassword;
    $saveCredentials = $_POST['save_credentials'] ?? $input['save_credentials'] ?? false;
    $startDate = $_POST['start_date'] ?? $input['start_date'] ?? date('Y-m-d 00:00');
    $endDate = $_POST['end_date'] ?? $input['end_date'] ?? date('Y-m-d 23:59');

    if ($saveCredentials && !empty($username) && !empty($password)) {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('pbx_username', '$username') ON DUPLICATE KEY UPDATE setting_value = '$username'");
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('pbx_password', '$password') ON DUPLICATE KEY UPDATE setting_value = '$password'");
        echo json_encode(["status" => "success", "message" => "Credentials saved"]);
        exit;
    }

    if (empty($username) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "PBX username and password required. Please enter or save credentials."]);
        exit;
    }

    $baseUrl = "https://ovijatgroup.pbx.com.bd";
    $loginUrl = $baseUrl . "/core/user_settings/user_dashboard.php";
    $cdrUrl = $baseUrl . "/app/xml_cdr/xml_cdr.php";

    if (!empty($startDate) && !empty($endDate)) {
        $cdrUrl .= "?start_stamp_begin=" . urlencode($startDate) . "&start_stamp_end=" . urlencode($endDate);
    }

    $cookieFile = sys_get_temp_dir() . '/pbx_cookie_' . md5($username) . '.txt';

    function makeRequest($url, $postData = null, $cookieFile = null, $returnHeaders = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        if ($cookieFile) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        }
        if ($postData) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }
        if ($returnHeaders) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) return ["error" => $error];
        if ($httpCode !== 200) return ["error" => "HTTP Error: $httpCode", "body" => $response];
        return ["body" => $response, "httpCode" => $httpCode];
    }

    function downloadRecording($url, $cookieFile, $savePath) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error || $httpCode !== 200 || empty($content)) {
            return false;
        }
        return file_put_contents($savePath, $content) !== false;
    }

    $loginResponse = makeRequest($loginUrl, ["username" => $username, "password" => $password], $cookieFile);
    if (isset($loginResponse['error'])) {
        echo json_encode(["status" => "error", "message" => "Login failed: " . $loginResponse['error']]);
        exit;
    }

    $cdrResponse = makeRequest($cdrUrl, null, $cookieFile);
    if (isset($cdrResponse['error'])) {
        echo json_encode(["status" => "error", "message" => "CDR fetch failed: " . $cdrResponse['error']]);
        exit;
    }

    $body = $cdrResponse['body'];
    if (empty($body) || strlen($body) < 100) {
        echo json_encode(["status" => "error", "message" => "Empty response from PBX"]);
        exit;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($body);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

$headers = [];
$headerNodes = $xpath->query('//tr[@class="list-header"]/th');
foreach ($headerNodes as $index => $th) {
    $headerText = trim(preg_replace('/\s+/', ' ', $th->nodeValue));
    if ($index === 0 && empty($headerText)) {
        $headers[] = "Direction";
    } else {
        $headers[] = $headerText ?: "Col_" . $index;
    }
}

$dataRows = $xpath->query('//tr[contains(@class, "list-row") and @href]');
$records = [];
$inserted = 0;
$skipped = 0;
$recordingsDownloaded = 0;

$recordingsFailed = 0;

// Load agent extensions and official phones for direction detection
$agentExtensions = [];
$agentOfficialPhones = [];
$resultAgents = $conn->query("SELECT extension, official_phone FROM agents WHERE is_deleted = 0");
while ($row = $resultAgents->fetch_assoc()) {
    if ($row['extension']) $agentExtensions[] = $row['extension'];
    if ($row['official_phone']) $agentOfficialPhones[] = normalizePhone($row['official_phone']);
}

foreach ($dataRows as $row) {
    $cols = $xpath->query('td', $row);
    $record = [];
    
    foreach ($cols as $colIndex => $col) {
        $headerName = $headers[$colIndex] ?? "Col_" . $colIndex;
        $cellValue = trim(preg_replace('/\s+/', ' ', $col->nodeValue));
        
        if ($colIndex === 0) {
            $img = $xpath->query('.//img', $col)->item(0);
            if ($img) {
                $cellValue = $img->getAttribute('title');
            }
        }
        
        if (strtolower($headerName) === 'recording' || strpos($cellValue, 'download') !== false) {
            $link = $xpath->query('.//a[contains(@href, "download.php")]', $col)->item(0);
            if ($link) {
                $href = $link->getAttribute('href');
                $cellValue = $baseUrl . "/app/xml_cdr/" . ltrim($href, '/');
            } else {
                $cellValue = "";
            }
        }
        
        $record[$headerName] = $cellValue;
    }
    
    $caller_number = $record['Caller Number'] ?? '';
    $caller_name = $record['Caller Name'] ?? '';
    $destination = $record['Destination'] ?? '';
    $extension = $record['Extension'] ?? '';
    $pbx_direction = $record['Direction'] ?? '';
    $status = $record['Status'] ?? '';
    $duration = $record['Duration'] ?? '';
    $recordingUrl = $record['Recording'] ?? '';
    $date = $record['Date'] ?? '';
    $time = $record['Time'] ?? '';
    $start_time = !empty($date) && !empty($time) ? date('Y-m-d H:i:s', strtotime("$date $time")) : date('Y-m-d H:i:s');
    $caller_dest = $record['Caller Destination'] ?? '';
    $codecs = $record['Codecs'] ?? '';
    $tta = $record['TTA'] ?? '';
    $pdd = $record['PDD'] ?? '';
    $call_data = json_encode($record);
    
    // Normalize BEFORE generating pbx_id
    $caller_norm = normalizePhone($caller_number);
    $dest_norm = normalizePhone($destination);
    $record['Caller Number'] = $caller_norm;
    $record['Destination'] = $dest_norm;
    $pbx_id = generatePbxId($record);
    
    // Determine direction based on caller/destination analysis
    // Check if caller is an agent's extension or official phone
    $is_caller_agent = in_array($caller_norm, $agentExtensions) || in_array($caller_norm, $agentOfficialPhones);
    $is_voicemail = ($caller_norm === '800' || $dest_norm === '800');
    
    if ($is_caller_agent || $caller_norm === '800') {
        // Agent or voicemail making call = Outbound (they initiated)
        $direction = 'outbound';
    } else {
        // External caller calling in = Inbound
        $direction = 'inbound';
    }
    
    // Override with PBX direction if available
    if (!empty($pbx_direction)) {
        $direction = strtolower($pbx_direction);
    }
    
    $localRecordingPath = '';
    if (!empty($recordingUrl)) {
        $recordingFileName = 'rec_' . md5($recordingUrl) . '.wav';
        $localRecordingPath = 'recordings/' . $recordingFileName;
        $fullSavePath = dirname(__DIR__) . '/' . $localRecordingPath;
        
        if (!file_exists($fullSavePath)) {
            $downloaded = downloadRecording($recordingUrl, $cookieFile, $fullSavePath);
            if ($downloaded) {
                $recordingsDownloaded++;
            } else {
                $recordingsFailed++;
                $localRecordingPath = $recordingUrl;
            }
        }
    }
    
    $stmt = $conn->prepare("SELECT id FROM calls WHERE pbx_id = ?");
    $stmt->bind_param("s", $pbx_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows === 0) {
        $agent_id = null;
        if (!empty($extension)) {
            $stmtAgent = $conn->prepare("SELECT id FROM agents WHERE extension = ?");
            $stmtAgent->bind_param("s", $extension);
            $stmtAgent->execute();
            $resultAgent = $stmtAgent->get_result();
            if ($agent = $resultAgent->fetch_assoc()) {
                $agent_id = $agent['id'];
            }
            $stmtAgent->close();
        }
        
        $stmtInsert = $conn->prepare("INSERT INTO calls (pbx_id, caller_number, caller_number_normalized, caller_name, destination, extension, direction, status, duration, recording_url, start_time, caller_destination, codecs, tta, pdd, call_data, agent_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtInsert->bind_param("ssssssssssssssssi", $pbx_id, $caller_number, $caller_norm, $caller_name, $destination, $extension, $direction, $status, $duration, $localRecordingPath, $start_time, $caller_dest, $codecs, $tta, $pdd, $call_data, $agent_id);
        $stmtInsert->execute();
        $stmtInsert->close();
        
        if (!empty($caller_number)) {
            findOrCreatePerson($caller_number);
        }
        $inserted++;
    } else {
        $skipped++;
    }
    $stmt->close();
    
    $records[] = $record;
}

if (file_exists($cookieFile)) unlink($cookieFile);

    $response = [
        "status" => "success",
        "total" => count($records),
        "inserted" => $inserted,
        "skipped" => $skipped,
        "recordings_downloaded" => $recordingsDownloaded,
        "recordings_failed" => $recordingsFailed
    ];

    if (count($records) === 0) {
        $response["debug"] = [
            "date_range" => "$startDate to $endDate",
            "body_length" => strlen($body),
            "body_preview" => substr($body, 0, 500)
        ];
        $response["status"] = "empty";
        $response["message"] = "No records found in date range. Try extending the date range.";
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
