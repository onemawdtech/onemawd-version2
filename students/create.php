<?php
/**
 * eClass - Create Student
 */
$pageTitle = 'Add Student';
$pageSubtitle = 'Register a new student';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $type = $_POST['student_type'] ?? 'regular';
    $year = (int)($_POST['year_level'] ?? 1);
    $section = trim($_POST['section'] ?? '');

    // Force section for officers/teachers
    if (hasSectionScope()) {
        $section = getUserSection();
    }

    if (empty($firstName) || empty($lastName)) {
        setFlash('error', 'First name and last name are required.');
    } else {
        // Auto-generate student ID: YYYY-NNNNN
        $currentYear = date('Y');
        $lastId = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
        $lastId->execute([$currentYear . '-%']);
        $lastRow = $lastId->fetch();
        if ($lastRow) {
            $lastSeq = (int)substr($lastRow['student_id'], strlen($currentYear) + 1);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }
        $studentId = $currentYear . '-' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO students (student_id, first_name, last_name, email, phone, student_type, year_level, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $firstName, $lastName, $email ?: null, $phone ?: null, $type, $year, $section ?: null]);
        setFlash('success', 'Student added successfully. ID: ' . $studentId);
        redirect('/students/');
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-2xl">
    <a href="<?= BASE_URL ?>/students/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Students
    </a>

    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <form method="POST" class="p-5 sm:p-6 space-y-5">
            <?= csrfField() ?>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Student ID</label>
                    <div class="flex items-center gap-2 px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-100 dark:bg-mono-800 text-mono-400">
                        <i class="fas fa-hashtag text-[10px]"></i>
                        <span>Auto-generated on save</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required placeholder="Juan"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required placeholder="Dela Cruz"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Email</label>
                    <input type="email" name="email" placeholder="student@email.com"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Phone</label>
                    <input type="tel" name="phone" placeholder="09XX-XXX-XXXX"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
            </div>

            <div class="border-t border-mono-100 dark:border-mono-800 pt-5">
                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Student Type</label>
                        <select name="student_type" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <option value="regular">Regular</option>
                            <option value="irregular">Irregular</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Year Level</label>
                        <select name="year_level" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                            <option value="5">5th Year</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Section</label>
                        <?php if (hasSectionScope()): ?>
                        <input type="hidden" name="section" value="<?= sanitize(getUserSection()) ?>">
                        <input type="text" disabled value="<?= sanitize(getUserSection()) ?>"
                               class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-100 dark:bg-mono-800 text-mono-500 cursor-not-allowed">
                        <?php else: ?>
                        <?php $activeSections = $pdo->query("SELECT section_name FROM sections WHERE status='active' ORDER BY section_name")->fetchAll(PDO::FETCH_COLUMN); ?>
                        <select name="section" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <option value="">Select section...</option>
                            <?php foreach ($activeSections as $sec): ?>
                            <option value="<?= sanitize($sec) ?>"><?= sanitize($sec) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/students/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Add Student
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
