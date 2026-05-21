<?php
require '../includes/bootstrap.php';
require_once '../includes/functions/wishlist.php';

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

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /wishlist");
        exit;
    }
}



$message = get_flash_message();


$brands = get_brands($conn);
$priorities = get_wishlist_priorities($conn);
$items = get_wishlist_items($conn, $_GET);

$stats = calculate_wishlist_stats($items);


$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['search']) || !empty($_GET['filter_priority']) || !empty($_GET['filter_obtained']);

?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-5xl mx-auto w-full"> <h1 class="page-title font-bold text-gray-700 text-center mb-8">✨ Wishlist</h1>
            
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
                const priorityData = <?= json_encode($stats['priority_counts']) ?>;
                const brandData = <?= json_encode($stats['brand_counts']) ?>;
                
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
                            <?php 
                            $palette = [
                                'bg-blue-100 text-blue-800',
                                'bg-green-100 text-green-800',
                                'bg-yellow-100 text-yellow-800',
                                'bg-red-100 text-red-800',
                                'bg-purple-100 text-purple-800',
                                'bg-pink-100 text-pink-800',
                                'bg-cyan-100 text-cyan-800',
                                'bg-lime-100 text-lime-800',
                                'bg-orange-100 text-orange-800',
                                'bg-indigo-100 text-indigo-800',
                            ];
                            ?>
                            <?php foreach ($items as $row): ?>
                                <?php 
                                    $safe_name = htmlspecialchars($row['name'], ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                                    $safe_link = htmlspecialchars($row['link'] ?? '', ENT_QUOTES);
                                    
                                    $brand_class = $palette[($row['brandid'] ?? 0) % count($palette)];
                                    
                                    $priority_class = match($row['priority']) {
                                        'High' => 'bg-red-100 text-red-800',
                                        'Mid' => 'bg-yellow-100 text-yellow-800',
                                        'Low' => 'bg-green-100 text-green-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100'>
                                    <td data-label="ID" class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'><?= $row['id'] ?></td>
                                    <td data-label="Kit Name" class='px-4 py-3 text-sm font-semibold text-gray-800'><?= $row['name'] ?></td>
                                    <td data-label="Brand" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                            <?= $row['brand'] ?>
                                        </span>
                                    </td>
                                    <td data-label="Priority" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <?php if (!empty($row['priority'])): ?>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $priority_class ?>">
                                            <?= $row['priority'] ?>
                                        </span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Obtained" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= $row['obtained'] ?></td>
                                    <td data-label="Link" class='px-4 py-3 text-sm text-gray-600'>
                                        <?php if (!empty($row['link'])): ?>
                                            <a href="<?= $safe_link ?>" target="_blank" class="text-blue-500 hover:underline">🔗 Link</a>
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
                                                data-notes='<?= $row['notes'] ?>'
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
    </script>

    <script>initScrollRestore('wishlist_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
