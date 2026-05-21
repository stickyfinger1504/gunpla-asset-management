<?php
/**
 * Backlog Plan Functions
 */

function get_backlog_items($conn, $filters = []) {
    $sql = "SELECT * FROM vw_kit_backlog_plan WHERE 1=1";
    
    if (!empty($filters['search'])) {
        $search = $conn->real_escape_string($filters['search']);
        $sql .= " AND (name LIKE '%$search%' OR id LIKE '%$search%')";
    }
    
    if (!empty($filters['filter_status'])) {
        $statusId = (int)$filters['filter_status'];
        $sql .= " AND status = $statusId";
    }
    
    if (!empty($filters['filter_buildplan'])) {
        $buildplanId = (int)$filters['filter_buildplan'];
        $sql .= " AND buildplanid = $buildplanId";
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
    
    $result = $conn->query($sql);
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
    $notes = $conn->real_escape_string($data['notes'] ?? '');
    $references = $conn->real_escape_string($data['references'] ?? '');
    
    $sql = "INSERT INTO kit_backlog_plan (inventoryid, buildplanid, status, notes, `references`) 
            VALUES ($inventoryid, $buildplanid, $status, '$notes', '$references')";
    
    return $conn->query($sql);
}

function update_backlog_item($conn, $data) {
    $id = (int)$data['edit_id'];
    $inventoryid = (int)$data['inventoryid'];
    $buildplanid = (int)$data['buildplanid'];
    $status = (int)$data['statusid'];
    $notes = $conn->real_escape_string($data['notes'] ?? '');
    $references = $conn->real_escape_string($data['references'] ?? '');
    
    $sql = "UPDATE kit_backlog_plan SET 
            inventoryid = $inventoryid, 
            buildplanid = $buildplanid, 
            status = $status, 
            notes = '$notes', 
            `references` = '$references' 
            WHERE backlogid = $id";
    
    return $conn->query($sql);
}

function delete_backlog_item($conn, $id) {
    $id = (int)$id;
    $sql = "DELETE FROM kit_backlog_plan WHERE backlogid = $id";
    return $conn->query($sql);
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
