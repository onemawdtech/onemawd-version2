<?php
/**
 * eClass - Receipt Image Server
 * Serves receipt images stored in the database
 */
ob_start();
require_once dirname(__DIR__) . '/config/app.php';
ob_end_clean(); // Clear any output from app.php

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$paymentId = (int)($_GET['id'] ?? 0);

if (!$paymentId) {
    http_response_code(404);
    exit('Not found');
}

// Get receipt from database
$stmt = $pdo->prepare("SELECT receipt_image, receipt_mime FROM fund_payments WHERE id = ?");
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment || empty($payment['receipt_image'])) {
    http_response_code(404);
    exit('Receipt not found');
}

// Output the image
header('Content-Type: ' . ($payment['receipt_mime'] ?? 'image/jpeg'));
header('Content-Length: ' . strlen($payment['receipt_image']));
header('Cache-Control: private, max-age=3600');
echo $payment['receipt_image'];
