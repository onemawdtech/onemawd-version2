<?php
/**
 * eClass - Delete Subject
 */
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

if (isTeacher()) {
    setFlash('error', 'Access denied. Teachers cannot delete subjects.');
    redirect('/subjects/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Subject deleted successfully.');
}
redirect('/subjects/');
