<?php
/**
 * Mixing Recipe Functions
 * CRUD operations for paint_recipe and paint_recipe_item tables
 */

function get_recipes($conn, $filters = []) {
    $sql = "SELECT * FROM paint_recipe WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $sql .= " AND name LIKE ?";
        $params[] = $search;
        $types .= "s";
    }

    $sql .= " ORDER BY createdat DESC";

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $recipes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if (empty($recipes)) return [];

    $recipe_ids = array_column($recipes, 'recipeid');
    $placeholders = implode(',', array_fill(0, count($recipe_ids), '?'));
    $types_items = str_repeat('i', count($recipe_ids));

    $stmt = $conn->prepare(
        "SELECT ri.*, p.name AS paint_name, p.inventoryid AS paint_actualid
         FROM paint_recipe_item ri
         LEFT JOIN paint_inventory p ON ri.paintid = p.inventoryid
         WHERE ri.recipeid IN ($placeholders)
         ORDER BY ri.sort_order ASC"
    );
    $stmt->bind_param($types_items, ...$recipe_ids);
    $stmt->execute();
    $all_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $items_by_recipe = [];
    foreach ($all_items as $item) {
        $items_by_recipe[$item['recipeid']][] = $item;
    }

    foreach ($recipes as &$recipe) {
        $recipe['items'] = $items_by_recipe[$recipe['recipeid']] ?? [];
    }

    return $recipes;
}

function get_recipe($conn, int $recipeid): ?array {
    $stmt = $conn->prepare("SELECT * FROM paint_recipe WHERE recipeid = ?");
    $stmt->bind_param("i", $recipeid);
    $stmt->execute();
    $recipe = $stmt->get_result()->fetch_assoc();

    if (!$recipe) return null;

    $stmt = $conn->prepare(
        "SELECT ri.*, p.name AS paint_name
         FROM paint_recipe_item ri
         LEFT JOIN paint_inventory p ON ri.paintid = p.inventoryid
         WHERE ri.recipeid = ?
         ORDER BY ri.sort_order ASC"
    );
    $stmt->bind_param("i", $recipeid);
    $stmt->execute();
    $recipe['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return $recipe;
}

function add_recipe($conn, $data, $items) {
    if (empty($data['name']) || empty($items)) return false;
    $conn->begin_transaction();
    try {
        $notes = !empty($data['notes']) ? $data['notes'] : null;
        $thinner = !empty($data['thinner_ratio']) ? $data['thinner_ratio'] : null;
        $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;

        $stmt = $conn->prepare(
            "INSERT INTO paint_recipe (name, thinner_ratio, imagepath, notes) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("ssss", $data['name'], $thinner, $imagepath, $notes);
        $stmt->execute();
        $recipeid = $conn->insert_id;

        $stmt = $conn->prepare(
            "INSERT INTO paint_recipe_item (recipeid, paintid, percentage, sort_order) VALUES (?, ?, ?, ?)"
        );
        foreach ($items as $i => $item) {
            $sort = $i + 1;
            $stmt->bind_param("iiii", $recipeid, $item['paintid'], $item['percentage'], $sort);
            $stmt->execute();
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function update_recipe($conn, $data, $items) {
    if (empty($data['edit_id']) || empty($data['name']) || empty($items)) return false;
    $conn->begin_transaction();
    try {
        $notes = !empty($data['notes']) ? $data['notes'] : null;
        $thinner = !empty($data['thinner_ratio']) ? $data['thinner_ratio'] : null;
        $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;
        $recipeid = (int)$data['edit_id'];

        $stmt = $conn->prepare(
            "UPDATE paint_recipe SET name=?, thinner_ratio=?, imagepath=?, notes=? WHERE recipeid=?"
        );
        $stmt->bind_param("ssssi", $data['name'], $thinner, $imagepath, $notes, $recipeid);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM paint_recipe_item WHERE recipeid = ?");
        $stmt->bind_param("i", $recipeid);
        $stmt->execute();

        $stmt = $conn->prepare(
            "INSERT INTO paint_recipe_item (recipeid, paintid, percentage, sort_order) VALUES (?, ?, ?, ?)"
        );
        foreach ($items as $i => $item) {
            $sort = $i + 1;
            $stmt->bind_param("iiii", $recipeid, $item['paintid'], $item['percentage'], $sort);
            $stmt->execute();
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function delete_recipe($conn, int $id) {
    $stmt = $conn->prepare("SELECT imagepath FROM paint_recipe WHERE recipeid = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM paint_recipe WHERE recipeid = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

    if ($success && !empty($old['imagepath'])) {
        delete_image_file($old['imagepath'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
    }
    return $success;
}

function get_paints_for_dropdown($conn) {
    $sql = "SELECT actualid, id, name, brand FROM vw_paint_inventory ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
