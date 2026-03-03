<?php
/**
 * eClass - Edit Account
 */
$pageTitle = 'Edit Account';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

// Get active sections
$sections = $pdo->query("SELECT section_name FROM sections WHERE status = 'active' ORDER BY section_name")->fetchAll(PDO::FETCH_COLUMN);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$id]);
$account = $stmt->fetch();

if (!$account) {
    setFlash('error', 'Account not found.');
    redirect('/accounts/');
}

$pageSubtitle = $account['full_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'officer';
    $section = trim($_POST['section'] ?? '');

    if (empty($name) || empty($username)) {
        setFlash('error', 'Name and username are required.');
    } elseif (in_array($role, ['officer', 'teacher']) && empty($section)) {
        setFlash('error', 'Section is required for officer and teacher accounts.');
    } else {
        $check = $pdo->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            setFlash('error', 'Username already in use.');
        } else {
            $sectionVal = in_array($role, ['officer', 'teacher']) ? $section : null;
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    setFlash('error', 'Password must be at least 6 characters.');
                    redirect('/accounts/edit.php?id=' . $id);
                    exit;
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE accounts SET full_name=?, username=?, password=?, role=?, section=? WHERE id=?");
                $stmt->execute([$name, $username, $hash, $role, $sectionVal, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE accounts SET full_name=?, username=?, role=?, section=? WHERE id=?");
                $stmt->execute([$name, $username, $role, $sectionVal, $id]);
            }
            setFlash('success', 'Account updated.');
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
        <form method="POST" class="p-5 sm:p-6 space-y-4" x-data="{ role: '<?= $account['role'] ?>' }">
            <?= csrfField() ?>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" required value="<?= sanitize($account['full_name']) ?>"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" required value="<?= sanitize($account['username']) ?>"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">New Password</label>
                <input type="password" name="password" minlength="6" placeholder="Leave blank to keep current"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                <p class="text-[10px] text-mono-400 mt-1">Leave empty to keep current password</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Role</label>
                <select name="role" x-model="role" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="officer" <?= $account['role'] === 'officer' ? 'selected' : '' ?>>Officer</option>
                    <option value="teacher" <?= $account['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                    <option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div x-show="role === 'officer' || role === 'teacher'" x-cloak>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Assigned Section <span class="text-red-500">*</span></label>
                <select name="section" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    <option value="">Select section...</option>
                    <?php foreach ($sections as $sec): ?>
                    <option value="<?= sanitize($sec) ?>" <?= $account['section'] === $sec ? 'selected' : '' ?>><?= sanitize($sec) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-mono-400 mt-1">This account will only access records from this section</p>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/accounts/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-save text-xs"></i> Update Account
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
