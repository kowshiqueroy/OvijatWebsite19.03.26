<?php
/**
 * Ovijat Group App Portal - Clean Luxury Edition
 * Minimalist, high-clarity, elegant rainbow accents.
 */

$exclude = ['.', '..', 'assets', 'includes', 'vendor', '.git'];
$dir = __DIR__;
$folders = array_filter(scandir($dir), function($item) use ($dir, $exclude) {
    return is_dir($dir . DIRECTORY_SEPARATOR . $item) && !in_array($item, $exclude);
});

function formatName($name) {
    return ucwords(str_replace(['-', '_'], ' ', $name));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ovijat Group App</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ov-green: #008d48;
            --ov-red: #ed1c24;
            --ov-yellow: #ffc107;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --radius: 14px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg);
            background-image: 
                radial-gradient(at 0% 0%, rgba(0, 141, 72, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(237, 28, 36, 0.03) 0px, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Minimal Elegant Header */
        header {
            padding: 60px 20px 30px;
            text-align: center;
        }

        header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        header h1 span {
            color: var(--ov-green);
        }

        /* Sleek Notice */
        .notice-wrapper {
            max-width: 700px;
            margin: 0 auto 40px;
            padding: 0 20px;
        }

        .maintenance-notice {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 50px;
            padding: 10px 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .maintenance-notice i {
            color: var(--ov-red);
            font-size: 0.9rem;
        }

        .maintenance-notice p {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .maintenance-notice b {
            color: var(--ov-red);
        }

        /* App Grid */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px 60px;
            width: 100%;
        }

        .app-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
        }

        /* Card with Animated Rainbow Border on Hover */
        .app-card {
            background: var(--card-bg);
            border: 1px solid #e2e8f0;
            padding: 25px 10px;
            border-radius: var(--radius);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 85px;
            position: relative;
            transition: all 0.3s ease;
            cursor: pointer;
            overflow: hidden;
        }

        /* The "Moving Color Line" - Ultra thin and elegant */
        .app-card::after {
            content: '';
            position: absolute;
            inset: 0;
            padding: 1.5px; /* Thin line */
            border-radius: var(--radius);
            background: linear-gradient(90deg, var(--ov-green), var(--ov-yellow), var(--ov-red), var(--ov-green));
            background-size: 200% auto;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .app-card:hover {
            transform: translateY(-4px);
            border-color: transparent;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.08);
        }

        .app-card:hover::after {
            opacity: 1;
            animation: borderFlow 3s infinite linear;
        }

        @keyframes borderFlow {
            to { background-position: 200% center; }
        }

        .app-card span {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-main);
            transition: color 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .app-card:hover span {
            color: var(--ov-green);
        }

        /* Modern Popup */
        #statusPopup {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .popup-content {
            background: #fff;
            padding: 40px;
            border-radius: 24px;
            text-align: center;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .popup-content i {
            font-size: 2.5rem;
            color: var(--ov-red);
            margin-bottom: 20px;
            display: block;
        }

        .popup-content h2 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            font-weight: 800;
        }

        .popup-content p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .popup-content button {
            background: var(--ov-green);
            color: #fff;
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }

        .popup-content button:hover {
            transform: scale(1.05);
            background: var(--ov-green-dark, #00723a);
        }

        /* Footer */
        footer {
            margin-top: auto;
            padding: 40px;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        footer b {
            color: var(--ov-green);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .app-grid { grid-template-columns: repeat(4, 1fr); }
        }

        @media (max-width: 768px) {
            header { padding: 40px 15px 20px; }
            .app-grid { 
                grid-template-columns: repeat(3, 1fr); 
                gap: 10px;
            }
            .app-card { min-height: 70px; padding: 15px 5px; }
            .app-card span { font-size: 0.75rem; }
            .maintenance-notice p { font-size: 0.75rem; }
        }

        /* Staggered Load */
        .app-card { animation: fadeIn 0.5s ease backwards; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        <?php 
        $delay = 0;
        foreach ($folders as $index => $folder) {
            echo ".app-card:nth-child(".($index+1).") { animation-delay: ".($delay)."s; }";
            $delay += 0.03;
        }
        ?>
    </style>
</head>
<body>

    <header>
        <h1>Ovijat Group <span>App</span></h1>
    </header>

    <div class="notice-wrapper">
        <div class="maintenance-notice">
            <i class="fas fa-circle-exclamation"></i>
            <p>Under maintenance until <b>5:00 PM, 11 May 2026</b></p>
        </div>
    </div>

    <div class="container">
        <div class="app-grid">
            <?php foreach ($folders as $folder): 
                $hasIndex = file_exists($dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'index.php') || 
                            file_exists($dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'index.html');
                $target = htmlspecialchars($folder) . "/";
            ?>
                <a href="javascript:void(0)" 
                   onclick="launchApp('<?php echo $target; ?>', <?php echo $hasIndex ? 'true' : 'false'; ?>)" 
                   class="app-card">
                    <span><?php echo formatName($folder); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="statusPopup">
        <div class="popup-content">
            <i class="fas fa-hourglass-start"></i>
            <h2>App Not Ready</h2>
            <p>This module is currently being developed. Please contact the IT Department for the deployment schedule.</p>
            <button onclick="closePopup()">GOT IT</button>
        </div>
    </div>

    <footer>
        by <b>IT Dept.</b>
    </footer>

    <script>
        function launchApp(url, ready) {
            if (ready) {
                window.location.href = url;
            } else {
                document.getElementById('statusPopup').style.display = 'flex';
            }
        }

        function closePopup() {
            document.getElementById('statusPopup').style.display = 'none';
        }

        window.onclick = function(event) {
            let popup = document.getElementById('statusPopup');
            if (event.target == popup) {
                closePopup();
            }
        }
    </script>
</body>
</html>
