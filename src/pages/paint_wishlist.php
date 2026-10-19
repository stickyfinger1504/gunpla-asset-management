<?php
require '../includes/bootstrap.php';

$current_section = 'paints';
$current_page = 'paint_wishlist';
$page_title = 'Paint Wishlist';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $action_success = delete_paint_wishlist_item($conn, (int)$_POST['deleteid']);
        $msg_text = $action_success ? "✅ Successfully Deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['obtainedid'])) {
        $action_success = mark_paint_wishlist_obtained($conn, (int)$_POST['obtainedid']);
        $msg_text = $action_success ? "✅ Marked as Obtained" : "❌ Failed to mark as obtained";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $action_success = add_paint_wishlist_item($conn, $_POST);
        $msg_text = $action_success ? "✅ Successfully added " . htmlspecialchars($_POST["name"]) : "❌ Error adding item";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        $action_success = update_paint_wishlist_item($conn, $_POST);
        $msg_text = $action_success ? "✅ Item updated successfully" : "❌ Error updating item";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'register_to_shelf') {
        $action_success = add_paint($conn, $_POST);
        if ($action_success && !empty($_POST['from_wishlist_id'])) {
            $wishlist_id = (int)$_POST['from_wishlist_id'];
            $new_paint_id = $conn->insert_id;
            $stmt = $conn->prepare("UPDATE paint_wishlist SET obtained = 1, inventoryid = ? WHERE wishlistid = ?");
            if ($stmt) {
                $stmt->bind_param("ii", $new_paint_id, $wishlist_id);
                $stmt->execute();
            } else {
                mark_paint_wishlist_obtained($conn, $wishlist_id);
            }
        }
        $msg_text = $action_success ? "✅ Successfully inducted " . htmlspecialchars($_POST["name"]) . " into your Paint Shelf!" : "❌ Error adding paint to shelf";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /paint_wishlist");
        exit;
    }
}

$message = get_flash_message();

$paint_brands = get_paint_brands($conn);
$paint_types = get_paint_types($conn);
$paint_finishes = get_paint_finishes($conn);
$thinned_statuses = get_thinned_statuses($conn);
$amount_levels = get_amount_levels($conn);
$priorities = get_paint_wishlist_priorities($conn);
$items = get_paint_wishlist_items($conn, $_GET);
$stats = calculate_paint_wishlist_stats($items);

$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['search']) || !empty($_GET['filter_priority']) || !empty($_GET['filter_painttype']) || !empty($_GET['filter_obtained']);

// Supply Command Hub Datasets (unfiltered maintains complete workbench overview)
$all_items = $has_filters ? get_paint_wishlist_items($conn) : $items;
$unobtained_items = array_values(array_filter($all_items, fn($i) => (int)($i['obtainedid'] ?? 0) === 0));
$high_priority_targets = array_values(array_filter($unobtained_items, fn($i) => strtolower(trim($i['priority'] ?? '')) === 'high'));
$target_pool = !empty($high_priority_targets) ? $high_priority_targets : $unobtained_items;
$top_target = !empty($target_pool) ? $target_pool[0] : null;

$obtained_items = array_values(array_filter($all_items, fn($i) => (int)($i['obtainedid'] ?? 0) === 1));
$latest_haul = !empty($obtained_items) ? $obtained_items[0] : null;

// Paint type breakdown for unobtained items (Option 2)
$unobtained_type_counts = [];
foreach ($unobtained_items as $item) {
    $t = !empty($item['painttype']) ? $item['painttype'] : 'Other';
    $unobtained_type_counts[$t] = ($unobtained_type_counts[$t] ?? 0) + 1;
}

// Find default category IDs for shelf intake modal
$default_amount_id = null;
foreach ($amount_levels as $amt) {
    if (stripos($amt['label'], 'full') !== false) {
        $default_amount_id = (int)$amt['id'];
        break;
    }
}
if (!$default_amount_id && !empty($amount_levels)) {
    $default_amount_id = (int)$amount_levels[0]['id'];
}

$default_thinned_id = null;
foreach ($thinned_statuses as $th) {
    if (stripos($th['label'], 'not thinned') !== false) {
        $default_thinned_id = (int)$th['id'];
        break;
    }
}
if (!$default_thinned_id && !empty($thinned_statuses)) {
    $default_thinned_id = (int)$thinned_statuses[0]['id'];
}

?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full">
            <h1 class="page-title font-bold text-gray-700 text-center mb-8">🎨 Paint Wishlist</h1>

            <?php include '../components/toast.php'; ?>

            <div class="mb-8">
                <?php if ($has_filters): ?>
                    <div class="text-sm text-blue-600 mb-2 flex items-center gap-2">
                        <span>📊</span>
                        <span>Showing stats for current filter</span>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php
                    $value = $stats['total_items'];
                    $label = $has_filters ? 'Filtered Items' : 'Total Items';
                    $color = 'blue';
                    include '../components/stats/stat_card.php';

                    $value = $stats['priority_counts']['High'] ?? 0;
                    $label = 'High Priority';
                    $color = 'red';
                    include '../components/stats/stat_card.php';

                    $value = $stats['priority_counts']['Mid'] ?? 0;
                    $label = 'Mid Priority';
                    $color = 'yellow';
                    include '../components/stats/stat_card.php';

                    $value = $stats['priority_counts']['Low'] ?? 0;
                    $label = 'Low Priority';
                    $color = 'green';
                    include '../components/stats/stat_card.php';
                    ?>
                </div>
            </div>

            <!-- Paint Supply Command Hub (Option 1 & 2) -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <span>🎯</span> Paint Supply Command
                    </h3>
                    <span class="text-xs text-gray-500 font-medium hidden sm:inline">Priority acquisition radar, supply run dispatcher & paint shelf intake</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Panel 1: Priority Bottle Radar (Option 1) -->
                    <div id="target-panel" class="bg-gradient-to-br from-white to-amber-50/40 border-2 border-amber-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                    🎯 Priority Target Radar
                                </span>
                                <?php if (count($target_pool) > 1): ?>
                                    <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                        <button type="button" onclick="stepTarget(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Target">◀</button>
                                        <span id="target-stepper-counter">1 of <?= count($target_pool) ?></span>
                                        <button type="button" onclick="stepTarget(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Target">▶</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-amber-800 font-medium mb-3 block">
                                <?= !empty($high_priority_targets) ? count($high_priority_targets) . ' high priority bottles queued' : count($unobtained_items) . ' unobtained paints on wishlist' ?>
                            </span>

                            <div id="target-card-content">
                                <?php if ($top_target): ?>
                                    <?php
                                        $top_priority_class = match($top_target['priority'] ?? '') {
                                            'High' => 'bg-red-100 text-red-800',
                                            'Mid' => 'bg-yellow-100 text-yellow-800',
                                            'Low' => 'bg-green-100 text-green-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                    ?>
                                    <div class="mb-4">
                                        <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                            <?= htmlspecialchars($top_target['name']) ?>
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                            <?php if (!empty($top_target['brand'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($top_target['brandid']) ?>">
                                                <?= htmlspecialchars($top_target['brand']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($top_target['painttype'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($top_target['painttypeid']) ?>">
                                                <?= htmlspecialchars($top_target['painttype']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $top_priority_class ?>">
                                                <?= htmlspecialchars($top_target['priority'] ?? 'Mid') ?> Priority
                                            </span>
                                        </div>
                                        <?php if (!empty($top_target['notes']) && $top_target['notes'] !== '-'): ?>
                                            <div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-amber-100 rounded-lg p-2.5 italic">
                                                📝 <?= htmlspecialchars($top_target['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="py-6 text-center">
                                        <div class="text-3xl mb-2">🎉</div>
                                        <p class="text-sm font-semibold text-gray-700">All Paint Targets Secured!</p>
                                        <p class="text-xs text-gray-500 mt-1">All paints on your wishlist have been obtained. Click + below to queue up new colors.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($top_target): ?>
                            <div class="pt-3 border-t border-amber-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                                <div id="target-store-link-container">
                                    <?php if (!empty($top_target['link'])): ?>
                                        <a href="<?= htmlspecialchars($top_target['link']) ?>" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 bg-white hover:bg-blue-50 border border-blue-200 rounded-lg transition-colors shadow-2xs">
                                            <span>🔗</span> Open Store
                                        </a>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 italic">No store link provided</span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex items-center gap-2">
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="obtainedid" id="target-form-obtained-id" value="<?= (int)$top_target['actualid'] ?>">
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow-sm transition-all"
                                                title="Mark this paint bottle as obtained">
                                            <span>⚡</span> Got It!
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 2: Supply Run Dispatcher & Paint Shelf Intake (Option 1 & 2) -->
                    <div id="haul-panel" class="bg-gradient-to-br from-white to-emerald-50/40 border-2 border-emerald-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <!-- Supply Run Mode (Top of Panel 2 - Option 2) -->
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <span>🛒</span> Supply Run Dispatcher
                                </span>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-white border border-emerald-200 text-emerald-800">
                                    <?= count($unobtained_items) ?> Pending
                                </span>
                            </div>

                            <div class="mb-3">
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <p class="text-xs text-gray-600 font-medium">
                                        Export pending paints to clipboard for store runs or online orders:
                                    </p>
                                    <button type="button" id="copy-supply-btn" onclick="copySupplyList()"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold bg-white hover:bg-emerald-50 text-emerald-900 border border-emerald-300 rounded-lg shadow-2xs transition-all flex-shrink-0"
                                            title="Copy formatted paint shopping list to clipboard">
                                        <span>📋</span> <span id="copy-supply-btn-text">Copy List</span>
                                    </button>
                                </div>

                                <?php if (!empty($unobtained_type_counts)): ?>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <?php foreach ($unobtained_type_counts as $type_label => $cnt): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/90 border border-emerald-200 text-gray-700">
                                                <span class="font-bold text-emerald-700"><?= $cnt ?></span> <?= htmlspecialchars($type_label) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Divider -->
                            <div class="border-t border-emerald-100 my-3"></div>

                            <!-- Recent Haul Section (Bottom of Panel 2 - Option 1) -->
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="text-xs font-bold text-emerald-900 uppercase tracking-wider flex items-center gap-1">
                                    <span>🎉</span> Recent Haul Intake
                                </span>
                                <?php if (count($obtained_items) > 1): ?>
                                    <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                        <button type="button" onclick="stepHaul(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Haul">◀</button>
                                        <span id="haul-stepper-counter">1 of <?= count($obtained_items) ?></span>
                                        <button type="button" onclick="stepHaul(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Haul">▶</button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div id="haul-card-content">
                                <?php if ($latest_haul): ?>
                                    <div class="mb-2">
                                        <h4 class="text-base font-bold text-gray-900 leading-snug">
                                            <?= htmlspecialchars($latest_haul['name']) ?>
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-2 mt-1">
                                            <?php if (!empty($latest_haul['brand'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($latest_haul['brandid']) ?>">
                                                <?= htmlspecialchars($latest_haul['brand']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($latest_haul['painttype'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($latest_haul['painttypeid']) ?>">
                                                <?= htmlspecialchars($latest_haul['painttype']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">
                                                ✅ Secured
                                            </span>
                                        </div>
                                        <?php if (!empty($latest_haul['notes']) && $latest_haul['notes'] !== '-'): ?>
                                            <div class="mt-2 text-xs text-gray-600 bg-white/80 border border-emerald-100 rounded-lg p-2 italic truncate" title="<?= htmlspecialchars($latest_haul['notes']) ?>">
                                                📝 <?= htmlspecialchars($latest_haul['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="py-4 text-center">
                                        <div class="text-2xl mb-1">📦</div>
                                        <p class="text-xs font-semibold text-gray-700">No Hauls Claimed Yet!</p>
                                        <p class="text-[11px] text-gray-500 mt-0.5">When you purchase a paint and click "Got It!", it will appear here ready to induct straight into your paint shelf.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($latest_haul): ?>
                            <div class="pt-3 border-t border-emerald-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                                <a href="/paint_inventory" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-900 transition-colors font-medium">
                                    View Paint Shelf →
                                </a>
                                <button id="haul-intake-btn" type="button" onclick="openShelfIntakeFromCurrent()"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 rounded-lg transition-all shadow-2xs">
                                    🎨 Add to Paint Shelf
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($stats['total_items'] > 0): ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php
                $id = 'priorityChart';
                $title = 'Priority Distribution';
                include '../components/charts/chart_canvas.php';

                $id = 'brandChart';
                $title = 'Brand Distribution';
                include '../components/charts/chart_canvas.php';
                ?>
            </div>

            <?php include '../components/charts/init_charts.php'; ?>
            <script>
            (function() {
                const priorityData = <?= json_encode($stats['priority_counts'], JSON_HEX_TAG) ?>;
                const brandData = <?= json_encode($stats['brand_counts'], JSON_HEX_TAG) ?>;

                initDoughnutChart('priorityChart', Object.keys(priorityData), Object.values(priorityData));
                initDoughnutChart('brandChart', Object.keys(brandData), Object.values(brandData));
            })();
            </script>
            <?php endif; ?>

            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xl font-bold text-gray-700">🎯 Current Wishlist</h3>
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
                               placeholder="Paint name..."
                               class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Brand</label>
                        <select name="filter_brand" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Brands</option>
                            <?php foreach($paint_brands as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= (isset($_GET['filter_brand']) && $_GET['filter_brand'] == $b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Paint Type</label>
                        <select name="filter_painttype" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Types</option>
                            <?php foreach($paint_types as $pt): ?>
                                <option value="<?= $pt['id'] ?>" <?= (isset($_GET['filter_painttype']) && $_GET['filter_painttype'] == $pt['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pt['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Priority</label>
                        <select name="filter_priority" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Priorities</option>
                            <?php foreach($priorities as $priority): ?>
                                <option value="<?= $priority['id'] ?>" <?= (isset($_GET['filter_priority']) && $_GET['filter_priority'] == $priority['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($priority['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Obtained Status</label>
                        <select name="filter_obtained" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Items</option>
                            <option value="unobtained" <?= (isset($_GET['filter_obtained']) && $_GET['filter_obtained'] == 'unobtained') ? 'selected' : '' ?>>Unobtained Only</option>
                            <option value="obtained" <?= (isset($_GET['filter_obtained']) && $_GET['filter_obtained'] == 'obtained') ? 'selected' : '' ?>>Obtained Only</option>
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
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'number')">ID</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Paint Name</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Brand</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Paint Type</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 4, 'text')">Priority</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 5, 'text')">Obtained</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Link</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($items) > 0): ?>

                            <?php foreach ($items as $row): ?>
                                <?php
                                    $safe_name = htmlspecialchars($row['name'] ?? '', ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                                    $safe_link = htmlspecialchars($row['link'] ?? '', ENT_QUOTES);
                                    $brand_class = get_brand_color_palette($row['brandid']);
                                    $type_class = get_brand_color_palette($row['painttypeid']);

                                    $priority_class = match($row['priority'] ?? '') {
                                        'High' => 'bg-red-100 text-red-800',
                                        'Mid' => 'bg-yellow-100 text-yellow-800',
                                        'Low' => 'bg-green-100 text-green-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100'>
                                    <td data-label="ID" class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'><?= e($row['id']) ?></td>
                                    <td data-label="Paint Name" class='px-4 py-3 text-sm font-semibold text-gray-800'><?= $safe_name ?></td>
                                    <td data-label="Brand" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <?php if (!empty($row['brand'])): ?>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                            <?= e($row['brand']) ?>
                                        </span>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td data-label="Paint Type" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <?php if (!empty($row['painttype'])): ?>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $type_class ?>">
                                            <?= e($row['painttype']) ?>
                                        </span>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td data-label="Priority" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <?php if (!empty($row['priority'])): ?>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $priority_class ?>">
                                            <?= e($row['priority']) ?>
                                        </span>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td data-label="Obtained" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= e($row['obtained']) ?></td>
                                    <td data-label="Link" class='px-4 py-3 text-sm text-gray-600'>
                                        <?php if (!empty($row['link'])): ?>
                                            <a href="<?= $safe_link ?>" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline">🔗 Link</a>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600 max-w-[150px] truncate' title='<?= $safe_notes ?>'><?= $safe_notes ?></td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
                                                data-id='<?= $row['actualid'] ?>'
                                                data-name='<?= $safe_name ?>'
                                                data-brand='<?= $row['brandid'] ?? '' ?>'
                                                data-painttype='<?= $row['painttypeid'] ?? '' ?>'
                                                data-priority='<?= $row['priorityid'] ?? '' ?>'
                                                data-obtained='<?= $row['obtainedid'] ?>'
                                                data-link='<?= $safe_link ?>'
                                                data-notes='<?= $safe_notes ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>

                                            <?php if ($row['obtainedid'] == 0): ?>
                                            <form method='POST' class='inline' onsubmit="return confirm('Mark ' + <?= json_encode($row['name']) ?> + ' as obtained?');">
                                                <input type='hidden' name='obtainedid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-green-100 rounded text-lg' title='Mark Obtained'>✅</button>
                                            </form>
                                            <?php else: ?>
                                            <button type='button' class='p-1 hover:bg-blue-100 rounded text-lg' title='Register to Paint Shelf'
                                                onclick='openShelfIntakeModal({
                                                    id: <?= (int)$row['actualid'] ?>,
                                                    name: <?= json_encode($row['name']) ?>,
                                                    brandid: <?= (int)($row['brandid'] ?? 0) ?>,
                                                    painttypeid: <?= (int)($row['painttypeid'] ?? 0) ?>,
                                                    notes: <?= json_encode($row['notes'] ?? '') ?>
                                                })'>
                                                🎨
                                            </button>
                                            <?php endif; ?>

                                            <form method='POST' class='inline' onsubmit="return confirm('Delete this wishlist item?');">
                                                <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='9' class='text-center py-6 text-gray-500'>No paints in your wishlist yet! Start adding some.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <button onclick="openAddModal()"
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Add to Wishlist">
        +
    </button>

    <?php $mode = 'add'; include '../components/paint_wishlist_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/paint_wishlist_modal.php'; ?>

    <!-- Paint Shelf Intake Modal (Bridge from Wishlist to Paint Inventory) -->
    <div id="shelfIntakeModal" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-md p-6 modal-animate relative">
            <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="closeShelfIntakeModal()">&times;</span>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">🎨</span>
                <h2 class="text-xl font-bold text-gray-800">Register to Paint Shelf</h2>
            </div>
            <p class="text-xs text-gray-500 mb-4">Transfer this acquired paint directly into your active paint inventory.</p>

            <form method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="register_to_shelf">
                <input type="hidden" name="from_wishlist_id" id="shelf_intake_wishlist_id">

                <div>
                    <label class="block text-sm font-semibold text-gray-600">Paint Name:</label>
                    <input type="text" name="name" id="shelf_intake_name" required
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Brand:</label>
                        <select name="brand" id="shelf_intake_brand" required class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">-- Select --</option>
                            <?php foreach($paint_brands as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Paint Type:</label>
                        <select name="painttype" id="shelf_intake_painttype" required class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">-- Select --</option>
                            <?php foreach($paint_types as $pt): ?>
                                <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Bottle Amount:</label>
                        <select name="amount" id="shelf_intake_amount" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <?php foreach($amount_levels as $amt): ?>
                                <option value="<?= $amt['id'] ?>" <?= ($amt['id'] == $default_amount_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($amt['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Thinned Status:</label>
                        <select name="thinned" id="shelf_intake_thinned" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <?php foreach($thinned_statuses as $th): ?>
                                <option value="<?= $th['id'] ?>" <?= ($th['id'] == $default_thinned_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($th['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Finish (Optional):</label>
                        <select name="finishid" id="shelf_intake_finish" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">-- None / Unknown --</option>
                            <?php foreach($paint_finishes as $f): ?>
                                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Color Hex (Optional):</label>
                        <div class="flex items-center gap-2 mt-1">
                            <input type="color" id="shelf_intake_color_picker" value="#3b82f6"
                                   oninput="document.getElementById('shelf_intake_color_hex').value = this.value"
                                   class="w-10 h-9 p-0.5 border border-gray-300 rounded cursor-pointer">
                            <input type="text" name="color_hex" id="shelf_intake_color_hex" placeholder="#3b82f6"
                                   oninput="if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('shelf_intake_color_picker').value = this.value;"
                                   class="w-full p-2 border border-gray-300 rounded text-xs font-mono focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                    <textarea name="notes" id="shelf_intake_notes" rows="2" placeholder="Storage location, project notes..." class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeShelfIntakeModal()" class="flex-1 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors border border-gray-200">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-sm">
                        🚀 Add to Paint Shelf
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.getElementById('addModal').style.display = 'flex';
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.getElementById('addModal').style.display = 'none';
        }

        function openEditModal(button) {
            document.getElementById('modal_id').value = button.getAttribute('data-id');
            document.getElementById('modal_name').value = button.getAttribute('data-name');
            document.getElementById('modal_brand').value = button.getAttribute('data-brand');
            document.getElementById('modal_painttype').value = button.getAttribute('data-painttype');
            document.getElementById('modal_priority').value = button.getAttribute('data-priority');
            document.getElementById('modal_obtained').value = button.getAttribute('data-obtained');
            document.getElementById('modal_link').value = button.getAttribute('data-link');
            document.getElementById('modal_notes').value = button.getAttribute('data-notes');
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').style.display = 'none';
        }

        // Supply Command Datasets
        const targetPool = <?= json_encode(array_values(array_map(fn($p) => [
            'actualid' => (int)$p['actualid'],
            'name' => $p['name'],
            'brand' => $p['brand'] ?? '',
            'brandid' => (int)($p['brandid'] ?? 0),
            'brand_badge' => get_brand_color_palette($p['brandid']),
            'painttype' => $p['painttype'] ?? '',
            'painttypeid' => (int)($p['painttypeid'] ?? 0),
            'type_badge' => get_brand_color_palette($p['painttypeid']),
            'priority' => $p['priority'] ?? 'Mid',
            'priority_class' => match($p['priority'] ?? '') {
                'High' => 'bg-red-100 text-red-800',
                'Mid' => 'bg-yellow-100 text-yellow-800',
                'Low' => 'bg-green-100 text-green-800',
                default => 'bg-gray-100 text-gray-800'
            },
            'link' => $p['link'] ?? '',
            'notes' => $p['notes'] ?? ''
        ], $target_pool)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const obtainedHauls = <?= json_encode(array_values(array_map(fn($p) => [
            'actualid' => (int)$p['actualid'],
            'name' => $p['name'],
            'brand' => $p['brand'] ?? '',
            'brandid' => (int)($p['brandid'] ?? 0),
            'brand_badge' => get_brand_color_palette($p['brandid']),
            'painttype' => $p['painttype'] ?? '',
            'painttypeid' => (int)($p['painttypeid'] ?? 0),
            'type_badge' => get_brand_color_palette($p['painttypeid']),
            'notes' => $p['notes'] ?? ''
        ], $obtained_items)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        let currentTargetIdx = 0;
        let currentHaulIdx = 0;

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function stepTarget(delta) {
            if (!targetPool || targetPool.length === 0) return;
            currentTargetIdx = (currentTargetIdx + delta + targetPool.length) % targetPool.length;
            renderTarget();
        }

        function renderTarget() {
            const item = targetPool[currentTargetIdx];
            if (!item) return;

            const counter = document.getElementById('target-stepper-counter');
            if (counter) counter.textContent = `${currentTargetIdx + 1} of ${targetPool.length}`;

            const formId = document.getElementById('target-form-obtained-id');
            if (formId) formId.value = item.actualid;

            const container = document.getElementById('target-card-content');
            if (container) {
                const notesHtml = (item.notes && item.notes !== '-') ? 
                    `<div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-amber-100 rounded-lg p-2.5 italic">📝 ${escapeHtml(item.notes)}</div>` : '';

                const brandPill = item.brand ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span>` : '';
                const typePill = item.painttype ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.type_badge}">${escapeHtml(item.painttype)}</span>` : '';

                container.innerHTML = `
                    <div class="mb-4">
                        <h4 class="text-lg font-bold text-gray-900 leading-snug">${escapeHtml(item.name)}</h4>
                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                            ${brandPill}
                            ${typePill}
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full ${item.priority_class}">
                                ${escapeHtml(item.priority)} Priority
                            </span>
                        </div>
                        ${notesHtml}
                    </div>
                `;
            }

            const storeContainer = document.getElementById('target-store-link-container');
            if (storeContainer) {
                if (item.link) {
                    storeContainer.innerHTML = `
                        <a href="${escapeHtml(item.link)}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 bg-white hover:bg-blue-50 border border-blue-200 rounded-lg transition-colors shadow-2xs">
                            <span>🔗</span> Open Store
                        </a>
                    `;
                } else {
                    storeContainer.innerHTML = '<span class="text-xs text-gray-400 italic">No store link provided</span>';
                }
            }
        }

        function stepHaul(delta) {
            if (!obtainedHauls || obtainedHauls.length === 0) return;
            currentHaulIdx = (currentHaulIdx + delta + obtainedHauls.length) % obtainedHauls.length;
            renderHaul();
        }

        function renderHaul() {
            const item = obtainedHauls[currentHaulIdx];
            if (!item) return;

            const counter = document.getElementById('haul-stepper-counter');
            if (counter) counter.textContent = `${currentHaulIdx + 1} of ${obtainedHauls.length}`;

            const container = document.getElementById('haul-card-content');
            if (container) {
                const notesHtml = (item.notes && item.notes !== '-') ? 
                    `<div class="mt-2 text-xs text-gray-600 bg-white/80 border border-emerald-100 rounded-lg p-2 italic truncate" title="${escapeHtml(item.notes)}">📝 ${escapeHtml(item.notes)}</div>` : '';

                const brandPill = item.brand ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span>` : '';
                const typePill = item.painttype ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.type_badge}">${escapeHtml(item.painttype)}</span>` : '';

                container.innerHTML = `
                    <div class="mb-2">
                        <h4 class="text-base font-bold text-gray-900 leading-snug">${escapeHtml(item.name)}</h4>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            ${brandPill}
                            ${typePill}
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">
                                ✅ Secured
                            </span>
                        </div>
                        ${notesHtml}
                    </div>
                `;
            }
        }

        function copySupplyList() {
            const unobtainedList = <?= json_encode(array_map(fn($p) => [
                'name' => $p['name'],
                'brand' => $p['brand'] ?? 'Unknown',
                'painttype' => $p['painttype'] ?? '',
                'priority' => $p['priority'] ?? 'Normal',
                'link' => $p['link'] ?? ''
            ], $unobtained_items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

            if (!unobtainedList || unobtainedList.length === 0) {
                alert("Your wishlist is currently fully obtained! No items to purchase.");
                return;
            }

            let text = "🛒 Gunpla Workshop - Paint Shopping List:\n";
            unobtainedList.forEach(p => {
                text += `• [${p.brand}] ${p.name}` + (p.painttype ? ` (${p.painttype})` : '') + ` - Priority: ${p.priority}`;
                if (p.link) text += ` [🔗 ${p.link}]`;
                text += "\n";
            });

            navigator.clipboard.writeText(text).then(() => {
                const btnText = document.getElementById('copy-supply-btn-text');
                if (btnText) {
                    const original = btnText.innerText;
                    btnText.innerText = `Copied (${unobtainedList.length})!`;
                    setTimeout(() => { btnText.innerText = original; }, 2500);
                }
            }).catch(err => {
                console.error("Clipboard copy failed:", err);
                alert("Paint Shopping List:\n\n" + text);
            });
        }

        function openShelfIntakeModal(data) {
            document.getElementById('shelf_intake_wishlist_id').value = data.id || '';
            document.getElementById('shelf_intake_name').value = data.name || '';
            document.getElementById('shelf_intake_brand').value = data.brandid || '';
            document.getElementById('shelf_intake_painttype').value = data.painttypeid || '';
            document.getElementById('shelf_intake_notes').value = (data.notes && data.notes !== '-') ? data.notes : '';
            document.getElementById('shelfIntakeModal').classList.remove('hidden');
            document.getElementById('shelfIntakeModal').style.display = 'flex';
        }

        function closeShelfIntakeModal() {
            document.getElementById('shelfIntakeModal').classList.add('hidden');
            document.getElementById('shelfIntakeModal').style.display = 'none';
        }

        function openShelfIntakeFromCurrent() {
            if (!obtainedHauls || obtainedHauls.length === 0) return;
            const item = obtainedHauls[currentHaulIdx];
            if (item) {
                openShelfIntakeModal({
                    id: item.actualid,
                    name: item.name,
                    brandid: item.brandid,
                    painttypeid: item.painttypeid,
                    notes: item.notes
                });
            }
        }
    </script>

    <script>initScrollRestore('paint_wishlist_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
