<?php
/**
 * eClass - Delete Account
 */
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    
    // Prevent self-delete
    if ($id === $_SESSION['user_id']) {
        setFlash('error', 'You cannot delete your own account.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Account deleted.');
    }
}
redirect('/accounts/');
