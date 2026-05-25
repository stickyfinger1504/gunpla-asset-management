<?php
require_once '../includes/bootstrap.php';
header('Content-Type: application/json');

$target_dir = __DIR__ . '/../assets/js/';
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$target_file = $target_dir . 'opencv.js';
$url = "https://docs.opencv.org/4.12.0/opencv.js";

$content = file_get_contents($url);
if ($content === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to download OpenCV from CDN. Check your internet connection.']);
    exit;
}

if (file_put_contents($target_file, $content) !== false) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to write file to assets/js folder. Check permissions.']);
}
