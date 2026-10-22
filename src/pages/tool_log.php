<?php
require_once '../includes/bootstrap.php';

$current_section = 'tools';
$current_page    = 'tool_log';
$page_title      = 'Tool Logs';

// ============================================
// POST REQUEST DISPATCH
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification if token is submitted
    if (isset($_POST['csrf_token']) && !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('❌ Security validation failed (invalid CSRF token)');
        header('Location: /tool_log');
        exit;
    }

    $action_success = false;
    $msg_text = null;

    // 1. Delete Log Entry
    if (!empty($_POST['deleteid']) || (($_POST['action_type'] ?? '') === 'delete' && !empty($_POST['logid']))) {
        $del_id = $_POST['deleteid'] ?? $_POST['logid'];
        $action_success = delete_tool_log($conn, $del_id);
        $msg_text = $action_success ? '✅ Log entry deleted successfully' : '❌ Failed to delete log entry';
    }
    // 2. Add New Log Entry
    elseif (($_POST['action_type'] ?? '') === 'add') {
        if (!empty($_FILES['image']['name'])) {
            $uploaded = handle_image_upload($_FILES['image'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
            if (!$uploaded['success']) {
                set_flash_message('❌ ' . $uploaded['error']);
                header('Location: /tool_log');
                exit;
            }
            $_POST['imagepath'] = $uploaded['path'];
        }
        $log_id = add_tool_log_entry($conn, $_POST);
        $action_success = (bool)$log_id;
        $msg_text = $action_success ? '✅ Tool log recorded successfully!' : '❌ Error recording tool log';
    }
    // 3. Edit Existing Log Entry
    elseif (($_POST['action_type'] ?? '') === 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $uploaded = handle_image_upload($_FILES['image'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
            if (!$uploaded['success']) {
                set_flash_message('❌ ' . $uploaded['error']);
                header('Location: /tool_log');
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
            }
            $_POST['imagepath'] = $uploaded['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? '';
        }
        $action_success = update_tool_log($conn, $_POST);
        $msg_text = $action_success ? '✅ Tool log updated successfully' : '❌ Error updating tool log';
    }

    if ($msg_text !== null) {
        set_flash_message($msg_text);
    }
    header('Location: /tool_log');
    exit;
}

// ============================================
// DATA FETCHING FOR RENDERING
// ============================================
$message      = get_flash_message();
$csrf_token   = csrf_token();
$tools_list   = get_tools_for_log_dropdown($conn);
$backlog_kits = get_backlog_kits_for_log_dropdown($conn);
$log_types    = get_tool_log_types();
$logs         = get_tool_logs_list($conn, $_GET);
$stats        = calculate_tool_log_stats($logs);

$has_filters = !empty($_GET['search']) 
            || !empty($_GET['filter_tool']) 
            || !empty($_GET['filter_type']) 
            || !empty($_GET['filter_backlog']) 
            || !empty($_GET['filter_date_from']) 
            || !empty($_GET['filter_date_to']);

// Dataset for Recent Log Spotlight Carousel
$recent_logs = array_slice($logs, 0, 10);
$top_log     = !empty($recent_logs) ? $recent_logs[0] : null;

include '../components/layout_header.php';
?>

<div class="max-w-7xl mx-auto w-full">
    <!-- Centered Page Header Matching Application Standard -->
    <h1 class="page-title font-bold text-gray-700 text-center mb-8">📝 Tool Activity Logs</h1>

    <?php include '../components/toast.php'; ?>

    <!-- Summary Stats Bar -->
    <div class="mb-8">
        <?php if ($has_filters): ?>
            <div class="text-sm text-blue-600 mb-2 flex items-center gap-2">
                <span>📊</span>
                <span>Showing stats for current filter</span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            unset($icon);
            $value = $stats['total_logs'];
            $label = $has_filters ? 'Filtered Logs' : 'Total Logs';
            $color = 'blue';
            include '../components/stats/stat_card.php';

            $value = $stats['maintenance_count'];
            $label = 'Maintenance Logs';
            $color = 'yellow';
            include '../components/stats/stat_card.php';

            $value = $stats['usage_count'];
            $label = 'Build Usage Logs';
            $color = 'green';
            include '../components/stats/stat_card.php';

            $value = $stats['tools_logged'];
            $label = 'Tools Logged';
            $color = 'purple';
            include '../components/stats/stat_card.php';
            ?>
        </div>
    </div>

    <!-- Workshop Activity Hub -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span>🧰</span> Workshop Activity Hub
            </h3>
            <span class="text-xs text-gray-500 font-medium hidden sm:inline">Rapid service logger & recent log inspection radar</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Panel 1: Fast Activity Logger -->
            <div class="bg-gradient-to-br from-white to-blue-50/40 border-2 border-blue-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3 gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                            <span>⚡</span> Quick Activity Logger
                        </span>
                        <span class="text-xs text-gray-500 font-medium hidden sm:inline">Instant workshop logging</span>
                    </div>

                    <form method="POST" action="/tool_log" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="action_type" value="add">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                        <!-- Tool Target -->
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Target Tool *</label>
                            <select name="toolid" required class="w-full text-xs p-2 border border-gray-300 rounded font-medium bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">-- Choose Workshop Tool --</option>
                                <?php foreach ($tools_list as $t): ?>
                                    <option value="<?= (int)$t['toolid'] ?>">
                                        <?= e($t['name']) ?> <?= !empty($t['brand']) ? '(' . e($t['brand']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <!-- Log Type -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Log Type *</label>
                                <select name="log_type" required class="w-full text-xs p-2 border border-gray-300 rounded font-medium bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                    <option value="Maintenance">Maintenance (Sharpen, clean, lube)</option>
                                    <option value="Usage">Usage (Kit build session)</option>
                                    <option value="General">General / Inspection</option>
                                </select>
                            </div>

                            <!-- Log Date -->
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Date *</label>
                                <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required
                                       class="w-full text-xs p-2 border border-gray-300 rounded font-medium bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            </div>
                        </div>

                        <!-- Associated Kit (Optional) -->
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Associated Kit (Optional)</label>
                            <select name="backlogid" class="w-full text-xs p-2 border border-gray-300 rounded font-medium bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">-- None / General Maintenance --</option>
                                <?php foreach ($backlog_kits as $kit): ?>
                                    <option value="<?= (int)$kit['actualid'] ?>">
                                        <?= e($kit['name']) ?> (<?= e($kit['status_label'] ?? 'Plan') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Service Notes *</label>
                            <input type="text" name="notes" placeholder="e.g. Swapped fresh blade, oiled pivot joint, deep cleaned nozzle..." required
                                   class="w-full text-xs p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                        </div>

                        <!-- Quick Photo -->
                        <div>
                            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Photo (Optional)</label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
                                   class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>

                        <div class="pt-1">
                            <button type="submit" class="w-full py-2 px-4 text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors shadow-sm">
                                + Record Activity Log
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Panel 2: Recent Log Spotlight & History Stepper -->
            <div id="spotlight-panel" class="bg-gradient-to-br from-white to-indigo-50/40 border-2 border-indigo-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-200">
                            <span class="inline-block w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                            🔍 Recent Activity Spotlight
                        </span>
                        <?php if (count($recent_logs) > 1): ?>
                            <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                <button type="button" onclick="stepLog(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Log">◀</button>
                                <span id="log-stepper-counter">1 of <?= count($recent_logs) ?></span>
                                <button type="button" onclick="stepLog(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Log">▶</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs text-indigo-800 font-medium mb-3 block">
                        <?= !empty($recent_logs) ? count($recent_logs) . ' recent workshop logs in history feed' : 'No logs recorded yet' ?>
                    </span>

                    <div id="log-spotlight-content">
                        <?php if ($top_log): ?>
                            <?php
                            $type_lower = strtolower($top_log['log_type'] ?? 'general');
                            $type_pill_class = match($type_lower) {
                                'maintenance' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'usage'       => 'bg-blue-100 text-blue-800 border-blue-200',
                                default       => 'bg-gray-100 text-gray-800 border-gray-200'
                            };
                            $display_img = !empty($top_log['imagepath']) ? $top_log['imagepath'] : ($top_log['tool_imagepath'] ?? null);
                            ?>
                            <div class="mb-4">
                                <div class="flex items-start gap-3.5">
                                    <?php if (!empty($display_img)): ?>
                                        <a href="<?= e($display_img) ?>" target="_blank" rel="noopener noreferrer" class="flex-shrink-0">
                                            <img src="<?= e($display_img) ?>" alt="Tool" class="w-16 h-16 object-cover rounded-lg border border-indigo-200 shadow-2xs hover:opacity-90 transition">
                                        </a>
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center text-2xl flex-shrink-0 text-indigo-400">
                                            🔧
                                        </div>
                                    <?php endif; ?>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                            <a href="/tool/<?= (int)$top_log['toolid'] ?>" class="hover:text-blue-600 transition">
                                                <?= htmlspecialchars($top_log['tool_name'] ?? 'Unnamed Tool') ?>
                                            </a>
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                            <?php if (!empty($top_log['brand'])): ?>
                                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($top_log['brandid']) ?>">
                                                    <?= htmlspecialchars($top_log['brand']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($top_log['category'])): ?>
                                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($top_log['categoryid']) ?>">
                                                    <?= htmlspecialchars($top_log['category']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full border <?= $type_pill_class ?>">
                                                <?= htmlspecialchars($top_log['log_type']) ?>
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 font-mono mt-1">
                                            🗓️ <?= date('d M Y', strtotime($top_log['log_date'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty($top_log['kit_name'])): ?>
                                    <div class="mt-3 text-xs bg-white/90 border border-indigo-100 rounded-lg p-2 flex items-center gap-2">
                                        <span>🤖</span>
                                        <span class="font-bold text-gray-700">Used on Kit:</span>
                                        <a href="/kit/<?= (int)$top_log['inventory_id'] ?>" class="text-blue-600 hover:underline font-semibold">
                                            <?= htmlspecialchars($top_log['kit_name']) ?>
                                        </a>
                                        <?php if (!empty($top_log['backlog_code'])): ?>
                                            <span class="text-gray-400 font-mono">(<?= htmlspecialchars($top_log['backlog_code']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($top_log['notes']) && $top_log['notes'] !== '-'): ?>
                                    <div class="mt-2 text-xs text-gray-700 bg-white/90 border border-indigo-100 rounded-lg p-2.5 italic">
                                        📝 <?= htmlspecialchars($top_log['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-6 text-center">
                                <div class="text-3xl mb-2">📜</div>
                                <p class="text-sm font-semibold text-gray-700">No Workshop Activity Recorded</p>
                                <p class="text-xs text-gray-500 mt-1">Use the quick logger on the left or the + button to record maintenance, sharpening, or build usage.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($top_log): ?>
                    <div class="pt-3 border-t border-indigo-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                        <a href="/tool_inventory" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-900 transition-colors font-medium">
                            View Tool Inventory →
                        </a>
                        <div id="spotlight-action-container">
                            <a id="spotlight-tool-link" href="/tool/<?= (int)$top_log['toolid'] ?>"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg transition-colors shadow-2xs">
                                🔍 View Tool Profile
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Analytics Charts -->
    <?php if ($stats['total_logs'] > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <?php
            $id = 'typeChart';
            $title = 'Activity by Log Type';
            include '../components/charts/chart_canvas.php';

            $id = 'toolActivityChart';
            $title = 'Most Serviced / Used Tools';
            include '../components/charts/chart_canvas.php';
            ?>
        </div>
        <?php include '../components/charts/init_charts.php'; ?>
        <script>
        (function() {
            const typeData = <?= json_encode($stats['type_counts'], JSON_HEX_TAG) ?>;
            const toolData = <?= json_encode(array_slice($stats['tool_counts'], 0, 6, true), JSON_HEX_TAG) ?>;
            initDoughnutChart('typeChart', Object.keys(typeData), Object.values(typeData));
            initBarChart('toolActivityChart', Object.keys(toolData), Object.values(toolData));
        })();
        </script>
    <?php endif; ?>

    <!-- Filter & Search Toolbar -->
    <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
        <form method="GET" action="/tool_log">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-bold text-gray-500 uppercase">Filters</span>
                <button type="button" class="filter-toggle-btn" onclick="toggleFilterBar(this)">▼ Filters</button>
            </div>
            <div class="filter-bar-body <?= $has_filters ? 'is-open' : '' ?>">
                <!-- Search Text -->
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                    <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Tool name, notes, kit..."
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm">
                </div>

                <!-- Tool Selector -->
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Tool</label>
                    <select name="filter_tool" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Tools</option>
                        <?php foreach ($tools_list as $t): ?>
                            <option value="<?= (int)$t['toolid'] ?>" <?= (isset($_GET['filter_tool']) && (int)$_GET['filter_tool'] === (int)$t['toolid']) ? 'selected' : '' ?>>
                                <?= e($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Log Type -->
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Log Type</label>
                    <select name="filter_type" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Types</option>
                        <?php foreach ($log_types as $lt): ?>
                            <option value="<?= e($lt) ?>" <?= (($_GET['filter_type'] ?? '') === $lt) ? 'selected' : '' ?>>
                                <?= e($lt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Associated Kit Backlog -->
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Associated Kit</label>
                    <select name="filter_backlog" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Kits</option>
                        <?php foreach ($backlog_kits as $kit): ?>
                            <option value="<?= (int)$kit['actualid'] ?>" <?= (isset($_GET['filter_backlog']) && (int)$_GET['filter_backlog'] === (int)$kit['actualid']) ? 'selected' : '' ?>>
                                <?= e($kit['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">From Date</label>
                    <input type="date" name="filter_date_from" value="<?= e($_GET['filter_date_from'] ?? '') ?>"
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">To Date</label>
                    <input type="date" name="filter_date_to" value="<?= e($_GET['filter_date_to'] ?? '') ?>"
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                </div>

                <!-- Filter Actions -->
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition font-medium text-sm">Apply</button>
                    <?php if ($has_filters): ?>
                        <a href="/tool_log" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition text-center flex items-center justify-center text-sm">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Interactive Logs Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 mobile-stack-table">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'date')">Date</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Tool</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Type</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Associated Kit</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes &amp; Photo</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $safe_name     = htmlspecialchars($log['tool_name'] ?? 'Tool', ENT_QUOTES);
                        $safe_notes    = htmlspecialchars($log['notes'] ?? '-', ENT_QUOTES);
                        $safe_type     = htmlspecialchars($log['log_type'] ?? 'General', ENT_QUOTES);
                        $type_lower    = strtolower($log['log_type'] ?? 'general');
                        $brand_class   = get_brand_color_palette($log['brandid'] ?? 0);
                        $cat_class     = get_brand_color_palette($log['categoryid'] ?? 0);

                        $type_badge_class = match($type_lower) {
                            'maintenance' => 'bg-amber-100 text-amber-800 border-amber-200',
                            'usage'       => 'bg-blue-100 text-blue-800 border-blue-200',
                            default       => 'bg-gray-100 text-gray-800 border-gray-200'
                        };
                        ?>
                        <tr class="hover:bg-gray-50 border-b border-gray-100 transition">
                            <!-- Date Column -->
                            <td data-label="Date" class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs font-mono text-center">
                                <?= e(date('d M Y', strtotime($log['log_date']))) ?>
                            </td>

                            <!-- Tool Column -->
                            <td data-label="Tool" class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($log['tool_imagepath'])): ?>
                                        <img src="<?= e($log['tool_imagepath']) ?>" alt="" class="w-9 h-9 object-cover rounded-lg border border-gray-200 flex-shrink-0">
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <a href="/tool/<?= (int)$log['toolid'] ?>" class="font-bold text-gray-900 hover:text-blue-600 transition">
                                            <?= $safe_name ?>
                                        </a>
                                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                            <?php if (!empty($log['brand'])): ?>
                                                <span class="px-2 py-0.2 text-[10px] font-semibold rounded-full <?= $brand_class ?>">
                                                    <?= e($log['brand']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($log['category'])): ?>
                                                <span class="px-2 py-0.2 text-[10px] font-semibold rounded-full <?= $cat_class ?>">
                                                    <?= e($log['category']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Log Type Column -->
                            <td data-label="Type" class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border <?= $type_badge_class ?>">
                                    <?= $safe_type ?>
                                </span>
                            </td>

                            <!-- Associated Kit Column -->
                            <td data-label="Associated Kit" class="px-4 py-3 text-gray-600">
                                <?php if (!empty($log['kit_name'])): ?>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs">🤖</span>
                                        <?php if (!empty($log['inventory_id'])): ?>
                                            <a href="/kit/<?= (int)$log['inventory_id'] ?>" class="text-xs font-semibold text-blue-600 hover:underline">
                                                <?= e($log['kit_name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs font-semibold text-gray-800"><?= e($log['kit_name']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($log['backlog_code'])): ?>
                                            <span class="text-[10px] text-gray-400 font-mono">(<?= e($log['backlog_code']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Notes & Photo Column -->
                            <td data-label="Notes &amp; Photo" class="px-4 py-3">
                                <div class="flex items-start gap-2.5">
                                    <?php if (!empty($log['imagepath'])): ?>
                                        <a href="<?= e($log['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="flex-shrink-0" title="View attached photo">
                                            <img src="<?= e($log['imagepath']) ?>" alt="Log Photo"
                                                 class="w-10 h-10 object-cover rounded border border-gray-300 shadow-2xs hover:opacity-90 transition">
                                        </a>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-700 max-w-sm line-clamp-2" title="<?= $safe_notes ?>">
                                        <?= !empty($log['notes']) ? nl2br($safe_notes) : '<span class="text-gray-400 italic">No notes</span>' ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Actions Column -->
                            <td data-label="Actions" class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center space-x-1">
                                    <button type="button" class="p-1 hover:bg-gray-200 rounded text-lg transition" title="Edit Log"
                                            data-logid="<?= e($log['logid']) ?>"
                                            data-toolid="<?= (int)$log['toolid'] ?>"
                                            data-logtype="<?= e($log['log_type']) ?>"
                                            data-logdate="<?= e($log['log_date']) ?>"
                                            data-backlogid="<?= (int)($log['backlogid'] ?? 0) ?>"
                                            data-notes="<?= $safe_notes ?>"
                                            data-imagepath="<?= htmlspecialchars($log['imagepath'] ?? '', ENT_QUOTES) ?>"
                                            onclick="openEditLogModal(this)">
                                        ✏️
                                    </button>

                                    <form method="POST" action="/tool_log" onsubmit="return confirm('Delete this log entry for <?= $safe_name ?>?');" class="inline m-0">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                        <input type="hidden" name="deleteid" value="<?= e($log['logid']) ?>">
                                        <button type="submit" class="p-1 hover:bg-red-100 rounded text-lg transition" title="Delete Log">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500">
                            <div class="text-3xl mb-2">📜</div>
                            <p class="font-bold text-gray-700">No Tool Logs Found</p>
                            <p class="text-xs text-gray-400 mt-1">Try resetting filters or click the floating + button to log workshop activity.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Floating Action Button -->
<button onclick="openAddLogModal()"
        class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
        title="Record Tool Activity Log">+
</button>

<!-- Add Tool Log Modal -->
<div id="addToolLogModal" role="dialog" aria-modal="true" aria-labelledby="add-log-title"
     class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50" style="display:none;"
     onclick="if (event.target === this) closeAddLogModal()">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-lg p-6 modal-animate relative overflow-y-auto max-h-[90vh]">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600 transition" onclick="closeAddLogModal()">&times;</span>
        <h3 id="add-log-title" class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span>📝</span> Record Tool Activity Log
        </h3>

        <form method="POST" action="/tool_log" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="add">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <!-- Tool Target -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Target Tool *</label>
                <select name="toolid" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm">
                    <option value="">-- Choose Workshop Tool --</option>
                    <?php foreach ($tools_list as $t): ?>
                        <option value="<?= (int)$t['toolid'] ?>">
                            <?= e($t['name']) ?> <?= !empty($t['brand']) ? '(' . e($t['brand']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Log Type -->
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Log Type *</label>
                    <select name="log_type" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="Maintenance">Maintenance (Sharpen, clean, lube)</option>
                        <option value="Usage">Usage (Kit build session)</option>
                        <option value="General">General / Inspection</option>
                    </select>
                </div>

                <!-- Log Date -->
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Log Date *</label>
                    <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <!-- Associated Kit Backlog -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Associated Kit (Optional)</label>
                <select name="backlogid" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="">-- None / General Maintenance --</option>
                    <?php foreach ($backlog_kits as $kit): ?>
                        <option value="<?= (int)$kit['actualid'] ?>">
                            <?= e($kit['name']) ?> (<?= e($kit['status_label'] ?? 'Plan') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Service Notes -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Notes / Description *</label>
                <textarea name="notes" rows="3" required placeholder="e.g. Swapped fresh blade, oiled pivot joint, deep cleaned airbrush nozzle..."
                          class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
            </div>

            <!-- Photo Upload -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Attach Photo (Optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
                       class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="closeAddLogModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-lg text-sm transition">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg text-sm transition shadow-sm">
                    Save Log Entry
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Tool Log Modal -->
<div id="editToolLogModal" role="dialog" aria-modal="true" aria-labelledby="edit-log-title"
     class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50" style="display:none;"
     onclick="if (event.target === this) closeEditLogModal()">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-lg p-6 modal-animate relative overflow-y-auto max-h-[90vh]">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600 transition" onclick="closeEditLogModal()">&times;</span>
        <h3 id="edit-log-title" class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span>✏️</span> Edit Tool Activity Log
        </h3>

        <form method="POST" action="/tool_log" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="edit">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
            <input type="hidden" name="logid" id="edit_logid">
            <input type="hidden" name="existing_imagepath" id="edit_existing_imagepath">

            <!-- Tool Target -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Target Tool *</label>
                <select name="toolid" id="edit_toolid" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm">
                    <option value="">-- Choose Workshop Tool --</option>
                    <?php foreach ($tools_list as $t): ?>
                        <option value="<?= (int)$t['toolid'] ?>">
                            <?= e($t['name']) ?> <?= !empty($t['brand']) ? '(' . e($t['brand']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Log Type -->
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Log Type *</label>
                    <select name="log_type" id="edit_logtype" required class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="Maintenance">Maintenance (Sharpen, clean, lube)</option>
                        <option value="Usage">Usage (Kit build session)</option>
                        <option value="General">General / Inspection</option>
                    </select>
                </div>

                <!-- Log Date -->
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Log Date *</label>
                    <input type="date" name="log_date" id="edit_logdate" required
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
            </div>

            <!-- Associated Kit Backlog -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Associated Kit (Optional)</label>
                <select name="backlogid" id="edit_backlogid" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="">-- None / General Maintenance --</option>
                    <?php foreach ($backlog_kits as $kit): ?>
                        <option value="<?= (int)$kit['actualid'] ?>">
                            <?= e($kit['name']) ?> (<?= e($kit['status_label'] ?? 'Plan') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Service Notes -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Notes / Description *</label>
                <textarea name="notes" id="edit_notes" rows="3" required
                          class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
            </div>

            <!-- Photo Upload & Existing Preview -->
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Photo Attachment</label>
                <div id="edit_image_preview" class="mb-2 hidden flex items-center gap-3">
                    <img id="edit_image_thumb" src="" alt="Photo" class="w-12 h-12 object-cover rounded border border-gray-200 shadow-2xs">
                    <span class="text-xs text-gray-500 italic">Current attached photo</span>
                </div>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
                       class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-[11px] text-gray-400 mt-0.5">Leave empty to keep existing photo</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="closeEditLogModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-lg text-sm transition">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg text-sm transition shadow-sm">
                    Update Log
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Spotlight Carousel Datasets & Logic
    const recentLogs = <?= json_encode(array_map(function($l) {
        $tLower = strtolower($l['log_type'] ?? 'general');
        $badgeClass = match($tLower) {
            'maintenance' => 'bg-amber-100 text-amber-800 border-amber-200',
            'usage'       => 'bg-blue-100 text-blue-800 border-blue-200',
            default       => 'bg-gray-100 text-gray-800 border-gray-200'
        };
        return [
            'logid'          => $l['logid'],
            'toolid'         => (int)$l['toolid'],
            'tool_name'      => $l['tool_name'] ?? 'Tool',
            'brand'          => $l['brand'] ?? '',
            'brand_badge'    => get_brand_color_palette($l['brandid'] ?? 0),
            'category'       => $l['category'] ?? '',
            'category_badge' => get_brand_color_palette($l['categoryid'] ?? 0),
            'log_type'       => $l['log_type'] ?? 'General',
            'type_badge'     => $badgeClass,
            'log_date'       => date('d M Y', strtotime($l['log_date'])),
            'notes'          => $l['notes'] ?? '',
            'imagepath'      => !empty($l['imagepath']) ? $l['imagepath'] : ($l['tool_imagepath'] ?? ''),
            'kit_name'       => $l['kit_name'] ?? '',
            'backlog_code'   => $l['backlog_code'] ?? '',
            'inventory_id'   => !empty($l['inventory_id']) ? (int)$l['inventory_id'] : null,
        ];
    }, $recent_logs), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];

    let currentLogIdx = 0;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stepLog(delta) {
        if (!recentLogs || recentLogs.length === 0) return;
        currentLogIdx = (currentLogIdx + delta + recentLogs.length) % recentLogs.length;
        renderLogSpotlight();
    }

    function renderLogSpotlight() {
        const item = recentLogs[currentLogIdx];
        if (!item) return;

        const counter = document.getElementById('log-stepper-counter');
        if (counter) counter.textContent = `${currentLogIdx + 1} of ${recentLogs.length}`;

        const container = document.getElementById('log-spotlight-content');
        if (container) {
            const imgHtml = item.imagepath ? 
                `<a href="${escapeHtml(item.imagepath)}" target="_blank" rel="noopener noreferrer" class="flex-shrink-0">
                    <img src="${escapeHtml(item.imagepath)}" alt="Tool" class="w-16 h-16 object-cover rounded-lg border border-indigo-200 shadow-2xs hover:opacity-90 transition">
                 </a>` :
                `<div class="w-16 h-16 rounded-lg bg-indigo-50 border border-indigo-200 flex items-center justify-center text-2xl flex-shrink-0 text-indigo-400">🔧</div>`;

            const brandPill = item.brand ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span>` : '';
            const catPill = item.category ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.category_badge}">${escapeHtml(item.category)}</span>` : '';

            const kitBox = item.kit_name ? `
                <div class="mt-3 text-xs bg-white/90 border border-indigo-100 rounded-lg p-2 flex items-center gap-2">
                    <span>🤖</span>
                    <span class="font-bold text-gray-700">Used on Kit:</span>
                    ${item.inventory_id ? `<a href="/kit/${item.inventory_id}" class="text-blue-600 hover:underline font-semibold">${escapeHtml(item.kit_name)}</a>` : `<span class="text-gray-800 font-semibold">${escapeHtml(item.kit_name)}</span>`}
                    ${item.backlog_code ? `<span class="text-gray-400 font-mono">(${escapeHtml(item.backlog_code)})</span>` : ''}
                </div>
            ` : '';

            const notesBox = (item.notes && item.notes !== '-') ? `
                <div class="mt-2 text-xs text-gray-700 bg-white/90 border border-indigo-100 rounded-lg p-2.5 italic">
                    📝 ${escapeHtml(item.notes)}
                </div>
            ` : '';

            container.innerHTML = `
                <div class="mb-4">
                    <div class="flex items-start gap-3.5">
                        ${imgHtml}
                        <div class="min-w-0 flex-1">
                            <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                <a href="/tool/${item.toolid}" class="hover:text-blue-600 transition">
                                    ${escapeHtml(item.tool_name)}
                                </a>
                            </h4>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                ${brandPill}
                                ${catPill}
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full border ${item.type_badge}">
                                    ${escapeHtml(item.log_type)}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 font-mono mt-1">
                                🗓️ ${escapeHtml(item.log_date)}
                            </div>
                        </div>
                    </div>
                    ${kitBox}
                    ${notesBox}
                </div>
            `;
        }

        const actionContainer = document.getElementById('spotlight-action-container');
        if (actionContainer) {
            actionContainer.innerHTML = `
                <a href="/tool/${item.toolid}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg transition-colors shadow-2xs">
                    🔍 View Tool Profile
                </a>
            `;
        }
    }

    // Modal Handlers
    function openAddLogModal() {
        const modal = document.getElementById('addToolLogModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
        }
    }

    function closeAddLogModal() {
        const modal = document.getElementById('addToolLogModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }
    }

    function openEditLogModal(btn) {
        const get = attr => btn.getAttribute('data-' + attr) || '';

        document.getElementById('edit_logid').value     = get('logid');
        document.getElementById('edit_toolid').value    = get('toolid');
        document.getElementById('edit_logtype').value   = get('logtype');
        document.getElementById('edit_logdate').value   = get('logdate');
        document.getElementById('edit_backlogid').value = get('backlogid') || '';
        document.getElementById('edit_notes').value     = get('notes') === '-' ? '' : get('notes');

        const imgPath = get('imagepath');
        document.getElementById('edit_existing_imagepath').value = imgPath;
        const preview = document.getElementById('edit_image_preview');
        const thumb   = document.getElementById('edit_image_thumb');

        if (imgPath) {
            thumb.src = imgPath;
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }

        const modal = document.getElementById('editToolLogModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
        }
    }

    function closeEditLogModal() {
        const modal = document.getElementById('editToolLogModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }
    }

    // Escape Key Listener
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddLogModal();
            closeEditLogModal();
        }
    });
</script>

<script>initScrollRestore('tool_log_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
