<?php
/**
 * Transaction Log (Build Progress) Functions
 * All queries use prepared statements for security.
 */

function generate_log_id(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // Version 4
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // Variant 1
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function handle_image_upload(array $file, string $upload_dir = UPLOAD_DIR, string $url_prefix = UPLOAD_URL_PREFIX): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, WebP, GIF'];
    }

    if ($file['size'] > MAX_IMAGE_SIZE) {
        $maxMB = MAX_IMAGE_SIZE / 1024 / 1024;
        return ['success' => false, 'error' => "File too large. Max {$maxMB}MB"];
    }

    $ext = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    };
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $upload_dir . $filename;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save file to disk'];
    }

    return ['success' => true, 'path' => $url_prefix . $filename];
}

function delete_image_file(string $imagepath, string $upload_dir = UPLOAD_DIR, string $url_prefix = UPLOAD_URL_PREFIX): void {
    if (empty($imagepath)) return;
    
    $filepath = str_replace($url_prefix, $upload_dir, $imagepath);
    
    $realpath = realpath($filepath);
    $realdir = realpath($upload_dir);
    if ($realpath && $realdir && str_starts_with($realpath, $realdir)) {
        unlink($realpath);
    }
}

function get_transaction_logs($conn, $filters = []) {
    $sql = "SELECT * FROM vw_kit_transaction_log WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $search_term = '%' . $filters['search'] . '%';
        $sql .= " AND (logname LIKE ? OR name LIKE ? OR logid LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }

    if (!empty($filters['filter_backlog'])) {
        $sql .= " AND actual_backlogid = ?";
        $params[] = (int)$filters['filter_backlog'];
        $types .= "i";
    }

    // Sort (whitelist — safe, no user input in SQL)
    $sort = $filters['sortby'] ?? 'date_desc';
    switch ($sort) {
        case 'date_asc':  $sql .= " ORDER BY createdat ASC"; break;
        case 'name_asc':  $sql .= " ORDER BY logname ASC"; break;
        case 'name_desc': $sql .= " ORDER BY logname DESC"; break;
        default:          $sql .= " ORDER BY createdat DESC";
    }

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_backlog_items_for_dropdown($conn) {
    $in_progress_id = get_category_id_by_label($conn, 'backlogplan', 'status', 'In Progress');
    if (!$in_progress_id) return [];
    $stmt = $conn->prepare("SELECT actualid, name FROM vw_kit_backlog_plan WHERE status = ? ORDER BY name ASC");
    $stmt->bind_param("i", $in_progress_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function add_transaction_log($conn, $data) {
    $logid = generate_log_id();
    $backlogid = (int)($data['backlogid'] ?? 0);
    $logname = $data['logname'] ?? '';
    $notes = $data['notes'] ?? '';
    $imagepath = $data['imagepath'] ?? '';

    $stmt = $conn->prepare(
        "INSERT INTO kit_transaction_log (logid, backlogid, logname, notes, imagepath) 
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sisss", $logid, $backlogid, $logname, $notes, $imagepath);
    return $stmt->execute();
}

function update_transaction_log($conn, $data) {
    $logid = $data['edit_id'] ?? '';
    $backlogid = (int)($data['backlogid'] ?? 0);
    $logname = $data['logname'] ?? '';
    $notes = $data['notes'] ?? '';
    $imagepath = $data['imagepath'] ?? '';

    $stmt = $conn->prepare(
        "UPDATE kit_transaction_log SET backlogid = ?, logname = ?, notes = ?, imagepath = ? WHERE logid = ?"
    );
    $stmt->bind_param("issss", $backlogid, $logname, $notes, $imagepath, $logid);
    return $stmt->execute();
}

function delete_transaction_log($conn, $logid) {
    $stmt = $conn->prepare("DELETE FROM kit_transaction_log WHERE logid = ?");
    $stmt->bind_param("s", $logid);
    return $stmt->execute();
}

function calculate_transaction_stats($items) {
    $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
    $stats = [
        'total_entries' => count($items),
        'kit_counts' => [],
        'recent_count' => 0,
    ];

    foreach ($items as $item) {
        if (!empty($item['name'])) {
            $kit = $item['name'];
            $stats['kit_counts'][$kit] = ($stats['kit_counts'][$kit] ?? 0) + 1;
        }
        if (($item['createdat'] ?? '') >= $week_ago) {
            $stats['recent_count']++;
        }
    }

    return $stats;
}
