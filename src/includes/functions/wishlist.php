<?php
/**
 * Wishlist Functions
 */

function get_wishlist_items($conn, $filters = []) {
    $sql = "SELECT * FROM vw_kit_wishlist WHERE 1=1";
    
    if (!empty($filters['search'])) {
        $search = $conn->real_escape_string($filters['search']);
        $sql .= " AND (name LIKE '%$search%' OR brand LIKE '%$search%' OR id LIKE '%$search%')";
    }
    
    if (!empty($filters['filter_brand'])) {
        $brandId = (int)$filters['filter_brand'];
        $sql .= " AND brandid = $brandId";
    }
    
    if (!empty($filters['filter_priority'])) {
        $priorityId = (int)$filters['filter_priority'];
        $sql .= " AND priorityid = $priorityId";
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
    
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function get_wishlist_priorities($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'wishlist' AND module = 'priority' ORDER BY id";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function add_wishlist_item($conn, $data) {
    $name = $conn->real_escape_string($data['kit_name']);
    $brandid = (int)$data['brandid'];
    $priorityid = (int)$data['priorityid'];
    $link = $conn->real_escape_string($data['link'] ?? '');
    $notes = $conn->real_escape_string($data['notes'] ?? '');
    
    $sql = "INSERT INTO kit_wishlist (name, brandid, obtained, priorityid, link, notes) 
            VALUES ('$name', $brandid, 0, $priorityid, '$link', '$notes')";
    
    return $conn->query($sql);
}

function update_wishlist_item($conn, $data) {
    $id = (int)$data['edit_id'];
    $name = $conn->real_escape_string($data['kit_name']);
    $brandid = (int)$data['brandid'];
    $priorityid = (int)$data['priorityid'];
    $obtained = (int)($data['obtained'] ?? 0);
    $link = $conn->real_escape_string($data['link'] ?? '');
    $notes = $conn->real_escape_string($data['notes'] ?? '');
    
    $sql = "UPDATE kit_wishlist SET 
            name = '$name', 
            brandid = $brandid, 
            priorityid = $priorityid, 
            obtained = $obtained,
            link = '$link', 
            notes = '$notes' 
            WHERE wishlistid = $id";
    
    return $conn->query($sql);
}

function delete_wishlist_item($conn, $id) {
    $id = (int)$id;
    $sql = "DELETE FROM kit_wishlist WHERE wishlistid = $id";
    return $conn->query($sql);
}

function mark_wishlist_obtained($conn, $id) {
    $id = (int)$id;
    $sql = "UPDATE kit_wishlist SET obtained = 1 WHERE wishlistid = $id";
    return $conn->query($sql);
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
