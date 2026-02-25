<?php
/**
 * Kit Detail Page — data fetching functions
 * Uses existing views with WHERE clauses, no new views needed.
 */

function get_kit_detail($conn, int $inventoryid): ?array {
    $stmt = $conn->prepare("SELECT * FROM vw_kit_inventory WHERE actualid = ?");
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    $kit = $stmt->get_result()->fetch_assoc();

    if (!$kit) return null;

    $stmt = $conn->prepare("SELECT * FROM vw_kit_wishlist WHERE inventoryid = ?");
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    $wishlist = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT * FROM vw_kit_backlog_plan WHERE inventoryid = ?");
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    $backlogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare(
        "SELECT * FROM vw_kit_transaction_log WHERE inventoryid = ? ORDER BY createdat DESC"
    );
    $stmt->bind_param("i", $inventoryid);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return [
        'kit'      => $kit,
        'wishlist' => $wishlist,
        'backlogs' => $backlogs,
        'logs'     => $logs,
    ];
}

/**
 * Build a chronological timeline from tasks and build logs.
 * No new queries needed — uses data already fetched.
 *
 * @param array $tasks  Tasks from get_tasks_for_kit()
 * @param array $logs   Logs from get_kit_detail()['logs']
 * @return array Sorted events, newest first
 */
function build_kit_timeline(array $tasks, array $logs): array {
    $events = [];

    foreach ($tasks as $t) {
        $events[] = [
            'type'      => 'task_created',
            'icon'      => '📋',
            'label'     => $t['description'],
            'date'      => $t['createdat'],
            'imagepath' => $t['imagepath'] ?? null,
        ];

        if ($t['is_done'] && !empty($t['modifiedat'])) {
            $events[] = [
                'type'  => 'task_done',
                'icon'  => '✅',
                'label' => $t['description'],
                'date'  => $t['modifiedat'],
            ];
        }
    }

    foreach ($logs as $l) {
        $events[] = [
            'type'      => 'build_log',
            'icon'      => '🔨',
            'label'     => $l['logname'],
            'notes'     => $l['notes'] ?? '',
            'imagepath' => $l['imagepath'] ?? null,
            'date'      => $l['createdat'],
        ];
    }

    usort($events, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

    return $events;
}
