<?php
/**
 * eClass - Generate Payment Ledger Form (Direct PDF Output)
 */
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

$fundId = (int)($_GET['fund_id'] ?? 0);

$stmt = $pdo->prepare("SELECT f.*, s.subject_code FROM funds f LEFT JOIN subjects s ON f.subject_id = s.id WHERE f.id = ?");
$stmt->execute([$fundId]);
$fund = $stmt->fetch();

if (!$fund) { setFlash('error', 'Fund not found.'); redirect('/funds/'); }

// Document number & QR
$docNum = 'FPL-' . date('Ymd') . '-' . strtoupper(substr(md5($fundId . time()), 0, 6));
$verifyUrl = (BASE_URL ?: 'http://localhost:8090/eclass') . '/funds/verify.php?doc=' . urlencode($docNum);

// Get students
$fType = $fund['fund_type'] ?? 'standard';
$assignees = [];
if ($fType !== 'general') {
    if (!empty($fund['subject_id'])) {
        $q = "SELECT st.*, COALESCE((SELECT SUM(amount_paid) FROM fund_payments WHERE fund_id = ? AND student_id = st.id), 0) as paid
              FROM students st JOIN subject_enrollments se ON st.id = se.student_id WHERE se.subject_id = ? AND st.status = 'active'";
        $p = [$fundId, $fund['subject_id']];
    } else {
        $q = "SELECT st.*, COALESCE((SELECT SUM(amount_paid) FROM fund_payments WHERE fund_id = ? AND student_id = st.id), 0) as paid
              FROM students st JOIN fund_assignees fa ON st.id = fa.student_id WHERE fa.fund_id = ?";
        $p = [$fundId, $fundId];
    }
    if (hasSectionScope()) { $q .= " AND st.section = ?"; $p[] = getUserSection(); }
    $q .= " ORDER BY st.last_name, st.first_name";
    $s = $pdo->prepare($q); $s->execute($p); $assignees = $s->fetchAll();
}

// Target amount
$perStudent = $fund['amount'];
if ($fund['collection_type'] === 'recurring') {
    $pc = $pdo->prepare("SELECT COUNT(*) FROM fund_billing_periods WHERE fund_id = ?");
    $pc->execute([$fundId]);
    $perStudent = $fund['amount'] * max(1, $pc->fetchColumn());
}

// Save document
$pdo->prepare("INSERT INTO fund_ledger_documents (fund_id, document_number, qr_code_data, generated_by, period_start, period_end) VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([$fundId, $docNum, $verifyUrl, $_SESSION['user_id'], date('Y-m-01'), date('Y-m-t')]);

// Helper for PDF money
function pdfMoney($amt) { return 'P' . number_format($amt, 2); }

// Build rows JSON
$rows = [];
foreach ($assignees as $i => $st) {
    $prevBal = max(0, $perStudent - $st['paid']);
    $rows[] = [
        $i + 1,
        addslashes(sanitize($st['last_name'] . ', ' . $st['first_name'])),
        $prevBal <= 0 ? 'PAID' : pdfMoney($prevBal),
        '',
        ''
    ];
}
$rowsJson = json_encode($rows);
?>
<!DOCTYPE html>
<html><head>
<script src="https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://unpkg.com/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>
<script src="https://unpkg.com/qrcode-generator@1.4.4/qrcode.js"></script>
</head><body><script>
(function(){
    var qr = qrcode(0, 'M');
    qr.addData('<?= $verifyUrl ?>');
    qr.make();
    var qrImg = qr.createDataURL(4, 0);

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'letter' });
    const pw = doc.internal.pageSize.getWidth();
    const m = 12;

    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('PAYMENT ACKNOWLEDGMENT LEDGER', m, 15);
    
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(100);
    doc.text('OneMAWD Classroom Management', m, 20);

    doc.setTextColor(0);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'bold');
    doc.text('<?= addslashes(sanitize($fund['fund_name'])) ?>', pw - m - 25, 12, { align: 'right' });
    
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(100);
    doc.text('<?= $docNum ?>', pw - m - 25, 17, { align: 'right' });

    doc.addImage(qrImg, 'PNG', pw - m - 22, 6, 20, 20);

    doc.setDrawColor(0);
    doc.setLineWidth(0.3);
    doc.line(m, 28, pw - m, 28);

    doc.setTextColor(0);
    doc.setFontSize(9);
    doc.text('Target: <?= pdfMoney($perStudent) ?>/student', m, 34);
    doc.text('Students: <?= count($assignees) ?>', pw / 2, 34, { align: 'center' });
    doc.text('Date: _______________', pw - m, 34, { align: 'right' });

    doc.autoTable({
        startY: 38,
        head: [['#', 'Student Name', 'Prev. Bal', 'Paid Today', 'Signature']],
        body: <?= $rowsJson ?>,
        theme: 'grid',
        styles: { fontSize: 8, cellPadding: 2, lineColor: [0, 0, 0], lineWidth: 0.1 },
        headStyles: { fillColor: [240, 240, 240], textColor: [0, 0, 0], fontStyle: 'bold', halign: 'center' },
        columnStyles: {
            0: { cellWidth: 10, halign: 'center' },
            1: { cellWidth: 'auto' },
            2: { cellWidth: 25, halign: 'center' },
            3: { cellWidth: 25, halign: 'center' },
            4: { cellWidth: 35 }
        },
        margin: { left: m, right: m },
    });

    const y = doc.lastAutoTable.finalY + 12;
    doc.setFontSize(8);
    doc.setTextColor(0);
    doc.text('Collected by: _______________________', m, y);
    doc.text('Verified by: _______________________', pw - m, y, { align: 'right' });

    doc.setFontSize(7);
    doc.setTextColor(120);
    doc.text('Generated by <?= addslashes(sanitize($_SESSION['username'])) ?> on <?= date('M d, Y g:i A') ?>', pw / 2, y + 8, { align: 'center' });

    // Output as blob and replace page
    var blob = doc.output('blob');
    var url = URL.createObjectURL(blob);
    window.location.replace(url);
})();
</script></body></html>
