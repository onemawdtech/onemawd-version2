<?php
/**
 * eClass - Login Page
 */
$pageTitle = 'Sign In';
require_once dirname(__DIR__) . '/config/app.php';

if (isLoggedIn()) {
    redirect('/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_section'] = $user['section'];
            redirect('/dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" 
      :class="{ 'dark': darkMode }"
      x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — OneMAWD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { mono: { 50:'#fafafa',100:'#f5f5f5',200:'#e5e5e5',300:'#d4d4d4',400:'#a3a3a3',500:'#737373',600:'#525252',700:'#404040',800:'#262626',900:'#171717',950:'#0a0a0a' } } } }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .page-loader { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: #fafafa; transition: opacity .3s, visibility .3s; }
        .dark .page-loader { background: #0a0a0a; }
        .page-loader.loaded { opacity: 0; visibility: hidden; pointer-events: none; }
        .loader-spinner { width: 22px; height: 22px; border: 2px solid #e5e5e5; border-top-color: #171717; border-radius: 50%; animation: spin .6s linear infinite; }
        .dark .loader-spinner { border-color: #404040; border-top-color: #e5e5e5; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-mono-50 dark:bg-mono-950 min-h-screen flex items-center justify-center p-4 transition-colors duration-200">

    <!-- Page Loader -->
    <div class="page-loader" id="pageLoader"><div class="loader-spinner"></div></div>
    <script>window.addEventListener('load',function(){document.getElementById('pageLoader').classList.add('loaded')});</script>
    
    <!-- Theme Toggle -->
    <button @click="darkMode = !darkMode" class="fixed top-4 right-4 p-2.5 rounded-lg bg-white dark:bg-mono-900 border border-mono-200 dark:border-mono-800 hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors">
        <i class="fas fa-moon text-sm text-mono-500" x-show="!darkMode"></i>
        <i class="fas fa-sun text-sm text-mono-400" x-show="darkMode" x-cloak></i>
    </button>

    <div class="w-full max-w-sm">
        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="OneMAWD" class="w-14 h-14 mx-auto mb-4 object-contain">
            <h1 class="text-2xl font-bold text-mono-900 dark:text-mono-100">Welcome back</h1>
            <p class="text-sm text-mono-400 dark:text-mono-500 mt-1">Sign in to your OneMAWD account</p>
        </div>

        <!-- Login Form -->
        <div class="bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 p-6 shadow-sm">
            <?php if ($error): ?>
            <div class="flex items-center gap-2 px-3 py-2.5 mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 text-sm border border-red-200 dark:border-red-800">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <?= csrfField() ?>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Username</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mono-400">
                            <i class="fas fa-user text-xs"></i>
                        </span>
                        <input type="text" name="username" value="<?= sanitize($username ?? '') ?>" required
                               class="w-full pl-9 pr-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100 focus:border-transparent transition-shadow"
                               placeholder="Enter your username">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Password</label>
                    <div class="relative" x-data="{ show: false }">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mono-400">
                            <i class="fas fa-lock text-xs"></i>
                        </span>
                        <input :type="show ? 'text' : 'password'" name="password" required
                               class="w-full pl-9 pr-10 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100 focus:border-transparent transition-shadow"
                               placeholder="••••••••">
                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-mono-400 hover:text-mono-600 dark:hover:text-mono-300">
                            <i class="fas text-xs" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" 
                        class="w-full py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-semibold hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-mono-400 dark:text-mono-600 mt-6">
            OneMAWD v<?= APP_VERSION ?> — Classroom Management System
        </p>
    </div>

</body>
</html>
