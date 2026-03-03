<?php
/**
 * eClass - Create Account
 */
$pageTitle = 'Add Account';
$pageSubtitle = 'Create a new account';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

// Get active sections
$sections = $pdo->query("SELECT section_name FROM sections WHERE status = 'active' ORDER BY section_name")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'officer';
    $section = trim($_POST['section'] ?? '');

    if (empty($name) || empty($username) || empty($password)) {
        setFlash('error', 'All fields are required.');
    } elseif (strlen($password) < 6) {
        setFlash('error', 'Password must be at least 6 characters.');
    } elseif (in_array($role, ['officer', 'teacher']) && empty($section)) {
        setFlash('error', 'Section is required for officer and teacher accounts.');
    } else {
        $check = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            setFlash('error', 'Username already in use.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $uuid = generateUuid();
            $stmt = $pdo->prepare("INSERT INTO accounts (uuid, full_name, username, password, role, section) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$uuid, $name, $username, $hash, $role, (in_array($role, ['officer', 'teacher']) ? $section : null)]);
            setFlash('success', 'Account created successfully.');
            redirect('/accounts/');
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-lg">
    <a href="<?= BASE_URL ?>/accounts/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Accounts
    </a>

    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <form method="POST" class="p-5 sm:p-6 space-y-4" x-data="{ role: 'officer' }">
            <?= csrfField() ?>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" required placeholder="e.g. Juan Dela Cruz"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" required placeholder="e.g. jdelacruz"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" required minlength="6" placeholder="Minimum 6 characters"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Role</label>
                <select name="role" x-model="role" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="officer">Officer</option>
                    <option value="teacher">Teacher</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div x-show="role === 'officer' || role === 'teacher'" x-cloak>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Assigned Section <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <select name="section" class="flex-1 px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <option value="">Select section...</option>
                        <?php foreach ($sections as $sec): ?>
                        <option value="<?= sanitize($sec) ?>"><?= sanitize($sec) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="text-[10px] text-mono-400 mt-1">This account will only access records from this section</p>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/accounts/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
