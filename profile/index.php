<?php
/**
 * OneMAWD - User Profile Management
 */
$pageTitle = 'My Profile';
$pageSubtitle = 'Manage your account settings';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$account = $stmt->fetch();

if (!$account) {
    setFlash('error', 'Account not found.');
    redirect('/dashboard.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if (empty($fullName) || empty($username)) {
            setFlash('error', 'Full name and username are required.');
        } else {
            // Check username uniqueness (exclude self)
            $check = $pdo->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
            $check->execute([$username, $account['id']]);
            if ($check->fetch()) {
                setFlash('error', 'Username is already taken by another account.');
            } else {
                $stmt = $pdo->prepare("UPDATE accounts SET full_name = ?, username = ? WHERE id = ?");
                $stmt->execute([$fullName, $username, $account['id']]);
                setFlash('success', 'Profile updated successfully.');
                redirect('/profile/');
            }
        }
    }

    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            setFlash('error', 'All password fields are required.');
        } elseif (!password_verify($currentPassword, $account['password'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($newPassword) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($newPassword !== $confirmPassword) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE accounts SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $account['id']]);
            setFlash('success', 'Password changed successfully.');
            redirect('/profile/');
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-2xl space-y-6">

    <!-- Profile Card -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="p-5 sm:p-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-16 h-16 rounded-full bg-mono-900 dark:bg-mono-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-xl font-bold text-white dark:text-mono-900"><?= getInitials($account['full_name']) ?></span>
                </div>
                <div>
                    <h2 class="text-lg font-semibold"><?= sanitize($account['full_name']) ?></h2>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium capitalize
                            <?php if ($account['role'] === 'admin'): ?>bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-800
                            <?php elseif ($account['role'] === 'teacher'): ?>bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800
                            <?php else: ?>bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800<?php endif; ?>">
                            <i class="fas <?= $account['role'] === 'admin' ? 'fa-shield-alt' : ($account['role'] === 'teacher' ? 'fa-chalkboard-teacher' : 'fa-id-badge') ?> text-[9px]"></i>
                            <?= $account['role'] ?>
                        </span>
                        <?php if ($account['section']): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-mono-100 dark:bg-mono-800 text-mono-500 dark:text-mono-400 border border-mono-200 dark:border-mono-700">
                            <i class="fas fa-layer-group text-[9px]"></i>
                            <?= sanitize($account['section']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Account Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-lg bg-mono-50 dark:bg-mono-800/50">
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-mono-400 mb-0.5">Username</p>
                    <p class="text-sm font-medium"><i class="fas fa-at text-mono-300 dark:text-mono-600 mr-1.5 text-xs"></i><?= sanitize($account['username']) ?></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-mono-400 mb-0.5">Account ID</p>
                    <p class="text-sm font-medium font-mono"><i class="fas fa-fingerprint text-mono-300 dark:text-mono-600 mr-1.5 text-xs"></i><?= sanitize($account['uuid']) ?></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-mono-400 mb-0.5">Role</p>
                    <p class="text-sm font-medium capitalize"><i class="fas fa-user-tag text-mono-300 dark:text-mono-600 mr-1.5 text-xs"></i><?= $account['role'] ?></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wider font-semibold text-mono-400 mb-0.5">Section</p>
                    <p class="text-sm font-medium"><i class="fas fa-layer-group text-mono-300 dark:text-mono-600 mr-1.5 text-xs"></i><?= $account['section'] ? sanitize($account['section']) : '<span class="text-mono-400 italic">All sections</span>' ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800" x-data="{ open: false }">
        <button @click="open = !open" class="w-full flex items-center justify-between p-5 sm:px-6 text-left">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                    <i class="fas fa-user-edit text-mono-500 text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold">Edit Profile</h3>
                    <p class="text-[11px] text-mono-400">Update your name and username</p>
                </div>
            </div>
            <i class="fas fa-chevron-down text-mono-400 text-xs transition-transform duration-200" :class="open && 'rotate-180'"></i>
        </button>
        <div x-show="open" x-cloak x-collapse>
            <form method="POST" class="px-5 pb-5 sm:px-6 sm:pb-6 space-y-4 border-t border-mono-100 dark:border-mono-800 pt-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" required value="<?= sanitize($account['full_name']) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Username</label>
                    <input type="text" value="<?= sanitize($account['username']) ?>" disabled
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-100 dark:bg-mono-800/60 text-mono-400 dark:text-mono-500 cursor-not-allowed">
                    <p class="text-[10px] text-mono-400 mt-1">Username cannot be changed. Contact an admin if needed.</p>
                </div>
                <div class="flex items-center justify-end gap-3 pt-1">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                        <i class="fas fa-save text-xs"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800" x-data="{ open: false, showCurrent: false, showNew: false, showConfirm: false }">
        <button @click="open = !open" class="w-full flex items-center justify-between p-5 sm:px-6 text-left">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                    <i class="fas fa-lock text-mono-500 text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold">Change Password</h3>
                    <p class="text-[11px] text-mono-400">Update your account password</p>
                </div>
            </div>
            <i class="fas fa-chevron-down text-mono-400 text-xs transition-transform duration-200" :class="open && 'rotate-180'"></i>
        </button>
        <div x-show="open" x-cloak x-collapse>
            <form method="POST" class="px-5 pb-5 sm:px-6 sm:pb-6 space-y-4 border-t border-mono-100 dark:border-mono-800 pt-4">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Current Password <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="showCurrent ? 'text' : 'password'" name="current_password" required placeholder="Enter current password"
                               class="w-full px-3 py-2.5 pr-10 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <button type="button" @click="showCurrent = !showCurrent" class="absolute right-3 top-1/2 -translate-y-1/2 text-mono-400 hover:text-mono-600 dark:hover:text-mono-300">
                            <i class="fas text-xs" :class="showCurrent ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">New Password <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="showNew ? 'text' : 'password'" name="new_password" required minlength="6" placeholder="Minimum 6 characters"
                               class="w-full px-3 py-2.5 pr-10 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <button type="button" @click="showNew = !showNew" class="absolute right-3 top-1/2 -translate-y-1/2 text-mono-400 hover:text-mono-600 dark:hover:text-mono-300">
                            <i class="fas text-xs" :class="showNew ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Confirm New Password <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input :type="showConfirm ? 'text' : 'password'" name="confirm_password" required minlength="6" placeholder="Re-enter new password"
                               class="w-full px-3 py-2.5 pr-10 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-mono-400 hover:text-mono-600 dark:hover:text-mono-300">
                            <i class="fas text-xs" :class="showConfirm ? 'fa-eye-slash' : 'fa-eye'"></i>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 pt-1">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</button>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                        <i class="fas fa-key text-xs"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Account Activity / Info -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="p-5 sm:p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-9 h-9 rounded-lg bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                    <i class="fas fa-info-circle text-mono-500 text-sm"></i>
                </div>
                <h3 class="text-sm font-semibold">Account Information</h3>
            </div>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between py-2 border-b border-mono-100 dark:border-mono-800">
                    <span class="text-mono-400"><i class="fas fa-fingerprint mr-2 text-xs"></i>Account ID</span>
                    <span class="font-medium font-mono text-xs"><?= sanitize($account['uuid']) ?></span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-mono-100 dark:border-mono-800">
                    <span class="text-mono-400"><i class="fas fa-user-tag mr-2 text-xs"></i>Role</span>
                    <span class="font-medium capitalize"><?= $account['role'] ?></span>
                </div>
                <div class="flex items-center justify-between py-2 border-b border-mono-100 dark:border-mono-800">
                    <span class="text-mono-400"><i class="fas fa-layer-group mr-2 text-xs"></i>Section</span>
                    <span class="font-medium"><?= $account['section'] ? sanitize($account['section']) : 'All sections (Global)' ?></span>
                </div>
                <div class="flex items-center justify-between py-2">
                    <span class="text-mono-400"><i class="fas fa-code-branch mr-2 text-xs"></i>System</span>
                    <span class="font-medium"><?= APP_NAME ?> v<?= APP_VERSION ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout -->
    <div class="pb-2">
        <a href="<?= BASE_URL ?>/auth/logout.php" 
           class="flex items-center justify-center gap-2 w-full px-5 py-3 rounded-xl border-2 border-red-200 dark:border-red-800/50 text-red-500 dark:text-red-400 text-sm font-semibold hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors"
           onclick="return confirm('Are you sure you want to log out?')">
            <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
