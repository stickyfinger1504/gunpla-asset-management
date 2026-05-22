<?php
require '../includes/bootstrap.php';

$current_section = 'kits';
$current_page = 'build_progress';
$page_title = 'Build Progress';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $stmt = $conn->prepare("SELECT imagepath FROM kit_transaction_log WHERE logid = ?");
        $stmt->bind_param("s", $_POST['deleteid']);
        $stmt->execute();
        $result = $stmt->get_result();
        $old = $result->fetch_assoc();

        $action_success = delete_transaction_log($conn, $_POST['deleteid']);
        
        if ($action_success && !empty($old['imagepath'])) {
            delete_image_file($old['imagepath']);
        }
        $msg_text = $action_success ? "✅ Log entry deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'clear_orphaned') {
        $stmt = $conn->prepare("SELECT imagepath FROM kit_transaction_log WHERE backlogid IS NULL");
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt = $conn->prepare("DELETE FROM kit_transaction_log WHERE backlogid IS NULL");
        $action_success = $stmt->execute();

        if ($action_success) {
            foreach ($images as $img) {
                if (!empty($img['imagepath'])) {
                    delete_image_file($img['imagepath']);
                }
            }
        }
        $msg_text = $action_success ? "✅ Orphaned logs cleared" : "❌ Error clearing logs";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $imagepath = '';
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /build_progress");
                exit;
            }
            $imagepath = $upload['path'];
        }
        $_POST['imagepath'] = $imagepath;
        $action_success = add_transaction_log($conn, $_POST);
        $msg_text = $action_success ? "✅ Progress logged!" : "❌ Error adding log";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /build_progress");
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath']);
            }
            $_POST['imagepath'] = $upload['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? '';
        }
        $action_success = update_transaction_log($conn, $_POST);
        $msg_text = $action_success ? "✅ Log updated" : "❌ Error updating log";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /build_progress");
        exit;
    }
}

$message = get_flash_message();

$backlog_items = get_backlog_items_for_dropdown($conn);
$logs = get_transaction_logs($conn, $_GET);
$stats = calculate_transaction_stats($logs);

$has_filters = !empty($_GET['filter_backlog']) || !empty($_GET['search']);
$has_orphaned = count(array_filter($logs, fn($l) => empty($l['name']))) > 0;

?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full">
            <h1 class="page-title font-bold text-gray-700 text-center mb-8">🔨 Build Progress</h1>
            
            <?php include '../components/toast.php'; ?>

            <?php if (!empty($logs)): 
                $latest = $logs[0];
                $latest_name = htmlspecialchars($latest['name'] ?? '-');
                $latest_logname = htmlspecialchars($latest['logname'] ?? '-');
                $latest_notes = htmlspecialchars($latest['notes'] ?? '');
                $latest_image = htmlspecialchars($latest['imagepath'] ?? '');
                $latest_date = !empty($latest['createdat']) ? date('M j, Y · H:i', strtotime($latest['createdat'])) : '';
            ?>
            <div class="mb-8">
                <h3 class="text-lg font-bold text-gray-700 mb-3">🆕 Latest Update</h3>
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden flex flex-col md:flex-row">
                    <?php if (!empty($latest_image)): ?>
                    <a href="<?= $latest_image ?>" target="_blank" class="md:w-48 flex-shrink-0">
                        <img src="<?= $latest_image ?>" alt="Latest build photo" 
                             class="w-full h-48 md:h-full object-cover hover:opacity-90 transition">
                    </a>
                    <?php endif; ?>
                    <div class="p-5 flex flex-col justify-center <?= empty($latest_image) ? '' : '' ?>">
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1"><?= $latest_date ?></p>
                        <p class="text-lg font-bold text-gray-800"><?= $latest_logname ?></p>
                        <p class="text-sm text-blue-600 font-medium mt-1"><?= $latest_name ?></p>
                        <?php if (!empty($latest_notes)): ?>
                        <p class="text-sm text-gray-500 mt-2"><?= $latest_notes ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="mb-8">
                <h3 class="text-lg font-bold text-gray-700 mb-3">📊 Activity Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-blue-600"><?= $stats['total_entries'] ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?= $has_filters ? 'Filtered Entries' : 'Total Log Entries' ?></p>
                    </div>
                    <div class="bg-white border border-green-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-green-600"><?= count($stats['kit_counts']) ?></p>
                        <p class="text-sm text-gray-500 mt-1">Kits Tracked</p>
                    </div>
                    <div class="bg-white border border-purple-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-purple-600"><?= $stats['recent_count'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">This Week</p>
                    </div>
                </div>
            </div>

            <?php if ($stats['total_entries'] > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php
                $id = 'kitChart';
                $title = 'Logs per Kit';
                include '../components/charts/chart_canvas.php';
                ?>
            </div>

            <?php include '../components/charts/init_charts.php'; ?>
            <script>
            (function() {
                const kitData = <?= json_encode($stats['kit_counts'], JSON_HEX_TAG) ?>;
                initDoughnutChart('kitChart', Object.keys(kitData), Object.values(kitData));
            })();
            </script>
            <?php endif; ?>

            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xl font-bold text-gray-700">📋 Log Entries</h3>
                <?php if ($has_orphaned): ?>
                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete all orphaned logs?');">
                    <input type="hidden" name="action_type" value="clear_orphaned">
                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm font-medium transition flex items-center gap-1">
                        🧹 Clear Error Logs
                    </button>
                </form>
                <?php endif; ?>
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
                               placeholder="Log name, kit name, or ID..." 
                               class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Filter by Kit</label>
                        <select name="filter_backlog" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="">All Kits</option>
                            <?php foreach($backlog_items as $bl): ?>
                                <option value="<?= $bl['actualid'] ?>" <?= (isset($_GET['filter_backlog']) && $_GET['filter_backlog'] == $bl['actualid']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex-1 w-full">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Sort By</label>
                        <select name="sortby" class="w-full mt-1 p-2 border border-gray-300 rounded">
                            <option value="date_desc" <?= (($_GET['sortby'] ?? '') == 'date_desc') ? 'selected' : '' ?>>Date: Newest</option>
                            <option value="date_asc" <?= (($_GET['sortby'] ?? '') == 'date_asc') ? 'selected' : '' ?>>Date: Oldest</option>
                            <option value="name_asc" <?= (($_GET['sortby'] ?? '') == 'name_asc') ? 'selected' : '' ?>>Name: A-Z</option>
                            <option value="name_desc" <?= (($_GET['sortby'] ?? '') == 'name_desc') ? 'selected' : '' ?>>Name: Z-A</option>
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
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'text')">Kit</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Log Entry</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'date')">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Image</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $row): ?>
                                <?php 
                                    $safe_logname = htmlspecialchars($row['logname'] ?? '-', ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '', ENT_QUOTES);
                                    $safe_image = htmlspecialchars($row['imagepath'] ?? '', ENT_QUOTES);
                                    $is_orphaned = empty($row['name']);
                                    $safe_name = $is_orphaned ? '<span class="text-gray-400 italic">Kit Not Found (Deleted)</span>' : htmlspecialchars($row['name'], ENT_QUOTES);
                                    $formatted_date = !empty($row['createdat']) ? date('M j, Y', strtotime($row['createdat'])) : '-';
                                    $formatted_time = !empty($row['createdat']) ? date('H:i', strtotime($row['createdat'])) : '';
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100' data-logid='<?= htmlspecialchars($row['logid'] ?? '') ?>'>
                                    <td data-label="Kit" class='px-4 py-3 text-sm font-semibold text-gray-800'><?= $safe_name ?></td>
                                    <td data-label="Log Entry" class='px-4 py-3 text-sm text-gray-700 font-medium'><?= $safe_logname ?></td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600 max-w-xs truncate'><?= $safe_notes ?: '-' ?></td>
                                    <td data-label="Date" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'>
                                        <div><?= $formatted_date ?></div>
                                        <?php if ($formatted_time): ?>
                                        <div class="text-xs text-gray-400"><?= $formatted_time ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Image" class='px-4 py-3 text-sm'>
                                        <?php if (!empty($row['imagepath'])): ?>
                                            <a href="<?= $safe_image ?>" target="_blank" title="Click to view full size">
                                                <img src="<?= $safe_image ?>" alt="Build photo" 
                                                     class="w-12 h-12 object-cover rounded border hover:opacity-80 transition cursor-pointer"
                                                     loading="lazy">
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
                                                data-id='<?= htmlspecialchars($row['logid'] ?? '') ?>'
                                                data-backlogid='<?= $row['actual_backlogid'] ?? '' ?>'
                                                data-logname='<?= $safe_logname ?>'
                                                data-notes='<?= $safe_notes ?>'
                                                data-imagepath='<?= $safe_image ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>
                                            
                                            <form method='POST' class='inline' onsubmit='return confirm("Delete this log entry?");'>
                                                <input type='hidden' name='deleteid' value='<?= htmlspecialchars($row['logid'] ?? '') ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='6' class='text-center py-6 text-gray-500'>No build progress logged yet. Start documenting your builds! 🔨</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <button onclick="openAddModal()" 
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Log New Progress">
        +
    </button>

    <?php $mode = 'add'; include '../components/log_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/log_modal.php'; ?>

    <script>
        function openEditModal(button) {
            document.getElementById('modal_id').value = button.getAttribute('data-id');
            document.getElementById('modal_backlogid').value = button.getAttribute('data-backlogid');
            document.getElementById('modal_logname').value = button.getAttribute('data-logname');
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
    </script>

<?php include '../components/layout_footer.php'; ?>
<script>initScrollRestore('buildprogress_scroll');</script>
