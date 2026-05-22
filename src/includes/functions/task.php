<?php
/**
 * Task management functions
 * All queries use prepared statements.
 */

function get_tasks($conn, $filters = []) {
    $sql = "SELECT * FROM vw_kit_task WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['filter_kit'])) {
        $sql .= " AND inventoryid = ?";
        $params[] = (int)$filters['filter_kit'];
        $types .= "i";
    }

    if (isset($filters['filter_status']) && $filters['filter_status'] !== '') {
        $sql .= " AND is_done = ?";
        $params[] = (int)$filters['filter_status'];
        $types .= "i";
    }

    if (!empty($filters['search'])) {
        $sql .= " AND description LIKE ?";
        $params[] = '%' . $filters['search'] . '%';
        $types .= "s";
    }

    $sql .= " ORDER BY is_done ASC, sort_order ASC, createdat DESC";

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function get_backlog_items_for_task_dropdown($conn) {
    $sql = "SELECT actualid, name FROM vw_kit_backlog_plan ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}


/**
 * Get tasks for a kit (all backlog entries combined, used on kit detail page)
 */
function get_tasks_for_kit($conn, int $inventoryid) {
    $stmt = $conn->prepare(
        "SELECT t.*, bp.backlogid as display_backlogid 
         FROM kit_task t
         JOIN kit_backlog_plan bp ON t.backlogid = bp.backlogid
         WHERE bp.inventoryid = ?
         ORDER BY t.is_done ASC, t.sort_order ASC, t.createdat DESC"
    );
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function add_task($conn, $data) {
    if (empty($data['description'])) return false;
    $backlogid = !empty($data['backlogid']) ? (int)$data['backlogid'] : null;
    $description = $data['description'];
    $imagepath = $data['imagepath'] ?? null;

    $stmt = $conn->prepare("INSERT INTO kit_task (backlogid, description, imagepath) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $backlogid, $description, $imagepath);
    return $stmt->execute();
}

function update_task($conn, $data) {
    if (empty($data['edit_id']) || empty($data['description'])) return false;
    $taskid = (int)$data['edit_id'];
    $backlogid = !empty($data['backlogid']) ? (int)$data['backlogid'] : null;
    $description = $data['description'];
    $imagepath = $data['imagepath'] ?? null;

    $stmt = $conn->prepare("UPDATE kit_task SET backlogid = ?, description = ?, imagepath = ? WHERE taskid = ?");
    $stmt->bind_param("issi", $backlogid, $description, $imagepath, $taskid);
    return $stmt->execute();
}

function toggle_task($conn, int $taskid) {
    $stmt = $conn->prepare("UPDATE kit_task SET is_done = NOT is_done WHERE taskid = ?");
    $stmt->bind_param("i", $taskid);
    return $stmt->execute();
}

/**
 * Delete a task (and its image file if any)
 */
function delete_task($conn, int $taskid) {
    $stmt = $conn->prepare("SELECT imagepath FROM kit_task WHERE taskid = ?");
    $stmt->bind_param("i", $taskid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM kit_task WHERE taskid = ?");
    $stmt->bind_param("i", $taskid);
    $success = $stmt->execute();

    if ($success && !empty($row['imagepath'])) {
        delete_image_file($row['imagepath']);
    }
    return $success;
}

function calculate_task_stats($tasks) {
    $total = count($tasks);
    $done = count(array_filter($tasks, fn($t) => $t['is_done']));
    return [
        'total'   => $total,
        'done'    => $done,
        'pending' => $total - $done,
    ];
}
