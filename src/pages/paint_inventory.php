<?php
require '../includes/bootstrap.php';

$current_section = 'paints';
$current_page = 'paint_inventory';
$page_title = 'Paint Inventory';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $action_success = delete_paint($conn, (int)$_POST['deleteid']);
        $msg_text = $action_success ? "✅ Paint deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $imagepath = '';
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /paint_inventory");
                exit;
            }
            $imagepath = $upload['path'];
        }
        $_POST['imagepath'] = $imagepath;
        $action_success = add_paint($conn, $_POST);
        $msg_text = $action_success ? "✅ Added " . htmlspecialchars($_POST["name"]) : "❌ Error adding paint";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /paint_inventory");
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
            }
            $_POST['imagepath'] = $upload['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? '';
        }
        $action_success = update_paint($conn, $_POST);
        $msg_text = $action_success ? "✅ Paint updated" : "❌ Error updating paint";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'quick_restock') {
        $paint_id = (int)($_POST['paint_id'] ?? 0);
        $result = quick_restock_paint($conn, $paint_id);
        $msg_text = $result['success'] ? "✅ " . $result['message'] : "❌ " . $result['message'];
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /paint_inventory");
        exit;
    }
}

$message = get_flash_message();

$paint_brands = get_paint_brands($conn);
$paint_types = get_paint_types($conn);
$paint_finishes = get_paint_finishes($conn);
$thinned_statuses = get_thinned_statuses($conn);
$amount_levels = get_amount_levels($conn);
$paints = get_paint_inventory($conn, $_GET);
$stats = calculate_paint_stats($paints);

$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['filter_painttype']) || !empty($_GET['filter_amount']) || !empty($_GET['search']);

// Resolve Paint Studio Hub datasets (unfiltered collection maintains workbench context)
$all_paints = $has_filters ? get_paint_inventory($conn) : $paints;
$low_stock_paints = array_values(array_filter($all_paints, function($p) {
    return is_low_stock_paint_amount($p['amount'] ?? null);
}));
?>
<?php include '../components/layout_header.php'; ?>
<script async src="/assets/js/opencv.js"></script>

        <div class="max-w-7xl mx-auto w-full">
            <h1 class="page-title font-bold text-gray-700 text-center mb-8">🎨 Paint Inventory</h1>

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
                    $value = $stats['total_paints'];
                    $label = $has_filters ? 'Filtered Paints' : 'Total Paints';
                    $color = 'blue';
                    include '../components/stats/stat_card.php';

                    $brand_count = count($stats['brand_counts']);
                    $value = $brand_count;
                    $label = 'Brands';
                    $color = 'green';
                    include '../components/stats/stat_card.php';

                    $type_count = count($stats['type_counts']);
                    $value = $type_count;
                    $label = 'Paint Types';
                    $color = 'yellow';
                    include '../components/stats/stat_card.php';

                    $low_stock = 0;
                    foreach ($stats['amount_counts'] as $amt => $cnt) {
                        if (is_low_stock_paint_amount($amt)) {
                            $low_stock += $cnt;
                        }
                    }
                    $value = $low_stock;
                    $label = 'Low / Empty';
                    $color = 'red';
                    include '../components/stats/stat_card.php';
                    ?>
                </div>
            </div>

            <!-- Paint Studio Hub -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <span>🎨</span> Paint Studio Hub
                    </h3>
                    <span class="text-xs text-gray-500 font-medium hidden sm:inline">Workbench stock monitoring & active project color staging</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Panel 1: Restock Radar -->
                    <?php
                    $has_low_stock = count($low_stock_paints) > 0;
                    $radar_border = $has_low_stock ? 'border-amber-300' : 'border-emerald-300';
                    $radar_gradient = $has_low_stock ? 'from-white to-amber-50/40' : 'from-white to-emerald-50/40';
                    $radar_badge = $has_low_stock ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300';
                    $radar_pulse = $has_low_stock ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500';
                    $radar_divider = $has_low_stock ? 'border-amber-100' : 'border-emerald-100';
                    ?>
                    <div class="bg-gradient-to-br <?= $radar_gradient ?> border-2 <?= $radar_border ?> rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?= $radar_badge ?> border">
                                    <span class="w-2 h-2 rounded-full <?= $radar_pulse ?>"></span>
                                    ⚠️ Restock Radar
                                </span>
                                <?php if ($has_low_stock): ?>
                                    <button type="button" id="copy-restock-btn" onclick="copyRestockList()"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold bg-white hover:bg-amber-50 text-amber-900 border border-amber-300 rounded-lg shadow-sm transition-all"
                                            title="Copy missing paint shopping list to clipboard">
                                        <span>📋</span> <span id="copy-restock-btn-text">Copy Shopping List</span>
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        ✨ Fully Stocked
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($has_low_stock): ?>
                                <div class="mb-2">
                                    <p class="text-xs text-gray-600 mb-2 font-medium">
                                        <span class="font-bold text-amber-800"><?= count($low_stock_paints) ?> bottle<?= count($low_stock_paints) !== 1 ? 's' : '' ?></span> running low or finished. Restock or copy to your shopping list:
                                    </p>
                                    <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                        <?php foreach ($low_stock_paints as $p): ?>
                                            <?php
                                                $safe_pname = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
                                                $brand_style = get_brand_color_palette($p['brandid']);
                                                $type_style = get_brand_color_palette($p['painttypeid']);
                                                $amt_label = $p['amount'] ?? 'Low';
                                                $amt_style = get_paint_amount_class($amt_label);
                                                $hex = !empty($p['color_hex']) ? htmlspecialchars($p['color_hex'], ENT_QUOTES, 'UTF-8') : '#9ca3af';
                                            ?>
                                            <div class="flex items-center justify-between gap-2 p-2 bg-white/90 border border-amber-200/80 rounded-lg shadow-2xs hover:bg-white transition-all">
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <span class="w-4 h-4 rounded-full border border-black/20 flex-shrink-0 shadow-inner"
                                                          style="background-color: <?= $hex ?>;"
                                                          title="Hex: <?= $hex ?>"></span>
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-1.5 flex-wrap">
                                                            <a href="/paint/<?= (int)$p['actualid'] ?>" class="text-sm font-bold text-gray-900 hover:text-blue-600 truncate">
                                                                <?= $safe_pname ?>
                                                            </a>
                                                            <span class="text-xs font-mono text-gray-400 font-semibold"><?= htmlspecialchars($p['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                                        </div>
                                                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                                            <span class="px-1.5 py-0.2 text-[10px] font-bold rounded-full <?= $brand_style ?>">
                                                                <?= htmlspecialchars($p['brand'], ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                            <span class="px-1.5 py-0.2 text-[10px] font-semibold rounded-full <?= $type_style ?>">
                                                                <?= htmlspecialchars($p['painttype'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                            <span class="px-1.5 py-0.2 text-[10px] font-bold rounded-full <?= $amt_style ?>">
                                                                <?= htmlspecialchars($amt_label, ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <form method="POST" class="inline m-0 flex-shrink-0">
                                                    <input type="hidden" name="action_type" value="quick_restock">
                                                    <input type="hidden" name="paint_id" value="<?= (int)$p['actualid'] ?>">
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 rounded-lg transition-colors shadow-2xs"
                                                            title="Restock to Full stock level">
                                                        <span>⚡</span> Restock
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="py-6 text-center">
                                    <div class="text-3xl mb-1.5">✨</div>
                                    <p class="text-sm font-bold text-gray-800">All paints well stocked!</p>
                                    <p class="text-xs text-gray-500 mt-1">No bottles are currently marked as Low, Few left, or Finished. Your hangar paint shelf is ready for any build.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="pt-3 border-t <?= $radar_divider ?> flex items-center justify-between text-xs text-gray-500 mt-auto">
                            <span>⚡ Quick Restock sets bottle to Full</span>
                            <span><?= count($all_paints) ?> total paints</span>
                        </div>
                    </div>

                    <!-- Panel 2: Active Project Palette -->
                    <div class="bg-gradient-to-br from-white to-indigo-50/40 border-2 border-indigo-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 border border-indigo-300">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    🎨 Active Project Palette
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <span id="palette-count-badge" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        0 Pinned
                                    </span>
                                    <button type="button" id="btn-palette-filter" onclick="togglePaletteFilter()"
                                            class="hidden inline-flex items-center gap-1 px-2 py-1 text-xs font-bold bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg shadow-2xs transition-all"
                                            title="Filter collection below to only show pinned project paints">
                                        <span>🔍</span> <span id="palette-filter-text">Filter View</span>
                                    </button>
                                    <button type="button" id="btn-palette-clear" onclick="clearActivePalette()"
                                            class="hidden inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 border border-transparent rounded-lg transition-colors"
                                            title="Clear pinned paints from active workbench palette">
                                        <span>🧹</span> Clear
                                    </button>
                                </div>
                            </div>

                            <div id="active-palette-container" class="mb-2">
                                <!-- Populated dynamically by renderActivePalette() -->
                            </div>
                        </div>

                        <div class="pt-3 border-t border-indigo-100 flex items-center justify-between text-xs text-gray-500 mt-auto">
                            <span id="palette-footer-hint">Click 📌 on any paint in the collection to stage it here</span>
                            <span id="palette-copy-notification" class="text-indigo-600 font-bold hidden transition-opacity">✅ Hex copied!</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($stats['total_paints'] > 0): ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php
                $id = 'brandChart';
                $title = 'Brand Distribution';
                include '../components/charts/chart_canvas.php';

                $id = 'typeChart';
                $title = 'Paint Type Distribution';
                include '../components/charts/chart_canvas.php';
                ?>
            </div>

            <?php include '../components/charts/init_charts.php'; ?>
            <script>
            (function() {
                const brandData = <?= json_encode($stats['brand_counts'], JSON_HEX_TAG) ?>;
                const typeData = <?= json_encode($stats['type_counts'], JSON_HEX_TAG) ?>;

                initDoughnutChart('brandChart', Object.keys(brandData), Object.values(brandData));
                initDoughnutChart('typeChart', Object.keys(typeData), Object.values(typeData));
            })();
            </script>
            <?php endif; ?>

            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xl font-bold text-gray-700">🎨 Current Collection</h3>
                <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
                    <button type="button" onclick="setView('table')" id="btn-table"
                            class="px-3 py-1.5 rounded-md text-sm font-medium transition-all"
                            title="Table View">
                        📋
                    </button>
                    <button type="button" onclick="setView('grid')" id="btn-grid"
                            class="px-3 py-1.5 rounded-md text-sm font-medium transition-all"
                            title="Grid View">
                        🎨
                    </button>
                </div>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
                <form method="GET">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-gray-500 uppercase">Filters</span>
                        <button type="button" class="filter-toggle-btn" onclick="toggleFilterBar(this)">▼ Filters</button>
                    </div>
                    <div class="filter-bar-body <?= $has_filters ? 'is-open' : '' ?>">
                    <input type="hidden" name="view" id="view-input" value="<?= htmlspecialchars($_GET['view'] ?? 'table') ?>">
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                               placeholder="Paint name or ID..."
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
                        <label class="block text-xs font-bold text-gray-500 uppercase">Amount</label>
                        <select name="filter_amount" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Amounts</option>
                            <?php foreach($amount_levels as $al): ?>
                                <option value="<?= $al['id'] ?>" <?= (isset($_GET['filter_amount']) && $_GET['filter_amount'] == $al['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($al['label']) ?>
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

            <div id="table-view" class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 mobile-stack-table">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'number')">ID</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Paint Name</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Brand</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Type</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Thinned</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>

                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 7, 'date')">Last Updated</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($paints) > 0): ?>

                            <?php foreach ($paints as $row): ?>
                                <?php
                                    $safe_name = htmlspecialchars($row['name'], ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                                    $brand_class = get_brand_color_palette($row['brandid']);
                                    $type_class = get_brand_color_palette($row['painttypeid']);

                                    $amount_label = $row['amount'] ?? '-';
                                    $amount_class = get_paint_amount_class($amount_label);
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100 paint-table-row' data-paint-id='<?= (int)$row['actualid'] ?>'>
                                    <td data-label="ID" class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'><?= e($row['id']) ?></td>
                                    <td data-label="Paint Name" class='px-4 py-3 text-sm font-semibold'>
                                        <a href="/paint/<?= $row['actualid'] ?>" class="text-blue-600 hover:underline"><?= $safe_name ?></a>
                                    </td>
                                    <td data-label="Brand" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                            <?= e($row['brand']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Type" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $type_class ?>">
                                            <?= e($row['painttype']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Thinned" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= e($row['thinned'] ?? '-') ?></td>
                                    <td data-label="Amount" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $amount_class ?>">
                                            <?= e($amount_label) ?>
                                        </span>
                                    </td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600 max-w-[150px] truncate' title='<?= $safe_notes ?>'><?= $safe_notes ?></td>
                                    <td data-label="Updated" class='px-4 py-3 text-sm text-gray-500 whitespace-nowrap'><?= !empty($row['lastupdate']) ? date('d M Y', strtotime($row['lastupdate'])) : '-' ?></td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-1.5'>
                                            <button type='button' class='p-1 hover:bg-amber-100 rounded text-base palette-pin-btn transition-colors'
                                                data-paint-id='<?= (int)$row['actualid'] ?>'
                                                onclick='togglePalettePin(<?= (int)$row['actualid'] ?>)'
                                                title='Pin to Active Project Palette'>
                                                📌
                                            </button>

                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-base' title='Edit'
                                                data-id='<?= $row['actualid'] ?>'
                                                data-name='<?= $safe_name ?>'
                                                data-brand='<?= $row['brandid'] ?>'
                                                data-painttype='<?= $row['painttypeid'] ?>'
                                                data-thinned='<?= $row['thinnedid'] ?? '' ?>'
                                                data-amount='<?= $row['amountid'] ?? '' ?>'
                                                data-color_hex='<?= htmlspecialchars($row['color_hex'] ?? '') ?>'
                                                data-finishid='<?= $row['finishid'] ?? '' ?>'
                                                data-createddate='<?= !empty($row['createddate']) ? date('Y-m-d', strtotime($row['createddate'])) : '' ?>'
                                                data-notes='<?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES) ?>'
                                                data-imagepath='<?= htmlspecialchars($row['imagepath'] ?? '', ENT_QUOTES) ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>

                                            <form method='POST' class='inline m-0' onsubmit='return confirm("Delete <?= $safe_name ?>?");'>
                                                <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-base' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='9' class='text-center py-6 text-gray-500'>No paints in your collection yet! Start adding some.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div id="grid-view" class="hidden">
                <?php if (count($paints) > 0): ?>
                    <?php
                    $by_brand = [];
                    foreach ($paints as $row) {
                        $brand = $row['brand'] ?? 'Unknown';
                        $by_brand[$brand][] = $row;
                    }
                    ?>

                    <?php foreach ($by_brand as $brand_name => $brand_paints): ?>
                    <div class="mb-8">
                        <h4 class="text-md font-bold text-gray-600 mb-3 border-b border-gray-200 pb-1"><?= e($brand_name) ?></h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            <?php foreach ($brand_paints as $row): ?>
                                <?php
                                    $safe_name = htmlspecialchars($row['name'], ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES);
                                    $amount_label = $row['amount'] ?? '-';
                                    $amount_class = get_paint_amount_class($amount_label);
                                ?>
                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow group paint-grid-card" data-paint-id='<?= (int)$row['actualid'] ?>'>
                                    <?php if (!empty($row['imagepath'])): ?>
                                    <a href="<?= e($row['imagepath']) ?>" target="_blank" rel="noopener noreferrer">
                                        <div class="aspect-square bg-gray-100 overflow-hidden">
                                            <img src="<?= e($row['imagepath']) ?>" alt="<?= $safe_name ?>"
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                                                 loading="lazy">
                                        </div>
                                    </a>
                                    <?php else: ?>
                                    <div class="aspect-square bg-gray-50 flex items-center justify-center">
                                        <span class="text-4xl opacity-30">🎨</span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="p-3">
                                        <p class="text-xs text-gray-400 font-mono"><?= e($row['id']) ?></p>
                                        <a href="/paint/<?= $row['actualid'] ?>" class="text-sm font-semibold text-blue-600 hover:underline truncate block" title="<?= $safe_name ?>"><?= $safe_name ?></a>
                                        <p class="text-xs text-gray-500 mt-0.5"><?= e($row['painttype'] ?? '-') ?></p>

                                        <div class="mt-2">
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $amount_class ?>">
                                                <?= e($amount_label) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="px-3 pb-3 flex items-center gap-1.5 flex-wrap">
                                        <button type='button' class='text-sm hover:bg-amber-50 rounded px-1.5 py-0.5 palette-pin-btn transition-colors'
                                            data-paint-id='<?= (int)$row['actualid'] ?>'
                                            onclick='togglePalettePin(<?= (int)$row['actualid'] ?>)'
                                            title='Pin to Active Project Palette'>
                                            📌 Pin
                                        </button>
                                        <button type='button' class='text-sm hover:bg-gray-100 rounded px-1.5 py-0.5' title='Edit'
                                            data-id='<?= $row['actualid'] ?>'
                                            data-name='<?= $safe_name ?>'
                                            data-brand='<?= $row['brandid'] ?>'
                                            data-painttype='<?= $row['painttypeid'] ?>'
                                            data-thinned='<?= $row['thinnedid'] ?? '' ?>'
                                            data-amount='<?= $row['amountid'] ?? '' ?>'
                                            data-color_hex='<?= htmlspecialchars($row['color_hex'] ?? '') ?>'
                                            data-finishid='<?= $row['finishid'] ?? '' ?>'
                                            data-createddate='<?= !empty($row['createddate']) ? date('Y-m-d', strtotime($row['createddate'])) : '' ?>'
                                            data-notes='<?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES) ?>'
                                            data-imagepath='<?= htmlspecialchars($row['imagepath'] ?? '', ENT_QUOTES) ?>'
                                            onclick='openEditModal(this)'>
                                            ✏️
                                        </button>
                                        <form method='POST' class='inline m-0' onsubmit='return confirm("Delete <?= $safe_name ?>?");'>
                                            <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                            <button type='submit' class='text-sm hover:bg-red-50 rounded px-1.5 py-0.5' title='Delete'>🗑️</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center text-gray-500">
                        No paints in your collection yet! Start adding some.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <button onclick="openAddModal()"
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Add Paint">
        +
    </button>

    <?php $mode = 'add'; include '../components/paint_inventory_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/paint_inventory_modal.php'; ?>


    <script>
        function toggleFilterBar(btn) {
            const body = document.querySelector('.filter-bar-body');
            body.classList.toggle('is-open');
            btn.innerHTML = body.classList.contains('is-open') ? '▲ Filters' : '▼ Filters';
        }

        // OpenCV Smart Extractor Logic
        function handleImageUpload(inputEl, canvasId, hexInputId, finishSelectId) {
            if (!inputEl.files || !inputEl.files[0]) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.getElementById(canvasId);
                    const ctx = canvas.getContext('2d');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    ctx.drawImage(img, 0, 0);

                    if (typeof cv === 'undefined' || !cv.Mat) {
                        alert("The Smart Extractor (OpenCV) is still loading in the background. Please wait a few seconds and try selecting the image again.");
                        return;
                    }

                    try {
                        let src = cv.imread(canvas);
                        let gray = new cv.Mat();
                        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);

                        // Threshold to find spoon (assuming non-background)
                        let thresh = new cv.Mat();
                        cv.threshold(gray, thresh, 50, 255, cv.THRESH_BINARY | cv.THRESH_OTSU);

                        // check corners to see what the background is
                        let corner1 = thresh.ucharPtr(0, 0)[0];
                        let corner2 = thresh.ucharPtr(0, thresh.cols - 1)[0];
                        let corner3 = thresh.ucharPtr(thresh.rows - 1, 0)[0];
                        let corner4 = thresh.ucharPtr(thresh.rows - 1, thresh.cols - 1)[0];
                        let bgVal = (corner1 + corner2 + corner3 + corner4) / 4 > 127 ? 255 : 0;
                        
                        if (bgVal === 255) {
                            cv.bitwise_not(thresh, thresh);
                        }

                        // Calculate average color within the mask
                        let mean = cv.mean(src, thresh);
                        
                        // Set Hex Color
                        let r = Math.round(mean[0]);
                        let g = Math.round(mean[1]);
                        let b = Math.round(mean[2]);
                        let hex = "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
                        document.getElementById(hexInputId).value = hex;

                        // Calculate standard deviation of lightness to guess finish
                        let hsv = new cv.Mat();
                        cv.cvtColor(src, hsv, cv.COLOR_RGBA2RGB);
                        cv.cvtColor(hsv, hsv, cv.COLOR_RGB2HSV);
                        
                        let stddev = new cv.Mat();
                        let meanHsv = new cv.Mat();
                        cv.meanStdDev(hsv, meanHsv, stddev, thresh);
                        
                        let vStdDev = stddev.data64F[2];
                        console.log("Extracted Lightness Variance (StdDev):", vStdDev);
                        
                        let predictedFinish = null;
                        if (vStdDev > 35) {
                            predictedFinish = 'Metallic';
                        } else if (vStdDev < 15) {
                            predictedFinish = 'Matte';
                        } else {
                            predictedFinish = 'Gloss';
                        }
                        
                        console.log("Predicted Finish:", predictedFinish);

                        let select = document.getElementById(finishSelectId);
                        if (select && predictedFinish) {
                            for (let i = 0; i < select.options.length; i++) {
                                if (select.options[i].text === predictedFinish) {
                                    select.selectedIndex = i;
                                    break;
                                }
                            }
                        }

                        src.delete(); gray.delete(); thresh.delete(); hsv.delete(); stddev.delete(); meanHsv.delete();
                    } catch (err) {
                        console.error("OpenCV processing error:", err);
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(inputEl.files[0]);
        }

        document.getElementById('add_image_upload')?.addEventListener('change', function() {
            handleImageUpload(this, 'add_cv_canvas', 'add_color_hex', 'add_finishid');
        });
        document.getElementById('edit_image_upload')?.addEventListener('change', function() {
            handleImageUpload(this, 'edit_cv_canvas', 'modal_color_hex', 'modal_finishid');
        });

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
            document.getElementById('modal_thinned').value = button.getAttribute('data-thinned');
            document.getElementById('modal_amount').value = button.getAttribute('data-amount');
            document.getElementById('modal_color_hex').value = button.getAttribute('data-color_hex') || '#000000';
            document.getElementById('modal_finishid').value = button.getAttribute('data-finishid');
            document.getElementById('modal_createddate').value = button.getAttribute('data-createddate');
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

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').style.display = 'none';
        }
        // Click outside to close is disabled.

        function setView(mode) {
            const tableView = document.getElementById('table-view');
            const gridView = document.getElementById('grid-view');
            const btnTable = document.getElementById('btn-table');
            const btnGrid = document.getElementById('btn-grid');
            const viewInput = document.getElementById('view-input');

            if (mode === 'grid') {
                tableView.classList.add('hidden');
                gridView.classList.remove('hidden');
                btnTable.classList.remove('bg-white', 'shadow-sm');
                btnTable.classList.add('text-gray-500');
                btnGrid.classList.add('bg-white', 'shadow-sm');
                btnGrid.classList.remove('text-gray-500');
            } else {
                tableView.classList.remove('hidden');
                gridView.classList.add('hidden');
                btnGrid.classList.remove('bg-white', 'shadow-sm');
                btnGrid.classList.add('text-gray-500');
                btnTable.classList.add('bg-white', 'shadow-sm');
                btnTable.classList.remove('text-gray-500');
            }

            localStorage.setItem('paint_view_mode', mode);
            if (viewInput) viewInput.value = mode;
        }

        (function() {
            const saved = '<?= htmlspecialchars($_GET['view'] ?? '', ENT_QUOTES) ?>' || localStorage.getItem('paint_view_mode') || 'table';
            setView(saved);
        })();

        // ==========================================
        // Paint Studio Hub: Restock & Active Palette
        // ==========================================
        const allPaintsData = <?= json_encode(array_map(function($p) {
            return [
                'actualid' => (int)$p['actualid'],
                'id' => $p['id'],
                'name' => $p['name'],
                'brand' => $p['brand'],
                'brandid' => (int)$p['brandid'],
                'painttype' => $p['painttype'],
                'amount' => $p['amount'],
                'color_hex' => $p['color_hex'] ?? '#9ca3af',
                'imagepath' => $p['imagepath'] ?? '',
                'brand_class' => get_brand_color_palette($p['brandid']),
                'type_class' => get_brand_color_palette($p['painttypeid']),
            ];
        }, $all_paints), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const paintsById = {};
        allPaintsData.forEach(p => { paintsById[p.actualid] = p; });

        function copyRestockList() {
            const lowPaints = <?= json_encode(array_map(function($p) {
                return [
                    'name' => $p['name'],
                    'brand' => $p['brand'],
                    'painttype' => $p['painttype'] ?? '',
                    'amount' => $p['amount'] ?? '',
                    'id' => $p['id'] ?? ''
                ];
            }, $low_stock_paints), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

            if (!lowPaints || lowPaints.length === 0) return;

            let text = "🛒 Gunpla Hangar - Paint Restock Shopping List:\n";
            lowPaints.forEach(p => {
                text += `• [${p.brand}] ${p.name} (${p.painttype}) - Status: ${p.amount} [${p.id}]\n`;
            });

            navigator.clipboard.writeText(text).then(() => {
                const btnText = document.getElementById('copy-restock-btn-text');
                if (btnText) {
                    const original = btnText.innerText;
                    btnText.innerText = `Copied (${lowPaints.length})!`;
                    setTimeout(() => { btnText.innerText = original; }, 2500);
                }
            }).catch(err => {
                console.error("Clipboard write failed:", err);
                alert("Shopping list:\n\n" + text);
            });
        }

        const PALETTE_STORAGE_KEY = 'gunpla_active_paint_palette';
        let isPaletteFilterActive = false;

        function getPaletteIds() {
            try {
                const stored = localStorage.getItem(PALETTE_STORAGE_KEY);
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                return [];
            }
        }

        function savePaletteIds(ids) {
            try {
                localStorage.setItem(PALETTE_STORAGE_KEY, JSON.stringify(ids));
            } catch (e) {
                console.error("Failed to save palette:", e);
            }
        }

        function togglePalettePin(paintId) {
            let ids = getPaletteIds();
            const index = ids.indexOf(paintId);
            if (index > -1) {
                ids.splice(index, 1);
            } else {
                ids.push(paintId);
            }
            savePaletteIds(ids);
            renderActivePalette();
            updatePinButtons(ids);
            if (isPaletteFilterActive) {
                applyPaletteFilter();
            }
        }

        function clearActivePalette() {
            if (confirm("Clear all pinned paints from your active palette?")) {
                savePaletteIds([]);
                renderActivePalette();
                updatePinButtons([]);
                if (isPaletteFilterActive) {
                    togglePaletteFilter();
                }
            }
        }

        function updatePinButtons(paletteIds) {
            const idSet = new Set(paletteIds);
            document.querySelectorAll('.palette-pin-btn').forEach(btn => {
                const pid = parseInt(btn.getAttribute('data-paint-id'), 10);
                const isPinned = idSet.has(pid);
                if (isPinned) {
                    btn.classList.add('bg-amber-100', 'text-amber-800', 'font-bold');
                    btn.classList.remove('hover:bg-amber-100', 'hover:bg-amber-50');
                    btn.title = 'Unpin from Active Palette';
                    if (btn.innerText.includes('Pin')) {
                        btn.innerHTML = '📍 Pinned';
                    } else {
                        btn.innerHTML = '📍';
                    }
                } else {
                    btn.classList.remove('bg-amber-100', 'text-amber-800', 'font-bold');
                    btn.title = 'Pin to Active Project Palette';
                    if (btn.innerText.includes('Pinned')) {
                        btn.innerHTML = '📌 Pin';
                    } else {
                        btn.innerHTML = '📌';
                    }
                }
            });
        }

        function copyHexCode(hex) {
            navigator.clipboard.writeText(hex).then(() => {
                const notification = document.getElementById('palette-copy-notification');
                const hint = document.getElementById('palette-footer-hint');
                if (notification && hint) {
                    notification.innerText = `✅ Copied ${hex}!`;
                    notification.classList.remove('hidden');
                    hint.classList.add('hidden');
                    setTimeout(() => {
                        notification.classList.add('hidden');
                        hint.classList.remove('hidden');
                    }, 2000);
                }
            });
        }

        function renderActivePalette() {
            const ids = getPaletteIds();
            const container = document.getElementById('active-palette-container');
            const badge = document.getElementById('palette-count-badge');
            const btnFilter = document.getElementById('btn-palette-filter');
            const btnClear = document.getElementById('btn-palette-clear');

            if (badge) badge.innerText = `${ids.length} Pinned`;

            if (ids.length > 0) {
                if (btnFilter) btnFilter.classList.remove('hidden');
                if (btnClear) btnClear.classList.remove('hidden');
            } else {
                if (btnFilter) btnFilter.classList.add('hidden');
                if (btnClear) btnClear.classList.add('hidden');
            }

            if (!container) return;

            if (ids.length === 0) {
                container.innerHTML = `
                    <div class="py-6 text-center">
                        <div class="text-3xl mb-1.5">🎨</div>
                        <p class="text-sm font-bold text-gray-800">Workbench palette is empty</p>
                        <p class="text-xs text-gray-500 mt-1">Click 📌 on any paint in the collection below to stage your project colors for quick bench reference.</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="flex flex-wrap gap-2 max-h-56 overflow-y-auto pr-1">';
            ids.forEach(id => {
                const paint = paintsById[id];
                if (!paint) return;
                const hex = paint.color_hex || '#9ca3af';
                html += `
                    <div class="inline-flex items-center gap-2 px-2.5 py-1.5 bg-white border border-indigo-200 rounded-lg shadow-2xs hover:border-indigo-400 transition-all group">
                        <span class="w-4 h-4 rounded-full border border-black/20 flex-shrink-0 cursor-pointer shadow-inner hover:scale-110 transition-transform"
                              style="background-color: ${hex};"
                              onclick="copyHexCode('${hex}')"
                              title="Click to copy Hex: ${hex}"></span>
                        <div class="flex items-center gap-1.5 min-w-0">
                            <a href="/paint/${paint.actualid}" class="text-xs font-bold text-gray-800 hover:text-indigo-600 truncate max-w-[110px]" title="${paint.name}">
                                ${paint.name}
                            </a>
                            <span class="text-[10px] font-mono px-1 py-0.2 rounded bg-gray-100 text-gray-600 cursor-pointer hover:bg-indigo-50"
                                  onclick="copyHexCode('${hex}')"
                                  title="Click to copy hex">
                                ${hex}
                            </span>
                        </div>
                        <button type="button" onclick="togglePalettePin(${paint.actualid})"
                                class="text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full w-4 h-4 flex items-center justify-center text-xs transition-colors ml-0.5"
                                title="Remove from palette">
                            ✕
                        </button>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function togglePaletteFilter() {
            isPaletteFilterActive = !isPaletteFilterActive;
            const filterBtnText = document.getElementById('palette-filter-text');
            const filterBtn = document.getElementById('btn-palette-filter');

            if (isPaletteFilterActive) {
                if (filterBtnText) filterBtnText.innerText = 'Show All';
                if (filterBtn) {
                    filterBtn.classList.add('bg-indigo-600', 'text-white', 'border-indigo-600');
                    filterBtn.classList.remove('bg-white', 'text-indigo-700', 'border-indigo-200');
                }
            } else {
                if (filterBtnText) filterBtnText.innerText = 'Filter View';
                if (filterBtn) {
                    filterBtn.classList.remove('bg-indigo-600', 'text-white', 'border-indigo-600');
                    filterBtn.classList.add('bg-white', 'text-indigo-700', 'border-indigo-200');
                }
            }
            applyPaletteFilter();
        }

        function applyPaletteFilter() {
            const ids = new Set(getPaletteIds());
            // Filter Table rows
            document.querySelectorAll('.paint-table-row').forEach(row => {
                const pid = parseInt(row.getAttribute('data-paint-id'), 10);
                if (!isPaletteFilterActive || ids.has(pid)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Filter Grid cards
            document.querySelectorAll('.paint-grid-card').forEach(card => {
                const pid = parseInt(card.getAttribute('data-paint-id'), 10);
                if (!isPaletteFilterActive || ids.has(pid)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Initialize Paint Studio Hub
        (function() {
            const paletteIds = getPaletteIds();
            renderActivePalette();
            updatePinButtons(paletteIds);
        })();
    </script>

    <script>initScrollRestore('paint_inventory_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
