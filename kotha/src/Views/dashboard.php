<?php $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Kotha Secure Messenger</title>
    <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/public/img/icon.svg">
    <link rel="shortcut icon" href="<?= $baseUrl ?>/public/img/icon.svg">
    <meta name="theme-color" content="#00f2fe">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/style.css?v=4">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/css/logo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        
        <!-- Sidebar Layout -->
        <?php include BASE_DIR . '/src/Views/layout/sidebar.php'; ?>

        <!-- Right Panel (Standby Placeholder) -->
        <div class="main-viewport">
            <div class="chat-placeholder">
                <div class="placeholder-logo-wrapper">
                    <!-- SVG icon mark — scales crispy at any DPI -->
                    <img src="<?= $baseUrl ?>/public/img/icon.svg"
                         alt="Kotha"
                         style="width:88px;height:88px;filter:drop-shadow(0 8px 24px rgba(0,242,254,.35));animation:logo-pulse 2s ease-in-out infinite alternate;">
                </div>
                <h3>Welcome to Kotha</h3>
                <p>
                    Select an active chat from the sidebar or click the chat icons to start a new direct or group discussion. 
                    Remember, all communications are hidden behind your 4-digit security PIN and vanish 5 seconds after reveal.
                </p>
                <div style="margin-top:20px; color:var(--text-muted); font-size:0.8rem; font-weight:500;">
                    <i class="fa-solid fa-lock" style="color:var(--accent-green); margin-right:5px;"></i> End-to-End P2P Media & Hard-Deleting Logs.
                </div>
            </div>
        </div>

    </div>

    <!-- Client-Side Scripts -->
    <script>
        const CURRENT_USER_ID = <?= intval($_SESSION['user_id']) ?>;
        const BASE_URL = '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\") ?>';
    </script>
    <script src="<?= $baseUrl ?>/public/js/app.js?v=6"></script>
    <script src="<?= $baseUrl ?>/public/js/sse.js?v=2"></script>
</body>
</html>
