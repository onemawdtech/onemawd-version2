<?php
/**
 * eClass - Fund Audit Sheet (Simple PDF)
 */
$pageTitle = 'Fund Audit Sheet';
$pageSubtitle = 'Generate PDF';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$officerSection = getUserSection();
$sectionScoped = hasSectionScope();

// Get funds
if ($sectionScoped) {
    $stmt = $pdo->prepare("
        SELECT f.*, s.subject_code, s.subject_name
        FROM funds f
        LEFT JOIN subjects s ON f.subject_id = s.id
        WHERE f.id IN (SELECT fa.fund_id FROM fund_assignees fa JOIN students st ON fa.student_id = st.id WHERE st.section = ?)
           OR f.subject_id IN (SELECT DISTINCT se.subject_id FROM subject_enrollments se JOIN students st ON se.student_id = st.id WHERE st.section = ?)
           OR f.created_by = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$officerSection, $officerSection, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->query("SELECT f.*, s.subject_code, s.subject_name FROM funds f LEFT JOIN subjects s ON f.subject_id = s.id ORDER BY f.created_at DESC");
}
$funds = $stmt->fetchAll();

$selectedFund = (int)($_GET['fund_id'] ?? 0);

// Fetch report data
$fundInfo = null;
$assignees = [];
$ledger = [];
$stats = [];

if ($selectedFund) {
    $stmt = $pdo->prepare("SELECT f.*, s.subject_code, s.subject_name FROM funds f LEFT JOIN subjects s ON f.subject_id = s.id WHERE f.id = ?");
    $stmt->execute([$selectedFund]);
    $fundInfo = $stmt->fetch();

    if ($fundInfo) {
        $fType = $fundInfo['fund_type'] ?? 'standard';
        $isGeneral = $fType === 'general';

        // Get billing periods count
        $periodCount = $pdo->prepare("SELECT COUNT(*) FROM fund_billing_periods WHERE fund_id = ?");
        $periodCount->execute([$selectedFund]);
        $totalPeriods = max(1, $periodCount->fetchColumn());

        // Get payments for ledger
        $payQ = "SELECT fp.*, st.first_name, st.last_name, fbp.period_label
                 FROM fund_payments fp
                 LEFT JOIN students st ON fp.student_id = st.id
                 LEFT JOIN fund_billing_periods fbp ON fp.billing_period_id = fbp.id
                 WHERE fp.fund_id = ?";
        $payParams = [$selectedFund];
        if ($sectionScoped && !$isGeneral) {
            $payQ .= " AND (st.section = ? OR fp.student_id IS NULL)";
            $payParams[] = $officerSection;
        }
        $payQ .= " ORDER BY fp.payment_date, fp.created_at";
        $payStmt = $pdo->prepare($payQ);
        $payStmt->execute($payParams);
        $payments = $payStmt->fetchAll();

        // Get withdrawals for ledger
        $wStmt = $pdo->prepare("SELECT * FROM fund_withdrawals WHERE fund_id = ? ORDER BY withdrawal_date, created_at");
        $wStmt->execute([$selectedFund]);
        $withdrawals = $wStmt->fetchAll();

        // Build ledger
        foreach ($payments as $p) {
            $ledger[] = [
                'date' => $p['payment_date'],
                'description' => $p['student_id'] 
                    ? $p['last_name'] . ', ' . $p['first_name'] 
                    : 'Deposit' . ($p['notes'] ? ' - ' . $p['notes'] : ''),
                'debit' => 0,
                'credit' => $p['amount_paid'],
                'created' => $p['created_at'],
            ];
        }
        foreach ($withdrawals as $w) {
            $ledger[] = [
                'date' => $w['withdrawal_date'],
                'description' => $w['purpose'] . ($w['notes'] ? ' - ' . $w['notes'] : ''),
                'debit' => $w['amount'],
                'credit' => 0,
                'created' => $w['created_at'],
            ];
        }
        usort($ledger, fn($a, $b) => strcmp($a['date'] . $a['created'], $b['date'] . $b['created']));

        // Get assignees
        if (!$isGeneral) {
            $hasSubject = !empty($fundInfo['subject_id']);
            if ($hasSubject) {
                $aQ = "SELECT st.*, COALESCE((SELECT SUM(amount_paid) FROM fund_payments WHERE fund_id = ? AND student_id = st.id), 0) as paid
                       FROM students st JOIN subject_enrollments se ON st.id = se.student_id
                       WHERE se.subject_id = ? AND st.status = 'active'";
                $aParams = [$selectedFund, $fundInfo['subject_id']];
            } else {
                $aQ = "SELECT st.*, COALESCE((SELECT SUM(amount_paid) FROM fund_payments WHERE fund_id = ? AND student_id = st.id), 0) as paid
                       FROM students st JOIN fund_assignees fa ON st.id = fa.student_id WHERE fa.fund_id = ?";
                $aParams = [$selectedFund, $selectedFund];
            }
            if ($sectionScoped) {
                $aQ .= " AND st.section = ?";
                $aParams[] = $officerSection;
            }
            $aQ .= " ORDER BY st.last_name, st.first_name";
            $aStmt = $pdo->prepare($aQ);
            $aStmt->execute($aParams);
            $assignees = $aStmt->fetchAll();
        }

        // Stats
        $totalCollected = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM fund_payments WHERE fund_id = ?");
        $totalCollected->execute([$selectedFund]);
        $collected = $totalCollected->fetchColumn();

        $totalWithdrawn = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM fund_withdrawals WHERE fund_id = ?");
        $totalWithdrawn->execute([$selectedFund]);
        $withdrawn = $totalWithdrawn->fetchColumn();

        $perPerson = $isGeneral ? 0 : $fundInfo['amount'] * $totalPeriods;
        $target = $perPerson * count($assignees);

        $stats = [
            'collected' => $collected,
            'withdrawn' => $withdrawn,
            'balance' => $collected - $withdrawn,
            'target' => $target,
            'per_person' => $perPerson,
            'assignees' => count($assignees),
        ];
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<script src="https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://unpkg.com/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>

<?php
// Helper function for PDF-safe money format (no ₱ symbol)
function pdfMoney($amount) {
    return 'P' . number_format($amount, 2);
}
?>

<div class="max-w-xl mx-auto">
    <!-- Filter -->
    <form method="GET" class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 mb-6">
        <label class="block text-xs font-medium text-mono-500 mb-2">Select Fund</label>
        <div class="flex gap-3">
            <select name="fund_id" class="flex-1 px-3 py-2.5 rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 text-sm">
                <option value="">— Choose Fund —</option>
                <?php foreach ($funds as $f): ?>
                <option value="<?= $f['id'] ?>" <?= $selectedFund == $f['id'] ? 'selected' : '' ?>><?= sanitize($f['fund_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium">
                <i class="fas fa-sync-alt mr-1"></i> Load
            </button>
        </div>
    </form>

    <?php if (!$selectedFund): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-12 text-center">
        <i class="fas fa-file-pdf text-4xl text-mono-200 dark:text-mono-700 mb-3"></i>
        <p class="text-sm text-mono-400">Select a fund to generate audit sheet PDF</p>
    </div>
    <?php elseif ($fundInfo): ?>

    <!-- Fund Summary Card -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 mb-4">
        <h2 class="text-base font-bold mb-3"><?= sanitize($fundInfo['fund_name']) ?></h2>
        <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="p-3 bg-mono-50 dark:bg-mono-800 rounded-lg">
                <p class="text-mono-400 mb-1">Collected</p>
                <p class="text-lg font-bold text-emerald-600"><?= formatMoney($stats['collected']) ?></p>
            </div>
            <div class="p-3 bg-mono-50 dark:bg-mono-800 rounded-lg">
                <p class="text-mono-400 mb-1">Balance</p>
                <p class="text-lg font-bold"><?= formatMoney($stats['balance']) ?></p>
            </div>
            <div class="p-3 bg-mono-50 dark:bg-mono-800 rounded-lg">
                <p class="text-mono-400 mb-1">Target</p>
                <p class="text-lg font-bold"><?= formatMoney($stats['target']) ?></p>
            </div>
            <div class="p-3 bg-mono-50 dark:bg-mono-800 rounded-lg">
                <p class="text-mono-400 mb-1">Students</p>
                <p class="text-lg font-bold"><?= $stats['assignees'] ?></p>
            </div>
        </div>
    </div>

    <!-- Generate Button -->
    <button onclick="generatePDF()" class="w-full py-4 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-bold transition-colors flex items-center justify-center gap-2">
        <i class="fas fa-file-pdf text-lg"></i>
        Download Audit Sheet PDF
    </button>

    <script>
    function generatePDF() {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const pw = doc.internal.pageSize.getWidth();
        const ph = doc.internal.pageSize.getHeight();
        const m = 15;

        // Title
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.text('FUND AUDIT SHEET', pw / 2, 18, { align: 'center' });
        
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text('<?= addslashes(sanitize($fundInfo['fund_name'])) ?>', pw / 2, 24, { align: 'center' });
        
        doc.setLineWidth(0.3);
        doc.line(m, 28, pw - m, 28);

        // Info row
        doc.setFontSize(8);
        doc.text('Date: <?= date('F d, Y') ?>', m, 34);
        doc.text('Per Student: <?= pdfMoney($stats['per_person']) ?>', pw / 2, 34, { align: 'center' });
        doc.text('Total Students: <?= $stats['assignees'] ?>', pw - m, 34, { align: 'right' });

        // Summary
        doc.setFontSize(8);
        doc.text('Collected: <?= pdfMoney($stats['collected']) ?>  |  Withdrawn: <?= pdfMoney($stats['withdrawn']) ?>  |  Balance: <?= pdfMoney($stats['balance']) ?>', pw / 2, 40, { align: 'center' });

        let startY = 45;

        // Transaction Ledger
        <?php if (!empty($ledger)): ?>
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        doc.text('TRANSACTION LEDGER', m, startY);
        startY += 3;

        const ledgerRows = [
            <?php 
            $runningBal = 0;
            foreach ($ledger as $l): 
                $runningBal += $l['credit'] - $l['debit'];
            ?>
            ['<?= date('M d, Y', strtotime($l['date'])) ?>', '<?= addslashes(substr(sanitize($l['description']), 0, 40)) ?>', '<?= $l['debit'] ? pdfMoney($l['debit']) : '' ?>', '<?= $l['credit'] ? pdfMoney($l['credit']) : '' ?>', '<?= pdfMoney($runningBal) ?>'],
            <?php endforeach; ?>
        ];

        doc.autoTable({
            startY: startY,
            head: [['Date', 'Description', 'Debit (Out)', 'Credit (In)', 'Balance']],
            body: ledgerRows,
            foot: [['', 'TOTALS', '<?= pdfMoney($stats['withdrawn']) ?>', '<?= pdfMoney($stats['collected']) ?>', '<?= pdfMoney($stats['balance']) ?>']],
            theme: 'grid',
            styles: { fontSize: 7, cellPadding: 1.5, lineColor: [0, 0, 0], lineWidth: 0.1 },
            headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold' },
            footStyles: { fillColor: [245, 245, 245], textColor: [0, 0, 0], fontStyle: 'bold' },
            columnStyles: { 
                0: { cellWidth: 22 }, 
                1: { cellWidth: 'auto' }, 
                2: { cellWidth: 22, halign: 'right' }, 
                3: { cellWidth: 22, halign: 'right' }, 
                4: { cellWidth: 22, halign: 'right' } 
            },
            margin: { left: m, right: m },
        });

        startY = doc.lastAutoTable.finalY + 10;
        <?php endif; ?>

        // Student Payment Status Table
        doc.setFontSize(9);
        doc.setFont('helvetica', 'bold');
        doc.text('STUDENT PAYMENT STATUS', m, startY);
        startY += 3;

        const studentRows = [
            <?php foreach ($assignees as $i => $a): 
                $bal = $stats['per_person'] - $a['paid'];
            ?>
            ['<?= $i + 1 ?>', '<?= addslashes(sanitize($a['last_name'] . ', ' . $a['first_name'])) ?>', '<?= addslashes(sanitize($a['student_id'])) ?>', '<?= pdfMoney($stats['per_person']) ?>', '<?= pdfMoney($a['paid']) ?>', '<?= $bal > 0 ? pdfMoney($bal) : 'PAID' ?>'],
            <?php endforeach; ?>
        ];

        doc.autoTable({
            startY: startY,
            head: [['#', 'Student Name', 'ID', 'Target', 'Paid', 'Balance']],
            body: studentRows,
            foot: [['', 'TOTAL', '', '<?= pdfMoney($stats['target']) ?>', '<?= pdfMoney($stats['collected']) ?>', '<?= pdfMoney(max($stats['target'] - $stats['collected'], 0)) ?>']],
            theme: 'grid',
            styles: { fontSize: 7, cellPadding: 1.5, lineColor: [0, 0, 0], lineWidth: 0.1 },
            headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold' },
            footStyles: { fillColor: [245, 245, 245], textColor: [0, 0, 0], fontStyle: 'bold' },
            columnStyles: { 
                0: { cellWidth: 8, halign: 'center' }, 
                1: { cellWidth: 'auto' }, 
                2: { cellWidth: 28 }, 
                3: { cellWidth: 22, halign: 'right' }, 
                4: { cellWidth: 22, halign: 'right' }, 
                5: { cellWidth: 22, halign: 'right' } 
            },
            margin: { left: m, right: m },
        });

        // Footer
        const y = doc.lastAutoTable.finalY + 12;
        doc.setFontSize(7);
        doc.setTextColor(100);
        doc.text('Generated by: <?= addslashes(sanitize($_SESSION['username'])) ?>', m, y);
        doc.text('<?= date('F d, Y h:i A') ?>', pw - m, y, { align: 'right' });
        doc.text('This is a computer-generated document.', pw / 2, y + 5, { align: 'center' });

        doc.save('Audit_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $fundInfo['fund_name']) ?>_<?= date('Y-m-d') ?>.pdf');
    }
    </script>

    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
