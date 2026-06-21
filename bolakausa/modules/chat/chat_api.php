<?php
/**
 * Chat AJAX API Endpoint
 * Handles fetching new messages, sending messages, and fetching attachment candidates.
 */
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_role = get_user_role();
$user_id = $_SESSION['user_id'];

// Helper to format a message for JS rendering
function format_chat_message($c, $current_user_role) {
    $is_admin = in_array($c['sender_role'], ['admin', 'manager']);
    
    // For client view: "me" is wholesale_user
    // For admin view: "me" is admin/manager
    $is_me = false;
    if ($current_user_role === 'wholesale_user' || $current_user_role === 'executive') {
        $is_me = ($c['sender_role'] === 'wholesale_user');
    } else {
        $is_me = $is_admin;
    }

    $attachment = null;
    if ($c['attachment_type'] && $c['attachment_id']) {
        global $pdo;
        if ($c['attachment_type'] === 'product') {
            $stmt = $pdo->prepare("SELECT p.id, p.name, p.base_price, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image FROM products p WHERE p.id = ?");
            $stmt->execute([$c['attachment_id']]);
            $prod = $stmt->fetch();
            if ($prod) {
                $attachment = [
                    'type' => 'product',
                    'id' => $prod['id'],
                    'name' => $prod['name'],
                    'price' => number_format($prod['base_price'], 2),
                    'image' => $prod['main_image'] ?: '/bolakausa/public/images/default_product.png'
                ];
            }
        } elseif ($c['attachment_type'] === 'order') {
            $stmt = $pdo->prepare("SELECT id, total_amount, created_at, status FROM orders WHERE id = ?");
            $stmt->execute([$c['attachment_id']]);
            $ord = $stmt->fetch();
            if ($ord) {
                $attachment = [
                    'type' => 'order',
                    'id' => $ord['id'],
                    'total' => number_format($ord['total_amount'], 2),
                    'date' => date('M d, Y', strtotime($ord['created_at'])),
                    'status' => $ord['status']
                ];
            }
        }
    }

    return [
        'id' => $c['id'],
        'user_id' => $c['user_id'],
        'admin_id' => $c['admin_id'],
        'admin_name' => $c['admin_name'] ?: ($c['admin_username'] ?: 'Support Representative'),
        'message' => $c['message'],
        'sender_role' => $c['sender_role'],
        'is_read' => $c['is_read'],
        'is_me' => $is_me,
        'created_at' => date('M d, H:i', strtotime($c['created_at'])),
        'attachment' => $attachment
    ];
}

switch ($action) {
    case 'fetch':
        // Client fetching new messages
        if (!in_array($user_role, ['wholesale_user', 'executive'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $last_id = (int)($_GET['last_id'] ?? 0);

        // Mark incoming messages as read
        $stmt = $pdo->prepare("UPDATE chats SET is_read = 1 WHERE user_id = ? AND sender_role IN ('admin', 'manager') AND is_read = 0");
        $stmt->execute([$user_id]);

        // Fetch messages since last_id
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS admin_name, u.username AS admin_username
            FROM chats c
            LEFT JOIN users u ON c.admin_id = u.id
            WHERE c.user_id = ? AND c.id > ?
            ORDER BY c.id ASC
        ");
        $stmt->execute([$user_id, $last_id]);
        $raw_messages = $stmt->fetchAll();

        $formatted = [];
        foreach ($raw_messages as $c) {
            $formatted[] = format_chat_message($c, $user_role);
        }

        echo json_encode(['messages' => $formatted]);
        break;

    case 'fetch_admin':
        // Admin/Manager fetching messages for a specific conversation
        if (!in_array($user_role, ['admin', 'manager'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        $target_user_id = (int)($_GET['user_id'] ?? 0);
        $last_id = (int)($_GET['last_id'] ?? 0);

        if (!$target_user_id) {
            echo json_encode(['messages' => [], 'threads' => []]);
            exit;
        }

        // Mark customer messages as read
        $stmt = $pdo->prepare("UPDATE chats SET is_read = 1 WHERE user_id = ? AND sender_role = 'wholesale_user' AND is_read = 0");
        $stmt->execute([$target_user_id]);

        // Fetch messages since last_id
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS admin_name, u.username AS admin_username
            FROM chats c
            LEFT JOIN users u ON c.admin_id = u.id
            WHERE c.user_id = ? AND c.id > ?
            ORDER BY c.id ASC
        ");
        $stmt->execute([$target_user_id, $last_id]);
        $raw_messages = $stmt->fetchAll();

        $messages_formatted = [];
        foreach ($raw_messages as $c) {
            $messages_formatted[] = format_chat_message($c, $user_role);
        }

        // Fetch active threads list update for sidebar
        $threads = $pdo->query("
            SELECT u.id, u.username, u.full_name, 
            (SELECT COUNT(*) FROM chats WHERE user_id = u.id AND sender_role = 'wholesale_user' AND is_read = 0) as unread_count,
            (SELECT MAX(created_at) FROM chats WHERE user_id = u.id) as last_message_at
            FROM users u
            WHERE EXISTS (SELECT 1 FROM chats WHERE user_id = u.id)
            ORDER BY last_message_at DESC
        ")->fetchAll();

        $threads_formatted = [];
        foreach ($threads as $t) {
            $threads_formatted[] = [
                'id' => $t['id'],
                'display_name' => $t['full_name'] ?: $t['username'],
                'unread_count' => (int)$t['unread_count'],
                'last_message_at' => date('M d, H:i', strtotime($t['last_message_at']))
            ];
        }

        echo json_encode([
            'messages' => $messages_formatted,
            'threads' => $threads_formatted
        ]);
        break;

    case 'get_attachments':
        // Fetch products catalog
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.base_price, 
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image 
            FROM products p 
            WHERE p.is_active = 1 
            ORDER BY p.name ASC
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();

        $products_formatted = [];
        foreach ($products as $p) {
            $products_formatted[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'price' => number_format($p['base_price'], 2),
                'image' => $p['main_image'] ?: '/bolakausa/public/images/default_product.png'
            ];
        }

        // Fetch orders list
        $orders_formatted = [];
        if (in_array($user_role, ['admin', 'manager'])) {
            $target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            if ($target_user_id) {
                $stmt = $pdo->prepare("SELECT id, total_amount, created_at, status FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 20");
                $stmt->execute([$target_user_id]);
            } else {
                $stmt = $pdo->prepare("SELECT id, total_amount, created_at, status FROM orders ORDER BY id DESC LIMIT 20");
                $stmt->execute();
            }
        } else {
            // Client: their own orders only
            $stmt = $pdo->prepare("SELECT id, total_amount, created_at, status FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 20");
            $stmt->execute([$user_id]);
        }
        $orders = $stmt->fetchAll();
        foreach ($orders as $o) {
            $orders_formatted[] = [
                'id' => $o['id'],
                'total' => number_format($o['total_amount'], 2),
                'date' => date('M d, Y', strtotime($o['created_at'])),
                'status' => $o['status']
            ];
        }

        echo json_encode([
            'products' => $products_formatted,
            'orders' => $orders_formatted
        ]);
        break;

    case 'send':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method Not Allowed']);
            exit;
        }

        $message = trim($_POST['message'] ?? '');
        $attachment_type = $_POST['attachment_type'] ?? null;
        $attachment_id = !empty($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : null;

        if ($attachment_type && !in_array($attachment_type, ['product', 'order'])) {
            $attachment_type = null;
            $attachment_id = null;
        }

        if (!$message && !$attachment_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Message or attachment is required']);
            exit;
        }

        if (in_array($user_role, ['wholesale_user', 'executive'])) {
            // Client sending
            $stmt = $pdo->prepare("
                INSERT INTO chats (user_id, admin_id, message, sender_role, attachment_type, attachment_id) 
                VALUES (?, NULL, ?, 'wholesale_user', ?, ?)
            ");
            $stmt->execute([$user_id, $message, $attachment_type, $attachment_id]);
            $new_msg_id = $pdo->lastInsertId();
        } else if (in_array($user_role, ['admin', 'manager'])) {
            // Admin sending
            $target_user_id = (int)($_POST['user_id'] ?? 0);
            if (!$target_user_id) {
                http_response_code(400);
                echo json_encode(['error' => 'Recipient user ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO chats (user_id, admin_id, message, sender_role, attachment_type, attachment_id) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$target_user_id, $user_id, $message, $user_role, $attachment_type, $attachment_id]);
            $new_msg_id = $pdo->lastInsertId();
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        // Fetch the newly created message to return
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS admin_name, u.username AS admin_username
            FROM chats c
            LEFT JOIN users u ON c.admin_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$new_msg_id]);
        $c = $stmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => format_chat_message($c, $user_role)
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
