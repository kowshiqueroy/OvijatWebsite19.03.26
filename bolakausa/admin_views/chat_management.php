<?php
/**
 * Admin Chat Management - Premium Glassmorphic Redesign
 */
restrict_to(['admin', 'manager']);

$success = '';
$error = '';

// Handle Admin Reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $target_user_id = (int)$_POST['user_id'];
    $message = trim($_POST['message'] ?? '');
    $admin_id = $_SESSION['user_id'];
    $sender_role = $_SESSION['user_role'];

    if ($message && $target_user_id) {
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, admin_id, message, sender_role) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$target_user_id, $admin_id, $message, $sender_role])) {
            $success = "Reply sent.";
        }
    }
}

// Fetch Conversation List
$conversations = $pdo->query("
    SELECT u.id, u.username, u.full_name, 
    (SELECT COUNT(*) FROM chats WHERE user_id = u.id AND sender_role = 'wholesale_user' AND is_read = 0) as unread_count,
    (SELECT MAX(created_at) FROM chats WHERE user_id = u.id) as last_message_at
    FROM users u
    WHERE EXISTS (SELECT 1 FROM chats WHERE user_id = u.id)
    ORDER BY last_message_at DESC
")->fetchAll();

// Handle Specific Conversation View
$view_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$messages = [];
if ($view_user_id) {
    // Mark as read
    $stmt = $pdo->prepare("UPDATE chats SET is_read = 1 WHERE user_id = ? AND sender_role = 'wholesale_user' AND is_read = 0");
    $stmt->execute([$view_user_id]);

    // Fetch messages
    $stmt = $pdo->prepare("SELECT * FROM chats WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$view_user_id]);
    $messages = $stmt->fetchAll();

    // Fetch user info
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$view_user_id]);
    $view_user = $stmt->fetch();
    $view_username = $view_user['full_name'] ?: $view_user['username'];
}
?>

<div class="section-title">
    <i class="fas fa-headset" style="color: #3b82f6;"></i>
    Client Communications
</div>

<div class="card" style="padding: 0; display: flex; height: 700px; overflow: hidden;">
    
    <!-- Sidebar: Conversations List -->
    <div style="width: 320px; background: rgba(15,23,42,0.02); border-right: 1px solid var(--glass-border); display: flex; flex-direction: column;">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--glass-border);">
            <h4 style="margin: 0; color: var(--secondary); font-weight: 800;">Active Threads</h4>
        </div>
        <div style="flex: 1; overflow-y: auto; padding: 1rem;">
            <?php if (!$conversations): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; margin-top: 1rem;">No active conversations.</p>
            <?php endif; ?>
            
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem;">
                <?php foreach ($conversations as $conv): ?>
                    <?php $is_active = ($view_user_id == $conv['id']); ?>
                    <li>
                        <a href="/bolakausa/admin/chats?user_id=<?php echo $conv['id']; ?>" style="display: block; padding: 1rem; text-decoration: none; border-radius: 12px; border: 1px solid <?php echo $is_active ? 'var(--primary)' : 'var(--glass-border)'; ?>; background: <?php echo $is_active ? 'rgba(16,185,129,0.1)' : 'rgba(255,255,255,0.5)'; ?>; transition: all 0.2s;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                <strong style="color: var(--secondary); font-size: 0.95rem;"><?php echo e($conv['full_name'] ?: $conv['username']); ?></strong>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span style="background: var(--accent); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 800; box-shadow: 0 0 8px var(--accent-glow);"><?php echo $conv['unread_count']; ?> New</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="far fa-clock"></i> <?php echo date('M d, H:i', strtotime($conv['last_message_at'])); ?></div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Main: Message View -->
    <div style="flex: 1; display: flex; flex-direction: column; background: rgba(255,255,255,0.3);">
        <?php if ($view_user_id): ?>
            <!-- Active Chat Header -->
            <div style="padding: 1.5rem; background: var(--glass-bg); border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; color: var(--secondary); font-weight: 800;">Negotiating with <?php echo e($view_username); ?></h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Partner ID: #<?php echo $view_user_id; ?></p>
                </div>
            </div>

            <!-- Messages Log -->
            <div id="admin-chat-box" style="flex: 1; overflow-y: auto; padding: 2rem; display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($messages as $m): ?>
                    <?php $is_admin = in_array($m['sender_role'], ['admin', 'manager']); ?>
                    <div style="max-width: 75%; padding: 1.25rem; border-radius: 16px; border: 1px solid var(--glass-border); <?php echo $is_admin ? 'align-self: flex-end; background: var(--secondary); color: white; border-bottom-right-radius: 4px;' : 'align-self: flex-start; background: rgba(255,255,255,0.9); color: var(--text-main); border-bottom-left-radius: 4px; box-shadow: var(--shadow-sm);'; ?>">
                        <div style="font-size: 0.75rem; margin-bottom: 0.5rem; opacity: 0.8; font-weight: 700; text-transform: uppercase;">
                            <?php echo $is_admin ? 'Bolakausa Support' : e($view_username); ?> &bull; <?php echo date('M d, H:i', strtotime($m['created_at'])); ?>
                        </div>
                        <div style="line-height: 1.5; font-size: 0.95rem;">
                            <?php echo nl2br(e($m['message'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Input Area -->
            <div style="padding: 1.5rem; background: var(--glass-bg); border-top: 1px solid var(--glass-border);">
                <form method="POST" style="display: flex; gap: 1rem;">
                    <input type="hidden" name="user_id" value="<?php echo $view_user_id; ?>">
                    <textarea name="message" placeholder="Type your response to <?php echo e($view_username); ?>..." style="flex: 1; height: 60px; resize: none; border-radius: 12px; padding: 1rem; border: 1px solid var(--border-light); background: rgba(255,255,255,0.7);" required></textarea>
                    <button type="submit" name="send_reply" class="btn btn-blue" style="border-radius: 12px; padding: 0 2rem;">
                        <i class="fas fa-reply"></i> Send
                    </button>
                </form>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); opacity: 0.5;">
                <i class="fas fa-comments" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                <h2>Select a conversation</h2>
                <p>Choose a thread from the sidebar to view messages.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    const chatBox = document.getElementById('admin-chat-box');
    if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
