<?php
/**
 * Student import script (updated class list)
 * Run: php database/import_students.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('CLI access only.');
}
require dirname(__DIR__) . '/config/database.php';

$section = 'WMD1B';
$yearLevel = 1;
$studentType = 'regular';

// Clear existing students and linked data
echo "Clearing existing data...\n";
$pdo->exec("DELETE FROM attendance_records");
$pdo->exec("DELETE FROM subject_enrollments");
$pdo->exec("DELETE FROM fund_assignees");
$pdo->exec("DELETE FROM students");
echo "Done.\n\n";

// Updated class list: [first_name, last_name]
$students = [
    ['Karl Aimiel', 'Balagtas'],
    ['Ralph Lauren', 'Batac'],
    ['Ysa', 'Cabrera'],
    ['Johann Skye', 'Caoili'],
    ['Reineer Matthew', 'Catajoy'],
    ['Ayanna Mikhaila', 'Collado'],
    ['Angel Martin', 'Dela Cruz'],
    ['Kelvin Rhay', 'Dela Cruz'],
    ['Ashley Nicole', 'Dizon'],
    ['Elijah', 'Garcia'],
    ['Sire Angelo', 'Jucar'],
    ['Anele Pelagia', 'Lasig'],
    ['Harold', 'Laxamana'],
    ['Hanna Samantha', 'Lising'],
    ['Shann Daennielle', 'Liwanag'],
    ['Josean Joaquin', 'Manimbo'],
    ['Jeiel Jyork', 'Manio'],
    ['Mico', 'Mansilungan'],
    ['Jhon Carlo', 'Marayag'],
    ['Josh Cedrick', 'Martinez'],
    ['Dean Mathew', 'Miranda'],
    ['Ralph Andrei', 'Miranda'],
    ['Divina', 'Navarro'],
    ['Ashton Toby', 'Ocampo'],
    ['Roi Vince', 'Pacleb'],
    ['Aljure Zheron', 'Palabasan'],
    ['Jornd Wallace', 'Paule'],
    ['Alexa Faye', 'Quiambao'],
    ['Tristan Kyle', 'Quiambao'],
    ['Tricia Ann', 'Quinto'],
    ['Joy Divine Grace', 'Ricohermoso'],
    ['Ravindrew', 'Roxas'],
    ['AJ', 'Sabat Jr.'],
    ['Jasper Gian', 'Sampang'],
    ['Evan Junell', 'Tubig'],
    ['Xanelle', 'Villavicencio'],
    ['Raphael Ashton', 'Viray'],
    ['Mhergine Earl', 'Viscaya'],
    ['Aira Shia', 'Yalung'],
];

// Get next sequence number
$currentYear = date('Y');
$lastId = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY student_id DESC LIMIT 1");
$lastId->execute([$currentYear . '-%']);
$lastRow = $lastId->fetch();
$nextSeq = $lastRow ? (int)substr($lastRow['student_id'], strlen($currentYear) + 1) + 1 : 1;

$stmt = $pdo->prepare("INSERT INTO students (student_id, first_name, last_name, student_type, year_level, section) VALUES (?, ?, ?, ?, ?, ?)");

$imported = 0;
$errors = [];

foreach ($students as [$first, $last]) {
    $studentId = $currentYear . '-' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
    try {
        $stmt->execute([$studentId, $first, $last, $studentType, $yearLevel, $section]);
        echo "✓ {$studentId} — {$first} {$last}\n";
        $imported++;
        $nextSeq++;
    } catch (PDOException $e) {
        $errors[] = "{$first} {$last}: " . $e->getMessage();
        echo "✗ {$first} {$last} — " . $e->getMessage() . "\n";
    }
}

echo "\n=============================\n";
echo "Imported: {$imported} / " . count($students) . "\n";
if ($errors) {
    echo "Errors: " . count($errors) . "\n";
}
