<?php
/**
 * eClass - Subjects List
 */
$pageTitle = 'Subjects';
$pageSubtitle = 'Manage your class subjects';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

// Search & filter
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'active';

$where = "WHERE 1=1";
$params = [];

// Scope: teachers see only their assigned subjects, officers see their section
$officerSection = getUserSection();
$sectionJoin = "";
if (isTeacher()) {
    $sectionJoin = "";
    $where .= " AND s.teacher_id = ?";
    $params[] = $_SESSION['user_id'];
} elseif (hasSectionScope()) {
    $sectionJoin = "JOIN subject_enrollments se2 ON s.id = se2.subject_id JOIN students st ON se2.student_id = st.id AND st.section = ?";
    $params[] = $officerSection;
}

if ($search) {
    $where .= " AND (s.subject_code LIKE ? OR s.subject_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter) {
    $where .= " AND s.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM subject_enrollments se WHERE se.subject_id = s.id) as student_count
    FROM subjects s
    $sectionJoin
    $where
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$subjects = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<!-- Actions Bar -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <form method="GET" class="w-full sm:w-auto">
        <div class="relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-mono-400 text-xs"></i>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search subjects..."
                   class="w-full sm:w-64 pl-9 pr-3 py-2 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-white dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
        </div>
    </form>
    <a href="<?= BASE_URL ?>/subjects/create.php" 
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
        <i class="fas fa-plus text-xs"></i>
        <span>New Subject</span>
    </a>
</div>

<!-- Subjects Grid -->
<?php if (empty($subjects)): ?>
<div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 px-6 py-16 text-center">
    <i class="fas fa-book text-4xl text-mono-200 dark:text-mono-700 mb-3"></i>
    <p class="text-mono-400 text-sm mb-4">No subjects found</p>
    <a href="<?= BASE_URL ?>/subjects/create.php" class="inline-flex items-center gap-2 text-sm font-medium text-mono-900 dark:text-mono-100 hover:underline">
        <i class="fas fa-plus text-xs"></i> Create your first subject
    </a>
</div>
<?php else: ?>
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($subjects as $subject): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 hover:border-mono-300 dark:hover:border-mono-700 transition-colors group">
        <div class="p-5">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <span class="inline-block px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider rounded bg-mono-100 dark:bg-mono-800 text-mono-500 dark:text-mono-400 mb-2">
                        <?= sanitize($subject['subject_code']) ?>
                    </span>
                    <h3 class="text-sm font-semibold"><?= sanitize($subject['subject_name']) ?></h3>
                </div>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false" class="p-1.5 rounded-md text-mono-400 hover:bg-mono-100 dark:hover:bg-mono-800 opacity-0 group-hover:opacity-100 transition-all">
                        <i class="fas fa-ellipsis-v text-xs"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 top-full mt-1 w-40 bg-white dark:bg-mono-800 rounded-lg border border-mono-200 dark:border-mono-700 shadow-lg py-1 z-10">
                        <a href="<?= BASE_URL ?>/subjects/edit.php?id=<?= $subject['id'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-mono-600 dark:text-mono-300 hover:bg-mono-50 dark:hover:bg-mono-700">
                            <i class="fas fa-edit text-xs w-4"></i> Edit
                        </a>
                        <a href="<?= BASE_URL ?>/subjects/enroll.php?id=<?= $subject['id'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-mono-600 dark:text-mono-300 hover:bg-mono-50 dark:hover:bg-mono-700">
                            <i class="fas fa-user-plus text-xs w-4"></i> Enrollments
                        </a>
                        <form method="POST" action="<?= BASE_URL ?>/subjects/delete.php" onsubmit="return confirm('Are you sure?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= $subject['id'] ?>">
                            <button type="submit" class="flex items-center gap-2 px-3 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 w-full text-left">
                                <i class="fas fa-trash text-xs w-4"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($subject['description']): ?>
            <p class="text-xs text-mono-400 mb-3 line-clamp-2"><?= sanitize($subject['description']) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-2 text-[11px] text-mono-400">
                <?php if ($subject['schedule_day']): ?>
                <span class="inline-flex items-center gap-1"><i class="fas fa-calendar-day"></i> <?= sanitize($subject['schedule_day']) ?></span>
                <?php endif; ?>
                <?php if ($subject['schedule_time']): ?>
                <span class="inline-flex items-center gap-1"><i class="fas fa-clock"></i> <?= sanitize($subject['schedule_time']) ?></span>
                <?php endif; ?>
                <?php if ($subject['room']): ?>
                <span class="inline-flex items-center gap-1"><i class="fas fa-door-open"></i> <?= sanitize($subject['room']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="border-t border-mono-100 dark:border-mono-800 px-5 py-3 flex items-center justify-between">
            <span class="text-xs text-mono-400">
                <i class="fas fa-users mr-1"></i> <?= $subject['student_count'] ?> students
            </span>
            <a href="<?= BASE_URL ?>/subjects/enroll.php?id=<?= $subject['id'] ?>" class="text-xs font-medium text-mono-900 dark:text-mono-100 hover:underline">
                Manage →
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
