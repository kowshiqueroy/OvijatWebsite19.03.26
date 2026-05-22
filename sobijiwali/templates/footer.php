<?php
/**
 * Global Customer Footer
 * Modern, Amber-themed, multi-column, and responsive.
 */
?>
    </main>

    <!-- Support Chat Popup -->
    <?php if (AuthManager::isLoggedIn()): ?>
        <div id="chat-popup" style="position: fixed; bottom: 30px; right: 30px; z-index: 5000;">
            <button id="chat-bubble" onclick="toggleChat()" style="width: 60px; height: 60px; border-radius: 50%; background: var(--primary); border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2); cursor: pointer; color: white; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; transition: var(--transition); position: relative;">
                💬
                <span id="chat-badge" style="position: absolute; top: -5px; right: -5px; background: var(--accent); color: white; border-radius: 50%; width: 22px; height: 22px; font-size: 0.7rem; font-weight: 800; display: none; align-items: center; justify-content: center; border: 2px solid white;">0</span>
            </button>

            <div id="chat-window" style="display: none; position: absolute; bottom: 80px; right: 0; width: 350px; height: 500px; background: white; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); border: 1px solid var(--border); overflow: hidden; flex-direction: column;">
                <div style="background: var(--primary); color: white; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin:0;">Sobjiwali Support</h4>
                    <button onclick="toggleChat()" style="background: none; border: none; color: white; cursor: pointer; font-size: 1.2rem;">×</button>
                </div>
                <div id="chat-messages" style="flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; background: var(--bg);">
                    <div style="text-align:center; padding:2rem; opacity:0.5; font-size:0.8rem;">Loading conversation...</div>
                </div>
                <div style="padding: 1rem; border-top: 1px solid var(--border); display: flex; gap: 10px; background: white;">
                    <input type="text" id="chat-input" placeholder="How can we help?" style="flex: 1; padding: 0.8rem; border-radius: 12px; border: 1px solid var(--border); font-size: 0.9rem; outline: none;">
                    <button onclick="sendChatMessage()" style="background: var(--primary); color: white; border: none; padding: 0 1rem; border-radius: 12px; cursor: pointer; font-weight: 800;">Send</button>
                </div>
            </div>
        </div>

        <script>
            let currentThreadId = null;
            let chatPoller = null;
            
            async function toggleChat() {
                const win = document.getElementById('chat-window');
                const isOpening = win.style.display === 'none';
                win.style.display = isOpening ? 'flex' : 'none';
                
                if (isOpening) {
                    await loadChatThreads();
                    if (currentThreadId) {
                        await loadMessages();
                        if (!chatPoller) chatPoller = setInterval(loadMessages, 5000);
                    } else {
                        showChatView('actions');
                    }
                } else {
                    if (chatPoller) { clearInterval(chatPoller); chatPoller = null; }
                }
            }

            function showChatView(view) {
                const container = document.getElementById('chat-messages');
                if (view === 'actions') {
                    container.innerHTML = `
                        <div style="display:grid; gap:0.8rem; padding:0.5rem;">
                            <p style="font-size:0.8rem; font-weight:700; opacity:0.6; text-align:center; margin-bottom:0.5rem;">How can we help you today?</p>
                            <button class="btn btn-outline" style="justify-content:flex-start; padding:1rem;" onclick="showChatView('orders')">📦 Order Inquiry</button>
                            <button class="btn btn-outline" style="justify-content:flex-start; padding:1rem;" onclick="sendQuickMsg('I want to check delivery charges for my area.')">🚚 Delivery Charges</button>
                            <button class="btn btn-outline" style="justify-content:flex-start; padding:1rem;" onclick="sendQuickMsg('Hello! I need some help.')">💬 General Chat</button>
                        </div>
                    `;
                } else if (view === 'orders') {
                    container.innerHTML = `<div style="text-align:center; padding:2rem; opacity:0.5; font-size:0.8rem;">Fetching your orders...</div>`;
                    fetch('api_support.php?action=get_recent_orders')
                        .then(r => r.json())
                        .then(data => {
                            if (data.success && data.orders.length > 0) {
                                container.innerHTML = `
                                    <div style="display:grid; gap:0.5rem; padding:0.5rem;">
                                        <button class="btn btn-outline" style="font-size:0.65rem; padding:5px;" onclick="showChatView('actions')">&larr; Back</button>
                                        <p style="font-size:0.7rem; font-weight:800; opacity:0.5; margin-bottom:0.5rem;">SELECT ORDER TO DISCUSS:</p>
                                        ${data.orders.map(o => `
                                            <button class="btn btn-outline" style="text-align:left; font-size:0.8rem; padding:0.8rem; flex-direction:column; align-items:flex-start;" onclick="sendQuickMsg('Hi, I am asking about Order #${o.id} (Status: ${o.status}).')">
                                                <strong>Order #${o.id}</strong>
                                                <small style="opacity:0.6;">Total: $${parseFloat(o.total_amount).toFixed(2)} | ${o.created_at}</small>
                                            </button>
                                        `).join('')}
                                    </div>
                                `;
                            } else {
                                container.innerHTML = `<div style="text-align:center; padding:2rem; opacity:0.5; font-size:0.8rem;">No recent orders found.<br><br><button class="btn btn-outline" onclick="showChatView('actions')">Back</button></div>`;
                            }
                        });
                }
            }

            async function sendQuickMsg(text) {
                const formData = new FormData();
                formData.append('message', text);
                if (currentThreadId) formData.append('thread_id', currentThreadId);

                const res = await fetch('<?php echo SITE_URL; ?>/api_support.php?action=send_message', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    currentThreadId = data.thread_id;
                    await loadMessages();
                    if (!chatPoller) chatPoller = setInterval(loadMessages, 5000);
                }
            }

            async function loadChatThreads() {
                const res = await fetch('<?php echo SITE_URL; ?>/api_support.php?action=get_threads');
                const data = await res.json();
                if (data.success && data.threads.length > 0) {
                    currentThreadId = data.threads[0].id;
                }
            }

            async function loadMessages() {
                if (!currentThreadId) return;
                const res = await fetch(`<?php echo SITE_URL; ?>/api_support.php?action=get_messages&thread_id=${currentThreadId}`);
                const data = await res.json();
                if (data.success) {
                    const container = document.getElementById('chat-messages');
                    const html = data.messages.map(m => `
                        <div style="max-width: 85%; padding: 0.8rem 1rem; border-radius: 16px; font-size: 0.85rem; line-height: 1.4; ${m.sender_type === 'customer' ? 'align-self: flex-end; background: var(--primary); color: white; border-bottom-right-radius: 4px;' : 'align-self: flex-start; background: white; border-bottom-left-radius: 4px; border: 1px solid var(--border);'}">
                            ${m.message}
                            <div style="font-size:0.6rem; opacity:0.5; margin-top:3px; text-align:right;">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                        </div>
                    `).join('');
                    
                    if (container.innerHTML !== html) {
                        container.innerHTML = html;
                        container.scrollTop = container.scrollHeight;
                    }
                    document.getElementById('chat-badge').style.display = 'none';
                }
            }

            async function sendChatMessage() {
                const input = document.getElementById('chat-input');
                const msg = input.value.trim();
                if (!msg) return;

                const formData = new FormData();
                formData.append('message', msg);
                if (currentThreadId) formData.append('thread_id', currentThreadId);

                const res = await fetch('<?php echo SITE_URL; ?>/api_support.php?action=send_message', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    currentThreadId = data.thread_id;
                    input.value = '';
                    await loadMessages();
                    if (!chatPoller) chatPoller = setInterval(loadMessages, 5000);
                }
            }

            async function checkNotifications() {
                try {
                    const res = await fetch('<?php echo SITE_URL; ?>/api_notifications.php?action=get_recent');
                    const data = await res.json();
                    if (data.success && data.unread_count > 0) {
                        const badge = document.getElementById('chat-badge');
                        badge.innerText = data.unread_count;
                        badge.style.display = 'flex';
                    }
                } catch(e) {}
            }

            setInterval(checkNotifications, 15000);
            checkNotifications();
        </script>
    <?php endif; ?>

    <style>
        footer { background: var(--bg-accent); color: var(--text); padding: 5rem 5% 2rem; border-top: 1px solid var(--border); margin-top: auto; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1.5fr; gap: 4rem; margin-bottom: 4rem; }
        .footer-logo { font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-bottom: 1.5rem; display: block; text-decoration: none; }
        .footer-about { opacity: 0.7; line-height: 1.8; font-size: 0.9rem; font-weight: 500; }
        .footer-title { font-weight: 800; font-size: 0.9rem; margin-bottom: 2rem; color: var(--text); text-transform: uppercase; letter-spacing: 1px; }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 1rem; }
        .footer-links a { text-decoration: none; color: var(--text-muted); transition: var(--transition); font-size: 0.9rem; font-weight: 600; }
        .footer-links a:hover { color: var(--primary); padding-left: 5px; }
        
        .newsletter-form { display: flex; gap: 0.5rem; margin-top: 1.5rem; }
        .newsletter-form input { background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.8rem 1rem; border-radius: 10px; flex: 1; font-family: inherit; }
        .newsletter-form button { background: var(--primary); border: none; color: var(--white); padding: 0.8rem 1.5rem; border-radius: 10px; font-weight: 800; cursor: pointer; transition: var(--transition); }
        .newsletter-form button:hover { background: var(--primary-light); transform: translateY(-2px); }

        .social-links { display: flex; gap: 1rem; margin-top: 2rem; }
        .social-icon { width: 40px; height: 40px; background: var(--bg); border: 1px solid var(--border); border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 1.2rem; transition: var(--transition); color: var(--text); }
        .social-icon:hover { background: var(--primary); color: var(--white); border-color: var(--primary); transform: translateY(-5px); }

        .footer-bottom { border-top: 1px solid var(--border); padding-top: 2rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); }
        
        #back-to-top { position: fixed; bottom: 30px; right: 30px; background: var(--white); color: var(--primary); width: 50px; height: 50px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 1000; opacity: 0; visibility: hidden; transition: var(--transition); box-shadow: var(--card-shadow); }
        #back-to-top.show { opacity: 1; visibility: visible; }
        #back-to-top:hover { transform: translateY(-5px); border-color: var(--primary); }

        @media (max-width: 1024px) {
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 3rem; }
        }
        @media (max-width: 600px) {
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; gap: 1rem; text-align: center; margin-bottom: 80px; }
            footer { padding-bottom: 4rem; }
        }
    </style>

    <footer>
        <div class="footer-grid">
            <div class="reveal">
                <a href="#" class="footer-logo">🥕 SOBJIWALI</a>
                <p class="footer-about">We are committed to bringing the freshest, organic, and locally-sourced vegetables directly to your doorstep. Supporting local farmers while ensuring your family eats healthy, pure nature.</p>
                <div class="social-links">
                    <a href="#" class="social-icon" title="Facebook">FB</a>
                    <a href="#" class="social-icon" title="Twitter">TW</a>
                    <a href="#" class="social-icon" title="Instagram">IG</a>
                    <a href="#" class="social-icon" title="YouTube">YT</a>
                </div>
            </div>

            <div class="reveal">
                <h4 class="footer-title">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo SITE_URL; ?>/catalog">Shop Produce</a></li>
                    <?php 
                    $footerPages = $db->query("SELECT title, slug FROM static_pages WHERE location IN ('footer', 'both')")->fetchAll();
                    foreach ($footerPages as $fp):
                    ?>
                        <li><a href="<?php echo SITE_URL; ?>/<?php echo $fp['slug']; ?>"><?php echo htmlspecialchars($fp['title']); ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="<?php echo SITE_URL; ?>/wholesale">Wholesale</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/contact">Contact Us</a></li>
                </ul>
            </div>

            <div class="reveal">
                <h4 class="footer-title">Customer Care</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo SITE_URL; ?>/account">My Account</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/track">Track Order</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/faq">FAQs</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/privacy">Privacy Policy</a></li>
                </ul>
            </div>

            <div class="reveal">
                <h4 class="footer-title">Join Our Field</h4>
                <p style="font-size: 0.85rem; opacity: 0.6; font-weight: 600;">Subscribe to get harvest alerts, seasonal recipes, and exclusive organic deals.</p>
                <form class="newsletter-form" onsubmit="event.preventDefault(); Toast.show('Welcome to the harvest family!')">
                    <input type="email" placeholder="Your email address" required>
                    <button type="submit">Join</button>
                </form>
            </div>
        </div>

        <div class="footer-bottom">
            <div>&copy; 2026 Sobjiwali Organic. All Rights Reserved.</div>
            <div>Crafted with 🌿 by <a href="https://sohojweb.com" target="_blank" style="color:var(--primary); text-decoration:none; font-weight:700;">sohojweb.com</a></div>
        </div>
    </footer>

    <button id="back-to-top" onclick="window.scrollTo({top: 0, behavior: 'smooth'})">↑</button>

    <script>
        // Scroll Logic
        window.addEventListener('scroll', () => {
            const btt = document.getElementById('back-to-top');
            if (window.scrollY > 800) btt.classList.add('show');
            else btt.classList.remove('show');
        });
    </script>
</body>
</html>
