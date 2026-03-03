<?php
/**
 * eClass - Attendance Report (PDF Generation)
 */
$pageTitle = 'Attendance Report';
$pageSubtitle = 'Generate PDF attendance summary';
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

$officerSection = getUserSection();
$sectionScoped = hasSectionScope();

// Get subjects
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
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Fetch report data if subject is selected
$reportData = [];
$sessions = [];
$subjectInfo = null;

if ($selectedSubject) {
    // Get subject info
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$selectedSubject]);
    $subjectInfo = $stmt->fetch();

    if ($subjectInfo) {
        // Get sessions in date range
        $sessQuery = "SELECT * FROM attendance_sessions WHERE subject_id = ? AND session_date BETWEEN ? AND ? ORDER BY session_date ASC";
        $stmt = $pdo->prepare($sessQuery);
        $stmt->execute([$selectedSubject, $dateFrom, $dateTo]);
        $sessions = $stmt->fetchAll();

        // Get enrolled students (section-scoped for officers/teachers)
        if ($sectionScoped) {
            $studentsStmt = $pdo->prepare("
                SELECT s.* FROM students s
                JOIN subject_enrollments se ON s.id = se.student_id
                WHERE se.subject_id = ? AND s.status = 'active' AND s.section = ?
                ORDER BY s.last_name, s.first_name
            ");
            $studentsStmt->execute([$selectedSubject, $officerSection]);
        } else {
            $studentsStmt = $pdo->prepare("
                SELECT s.* FROM students s
                JOIN subject_enrollments se ON s.id = se.student_id
                WHERE se.subject_id = ? AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ");
            $studentsStmt->execute([$selectedSubject]);
        }
        $students = $studentsStmt->fetchAll();

        // Build report: for each student, get their status per session
        foreach ($students as $student) {
            $row = [
                'id' => $student['id'],
                'student_id' => $student['student_id'],
                'name' => $student['last_name'] . ', ' . $student['first_name'],
                'section' => $student['section'],
                'sessions' => [],
                'present' => 0,
                'late' => 0,
                'absent' => 0,
                'excused' => 0,
            ];

            foreach ($sessions as $session) {
                $recStmt = $pdo->prepare("SELECT status FROM attendance_records WHERE session_id = ? AND student_id = ?");
                $recStmt->execute([$session['id'], $student['id']]);
                $rec = $recStmt->fetch();
                $status = $rec ? $rec['status'] : null;

                $row['sessions'][] = [
                    'date' => $session['session_date'],
                    'status' => $status,
                ];

                if ($status === 'present') $row['present']++;
                elseif ($status === 'late') $row['late']++;
                elseif ($status === 'absent') $row['absent']++;
                elseif ($status === 'excused') $row['excused']++;
            }

            $totalSessions = count($sessions);
            $row['total_sessions'] = $totalSessions;
            $row['attendance_rate'] = $totalSessions > 0 ? round((($row['present'] + $row['late']) / $totalSessions) * 100, 1) : 0;
            $reportData[] = $row;
        }
    }
}

// Overall stats
$totalStudents = count($reportData);
$totalSessions = count($sessions);
$avgRate = $totalStudents > 0 ? round(array_sum(array_column($reportData, 'attendance_rate')) / $totalStudents, 1) : 0;

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
include dirname(__DIR__) . '/includes/topbar.php';
?>

<!-- jsPDF Libraries -->
<script src="https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="https://unpkg.com/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>

<div class="max-w-5xl">
    <a href="<?= BASE_URL ?>/attendance/" class="inline-flex items-center gap-1.5 text-sm text-mono-400 hover:text-mono-600 dark:hover:text-mono-300 mb-4 transition-colors">
        <i class="fas fa-arrow-left text-xs"></i> Back to Attendance
    </a>

    <!-- Filter Form -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 mb-6">
        <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h3 class="text-sm font-semibold"><i class="fas fa-filter mr-1.5 text-mono-400"></i> Report Parameters</h3>
        </div>
        <form method="GET" class="p-5">
            <div class="grid sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Subject <span class="text-red-500">*</span></label>
                    <select name="subject_id" required class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                        <option value="">Select subject...</option>
                        <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSubject == $s['id'] ? 'selected' : '' ?>><?= sanitize($s['subject_code']) ?> &mdash; <?= sanitize($s['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Date From</label>
                    <input type="date" name="date_from" value="<?= sanitize($dateFrom) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-mono-500 dark:text-mono-400 mb-1.5">Date To</label>
                    <input type="date" name="date_to" value="<?= sanitize($dateTo) ?>"
                           class="w-full px-3 py-2.5 text-sm rounded-lg border border-mono-200 dark:border-mono-700 bg-mono-50 dark:bg-mono-800 focus:outline-none focus:ring-2 focus:ring-mono-900 dark:focus:ring-mono-100">
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-mono-900 dark:bg-mono-100 text-white dark:text-mono-900 text-sm font-medium hover:bg-mono-800 dark:hover:bg-mono-200 transition-colors">
                    <i class="fas fa-chart-bar text-xs"></i> Generate Report
                </button>
                <?php if ($selectedSubject && !empty($reportData)): ?>
                <button type="button" onclick="generatePDF()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border-2 border-red-500 text-red-600 dark:text-red-400 text-sm font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <i class="fas fa-file-pdf text-xs"></i> Download PDF
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($selectedSubject && $subjectInfo): ?>
    <!-- Report Summary -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 p-5 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded bg-mono-100 dark:bg-mono-800 text-mono-500 dark:text-mono-400"><?= sanitize($subjectInfo['subject_code']) ?></span>
                    <h2 class="text-base font-bold"><?= sanitize($subjectInfo['subject_name']) ?></h2>
                </div>
                <p class="text-xs text-mono-400">
                    <i class="fas fa-calendar-alt mr-1"></i>
                    <?= formatDate($dateFrom) ?> &mdash; <?= formatDate($dateTo) ?>
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="text-center p-3 rounded-lg bg-mono-50 dark:bg-mono-800">
                <p class="text-xl font-bold"><?= $totalSessions ?></p>
                <p class="text-[10px] text-mono-400 uppercase tracking-wider font-medium">Sessions</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-mono-50 dark:bg-mono-800">
                <p class="text-xl font-bold"><?= $totalStudents ?></p>
                <p class="text-[10px] text-mono-400 uppercase tracking-wider font-medium">Students</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/10">
                <p class="text-xl font-bold text-emerald-600 dark:text-emerald-400"><?= $avgRate ?>%</p>
                <p class="text-[10px] text-emerald-500 dark:text-emerald-400 uppercase tracking-wider font-medium">Avg Rate</p>
            </div>
            <div class="text-center p-3 rounded-lg bg-red-50 dark:bg-red-900/10">
                <?php
                    $totalAbsences = array_sum(array_column($reportData, 'absent'));
                ?>
                <p class="text-xl font-bold text-red-600 dark:text-red-400"><?= $totalAbsences ?></p>
                <p class="text-[10px] text-red-500 dark:text-red-400 uppercase tracking-wider font-medium">Total Absences</p>
            </div>
        </div>
    </div>

    <?php if (!empty($reportData)): ?>
    <!-- Report Table -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 mb-6">
        <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold"><i class="fas fa-table mr-1.5 text-mono-400"></i> Student Attendance Summary</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="reportTable">
                <thead>
                    <tr class="border-b border-mono-100 dark:border-mono-800">
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 whitespace-nowrap">#</th>
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 whitespace-nowrap">Student</th>
                        <th class="text-left px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 whitespace-nowrap hidden md:table-cell">ID</th>
                        <?php foreach ($sessions as $sess): ?>
                        <th class="text-center px-2 py-3 text-[10px] font-semibold text-mono-400 whitespace-nowrap" title="<?= formatDate($sess['session_date'], 'l, F d, Y') ?>">
                            <?= date('M d', strtotime($sess['session_date'])) ?>
                        </th>
                        <?php endforeach; ?>
                        <th class="text-center px-3 py-3 text-[11px] font-semibold uppercase tracking-wider text-emerald-500 whitespace-nowrap">P</th>
                        <th class="text-center px-3 py-3 text-[11px] font-semibold uppercase tracking-wider text-amber-500 whitespace-nowrap">L</th>
                        <th class="text-center px-3 py-3 text-[11px] font-semibold uppercase tracking-wider text-red-500 whitespace-nowrap">A</th>
                        <th class="text-center px-3 py-3 text-[11px] font-semibold uppercase tracking-wider text-blue-400 whitespace-nowrap">E</th>
                        <th class="text-center px-3 py-3 text-[11px] font-semibold uppercase tracking-wider text-mono-400 whitespace-nowrap">Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mono-100 dark:divide-mono-800">
                    <?php foreach ($reportData as $i => $row): ?>
                    <tr class="hover:bg-mono-50 dark:hover:bg-mono-800/30 transition-colors">
                        <td class="px-4 py-2.5 text-mono-400 text-xs"><?= $i + 1 ?></td>
                        <td class="px-4 py-2.5 font-medium whitespace-nowrap"><?= sanitize($row['name']) ?></td>
                        <td class="px-4 py-2.5 text-mono-400 text-xs hidden md:table-cell"><?= sanitize($row['student_id']) ?></td>
                        <?php foreach ($row['sessions'] as $sess):
                            $s = $sess['status'];
                            $statusLabel = match($s) {
                                'present' => 'P', 'late' => 'L', 'absent' => 'A', 'excused' => 'E', default => '—'
                            };
                            $statusColor = match($s) {
                                'present' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20',
                                'late'    => 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20',
                                'absent'  => 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20',
                                'excused' => 'text-blue-500 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20',
                                default   => 'text-mono-300 dark:text-mono-600'
                            };
                        ?>
                        <td class="text-center px-1 py-2.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-md text-[10px] font-bold <?= $statusColor ?>"><?= $statusLabel ?></span>
                        </td>
                        <?php endforeach; ?>
                        <td class="text-center px-3 py-2.5 font-semibold text-emerald-600 dark:text-emerald-400"><?= $row['present'] ?></td>
                        <td class="text-center px-3 py-2.5 font-semibold text-amber-600 dark:text-amber-400"><?= $row['late'] ?></td>
                        <td class="text-center px-3 py-2.5 font-semibold text-red-600 dark:text-red-400"><?= $row['absent'] ?></td>
                        <td class="text-center px-3 py-2.5 font-semibold text-blue-500 dark:text-blue-400"><?= $row['excused'] ?></td>
                        <td class="text-center px-3 py-2.5">
                            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[10px] font-bold
                                <?= $row['attendance_rate'] >= 90 ? 'bg-emerald-100 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400' :
                                   ($row['attendance_rate'] >= 75 ? 'bg-amber-100 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400' :
                                   'bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400') ?>">
                                <?= $row['attendance_rate'] ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($sessions)): ?>
    <!-- Per-Session Summary -->
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800">
        <div class="px-5 py-4 border-b border-mono-200 dark:border-mono-800">
            <h3 class="text-sm font-semibold"><i class="fas fa-calendar-day mr-1.5 text-mono-400"></i> Per-Session Breakdown</h3>
        </div>
        <div class="divide-y divide-mono-100 dark:divide-mono-800">
            <?php foreach ($sessions as $sess):
                // Get counts for this session
                $countStmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_count,
                        SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN status='excused' THEN 1 ELSE 0 END) as excused_count
                    FROM attendance_records WHERE session_id = ?
                ");
                $countStmt->execute([$sess['id']]);
                $sc = $countStmt->fetch();
                $sRate = $sc['total'] > 0 ? round(($sc['present_count'] / $sc['total']) * 100) : 0;
            ?>
            <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-mono-50 dark:bg-mono-800 flex flex-col items-center justify-center flex-shrink-0">
                        <span class="text-[8px] font-semibold uppercase text-mono-400"><?= date('M', strtotime($sess['session_date'])) ?></span>
                        <span class="text-sm font-bold leading-none"><?= date('d', strtotime($sess['session_date'])) ?></span>
                    </div>
                    <div>
                        <p class="text-sm font-medium"><?= formatDate($sess['session_date'], 'l, F d, Y') ?></p>
                        <?php if ($sess['notes']): ?><p class="text-[10px] text-mono-400"><?= sanitize($sess['notes']) ?></p><?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-xs ml-13 sm:ml-0">
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> <strong><?= $sc['present_count'] ?></strong></span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span> <strong><?= $sc['late_count'] ?></strong></span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> <strong><?= $sc['absent_count'] ?></strong></span>
                    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400"></span> <strong><?= $sc['excused_count'] ?></strong></span>
                    <span class="font-bold ml-2"><?= $sRate ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($selectedSubject): ?>
    <div class="bg-white dark:bg-mono-900 rounded-xl border border-mono-200 dark:border-mono-800 px-6 py-12 text-center">
        <div class="w-12 h-12 rounded-full bg-mono-100 dark:bg-mono-800 flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-calendar-xmark text-mono-300 dark:text-mono-600 text-lg"></i>
        </div>
        <p class="text-sm text-mono-500 font-medium mb-1">No sessions found</p>
        <p class="text-xs text-mono-400">No attendance sessions found for this subject in the selected date range.</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($selectedSubject && !empty($reportData)): ?>
<script>
function generatePDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 14;

    // Colors
    const darkColor = [23, 23, 23];
    const grayColor = [115, 115, 115];
    const lightGray = [163, 163, 163];
    const accentGreen = [16, 185, 129];
    const accentAmber = [245, 158, 11];
    const accentRed = [239, 68, 68];
    const accentBlue = [96, 165, 250];

    // === HEADER ===
    // Logo box
    doc.setFillColor(...darkColor);
    doc.roundedRect(margin, 12, 10, 10, 2, 2, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(7);
    doc.setFont('helvetica', 'bold');
    doc.text('eC', margin + 5, 18.5, { align: 'center' });

    // Title
    doc.setTextColor(...darkColor);
    doc.setFontSize(16);
    doc.setFont('helvetica', 'bold');
    doc.text('Attendance Report', margin + 14, 17);

    // Subject & date info
    doc.setFontSize(9);
    doc.setTextColor(...grayColor);
    doc.setFont('helvetica', 'normal');
    doc.text('<?= addslashes(sanitize($subjectInfo['subject_code'])) ?> — <?= addslashes(sanitize($subjectInfo['subject_name'])) ?>', margin + 14, 22);
    
    // Right side info
    doc.setFontSize(8);
    doc.setTextColor(...lightGray);
    doc.text('Period: <?= formatDate($dateFrom) ?> — <?= formatDate($dateTo) ?>', pageWidth - margin, 14, { align: 'right' });
    doc.text('Generated: <?= date('F d, Y h:i A') ?>', pageWidth - margin, 19, { align: 'right' });
    doc.text('OneMAWD Classroom Management System', pageWidth - margin, 24, { align: 'right' });

    // Divider line
    doc.setDrawColor(229, 229, 229);
    doc.setLineWidth(0.5);
    doc.line(margin, 27, pageWidth - margin, 27);

    // === SUMMARY BOXES ===
    const boxY = 30;
    const boxW = (pageWidth - margin * 2 - 9) / 4;
    const boxH = 14;
    const summaryData = [
        { label: 'TOTAL SESSIONS', value: '<?= $totalSessions ?>', bg: [250, 250, 250], textColor: darkColor },
        { label: 'TOTAL STUDENTS', value: '<?= $totalStudents ?>', bg: [250, 250, 250], textColor: darkColor },
        { label: 'AVG ATTENDANCE', value: '<?= $avgRate ?>%', bg: [236, 253, 245], textColor: accentGreen },
        { label: 'TOTAL ABSENCES', value: '<?= $totalAbsences ?>', bg: [254, 242, 242], textColor: accentRed },
    ];

    summaryData.forEach((item, i) => {
        const x = margin + i * (boxW + 3);
        doc.setFillColor(...item.bg);
        doc.roundedRect(x, boxY, boxW, boxH, 2, 2, 'F');
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(...item.textColor);
        doc.text(item.value, x + boxW / 2, boxY + 7, { align: 'center' });
        doc.setFontSize(5.5);
        doc.setTextColor(...lightGray);
        doc.setFont('helvetica', 'normal');
        doc.text(item.label, x + boxW / 2, boxY + 11.5, { align: 'center' });
    });

    // === STUDENT SUMMARY TABLE ===
    const tableStartY = boxY + boxH + 6;

    // Section header
    doc.setFontSize(9);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...darkColor);
    doc.text('Student Attendance Summary', margin, tableStartY);

    // Build session date headers
    const sessionDates = [<?php foreach ($sessions as $s): ?>'<?= date('M d', strtotime($s['session_date'])) ?>',<?php endforeach ?>];

    // Build table columns: #, Student, ID, ...session dates..., P, L, A, E, Rate
    const columns = [
        { header: '#', dataKey: 'num' },
        { header: 'Student Name', dataKey: 'name' },
        { header: 'Student ID', dataKey: 'sid' },
    ];
    
    sessionDates.forEach((d, i) => {
        columns.push({ header: d, dataKey: 'sess_' + i });
    });

    columns.push(
        { header: 'P', dataKey: 'present' },
        { header: 'L', dataKey: 'late' },
        { header: 'A', dataKey: 'absent' },
        { header: 'E', dataKey: 'excused' },
        { header: 'Rate', dataKey: 'rate' }
    );

    // Build table rows
    const rows = [
        <?php foreach ($reportData as $i => $row): ?>
        {
            num: '<?= $i + 1 ?>',
            name: '<?= addslashes(sanitize($row['name'])) ?>',
            sid: '<?= addslashes(sanitize($row['student_id'])) ?>',
            <?php foreach ($row['sessions'] as $j => $sess): ?>
            sess_<?= $j ?>: '<?= match($sess['status']) { 'present' => 'P', 'late' => 'L', 'absent' => 'A', 'excused' => 'E', default => '-' } ?>',
            <?php endforeach; ?>
            present: '<?= $row['present'] ?>',
            late: '<?= $row['late'] ?>',
            absent: '<?= $row['absent'] ?>',
            excused: '<?= $row['excused'] ?>',
            rate: '<?= $row['attendance_rate'] ?>%'
        },
        <?php endforeach; ?>
    ];

    const sessionColCount = sessionDates.length;
    const totalCols = 3 + sessionColCount + 5; // #, name, id, sessions, P, L, A, E, Rate

    doc.autoTable({
        startY: tableStartY + 3,
        columns: columns,
        body: rows,
        theme: 'plain',
        margin: { left: margin, right: margin },
        styles: {
            fontSize: sessionColCount > 12 ? 5.5 : (sessionColCount > 8 ? 6 : 6.5),
            cellPadding: { top: 2, bottom: 2, left: 1.5, right: 1.5 },
            textColor: darkColor,
            lineColor: [229, 229, 229],
            lineWidth: 0.2,
            font: 'helvetica',
            overflow: 'ellipsize',
        },
        headStyles: {
            fillColor: [245, 245, 245],
            textColor: grayColor,
            fontStyle: 'bold',
            fontSize: sessionColCount > 12 ? 5 : (sessionColCount > 8 ? 5.5 : 6),
            halign: 'center',
        },
        columnStyles: {
            num: { halign: 'center', cellWidth: 7 },
            name: { halign: 'left', cellWidth: sessionColCount > 8 ? 28 : 35, fontStyle: 'bold' },
            sid: { halign: 'left', cellWidth: sessionColCount > 8 ? 18 : 22, textColor: grayColor, fontSize: 5.5 },
            present: { halign: 'center', cellWidth: 7, fontStyle: 'bold', textColor: accentGreen },
            late: { halign: 'center', cellWidth: 7, fontStyle: 'bold', textColor: accentAmber },
            absent: { halign: 'center', cellWidth: 7, fontStyle: 'bold', textColor: accentRed },
            excused: { halign: 'center', cellWidth: 7, fontStyle: 'bold', textColor: accentBlue },
            rate: { halign: 'center', cellWidth: 10, fontStyle: 'bold' },
        },
        didParseCell: function(data) {
            // Style session columns
            if (data.column.dataKey && data.column.dataKey.startsWith('sess_')) {
                data.cell.styles.halign = 'center';
                if (data.section === 'body') {
                    const val = data.cell.raw;
                    if (val === 'P') data.cell.styles.textColor = accentGreen;
                    else if (val === 'L') data.cell.styles.textColor = accentAmber;
                    else if (val === 'A') data.cell.styles.textColor = accentRed;
                    else if (val === 'E') data.cell.styles.textColor = accentBlue;
                    else data.cell.styles.textColor = [200, 200, 200];
                    data.cell.styles.fontStyle = 'bold';
                }
            }
            // Color-code rate
            if (data.column.dataKey === 'rate' && data.section === 'body') {
                const rate = parseFloat(data.cell.raw);
                if (rate >= 90) data.cell.styles.textColor = accentGreen;
                else if (rate >= 75) data.cell.styles.textColor = accentAmber;
                else data.cell.styles.textColor = accentRed;
            }
        },
        alternateRowStyles: {
            fillColor: [252, 252, 252],
        },
    });

    // === PER-SESSION BREAKDOWN TABLE ===
    let currentY = doc.lastAutoTable.finalY + 10;

    // Check if we need a new page
    if (currentY + 30 > pageHeight) {
        doc.addPage();
        currentY = margin + 5;
    }

    doc.setFontSize(9);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...darkColor);
    doc.text('Per-Session Breakdown', margin, currentY);

    const sessionRows = [
        <?php foreach ($sessions as $sess):
            $countStmt2 = $pdo->prepare("
                SELECT COUNT(*) as total,
                    SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as p,
                    SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as l,
                    SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) as a,
                    SUM(CASE WHEN status='excused' THEN 1 ELSE 0 END) as e
                FROM attendance_records WHERE session_id = ?
            ");
            $countStmt2->execute([$sess['id']]);
            $sc2 = $countStmt2->fetch();
            $sRate2 = $sc2['total'] > 0 ? round(($sc2['p'] / $sc2['total']) * 100) : 0;
        ?>
        {
            date: '<?= formatDate($sess['session_date'], 'l, M d, Y') ?>',
            notes: '<?= addslashes(sanitize($sess['notes'] ?? '')) ?>',
            present: '<?= $sc2['p'] ?>',
            late: '<?= $sc2['l'] ?>',
            absent: '<?= $sc2['a'] ?>',
            excused: '<?= $sc2['e'] ?>',
            total: '<?= $sc2['total'] ?>',
            rate: '<?= $sRate2 ?>%'
        },
        <?php endforeach; ?>
    ];

    doc.autoTable({
        startY: currentY + 3,
        columns: [
            { header: 'Date', dataKey: 'date' },
            { header: 'Notes', dataKey: 'notes' },
            { header: 'Present', dataKey: 'present' },
            { header: 'Late', dataKey: 'late' },
            { header: 'Absent', dataKey: 'absent' },
            { header: 'Excused', dataKey: 'excused' },
            { header: 'Total', dataKey: 'total' },
            { header: 'Rate', dataKey: 'rate' },
        ],
        body: sessionRows,
        theme: 'plain',
        margin: { left: margin, right: margin },
        styles: {
            fontSize: 7,
            cellPadding: { top: 2.5, bottom: 2.5, left: 3, right: 3 },
            textColor: darkColor,
            lineColor: [229, 229, 229],
            lineWidth: 0.2,
            font: 'helvetica',
        },
        headStyles: {
            fillColor: [245, 245, 245],
            textColor: grayColor,
            fontStyle: 'bold',
            fontSize: 6.5,
        },
        columnStyles: {
            date: { cellWidth: 45, fontStyle: 'bold' },
            notes: { cellWidth: 60, textColor: grayColor },
            present: { halign: 'center', cellWidth: 18, fontStyle: 'bold', textColor: accentGreen },
            late: { halign: 'center', cellWidth: 18, fontStyle: 'bold', textColor: accentAmber },
            absent: { halign: 'center', cellWidth: 18, fontStyle: 'bold', textColor: accentRed },
            excused: { halign: 'center', cellWidth: 18, fontStyle: 'bold', textColor: accentBlue },
            total: { halign: 'center', cellWidth: 18, fontStyle: 'bold' },
            rate: { halign: 'center', cellWidth: 18, fontStyle: 'bold' },
        },
        didParseCell: function(data) {
            if (data.column.dataKey === 'rate' && data.section === 'body') {
                const rate = parseFloat(data.cell.raw);
                if (rate >= 90) data.cell.styles.textColor = accentGreen;
                else if (rate >= 75) data.cell.styles.textColor = accentAmber;
                else data.cell.styles.textColor = accentRed;
            }
        },
        alternateRowStyles: {
            fillColor: [252, 252, 252],
        },
    });

    // === FOOTER on every page ===
    const totalPages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
        doc.setPage(i);
        doc.setDrawColor(229, 229, 229);
        doc.setLineWidth(0.3);
        doc.line(margin, pageHeight - 10, pageWidth - margin, pageHeight - 10);
        doc.setFontSize(6);
        doc.setTextColor(...lightGray);
        doc.setFont('helvetica', 'normal');
        doc.text('OneMAWD Classroom Management System — Attendance Report', margin, pageHeight - 6);
        doc.text('Page ' + i + ' of ' + totalPages, pageWidth - margin, pageHeight - 6, { align: 'right' });
    }

    // Save the PDF
    const filename = 'Attendance_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $subjectInfo['subject_code']) ?>_<?= $dateFrom ?>_to_<?= $dateTo ?>.pdf';
    doc.save(filename);
}
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
