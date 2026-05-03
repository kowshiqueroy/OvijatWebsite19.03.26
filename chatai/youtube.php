<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared YouTube - Gemini</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;600&family=Roboto:wght@300;400;500&family=Roboto+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --yt-bg: #0a0a0a;
            --accent: #8ab4f8;
            --header-h: 50px;
            --footer-h: 120px;
        }
        body.yt-mode {
            background: var(--yt-bg);
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100vh;
            height: -webkit-fill-available;
            display: flex;
            flex-direction: column;
            font-family: 'Google Sans', sans-serif;
        }
        html {
            height: -webkit-fill-available;
        }
        .cinematic-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 50% 50%, #1a1a1a 0%, #0a0a0a 100%);
            z-index: -1;
        }
        .yt-header {
            height: var(--header-h);
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(10px);
            z-index: 100;
        }
        .yt-header h1 {
            font-size: 16px;
            font-weight: 500;
            margin: 0;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .back-btn, .sugg-btn {
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            opacity: 0.7;
            background: rgba(255,255,255,0.1);
            padding: 5px 12px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }
        .sugg-btn { opacity: 1; }
        .sugg-btn.active { background: var(--accent); color: #000; }

        .main-stage {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: #000;
        }

        .player-outer {
            width: 100%;
            width: 100vw;
            aspect-ratio: 16/9;
            background: #000;
            position: relative;
            box-shadow: 0 0 50px rgba(0,0,0,0.5);
        }

        #player {
            width: 100%;
            height: 100%;
            border: none;
        }

        #comments-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            z-index: 10;
            overflow: hidden;
        }

        .popup-comment {
            position: absolute;
            background: rgba(0,0,0,0.08);
            backdrop-filter: blur(4px);
            color: rgba(255,255,255,0.25);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            white-space: nowrap;
            animation: drift 5s linear forwards;
            border: none;
        }
        .popup-comment.self { left: auto !important; right: 15%; }
        .popup-comment.other { left: 15% !important; }

        @keyframes drift {
            from { bottom: -50px; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            to { bottom: 100%; opacity: 0; }
        }

        .yt-footer {
            height: var(--footer-h);
            background: rgba(18,18,18,0.97);
            backdrop-filter: blur(24px) saturate(1.5);
            -webkit-backdrop-filter: blur(24px) saturate(1.5);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            z-index: 100;
            box-sizing: border-box;
            transition: background 0.5s, box-shadow 0.5s;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .yt-footer.status-online {
            background: rgba(10,42,10,0.97);
            box-shadow: 0 -4px 30px -10px rgba(76,175,80,0.2);
        }
        .yt-footer.status-typing {
            background: rgba(10,26,42,0.97);
            box-shadow: 0 -4px 30px -10px rgba(33,150,243,0.2);
        }
        .yt-footer.status-offline {
            background: rgba(42,10,10,0.97);
            box-shadow: 0 -4px 30px -10px rgba(244,67,54,0.2);
        }

        .fake-comments {
            flex: 1;
            display: flex;
            flex-direction: column-reverse;
            gap: 5px;
            max-height: calc(var(--footer-h) - 28px);
            overflow: hidden;
            pointer-events: none;
            min-width: 0;
        }
        .fake-comment {
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #bbb;
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            white-space: nowrap;
            animation: fadeInUp 0.4s ease-out;
            border: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .controls-right {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 280px;
            max-width: 400px;
        }
        .input-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .modern-input {
            flex: 1;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 10px 16px;
            color: #fff;
            font-size: 13px;
            outline: none;
            transition: all 0.25s ease;
            font-family: 'Google Sans', sans-serif;
            min-width: 0;
        }
        .modern-input:focus {
            border-color: var(--accent);
            background: rgba(255,255,255,0.09);
            box-shadow: 0 0 0 3px rgba(138,180,248,0.1);
        }
        .modern-input::placeholder { color: rgba(255,255,255,0.3); }
        .modern-btn {
            background: linear-gradient(135deg, var(--accent), #6ca0f0);
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            font-family: 'Google Sans', sans-serif;
            white-space: nowrap;
        }
        .modern-btn:hover { opacity: 0.88; transform: translateY(-1px); }
        .modern-btn:active { transform: translateY(0); }

        .status-indicator {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 11px;
            color: #aaa;
            background: rgba(0,0,0,0.5);
            padding: 4px 8px;
            border-radius: 10px;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .theater-icon {
            font-size: 14px;
        }

        .sync-status {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 10px;
            color: var(--accent);
            background: rgba(0,0,0,0.5);
            padding: 4px 8px;
            border-radius: 10px;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .fake-comments {
            flex: 1;
            display: flex;
            flex-direction: column-reverse;
            gap: 5px;
            max-height: calc(var(--footer-h) - 24px);
            overflow: hidden;
            pointer-events: none;
        }
        .fake-comment {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(6px);
            color: #aaa;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 11px;
            white-space: nowrap;
            animation: fadeIn 0.4s ease-out;
            border: 1px solid rgba(255,255,255,0.05);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .controls-right {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 320px;
        }
        .sync-status::before {
            content: "";
            width: 6px; height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }

        @media (min-width: 768px) {
            :root {
                --footer-h: 80px;
            }
            .yt-footer {
                flex-direction: row;
                align-items: flex-end;
                padding: 14px 30px;
            }
            .player-outer {
                width: 90%;
                max-width: 1200px;
                border-radius: 20px;
            }
            .main-stage {
                background: transparent;
            }
        }

        @media (max-width: 767px) {
            :root {
                --footer-h: 160px;
            }
            .yt-footer {
                flex-direction: column-reverse;
                align-items: stretch;
                padding: 12px 14px;
                gap: 10px;
            }
            .fake-comments {
                flex-direction: row;
                flex-wrap: nowrap;
                max-height: 36px;
                height: 36px;
                gap: 8px;
                overflow-x: auto;
                overflow-y: hidden;
                padding-bottom: 4px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .fake-comments::-webkit-scrollbar { display: none; }
            .fake-comment {
                flex-shrink: 0;
                font-size: 10px;
                padding: 5px 10px;
                max-width: none;
                overflow: visible;
            }
            .controls-right {
                min-width: 0;
                max-width: 100%;
            }
            .modern-input {
                font-size: 12px;
                padding: 9px 12px;
            }
            .modern-btn {
                padding: 9px 14px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="yt-mode">
    <div class="cinematic-bg"></div>
    
    <header class="yt-header">
        <h1><span class="sparkle-logo">✦</span> Gemini Theater</h1>
        <div class="header-actions">
            <a href="youtube.php" class="back-btn" id="exit-btn">Exit</a>
            <a href="index.php" class="back-btn" id="exit-to-gemini">Exit to Gemini</a>
        </div>
    </header>

        <main class="main-stage">
            <div class="player-outer">
                <div id="player"></div>
                <div id="comments-overlay"></div>
            </div>

            <div class="sync-status">Live Sync Active</div>
            <div class="status-indicator" id="status-indicator">
                <span class="theater-icon" id="theater-icon">👤</span>
                <span id="status-text">Viewing alone</span>
                <span id="status-ago"></span>
            </div>
        </main>

    <footer class="yt-footer">
        <div class="fake-comments" id="fake-comments"></div>
        <div class="controls-right">
            <div class="input-row">
                <input type="text" id="video-url" class="modern-input" placeholder="Paste Video Link...">
                <button id="load-video" class="modern-btn">Change Video</button>
            </div>
            <div class="input-row">
                <input type="text" id="comment-input" class="modern-input" placeholder="Say something...">
                <button id="send-comment" class="modern-btn">Send</button>
            </div>
        </div>
    </footer>

    <script>
        const CURRENT_USER_ID = <?php echo $user_id; ?>;
        window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        
        let player;
        let isUpdating = false;
        let lastSyncTime = 0;
        let prevOtherOnline = true;

        const tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        const firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

        function onYouTubeIframeAPIReady() {
            player = new YT.Player('player', {
                videoId: '',
                playerVars: {
                    'playsinline': 1,
                    'autoplay': 0,
                    'controls': 1,
                    'modestbranding': 1,
                    'rel': 0
                },
                events: {
                    'onReady': onPlayerReady,
                    'onStateChange': onPlayerStateChange
                }
            });
        }

        let heartbeatInterval;

        function onPlayerReady(event) {
            // Load last video from localStorage
            const lastVideoId = localStorage.getItem('yt_last_video');
            if (lastVideoId) {
                player.loadVideoById(lastVideoId);
                updateSync(lastVideoId);
                document.getElementById('video-url').value = lastVideoId;
            }
            startSync();
            startComments();
            startStatus();
            // Mark self as in theater
            secureFetch('api.php?action=update_status', {
                method: 'POST', body: JSON.stringify({ is_typing: 0, in_theater: 1 })
            });
            heartbeatInterval = setInterval(() => {
                secureFetch('api.php?action=update_status', {
                    method: 'POST', body: JSON.stringify({ is_typing: 0, in_theater: 1 })
                });
            }, 5000);
        }

        // Exit: show comment that leaving, then redirect
        document.getElementById('exit-btn').onclick = (e) => {
            e.preventDefault();
            const time = player ? Math.floor(player.getCurrentTime()) : 0;
            secureFetch('api.php?action=send_youtube_comment', {
                method: 'POST',
                body: JSON.stringify({ content: `Im leaving for now, coming soon. (Left at ${time}s)` })
            });
            navigator.sendBeacon(`api.php?action=leave_theater_beacon&_csrf=${window.CSRF_TOKEN}`);
            clearInterval(heartbeatInterval);
            setTimeout(() => { window.location.href = 'index.php'; }, 500);
        };

        // Exit to Gemini: bring other to chat page too
        document.getElementById('exit-to-gemini').onclick = (e) => {
            e.preventDefault();
            secureFetch('api.php?action=send_youtube_comment', {
                method: 'POST',
                body: JSON.stringify({ content: 'Im going to Gemini chat, come join me there!' })
            });
            navigator.sendBeacon(`api.php?action=leave_theater_beacon&_csrf=${window.CSRF_TOKEN}`);
            clearInterval(heartbeatInterval);
            setTimeout(() => { window.location.href = 'index.php'; }, 500);
        };

        // Mark as left theater when leaving
        window.addEventListener('beforeunload', () => {
            navigator.sendBeacon(`api.php?action=leave_theater_beacon&_csrf=${window.CSRF_TOKEN}`);
            clearInterval(heartbeatInterval);
        });

        function onPlayerStateChange(event) {
            if (isUpdating) return;
            if (event.data === YT.PlayerState.PLAYING || event.data === YT.PlayerState.PAUSED) {
                updateSync();
            }
        }

        async function secureFetch(url, options = {}) {
            const defaultHeaders = { 'X-CSRF-TOKEN': window.CSRF_TOKEN || '' };
            if (options.body && !(options.body instanceof FormData)) {
                defaultHeaders['Content-Type'] = 'application/json';
            }
            options.headers = { ...defaultHeaders, ...(options.headers || {}) };
            try { return await fetch(url, options); } catch (e) { return null; }
        }

        function extractVideoId(url) {
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            const match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : url;
        }

        document.getElementById('load-video').onclick = () => {
            const val = document.getElementById('video-url').value.trim();
            if (!val) return;
            const videoId = extractVideoId(val);
            player.loadVideoById(videoId);
            updateSync(videoId);
            localStorage.setItem('yt_last_video', videoId);
        };

        document.getElementById('send-comment').onclick = sendComment;
        document.getElementById('comment-input').onkeypress = (e) => { if (e.key === 'Enter') sendComment(); };

        async function sendComment() {
            const input = document.getElementById('comment-input');
            const val = input.value.trim();
            if (!val) return;
            input.value = '';
            await secureFetch('api.php?action=send_youtube_comment', {
                method: 'POST',
                body: JSON.stringify({ content: val })
            });
        }

        async function updateSync(newVideoId = null) {
            if (!player || !player.getCurrentTime) return;
            const state = player.getPlayerState();
            const currentTime = player.getCurrentTime();
            await secureFetch('api.php?action=update_youtube_sync', {
                method: 'POST',
                body: JSON.stringify({
                    video_id: newVideoId,
                    state: state === YT.PlayerState.PLAYING ? 1 : 0,
                    current_time: currentTime
                })
            });
            if (newVideoId) localStorage.setItem('yt_last_video', newVideoId);
        }

        function startSync() {
            setInterval(async () => {
                const resp = await secureFetch('api.php?action=get_youtube_sync');
                if (!resp) return;
                const data = await resp.json();
                if (data.last_updated_by == CURRENT_USER_ID) return;
                const remoteUpdatedAt = new Date(data.updated_at).getTime();
                if (remoteUpdatedAt <= lastSyncTime) return;
                lastSyncTime = remoteUpdatedAt;

                isUpdating = true;
                const currentVideoId = player.getVideoData().video_id;

                // Pause if other user is offline
                if (data.other_online === false) {
                    player.pauseVideo();
                    isUpdating = false;
                    return;
                }

                if (data.video_id && data.video_id !== currentVideoId) {
                    player.loadVideoById(data.video_id, data.current_time);
                } else {
                    const localTime = player.getCurrentTime();
                    if (Math.abs(localTime - data.current_time) > 2) {
                        player.seekTo(data.current_time, true);
                    }
                }
                if (data.state === 1) player.playVideo(); else player.pauseVideo();
                setTimeout(() => { isUpdating = false; }, 500);
            }, 2000);
        }

        function startComments() {
            const seenCommentIds = new Set();
            setInterval(async () => {
                const resp = await secureFetch('api.php?action=get_youtube_comments');
                if (!resp) return;
                const comments = await resp.json();
                comments.reverse().forEach(c => {
                    if (!seenCommentIds.has(c.id)) {
                        seenCommentIds.add(c.id);
                        showComment(c.content, c.sender_id == CURRENT_USER_ID);
                    }
                });
            }, 2000);
        }

        function showComment(text, isMe = false) {
            const overlay = document.getElementById('comments-overlay');
            const div = document.createElement('div');
            div.className = 'popup-comment ' + (isMe ? 'self' : 'other');
            div.textContent = text;
            div.style.bottom = `-50px`;
            overlay.appendChild(div);
            setTimeout(() => div.remove(), 6000);
        }

        // Fake live comments on left side
        const fakeComments = [
            'Wow! 😮', 'So cool!', '🔥🔥🔥', 'This part is amazing', 'LMAO 😂',
            'Agree!', 'First time seeing this', 'Plo subscribe!', 'So true 💯',
            'Haha exactly', 'This is gold', 'Wait whaaat?! 😱', 'Mind = blown 🤯',
            'YESS! FINALLY', 'So relatable 😭', 'Anyone 2026?', 'legendary 🏆'
        ];
        const fakeNames = ['Gemini Fan', 'Night Owl', 'Movie Buff', 'Tech Geek', 'Happy Viewer'];
        function startFakeComments() {
            const container = document.getElementById('fake-comments');
            setInterval(() => {
                const div = document.createElement('div');
                div.className = 'fake-comment';
                const name = fakeNames[Math.floor(Math.random() * fakeNames.length)];
                div.textContent = `${name}: ${fakeComments[Math.floor(Math.random() * fakeComments.length)]}`;
                container.appendChild(div);
                if (container.children.length > 8) container.removeChild(container.firstChild);
                setTimeout(() => { if (div.parentNode) div.remove(); }, 8000);
            }, 2500);
        }
        startFakeComments();

        function startStatus() {
            const footer = document.querySelector('.yt-footer');
            const icon = document.getElementById('theater-icon');
            const text = document.getElementById('status-text');
            const ago = document.getElementById('status-ago');

            setInterval(async () => {
                const [resp1, resp2] = await Promise.all([
                    secureFetch('api.php?action=get_other_status'),
                    secureFetch('api.php?action=theater_status')
                ]);
                if (!resp1 || !resp2) return;
                const otherStatus = await resp1.json();
                const theaterData = await resp2.json();

                footer.classList.remove('status-online','status-typing','status-offline');

                // Update theater icon: 👤 alone, 👥 both
                if (theaterData.users_in_theater >= 2) {
                    icon.textContent = '👥';
                    text.textContent = 'Gemini Partner is With You';
                } else {
                    icon.textContent = '👤';
                    text.textContent = 'Viewing alone';
                }

                if (otherStatus.status === 'typing') {
                    footer.classList.add('status-typing');
                    text.textContent = 'Other is typing...';
                } else if (otherStatus.status === 'active') {
                    footer.classList.add('status-online');
                } else {
                    footer.classList.add('status-offline');
                }

                if (otherStatus.last_seen && otherStatus.status === 'offline') {
                    const diff = Math.floor((Date.now()/1000 - otherStatus.last_seen) / 60);
                    ago.textContent = diff <= 0 ? '(just now)' : `(${diff}m ago)`;
                } else {
                    ago.textContent = '';
                }

            }, 2000);
        }

        let typingTimer;
        const commentInput = document.getElementById('comment-input');
        commentInput.addEventListener('input', () => {
            clearTimeout(typingTimer);
            secureFetch('api.php?action=update_status', {
                method: 'POST', body: JSON.stringify({ is_typing: 1 })
            });
            typingTimer = setTimeout(() => {
                secureFetch('api.php?action=update_status', {
                    method: 'POST', body: JSON.stringify({ is_typing: 0 })
                });
            }, 1500);
        });
    </script>
</body>
</html>