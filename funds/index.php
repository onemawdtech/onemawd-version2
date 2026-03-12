<?php
/**
 * eClass - Class Funds List
 */
$pageTitle = 'Class Funds';
$pageSubtitle = 'Manage fund collections';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$statusFilter = $_GET['status'] ?? 'active';
$officerSection = getUserSection();
$sectionScoped = hasSectionScope();

if ($sectionScoped) {
    $stmt = $pdo->prepare("
        SELECT f.*, s.subject_code, s.subject_name,
               CASE WHEN f.fund_type = 'general' THEN 0
                    WHEN f.subject_id IS NOT NULL
                    THEN (SELECT COUNT(*) FROM subject_enrollments se2 JOIN students st2 ON se2.student_id = st2.id WHERE se2.subject_id = f.subject_id AND st2.section = ? AND st2.status = 'active')
                    ELSE (SELECT COUNT(*) FROM fund_assignees fa2 JOIN students st2 ON fa2.student_id = st2.id WHERE fa2.fund_id = f.id AND st2.section = ?)
               END as assignee_count,
               COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp LEFT JOIN students st3 ON fp.student_id = st3.id WHERE fp.fund_id = f.id AND (st3.section = ? OR fp.student_id IS NULL)), 0) as collected,
               COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = f.id AND fp.student_id IS NULL), 0) as deposits
        FROM funds f
        LEFT JOIN subjects s ON f.subject_id = s.id
        WHERE f.status = ?
          AND (
            f.id IN (SELECT fa.fund_id FROM fund_assignees fa JOIN students st ON fa.student_id = st.id WHERE st.section = ?)
            OR f.subject_id IN (SELECT DISTINCT se.subject_id FROM subject_enrollments se JOIN students st ON se.student_id = st.id WHERE st.section = ?)
            OR f.created_by = ?
          )
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$officerSection, $officerSection, $officerSection, $statusFilter, $officerSection, $officerSection, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("
        SELECT f.*, s.subject_code, s.subject_name,
               CASE WHEN f.fund_type = 'general' THEN 0
                    WHEN f.subject_id IS NOT NULL
                    THEN (SELECT COUNT(*) FROM subject_enrollments se2 JOIN students st2 ON se2.student_id = st2.id WHERE se2.subject_id = f.subject_id AND st2.status = 'active')
                    ELSE (SELECT COUNT(*) FROM fund_assignees fa WHERE fa.fund_id = f.id)
               END as assignee_count,
               COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = f.id), 0) as collected,
               COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = f.id AND fp.student_id IS NULL), 0) as deposits
        FROM funds f
        LEFT JOIN subjects s ON f.subject_id = s.id
        WHERE f.status = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$statusFilter]);
}
$funds = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<!-- Actions -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <div class="flex gap-1">
        <a href="?status=active" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?= $statusFilter === 'active' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'bg-mono-100 dark:bg-mono-800 text-mono-500 hover:bg-mono-200 dark:hover:bg-mono-700' ?>">Active</a>
        <a href="?status=closed" class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors <?= $statusFilter === 'closed' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'bg-mono-100 dark:bg-mono-800 text-mono-500 hover:bg-mono-200 dark:hover:bg-mono-700' ?>">Closed</a>
    </div>
    <div class="flex gap-2">
        <a href="<?= BASE_URL ?>/funds/audit.php" 
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border-2 border-red-500 text-red-600 dark:text-red-400 text-sm font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
            <i class="fas fa-file-pdf text-xs"></i>
            <span>Audit Report</span>
        </a>
        <a href="<?= BASE_URL ?>/funds/create.php" 
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
            <i class="fas fa-plus text-xs"></i>
            <span>New Fund</span>
        </a>
    </div>
</div>

<!-- Funds Grid -->
<?php if (empty($funds)): ?>
<div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 px-6 py-16 text-center">
    <i class="fas fa-wallet text-4xl text-mono-200 dark:text-mono-700 mb-3"></i>
    <p class="text-mono-400 text-sm mb-4">No <?= $statusFilter ?> funds found</p>
    <a href="<?= BASE_URL ?>/funds/create.php" class="inline-flex items-center gap-2 text-sm font-medium text-mono-900 dark:text-mono-100 hover:underline">
        <i class="fas fa-plus text-xs"></i> Create your first fund
    </a>
</div>
<?php else: ?>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($funds as $fund): 
        $fType = $fund['fund_type'] ?? 'standard';
        $fIsGeneral = $fType === 'general';
        $fIsVoluntary = $fType === 'voluntary';
        $target = ($fIsGeneral || $fIsVoluntary) ? 0 : $fund['amount'] * $fund['assignee_count'];
        $pct = $target > 0 ? min(100, ($fund['collected'] / $target) * 100) : 0;
    ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 hover:border-mono-300 dark:hover:border-mono-700 transition-colors">
        <div class="p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-sm font-semibold"><?= sanitize($fund['fund_name']) ?></h3>
                        <?php if ($fIsGeneral): ?>
                        <span class="px-1.5 py-0.5 text-[9px] font-medium rounded bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 uppercase tracking-wider">General</span>
                        <?php elseif ($fIsVoluntary): ?>
                        <span class="px-1.5 py-0.5 text-[9px] font-medium rounded bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 uppercase tracking-wider">Voluntary</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($fund['subject_code']): ?>
                    <span class="text-[10px] text-mono-400"><i class="fas fa-book mr-1"></i><?= sanitize($fund['subject_code']) ?></span>
                    <?php endif; ?>
                </div>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full uppercase tracking-wider bg-mono-100 dark:bg-mono-800 text-mono-500 dark:text-mono-400">
                    <?= $fund['frequency'] ?>
                </span>
            </div>

            <?php if ($fund['description']): ?>
            <p class="text-xs text-mono-400 mb-3 line-clamp-2"><?= sanitize($fund['description']) ?></p>
            <?php endif; ?>

            <?php if ($fIsGeneral || $fIsVoluntary): ?>
            <div class="mb-2">
                <span class="text-xl font-bold"><?= formatMoney($fund['collected']) ?></span>
                <span class="text-xs text-mono-400 ml-1">collected</span>
                <?php if ($fund['deposits'] > 0): ?>
                <div class="mt-1 text-[11px] text-amber-500 dark:text-amber-400"><i class="fas fa-piggy-bank mr-1"></i><?= formatMoney($fund['deposits']) ?> in deposits</div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="flex items-baseline justify-between mb-2">
                <span class="text-xl font-bold"><?= formatMoney($fund['amount']) ?></span>
                <span class="text-xs text-mono-400">per student</span>
            </div>

            <div class="mb-2">
                <div class="flex items-center justify-between text-[11px] text-mono-400 mb-1">
                    <span><?= formatMoney($fund['collected']) ?> collected</span>
                    <span><?= round($pct) ?>%</span>
                </div>
                <div class="w-full h-1.5 bg-mono-100 dark:bg-mono-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 <?= $pct >= 100 ? 'bg-emerald-500' : 'bg-mono-900 dark:bg-mono-100' ?>" style="width: <?= $pct ?>%"></div>
                </div>
                <?php if ($fund['deposits'] > 0): ?>
                <div class="mt-1 text-[11px] text-amber-500 dark:text-amber-400"><i class="fas fa-piggy-bank mr-1"></i><?= formatMoney($fund['deposits']) ?> in deposits</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($fund['due_date']): ?>
            <p class="text-[11px] text-mono-400"><i class="fas fa-calendar-alt mr-1"></i>Due: <?= formatDate($fund['due_date']) ?></p>
            <?php endif; ?>
        </div>
        <div class="border-t border-mono-100 dark:border-mono-800 px-5 py-3 flex items-center justify-between">
            <?php if ($fIsGeneral): ?>
            <span class="text-xs text-mono-400"><i class="fas fa-piggy-bank mr-1"></i> Open fund</span>
            <?php else: ?>
            <span class="text-xs text-mono-400"><i class="fas fa-users mr-1"></i> <?= $fund['assignee_count'] ?> students</span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/funds/manage.php?id=<?= $fund['id'] ?>" class="text-xs font-medium text-mono-900 dark:text-mono-100 hover:underline">
                Manage →
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
