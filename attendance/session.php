<?php
/**
 * eClass - View/Edit Attendance Session (Redesigned UI)
 */
$pageTitle = 'Attendance Session';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT a_s.*, s.subject_code, s.subject_name
    FROM attendance_sessions a_s
    JOIN subjects s ON a_s.subject_id = s.id
    WHERE a_s.id = ?
");
$stmt->execute([$id]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Session not found.');
    redirect('/attendance/');
}

// Access check for teachers — only their assigned subjects
if (isTeacher()) {
    $checkTeacher = $pdo->prepare("SELECT teacher_id FROM subjects WHERE id = ?");
    $checkTeacher->execute([$session['subject_id']]);
    if ($checkTeacher->fetchColumn() != $_SESSION['user_id']) {
        setFlash('error', 'Access denied. This subject is not assigned to you.');
        redirect('/attendance/');
    }
} elseif (hasSectionScope()) {
    $checkAccess = $pdo->prepare("SELECT COUNT(*) FROM attendance_records ar JOIN students st ON ar.student_id = st.id WHERE ar.session_id = ? AND st.section = ?");
    $checkAccess->execute([$id, getUserSection()]);
    if ($checkAccess->fetchColumn() == 0) {
        setFlash('error', 'Access denied. This session has no students from your section.');
        redirect('/attendance/');
    }
}

$pageSubtitle = $session['subject_code'] . ' - ' . formatDate($session['session_date']);

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update') {
        $statuses = $_POST['status'] ?? [];
        foreach ($statuses as $studentId => $status) {
            $stmt = $pdo->prepare("UPDATE attendance_records SET status = ? WHERE session_id = ? AND student_id = ?");
            $stmt->execute([$status, $id, (int)$studentId]);
        }
        setFlash('success', 'Attendance updated.');
        redirect('/attendance/session.php?id=' . $id);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM attendance_sessions WHERE id = ?")->execute([$id]);
        setFlash('success', 'Session deleted.');
        redirect('/attendance/');
    }
}

// Get records
$records = $pdo->prepare("
    SELECT ar.*, st.first_name, st.last_name, st.student_id as sid
    FROM attendance_records ar
    JOIN students st ON ar.student_id = st.id
    WHERE ar.session_id = ?
    ORDER BY st.last_name, st.first_name
");
$records->execute([$id]);
$attendanceRecords = $records->fetchAll();

// Stats
$total = count($attendanceRecords);
$present = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'present'));
$late = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'late'));
$absent = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'absent'));
$excused = count(array_filter($attendanceRecords, fn($r) => $r['status'] === 'excused'));
$attendanceRate = $total > 0 ? round((($present + $late) / $total) * 100) : 0;

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-3xl"
     x-data="{
        search: '',
        editing: false,
        counts: { present: <?= $present ?>, late: <?= $late ?>, absent: <?= $absent ?>, excused: <?= $excused ?> },
        total: <?= $total ?>,
        updateCounts() {
            this.counts = { present: 0, late: 0, absent: 0, excused: 0 };
            document.querySelectorAll('#recordsForm input[type=radio]:checked').forEach(r => {
                if (this.counts.hasOwnProperty(r.value)) this.counts[r.value]++;
            });
        },
        matchSearch(name) {
            if (!this.search) return true;
            return name.toLowerCase().includes(this.search.toLowerCase());
        }
     }">
    <a href="<?= BASE_URL ?>/attendance/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Attendance
    </a>

    <!-- Session Info -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-md bg-mono-100 dark:bg-mono-800 text-mono-600 dark:text-mono-400"><?= sanitize($session['subject_code']) ?></span>
                    <h2 class="text-base font-bold"><?= sanitize($session['subject_name']) ?></h2>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-mono-400">
                    <span><i class="fas fa-calendar mr-1"></i> <?= formatDate($session['session_date'], 'l, F d, Y') ?></span>
                    <?php if ($session['notes']): ?>
                    <span><i class="fas fa-sticky-note mr-1"></i> <?= sanitize($session['notes']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <button @click="editing = !editing" type="button"
                        class="px-3 py-1.5 text-xs font-medium border border-mono-200 dark:border-mono-700 rounded-lg hover:bg-mono-50 dark:hover:bg-mono-800 transition-colors"
                        x-text="editing ? 'Cancel Edit' : 'Edit Attendance'">
                </button>
                <form method="POST" onsubmit="return confirm('Delete this session?')" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-500 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="mt-4 pt-4 border-t border-mono-100 dark:border-mono-800">
            <div class="grid grid-cols-5 gap-2 sm:gap-3">
                <div class="text-center p-2.5 rounded-lg bg-mono-50 dark:bg-mono-800">
                    <p class="text-lg font-bold"><?= $attendanceRate ?>%</p>
                    <p class="text-[9px] text-mono-400 uppercase tracking-wider font-medium">Rate</p>
                </div>
                <div class="text-center p-2.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/10">
                    <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400" x-text="counts.present"><?= $present ?></p>
                    <p class="text-[9px] text-emerald-500 dark:text-emerald-400 uppercase tracking-wider font-medium">Present</p>
                </div>
                <div class="text-center p-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/10">
                    <p class="text-lg font-bold text-amber-600 dark:text-amber-400" x-text="counts.late"><?= $late ?></p>
                    <p class="text-[9px] text-amber-500 dark:text-amber-400 uppercase tracking-wider font-medium">Late</p>
                </div>
                <div class="text-center p-2.5 rounded-lg bg-red-50 dark:bg-red-900/10">
                    <p class="text-lg font-bold text-red-600 dark:text-red-400" x-text="counts.absent"><?= $absent ?></p>
                    <p class="text-[9px] text-red-500 dark:text-red-400 uppercase tracking-wider font-medium">Absent</p>
                </div>
                <div class="text-center p-2.5 rounded-lg bg-blue-50 dark:bg-blue-900/10">
                    <p class="text-lg font-bold text-blue-500 dark:text-blue-400" x-text="counts.excused"><?= $excused ?></p>
                    <p class="text-[9px] text-blue-400 dark:text-blue-400 uppercase tracking-wider font-medium">Excused</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Records -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <form method="POST" id="recordsForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update">

            <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold">
                        Attendance Records
                        <span class="text-mono-400 font-normal ml-1"><?= $total ?> students</span>
                    </h3>
                    <div x-show="editing" x-cloak>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-xs font-semibold hover:bg-mono-800 dark:hover:bg-mono-200 transition-all active:scale-[0.98]">
                            <i class="fas fa-save text-[10px]"></i> Save Changes
                        </button>
                    </div>
                </div>
                <!-- Search -->
                <div class="mt-3 relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-mono-300 dark:text-mono-600 text-xs"></i>
                    <input type="text" x-model="search" placeholder="Search student name..."
                           class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
            </div>

            <div class="divide-y divide-mono-100 dark:divide-mono-800 max-h-[60vh] overflow-y-auto">
                <?php foreach ($attendanceRecords as $i => $record): 
                    $fullName = $record['last_name'] . ', ' . $record['first_name'];
                    $statusConfig = [
                        'present' => ['label' => 'P', 'full' => 'Present', 'color' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'border' => 'border-emerald-200 dark:border-emerald-800', 'active' => 'bg-emerald-500 border-emerald-500 text-white', 'ring' => 'ring-emerald-500/30'],
                        'late'    => ['label' => 'L', 'full' => 'Late',    'color' => 'bg-amber-500',   'text' => 'text-amber-600 dark:text-amber-400',     'bg' => 'bg-amber-50 dark:bg-amber-900/20',     'border' => 'border-amber-200 dark:border-amber-800',   'active' => 'bg-amber-500 border-amber-500 text-white',   'ring' => 'ring-amber-500/30'],
                        'absent'  => ['label' => 'A', 'full' => 'Absent',  'color' => 'bg-red-500',     'text' => 'text-red-600 dark:text-red-400',         'bg' => 'bg-red-50 dark:bg-red-900/20',         'border' => 'border-red-200 dark:border-red-800',       'active' => 'bg-red-500 border-red-500 text-white',       'ring' => 'ring-red-500/30'],
                        'excused' => ['label' => 'E', 'full' => 'Excused', 'color' => 'bg-blue-400',    'text' => 'text-blue-500 dark:text-blue-400',       'bg' => 'bg-blue-50 dark:bg-blue-900/20',       'border' => 'border-blue-200 dark:border-blue-800',     'active' => 'bg-blue-400 border-blue-400 text-white',     'ring' => 'ring-blue-400/30'],
                    ];
                    $currentStatus = $statusConfig[$record['status']] ?? $statusConfig['present'];
                ?>
                <div class="px-4 sm:px-5 py-3 transition-colors hover:bg-mono-50 dark:hover:bg-mono-800/30"
                     x-show="matchSearch('<?= addslashes($fullName) ?>')" x-cloak>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3">
                        <!-- Student Info -->
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <span class="text-[10px] text-mono-400 w-5 text-right flex-shrink-0"><?= $i + 1 ?></span>
                            <div class="w-9 h-9 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                                <span class="text-[10px] font-bold text-mono-500"><?= getInitials($record['first_name'] . ' ' . $record['last_name']) ?></span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium"><?= sanitize($fullName) ?></p>
                                <p class="text-[10px] text-mono-400"><?= sanitize($record['sid']) ?></p>
                            </div>
                        </div>

                        <!-- View Mode: Status Badge -->
                        <div x-show="!editing" class="self-end sm:self-auto">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold <?= $currentStatus['bg'] ?> <?= $currentStatus['text'] ?> <?= $currentStatus['border'] ?> border">
                                <span class="w-1.5 h-1.5 rounded-full <?= $currentStatus['color'] ?>"></span>
                                <?= $currentStatus['full'] ?>
                            </span>
                        </div>

                        <!-- Edit Mode: Status Buttons -->
                        <div x-show="editing" x-cloak class="flex items-center gap-1.5 flex-shrink-0 self-end sm:self-auto">
                            <?php foreach ($statusConfig as $val => $cfg): ?>
                            <label class="relative cursor-pointer" title="<?= $cfg['full'] ?>">
                                <input type="radio" name="status[<?= $record['student_id'] ?>]" value="<?= $val ?>"
                                       <?= $record['status'] === $val ? 'checked' : '' ?> class="peer sr-only" @change="updateCounts()">
                                <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl border-2 border-mono-200 dark:border-mono-700 flex items-center justify-center
                                            text-xs font-bold text-mono-400 transition-all duration-150
                                            peer-checked:<?= str_replace(' ', ' peer-checked:', $cfg['active']) ?>
                                            peer-checked:ring-2 peer-checked:<?= $cfg['ring'] ?>
                                            peer-checked:shadow-sm
                                            hover:border-mono-400 dark:hover:border-mono-500 active:scale-95">
                                    <?= $cfg['label'] ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Save bar (only in edit mode) -->
            <div x-show="editing" x-cloak class="border-t border-mono-200 dark:border-mono-800 p-4 sm:p-5 bg-mono-50/50 dark:bg-mono-800/20 rounded-b-xl">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 text-[10px] text-mono-400">
                        <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span><span x-text="counts.present"></span> Present</span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-1"></span><span x-text="counts.late"></span> Late</span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span><span x-text="counts.absent"></span> Absent</span>
                        <span><span class="inline-block w-2 h-2 rounded-full bg-blue-400 mr-1"></span><span x-text="counts.excused"></span> Excused</span>
                    </div>
                    <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-bold hover:bg-mono-800 dark:hover:bg-mono-200 transition-all active:scale-[0.98] shadow-sm">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
