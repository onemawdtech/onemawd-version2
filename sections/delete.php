<?php
/**
 * eClass - Delete Section
 */
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Check if section has students or accounts
    $stmt = $pdo->prepare("SELECT section_name FROM sections WHERE id = ?");
    $stmt->execute([$id]);
    $section = $stmt->fetch();
    
    if ($section) {
        $studentCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section = ?");
        $studentCount->execute([$section['section_name']]);
        $accountCount = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE section = ?");
        $accountCount->execute([$section['section_name']]);
        
        if ($studentCount->fetchColumn() > 0 || $accountCount->fetchColumn() > 0) {
            setFlash('error', 'Cannot delete section that has students or accounts assigned.');
        } else {
            $pdo->prepare("DELETE FROM sections WHERE id = ?")->execute([$id]);
            setFlash('success', 'Section deleted successfully.');
        }
    }
}
redirect('/sections/');
