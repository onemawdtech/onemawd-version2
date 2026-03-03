<?php
/**
 * eClass - Edit Subject
 */
$pageTitle = 'Edit Subject';
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

// Teachers can only edit their own subjects
if (isTeacher() && $subject['teacher_id'] != $_SESSION['user_id']) {
    setFlash('error', 'Access denied. This subject is not assigned to you.');
    redirect('/subjects/');
}

$pageSubtitle = $subject['subject_code'] . ' — ' . $subject['subject_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['subject_code'] ?? '');
    $name = trim($_POST['subject_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $day = trim($_POST['schedule_day'] ?? '');
    $time = trim($_POST['schedule_time'] ?? '');
    $room = trim($_POST['room'] ?? '');
    $status = $_POST['status'] ?? 'active';

    if (empty($code) || empty($name)) {
        setFlash('error', 'Subject code and name are required.');
    } else {
        $teacherId = isAdmin() ? (int)($_POST['teacher_id'] ?? 0) ?: null : $subject['teacher_id'];
        $stmt = $pdo->prepare("UPDATE subjects SET subject_code=?, subject_name=?, description=?, schedule_day=?, schedule_time=?, room=?, teacher_id=?, status=? WHERE id=?");
        $stmt->execute([$code, $name, $desc ?: null, $day ?: null, $time ?: null, $room ?: null, $teacherId, $status, $id]);
        setFlash('success', 'Subject updated successfully.');
        redirect('/subjects/');
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-2xl">
    <a href="<?= BASE_URL ?>/subjects/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Subjects
    </a>

    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <form method="POST" class="p-5 sm:p-6 space-y-5">
            <?= csrfField() ?>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Subject Code <span class="text-red-500">*</span></label>
                    <input type="text" name="subject_code" required value="<?= sanitize($subject['subject_code']) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Subject Name <span class="text-red-500">*</span></label>
                    <input type="text" name="subject_name" required value="<?= sanitize($subject['subject_name']) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Description</label>
                <textarea name="description" rows="3"
                          class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100 resize-none"><?= sanitize($subject['description']) ?></textarea>
            </div>

            <?php if (isAdmin()): ?>
            <?php $teachers = $pdo->query("SELECT id, full_name FROM accounts WHERE role='teacher' ORDER BY full_name")->fetchAll(); ?>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Assigned Teacher</label>
                <select name="teacher_id" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="">No teacher assigned</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $subject['teacher_id'] == $t['id'] ? 'selected' : '' ?>><?= sanitize($t['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="border-t border-mono-100 dark:border-mono-800 pt-5">
                <p class="text-xs font-medium text-mono-500 dark:text-mono-400 mb-3"><i class="fas fa-clock mr-1"></i> Schedule (Optional)</p>
                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-mono-400 mb-1.5">Day</label>
                        <select name="schedule_day" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <option value="">Select day</option>
                            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','MWF','TTh'] as $d): ?>
                            <option <?= $subject['schedule_day'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-mono-400 mb-1.5">Time</label>
                        <input type="text" name="schedule_time" value="<?= sanitize($subject['schedule_time']) ?>"
                               class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    </div>
                    <div>
                        <label class="block text-xs text-mono-400 mb-1.5">Room</label>
                        <input type="text" name="room" value="<?= sanitize($subject['room']) ?>"
                               class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Status</label>
                <select name="status" class="w-full sm:w-48 px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="active" <?= $subject['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="archived" <?= $subject['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/subjects/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-save text-xs"></i> Update Subject
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
