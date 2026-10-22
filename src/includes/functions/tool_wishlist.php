<?php
/**
 * Tool Wishlist Functions
 * Domain logic & CRUD operations for tool_wishlist table and vw_tool_wishlist view
 */

/**
 * Fetch tool wishlist items with optional filtering and sorting
 *
 * @param mysqli $conn
 * @param array $filters Supported: search, filter_brand, filter_category, filter_priority, filter_obtained, sortby
 * @return array
 */
function get_tool_wishlist_items($conn, $filters = []) {
    $sql = "SELECT * FROM vw_tool_wishlist WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $search = '%' . trim($filters['search']) . '%';
        $sql .= " AND (name LIKE ? OR brand LIKE ? OR notes LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "sss";
    }

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

    if (!empty($filters['filter_priority'])) {
        $sql .= " AND priorityid = ?";
        $params[] = (int)$filters['filter_priority'];
        $types .= "i";
    }

    if (isset($filters['filter_obtained']) && $filters['filter_obtained'] !== '') {
        $val = $filters['filter_obtained'];
        if ($val === 'obtained' || $val === '1' || $val === 1) {
            $sql .= " AND obtainedid = 1";
        } elseif ($val === 'unobtained' || $val === '0' || $val === 0) {
            $sql .= " AND obtainedid = 0";
        }
    }

    $sort = $filters['sortby'] ?? 'date_desc';
    switch ($sort) {
        case 'name_asc':      $sql .= " ORDER BY name ASC"; break;
        case 'name_desc':     $sql .= " ORDER BY name DESC"; break;
        case 'priority_asc':  $sql .= " ORDER BY priorityid ASC"; break;
        case 'priority_desc': $sql .= " ORDER BY priorityid DESC"; break;
        case 'date_asc':      $sql .= " ORDER BY createdat ASC"; break;
        case 'id_asc':        $sql .= " ORDER BY actualid ASC"; break;
        case 'id_desc':       $sql .= " ORDER BY actualid DESC"; break;
        case 'date_desc':
        default:              $sql .= " ORDER BY createdat DESC, actualid DESC"; break;
    }

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Fetch single tool wishlist item by ID
 *
 * @param mysqli $conn
 * @param int $id
 * @return array|null
 */
function get_tool_wishlist_item($conn, $id) {
    $id = (int)$id;
    if ($id <= 0) return null;

    $stmt = $conn->prepare("SELECT * FROM vw_tool_wishlist WHERE actualid = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Get wishlist priorities from dim_category
 *
 * @param mysqli $conn
 * @return array
 */
function get_tool_wishlist_priorities($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'wishlist' AND module = 'priority' ORDER BY id ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Add a new tool wishlist item
 *
 * @param mysqli $conn
 * @param array $data Fields: name, brandid/brand, categoryid/category, priorityid/priority, imagepath, link, notes
 * @return bool
 */
function add_tool_wishlist_item($conn, $data) {
    if (empty($data['name'])) return false;

    $name        = trim($data['name']);
    $brandid     = !empty($data['brandid'])    ? (int)$data['brandid']    : (!empty($data['brand']) ? (int)$data['brand'] : null);
    $categoryid  = !empty($data['categoryid']) ? (int)$data['categoryid'] : (!empty($data['category']) ? (int)$data['category'] : null);
    $priorityid  = !empty($data['priorityid']) ? (int)$data['priorityid'] : (!empty($data['priority']) ? (int)$data['priority'] : null);
    $obtained    = !empty($data['obtained'])   ? 1 : 0;
    $imagepath   = !empty($data['imagepath'])  ? $data['imagepath']       : null;
    $link        = !empty($data['link'])       ? trim($data['link'])      : null;
    $notes       = (isset($data['notes']) && trim($data['notes']) !== '') ? trim($data['notes']) : null;

    $stmt = $conn->prepare(
        "INSERT INTO tool_wishlist (name, brandid, categoryid, priorityid, obtained, imagepath, link, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return false;

    $stmt->bind_param("siiiisss", $name, $brandid, $categoryid, $priorityid, $obtained, $imagepath, $link, $notes);
    return $stmt->execute();
}

/**
 * Update an existing tool wishlist item by wishlistid
 *
 * @param mysqli $conn
 * @param array $data Fields: wishlistid/edit_id/id, name, brandid/brand, categoryid/category, priorityid/priority, obtained, imagepath, link, notes
 * @return bool
 */
function update_tool_wishlist_item($conn, $data) {
    $id = (int)($data['wishlistid'] ?? $data['edit_id'] ?? $data['id'] ?? 0);
    if ($id <= 0 || empty($data['name'])) return false;

    $name        = trim($data['name']);
    $brandid     = !empty($data['brandid'])    ? (int)$data['brandid']    : (!empty($data['brand']) ? (int)$data['brand'] : null);
    $categoryid  = !empty($data['categoryid']) ? (int)$data['categoryid'] : (!empty($data['category']) ? (int)$data['category'] : null);
    $priorityid  = !empty($data['priorityid']) ? (int)$data['priorityid'] : (!empty($data['priority']) ? (int)$data['priority'] : null);
    $obtained    = isset($data['obtained'])    ? (int)$data['obtained']   : 0;
    $link        = !empty($data['link'])       ? trim($data['link'])      : null;
    $notes       = (isset($data['notes']) && trim($data['notes']) !== '') ? trim($data['notes']) : null;

    // Manage imagepath: preserve existing if not explicitly provided
    if (array_key_exists('imagepath', $data)) {
        $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;
    } elseif (array_key_exists('existing_imagepath', $data)) {
        $imagepath = !empty($data['existing_imagepath']) ? $data['existing_imagepath'] : null;
    } else {
        $stmt_cur = $conn->prepare("SELECT imagepath FROM tool_wishlist WHERE wishlistid = ?");
        if ($stmt_cur) {
            $stmt_cur->bind_param("i", $id);
            $stmt_cur->execute();
            $curr = $stmt_cur->get_result()->fetch_assoc();
            $imagepath = $curr['imagepath'] ?? null;
        } else {
            $imagepath = null;
        }
    }

    $stmt = $conn->prepare(
        "UPDATE tool_wishlist
         SET name = ?, brandid = ?, categoryid = ?, priorityid = ?, obtained = ?, imagepath = ?, link = ?, notes = ?
         WHERE wishlistid = ?"
    );
    if (!$stmt) return false;

    $stmt->bind_param("siiiisssi", $name, $brandid, $categoryid, $priorityid, $obtained, $imagepath, $link, $notes, $id);
    return $stmt->execute();
}

/**
 * Delete a tool wishlist item by wishlistid
 * Also cleans up image file if present
 *
 * @param mysqli $conn
 * @param int $id
 * @return bool
 */
function delete_tool_wishlist_item($conn, $id) {
    $id = (int)$id;
    if ($id <= 0) return false;

    $old_image = null;
    $stmt_img = $conn->prepare("SELECT imagepath FROM tool_wishlist WHERE wishlistid = ?");
    if ($stmt_img) {
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $res = $stmt_img->get_result();
        if ($row = $res->fetch_assoc()) {
            $old_image = $row['imagepath'];
        }
    }

    $stmt = $conn->prepare("DELETE FROM tool_wishlist WHERE wishlistid = ?");
    if (!$stmt) return false;

    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

    if ($success && !empty($old_image) && function_exists('delete_image_file') && defined('TOOL_UPLOAD_DIR') && defined('TOOL_UPLOAD_URL_PREFIX')) {
        delete_image_file($old_image, TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
    }

    return $success;
}

/**
 * Mark a tool wishlist item as obtained
 *
 * @param mysqli $conn
 * @param int $id
 * @return bool
 */
function mark_tool_wishlist_obtained($conn, $id) {
    $id = (int)$id;
    if ($id <= 0) return false;

    $stmt = $conn->prepare("UPDATE tool_wishlist SET obtained = 1 WHERE wishlistid = ?");
    if (!$stmt) return false;

    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

/**
 * Induct a wishlist item into tool_inventory
 * - Inserts a new record into tool_inventory with status = 'New' (or provided status)
 * - Updates tool_wishlist setting obtained = 1 and toolid = $new_tool_id
 *
 * @param mysqli $conn
 * @param int $wishlist_id
 * @param array $inventory_data Optional overrides (name, brand, category, price/pricebought, quantity, unit, datebought, link, notes, imagepath)
 * @return int|bool Returns new tool inventory ID on success, false on failure
 */
function induct_tool_to_inventory($conn, $wishlist_id, $inventory_data = []) {
    $wishlist_id = (int)$wishlist_id;
    if ($wishlist_id <= 0) return false;

    $stmt = $conn->prepare("SELECT * FROM tool_wishlist WHERE wishlistid = ? LIMIT 1");
    if (!$stmt) return false;

    $stmt->bind_param("i", $wishlist_id);
    $stmt->execute();
    $wishlist = $stmt->get_result()->fetch_assoc();
    if (!$wishlist) return false;

    $name = !empty($inventory_data['name']) ? trim($inventory_data['name']) : $wishlist['name'];
    $brand = !empty($inventory_data['brand']) 
        ? (int)$inventory_data['brand'] 
        : (!empty($inventory_data['brandid']) ? (int)$inventory_data['brandid'] : (!empty($wishlist['brandid']) ? (int)$wishlist['brandid'] : null));
    $category = !empty($inventory_data['category']) 
        ? (int)$inventory_data['category'] 
        : (!empty($inventory_data['categoryid']) ? (int)$inventory_data['categoryid'] : (!empty($wishlist['categoryid']) ? (int)$wishlist['categoryid'] : null));

    // Resolve 'New' status ID from dim_category
    $status = null;
    if (!empty($inventory_data['status'])) {
        $status = (int)$inventory_data['status'];
    } else {
        $stmt_status = $conn->prepare("SELECT id FROM dim_category WHERE section = 'toolbox' AND module = 'status' AND label = 'New' LIMIT 1");
        if ($stmt_status) {
            $stmt_status->execute();
            $res_status = $stmt_status->get_result();
            if ($row_status = $res_status->fetch_assoc()) {
                $status = (int)$row_status['id'];
            }
        }
    }

    $quantity   = isset($inventory_data['quantity']) ? (int)$inventory_data['quantity'] : 1;
    $unit       = !empty($inventory_data['unit']) ? trim($inventory_data['unit']) : null;
    $price      = !empty($inventory_data['pricebought']) 
        ? (int)$inventory_data['pricebought'] 
        : (!empty($inventory_data['price']) ? (int)$inventory_data['price'] : null);
    $datebought = !empty($inventory_data['datebought']) ? $inventory_data['datebought'] : date('Y-m-d');
    $imagepath  = !empty($inventory_data['imagepath']) ? $inventory_data['imagepath'] : ($wishlist['imagepath'] ?? null);
    $link       = !empty($inventory_data['link']) ? trim($inventory_data['link']) : ($wishlist['link'] ?? null);
    $notes      = (isset($inventory_data['notes']) && trim($inventory_data['notes']) !== '') 
        ? trim($inventory_data['notes']) 
        : ($wishlist['notes'] ?? null);

    $stmt_inv = $conn->prepare(
        "INSERT INTO tool_inventory (name, brand, category, status, quantity, unit, pricebought, datebought, imagepath, link, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt_inv) return false;

    $stmt_inv->bind_param(
        "siiiisissss",
        $name, $brand, $category, $status, $quantity, $unit, $price, $datebought, $imagepath, $link, $notes
    );

    if (!$stmt_inv->execute()) {
        return false;
    }

    $new_tool_id = (int)$conn->insert_id;

    // Update wishlist record with obtained = 1 and toolid = $new_tool_id
    $stmt_upd = $conn->prepare("UPDATE tool_wishlist SET obtained = 1, toolid = ? WHERE wishlistid = ?");
    if ($stmt_upd) {
        $stmt_upd->bind_param("ii", $new_tool_id, $wishlist_id);
        $stmt_upd->execute();
    }

    return $new_tool_id;
}

/**
 * Calculate statistical aggregates for tool wishlist items
 *
 * @param array $items
 * @return array
 */
function calculate_tool_wishlist_stats($items) {
    $stats = [
        'total_items'     => count($items),
        'obtained_items'  => 0,
        'pending_items'   => 0,
        'priority_counts' => [],
        'category_counts' => [],
        'brand_counts'    => [],
    ];

    foreach ($items as $item) {
        $is_obtained = ((int)($item['obtainedid'] ?? 0) === 1 || ($item['obtained'] ?? '') === 'Yes');
        if ($is_obtained) {
            $stats['obtained_items']++;
        } else {
            $stats['pending_items']++;
        }

        $priority = !empty($item['priority']) ? $item['priority'] : 'Unknown';
        $stats['priority_counts'][$priority] = ($stats['priority_counts'][$priority] ?? 0) + 1;

        $category = !empty($item['category']) ? $item['category'] : 'Unknown';
        $stats['category_counts'][$category] = ($stats['category_counts'][$category] ?? 0) + 1;

        $brand = !empty($item['brand']) ? $item['brand'] : 'Unknown';
        $stats['brand_counts'][$brand] = ($stats['brand_counts'][$brand] ?? 0) + 1;
    }

    return $stats;
}
