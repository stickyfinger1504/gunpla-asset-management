<?php
/**
 * Wishlist Functions
 */

function get_wishlist_items($conn, $filters = []) {
    $sql = "SELECT * FROM vw_kit_wishlist WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $sql .= " AND (name LIKE ? OR brand LIKE ? OR actualid LIKE ?)";
        $params[] = $search; $params[] = $search; $params[] = $search;
        $types .= "sss";
    }
    
    if (!empty($filters['filter_brand'])) {
        $brandId = (int)$filters['filter_brand'];
        $sql .= " AND brandid = ?";
        $params[] = $brandId;
        $types .= "i";
    }
    
    if (!empty($filters['filter_priority'])) {
        $priorityId = (int)$filters['filter_priority'];
        $sql .= " AND priorityid = ?";
        $params[] = $priorityId;
        $types .= "i";
    }
    
    if (!empty($filters['filter_obtained'])) {
        if ($filters['filter_obtained'] === 'obtained') {
            $sql .= " AND obtainedid = 1";
        } elseif ($filters['filter_obtained'] === 'unobtained') {
            $sql .= " AND obtainedid = 0";
        }
    }
    
    $sortby = $filters['sortby'] ?? 'id_desc';
    switch ($sortby) {
        case 'name_asc':
            $sql .= " ORDER BY name ASC";
            break;
        case 'name_desc':
            $sql .= " ORDER BY name DESC";
            break;
        case 'priority_asc':
            $sql .= " ORDER BY priorityid ASC";
            break;
        case 'priority_desc':
            $sql .= " ORDER BY priorityid DESC";
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

function get_wishlist_priorities($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'wishlist' AND module = 'priority' ORDER BY id";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function add_wishlist_item($conn, $data) {
    $name = $data['kit_name'];
    $brandid = (int)$data['brandid'];
    $priorityid = (int)$data['priorityid'];
    $link = $data['link'] ?? '';
    $notes = $data['notes'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO kit_wishlist (name, brandid, obtained, priorityid, link, notes) VALUES (?, ?, 0, ?, ?, ?)");
    $stmt->bind_param("siiss", $name, $brandid, $priorityid, $link, $notes);
    return $stmt->execute();
}

function update_wishlist_item($conn, $data) {
    $id = (int)$data['edit_id'];
    $name = $data['kit_name'];
    $brandid = (int)$data['brandid'];
    $priorityid = (int)$data['priorityid'];
    $obtained = (int)($data['obtained'] ?? 0);
    $link = $data['link'] ?? '';
    $notes = $data['notes'] ?? '';
    
    $stmt = $conn->prepare("UPDATE kit_wishlist SET name=?, brandid=?, priorityid=?, obtained=?, link=?, notes=? WHERE wishlistid=?");
    $stmt->bind_param("siiissi", $name, $brandid, $priorityid, $obtained, $link, $notes, $id);
    return $stmt->execute();
}

function delete_wishlist_item($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM kit_wishlist WHERE wishlistid=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function mark_wishlist_obtained($conn, $id) {
    $stmt = $conn->prepare("UPDATE kit_wishlist SET obtained=1 WHERE wishlistid=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function calculate_wishlist_stats($items) {
    $stats = [
        'total_items' => count($items),
        'priority_counts' => [],
        'brand_counts' => []
    ];
    
    foreach ($items as $item) {
        $priority = $item['priority'] ?? 'Unknown';
        $stats['priority_counts'][$priority] = ($stats['priority_counts'][$priority] ?? 0) + 1;
        
        $brand = $item['brand'] ?? 'Unknown';
        $stats['brand_counts'][$brand] = ($stats['brand_counts'][$brand] ?? 0) + 1;
    }
    
    return $stats;
}
