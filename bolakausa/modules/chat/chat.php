<?php
/**
 * Wholesale User Chat Interface - Premium Glassmorphic Redesign
 */
restrict_to(['wholesale_user']);

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Message Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message'] ?? '');
    if ($message) {
        $stmt = $pdo->prepare("INSERT INTO chats (user_id, message, sender_role) VALUES (?, ?, 'wholesale_user')");
        if ($stmt->execute([$user_id, $message])) {
            $success = "Message sent.";
        }
    }
}

// Mark messages as read by user (messages from admin)
$stmt = $pdo->prepare("UPDATE chats SET is_read = 1 WHERE user_id = ? AND sender_role IN ('admin', 'manager') AND is_read = 0");
$stmt->execute([$user_id]);

// Fetch Chat History
$stmt = $pdo->prepare("SELECT * FROM chats WHERE user_id = ? ORDER BY created_at ASC");
$stmt->execute([$user_id]);
$chats = $stmt->fetchAll();
?>

<div class="section-title">
    <i class="fas fa-comments" style="color: var(--primary);"></i>
    Partner Support & Negotiation
</div>

<div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 600px;">
    <!-- Chat Header -->
    <div style="padding: 1.5rem; background: rgba(15,23,42,0.02); border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0; color: var(--secondary); font-weight: 800;">Direct Line</h3>
            <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Dedicated support for <?php echo e(get_setting($pdo, 'company_name')); ?> Partners</p>
        </div>
        <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 10px var(--primary-glow);"></div>
    </div>

    <!-- Chat Messages Area -->
    <div id="chat-box" style="flex: 1; overflow-y: auto; padding: 2rem; background: rgba(255,255,255,0.2); display: flex; flex-direction: column; gap: 1rem;">
        <?php if (!$chats): ?>
            <div style="text-align: center; color: var(--text-muted); margin-top: 2rem;">
                <i class="fas fa-handshake" style="font-size: 3rem; opacity: 0.2; margin-bottom: 1rem;"></i>
                <p>No messages yet. Start a conversation to negotiate terms or report an issue.</p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($chats as $c): ?>
            <?php $is_me = ($c['sender_role'] === 'wholesale_user'); ?>
            <div style="max-width: 75%; padding: 1.25rem; border-radius: 16px; border: 1px solid var(--glass-border); <?php echo $is_me ? 'align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 4px; box-shadow: 0 4px 15px var(--primary-glow);' : 'align-self: flex-start; background: rgba(255,255,255,0.8); color: var(--text-main); border-bottom-left-radius: 4px; box-shadow: var(--shadow-sm);'; ?>">
                <div style="font-size: 0.75rem; margin-bottom: 0.5rem; opacity: 0.8; font-weight: 700; text-transform: uppercase;">
                    <?php echo $is_me ? 'You' : 'Account Manager'; ?> &bull; <?php echo date('M d, H:i', strtotime($c['created_at'])); ?>
                </div>
                <div style="line-height: 1.5; font-size: 0.95rem;">
                    <?php echo nl2br(e($c['message'])); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Message Input -->
    <div style="padding: 1.5rem; background: var(--glass-bg); border-top: 1px solid var(--glass-border);">
        <form method="POST" style="display: flex; gap: 1rem;">
            <textarea name="message" placeholder="Type your message here..." style="flex: 1; height: 60px; resize: none; border-radius: 12px; padding: 1rem; border: 1px solid var(--border-light); background: rgba(255,255,255,0.7);" required></textarea>
            <button type="submit" name="send_message" class="btn btn-green" style="border-radius: 12px; padding: 0 2rem;">
                <i class="fas fa-paper-plane"></i> Send
            </button>
        </form>
    </div>
</div>

<script>
    // Auto-scroll to latest message
    const chatBox = document.getElementById('chat-box');
    chatBox.scrollTop = chatBox.scrollHeight;
</script>
