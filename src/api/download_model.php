<?php
// download_model.php
// This script downloads the ONNX model files from HuggingFace if they don't exist locally
require_once '../includes/bootstrap.php';

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}
$model_dir = '../assets/models';
if (!is_dir($model_dir)) {
    mkdir($model_dir, 0777, true);
}

$files = [
    'lineart.onnx' => 'https://huggingface.co/deepghs/imgutils-models/resolve/main/lineart/lineart.onnx'
];

$results = [];
$all_success = true;

foreach ($files as $filename => $url) {
    $target_path = $model_dir . '/' . $filename;
    
    // Check if file already exists
    if (file_exists($target_path)) {
        $results[$filename] = 'Already exists';
        continue;
    }
    
    // Download the file
    $content = @file_get_contents($url);
    if ($content === false) {
        $results[$filename] = 'Download failed';
        $all_success = false;
    } else {
        if (file_put_contents($target_path, $content)) {
            $results[$filename] = 'Downloaded successfully';
        } else {
            $results[$filename] = 'Save failed. Check permissions.';
            $all_success = false;
        }
    }
}

header('Content-Type: application/json');
if ($all_success) {
    echo json_encode(['success' => true, 'message' => 'Models are ready.', 'details' => $results]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to download one or more model files.', 'details' => $results]);
}
