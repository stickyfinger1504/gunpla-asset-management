<?php
/**
 * Paint Wishlist Functions
 * CRUD operations for paint_wishlist table
 */

function get_paint_wishlist_items($conn, $filters = []) {
    $sql = "SELECT 
                w.wishlistid as actualid,
                w.wishlistid as id,
                w.name,
                b.name as brand,
                w.brandid,
                p.label as priority,
                w.priorityid,
                w.obtained as obtainedid,
                CASE WHEN w.obtained = 1 THEN 'Yes' ELSE 'No' END as obtained,
                pt.label as painttype,
                w.painttypeid,
                w.link,
                w.notes
            FROM paint_wishlist w
            LEFT JOIN dim_brand b ON w.brandid = b.id AND b.section = 'paint'
            LEFT JOIN dim_category p ON w.priorityid = p.id AND p.section = 'wishlist' AND p.module = 'priority'
            LEFT JOIN dim_category pt ON w.painttypeid = pt.id AND pt.section = 'paintlist' AND pt.module = 'painttype'
            WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $search_term = '%' . $filters['search'] . '%';
        $sql .= " AND (w.name LIKE ? OR b.name LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }

    if (!empty($filters['filter_brand'])) {
        $sql .= " AND w.brandid = ?";
        $params[] = (int)$filters['filter_brand'];
        $types .= "i";
    }

    if (!empty($filters['filter_priority'])) {
        $sql .= " AND w.priorityid = ?";
        $params[] = (int)$filters['filter_priority'];
        $types .= "i";
    }

    if (!empty($filters['filter_painttype'])) {
        $sql .= " AND w.painttypeid = ?";
        $params[] = (int)$filters['filter_painttype'];
        $types .= "i";
    }

    if (!empty($filters['filter_obtained'])) {
        if ($filters['filter_obtained'] === 'obtained') {
            $sql .= " AND w.obtained = 1";
        } elseif ($filters['filter_obtained'] === 'unobtained') {
            $sql .= " AND w.obtained = 0";
        }
    }

    $sql .= " ORDER BY w.wishlistid DESC";

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

function get_paint_wishlist_priorities($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'wishlist' AND module = 'priority' ORDER BY id";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function add_paint_wishlist_item($conn, $data) {
    $painttypeid = !empty($data['painttypeid']) ? (int)$data['painttypeid'] : null;
    $link = !empty($data['link']) ? $data['link'] : null;
    $notes = !empty($data['notes']) ? $data['notes'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO paint_wishlist (name, brandid, obtained, priorityid, painttypeid, link, notes) VALUES (?, ?, 0, ?, ?, ?, ?)"
    );
    $stmt->bind_param("siiiss", $data['name'], $data['brandid'], $data['priorityid'], $painttypeid, $link, $notes);
    return $stmt->execute();
}

function update_paint_wishlist_item($conn, $data) {
    $obtained = (int)($data['obtained'] ?? 0);
    $painttypeid = !empty($data['painttypeid']) ? (int)$data['painttypeid'] : null;
    $link = !empty($data['link']) ? $data['link'] : null;
    $notes = (isset($data['notes']) && $data['notes'] !== '') ? $data['notes'] : null;

    $stmt = $conn->prepare(
        "UPDATE paint_wishlist SET name=?, brandid=?, priorityid=?, obtained=?, painttypeid=?, link=?, notes=? WHERE wishlistid=?"
    );
    $stmt->bind_param("siiisssi", $data['name'], $data['brandid'], $data['priorityid'], $obtained, $painttypeid, $link, $notes, $data['edit_id']);
    return $stmt->execute();
}

function delete_paint_wishlist_item($conn, $id) {
    $stmt = $conn->prepare("DELETE FROM paint_wishlist WHERE wishlistid=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function mark_paint_wishlist_obtained($conn, $id) {
    $stmt = $conn->prepare("UPDATE paint_wishlist SET obtained = 1 WHERE wishlistid=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function calculate_paint_wishlist_stats($items) {
    $stats = [
        'total_items' => count($items),
        'priority_counts' => [],
        'brand_counts' => [],
        'type_counts' => [],
    ];

    foreach ($items as $item) {
        $priority = $item['priority'] ?? 'Unknown';
        $stats['priority_counts'][$priority] = ($stats['priority_counts'][$priority] ?? 0) + 1;

        $brand = $item['brand'] ?? 'Unknown';
        $stats['brand_counts'][$brand] = ($stats['brand_counts'][$brand] ?? 0) + 1;

        $type = $item['painttype'] ?? 'Unknown';
        $stats['type_counts'][$type] = ($stats['type_counts'][$type] ?? 0) + 1;
    }

    return $stats;
}
