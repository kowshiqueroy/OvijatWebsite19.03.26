<?php
session_start();
require_once 'db.php';

if (!isLoggedIn()) { header('Location: index.php'); exit; }

$chatWithId = (int)($_GET['user'] ?? 0);
if (!$chatWithId) { header('Location: users.php'); exit; }

$myId = $_SESSION['user_id'];
updateLastActive($myId, "u$chatWithId");

$chatUser = getUserById($chatWithId);
if (!$chatUser) { header('Location: users.php'); exit; }
$nickname = getNickname($myId, $chatWithId);
$chatDisplayName = str_replace(' ', '_', $nickname ?: $chatUser['display_name']);

$isUnlocked = isChatUnlocked($myId, $chatWithId);
markMessagesAsRead($myId, $chatWithId);
$messages = getMessages($myId, $chatWithId);
$connectedUsers = getConnectedUsers($myId);

function getRealisticCamouflage($msg, $isSent) {
    $seed = crc32($msg); mt_srand($seed);
    $prompts = ["Explain Virtual DOM in React.", "Python script for web scraping.", "বাংলা সংস্কৃতি নিয়ে বলুন।", "SQL vs NoSQL scalability.", "GitHub Actions CI/CD guide.", "JS arrow function scope.", "একটি কোড লিখে দাও।", "Distributed systems CAP theorem.", "JWT security in Node.js.", "Best frontend framework 2024?", "Complex CSS Grid dashboards.", "PostgreSQL performance tuning.", "Robot emotions short story.", "Hoisting in JavaScript.", "OS Process vs Thread.", "রেসপন্সিভ ডিজাইন ও মিডিয়া কুয়েরি।"];
    $responses = ["The Virtual DOM is a lightweight copy...", "Use requests and BeautifulSoup in Python...", "বাংলা সংস্কৃতি অত্যন্ত সমৃদ্ধ...", "SQL is vertical, NoSQL is horizontal...", "CI/CD automates your deployment pipeline...", "Arrow functions inherit 'this' lexically...", "আমার দ্বারা আর বেশি কাজ হচ্ছে না। আপনি প্রো ইউজার...। তাই আরও লিমিট দরকার", "Consistency, Availability, Partition tolerance...", "Store JWT in HttpOnly cookies for safety...", "React and Next.js are current leaders...", "CSS Grid is perfect for 2D layouts...", "Proper indexing is key for database speed...", "The robot began to feel a strange warmth...", "Hoisting moves declarations to the top...", "A process has its own memory space...", "Media queries are essential for mobile."];
    $res = $isSent ? $prompts[mt_rand(0, count($prompts)-1)] : $responses[mt_rand(0, count($responses)-1)];
    mt_srand(); return $res;
}

function getSynthesizedView($realMsg) {
    $words = explode(' ', $realMsg); $reversed = array_reverse($words);
    $code = "#include <stdio.h>\n\nint main() {\n";
    $mid = floor(count($reversed) / 2);
    foreach($reversed as $i => $w) {
        if ($i == $mid) $code .= "    // demo logic execute\n";
        $code .= "    printf(\"" . htmlspecialchars($w) . "!\\n\");\n";
    }
    $code .= "    return 0;\n}";
    return $code;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Gemini</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&family=Roboto+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --gemini-bg: #131314;
            --gemini-sidebar: #1e1f20;
            --gemini-text: #e3e3e3;
            --gemini-dim: #b4b4b4;
            --gemini-blue: #4285f4;
            --gemini-input: #1e1f20;
            --status-color: var(--gemini-blue);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        html, body { 
            height: 100dvh; 
            overflow: hidden; 
            background: var(--gemini-bg);
            width: 100vw;
        }

        body { font-family: 'Google Sans', Roboto, Arial, sans-serif; color: var(--gemini-text); display: flex; user-select: none; }

        .sidebar { 
            width: 280px; background: var(--gemini-sidebar); padding: calc(20px + env(safe-area-inset-top)) 12px 20px; 
            display: flex; flex-direction: column; gap: 8px; position: relative; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 1001; flex-shrink: 0;
        }
        @media (max-width: 768px) {
            .sidebar { position: absolute; left: 0; top: 0; bottom: 0; transform: translateX(-100%); width: 280px; }
            .sidebar.active { transform: translateX(0); box-shadow: 10px 0 30px rgba(0,0,0,0.5); }
        }
        
        .new-chat-btn { background: #28292a; color: var(--gemini-text); padding: 12px 16px; border-radius: 24px; font-size: 14px; display: flex; align-items: center; gap: 12px; cursor: pointer; width: fit-content; margin-bottom: 20px; }
        .recent-item { padding: 10px 12px; font-size: 13px; color: var(--gemini-text); opacity: 0.7; border-radius: 8px; cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .main-container { flex: 1; display: flex; flex-direction: column; height: 100dvh; min-width: 0; position: relative; }

        .header { 
            padding: calc(10px + env(safe-area-inset-top)) 16px 10px; 
            display: flex; justify-content: space-between; align-items: center; 
            background: var(--gemini-bg); border-bottom: 1px solid rgba(255,255,255,0.05); flex-shrink: 0;
        }
        .header-left { display: flex; align-items: center; gap: 10px; font-weight: 500; font-size: 18px; }
        .menu-toggle { font-size: 20px; cursor: pointer; padding: 4px; }
        @media (min-width: 769px) { .menu-toggle { display: none; } }
        .sparkle-logo { background: linear-gradient(45deg, #4285f4, #9b72cb, #d96570); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 20px; }

        .header-right { display: flex; align-items: center; gap: 12px; }
        .ai-pro-text { font-size: 14px; font-weight: bold; background: linear-gradient(90deg, #4285f4, #9b72cb, #d96570, #4285f4); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: shimmerText 3s linear infinite; }
        @keyframes shimmerText { to { background-position: 200% center; } }
        
        .timer-container { position: relative; width: 28px; height: 28px; }
        .timer-container svg { transform: rotate(-90deg); width: 28px; height: 28px; }
        .timer-container circle { fill: none; stroke-width: 2.5; transition: stroke 0.3s; }
        .timer-container .bg { stroke: #222; }
        .timer-container .progress { stroke: var(--status-color); stroke-dasharray: 88; stroke-dashoffset: 0; }
        .timer-btn { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: none; border: none; font-size: 11px; color: var(--gemini-dim); cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; }

        .chat-content { flex: 1; overflow-y: auto; padding: 10px 0; display: flex; flex-direction: column; align-items: center; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; }
        .chat-inner { width: 100%; max-width: 760px; padding: 0 16px; }

        .message-row { margin-bottom: 24px; display: flex; gap: 12px; width: 100%; }
        .avatar { width: 32px; height: 32px; border-radius: 50%; background: #333; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .avatar.real-ai { background: linear-gradient(45deg, #4285f4, #9b72cb, #d96570); color: #fff; }
        .avatar.real-user { background: #4285f4; color: #fff; }
        .avatar.demo { background: #333; color: #888; }

        .msg-body { flex: 1; line-height: 1.6; font-size: 15px; color: var(--gemini-text); overflow-wrap: break-word; }
        .msg-text.revealed { color: #fff; font-weight: 500; font-family: 'Roboto Mono', monospace; font-size: 13px; border-left: 2px solid var(--gemini-blue); padding-left: 12px; background: rgba(66, 133, 244, 0.05); white-space: pre-wrap; }

        .thinking-area { width: 100%; max-width: 760px; margin: 0 auto; padding: 0 16px 10px 16px; display: flex; align-items: flex-start; gap: 12px; flex-shrink: 0; }
        .thinking-sparkle { width: 32px; height: 32px; border-radius: 50%; background: #222; display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--gemini-dim); flex-shrink: 0; }
        .thinking-sparkle.active { background: linear-gradient(45deg, #4285f4, #9b72cb, #d96570); color: #fff; animation: sparklePulse 2s infinite; }
        .thinking-content { flex: 1; }
        .thinking-main-text { font-size: 14px; color: var(--gemini-blue); font-weight: 500; margin-bottom: 2px; }
        .shimmer-line { height: 8px; width: 60%; background: #222; border-radius: 4px; position: relative; overflow: hidden; margin-bottom: 4px; }
        .shimmer-line::after { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent); animation: shimmer 1.5s infinite; }
        .status-log { font-size: 11px; color: var(--gemini-dim); font-family: 'Roboto Mono', monospace; min-height: 1.2em; line-height: 1.2; }

        @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        @keyframes sparklePulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }

        .input-outer { 
            padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); 
            background: var(--gemini-bg); display: flex; justify-content: center; 
            border-top: 1px solid rgba(255,255,255,0.05); flex-shrink: 0;
        }
        .input-wrapper { width: 100%; max-width: 760px; background: var(--gemini-input); border-radius: 28px; padding: 4px 16px; display: flex; align-items: center; gap: 10px; }
        .input-wrapper input { flex: 1; background: none; border: none; color: var(--gemini-text); font-size: 16px; outline: none; padding: 10px 0; min-width: 0; }
        .send-btn { background: none; border: none; color: var(--gemini-blue); font-size: 24px; cursor: pointer; padding: 4px; display: flex; align-items: center; }

        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; backdrop-filter: blur(2px); }
        .overlay.active { display: block; }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
    <div class="sidebar" id="sidebar">
        <div class="new-chat-btn" onclick="location.href='users.php'"><span>+</span> New chat</div>
        <div style="font-size:12px; color:var(--gemini-dim); padding-left:12px; margin-bottom:10px;">Recent</div>
        
        <?php 
        $fakeHistory = [
            "Python Recursion Analysis",
            "Mughal Empire History",
            "React Virtual DOM Guide",
            "CSS Grid Layout Tips",
            "JavaScript Event Loop",
            "Bangla Poem Generation",
            "PostgreSQL Query Tuning",
            "JWT Security Protocols"
        ];
        
        // Render Real Chats first
        foreach ($connectedUsers as $cu): ?>
            <div class="recent-item" onclick="location.href='aichat.php?user=<?= $cu['id'] ?>'">
                <b style="color:var(--gemini-text)"><?= sanitize($cu['display_name']) ?></b>
            </div>
        <?php endforeach; 
        
        // Render Fake History
        foreach ($fakeHistory as $fh): ?>
            <div class="recent-item" style="opacity:0.6; cursor:default;">
                <b style="color:var(--gemini-text)"><?= $fh ?></b>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="main-container">
        <div class="header">
            <div class="header-left">
                <span class="menu-toggle" onclick="toggleSidebar()">☰</span>
                Gemini <span class="sparkle-logo">✦</span>
            </div>
            <div class="header-right">
                <div class="ai-pro-text">AI Pro</div>
                <div class="timer-container">
                    <svg viewBox="0 0 34 34"><circle class="bg" cx="17" cy="17" r="14"/><circle class="progress" id="timerCircle" cx="17" cy="17" r="14"/></svg>
                    <button class="timer-btn" onclick="toggleLock()"><?= $isUnlocked ? 'U' : 'L' ?></button>
                </div>
            </div>
        </div>

        <div class="chat-content" id="msgs">
            <div class="chat-inner">
                <div class="message-row"><div class="avatar demo">👤</div><div class="msg-body">How do I center a div in CSS?</div></div>
                <div class="message-row"><div class="avatar demo">✦</div><div class="msg-body">Use `display: grid; place-items: center;` on the parent container.</div></div>
                <?php foreach ($messages as $m): ?>
                    <?php $isSent = $m['sender_id'] == $myId; $cam = getRealisticCamouflage($m['original'] ?? $m['message'], $isSent); $syn = getSynthesizedView($m['original'] ?? $m['message']); ?>
                    <div class="message-row msg-real" data-id="<?= $m['id'] ?>" data-synthesized="<?= sanitize($syn) ?>" data-camouflage="<?= sanitize($cam) ?>">
                        <div class="avatar <?= $isSent ? 'real-user' : 'real-ai' ?>"><?= $isSent ? '👤' : '✦' ?></div>
                        <div class="msg-body"><div class="msg-text"><?= $cam ?></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="thinking-area">
            <div class="thinking-sparkle active" id="thinkingSparkle">✦</div>
            <div class="thinking-content">
                <div class="thinking-main-text" id="thinkingMainText">Gemini is thinking...</div>
                <div class="shimmer-line"></div>
                <div class="status-log" id="statusLog">[Nodes_Synchronized]</div>
            </div>
        </div>

        <div class="input-outer">
            <div class="input-wrapper">
                <form id="chatForm" style="display:flex; flex:1; align-items:center;">
                    <input type="text" id="msgInput" placeholder="Enter a prompt here" autocomplete="off">
                    <button type="submit" class="send-btn">➤</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    const chatWithId = <?= $chatWithId ?>, isUnlocked = <?= $isUnlocked ? 'true' : 'false' ?>, myId = <?= $myId ?>, displayName = "<?= sanitize($chatDisplayName) ?>";
    let lastId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>, timeLeft = 60, lockInterval, isThinking = false, isOtherTyping = false;
    const circumference = 2 * Math.PI * 14;

    function toggleSidebar() { document.getElementById('sidebar').classList.toggle('active'); document.getElementById('overlay').classList.toggle('active'); }
    function resetTimer() { if (!isUnlocked) return; timeLeft = 60; clearInterval(lockInterval); lockInterval = setInterval(() => { timeLeft--; updateTimerUI(); if (timeLeft <= 0) toggleLock(); }, 1000); }
    function updateTimerUI() { document.getElementById('timerCircle').style.strokeDashoffset = circumference * (1 - timeLeft / 60); }
    function toggleLock() { if (isUnlocked) { const fd = new FormData(); fd.append('action', 'lock'); fd.append('csrf_token', '<?= generateCsrfToken() ?>'); fetch('chat.php?user=' + chatWithId, { method: 'POST', body: fd }).then(() => location.reload()); } }

    const logs = ["[System_Active]", "[Nodes_Synchronized]", "[Connection_Secure]", "[Analyzing_Clusters]", "[Synthesizing_Output]"];
    const standbyLogs = ["[Nodes_Dormant]", "[Standby_Mode]", "[Last_Sync_OK]", "[Power_Optimization]"];
    const thinkLogs = ["[Improvising_Context]", "[Drafting_Insights]", "[Processing_Neural_Nodes]"];

    document.getElementById('msgs').addEventListener('click', (e) => {
        const row = e.target.closest('.msg-real'); if (!row || !isUnlocked) return;
        const textEl = row.querySelector('.msg-text');
        const isReceived = row.querySelector('.avatar').classList.contains('real-ai');
        
        if (isReceived) {
            fetch('api.php?action=view_message&id=' + row.dataset.id);
        }

        textEl.textContent = row.dataset.synthesized; textEl.classList.add('revealed'); resetTimer();
        
        setTimeout(() => { 
            textEl.textContent = row.dataset.camouflage; 
            textEl.classList.remove('revealed'); 
        }, 5000);
    });

    document.getElementById('msgInput').addEventListener('input', () => {
        fetch(`api.php?action=set_typing&user=${chatWithId}`);
        resetTimer();
    });

    document.getElementById('chatForm').addEventListener('submit', (e) => {
        e.preventDefault(); const input = document.getElementById('msgInput'), msg = input.value.trim();
        if (!msg) return; input.value = ''; isThinking = true; resetTimer();
        const fd = new FormData(); fd.append('user', chatWithId); fd.append('message', msg); fd.append('csrf_token', '<?= generateCsrfToken() ?>');
        fetch('api.php?action=send_message', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.unlocked) location.reload(); else { fetchMessages(); setTimeout(()=>isThinking=false, 3000); } });
    });

    function fetchMessages() {
        fetch(`api.php?action=messages&user=${chatWithId}&last_id=${lastId}`).then(r => r.json()).then(d => {
            if (d.messages && d.messages.length > 0) {
                const container = document.querySelector('.chat-inner');
                d.messages.forEach(m => {
                    if (document.querySelector(`.msg-real[data-id="${m.id}"]`)) return;
                    lastId = m.id; const isSent = m.sender_id == myId;
                    const words = (m.original || m.message).split(' '), rev = words.reverse();
                    let code = "#include <stdio.h>\n\nint main() {\n";
                    rev.forEach((w, i) => { if(i==Math.floor(rev.length/2)) code+="    // demo logic\n"; code+=`    printf("${w}!\\n");\n`; });
                    code += "    return 0;\n}";
                    const cam = getCam(m.original || m.message, isSent);
                    const div = document.createElement('div'); div.className = 'message-row msg-real';
                    div.dataset.id = m.id; div.dataset.synthesized = code; div.dataset.camouflage = cam;
                    div.innerHTML = `<div class="avatar ${isSent ? 'real-user' : 'real-ai'}">${isSent ? '👤' : '✦'}</div><div class="msg-body"><div class="msg-text">${cam}</div></div>`;
                    container.appendChild(div);
                });
                document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
            }
        });
    }

    function getCam(msg, isSent) {
        let hash = 0; for (let i=0; i<msg.length; i++) hash = ((hash << 5) - hash) + msg.charCodeAt(i);
        const arr = isSent ? ["Explain React Virtual DOM...", "Python data scraping...", "SQL query for salary...", "কেমন আছেন আপনি?", "Mughal History summary..."] : ["The Virtual DOM is a copy...", "Use BeautifulSoup in Python...", "SELECT MAX(salary) FROM...", "আমি ভালো আছি, ধন্যবাদ।", "The Empire was founded..."];
        return arr[Math.abs(hash) % arr.length];
    }

    function updateStatus() {
        fetch(`api.php?action=status&user=${chatWithId}`).then(r => r.json()).then(d => {
            const logEl = document.getElementById('statusLog');
            const root = document.documentElement;
            
            // Log rotation logic
            let currentStatus = d.status.split('(')[0].trim().replace(/ /g, '_');
            let pool = [];
            if (isThinking || d.typing) {
                root.style.setProperty('--status-color', '#4285f4'); // Blue
                pool = [thinkLogs[Math.floor(Math.random()*thinkLogs.length)], `[${displayName}]`, `[Typing...]` ];
                isOtherTyping = d.typing;
            } else if (d.is_online) {
                root.style.setProperty('--status-color', d.status === 'In other chat' ? '#ffca28' : '#28a745');
                pool = [logs[Math.floor(Math.random()*logs.length)], `[${displayName}]`, `[${currentStatus}]` ];
            } else {
                root.style.setProperty('--status-color', '#f44336'); // Red
                let timeAgo = d.status.includes('(') ? d.status.split('(')[1].replace('Last seen ', '').replace(')', '').replace(/ /g, '_') : 'Never';
                pool = [standbyLogs[Math.floor(Math.random()*standbyLogs.length)], `[${displayName}]`, `[Offline_${timeAgo}]` ];
            }
            logEl.textContent = pool[Math.floor(Date.now() / 1000) % pool.length];
        });
        fetch(`api.php?action=typing&user=${chatWithId}`).then(r => r.json()).then(d => isOtherTyping = d.typing);
    }

    // Scroll Fix for iOS Keyboard
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
            const h = window.visualViewport.height;
            document.body.style.height = h + 'px';
            window.scrollTo(0, 0); // Prevent iOS scroll shift
            document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
        });
    }

    setInterval(fetchMessages, 2000); setInterval(updateStatus, 1000); updateStatus();
    if (isUnlocked) resetTimer();
    document.getElementById('msgs').scrollTop = document.getElementById('msgs').scrollHeight;
    window.addEventListener('mousemove', resetTimer); window.addEventListener('keypress', resetTimer);
    window.addEventListener('touchstart', resetTimer);
    </script>
</body>
</html>