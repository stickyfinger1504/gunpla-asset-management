<?php
require '../includes/bootstrap.php';

$current_section = 'kits';
$current_page = 'build_progress';
$page_title = 'Build Progress';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $stmt = $conn->prepare("SELECT imagepath FROM kit_transaction_log WHERE logid = ?");
        $stmt->bind_param("s", $_POST['deleteid']);
        $stmt->execute();
        $result = $stmt->get_result();
        $old = $result->fetch_assoc();

        $action_success = delete_transaction_log($conn, $_POST['deleteid']);
        
        if ($action_success && !empty($old['imagepath'])) {
            delete_image_file($old['imagepath']);
        }
        $msg_text = $action_success ? "✅ Log entry deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'clear_orphaned') {
        $stmt = $conn->prepare("SELECT imagepath FROM kit_transaction_log WHERE backlogid IS NULL AND imagepath IS NOT NULL");
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($images as $img) {
            if (!empty($img['imagepath'])) {
                delete_image_file($img['imagepath']);
            }
        }

        $stmt = $conn->prepare("DELETE FROM kit_transaction_log WHERE backlogid IS NULL");
        $action_success = $stmt->execute();
        $msg_text = $action_success ? "✅ Orphaned logs cleared" : "❌ Error clearing logs";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $imagepath = '';
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /build_progress");
                exit;
            }
            $imagepath = $upload['path'];
        }
        $_POST['imagepath'] = $imagepath;
        $action_success = add_transaction_log($conn, $_POST);
        $msg_text = $action_success ? "✅ Progress logged!" : "❌ Error adding log";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /build_progress");
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath']);
            }
            $_POST['imagepath'] = $upload['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? '';
        }
        $action_success = update_transaction_log($conn, $_POST);
        $msg_text = $action_success ? "✅ Log updated" : "❌ Error updating log";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /build_progress");
        exit;
    }
}

$message = get_flash_message();

$backlog_items = get_backlog_items_for_dropdown($conn);
$logs = get_transaction_logs($conn, $_GET);
$stats = calculate_transaction_stats($logs);

$has_filters = !empty($_GET['filter_backlog']) || !empty($_GET['search']);
$has_orphaned = count(array_filter($logs, fn($l) => empty($l['name']))) > 0;

// Studio Workbench Datasets
$all_unfiltered_logs = $has_filters ? get_transaction_logs($conn) : $logs;

// Active Project Resolution (Focus on 'In Progress' build or kit with latest log)
$active_project = null;
$active_kit_stmt = $conn->query("
    SELECT bp.actualid as backlogid, bp.inventoryid, bp.name, bp.buildplan_label, bp.status_label
    FROM vw_kit_backlog_plan bp
    WHERE LOWER(bp.status_label) = 'in progress'
    ORDER BY bp.actualid DESC LIMIT 1
");
if ($active_kit_stmt && $row = $active_kit_stmt->fetch_assoc()) {
    $active_project = $row;
} else {
    $active_inv_stmt = $conn->query("
        SELECT ki.actualid as inventoryid, ki.name, bp.actualid as backlogid, bp.buildplan_label
        FROM vw_kit_inventory ki
        LEFT JOIN vw_kit_backlog_plan bp ON ki.actualid = bp.inventoryid
        WHERE LOWER(ki.status) = 'in progress'
        LIMIT 1
    ");
    if ($active_inv_stmt && $row = $active_inv_stmt->fetch_assoc()) {
        $active_project = $row;
    }
}

// Fallback: Pick kit with latest log entry
if (!$active_project && !empty($all_unfiltered_logs)) {
    foreach ($all_unfiltered_logs as $l) {
        if (!empty($l['name']) && !empty($l['actual_backlogid'])) {
            $active_project = [
                'backlogid' => $l['actual_backlogid'],
                'inventoryid' => $l['inventoryid'],
                'name' => $l['name'],
                'buildplan_label' => null
            ];
            break;
        }
    }
}

// Override active project if table filter is set to a specific kit
if (!empty($_GET['filter_backlog'])) {
    foreach ($all_unfiltered_logs as $l) {
        if (!empty($l['actual_backlogid']) && $l['actual_backlogid'] == $_GET['filter_backlog']) {
            $active_project = [
                'backlogid' => $l['actual_backlogid'],
                'inventoryid' => $l['inventoryid'],
                'name' => $l['name'],
                'buildplan_label' => null
            ];
            break;
        }
    }
}

// Logs belonging to active project
$active_logs = [];
if ($active_project) {
    $active_logs = array_values(array_filter($all_unfiltered_logs, function($l) use ($active_project) {
        if (!empty($active_project['backlogid']) && !empty($l['actual_backlogid'])) {
            return (int)$l['actual_backlogid'] === (int)$active_project['backlogid'];
        }
        return !empty($active_project['inventoryid']) && (int)($l['inventoryid'] ?? 0) === (int)$active_project['inventoryid'];
    }));
}

// Compute Momentum & Rhythm Stats
$momentum = [
    'days_active' => 0,
    'last_active_text' => 'No activity yet',
    'avg_cadence' => 'N/A',
    'photos_count' => 0,
    'total_logs' => count($active_logs)
];

if (!empty($active_logs)) {
    $latest_ts = strtotime($active_logs[0]['createdat']);
    $oldest_ts = strtotime(end($active_logs)['createdat']);
    $days_diff = max(1, round((time() - $oldest_ts) / 86400));
    $momentum['days_active'] = $days_diff;
    
    $secs_since_last = time() - $latest_ts;
    if ($secs_since_last < 3600) {
        $momentum['last_active_text'] = 'Just now';
    } elseif ($secs_since_last < 86400) {
        $hrs = floor($secs_since_last / 3600);
        $momentum['last_active_text'] = $hrs . ($hrs == 1 ? ' hr ago' : ' hrs ago');
    } elseif ($secs_since_last < 172800) {
        $momentum['last_active_text'] = 'Yesterday';
    } else {
        $days = floor($secs_since_last / 86400);
        $momentum['last_active_text'] = $days . ' days ago';
    }

    $log_count = count($active_logs);
    if ($log_count > 1) {
        $cadence_days = round($days_diff / $log_count, 1);
        $momentum['avg_cadence'] = "~1 update every {$cadence_days} days";
    } else {
        $momentum['avg_cadence'] = '1st session logged';
    }

    $momentum['photos_count'] = count(array_filter($active_logs, fn($l) => !empty($l['imagepath'])));
}

// Multi-Project Dock Dataset
$project_dock = [];
foreach ($all_unfiltered_logs as $l) {
    if (empty($l['name'])) continue;
    $bid = $l['actual_backlogid'] ?? 0;
    if (!isset($project_dock[$bid])) {
        $project_dock[$bid] = [
            'backlogid' => $bid,
            'name' => $l['name'],
            'inventoryid' => $l['inventoryid'] ?? null,
            'log_count' => 0,
            'photo_count' => 0,
            'latest_date' => $l['createdat']
        ];
    }
    $project_dock[$bid]['log_count']++;
    if (!empty($l['imagepath'])) {
        $project_dock[$bid]['photo_count']++;
    }
}
$project_dock = array_values($project_dock);
?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full">
            <h1 class="page-title font-bold text-gray-700 text-center mb-8">🔨 Build Progress</h1>
            
            <?php include '../components/toast.php'; ?>

            <!-- Studio Workbench Header: Active Build Chronicle & Momentum Deck -->
            <div class="mb-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Panel 1: Active Build Chronicle & Timeline Stepper -->
                    <div id="chronicle-panel" class="bg-gradient-to-br from-white to-blue-50/40 border-2 border-blue-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                                    📸 Active Build
                                </span>
                                <?php if (count($active_logs) > 1): ?>
                                    <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                        <button type="button" onclick="stepChronicle(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Older Entry">◀</button>
                                        <span id="chronicle-stepper-counter" class="font-semibold text-gray-700">Entry 1 of <?= count($active_logs) ?></span>
                                        <button type="button" onclick="stepChronicle(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Newer Entry">▶</button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($active_project): ?>
                                <div class="mb-3">
                                    <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                        <a href="/kit/<?= $active_project['inventoryid'] ?>" class="hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars($active_project['name']) ?>
                                        </a>
                                    </h4>
                                    <?php if (!empty($active_project['buildplan_label'])): ?>
                                        <span class="inline-block px-2 py-0.5 text-xs font-bold rounded-full bg-purple-100 text-purple-800 mt-1">
                                            <?= htmlspecialchars($active_project['buildplan_label']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($active_logs)): 
                                    $cur = $active_logs[0];
                                    $cur_date = !empty($cur['createdat']) ? date('M j, Y · H:i', strtotime($cur['createdat'])) : '';
                                ?>
                                    <div id="chronicle-content-card" class="bg-white/95 border border-blue-100 rounded-xl p-3.5 shadow-2xs flex flex-col sm:flex-row gap-3.5">
                                        <div id="chronicle-image-container" class="sm:w-44 flex-shrink-0">
                                            <?php if (!empty($cur['imagepath'])): ?>
                                                <a id="chronicle-img-link" href="<?= htmlspecialchars($cur['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="block w-full h-36 sm:h-full">
                                                    <img id="chronicle-img" src="<?= htmlspecialchars($cur['imagepath']) ?>" alt="Build snapshot" class="w-full h-36 sm:h-full object-cover rounded-lg hover:opacity-90 transition">
                                                </a>
                                            <?php else: ?>
                                                <div class="w-full h-36 sm:h-full bg-gray-50 border border-dashed border-gray-200 rounded-lg flex flex-col items-center justify-center text-gray-400 text-xs p-3 text-center">
                                                    <span class="text-2xl mb-1">📝</span>
                                                    <span>Note Only</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1 flex flex-col justify-center">
                                            <p id="chronicle-date" class="text-2xs text-gray-400 uppercase tracking-wider font-semibold mb-1"><?= $cur_date ?></p>
                                            <h5 id="chronicle-logname" class="text-base font-bold text-gray-800 leading-snug"><?= htmlspecialchars($cur['logname']) ?></h5>
                                            <p id="chronicle-notes" class="text-xs text-gray-600 mt-1.5 italic <?= empty($cur['notes']) ? 'hidden' : '' ?>">
                                                <?= htmlspecialchars($cur['notes']) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 text-center text-xs text-gray-500">
                                        No progress logged for this kit yet. Click <strong>+</strong> below to log your first build milestone!
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="py-6 text-center">
                                    <div class="text-3xl mb-2">✂️</div>
                                    <p class="text-sm font-semibold text-gray-700">No Active Build In Progress</p>
                                    <p class="text-xs text-gray-500 mt-1">Start a kit from your Hangar to document its progress chronicle here.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($active_project): ?>
                            <div class="pt-3 border-t border-blue-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if (!empty($active_project['backlogid'])): ?>
                                        <a href="/blueprint?backlogid=<?= $active_project['backlogid'] ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg transition-colors">
                                            📐 Blueprint
                                        </a>
                                        <a href="/tasks?filter_kit=<?= $active_project['inventoryid'] ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-lg transition-colors">
                                            📋 Kit Tasks
                                        </a>
                                    <?php endif; ?>
                                    <a href="/kit/<?= $active_project['inventoryid'] ?>" class="text-xs text-gray-500 hover:text-gray-900 font-medium px-2 py-1">
                                        Details →
                                    </a>
                                </div>
                                <button type="button" onclick="openAddModal()" 
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 rounded-lg transition-all shadow-2xs">
                                    📸 Log New Photo
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 2: Build Momentum & Session Rhythm -->
                    <div id="momentum-panel" class="bg-gradient-to-br from-white to-purple-50/30 border-2 border-purple-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                    ⏱️ Build Momentum & Rhythm
                                </span>
                                <?php if ($active_project): ?>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200">
                                        <?= $momentum['total_logs'] ?> logs recorded
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-500 mb-3.5">
                                Active crafting metrics and quick table view shortcuts.
                            </p>

                            <!-- Metric Tiles -->
                            <div class="grid grid-cols-3 gap-2.5 mb-4">
                                <div class="bg-white/90 border border-purple-100 rounded-lg p-3 text-center shadow-2xs">
                                    <span class="text-xl">🗓️</span>
                                    <p class="text-lg font-bold text-gray-800 mt-1"><?= $momentum['days_active'] ?>d</p>
                                    <p class="text-2xs text-gray-400 uppercase font-semibold">Days on Mat</p>
                                </div>
                                <div class="bg-white/90 border border-purple-100 rounded-lg p-3 text-center shadow-2xs">
                                    <span class="text-xl">⚡</span>
                                    <p class="text-sm font-bold text-purple-700 mt-1 truncate" title="<?= $momentum['last_active_text'] ?>"><?= $momentum['last_active_text'] ?></p>
                                    <p class="text-2xs text-gray-400 uppercase font-semibold">Last Session</p>
                                </div>
                                <div class="bg-white/90 border border-purple-100 rounded-lg p-3 text-center shadow-2xs">
                                    <span class="text-xl">📸</span>
                                    <p class="text-lg font-bold text-emerald-700 mt-1"><?= $momentum['photos_count'] ?></p>
                                    <p class="text-2xs text-gray-400 uppercase font-semibold">WIP Photos</p>
                                </div>
                            </div>

                            <!-- Average Cadence Callout -->
                            <div class="bg-white/70 border border-purple-100 rounded-lg p-2.5 text-xs text-gray-600 flex items-center justify-between">
                                <span class="font-medium">Cadence Pace:</span>
                                <span class="font-bold text-purple-800"><?= $momentum['avg_cadence'] ?></span>
                            </div>
                        </div>

                        <!-- Quick Filter Action Chips -->
                        <div class="pt-3 border-t border-purple-100 mt-auto">
                            <span class="text-2xs font-bold text-gray-400 uppercase tracking-wider block mb-1.5">Quick Table Views</span>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button type="button" onclick="filterTablePhotosOnly()" 
                                        class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 border border-emerald-300 transition-colors shadow-2xs">
                                    📸 Photos Only
                                </button>
                                <?php if ($active_project && !empty($active_project['backlogid'])): ?>
                                    <a href="/build_progress?filter_backlog=<?= $active_project['backlogid'] ?>" 
                                       class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 transition-colors shadow-2xs">
                                        🔍 Focus Active Kit
                                    </a>
                                <?php endif; ?>
                                <button type="button" onclick="resetTableFilter()" 
                                        class="px-2.5 py-1 text-xs font-medium rounded-lg bg-white hover:bg-gray-100 text-gray-600 border border-gray-200 transition-colors shadow-2xs">
                                    🔄 Reset Filter
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="mb-8">
                <h3 class="text-lg font-bold text-gray-700 mb-3">📊 Activity Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-blue-600"><?= $stats['total_entries'] ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?= $has_filters ? 'Filtered Entries' : 'Total Log Entries' ?></p>
                    </div>
                    <div class="bg-white border border-green-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-green-600"><?= count($stats['kit_counts']) ?></p>
                        <p class="text-sm text-gray-500 mt-1">Kits Tracked</p>
                    </div>
                    <div class="bg-white border border-purple-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-purple-600"><?= $stats['recent_count'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">This Week</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($project_dock)): ?>
            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-bold text-gray-700 flex items-center gap-2">
                        <span>🗂️ Active Projects Dock</span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                            <?= count($project_dock) ?> <?= count($project_dock) === 1 ? 'Project' : 'Projects' ?> Documented
                        </span>
                    </h3>
                    <?php if (!empty($_GET['filter_backlog'])): ?>
                        <a href="/build_progress" class="text-xs text-blue-600 hover:underline font-semibold flex items-center gap-1">
                            ✕ Show All Projects
                        </a>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <?php foreach ($project_dock as $proj): 
                        $is_focused = (!empty($_GET['filter_backlog']) && $_GET['filter_backlog'] == $proj['backlogid']) || (empty($_GET['filter_backlog']) && $active_project && $active_project['backlogid'] == $proj['backlogid']);
                    ?>
                        <div onclick="selectProject('<?= $proj['backlogid'] ?>')" 
                             class="cursor-pointer p-3.5 rounded-xl border-2 transition-all flex flex-col justify-between select-none
                             <?= $is_focused ? 'bg-blue-50/70 border-blue-400 shadow-sm ring-2 ring-blue-200' : 'bg-white border-gray-200 hover:border-blue-300 hover:shadow-xs' ?>">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <h5 class="text-sm font-bold text-gray-800 leading-snug line-clamp-1">
                                    <?= htmlspecialchars($proj['name']) ?>
                                </h5>
                                <?php if ($is_focused): ?>
                                    <span class="text-2xs font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-600 text-white flex-shrink-0">
                                        Focused
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500 pt-2 border-t border-gray-100">
                                <span class="flex items-center gap-1">
                                    📝 <strong><?= $proj['log_count'] ?></strong> <?= $proj['log_count'] === 1 ? 'entry' : 'entries' ?>
                                </span>
                                <?php if ($proj['photo_count'] > 0): ?>
                                    <span class="flex items-center gap-1 text-emerald-600 font-semibold">
                                        📸 <?= $proj['photo_count'] ?> photos
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400 text-2xs italic">No photos</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xl font-bold text-gray-700">📋 Log Entries</h3>
                <?php if ($has_orphaned): ?>
                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete all orphaned logs?');">
                    <input type="hidden" name="action_type" value="clear_orphaned">
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium transition flex items-center gap-1">
                        🧹 Clear Error Logs
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
                <form method="GET">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-gray-500 uppercase">Filters</span>
                        <button type="button" class="filter-toggle-btn" onclick="toggleFilterBar(this)">▼ Filters</button>
                    </div>
                    <div class="filter-bar-body <?= $has_filters ? 'is-open' : '' ?>">
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                               placeholder="Log name, kit name, or ID..." 
                               class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Filter by Kit</label>
                        <select name="filter_backlog" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Kits</option>
                            <?php foreach($backlog_items as $bl): ?>
                                <option value="<?= $bl['actualid'] ?>" <?= (isset($_GET['filter_backlog']) && $_GET['filter_backlog'] == $bl['actualid']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Sort By</label>
                        <select name="sortby" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="date_desc" <?= (($_GET['sortby'] ?? '') == 'date_desc') ? 'selected' : '' ?>>Date: Newest</option>
                            <option value="date_asc" <?= (($_GET['sortby'] ?? '') == 'date_asc') ? 'selected' : '' ?>>Date: Oldest</option>
                            <option value="name_asc" <?= (($_GET['sortby'] ?? '') == 'name_asc') ? 'selected' : '' ?>>Name: A-Z</option>
                            <option value="name_desc" <?= (($_GET['sortby'] ?? '') == 'name_desc') ? 'selected' : '' ?>>Name: Z-A</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Apply</button>
                        <?php if ($has_filters): ?>
                        <button type="button" onclick="clearFilters(this)" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Clear</button>
                        <?php endif; ?>
                    </div>
                </div>
                </form>
            </div>


            <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 mobile-stack-table">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'text')">Kit</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Log Entry</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'date')">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Image</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $row): ?>
                                <?php 
                                    $safe_logname = htmlspecialchars($row['logname'] ?? '-', ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES);
                                    $safe_image = htmlspecialchars($row['imagepath'] ?? '', ENT_QUOTES);
                                    $is_orphaned = empty($row['name']);
                                    $safe_name = $is_orphaned ? '<span class="text-gray-400 italic">Kit Not Found (Deleted)</span>' : htmlspecialchars($row['name'], ENT_QUOTES);
                                    $formatted_date = !empty($row['createdat']) ? date('M j, Y', strtotime($row['createdat'])) : '-';
                                    $formatted_time = !empty($row['createdat']) ? date('H:i', strtotime($row['createdat'])) : '';
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100' data-logid='<?= htmlspecialchars($row['logid'] ?? '') ?>'>
                                    <td data-label="Kit" class='px-4 py-3 text-sm font-semibold text-gray-800'><?= $safe_name ?></td>
                                    <td data-label="Log Entry" class='px-4 py-3 text-sm text-gray-700 font-medium'><?= $safe_logname ?></td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600 max-w-xs truncate'><?= $safe_notes ?: '-' ?></td>
                                    <td data-label="Date" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'>
                                        <div><?= $formatted_date ?></div>
                                        <?php if ($formatted_time): ?>
                                        <div class="text-xs text-gray-400"><?= $formatted_time ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Image" class='px-4 py-3 text-sm'>
                                        <?php if (!empty($row['imagepath'])): ?>
                                            <a href="<?= $safe_image ?>" target="_blank" rel="noopener noreferrer" title="Click to view full size">
                                                <img src="<?= $safe_image ?>" alt="Build photo" 
                                                     class="w-12 h-12 object-cover rounded border hover:opacity-80 transition cursor-pointer"
                                                     loading="lazy">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
                                                data-id='<?= htmlspecialchars($row['logid'] ?? '') ?>'
                                                data-backlogid='<?= $row['actual_backlogid'] ?? '' ?>'
                                                data-logname='<?= $safe_logname ?>'
                                                data-notes='<?= $safe_notes ?>'
                                                data-imagepath='<?= $safe_image ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>
                                            
                                            <form method='POST' class='inline' onsubmit='return confirm("Delete this log entry?");'>
                                                <input type='hidden' name='deleteid' value='<?= htmlspecialchars($row['logid'] ?? '') ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='6' class='text-center py-6 text-gray-500'>No build progress logged yet. Start documenting your builds! 🔨</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <button onclick="openAddModal()" 
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Log New Progress">
        +
    </button>

    <?php $mode = 'add'; include '../components/log_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/log_modal.php'; ?>

    <script>
        function openEditModal(button) {
            document.getElementById('modal_id').value = button.getAttribute('data-id');
            document.getElementById('modal_backlogid').value = button.getAttribute('data-backlogid');
            document.getElementById('modal_logname').value = button.getAttribute('data-logname');
            document.getElementById('modal_notes').value = button.getAttribute('data-notes');

            const imagepath = button.getAttribute('data-imagepath');
            document.getElementById('modal_imagepath').value = imagepath;
            const preview = document.getElementById('modal_image_preview');
            const thumb = document.getElementById('modal_image_thumb');
            if (imagepath) {
                thumb.src = imagepath;
                preview.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
            }

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex';
        }

        // Studio Workbench Data
        const activeProjectLogs = <?= json_encode(array_values(array_map(fn($l) => [
            'logid' => $l['logid'],
            'name' => $l['name'] ?? '',
            'logname' => $l['logname'] ?? '',
            'notes' => $l['notes'] ?? '',
            'imagepath' => $l['imagepath'] ?? '',
            'date_formatted' => !empty($l['createdat']) ? date('M j, Y · H:i', strtotime($l['createdat'])) : ''
        ], $active_logs)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        let currentChronicleIdx = 0;

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function stepChronicle(delta) {
            if (!activeProjectLogs || activeProjectLogs.length === 0) return;
            currentChronicleIdx = (currentChronicleIdx + delta + activeProjectLogs.length) % activeProjectLogs.length;
            renderChronicle();
        }

        function renderChronicle() {
            const log = activeProjectLogs[currentChronicleIdx];
            if (!log) return;

            const counter = document.getElementById('chronicle-stepper-counter');
            if (counter) counter.textContent = `Entry ${currentChronicleIdx + 1} of ${activeProjectLogs.length}`;

            const dateEl = document.getElementById('chronicle-date');
            if (dateEl) dateEl.textContent = log.date_formatted;

            const titleEl = document.getElementById('chronicle-logname');
            if (titleEl) titleEl.textContent = log.logname;

            const notesEl = document.getElementById('chronicle-notes');
            if (notesEl) {
                if (log.notes) {
                    notesEl.textContent = log.notes;
                    notesEl.classList.remove('hidden');
                } else {
                    notesEl.textContent = '';
                    notesEl.classList.add('hidden');
                }
            }

            const imgContainer = document.getElementById('chronicle-image-container');
            if (imgContainer) {
                if (log.imagepath) {
                    imgContainer.innerHTML = `
                        <a id="chronicle-img-link" href="${escapeHtml(log.imagepath)}" target="_blank" rel="noopener noreferrer" class="block w-full h-36 sm:h-full">
                            <img id="chronicle-img" src="${escapeHtml(log.imagepath)}" alt="Build snapshot" class="w-full h-36 sm:h-full object-cover rounded-lg hover:opacity-90 transition">
                        </a>`;
                } else {
                    imgContainer.innerHTML = `
                        <div class="w-full h-36 sm:h-full bg-gray-50 border border-dashed border-gray-200 rounded-lg flex flex-col items-center justify-center text-gray-400 text-xs p-3 text-center">
                            <span class="text-2xl mb-1">📝</span>
                            <span>Note Only</span>
                        </div>`;
                }
            }
        }

        function selectProject(backlogId) {
            if (backlogId) {
                window.location.href = `/build_progress?filter_backlog=${encodeURIComponent(backlogId)}`;
            } else {
                window.location.href = '/build_progress';
            }
        }

        function filterTablePhotosOnly() {
            const rows = document.querySelectorAll('tbody tr[data-logid]');
            rows.forEach(r => {
                const imgCell = r.querySelector('td[data-label="Image"] img');
                r.style.display = imgCell ? '' : 'none';
            });
        }

        function resetTableFilter() {
            const rows = document.querySelectorAll('tbody tr[data-logid]');
            rows.forEach(r => r.style.display = '');
        }
    </script>

<?php include '../components/layout_footer.php'; ?>
<script>initScrollRestore('buildprogress_scroll');</script>
