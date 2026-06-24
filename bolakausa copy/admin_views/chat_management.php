<?php
/**
 * Admin Chat Management - Premium Glassmorphic Redesign with Live Polling
 */
restrict_to(['admin', 'manager']);

$success = '';
$error = '';

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

    // Fetch messages joining users to show admin name details
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS admin_name, u.username AS admin_username
        FROM chats c
        LEFT JOIN users u ON c.admin_id = u.id
        WHERE c.user_id = ? 
        ORDER BY c.id ASC
    ");
    $stmt->execute([$view_user_id]);
    $messages = $stmt->fetchAll();

    // Fetch user info
    $stmt = $pdo->prepare("SELECT username, full_name FROM users WHERE id = ?");
    $stmt->execute([$view_user_id]);
    $view_user = $stmt->fetch();
    $view_username = $view_user ? ($view_user['full_name'] ?: $view_user['username']) : 'Unknown User';
}

$max_message_id = 0;
foreach ($messages as $m) {
    if ($m['id'] > $max_message_id) {
        $max_message_id = $m['id'];
    }
}
?>

<div class="section-title">
    <i class="fas fa-headset" style="color: #3b82f6;"></i>
    Client Communications
</div>

<div class="card" style="padding: 0; display: flex; height: 700px; overflow: hidden; border-radius: var(--radius-lg); border: 1px solid var(--border-light); box-shadow: var(--glass-shadow); background: white;">
    
    <!-- Sidebar: Conversations List -->
    <div style="width: 320px; background: rgba(15,23,42,0.02); border-right: 1px solid var(--glass-border); display: flex; flex-direction: column;">
        <div style="padding: 1.5rem; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; color: var(--secondary); font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif;">Active Threads</h4>
            <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 6px var(--primary);" title="Live thread list sync active"></div>
        </div>
        <div style="flex: 1; overflow-y: auto; padding: 1rem;">
            <?php if (!$conversations): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; margin-top: 1rem;">No active conversations.</p>
            <?php endif; ?>
            
            <ul id="threads-list" style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem;">
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
                    <h3 style="margin: 0; color: var(--secondary); font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif;">Negotiating with <?php echo e($view_username); ?></h3>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">Partner ID: #<?php echo $view_user_id; ?></p>
                </div>
            </div>

            <!-- Messages Log -->
            <div id="admin-chat-box" style="flex: 1; overflow-y: auto; padding: 2rem; display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($messages as $m): ?>
                    <?php 
                    $is_admin = in_array($m['sender_role'], ['admin', 'manager']); 
                    $admin_display_name = $m['admin_name'] ?: ($m['admin_username'] ?: 'Support Representative');
                    
                    // Format attachments
                    $attachment_html = '';
                    if ($m['attachment_type'] && $m['attachment_id']) {
                        if ($m['attachment_type'] === 'product') {
                            $stmt_prod = $pdo->prepare("SELECT p.id, p.name, p.base_price, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image FROM products p WHERE p.id = ?");
                            $stmt_prod->execute([$m['attachment_id']]);
                            $prod = $stmt_prod->fetch();
                            if ($prod) {
                                $p_img = $prod['main_image'] ?: '/bolakausa/public/images/default_product.png';
                                $p_price = number_format($prod['base_price'], 2);
                                $attachment_html = "
                                    <div class='attachment-card' style='margin-top: 0.5rem; padding: 0.75rem; border-radius: 12px; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.05); display: flex; gap: 0.75rem; align-items: center; text-align: left; color: var(--text-main);'>
                                        <img src='{$p_img}' style='width: 50px; height: 50px; border-radius: 8px; object-fit: cover;'>
                                        <div style='flex: 1;'>
                                            <h4 style='margin: 0; font-size: 0.85rem; font-weight: 800; color: var(--secondary);'>".e($prod['name'])."</h4>
                                            <span style='font-size: 0.75rem; font-weight: 700; color: var(--primary);'>\${$p_price}</span>
                                        </div>
                                        <a href='/bolakausa/home#product-card-{$prod['id']}' class='btn btn-outline' style='padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;' target='_blank'>View Catalog</a>
                                    </div>
                                ";
                            }
                        } elseif ($m['attachment_type'] === 'order') {
                            $stmt_ord = $pdo->prepare("SELECT id, total_amount, created_at, status FROM orders WHERE id = ?");
                            $stmt_ord->execute([$m['attachment_id']]);
                            $ord = $stmt_ord->fetch();
                            if ($ord) {
                                $o_total = number_format($ord['total_amount'], 2);
                                $o_date = date('M d, Y', strtotime($ord['created_at']));
                                $attachment_html = "
                                    <div class='attachment-card' style='margin-top: 0.5rem; padding: 0.75rem; border-radius: 12px; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.05); display: flex; gap: 0.75rem; align-items: center; text-align: left; color: var(--text-main);'>
                                        <div style='width: 40px; height: 40px; border-radius: 50%; background: rgba(99,102,241,0.1); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;'>
                                            <i class='fas fa-file-invoice'></i>
                                        </div>
                                        <div style='flex: 1;'>
                                            <h4 style='margin: 0; font-size: 0.85rem; font-weight: 800; color: var(--secondary);'>Order #{$ord['id']}</h4>
                                            <span style='font-size: 0.75rem; color: var(--text-muted);'>{$o_date} &bull; <strong>\${$o_total}</strong></span>
                                        </div>
                                        <span style='font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; background: #e2e8f0; color: #475569;'>{$ord['status']}</span>
                                        <a href='/bolakausa/invoice?id={$ord['id']}' class='btn btn-outline' style='padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;' target='_blank'>Invoice</a>
                                    </div>
                                ";
                            }
                        }
                    }
                    ?>
                    <div style="max-width: 75%; padding: 1.25rem; border-radius: 16px; border: 1px solid var(--glass-border); <?php echo $is_admin ? 'align-self: flex-end; background: var(--secondary); color: white; border-bottom-right-radius: 4px;' : 'align-self: flex-start; background: rgba(255,255,255,0.9); color: var(--text-main); border-bottom-left-radius: 4px; box-shadow: var(--shadow-sm);'; ?>">
                        <div style="font-size: 0.725rem; margin-bottom: 0.5rem; opacity: 0.8; font-weight: 700; text-transform: uppercase;">
                            <?php echo $is_admin ? '<i class="fas fa-reply"></i> Bolakausa Support ('.$admin_display_name.')' : e($view_username); ?> &bull; <?php echo date('M d, H:i', strtotime($m['created_at'])); ?>
                        </div>
                        <div style="line-height: 1.5; font-size: 0.95rem; word-break: break-word;">
                            <?php echo nl2br(e($m['message'])); ?>
                        </div>
                        <?php echo $attachment_html; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Active Attachment Preview -->
            <div id="attachment-preview" style="display: none; align-items: center; justify-content: space-between; padding: 0.75rem 2rem; background: #ecfdf5; border-top: 1px solid var(--border-light);">
                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: #065f46; font-weight: 600;">
                    <i class="fas fa-paperclip"></i>
                    <span id="attachment-preview-text">Attached Product Reference</span>
                </div>
                <button type="button" onclick="clearAttachment()" style="background: none; border: none; color: #065f46; cursor: pointer; font-size: 1rem; padding: 0.25rem;"><i class="fas fa-times"></i></button>
            </div>

            <!-- Attachment Picker Drawer -->
            <div id="attachment-drawer" style="display: none; max-height: 250px; border-top: 1px solid var(--border-light); background: #f8fafc; flex-direction: column;">
                <div style="display: flex; gap: 1rem; border-bottom: 1px solid var(--border-light); padding: 0.75rem 2rem; background: #f1f5f9;">
                    <button type="button" id="tab-btn-products" class="btn btn-outline active-tab" onclick="switchAttachTab('products')" style="padding: 0.4rem 1rem; font-size: 0.8rem; font-weight: 700;">
                        <i class="fas fa-boxes"></i> Catalog Products
                    </button>
                    <button type="button" id="tab-btn-orders" class="btn btn-outline" onclick="switchAttachTab('orders')" style="padding: 0.4rem 1rem; font-size: 0.8rem; font-weight: 700;">
                        <i class="fas fa-file-invoice"></i> Client Orders
                    </button>
                </div>
                <div style="flex: 1; overflow-y: auto; padding: 1rem 2rem;">
                    <!-- Products Panel -->
                    <div id="attach-products-panel" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <input type="text" id="attach-product-search" placeholder="Search catalog items..." oninput="filterAttachProducts()" style="padding: 0.5rem; border-radius: 8px; font-size: 0.8rem; border: 1px solid #cbd5e1; margin-bottom: 0.5rem; width: 100%;">
                        <div id="attach-products-list" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
                    </div>
                    <!-- Orders Panel -->
                    <div id="attach-orders-panel" style="display: none; flex-direction: column; gap: 0.5rem;">
                        <div id="attach-orders-list" style="display: flex; flex-direction: column; gap: 0.5rem;"></div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div style="padding: 1.5rem; background: var(--glass-bg); border-top: 1px solid var(--glass-border);">
                <form id="admin-chat-form" onsubmit="submitAdminReply(event)" style="display: flex; gap: 1rem; align-items: center;">
                    <input type="hidden" name="user_id" id="form-target-user-id" value="<?php echo $view_user_id; ?>">
                    <input type="hidden" name="attachment_type" id="form-attachment-type" value="">
                    <input type="hidden" name="attachment_id" id="form-attachment-id" value="">
                    
                    <button type="button" id="attachment-btn" onclick="toggleAttachmentDrawer()" class="btn btn-outline" style="border-radius: 12px; height: 50px; width: 50px; display: flex; align-items: center; justify-content: center; padding: 0; flex-shrink: 0;" title="Attach Order or Product">
                        <i class="fas fa-paperclip" style="font-size: 1.1rem;"></i>
                    </button>

                    <textarea name="message" id="message-text" placeholder="Type your response to <?php echo e($view_username); ?>..." style="flex: 1; height: 50px; resize: none; border-radius: 12px; padding: 0.75rem 1rem; border: 1px solid var(--border-light); background: rgba(255,255,255,0.7); font-family: inherit;" required></textarea>
                    
                    <button type="submit" class="btn btn-blue" style="border-radius: 12px; height: 50px; padding: 0 1.75rem; flex-shrink: 0;">
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

<style>
    .active-tab {
        background: var(--primary) !important;
        color: white !important;
        border-color: var(--primary) !important;
    }
    .attach-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.6rem 0.8rem;
        background: white;
        border: 1px solid var(--border-light);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: left;
        color: var(--text-main);
    }
    .attach-item:hover {
        border-color: var(--primary);
        background: #f0fdf4;
    }
</style>

<script>
    const viewUserId = <?php echo $view_user_id ? $view_user_id : 'null'; ?>;
    let lastMessageId = <?php echo $max_message_id; ?>;
    let attachmentsLoaded = false;
    let productsData = [];
    let ordersData = [];

    const chatBox = document.getElementById('admin-chat-box');
    function scrollToBottom() {
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    }
    scrollToBottom();

    // Toggle attachment drawer
    function toggleAttachmentDrawer() {
        if (!viewUserId) return;
        const drawer = document.getElementById('attachment-drawer');
        if (drawer.style.display === 'none') {
            drawer.style.display = 'flex';
            document.getElementById('attachment-btn').style.background = '#e2e8f0';
            loadAttachments();
        } else {
            drawer.style.display = 'none';
            document.getElementById('attachment-btn').style.background = 'transparent';
        }
    }

    // Switch tabs in drawer
    function switchAttachTab(tab) {
        document.getElementById('tab-btn-products').classList.remove('active-tab');
        document.getElementById('tab-btn-orders').classList.remove('active-tab');
        document.getElementById('attach-products-panel').style.display = 'none';
        document.getElementById('attach-orders-panel').style.display = 'none';

        if (tab === 'products') {
            document.getElementById('tab-btn-products').classList.add('active-tab');
            document.getElementById('attach-products-panel').style.display = 'flex';
        } else {
            document.getElementById('tab-btn-orders').classList.add('active-tab');
            document.getElementById('attach-orders-panel').style.display = 'flex';
        }
    }

    // Load attachments
    function loadAttachments() {
        if (attachmentsLoaded) return;
        
        fetch(`/bolakausa/chat-api?action=get_attachments&user_id=${viewUserId}`)
            .then(res => res.json())
            .then(data => {
                productsData = data.products || [];
                ordersData = data.orders || [];
                
                renderProductsList(productsData);
                renderOrdersList(ordersData);
                
                attachmentsLoaded = true;
            })
            .catch(err => console.error("Error loading attachments:", err));
    }

    // Render products in drawer
    function renderProductsList(items) {
        const container = document.getElementById('attach-products-list');
        container.innerHTML = '';
        if (items.length === 0) {
            container.innerHTML = '<span style="font-size:0.8rem; color:var(--text-muted);">No products found.</span>';
            return;
        }
        
        items.forEach(p => {
            const el = document.createElement('div');
            el.className = 'attach-item';
            el.onclick = () => selectAttachment('product', p.id, p.name);
            el.innerHTML = `
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <img src="${p.image}" style="width:30px; height:30px; border-radius:4px; object-fit:cover;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--secondary);">${p.name}</span>
                </div>
                <span style="font-size:0.8rem; color:var(--primary); font-weight:700;">$${p.price}</span>
            `;
            container.appendChild(el);
        });
    }

    // Filter products
    function filterAttachProducts() {
        const q = document.getElementById('attach-product-search').value.toLowerCase();
        const filtered = productsData.filter(p => p.name.toLowerCase().includes(q));
        renderProductsList(filtered);
    }

    // Render orders in drawer
    function renderOrdersList(items) {
        const container = document.getElementById('attach-orders-list');
        container.innerHTML = '';
        if (items.length === 0) {
            container.innerHTML = '<span style="font-size:0.8rem; color:var(--text-muted);">No orders found for this user.</span>';
            return;
        }
        
        items.forEach(o => {
            const el = document.createElement('div');
            el.className = 'attach-item';
            el.onclick = () => selectAttachment('order', o.id, `Order #${o.id} - $${o.total}`);
            el.innerHTML = `
                <div style="display:flex; flex-direction:column;">
                    <span style="font-size:0.8rem; font-weight:700; color:var(--secondary);">Order #${o.id}</span>
                    <span style="font-size:0.7rem; color:var(--text-muted);">${o.date}</span>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:0.7rem; padding:2px 6px; border-radius:4px; background:#e2e8f0; color:#475569; font-weight:700;">${o.status}</span>
                    <span style="font-size:0.8rem; color:var(--secondary); font-weight:800;">$${o.total}</span>
                </div>
            `;
            container.appendChild(el);
        });
    }

    // Select attachment
    function selectAttachment(type, id, label) {
        document.getElementById('form-attachment-type').value = type;
        document.getElementById('form-attachment-id').value = id;
        
        document.getElementById('attachment-preview-text').textContent = `Attached Reference: ${label}`;
        document.getElementById('attachment-preview').style.display = 'flex';
        
        // Close drawer
        document.getElementById('attachment-drawer').style.display = 'none';
        document.getElementById('attachment-btn').style.background = 'transparent';
    }

    // Clear attachment
    function clearAttachment() {
        document.getElementById('form-attachment-type').value = '';
        document.getElementById('form-attachment-id').value = '';
        document.getElementById('attachment-preview').style.display = 'none';
    }

    // Submit reply via AJAX
    function submitAdminReply(e) {
        e.preventDefault();
        
        const messageInput = document.getElementById('message-text');
        const message = messageInput.value.trim();
        const attachType = document.getElementById('form-attachment-type').value;
        const attachId = document.getElementById('form-attachment-id').value;

        if (!message && !attachId) return;

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('user_id', viewUserId);
        formData.append('message', message);
        formData.append('attachment_type', attachType);
        formData.append('attachment_id', attachId);

        // Clear input and attachment early for snappy experience
        messageInput.value = '';
        clearAttachment();

        fetch('/bolakausa/chat-api', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                appendAdminMessage(res.message);
                if (res.message.id > lastMessageId) {
                    lastMessageId = res.message.id;
                }
            } else {
                alert("Failed to send message: " + (res.error || "Unknown error"));
            }
        })
        .catch(err => {
            console.error("Error sending reply:", err);
            alert("Error sending message. Please try again.");
        });
    }

    // Append single message to DOM in admin view
    function appendAdminMessage(msg) {
        const msgDiv = document.createElement('div');
        msgDiv.style.maxWidth = '75%';
        msgDiv.style.padding = '1.25rem';
        msgDiv.style.borderRadius = '16px';
        msgDiv.style.border = '1px solid var(--glass-border)';

        if (msg.is_me) {
            msgDiv.style.alignSelf = 'flex-end';
            msgDiv.style.background = 'var(--secondary)';
            msgDiv.style.color = 'white';
            msgDiv.style.borderBottomRightRadius = '4px';
        } else {
            msgDiv.style.alignSelf = 'flex-start';
            msgDiv.style.background = 'rgba(255,255,255,0.9)';
            msgDiv.style.color = 'var(--text-main)';
            msgDiv.style.borderBottomLeftRadius = '4px';
            msgDiv.style.boxShadow = 'var(--shadow-sm)';
        }

        let attachHtml = '';
        if (msg.attachment) {
            if (msg.attachment.type === 'product') {
                attachHtml = `
                    <div class="attachment-card" style="margin-top: 0.5rem; padding: 0.75rem; border-radius: 12px; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.05); display: flex; gap: 0.75rem; align-items: center; text-align: left; color: var(--text-main);">
                        <img src="${msg.attachment.image}" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 0.85rem; font-weight: 800; color: var(--secondary);">${msg.attachment.name}</h4>
                            <span style="font-size: 0.75rem; font-weight: 700; color: var(--primary);">$${msg.attachment.price}</span>
                        </div>
                        <a href="/bolakausa/home#product-card-${msg.attachment.id}" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;" target="_blank">View Catalog</a>
                    </div>
                `;
            } else if (msg.attachment.type === 'order') {
                attachHtml = `
                    <div class="attachment-card" style="margin-top: 0.5rem; padding: 0.75rem; border-radius: 12px; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.05); display: flex; gap: 0.75rem; align-items: center; text-align: left; color: var(--text-main);">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: rgba(99,102,241,0.1); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0; font-size: 0.85rem; font-weight: 800; color: var(--secondary);">Order #${msg.attachment.id}</h4>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">${msg.attachment.date} &bull; <strong>$${msg.attachment.total}</strong></span>
                        </div>
                        <span style="font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; font-weight: 700; background: #e2e8f0; color: #475569;">${msg.attachment.status}</span>
                        <a href="/bolakausa/invoice?id=${msg.attachment.id}" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;" target="_blank">Invoice</a>
                    </div>
                `;
            }
        }

        const senderHeader = msg.is_me 
            ? `<i class="fas fa-reply"></i> Bolakausa Support (${msg.admin_name})`
            : escapeHtml(msg.admin_name); // for customer name representation on the left

        const safeMessage = msg.message.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/\n/g, '<br>');

        msgDiv.innerHTML = `
            <div style="font-size: 0.725rem; margin-bottom: 0.5rem; opacity: 0.8; font-weight: 700; text-transform: uppercase;">
                ${senderHeader} &bull; ${msg.created_at}
            </div>
            <div style="line-height: 1.5; font-size: 0.95rem; word-break: break-word;">
                ${safeMessage}
            </div>
            ${attachHtml}
        `;
        
        chatBox.appendChild(msgDiv);
        scrollToBottom();
    }

    // Render active conversations sidebar list
    function renderSidebarThreads(threads) {
        const container = document.getElementById('threads-list');
        if (!container) return;
        
        container.innerHTML = '';
        if (threads.length === 0) {
            container.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; margin-top: 1rem;">No active conversations.</p>';
            return;
        }

        threads.forEach(t => {
            const isActive = (viewUserId == t.id);
            const unreadBadge = t.unread_count > 0 
                ? `<span style="background: var(--accent); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 800; box-shadow: 0 0 8px var(--accent-glow);">${t.unread_count} New</span>` 
                : '';
            
            const li = document.createElement('li');
            li.innerHTML = `
                <a href="/bolakausa/admin/chats?user_id=${t.id}" style="display: block; padding: 1rem; text-decoration: none; border-radius: 12px; border: 1px solid ${isActive ? 'var(--primary)' : 'var(--glass-border)'}; background: ${isActive ? 'rgba(16,185,129,0.1)' : 'rgba(255,255,255,0.5)'}; transition: all 0.2s;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                        <strong style="color: var(--secondary); font-size: 0.95rem;">${escapeHtml(t.display_name)}</strong>
                        ${unreadBadge}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><i class="far fa-clock"></i> ${t.last_message_at}</div>
                </a>
            `;
            container.appendChild(li);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Polling function for Admin
    function pollAdminMessages() {
        if (!viewUserId) return;
        
        fetch(`/bolakausa/chat-api?action=fetch_admin&user_id=${viewUserId}&last_id=${lastMessageId}`)
            .then(res => res.json())
            .then(res => {
                // Update messages if new ones arrived
                if (res.messages && res.messages.length > 0) {
                    res.messages.forEach(msg => {
                        appendAdminMessage(msg);
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                        }
                    });
                }
                // Update sidebar threads list
                if (res.threads) {
                    renderSidebarThreads(res.threads);
                }
            })
            .catch(err => console.error("Admin polling error:", err));
    }

    // Poll every 3 seconds
    if (viewUserId) {
        setInterval(pollAdminMessages, 3000);
    }
</script>

<p style="margin-top: 2rem;"><a href="/bolakausa/admin" class="btn btn-blue"><i class="fas fa-arrow-left"></i> Back to Dashboard</a></p>
