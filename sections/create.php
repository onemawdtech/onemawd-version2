<?php
/**
 * eClass - Create Section
 */
$pageTitle = 'Add Section';
$pageSubtitle = 'Create a new class section';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = strtoupper(trim($_POST['section_name'] ?? ''));
    $desc = trim($_POST['description'] ?? '');
    $yearLevel = (int)($_POST['year_level'] ?? 0) ?: null;

    if (empty($name)) {
        setFlash('error', 'Section name is required.');
    } else {
        $check = $pdo->prepare("SELECT id FROM sections WHERE section_name = ?");
        $check->execute([$name]);
        if ($check->fetch()) {
            setFlash('error', 'Section name already exists.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO sections (section_name, description, year_level) VALUES (?, ?, ?)");
            $stmt->execute([$name, $desc ?: null, $yearLevel]);
            setFlash('success', 'Section "' . $name . '" created successfully.');
            redirect('/sections/');
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-lg">
    <a href="<?= BASE_URL ?>/sections/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Sections
    </a>

    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <form method="POST" class="p-5 sm:p-6 space-y-4">
            <?= csrfField() ?>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Section Name <span class="text-red-500">*</span></label>
                <input type="text" name="section_name" required placeholder="e.g. A, B, BSIT-3A" style="text-transform:uppercase"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Description</label>
                <input type="text" name="description" placeholder="e.g. Bachelor of Science in IT - 3rd Year Block A"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Year Level</label>
                <select name="year_level" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="">Not specified</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                    <option value="5">5th Year</option>
                </select>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/sections/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Create Section
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
