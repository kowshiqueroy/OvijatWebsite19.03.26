<?php
/**
 * Manage Saved Replies (Canned Responses)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';
require_once __DIR__ . '/../includes/SupportManager.php';

AuthManager::requireRole(['admin', 'editor', 'support'], 'gatekeeper.php');

$db = Database::getInstance();
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) die("CSRF Invalid");
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $title = trim($_POST['title']);
        $body = trim($_POST['message']);
        
        if ($id) {
            $db->query("UPDATE canned_responses SET title = ?, message = ? WHERE id = ?", [$title, $body, $id]);
            $message = "Reply updated.";
        } else {
            $db->query("INSERT INTO canned_responses (title, message) VALUES (?, ?)", [$title, $body]);
            $message = "New saved reply added.";
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM canned_responses WHERE id = ?", [$id]);
        $message = "Reply deleted.";
    }
}

$pageTitle = 'Manage Saved Replies';
require_once 'layout_header.php';
$replies = $db->query("SELECT * FROM canned_responses ORDER BY title ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>💬 Saved Replies</h1>
    <div style="display: flex; gap: 10px;">
        <a href="support.php" class="btn btn-outline">&larr; Back to Live Chat</a>
        <button class="btn btn-primary" onclick="openReplyModal()">+ Add New Reply</button>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 16px;">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 250px;">Chip Label (Title)</th>
                    <th>Full Message Content</th>
                    <th style="width: 150px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($replies as $r): ?>
                <tr>
                    <td><strong style="color: var(--primary);"><?php echo htmlspecialchars($r['title']); ?></strong></td>
                    <td style="white-space: normal; line-height: 1.6; opacity: 0.8; font-size: 0.85rem;">
                        <?php echo nl2br(htmlspecialchars($r['message'])); ?>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 5px; justify-content: flex-end;">
                            <button class="btn btn-outline" style="padding: 5px 10px;" onclick='editReply(<?php echo json_encode($r); ?>)'>Edit</button>
                            <form method="POST" onsubmit="return confirm('Permanently delete this saved reply?')" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 5px 10px;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($replies)): ?>
                    <tr><td colspan="3" style="text-align:center; padding: 3rem; opacity: 0.5;">No saved replies yet. Click "+ Add New" to create one.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="replyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:center; justify-content:center;">
    <div class="card" style="width:500px; animation: modalIn 0.3s ease-out;">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Add Saved Reply</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="replyId">
            
            <div class="form-group">
                <label>Chip Label (Short Title)</label>
                <input type="text" name="title" id="replyTitle" placeholder="e.g. Greeting, Refund Policy..." required>
                <small style="opacity: 0.5;">This name appears on the button in the chat.</small>
            </div>
            
            <div class="form-group">
                <label>Message Content</label>
                <textarea name="message" id="replyBody" style="height: 150px;" placeholder="The full text that will be sent to the customer..." required></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary" style="padding: 1rem;">Save Template</button>
                <button type="button" onclick="closeModal()" class="btn btn-outline">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
    @keyframes modalIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
</style>

<script>
    function openReplyModal() {
        document.getElementById('modalTitle').innerText = 'Add Saved Reply';
        document.getElementById('replyId').value = '';
        document.getElementById('replyTitle').value = '';
        document.getElementById('replyBody').value = '';
        document.getElementById('replyModal').style.display = 'flex';
    }

    function editReply(data) {
        document.getElementById('modalTitle').innerText = 'Edit Saved Reply';
        document.getElementById('replyId').value = data.id;
        document.getElementById('replyTitle').value = data.title;
        document.getElementById('replyBody').value = data.message;
        document.getElementById('replyModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('replyModal').style.display = 'none';
    }
</script>

<?php require_once 'layout_footer.php'; ?>
