<?php
// src/Controller/CallController.php

namespace Controller;

use Models\Call;
use Models\Chat;
use Models\Plan;

class CallController extends AuthController {
    
    /**
     * Call UI view.
     */
    public function callInterface(): void {
        if (!isset($_SESSION['user_id']) || \Models\User::getById($_SESSION['user_id']) === null) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            $this->redirect('/login');
        }

        $chatId = $_GET['chatId'] ?? '';
        $type = $_GET['type'] ?? 'audio'; // 'audio' or 'video'
        $mode = $_GET['mode'] ?? 'outgoing'; // 'outgoing' or 'incoming'
        
        $userId = $_SESSION['user_id'];
        
        // Ensure participant
        if ($_SESSION['is_admin'] != 1 && !Chat::isParticipant($chatId, $userId)) {
            $this->redirect('/dashboard');
        }

        // Get participants to display caller/receiver name
        $participants = Chat::getParticipants($chatId);
        $partnerName = 'Unknown User';
        $partnerId = null;
        foreach ($participants as $p) {
            if ($p['id'] != $userId) {
                $partnerName = $p['full_name'];
                $partnerId = $p['id'];
                break;
            }
        }
        if ($partnerId) {
            $db = \Database::getCoreConnection();
            $stmtNick = $db->prepare("
                SELECT nickname FROM nicknames 
                WHERE user_id = ? AND target_type = 'user' AND target_id = ?
            ");
            $stmtNick->execute([$userId, (string)$partnerId]);
            $nickRow = $stmtNick->fetch();
            if ($nickRow) {
                $partnerName = $nickRow['nickname'];
            }
        }

        $this->render('call', [
            'chatId' => $chatId,
            'callType' => $type,
            'callMode' => $mode,
            'partnerName' => $partnerName,
            'partnerId' => $partnerId,
            'userId' => $userId,
            'userName' => $_SESSION['user_name']
        ]);
    }

    /**
     * API to start, append chunks, and finish a call recording.
     */
    public function saveCallRecord(): void {
        if (!isset($_SESSION['user_id']) || \Models\User::getById($_SESSION['user_id']) === null) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            header("HTTP/1.1 403 Forbidden");
            exit;
        }

        $action = $_POST['action'] ?? '';
        
        header('Content-Type: application/json');

        if ($action === 'start') {
            $chatId = $_POST['chat_id'] ?? '';
            $callerId = $_SESSION['user_id'];
            
            if (empty($chatId)) {
                echo json_encode(['success' => false, 'error' => 'Missing chat ID']);
                exit;
            }

            $callType = $_POST['call_type'] ?? 'audio';
            $callId = Call::logCall($callerId, $chatId, $callType);
            
            // Send call notification through SSE
            $callerName = $_SESSION['user_name'];
            
            Chat::publishEvent($chatId, $callerId, 'call', [
                'chat_id' => $chatId,
                'caller_id' => $callerId,
                'caller_name' => $callerName,
                'call_type' => $callType,
                'call_id' => $callId,
                'peer_id' => 'kotha_user_' . $callerId
            ]);

            echo json_encode(['success' => true, 'call_id' => $callId]);
            exit;
        } 
        
        if ($action === 'append') {
            $callId = intval($_POST['call_id'] ?? 0);
            
            if ($callId <= 0 || !isset($_FILES['video_chunk'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid append parameters']);
                exit;
            }

            $tempFile = RECORDINGS_DIR . '/temp_' . $callId . '.webm';
            $chunkFile = $_FILES['video_chunk']['tmp_name'];

            $out = fopen($tempFile, 'ab');
            $in = fopen($chunkFile, 'rb');
            if ($out && $in) {
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to write chunk']);
            }
            exit;
        } 
        
        if ($action === 'finish') {
            $callId          = (int)($_POST['call_id']          ?? 0);
            $durationMinutes = (int)($_POST['duration_minutes'] ?? 0);

            if ($callId <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid finish parameters']);
                exit;
            }

            // Record duration for usage tracking
            $callRecord = Call::getById($callId);
            if ($callRecord && $durationMinutes > 0) {
                $db = \Database::getCoreConnection();
                $db->prepare("UPDATE call_records SET ended_at = CURRENT_TIMESTAMP, duration_minutes = ? WHERE id = ?")
                   ->execute([$durationMinutes, $callId]);

                // Increment caller's daily call usage
                $callType  = $callRecord['call_type'] ?? 'audio';
                $callerId  = (int)($callRecord['caller_id'] ?? 0);
                if ($callerId > 0) {
                    Plan::incrementUsage($callerId, $callType . '_call', $durationMinutes);
                }
            }

            $tempFile      = RECORDINGS_DIR . '/temp_' . $callId . '.webm';
            $finalFilename = 'call_' . $callId . '_' . bin2hex(random_bytes(4)) . '.webm';
            $finalFile     = RECORDINGS_DIR . '/' . $finalFilename;

            if (file_exists($tempFile)) {
                if (rename($tempFile, $finalFile)) {
                    Call::updateRecordingFile($callId, $finalFilename);
                    echo json_encode(['success' => true, 'file' => $finalFilename]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to finalize recording']);
                }
            } else {
                echo json_encode(['success' => true, 'message' => 'No recording data created']);
            }
            exit;
        }

        // Notify all chat participants that this call ended (fires cross-tab SSE disconnect)
        if ($action === 'end') {
            $chatId = $_POST['chat_id'] ?? '';
            if (!empty($chatId)) {
                Chat::publishEvent($chatId, $_SESSION['user_id'], 'call-end', [
                    'chat_id'  => $chatId,
                    'ended_by' => $_SESSION['user_id'],
                ]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        exit;
    }
}
