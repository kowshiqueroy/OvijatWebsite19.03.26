<?php
// Get all directories
$all_folders = glob(__DIR__ . '/*', GLOB_ONLYDIR);
$valid_folders = [];

// Filter out folders containing 'copy' or 'backup'
foreach ($all_folders as $folder) {
    $folder_name = strtolower(basename($folder));
    if (strpos($folder_name, 'copy') === false && strpos($folder_name, 'backup') === false) {
        $valid_folders[] = $folder;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Lato', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 1rem;
        }

        /* Container & Card */
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 900px;
        }

        .projects {
            width: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 3rem 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            text-align: center;
            animation: fade-in-down 0.8s ease-out;
        }

        h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 2rem;
            letter-spacing: -0.5px;
        }

        /* Responsive Grid for Buttons */
        .btn-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.2rem;
            width: 100%;
        }

        .btn {
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.2);
            transition: all 0.3s ease;
            opacity: 0; /* Base for animation */
            animation: pop-in 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        /* Staggered Animations for the first few buttons */
        .btn:nth-child(1) { animation-delay: 0.1s; }
        .btn:nth-child(2) { animation-delay: 0.2s; }
        .btn:nth-child(3) { animation-delay: 0.3s; }
        .btn:nth-child(4) { animation-delay: 0.4s; }
        .btn:nth-child(n+5) { animation-delay: 0.5s; }

        .btn:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(118, 75, 162, 0.4);
        }

        .btn:active {
            transform: translateY(-2px);
        }

        /* Footer */
        .powered-by {
            margin-top: 3rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
            animation: fade-in-up 1s ease-out 0.5s forwards;
            opacity: 0;
        }

        .powered-by span {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .powered-by a {
            text-decoration: none;
            color: #764ba2;
            font-weight: 700;
            transition: color 0.2s ease;
        }

        .powered-by a:hover {
            color: #667eea;
        }

        /* Animations */
        @keyframes fade-in-down {
            0% { opacity: 0; transform: translateY(-30px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @keyframes pop-in {
            0% { opacity: 0; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }

        @keyframes fade-in-up {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* Mobile Adjustments */
        @media (max-width: 600px) {
            h1 { font-size: 2rem; }
            .projects { padding: 2rem 1.5rem; }
            .btn-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="projects">
            <h1>My Projects</h1>
            
            <div class="btn-container">
                <?php if (empty($valid_folders)): ?>
                    <p style="grid-column: 1 / -1; color: #666;">No projects found.</p>
                <?php else: ?>
                    <?php foreach ($valid_folders as $folder): ?>
                        <a href="<?= htmlspecialchars(basename($folder)) ?>" class="btn">
                            <?= htmlspecialchars(ucwords(str_replace('-', ' ', basename($folder)))) ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="powered-by">
        <span>Developer: <a href="https://www.kowshiqueroy.com" target="_blank">Kowshique Roy</a></span>
        <span>Powered by: <a href="https://sohojweb.com" target="_blank">SoHoJ Web</a></span>
    </div>

</body>
</html>