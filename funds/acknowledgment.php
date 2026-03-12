<?php
/**
 * eClass - Acknowledgment Form Viewer
 * Serves uploaded acknowledgment forms from billing periods
 */
require_once dirname(__DIR__) . '/config/app.php';
requireLogin();

$periodId = (int)($_GET['period_id'] ?? 0);

if (!$periodId) {
    http_response_code(404);
    exit('Not found');
}

// Get form from database
$stmt = $pdo->prepare("SELECT acknowledgment_form, form_mime FROM fund_billing_periods WHERE id = ?");
$stmt->execute([$periodId]);
$period = $stmt->fetch();

if (!$period || empty($period['acknowledgment_form'])) {
    http_response_code(404);
    exit('Form not found');
}

// Output the file
header('Content-Type: ' . ($period['form_mime'] ?? 'application/octet-stream'));
header('Content-Length: ' . strlen($period['acknowledgment_form']));
header('Cache-Control: private, max-age=3600');

// For PDF, display inline; for images, also display inline
$disposition = 'inline';
header('Content-Disposition: ' . $disposition);

echo $period['acknowledgment_form'];
