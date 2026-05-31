<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Shared YouTube - Gemini</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✦</text></svg>">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600&family=Roboto:wght@300;400;500&family=Roboto+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --yt-bg: #0f0f0f;
            --accent: #3ea6ff;
            --header-h: 56px;
        }
        .yt-header {
            height: var(--header-h);
            padding: 0 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f0f0f;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #222;
        }
        .yt-header h1 {
            font-size: 18px;
            font-weight: 500;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .header-actions { display: flex; gap: 8px; }
        .modern-btn-header {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s;
        }
        .modern-btn-header:hover { background: rgba(255,255,255,0.2); }
        body.yt-mode {
            background: var(--yt-bg);
            margin:0;
            padding:0;
            overflow-x: hidden;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            font-family: 'Roboto', Arial, sans-serif;
            color: #fff;
            -webkit-tap-highlight-color: transparent;
        }

        /* App structure */
        .main-container { 
            display: flex; 
            flex-direction: column; 
            width: 100%; 
            min-height: 100vh;
            overflow-y: auto;
        }

        .primary-content { width: 100%; }
        .secondary-content { width: 100%; padding: 16px; box-sizing: border-box; }

        .player-outer {
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            position: relative;
            z-index: 10;
        }

        .video-info { padding: 12px; }
        .video-title { font-size: 18px; font-weight: 600; line-height: 24px; margin-bottom: 8px; }
        
        .video-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }
        .channel-info { display: flex; align-items: center; gap: 10px; }
        .channel-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 14px;
            flex-shrink: 0;
        }
        .channel-name { font-weight: 500; font-size: 15px; }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none;
        }
        .action-buttons::-webkit-scrollbar { display: none; }
        
        .action-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            padding: 8px 16px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            display: flex; align-items: center; gap: 6px;
        }

        .subscribe-btn {
            background: #cc0000;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .subscribe-btn:hover { background: #ff0000; }

        .video-description {
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 12px;
            font-size: 13px;
            line-height: 18px;
        }

        .comments-section { padding: 12px; }
        .comments-count { font-size: 16px; font-weight: 700; margin-bottom: 16px; }
        
        .add-comment { display: flex; gap: 12px; margin-bottom: 24px; }
        .comment-input-wrapper { flex: 1; }
        .comment-input {
            background: rgba(255,255,255,0.05);
            border: none;
            border-radius: 8px;
            padding: 10px 12px;
            color: #fff;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            outline: none;
        }
        .comment-input:focus { background: rgba(255,255,255,0.1); }

        .comment-actions {
            display: none;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 8px;
        }
        .cancel-btn {
            background: rgba(255,255,255,0.1);
            color: #aaa;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .cancel-btn:hover { background: rgba(255,255,255,0.2); }
        .comment-btn {
            background: #3ea6ff;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .comment-btn:hover { background: #65b8ff; }
        .delete-all-btn {
            background: rgba(255,70,70,0.15);
            color: #ff4646;
            border: 1px solid rgba(255,70,70,0.3);
            padding: 6px 12px;
            border-radius: 18px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .delete-all-btn:hover { background: rgba(255,70,70,0.3); }

        .comment-list { display: flex; flex-direction: column; gap: 16px; }
        .comment-item { display: flex; gap: 12px; }
        .comment-text { font-size: 13px; line-height: 18px; color: #f1f1f1; margin-top: 2px; }

        .sidebar-title { font-size: 15px; font-weight: 700; margin-bottom: 12px; }
        .history-list { display: flex; flex-direction: column; gap: 12px; }
        .history-item { display: flex; gap: 10px; text-decoration: none; color: inherit; }
        .history-thumb {
            width: 120px; aspect-ratio: 16/9;
            background: #222; border-radius: 8px;
            overflow: hidden; flex-shrink: 0;
        }
        .history-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .history-item-title { font-size: 13px; font-weight: 500; line-height: 16px; }
        .history-item-meta { font-size: 12px; color: #aaa; margin-top: 4px; }

        #comments-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none; z-index: 100;
            overflow: hidden;
        }
        .popup-comment {
            position: absolute;
            bottom: -50px;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            color: #fff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            white-space: pre-wrap;
            max-width: 45%;
            word-wrap: break-word;
            animation: driftUp var(--drift-duration, 8s) linear forwards;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 101;
        }
        
        .popup-comment.self {
            right: 15px;
            border-bottom-right-radius: 4px;
        }
        
        .popup-comment.other {
            left: 15px;
            border-bottom-left-radius: 4px;
        }

        @keyframes driftUp {
            0% { transform: translateY(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-350px); opacity: 0; }
        }

        /* Easy Comment UI */
        .easy-comment-trigger {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 25px;
            background: var(--accent);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            z-index: 2000;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .easy-comment-trigger:active { transform: scale(0.9); }

        .easy-comment-panel {
            position: fixed;
            bottom: 80px;
            right: 20px;
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 16px;
            padding: 12px;
            display: none;
            flex-direction: column;
            gap: 8px;
            width: 200px;
            z-index: 2000;
            box-shadow: 0 8px 30px rgba(0,0,0,0.6);
            animation: slideUp 0.3s ease;
        }
        .easy-comment-panel.show { display: flex; }
        
        .easy-btn {
            background: #2b2b2b;
            border: none;
            color: white;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            text-align: left;
            cursor: pointer;
            transition: background 0.2s;
        }
        .easy-btn:active { background: #3d3d3d; }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Desktop Adjustments */
        @media (min-width: 1000px) {
            .main-container {
                flex-direction: row;
                max-width: 1280px;
                margin: 0 auto;
                padding: 24px;
                gap: 24px;
            }
            .primary-content { flex: 1; }
            .secondary-content { width: 350px; padding: 0; }
            .player-outer { border-radius: 12px; }
            .video-info, .comments-section { padding: 12px 0; }
            .video-title { font-size: 20px; }
            .video-meta { flex-direction: row; justify-content: space-between; align-items: center; }
            .channel-avatar { width: 40px; height: 40px; }
        }

        .easy-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 8px; }
        .easy-btn { 
            background: #2b2b2b; border: 1px solid #333; color: white; padding: 8px; 
            border-radius: 8px; font-size: 12px; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .easy-btn:hover { background: #3d3d3d; border-color: #444; transform: translateY(-1px); }
        .easy-btn:active { transform: translateY(0); }
        
        .custom-comment-box { 
            display: flex; gap: 6px; background: #2b2b2b; padding: 4px; 
            border-radius: 10px; border: 1px solid #333;
        }
        .custom-comment-box input { 
            background: transparent; border: none; color: white; font-size: 13px; 
            padding: 6px; flex: 1; outline: none; width: 0;
        }
        .custom-comment-box button { 
            background: var(--accent); border: none; color: white; 
            width: 30px; height: 30px; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .easy-comment-panel { width: 220px; }
    </style>
</head>
<body class="yt-mode">
    <header class="yt-header">
        <h1>
            <span style="color: #ff0000; font-size: 24px;">▶</span>
            YouTube
        </h1>
        <div class="header-actions">
            <button class="modern-btn-header" onclick="location.href='index.php'">Home</button>
            <button class="modern-btn-header" id="exit-btn">Exit</button>
        </div>
    </header>

    <main class="main-container">
        <div class="primary-content">
            <div class="player-outer">
                <div id="player" style="width:100%; height:100%;"></div>
                <div id="comments-overlay"></div>
                <div id="loader-overlay" class="hidden">
                    <div class="loader-spinner"></div>
                </div>
            </div>

            <div class="video-info">
                <div class="video-title" id="display-title">YouTube Shared Theater</div>
                <div class="video-meta">
                    <div class="channel-info">
                        <div class="channel-avatar" id="channel-avatar">G</div>
                        <div>
                            <div class="channel-name" id="theater-status-text">Gemini Theater</div>
                            <div style="font-size: 12px; color: #aaa;" id="theater-users-count">1.2M subscribers</div>
                        </div>
                        <button class="subscribe-btn" id="subscribe-btn">Subscribe</button>
                    </div>
                    <div class="action-buttons">
                        <button class="action-btn" id="toggle-comments">
                            <span id="comment-status-icon">💬</span> 
                            <span id="comment-status-text">Hide Chat</span>
                        </button>
                        <button class="action-btn" id="load-video-trigger">Share Video</button>
                        <button class="action-btn">More</button>
                    </div>
                </div>

                <div class="video-description" id="video-desc">
                    <strong id="video-stats">1,245,678 views  Jan 1, 2026</strong><br>
                    <span id="video-description-text">Welcome to the private theater session. Share links and watch together in perfect sync.</span><br>
                    <span style="color: #aaa; margin-top: 8px; display: block;">Show more</span>
                </div>
            </div>

            <div class="comments-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <div class="comments-count" style="margin-bottom:0;">Recent Activity</div>
                    <button class="delete-all-btn" id="delete-all-comments">🗑️ Delete All</button>
                </div>
                <div class="add-comment">
                    <div class="channel-avatar" style="width: 32px; height: 32px; background: #555;" id="my-avatar">U</div>
                    <div class="comment-input-wrapper">
                        <input type="text" id="comment-input" class="comment-input" placeholder="Add a comment...">
                        <div class="comment-actions" id="comment-actions">
                            <button class="cancel-btn" id="comment-cancel">Cancel</button>
                            <button class="comment-btn" id="send-comment">Comment</button>
                        </div>
                    </div>
                </div>
                <div class="comment-list" id="comment-list">
                    <!-- Comments populate here -->
                </div>
            </div>
        </div>

        <div class="secondary-content">
            <div class="sidebar-section">
                <div class="sidebar-title">Recently Watched</div>
                <div class="history-list" id="history-list">
                    <!-- History populate here -->
                </div>
            </div>
        </div>
    </main>

    <!-- Easy Comment UI -->
    <button class="easy-comment-trigger" id="easy-comment-trigger" title="Quick Comment">💬</button>
    <div class="easy-comment-panel" id="easy-comment-panel">
        <div class="easy-grid">
            <button class="easy-btn" onclick="sendEasyComment('Wow! 😮')">Wow! 😮</button>
            <button class="easy-btn" onclick="sendEasyComment('So cool! 🔥')">So cool! 🔥</button>
            <button class="easy-btn" onclick="sendEasyComment('LMAO 😂')">LMAO 😂</button>
            <button class="easy-btn" onclick="sendEasyComment('Wait whaaat?! 😱')">Wait whaaat?! 😱</button>
            <button class="easy-btn" onclick="sendEasyComment('Mind = blown 🤯')">Mind = blown 🤯</button>
            <button class="easy-btn" onclick="sendEasyComment('Agree! 💯')">Agree! 💯</button>
        </div>
        <div class="custom-comment-box">
            <input type="text" id="easy-custom-input" placeholder="Type a comment..." onkeydown="if(event.key==='Enter') sendCustomEasy()">
            <button onclick="sendCustomEasy()">➤</button>
        </div>
    </div>

    <style>
        .easy-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 8px; }
        .easy-btn { 
            background: #2b2b2b; border: 1px solid #333; color: white; padding: 8px; 
            border-radius: 8px; font-size: 12px; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center;
        }
        .easy-btn:hover { background: #3d3d3d; border-color: #444; transform: translateY(-1px); }
        .easy-btn:active { transform: translateY(0); }
        
        .custom-comment-box { 
            display: flex; gap: 6px; background: #2b2b2b; padding: 4px; 
            border-radius: 10px; border: 1px solid #333;
        }
        .custom-comment-box input { 
            background: transparent; border: none; color: white; font-size: 13px; 
            padding: 6px; flex: 1; outline: none; width: 0;
        }
        .custom-comment-box button { 
            background: var(--accent); border: none; color: white; 
            width: 30px; height: 30px; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .easy-comment-panel { width: 220px; }
    </style>

    <script>
        const CURRENT_USER_ID = <?php echo $user_id; ?>;
        window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        let seenIds = new Set();
        
        const randomNames = [
            'Movie Buff', 'Cool Cat', 'Silent Watcher', 'Tech Geek', 'Night Owl', 
            'Happy Viewer', 'Film Fanatic', 'Daily Streamer', 'Web Surfer', 'Pixel Pal'
        ];
        // Fixed names for session consistency
        const myFakeName = randomNames[CURRENT_USER_ID % randomNames.length];
        const otherFakeName = randomNames[(CURRENT_USER_ID == 1 ? 2 : 1) % randomNames.length];

        const fakeCommentsList = [
            'Wow! 😮', 'So cool!', '🔥🔥🔥', 'This part is amazing', 'LMAO 😂',
            'Agree!', 'First time seeing this', 'Pls subscribe!', 'So true 💯',
            'Haha exactly', 'This is gold', 'Wait whaaat?! 😱', 'Mind = blown 🤯',
            'YESS! FINALLY', 'So relatable 😭', 'Anyone 2026?', 'legendary 🏆',
            'I love this song!', 'The cinematography is peak', 'Who else is watching this at 3am?',
            'This needs more views', 'Iconic moment right here', 'The ending always gets me.'
        ];

        let player;
        let isUpdating = false;
        let lastSyncTime = 0;
        let currentUrl = '';
        let isMeReady = false;
        let isOtherReady = false;
        let commentsEnabled = true;
        let realCommentsHidden = true;
        let revealTimeout;

        document.addEventListener('DOMContentLoaded', () => {
            seenIds.clear(); // Reset on load
            const lastVideo = localStorage.getItem('yt_last_video');
            if (lastVideo) currentUrl = lastVideo;
            
            document.getElementById('my-avatar').textContent = myFakeName[0];
            
            startSync();
            startComments();
            startStatus();
            loadHistory();
            startFakeComments();

            // Heartbeat to keep user online and set theater status
            setInterval(() => {
                const blob = new Blob([JSON.stringify({ is_typing: 0, in_theater: 1, in_call: 0 })], { type: 'application/json' });
                navigator.sendBeacon(`api.php?action=update_status&_csrf=${window.CSRF_TOKEN}`, blob);
            }, 5000);

            const input = document.getElementById('comment-input');
            const actions = document.getElementById('comment-actions');
            const commentBox = input.closest('.add-comment');
            input.onfocus = () => {
                actions.style.display = 'flex';
                // Scroll to keep input visible without pushing video
                setTimeout(() => {
                    commentBox.scrollIntoView({ block: 'end', behavior: 'smooth' });
                }, 100);
            };
            document.getElementById('comment-cancel').onclick = () => {
                input.value = '';
                actions.style.display = 'none';
            };

            document.getElementById('send-comment').onclick = () => {
                const txt = input.value.trim();
                if (txt) {
                    sendEasyComment(txt);
                    input.value = '';
                    actions.style.display = 'none';
                }
            };

            document.getElementById('load-video-trigger').onclick = () => {
                const url = prompt("Paste YouTube Video URL:");
                if (url) loadVideo(url);
            };

            let isSubscribed = false;
            document.getElementById('subscribe-btn').onclick = (e) => {
                e.stopPropagation();
                isSubscribed = !isSubscribed;
                const btn = document.getElementById('subscribe-btn');
                btn.textContent = isSubscribed ? 'Subscribed ✓' : 'Subscribe';
                btn.style.background = isSubscribed ? '#282828' : '#cc0000';
                btn.style.color = isSubscribed ? '#aaa' : '#fff';
                btn.style.border = isSubscribed ? '1px solid #555' : 'none';
            };

            document.getElementById('toggle-comments').onclick = () => {
                updateSync(null, !commentsEnabled);
            };

            document.getElementById('delete-all-comments').onclick = async () => {
                if (!confirm("Delete all previous comments?")) return;
                const resp = await fetch(`api.php?action=delete_youtube_comments&_csrf=${window.CSRF_TOKEN}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN }
                });
                const result = await resp.json();
                if (resp.ok && result.success) {
                    document.getElementById('comment-list').innerHTML = '';
                    seenIds.clear();
                } else {
                    alert('Delete failed: ' + (result.error || resp.status));
                }
            };

            // Easy Comment Panel Toggle
            const trigger = document.getElementById('easy-comment-trigger');
            const panel = document.getElementById('easy-comment-panel');
            trigger.onclick = (e) => {
                e.stopPropagation();
                const wasShown = panel.classList.contains('show');
                panel.classList.toggle('show');
                if (!wasShown) setTimeout(() => document.getElementById('easy-custom-input').focus(), 100);
            };
            panel.onclick = (e) => e.stopPropagation();
            document.addEventListener('click', () => panel.classList.remove('show'));
        });

        function revealRealComments() {
            realCommentsHidden = false;
            clearTimeout(revealTimeout);
            // Refresh all currently displayed comments immediately
            refreshCommentDisplay();
            revealTimeout = setTimeout(() => {
                realCommentsHidden = true;
                refreshCommentDisplay();
            }, 10000);
        }

        function refreshCommentDisplay() {
            document.querySelectorAll('.comment-item[data-real-content]').forEach(el => {
                const txtEl = el.querySelector('.comment-text');
                const userEl = el.querySelector('.comment-user');
                const avatarEl = el.querySelector('.channel-avatar');
                
                if (realCommentsHidden) {
                    txtEl.textContent = el.dataset.fakeContent;
                    userEl.textContent = el.dataset.fakeUser + ' • ' + el.dataset.time;
                    avatarEl.textContent = el.dataset.fakeUser[0];
                    avatarEl.style.background = '#444';
                    el.style.cursor = 'pointer';
                } else {
                    txtEl.textContent = el.dataset.realContent;
                    userEl.textContent = el.dataset.realUser + ' • Just now';
                    avatarEl.textContent = el.dataset.realUser[0];
                    avatarEl.style.background = el.dataset.isMe === 'true' ? '#3ea6ff' : '#cc0000';
                    el.style.cursor = 'default';
                }
            });
        }

        async function sendEasyComment(text) {
            await secureFetch('api.php?action=send_youtube_comment', {
                method: 'POST', body: JSON.stringify({ content: text })
            });
            document.getElementById('easy-comment-panel').classList.remove('show');
        }

        function sendCustomEasy() {
            const el = document.getElementById('easy-custom-input');
            const txt = el.value.trim();
            if (txt) {
                sendEasyComment(txt);
                el.value = '';
            }
        }

        const tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

        function extractVideoId(url) {
            if (!url) return '';
            url = url.trim();
            try {
                if (url.startsWith('http') || url.startsWith('www')) {
                    let fullUrl = url;
                    if (!fullUrl.startsWith('http')) fullUrl = 'https://' + fullUrl;
                    const u = new URL(fullUrl);
                    if (u.hostname.includes('youtube.com')) {
                        if (u.pathname === '/watch') return u.searchParams.get('v');
                        if (u.pathname.startsWith('/embed/')) return u.pathname.split('/')[2];
                        if (u.pathname.startsWith('/v/')) return u.pathname.split('/')[2];
                        if (u.pathname.startsWith('/live/')) return u.pathname.split('/')[2];
                    } else if (u.hostname === 'youtu.be') {
                        return u.pathname.substring(1);
                    }
                }
            } catch (e) {}
            const match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);
            return (match && match[1]) ? match[1] : (url.length === 11 ? url : '');
        }

        function onYouTubeIframeAPIReady() {
            const playerVars = {
                'playsinline': 1, 'autoplay': 0, 'controls': 1, 'modestbranding': 1, 'rel': 0,
                'origin': window.location.origin,
                'enablejsapi': 1
            };
            player = new YT.Player('player', {
                videoId: extractVideoId(currentUrl),
                playerVars: playerVars,
                events: { 'onReady': onPlayerReady, 'onStateChange': onPlayerStateChange }
            });
        }

        function onPlayerReady() {
            if (currentUrl) {
                const check = setInterval(() => {
                    if (player.getPlayerState() !== -1) { setReady(true); clearInterval(check); }
                }, 500);
            }
        }

        function onPlayerStateChange(event) {
            if (isUpdating) return;
            // Relaxed blocking: only block if BOTH are in theater and one is significantly not ready
            if (event.data === YT.PlayerState.PLAYING) {
                checkTheaterStatus().then(data => {
                    if (data.other_in_theater && (!isMeReady || !isOtherReady)) {
                        player.pauseVideo();
                    }
                });
            }
            if (event.data === YT.PlayerState.PLAYING || event.data === YT.PlayerState.PAUSED) updateSync();
        }

        async function setReady(ready) {
            isMeReady = ready;
            await secureFetch('api.php?action=update_youtube_sync', {
                method: 'POST', body: JSON.stringify({ ready: ready ? 1 : 0 })
            });
        }

        function loadVideo(url, time = 0, shouldSync = true) {
            const id = extractVideoId(url);
            if (!id) {
                if (shouldSync) alert("Invalid YouTube URL");
                return;
            }
            if (!player || !player.loadVideoById) return;
            
            isUpdating = true;
            currentUrl = url;
            localStorage.setItem('yt_last_video', url);
            setReady(false);
            
            // Only show loader if partner might be watching
            checkTheaterStatus().then(data => {
                if (data.other_in_theater) document.getElementById('loader-overlay').classList.remove('hidden');
            });

            player.loadVideoById(id, time);
            player.pauseVideo();
            
            // Fetch title and update history with retries
            let titleAttempts = 0;
            const fetchTitle = setInterval(() => {
                if (!player || !player.getVideoData) { clearInterval(fetchTitle); return; }
                const videoData = player.getVideoData();
                const title = videoData ? videoData.title : '';
                if (title || titleAttempts > 10) {
                    clearInterval(fetchTitle);
                    if (title) {
                        document.getElementById('display-title').textContent = title;
                        if (shouldSync) updateSync(url, null, title);
                        saveToHistory(url, title);
                    }
                }
                titleAttempts++;
            }, 1000);

            const check = setInterval(() => {
                if (!player || !player.getPlayerState) { clearInterval(check); return; }
                const state = player.getPlayerState();
                if (state !== YT.PlayerState.UNSTARTED && state !== -1) {
                    setReady(true); clearInterval(check); isUpdating = false;
                }
            }, 500);
        }

        async function checkTheaterStatus() {
            try {
                const resp = await secureFetch('api.php?action=get_youtube_sync');
                return resp ? await resp.json() : { other_in_theater: false };
            } catch (e) {
                return { other_in_theater: false };
            }
        }

        async function updateSync(newUrl = null, newCommentsState = null, title = '') {
            const payload = {
                state: player && player.getPlayerState ? (player.getPlayerState() === YT.PlayerState.PLAYING ? 1 : 0) : 0,
                current_time: player && player.getCurrentTime ? player.getCurrentTime() : 0
            };
            if (newUrl !== null) payload.video_id = newUrl;
            if (newCommentsState !== null) payload.comments_enabled = newCommentsState ? 1 : 0;
            if (title) payload.video_title = title;

            await secureFetch('api.php?action=update_youtube_sync', {
                method: 'POST', body: JSON.stringify(payload)
            });
        }

        function startSync() {
            setInterval(async () => {
                const resp = await secureFetch('api.php?action=get_youtube_sync');
                if (!resp) return;
                const data = await resp.json();
                
                isOtherReady = (CURRENT_USER_ID == 1) ? !!data.ready_user2 : !!data.ready_user1;
                isMeReady = (CURRENT_USER_ID == 1) ? !!data.ready_user1 : !!data.ready_user2;
                
                commentsEnabled = !!data.comments_enabled;
                document.getElementById('comment-status-text').textContent = commentsEnabled ? "Hide Chat" : "Show Chat";
                document.getElementById('comments-overlay').style.display = commentsEnabled ? 'block' : 'none';

                if (data.video_id && data.video_id !== currentUrl) {
                    loadVideo(data.video_id, data.current_time, false);
                    return;
                }

                if (data.last_updated_by == CURRENT_USER_ID) return;
                const remoteTime = parseFloat(data.updated_at);
                if (remoteTime <= lastSyncTime) return;
                lastSyncTime = remoteTime;

                isUpdating = true;
                
                // NEW: Only block playback if BOTH are in theater and someone isn't ready
                const mustSync = data.other_in_theater;
                
                if (!mustSync || (isMeReady && isOtherReady)) {
                    document.getElementById('loader-overlay').classList.add('hidden');
                    let target = parseFloat(data.current_time);
                    if (data.state == 1) target += (parseFloat(data.server_time) - remoteTime);
                    if (player && player.getCurrentTime && Math.abs(player.getCurrentTime() - target) > 1.5) player.seekTo(target, true);
                    if (player && player.playVideo) data.state == 1 ? player.playVideo() : player.pauseVideo();
                } else {
                    document.getElementById('loader-overlay').classList.remove('hidden');
                    if (player && player.pauseVideo) player.pauseVideo();
                }
                setTimeout(() => isUpdating = false, 500);
            }, 1000);
        }

        async function saveToHistory(videoId, title) {
            await secureFetch('api.php?action=update_youtube_sync', {
                method: 'POST',
                body: JSON.stringify({ video_id: videoId, video_title: title })
            });
        }

        async function loadHistory() {
            const resp = await secureFetch('api.php?action=get_video_history');
            if (!resp) { console.error('loadHistory: failed to fetch'); return; }
            
            let history = [];
            try {
                const text = await resp.text();
                if (text) history = JSON.parse(text);
            } catch (e) {
                console.error('loadHistory: parse error', e);
                return;
            }

            console.log('loadHistory:', history);
            const list = document.getElementById('history-list');
            list.innerHTML = '';
            if (history.length === 0) {
                list.innerHTML = '<div style="color:#aaa;font-size:13px;padding:8px;">No videos watched yet</div>';
                return;
            }
            history.forEach(item => {
                const div = document.createElement('a');
                div.className = 'history-item';
                div.onclick = () => loadVideo(item.video_id);
                const id = extractVideoId(item.video_id);
                div.innerHTML = `
                    <div class="history-thumb"><img src="https://img.youtube.com/vi/${id}/mqdefault.jpg"></div>
                    <div class="history-info">
                        <div class="history-item-title">${item.title || 'Untitled Video'}</div>
                        <div class="history-item-meta">Watch again</div>
                    </div>
                `;
                list.appendChild(div);
            });
        }

        function startFakeComments() {
            const list = document.getElementById('comment-list');
            setInterval(() => {
                const name = randomNames[Math.floor(Math.random() * randomNames.length)];
                const text = fakeCommentsList[Math.floor(Math.random() * fakeCommentsList.length)];
                const div = document.createElement('div');
                div.className = 'comment-item';
                div.onclick = () => { if (realCommentsHidden) revealRealComments(); };
                div.innerHTML = `
                    <div class="channel-avatar" style="width:32px; height:32px; background:#444;">${name[0]}</div>
                    <div class="comment-content">
                        <div class="comment-user">${name} • ${Math.floor(Math.random()*59)+1}m ago</div>
                        <div class="comment-text">${text}</div>
                    </div>
                `;
                list.appendChild(div);
                if (list.children.length > 50) list.removeChild(list.firstChild);
            }, 8000);
        }

        function startComments() {
            setInterval(async () => {
                const resp = await secureFetch('api.php?action=get_youtube_comments');
                if (!resp) return;
                const comments = await resp.json();
                const list = document.getElementById('comment-list');
                comments.forEach(c => {
                    if (!seenIds.has(c.id)) {
                        seenIds.add(c.id);
                        const isMe = c.sender_id == CURRENT_USER_ID;
                        const realName = isMe ? myFakeName : otherFakeName;
                        const fName = randomNames[Math.floor(Math.random() * randomNames.length)];
                        const fText = fakeCommentsList[Math.floor(Math.random() * fakeCommentsList.length)];
                        const fTime = (Math.floor(Math.random()*59)+1) + 'm ago';
                        
                        showComment(c.content, isMe, realName);
                        
                        const div = document.createElement('div');
                        div.className = 'comment-item';
                        div.dataset.realContent = c.content;
                        div.dataset.realUser = realName;
                        div.dataset.isMe = isMe;
                        div.dataset.fakeContent = fText;
                        div.dataset.fakeUser = fName;
                        div.dataset.time = fTime;
                        div.onclick = () => { if (realCommentsHidden) revealRealComments(); };

                        const dispUser = realCommentsHidden ? fName : realName;
                        const dispText = realCommentsHidden ? fText : c.content;
                        const dispAvatar = dispUser[0];
                        const dispTime = realCommentsHidden ? fTime : 'Just now';
                        const avatarBg = realCommentsHidden ? '#444' : (isMe ? '#3ea6ff' : '#cc0000');
                        
                        div.innerHTML = `
                            <div class="channel-avatar" style="width:32px; height:32px; background:${avatarBg};">${dispAvatar}</div>
                            <div class="comment-content">
                                <div class="comment-user">${dispUser} • ${dispTime}</div>
                                <div class="comment-text">${dispText}</div>
                            </div>
                        `;
                        list.insertBefore(div, list.firstChild);
                    }
                });
            }, 2000);
        }

        function showComment(text, isMe, displayName) {
            if (!commentsEnabled) return;
            const overlay = document.getElementById('comments-overlay');
            const div = document.createElement('div');
            
            // Side-based classes
            div.className = 'popup-comment ' + (isMe ? 'self' : 'other');
            div.textContent = text;
            
            // Dynamic speed: Base 6s + (0.1s per character). Max 15s.
            const duration = Math.min(15, 6 + (text.length * 0.1));
            div.style.setProperty('--drift-duration', duration + 's');
            
            // Random horizontal offset within their side (0-15px jitter)
            const jitter = Math.random() * 15;
            if (isMe) div.style.right = (15 + jitter) + 'px';
            else div.style.left = (15 + jitter) + 'px';
            
            overlay.appendChild(div);
            
            // Cleanup after animation
            setTimeout(() => div.remove(), duration * 1000);
        }

        function startStatus() {
            const avatar = document.getElementById('channel-avatar');
            const statusText = document.getElementById('theater-status-text');
            const countText = document.getElementById('theater-users-count');

            setInterval(async () => {
                const [resp1, resp2] = await Promise.all([
                    secureFetch('api.php?action=get_other_status'),
                    secureFetch('api.php?action=theater_status')
                ]);
                if (!resp1 || !resp2) return;
                const status = await resp1.json();
                const theater = await resp2.json();

                if (theater.users_in_theater >= 2) {
                    avatar.style.background = status.status === 'active' ? '#3ea6ff' : (status.status === 'typing' ? '#8ab4f8' : '#606060');
                    statusText.textContent = status.status === 'typing' ? `${otherFakeName} is typing...` : 'Gemini is Watching';
                    countText.textContent = '2 users in theater';
                } else {
                    avatar.style.background = '#606060';
                    statusText.textContent = 'Viewing Alone';
                    countText.textContent = '1 user in theater';
                }
            }, 3000);
        }
        // ... rest of scripts unchanged

        async function secureFetch(url, options = {}) {
            const defaultHeaders = { 'X-CSRF-TOKEN': window.CSRF_TOKEN || '' };
            if (options.body && !(options.body instanceof FormData)) {
                defaultHeaders['Content-Type'] = 'application/json';
            }
            options.headers = { ...defaultHeaders, ...(options.headers || {}) };
            try {
                const resp = await fetch(url, options);
                return resp.ok ? resp : null;
            } catch (e) { return null; }
        }

        document.getElementById('exit-btn').onclick = () => {
            navigator.sendBeacon(`api.php?action=leave_theater_beacon&_csrf=${window.CSRF_TOKEN}`);
            location.href = 'index.php';
        };
    </script>
</body>
</html>