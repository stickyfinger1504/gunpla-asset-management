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
                        $lower = strtolower($amt);
                        if (str_contains($lower, 'low') || str_contains($lower, 'empty')) {
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
                                <tr class='hover:bg-gray-50 border-b border-gray-100'>
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
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
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

                                            <form method='POST' class='inline' onsubmit='return confirm("Delete <?= $safe_name ?>?");'>
                                                <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
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
                                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow group">
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

                                    <div class="px-3 pb-3 flex gap-2">
                                        <button type='button' class='text-sm hover:bg-gray-100 rounded px-1' title='Edit'
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
                                        <form method='POST' class='inline' onsubmit='return confirm("Delete <?= $safe_name ?>?");'>
                                            <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                            <button type='submit' class='text-sm hover:bg-red-50 rounded px-1' title='Delete'>🗑️</button>
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
    </script>

    <script>initScrollRestore('paint_inventory_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
