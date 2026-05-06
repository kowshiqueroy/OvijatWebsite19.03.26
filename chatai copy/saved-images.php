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
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
        body { background: var(--bz-bg); color: var(--bz-text); font-family: 'Roboto', sans-serif; min-height: 100vh; }

        /* Header */
        header {
            height: 60px;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 15px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #222;
        }
        .site-logo { font-size: 20px; font-weight: 900; color: #fff; text-decoration: none; }
        .site-logo span { color: var(--bz-orange); }
        .unlock-btn {
            background: var(--bz-orange);
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
        }

        /* Sub Nav */
        .sub-nav {
            display: flex;
            background: #151515;
            padding: 10px 15px;
            gap: 15px;
            overflow-x: auto;
            scrollbar-width: none;
            border-bottom: 1px solid #222;
        }
        .sub-nav::-webkit-scrollbar { display: none; }
        .nav-tab { white-space: nowrap; font-size: 13px; font-weight: 700; color: var(--bz-dim); text-transform: uppercase; }
        .nav-tab.active { color: var(--bz-orange); border-bottom: 2px solid var(--bz-orange); }

        /* Fake Media Grid */
        .media-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1px;
            padding-bottom: 80px;
        }
        @media (min-width: 768px) {
            .media-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (min-width: 1024px) {
            .media-grid { grid-template-columns: repeat(3, 1fr); }
        }
        .bz-card { background: #000; }
        .bz-thumb-container {
            width: 100%;
            aspect-ratio: 16/9;
            background: #111;
            overflow: hidden;
            position: relative;
        }
        .bz-thumb { width: 100%; height: 100%; object-fit: cover; filter: brightness(0.7); }
        .bz-info { padding: 12px 15px; }
        .bz-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; color: #eee; }
        .bz-meta { font-size: 11px; color: var(--bz-dim); display: flex; gap: 10px; }

        /* PIN Modal */
        .pin-modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 5000;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .pin-modal.active { display: flex; }
        .pin-title { font-size: 20px; font-weight: 700; margin-bottom: 20px; color: #fff; }
        .pin-input {
            width: 100%;
            max-width: 300px;
            background: #222;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 15px;
            color: white;
            font-size: 20px;
            text-align: center;
            margin-bottom: 20px;
            outline: none;
        }
        .pin-submit {
            width: 100%;
            max-width: 300px;
            background: var(--bz-orange);
            color: #000;
            border: none;
            padding: 15px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
        }
        .pin-error { color: #ff3b30; font-size: 14px; margin-top: 10px; display: none; }

        /* Gallery Modal */
        .gallery-modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: #000;
            z-index: 6000;
            flex-direction: column;
        }
        .gallery-modal.active { display: flex; }
        .gallery-header {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 15px;
            border-bottom: 1px solid #222;
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
            padding: 10px;
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
            max-width: 500px;
        }
        .gallery-body img, .gallery-body video, .gallery-body audio {
            max-width: 100%;
            max-height: 100%;
        }
        .gallery-nav {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            border-top: 1px solid #222;
        }
        .gallery-nav button {
            background: none;
            border: none;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            padding: 10px 20px;
        }
        .gallery-counter { color: var(--bz-dim); font-size: 14px; }

        /* Loading */
        .loading { color: var(--bz-dim); text-align: center; padding: 40px; font-size: 14px; }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="site-logo">PREMIUM<span>VAULT</span></a>
        <button class="unlock-btn" onclick="showPinModal('unlock')">Unlock Gallery</button>
    </header>

    <div class="sub-nav">
        <div class="nav-tab active">Recent Updates</div>
        <div class="nav-tab">Trending</div>
        <div class="nav-tab">Models</div>
    </div>

    <main id="media-gallery" class="media-grid">
        <!-- Fake media will be injected here -->
    </main>

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

        // Fake media data
        const FAKE_THUMBS = [
            'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1542038783-0ad457d2f627?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1554080353-a576cf803bda?q=80&w=800&auto=format&fit=crop'
        ];

        // Load fake media on page load
        function loadFakeMedia() {
            const grid = document.getElementById('media-gallery');
            grid.innerHTML = '';
            // Generate 12 fake items
            for (let i = 0; i < 12; i++) {
                const card = document.createElement('div');
                card.className = 'bz-card';
                const fakeThumb = FAKE_THUMBS[i % FAKE_THUMBS.length];
                card.innerHTML = `
                    <div class="bz-thumb-container">
                        <img src="${fakeThumb}" class="bz-thumb">
                        <div style="position:absolute; bottom:8px; left:12px; font-size:10px; font-weight:900; color:white; background:rgba(0,0,0,0.6); padding:2px 5px;">EXCLUSIVE</div>
                    </div>
                    <div class="bz-info">
                        <div class="bz-title">Membership Content Scene #${i+1}</div>
                        <div class="bz-meta"><span>100% Rating</span><span>2026-05-05</span></div>
                    </div>
                `;
                grid.appendChild(card);
            }
        }

        // Show PIN modal
        function showPinModal(action) {
            currentPinAction = action;
            const modal = document.getElementById('pin-modal');
            const title = document.getElementById('pin-title');
            const error = document.getElementById('pin-error');
            error.style.display = 'none';
            document.getElementById('pin-input').value = '';

            if (action === 'unlock') title.textContent = 'Enter PIN to Unlock Gallery';

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

        // Load fake media on page load
        loadFakeMedia();
    </script>
</body>
</html>
