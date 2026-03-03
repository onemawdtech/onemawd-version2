<?php
/**
 * eClass - Accounts Management
 */
$pageTitle = 'Accounts';
$pageSubtitle = 'Manage system accounts';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

$accounts = $pdo->query("SELECT * FROM accounts ORDER BY created_at DESC")->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<!-- Actions -->
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-mono-400"><?= count($accounts) ?> account(s)</p>
    <a href="<?= BASE_URL ?>/accounts/create.php" 
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
        <i class="fas fa-plus text-xs"></i>
        <span>Add Account</span>
    </a>
</div>

<!-- Accounts Grid -->
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($accounts as $account): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 group">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                    <span class="text-sm font-bold text-mono-500 dark:text-mono-400"><?= getInitials($account['full_name']) ?></span>
                </div>
                <div>
                    <h3 class="text-sm font-semibold"><?= sanitize($account['full_name']) ?></h3>
                    <p class="text-[11px] text-mono-400">@<?= sanitize($account['username']) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <a href="<?= BASE_URL ?>/accounts/edit.php?id=<?= $account['id'] ?>" class="p-1.5 rounded-md text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors">
                    <i class="fas fa-edit text-xs"></i>
                </a>
                <?php if ($account['id'] !== $_SESSION['user_id']): ?>
                <form method="POST" action="<?= BASE_URL ?>/accounts/delete.php" onsubmit="return confirm('Delete this account?')" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $account['id'] ?>">
                    <button type="submit" class="p-1.5 rounded-md text-mono-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full uppercase tracking-wider
                <?= $account['role'] === 'admin' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'bg-mono-100 dark:bg-mono-800 text-mono-500 dark:text-mono-400' ?>">
                <?= $account['role'] ?>
            </span>
            <?php if (in_array($account['role'], ['officer', 'teacher']) && $account['section']): ?>
            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full tracking-wider bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800">
                <i class="fas fa-layer-group mr-1"></i><?= sanitize($account['section']) ?>
            </span>
            <?php endif; ?>
            <span class="text-[10px] text-mono-400">Joined <?= formatDate($account['created_at']) ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
