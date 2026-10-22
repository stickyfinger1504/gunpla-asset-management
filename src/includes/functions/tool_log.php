<?php
/**
 * Tool Log Functions
 * Domain logic & CRUD operations for tool_log table and vw_tool_log view
 */

/**
 * Single Source of Truth (SSOT) definitions for Tool Log Types
 * Maps type keys to labels, helper descriptions, badge classes, and emoji icons.
 *
 * @return array
 */
function get_tool_log_type_definitions() {
    return [
        'Usage' => [
            'label'              => 'Usage',
            'description'        => 'Tool usage logged against a specific kit build or project session',
            'helper_description' => 'Tool usage logged against a specific kit build or project session',
            'badge'              => 'bg-blue-100 text-blue-800 border-blue-200',
            'badge_class'        => 'bg-blue-100 text-blue-800 border-blue-200',
            'icon'               => '🔨',
            'emoji'              => '🔨',
        ],
        'Maintenance' => [
            'label'              => 'Maintenance',
            'description'        => 'Maintenance actions such as cleaning, blade replacement, sharpening, or oiling',
            'helper_description' => 'Maintenance actions such as cleaning, blade replacement, sharpening, or oiling',
            'badge'              => 'bg-amber-100 text-amber-800 border-amber-200',
            'badge_class'        => 'bg-amber-100 text-amber-800 border-amber-200',
            'icon'               => '🧰',
            'emoji'              => '🧰',
        ],
        'Test Sample' => [
            'label'              => 'Test Sample',
            'description'        => 'Experimental sample cuts, scribe testing, or airbrush pattern tests',
            'helper_description' => 'Experimental sample cuts, scribe testing, or airbrush pattern tests',
            'badge'              => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            'badge_class'        => 'bg-indigo-100 text-indigo-800 border-indigo-200',
            'icon'               => '🧪',
            'emoji'              => '🧪',
        ],
        'General' => [
            'label'              => 'General',
            'description'        => 'General workbench notes, unboxing, condition inspection, or miscellaneous comments',
            'helper_description' => 'General workbench notes, unboxing, condition inspection, or miscellaneous comments',
            'badge'              => 'bg-gray-100 text-gray-700 border-gray-200',
            'badge_class'        => 'bg-gray-100 text-gray-700 border-gray-200',
            'icon'               => '📝',
            'emoji'              => '📝',
        ],
    ];
}

/**
 * Supported default tool log types
 *
 * @return array
 */
function get_tool_log_types() {
    return ['Usage', 'Maintenance', 'Test Sample', 'General'];
}

/**
 * Pure presentation helper for badge styling in code
 *
 * @param string $type
 * @return string
 */
function get_log_type_badge_class(string $type): string {
    return match(strtolower(trim($type))) {
        'usage'       => 'bg-blue-100 text-blue-800 border-blue-200',
        'maintenance' => 'bg-amber-100 text-amber-800 border-amber-200',
        'test sample' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
        default       => 'bg-gray-100 text-gray-800 border-gray-200',
    };
}

/**
 * Fetch tool logs with optional filtering and sorting
 *
 * @param mysqli $conn
 * @param array $filters Supported: search, filter_tool, filter_type, filter_backlog, filter_date_from, filter_date_to, sortby
 * @return array
 */
function get_tool_logs_list($conn, $filters = []) {
    $sql = "SELECT * FROM vw_tool_log WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $search = '%' . trim($filters['search']) . '%';
        $sql .= " AND (tool_name LIKE ? OR notes LIKE ? OR kit_name LIKE ? OR log_type LIKE ? OR brand LIKE ? OR category LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
        $types .= "ssssss";
    }

    if (!empty($filters['filter_tool'])) {
        $sql .= " AND toolid = ?";
        $params[] = (int)$filters['filter_tool'];
        $types .= "i";
    }

    if (!empty($filters['filter_type'])) {
        $sql .= " AND log_type = ?";
        $params[] = trim($filters['filter_type']);
        $types .= "s";
    }

    if (!empty($filters['filter_backlog'])) {
        $sql .= " AND backlogid = ?";
        $params[] = (int)$filters['filter_backlog'];
        $types .= "i";
    }

    if (!empty($filters['filter_date_from'])) {
        $sql .= " AND log_date >= ?";
        $params[] = trim($filters['filter_date_from']);
        $types .= "s";
    }

    if (!empty($filters['filter_date_to'])) {
        $sql .= " AND log_date <= ?";
        $params[] = trim($filters['filter_date_to']);
        $types .= "s";
    }

    $sort = $filters['sortby'] ?? 'date_desc';
    switch ($sort) {
        case 'date_asc':  $sql .= " ORDER BY log_date ASC, logid ASC"; break;
        case 'tool_asc':  $sql .= " ORDER BY tool_name ASC, log_date DESC"; break;
        case 'tool_desc': $sql .= " ORDER BY tool_name DESC, log_date DESC"; break;
        case 'type_asc':  $sql .= " ORDER BY log_type ASC, log_date DESC"; break;
        case 'date_desc':
        default:          $sql .= " ORDER BY log_date DESC, logid DESC"; break;
    }

    if (count($params) > 0) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Fetch a single tool log entry by UUID
 *
 * @param mysqli $conn
 * @param string $log_id
 * @return array|null
 */
function get_tool_log_by_id($conn, $log_id) {
    if (empty($log_id)) return null;

    $stmt = $conn->prepare("SELECT * FROM vw_tool_log WHERE logid = ? LIMIT 1");
    if (!$stmt) return null;

    $stmt->bind_param("s", $log_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Add a new tool log entry
 *
 * @param mysqli $conn
 * @param array $data Fields: toolid, log_type, log_date, notes, backlogid, imagepath
 * @return string|bool Returns log ID on success, false on failure
 */
function add_tool_log_entry($conn, $data) {
    if (empty($data['toolid']) || empty($data['log_type'])) {
        return false;
    }

    $logid     = !empty($data['logid']) ? $data['logid'] : generate_tool_log_id();
    $toolid    = (int)$data['toolid'];
    $log_type  = trim($data['log_type']);
    $log_date  = !empty($data['log_date']) ? $data['log_date'] : date('Y-m-d');
    $notes     = (isset($data['notes']) && trim($data['notes']) !== '') ? trim($data['notes']) : null;
    $backlogid = !empty($data['backlogid']) ? (int)$data['backlogid'] : null;
    $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO tool_log (logid, toolid, log_type, log_date, notes, imagepath, backlogid)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return false;

    $stmt->bind_param("sisssis", $logid, $toolid, $log_type, $log_date, $notes, $imagepath, $backlogid);
    return $stmt->execute() ? $logid : false;
}

/**
 * Update an existing tool log entry
 *
 * @param mysqli $conn
 * @param array $data Fields: logid/edit_id, toolid, log_type, log_date, notes, backlogid, imagepath, existing_imagepath
 * @return bool
 */
function update_tool_log($conn, $data) {
    $logid = trim($data['logid'] ?? $data['edit_id'] ?? '');
    if (empty($logid) || empty($data['toolid']) || empty($data['log_type'])) {
        return false;
    }

    $toolid    = (int)$data['toolid'];
    $log_type  = trim($data['log_type']);
    $log_date  = !empty($data['log_date']) ? $data['log_date'] : date('Y-m-d');
    $notes     = (isset($data['notes']) && trim($data['notes']) !== '') ? trim($data['notes']) : null;
    $backlogid = !empty($data['backlogid']) ? (int)$data['backlogid'] : null;

    if (array_key_exists('imagepath', $data)) {
        $imagepath = !empty($data['imagepath']) ? $data['imagepath'] : null;
    } elseif (array_key_exists('existing_imagepath', $data)) {
        $imagepath = !empty($data['existing_imagepath']) ? $data['existing_imagepath'] : null;
    } else {
        $stmt_cur = $conn->prepare("SELECT imagepath FROM tool_log WHERE logid = ?");
        if ($stmt_cur) {
            $stmt_cur->bind_param("s", $logid);
            $stmt_cur->execute();
            $curr = $stmt_cur->get_result()->fetch_assoc();
            $imagepath = $curr['imagepath'] ?? null;
        } else {
            $imagepath = null;
        }
    }

    $stmt = $conn->prepare(
        "UPDATE tool_log
         SET toolid = ?, log_type = ?, log_date = ?, notes = ?, imagepath = ?, backlogid = ?
         WHERE logid = ?"
    );
    if (!$stmt) return false;

    $stmt->bind_param("issssis", $toolid, $log_type, $log_date, $notes, $imagepath, $backlogid, $logid);
    return $stmt->execute();
}

/**
 * Calculate statistical aggregates for tool logs
 *
 * @param array $logs
 * @return array
 */
function calculate_tool_log_stats($logs) {
    $stats = [
        'total_logs'        => count($logs),
        'usage_count'       => 0,
        'maintenance_count' => 0,
        'test_sample_count' => 0,
        'general_count'     => 0,
        'type_counts'       => [],
        'tool_counts'       => [],
        'tools_logged'      => 0,
        'latest_log_date'   => null,
    ];

    $unique_tool_ids = [];

    foreach ($logs as $log) {
        $type = !empty($log['log_type']) ? $log['log_type'] : 'General';
        $stats['type_counts'][$type] = ($stats['type_counts'][$type] ?? 0) + 1;

        $type_lower = strtolower($type);
        if ($type_lower === 'usage') {
            $stats['usage_count']++;
        } elseif ($type_lower === 'maintenance') {
            $stats['maintenance_count']++;
        } elseif ($type_lower === 'test sample') {
            $stats['test_sample_count']++;
        } elseif ($type_lower === 'general') {
            $stats['general_count']++;
        }

        $tool_id = (int)($log['toolid'] ?? 0);
        if ($tool_id > 0) {
            $unique_tool_ids[$tool_id] = true;
        }

        $tool_name = $log['tool_name'] ?? 'Unknown';
        $stats['tool_counts'][$tool_name] = ($stats['tool_counts'][$tool_name] ?? 0) + 1;

        if (!empty($log['log_date'])) {
            if ($stats['latest_log_date'] === null || $log['log_date'] > $stats['latest_log_date']) {
                $stats['latest_log_date'] = $log['log_date'];
            }
        }
    }

    $stats['tools_logged'] = count($unique_tool_ids);
    return $stats;
}

/**
 * Fetch tools list for log dropdown selectors
 *
 * @param mysqli $conn
 * @return array
 */
function get_tools_for_log_dropdown($conn) {
    $sql = "SELECT actualid AS toolid, name, brand, category, imagepath FROM vw_tool_inventory ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Fetch kit backlog items for log dropdown selectors
 *
 * @param mysqli $conn
 * @return array
 */
function get_backlog_kits_for_log_dropdown($conn) {
    $sql = "SELECT actualid, name, backlogid, status_label FROM vw_kit_backlog_plan ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}
