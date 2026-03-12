<?php
/**
 * eClass - Manage Fund (View payments, record payments, billing periods)
 */
$pageTitle = 'Manage Fund';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT f.*, s.subject_code, s.subject_name
    FROM funds f
    LEFT JOIN subjects s ON f.subject_id = s.id
    WHERE f.id = ?
");
$stmt->execute([$id]);
$fund = $stmt->fetch();

if (!$fund) {
    setFlash('error', 'Fund not found.');
    redirect('/funds/');
}

// Section access check for officers/teachers
$officerSection = getUserSection();
$sectionScoped = hasSectionScope();
if ($sectionScoped) {
    $hasAccess = false;
    // General funds: allow access if user created the fund
    if ($isGeneral) {
        $hasAccess = ($fund['created_by'] == $_SESSION['user_id']);
    }
    if (!$hasAccess) {
        // Check fund_assignees (manual funds)
        $checkAssignees = $pdo->prepare("SELECT COUNT(*) FROM fund_assignees fa JOIN students st ON fa.student_id = st.id WHERE fa.fund_id = ? AND st.section = ?");
        $checkAssignees->execute([$id, $officerSection]);
        if ($checkAssignees->fetchColumn() > 0) $hasAccess = true;
    }
    if (!$hasAccess && !empty($fund['subject_id'])) {
        // Check subject_enrollments (subject-linked funds)
        $checkEnroll = $pdo->prepare("SELECT COUNT(*) FROM subject_enrollments se JOIN students st ON se.student_id = st.id WHERE se.subject_id = ? AND st.section = ? AND st.status = 'active'");
        $checkEnroll->execute([$fund['subject_id'], $officerSection]);
        if ($checkEnroll->fetchColumn() > 0) $hasAccess = true;
    }
    // Allow access if user created the fund
    if (!$hasAccess && $fund['created_by'] == $_SESSION['user_id']) $hasAccess = true;
    if (!$hasAccess) {
        setFlash('error', 'Access denied. This fund has no students from your section.');
        redirect('/funds/');
    }
}

$pageSubtitle = $fund['fund_name'];
$isRecurring = $fund['frequency'] !== 'one-time';
$fundType = $fund['fund_type'] ?? 'standard';
$isGeneral = $fundType === 'general';
$isVoluntary = $fundType === 'voluntary';

// Fetch billing periods for recurring funds
$billingPeriods = [];
if ($isRecurring) {
    $bpStmt = $pdo->prepare("SELECT * FROM fund_billing_periods WHERE fund_id = ? ORDER BY period_start ASC");
    $bpStmt->execute([$id]);
    $billingPeriods = $bpStmt->fetchAll();
}

// Active billing period (from query param or latest)
$activePeriodId = (int)($_GET['period'] ?? 0);
if ($isRecurring && !empty($billingPeriods)) {
    if (!$activePeriodId) {
        $activePeriod = null;
        foreach (array_reverse($billingPeriods) as $bp) {
            if ($bp['status'] === 'active') { $activePeriod = $bp; break; }
        }
        if (!$activePeriod) $activePeriod = end($billingPeriods);
        $activePeriodId = $activePeriod['id'];
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $isDeposit = !empty($_POST['is_deposit']);
        $amountPaid = (float)($_POST['amount_paid'] ?? 0);
        $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
        $method = $_POST['payment_method'] ?? 'cash';
        $notes = trim($_POST['notes'] ?? '');
        $billingPeriodId = $isRecurring ? (int)($_POST['billing_period_id'] ?? $activePeriodId) : null;

        if ($amountPaid > 0 && ($studentId || $isDeposit)) {
            $stmt = $pdo->prepare("INSERT INTO fund_payments (fund_id, student_id, amount_paid, payment_date, payment_method, notes, recorded_by, billing_period_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, $isDeposit ? null : $studentId, $amountPaid, $paymentDate, $method, $notes ?: null, $_SESSION['user_id'], $billingPeriodId]);
            setFlash('success', $isDeposit ? 'Deposit recorded successfully.' : 'Payment recorded successfully.');
        }
        redirect('/funds/manage.php?id=' . $id . ($billingPeriodId ? '&period=' . $billingPeriodId : ''));
    } elseif ($action === 'generate_next_period') {
        if ($isRecurring) {
            $lastPeriod = !empty($billingPeriods) ? end($billingPeriods) : null;
            $periodStart = $lastPeriod ? date('Y-m-d', strtotime($lastPeriod['period_end'] . ' +1 day')) : date('Y-m-d');

            switch ($fund['frequency']) {
                case 'weekly':
                    $periodEnd = date('Y-m-d', strtotime($periodStart . ' +6 days'));
                    $periodLabel = 'Week of ' . date('M d', strtotime($periodStart));
                    break;
                case 'monthly':
                    $periodEnd = date('Y-m-d', strtotime($periodStart . ' +1 month -1 day'));
                    $periodLabel = date('F Y', strtotime($periodStart));
                    break;
                case 'semestral':
                    $periodEnd = date('Y-m-d', strtotime($periodStart . ' +6 months -1 day'));
                    $periodLabel = date('M Y', strtotime($periodStart)) . ' - ' . date('M Y', strtotime($periodEnd));
                    break;
                case 'annual':
                    $periodEnd = date('Y-m-d', strtotime($periodStart . ' +1 year -1 day'));
                    $periodLabel = 'AY ' . date('Y', strtotime($periodStart)) . '-' . date('Y', strtotime($periodEnd));
                    break;
                default:
                    $periodEnd = date('Y-m-d', strtotime($periodStart . ' +1 month'));
                    $periodLabel = 'Period ' . (count($billingPeriods) + 1);
            }

            if ($lastPeriod) {
                $pdo->prepare("UPDATE fund_billing_periods SET status = 'closed' WHERE id = ?")->execute([$lastPeriod['id']]);
            }

            $stmt = $pdo->prepare("INSERT INTO fund_billing_periods (fund_id, period_label, period_start, period_end, due_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $periodLabel, $periodStart, $periodEnd, $periodEnd]);
            $newPeriodId = $pdo->lastInsertId();
            setFlash('success', 'New billing period "' . $periodLabel . '" generated. Students are now charged ' . formatMoney($fund['amount']) . ' each.');
            redirect('/funds/manage.php?id=' . $id . '&period=' . $newPeriodId);
        }
    } elseif ($action === 'close_period') {
        $periodId = (int)($_POST['period_id'] ?? 0);
        $pdo->prepare("UPDATE fund_billing_periods SET status = 'closed' WHERE id = ? AND fund_id = ?")->execute([$periodId, $id]);
        setFlash('success', 'Billing period closed.');
        redirect('/funds/manage.php?id=' . $id);
    } elseif ($action === 'delete_period') {
        $periodId = (int)($_POST['period_id'] ?? 0);
        // Delete all payments linked to this period first
        $pdo->prepare("DELETE FROM fund_payments WHERE fund_id = ? AND billing_period_id = ?")->execute([$id, $periodId]);
        $pdo->prepare("DELETE FROM fund_billing_periods WHERE id = ? AND fund_id = ?")->execute([$periodId, $id]);
        setFlash('success', 'Billing period and its payments deleted.');
        redirect('/funds/manage.php?id=' . $id);
    } elseif ($action === 'close') {
        $pdo->prepare("UPDATE funds SET status = 'closed' WHERE id = ?")->execute([$id]);
        setFlash('success', 'Fund closed.');
        redirect('/funds/');
    } elseif ($action === 'reopen') {
        $pdo->prepare("UPDATE funds SET status = 'active' WHERE id = ?")->execute([$id]);
        setFlash('success', 'Fund reopened.');
        redirect('/funds/manage.php?id=' . $id);
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM funds WHERE id = ?")->execute([$id]);
        setFlash('success', 'Fund deleted.');
        redirect('/funds/');
    } elseif ($action === 'delete_payment') {
        $paymentId = (int)($_POST['payment_id'] ?? 0);
        $pdo->prepare("DELETE FROM fund_payments WHERE id = ? AND fund_id = ?")->execute([$paymentId, $id]);
        setFlash('success', 'Payment removed.');
        redirect('/funds/manage.php?id=' . $id . ($activePeriodId ? '&period=' . $activePeriodId : ''));
    }
}

// Re-fetch billing periods after POST
if ($isRecurring) {
    $bpStmt = $pdo->prepare("SELECT * FROM fund_billing_periods WHERE fund_id = ? ORDER BY period_start ASC");
    $bpStmt->execute([$id]);
    $billingPeriods = $bpStmt->fetchAll();
    if ($activePeriodId) {
        $found = false;
        foreach ($billingPeriods as $bp) {
            if ($bp['id'] == $activePeriodId) { $found = true; break; }
        }
        if (!$found && !empty($billingPeriods)) $activePeriodId = end($billingPeriods)['id'];
    }
}

$totalPeriodsCount = count($billingPeriods);
$amountPerPeriod = $fund['amount'];

// Get assignees with payment info
// General funds: no assignees
// Subject-linked funds: pull dynamically from subject_enrollments
// Manual funds: pull from static fund_assignees table
$fundAssignees = [];
if (!$isGeneral) {
    $hasSubject = !empty($fund['subject_id']);
    $queryParams = [];

    // Build SELECT fields
    $selectFields = "st.*";
    if ($hasSubject) {
        $selectFields .= ", se.id as assignee_id";
    } else {
        $selectFields .= ", fa.id as assignee_id";
    }

    if ($isRecurring && $activePeriodId) {
        $selectFields .= ",
            COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = ? AND fp.student_id = st.id AND fp.billing_period_id = ?), 0) as period_paid,
            COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = ? AND fp.student_id = st.id), 0) as total_paid";
        array_push($queryParams, $id, $activePeriodId, $id);
    } else {
        $selectFields .= ",
            COALESCE((SELECT SUM(fp.amount_paid) FROM fund_payments fp WHERE fp.fund_id = ? AND fp.student_id = st.id), 0) as total_paid";
        $queryParams[] = $id;
    }

    // Build FROM/JOIN and WHERE
    if ($hasSubject) {
        $fromClause = "FROM students st JOIN subject_enrollments se ON st.id = se.student_id";
        $whereClause = "WHERE se.subject_id = ? AND st.status = 'active'";
        $queryParams[] = $fund['subject_id'];
    } else {
        $fromClause = "FROM students st JOIN fund_assignees fa ON st.id = fa.student_id";
        $whereClause = "WHERE fa.fund_id = ?";
        $queryParams[] = $id;
    }

    if ($sectionScoped) {
        $whereClause .= " AND st.section = ?";
        $queryParams[] = $officerSection;
    }

    $assigneeQuery = "SELECT $selectFields $fromClause $whereClause ORDER BY st.last_name, st.first_name";
    $assignees = $pdo->prepare($assigneeQuery);
    $assignees->execute($queryParams);
    $fundAssignees = $assignees->fetchAll();
}

// Payment history
$paymentQuery = "SELECT fp.*, st.first_name, st.last_name, st.student_id as sid, fbp.period_label
    FROM fund_payments fp
    LEFT JOIN students st ON fp.student_id = st.id
    LEFT JOIN fund_billing_periods fbp ON fp.billing_period_id = fbp.id
    WHERE fp.fund_id = ?";
$paymentParams = [$id];
if ($sectionScoped) {
    $paymentQuery .= " AND (st.section = ? OR fp.student_id IS NULL)";
    $paymentParams[] = $officerSection;
}
if ($isRecurring && $activePeriodId) {
    $paymentQuery .= " AND fp.billing_period_id = ?";
    $paymentParams[] = $activePeriodId;
}
$paymentQuery .= " ORDER BY fp.payment_date DESC, fp.created_at DESC";
$payments = $pdo->prepare($paymentQuery);
$payments->execute($paymentParams);
$paymentHistory = $payments->fetchAll();

// Deposit totals (payments with no student)
$depositQuery = "SELECT COALESCE(SUM(fp.amount_paid), 0) FROM fund_payments fp WHERE fp.fund_id = ? AND fp.student_id IS NULL";
$depositParams = [$id];
if ($isRecurring && $activePeriodId) {
    $depositQuery .= " AND fp.billing_period_id = ?";
    $depositParams[] = $activePeriodId;
}
$depositStmt = $pdo->prepare($depositQuery);
$depositStmt->execute($depositParams);
$periodDeposits = (float)$depositStmt->fetchColumn();

$allDepositsStmt = $pdo->prepare("SELECT COALESCE(SUM(fp.amount_paid), 0) FROM fund_payments fp WHERE fp.fund_id = ? AND fp.student_id IS NULL");
$allDepositsStmt->execute([$id]);
$totalDeposits = (float)$allDepositsStmt->fetchColumn();

// Totals
if ($isGeneral || $isVoluntary) {
    // General/voluntary: no target, just sum all payments
    $allPaymentsStmt = $pdo->prepare("SELECT COALESCE(SUM(fp.amount_paid), 0) FROM fund_payments fp WHERE fp.fund_id = ?");
    $allPaymentsStmt->execute([$id]);
    $totalCollected = (float)$allPaymentsStmt->fetchColumn();
    $totalTarget = 0;
    $paidCount = 0;
    $grandTarget = 0;
    $grandCollected = $totalCollected;
    if ($isRecurring && $activePeriodId) {
        $periodPayStmt = $pdo->prepare("SELECT COALESCE(SUM(fp.amount_paid), 0) FROM fund_payments fp WHERE fp.fund_id = ? AND fp.billing_period_id = ?");
        $periodPayStmt->execute([$id, $activePeriodId]);
        $totalCollected = (float)$periodPayStmt->fetchColumn();
    }
} elseif ($isRecurring && $activePeriodId) {
    $totalTarget = $amountPerPeriod * count($fundAssignees);
    $totalCollected = array_sum(array_column($fundAssignees, 'period_paid')) + $periodDeposits;
    $paidCount = count(array_filter($fundAssignees, fn($a) => $a['period_paid'] >= $amountPerPeriod));
    $grandTarget = $amountPerPeriod * count($fundAssignees) * max(1, $totalPeriodsCount);
    $grandCollected = array_sum(array_column($fundAssignees, 'total_paid')) + $totalDeposits;
} else {
    $totalTarget = $fund['amount'] * count($fundAssignees);
    $totalCollected = array_sum(array_column($fundAssignees, 'total_paid')) + $totalDeposits;
    $paidCount = count(array_filter($fundAssignees, fn($a) => $a['total_paid'] >= $fund['amount']));
    $grandTarget = $totalTarget;
    $grandCollected = $totalCollected;
}
$overallPct = $totalTarget > 0 ? min(100, ($totalCollected / $totalTarget) * 100) : 0;

// Active billing period info
$activeBP = null;
if ($isRecurring && $activePeriodId) {
    foreach ($billingPeriods as $bp) {
        if ($bp['id'] == $activePeriodId) { $activeBP = $bp; break; }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<div class="max-w-4xl">
    <a href="<?= BASE_URL ?>/funds/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Funds
    </a>

    <!-- Fund Overview -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 sm:p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <h2 class="text-lg font-bold"><?= sanitize($fund['fund_name']) ?></h2>
                    <span class="px-2 py-0.5 text-[10px] font-medium rounded-full uppercase tracking-wider <?= $fund['status'] === 'active' ? 'bg-emerald-100 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400' : 'bg-mono-100 dark:bg-mono-800 text-mono-500' ?>">
                        <?= $fund['status'] ?>
                    </span>
                </div>
                <?php if ($fund['description']): ?>
                <p class="text-xs text-mono-400 mb-2"><?= sanitize($fund['description']) ?></p>
                <?php endif; ?>
                <div class="flex flex-wrap gap-3 text-xs text-mono-400">
                    <?php if ($isGeneral): ?>
                    <span><i class="fas fa-piggy-bank mr-1"></i> General Fund</span>
                    <?php elseif ($isVoluntary): ?>
                    <span><i class="fas fa-hand-holding-heart mr-1"></i> Voluntary</span>
                    <?php else: ?>
                    <span><i class="fas fa-coins mr-1"></i> <?= formatMoney($fund['amount']) ?> per student<?= $isRecurring ? ' / ' . $fund['frequency'] : '' ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-redo mr-1"></i> <?= ucfirst($fund['frequency']) ?></span>
                    <?php if ($fund['due_date']): ?><span><i class="fas fa-calendar-alt mr-1"></i> Due: <?= formatDate($fund['due_date']) ?></span><?php endif; ?>
                    <?php if ($fund['subject_code']): ?><span><i class="fas fa-book mr-1"></i> <?= sanitize($fund['subject_code']) ?></span><?php endif; ?>
                    <?php if ($isRecurring): ?><span><i class="fas fa-layer-group mr-1"></i> <?= $totalPeriodsCount ?> billing period<?= $totalPeriodsCount !== 1 ? 's' : '' ?></span><?php endif; ?>
                </div>
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <?php if ($fund['status'] === 'active'): ?>
                <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="close">
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium border border-mono-200 dark:border-mono-700 rounded-lg hover:bg-mono-50 dark:hover:bg-mono-800 transition-colors">Close Fund</button>
                </form>
                <?php else: ?>
                <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="reopen">
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium border border-mono-200 dark:border-mono-700 rounded-lg hover:bg-mono-50 dark:hover:bg-mono-800 transition-colors">Reopen</button>
                </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this fund and all payments?')"><?= csrfField() ?><input type="hidden" name="action" value="delete">
                    <button type="submit" class="px-3 py-1.5 text-xs font-medium text-red-500 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>

        <!-- Progress -->
        <?php if ($isGeneral || $isVoluntary): ?>
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="text-center p-3 rounded-lg bg-mono-50 dark:bg-mono-800">
                <p class="text-lg font-bold"><?= formatMoney($totalCollected) ?></p>
                <p class="text-[10px] text-mono-400 uppercase tracking-wider">Total Collected</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-mono-50 dark:bg-mono-800">
                <p class="text-lg font-bold"><?= count($paymentHistory) ?></p>
                <p class="text-[10px] text-mono-400 uppercase tracking-wider"><?= $isGeneral ? 'Deposits' : 'Contributions' ?></p>
            </div>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-3 gap-4 mb-4">
            <div class="text-center p-3 rounded-lg bg-mono-50 dark:bg-mono-800">
                <p class="text-lg font-bold"><?= formatMoney($totalCollected) ?></p>
                <p class="text-[10px] text-mono-400 uppercase tracking-wider"><?= $isRecurring && $activeBP ? 'Period Collected' : 'Collected' ?></p>
            </div>
            <div class="text-center p-3 rounded-lg bg-mono-50 dark:bg-mono-800">
                <p class="text-lg font-bold"><?= formatMoney($totalTarget) ?></p>
                <p class="text-[10px] text-mono-400 uppercase tracking-wider"><?= $isRecurring && $activeBP ? 'Period Target' : 'Target' ?></p>
            </div>
            <div class="text-center p-3 rounded-lg bg-mono-50 dark:bg-mono-800">
                <p class="text-lg font-bold"><?= $paidCount ?>/<?= count($fundAssignees) ?></p>
                <p class="text-[10px] text-mono-400 uppercase tracking-wider">Fully Paid</p>
            </div>
        </div>
        <div class="w-full h-2 bg-mono-100 dark:bg-mono-800 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 <?= $overallPct >= 100 ? 'bg-emerald-500' : 'bg-mono-900 dark:bg-mono-100' ?>" style="width: <?= $overallPct ?>%"></div>
        </div>
        <p class="text-right text-[11px] text-mono-400 mt-1"><?= round($overallPct, 1) ?>% collected</p>
        <?php endif; /* standard fund progress */ ?>

        <?php if ($isRecurring && $totalPeriodsCount > 0): ?>
        <div class="mt-3 pt-3 border-t border-mono-100 dark:border-mono-800 flex items-center justify-between text-xs text-mono-400">
            <?php if ($isGeneral || $isVoluntary): ?>
            <span><i class="fas fa-calculator mr-1"></i> All Periods Total: <?= formatMoney($grandCollected) ?></span>
            <span class="font-medium"><?= count($billingPeriods) ?> period<?= count($billingPeriods) !== 1 ? 's' : '' ?></span>
            <?php else: ?>
            <span><i class="fas fa-calculator mr-1"></i> All Periods Total: <?= formatMoney($grandCollected) ?> / <?= formatMoney($grandTarget) ?></span>
            <span class="font-medium"><?= $grandTarget > 0 ? round(($grandCollected / $grandTarget) * 100, 1) : 0 ?>% overall</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isRecurring): ?>
    <!-- Billing Periods Navigation -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-4 sm:p-5 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold"><i class="fas fa-calendar-week mr-1.5 text-mono-400"></i> Billing Periods</h3>
            <?php if ($fund['status'] === 'active'): ?>
            <form method="POST" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="generate_next_period">
                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 rounded-lg hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-plus text-[10px]"></i> Generate Next Period
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (empty($billingPeriods)): ?>
        <div class="text-center py-6">
            <div class="w-10 h-10 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-calendar-plus text-mono-400"></i>
            </div>
            <p class="text-xs text-mono-400 mb-2">No billing periods yet.</p>
            <p class="text-[11px] text-mono-400">Click "Generate Next Period" to create the first billing period and auto-charge students.</p>
        </div>
        <?php else: ?>
        <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-thin">
            <?php foreach ($billingPeriods as $bp):
                $isActiveBP = $bp['id'] == $activePeriodId;
                $isPeriodClosed = $bp['status'] === 'closed';
                $bpCollectStmt = $pdo->prepare("SELECT COALESCE(SUM(fp.amount_paid),0) FROM fund_payments fp WHERE fp.fund_id = ? AND fp.billing_period_id = ?");
                $bpCollectStmt->execute([$id, $bp['id']]);
                $bpCollected = (float)$bpCollectStmt->fetchColumn();
                $bpTarget = ($isGeneral || $isVoluntary) ? 0 : $amountPerPeriod * count($fundAssignees);
                $bpPct = $bpTarget > 0 ? min(100, ($bpCollected / $bpTarget) * 100) : 0;
            ?>
            <a href="<?= BASE_URL ?>/funds/manage.php?id=<?= $id ?>&period=<?= $bp['id'] ?>"
               class="flex-shrink-0 min-w-[160px] p-3 rounded-lg border-2 transition-all <?= $isActiveBP ? 'border-mono-900 dark:border-mono-100 bg-mono-50 dark:bg-mono-800' : 'border-mono-100 dark:border-mono-800 hover:border-mono-300 dark:hover:border-mono-600' ?>">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold <?= $isActiveBP ? '' : 'text-mono-500' ?>"><?= sanitize($bp['period_label']) ?></span>
                    <?php if ($isPeriodClosed): ?>
                    <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-mono-100 dark:bg-mono-700 text-mono-400 font-medium">Closed</span>
                    <?php elseif ($bpPct >= 100 && !$isGeneral && !$isVoluntary): ?>
                    <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-medium">Complete</span>
                    <?php else: ?>
                    <span class="text-[9px] px-1.5 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 font-medium">Active</span>
                    <?php endif; ?>
                </div>
                <div class="text-[10px] text-mono-400 mb-2">
                    <?= date('M d', strtotime($bp['period_start'])) ?> &ndash; <?= date('M d, Y', strtotime($bp['period_end'])) ?>
                </div>
                <?php if ($isGeneral || $isVoluntary): ?>
                <div class="text-[10px] text-mono-400 font-medium mt-1"><?= formatMoney($bpCollected) ?> collected</div>
                <?php else: ?>
                <div class="w-full h-1 bg-mono-100 dark:bg-mono-700 rounded-full overflow-hidden mb-1">
                    <div class="h-full rounded-full <?= $bpPct >= 100 ? 'bg-emerald-500' : 'bg-mono-400' ?>" style="width: <?= $bpPct ?>%"></div>
                </div>
                <div class="text-[10px] text-mono-400 font-medium"><?= formatMoney($bpCollected) ?> / <?= formatMoney($bpTarget) ?></div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($activeBP): ?>
    <div class="flex items-center justify-between px-4 py-2.5 mb-4 rounded-lg bg-mono-50 dark:bg-mono-800/50 border border-mono-200 dark:border-mono-700">
        <div class="flex items-center gap-2 text-xs">
            <i class="fas fa-calendar-check text-mono-400"></i>
            <span class="font-semibold"><?= sanitize($activeBP['period_label']) ?></span>
            <span class="text-mono-400">&bull;</span>
            <span class="text-mono-400"><?= formatDate($activeBP['period_start']) ?> &ndash; <?= formatDate($activeBP['period_end']) ?></span>
            <?php if ($activeBP['due_date']): ?>
            <span class="text-mono-400">&bull;</span>
            <span class="text-mono-400">Due: <?= formatDate($activeBP['due_date']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($activeBP['status'] === 'active' && $fund['status'] === 'active'): ?>
        <div class="flex items-center gap-3">
            <form method="POST" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="close_period">
                <input type="hidden" name="period_id" value="<?= $activeBP['id'] ?>">
                <button type="submit" class="text-[10px] font-medium text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 transition-colors" onclick="return confirm('Close this billing period?')">
                    <i class="fas fa-lock text-[9px] mr-0.5"></i> Close Period
                </button>
            </form>
            <form method="POST" class="inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_period">
                <input type="hidden" name="period_id" value="<?= $activeBP['id'] ?>">
                <button type="submit" class="text-[10px] font-medium text-red-400 hover:text-red-600 dark:hover:text-red-300 transition-colors" onclick="return confirm('Delete this billing period and all its payments? This cannot be undone.')">
                    <i class="fas fa-trash text-[9px] mr-0.5"></i> Delete Period
                </button>
            </form>
        </div>
        <?php elseif ($activeBP['status'] === 'closed'): ?>
        <form method="POST" class="inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_period">
            <input type="hidden" name="period_id" value="<?= $activeBP['id'] ?>">
            <button type="submit" class="text-[10px] font-medium text-red-400 hover:text-red-600 dark:hover:text-red-300 transition-colors" onclick="return confirm('Delete this closed period and all its payments? This cannot be undone.')">
                <i class="fas fa-trash text-[9px] mr-0.5"></i> Delete Period
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="grid lg:grid-cols-5 gap-6">
        <!-- Student Payment Status / Fund Activity -->
        <div class="lg:col-span-3 bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
            <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
                <h3 class="text-sm font-semibold">
                    <?php if ($isGeneral): ?>
                    Deposits
                    <?php elseif ($isVoluntary): ?>
                    Contributions
                    <?php else: ?>
                    Student Payments
                    <?php endif; ?>
                    <?php if ($isRecurring && $activeBP): ?>
                    <span class="text-mono-400 font-normal">&mdash; <?= sanitize($activeBP['period_label']) ?></span>
                    <?php endif; ?>
                </h3>
            </div>
            <?php if ($isGeneral): ?>
            <!-- General fund: show recent deposits -->
            <div class="divide-y divide-mono-100 dark:divide-mono-800 max-h-[500px] overflow-y-auto scrollbar-thin">
                <?php if (empty($paymentHistory)): ?>
                <div class="px-5 py-8 text-center">
                    <i class="fas fa-piggy-bank text-2xl text-mono-200 dark:text-mono-700 mb-2"></i>
                    <p class="text-xs text-mono-400">No deposits yet. Record a deposit to get started.</p>
                </div>
                <?php else: ?>
                <?php foreach ($paymentHistory as $dep): ?>
                <div class="px-5 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/20 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-piggy-bank text-[9px] text-blue-500"></i>
                        </div>
                        <div>
                            <span class="text-sm font-medium">
                                <?php if ($dep['student_id']): ?>
                                    <?= sanitize($dep['last_name'] . ', ' . $dep['first_name']) ?>
                                <?php else: ?>
                                    Deposit<?= $dep['notes'] ? ' — ' . sanitize($dep['notes']) : '' ?>
                                <?php endif; ?>
                            </span>
                            <div class="text-[10px] text-mono-400"><?= formatDate($dep['payment_date']) ?> · <?= ucfirst(str_replace('_', ' ', $dep['payment_method'])) ?></div>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">+<?= formatMoney($dep['amount_paid']) ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php elseif ($isVoluntary): ?>
            <!-- Voluntary fund: show who contributed, no balance tracking -->
            <div class="divide-y divide-mono-100 dark:divide-mono-800 max-h-[500px] overflow-y-auto scrollbar-thin">
                <?php foreach ($fundAssignees as $assignee):
                    $contributed = $isRecurring && isset($assignee['period_paid']) ? $assignee['period_paid'] : $assignee['total_paid'];
                ?>
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                                <span class="text-[9px] font-semibold text-mono-500"><?= getInitials($assignee['first_name'] . ' ' . $assignee['last_name']) ?></span>
                            </div>
                            <div>
                                <span class="text-sm font-medium"><?= sanitize($assignee['last_name'] . ', ' . $assignee['first_name']) ?></span>
                                <?php if ($isRecurring && $assignee['total_paid'] > 0): ?>
                                <span class="text-[10px] text-mono-400 ml-1">(Total: <?= formatMoney($assignee['total_paid']) ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-xs font-medium <?= $contributed > 0 ? 'text-emerald-500' : 'text-mono-300 dark:text-mono-600' ?>">
                            <?= $contributed > 0 ? formatMoney($contributed) : '—' ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($fundAssignees)): ?>
                <div class="px-5 py-8 text-center text-xs text-mono-400">No students assigned to this fund.</div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <!-- Standard fund: per-student balance tracking -->
            <div class="divide-y divide-mono-100 dark:divide-mono-800 max-h-[500px] overflow-y-auto scrollbar-thin">
                <?php foreach ($fundAssignees as $assignee):
                    $paid = $isRecurring && isset($assignee['period_paid']) ? $assignee['period_paid'] : $assignee['total_paid'];
                    $balance = $fund['amount'] - $paid;
                    $pct = $fund['amount'] > 0 ? min(100, ($paid / $fund['amount']) * 100) : 0;
                ?>
                <div class="px-5 py-3">
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                                <span class="text-[9px] font-semibold text-mono-500"><?= getInitials($assignee['first_name'] . ' ' . $assignee['last_name']) ?></span>
                            </div>
                            <div>
                                <span class="text-sm font-medium"><?= sanitize($assignee['last_name'] . ', ' . $assignee['first_name']) ?></span>
                                <?php if ($isRecurring): ?>
                                <span class="text-[10px] text-mono-400 ml-1">(Total: <?= formatMoney($assignee['total_paid']) ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-xs font-medium <?= $balance <= 0 ? 'text-emerald-500' : 'text-mono-500' ?>">
                            <?= $balance <= 0 ? '&#10003; Paid' : formatMoney($balance) . ' due' ?>
                        </span>
                    </div>
                    <div class="ml-9">
                        <div class="w-full h-1 bg-mono-100 dark:bg-mono-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all <?= $pct >= 100 ? 'bg-emerald-500' : 'bg-mono-400' ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="text-[10px] text-mono-400"><?= formatMoney($paid) ?> / <?= formatMoney($fund['amount']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($fundAssignees)): ?>
                <div class="px-5 py-8 text-center text-xs text-mono-400">No students assigned to this fund.</div>
                <?php endif; ?>
            </div>
            <?php endif; /* end fund type panels */ ?>
        </div>

        <!-- Record Payment -->
        <div class="lg:col-span-2">
            <?php if ($fund['status'] === 'active'): ?>
            <script>
                window.__fundStudents = <?= json_encode(array_map(function($a) use ($isRecurring, $fund) {
                    $aPaid = $isRecurring && isset($a['period_paid']) ? $a['period_paid'] : $a['total_paid'];
                    $aDue = $fund['amount'] - $aPaid;
                    return [
                        'id' => $a['id'],
                        'name' => $a['last_name'] . ', ' . $a['first_name'],
                        'initials' => mb_strtoupper(mb_substr($a['first_name'],0,1) . mb_substr($a['last_name'],0,1)),
                        'due' => $aDue,
                        'dueLabel' => $aDue <= 0 ? 'Paid' : '₱' . number_format($aDue, 2) . ' due'
                    ];
                }, $fundAssignees), JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            </script>
            <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 sticky top-20"
                 x-data="{
                    isDeposit: <?= $isGeneral ? 'true' : 'false' ?>,
                    showStudentPicker: false,
                    studentSearch: '',
                    selectedStudentId: '',
                    selectedStudentLabel: '',
                    students: window.__fundStudents || [],
                    get filteredStudents() {
                        if (!this.studentSearch) return this.students;
                        const q = this.studentSearch.toLowerCase();
                        return this.students.filter(s => s.name.toLowerCase().includes(q));
                    },
                    pickStudent(s) {
                        this.selectedStudentId = s.id;
                        this.selectedStudentLabel = s.name;
                        this.showStudentPicker = false;
                        this.studentSearch = '';
                    },
                    clearStudent() {
                        this.selectedStudentId = '';
                        this.selectedStudentLabel = '';
                    }
                 }"
                 @keydown.escape.window="showStudentPicker = false">
                <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
                    <h3 class="text-sm font-semibold"><i class="fas fa-plus-circle mr-1 text-mono-400"></i> Record Payment</h3>
                </div>
                <form method="POST" class="p-5 space-y-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="is_deposit" :value="isDeposit ? '1' : ''">

                    <!-- Payment Type Toggle -->
                    <?php if ($isGeneral): ?>
                    <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800">
                        <i class="fas fa-piggy-bank text-blue-500 text-xs"></i>
                        <span class="text-[11px] text-blue-600 dark:text-blue-400">General fund &mdash; all payments are recorded as deposits</span>
                    </div>
                    <?php else: ?>
                    <div class="flex gap-1.5 p-1 bg-mono-100 dark:bg-mono-800 rounded-lg">
                        <button type="button" @click="isDeposit = false"
                                :class="!isDeposit ? 'bg-white dark:bg-mono-700 shadow-sm font-semibold' : 'text-mono-400 hover:text-mono-600 dark:hover:text-mono-300'"
                                class="flex-1 px-3 py-1.5 text-[11px] rounded-md transition-all text-center">
                            <i class="fas fa-user mr-1"></i> Student
                        </button>
                        <button type="button" @click="isDeposit = true"
                                :class="isDeposit ? 'bg-white dark:bg-mono-700 shadow-sm font-semibold' : 'text-mono-400 hover:text-mono-600 dark:hover:text-mono-300'"
                                class="flex-1 px-3 py-1.5 text-[11px] rounded-md transition-all text-center">
                            <i class="fas fa-piggy-bank mr-1"></i> Deposit
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php if ($isRecurring && !empty($billingPeriods)): ?>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Billing Period</label>
                        <select name="billing_period_id" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <?php foreach ($billingPeriods as $bp): ?>
                            <option value="<?= $bp['id'] ?>" <?= $bp['id'] == $activePeriodId ? 'selected' : '' ?>><?= sanitize($bp['period_label']) ?><?= $bp['status'] === 'closed' ? ' (Closed)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Student Select (hidden when deposit) -->
                    <div x-show="!isDeposit" x-transition>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Student</label>
                        <input type="hidden" name="student_id" :value="selectedStudentId" :required="!isDeposit">
                        <button type="button" @click="showStudentPicker = true; $nextTick(() => $refs.studentSearchInput.focus())"
                                class="w-full flex items-center justify-between px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100 text-left transition-colors hover:border-mono-300 dark:hover:border-mono-600">
                            <span :class="selectedStudentLabel ? '' : 'text-mono-400'" x-text="selectedStudentLabel || 'Select student...'"></span>
                            <i class="fas fa-search text-[10px] text-mono-400"></i>
                        </button>
                        <template x-if="selectedStudentLabel">
                            <button type="button" @click="clearStudent()" class="mt-1 text-[10px] text-mono-400 hover:text-red-500 transition-colors">
                                <i class="fas fa-times mr-0.5"></i> Clear selection
                            </button>
                        </template>
                    </div>

                    <!-- Student Picker Modal -->
                    <div x-show="showStudentPicker" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                        <!-- Backdrop -->
                        <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="showStudentPicker = false; studentSearch = ''"></div>
                        <!-- Modal -->
                        <div class="relative w-full max-w-md bg-white dark:bg-mono-900 rounded-2xl border border-mono-200 dark:border-mono-800 shadow-2xl overflow-hidden"
                             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             @click.away="showStudentPicker = false; studentSearch = ''">
                            <!-- Header -->
                            <div class="px-5 py-4 border-b border-mono-100 dark:border-mono-800">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-sm font-semibold"><i class="fas fa-user-check mr-1.5 text-mono-400"></i> Select Student</h3>
                                    <button type="button" @click="showStudentPicker = false; studentSearch = ''" class="p-1 text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 transition-colors">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </div>
                                <!-- Search -->
                                <div class="relative">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-mono-400 text-xs"></i>
                                    <input type="text" x-model="studentSearch" x-ref="studentSearchInput"
                                           placeholder="Search by name..."
                                           class="w-full pl-9 pr-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100"
                                           @keydown.escape="showStudentPicker = false; studentSearch = ''">
                                </div>
                            </div>
                            <!-- Student list -->
                            <div class="max-h-[320px] overflow-y-auto scrollbar-thin">
                                <template x-for="s in filteredStudents" :key="s.id">
                                    <button type="button" @click="pickStudent(s)"
                                            :class="selectedStudentId == s.id ? 'bg-mono-50 dark:bg-mono-800 border-l-2 border-mono-900 dark:border-mono-100' : 'border-l-2 border-transparent hover:bg-mono-50 dark:hover:bg-mono-800/50'"
                                            class="w-full flex items-center gap-3 px-5 py-3 text-left transition-colors">
                                        <div class="w-8 h-8 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center flex-shrink-0">
                                            <span class="text-[10px] font-semibold text-mono-500" x-text="s.initials"></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium truncate" x-text="s.name"></p>
                                            <p class="text-[10px]" :class="s.due <= 0 ? 'text-emerald-500' : 'text-mono-400'" x-text="s.dueLabel"></p>
                                        </div>
                                        <i x-show="selectedStudentId == s.id" class="fas fa-check text-xs text-emerald-500"></i>
                                    </button>
                                </template>
                                <div x-show="filteredStudents.length === 0" class="px-5 py-8 text-center">
                                    <i class="fas fa-search text-mono-200 dark:text-mono-700 text-lg mb-2"></i>
                                    <p class="text-xs text-mono-400">No students match your search</p>
                                </div>
                            </div>
                            <!-- Footer -->
                            <div class="px-5 py-3 border-t border-mono-100 dark:border-mono-800 text-[10px] text-mono-400 text-center" x-text="filteredStudents.length + ' student' + (filteredStudents.length !== 1 ? 's' : '')"></div>
                        </div>
                    </div>

                    <!-- Deposit info -->
                    <div x-show="isDeposit" x-transition x-cloak>
                        <div class="flex items-center gap-2 px-3 py-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800">
                            <i class="fas fa-piggy-bank text-amber-500 text-xs"></i>
                            <span class="text-[11px] text-amber-600 dark:text-amber-400">General deposit — no student contributor</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mono-400 text-xs">&#8369;</span>
                            <input type="number" name="amount_paid" required step="0.01" min="0.01" :value="isDeposit ? '' : '<?= ($isGeneral || $isVoluntary) ? '' : $fund['amount'] ?>'" :placeholder="isDeposit || <?= ($isGeneral || $isVoluntary) ? 'true' : 'false' ?> ? 'Enter amount...' : ''"
                                   class="w-full pl-7 pr-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Date</label>
                        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"
                               class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Method</label>
                        <select name="payment_method" class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5" x-text="isDeposit ? 'Source / Notes' : 'Notes'">Notes</label>
                        <input type="text" name="notes" :placeholder="isDeposit ? 'e.g. Fundraiser proceeds, Donation...' : 'Optional notes...'"
                               class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-semibold hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                        <i class="fas" :class="isDeposit ? 'fa-piggy-bank' : 'fa-check'" class="mr-1"></i>
                        <span x-text="isDeposit ? 'Record Deposit' : 'Record Payment'"></span>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment History -->
    <?php if (!empty($paymentHistory)): ?>
    <div class="mt-6 bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h3 class="text-sm font-semibold">
                <i class="fas fa-receipt mr-1.5 text-mono-400"></i> Payment History
                <?php if ($isRecurring && $activeBP): ?>
                <span class="text-mono-400 font-normal">&mdash; <?= sanitize($activeBP['period_label']) ?></span>
                <?php endif; ?>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-mono-100 dark:border-mono-800">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Date</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Student</th>
                        <?php if ($isRecurring): ?><th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden md:table-cell">Period</th><?php endif; ?>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 hidden sm:table-cell">Method</th>
                        <th class="text-right px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400">Amount</th>
                        <th class="text-right px-5 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mono-100 dark:divide-mono-800">
                    <?php foreach ($paymentHistory as $payment): ?>
                    <tr class="hover:bg-mono-50 dark:hover:bg-mono-800/50 transition-colors group">
                        <td class="px-5 py-3 text-mono-500"><?= formatDate($payment['payment_date']) ?></td>
                        <td class="px-5 py-3 font-medium">
                            <?php if ($payment['student_id']): ?>
                                <?= sanitize($payment['last_name'] . ', ' . $payment['first_name']) ?>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                    <i class="fas fa-piggy-bank text-[10px]"></i> Deposit
                                </span>
                                <?php if ($payment['notes']): ?>
                                <span class="text-[10px] text-mono-400 ml-1">&mdash; <?= sanitize($payment['notes']) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <?php if ($isRecurring): ?><td class="px-5 py-3 text-mono-400 hidden md:table-cell"><?= sanitize($payment['period_label'] ?? '&mdash;') ?></td><?php endif; ?>
                        <td class="px-5 py-3 text-mono-400 hidden sm:table-cell capitalize"><?= str_replace('_', ' ', $payment['payment_method']) ?></td>
                        <td class="px-5 py-3 text-right font-semibold"><?= formatMoney($payment['amount_paid']) ?></td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" class="inline opacity-0 group-hover:opacity-100 transition-opacity" onsubmit="return confirm('Remove this payment?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_payment">
                                <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                <button type="submit" class="p-1 text-mono-400 hover:text-red-500"><i class="fas fa-times text-xs"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
