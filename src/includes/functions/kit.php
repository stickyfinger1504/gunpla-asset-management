<?php
/**
 * Kit-related functions
 * All CRUD operations for kit inventory
 */

function get_brands($conn) {
    $sql = "SELECT id, name FROM dim_brand WHERE section = 'kit' ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function get_statuses($conn) {
    $sql = "SELECT id, label FROM dim_category WHERE section = 'kitinventory' AND module = 'status' ORDER BY label ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function get_archived_status_id($conn) {
    $label = 'archived';
    
    $stmt = $conn->prepare("SELECT id FROM dim_category WHERE section='kitinventory' AND module='status' AND label=? LIMIT 1");
    $stmt->bind_param("s", $label);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['id'];
    } 
    
    $stmt = $conn->prepare("INSERT INTO dim_category (section, module, label) VALUES ('kitinventory', 'status', ?)");
    $stmt->bind_param("s", $label);
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return null;
}

function get_kit_inventory($conn, $filters = []) {
    $sql = "SELECT * FROM vw_kit_inventory WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($filters['filter_status'])) {
        $status_id = (int)$filters['filter_status'];
        $sql .= " AND statusid = ?";
        $params[] = $status_id;
        $types .= "i";
    }

    if (!empty($filters['filter_brand'])) {
        $brand_id = (int)$filters['filter_brand'];
        $sql .= " AND brandid = ?";
        $params[] = $brand_id;
        $types .= "i";
    }

    if (!empty($filters['search'])) {
        $search_term = '%' . $filters['search'] . '%';
        $sql .= " AND (name LIKE ? OR id LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }

    // Sort (whitelist approach - safe, no user input in SQL)
    $sort = $filters['sortby'] ?? 'date_desc';
    switch($sort) {
        case 'price_desc': $sql .= " ORDER BY pricebought DESC"; break;
        case 'price_asc':  $sql .= " ORDER BY pricebought ASC"; break;
        case 'date_asc':   $sql .= " ORDER BY datebought ASC"; break;
        default:           $sql .= " ORDER BY datebought DESC";
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

function add_kit($conn, $data) {
    if (empty($data["kit_name"]) || empty($data["brandid"]) || empty($data["statusid"])) return false;
    
    $datebought = !empty($data["datebought"]) ? $data["datebought"] : null;
    $pricebought = !empty($data["pricebought"]) ? $data["pricebought"] : null;
    $notes = !empty($data["notes"]) ? $data['notes'] : null;

    $stmt = $conn->prepare("INSERT INTO kit_inventory (name, status, datebought, pricebought, notes, brandid) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("sisisi", $data["kit_name"], $data["statusid"], $datebought, $pricebought, $notes, $data["brandid"]);
    return $stmt->execute();
}

function update_kit($conn, $data) {
    if (empty($data["edit_id"]) || empty($data["kit_name"]) || empty($data["brandid"]) || empty($data["statusid"])) return false;
    
    $datebought = !empty($data["datebought"]) ? $data["datebought"] : null;
    $pricebought = !empty($data["pricebought"]) ? $data["pricebought"] : null;
    $notes = (isset($data["notes"]) && $data["notes"] !== '') ? $data['notes'] : null;

    $stmt = $conn->prepare("UPDATE kit_inventory SET name=?, status=?, datebought=?, pricebought=?, notes=?, brandid=? WHERE inventoryid=?");
    $stmt->bind_param("sisisii", $data["kit_name"], $data["statusid"], $datebought, $pricebought, $notes, $data["brandid"], $data['edit_id']);
    return $stmt->execute();
}

function delete_kit($conn, $id) {
    $stmt = $conn->prepare("SELECT t.imagepath FROM kit_task t JOIN kit_backlog_plan b ON t.backlogid = b.backlogid WHERE b.inventoryid = ? AND t.imagepath IS NOT NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = $result->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("DELETE FROM kit_inventory WHERE inventoryid=?");
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

function archive_kit($conn, $id) {
    $archived_id = get_archived_status_id($conn);
    if (!$archived_id) return false;

    $stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
    $stmt->bind_param("ii", $archived_id, $id);
    return $stmt->execute();
}
