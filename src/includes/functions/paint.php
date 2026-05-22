<?php
/**
 * Paint inventory functions
 * CRUD operations for paint_inventory table
 */

function get_paint_brands($conn) {
    $sql = "SELECT id, name FROM dim_brand WHERE section = 'paint' ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_paint_types($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'paintlist' AND module = 'painttype' ORDER BY label ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_thinned_statuses($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'paintlist' AND module = 'thinnedstatus' ORDER BY label ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_amount_levels($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'paintlist' AND module = 'amount' ORDER BY label ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_paint_inventory($conn, $filters = []) {
    $sql = "SELECT * FROM vw_paint_inventory WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['filter_brand'])) {
        $sql .= " AND brandid = ?";
        $params[] = (int)$filters['filter_brand'];
        $types .= "i";
    }

    if (!empty($filters['filter_painttype'])) {
        $sql .= " AND painttypeid = ?";
        $params[] = (int)$filters['filter_painttype'];
        $types .= "i";
    }

    if (!empty($filters['filter_amount'])) {
        $sql .= " AND amountid = ?";
        $params[] = (int)$filters['filter_amount'];
        $types .= "i";
    }

    if (!empty($filters['search'])) {
        $search_term = '%' . $filters['search'] . '%';
        $sql .= " AND (name LIKE ? OR id LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }

    $sort = $filters['sortby'] ?? 'date_desc';
    switch($sort) {
        case 'name_asc':  $sql .= " ORDER BY name ASC"; break;
        case 'name_desc': $sql .= " ORDER BY name DESC"; break;
        case 'date_asc':  $sql .= " ORDER BY createddate ASC"; break;
        default:          $sql .= " ORDER BY createddate DESC";
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

function add_paint($conn, $data) {
    if (empty($data['name']) || empty($data['brand']) || empty($data['painttype'])) return false;

    $thinned = !empty($data['thinned']) ? (int)$data['thinned'] : null;
    $amount = !empty($data['amount']) ? (int)$data['amount'] : null;
    $notes = !empty($data['notes']) ? $data['notes'] : null;
    $createddate = !empty($data['createddate']) ? $data['createddate'] : null;
    $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO paint_inventory (name, brand, painttype, thinned, amount, createddate, notes, imagepath) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("siiiisss", $data['name'], $data['brand'], $data['painttype'], $thinned, $amount, $createddate, $notes, $imagepath);
    return $stmt->execute();
}

function update_paint($conn, $data) {
    if (empty($data['edit_id']) || empty($data['name']) || empty($data['brand']) || empty($data['painttype'])) return false;

    $thinned = !empty($data['thinned']) ? (int)$data['thinned'] : null;
    $amount = !empty($data['amount']) ? (int)$data['amount'] : null;
    $notes = (isset($data['notes']) && $data['notes'] !== '') ? $data['notes'] : null;
    $createddate = !empty($data['createddate']) ? $data['createddate'] : null;
    $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;

    $stmt = $conn->prepare(
        "UPDATE paint_inventory SET name=?, brand=?, painttype=?, thinned=?, amount=?, createddate=?, notes=?, imagepath=? WHERE inventoryid=?"
    );
    $stmt->bind_param("siiiisssi", $data['name'], $data['brand'], $data['painttype'], $thinned, $amount, $createddate, $notes, $imagepath, $data['edit_id']);
    return $stmt->execute();
}

function delete_paint($conn, $id) {
    $stmt = $conn->prepare("SELECT imagepath FROM paint_inventory WHERE inventoryid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old = $result->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM paint_inventory WHERE inventoryid=?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

    if ($success && !empty($old['imagepath'])) {
        delete_image_file($old['imagepath'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
    }
    return $success;
}

function calculate_paint_stats($paints) {
    $stats = [
        'total_paints' => count($paints),
        'brand_counts' => [],
        'type_counts'  => [],
        'amount_counts' => [],
    ];

    if (empty($paints)) return $stats;

    foreach ($paints as $p) {
        $brand = $p['brand'] ?? 'Unknown';
        $stats['brand_counts'][$brand] = ($stats['brand_counts'][$brand] ?? 0) + 1;

        $type = $p['painttype'] ?? 'Unknown';
        $stats['type_counts'][$type] = ($stats['type_counts'][$type] ?? 0) + 1;

        $amount = $p['amount'] ?? 'Unknown';
        $stats['amount_counts'][$amount] = ($stats['amount_counts'][$amount] ?? 0) + 1;
    }

    return $stats;
}
