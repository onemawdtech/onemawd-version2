<?php
/**
 * eClass - Students List
 */
$pageTitle = 'Students';
$pageSubtitle = 'Manage student records';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? 'active';

$where = "WHERE 1=1";
$params = [];

// Section scoping for officers/teachers
$officerSection = getUserSection();
if (hasSectionScope()) {
    $where .= " AND s.section = ?";
    $params[] = $officerSection;
}

if ($search) {
    $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
}
if ($typeFilter) {
    $where .= " AND s.student_type = ?";
    $params[] = $typeFilter;
}
if ($statusFilter) {
    $where .= " AND s.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT s.*,
           (SELECT COUNT(*) FROM subject_enrollments se WHERE se.student_id = s.id) as subject_count
    FROM students s
    $where
    ORDER BY s.last_name, s.first_name
");
$stmt->execute($params);
$students = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<!-- Actions -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <div class="relative flex-1 sm:flex-initial">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-mono-400 text-xs"></i>
            <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search students..."
                   class="w-full sm:w-64 pl-9 pr-3 py-2 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-white dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
        </div>
        <select name="type" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-white dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            <option value="">All Types</option>
            <option value="regular" <?= $typeFilter === 'regular' ? 'selected' : '' ?>>Regular</option>
            <option value="irregular" <?= $typeFilter === 'irregular' ? 'selected' : '' ?>>Irregular</option>
        </select>
        <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
    </form>
    <a href="<?= BASE_URL ?>/students/create.php" 
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors whitespace-nowrap">
        <i class="fas fa-plus text-xs"></i>
        <span>Add Student</span>
    </a>
</div>

<!-- Students Table -->
<div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-mono-200 dark:border-mono-800 bg-mono-50 dark:bg-mono-800/50">
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Student</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden sm:table-cell">ID Number</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Type</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden md:table-cell">Subjects</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden lg:table-cell">Year</th>
                    <th class="text-right px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mono-100 dark:divide-mono-800">
                <?php if (empty($students)): ?>
                <tr><td colspan="6" class="px-5 py-12 text-center text-mono-400">
                    <i class="fas fa-users text-3xl text-mono-200 dark:text-mono-700 mb-3 block"></i>
                    No students found
                </td></tr>
                <?php else: ?>
                <?php foreach ($students as $student): ?>
                <tr class="hover:bg-mono-50 dark:hover:bg-mono-800/50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-semibold text-mono-500"><?= getInitials($student['first_name'] . ' ' . $student['last_name']) ?></span>
                            </div>
                            <div>
                                <a href="<?= BASE_URL ?>/students/profile.php?id=<?= $student['id'] ?>" class="font-medium hover:underline"><?= sanitize($student['last_name'] . ', ' . $student['first_name']) ?></a>
                                <?php if ($student['email']): ?>
                                <p class="text-[11px] text-mono-400"><?= sanitize($student['email']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-mono-500 hidden sm:table-cell font-mono text-xs"><?= sanitize($student['student_id']) ?></td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full uppercase tracking-wider
                            <?= $student['student_type'] === 'regular' ? 'bg-mono-100 dark:bg-mono-800 text-mono-600 dark:text-mono-400' : 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' ?>">
                            <?= $student['student_type'] ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-mono-400 hidden md:table-cell"><?= $student['subject_count'] ?></td>
                    <td class="px-5 py-3 text-mono-400 hidden lg:table-cell"><?= $student['year_level'] ?><?= $student['section'] ? ' - ' . sanitize($student['section']) : '' ?></td>
                    <td class="px-5 py-3 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="<?= BASE_URL ?>/students/profile.php?id=<?= $student['id'] ?>" class="p-1.5 rounded-md text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors" title="View Profile">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="<?= BASE_URL ?>/students/edit.php?id=<?= $student['id'] ?>" class="p-1.5 rounded-md text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </a>
                            <form method="POST" action="<?= BASE_URL ?>/students/delete.php" onsubmit="return confirm('Are you sure?')" class="inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= $student['id'] ?>">
                                <button type="submit" class="p-1.5 rounded-md text-mono-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Delete">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($students)): ?>
    <div class="border-t border-mono-200 dark:border-mono-800 px-5 py-3">
        <p class="text-xs text-mono-400"><?= count($students) ?> student(s) found</p>
    </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
