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
    if (empty($data['inventoryid']) || empty($data['buildplanid']) || empty($data['statusid'])) return false;
    $inventoryid = (int)$data['inventoryid'];
    $buildplanid = (int)$data['buildplanid'];
    $status = (int)$data['statusid'];
    $notes = $data['notes'] ?? '';
    $references = $data['references'] ?? '';

    $in_progress_backlog_id = get_category_id_by_label($conn, 'backlogplan', 'status', 'In Progress');
    $done_backlog_id = get_category_id_by_label($conn, 'backlogplan', 'status', 'Done');

    // Restrict Active Plans
    if ($status === $in_progress_backlog_id) {
        $check_stmt = $conn->prepare("SELECT backlogid FROM kit_backlog_plan WHERE inventoryid = ? AND status = ? LIMIT 1");
        $check_stmt->bind_param("ii", $inventoryid, $in_progress_backlog_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            return false;
        }
    }

    $stmt = $conn->prepare("INSERT INTO kit_backlog_plan (inventoryid, buildplanid, status, notes, `references`) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiss", $inventoryid, $buildplanid, $status, $notes, $references);
    $success = $stmt->execute();

    if ($success) {
        $new_backlogid = $conn->insert_id;
        if ($status === $in_progress_backlog_id) {
            $kit_in_progress_id = get_category_id_by_label($conn, 'kitinventory', 'status', 'In Progress');
            if ($kit_in_progress_id) {
                $upd_stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
                $upd_stmt->bind_param("ii", $kit_in_progress_id, $inventoryid);
                $upd_stmt->execute();
            }
        } elseif ($status === $done_backlog_id) {
            $kit_done_id = get_category_id_by_label($conn, 'kitinventory', 'status', 'Done');
            if ($kit_done_id) {
                $upd_stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
                $upd_stmt->bind_param("ii", $kit_done_id, $inventoryid);
                $upd_stmt->execute();
            }
            $detach_stmt = $conn->prepare("UPDATE kit_backlog_plan SET inventoryid = NULL WHERE inventoryid = ? AND backlogid != ?");
            $detach_stmt->bind_param("ii", $inventoryid, $new_backlogid);
            $detach_stmt->execute();
        }
    }

    return $success;
}

function update_backlog_item($conn, $data) {
    if (empty($data['edit_id']) || empty($data['inventoryid']) || empty($data['buildplanid']) || empty($data['statusid'])) return false;
    $id = (int)$data['edit_id'];
    $inventoryid = (int)$data['inventoryid'];
    $buildplanid = (int)$data['buildplanid'];
    $status = (int)$data['statusid'];
    $notes = $data['notes'] ?? '';
    $references = $data['references'] ?? '';
    
    // Fetch old status
    $stmt_old = $conn->prepare("SELECT status FROM kit_backlog_plan WHERE backlogid = ?");
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $old_result = $stmt_old->get_result();
    $old_status = null;
    if ($old_result && $old_result->num_rows > 0) {
        $old_status = (int)$old_result->fetch_assoc()['status'];
    }

    $in_progress_backlog_id = get_category_id_by_label($conn, 'backlogplan', 'status', 'In Progress');
    $not_started_backlog_id = get_category_id_by_label($conn, 'backlogplan', 'status', 'Not Started');
    $done_backlog_id = get_category_id_by_label($conn, 'backlogplan', 'status', 'Done');

    // Restrict Active Plans
    if ($status === $in_progress_backlog_id) {
        $check_stmt = $conn->prepare("SELECT backlogid FROM kit_backlog_plan WHERE inventoryid = ? AND status = ? AND backlogid != ? LIMIT 1");
        $check_stmt->bind_param("iii", $inventoryid, $in_progress_backlog_id, $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            return false;
        }
    }

    $stmt = $conn->prepare("UPDATE kit_backlog_plan SET inventoryid=?, buildplanid=?, status=?, notes=?, `references`=? WHERE backlogid=?");
    $stmt->bind_param("iiissi", $inventoryid, $buildplanid, $status, $notes, $references, $id);
    $success = $stmt->execute();

    if ($success) {
        if ($status === $in_progress_backlog_id && $old_status !== $in_progress_backlog_id) {
            $kit_in_progress_id = get_category_id_by_label($conn, 'kitinventory', 'status', 'In Progress');
            if ($kit_in_progress_id) {
                $upd_stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
                $upd_stmt->bind_param("ii", $kit_in_progress_id, $inventoryid);
                $upd_stmt->execute();
            }
        } elseif ($old_status === $in_progress_backlog_id && $status === $not_started_backlog_id) {
            $kit_not_started_id = get_category_id_by_label($conn, 'kitinventory', 'status', 'Not Started');
            if ($kit_not_started_id) {
                $upd_stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
                $upd_stmt->bind_param("ii", $kit_not_started_id, $inventoryid);
                $upd_stmt->execute();
            }
        } elseif ($status === $done_backlog_id && $old_status !== $done_backlog_id) {
            $kit_done_id = get_category_id_by_label($conn, 'kitinventory', 'status', 'Done');
            if ($kit_done_id) {
                $upd_stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
                $upd_stmt->bind_param("ii", $kit_done_id, $inventoryid);
                $upd_stmt->execute();
            }
            $detach_stmt = $conn->prepare("UPDATE kit_backlog_plan SET inventoryid = NULL WHERE inventoryid = ? AND backlogid != ?");
            $detach_stmt->bind_param("ii", $inventoryid, $id);
            $detach_stmt->execute();
        }
    }

    return $success;
}

function delete_backlog_item($conn, $id) {
    $stmt = $conn->prepare("SELECT imagepath FROM kit_task WHERE backlogid = ? AND imagepath IS NOT NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = $result->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("DELETE FROM kit_backlog_plan WHERE backlogid = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

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
