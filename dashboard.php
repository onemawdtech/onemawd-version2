<?php
/**
 * eClass - Dashboard
 */
$pageTitle = 'Dashboard';
$pageSubtitle = 'Overview of your classroom';
require_once __DIR__ . '/config/app.php';
requireLogin();

// Section scoping for officers/teachers
$officerSection = getUserSection();
$isSectionScoped = hasSectionScope();

// Stats
if (isTeacher()) {
    // Teachers: only their subjects and attendance
    $teacherSubjectIds = getTeacherSubjectIds();
    $totalStudents = 0;
    $totalSubjects = count($teacherSubjectIds);
    $totalFunds = 0;

    if (!empty($teacherSubjectIds)) {
        $placeholders = implode(',', array_fill(0, count($teacherSubjectIds), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT se.student_id) FROM subject_enrollments se JOIN students st ON se.student_id = st.id WHERE se.subject_id IN ($placeholders) AND st.status='active'");
        $stmt->execute($teacherSubjectIds);
        $totalStudents = $stmt->fetchColumn();

        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE session_date = ? AND subject_id IN ($placeholders)");
        $stmt->execute(array_merge([$today], $teacherSubjectIds));
        $todaySessionCount = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT a_s.*, s.subject_name, s.subject_code,
                   (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id AND ar.status = 'present') as present_count,
                   (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id) as total_count
            FROM attendance_sessions a_s
            JOIN subjects s ON a_s.subject_id = s.id
            WHERE a_s.subject_id IN ($placeholders)
            ORDER BY a_s.session_date DESC, a_s.created_at DESC
            LIMIT 5
        ");
        $stmt->execute($teacherSubjectIds);
        $recentAttendance = $stmt->fetchAll();
    } else {
        $todaySessionCount = 0;
        $recentAttendance = [];
    }
    $fundStats = [];
    $recentStudents = [];
} elseif ($isSectionScoped) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE status='active' AND section = ?");
    $stmt->execute([$officerSection]);
    $totalStudents = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) FROM subjects s JOIN subject_enrollments se ON s.id = se.subject_id JOIN students st ON se.student_id = st.id WHERE s.status='active' AND st.section = ?");
    $stmt->execute([$officerSection]);
    $totalSubjects = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT f.id) FROM funds f JOIN fund_assignees fa ON f.id = fa.fund_id JOIN students st ON fa.student_id = st.id WHERE f.status='active' AND st.section = ?");
    $stmt->execute([$officerSection]);
    $totalFunds = $stmt->fetchColumn();

    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a_s.id) FROM attendance_sessions a_s JOIN attendance_records ar ON a_s.id = ar.session_id JOIN students st ON ar.student_id = st.id WHERE a_s.session_date = ? AND st.section = ?");
    $stmt->execute([$today, $officerSection]);
    $todaySessionCount = $stmt->fetchColumn();

    // Recent attendance
    $stmt = $pdo->prepare("
        SELECT DISTINCT a_s.*, s.subject_name, s.subject_code,
               (SELECT COUNT(*) FROM attendance_records ar JOIN students st2 ON ar.student_id = st2.id WHERE ar.session_id = a_s.id AND ar.status = 'present' AND st2.section = ?) as present_count,
               (SELECT COUNT(*) FROM attendance_records ar JOIN students st2 ON ar.student_id = st2.id WHERE ar.session_id = a_s.id AND st2.section = ?) as total_count
        FROM attendance_sessions a_s
        JOIN subjects s ON a_s.subject_id = s.id
        JOIN attendance_records ar2 ON a_s.id = ar2.session_id
        JOIN students st ON ar2.student_id = st.id
        WHERE st.section = ?
        ORDER BY a_s.session_date DESC, a_s.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$officerSection, $officerSection, $officerSection]);
    $recentAttendance = $stmt->fetchAll();

    // Fund collection summary
    $stmt = $pdo->prepare("
        SELECT f.fund_name, f.amount,
               COALESCE(SUM(fp.amount_paid), 0) as collected,
               (SELECT COUNT(*) FROM fund_assignees fa2 JOIN students st2 ON fa2.student_id = st2.id WHERE fa2.fund_id = f.id AND st2.section = ?) as assignee_count
        FROM funds f
        JOIN fund_assignees fa ON f.id = fa.fund_id
        JOIN students st ON fa.student_id = st.id
        LEFT JOIN fund_payments fp ON f.id = fp.fund_id AND fp.student_id IN (SELECT id FROM students WHERE section = ?)
        WHERE f.status = 'active' AND st.section = ?
        GROUP BY f.id
        ORDER BY f.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$officerSection, $officerSection, $officerSection]);
    $fundStats = $stmt->fetchAll();

    // Recent students
    $stmt = $pdo->prepare("SELECT * FROM students WHERE status='active' AND section = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$officerSection]);
    $recentStudents = $stmt->fetchAll();
} else {
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
    $totalSubjects = $pdo->query("SELECT COUNT(*) FROM subjects WHERE status='active'")->fetchColumn();
    $totalFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='active'")->fetchColumn();

    $today = date('Y-m-d');
    $todaySessions = $pdo->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE session_date = ?");
    $todaySessions->execute([$today]);
    $todaySessionCount = $todaySessions->fetchColumn();

    // Recent attendance
    $recentAttendance = $pdo->query("
        SELECT a_s.*, s.subject_name, s.subject_code,
               (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id AND ar.status = 'present') as present_count,
               (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = a_s.id) as total_count
        FROM attendance_sessions a_s
        JOIN subjects s ON a_s.subject_id = s.id
        ORDER BY a_s.session_date DESC, a_s.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Fund collection summary
    $fundStats = $pdo->query("
        SELECT f.fund_name, f.amount,
               COALESCE(SUM(fp.amount_paid), 0) as collected,
               (SELECT COUNT(*) FROM fund_assignees fa WHERE fa.fund_id = f.id) as assignee_count
        FROM funds f
        LEFT JOIN fund_payments fp ON f.id = fp.fund_id
        WHERE f.status = 'active'
        GROUP BY f.id
        ORDER BY f.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Recent students
    $recentStudents = $pdo->query("SELECT * FROM students WHERE status='active' ORDER BY created_at DESC LIMIT 5")->fetchAll();
}

include 'includes/header.php';
include 'includes/sidebar.php';
include 'includes/topbar.php';
?>

<!-- Stats Grid -->
<div class="grid grid-cols-2 <?= isTeacher() ? 'lg:grid-cols-3' : 'lg:grid-cols-4' ?> gap-4 mb-6">
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                <i class="fas fa-users text-mono-500 dark:text-mono-400"></i>
            </div>
            <span class="text-[10px] font-medium uppercase tracking-wider text-mono-400"><?= isTeacher() ? 'Enrolled' : 'Students' ?></span>
        </div>
        <p class="text-2xl font-bold"><?= $totalStudents ?></p>
        <p class="text-xs text-mono-400 mt-0.5"><?= isTeacher() ? 'Students in your subjects' : 'Active students' ?></p>
    </div>

    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                <i class="fas fa-book text-mono-500 dark:text-mono-400"></i>
            </div>
            <span class="text-[10px] font-medium uppercase tracking-wider text-mono-400">Subjects</span>
        </div>
        <p class="text-2xl font-bold"><?= $totalSubjects ?></p>
        <p class="text-xs text-mono-400 mt-0.5">Active subjects</p>
    </div>

    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                <i class="fas fa-clipboard-check text-mono-500 dark:text-mono-400"></i>
            </div>
            <span class="text-[10px] font-medium uppercase tracking-wider text-mono-400">Today</span>
        </div>
        <p class="text-2xl font-bold"><?= $todaySessionCount ?></p>
        <p class="text-xs text-mono-400 mt-0.5">Attendance sessions</p>
    </div>

    <?php if (!isTeacher()): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-mono-100 dark:bg-mono-800 flex items-center justify-center">
                <i class="fas fa-wallet text-mono-500 dark:text-mono-400"></i>
            </div>
            <span class="text-[10px] font-medium uppercase tracking-wider text-mono-400">Funds</span>
        </div>
        <p class="text-2xl font-bold"><?= $totalFunds ?></p>
        <p class="text-xs text-mono-400 mt-0.5">Active funds</p>
    </div>
    <?php endif; ?>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <!-- Recent Attendance -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="flex items-center justify-between px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h2 class="text-sm font-semibold">Recent Attendance</h2>
            <a href="<?= BASE_URL ?>/attendance/" class="text-xs text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 transition-colors">View all →</a>
        </div>
        <div class="divide-y divide-mono-100 dark:divide-mono-800">
            <?php if (empty($recentAttendance)): ?>
            <div class="px-5 py-8 text-center">
                <i class="fas fa-clipboard text-2xl text-mono-300 dark:text-mono-700 mb-2"></i>
                <p class="text-sm text-mono-400">No attendance records yet</p>
            </div>
            <?php else: ?>
            <?php foreach ($recentAttendance as $session): ?>
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium"><?= sanitize($session['subject_code']) ?></p>
                    <p class="text-xs text-mono-400"><?= formatDate($session['session_date']) ?></p>
                </div>
                <div class="text-right">
                    <span class="text-sm font-semibold"><?= $session['present_count'] ?>/<?= $session['total_count'] ?></span>
                    <p class="text-[10px] text-mono-400">present</p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fund Collection -->
    <?php if (!isTeacher()): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="flex items-center justify-between px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h2 class="text-sm font-semibold">Fund Collections</h2>
            <a href="<?= BASE_URL ?>/funds/" class="text-xs text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 transition-colors">View all →</a>
        </div>
        <div class="divide-y divide-mono-100 dark:divide-mono-800">
            <?php if (empty($fundStats)): ?>
            <div class="px-5 py-8 text-center">
                <i class="fas fa-wallet text-2xl text-mono-300 dark:text-mono-700 mb-2"></i>
                <p class="text-sm text-mono-400">No active funds yet</p>
            </div>
            <?php else: ?>
            <?php foreach ($fundStats as $fund): 
                $target = $fund['amount'] * $fund['assignee_count'];
                $pct = $target > 0 ? min(100, ($fund['collected'] / $target) * 100) : 0;
            ?>
            <div class="px-5 py-3">
                <div class="flex items-center justify-between mb-1.5">
                    <p class="text-sm font-medium"><?= sanitize($fund['fund_name']) ?></p>
                    <span class="text-xs text-mono-400"><?= formatMoney($fund['collected']) ?> / <?= formatMoney($target) ?></span>
                </div>
                <div class="w-full h-1.5 bg-mono-100 dark:bg-mono-800 rounded-full overflow-hidden">
                    <div class="h-full bg-mono-900 dark:bg-mono-100 rounded-full transition-all duration-500" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; /* !isTeacher fund collections */ ?>
</div>

<!-- Recent Students -->
<?php if (!isTeacher()): ?>
<div class="mt-6 bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
    <div class="flex items-center justify-between px-5 py-4 border-b border-mono-200 dark:border-mono-800">
        <h2 class="text-sm font-semibold">Recent Students</h2>
        <a href="<?= BASE_URL ?>/students/" class="text-xs text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 transition-colors">View all →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-mono-100 dark:border-mono-800">
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Student</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden sm:table-cell">ID</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Type</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden md:table-cell">Added</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mono-100 dark:divide-mono-800">
                <?php if (empty($recentStudents)): ?>
                <tr><td colspan="4" class="px-5 py-8 text-center text-mono-400">No students yet</td></tr>
                <?php else: ?>
                <?php foreach ($recentStudents as $student): ?>
                <tr class="hover:bg-mono-50 dark:hover:bg-mono-800/50 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                                <span class="text-[10px] font-semibold text-mono-500"><?= getInitials($student['first_name'] . ' ' . $student['last_name']) ?></span>
                            </div>
                            <a href="<?= BASE_URL ?>/students/profile.php?id=<?= $student['id'] ?>" class="font-medium hover:underline"><?= sanitize($student['first_name'] . ' ' . $student['last_name']) ?></a>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-mono-400 hidden sm:table-cell"><?= sanitize($student['student_id']) ?></td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full uppercase tracking-wider
                            <?= $student['student_type'] === 'regular' ? 'bg-mono-100 dark:bg-mono-800 text-mono-600 dark:text-mono-400' : 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' ?>">
                            <?= $student['student_type'] ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-mono-400 hidden md:table-cell"><?= formatDate($student['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; /* !isTeacher recent students */ ?>

<?php include 'includes/footer.php'; ?>
