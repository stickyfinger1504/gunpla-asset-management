<?php
require '../includes/bootstrap.php';

$current_section = 'kits';
$current_page = 'inventory';
$page_title = 'Inventory';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $action_success = delete_kit($conn, $_POST['deleteid']);
        $msg_text = $action_success ? "✅ Successfully Deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['archiveid'])) {
        $action_success = archive_kit($conn, $_POST['archiveid']);
        $msg_text = $action_success ? "✅ Successfully Archived" : "❌ Failed to archive";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $action_success = add_kit($conn, $_POST);
        $msg_text = $action_success ? "✅ Successfully added " . htmlspecialchars($_POST["kit_name"]) : "❌ Error adding kit";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        $action_success = update_kit($conn, $_POST);
        $msg_text = $action_success ? "✅ Kit updated successfully" : "❌ Error updating kit";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /inventory");
        exit;
    }
}

$message = get_flash_message();

$brands = get_brands($conn);
$statuses = get_statuses($conn);
$kits = get_kit_inventory($conn, $_GET);

$stats = calculate_kit_stats($kits);


$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['search']) || !empty($_GET['filter_status']);

?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full"> <h1 class="page-title font-bold text-gray-700 text-center mb-8">🤖 Gunpla Hangar</h1>
            
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
                    $value = $stats['total_kits'];
                    $label = $has_filters ? 'Filtered Kits' : 'Total Kits';
                    $color = 'blue';
                    include '../components/stats/stat_card.php';
                    
                    $value = format_currency($stats['total_spent']);
                    $label = 'Total Invested';
                    $color = 'green';
                    include '../components/stats/stat_card.php';
                    
                    $value = format_currency($stats['avg_price']);
                    $label = 'Avg Price';
                    $color = 'yellow';
                    include '../components/stats/stat_card.php';
                    
                    $value = format_currency($stats['max_price']);
                    $label = 'Most Expensive';
                    $color = 'purple';
                    include '../components/stats/stat_card.php';
                    ?>
                </div>
            </div>

            <?php if ($stats['total_kits'] > 0): ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php
                $id = 'brandChart';
                $title = 'Brand Distribution';
                include '../components/charts/chart_canvas.php';

                $id = 'statusChart';
                $title = 'Status Distribution';
                include '../components/charts/chart_canvas.php';

                $id = 'spendingChart';
                $title = 'Spending Over Time';
                include '../components/charts/chart_canvas.php';

                $id = 'purchasesChart';
                $title = 'Kits Bought Over Time';
                $extra_controls = '
                    <div class="flex bg-gray-100 rounded-lg p-1 w-max">
                        <button id="btnMonthly" onclick="switchPurchasesView(\'monthly\')" 
                                class="px-3 py-1 text-xs font-medium rounded bg-blue-500 text-white">Monthly</button>
                        <button id="btnYearly" onclick="switchPurchasesView(\'yearly\')" 
                                class="px-3 py-1 text-xs font-medium rounded text-gray-600 hover:bg-gray-200">Yearly</button>
                    </div>';
                include '../components/charts/chart_canvas.php';
                ?>
            </div>

            <?php include '../components/charts/init_charts.php'; ?>
            <script>
            (function() {
                const brandData = <?= json_encode($stats['brand_counts'], JSON_HEX_TAG) ?>;
                const statusData = <?= json_encode($stats['status_counts'], JSON_HEX_TAG) ?>;
                
                initDoughnutChart('brandChart', Object.keys(brandData), Object.values(brandData));
                initDoughnutChart('statusChart', Object.keys(statusData), Object.values(statusData));
                
                const monthlySpending = <?= json_encode($stats['monthly_spending'], JSON_HEX_TAG) ?>;
                const monthlyPurchases = <?= json_encode($stats['monthly_purchases'], JSON_HEX_TAG) ?>;
                const yearlyPurchases = <?= json_encode($stats['yearly_purchases'], JSON_HEX_TAG) ?>;
                const colors = ChartConfig.colors;

                new Chart(document.getElementById('spendingChart'), {
                    type: 'scatter',
                    data: { 
                        datasets: [{ 
                            label: 'Kit Price', 
                            data: monthlySpending.map(d => ({ x: new Date(d.x), y: d.y, name: d.name })),
                            backgroundColor: '#10B981',
                            pointRadius: 6
                        }] 
                    },
                    options: { 
                        responsive: true, maintainAspectRatio: false, 
                        plugins: { 
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => ctx.raw.name + ': ' + ChartConfig.formatCurrency(ctx.raw.y) } }
                        }, 
                        scales: { 
                            x: { type: 'time', time: { unit: 'month', displayFormats: { month: 'MMM yyyy' } } },
                            y: { beginAtZero: true } 
                        } 
                    }
                });
                
                window.purchasesChart = new Chart(document.getElementById('purchasesChart'), {
                    type: 'bar',
                    data: { labels: Object.keys(monthlyPurchases).map(ym => { const [y,m] = ym.split('-'); return m + '/' + y; }), datasets: [{ label: 'Kits', data: Object.values(monthlyPurchases), backgroundColor: '#3B82F6' }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
                
                window.purchasesData = {
                    monthly: { labels: Object.keys(monthlyPurchases).map(ym => { const [y,m] = ym.split('-'); return m + '/' + y; }), data: Object.values(monthlyPurchases) },
                    yearly: { labels: Object.keys(yearlyPurchases), data: Object.values(yearlyPurchases) }
                };
            })();

            window.switchPurchasesView = function(mode) {
                const chart = window.purchasesChart;
                const data = window.purchasesData[mode];
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.data;
                chart.update();
                
                const btnMonthly = document.getElementById('btnMonthly');
                const btnYearly = document.getElementById('btnYearly');
                if (mode === 'monthly') {
                    btnMonthly.className = 'px-3 py-1 text-xs font-medium rounded bg-blue-500 text-white';
                    btnYearly.className = 'px-3 py-1 text-xs font-medium rounded text-gray-600 hover:bg-gray-200';
                } else {
                    btnYearly.className = 'px-3 py-1 text-xs font-medium rounded bg-blue-500 text-white';
                    btnMonthly.className = 'px-3 py-1 text-xs font-medium rounded text-gray-600 hover:bg-gray-200';
                }
            }
            </script>
            <?php endif; ?>

            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xl font-bold text-gray-700">📦 Current Inventory</h3>
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
                            <label class="block text-xs font-bold text-gray-500 uppercase">Sort By</label>
                            <select name="sortby" class="w-full mt-1 p-2 border border-gray-300 rounded">
                                <option value="date_desc" <?= (isset($_GET['sortby']) && $_GET['sortby'] == 'date_desc') ? 'selected' : '' ?>>Date Bought (Newest)</option>
                                <option value="date_asc" <?= (isset($_GET['sortby']) && $_GET['sortby'] == 'date_asc') ? 'selected' : '' ?>>Date Bought (Oldest)</option>
                                <option value="price_desc" <?= (isset($_GET['sortby']) && $_GET['sortby'] == 'price_desc') ? 'selected' : '' ?>>Price (Highest)</option>
                                <option value="price_asc" <?= (isset($_GET['sortby']) && $_GET['sortby'] == 'price_asc') ? 'selected' : '' ?>>Price (Lowest)</option>
                            </select>
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Filter Status</label>
                            <select name="filter_status" class="w-full mt-1 p-2 border border-gray-300 rounded">
                                <option value="">All Statuses</option>
                                <?php foreach($statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == $status['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['label']) ?>
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
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Brand</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 4, 'date')">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 5, 'number')">Price</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($kits) > 0): ?>
                            <?php foreach ($kits as $row): ?>
                                <?php 
                                    $price_display = $row['pricebought'] ? format_currency($row['pricebought']) : "-";
                                    $safe_name = htmlspecialchars($row['name'], ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                                    $safe_dates = htmlspecialchars($row['datebought'] ??'-', ENT_QUOTES);
                                    
                                    $brand_class = get_brand_color_palette($row['brandid']);
                                    $status_class = get_brand_color_palette($row['statusid']);
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100'>
                                    <td data-label="ID" class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'><?= $row['id'] ?></td>
                                    <td data-label="Kit Name" class='px-4 py-3 text-sm font-semibold text-gray-800'>
                                        <a href="/kit/<?= $row['actualid'] ?>" class="text-blue-600 hover:underline"><?= $safe_name ?></a>
                                    </td>
                                    <td data-label="Brand" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                            <?= e($row['brand']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Status" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $status_class ?>">
                                            <?= e($row['status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Date" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= $safe_dates ?></td>
                                    <td data-label="Price" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= $price_display ?></td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600'><?= $safe_notes ?></td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
                                                data-id='<?= $row['actualid'] ?>'
                                                data-name='<?= $safe_name ?>'
                                                data-brand='<?= $row['brandid'] ?>'
                                                data-status='<?= $row['statusid'] ?>'
                                                data-date='<?= $row['datebought'] ?>'
                                                data-price='<?= $row['pricebought'] ?>'
                                                data-notes='<?= e($row['notes']) ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>
                                            
                                            <form method='POST' class='inline' onsubmit='return confirm("Archive <?= $safe_name ?>?");'>
                                                <input type='hidden' name='archiveid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-blue-100 rounded text-lg' title='Archive'>📦</button>
                                            </form>

                                            <form method='POST' class='inline' onsubmit='return confirm("Delete this kit?");'>
                                                <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='8' class='text-center py-6 text-gray-500'>No kits found in the hangar yet! Start buying!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <button onclick="openAddModal()" 
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Add New Kit">
        +
    </button>

    <?php $mode = 'add'; include '../components/inventory_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/inventory_modal.php'; ?>

    <script>
        function openEditModal(button) {
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const brand = button.getAttribute('data-brand');
            const status = button.getAttribute('data-status');
            const date = button.getAttribute('data-date');
            const price = button.getAttribute('data-price');
            const notes = button.getAttribute('data-notes');

            document.getElementById('modal_id').value = id;
            document.getElementById('modal_name').value = name;
            document.getElementById('modal_brand').value = brand;   
            document.getElementById('modal_status').value = status; 
            document.getElementById('modal_date').value = date;
            document.getElementById('modal_price').value = price;
            document.getElementById('modal_notes').value = notes;
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex'; 
        }
    </script>

    <script>initScrollRestore('inventory_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
