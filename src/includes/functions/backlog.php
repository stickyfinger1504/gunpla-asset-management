<?php
/**
 * Backlog Plan Functions
 */

function get_backlog_items($conn, $filters = []) {
    $sql = "SELECT * FROM vw_kit_backlog_plan WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $sql .= " AND (name LIKE ? OR actualid LIKE ?)";
        $params[] = $search; $params[] = $search;
        $types .= "ss";
    }
    
    if (!empty($filters['filter_status'])) {
        $statusId = (int)$filters['filter_status'];
        $sql .= " AND status = ?";
        $params[] = $statusId;
        $types .= "i";
    }
    
    if (!empty($filters['filter_buildplan'])) {
        $buildplanId = (int)$filters['filter_buildplan'];
        $sql .= " AND buildplanid = ?";
        $params[] = $buildplanId;
        $types .= "i";
    }
    
    $sortby = $filters['sortby'] ?? 'id_desc';
    switch ($sortby) {
        case 'name_asc':
            $sql .= " ORDER BY name ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY name DESC";
            break;
        case 'id_asc':
            $sql .= " ORDER BY actualid ASC";
            break;
        case 'id_desc':
        default:
            $sql .= " ORDER BY actualid DESC";
            break;
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

function get_backlog_statuses($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'backlogplan' AND module = 'status' ORDER BY id";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_backlog_buildplans($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'backlogplan' AND module = 'buildplan' ORDER BY id";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_inventory_kits($conn) {
    $sql = "SELECT actualid, name, notes FROM vw_kit_inventory ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function add_backlog_item($conn, $data) {
    $inventoryid = (int)$data['inventoryid'];
    $buildplanid = (int)$data['buildplanid'];
    $status = (int)$data['statusid'];
    $notes = $data['notes'] ?? '';
    $references = $data['references'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO kit_backlog_plan (inventoryid, buildplanid, status, notes, `references`) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $inventoryid, $buildplanid, $status, $notes, $references);
    return $stmt->execute();
}

function update_backlog_item($conn, $data) {
    $id = (int)$data['edit_id'];
    $inventoryid = (int)$data['inventoryid'];
    $buildplanid = (int)$data['buildplanid'];
    $status = (int)$data['statusid'];
    $notes = $data['notes'] ?? '';
    $references = $data['references'] ?? '';
    
    $stmt = $conn->prepare("UPDATE kit_backlog_plan SET inventoryid=?, buildplanid=?, status=?, notes=?, `references`=? WHERE backlogid=?");
    $stmt->bind_param("iiissi", $inventoryid, $buildplanid, $status, $notes, $references, $id);
    return $stmt->execute();
}

function delete_backlog_item($conn, $id) {
    $stmt = $conn->prepare("SELECT imagepath FROM kit_task WHERE backlogid = ? AND imagepath IS NOT NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = $result->fetch_all(MYSQLI_ASSOC);

    $id = (int)$id;
    $sql = "DELETE FROM kit_backlog_plan WHERE backlogid = $id";
    $success = $conn->query($sql);

    if ($success) {
        foreach ($images as $img) {
            if (!empty($img['imagepath'])) {
                delete_image_file($img['imagepath']);
            }
        }
    }
    return $success;
}

function calculate_backlog_stats($items) {
    $stats = [
        'total_items' => count($items),
        'status_counts' => [],
        'buildplan_counts' => []
    ];
    
    foreach ($items as $item) {
        if (!empty($item['status_label'])) {
            $status = $item['status_label'];
            $stats['status_counts'][$status] = ($stats['status_counts'][$status] ?? 0) + 1;
        }
        
        if (!empty($item['buildplan_label'])) {
            $buildplan = $item['buildplan_label'];
            $stats['buildplan_counts'][$buildplan] = ($stats['buildplan_counts'][$buildplan] ?? 0) + 1;
        }
    }
    
    return $stats;
}
