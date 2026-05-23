<?php
switch ($action) {
    case 'update_status':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("SELECT * FROM user_status WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $current = $stmt->fetch();
        
        $is_typing = isset($data['is_typing']) ? (int)$data['is_typing'] : ($current['is_typing'] ?? 0);
        $in_theater = isset($data['in_theater']) ? (int)$data['in_theater'] : ($current['in_theater'] ?? 0);
        $in_call = isset($data['in_call']) ? (int)$data['in_call'] : ($current['in_call'] ?? 0);
        
        if ($current) {
            $stmt = $pdo->prepare("UPDATE user_status SET last_seen = ?, is_typing = ?, in_theater = ?, in_call = ? WHERE user_id = ?");
            $stmt->execute([time(), $is_typing, $in_theater, $in_call, $user_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO user_status (user_id, last_seen, is_typing, in_theater, in_call) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, time(), $is_typing, $in_theater, $in_call]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'get_other_status':
        $other_user_id = ($user_id == 1) ? 2 : 1;
        $stmt = $pdo->prepare("SELECT last_seen, is_typing, in_theater, in_call FROM user_status WHERE user_id = ?");
        $stmt->execute([$other_user_id]);
        $status = $stmt->fetch();
        
        $response = ['status' => 'offline', 'last_seen' => null];
        if ($status) {
            $response['last_seen'] = (int)$status['last_seen'];
            $is_online = (time() - $status['last_seen']) < 10;
            if ($is_online) {
                $response['status'] = $status['is_typing'] ? 'typing' : 'active';
                $response['in_theater'] = (bool)$status['in_theater'];
                $response['in_call'] = (bool)$status['in_call'];
            }
        }
        echo json_encode($response);
        break;

    case 'theater_status':
        $tenSecondsAgo = time() - 10;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_status WHERE last_seen > ? AND in_theater = 1");
        $stmt->execute([$tenSecondsAgo]);
        $count = $stmt->fetchColumn();
        $other_id = ($user_id == 1) ? 2 : 1;
        $stmt2 = $pdo->prepare("SELECT last_seen, in_theater, in_call FROM user_status WHERE user_id = ?");
        $stmt2->execute([$other_id]);
        $other = $stmt2->fetch();
        $partner_online = $other && $other['last_seen'] > (time() - 10);
        $partner_in_theater = $partner_online && $other['in_theater'] == 1;
        $partner_in_call = $partner_online && $other['in_call'] == 1;
        echo json_encode(['users_in_theater' => (int)$count, 'partner_in_theater' => $partner_in_theater, 'partner_in_call' => $partner_in_call]);
        break;

    case 'leave_theater_beacon':
        $pdo->prepare("UPDATE user_status SET in_theater = 0 WHERE user_id = ?")->execute([$user_id]);
        echo json_encode(['success' => true]);
        break;
}
