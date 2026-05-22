<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Gemini - Advanced AI Settings</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>✦</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gemini-blue: #4285f4;
            --gemini-bg: #0f0f10;
            --gemini-card: rgba(255, 255, 255, 0.04);
            --gemini-text: #e8eaed;
            --gemini-dim: #9aa0a6;
            --glass-border: rgba(255, 255, 255, 0.08);
            --panic-red: #ea4335;
            --tab-hover: rgba(255,255,255,0.06);
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
        body { 
            background: var(--gemini-bg); 
            color: var(--gemini-text); 
            font-family: 'Google Sans', sans-serif; 
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Fake Buy Page Styles */
        #fake-buy-page {
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .hero-badge {
            display: inline-block;
            background: rgba(66, 133, 244, 0.15);
            color: var(--gemini-blue);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .hero h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .pricing-card {
            background: var(--gemini-card);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 32px;
            text-align: center;
            transition: transform 0.3s;
        }
        .pricing-card.premium {
            border: 2px solid var(--gemini-blue);
            background: linear-gradient(135deg, rgba(66, 133, 244, 0.05) 0%, rgba(155, 114, 203, 0.05) 100%);
        }
        .buy-btn {
            background: var(--gemini-blue);
            color: #fff;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            margin-top: 24px;
        }

        /* Panic Buttons Overlay */
        .panic-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            z-index: 10000;
            display: none;
        }
        .panic-overlay.active { display: block; }
        .side-panic {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 80px;
            background: rgba(234, 67, 53, 0.15);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(234, 67, 53, 0.3);
            color: var(--panic-red);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            cursor: pointer;
            pointer-events: auto;
            font-size: 10px;
            writing-mode: vertical-rl;
            text-orientation: upright;
            letter-spacing: 2px;
            border-radius: 0 12px 12px 0;
            transition: all 0.2s;
        }
        .side-panic.right {
            right: 0;
            border-radius: 12px 0 0 12px;
        }
        .side-panic:active { background: var(--panic-red); color: #fff; }

        /* Lock Screen */
        .lock-screen {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: var(--gemini-bg);
            z-index: 9000;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .lock-screen.active { display: flex; }

        /* Vault Header */
        #vault-ui { display: none; }
        #vault-ui.active { display: block; }
        .vault-header {
            position: sticky; top: 0; z-index: 100;
            background: rgba(15, 15, 16, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
        }
        .vault-header-top {
            display: flex; align-items: center; padding: 12px 16px;
        }
        .vault-header-top h1 {
            font-size: 18px; font-weight: 500;
        }
        .vault-header-top h1 span { color: var(--gemini-blue); }
        .vault-header-actions {
            margin-left: auto; display: flex; align-items: center; gap: 10px;
        }
        .cache-btn {
            background: rgba(255, 255, 255, 0.05); color: var(--gemini-dim); border: 1px solid var(--glass-border);
            padding: 6px 12px; border-radius: 20px; font-size: 11px;
            cursor: pointer; transition: 0.15s;
        }
        .cache-btn:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .hide-btn {
            background: rgba(234, 67, 53, 0.1); color: var(--panic-red); border: 1px solid rgba(234, 67, 53, 0.2);
            padding: 6px 14px; border-radius: 20px; font-size: 12px;
            font-weight: 500; cursor: pointer; transition: 0.15s;
        }
        .hide-btn:hover { background: var(--panic-red); color: #fff; }

        /* Progress Ring in Grid */
        .progress-ring-container {
            position: absolute; inset: 0;
            display: none; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(2px);
            z-index: 5;
            border-radius: 12px;
        }
        .media-item.loading .progress-ring-container { display: flex; }
        .progress-ring {
            width: 44px; height: 44px;
            transform: rotate(-90deg);
        }
        .progress-ring circle {
            fill: none; stroke-width: 4; stroke-linecap: round;
            stroke: rgba(255,255,255,0.1);
        }
        .progress-ring .progress-bar {
            stroke: var(--gemini-blue);
            stroke-dasharray: 113.1;
            stroke-dashoffset: 113.1;
            transition: stroke-dashoffset 0.1s linear;
        }
        .progress-text {
            position: absolute; font-size: 10px; font-weight: 700; color: #fff;
            font-family: monospace;
        }

        .media-item.cached .media-item-placeholder { filter: brightness(0.6); }
        .media-item.cached::after {
            content: "READY"; position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%); font-size: 10px; font-weight: 900;
            color: #fff; letter-spacing: 1.5px; text-shadow: 0 0 10px rgba(0,0,0,0.5);
            pointer-events: none; opacity: 0.8;
        }

        #media-stats { color: var(--gemini-dim); font-size: 12px; }

        /* Media Tabs */
        .media-tabs {
            display: flex; gap: 8px; padding: 0 16px 12px;
            overflow-x: auto; -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .media-tabs::-webkit-scrollbar { display: none; }
        .tab {
            flex-shrink: 0; padding: 8px 18px; border-radius: 20px;
            border: 1px solid var(--glass-border); background: rgba(255,255,255,0.03);
            color: var(--gemini-dim); font-size: 13px; cursor: pointer;
            font-family: inherit; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tab:hover { background: var(--tab-hover); color: #fff; }
        .tab.active {
            background: var(--gemini-blue); color: #fff; border-color: var(--gemini-blue);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }
        
        /* Media Grid Refined */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            padding: 16px;
        }
        .media-item { 
            aspect-ratio: 1; 
            background: #1a1a1c; 
            overflow: hidden; 
            cursor: pointer; 
            position: relative; 
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            transition: transform 0.2s, border-color 0.2s;
        }
        .media-item:hover { transform: scale(1.02); border-color: rgba(66, 133, 244, 0.4); }
        .media-item img, .media-item video { width: 100%; height: 100%; object-fit: cover; }
        .media-item-placeholder {
            height: 100%; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #1a1a1c 0%, #0f0f10 100%); 
            font-size: 32px;
            transition: filter 0.3s;
        }
        .media-item-overlay {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.85));
            padding: 10px 12px; color: #fff;
            display: flex; flex-direction: column; gap: 2px;
        }
        .media-info-name { font-size: 11px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .media-info-meta { font-size: 9px; color: rgba(255,255,255,0.6); display: flex; justify-content: space-between; }

        .cache-badge {
            position: absolute; top: 8px; right: 8px; 
            width: 8px; height: 8px; border-radius: 50%;
            background: #34a853; box-shadow: 0 0 6px #34a853;
            display: none;
            z-index: 6;
        }
        .media-item.cached .cache-badge { display: block; }

        /* Viewer Modal - Centered Panel */
        .viewer-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 8000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .viewer-modal.active { display: flex; }
        .viewer-backdrop {
            position: absolute; inset: 0; background: rgba(0,0,0,0.8);
            backdrop-filter: blur(4px);
        }
        .viewer-panel {
            position: relative;
            background: var(--gemini-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
        }
        .viewer-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 18px; border-bottom: 1px solid var(--glass-border);
            flex-shrink: 0;
        }
        .viewer-panel-header button {
            background: none; border: none; cursor: pointer; font-size: 14px; font-weight: 600;
            font-family: inherit;
        }
        .viewer-panel-header .close-btn { color: #fff; font-size: 22px; padding: 0 4px; }
        .viewer-panel-header .delete-btn { color: var(--panic-red); }
        #viewer-counter { font-family: monospace; font-size: 13px; color: var(--gemini-dim); }

        .viewer-media-area {
            flex: 1; display: flex; align-items: center; justify-content: center;
            position: relative; min-height: 200px; background: #000;
        }
        .viewer-media-area img {
            max-width: 100%; max-height: 65vh; object-fit: contain; display: block;
        }
        .viewer-media-area video {
            max-width: 100%; max-height: 65vh; object-fit: contain;
            background: #000;
        }
        .viewer-media-area audio {
            width: 85%; max-width: 400px;
        }
        .viewer-media-area > * { transition: opacity 0.25s ease-in-out; }

        .viewer-progress-container {
            position: absolute; top: 0; left: 0; width: 100%; height: 3px;
            background: rgba(255,255,255,0.1);
        }
        .viewer-progress-bar {
            height: 100%; width: 0%;
            background: var(--gemini-blue);
            transition: width 0.2s ease-out;
        }
        .viewer-loader {
            position: absolute;
            width: 36px; height: 36px;
            display: none;
        }
        .viewer-loader svg {
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Details Panel */
        .viewer-details {
            padding: 12px 18px;
            border-top: 1px solid var(--glass-border);
            flex-shrink: 0;
        }
        .detail-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px;
        }
        .detail-label { font-size: 11px; color: var(--gemini-dim); text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-value { font-size: 12px; color: var(--gemini-text); word-break: break-all; }

        /* Nav */
        .viewer-nav {
            display: flex; align-items: center; justify-content: center;
            gap: 30px; padding: 10px 0 14px; flex-shrink: 0;
        }
        .viewer-nav button {
            background: var(--gemini-card); border: 1px solid var(--glass-border);
            color: #fff; width: 44px; height: 44px; border-radius: 50%;
            font-size: 22px; cursor: pointer; transition: 0.15s;
        }
        .viewer-nav button:active { background: rgba(255,255,255,0.1); }

        /* Camouflage */
        #camouflage-ui {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: var(--gemini-bg);
            z-index: 20000;
            padding: 20px;
        }
        #camouflage-ui.active { display: block; }
        
        .hidden { display: none !important; }
    </style>
</head>
<body>

    <!-- Panic Buttons - Always Present -->
    <div class="panic-overlay" id="panic-overlay">
        <div class="side-panic left" onclick="handlePanic()">PANIC</div>
        <div class="side-panic right" onclick="handlePanic()">PANIC</div>
    </div>

    <!-- Fake Buy Page (Landing) -->
    <div id="fake-buy-page">
        <header style="height: 60px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px;">
            <a href="index.php" style="text-decoration: none; color: inherit; font-size: 20px; font-weight: 500;">
                <span style="color: var(--gemini-blue);">✦</span> Gemini
            </a>
            <button style="background: none; border: 1px solid var(--glass-border); color: #fff; padding: 8px 16px; border-radius: 20px; font-size: 13px;">Sign In</button>
        </header>

        <div class="hero" style="text-align: center; margin-bottom: 60px;">
            <div class="hero-badge">Gemini Pro AI</div>
            <h1>Unlock the next generation of AI</h1>
            <p style="color: var(--gemini-dim); max-width: 600px; margin: 0 auto;">Get priority access to our most capable models, advanced image generation, and dedicated workspace storage.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
            <div class="pricing-card">
                <div style="font-size: 14px; color: var(--gemini-dim); margin-bottom: 10px;">Starter</div>
                <div style="font-size: 32px; font-weight: 700;">$0 <span style="font-size: 16px; font-weight: 400; color: var(--gemini-dim);">/mo</span></div>
                <ul style="list-style: none; margin-top: 20px; text-align: left; color: var(--gemini-dim); font-size: 14px;">
                    <li style="margin-bottom: 12px;">✓ Standard AI access</li>
                    <li style="margin-bottom: 12px;">✓ Basic web search</li>
                    <li>✓ 5GB shared storage</li>
                </ul>
                <button class="buy-btn" style="background: var(--gemini-card); color: #fff; border: 1px solid var(--glass-border);">Current Plan</button>
            </div>

            <div class="pricing-card premium">
                <div style="font-size: 14px; color: var(--gemini-blue); margin-bottom: 10px; font-weight: 700;">Most Popular</div>
                <div style="font-size: 32px; font-weight: 700;">$19.99 <span style="font-size: 16px; font-weight: 400; color: var(--gemini-dim);">/mo</span></div>
                <ul style="list-style: none; margin-top: 20px; text-align: left; color: var(--gemini-dim); font-size: 14px;">
                    <li style="margin-bottom: 12px; color: #fff;">✓ Gemini Ultra 1.0 access</li>
                    <li style="margin-bottom: 12px; color: #fff;">✓ Priority during peak times</li>
                    <li style="margin-bottom: 12px; color: #fff;">✓ Advanced image generation</li>
                    <li style="color: #fff;">✓ 2TB private vault storage</li>
                </ul>
                <button class="buy-btn" onclick="startUnlock()">Upgrade to Pro</button>
            </div>
        </div>
    </div>

    <!-- Lock Screen (PIN) -->
    <div id="lock-screen" class="lock-screen">
        <div style="font-size: 48px; margin-bottom: 20px;">💎</div>
        <h2 style="margin-bottom: 10px;">Security Verification</h2>
        <p style="color: var(--gemini-dim); font-size: 14px; margin-bottom: 40px;">Enter your 4-digit PIN to continue</p>
        
        <div style="display: flex; gap: 15px; margin-bottom: 40px;">
            <div class="pin-dot"></div><div class="pin-dot"></div><div class="pin-dot"></div><div class="pin-dot"></div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 280px;">
            <button class="num-btn" onclick="appendPin('1')">1</button><button class="num-btn" onclick="appendPin('2')">2</button><button class="num-btn" onclick="appendPin('3')">3</button>
            <button class="num-btn" onclick="appendPin('4')">4</button><button class="num-btn" onclick="appendPin('5')">5</button><button class="num-btn" onclick="appendPin('6')">6</button>
            <button class="num-btn" onclick="appendPin('7')">7</button><button class="num-btn" onclick="appendPin('8')">8</button><button class="num-btn" onclick="appendPin('9')">9</button>
            <button class="num-btn" style="background:transparent; border:none;" onclick="clearPin()">C</button><button class="num-btn" onclick="appendPin('0')">0</button><button class="num-btn" style="background:transparent; border:none;" onclick="handlePanic()">✕</button>
        </div>
        <style>
            .pin-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--gemini-dim); }
            .pin-dot.filled { background: var(--gemini-blue); border-color: var(--gemini-blue); box-shadow: 0 0 10px rgba(66, 133, 244, 0.5); }
            .num-btn { width: 70px; height: 70px; border-radius: 50%; background: var(--gemini-card); border: 1px solid var(--glass-border); color: #fff; font-size: 24px; cursor: pointer; }
        </style>
    </div>

    <!-- Actual Vault UI -->
    <div id="vault-ui">
        <div class="vault-header">
            <div class="vault-header-top">
                <h1>Gemini <span>Vault</span></h1>
                <div class="vault-header-actions">
                    <button class="cache-btn" onclick="clearVaultCache()">Clear Cache</button>
                    <button class="hide-btn" onclick="handlePanic()">✕ Hide</button>
                    <span id="media-stats">0 items</span>
                </div>
            </div>
            <div class="media-tabs" id="media-tabs">
                <button class="tab active" data-filter="recording">Recordings</button>
                <button class="tab" data-filter="video">Videos</button>
                <button class="tab" data-filter="image">Photos</button>
                <button class="tab" data-filter="voice">Voices</button>
                <button class="tab" data-filter="orphans" style="border-color:rgba(234,67,53,0.3); color:var(--panic-red);">Orphans</button>
                <button class="tab" data-filter="missing" style="border-color:rgba(234,67,53,0.3); color:var(--panic-red);">Missing</button>
            </div>
        </div>
        <div class="media-grid" id="media-grid"></div>
    </div>

    <!-- Viewer Modal (Centered Panel) -->
    <div id="viewer-modal" class="viewer-modal">
        <div class="viewer-backdrop" onclick="closeViewer()"></div>
        <div class="viewer-panel">
            <div class="viewer-panel-header">
                <button class="close-btn" onclick="closeViewer()">✕</button>
                <div id="viewer-counter">0 / 0</div>
                <button class="delete-btn" onclick="deleteCurrentMedia()">DELETE</button>
            </div>
            <div class="viewer-media-area" id="viewer-media-area">
                <div class="viewer-progress-container"><div class="viewer-progress-bar" id="viewer-progress"></div></div>
                <div class="viewer-loader" id="viewer-loader">
                    <svg width="36" height="36" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="22" cy="22" r="20" stroke="rgba(255,255,255,0.1)" stroke-width="4"/>
                        <path d="M22 2C33.0457 2 42 11.0457 42 22" stroke="var(--gemini-blue)" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
            <div class="viewer-details" id="viewer-details">
                <div class="detail-grid">
                    <div><div class="detail-label">Name</div><div class="detail-value" id="detail-name">-</div></div>
                    <div><div class="detail-label">Type</div><div class="detail-value" id="detail-type">-</div></div>
                    <div><div class="detail-label">Size</div><div class="detail-value" id="detail-size">-</div></div>
                    <div><div class="detail-label">Saved</div><div class="detail-value" id="detail-date">-</div></div>
                </div>
            </div>
            <div class="viewer-nav">
                <button onclick="prevMedia()">‹</button>
                <button onclick="nextMedia()">›</button>
            </div>
        </div>
    </div>

    <!-- Camouflage UI -->
    <div id="camouflage-ui">
        <div style="background: var(--gemini-card); border-radius: 24px; padding: 12px 20px; color: var(--gemini-dim); font-size: 14px; margin-bottom: 24px;">🔍 Search Workspace</div>
        <div style="color: var(--gemini-dim); font-size: 13px;">
            <p style="margin-bottom:15px;">Recent Documents:</p>
            <div style="background:var(--gemini-card); padding:15px; border-radius:12px; margin-bottom:10px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:24px;">📄</span>
                <div><div style="color:#fff; font-weight:500;">API_Refactor_Notes.pdf</div><div style="font-size:11px;">Modified 5m ago</div></div>
            </div>
        </div>
    </div>

    <script>
        const CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        let galleryMedia = [];
        let currentMediaIndex = 0;
        let currentPin = "";
        let isVaultActive = false;
        let currentFilter = 'recording';

        const mediaCache = new Map();
        const CACHE_NAME = 'gemini-vault-v1';
        const EXPIRY_DAYS = 7;

        async function getCachedMedia(imageData) {
            try {
                const cache = await caches.open(CACHE_NAME);
                const response = await cache.match(imageData);
                if (response) {
                    const lastView = localStorage.getItem('vault_view_' + imageData);
                    if (lastView) {
                        const age = (Date.now() - parseInt(lastView)) / (1000 * 60 * 60 * 24);
                        if (age > EXPIRY_DAYS) {
                            await cache.delete(imageData);
                            localStorage.removeItem('vault_view_' + imageData);
                            return null;
                        }
                    }
                    const blob = await response.blob();
                    localStorage.setItem('vault_view_' + imageData, Date.now());
                    return { url: URL.createObjectURL(blob), size: blob.size };
                }
            } catch (e) { console.error("Cache Read Error", e); }
            return null;
        }

        async function saveToCache(imageData, blob) {
            try {
                const cache = await caches.open(CACHE_NAME);
                await cache.put(imageData, new Response(blob));
                localStorage.setItem('vault_view_' + imageData, Date.now());
            } catch (e) { console.error("Cache Write Error", e); }
        }

        async function cleanupVaultCacheOld() {
            try {
                const cache = await caches.open(CACHE_NAME);
                const keys = await cache.keys();
                const now = Date.now();
                for (const request of keys) {
                    const imageData = new URL(request.url).pathname.split('/').pop(); 
                    // Note: If we use strings as keys, request.url might be a full URL.
                    // Let's use a more robust way to match keys in localStorage.
                }
                // Simpler: iterate localStorage
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.startsWith('vault_view_')) {
                        const lastView = parseInt(localStorage.getItem(key));
                        if (now - lastView > EXPIRY_DAYS * 24 * 60 * 60 * 1000) {
                            const imageData = key.replace('vault_view_', '');
                            await cache.delete(imageData);
                            localStorage.removeItem(key);
                        }
                    }
                }
            } catch (e) {}
        }

        function getFiltered() {
            if (currentFilter === 'recording') return galleryMedia.filter(m => m.image_data.includes('recording_'));
            if (currentFilter === 'image') return galleryMedia.filter(m => m.is_voice == 0 && m.is_video == 0);
            if (currentFilter === 'video') return galleryMedia.filter(m => m.is_video == 1 && !m.image_data.includes('recording_'));
            if (currentFilter === 'voice') return galleryMedia.filter(m => m.is_voice == 1);
            return galleryMedia;
        }

        async function clearVaultCache() {
            mediaCache.forEach(val => URL.revokeObjectURL(val.url));
            mediaCache.clear();
            const cache = await caches.open(CACHE_NAME);
            const keys = await cache.keys();
            for (const key of keys) await cache.delete(key);
            for (let i = 0; i < localStorage.length; i++) {
                const k = localStorage.key(i);
                if (k && k.startsWith('vault_view_')) { localStorage.removeItem(k); i--; }
            }
            switchTab(currentFilter);
            alert("Cache cleared");
        }

        function startUnlock() {
            document.getElementById('fake-buy-page').style.display = 'none';
            document.getElementById('lock-screen').classList.add('active');
        }

        function appendPin(num) {
            if (currentPin.length >= 4) return;
            currentPin += num;
            updatePinDots();
            if (currentPin.length === 4) verifyPin();
        }

        function clearPin() { currentPin = ""; updatePinDots(); }

        function updatePinDots() {
            const dots = document.querySelectorAll('.pin-dot');
            dots.forEach((dot, i) => i < currentPin.length ? dot.classList.add('filled') : dot.classList.remove('filled'));
        }

        async function verifyPin() {
            try {
                const resp = await fetch('api.php?action=verify_pin', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pin: currentPin })
                });
                const data = await resp.json();
                if (data.success) {
                    isVaultActive = true;
                    document.getElementById('lock-screen').classList.remove('active');
                    document.getElementById('vault-ui').classList.add('active');
                    document.getElementById('panic-overlay').classList.add('active');
                    loadGallery();
                } else { clearPin(); }
            } catch (e) { clearPin(); }
        }

        let auditData = null;

        async function switchTab(filter) {
            currentFilter = filter;
            document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.filter === filter));
            const grid = document.getElementById('media-grid');
            grid.innerHTML = "";

            if (filter === 'orphans' || filter === 'missing') {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--gemini-dim);">Scanning storage...</div>';
                try {
                    const resp = await fetch('api.php?action=vault_audit', { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                    auditData = await resp.json();
                    if (auditData.success) {
                        renderAuditTab(filter);
                    } else {
                        grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--panic-red);">Scan failed: ${auditData.error || 'Unknown error'}</div>`;
                    }
                } catch (e) { grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:40px; color:var(--panic-red);">Scan failed.</div>'; }
                return;
            }

            const filtered = getFiltered();
            filtered.forEach((item, idx) => {
                const realIdx = galleryMedia.indexOf(item);
                const div = document.createElement('div');
                div.className = "media-item";
                
                // Use a unique ID that includes type to avoid collisions
                const typeKey = item.is_voice == 1 ? 'voi' : (item.is_video == 1 ? 'vid' : 'img');
                const uniqueId = `media-item-${typeKey}-${item.id}`;
                div.id = uniqueId;

                getCachedMedia(item.image_data).then(cached => {
                    if (cached) {
                        div.classList.add('cached');
                        mediaCache.set(item.image_data, cached);
                        if (!item.is_voice && !item.is_video) {
                            const placeholder = div.querySelector('.media-item-placeholder');
                            if (placeholder) placeholder.innerHTML = `<img src="${cached.url}" loading="lazy">`;
                        }
                    }
                });

                div.onclick = () => handleMediaClick(item, realIdx);
                
                const dateLabel = item.saved_at ? formatSavedDate(item.saved_at) : '';
                const fileName = item.image_data.split('/').pop();
                
                let icon = '📄';
                if (item.is_voice == 1) icon = '🎤';
                else if (item.image_data.includes('recording_')) icon = '✦';
                else if (item.is_video == 1) icon = '▶';
                else icon = '🖼️';

                let thumb = `<div class="media-item-placeholder">${icon}</div>`;

                div.innerHTML = `
                    ${thumb}
                    <div class="progress-ring-container">
                        <svg class="progress-ring">
                            <circle cx="22" cy="22" r="18"/>
                            <circle class="progress-bar" cx="22" cy="22" r="18"/>
                        </svg>
                        <div class="progress-text">0%</div>
                    </div>
                    <div class="cache-badge"></div>
                    <div class="media-item-overlay">
                        <div class="media-info-name">${fileName}</div>
                        <div class="media-info-meta">
                            <span>${getMediaType(item)}</span>
                            <span>${dateLabel}</span>
                        </div>
                    </div>
                `;
                grid.appendChild(div);
            });

            const total = galleryMedia.length;
            document.getElementById('media-stats').textContent = `${total} items`;
        }

        function renderAuditTab(filter) {
            const grid = document.getElementById('media-grid');
            grid.innerHTML = "";
            const items = filter === 'orphans' ? auditData.orphans : auditData.missing;
            const count = filter === 'orphans' ? auditData.orphan_count : auditData.missing_count;

            if (count === 0) {
                grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding:80px; color:var(--gemini-dim);">
                    <div style="font-size:48px; margin-bottom:20px;">✅</div>
                    <p>No ${filter} items found.</p>
                </div>`;
                return;
            }

            // Header for the list
            const header = document.createElement('div');
            header.style.cssText = 'grid-column: 1/-1; background:rgba(234,67,53,0.1); border:1px solid rgba(234,67,53,0.2); padding:15px; border-radius:12px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;';
            header.innerHTML = `
                <div>
                    <div style="font-weight:700; color:var(--panic-red);">${count} ${filter.toUpperCase()} FOUND</div>
                    <div style="font-size:11px; color:var(--gemini-dim);">${filter === 'orphans' ? 'Files on disk with no DB record' : 'DB records with no file on disk'}</div>
                </div>
                <button class="hide-btn" onclick="cleanupVault('${filter}')">CLEAN ALL</button>
            `;
            grid.appendChild(header);

            items.forEach(item => {
                const div = document.createElement('div');
                div.className = "media-item";
                div.style.height = "auto";
                div.style.aspectRatio = "unset";
                div.style.padding = "12px";
                div.style.display = "flex";
                div.style.flexDirection = "column";
                div.style.gap = "8px";
                div.style.border = "1px solid rgba(255,255,255,0.05)";
                
                const name = filter === 'orphans' ? item.name : item.image_data.split('/').pop();
                const sub = filter === 'orphans' ? formatFileSize(item.size) : `ID: ${item.id}`;

                div.innerHTML = `
                    <div style="font-size:11px; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#fff;">${name}</div>
                    <div style="font-size:10px; color:var(--gemini-dim);">${sub}</div>
                    <div style="margin-top:auto; font-size:9px; color:var(--panic-red); font-weight:700;">${filter === 'orphans' ? 'ORPHANED' : 'MISSING'}</div>
                `;
                grid.appendChild(div);
            });
        }

        async function handleMediaClick(item, realIdx) {
            if (mediaCache.has(item.image_data)) {
                openViewer(realIdx);
                return;
            }
            // Load if not cached
            await preloadSingleMedia(item, realIdx);
        }

        async function preloadSingleMedia(item, realIdx) {
            const typeKey = item.is_voice == 1 ? 'voi' : (item.is_video == 1 ? 'vid' : 'img');
            const uniqueId = `media-item-${typeKey}-${item.id}`;
            const el = document.getElementById(uniqueId);
            
            if (!el || el.classList.contains('loading')) return;
            
            // Check cache again just in case
            const cached = await getCachedMedia(item.image_data);
            if (cached) {
                mediaCache.set(item.image_data, cached);
                el.classList.add('cached');
                if (!item.is_voice && !item.is_video) {
                    const placeholder = el.querySelector('.media-item-placeholder');
                    if (placeholder) placeholder.innerHTML = `<img src="${cached.url}" style="width:100%;height:100%;object-fit:cover;">`;
                }
                return;
            }

            el.classList.add('loading');
            const ring = el.querySelector('.progress-bar');
            const text = el.querySelector('.progress-text');
            const circumference = 2 * Math.PI * 18;
            
            // Init ring
            if (ring) ring.style.strokeDashoffset = circumference;

            try {
                // Ensure we use the view.php proxy for authorized access
                const fileName = item.image_data.split('/').pop();
                const proxyUrl = `view.php?dir=premium_vault&file=${fileName}`;
                
                const response = await fetch(proxyUrl);
                if (!response.ok) throw new Error('Network error');

                const contentLength = response.headers.get('content-length');
                const total = contentLength ? parseInt(contentLength, 10) : 0;
                let loaded = 0;

                const reader = response.body.getReader();
                const chunks = [];

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    chunks.push(value);
                    loaded += value.length;
                    
                    if (total > 0) {
                        const pct = Math.round((loaded / total) * 100);
                        const offset = circumference - (pct / 100) * circumference;
                        requestAnimationFrame(() => {
                            if (ring) ring.style.strokeDashoffset = offset;
                            if (text) text.textContent = pct + '%';
                        });
                    } else {
                        if (text) text.textContent = "...";
                    }
                }

                const blob = new Blob(chunks);
                const url = URL.createObjectURL(blob);
                mediaCache.set(item.image_data, { url, size: blob.size });
                await saveToCache(item.image_data, blob);
                
                el.classList.remove('loading');
                el.classList.add('cached');
                
                // If it's a photo, we can show the thumb now
                if (!item.is_voice && !item.is_video) {
                    const placeholder = el.querySelector('.media-item-placeholder');
                    if (placeholder) placeholder.innerHTML = `<img src="${url}" style="width:100%;height:100%;object-fit:cover;">`;
                }
            } catch (err) {
                console.error(err);
                el.classList.remove('loading');
                alert("Failed to load media");
            }
        }

        async function loadGallery() {
            cleanupVaultCacheOld();
            const resp = await fetch('api.php?action=get_saved_images');
            galleryMedia = await resp.json();
            switchTab(currentFilter);

            // Tab click handlers
            document.querySelectorAll('.tab').forEach(tab => {
                tab.onclick = () => switchTab(tab.dataset.filter);
            });
        }

        function openViewer(idx) {
            currentMediaIndex = idx;
            document.getElementById('viewer-modal').classList.add('active');
            showMedia(idx);
        }

        function getMediaType(item) {
            if (item.image_data.includes('recording_')) return 'Call Rec';
            if (item.is_voice == 1) return 'Voice';
            if (item.is_video == 1) return 'Video';
            return 'Photo';
        }

        function formatFileSize(bytes) {
            if (!bytes || bytes <= 0) return '-';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function formatSavedDate(dateStr) {
            if (!dateStr) return '-';
            try {
                // dateStr is now ISO8601 UTC (e.g., 2026-05-22T12:00:00Z)
                const d = new Date(dateStr);
                const now = new Date();
                
                const diff = (now.getTime() - d.getTime()) / 1000;
                if (diff < 60) return 'Just now';
                if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
                if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
                
                return d.toLocaleDateString(undefined, { 
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric'
                });
            } catch(e) { return dateStr; }
        }

        function updateDetails(item, fileSize) {
            document.getElementById('detail-name').textContent = item.image_data.split('/').pop() || '-';
            document.getElementById('detail-type').textContent = getMediaType(item);
            document.getElementById('detail-size').textContent = formatFileSize(fileSize);
            
            if (item.saved_at) {
                try {
                    const d = new Date(item.saved_at);
                    document.getElementById('detail-date').textContent = d.toLocaleString("en-GB", {
                        timeZone: "Asia/Dhaka",
                        hour12: true,
                        year: 'numeric', month: 'short', day: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    });
                } catch(e) { document.getElementById('detail-date').textContent = item.saved_at; }
            } else {
                document.getElementById('detail-date').textContent = '-';
            }
        }

        function createMediaElement(item, src) {
            let el;
            if (item.is_voice == 1) {
                el = document.createElement('audio');
                el.controls = true; el.autoplay = true;
                el.muted = false; // Allow audio
            } else if (item.is_video == 1) {
                el = document.createElement('video');
                el.controls = true; el.autoplay = true;
                el.playsinline = true;
                el.setAttribute('webkit-playsinline', 'true');
                el.setAttribute('x5-playsinline', 'true');
                el.setAttribute('playsinline', 'true');
                el.disablePictureInPicture = true;
                el.controlsList = 'nodownload noremoteplayback';
                el.preload = 'auto';
                el.muted = false; // Allow audio if user clicked
            } else {
                el = document.createElement('img');
            }
            el.src = src;
            el.style.opacity = '0';

            // Handle play promise to avoid console errors
            if (el.tagName === 'VIDEO' || el.tagName === 'AUDIO') {
                const playPromise = el.play();
                if (playPromise !== undefined) {
                    playPromise.catch(e => {
                        console.warn("[Media_Stream] Autoplay policy:", e.name);
                        el.muted = true;
                        el.play();
                    });
                }
            }
            return el;
        }

        function renderMedia(item, blobUrl, fileSize) {
            const area = document.getElementById('viewer-media-area');
            const old = area.querySelector('img, video, audio');

            const el = createMediaElement(item, blobUrl);
            area.appendChild(el);

            if (old) old.style.opacity = '0';
            requestAnimationFrame(() => { el.style.opacity = '1'; });

            if (old) setTimeout(() => { if (old.parentNode) old.remove(); }, 300);

            const filtered = getFiltered();
            const pos = filtered.indexOf(item);
            document.getElementById('viewer-counter').textContent = pos !== -1 ? `${pos + 1} / ${filtered.length}` : '- / -';

            updateDetails(item, fileSize);
        }

        async function showMedia(idx) {
            const item = galleryMedia[idx];
            const loader = document.getElementById('viewer-loader');
            const progress = document.getElementById('viewer-progress');

            const filtered = getFiltered();
            const pos = filtered.indexOf(item);
            document.getElementById('viewer-counter').textContent = pos !== -1 ? `${pos + 1} / ${filtered.length}` : '- / -';

            updateDetails(item, null);

            let cached = mediaCache.get(item.image_data);
            if (!cached) cached = await getCachedMedia(item.image_data);

            if (cached) {
                mediaCache.set(item.image_data, cached);
                loader.style.display = 'none';
                progress.style.width = '100%';
                renderMedia(item, cached.url, cached.size);
                return;
            }

            loader.style.display = 'block';
            progress.style.width = '0%';

            try {
                // Ensure we use the view.php proxy for authorized access
                const fileName = item.image_data.split('/').pop();
                const proxyUrl = `view.php?dir=premium_vault&file=${fileName}`;
                
                const response = await fetch(proxyUrl);
                if (!response.ok) throw new Error('Network error');

                const contentLength = response.headers.get('content-length');
                const total = contentLength ? parseInt(contentLength, 10) : 0;
                let loaded = 0;

                if (!contentLength) {
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    mediaCache.set(item.image_data, { url, size: blob.size });
                    loader.style.display = 'none';
                    renderMedia(item, url, blob.size);
                    return;
                }

                const reader = response.body.getReader();
                const chunks = [];

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    chunks.push(value);
                    loaded += value.length;
                    requestAnimationFrame(() => {
                        progress.style.width = `${Math.round((loaded / total) * 100)}%`;
                    });
                }

                const blob = new Blob(chunks);
                const url = URL.createObjectURL(blob);
                mediaCache.set(item.image_data, { url, size: blob.size });
                loader.style.display = 'none';
                renderMedia(item, url, blob.size);

            } catch (err) {
                console.error(err);
                loader.style.display = 'none';
            }
        }

        function closeViewer() { 
            document.getElementById('viewer-modal').classList.remove('active'); 
            document.getElementById('viewer-media-area').innerHTML = `
                <div class="viewer-progress-container"><div class="viewer-progress-bar" id="viewer-progress"></div></div>
                <div class="viewer-loader" id="viewer-loader">
                    <svg width="36" height="36" viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="22" cy="22" r="20" stroke="rgba(255,255,255,0.1)" stroke-width="4"/>
                        <path d="M22 2C33.0457 2 42 11.0457 42 22" stroke="var(--gemini-blue)" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                </div>`;
        }

        function navigateMedia(delta) {
            const filtered = getFiltered();
            if (filtered.length === 0) return;
            const item = galleryMedia[currentMediaIndex];
            let pos = filtered.indexOf(item);
            pos = (pos + delta + filtered.length) % filtered.length;
            currentMediaIndex = galleryMedia.indexOf(filtered[pos]);
            showMedia(currentMediaIndex);
        }

        function nextMedia() { navigateMedia(1); }
        function prevMedia() { navigateMedia(-1); }

        async function deleteCurrentMedia() {
            if (!confirm("Delete permanently?")) return;
            const item = galleryMedia[currentMediaIndex];
            const resp = await fetch('api.php?action=delete_saved_image', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
                body: JSON.stringify({ image_id: item.id, image_path: item.image_data })
            });
            if ((await resp.json()).success) {
                mediaCache.delete(item.image_data);
                galleryMedia.splice(currentMediaIndex, 1);
                if (galleryMedia.length === 0) { closeViewer(); loadGallery(); }
                else {
                    currentMediaIndex = Math.min(currentMediaIndex, galleryMedia.length - 1);
                    showMedia(currentMediaIndex);
                    loadGallery();
                }
            }
        }

        async function cleanupVault(type) {
            if (!confirm(`Permanently clean all ${type}?`)) return;
            
            try {
                const resp = await fetch('api.php?action=vault_cleanup', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type })
                });
                const data = await resp.json();
                if (data.success) {
                    alert(`Cleaned ${data.count} items.`);
                    // Refresh audit data and view
                    const auditResp = await fetch('api.php?action=vault_audit', { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } });
                    auditData = await auditResp.json();
                    renderAuditTab(type);
                    if (type === 'missing') loadGallery();
                }
            } catch (e) { console.error(e); }
        }

        function handlePanic() {
            mediaCache.clear();
            document.body.innerHTML = "";
            document.body.style.background = "#0f0f10";
            location.href = 'index.php';
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === "Escape" || e.key === "`") {
                if (document.getElementById('viewer-modal').classList.contains('active')) {
                    closeViewer();
                } else if (isVaultActive) {
                    handlePanic();
                }
                return;
            }
            if (document.getElementById('viewer-modal').classList.contains('active')) {
                if (e.key === "ArrowRight") nextMedia();
                if (e.key === "ArrowLeft") prevMedia();
            }
        });
    </script>
</body>
</html>
