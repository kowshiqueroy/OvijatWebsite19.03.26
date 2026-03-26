<?php
require_once __DIR__ . '/../../includes/config/database.php';
$favicon = getSetting('company_favicon', '');
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | SohojWeb</title>
    <?php if($favicon): ?><link rel="icon" type="image/*" href="<?= escape($favicon) ?>"><?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="text-center px-4">
        <div class="text-9xl font-black text-gray-200 mb-4">404</div>
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Page Not Found</h1>
        <p class="text-gray-500 mb-8">The page you're looking for doesn't exist or has been moved.</p>
        <a href="<?= BASE_URL ?>/" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-all">
            <i class="fas fa-home mr-2"></i> Back to Home
        </a>
    </div>
</body>
</html>
