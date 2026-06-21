<?php
/**
 * Wholesale User Chat Interface - Premium Redesign with Attachments & Live Polling
 */
restrict_to(['wholesale_user', 'executive']);

$user_id = $_SESSION['user_id'];
$user_role = get_user_role();

// Mark messages as read by user (messages from admin/manager)
$stmt = $pdo->prepare("UPDATE chats SET is_read = 1 WHERE user_id = ? AND sender_role IN ('admin', 'manager') AND is_read = 0");
$stmt->execute([$user_id]);

// Fetch Chat History (joining users to get admin/manager responder details)
$stmt = $pdo->prepare("
    SELECT c.*, u.full_name AS admin_name, u.username AS admin_username
    FROM chats c
    LEFT JOIN users u ON c.admin_id = u.id
    WHERE c.user_id = ? 
    ORDER BY c.id ASC
");
$stmt->execute([$user_id]);
$chats = $stmt->fetchAll();

// Track highest message ID loaded initially
$max_message_id = 0;
foreach ($chats as $c) {
    if ($c['id'] > $max_message_id) {
        $max_message_id = $c['id'];
    }
}
?>

<div class="section-title">
    <i class="fas fa-comments"></i> Support & Terms Negotiation
</div>

<div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; height: 620px; border: 1px solid var(--border-light); box-shadow: var(--glass-shadow); background: white; border-radius: var(--radius-lg);">
    <!-- Chat Header -->
    <div style="padding: 1.25rem 2rem; background: #f8fafc; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 style="margin: 0; color: var(--secondary); font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                Account Operations Representative
            </h3>
            <p style="margin: 0; font-size: 0.775rem; color: var(--text-muted); font-weight: 500;">Direct contact line for custom requests & orders</p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 0.75rem; color: var(--primary-dark); font-weight: 700; text-transform: uppercase;">Live Sync Active</span>
            <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 8px var(--primary); animation: pulse-live 1.5s infinite;"></div>
        </div>
    </div>

    <!-- Chat Messages Area -->
    <div id="chat-box" style="flex: 1; overflow-y: auto; padding: 2rem; background: #fafafa; display: flex; flex-direction: column; gap: 1.25rem;">
        <?php if (!$chats): ?>
            <div id="chat-empty-state" style="text-align: center; color: var(--text-muted); margin-top: 4rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                <i class="fas fa-comments" style="font-size: 3.5rem; opacity: 0.15; margin-bottom: 1.25rem; display: block; color: var(--secondary);"></i>
                <h4 style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; color: var(--secondary); margin-bottom: 0.25rem;">Direct Messaging Channel</h4>
                <p style="font-size: 0.8rem; line-height: 1.4;">Submit queries about delivery rates, product availability, or custom price quotes.</p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($chats as $c): ?>
            <?php 
            $is_me = ($c['sender_role'] === 'wholesale_user'); 
            $is_admin = in_array($c['sender_role'], ['admin', 'manager']);
            
            // Format attachments
            $attachment_html = '';
            if ($c['attachment_type'] && $c['attachment_id']) {
                if ($c['attachment_type'] === 'product') {
                    $stmt_prod = $pdo->prepare("SELECT p.id, p.name, p.base_price, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image FROM products p WHERE p.id = ?");
                    $stmt_prod->execute([$c['attachment_id']]);
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
                                <a href='/bolakausa/home#product-card-{$prod['id']}' class='btn btn-outline' style='padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;'>View Catalog</a>
                            </div>
                        ";
                    }
                } elseif ($c['attachment_type'] === 'order') {
                    $stmt_ord = $pdo->prepare("SELECT id, total_amount, created_at, status FROM orders WHERE id = ?");
                    $stmt_ord->execute([$c['attachment_id']]);
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
                                <a href='/bolakausa/invoice?id={$ord['id']}' class='btn btn-outline' style='padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;'>Invoice</a>
                            </div>
                        ";
                    }
                }
            }
            ?>
            <div style="display: flex; flex-direction: column; <?php echo $is_me ? 'align-items: flex-end;' : 'align-items: flex-start;'; ?>">
                <div style="max-width: 70%; padding: 1rem 1.25rem; border-radius: 16px; border: 1px solid var(--border-light); 
                    <?php echo $is_me ? 'background: var(--primary); color: white; border-bottom-right-radius: 4px; box-shadow: 0 4px 12px var(--primary-glow); border-color: transparent;' : 'background: white; color: var(--text-main); border-bottom-left-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);'; ?>">
                    
                    <?php if ($is_admin): ?>
                        <div style="font-size: 0.7rem; margin-bottom: 0.35rem; opacity: 0.8; font-weight: 700; text-transform: uppercase;">
                            <i class="fas fa-user-shield"></i> <?php echo e($c['admin_name'] ?: ($c['admin_username'] ?: 'Support Representative')); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div style="line-height: 1.5; font-size: 0.875rem; word-break: break-word;">
                        <?php echo nl2br(e($c['message'])); ?>
                    </div>
                    <?php echo $attachment_html; ?>
                </div>
                <span style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem; font-weight: 600; padding: 0 0.25rem;">
                    <?php echo date('M d, H:i', strtotime($c['created_at'])); ?>
                </span>
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
                <i class="fas fa-file-invoice"></i> My Orders
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

    <!-- Message Input Area -->
    <div style="padding: 1.25rem 2rem; background: white; border-top: 1px solid var(--border-light);">
        <form id="chat-input-form" onsubmit="submitChatMessage(event)" style="display: flex; gap: 1rem; align-items: center;">
            <input type="hidden" name="attachment_type" id="form-attachment-type" value="">
            <input type="hidden" name="attachment_id" id="form-attachment-id" value="">
            
            <button type="button" id="attachment-btn" onclick="toggleAttachmentDrawer()" class="btn btn-outline" style="border-radius: 10px; height: 50px; width: 50px; display: flex; align-items: center; justify-content: center; padding: 0; flex-shrink: 0;" title="Attach Order or Product">
                <i class="fas fa-paperclip" style="font-size: 1.1rem;"></i>
            </button>

            <textarea name="message" id="message-text" placeholder="Type a message to support..." style="flex: 1; height: 50px; resize: none; border-radius: 10px; padding: 0.75rem 1rem; border: 1px solid #cbd5e1; font-size: 0.9rem; font-family: inherit;" required></textarea>
            
            <button type="submit" class="btn btn-green" style="border-radius: 10px; height: 50px; padding: 0 1.75rem; font-size: 0.9rem; box-shadow: 0 4px 10px var(--primary-glow); flex-shrink: 0;">
                <i class="fas fa-paper-plane"></i> Send
            </button>
        </form>
    </div>
</div>

<style>
    @keyframes pulse-live {
        0% { transform: scale(0.9); opacity: 0.6; }
        50% { transform: scale(1.15); opacity: 1; }
        100% { transform: scale(0.9); opacity: 0.6; }
    }
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
    }
    .attach-item:hover {
        border-color: var(--primary);
        background: #f0fdf4;
    }
</style>

<script>
    let lastMessageId = <?php echo $max_message_id; ?>;
    let attachmentsLoaded = false;
    let productsData = [];
    let ordersData = [];

    // Auto-scroll chat box to bottom
    const chatBox = document.getElementById('chat-box');
    function scrollToBottom() {
        if (chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    }
    scrollToBottom();

    // Toggle attachment drawer and fetch options
    function toggleAttachmentDrawer() {
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

    // Fetch products & orders for picker
    function loadAttachments() {
        if (attachmentsLoaded) return;
        
        fetch('/bolakausa/chat-api?action=get_attachments')
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
            container.innerHTML = '<span style="font-size:0.8rem; color:var(--text-muted);">No orders found.</span>';
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

    // Send message via AJAX
    function submitChatMessage(e) {
        e.preventDefault();
        
        const messageInput = document.getElementById('message-text');
        const message = messageInput.value.trim();
        const attachType = document.getElementById('form-attachment-type').value;
        const attachId = document.getElementById('form-attachment-id').value;

        if (!message && !attachId) return;

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('message', message);
        formData.append('attachment_type', attachType);
        formData.append('attachment_id', attachId);

        // Clear input early for snappy UI feel
        messageInput.value = '';
        clearAttachment();

        fetch('/bolakausa/chat-api', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                appendMessage(res.message);
                if (res.message.id > lastMessageId) {
                    lastMessageId = res.message.id;
                }
            } else {
                alert("Failed to send message: " + (res.error || "Unknown error"));
            }
        })
        .catch(err => {
            console.error("Error sending message:", err);
            alert("Error sending message. Please try again.");
        });
    }

    // Append single message to DOM
    function appendMessage(msg) {
        // Remove empty state if present
        const emptyState = document.getElementById('chat-empty-state');
        if (emptyState) emptyState.remove();

        const msgDiv = document.createElement('div');
        msgDiv.style.display = 'flex';
        msgDiv.style.flexDirection = 'column';
        
        if (msg.is_me) {
            msgDiv.style.alignItems = 'flex-end';
        } else {
            msgDiv.style.alignItems = 'flex-start';
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
                        <a href="/bolakausa/home#product-card-${msg.attachment.id}" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;">View Catalog</a>
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
                        <a href="/bolakausa/invoice?id=${msg.attachment.id}" class="btn btn-outline" style="padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 6px; height: auto;">Invoice</a>
                    </div>
                `;
            }
        }

        const bubbleBg = msg.is_me 
            ? 'background: var(--primary); color: white; border-bottom-right-radius: 4px; box-shadow: 0 4px 12px var(--primary-glow); border-color: transparent;' 
            : 'background: white; color: var(--text-main); border-bottom-left-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);';

        const adminHeader = (!msg.is_me && msg.sender_role !== 'wholesale_user') 
            ? `<div style="font-size: 0.7rem; margin-bottom: 0.35rem; opacity: 0.8; font-weight: 700; text-transform: uppercase;"><i class="fas fa-user-shield"></i> ${msg.admin_name}</div>` 
            : '';

        // Safe rendering of line breaks in message
        const safeMessage = msg.message.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;").replace(/\n/g, '<br>');

        msgDiv.innerHTML = `
            <div style="max-width: 70%; padding: 1rem 1.25rem; border-radius: 16px; border: 1px solid var(--border-light); ${bubbleBg}">
                ${adminHeader}
                <div style="line-height: 1.5; font-size: 0.875rem; word-break: break-word;">
                    ${safeMessage}
                </div>
                ${attachHtml}
            </div>
            <span style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem; font-weight: 600; padding: 0 0.25rem;">
                ${msg.created_at}
            </span>
        `;
        
        chatBox.appendChild(msgDiv);
        scrollToBottom();
    }

    // Polling function
    function pollMessages() {
        fetch(`/bolakausa/chat-api?action=fetch&last_id=${lastMessageId}`)
            .then(res => res.json())
            .then(res => {
                if (res.messages && res.messages.length > 0) {
                    res.messages.forEach(msg => {
                        appendMessage(msg);
                        if (msg.id > lastMessageId) {
                            lastMessageId = msg.id;
                        }
                    });
                }
            })
            .catch(err => console.error("Polling error:", err));
    }

    // Start background poller every 3 seconds
    setInterval(pollMessages, 3000);
</script>
