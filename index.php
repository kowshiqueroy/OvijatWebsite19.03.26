



<?php


header("Location: /ems-root/");
/**
 * Modern Project Dashboard
 * Enhanced with metadata tracking and dark mode UI.
 */

// Configuration
$exclude_patterns = ['node_modules', '.git'];
$project_exclude = ['copy', 'backup'];

// Get all items in directory
$all_items = glob(__DIR__ . '/*');
$projects = [];
$others = [];

// Filter and Gather Metadata
foreach ($all_items as $item) {
    $item_name = strtolower(basename($item));
    $is_dir = is_dir($item);
    
    // Check global exclusions
    $skip = false;
    foreach ($exclude_patterns as $pattern) {
        if (strpos($item_name, $pattern) !== false) {
            $skip = true;
            break;
        }
    }
    if ($skip || $item_name === 'index.php') continue;

    // Determine if it's a "Project" or "Other"
    $is_project = $is_dir;
    if ($is_dir) {
        foreach ($project_exclude as $pattern) {
            if (strpos($item_name, $pattern) !== false) {
                $is_project = false;
                break;
            }
        }
    }

    // Metadata
    $created_at = @filectime($item);
    $last_updated = @filemtime($item);
    $item_count = 0;

    if ($is_dir) {
        $files = glob($item . '/{*,.*}', GLOB_BRACE);
        if ($files) {
            foreach ($files as $file) {
                if (basename($file) === '.' || basename($file) === '..') continue;
                $item_count++;
                $mtime = @filemtime($file);
                if ($mtime > $last_updated) {
                    $last_updated = $mtime;
                }
            }
        }
    }

    $data = [
        'name' => htmlspecialchars($is_dir ? ucwords(str_replace(['-', '_'], ' ', basename($item))) : basename($item)),
        'path' => htmlspecialchars(basename($item)),
        'created_at' => $created_at ? date('M j, Y', $created_at) : 'Unknown',
        'last_updated' => $last_updated ? date('M j, Y, g:i a', $last_updated) : 'Unknown',
        'item_count' => $item_count,
        'timestamp' => $last_updated,
        'is_dir' => $is_dir
    ];

    if ($is_project) {
        $projects[] = $data;
    } else {
        $others[] = $data;
    }
}

// Sort projects by last updated (newest first)
usort($projects, function($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});

// Sort others by last updated (newest first)
usort($others, function($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workspace Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --card-hover: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.4);
            --border: #334155;
            --success: #10b981;
            --other-accent: #64748b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 4rem 1.5rem;
            line-height: 1.5;
        }

        .container {
            flex: 1;
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
        }

        header {
            margin-bottom: 4rem;
            text-align: center;
            animation: slideDown 0.6s ease-out;
        }

        h1 {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.05em;
            background: linear-gradient(to right, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.75rem;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1.2rem;
            font-weight: 400;
        }

        section {
            margin-bottom: 5rem;
        }

        h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        h2::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Projects Grid */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
        }

        .project-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .project-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--accent);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .project-card.other-item::after {
            background: var(--other-accent);
        }

        .project-card:hover {
            transform: translateY(-8px);
            background-color: var(--card-hover);
            border-color: var(--accent);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.04), 0 0 20px var(--accent-glow);
        }

        .project-card.other-item:hover {
            border-color: var(--other-accent);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
        }

        .project-card:hover::after {
            transform: scaleX(1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .project-title {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-main);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .project-icon {
            font-size: 1.5rem;
            background: rgba(59, 130, 246, 0.1);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: var(--accent);
            flex-shrink: 0;
        }

        .other-item .project-icon {
            background: rgba(148, 163, 184, 0.1);
            color: var(--other-accent);
        }

        .project-meta {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            border-top: 1px solid var(--border);
            padding-top: 1.25rem;
            margin-top: auto;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .meta-label {
            font-weight: 600;
            color: var(--text-main);
            width: 80px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.6;
        }

        .meta-value {
            font-weight: 500;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            margin-top: 0.5rem;
            align-self: flex-start;
        }

        /* Footer */
        footer {
            margin-top: auto;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border-bottom: 1px solid transparent;
        }

        footer a:hover {
            color: #60a5fa;
            border-bottom-color: #60a5fa;
        }

        /* Animations */
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            h1 { font-size: 2.25rem; }
            body { padding: 3rem 1rem; }
            .projects-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>My Projects</h1>
            <div class="subtitle">Local Development Environment</div>
        </header>

        <section>
            <h2>Active Projects</h2>
            <div class="projects-grid">
                <?php if (empty($projects)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 4rem; background: var(--card-bg); border-radius: 16px; color: var(--text-muted);">
                        <p>No active projects found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($projects as $index => $project): ?>
                        <a href="<?= $project['path'] ?>" class="project-card" style="animation: fadeInUp 0.5s ease-out <?= 0.1 * ($index % 10) ?>s forwards; opacity: 0;">
                            <div class="card-header">
                                <div class="project-title" title="<?= $project['name'] ?>"><?= $project['name'] ?></div>
                                <div class="project-icon">📁</div>
                            </div>
                            
                            <div class="badge"><?= $project['item_count'] ?> items</div>

                            <div class="project-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Created</span>
                                    <span class="meta-value"><?= $project['created_at'] ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Updated</span>
                                    <span class="meta-value"><?= $project['last_updated'] ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($others)): ?>
        <section>
            <h2>Other Items (Backups & Files)</h2>
            <div class="projects-grid">
                <?php foreach ($others as $index => $item): ?>
                    <a href="<?= $item['path'] ?>" class="project-card other-item" style="animation: fadeInUp 0.5s ease-out <?= 0.05 * ($index % 20) ?>s forwards; opacity: 0;">
                        <div class="card-header">
                            <div class="project-title" title="<?= $item['name'] ?>"><?= $item['name'] ?></div>
                            <div class="project-icon"><?= $item['is_dir'] ? '📦' : '📄' ?></div>
                        </div>
                        
                        <?php if ($item['is_dir']): ?>
                            <div class="badge"><?= $item['item_count'] ?> items</div>
                        <?php else: ?>
                            <div class="badge" style="background: rgba(100, 116, 139, 0.1); color: var(--other-accent);">File</div>
                        <?php endif; ?>

                        <div class="project-meta">
                            <div class="meta-item">
                                <span class="meta-label">Created</span>
                                <span class="meta-value"><?= $item['created_at'] ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Updated</span>
                                <span class="meta-value"><?= $item['last_updated'] ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <footer>
        <p>
            Developed by <a href="https://www.kowshiqueroy.com" target="_blank">Kowshique Roy</a> 
            &bull; Powered by <a href="https://sohojweb.com" target="_blank">SoHoJ Web</a>
        </p>
    </footer>

</body>
</html>
