<?php
/**
 * eClass - Student Profile
 * Shows attendance, absences, funds balance & payment history
 */
$pageTitle = 'Student Profile';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect('/students/');
}

// Section access check for officers/teachers
if (hasSectionScope() && $student['section'] !== getUserSection()) {
    setFlash('error', 'Access denied. Student is not in your section.');
    redirect('/students/');
}

$pageSubtitle = $student['first_name'] . ' ' . $student['last_name'];

// Enrolled subjects
$subjects = $pdo->prepare("
    SELECT s.* FROM subjects s
    JOIN subject_enrollments se ON s.id = se.subject_id
    WHERE se.student_id = ? AND s.status = 'active'
    ORDER BY s.subject_code
");
$subjects->execute([$id]);
$enrolledSubjects = $subjects->fetchAll();

// Attendance stats per subject
$attendanceStats = [];
foreach ($enrolledSubjects as $subj) {
    $stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused
        FROM attendance_records ar
        JOIN attendance_sessions a_s ON ar.session_id = a_s.id
        WHERE ar.student_id = ? AND a_s.subject_id = ?
    ");
    $stats->execute([$id, $subj['id']]);
    $attendanceStats[$subj['id']] = $stats->fetch();
}

// Overall attendance
$overallAttendance = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
    FROM attendance_records WHERE student_id = ?
");
$overallAttendance->execute([$id]);
$overall = $overallAttendance->fetch();

// Fund obligations & payments
$funds = $pdo->prepare("
    SELECT f.*, 
           COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = f.id AND fp.student_id = ?), 0) as total_paid
    FROM funds f
    JOIN fund_assignees fa ON f.id = fa.fund_id
    WHERE fa.student_id = ?
    ORDER BY f.created_at DESC
");
$funds->execute([$id, $id]);
$studentFunds = $funds->fetchAll();

// Payment history
$payments = $pdo->prepare("
    SELECT fp.*, f.fund_name
    FROM fund_payments fp
    JOIN funds f ON fp.fund_id = f.id
    WHERE fp.student_id = ?
    ORDER BY fp.payment_date DESC, fp.created_at DESC
    LIMIT 20
");
$payments->execute([$id]);
$paymentHistory = $payments->fetchAll();

// Totals
$totalOwed = array_sum(array_column($studentFunds, 'amount'));
$totalPaid = array_sum(array_column($studentFunds, 'total_paid'));
$totalBalance = $totalOwed - $totalPaid;

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<a href="<?= BASE_URL ?>/students/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
    <i class="fas fa-arrow-left text-xs"></i> Back to Students
</a>

<!-- Profile Header -->
<div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 sm:p-6 mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="w-16 h-16 rounded-2xl bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
            <span class="text-xl font-bold text-mono-500 dark:text-mono-400"><?= getInitials($student['first_name'] . ' ' . $student['last_name']) ?></span>
        </div>
        <div class="flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-1">
                <h2 class="text-xl font-bold"><?= sanitize($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full uppercase tracking-wider
                    <?= $student['student_type'] === 'regular' ? 'bg-mono-100 dark:bg-mono-800 text-mono-600 dark:text-mono-400' : 'bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900' ?>">
                    <?= $student['student_type'] ?>
                </span>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-mono-400">
                <span><i class="fas fa-id-card mr-1"></i> <?= sanitize($student['student_id']) ?></span>
                <span><i class="fas fa-layer-group mr-1"></i> Year <?= $student['year_level'] ?><?= $student['section'] ? ' - ' . sanitize($student['section']) : '' ?></span>
                <?php if ($student['email']): ?><span><i class="fas fa-envelope mr-1"></i> <?= sanitize($student['email']) ?></span><?php endif; ?>
                <?php if ($student['phone']): ?><span><i class="fas fa-phone mr-1"></i> <?= sanitize($student['phone']) ?></span><?php endif; ?>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/students/edit.php?id=<?= $student['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-mono-200 dark:border-mono-700 text-sm font-medium hover:bg-mono-50 dark:hover:bg-mono-800 transition-colors">
            <i class="fas fa-edit text-xs"></i> Edit
        </a>
    </div>
</div>

<!-- Quick Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-mono-400 mb-1">Attendance Rate</p>
        <p class="text-2xl font-bold"><?= $overall['total'] > 0 ? round(($overall['present'] / $overall['total']) * 100) : 0 ?>%</p>
        <p class="text-xs text-mono-400"><?= $overall['present'] ?? 0 ?> / <?= $overall['total'] ?? 0 ?> sessions</p>
    </div>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-mono-400 mb-1">Absences</p>
        <p class="text-2xl font-bold"><?= $overall['absent'] ?? 0 ?></p>
        <p class="text-xs text-mono-400"><?= $overall['late'] ?? 0 ?> late, <?= $overall['excused'] ?? 0 ?> excused</p>
    </div>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-mono-400 mb-1">Total Paid</p>
        <p class="text-2xl font-bold"><?= formatMoney($totalPaid) ?></p>
        <p class="text-xs text-mono-400">of <?= formatMoney($totalOwed) ?> owed</p>
    </div>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-mono-400 mb-1">Balance</p>
        <p class="text-2xl font-bold <?= $totalBalance > 0 ? 'text-red-500' : '' ?>"><?= formatMoney($totalBalance) ?></p>
        <p class="text-xs text-mono-400"><?= $totalBalance > 0 ? 'remaining' : 'all settled' ?></p>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">

    <!-- Attendance Per Subject -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h3 class="text-sm font-semibold"><i class="fas fa-clipboard-check mr-1.5 text-mono-400"></i> Attendance by Subject</h3>
        </div>
        <div class="divide-y divide-mono-100 dark:divide-mono-800">
            <?php if (empty($enrolledSubjects)): ?>
            <div class="px-5 py-8 text-center">
                <p class="text-sm text-mono-400">Not enrolled in any subjects</p>
            </div>
            <?php else: ?>
            <?php foreach ($enrolledSubjects as $subj): 
                $stat = $attendanceStats[$subj['id']];
                $rate = $stat['total_sessions'] > 0 ? round(($stat['present'] / $stat['total_sessions']) * 100) : 0;
            ?>
            <div class="px-5 py-4">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-sm font-medium"><?= sanitize($subj['subject_code']) ?></p>
                        <p class="text-[11px] text-mono-400"><?= sanitize($subj['subject_name']) ?></p>
                    </div>
                    <span class="text-sm font-bold"><?= $rate ?>%</span>
                </div>
                <div class="w-full h-2 bg-mono-100 dark:bg-mono-800 rounded-full overflow-hidden flex">
                    <?php if ($stat['total_sessions'] > 0): ?>
                    <div class="h-full bg-mono-900 dark:bg-mono-100 transition-all" style="width: <?= ($stat['present'] / $stat['total_sessions']) * 100 ?>%" title="Present: <?= $stat['present'] ?>"></div>
                    <div class="h-full bg-mono-400 transition-all" style="width: <?= ($stat['late'] / $stat['total_sessions']) * 100 ?>%" title="Late: <?= $stat['late'] ?>"></div>
                    <div class="h-full bg-mono-300 dark:bg-mono-600 transition-all" style="width: <?= ($stat['excused'] / $stat['total_sessions']) * 100 ?>%" title="Excused: <?= $stat['excused'] ?>"></div>
                    <?php endif; ?>
                </div>
                <div class="flex gap-4 mt-1.5 text-[10px] text-mono-400">
                    <span><span class="inline-block w-2 h-2 rounded-full bg-mono-900 dark:bg-mono-100 mr-1"></span>Present <?= $stat['present'] ?? 0 ?></span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-mono-400 mr-1"></span>Late <?= $stat['late'] ?? 0 ?></span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-red-400 mr-1"></span>Absent <?= $stat['absent'] ?? 0 ?></span>
                    <span><span class="inline-block w-2 h-2 rounded-full bg-mono-300 dark:bg-mono-600 mr-1"></span>Excused <?= $stat['excused'] ?? 0 ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fund Obligations -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h3 class="text-sm font-semibold"><i class="fas fa-wallet mr-1.5 text-mono-400"></i> Fund Obligations</h3>
        </div>
        <div class="divide-y divide-mono-100 dark:divide-mono-800">
            <?php if (empty($studentFunds)): ?>
            <div class="px-5 py-8 text-center">
                <p class="text-sm text-mono-400">No fund obligations</p>
            </div>
            <?php else: ?>
            <?php foreach ($studentFunds as $fund): 
                $balance = $fund['amount'] - $fund['total_paid'];
                $pct = $fund['amount'] > 0 ? min(100, ($fund['total_paid'] / $fund['amount']) * 100) : 0;
            ?>
            <div class="px-5 py-4">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-sm font-medium"><?= sanitize($fund['fund_name']) ?></p>
                    <span class="text-xs font-medium <?= $balance > 0 ? 'text-red-500' : 'text-emerald-500' ?>">
                        <?= $balance > 0 ? formatMoney($balance) . ' due' : 'Paid ✓' ?>
                    </span>
                </div>
                <div class="flex items-center justify-between text-[11px] text-mono-400 mb-2">
                    <span><?= ucfirst($fund['frequency']) ?> · <?= formatMoney($fund['amount']) ?></span>
                    <span><?= formatMoney($fund['total_paid']) ?> paid</span>
                </div>
                <div class="w-full h-1.5 bg-mono-100 dark:bg-mono-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 <?= $pct >= 100 ? 'bg-emerald-500' : 'bg-mono-900 dark:bg-mono-100' ?>" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Payment History -->
<div class="mt-6 bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
    <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
        <h3 class="text-sm font-semibold"><i class="fas fa-receipt mr-1.5 text-mono-400"></i> Payment History</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-mono-100 dark:border-mono-800">
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Date</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Fund</th>
                    <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden sm:table-cell">Method</th>
                    <th class="text-right px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mono-100 dark:divide-mono-800">
                <?php if (empty($paymentHistory)): ?>
                <tr><td colspan="4" class="px-5 py-8 text-center text-mono-400">No payments recorded yet</td></tr>
                <?php else: ?>
                <?php foreach ($paymentHistory as $payment): ?>
                <tr class="hover:bg-mono-50 dark:hover:bg-mono-800/50 transition-colors">
                    <td class="px-5 py-3 text-mono-500"><?= formatDate($payment['payment_date']) ?></td>
                    <td class="px-5 py-3 font-medium"><?= sanitize($payment['fund_name']) ?></td>
                    <td class="px-5 py-3 text-mono-400 hidden sm:table-cell capitalize"><?= sanitize($payment['payment_method']) ?></td>
                    <td class="px-5 py-3 text-right font-semibold"><?= formatMoney($payment['amount_paid']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
