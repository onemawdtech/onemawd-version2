<?php
/**
 * eClass - Delete Student
 */
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireNotTeacher();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Section access check for officers
    if (isOfficer() && getUserSection()) {
        $check = $pdo->prepare("SELECT section FROM students WHERE id = ?");
        $check->execute([$id]);
        $student = $check->fetch();
        if (!$student || $student['section'] !== getUserSection()) {
            setFlash('error', 'Access denied.');
            redirect('/students/');
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Student deleted successfully.');
}
redirect('/students/');
