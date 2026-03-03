<?php
/**
 * eClass - Attendance List
 */
$pageTitle = 'Attendance';
$pageSubtitle = 'Track per-subject attendance';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

// Get subjects for filter
$officerSection = getUserSection();
$sectionScoped = hasSectionScope();

if (isTeacher()) {
    $allSubjects = $pdo->prepare("SELECT * FROM subjects WHERE status='active' AND teacher_id = ? ORDER BY subject_code");
    $allSubjects->execute([$_SESSION['user_id']]);
    $allSubjects = $allSubjects->fetchAll();
} elseif ($sectionScoped) {
    $allSubjects = $pdo->prepare("SELECT DISTINCT s.* FROM subjects s JOIN subject_enrollments se ON s.id = se.subject_id JOIN students st ON se.student_id = st.id WHERE s.status='active' AND st.section = ? ORDER BY s.subject_code");
    $allSubjects->execute([$officerSection]);
    $allSubjects = $allSubjects->fetchAll();
} else {
    $allSubjects = $pdo->query("SELECT * FROM subjects WHERE status='active' ORDER BY subject_code")->fetchAll();
}

$subjectFilter = (int)($_GET['subject_id'] ?? 0);
$dateFilter = $_GET['date'] ?? '';

$where = "WHERE 1=1";
$params = [];

// Scope attendance sessions
if (isTeacher()) {
    $teacherSubjectIds = getTeacherSubjectIds();
    if (!empty($teacherSubjectIds)) {
        $placeholders = implode(',', array_fill(0, count($teacherSubjectIds), '?'));
        $where .= " AND a_s.subject_id IN ($placeholders)";
        $params = array_merge($params, $teacherSubjectIds);
    } else {
        $where .= " AND 1=0";
    }
} elseif ($sectionScoped) {
    $where .= " AND a_s.id IN (SELECT ar2.session_id FROM attendance_records ar2 JOIN students st2 ON ar2.student_id = st2.id WHERE st2.section = ?)";
    $params[] = $officerSection;
}

if ($subjectFilter) {
    $where .= " AND a_s.subject_id = ?";
    $params[] = $subjectFilter;
}
if ($dateFilter) {
    $where .= " AND a_s.session_date = ?";
    $params[] = $dateFilter;
}

$stmt = $pdo->prepare("
    SELECT a_s.*, s.subject_code, s.subject_name,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id) as total,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id AND ar.status = 'present') as present_count,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id AND ar.status = 'absent') as absent_count,
           (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id AND ar.status = 'late') as late_count
    FROM attendance_sessions a_s
    JOIN subjects s ON a_s.subject_id = s.id
    $where
    ORDER BY a_s.session_date DESC, a_s.created_at DESC
");
$stmt->execute($params);
$sessions = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<!-- Actions -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
        <select name="subject_id" onchange="this.form.submit()" class="px-3 py-2 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-white dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            <option value="">All Subjects</option>
            <?php foreach ($allSubjects as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $subjectFilter == $s['id'] ? 'selected' : '' ?>><?= sanitize($s['subject_code']) ?> — <?= sanitize($s['subject_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" value="<?= sanitize($dateFilter) ?>" onchange="this.form.submit()"
               class="px-3 py-2 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-white dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
    </form>
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/attendance/report.php" 
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-mono-200 dark:border-mono-700 text-sm font-medium hover:bg-mono-50 dark:hover:bg-mono-800 transition-colors whitespace-nowrap">
            <i class="fas fa-file-pdf text-xs text-red-500"></i>
            <span>Report</span>
        </a>
        <a href="<?= BASE_URL ?>/attendance/create.php" 
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors whitespace-nowrap">
            <i class="fas fa-plus text-xs"></i>
            <span>New Session</span>
        </a>
    </div>
</div>

<!-- Sessions List -->
<?php if (empty($sessions)): ?>
<div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 px-6 py-16 text-center">
    <i class="fas fa-clipboard-check text-4xl text-mono-200 dark:text-mono-700 mb-3"></i>
    <p class="text-mono-400 text-sm mb-4">No attendance sessions found</p>
    <a href="<?= BASE_URL ?>/attendance/create.php" class="inline-flex items-center gap-2 text-sm font-medium text-mono-900 dark:text-mono-100 hover:underline">
        <i class="fas fa-plus text-xs"></i> Create your first session
    </a>
</div>
<?php else: ?>
<div class="space-y-3">
    <?php foreach ($sessions as $session): 
        $rate = $session['total'] > 0 ? round(($session['present_count'] / $session['total']) * 100) : 0;
    ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 hover:border-mono-300 dark:hover:border-mono-700 transition-colors">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-5 gap-3">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-mono-100 dark:bg-mono-800 flex flex-col items-center justify-center flex-shrink-0">
                    <span class="text-[10px] font-semibold uppercase text-mono-400"><?= date('M', strtotime($session['session_date'])) ?></span>
                    <span class="text-lg font-bold leading-none"><?= date('d', strtotime($session['session_date'])) ?></span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded bg-mono-100 dark:bg-mono-800 text-mono-500 dark:text-mono-400"><?= sanitize($session['subject_code']) ?></span>
                        <span class="text-sm font-medium"><?= sanitize($session['subject_name']) ?></span>
                    </div>
                    <?php if ($session['notes']): ?>
                    <p class="text-xs text-mono-400 mt-1"><?= sanitize($session['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-4 sm:gap-6">
                <div class="flex items-center gap-3 text-xs">
                    <span class="flex items-center gap-1 text-mono-900 dark:text-mono-100 font-medium">
                        <span class="w-2 h-2 rounded-full bg-mono-900 dark:bg-mono-100"></span> <?= $session['present_count'] ?>
                    </span>
                    <span class="flex items-center gap-1 text-mono-400">
                        <span class="w-2 h-2 rounded-full bg-mono-400"></span> <?= $session['late_count'] ?>
                    </span>
                    <span class="flex items-center gap-1 text-red-400">
                        <span class="w-2 h-2 rounded-full bg-red-400"></span> <?= $session['absent_count'] ?>
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-lg font-bold"><?= $rate ?>%</span>
                </div>
                <a href="<?= BASE_URL ?>/attendance/session.php?id=<?= $session['id'] ?>" class="p-2 rounded-lg text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 hover:bg-mono-100 dark:hover:bg-mono-800 transition-colors">
                    <i class="fas fa-chevron-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
