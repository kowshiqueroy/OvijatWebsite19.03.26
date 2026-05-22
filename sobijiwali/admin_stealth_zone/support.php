<?php
/**
 * Admin Support Hub - Command Center Edition
 * Features Drop-up tools for Products, Orders, and Saved Replies.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/AuthManager.php';
require_once __DIR__ . '/../includes/SupportManager.php';

// Auth Check
AuthManager::requireRole(['admin', 'editor', 'support'], 'gatekeeper.php');

$sm = new SupportManager();
$db = Database::getInstance();

$selectedThreadId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Handle Actions (Metadata)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_thread') {
    if (!AuthManager::verifyCSRFToken($_POST['csrf_token'] ?? '')) die("CSRF Invalid");
    $sm->updateThread($selectedThreadId, [
        'priority' => $_POST['priority'],
        'status' => $_POST['status'],
        'internal_notes' => $_POST['internal_notes']
    ]);
    header("Location: support.php?id=$selectedThreadId&success=updated");
    exit;
}

$pageTitle = 'Command Center';
require_once 'layout_header.php';

$threads = $sm->getAdminThreads();
$canned = $sm->getCannedResponses();
$allProducts = $db->query("SELECT p.name, p.slug, p.base_price, c.name as cat_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.name ASC LIMIT 50")->fetchAll();

$selectedThread = null;
$customerData = null;

if ($selectedThreadId) {
    $sm->markAsRead($selectedThreadId, 'admin');
    $selectedThread = $db->query("SELECT t.*, u.email, u.role, u.last_login_at, u.created_at as joined_at, 
                                 (SELECT email FROM users WHERE id = t.last_admin_id) as last_admin_email
                                 FROM chat_threads t 
                                 JOIN users u ON t.customer_id = u.id 
                                 WHERE t.id = ?", [$selectedThreadId])->fetch();
    
    if ($selectedThread) {
        $email = strtolower(trim($selectedThread['email']));
        $uid = (int)$selectedThread['customer_id'];

        // 1. Fetch IDs from both identity sources separately
        $regIds = $db->query("SELECT id FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10", [$uid])->fetchAll(PDO::FETCH_COLUMN);
        $guestIds = $db->query("SELECT id FROM orders WHERE LOWER(TRIM(guest_email)) = ? ORDER BY id DESC LIMIT 10", [$email])->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. Merge, Deduplicate and Sort (Bulletproof Uniqueness)
        $masterIds = array_unique(array_merge($regIds, $guestIds));
        rsort($masterIds); // Sort IDs descending (newest first)
        $finalIds = array_slice($masterIds, 0, 5);
        
        $recentOrders = [];
        foreach($finalIds as $orderId) {
            $order = $db->query("SELECT id, status, total_amount, created_at FROM orders WHERE id = ?", [$orderId])->fetch();
            if ($order) {
                // Fetch specific items
                $order['items'] = $db->query("SELECT oi.quantity, p.name 
                                           FROM order_items oi 
                                           JOIN product_variations v ON oi.product_variation_id = v.id 
                                           JOIN products p ON v.product_id = p.id 
                                           WHERE oi.order_id = ?", [$orderId])->fetchAll();
                $recentOrders[] = $order;
            }
        }

        $customerData = [
            'total_orders' => count($masterIds),
            'total_spent' => $db->query("SELECT SUM(total_amount) FROM orders WHERE id IN (" . (empty($masterIds) ? '0' : implode(',', $masterIds)) . ") AND status='shipped'")->fetchColumn() ?: 0,
            'recent_orders' => $recentOrders
        ];
    }
}
?>

<style>
    .admin-main { height: 100vh; overflow: hidden; }
    .content-body { padding: 0 !important; height: calc(100vh - 60px); }

    .support-container { display: flex; height: 100%; background: #f4f7f6; }

    /* Left: Modern Sidebar */
    .support-sidebar { width: 320px; background: white; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; }
    .sidebar-header { padding: 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fafafa; }
    .sidebar-header h2 { font-size: 1rem; font-weight: 800; margin: 0; color: var(--sidebar); display: flex; justify-content: space-between; align-items: center; }
    
    .thread-list { flex: 1; overflow-y: auto; }
    .thread-row { display: block; padding: 1.25rem 1.5rem; text-decoration: none; color: inherit; border-bottom: 1px solid #f8fafc; transition: all 0.2s; position: relative; border-left: 5px solid transparent; }
    .thread-row:hover { background: #f8fafc; }
    .thread-row.active { background: #f0fdf4; border-left-color: var(--primary); }
    
    .row-meta { display: flex; justify-content: space-between; margin-bottom: 0.3rem; }
    .row-email { font-weight: 800; font-size: 0.85rem; color: #1a202c; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px; }
    .row-time { font-size: 0.65rem; opacity: 0.4; font-weight: 700; }
    .row-prio { width: 8px; height: 8px; border-radius: 50%; }

    /* Center: Premium Chat */
    .support-chat { flex: 1; display: flex; flex-direction: column; background: #fff; position: relative; }
    .chat-nav { padding: 1.2rem 2.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: white; z-index: 50; }
    .chat-feed { flex: 1; overflow-y: auto; padding: 2.5rem; display: flex; flex-direction: column; gap: 1.5rem; background: #fcfcfc; scroll-behavior: smooth; }
    
    .bubble { max-width: 75%; padding: 1.2rem 1.6rem; border-radius: 20px; font-size: 0.95rem; line-height: 1.6; position: relative; }
    .bubble-cust { align-self: flex-start; background: #f1f5f9; color: #1a202c; border-bottom-left-radius: 2px; }
    .bubble-staff { align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 2px; box-shadow: 0 4px 15px rgba(45,90,39,0.15); }
    .b-time { font-size: 0.6rem; margin-top: 0.6rem; opacity: 0.5; text-align: right; font-weight: 800; }

    /* Toolbars & Drop-ups */
    .chat-footer { padding: 1.5rem 2.5rem; border-top: 1px solid #eee; position: relative; background: white; }
    
    .tools-bar { display: flex; gap: 10px; margin-bottom: 1rem; }
    .tool-btn { padding: 8px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.75rem; font-weight: 800; color: #475569; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
    .tool-btn:hover { border-color: var(--primary); background: #f0fdf4; color: var(--primary); }
    .tool-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

    .drop-up-panel { display: none; position: absolute; bottom: 100%; left: 2.5rem; right: 2.5rem; background: white; border: 1px solid #e2e8f0; border-radius: 20px 20px 0 0; box-shadow: 0 -10px 40px rgba(0,0,0,0.1); max-height: 400px; overflow: hidden; flex-direction: column; z-index: 100; }
    .panel-header { padding: 1.2rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #eee; font-weight: 800; font-size: 0.8rem; display: flex; justify-content: space-between; align-items: center; }
    .panel-content { flex: 1; overflow-y: auto; padding: 1rem; }
    
    .item-select { display: block; width: 100%; padding: 1rem; border: 1px solid #f1f5f9; border-radius: 12px; margin-bottom: 0.5rem; text-align: left; background: white; cursor: pointer; transition: 0.2s; text-decoration: none; color: inherit; }
    .item-select:hover { background: #f0fdf4; border-color: var(--primary); }

    .input-row { display: flex; gap: 10px; align-items: flex-end; }
    .input-box { flex: 1; background: #f8fafc; border: 2px solid #f1f5f9; border-radius: 15px; padding: 1rem 1.5rem; font-size: 1rem; outline: none; transition: 0.2s; max-height: 200px; resize: none; font-family: inherit; }
    .input-box:focus { border-color: var(--primary); background: white; }
    .btn-send { width: 55px; height: 55px; border-radius: 50%; background: var(--primary); color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(45,90,39,0.25); }

    /* Right: Insight Desk (Hidable) */
    .support-intel { display: none; width: 340px; background: white; border-left: 1px solid #e2e8f0; overflow-y: auto; flex-direction: column; animation: slideIn 0.3s ease-out; }
    @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }

    .intel-box { padding: 1.8rem; border-bottom: 1px solid #f1f5f9; }
    .intel-title { font-size: 0.75rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 1.5rem; }
    
    .data-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .data-cell { background: #f8fafc; padding: 1rem; border-radius: 14px; border: 1px solid #f1f5f9; }
    .cell-label { font-size: 0.6rem; opacity: 0.5; font-weight: 800; margin-bottom: 0.3rem; text-transform: uppercase; }
    .cell-val { font-size: 1rem; font-weight: 800; color: var(--text); }

    .order-micro-card { padding: 0.8rem; background: #f8fafc; border-radius: 10px; border: 1px solid #edf2f7; margin-bottom: 0.6rem; font-size: 0.75rem; }
    .order-micro-card .st { font-size: 0.6rem; font-weight: 900; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
</style>

<div class="support-container">
    
    <!-- 1. Inbox -->
    <aside class="support-sidebar">
        <div class="sidebar-header">
            <h2>Support Inbox <span class="badge" style="background:var(--primary); color:white; border-radius:6px;"><?php echo count($threads); ?></span></h2>
        </div>
        <div class="thread-list">
            <?php foreach ($threads as $t): ?>
                <a href="?id=<?php echo $t['id']; ?>" class="thread-row <?php echo $selectedThreadId == $t['id'] ? 'active' : ''; ?>">
                    <div class="row-meta">
                        <span class="row-email"><?php echo htmlspecialchars($t['customer_email']); ?></span>
                        <span class="row-time"><?php echo date('H:i', strtotime($t['last_message_at'])); ?></span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:5px;">
                        <div class="row-prio" style="background: <?php echo ($t['priority'] === 'critical' ? 'var(--error)' : ($t['priority'] === 'high' ? 'var(--accent)' : '#cbd5e0')); ?>"></div>
                        <div style="font-size:0.75rem; opacity:0.6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($t['subject'] ?: 'Inquiry'); ?></div>
                    </div>
                    <?php if ($t['unread_count'] > 0): ?>
                        <div style="position:absolute; right:15px; bottom:15px; background:var(--accent); color:white; font-size:0.6rem; font-weight:900; padding:2px 6px; border-radius:4px;">NEW</div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- 2. Chat Area -->
    <main class="support-chat">
        <?php if (!$selectedThread): ?>
            <div style="flex:1; display:flex; align-items:center; justify-content:center; flex-direction:column; opacity:0.1;">
                <div style="font-size:8rem;">🥦</div>
                <h1>SOBJIWALI LIVE DESK</h1>
            </div>
        <?php else: ?>
            <div class="chat-nav">
                <div>
                    <h3 style="margin:0; font-weight:800; font-size:1.1rem;">#<?php echo $selectedThreadId; ?>: <?php echo htmlspecialchars($selectedThread['subject']); ?></h3>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:3px;">
                        <span class="badge" style="background:<?php echo $selectedThread['status'] === 'open' ? 'var(--primary)' : '#94a3b8'; ?>; color:white; font-size:0.55rem;"><?php echo strtoupper($selectedThread['status']); ?></span>
                        <span style="font-size:0.7rem; opacity:0.4; font-weight:700;">STARTED <?php echo date('M d, H:i', strtotime($selectedThread['created_at'])); ?></span>
                    </div>
                </div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <button class="btn btn-outline" style="border-radius:12px;" onclick="toggleIntel()">📊 Insights & Meta</button>
                    <?php if ($selectedThread['last_admin_email']): ?>
                        <div style="text-align:right; border-left: 1px solid #eee; padding-left: 15px;">
                            <div style="font-size:0.6rem; font-weight:800; opacity:0.4; text-transform:uppercase;">Handled By</div>
                            <div style="font-size:0.8rem; font-weight:800; color:var(--primary);"><?php echo $selectedThread['last_admin_email']; ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="chat-feed" id="chat-window">
                <!-- AJAX -->
            </div>

            <?php 
                // Dynamic Input Styling Logic
                $inputBg = '#f8fafc';
                $inputBorder = '#f1f5f9';
                $inputFocus = 'var(--primary)';
                $statusNotice = 'Auto-save active • Real-time sync engaged';

                if ($selectedThread['status'] === 'closed') {
                    $inputBg = '#f1f5f9';
                    $inputBorder = '#e2e8f0';
                    $statusNotice = '🚩 THIS THREAD IS CLOSED / RESOLVED';
                } else {
                    switch($selectedThread['priority']) {
                        case 'critical':
                            $inputBg = '#fff5f5';
                            $inputBorder = '#feb2b2';
                            $inputFocus = '#e53e3e';
                            break;
                        case 'high':
                            $inputBg = '#fffaf0';
                            $inputBorder = '#fbd38d';
                            $inputFocus = '#dd6b20';
                            break;
                        case 'low':
                            $inputBg = '#f0fff4';
                            $inputBorder = '#c6f6d5';
                            $inputFocus = '#38a169';
                            break;
                    }
                }
            ?>

            <div class="chat-footer">
                <!-- Drop-up Panels -->
                <div id="panel-saved" class="drop-up-panel">
                    <div class="panel-header"><span>⚡ Saved Replies</span><button onclick="togglePanel('saved')" style="background:none; border:none; cursor:pointer;">×</button></div>
                    <div class="panel-content">
                        <?php foreach($canned as $c): ?>
                            <button class="item-select" onclick='insertText(<?php echo json_encode($c['message']); ?>)'>
                                <div style="font-weight:800; font-size:0.85rem; color:var(--primary); margin-bottom:3px;"><?php echo htmlspecialchars($c['title']); ?></div>
                                <div style="font-size:0.75rem; opacity:0.6;"><?php echo substr(htmlspecialchars($c['message']), 0, 80); ?>...</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="panel-products" class="drop-up-panel">
                    <div class="panel-header"><span>🥦 Product Catalog</span><button onclick="togglePanel('products')" style="background:none; border:none; cursor:pointer;">×</button></div>
                    <div class="panel-content">
                        <?php foreach($allProducts as $p): ?>
                            <button class="item-select" onclick='insertText("I highly recommend our <?php echo addslashes($p['name']); ?> (Price: $<?php echo number_format($p['base_price'], 2); ?>). You can find it here: <?php echo SITE_URL; ?>/product/<?php echo $p['slug']; ?>")'>
                                <div style="font-weight:800; font-size:0.85rem;"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div style="font-size:0.75rem; opacity:0.6;">$<?php echo number_format($p['base_price'], 2); ?> | <?php echo htmlspecialchars($p['cat_name']); ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="panel-orders" class="drop-up-panel">
                    <div class="panel-header"><span>📦 Customer Orders</span><button onclick="togglePanel('orders')" style="background:none; border:none; cursor:pointer;">×</button></div>
                    <div class="panel-content">
                        <?php foreach($customerData['recent_orders'] as $o): ?>
                            <button class="item-select" onclick='insertText("I am looking into your Order #<?php echo $o['id']; ?> (Status: <?php echo strtoupper($o['status']); ?>). Is there a specific item you need help with?")'>
                                <div style="font-weight:800; font-size:0.85rem;">Order #<?php echo $o['id']; ?></div>
                                <div style="font-size:0.75rem; opacity:0.6;">$<?php echo number_format($o['total_amount'], 2); ?> | <?php echo date('M d, Y', strtotime($o['created_at'])); ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="tools-bar">
                    <button class="tool-btn" onclick="togglePanel('saved')">⚡ Saved Replies</button>
                    <button class="tool-btn" onclick="togglePanel('products')">🥦 Products</button>
                    <button class="tool-btn" onclick="togglePanel('orders')">📦 Orders</button>
                </div>
                
                <div class="input-row">
                    <textarea id="reply-box" class="input-box" 
                              style="background: <?php echo $inputBg; ?>; border-color: <?php echo $inputBorder; ?>;" 
                              onfocus="this.style.borderColor='<?php echo $inputFocus; ?>'"
                              onblur="this.style.borderColor='<?php echo $inputBorder; ?>'"
                              placeholder="<?php echo $selectedThread['status'] === 'closed' ? 'Thread closed. Type to re-open...' : 'Write a response...'; ?>"></textarea>
                    <button id="send-trigger" onclick="sendAdminReply()" class="btn-send" style="background: <?php echo $inputFocus; ?>;">
                        <span style="font-size:1.5rem; transform: rotate(45deg); margin-bottom:5px; margin-left:5px;">✈️</span>
                    </button>
                </div>
                <p style="text-align:center; font-size:0.65rem; color: <?php echo $inputFocus; ?>; margin-top:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; opacity: 0.8;"><?php echo $statusNotice; ?></p>
            </div>
        <?php endif; ?>
    </main>

    <!-- 3. Intelligence (Hidable) -->
    <?php if ($selectedThread): ?>
    <aside id="intel-panel" class="support-intel">
        <div class="intel-box" style="padding-top: 2.5rem;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-weight:900; font-size:1.1rem; color: var(--primary);"><?php echo htmlspecialchars($selectedThread['email']); ?></div>
                <div class="badge" style="background:#edf2f7; color:#4a5568; margin-top:8px;"><?php echo strtoupper($selectedThread['role']); ?> PARTNER</div>
            </div>
            
            <div class="data-grid">
                <div class="data-cell"><div class="cell-label">LTV</div><div class="cell-val">$<?php echo number_format($customerData['total_spent'], 2); ?></div></div>
                <div class="data-cell"><div class="cell-label">Orders</div><div class="cell-val"><?php echo $customerData['total_orders']; ?></div></div>
            </div>

            <div style="margin-top: 1.5rem;">
                <h5 style="font-size:0.65rem; text-transform:uppercase; opacity:0.4; margin-bottom:1rem;">Order History</h5>
                <?php foreach ($customerData['recent_orders'] as $ro): ?>
                    <div class="order-micro-card">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                            <strong>#<?php echo $ro['id']; ?></strong>
                            <span class="st" style="background:<?php echo $ro['status'] == 'shipped' ? '#c6f6d5' : '#feebc8'; ?>; color:<?php echo $ro['status'] == 'shipped' ? '#22543d' : '#744210'; ?>;"><?php echo $ro['status']; ?></span>
                        </div>
                        <div style="opacity:0.8; font-size:0.7rem; margin-bottom:8px; padding-bottom:5px; border-bottom:1px dashed #eee;">
                            <?php 
                                $itemNames = [];
                                foreach($ro['items'] as $item) $itemNames[] = $item['quantity'] . 'x ' . $item['name'];
                                echo implode(', ', $itemNames);
                            ?>
                        </div>
                        <div style="opacity:0.6; font-size:0.65rem;">$<?php echo number_format($ro['total_amount'], 2); ?> • <?php echo date('M d', strtotime($ro['created_at'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="intel-box" style="flex:1; background:#fffbeb;">
            <h4 class="intel-title" style="opacity:0.8;">Admin Metadata</h4>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo AuthManager::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_thread">
                
                <div class="form-group"><label>THREAD PRIORITY</label>
                    <select name="priority">
                        <option value="low" <?php echo $selectedThread['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $selectedThread['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $selectedThread['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="critical" <?php echo $selectedThread['priority'] == 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
                
                <div class="form-group"><label>RESOLUTION</label>
                    <select name="status">
                        <option value="open" <?php echo $selectedThread['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo $selectedThread['status'] == 'closed' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>

                <div class="form-group"><label>STAFF NOTES</label>
                    <textarea name="internal_notes" style="height:120px;"><?php echo htmlspecialchars($selectedThread['internal_notes'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width:100%; background:#1a202c; border:none; padding:1.2rem; border-radius:15px; font-weight:800;">SAVE METADATA</button>
            </form>
        </div>
    </aside>
    <?php endif; ?>

</div>

<script>
    const threadId = <?php echo $selectedThreadId ?: 'null'; ?>;
    let lastMsgCount = 0;

    async function loadMessages() {
        if (!threadId) return;
        try {
            const res = await fetch(`../api_support.php?action=get_messages&thread_id=${threadId}`);
            const data = await res.json();
            if (data.success) {
                const container = document.getElementById('chat-window');
                const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
                
                if (data.messages.length !== lastMsgCount) {
                    container.innerHTML = data.messages.map(m => `
                        <div class="bubble ${m.sender_type === 'admin' ? 'bubble-staff' : 'bubble-cust'}">
                            ${m.message}
                            <div class="b-time">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                        </div>
                    `).join('');
                    
                    lastMsgCount = data.messages.length;
                    if (isAtBottom) container.scrollTop = container.scrollHeight;
                }
            }
        } catch(e) {}
    }

    async function sendAdminReply() {
        const input = document.getElementById('reply-box');
        const msg = input.value.trim();
        if (!msg || !threadId) return;

        const formData = new FormData();
        formData.append('thread_id', threadId);
        formData.append('message', msg);
        formData.append('sender_type', 'admin');

        try {
            const res = await fetch('../api_support.php?action=send_message', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                input.value = '';
                await loadMessages();
                const container = document.getElementById('chat-window');
                container.scrollTop = container.scrollHeight;
                // Close any open panels
                document.querySelectorAll('.drop-up-panel').forEach(p => p.style.display = 'none');
            }
        } catch(e) {}
    }

    function togglePanel(type) {
        const panels = ['saved', 'products', 'orders'];
        panels.forEach(p => {
            const el = document.getElementById('panel-' + p);
            if (p === type) {
                el.style.display = el.style.display === 'flex' ? 'none' : 'flex';
            } else {
                el.style.display = 'none';
            }
        });
    }

    function toggleIntel() {
        const panel = document.getElementById('intel-panel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'flex' : 'none';
    }

    function insertText(text) {
        const box = document.getElementById('reply-box');
        box.value = text;
        box.focus();
    }

    document.getElementById('reply-box')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendAdminReply();
        }
    });

    if (threadId) {
        loadMessages();
        setInterval(loadMessages, 3000);
        setTimeout(() => {
            const c = document.getElementById('chat-window');
            c.scrollTop = c.scrollHeight;
        }, 200);
    }
</script>

<?php require_once 'layout_footer.php'; ?>
