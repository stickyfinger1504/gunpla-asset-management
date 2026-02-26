<?php
require_once '../includes/bootstrap.php';

$current_section = 'tools';
$current_page = 'tool_inventory';
$page_title = 'Tool Inventory';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['deleteid'])) {
        $success = delete_tool($conn, (int)$_POST['deleteid']);
        set_flash_message($success ? '✅ Tool deleted' : '❌ Error deleting tool');
        header('Location: /tool_inventory'); exit;
    }

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $uploaded = handle_image_upload($_FILES['image'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
        if (!$uploaded['success']) {
            set_flash_message('❌ ' . $uploaded['error']);
            header('Location: /tool_inventory'); exit;
        }
        // On edit, delete the old image
        if ($_POST['action_type'] === 'edit' && !empty($_POST['existing_imagepath'])) {
            delete_image_file($_POST['existing_imagepath'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
        }
        $_POST['imagepath'] = $uploaded['path'];
    } else {
        $_POST['imagepath'] = $_POST['existing_imagepath'] ?? '';
    }

    if ($_POST['action_type'] === 'add') {
        $success = add_tool($conn, $_POST);
        set_flash_message($success ? '✅ Tool added' : '❌ Error adding tool');
    } elseif ($_POST['action_type'] === 'edit') {
        $success = update_tool($conn, $_POST);
        set_flash_message($success ? '✅ Tool updated' : '❌ Error updating tool');
    }

    header('Location: /tool_inventory'); exit;
}

$message       = get_flash_message();
$tool_brands   = get_tool_brands($conn);
$tool_cats     = get_tool_categories($conn);
$tool_statuses = get_tool_statuses($conn);
$tools         = get_tool_inventory($conn, $_GET);
$stats         = calculate_tool_stats($tools);

$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['filter_category'])
            || !empty($_GET['filter_status']) || !empty($_GET['search']);
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-7xl mx-auto w-full">
    <h1 class="text-3xl font-bold text-gray-700 text-center mb-8">🔧 Tool Inventory</h1>

    <?php include '../components/toast.php'; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php
        $value = $stats['total_tools'];
        $label = $has_filters ? 'Filtered Tools' : 'Total Tools';
        $color = 'blue'; include '../components/stats/stat_card.php';

        $value = count($stats['category_counts']);
        $label = 'Categories';
        $color = 'green'; include '../components/stats/stat_card.php';

        $value = format_currency($stats['total_spent']);
        $label = 'Total Spent';
        $color = 'yellow'; include '../components/stats/stat_card.php';

        $value = $stats['low_quantity'];
        $label = 'Low Stock';
        $color = 'red'; include '../components/stats/stat_card.php';
        ?>
    </div>

    <?php if ($stats['total_tools'] > 0): ?>
    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <?php
        $id = 'categoryChart'; $title = 'By Category'; include '../components/charts/chart_canvas.php';
        $id = 'brandChart';    $title = 'By Brand';    include '../components/charts/chart_canvas.php';
        ?>
    </div>
    <?php include '../components/charts/init_charts.php'; ?>
    <script>
    (function() {
        const catData   = <?= json_encode($stats['category_counts']) ?>;
        const brandData = <?= json_encode($stats['brand_counts']) ?>;
        initDoughnutChart('categoryChart', Object.keys(catData),   Object.values(catData));
        initDoughnutChart('brandChart',    Object.keys(brandData), Object.values(brandData));
    })();
    </script>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-xl font-bold text-gray-700">🔧 My Tools</h3>
    </div>
    <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
        <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       placeholder="Tool name..."
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-gray-500 uppercase">Brand</label>
                <select name="filter_brand" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">All Brands</option>
                    <?php foreach ($tool_brands as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= (isset($_GET['filter_brand']) && $_GET['filter_brand'] == $b['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-gray-500 uppercase">Category</label>
                <select name="filter_category" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">All Categories</option>
                    <?php foreach ($tool_cats as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($_GET['filter_category']) && $_GET['filter_category'] == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-gray-500 uppercase">Status</label>
                <select name="filter_status" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">All Statuses</option>
                    <?php foreach ($tool_statuses as $st): ?>
                        <option value="<?= $st['id'] ?>" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == $st['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($st['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-1 w-full">
                <label class="block text-xs font-bold text-gray-500 uppercase">Sort By</label>
                <select name="sortby" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="date_desc" <?= (($_GET['sortby'] ?? '') === 'date_desc') ? 'selected' : '' ?>>Date Added (Newest)</option>
                    <option value="date_asc"  <?= (($_GET['sortby'] ?? '') === 'date_asc')  ? 'selected' : '' ?>>Date Added (Oldest)</option>
                    <option value="name_asc"  <?= (($_GET['sortby'] ?? '') === 'name_asc')  ? 'selected' : '' ?>>Name (A-Z)</option>
                    <option value="name_desc" <?= (($_GET['sortby'] ?? '') === 'name_desc') ? 'selected' : '' ?>>Name (Z-A)</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Apply</button>
                <button type="button" onclick="clearFilters(this)" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Clear</button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Brand</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Category</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Qty</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Price</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (count($tools) > 0): ?>
                    <?php
                    $palette = [
                        'bg-blue-100 text-blue-800', 'bg-green-100 text-green-800',
                        'bg-yellow-100 text-yellow-800', 'bg-red-100 text-red-800',
                        'bg-purple-100 text-purple-800', 'bg-pink-100 text-pink-800',
                        'bg-cyan-100 text-cyan-800', 'bg-lime-100 text-lime-800',
                        'bg-orange-100 text-orange-800', 'bg-indigo-100 text-indigo-800',
                    ];
                    ?>
                    <?php foreach ($tools as $row): ?>
                        <?php
                        $safe_name  = htmlspecialchars($row['name'], ENT_QUOTES);
                        $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                        $brand_class = $palette[($row['brandid'] ?? 0) % count($palette)];
                        $cat_class   = $palette[($row['categoryid'] ?? 0) % count($palette)];

                        $status_label = $row['status'] ?? '-';
                        $status_lower = strtolower($status_label);
                        if (str_contains($status_lower, 'new') || str_contains($status_lower, 'good')) {
                            $status_class = 'bg-green-100 text-green-800';
                        } elseif (str_contains($status_lower, 'worn')) {
                            $status_class = 'bg-yellow-100 text-yellow-800';
                        } elseif (str_contains($status_lower, 'needs')) {
                            $status_class = 'bg-orange-100 text-orange-800';
                        } elseif (str_contains($status_lower, 'broken') || str_contains($status_lower, 'retired')) {
                            $status_class = 'bg-red-100 text-red-800';
                        } else {
                            $status_class = 'bg-gray-100 text-gray-800';
                        }

                        $qty = (int)($row['quantity'] ?? 1);
                        $qty_class = $qty <= 3 ? 'text-red-600 font-bold' : 'text-gray-800';
                        ?>
                        <tr class="hover:bg-gray-50 border-b border-gray-100">
                            <td class="px-4 py-3 text-sm font-semibold text-gray-800">
                                <?php if (!empty($row['imagepath'])): ?>
                                <a href="<?= e($row['imagepath']) ?>" target="_blank" class="mr-2">
                                    <img src="<?= e($row['imagepath']) ?>" alt="Tool"
                                         class="w-8 h-8 object-cover rounded inline-block align-middle border">
                                </a>
                                <?php endif; ?>
                                <?= $safe_name ?>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                    <?= e($row['brand'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?= $cat_class ?>">
                                    <?= e($row['category'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                <?php if (!empty($row['status'])): ?>
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?= $status_class ?>">
                                    <?= e($status_label) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-center <?= $qty_class ?>">
                                <?= $qty ?><?= !empty($row['unit']) ? ' ' . e($row['unit']) : '' ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                <?= $row['pricebought'] ? format_currency($row['pricebought']) : '-' ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 max-w-[150px] truncate" title="<?= $safe_notes ?>">
                                <?= $safe_notes ?>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex items-center space-x-2">
                                    <button type="button" class="p-1 hover:bg-gray-200 rounded text-lg" title="Edit"
                                        data-id="<?= $row['actualid'] ?>"
                                        data-name="<?= $safe_name ?>"
                                        data-brand="<?= $row['brandid'] ?? '' ?>"
                                        data-category="<?= $row['categoryid'] ?? '' ?>"
                                        data-status="<?= $row['statusid'] ?? '' ?>"
                                        data-quantity="<?= $row['quantity'] ?>"
                                        data-unit="<?= htmlspecialchars($row['unit'] ?? '', ENT_QUOTES) ?>"
                                        data-pricebought="<?= $row['pricebought'] ?? '' ?>"
                                        data-datebought="<?= !empty($row['datebought']) ? date('Y-m-d', strtotime($row['datebought'])) : '' ?>"
                                        data-link="<?= htmlspecialchars($row['link'] ?? '', ENT_QUOTES) ?>"
                                        data-notes="<?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES) ?>"
                                        data-imagepath="<?= htmlspecialchars($row['imagepath'] ?? '', ENT_QUOTES) ?>"
                                        onclick="openEditModal(this)">✏️
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete <?= $safe_name ?>?');">
                                        <input type="hidden" name="deleteid" value="<?= $row['actualid'] ?>">
                                        <button type="submit" class="p-1 hover:bg-red-100 rounded text-lg" title="Delete">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center py-6 text-gray-500">No tools yet! Add your first one.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- FAB -->
<button onclick="openAddModal()"
        class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
        title="Add Tool">+
</button>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-lg p-6 modal-animate relative overflow-y-auto max-h-[90vh]">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="closeAddModal()">&times;</span>
        <h2 class="text-xl font-bold text-gray-700 mb-4">➕ Add Tool</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="add">

            <div>
                <label class="block text-sm font-semibold text-gray-600">Tool Name *</label>
                <input type="text" name="name" required placeholder="e.g. Tamiya Sharp Nipper"
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Brand</label>
                    <select name="brand" class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select Brand --</option>
                        <?php foreach ($tool_brands as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Category</label>
                    <select name="category" class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($tool_cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Status / Condition</label>
                <select name="status" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">-- Optional --</option>
                    <?php foreach ($tool_statuses as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Quantity &amp; Unit</label>
                <div class="flex gap-2 mt-1">
                    <input type="number" name="quantity" value="1" min="1" placeholder="1"
                           class="w-1/3 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                    <input type="text" name="unit" placeholder="pcs / sheets"
                           class="flex-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Price Bought (Rp)</label>
                    <input type="number" name="pricebought" min="0" placeholder="e.g. 150000"
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Date Bought</label>
                    <input type="date" name="datebought"
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Reorder Link</label>
                <input type="url" name="link" placeholder="https://..."
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Image</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes</label>
                <textarea name="notes" rows="2" placeholder="Any extra notes..."
                          class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                Add to Toolbox
            </button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-lg p-6 modal-animate relative overflow-y-auto max-h-[90vh]">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="closeEditModal()">&times;</span>
        <h2 class="text-xl font-bold text-gray-700 mb-4">✏️ Edit Tool</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="edit">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="existing_imagepath" id="modal_imagepath">

            <div>
                <label class="block text-sm font-semibold text-gray-600">Tool Name *</label>
                <input type="text" name="name" id="modal_name" required
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Brand</label>
                    <select name="brand" id="modal_brand" class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select Brand --</option>
                        <?php foreach ($tool_brands as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Category</label>
                    <select name="category" id="modal_category" class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($tool_cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Status / Condition</label>
                <select name="status" id="modal_status" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">-- Optional --</option>
                    <?php foreach ($tool_statuses as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Quantity &amp; Unit</label>
                <div class="flex gap-2 mt-1">
                    <input type="number" name="quantity" id="modal_quantity" min="1" placeholder="1"
                           class="w-1/3 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                    <input type="text" name="unit" id="modal_unit" placeholder="pcs / sheets"
                           class="flex-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Price Bought (Rp)</label>
                    <input type="number" name="pricebought" id="modal_pricebought" min="0"
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Date Bought</label>
                    <input type="date" name="datebought" id="modal_datebought"
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Reorder Link</label>
                <input type="url" name="link" id="modal_link" placeholder="https://..."
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Image</label>
                <div id="modal_image_preview" class="hidden mb-2">
                    <img id="modal_image_thumb" src="" alt="Current image" class="w-16 h-16 object-cover rounded border">
                    <span class="text-xs text-gray-500 ml-2">Current (upload new to replace)</span>
                </div>
                <input type="file" name="image" accept="image/*"
                       class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes</label>
                <textarea name="notes" id="modal_notes" rows="2"
                          class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Save Changes
            </button>
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
        const get = attr => button.getAttribute('data-' + attr);

        document.getElementById('edit_id').value           = get('id');
        document.getElementById('modal_name').value        = get('name');
        document.getElementById('modal_brand').value       = get('brand');
        document.getElementById('modal_category').value    = get('category');
        document.getElementById('modal_status').value      = get('status');
        document.getElementById('modal_quantity').value    = get('quantity');
        document.getElementById('modal_unit').value        = get('unit');
        document.getElementById('modal_pricebought').value = get('pricebought');
        document.getElementById('modal_datebought').value  = get('datebought');
        document.getElementById('modal_link').value        = get('link');
        document.getElementById('modal_notes').value       = get('notes');

        const imagepath = get('imagepath');
        document.getElementById('modal_imagepath').value = imagepath;
        const preview = document.getElementById('modal_image_preview');
        const thumb   = document.getElementById('modal_image_thumb');
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

    window.onclick = function(event) {
        const addModal  = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        if (event.target == addModal)  closeAddModal();
        if (event.target == editModal) closeEditModal();
    };

    function clearFilters(btn) {
        const form = btn.closest('form');
        form.querySelectorAll('input[type="text"]').forEach(el => el.value = '');
        form.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
        form.submit();
    }
</script>

<script>
    (function() {
        var pos = sessionStorage.getItem('tool_inventory_scroll');
        if (pos) {
            window.scrollTo(0, parseInt(pos));
            sessionStorage.removeItem('tool_inventory_scroll');
        }
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                sessionStorage.setItem('tool_inventory_scroll', window.scrollY);
            });
        });
    })();
</script>

<?php include '../components/layout_footer.php'; ?>
