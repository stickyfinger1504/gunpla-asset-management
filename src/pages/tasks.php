<?php
require_once '../includes/bootstrap.php';

$current_section = 'kits';
$current_page = 'tasks';
$page_title = 'Tasks';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['toggle_id'])) {
        $action_success = toggle_task($conn, (int)$_POST['toggle_id']);
        $query = ($_SERVER['QUERY_STRING'] ?? '') ? '?' . $_SERVER['QUERY_STRING'] : '';
        header("Location: /tasks" . $query);
        exit;
    }
    elseif (isset($_POST['deleteid'])) {
        $action_success = delete_task($conn, (int)$_POST['deleteid']);
        $msg_text = $action_success ? "✅ Task deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /tasks");
                exit;
            }
            $_POST['imagepath'] = $upload['path'];
        }
        $action_success = add_task($conn, $_POST);
        $msg_text = $action_success ? "✅ Task added" : "❌ Error adding task";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /tasks");
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath']);
            }
            $_POST['imagepath'] = $upload['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? null;
        }
        $action_success = update_task($conn, $_POST);
        $msg_text = $action_success ? "✅ Task updated" : "❌ Error updating task";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /tasks");
        exit;
    }
}

$message = get_flash_message();

$backlog_items = get_backlog_items_for_task_dropdown($conn);
$all_tasks = get_tasks($conn, $_GET);
$stats = calculate_task_stats($all_tasks);
$has_filters = !empty($_GET['filter_kit']) || (isset($_GET['filter_status']) && $_GET['filter_status'] !== '') || !empty($_GET['search']);

$grouped = [];
foreach ($all_tasks as $task) {
    $group_key = $task['kit_name'] ?? '__general__';
    $grouped[$group_key][] = $task;
}
if (isset($grouped['__general__'])) {
    $general = $grouped['__general__'];
    unset($grouped['__general__']);
    $grouped['__general__'] = $general;
}

$filter_kits_result = $conn->query("SELECT DISTINCT inventoryid, name FROM vw_kit_backlog_plan ORDER BY name ASC");
$filter_kits = $filter_kits_result ? $filter_kits_result->fetch_all(MYSQLI_ASSOC) : [];

// Task Operations Cockpit Datasets
$unfiltered_tasks = $has_filters ? get_tasks($conn) : $all_tasks;
$general_tasks = array_values(array_filter($unfiltered_tasks, fn($t) => empty($t['kit_name']) || empty($t['backlogid'])));

// Active Build Project Resolution
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

// Fallback: If no kit marked "in progress", pick kit with most pending tasks
if (!$active_project && !empty($unfiltered_tasks)) {
    $kit_counts = [];
    foreach ($unfiltered_tasks as $t) {
        if (!empty($t['inventoryid']) && (int)$t['is_done'] === 0) {
            $kit_counts[$t['inventoryid']] = ($kit_counts[$t['inventoryid']] ?? 0) + 1;
        }
    }
    if (!empty($kit_counts)) {
        arsort($kit_counts);
        $top_invid = array_key_first($kit_counts);
        foreach ($unfiltered_tasks as $t) {
            if ((int)$t['inventoryid'] === (int)$top_invid) {
                $active_project = [
                    'backlogid' => $t['backlogid'],
                    'inventoryid' => $t['inventoryid'],
                    'name' => $t['kit_name'],
                    'buildplan_label' => $t['buildplan_label'] ?? null
                ];
                break;
            }
        }
    }
}

$active_tasks = [];
$active_total = 0;
$active_done = 0;
$active_pct = 0;
$next_active_task = null;

if ($active_project) {
    $active_tasks = array_values(array_filter($unfiltered_tasks, function($t) use ($active_project) {
        if (!empty($active_project['backlogid']) && !empty($t['backlogid'])) {
            return (int)$t['backlogid'] === (int)$active_project['backlogid'];
        }
        return (int)($t['inventoryid'] ?? 0) === (int)$active_project['inventoryid'];
    }));

    $active_total = count($active_tasks);
    $active_done = count(array_filter($active_tasks, fn($t) => (int)$t['is_done'] === 1));
    $active_pct = $active_total > 0 ? round(($active_done / $active_total) * 100) : 0;
    foreach ($active_tasks as $t) {
        if ((int)$t['is_done'] === 0) {
            $next_active_task = $t;
            break;
        }
    }
}
?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full">
            <h1 class="page-title font-bold text-gray-700 text-center mb-8">📋 Tasks</h1>

            <?php include '../components/toast.php'; ?>

            <div class="mb-8">
                <h3 class="text-lg font-bold text-gray-700 mb-3">📊 Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white border border-orange-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-orange-600"><?= $stats['pending'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">To-Do</p>
                    </div>
                    <div class="bg-white border border-green-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-green-600"><?= $stats['done'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">Done</p>
                    </div>
                    <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-blue-600"><?= $stats['total'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">Total Tasks</p>
                    </div>
                </div>
            </div>

            <!-- Task Operations Cockpit -->
            <div class="mb-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Panel 1: Active Build Task Deck -->
                    <div id="active-task-panel" class="bg-gradient-to-br from-white to-blue-50/40 border-2 border-blue-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                                    🛠️ Active Build Task
                                </span>
                                <?php if ($active_project && $active_total > 0): ?>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                        <?= $active_done ?> / <?= $active_total ?> Steps (<?= $active_pct ?>%)
                                    </span>
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

                                    <!-- Progress Bar -->
                                    <div class="w-full bg-gray-200 rounded-full h-2 mt-3 overflow-hidden">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: <?= $active_pct ?>%"></div>
                                    </div>
                                </div>

                                <!-- Next Up Step Card -->
                                <?php if ($next_active_task): ?>
                                    <div class="bg-white/90 border border-blue-100 rounded-lg p-3 mt-3 shadow-2xs">
                                        <div class="text-2xs font-bold text-blue-700 uppercase tracking-wider mb-1 flex items-center gap-1">
                                            <span>⚡ Next Step:</span>
                                        </div>
                                        <div class="flex items-center gap-2.5">
                                            <?php if (!empty($next_active_task['imagepath'])): ?>
                                                <a href="<?= htmlspecialchars($next_active_task['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="flex-shrink-0">
                                                    <img src="<?= htmlspecialchars($next_active_task['imagepath']) ?>" alt="Ref" class="w-10 h-10 object-cover rounded border hover:opacity-80 transition">
                                                </a>
                                            <?php endif; ?>
                                            <p class="text-sm font-semibold text-gray-800 flex-1 leading-snug">
                                                <?= htmlspecialchars($next_active_task['description']) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php elseif ($active_total > 0): ?>
                                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-3 mt-3 text-center text-xs text-emerald-800 font-semibold">
                                        🎉 All tasks for this kit are completed! Ready for inspection or final topcoat.
                                    </div>
                                <?php else: ?>
                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 mt-3 text-center text-xs text-gray-500">
                                        No build tasks logged for this kit yet. Use the Quick Dispatcher on the right to log your next step!
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="py-6 text-center">
                                    <div class="text-3xl mb-2">✂️</div>
                                    <p class="text-sm font-semibold text-gray-700">No Active Build In Progress</p>
                                    <p class="text-xs text-gray-500 mt-1">Start a kit from your Hangar or Backlog Plan to track active build tasks here.</p>
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
                                        <a href="/build_progress?filter_backlog=<?= $active_project['backlogid'] ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-lg transition-colors">
                                            📝 Build Log
                                        </a>
                                    <?php else: ?>
                                        <a href="/kit/<?= $active_project['inventoryid'] ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-lg transition-colors">
                                            Kit Details →
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($next_active_task): ?>
                                    <form method="POST">
                                        <input type="hidden" name="toggle_id" value="<?= $next_active_task['taskid'] ?>">
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-800 border border-emerald-300 rounded-lg transition-all shadow-2xs">
                                            ☑️ Complete Step
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 2: Quick Dispatch & Rapid Capture -->
                    <div id="dispatch-panel" class="bg-gradient-to-br from-white to-amber-50/30 border-2 border-amber-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    ⚡ Quick Task Insert
                                </span>
                                <span class="text-xs text-amber-700 font-medium">
                                    <?= $stats['pending'] ?> to-do tasks total
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">
                                Rapidly log build steps without leaving your current workflow.
                            </p>

                            <!-- Quick Form -->
                            <form method="POST" class="space-y-2.5">
                                <input type="hidden" name="action_type" value="add">
                                
                                <div class="flex gap-2">
                                    <input type="text" name="description" placeholder="e.g. Scribe panel lines, prime armor pieces..." required
                                           class="flex-1 px-3 py-2 text-xs sm:text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:outline-none bg-white">
                                    
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold bg-amber-50 hover:bg-amber-600 hover:text-white text-amber-800 border border-amber-300 rounded-lg transition-all shadow-2xs whitespace-nowrap">
                                        ➕ Add Step
                                    </button>
                                </div>

                                <div class="flex items-center gap-2 text-xs">
                                    <span class="text-gray-500 font-medium whitespace-nowrap">Assign to:</span>
                                    <select name="backlogid" class="flex-1 p-1.5 text-xs border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                        <?php if ($active_project && !empty($active_project['backlogid'])): ?>
                                            <option value="<?= $active_project['backlogid'] ?>" selected>
                                                🎯 Active: <?= htmlspecialchars($active_project['name']) ?>
                                            </option>
                                        <?php endif; ?>
                                        <option value="" <?= (!$active_project || empty($active_project['backlogid'])) ? 'selected' : '' ?>>📌 General Task (No Kit)</option>
                                        <?php foreach ($backlog_items as $item): ?>
                                            <?php if (!$active_project || empty($active_project['backlogid']) || $item['actualid'] != $active_project['backlogid']): ?>
                                                <option value="<?= $item['actualid'] ?>"><?= htmlspecialchars($item['name']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>

                            <!-- Mini Bench Tasks Bar -->
                            <div class="mt-4 pt-3 border-t border-amber-100">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-gray-700 flex items-center gap-1">
                                        <span>📌 General Bench Chores</span>
                                        <span class="text-gray-400 font-normal">(<?= count($general_tasks) ?>)</span>
                                    </span>
                                </div>
                                <?php if (!empty($general_tasks)): ?>
                                    <div class="space-y-1.5 max-h-24 overflow-y-auto pr-1">
                                        <?php foreach (array_slice($general_tasks, 0, 3) as $gt): ?>
                                            <div class="flex items-center justify-between gap-2 p-1.5 bg-white/80 rounded border border-gray-200 text-xs">
                                                <span class="truncate text-gray-700 <?= $gt['is_done'] ? 'line-through text-gray-400' : '' ?>">
                                                    <?= htmlspecialchars($gt['description']) ?>
                                                </span>
                                                <form method="POST" class="flex-shrink-0">
                                                    <input type="hidden" name="toggle_id" value="<?= $gt['taskid'] ?>">
                                                    <button type="submit" class="text-xs hover:scale-110 transition" title="Toggle">
                                                        <?= $gt['is_done'] ? '☑️' : '⬜' ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-xs text-gray-400 italic">No general tasks logged. Use this for tool maintenance, sanding prep, or restock chores.</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-amber-100 flex items-center justify-between mt-auto">
                            <span class="text-2xs text-gray-400">💡 Tip: Press Enter to rapidly add steps while building</span>
                            <button type="button" onclick="openAddModal()" class="text-xs text-amber-700 hover:text-amber-900 font-medium">
                                Full Task Dialog (+ Image) →
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <div class="flex items-center justify-between mb-2">
            <h3 class="text-xl font-bold text-gray-700">📝 Current Tasks</h3>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
                <form method="GET">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-gray-500 uppercase">Filters</span>
                        <button type="button" class="filter-toggle-btn" onclick="toggleFilterBar(this)">
                            ▼ Filters
                        </button>
                    </div>
                    <div class="filter-bar-body <?= $has_filters ? 'is-open' : '' ?>">
                    
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                   placeholder="Task description..." 
                                   class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Kit</label>
                            <select name="filter_kit" class="w-full mt-1 p-2 border border-gray-300 rounded">
                                <option value="">All Kits</option>
                                <?php foreach ($filter_kits as $kit): ?>
                                    <option value="<?= $kit['inventoryid'] ?>" <?= ($_GET['filter_kit'] ?? '') == $kit['inventoryid'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kit['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Status</label>
                            <select name="filter_status" class="w-full mt-1 p-2 border border-gray-300 rounded">
                                <option value="">All Statuses</option>
                                <option value="0" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] === '0') ? 'selected' : '' ?>>To-Do</option>
                                <option value="1" <?= ($_GET['filter_status'] ?? '') === '1' ? 'selected' : '' ?>>Done</option>
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

            <?php foreach ($grouped as $group_key => $tasks): ?>
                <div class="mb-6">

                    <h3 class="text-md font-bold text-gray-700 mb-2 flex items-center gap-2">
                        <?php if ($group_key === '__general__'): ?>
                            📌 General Tasks
                        <?php else: ?>
                            🔶 <a href="/kit/<?= $tasks[0]['inventoryid'] ?>" class="text-blue-600 hover:underline"><?= e($group_key) ?></a>
                            <?php
                            $first = $tasks[0];
                            if (!empty($first['buildplan_label'])): ?>
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-purple-100 text-purple-800">
                                    <?= e($first['buildplan_label']) ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </h3>

                    <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
                        <?php foreach ($tasks as $task): ?>
                        <div class="flex items-center px-4 py-3 gap-3 <?= $task['is_done'] ? 'opacity-50' : '' ?>">

                            <form method="POST" class="flex-shrink-0">
                                <input type="hidden" name="toggle_id" value="<?= $task['taskid'] ?>">
                                <button type="submit" class="text-xl hover:scale-110 transition" title="Toggle">
                                    <?= $task['is_done'] ? '☑️' : '⬜' ?>
                                </button>
                            </form>

                            <span class="flex-1 text-sm <?= $task['is_done'] ? 'line-through text-gray-400' : 'text-gray-800' ?>">
                                <?php if (!empty($task['kit_name'])): ?>
                                    <span class="font-bold mr-1"><?= e($task['kit_name']) ?> —</span>
                                <?php endif; ?>
                                <?= e($task['description']) ?>
                            </span>

                            <?php if (!empty($task['imagepath'])): ?>
                            <a href="<?= e($task['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="flex-shrink-0">
                                <img src="<?= e($task['imagepath']) ?>" alt="Reference"
                                     class="w-10 h-10 object-cover rounded border hover:opacity-80 transition"
                                     loading="lazy">
                            </a>
                            <?php endif; ?>

                            <span class="text-xs text-gray-400 flex-shrink-0">
                                <?= date('d M', strtotime($task['createdat'])) ?>
                            </span>

                            <div class="flex items-center gap-1 flex-shrink-0">
                                <button type="button" class="p-1 hover:bg-gray-200 rounded text-sm" title="Edit"
                                        data-id="<?= $task['taskid'] ?>"
                                        data-backlogid="<?= $task['backlogid'] ?? '' ?>"
                                        data-description="<?= e($task['description']) ?>"
                                        data-imagepath="<?= e($task['imagepath'] ?? '') ?>"
                                        onclick="openEditModal(this)">
                                    ✏️
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this task?');">
                                    <input type="hidden" name="deleteid" value="<?= $task['taskid'] ?>">
                                    <button type="submit" class="p-1 hover:bg-red-100 rounded text-sm" title="Delete">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($grouped)): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center text-sm text-gray-500">
                    No tasks yet! Click the + button to add one.
                </div>
            <?php endif; ?>

        </div>

    <button onclick="openAddModal()" 
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Add Task">
        +
    </button>

    <?php $mode = 'add'; include '../components/task_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/task_modal.php'; ?>

    <script>
        function previewImage(input, previewId) {
            var preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
            }
        }

        function openEditModal(button) {
            document.getElementById('modal_id').value = button.getAttribute('data-id');
            document.getElementById('modal_backlogid').value = button.getAttribute('data-backlogid');
            document.getElementById('modal_description').value = button.getAttribute('data-description');

            var imagepath = button.getAttribute('data-imagepath');
            document.getElementById('modal_existing_imagepath').value = imagepath;

            var preview = document.getElementById('editPreview');
            var currentLabel = document.getElementById('editCurrentImage');
            if (imagepath) {
                preview.src = imagepath;
                preview.classList.remove('hidden');
                currentLabel.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
                currentLabel.classList.add('hidden');
            }

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex';
        }
    </script>

<?php include '../components/layout_footer.php'; ?>
<script>initScrollRestore('tasks_scroll');</script>
