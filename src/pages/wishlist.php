<?php
require '../includes/bootstrap.php';
$current_section = 'kits';
$current_page = 'wishlist';
$page_title = 'Wishlist';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $action_success = delete_wishlist_item($conn, $_POST['deleteid']);
        $msg_text = $action_success ? "✅ Successfully Deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['obtainedid'])) {
        $action_success = mark_wishlist_obtained($conn, $_POST['obtainedid']);
        $msg_text = $action_success ? "✅ Marked as Obtained" : "❌ Failed to mark as obtained";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $action_success = add_wishlist_item($conn, $_POST);
        $msg_text = $action_success ? "✅ Successfully added " . htmlspecialchars($_POST["kit_name"]) : "❌ Error adding item";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        $action_success = update_wishlist_item($conn, $_POST);
        $msg_text = $action_success ? "✅ Item updated successfully" : "❌ Error updating item";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'register_to_hangar') {
        $action_success = add_kit($conn, $_POST);
        if ($action_success && !empty($_POST['from_wishlist_id'])) {
            mark_wishlist_obtained($conn, (int)$_POST['from_wishlist_id']);
        }
        $msg_text = $action_success ? "✅ Successfully inducted " . htmlspecialchars($_POST["kit_name"]) . " into your Hangar!" : "❌ Error adding kit to hangar";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /wishlist");
        exit;
    }
}



$message = get_flash_message();


$brands = get_brands($conn);
$priorities = get_wishlist_priorities($conn);
$statuses = get_statuses($conn);
$items = get_wishlist_items($conn, $_GET);

$stats = calculate_wishlist_stats($items);

$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['search']) || !empty($_GET['filter_priority']) || !empty($_GET['filter_obtained']);

// Workbench Hub Dataset
$all_items = $has_filters ? get_wishlist_items($conn) : $items;
$unobtained_items = array_values(array_filter($all_items, fn($i) => (int)$i['obtainedid'] === 0));
$high_priority_targets = array_values(array_filter($unobtained_items, fn($i) => strtolower(trim($i['priority'] ?? '')) === 'high'));
$target_pool = !empty($high_priority_targets) ? $high_priority_targets : $unobtained_items;
$top_target = !empty($target_pool) ? $target_pool[0] : null;

$obtained_items = array_values(array_filter($all_items, fn($i) => (int)$i['obtainedid'] === 1));
$latest_haul = !empty($obtained_items) ? $obtained_items[0] : null;

$not_started_status_id = null;
foreach ($statuses as $st) {
    if (strtolower(trim($st['label'])) === 'not started') {
        $not_started_status_id = (int)$st['id'];
        break;
    }
}
if (!$not_started_status_id && !empty($statuses)) {
    $not_started_status_id = (int)$statuses[0]['id'];
}
?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full"> <h1 class="page-title font-bold text-gray-700 text-center mb-8">✨ Wishlist</h1>
            
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

            <!-- Hunter's Hub: Top Target Spotlight & Recent Haul Intake -->
            <div class="mb-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Panel 1: Top Target / Grail Spotlight -->
                    <div id="target-panel" class="bg-gradient-to-br from-white to-rose-50/40 border-2 border-rose-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                    <span class="inline-block w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                    🎯 Top Target
                                </span>
                                <?php if (count($target_pool) > 1): ?>
                                    <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                        <button type="button" onclick="stepTarget(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Target">◀</button>
                                        <span id="target-stepper-counter">1 of <?= count($target_pool) ?></span>
                                        <button type="button" onclick="stepTarget(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Target">▶</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-rose-700 font-medium mb-3 block">
                                <?= !empty($high_priority_targets) ? count($high_priority_targets) . ' high priority targets queued' : count($unobtained_items) . ' unobtained kits on wishlist' ?>
                            </span>

                            <div id="target-card-content">
                                <?php if ($top_target): ?>
                                    <?php 
                                        $top_priority_class = match($top_target['priority']) {
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
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($top_target['brandid']) ?>">
                                                <?= htmlspecialchars($top_target['brand']) ?>
                                            </span>
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $top_priority_class ?>">
                                                <?= htmlspecialchars($top_target['priority']) ?> Priority
                                            </span>
                                        </div>
                                        <?php if (!empty($top_target['notes']) && $top_target['notes'] !== '-'): ?>
                                            <div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-rose-100 rounded-lg p-2.5 italic">
                                                📝 <?= htmlspecialchars($top_target['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="py-6 text-center">
                                        <div class="text-3xl mb-2">🎉</div>
                                        <p class="text-sm font-semibold text-gray-700">Wishlist Targets Conquered!</p>
                                        <p class="text-xs text-gray-500 mt-1">All kits on your wishlist have been obtained. Click + below to add new targets!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($top_target): ?>
                            <div class="pt-3 border-t border-rose-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                                <div id="target-store-link-container">
                                    <?php if (!empty($top_target['link'])): ?>
                                        <a id="target-store-link" href="<?= htmlspecialchars($top_target['link']) ?>" target="_blank" rel="noopener noreferrer" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg transition-colors">
                                            🔗 Check Store
                                        </a>
                                    <?php else: ?>
                                        <span id="target-store-link" class="text-xs text-gray-400 italic">No store link</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" onsubmit="return confirm('Mark this kit as obtained?');">
                                    <input type="hidden" name="obtainedid" id="target-form-obtained-id" value="<?= $top_target['actualid'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-800 border border-emerald-300 rounded-lg transition-all shadow-2xs">
                                        ✅ Got It!
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 2: Recent Haul & Hangar Intake -->
                    <div id="haul-panel" class="bg-gradient-to-br from-white to-emerald-50/40 border-2 border-emerald-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    🎉 Recent Haul Intake
                                </span>
                                <?php if (count($obtained_items) > 1): ?>
                                    <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                        <button type="button" onclick="stepHaul(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Haul">◀</button>
                                        <span id="haul-stepper-counter">1 of <?= count($obtained_items) ?></span>
                                        <button type="button" onclick="stepHaul(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Haul">▶</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-emerald-700 font-medium mb-3 block">
                                <?= count($obtained_items) ?> kits secured from wishlist
                            </span>

                            <div id="haul-card-content">
                                <?php if ($latest_haul): ?>
                                    <div class="mb-4">
                                        <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                            <?= htmlspecialchars($latest_haul['name']) ?>
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($latest_haul['brandid']) ?>">
                                                <?= htmlspecialchars($latest_haul['brand']) ?>
                                            </span>
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">
                                                ✅ Secured
                                            </span>
                                        </div>
                                        <?php if (!empty($latest_haul['notes']) && $latest_haul['notes'] !== '-'): ?>
                                            <div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-emerald-100 rounded-lg p-2.5 italic">
                                                📝 <?= htmlspecialchars($latest_haul['notes']) ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="mt-2 text-xs text-gray-500">
                                                Claimed! Ready to be registered into your hangar inventory.
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="py-6 text-center">
                                        <div class="text-3xl mb-2">📦</div>
                                        <p class="text-sm font-semibold text-gray-700">No Hauls Claimed Yet!</p>
                                        <p class="text-xs text-gray-500 mt-1">When you hunt down a kit and click "Got It!", it will appear here ready to induct into your active hangar.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($latest_haul): ?>
                            <div class="pt-3 border-t border-emerald-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                                <a href="/inventory" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-gray-500 hover:text-gray-900 transition-colors font-medium">
                                    View Inventory →
                                </a>
                                <button id="haul-intake-btn" type="button" onclick="openHangarIntakeFromCurrent()" 
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 rounded-lg transition-all shadow-2xs">
                                    📦 Register to Inventory
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
                               placeholder="Kit name or ID..." 
                               class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Filter Brand</label>
                        <select name="filter_brand" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Brands</option>
                            <?php foreach($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>" <?= (isset($_GET['filter_brand']) && $_GET['filter_brand'] == $brand['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Filter Priority</label>
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
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Kit Name</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Brand</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Priority</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 4, 'text')">Obtained</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Link</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($items) > 0): ?>

                            <?php foreach ($items as $row): ?>
                                <?php 
                                    $safe_name = htmlspecialchars($row['name'], ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                                    $safe_link = htmlspecialchars($row['link'] ?? '', ENT_QUOTES);
                                    $brand_class = get_brand_color_palette($row['brandid']);
                                    
                                    $priority_class = match($row['priority']) {
                                        'High' => 'bg-red-100 text-red-800',
                                        'Mid' => 'bg-yellow-100 text-yellow-800',
                                        'Low' => 'bg-green-100 text-green-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100'>
                                    <td data-label="ID" class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'><?= e($row['id']) ?></td>
                                    <td data-label="Kit Name" class='px-4 py-3 text-sm font-semibold text-gray-800'><?= e($row['name']) ?></td>
                                    <td data-label="Brand" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                            <?= e($row['brand']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Priority" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <?php if (!empty($row['priority'])): ?>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $priority_class ?>">
                                            <?= e($row['priority']) ?>
                                        </span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Obtained" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= e($row['obtained']) ?></td>
                                    <td data-label="Link" class='px-4 py-3 text-sm text-gray-600'>
                                        <?php if (!empty($row['link'])): ?>
                                            <a href="<?= $safe_link ?>" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline">🔗 Link</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600'><?= $safe_notes ?></td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
                                                data-id='<?= $row['actualid'] ?>'
                                                data-name='<?= $safe_name ?>'
                                                data-brand='<?= $row['brandid'] ?>'
                                                data-priority='<?= $row['priorityid'] ?>'
                                                data-obtained='<?= $row['obtainedid'] ?>'
                                                data-link='<?= $safe_link ?>'
                                                data-notes='<?= e($row['notes']) ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>
                                            
                                            <?php if ($row['obtainedid'] == 0): ?>
                                            <form method='POST' class='inline' onsubmit='return confirm("Mark <?= $safe_name ?> as obtained?");'>
                                                <input type='hidden' name='obtainedid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-green-100 rounded text-lg' title='Mark Obtained'>✅</button>
                                            </form>
                                            <?php endif; ?>

                                            <form method='POST' class='inline' onsubmit='return confirm("Delete this item?");'>
                                                <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='8' class='text-center py-6 text-gray-500'>No items in your wishlist yet! Start adding!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <button onclick="openAddModal()" 
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Add New Item">
        +
    </button>

    <?php $mode = 'add'; include '../components/wishlist_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/wishlist_modal.php'; ?> 

    <!-- Hangar Intake Modal (Bridge from Wishlist to Inventory) -->
    <div id="hangarIntakeModal" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
        <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-md p-6 modal-animate relative">
            <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="closeHangarIntakeModal()">&times;</span>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl">📦</span>
                <h2 class="text-xl font-bold text-gray-800">Register to Hangar</h2>
            </div>
            <p class="text-xs text-gray-500 mb-4">Transfer this acquired kit into your active kit inventory.</p>
            
            <form method="post" class="space-y-4">
                <input type="hidden" name="action_type" value="register_to_hangar">
                <input type="hidden" name="from_wishlist_id" id="intake_wishlist_id">
                
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Kit Name:</label>
                    <input type="text" name="kit_name" id="intake_kit_name" required 
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Brand:</label>
                        <select name="brandid" id="intake_brand" required class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">-- Select --</option>
                            <?php foreach($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Initial Status:</label>
                        <select name="statusid" id="intake_status" required class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <?php foreach($statuses as $status): ?>
                                <option value="<?= $status['id'] ?>" <?= ($status['id'] == $not_started_status_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Date Bought:</label>
                        <input type="date" name="datebought" id="intake_date" value="<?= date('Y-m-d') ?>" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600">Price (IDR):</label>
                        <input type="number" step="1" name="pricebought" id="intake_price" placeholder="150000" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                    <textarea name="notes" id="intake_notes" rows="2" placeholder="Details or purchase notes..." class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="closeHangarIntakeModal()" class="flex-1 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors border border-gray-200">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-sm">
                        🚀 Add to Hangar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(button) {
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const brand = button.getAttribute('data-brand');
            const priority = button.getAttribute('data-priority');
            const obtained = button.getAttribute('data-obtained');
            const link = button.getAttribute('data-link');
            const notes = button.getAttribute('data-notes');

            document.getElementById('modal_id').value = id;
            document.getElementById('modal_name').value = name;
            document.getElementById('modal_brand').value = brand;   
            document.getElementById('modal_priority').value = priority;
            document.getElementById('modal_obtained').value = obtained;
            document.getElementById('modal_link').value = link;
            document.getElementById('modal_notes').value = notes;
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex'; 
        }

        // Workbench Datasets
        const targetPool = <?= json_encode(array_values(array_map(fn($k) => [
            'actualid' => $k['actualid'],
            'name' => $k['name'],
            'brand' => $k['brand'],
            'brandid' => $k['brandid'],
            'brand_badge' => get_brand_color_palette($k['brandid']),
            'priority' => $k['priority'],
            'priority_class' => match($k['priority']) {
                'High' => 'bg-red-100 text-red-800',
                'Mid' => 'bg-yellow-100 text-yellow-800',
                'Low' => 'bg-green-100 text-green-800',
                default => 'bg-gray-100 text-gray-800'
            },
            'link' => $k['link'] ?? '',
            'notes' => $k['notes'] ?? ''
        ], $target_pool)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const obtainedHauls = <?= json_encode(array_values(array_map(fn($k) => [
            'actualid' => $k['actualid'],
            'name' => $k['name'],
            'brand' => $k['brand'],
            'brandid' => $k['brandid'],
            'brand_badge' => get_brand_color_palette($k['brandid']),
            'notes' => $k['notes'] ?? ''
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
                    `<div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-rose-100 rounded-lg p-2.5 italic">📝 ${escapeHtml(item.notes)}</div>` : '';
                container.innerHTML = `
                    <div class="mb-4">
                        <h4 class="text-lg font-bold text-gray-900 leading-snug">${escapeHtml(item.name)}</h4>
                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full ${item.priority_class}">${escapeHtml(item.priority)} Priority</span>
                        </div>
                        ${notesHtml}
                    </div>`;
            }

            const linkContainer = document.getElementById('target-store-link-container');
            if (linkContainer) {
                if (item.link) {
                    linkContainer.innerHTML = `<a id="target-store-link" href="${escapeHtml(item.link)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg transition-colors">🔗 Check Store</a>`;
                } else {
                    linkContainer.innerHTML = `<span id="target-store-link" class="text-xs text-gray-400 italic">No store link</span>`;
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
                    `<div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-emerald-100 rounded-lg p-2.5 italic">📝 ${escapeHtml(item.notes)}</div>` : 
                    `<p class="mt-2 text-xs text-gray-500">Claimed! Ready to be registered into your hangar inventory.</p>`;
                container.innerHTML = `
                    <div class="mb-4">
                        <h4 class="text-lg font-bold text-gray-900 leading-snug">${escapeHtml(item.name)}</h4>
                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">✅ Secured</span>
                        </div>
                        ${notesHtml}
                    </div>`;
            }
        }

        function openHangarIntakeFromCurrent() {
            if (!obtainedHauls || obtainedHauls.length === 0) return;
            const item = obtainedHauls[currentHaulIdx];
            if (!item) return;

            document.getElementById('intake_wishlist_id').value = item.actualid;
            document.getElementById('intake_kit_name').value = item.name;
            document.getElementById('intake_brand').value = item.brandid;
            document.getElementById('intake_notes').value = (item.notes && item.notes !== '-') ? item.notes : '';
            
            const modal = document.getElementById('hangarIntakeModal');
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        }

        function closeHangarIntakeModal() {
            const modal = document.getElementById('hangarIntakeModal');
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    </script>

    <script>initScrollRestore('wishlist_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
