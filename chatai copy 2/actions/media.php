<?php
switch ($action) {
    case 'save_image':
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            $fileType = $file['type'];
            
            $allowedMimes = [
                'image/jpeg', 'image/png', 'image/webp',
                'video/mp4', 'video/webm', 'video/quicktime',
                'audio/webm', 'audio/mp4', 'audio/ogg', 'audio/wav'
            ];
            
            if (!in_array($fileType, $allowedMimes)) {
                echo json_encode(array('success' => false, 'error' => 'Invalid file type'));
                break;
            }

            $hash = md5_file($file['tmp_name']);
            $stmt = $pdo->prepare("SELECT image_data FROM saved_images WHERE file_hash = ? LIMIT 1");
            $stmt->execute([$hash]);
            $existing = $stmt->fetchColumn();
            if ($existing && file_exists(__DIR__ . '/../' . $existing)) {
                $is_v = (strpos($fileType, 'audio') !== false) ? 1 : 0;
                $is_vid = (strpos($fileType, 'video') !== false) ? 1 : 0;
                $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video, file_hash) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute(array($user_id, $existing, $is_v, $is_vid, $hash));
                echo json_encode(array('success' => true));
                break;
            }

            $is_v = (strpos($fileType, 'audio') !== false) ? 1 : 0;
            $is_vid = (strpos($fileType, 'video') !== false) ? 1 : 0;
            $prefix = $is_v ? 'p_voice' : ($is_vid ? 'p_video' : 'p_img');
            
            $mimeToExt = array(
                'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
                'audio/webm' => 'webm', 'audio/mp4' => 'mp4', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav'
            );
            $extension = $mimeToExt[$fileType] ?? 'bin';
            
            $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
            $targetDir = __DIR__ . '/../premium_vault/';
            if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
            $targetPath = $targetDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $path = 'premium_vault/' . $filename;
                $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video, file_hash) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute(array($user_id, $path, $is_v, $is_vid, $hash));
                echo json_encode(array('success' => true));
            } else {
                echo json_encode(array('success' => false, 'error' => 'Failed to move uploaded file'));
            }
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $media = $data['media_data'] ?? $data['image_data'] ?? '';
        $is_v = $data['is_voice'] ?? 0;
        $is_vid = $data['is_video'] ?? 0;
        if (empty($media)) { echo json_encode(array('success' => false, 'error' => 'No media')); break; }
        
        $prefix = $is_v ? 'p_voice' : ($is_vid ? 'p_video' : 'p_img');
        $path = saveBase64File($media, $prefix, 'premium_vault');
        
        if (!$path) { echo json_encode(array('success' => false, 'error' => 'Failed save')); break; }
        $hash = file_exists(__DIR__ . '/../' . $path) ? md5_file(__DIR__ . '/../' . $path) : null;
        $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video, file_hash) VALUES (?, ?, ?, ?, ?)')
            ->execute(array($user_id, $path, $is_v, $is_vid, $hash));
        echo json_encode(array('success' => true));
        break;

    case 'get_saved_images':
        $stmt = $pdo->prepare("SELECT id, user_id, image_data, is_voice, is_video, file_hash, strftime('%Y-%m-%dT%H:%M:%SZ', saved_at) as saved_at FROM saved_images WHERE user_id = ? ORDER BY saved_at DESC");
        $stmt->execute(array($user_id));
        $dbItems = $stmt->fetchAll();
        echo json_encode($dbItems);
        break;

    case 'vault_audit':
        // 1. Get ALL files in DB for orphan detection
        $allStmt = $pdo->prepare("SELECT image_data FROM saved_images");
        $allStmt->execute();
        $allDbFiles = $allStmt->fetchAll(PDO::FETCH_COLUMN);
        $allDbBasenames = array_map(function($f) { return basename($f); }, $allDbFiles);

        // 2. Get this user's files to check for missing ones
        $stmt = $pdo->prepare("SELECT id, image_data, strftime('%Y-%m-%dT%H:%M:%SZ', saved_at) as saved_at FROM saved_images WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $myItems = $stmt->fetchAll();
        
        $missingInFolder = [];
        foreach ($myItems as $item) {
            $fullPath = __DIR__ . '/../' . $item['image_data'];
            if (!file_exists($fullPath)) {
                $missingInFolder[] = $item;
            }
        }

        // 3. Scan disk for orphans
        $orphanFiles = [];
        $vaultDir = __DIR__ . '/../premium_vault/';
        if (is_dir($vaultDir)) {
            $diskFiles = scandir($vaultDir);
            foreach ($diskFiles as $f) {
                if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
                if (!in_array($f, $allDbBasenames)) {
                    $orphanFiles[] = [
                        'name' => $f,
                        'size' => filesize($vaultDir . $f),
                        'mtime' => filemtime($vaultDir . $f)
                    ];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'orphan_count' => count($orphanFiles),
            'orphans' => $orphanFiles,
            'missing_count' => count($missingInFolder),
            'missing' => $missingInFolder
        ]);
        break;

    case 'vault_cleanup':
        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? ''; // 'orphans' or 'missing'
        
        if ($type === 'orphans') {
            // Delete files in folder not in DB
            $stmt = $pdo->prepare("SELECT image_data FROM saved_images");
            $stmt->execute();
            $allDbFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $allDbFileBasenames = array_map(function($f) { return basename($f); }, $allDbFiles);
            
            $vaultDir = __DIR__ . '/../premium_vault/';
            $count = 0;
            if (is_dir($vaultDir)) {
                $diskFiles = scandir($vaultDir);
                foreach ($diskFiles as $f) {
                    if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
                    if (!in_array($f, $allDbFileBasenames)) {
                        if (@unlink($vaultDir . $f)) $count++;
                    }
                }
            }
            echo json_encode(['success' => true, 'count' => $count]);
        } elseif ($type === 'missing') {
            // Delete DB records where file is missing
            $stmt = $pdo->prepare("SELECT id, image_data FROM saved_images WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $dbItems = $stmt->fetchAll();
            $count = 0;
            foreach ($dbItems as $item) {
                if (!file_exists(__DIR__ . '/../' . $item['image_data'])) {
                    $del = $pdo->prepare("DELETE FROM saved_images WHERE id = ?");
                    $del->execute([$item['id']]);
                    $count++;
                }
            }
            echo json_encode(['success' => true, 'count' => $count]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid type']);
        }
        break;

    case 'delete_saved_image':
        $data = json_decode(file_get_contents('php://input'), true);
        $image_id = $data['image_id'] ?? 0;
        $image_path = $data['image_path'] ?? '';
        $path = '';

        if ($image_id > 0) {
            $stmt = $pdo->prepare('SELECT image_data, user_id FROM saved_images WHERE id = ?');
            $stmt->execute(array($image_id));
            $row = $stmt->fetch();
            if (!$row || $row['user_id'] != $user_id) {
                echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
                break;
            }
            $path = $row['image_data'];
            $pdo->prepare('DELETE FROM saved_images WHERE id = ?')->execute(array($image_id));
        } else if ($image_path) {
            // For direct path deletion, we must still verify ownership/access
            $cleanPath = 'premium_vault/' . basename($image_path);
            $stmt = $pdo->prepare('SELECT id FROM saved_images WHERE image_data = ? AND user_id = ?');
            $stmt->execute(array($cleanPath, $user_id));
            if ($stmt->fetch()) {
                $path = $cleanPath;
                $pdo->prepare('DELETE FROM saved_images WHERE image_data = ? AND user_id = ?')->execute(array($cleanPath, $user_id));
            } else {
                echo json_encode(array('success' => false, 'error' => 'Unauthorized or file not found in vault'));
                break;
            }
        }

        if ($path && strpos($path, 'premium_vault/') === 0) {
            // Deduplication safety check: only delete from disk if no other record uses this file
            $stCheck = $pdo->prepare("SELECT COUNT(*) FROM saved_images WHERE image_data = ?");
            $stCheck->execute([$path]);
            if ($stCheck->fetchColumn() == 0) {
                $fullPath = __DIR__ . '/../' . $path;
                if (file_exists($fullPath)) unlink($fullPath);
            }
        }
        
        echo json_encode(array('success' => true));
        break;

    case 'save_recording':
        $error = null;
        if (isset($_FILES['recording'])) {
            $file = $_FILES['recording'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'Upload error code: ' . $file['error'];
            } else {
                $rec_session_id = $_POST['rec_session_id'] ?? '';
                $chunk_index = (int)($_POST['chunk_index'] ?? 0);
                
                if ($rec_session_id) {
                    $rec_session_id = preg_replace('/[^a-zA-Z0-9]/', '', $rec_session_id);
                    $filename = 'recording_' . $user_id . '_' . $rec_session_id . '.webm';
                } else {
                    $filename = 'recording_' . $user_id . '_' . time() . '.webm';
                }
                
                $targetDir = __DIR__ . '/../premium_vault/';
                if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
                $targetPath = $targetDir . $filename;
                
                $content = file_get_contents($file['tmp_name']);
                if ($chunk_index == 0) {
                    $hash = md5($content);
                    $stmt = $pdo->prepare("SELECT image_data FROM saved_images WHERE file_hash = ? LIMIT 1");
                    $stmt->execute([$hash]);
                    $existing = $stmt->fetchColumn();
                    if ($existing && file_exists(__DIR__ . '/../' . $existing)) {
                        $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video, file_hash) VALUES (?, ?, 0, 1, ?)');
                        $stmt->execute(array($user_id, $existing, $hash));
                        echo json_encode(array('success' => true, 'filename' => basename($existing)));
                        break;
                    }

                    if (file_put_contents($targetPath, $content)) {
                        $path = 'premium_vault/' . $filename;
                        try {
                            $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video, file_hash) VALUES (?, ?, 0, 1, ?)');
                            $stmt->execute(array($user_id, $path, $hash));
                            echo json_encode(array('success' => true, 'filename' => $filename));
                            break;
                        } catch (Exception $e) { $error = 'DB insert failed: ' . $e->getMessage(); }
                    } else { $error = 'file_put_contents failed'; }
                } else {
                    if (file_put_contents($targetPath, $content, FILE_APPEND)) {
                        echo json_encode(array('success' => true, 'filename' => $filename, 'appended' => true));
                        break;
                    } else { $error = 'FILE_APPEND failed'; }
                }
            }
        } else {
            $error = 'No recording file in $_FILES';
        }
        echo json_encode(array('success' => false, 'error' => $error));
        break;

    case 'upload_chunk':
        $chunkIndex = (int)($_POST['chunkIndex'] ?? 0);
        $totalChunks = (int)($_POST['totalChunks'] ?? 0);
        $uploadId = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['uploadId'] ?? '');
        $filename = $_POST['filename'] ?? 'file.bin';
        $targetDirType = $_POST['targetDir'] ?? 'uploads'; // uploads or premium_vault

        if (!$uploadId || !$totalChunks) {
            echo json_encode(['success' => false, 'error' => 'Missing metadata']);
            break;
        }

        $tempDir = __DIR__ . '/../uploads/temp_' . $uploadId;
        if (!file_exists($tempDir)) mkdir($tempDir, 0755, true);

        $chunkPath = $tempDir . '/chunk_' . $chunkIndex;
        if (move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
            // Check if all chunks are uploaded
            $uploadedChunks = count(glob($tempDir . '/chunk_*'));
            if ($uploadedChunks === $totalChunks) {
                // Finalize upload
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'webm', 'mov', 'ogg', 'wav', 'mp3'];
                if (!in_array($ext, $allowedExts)) $ext = 'bin';
                
                // Reassemble to get full hash before deciding to keep it
                $finalDir = __DIR__ . '/../' . $targetDirType . '/';
                if (!file_exists($finalDir)) mkdir($finalDir, 0755, true);
                
                // Temporary final path to calculate hash
                $tempFinalPath = $tempDir . '/final_assembled.tmp';
                $out = fopen($tempFinalPath, 'wb');
                for ($i = 0; $i < $totalChunks; $i++) {
                    $partPath = $tempDir . '/chunk_' . $i;
                    $in = fopen($partPath, 'rb');
                    while ($buff = fread($in, 4096)) fwrite($out, $buff);
                    fclose($in);
                }
                fclose($out);
                
                $hash = md5_file($tempFinalPath);
                
                // Check for duplicates
                $table = ($targetDirType === 'premium_vault') ? 'saved_images' : 'messages';
                $col = ($targetDirType === 'premium_vault') ? 'image_data' : 'content';
                $stmt = $pdo->prepare("SELECT $col FROM $table WHERE file_hash = ? LIMIT 1");
                $stmt->execute([$hash]);
                $existing = $stmt->fetchColumn();
                
                if ($existing && file_exists(__DIR__ . '/../' . $existing)) {
                    $dbPath = $existing;
                    unlink($tempFinalPath);
                    // Clean temp chunks
                    for ($i = 0; $i < $totalChunks; $i++) unlink($tempDir . '/chunk_' . $i);
                    rmdir($tempDir);
                } else {
                    $prefix = ($targetDirType === 'premium_vault') ? 'p_file' : 'file';
                    $finalFilename = $prefix . '_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
                    $finalPath = $finalDir . $finalFilename;
                    rename($tempFinalPath, $finalPath);
                    
                    // Clean temp chunks
                    for ($i = 0; $i < $totalChunks; $i++) unlink($tempDir . '/chunk_' . $i);
                    rmdir($tempDir);
                    $dbPath = $targetDirType . '/' . $finalFilename;
                }

                $is_v = (in_array($ext, ['wav', 'ogg', 'mp3'])) ? 1 : 0;
                $is_vid = (in_array($ext, ['mp4', 'webm', 'mov'])) ? 1 : 0;

                if ($targetDirType === 'premium_vault') {
                    $stmt = $pdo->prepare('INSERT INTO saved_images (user_id, image_data, is_voice, is_video, file_hash) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute(array($user_id, $dbPath, $is_v, $is_vid, $hash));
                }

                echo json_encode(['success' => true, 'path' => $dbPath, 'is_voice' => $is_v, 'is_video' => $is_vid, 'filename' => basename($dbPath)]);
            } else {
                echo json_encode(['success' => true, 'chunk' => $chunkIndex, 'status' => 'partial']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save chunk']);
        }
        break;
}
