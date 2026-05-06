<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Premium Vault - Gemini</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bz-orange: #ff8a00;
            --bz-bg: #0f0f0f;
            --bz-card: #1a1a1a;
            --bz-text: #ffffff;
            --bz-dim: #999999;
            --bz-accent: #8ab4f8;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin:0; padding:0; }
        body { background: var(--bz-bg); color: var(--bz-text); font-family: 'Roboto', sans-serif; min-height:100vh; }

        /* Header */
        header {
            height:60px;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding:0 15px;
            position: sticky;
            top:0;
            z-index:1000;
            border-bottom:1px solid #222;
        }
        .site-logo { font-size: 20px; font-weight: 900; color: #fff; text-decoration: none; }
        .site-logo span { color: var(--bz-orange); }
        .unlock-btn {
            background: var(--bz-orange);
            color: #000;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .unlock-btn:hover { background: #ff9a20; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(255,138,0,0.3); }

        /* Hero Section */
        .hero {
            padding: 60px 20px 40px;
            text-align: center;
            background: linear-gradient(180deg, #1a1a1a 0%, #0f0f0f 100%);
            border-bottom: 1px solid #222;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(255,138,0,0.15);
            color: var(--bz-orange);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .hero h1 {
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 12px;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero p {
            font-size: 15px;
            color: #aaa;
            max-width: 600px;
            margin: 0 auto 24px;
            line-height: 1.6;
        }
        .hero .unlock-btn {
            font-size: 16px;
            padding: 14px 32px;
        }
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #222;
        }
        .stat-item { text-align: center; }
        .stat-value { font-size: 24px; font-weight: 900; color: var(--bz-orange); }
        .stat-label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

        /* Sub Nav */
        .sub-nav {
            display: flex;
            background: #151515;
            padding: 10px 15px;
            gap: 15px;
            overflow-x: auto;
            scrollbar-width: none;
            border-bottom:1px solid #222;
        }
        .sub-nav::-webkit-scrollbar { display: none; }
        .nav-tab { white-space: nowrap; font-size: 13px; font-weight: 700; color: var(--bz-dim); text-transform: uppercase; cursor: pointer; padding-bottom: 2px; }
        .nav-tab.active { color: var(--bz-orange); border-bottom: 2px solid var(--bz-orange); }

        /* PIN Modal */
        .pin-modal {
            display: none;
            position: fixed;
            top:0; left:0;
            width:100%; height:100%;
            background: rgba(0,0,0,0.95);
            z-index:5000;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding:20px;
        }
        .pin-modal.active { display: flex; }
        .pin-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: #fff; }
        .pin-input {
            width:100%;
            max-width:300px;
            background: #222;
            border:1px solid #333;
            border-radius:4px;
            padding:15px;
            color: white;
            font-size:20px;
            text-align: center;
            margin-bottom:20px;
            outline: none;
        }
        .pin-submit {
            width:100%;
            max-width:300px;
            background: var(--bz-orange);
            color: #000;
            border: none;
            padding:15px;
            border-radius:4px;
            font-weight:700;
            font-size:16px;
            cursor: pointer;
        }
        .pin-error { color: #ff3b30; font-size: 14px; margin-top:10px; display: none; }

        /* Gallery Modal */
        .gallery-modal {
            display: none;
            position: fixed;
            top:0; left:0;
            width:100%; height:100%;
            background: #000;
            z-index:6000;
            flex-direction: column;
        }
        .gallery-modal.active { display: flex; }
        .gallery-header {
            height:60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding:0 15px;
            border-bottom:1px solid #222;
        }
        .gallery-close { color: #fff; font-size: 24px; cursor: pointer; background: none; border: none; }
        .gallery-delete {
            color: #ff3b30;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }
        .gallery-body {
            flex:1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding:10px;
            position: relative;
            overflow: hidden;
        }
        .gallery-body img, .gallery-body video {
            max-width: calc(100vw - 20px);
            max-height: calc(100vh - 140px);
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .gallery-body audio {
            width: 90%;
            max-width:500px;
        }
        .gallery-nav {
            height:60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding:0 20px;
            border-top:1px solid #222;
        }
        .gallery-nav button {
            background: none;
            border: none;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            padding:10px 20px;
        }
        .gallery-counter { color: var(--bz-dim); font-size: 14px; }

        /* Loading */
        .loading { color: var(--bz-dim); text-align: center; padding:40px; font-size:14px; }
    </style>
</head>
<body>
    <header>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:24px;">✦</span>
            <a href="index.php" class="site-logo" style="font-size:20px;">Gemini</a>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
            <button style="background:transparent; border:1px solid #5f6368; color:#e8eaed; padding:8px 16px; border-radius:20px; font-size:13px; cursor:pointer;">Sign In</button>
            <button class="unlock-btn" onclick="showPinModal('unlock')">Upgrade to Pro</button>
        </div>
    </header>

    <!-- Hero Section -->
    <div class="hero">
        <div class="hero-badge" style="background:rgba(138,180,248,0.15); color:#8ab4f8;">✦ Introducing Gemini Pro</div>
        <h1 style="background:linear-gradient(135deg, #8ab4f8 0%, #c58af9 50%, #f9ab00 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent;">Experience the power of AI</h1>
        <p style="max-width:700px;">Upgrade to Gemini Pro and unlock advanced capabilities, priority access, and exclusive features designed for power users.</p>
        <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
            <button class="unlock-btn" onclick="showPinModal('unlock')" style="font-size:16px; padding:14px 32px;">Upgrade to Pro</button>
            <button style="background:transparent; border:1px solid #5f6368; color:#e8eaed; padding:14px 32px; border-radius:6px; font-size:16px; font-weight:500; cursor:pointer;">Learn More</button>
        </div>
    </div>

    <!-- Pricing Section -->
    <div style="padding:40px 20px; max-width:1200px; margin:0 auto;">
        <h2 style="text-align:center; font-size:28px; font-weight:700; margin-bottom:8px;">Choose your plan</h2>
        <p style="text-align:center; color:#9aa0a6; margin-bottom:40px;">Flexible options for everyone</p>
        
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; max-width:900px; margin:0 auto;">
            <!-- Free Tier -->
            <div style="background:#1a1a1a; border:1px solid #333; border-radius:12px; padding:24px;">
                <div style="font-size:12px; color:#9aa0a6; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Free</div>
                <div style="font-size:36px; font-weight:900; margin-bottom:4px;">$0</div>
                <div style="font-size:13px; color:#9aa0a6; margin-bottom:20px;">per month</div>
                <div style="font-size:14px; color:#e8eaed; margin-bottom:20px;">Basic AI assistance for everyone</div>
                <ul style="list-style:none; padding:0; margin:0 0 24px 0;">
                    <li style="padding:6px 0; font-size:13px; color:#9aa0a6;">✦ Standard response speed</li>
                    <li style="padding:6px 0; font-size:13px; color:#9aa0a6;">✦ Basic model access</li>
                    <li style="padding:6px 0; font-size:13px; color:#9aa0a6;">✦ Limited queries per day</li>
                </ul>
                <button style="width:100%; background:transparent; border:1px solid #5f6368; color:#e8eaed; padding:12px; border-radius:6px; font-size:14px; font-weight:500; cursor:pointer;">Current Plan</button>
            </div>
            
            <!-- Pro Tier -->
            <div style="background:linear-gradient(135deg, #1a1a2e 0%, #2a1a3e 100%); border:2px solid #8ab4f8; border-radius:12px; padding:24px; position:relative;">
                <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:#8ab4f8; color:#000; padding:4px 12px; border-radius:12px; font-size:11px; font-weight:700;">RECOMMENDED</div>
                <div style="font-size:12px; color:#8ab4f8; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Pro</div>
                <div style="font-size:36px; font-weight:900; margin-bottom:4px; color:#fff;">$19.99</div>
                <div style="font-size:13px; color:#9aa0a6; margin-bottom:20px;">per month</div>
                <div style="font-size:14px; color:#e8eaed; margin-bottom:20px;">Advanced AI for power users</div>
                <ul style="list-style:none; padding:0; margin:0 0 24px 0;">
                    <li style="padding:6px 0; font-size:13px; color:#e8eaed;">✦ Priority response speed</li>
                    <li style="padding:6px 0; font-size:13px; color:#e8eaed;">✦ Advanced model access</li>
                    <li style="padding:6px 0; font-size:13px; color:#e8eaed;">✦ Unlimited queries</li>
                    <li style="padding:6px 0; font-size:13px; color:#e8eaed;">✦ Premium Vault access</li>
                    <li style="padding:6px 0; font-size:13px; color:#e8eaed;">✦ Priority support</li>
                </ul>
                <button class="unlock-btn" onclick="showPinModal('unlock')" style="width:100%; font-size:14px; padding:12px;">Upgrade Now</button>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div style="padding:40px 20px; background:#1a1a1a;">
        <h2 style="text-align:center; font-size:24px; font-weight:700; margin-bottom:30px;">Why upgrade to Pro?</h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px; max-width:1000px; margin:0 auto;">
            <div style="text-align:center; padding:20px;">
                <div style="font-size:32px; margin-bottom:12px;">⚡</div>
                <div style="font-size:16px; font-weight:600; margin-bottom:8px;">Faster Responses</div>
                <div style="font-size:13px; color:#9aa0a6;">Priority processing with reduced latency</div>
            </div>
            <div style="text-align:center; padding:20px;">
                <div style="font-size:32px; margin-bottom:12px;">🎨</div>
                <div style="font-size:16px; font-weight:600; margin-bottom:8px;">Premium Vault</div>
                <div style="font-size:13px; color:#9aa0a6;">Exclusive access to premium content library</div>
            </div>
            <div style="text-align:center; padding:20px;">
                <div style="font-size:32px; margin-bottom:12px;">🔬</div>
                <div style="font-size:16px; font-weight:600; margin-bottom:8px;">Advanced Models</div>
                <div style="font-size:13px; color:#9aa0a6;">Access to Gemini Ultra and specialized models</div>
            </div>
        </div>
    </div>

    <!-- PIN Prompt Modal -->
    <div class="pin-modal" id="pin-modal">
        <div class="pin-title" id="pin-title">Enter PIN to Unlock Gallery</div>
        <input type="password" class="pin-input" id="pin-input" placeholder="4-digit PIN" maxlength="4" inputmode="numeric" autofocus>
        <button class="pin-submit" onclick="handlePinSubmit()">Submit</button>
        <div class="pin-error" id="pin-error">Invalid PIN</div>
    </div>

    <!-- Gallery Modal -->
    <div class="gallery-modal" id="gallery-modal">
        <div class="gallery-header">
            <button class="gallery-close" onclick="closeGallery()">✕</button>
            <button class="gallery-delete" onclick="deleteCurrentMedia()">Delete Current</button>
        </div>
        <div class="gallery-body" id="gallery-body">
            <div class="loading" id="gallery-loading">Loading media...</div>
        </div>
        <div class="gallery-nav">
            <button onclick="prevMedia()">← Previous</button>
            <div class="gallery-counter" id="gallery-counter">0 / 0</div>
            <div style="display:flex; gap:10px;">
                <button onclick="closeGallery()" style="background:rgba(255,255,255,0.1); border-radius:4px; padding: 10px 20px;">Close</button>
                <button onclick="nextMedia()">Next →</button>
            </div>
        </div>
    </div>

    <script>
        const CURRENT_USER_ID = <?php echo $user_id; ?>;
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        let currentPinAction = null; // 'unlock' or 'delete'
        let galleryMedia = [];
        let currentMediaIndex = 0;
        let deleteTargetId = null;

        // Inactivity timers
        let logoutTimer = null;
        let galleryTimer = null;

        // Reset logout timer on user activity
        function resetLogoutTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                window.location.href = 'auth.php?action=logout';
            }, 60000); // 1 minute
        }

        // Reset gallery timer on user activity
        function resetGalleryTimer() {
            const galleryModal = document.getElementById('gallery-modal');
            if (!galleryModal.classList.contains('active')) return;

            clearTimeout(galleryTimer);
            galleryTimer = setTimeout(() => {
                closeGallery();
            }, 30000); // 30 seconds
        }

        // Track user activity
        function trackActivity() {
            resetLogoutTimer();
            resetGalleryTimer();
        }

        // Add event listeners for user activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(evt => {
            document.addEventListener(evt, trackActivity, true);
        });

        // Initialize timers
        resetLogoutTimer();

        // Show PIN modal
        function showPinModal(action) {
            currentPinAction = action;
            const modal = document.getElementById('pin-modal');
            const title = document.getElementById('pin-title');
            const error = document.getElementById('pin-error');
            error.style.display = 'none';
            document.getElementById('pin-input').value = '';
 
            if (action === 'unlock') title.textContent = 'Enter PIN to Buy Premium';
 
            modal.classList.add('active');
            setTimeout(() => document.getElementById('pin-input').focus(), 100);
        }

        // Handle PIN submit
        async function handlePinSubmit() {
            const pin = document.getElementById('pin-input').value;
            if (pin.length !== 4) return;

            try {
                const resp = await fetch('api.php?action=verify_pin', {
                    method: 'POST',
                    headers: { 
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ pin: pin })
                });
                const data = await resp.json();

                if (data.success) {
                    document.getElementById('pin-modal').classList.remove('active');
                    if (currentPinAction === 'unlock') openGallery();
                } else {
                    document.getElementById('pin-error').style.display = 'block';
                }
            } catch (e) {
                document.getElementById('pin-error').textContent = 'Error verifying PIN';
                document.getElementById('pin-error').style.display = 'block';
            }
        }

        // Open gallery after PIN verification
        async function openGallery() {
            const modal = document.getElementById('gallery-modal');
            const body = document.getElementById('gallery-body');
            const loading = document.getElementById('gallery-loading');
            modal.classList.add('active');
            loading.style.display = 'block';
            body.innerHTML = '';
            body.appendChild(loading);

            // Start gallery inactivity timer
            resetGalleryTimer();

            try {
                const resp = await fetch('api.php?action=get_saved_images');
                galleryMedia = await resp.json();
                currentMediaIndex = 0;
                if (galleryMedia.length > 0) {
                    showMedia(currentMediaIndex);
                } else {
                    body.innerHTML = '<div class="loading">No media found in vault</div>';
                }
            } catch (e) {
                body.innerHTML = '<div class="loading">Failed to load media</div>';
            }
        }

        // Show specific media in gallery
        function showMedia(index) {
            if (galleryMedia.length === 0) return;
            const item = galleryMedia[index];
            const body = document.getElementById('gallery-body');
            body.innerHTML = '';

            if (item.is_voice == 1) {
                body.innerHTML = `<audio id="media-player" controls src="${item.image_data}"></audio>`;
            } else if (item.is_video == 1) {
                body.innerHTML = `<video id="media-player" src="${item.image_data}" controls></video>`;
            } else {
                body.innerHTML = `<img src="${item.image_data}">`;
            }

            document.getElementById('gallery-counter').textContent = `${index + 1} / ${galleryMedia.length}`;
            currentMediaIndex = index;

            // Reset gallery timer on media change
            resetGalleryTimer();
        }

        // Navigation
        function nextMedia() {
            if (galleryMedia.length === 0) return;
            currentMediaIndex = (currentMediaIndex + 1) % galleryMedia.length;
            showMedia(currentMediaIndex);
        }

        function prevMedia() {
            if (galleryMedia.length === 0) return;
            currentMediaIndex = (currentMediaIndex - 1 + galleryMedia.length) % galleryMedia.length;
            showMedia(currentMediaIndex);
        }

        // Delete current media
        async function deleteCurrentMedia() {
            console.log('[Gallery_Delete] Initiated');
            if (galleryMedia.length === 0) {
                console.warn('[Gallery_Delete] No media to delete');
                return;
            }
            const item = galleryMedia[currentMediaIndex];
            console.log('[Gallery_Delete] Target item:', item);

            const mediaId = item.id;
            const originalIndex = currentMediaIndex;
            const removedItem = galleryMedia.splice(currentMediaIndex, 1)[0];

            console.log('[Gallery_Delete] Optimistically removing item at index:', originalIndex);

            if (galleryMedia.length === 0) {
                document.getElementById('gallery-body').innerHTML = '<div class="loading">No media left</div>';
                document.getElementById('gallery-counter').textContent = '0 / 0';
            } else {
                if (currentMediaIndex >= galleryMedia.length) currentMediaIndex = galleryMedia.length - 1;
                showMedia(currentMediaIndex);
            }

            try {
                console.log('[Gallery_Delete] Sending API request for ID:', mediaId);
                const resp = await fetch('api.php?action=delete_saved_image', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        image_id: mediaId
                    })
                });
                
                console.log('[Gallery_Delete] API Response Status:', resp.status);
                const data = await resp.json();
                console.log('[Gallery_Delete] API Response Data:', data);

                if (!data.success) {
                    console.error('[Gallery_Delete] Server failed:', data.error);
                    alert('Server error: ' + (data.error || 'Failed to sync deletion'));
                    galleryMedia.splice(originalIndex, 0, removedItem);
                    currentMediaIndex = originalIndex;
                    showMedia(currentMediaIndex);
                } else {
                    console.log('[Gallery_Delete] Successfully deleted from server');
                }
            } catch (e) {
                console.error('[Gallery_Delete] Network/Unexpected error:', e);
                alert('Network error deleting media');
                galleryMedia.splice(originalIndex, 0, removedItem);
                currentMediaIndex = originalIndex;
                showMedia(currentMediaIndex);
            }
        }

        // Close gallery when clicking outside media/nav/header or on backdrop
        document.getElementById('gallery-modal').addEventListener('click', (e) => {
            const galleryModal = document.getElementById('gallery-modal');
            const galleryBody = document.getElementById('gallery-body');
            const galleryNav = document.querySelector('.gallery-nav');
            const galleryHeader = document.querySelector('.gallery-header');

            // Click on backdrop (modal itself) -> close
            if (e.target === galleryModal) {
                closeGallery();
                return;
            }

            // Click outside body, nav, header -> close
            if (!galleryBody.contains(e.target) &&
                !galleryNav.contains(e.target) &&
                !galleryHeader.contains(e.target)) {
                closeGallery();
            }
        });

        // Close gallery on scroll (up/down)
        window.addEventListener('scroll', () => {
            if (document.getElementById('gallery-modal').classList.contains('active')) {
                closeGallery();
            }
        });

        // Close gallery on touch move (mobile scroll)
        window.addEventListener('touchmove', () => {
            if (document.getElementById('gallery-modal').classList.contains('active')) {
                closeGallery();
            }
        });

        // Close gallery
        function closeGallery() {
            const player = document.getElementById('media-player');
            if (player) {
                player.pause();
                player.src = "";
                player.load();
            }
            document.getElementById('gallery-modal').classList.remove('active');
            document.getElementById('gallery-body').innerHTML = '';
            galleryMedia = [];
            currentMediaIndex = 0;
            clearTimeout(galleryTimer);
        }

        // PIN input enter key
        document.getElementById('pin-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') handlePinSubmit();
        });
    </script>
</body>
</html>
