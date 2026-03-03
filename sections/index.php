<?php
/**
 * eClass - Section Management
 */
$pageTitle = 'Sections';
$pageSubtitle = 'Manage class sections';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

$sections = $pdo->query("
    SELECT sec.*, 
           (SELECT COUNT(*) FROM students st WHERE st.section = sec.section_name AND st.status = 'active') as student_count,
           (SELECT COUNT(*) FROM accounts a WHERE a.section = sec.section_name) as account_count
    FROM sections sec
    ORDER BY sec.status ASC, sec.section_name ASC
")->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<!-- Actions -->
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-mono-400"><?= count($sections) ?> section(s)</p>
    <a href="<?= BASE_URL ?>/sections/create.php" 
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
        <i class="fas fa-plus text-xs"></i>
        <span>New Section</span>
    </a>
</div>

<?php if (empty($sections)): ?>
<div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 px-6 py-16 text-center">
    <i class="fas fa-layer-group text-4xl text-mono-200 dark:text-mono-700 mb-3"></i>
    <p class="text-mono-400 text-sm mb-4">No sections created yet</p>
    <a href="<?= BASE_URL ?>/sections/create.php" class="inline-flex items-center gap-2 text-sm font-medium text-mono-900 dark:text-mono-100 hover:underline">
        <i class="fas fa-plus text-xs"></i> Create your first section
    </a>
</div>
<?php else: ?>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($sections as $section): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 hover:border-mono-300 dark:hover:border-mono-700 transition-colors group <?= $section['status'] === 'inactive' ? 'opacity-60' : '' ?>">
        <div class="p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-10 h-10 rounded-xl bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                            <i class="fas fa-layer-group text-mono-500 dark:text-mono-400"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold"><?= sanitize($section['section_name']) ?></h3>
                            <?php if ($section['year_level']): ?>
                            <p class="text-[10px] text-mono-400"><?= $section['year_level'] ?><?= ['st','nd','rd','th','th'][$section['year_level']-1] ?? 'th' ?> Year</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false" class="p-1.5 rounded-md text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 opacity-0 group-hover:opacity-100 transition-all">
                        <i class="fas fa-ellipsis-v text-xs"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-full mt-1 w-40 bg-white dark:bg-mono-800 rounded-lg border border-mono-200 dark:border-mono-700 shadow-lg py-1 z-10">
                        <a href="<?= BASE_URL ?>/sections/edit.php?id=<?= $section['id'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-mono-600 dark:text-mono-300 hover:bg-mono-50 dark:hover:bg-mono-700">
                            <i class="fas fa-edit text-xs w-4"></i> Edit
                        </a>
                        <?php if ($section['student_count'] == 0 && $section['account_count'] == 0): ?>
                        <form method="POST" action="<?= BASE_URL ?>/sections/delete.php" onsubmit="return confirm('Delete section <?= sanitize($section['section_name']) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $section['id'] ?>">
                            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
                                <i class="fas fa-trash text-xs w-4"></i> Delete
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($section['description']): ?>
            <p class="text-xs text-mono-400 mb-3 line-clamp-2"><?= sanitize($section['description']) ?></p>
            <?php endif; ?>

            <div class="flex items-center gap-3 text-xs text-mono-400">
                <span><i class="fas fa-users mr-1"></i> <?= $section['student_count'] ?> student(s)</span>
                <span><i class="fas fa-user-shield mr-1"></i> <?= $section['account_count'] ?> account(s)</span>
            </div>

            <div class="mt-3 pt-3 border-t border-mono-100 dark:border-mono-800">
                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full uppercase tracking-wider
                    <?= $section['status'] === 'active' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400' : 'bg-mono-100 dark:bg-mono-800 text-mono-500' ?>">
                    <?= $section['status'] ?>
                </span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
