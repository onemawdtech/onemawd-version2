<?php
/**
 * eClass - Topbar Component
 */
if (!defined('OMAWD_ACCESS')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Direct access not allowed.</p>');
}
?>

<!-- Main Content Wrapper -->
<div class="lg:ml-64 min-h-screen flex flex-col">

    <!-- Top Bar -->
    <header class="sticky top-0 z-20 h-16 bg-white/80 dark:bg-mono-900/80 backdrop-blur-md border-b border-mono-200 dark:border-mono-800 flex items-center justify-between px-4 sm:px-6">
        <div class="flex items-center gap-3">
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors">
                <i class="fas fa-bars text-mono-600 dark:text-mono-400"></i>
            </button>
            <div>
                <h1 class="text-lg font-semibold"><?= $pageTitle ?? 'Dashboard' ?></h1>
                <?php if (isset($pageSubtitle)): ?>
                <p class="text-xs text-mono-400 dark:text-mono-500"><?= $pageSubtitle ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <?php if (hasSectionScope()): ?>
            <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-lg bg-mono-100 dark:bg-mono-800 text-mono-500 dark:text-mono-400 border border-mono-200 dark:border-mono-700">
                <i class="fas fa-layer-group text-[10px]"></i>
                Section <?= sanitize(getUserSection()) ?>
            </span>
            <?php endif; ?>
            <!-- Theme Toggle -->
            <button @click="darkMode = !darkMode" 
                    class="p-2.5 rounded-lg hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors"
                    :title="darkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
                <i class="fas fa-moon text-sm text-mono-500" x-show="!darkMode"></i>
                <i class="fas fa-sun text-sm text-mono-400" x-show="darkMode" x-cloak></i>
            </button>
            <!-- Profile -->
            <a href="<?= BASE_URL ?>/profile/" class="p-2.5 rounded-lg hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors" title="My Profile">
                <i class="fas fa-user-circle text-sm text-mono-500 dark:text-mono-400"></i>
            </a>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if ($flash): ?>
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="mx-4 sm:mx-6 mt-4">
        <div class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm
                    <?= $flash['type'] === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : '' ?>
                    <?= $flash['type'] === 'error' ? 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800' : '' ?>
                    <?= $flash['type'] === 'info' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-800' : '' ?>">
            <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : ($flash['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
            <span><?= $flash['message'] ?></span>
            <button @click="show = false" class="ml-auto"><i class="fas fa-times text-xs opacity-50 hover:opacity-100"></i></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Page Content -->
    <main class="flex-1 p-4 sm:p-6">
