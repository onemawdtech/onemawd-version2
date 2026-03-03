<?php
/**
 * eClass - Subject Enrollment Management
 */
$pageTitle = 'Manage Enrollment';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
$stmt->execute([$id]);
$subject = $stmt->fetch();

if (!$subject) {
    setFlash('error', 'Subject not found.');
    redirect('/subjects/');
}

// Teachers can only manage enrollment for their own subjects
if (isTeacher() && $subject['teacher_id'] != $_SESSION['user_id']) {
    setFlash('error', 'Access denied. This subject is not assigned to you.');
    redirect('/subjects/');
}

$pageSubtitle = $subject['subject_code'] . ' — ' . $subject['subject_name'];

// Handle enrollment toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'enroll') {
        $studentIds = $_POST['student_ids'] ?? [];
        $enrolled = 0;
        foreach ($studentIds as $sid) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subject_enrollments (subject_id, student_id) VALUES (?, ?)");
                $stmt->execute([$id, (int)$sid]);
                $enrolled++;
            } catch (PDOException $e) {
                // Already enrolled, skip
            }
        }
        setFlash('success', "$enrolled student(s) enrolled successfully.");
    } elseif ($action === 'unenroll') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM subject_enrollments WHERE subject_id = ? AND student_id = ?");
        $stmt->execute([$id, $sid]);
        setFlash('success', 'Student removed from subject.');
    }
    redirect('/subjects/enroll.php?id=' . $id);
}

// Get enrolled students
$enrolled = $pdo->prepare("
    SELECT s.* FROM students s
    JOIN subject_enrollments se ON s.id = se.student_id
    WHERE se.subject_id = ?
    ORDER BY s.last_name, s.first_name
");
$enrolled->execute([$id]);
$enrolledStudents = $enrolled->fetchAll();
$enrolledIds = array_column($enrolledStudents, 'id');

// Get available students
$officerSection = getUserSection();
if (hasSectionScope()) {
    $availStmt = $pdo->prepare("SELECT * FROM students WHERE status = 'active' AND section = ? ORDER BY last_name, first_name");
    $availStmt->execute([$officerSection]);
    $available = $availStmt->fetchAll();
} else {
    $available = $pdo->query("SELECT * FROM students WHERE status = 'active' ORDER BY last_name, first_name")->fetchAll();
}
$availableStudents = array_filter($available, fn($s) => !in_array($s['id'], $enrolledIds));

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-4xl">
    <a href="<?= BASE_URL ?>/subjects/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Subjects
    </a>

    <!-- Subject Info Card -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
            <div class="w-12 h-12 rounded-xl bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-book text-mono-500 dark:text-mono-400"></i>
            </div>
            <div class="flex-1">
                <h2 class="text-base font-semibold"><?= sanitize($subject['subject_name']) ?></h2>
                <div class="flex flex-wrap gap-3 mt-1 text-xs text-mono-400">
                    <span><i class="fas fa-hashtag mr-1"></i><?= sanitize($subject['subject_code']) ?></span>
                    <?php if ($subject['schedule_day']): ?><span><i class="fas fa-calendar mr-1"></i><?= sanitize($subject['schedule_day']) ?></span><?php endif; ?>
                    <?php if ($subject['schedule_time']): ?><span><i class="fas fa-clock mr-1"></i><?= sanitize($subject['schedule_time']) ?></span><?php endif; ?>
                    <?php if ($subject['room']): ?><span><i class="fas fa-door-open mr-1"></i><?= sanitize($subject['room']) ?></span><?php endif; ?>
                </div>
            </div>
            <span class="text-sm font-semibold text-mono-900 dark:text-mono-100"><?= count($enrolledStudents) ?> enrolled</span>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <!-- Enrolled Students -->
        <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
            <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
                <h3 class="text-sm font-semibold">Enrolled Students (<?= count($enrolledStudents) ?>)</h3>
            </div>
            <div class="divide-y divide-mono-100 dark:divide-mono-800 max-h-[500px] overflow-y-auto scrollbar-thin">
                <?php if (empty($enrolledStudents)): ?>
                <div class="px-5 py-8 text-center">
                    <p class="text-sm text-mono-400">No students enrolled yet</p>
                </div>
                <?php else: ?>
                <?php foreach ($enrolledStudents as $student): ?>
                <div class="flex items-center justify-between px-5 py-3 group">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                            <span class="text-[10px] font-semibold text-mono-500"><?= getInitials($student['first_name'] . ' ' . $student['last_name']) ?></span>
                        </div>
                        <div>
                            <p class="text-sm font-medium"><?= sanitize($student['first_name'] . ' ' . $student['last_name']) ?></p>
                            <p class="text-[11px] text-mono-400"><?= sanitize($student['student_id']) ?></p>
                        </div>
                    </div>
                    <form method="POST" class="opacity-0 group-hover:opacity-100 transition-opacity">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="unenroll">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                        <button type="submit" class="p-1.5 rounded-md text-mono-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Remove">
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Students -->
        <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
            <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
                <h3 class="text-sm font-semibold">Add Students</h3>
            </div>
            <?php if (empty($availableStudents)): ?>
            <div class="px-5 py-8 text-center">
                <p class="text-sm text-mono-400">All students are already enrolled</p>
            </div>
            <?php else: ?>
            <form method="POST" x-data="{ selected: [] }">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="enroll">
                <div class="divide-y divide-mono-100 dark:divide-mono-800 max-h-[400px] overflow-y-auto scrollbar-thin">
                    <?php foreach ($availableStudents as $student): ?>
                    <label class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-mono-50 dark:hover:bg-mono-800/50 transition-colors">
                        <input type="checkbox" name="student_ids[]" value="<?= $student['id'] ?>"
                               x-model="selected"
                               class="w-4 h-4 rounded border-mono-300 dark:border-mono-600 text-mono-900 dark:text-mono-100 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <div class="w-8 h-8 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                            <span class="text-[10px] font-semibold text-mono-500"><?= getInitials($student['first_name'] . ' ' . $student['last_name']) ?></span>
                        </div>
                        <div>
                            <p class="text-sm font-medium"><?= sanitize($student['first_name'] . ' ' . $student['last_name']) ?></p>
                            <p class="text-[11px] text-mono-400"><?= sanitize($student['student_id']) ?> · <?= ucfirst($student['student_type']) ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="border-t border-mono-200 dark:border-mono-800 px-5 py-3 flex items-center justify-between">
                    <span class="text-xs text-mono-400" x-text="selected.length + ' selected'"></span>
                    <button type="submit" x-show="selected.length > 0"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-xs font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                        <i class="fas fa-plus text-[10px]"></i> Enroll Selected
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
