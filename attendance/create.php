<?php
/**
 * eClass - Create Attendance Session (Redesigned UI)
 */
$pageTitle = 'New Attendance Session';
$pageSubtitle = 'Mark attendance for a subject';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

$officerSection = getUserSection();
$sectionScoped = hasSectionScope();

if (isTeacher()) {
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE status='active' AND teacher_id = ? ORDER BY subject_code");
    $stmt->execute([$_SESSION['user_id']]);
    $subjects = $stmt->fetchAll();
} elseif ($sectionScoped) {
    $stmt = $pdo->prepare("SELECT DISTINCT s.* FROM subjects s JOIN subject_enrollments se ON s.id = se.subject_id JOIN students st ON se.student_id = st.id WHERE s.status='active' AND st.section = ? ORDER BY s.subject_code");
    $stmt->execute([$officerSection]);
    $subjects = $stmt->fetchAll();
} else {
    $subjects = $pdo->query("SELECT * FROM subjects WHERE status='active' ORDER BY subject_code")->fetchAll();
}

$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Get enrolled students if subject selected
$enrolledStudents = [];
if ($selectedSubject) {
    if ($sectionScoped) {
        $stmt = $pdo->prepare("
            SELECT s.* FROM students s
            JOIN subject_enrollments se ON s.id = se.student_id
            WHERE se.subject_id = ? AND s.status = 'active' AND s.section = ?
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$selectedSubject, $officerSection]);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.* FROM students s
            JOIN subject_enrollments se ON s.id = se.student_id
            WHERE se.subject_id = ? AND s.status = 'active'
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$selectedSubject]);
    }
    $enrolledStudents = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $sessionDate = $_POST['session_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $statuses = $_POST['status'] ?? [];

    if (!$subjectId || empty($statuses)) {
        setFlash('error', 'Please select a subject and mark attendance.');
    } else {
        $stmt = $pdo->prepare("INSERT INTO attendance_sessions (subject_id, session_date, notes, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$subjectId, $sessionDate, $notes ?: null, $_SESSION['user_id']]);
        $sessionId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO attendance_records (session_id, student_id, status) VALUES (?, ?, ?)");
        foreach ($statuses as $studentId => $status) {
            $stmt->execute([$sessionId, (int)$studentId, $status]);
        }

        setFlash('success', 'Attendance recorded for ' . count($statuses) . ' students.');
        redirect('/attendance/session.php?id=' . $sessionId);
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-3xl">
    <a href="<?= BASE_URL ?>/attendance/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Attendance
    </a>

    <!-- Step 1: Select Subject & Date -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 mb-6">
        <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h3 class="text-sm font-semibold"><i class="fas fa-clipboard-list mr-1.5 text-mono-400"></i> Session Details</h3>
        </div>
        <form method="GET" class="p-5">
            <div class="grid sm:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Subject</label>
                    <select name="subject_id" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <option value="">Select a subject...</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSubject == $s['id'] ? 'selected' : '' ?>><?= sanitize($s['subject_code']) ?> &mdash; <?= sanitize($s['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Date</label>
                    <input type="date" name="date" value="<?= sanitize($selectedDate) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
            </div>
            <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-lg bg-mono-100 dark:bg-mono-800 text-sm font-medium hover:bg-mono-200 dark:hover:bg-mono-700 transition-colors">
                <i class="fas fa-users mr-1.5"></i> Load Students
            </button>
        </form>
    </div>

    <!-- Step 2: Mark Attendance -->
    <?php if ($selectedSubject && !empty($enrolledStudents)): ?>
    <div x-data="{
            search: '',
            counts: { present: <?= count($enrolledStudents) ?>, late: 0, absent: 0, excused: 0 },
            total: <?= count($enrolledStudents) ?>,
            updateCounts() {
                this.counts = { present: 0, late: 0, absent: 0, excused: 0 };
                document.querySelectorAll('input[type=radio]:checked').forEach(r => {
                    if (this.counts.hasOwnProperty(r.value)) this.counts[r.value]++;
                });
            },
            markAll(status) {
                document.querySelectorAll('input[type=radio][value=' + status + ']').forEach(r => {
                    r.checked = true;
                });
                this.updateCounts();
            },
            matchSearch(name) {
                if (!this.search) return true;
                return name.toLowerCase().includes(this.search.toLowerCase());
            }
         }">

        <!-- Live Summary Bar -->
        <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4 mb-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                        <span class="text-xs font-semibold" x-text="counts.present"></span>
                        <span class="text-[10px] text-mono-400 hidden sm:inline">Present</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                        <span class="text-xs font-semibold" x-text="counts.late"></span>
                        <span class="text-[10px] text-mono-400 hidden sm:inline">Late</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-red-500"></div>
                        <span class="text-xs font-semibold" x-text="counts.absent"></span>
                        <span class="text-[10px] text-mono-400 hidden sm:inline">Absent</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-blue-400"></div>
                        <span class="text-xs font-semibold" x-text="counts.excused"></span>
                        <span class="text-[10px] text-mono-400 hidden sm:inline">Excused</span>
                    </div>
                </div>
                <span class="text-[10px] text-mono-400 font-medium" x-text="total + ' students'"></span>
            </div>
        </div>

        <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
            <!-- Header with search and bulk actions -->
            <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold"><?= count($enrolledStudents) ?> Students</h3>
                    <div class="flex items-center gap-2">
                        <button @click="markAll('present')" type="button" class="px-2.5 py-1.5 text-[10px] font-semibold rounded-lg bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 transition-colors uppercase tracking-wider">
                            <i class="fas fa-check mr-0.5"></i> All P
                        </button>
                        <button @click="markAll('absent')" type="button" class="px-2.5 py-1.5 text-[10px] font-semibold rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors uppercase tracking-wider">
                            <i class="fas fa-times mr-0.5"></i> All A
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

            <form method="POST" id="attendanceForm">
                <?= csrfField() ?>
                <input type="hidden" name="subject_id" value="<?= $selectedSubject ?>">
                <input type="hidden" name="session_date" value="<?= sanitize($selectedDate) ?>">

                <div class="divide-y divide-mono-100 dark:divide-mono-800 max-h-[60vh] overflow-y-auto">
                    <?php foreach ($enrolledStudents as $i => $student): 
                        $fullName = $student['last_name'] . ', ' . $student['first_name'];
                    ?>
                    <div class="px-4 sm:px-5 py-3 transition-colors hover:bg-mono-50 dark:hover:bg-mono-800/30"
                         x-show="matchSearch('<?= addslashes($fullName) ?>')" x-cloak>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-3">
                            <!-- Student Info -->
                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <span class="text-[10px] text-mono-400 w-5 text-right flex-shrink-0"><?= $i + 1 ?></span>
                                <div class="w-9 h-9 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                                    <span class="text-[10px] font-bold text-mono-500"><?= getInitials($student['first_name'] . ' ' . $student['last_name']) ?></span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium"><?= sanitize($fullName) ?></p>
                                    <p class="text-[10px] text-mono-400"><?= sanitize($student['student_id']) ?></p>
                                </div>
                            </div>

                            <!-- Status Buttons -->
                            <div class="flex items-center gap-1.5 flex-shrink-0 self-end sm:self-auto">
                                <?php 
                                $statusConfig = [
                                    'present' => ['label' => 'P', 'full' => 'Present', 'icon' => 'fa-check', 'active' => 'bg-emerald-500 border-emerald-500 text-white', 'ring' => 'ring-emerald-500/30'],
                                    'late'    => ['label' => 'L', 'full' => 'Late',    'icon' => 'fa-clock', 'active' => 'bg-amber-500 border-amber-500 text-white', 'ring' => 'ring-amber-500/30'],
                                    'absent'  => ['label' => 'A', 'full' => 'Absent',  'icon' => 'fa-times', 'active' => 'bg-red-500 border-red-500 text-white', 'ring' => 'ring-red-500/30'],
                                    'excused' => ['label' => 'E', 'full' => 'Excused', 'icon' => 'fa-info',  'active' => 'bg-blue-400 border-blue-400 text-white', 'ring' => 'ring-blue-400/30'],
                                ];
                                foreach ($statusConfig as $val => $cfg): ?>
                                <label class="relative cursor-pointer" title="<?= $cfg['full'] ?>">
                                    <input type="radio" name="status[<?= $student['id'] ?>]" value="<?= $val ?>" 
                                           <?= $val === 'present' ? 'checked' : '' ?> class="peer sr-only" @change="updateCounts()">
                                    <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl border-2 border-mono-200 dark:border-mono-700 flex items-center justify-center
                                                text-xs font-bold text-mono-400 transition-all duration-150
                                                peer-checked:<?= str_replace(' ', ' peer-checked:', $cfg['active']) ?>
                                                peer-checked:ring-2 peer-checked:<?= $cfg['ring'] ?>
                                                peer-checked:shadow-sm
                                                hover:border-mono-400 dark:hover:border-mono-500 active:scale-95">
                                        <span class="sm:hidden"><?= $cfg['label'] ?></span>
                                        <span class="hidden sm:inline text-[10px]"><?= $cfg['label'] ?></span>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Bottom Action Bar -->
                <div class="border-t border-mono-200 dark:border-mono-800 p-4 sm:p-5 bg-mono-50/50 dark:bg-mono-800/20 rounded-b-xl">
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Quiz day, Field trip, Lab session..."
                               class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-white dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <div class="hidden sm:flex items-center gap-3 text-[10px] text-mono-400">
                            <span><span class="inline-block w-2 h-2 rounded-full bg-emerald-500 mr-1"></span><span x-text="counts.present"></span> Present</span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-1"></span><span x-text="counts.late"></span> Late</span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-red-500 mr-1"></span><span x-text="counts.absent"></span> Absent</span>
                            <span><span class="inline-block w-2 h-2 rounded-full bg-blue-400 mr-1"></span><span x-text="counts.excused"></span> Excused</span>
                        </div>
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-bold hover:bg-mono-800 dark:hover:bg-mono-200 transition-all active:scale-[0.98] shadow-sm">
                            <i class="fas fa-check"></i> Save Attendance
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php elseif ($selectedSubject && empty($enrolledStudents)): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 px-6 py-12 text-center">
        <div class="w-12 h-12 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-users text-mono-300 dark:text-mono-600 text-lg"></i>
        </div>
        <p class="text-sm text-mono-500 font-medium mb-1">No students enrolled</p>
        <p class="text-xs text-mono-400 mb-4">This subject doesn't have any enrolled students yet.</p>
        <a href="<?= BASE_URL ?>/subjects/enroll.php?id=<?= $selectedSubject ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
            <i class="fas fa-user-plus text-xs"></i> Enroll Students
        </a>
    </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
