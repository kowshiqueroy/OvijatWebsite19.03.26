<?php
require_once 'auth.php';
$user_id = $_SESSION['user_id'] ?? 0;
if ($user_id != 1) { header("Location: index.php"); exit(); }
$verified = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_pin'])) {
    $pin = $_POST['pin'] ?? '';
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute(["pin_1"]);
    $hash = $stmt->fetchColumn();
    if ($hash && password_verify($pin, $hash)) {
        $verified = true;
        $_SESSION['vault_unlocked'] = time();
    } else $error = 'Invalid PIN';
}
if (isset($_SESSION['vault_unlocked']) && (time() - $_SESSION['vault_unlocked'] < 300)) $verified = true;
if (isset($_GET['lock'])) { unset($_SESSION['vault_unlocked']); header("Location: saved-images.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Premium Membership - Ultra HD</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --bz-orange: #ff8a00; --bz-bg: #0f0f0f; --bz-card: #1a1a1a; --bz-text: #ffffff; --bz-dim: #999999; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { margin: 0; padding: 0; background: var(--bz-bg); color: var(--bz-text); font-family: 'Roboto', sans-serif; }
        .auth-container { height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; background: #000; }
        .logo-placeholder { font-size: 42px; font-weight: 900; color: var(--bz-orange); margin-bottom: 30px; letter-spacing: -2px; }
        .logo-placeholder span { color: #fff; background: var(--bz-orange); padding: 0 5px; border-radius: 4px; margin-left: 2px; }
        .btn-verify { width: 100%; background: var(--bz-orange); color: #000; border: none; padding: 15px; border-radius: 4px; font-weight: 700; font-size: 16px; cursor: pointer; text-transform: uppercase; }
        .pin-input { width: 100%; background: #222; border: 1px solid #333; border-radius: 4px; padding: 15px; color: white; font-size: 20px; text-align: center; margin-bottom: 20px; outline: none; }
        
        header { height: 60px; background: #000; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid #222; }
        .site-logo { font-size: 24px; font-weight: 900; color: #fff; text-decoration: none; }
        .site-logo span { color: var(--bz-orange); }
        .sub-nav { display: flex; background: #151515; padding: 10px; gap: 15px; overflow-x: auto; scrollbar-width: none; border-bottom: 1px solid #222; }
        .nav-tab { white-space: nowrap; font-size: 13px; font-weight: 700; color: var(--bz-dim); text-transform: uppercase; }
        .nav-tab.active { color: var(--bz-orange); border-bottom: 2px solid var(--bz-orange); }

        .media-grid { display: grid; grid-template-columns: 1fr; gap: 1px; padding-bottom: 80px; }
        .bz-card { background: #000; position: relative; transition: opacity 0.5s; }
        .bz-card.hidden-fake { opacity: 0; pointer-events: none; height: 0; overflow: hidden; margin: 0; padding: 0; }
        .bz-thumb-container { width: 100%; aspect-ratio: 16/9; background: #111; overflow: hidden; position: relative; }
        .bz-thumb { width: 100%; height: 100%; object-fit: cover; filter: brightness(0.7); }
        .bz-info { padding: 12px 15px; }
        .bz-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; color: #eee; }
        .bz-meta { font-size: 11px; color: var(--bz-dim); display: flex; gap: 10px; }
        .bz-actions { padding: 10px 15px; display: flex; gap: 20px; border-bottom: 1px solid #111; }
        .bz-action { font-size: 12px; color: var(--bz-dim); font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 4px; }
        .real-trigger { color: #ff3b30; } /* Label is 'Delete' but it's the trigger */
        
        /* Secret Hotzone UI */
        .reveal-hotzone { display: none; background: #111; color: #333; padding: 10px; text-align: center; font-size: 10px; border: 1px dashed #222; margin: 0 15px 15px; border-radius: 4px; cursor: pointer; }
        .reveal-hotzone.active { display: block; animation: pulse 1s infinite; }
        @keyframes pulse { 0% { opacity: 0.5; } 50% { opacity: 1; } 100% { opacity: 0.5; } }

        /* Fake Delete Notification */
        #fake-toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.9); color: white; padding: 10px 20px; border-radius: 4px; font-size: 12px; z-index: 5000; display: none; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #000; z-index: 6000; flex-direction: column; }
        .modal-header { height: 50px; display: flex; align-items: center; padding: 0 15px; justify-content: space-between; border-bottom: 1px solid #222; }
        .modal-body { flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px; }
        .modal-body img, .modal-body video { max-width: 100%; max-height: 85vh; border-radius: 2px; }
        .fab-upload { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; background: var(--bz-orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #000; z-index: 2000; }
    </style>
</head>
<body onclick="handleGlobalClick(event)">

<?php if (!$verified): ?>
    <div class="auth-container">
        <div class="logo-placeholder">PREMIUM<span>ACCESS</span></div>
        <div class="auth-card"><form method="POST"><input type="password" name="pin" class="pin-input" placeholder="ACCESS PIN" maxlength="4" autofocus required inputmode="numeric"><button type="submit" name="verify_pin" class="btn-verify">Activate Membership</button></form></div>
    </div>
<?php else: ?>
    <header><a href="index.php" class="site-logo">PREMIUM<span>VAULT</span></a><div onclick="location.href='?lock=1'" style="cursor:pointer">🚪</div></header>
    <div class="sub-nav"><div class="nav-tab active">Recent Updates</div><div class="nav-tab">Trending</div><div class="nav-tab">Models</div></div>
    
    <div id="fake-toast">Content Permanently Removed</div>

    <main id="media-gallery" class="media-grid">
        <!-- Content will be injected -->
    </main>

    <div class="fab-upload" onclick="document.getElementById('vault-file').click()">+</div>
    <input type="file" id="vault-file" accept="image/*,video/*" style="display:none" onchange="handleVaultUpload(this)">

    <div id="media-modal" class="modal">
        <div class="modal-header"><span onclick="closeModal()">✕</span><span style="font-weight:900; color:var(--bz-orange);">ULTRA HD PREVIEW</span><span></span></div>
        <div class="modal-body" id="modal-body"></div>
    </div>

    <script>
        window.CSRF_TOKEN = '<?php echo $_SESSION['csrf_token']; ?>';
        const FAKE_THUMBS = [
            'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1542038783-0ad457d2f627?q=80&w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1554080353-a576cf803bda?q=80&w=800&auto=format&fit=crop'
        ];

        let revealPendingId = null;

        async function secureFetch(url, opts = {}) {
            const h = { 'X-CSRF-TOKEN': window.CSRF_TOKEN };
            if (opts.body && !(opts.body instanceof FormData)) h['Content-Type'] = 'application/json';
            opts.headers = { ...h, ...(opts.headers || {}) };
            return fetch(url, opts);
        }

        async function loadMedia() {
            const resp = await secureFetch('api.php?action=get_saved_images');
            const data = await resp.json();
            const g = document.getElementById('media-gallery');
            g.innerHTML = '';
            
            data.forEach((item, idx) => {
                const card = document.createElement('div');
                card.className = 'bz-card';
                card.id = `card-${item.id}`;
                
                // Show fake thumbnail
                const fakeThumb = FAKE_THUMBS[item.id % FAKE_THUMBS.length];

                card.innerHTML = `
                    <div class="bz-thumb-container" onclick="triggerFakeDelete(${item.id})">
                        <img src="${fakeThumb}" class="bz-thumb">
                        <div style="position:absolute; bottom:8px; left:12px; font-size:10px; font-weight:900; color:white; background:rgba(0,0,0,0.6); padding:2px 5px;">EXCLUSIVE</div>
                    </div>
                    <div class="bz-info">
                        <div class="bz-title">Membership Content Scene #${item.id}</div>
                        <div class="bz-meta"><span>100% Rating</span><span>${new Date(item.saved_at).toLocaleDateString()}</span></div>
                    </div>
                    <div class="bz-actions">
                        <div class="bz-action" onclick="triggerFakeDelete(${item.id})">View Scene</div>
                        <div class="bz-action real-trigger" onclick="initiateReveal(${item.id}, event)">Delete Item</div>
                        <div class="bz-action" style="margin-left:auto; color:#ff3b30;" onclick="realDelete(${item.id})">Burn</div>
                    </div>
                    <div class="reveal-hotzone" id="hotzone-${item.id}" onclick="finishReveal(${JSON.stringify(item).replace(/"/g, '&quot;')}, event)">
                        <span>Verifying cache integrity... (Touch to confirm)</span>
                    </div>
                `;
                g.appendChild(card);
            });
        }

        function triggerFakeDelete(id) {
            const card = document.getElementById(`card-${id}`);
            card.classList.add('hidden-fake');
            const toast = document.getElementById('fake-toast');
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 2000);
            // Bring it back after 30 seconds
            setTimeout(() => { card.classList.remove('hidden-fake'); }, 30000);
        }

        function initiateReveal(id, event) {
            event.stopPropagation();
            // Close any existing hotzones
            document.querySelectorAll('.reveal-hotzone').forEach(hz => hz.classList.remove('active'));
            
            const hz = document.getElementById(`hotzone-${id}`);
            hz.classList.add('active');
            revealPendingId = id;
        }

        function finishReveal(item, event) {
            event.stopPropagation();
            revealPendingId = null;
            document.getElementById(`hotzone-${item.id}`).classList.remove('active');
            
            const body = document.getElementById('modal-body');
            if (item.is_voice == 1) {
                body.innerHTML = `<audio controls autoplay src="${item.image_data}"></audio>`;
            } else if (item.is_video == 1) {
                body.innerHTML = `<video src="${item.image_data}" controls autoplay></video>`;
            } else {
                body.innerHTML = `<img src="${item.image_data}">`;
            }
            document.getElementById('media-modal').style.display = 'flex';
        }

        function handleGlobalClick(event) {
            // If a reveal is pending and we clicked anywhere that IS NOT the hotzone
            if (revealPendingId !== null) {
                const hz = document.getElementById(`hotzone-${revealPendingId}`);
                if (!hz.contains(event.target) && !event.target.classList.contains('real-trigger')) {
                    // LOCK IMMEDIATELY
                    location.href = '?lock=1';
                }
            }
        }

        function closeModal() {
            document.getElementById('media-modal').style.display = 'none';
            document.getElementById('modal-body').innerHTML = '';
        }

        async function realDelete(id) {
            if (!confirm('Permanently wipe this metadata?')) return;
            const r = await secureFetch('api.php?action=delete_saved_image', { method: 'POST', body: JSON.stringify({ image_id: id }) });
            if ((await r.json()).success) loadMedia();
        }

        function handleVaultUpload(el) {
            const file = el.files[0]; if (!file) return;
            const reader = new FileReader();
            reader.onload = async (e) => {
                const isV = file.type.startsWith('video');
                await secureFetch('api.php?action=save_image', { method: 'POST', body: JSON.stringify({ media_data: e.target.result, is_video: isV ? 1 : 0 }) });
                loadMedia();
            };
            reader.readAsDataURL(file);
        }

        loadMedia();

        setInterval(() => {
            const blob = new Blob([JSON.stringify({ is_typing: 0, in_theater: 0 })], { type: 'application/json' });
            navigator.sendBeacon(`api.php?action=update_status&_csrf=${window.CSRF_TOKEN}`, blob);
        }, 5000);
    </script>
<?php endif; ?>
</body>
</html>
