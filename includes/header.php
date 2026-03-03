<?php
/**
 * eClass - Header Component
 */
if (!defined('OMAWD_ACCESS')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Direct access not allowed.</p>');
}
require_once dirname(__DIR__) . '/config/app.php';
$user = currentUser();
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }" 
      x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' — ' : '' ?>OneMAWD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        mono: {
                            50: '#fafafa',
                            100: '#f5f5f5',
                            200: '#e5e5e5',
                            300: '#d4d4d4',
                            400: '#a3a3a3',
                            500: '#737373',
                            600: '#525252',
                            700: '#404040',
                            800: '#262626',
                            900: '#171717',
                            950: '#0a0a0a',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        [x-cloak] { display: none !important; }
        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #a3a3a3; border-radius: 2px; }
        .dark .scrollbar-thin::-webkit-scrollbar-thumb { background: #525252; }
        /* Page loader */
        .page-loader { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: #fafafa; transition: opacity .3s, visibility .3s; }
        .dark .page-loader { background: #0a0a0a; }
        .page-loader.loaded { opacity: 0; visibility: hidden; pointer-events: none; }
        .loader-spinner { width: 22px; height: 22px; border: 2px solid #e5e5e5; border-top-color: #171717; border-radius: 50%; animation: spin .6s linear infinite; }
        .dark .loader-spinner { border-color: #404040; border-top-color: #e5e5e5; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-mono-50 dark:bg-mono-950 text-mono-900 dark:text-mono-100 min-h-screen transition-colors duration-200">

<!-- Page Loader -->
<div class="page-loader" id="pageLoader"><div class="loader-spinner"></div></div>
<script>window.addEventListener('load',function(){document.getElementById('pageLoader').classList.add('loaded')});</script>
