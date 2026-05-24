<?php
function get_blueprint_by_backlog($conn, $backlogid) {
    $stmt = $conn->prepare("SELECT * FROM kit_blueprint WHERE backlogid = ?");
    $stmt->bind_param("i", $backlogid);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc();
}

function save_blueprint($conn, $backlogid, $canvas_data, $base64_image) {
    $existing = get_blueprint_by_backlog($conn, $backlogid);
    $imagepath = null;
    
    if (!empty($base64_image)) {
        // Parse the base64 string
        $parts = explode(',', $base64_image);
        if (count($parts) == 2) {
            $data = base64_decode($parts[1]);
            $filename = 'blueprint_' . $backlogid . '_' . time() . '.png';
            $upload_dir = BLUEPRINT_UPLOAD_DIR;
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            file_put_contents($upload_dir . $filename, $data);
            $imagepath = BLUEPRINT_UPLOAD_URL_PREFIX . $filename;
            
            // Cleanup old image
            if ($existing && !empty($existing['imagepath'])) {
                $oldpath = str_replace(BLUEPRINT_UPLOAD_URL_PREFIX, BLUEPRINT_UPLOAD_DIR, $existing['imagepath']);
                if (file_exists($oldpath)) unlink($oldpath);
            }
        }
    } else {
        $imagepath = $existing['imagepath'] ?? null;
    }

    if ($existing) {
        $stmt = $conn->prepare("UPDATE kit_blueprint SET canvas_data = ?, imagepath = ? WHERE backlogid = ?");
        $stmt->bind_param("ssi", $canvas_data, $imagepath, $backlogid);
    } else {
        $stmt = $conn->prepare("INSERT INTO kit_blueprint (backlogid, canvas_data, imagepath) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $backlogid, $canvas_data, $imagepath);
    }
    return $stmt->execute();
}
