<?php
/**
 * Tool Inventory Functions
 * CRUD operations for tool_inventory table
 */

function get_tool_brands($conn) {
    $sql = "SELECT id, name FROM dim_brand WHERE section = 'tool' ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_tool_categories($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'toolbox' AND module = 'category' ORDER BY label ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_tool_statuses($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'toolbox' AND module = 'status' ORDER BY id ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_tool_inventory($conn, $filters = []) {
    $sql = "SELECT * FROM vw_tool_inventory WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['filter_brand'])) {
        $sql .= " AND brandid = ?";
        $params[] = (int)$filters['filter_brand'];
        $types .= "i";
    }

    if (!empty($filters['filter_category'])) {
        $sql .= " AND categoryid = ?";
        $params[] = (int)$filters['filter_category'];
        $types .= "i";
    }

    if (!empty($filters['filter_status'])) {
        $sql .= " AND statusid = ?";
        $params[] = (int)$filters['filter_status'];
        $types .= "i";
    }

    if (!empty($filters['search'])) {
        $search_term = '%' . $filters['search'] . '%';
        $sql .= " AND (name LIKE ? OR brand LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }

    $sort = $filters['sortby'] ?? 'date_desc';
    switch ($sort) {
        case 'name_asc':  $sql .= " ORDER BY name ASC"; break;
        case 'name_desc': $sql .= " ORDER BY name DESC"; break;
        case 'date_asc':  $sql .= " ORDER BY createdat ASC"; break;
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

function add_tool($conn, $data) {
    if (empty($data['name'])) return false;

    $brand     = !empty($data['brand'])       ? (int)$data['brand']       : null;
    $category  = !empty($data['category'])    ? (int)$data['category']    : null;
    $status    = !empty($data['status'])      ? (int)$data['status']      : null;
    $quantity  = !empty($data['quantity'])    ? (int)$data['quantity']    : 1;
    $unit      = !empty($data['unit'])        ? $data['unit']             : null;
    $price     = !empty($data['pricebought']) ? (int)$data['pricebought'] : null;
    $date      = !empty($data['datebought'])  ? $data['datebought']       : null;
    $imagepath = !empty($data['imagepath'])   ? $data['imagepath']        : null;
    $link      = !empty($data['link'])        ? $data['link']             : null;
    $notes     = !empty($data['notes'])       ? $data['notes']            : null;

    $stmt = $conn->prepare(
        "INSERT INTO tool_inventory (name, brand, category, status, quantity, unit, pricebought, datebought, imagepath, link, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("siiiisissss",
        $data['name'], $brand, $category, $status, $quantity,
        $unit, $price, $date, $imagepath, $link, $notes
    );
    return $stmt->execute();
}

function update_tool($conn, $data) {
    if (empty($data['edit_id']) || empty($data['name'])) return false;

    $brand     = !empty($data['brand'])       ? (int)$data['brand']       : null;
    $category  = !empty($data['category'])    ? (int)$data['category']    : null;
    $status    = !empty($data['status'])      ? (int)$data['status']      : null;
    $quantity  = !empty($data['quantity'])    ? (int)$data['quantity']    : 1;
    $unit      = !empty($data['unit'])        ? $data['unit']             : null;
    $price     = !empty($data['pricebought']) ? (int)$data['pricebought'] : null;
    $date      = !empty($data['datebought'])  ? $data['datebought']       : null;
    $imagepath = !empty($data['imagepath'])   ? $data['imagepath']        : null;
    $link      = !empty($data['link'])        ? $data['link']             : null;
    $notes     = (isset($data['notes']) && $data['notes'] !== '') ? $data['notes'] : null;

    $stmt = $conn->prepare(
        "UPDATE tool_inventory
         SET name=?, brand=?, category=?, status=?, quantity=?, unit=?, pricebought=?, datebought=?, imagepath=?, link=?, notes=?
         WHERE toolid=?"
    );
    // s-i-i-i-i-s-i-s-s-s-s-i = 12 params
    $stmt->bind_param("siiiisissssi",
        $data['name'], $brand, $category, $status, $quantity,
        $unit, $price, $date, $imagepath, $link, $notes,
        $data['edit_id']
    );
    return $stmt->execute();
}

function delete_tool($conn, $id) {
    $stmt = $conn->prepare("SELECT imagepath FROM tool_inventory WHERE toolid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM tool_inventory WHERE toolid = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

    if ($success && !empty($old['imagepath'])) {
        delete_image_file($old['imagepath'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
    }
    return $success;
}

function calculate_tool_stats($tools) {
    $stats = [
        'total_tools'      => count($tools),
        'total_spent'      => 0,
        'category_counts'  => [],
        'brand_counts'     => [],
        'status_counts'    => [],
        'low_quantity'     => 0,
        'prime_condition'  => 0,
        'attention_needed' => 0,
    ];

    foreach ($tools as $t) {
        $stats['total_spent'] += (int)($t['pricebought'] ?? 0);

        $cat = $t['category'] ?? 'Unknown';
        $stats['category_counts'][$cat] = ($stats['category_counts'][$cat] ?? 0) + 1;

        $brand = $t['brand'] ?? 'Unknown';
        $stats['brand_counts'][$brand] = ($stats['brand_counts'][$brand] ?? 0) + 1;

        $status = $t['status'] ?? 'Unknown';
        $stats['status_counts'][$status] = ($stats['status_counts'][$status] ?? 0) + 1;

        $status_lower = strtolower($status);
        $qty = (int)($t['quantity'] ?? 1);
        $is_low = $qty <= 3;
        $is_degraded = str_contains($status_lower, 'worn') || 
                       str_contains($status_lower, 'needs') || 
                       str_contains($status_lower, 'broken') || 
                       str_contains($status_lower, 'retired');

        if ($is_low) {
            $stats['low_quantity']++;
        }

        if (str_contains($status_lower, 'good') || str_contains($status_lower, 'new')) {
            $stats['prime_condition']++;
        }

        if ($is_low || $is_degraded) {
            $stats['attention_needed']++;
        }
    }

    return $stats;
}

function update_tool_quantity($conn, int $tool_id, int $delta): bool {
    $stmt = $conn->prepare("UPDATE tool_inventory SET quantity = GREATEST(0, quantity + ?) WHERE toolid = ?");
    $stmt->bind_param("ii", $delta, $tool_id);
    return $stmt->execute();
}

function update_tool_status($conn, int $tool_id, int $status_id): bool {
    $stmt = $conn->prepare("UPDATE tool_inventory SET status = ? WHERE toolid = ?");
    $stmt->bind_param("ii", $status_id, $tool_id);
    return $stmt->execute();
}

function get_recent_tool_logs($conn, int $limit = 4): array {
    $sql = "SELECT tl.*, t.name as tool_name, b.name as brand, c.label as category
            FROM tool_log tl
            LEFT JOIN tool_inventory t ON tl.toolid = t.toolid
            LEFT JOIN dim_brand b ON t.brand = b.id
            LEFT JOIN dim_category c ON t.category = c.id
            ORDER BY tl.log_date DESC, tl.logid DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_latest_logs_by_tool($conn): array {
    $sql = "SELECT toolid, log_type, log_date, notes FROM tool_log ORDER BY log_date ASC, logid ASC";
    $result = $conn->query($sql);
    $logs = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[(int)$row['toolid']] = $row;
        }
    }
    return $logs;
}

function generate_tool_log_id(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // Version 4
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // Variant 1
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function get_tool_by_id($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM vw_tool_inventory WHERE actualid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_tool_logs($conn, $tool_id) {
    $sql = "SELECT tl.*, bp.name as kit_name, bp.inventory_id
            FROM tool_log tl
            LEFT JOIN vw_kit_backlog_plan bp ON tl.backlogid = bp.actualid
            WHERE tl.toolid = ?
            ORDER BY tl.log_date DESC, tl.logid DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $tool_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function add_tool_log($conn, $data) {
    $logid = generate_tool_log_id();
    $toolid = (int)$data['toolid'];
    $log_type = $data['log_type'];
    $log_date = !empty($data['log_date']) ? $data['log_date'] : date('Y-m-d');
    $notes = !empty($data['notes']) ? $data['notes'] : null;
    $backlogid = !empty($data['backlogid']) ? (int)$data['backlogid'] : null;
    $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO tool_log (logid, toolid, log_type, log_date, notes, imagepath, backlogid) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sisssss", $logid, $toolid, $log_type, $log_date, $notes, $imagepath, $backlogid);
    return $stmt->execute();
}

function delete_tool_log($conn, $log_id) {
    $stmt = $conn->prepare("SELECT imagepath FROM tool_log WHERE logid = ?");
    $stmt->bind_param("s", $log_id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM tool_log WHERE logid = ?");
    $stmt->bind_param("s", $log_id);
    $success = $stmt->execute();

    if ($success && !empty($old['imagepath'])) {
        delete_image_file($old['imagepath'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
    }
    return $success;
}
