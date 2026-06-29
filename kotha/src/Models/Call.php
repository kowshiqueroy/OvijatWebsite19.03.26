<?php
// src/Models/Call.php

namespace Models;

use Database;
use PDO;

class Call {
    public static function logCall(int $callerId, string $chatId, string $callType = 'audio'): int {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("
            INSERT INTO call_records (caller_id, chat_id, call_type, recording_file) 
            VALUES (?, ?, ?, NULL)
        ");
        $stmt->execute([$callerId, $chatId, $callType]);
        return (int)$db->lastInsertId();
    }

    /**
     * Associate an uploaded recording file with the call.
     */
    public static function updateRecordingFile(int $callId, string $filename): bool {
        $db = Database::getCoreConnection();
        $stmt = $db->prepare("UPDATE call_records SET recording_file = ? WHERE id = ?");
        return $stmt->execute([$filename, $callId]);
    }

    /**
     * Get all calls that have associated WebM recordings for Admin dashboard.
     * Returns caller + receiver details for both parties.
     */
    public static function getAllRecordings(): array {
        $db   = Database::getCoreConnection();
        $stmt = $db->query("
            SELECT cr.id, cr.chat_id, cr.call_type, cr.recording_file,
                   cr.created_at, cr.duration_minutes,
                   u.full_name  AS caller_name,
                   u.email      AS caller_email,
                   (SELECT u2.full_name
                    FROM   chat_participants cp2
                    JOIN   users u2 ON u2.id = cp2.user_id
                    WHERE  cp2.chat_id   = cr.chat_id
                      AND  cp2.user_id  != COALESCE(cr.caller_id, -1)
                    LIMIT 1) AS receiver_name,
                   (SELECT u2.email
                    FROM   chat_participants cp2
                    JOIN   users u2 ON u2.id = cp2.user_id
                    WHERE  cp2.chat_id   = cr.chat_id
                      AND  cp2.user_id  != COALESCE(cr.caller_id, -1)
                    LIMIT 1) AS receiver_email
            FROM  call_records cr
            LEFT JOIN users u ON cr.caller_id = u.id
            ORDER BY cr.created_at DESC
        ");
        $all = $stmt->fetchAll();

        // Include calls that have data (finalized file OR actively-written temp file).
        // Temp files older than 1 hour are stale (call crashed / browser closed without
        // sending action=finish) and are excluded from the live list.
        // They will appear as orphaned files in the Storage → Orphaned Recordings tab.
        $filtered = [];
        $staleAge  = 3600; // 1 hour — adjust if calls legitimately last longer

        foreach ($all as $rec) {
            if (!empty($rec['recording_file'])) {
                // Finalized recording — always include
                $filtered[] = $rec;
            } else {
                $tempPath = RECORDINGS_DIR . '/temp_' . $rec['id'] . '.webm';
                if (file_exists($tempPath)) {
                    $ageSeconds = time() - filemtime($tempPath);
                    if ($ageSeconds < $staleAge) {
                        // Temp file is recent → genuinely live call
                        $filtered[] = $rec;
                    } else {
                        // Stale temp file (call crashed / browser closed without finishing).
                        // Auto-finalize: rename to a proper recording file and mark ended_at
                        // so it shows up in Final recordings instead of Live forever.
                        $finalName = 'call_' . $rec['id'] . '_stale.webm';
                        $finalPath = RECORDINGS_DIR . '/' . $finalName;
                        if (@rename($tempPath, $finalPath)) {
                            $db->prepare("UPDATE call_records
                                          SET recording_file = ?, ended_at = CURRENT_TIMESTAMP
                                          WHERE id = ? AND recording_file IS NULL")
                               ->execute([$finalName, $rec['id']]);
                            // Re-add as a finalized recording
                            $rec['recording_file'] = $finalName;
                            $filtered[] = $rec;
                        }
                    }
                }
            }
        }
        return $filtered;
    }

    /**
     * Delete one recording — removes the .webm file(s) on disk and the DB row.
     */
    public static function deleteRecording(int $id): bool {
        $db   = Database::getCoreConnection();
        $stmt = $db->prepare("SELECT recording_file FROM call_records WHERE id = ?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch();

        if ($row && !empty($row['recording_file'])) {
            $final = RECORDINGS_DIR . '/' . basename($row['recording_file']);
            if (file_exists($final)) @unlink($final);
        }
        // Also try in-progress temp file (live calls)
        $temp = RECORDINGS_DIR . '/temp_' . $id . '.webm';
        if (file_exists($temp)) @unlink($temp);

        return $db->prepare("DELETE FROM call_records WHERE id = ?")->execute([$id]);
    }

    /**
     * List files in server_records/ that are NOT referenced by any call_records row.
     * These are leftover temp files, partial uploads, or files from deleted call rows.
     */
    public static function getOrphanedFiles(): array {
        $allFiles = glob(RECORDINGS_DIR . '/*.webm') ?: [];
        if (!$allFiles) return [];

        $db   = Database::getCoreConnection();
        $stmt = $db->query("SELECT id, recording_file FROM call_records");
        $rows = $stmt->fetchAll();

        $validNames = [];
        foreach ($rows as $r) {
            if (!empty($r['recording_file'])) $validNames[] = basename($r['recording_file']);
            $validNames[] = 'temp_' . $r['id'] . '.webm';
        }

        $orphaned = [];
        foreach ($allFiles as $path) {
            $name = basename($path);
            if (!in_array($name, $validNames)) {
                $orphaned[] = [
                    'filename' => $name,
                    'size'     => filesize($path),
                    'modified' => filemtime($path),
                ];
            }
        }
        usort($orphaned, fn($a, $b) => $b['modified'] - $a['modified']);
        return $orphaned;
    }

    /** Delete a single file from server_records/ by filename (validated via basename). */
    public static function deleteOrphanedFile(string $filename): bool {
        $safe = basename($filename);
        // Only allow .webm files to prevent path traversal
        if (!preg_match('/^[\w\-]+\.webm$/', $safe)) return false;
        $path = RECORDINGS_DIR . '/' . $safe;
        if (!file_exists($path)) return false;
        return @unlink($path) !== false;
    }

    /** Delete ALL orphaned recording files. Returns count deleted. */
    public static function deleteAllOrphanedFiles(): int {
        $orphaned = self::getOrphanedFiles();
        $deleted  = 0;
        foreach ($orphaned as $f) {
            if (self::deleteOrphanedFile($f['filename'])) $deleted++;
        }
        return $deleted;
    }

    /**
     * Delete ALL recordings — clears every .webm in server_records/ and empties call_records.
     * Returns count of files removed from disk.
     */
    public static function deleteAllRecordings(): int {
        $db   = Database::getCoreConnection();
        $deleted = 0;

        // Remove every .webm file in the recordings directory
        foreach (glob(RECORDINGS_DIR . '/*.webm') as $f) {
            if (@unlink($f)) $deleted++;
        }
        // Clear the DB table
        $db->exec("DELETE FROM call_records");

        return $deleted;
    }

    /**
     * Get a specific call record with full caller + receiver details.
     */
    public static function getById(int $id): ?array {
        $db   = Database::getCoreConnection();
        $stmt = $db->prepare("
            SELECT cr.*,
                   u.full_name  AS caller_name,
                   u.email      AS caller_email,
                   (SELECT u2.full_name
                    FROM   chat_participants cp2
                    JOIN   users u2 ON u2.id = cp2.user_id
                    WHERE  cp2.chat_id   = cr.chat_id
                      AND  cp2.user_id  != COALESCE(cr.caller_id, -1)
                    LIMIT 1) AS receiver_name,
                   (SELECT u2.email
                    FROM   chat_participants cp2
                    JOIN   users u2 ON u2.id = cp2.user_id
                    WHERE  cp2.chat_id   = cr.chat_id
                      AND  cp2.user_id  != COALESCE(cr.caller_id, -1)
                    LIMIT 1) AS receiver_email
            FROM  call_records cr
            LEFT JOIN users u ON cr.caller_id = u.id
            WHERE cr.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
