<?php
require '../includes/bootstrap.php';
require_once '../includes/functions/backlog.php';

$current_section = 'kits';
$current_page = 'backlog';
$page_title = 'Backlog Plan';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $action_success = delete_backlog_item($conn, $_POST['deleteid']);
        $msg_text = $action_success ? "✅ Successfully Deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $action_success = add_backlog_item($conn, $_POST);
        $msg_text = $action_success ? "✅ Successfully added to backlog" : "❌ Error adding item";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        $action_success = update_backlog_item($conn, $_POST);
        $msg_text = $action_success ? "✅ Item updated successfully" : "❌ Error updating item";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /backlog");
        exit;
    }
}

$message = get_flash_message();

$statuses = get_backlog_statuses($conn);
$buildplans = get_backlog_buildplans($conn);
$inventory_kits = get_inventory_kits($conn);
$items = get_backlog_items($conn, $_GET);

$stats = calculate_backlog_stats($items);

$has_filters = !empty($_GET['filter_status']) || !empty($_GET['search']) || !empty($_GET['filter_buildplan']);

?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full"> <h1 class="page-title font-bold text-gray-700 text-center mb-8">🚧 Backlog Plan</h1>
            
            <?php include '../components/toast.php'; ?>

            <div class="mb-8">
                <h3 class="text-lg font-bold text-gray-700 mb-3">🔨 Currently In Progress</h3>
                <?php 
                $in_progress = array_filter($items, fn($item) => ($item['status_label'] ?? '') === 'In Progress');
                ?>
                <?php if (!empty($in_progress)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($in_progress as $ip_item): ?>
                    <a href="/build_progress?filter_backlog=<?= $ip_item['actualid'] ?? '' ?>" 
                       class="bg-white border border-blue-200 rounded-lg p-3 flex items-center gap-3 shadow-sm hover:border-blue-400 hover:shadow-md transition-all">
                        <div class="w-2 h-10 bg-blue-500 rounded-full flex-shrink-0"></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($ip_item['name'] ?? '-') ?></p>
                            <p class="text-xs text-gray-500">
                                <?= htmlspecialchars($ip_item['buildplan_label'] ?? 'No plan') ?>
                            </p>
                        </div>
                        <span class="text-gray-400 text-sm flex-shrink-0">→</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center text-sm text-gray-500">
                    No kits currently in progress. Pick one from the backlog below! 🚀
                </div>
                <?php endif; ?>
            </div>

            <?php if ($stats['total_items'] > 0): ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php
                $id = 'statusChart';
                $title = 'Status Distribution';
                include '../components/charts/chart_canvas.php';

                $id = 'buildplanChart';
                $title = 'Build Plan Distribution';
                include '../components/charts/chart_canvas.php';
                ?>
            </div>

            <?php include '../components/charts/init_charts.php'; ?>
            <script>
            (function() {
                const statusData = <?= json_encode($stats['status_counts'], JSON_HEX_TAG) ?>;
                const buildplanData = <?= json_encode($stats['buildplan_counts'], JSON_HEX_TAG) ?>;
                
                initDoughnutChart('statusChart', Object.keys(statusData), Object.values(statusData));
                initDoughnutChart('buildplanChart', Object.keys(buildplanData), Object.values(buildplanData));
            })();
            </script>
            <?php endif; ?>

            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xl font-bold text-gray-700">📋 Current Backlog</h3>
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
                        <label class="block text-xs font-bold text-gray-500 uppercase">Filter Status</label>
                        <select name="filter_status" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Status</option>
                            <?php foreach($statuses as $status): ?>
                                <option value="<?= $status['id'] ?>" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == $status['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Filter Build Plan</label>
                        <select name="filter_buildplan" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Build Plans</option>
                            <?php foreach($buildplans as $bp): ?>
                                <option value="<?= $bp['id'] ?>" <?= (isset($_GET['filter_buildplan']) && $_GET['filter_buildplan'] == $bp['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bp['label']) ?>
                                </option>
                            <?php endforeach; ?>
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
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Build Plan</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">References</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($items) > 0): ?>

                            <?php foreach ($items as $row): ?>
                                <?php 
                                    $safe_name = htmlspecialchars($row['name'] ?? '-', ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                                    $safe_refs = htmlspecialchars($row['references'] ?? '', ENT_QUOTES);

                                    $status_class = get_brand_color_palette((int)($row['status'] ?? 0));
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100'>
                                    <td data-label="ID" class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'><?= $row['backlogid'] ?? '' ?></td>
                                    <td data-label="Kit Name" class='px-4 py-3 text-sm font-semibold text-gray-800'>
                                        <a href="/kit/<?= $row['inventoryid'] ?>" class="text-blue-600 hover:underline"><?= $safe_name ?></a>
                                    </td>
                                    <td data-label="Build Plan" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <?php if (!empty($row['buildplan_label'])): 
                                            $bp_colors = [
                                                'Clean Build'      => 'bg-green-100 text-green-800',
                                                'Custom Build'     => 'bg-purple-100 text-purple-800',
                                                'Painted Build'    => 'bg-orange-100 text-orange-800',
                                                'Full Build'       => 'bg-red-100 text-red-800',
                                                'Kitbash Material' => 'bg-yellow-100 text-yellow-800',
                                            ];
                                            $bp_class = $bp_colors[$row['buildplan_label']] ?? 'bg-blue-100 text-blue-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $bp_class ?>">
                                            <?= htmlspecialchars($row['buildplan_label']) ?>
                                        </span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <?php if (!empty($row['status_label'])): ?>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $status_class ?>">
                                            <?= htmlspecialchars($row['status_label']) ?>
                                        </span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600'><?= $safe_notes ?></td>
                                    <td data-label="References" class='px-4 py-3 text-sm text-gray-600'>
                                        <?php if (!empty($row['references'])): ?>
                                            <a href="<?= $safe_refs ?>" target="_blank" class="text-blue-500 hover:underline">🔗 Link</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
                                                data-id='<?= $row['actualid'] ?>'
                                                data-inventoryid='<?= $row['inventoryid'] ?>'
                                                data-buildplanid='<?= $row['buildplanid'] ?>'
                                                data-status='<?= $row['status'] ?>'
                                                data-notes='<?= $safe_notes ?>'
                                                data-references='<?= $safe_refs ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>
                                            
                                            <form method='POST' class='inline' onsubmit='return confirm("Delete this item?");'>
                                                <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='7' class='text-center py-6 text-gray-500'>No items in your backlog yet! Start adding!</td></tr>
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

    <?php $mode = 'add'; include '../components/backlog_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/backlog_modal.php'; ?>

    <script>
        function openEditModal(button) {
            const id = button.getAttribute('data-id');
            const inventoryid = button.getAttribute('data-inventoryid');
            const buildplanid = button.getAttribute('data-buildplanid');
            const status = button.getAttribute('data-status');
            const notes = button.getAttribute('data-notes');
            const references = button.getAttribute('data-references');

            document.getElementById('modal_id').value = id;
            document.getElementById('modal_inventoryid').value = inventoryid;
            document.getElementById('modal_buildplanid').value = buildplanid;
            document.getElementById('modal_status').value = status;
            document.getElementById('modal_notes').value = notes;
            document.getElementById('modal_references').value = references;
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex'; 
        }
    </script>

    <script>initScrollRestore('backlog_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
