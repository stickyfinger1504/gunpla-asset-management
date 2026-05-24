<?php
require_once '../includes/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (empty($data['backlogid'])) {
    echo json_encode(['success' => false, 'error' => 'Missing backlogid']);
    exit;
}

$backlogid = (int)$data['backlogid'];
$canvas_data = $data['canvas_data'] ?? null;
$base64_image = $data['image'] ?? null;

$success = save_blueprint($conn, $backlogid, $canvas_data, $base64_image);
echo json_encode(['success' => $success]);
