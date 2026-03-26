<?php
require_once __DIR__ . '/../includes/config/database.php';
$loginError = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SohojWeb Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { colors: { primary: { '500': '#3b82f6', '600': '#2563eb', '700': '#1d4ed8' } } } }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <?php if ($loginError): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg"><?= escape($loginError) ?></div>
        <?php endif; ?>
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-primary-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                <span class="text-white font-bold text-2xl">S</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">SohojWeb Admin</h1>
            <p class="text-gray-500">Sign in to your account</p>
        </div>
        
        <form action="login-process.php" method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').innerText='Signing in...';" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="admin@sohojweb.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="••••••••">
            </div>
            <button type="submit" class="w-full py-3 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition font-medium">
                Sign In
            </button>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-500">
            Default: admin@sohojweb.com / admin123
        </div>
    </div>
</body>
</html>
