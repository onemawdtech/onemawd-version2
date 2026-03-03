<?php
/**
 * eClass - Sidebar Navigation
 */
if (!defined('OMAWD_ACCESS')) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1><p>Direct access not allowed.</p>');
}
?>

<!-- Mobile Overlay -->
<div x-show="sidebarOpen" x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 bg-black/50 z-40 lg:hidden"
     x-transition:enter="transition-opacity ease-out duration-200"
     x-transition:leave="transition-opacity ease-in duration-150"></div>

<!-- Sidebar -->
<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed top-0 left-0 z-50 h-full w-64 bg-white dark:bg-mono-900 border-r border-mono-200 dark:border-mono-800 transform transition-transform duration-200 ease-in-out lg:z-30 flex flex-col">
    
    <!-- Logo -->
    <div class="flex items-center justify-between h-16 px-5 border-b border-mono-200 dark:border-mono-800 flex-shrink-0">
        <a href="<?= BASE_URL ?>/dashboard.php" class="flex items-center gap-2.5">
            <img src="<?= BASE_URL ?>/assets/img/logo.svg" alt="OneMAWD" class="w-8 h-8 rounded-lg object-contain">
            <span class="text-lg font-semibold tracking-tight">OneMAWD</span>
        </a>
        <button @click="sidebarOpen = false" class="lg:hidden p-1 rounded-md hover:bg-mono-100 dark:hover:bg-mono-800">
            <i class="fas fa-times text-mono-500"></i>
        </button>
    </div>

    <!-- Nav Links -->
    <nav class="flex-1 overflow-y-auto scrollbar-thin py-4 px-3 space-y-1">
        <p class="px-3 mb-2 text-[10px] font-semibold uppercase tracking-wider text-mono-400 dark:text-mono-500">Main</p>
        
        <a href="<?= BASE_URL ?>/dashboard.php" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentPage === 'dashboard' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-th-large w-5 text-center text-xs"></i>
            <span>Dashboard</span>
        </a>

        <p class="px-3 mt-5 mb-2 text-[10px] font-semibold uppercase tracking-wider text-mono-400 dark:text-mono-500">Management</p>

        <a href="<?= BASE_URL ?>/subjects/" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentDir === 'subjects' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-book w-5 text-center text-xs"></i>
            <span>Subjects</span>
        </a>

        <?php if (!isTeacher()): ?>
        <a href="<?= BASE_URL ?>/students/" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentDir === 'students' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-users w-5 text-center text-xs"></i>
            <span>Students</span>
        </a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/attendance/" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentDir === 'attendance' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-clipboard-check w-5 text-center text-xs"></i>
            <span>Attendance</span>
        </a>

        <?php if (!isTeacher()): ?>
        <a href="<?= BASE_URL ?>/funds/" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentDir === 'funds' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-wallet w-5 text-center text-xs"></i>
            <span>Class Funds</span>
        </a>
        <?php endif; ?>

        <p class="px-3 mt-5 mb-2 text-[10px] font-semibold uppercase tracking-wider text-mono-400 dark:text-mono-500">System</p>

        <a href="<?= BASE_URL ?>/profile/" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentDir === 'profile' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-user-circle w-5 text-center text-xs"></i>
            <span>My Profile</span>
        </a>

        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/sections/" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentDir === 'sections' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-layer-group w-5 text-center text-xs"></i>
            <span>Sections</span>
        </a>
        <a href="<?= BASE_URL ?>/accounts/" 
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                  <?= $currentDir === 'accounts' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'text-mono-600 dark:text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 hover:text-mono-900 dark:hover:text-mono-100' ?>">
            <i class="fas fa-user-shield w-5 text-center text-xs"></i>
            <span>Accounts</span>
        </a>
        <?php endif; ?>
    </nav>

    <!-- User Info -->
    <?php if ($user): ?>
    <div class="flex-shrink-0 border-t border-mono-200 dark:border-mono-800 p-3">
        <div class="flex items-center gap-3 px-2">
            <a href="<?= BASE_URL ?>/profile/" class="flex items-center gap-3 flex-1 min-w-0 group">
                <div class="w-8 h-8 rounded-full bg-mono-200 dark:bg-mono-700 flex items-center justify-center flex-shrink-0 group-hover:bg-mono-300 dark:group-hover:bg-mono-600 transition-colors">
                    <span class="text-xs font-semibold text-mono-600 dark:text-mono-300"><?= getInitials($user['full_name']) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate group-hover:text-mono-600 dark:group-hover:text-mono-300 transition-colors"><?= sanitize($user['full_name']) ?></p>
                    <p class="text-[11px] text-mono-400 dark:text-mono-500 capitalize"><?= $user['role'] ?><?php if (in_array($user['role'], ['officer', 'teacher']) && $user['section']): ?> · <?= sanitize($user['section']) ?><?php endif; ?></p>
                </div>
            </a>
            <a href="<?= BASE_URL ?>/auth/logout.php" class="p-1.5 rounded-md text-mono-400 hover:text-red-500 hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors" title="Logout">
                <i class="fas fa-sign-out-alt text-xs"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</aside>
