<?php
/**
 * eClass - Edit Student
 */
$pageTitle = 'Edit Student';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect('/students/');
}

$pageSubtitle = $student['first_name'] . ' ' . $student['last_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $type = $_POST['student_type'] ?? 'regular';
    $year = (int)($_POST['year_level'] ?? 1);
    $section = trim($_POST['section'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($firstName) || empty($lastName)) {
        setFlash('error', 'First name and last name are required.');
    } else {
        $stmt = $pdo->prepare("UPDATE students SET first_name=?, last_name=?, email=?, phone=?, student_type=?, year_level=?, section=?, status=? WHERE id=?");
        $stmt->execute([$firstName, $lastName, $email ?: null, $phone ?: null, $type, $year, $section ?: null, $status, $id]);
        setFlash('success', 'Student updated successfully.');
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
                    <div class="flex items-center gap-2 w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-100 dark:bg-mono-800 text-mono-500">
                        <i class="fas fa-hashtag text-[10px] text-mono-400"></i>
                        <?= sanitize($student['student_id']) ?>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required value="<?= sanitize($student['first_name']) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required value="<?= sanitize($student['last_name']) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Email</label>
                    <input type="email" name="email" value="<?= sanitize($student['email']) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Phone</label>
                    <input type="tel" name="phone" value="<?= sanitize($student['phone']) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
            </div>

            <div class="border-t border-mono-100 dark:border-mono-800 pt-5">
                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Student Type</label>
                        <select name="student_type" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <option value="regular" <?= $student['student_type'] === 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="irregular" <?= $student['student_type'] === 'irregular' ? 'selected' : '' ?>>Irregular</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Year Level</label>
                        <select name="year_level" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= $student['year_level'] == $i ? 'selected' : '' ?>><?= $i ?><?= ['st','nd','rd','th','th'][$i-1] ?> Year</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Section</label>
                        <?php $activeSections = $pdo->query("SELECT section_name FROM sections WHERE status='active' ORDER BY section_name")->fetchAll(PDO::FETCH_COLUMN); ?>
                        <select name="section" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <option value="">Select section...</option>
                            <?php foreach ($activeSections as $sec): ?>
                            <option value="<?= sanitize($sec) ?>" <?= $student['section'] === $sec ? 'selected' : '' ?>><?= sanitize($sec) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Status</label>
                <select name="status" class="w-full sm:w-48 px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="active" <?= $student['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $student['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/students/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-save text-xs"></i> Update Student
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
