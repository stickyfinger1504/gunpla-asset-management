<?php
ob_start(); // Buffer output to prevent warnings from breaking JSON
require_once '../includes/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['backlogid'])) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Missing backlogid']);
    exit;
}

$backlogid = (int)$data['backlogid'];
$canvas_data = $data['canvas_data'] ?? null;
$base64_image = $data['image'] ?? null;
try {
    $success = save_blueprint($conn, $backlogid, $canvas_data, $base64_image);
    ob_clean();
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
