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

function get_paint_finishes($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'paintlist' AND module = 'finish' ORDER BY label ASC";
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
    $notes = (isset($data['notes']) && $data['notes'] !== '') ? $data['notes'] : null;
    $createddate = !empty($data['createddate']) ? $data['createddate'] : date('Y-m-d H:i:s');
    $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;
    $color_hex = !empty($data['color_hex']) ? $data['color_hex'] : null;
    $finishid = !empty($data['finishid']) ? (int)$data['finishid'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO paint_inventory (name, brand, painttype, thinned, amount, createddate, notes, imagepath, color_hex, finishid) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("siiiissssi", $data['name'], $data['brand'], $data['painttype'], $thinned, $amount, $createddate, $notes, $imagepath, $color_hex, $finishid);
    return $stmt->execute();
}

function update_paint($conn, $data) {
    if (empty($data['edit_id']) || empty($data['name']) || empty($data['brand']) || empty($data['painttype'])) return false;

    $thinned = !empty($data['thinned']) ? (int)$data['thinned'] : null;
    $amount = !empty($data['amount']) ? (int)$data['amount'] : null;
    $notes = (isset($data['notes']) && $data['notes'] !== '') ? $data['notes'] : null;
    $createddate = !empty($data['createddate']) ? $data['createddate'] : null;
    $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;
    $color_hex = !empty($data['color_hex']) ? $data['color_hex'] : null;
    $finishid = !empty($data['finishid']) ? (int)$data['finishid'] : null;

    $stmt = $conn->prepare(
        "UPDATE paint_inventory SET name=?, brand=?, painttype=?, thinned=?, amount=?, createddate=?, notes=?, imagepath=?, color_hex=?, finishid=? WHERE inventoryid=?"
    );
    $stmt->bind_param("siiiisssisi", $data['name'], $data['brand'], $data['painttype'], $thinned, $amount, $createddate, $notes, $imagepath, $color_hex, $finishid, $data['edit_id']);
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

/**
 * Determine if a paint amount level indicates low or empty stock requiring replenishment.
 *
 * @param string|null $amount_label The amount label (e.g. "Low", "Finished", "Few left")
 * @return bool True if the paint level is low/empty, false otherwise
 */
function is_low_stock_paint_amount(?string $amount_label): bool
{
    if (empty($amount_label) || $amount_label === '-') {
        return false;
    }

    $lower = strtolower(trim($amount_label));
    return str_contains($lower, 'low')
        || str_contains($lower, 'empty')
        || str_contains($lower, 'few')
        || str_contains($lower, 'little')
        || str_contains($lower, 'finished');
}

/**
 * Quickly restock a paint bottle to Full stock level.
 *
 * @param mysqli $conn Active database connection
 * @param int $paint_id Paint inventory ID
 * @return array{success: bool, name?: string, message: string} Result summary with status and message
 */
function quick_restock_paint(mysqli $conn, int $paint_id): array
{
    if ($paint_id <= 0) {
        return ['success' => false, 'message' => 'Invalid paint ID'];
    }

    $full_id = get_category_id_by_label($conn, 'paintlist', 'amount', 'Full or virtually full');
    if (!$full_id) {
        $stmt = $conn->prepare("SELECT id FROM dim_category WHERE section = 'paintlist' AND module = 'amount' AND label LIKE '%Full%' LIMIT 1");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $full_id = (int)$r['id'];
        }
    }

    if (!$full_id) {
        return ['success' => false, 'message' => 'Full stock level category not found in system'];
    }

    $n_stmt = $conn->prepare("SELECT name FROM paint_inventory WHERE inventoryid = ?");
    $n_stmt->bind_param("i", $paint_id);
    $n_stmt->execute();
    $name_res = $n_stmt->get_result();
    if ($name_res->num_rows === 0) {
        return ['success' => false, 'message' => 'Paint record not found'];
    }
    $paint_name = $name_res->fetch_assoc()['name'] ?? 'Paint';

    $stmt = $conn->prepare("UPDATE paint_inventory SET amount = ? WHERE inventoryid = ?");
    $stmt->bind_param("ii", $full_id, $paint_id);
    $success = $stmt->execute();

    if ($success) {
        return [
            'success' => true,
            'name' => $paint_name,
            'message' => "Restocked \"{$paint_name}\" to Full!"
        ];
    }

    return ['success' => false, 'message' => 'Database error restocking paint'];
}
