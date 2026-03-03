<?php
/**
 * eClass - Edit Section
 */
$pageTitle = 'Edit Section';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ?");
$stmt->execute([$id]);
$section = $stmt->fetch();

if (!$section) {
    setFlash('error', 'Section not found.');
    redirect('/sections/');
}

$pageSubtitle = $section['section_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = strtoupper(trim($_POST['section_name'] ?? ''));
    $desc = trim($_POST['description'] ?? '');
    $yearLevel = (int)($_POST['year_level'] ?? 0) ?: null;
    $status = $_POST['status'] ?? 'active';
    $oldName = $section['section_name'];

    if (empty($name)) {
        setFlash('error', 'Section name is required.');
    } else {
        $check = $pdo->prepare("SELECT id FROM sections WHERE section_name = ? AND id != ?");
        $check->execute([$name, $id]);
        if ($check->fetch()) {
            setFlash('error', 'Section name already exists.');
        } else {
            $pdo->beginTransaction();
            try {
                // Update section
                $stmt = $pdo->prepare("UPDATE sections SET section_name=?, description=?, year_level=?, status=? WHERE id=?");
                $stmt->execute([$name, $desc ?: null, $yearLevel, $status, $id]);

                // If name changed, update all references
                if ($name !== $oldName) {
                    $pdo->prepare("UPDATE students SET section = ? WHERE section = ?")->execute([$name, $oldName]);
                    $pdo->prepare("UPDATE accounts SET section = ? WHERE section = ?")->execute([$name, $oldName]);
                }

                $pdo->commit();
                setFlash('success', 'Section updated successfully.');
                redirect('/sections/');
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('error', 'Failed to update section.');
            }
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
                <input type="text" name="section_name" required value="<?= sanitize($section['section_name']) ?>" style="text-transform:uppercase"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Description</label>
                <input type="text" name="description" value="<?= sanitize($section['description']) ?>"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Year Level</label>
                <select name="year_level" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="">Not specified</option>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <option value="<?= $i ?>" <?= $section['year_level'] == $i ? 'selected' : '' ?>><?= $i ?><?= ['st','nd','rd','th','th'][$i-1] ?> Year</option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Status</label>
                <select name="status" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="active" <?= $section['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $section['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/sections/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-save text-xs"></i> Update Section
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
