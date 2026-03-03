<?php
/**
 * eClass - Create Fund
 */
$pageTitle = 'Create Fund';
$pageSubtitle = 'Set up a new class fund';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$officerSection = getUserSection();
$sectionScoped = hasSectionScope();

if ($sectionScoped) {
    $stmt = $pdo->prepare("SELECT DISTINCT s.* FROM subjects s JOIN subject_enrollments se ON s.id = se.subject_id JOIN students st ON se.student_id = st.id WHERE s.status='active' AND st.section = ? ORDER BY s.subject_code");
    $stmt->execute([$officerSection]);
    $subjects = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM students WHERE status='active' AND section = ? ORDER BY last_name, first_name");
    $stmt->execute([$officerSection]);
    $students = $stmt->fetchAll();
} else {
    $subjects = $pdo->query("SELECT * FROM subjects WHERE status='active' ORDER BY subject_code")->fetchAll();
    $students = $pdo->query("SELECT * FROM students WHERE status='active' ORDER BY last_name, first_name")->fetchAll();
}

// Build subject → enrolled students map for live preview
$subjectStudentsMap = [];
foreach ($subjects as $subj) {
    if ($sectionScoped) {
        $enrollStmt = $pdo->prepare("SELECT st.id, st.student_id, st.first_name, st.last_name FROM subject_enrollments se JOIN students st ON se.student_id = st.id WHERE se.subject_id = ? AND st.status = 'active' AND st.section = ? ORDER BY st.last_name, st.first_name");
        $enrollStmt->execute([$subj['id'], $officerSection]);
    } else {
        $enrollStmt = $pdo->prepare("SELECT st.id, st.student_id, st.first_name, st.last_name FROM subject_enrollments se JOIN students st ON se.student_id = st.id WHERE se.subject_id = ? AND st.status = 'active' ORDER BY st.last_name, st.first_name");
        $enrollStmt->execute([$subj['id']]);
    }
    $subjectStudentsMap[$subj['id']] = $enrollStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['fund_name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $fundType = $_POST['fund_type'] ?? 'standard';
    $amount = (float)($_POST['amount'] ?? 0);
    $frequency = $_POST['frequency'] ?? 'one-time';
    $dueDate = $_POST['due_date'] ?? '';
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $assignMode = $_POST['assign_mode'] ?? 'manual';
    $selectedStudents = $_POST['student_ids'] ?? [];

    // General/voluntary funds don't require amount
    if ($fundType === 'general') {
        $amount = 0;
        $assignMode = 'none';
    } elseif ($fundType === 'voluntary') {
        $amount = 0; // no fixed amount
    }

    if (empty($name)) {
        setFlash('error', 'Fund name is required.');
    } elseif ($fundType === 'standard' && $amount <= 0) {
        setFlash('error', 'Amount per student is required for standard funds.');
    } else {
        // Create fund
        $stmt = $pdo->prepare("INSERT INTO funds (fund_name, fund_type, description, amount, frequency, due_date, subject_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $fundType, $desc ?: null, $amount, $frequency, $dueDate ?: null, $subjectId ?: null, $_SESSION['user_id']]);
        $fundId = $pdo->lastInsertId();

        // Assign students (skip for general funds)
        if ($fundType !== 'general') {
            if ($assignMode === 'subject' && $subjectId) {
                // Subject-linked fund: students are pulled dynamically from subject_enrollments in manage.php
                // No need to copy into fund_assignees — the subject_id on the fund is the source of truth
            } else {
                // Manual selection
                $assignStmt = $pdo->prepare("INSERT INTO fund_assignees (fund_id, student_id) VALUES (?, ?)");
                foreach ($selectedStudents as $sid) {
                    $assignStmt->execute([$fundId, (int)$sid]);
                }
            }
        }

        // Auto-create first billing period for recurring funds
        if ($frequency !== 'one-time') {
            $periodStart = date('Y-m-d');
            switch ($frequency) {
                case 'weekly':
                    $periodEnd = date('Y-m-d', strtotime('+7 days'));
                    $periodLabel = 'Week of ' . date('M d', strtotime($periodStart));
                    break;
                case 'monthly':
                    $periodEnd = date('Y-m-d', strtotime('+1 month'));
                    $periodLabel = date('F Y');
                    break;
                case 'semestral':
                    $periodEnd = date('Y-m-d', strtotime('+6 months'));
                    $periodLabel = date('M Y') . ' - ' . date('M Y', strtotime('+6 months'));
                    break;
                case 'annual':
                    $periodEnd = date('Y-m-d', strtotime('+1 year'));
                    $periodLabel = 'AY ' . date('Y') . '-' . date('Y', strtotime('+1 year'));
                    break;
                default:
                    $periodEnd = $dueDate ?: date('Y-m-d', strtotime('+1 month'));
                    $periodLabel = 'Period 1';
            }
            $stmt = $pdo->prepare("INSERT INTO fund_billing_periods (fund_id, period_label, period_start, period_end, due_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$fundId, $periodLabel, $periodStart, $periodEnd, $dueDate ?: $periodEnd]);
        }

        setFlash('success', 'Fund created successfully.');
        redirect('/funds/manage.php?id=' . $fundId);
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<script>
    window.__subjectStudents = <?= json_encode($subjectStudentsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="max-w-3xl" x-data="{
    assignMode: 'manual',
    fundType: 'standard',
    selectedSubject: '',
    subjectStudents: window.__subjectStudents || {},
    get enrolledStudents() { return this.selectedSubject ? (this.subjectStudents[this.selectedSubject] || []) : []; }
}">
    <a href="<?= BASE_URL ?>/funds/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Funds
    </a>

    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <form method="POST" class="p-5 sm:p-6 space-y-5">
            <?= csrfField() ?>
            <!-- Fund Type -->
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-2">Fund Type</label>
                <input type="hidden" name="fund_type" :value="fundType">
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" @click="fundType = 'standard'"
                            :class="fundType === 'standard' ? 'border-mono-900 dark:border-mono-100 bg-mono-50 dark:bg-mono-800 ring-1 ring-mono-900 dark:ring-mono-100' : 'border-mono-200 dark:border-mono-700 hover:border-mono-300 dark:hover:border-mono-600'"
                            class="flex flex-col items-center gap-1.5 px-3 py-3 rounded-lg border transition-all text-center">
                        <i class="fas fa-receipt text-sm" :class="fundType === 'standard' ? 'text-mono-900 dark:text-mono-100' : 'text-mono-400'"></i>
                        <span class="text-[11px] font-semibold" :class="fundType === 'standard' ? '' : 'text-mono-500'">Standard</span>
                        <span class="text-[9px] text-mono-400 leading-tight">Fixed amount per student</span>
                    </button>
                    <button type="button" @click="fundType = 'voluntary'"
                            :class="fundType === 'voluntary' ? 'border-amber-500 dark:border-amber-400 bg-amber-50 dark:bg-amber-900/10 ring-1 ring-amber-500 dark:ring-amber-400' : 'border-mono-200 dark:border-mono-700 hover:border-mono-300 dark:hover:border-mono-600'"
                            class="flex flex-col items-center gap-1.5 px-3 py-3 rounded-lg border transition-all text-center">
                        <i class="fas fa-hand-holding-heart text-sm" :class="fundType === 'voluntary' ? 'text-amber-500' : 'text-mono-400'"></i>
                        <span class="text-[11px] font-semibold" :class="fundType === 'voluntary' ? 'text-amber-700 dark:text-amber-400' : 'text-mono-500'">Voluntary</span>
                        <span class="text-[9px] text-mono-400 leading-tight">Students contribute freely</span>
                    </button>
                    <button type="button" @click="fundType = 'general'"
                            :class="fundType === 'general' ? 'border-blue-500 dark:border-blue-400 bg-blue-50 dark:bg-blue-900/10 ring-1 ring-blue-500 dark:ring-blue-400' : 'border-mono-200 dark:border-mono-700 hover:border-mono-300 dark:hover:border-mono-600'"
                            class="flex flex-col items-center gap-1.5 px-3 py-3 rounded-lg border transition-all text-center">
                        <i class="fas fa-piggy-bank text-sm" :class="fundType === 'general' ? 'text-blue-500' : 'text-mono-400'"></i>
                        <span class="text-[11px] font-semibold" :class="fundType === 'general' ? 'text-blue-700 dark:text-blue-400' : 'text-mono-500'">General</span>
                        <span class="text-[9px] text-mono-400 leading-tight">No specific target or students</span>
                    </button>
                </div>
            </div>

            <!-- Fund Details -->
            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Fund Name <span class="text-red-500">*</span></label>
                <input type="text" name="fund_name" required placeholder="e.g. Class T-Shirt Fund"
                       class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
            </div>

            <div>
                <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Description</label>
                <textarea name="description" rows="2" placeholder="Brief description..."
                          class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100 resize-none"></textarea>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div x-show="fundType === 'standard'" x-transition>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Amount per Student <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mono-400 text-xs">₱</span>
                        <input type="number" name="amount" :required="fundType === 'standard'" step="0.01" min="0.01" placeholder="0.00"
                               class="w-full pl-7 pr-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Frequency</label>
                    <select name="frequency" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <option value="one-time">One-Time</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="semestral">Semestral</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Due Date</label>
                    <input type="date" name="due_date"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
            </div>

            <!-- Assign Students (hidden for general funds) -->
            <div class="border-t border-mono-100 dark:border-mono-800 pt-5" x-show="fundType !== 'general'" x-transition>
                <p class="text-xs font-medium text-mono-500 dark:text-mono-400 mb-3"><i class="fas fa-users mr-1"></i> Assign Students</p>
                
                <div class="flex gap-2 mb-4">
                    <button type="button" @click="assignMode = 'manual'" 
                            :class="assignMode === 'manual' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'bg-mono-100 dark:bg-mono-800 text-mono-500'"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors">
                        Select Manually
                    </button>
                    <button type="button" @click="assignMode = 'subject'" 
                            :class="assignMode === 'subject' ? 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' : 'bg-mono-100 dark:bg-mono-800 text-mono-500'"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors">
                        By Subject
                    </button>
                </div>

                <input type="hidden" name="assign_mode" :value="assignMode">

                <!-- By Subject -->
                <div x-show="assignMode === 'subject'" x-cloak>
                    <select name="subject_id" x-model="selectedSubject" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <option value="">Select a subject...</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= sanitize($s['subject_code']) ?> — <?= sanitize($s['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Enrolled Students Preview -->
                    <template x-if="selectedSubject && enrolledStudents.length > 0">
                        <div class="mt-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-medium text-mono-500 dark:text-mono-400">
                                    <i class="fas fa-user-check mr-1 text-emerald-500"></i> 
                                    <span x-text="enrolledStudents.length"></span> student<span x-show="enrolledStudents.length !== 1">s</span> will be assigned
                                </p>
                            </div>
                            <div class="max-h-52 overflow-y-auto scrollbar-thin border border-mono-200 dark:border-mono-700 rounded-lg divide-y divide-mono-100 dark:divide-mono-800">
                                <template x-for="student in enrolledStudents" :key="student.id">
                                    <div class="flex items-center gap-3 px-4 py-2.5">
                                        <div class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/20 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-check text-[8px] text-emerald-500"></i>
                                        </div>
                                        <span class="text-sm" x-text="student.last_name + ', ' + student.first_name"></span>
                                        <span class="text-[10px] text-mono-400 ml-auto" x-text="student.student_id"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="selectedSubject && enrolledStudents.length === 0">
                        <div class="mt-3 px-4 py-4 text-center rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10">
                            <i class="fas fa-exclamation-triangle text-amber-500 mr-1"></i>
                            <span class="text-xs text-amber-600 dark:text-amber-400">No students enrolled in this subject.</span>
                        </div>
                    </template>
                    <template x-if="!selectedSubject">
                        <p class="text-[11px] text-mono-400 mt-1.5">Select a subject to see enrolled students.</p>
                    </template>
                </div>

                <!-- Manual Select -->
                <div x-show="assignMode === 'manual'" x-data="{ selectAll: false }">
                    <?php if (!empty($students)): ?>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs text-mono-400">
                            <span class="font-medium"><?= count($students) ?></span> students available
                        </p>
                        <button type="button" @click="selectAll = !selectAll; document.querySelectorAll('input[name=\'student_ids[]\']').forEach(cb => cb.checked = selectAll)"
                                class="text-[11px] font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">
                            <i class="fas" :class="selectAll ? 'fa-square' : 'fa-check-square'" class="mr-1"></i>
                            <span x-text="selectAll ? 'Deselect All' : 'Select All'"></span>
                        </button>
                    </div>
                    <?php endif; ?>
                    <div class="max-h-64 overflow-y-auto scrollbar-thin border border-mono-200 dark:border-mono-700 rounded-lg divide-y divide-mono-100 dark:divide-mono-800">
                        <?php if (empty($students)): ?>
                        <div class="px-4 py-6 text-center text-sm text-mono-400">No students available</div>
                        <?php else: ?>
                        <?php foreach ($students as $student): ?>
                        <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-mono-50 dark:hover:bg-mono-800/50 transition-colors">
                            <input type="checkbox" name="student_ids[]" value="<?= $student['id'] ?>"
                                   class="w-4 h-4 rounded border-mono-300 dark:border-mono-600 text-mono-900 dark:text-mono-100 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <span class="text-sm"><?= sanitize($student['last_name'] . ', ' . $student['first_name']) ?></span>
                            <span class="text-[10px] text-mono-400 ml-auto"><?= sanitize($student['student_id']) ?></span>
                        </label>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="<?= BASE_URL ?>/funds/" class="px-4 py-2 text-sm font-medium text-mono-500 hover:text-mono-700 dark:hover:text-mono-300 transition-colors">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-plus text-xs"></i> Create Fund
                </button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
