<?php
require_once '../includes/bootstrap.php';

$current_section = 'kits';
$current_page = 'tasks';
$page_title = 'Tasks';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['toggle_id'])) {
        $action_success = toggle_task($conn, (int)$_POST['toggle_id']);
        $query = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
        header("Location: /tasks" . $query);
        exit;
    }
    elseif (isset($_POST['deleteid'])) {
        $action_success = delete_task($conn, (int)$_POST['deleteid']);
        $msg_text = $action_success ? "✅ Task deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /tasks");
                exit;
            }
            $_POST['imagepath'] = $upload['path'];
        }
        $action_success = add_task($conn, $_POST);
        $msg_text = $action_success ? "✅ Task added" : "❌ Error adding task";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image']);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /tasks");
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath']);
            }
            $_POST['imagepath'] = $upload['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? null;
        }
        $action_success = update_task($conn, $_POST);
        $msg_text = $action_success ? "✅ Task updated" : "❌ Error updating task";
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /tasks");
        exit;
    }
}

$message = get_flash_message();

$backlog_items = get_backlog_items_for_task_dropdown($conn);
$all_tasks = get_tasks($conn, $_GET);
$stats = calculate_task_stats($all_tasks);
$has_filters = !empty($_GET['filter_kit']) || (isset($_GET['filter_status']) && $_GET['filter_status'] !== '') || !empty($_GET['search']);

$grouped = [];
foreach ($all_tasks as $task) {
    $group_key = $task['kit_name'] ?? '__general__';
    $grouped[$group_key][] = $task;
}
if (isset($grouped['__general__'])) {
    $general = $grouped['__general__'];
    unset($grouped['__general__']);
    $grouped['__general__'] = $general;
}

$filter_kits_result = $conn->query("SELECT DISTINCT inventoryid, name FROM vw_kit_backlog_plan ORDER BY name ASC");
$filter_kits = $filter_kits_result ? $filter_kits_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-5xl mx-auto w-full">
            <h1 class="page-title font-bold text-gray-700 text-center mb-8">📋 Tasks</h1>

            <?php include '../components/toast.php'; ?>

            <!-- Activity Summary -->
            <div class="mb-8">
                <h3 class="text-lg font-bold text-gray-700 mb-3">📊 Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white border border-orange-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-orange-600"><?= $stats['pending'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">To-Do</p>
                    </div>
                    <div class="bg-white border border-green-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-green-600"><?= $stats['done'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">Done</p>
                    </div>
                    <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm text-center">
                        <p class="text-3xl font-bold text-blue-600"><?= $stats['total'] ?></p>
                        <p class="text-sm text-gray-500 mt-1">Total Tasks</p>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Search</label>
                        <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                               placeholder="Task description..." 
                               class="w-full p-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Kit</label>
                        <select name="filter_kit" class="w-full p-2 border border-gray-300 rounded">
                            <option value="">All Kits</option>
                            <?php foreach ($filter_kits as $kit): ?>
                                <option value="<?= $kit['inventoryid'] ?>" <?= ($_GET['filter_kit'] ?? '') == $kit['inventoryid'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kit['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
                        <select name="filter_status" class="w-full p-2 border border-gray-300 rounded">
                            <option value="">All</option>
                            <option value="0" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] === '0') ? 'selected' : '' ?>>To-Do</option>
                            <option value="1" <?= ($_GET['filter_status'] ?? '') === '1' ? 'selected' : '' ?>>Done</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">Filter</button>
                        <?php if ($has_filters): ?>
                        <button type="button" onclick="clearFilters(this)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded transition">✕</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Grouped Task Lists -->
            <?php foreach ($grouped as $group_key => $tasks): ?>
                <div class="mb-6">
                    <!-- Group Header -->
                    <h3 class="text-md font-bold text-gray-700 mb-2 flex items-center gap-2">
                        <?php if ($group_key === '__general__'): ?>
                            📌 General Tasks
                        <?php else: ?>
                            🔶 <a href="/kit/<?= $tasks[0]['inventoryid'] ?>" class="text-blue-600 hover:underline"><?= e($group_key) ?></a>
                            <?php
                            $first = $tasks[0];
                            if (!empty($first['buildplan_label'])): ?>
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-purple-100 text-purple-800">
                                    <?= e($first['buildplan_label']) ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </h3>

                    <!-- Task Items -->
                    <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
                        <?php foreach ($tasks as $task): ?>
                        <div class="flex items-center px-4 py-3 gap-3 <?= $task['is_done'] ? 'opacity-50' : '' ?>">
                            <!-- Toggle Checkbox -->
                            <form method="POST" class="flex-shrink-0">
                                <input type="hidden" name="toggle_id" value="<?= $task['taskid'] ?>">
                                <button type="submit" class="text-xl hover:scale-110 transition" title="Toggle">
                                    <?= $task['is_done'] ? '☑️' : '⬜' ?>
                                </button>
                            </form>

                            <!-- Description -->
                            <span class="flex-1 text-sm <?= $task['is_done'] ? 'line-through text-gray-400' : 'text-gray-800' ?>">
                                <?php if (!empty($task['kit_name'])): ?>
                                    <span class="font-bold mr-1"><?= e($task['kit_name']) ?> —</span>
                                <?php endif; ?>
                                <?= e($task['description']) ?>
                            </span>

                            <!-- Image Thumbnail -->
                            <?php if (!empty($task['imagepath'])): ?>
                            <a href="<?= e($task['imagepath']) ?>" target="_blank" class="flex-shrink-0">
                                <img src="<?= e($task['imagepath']) ?>" alt="Reference"
                                     class="w-10 h-10 object-cover rounded border hover:opacity-80 transition"
                                     loading="lazy">
                            </a>
                            <?php endif; ?>

                            <!-- Date -->
                            <span class="text-xs text-gray-400 flex-shrink-0">
                                <?= date('d M', strtotime($task['createdat'])) ?>
                            </span>

                            <!-- Actions -->
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <button type="button" class="p-1 hover:bg-gray-200 rounded text-sm" title="Edit"
                                        data-id="<?= $task['taskid'] ?>"
                                        data-backlogid="<?= $task['backlogid'] ?? '' ?>"
                                        data-description="<?= e($task['description']) ?>"
                                        data-imagepath="<?= e($task['imagepath'] ?? '') ?>"
                                        onclick="openEditModal(this)">
                                    ✏️
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this task?');">
                                    <input type="hidden" name="deleteid" value="<?= $task['taskid'] ?>">
                                    <button type="submit" class="p-1 hover:bg-red-100 rounded text-sm" title="Delete">🗑️</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($grouped)): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center text-sm text-gray-500">
                    No tasks yet! Click the + button to add one.
                </div>
            <?php endif; ?>

        </div>

    <!-- Floating Action Button -->
    <button onclick="openAddModal()" 
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Add Task">
        +
    </button>

    <?php $mode = 'add'; include '../components/task_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/task_modal.php'; ?>

    <script>
        function previewImage(input, previewId) {
            var preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('hidden');
            }
        }

        function openEditModal(button) {
            document.getElementById('modal_id').value = button.getAttribute('data-id');
            document.getElementById('modal_backlogid').value = button.getAttribute('data-backlogid');
            document.getElementById('modal_description').value = button.getAttribute('data-description');

            var imagepath = button.getAttribute('data-imagepath');
            document.getElementById('modal_existing_imagepath').value = imagepath;

            var preview = document.getElementById('editPreview');
            var currentLabel = document.getElementById('editCurrentImage');
            if (imagepath) {
                preview.src = imagepath;
                preview.classList.remove('hidden');
                currentLabel.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
                currentLabel.classList.add('hidden');
            }

            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex';
        }
    </script>

<?php include '../components/layout_footer.php'; ?>
<script>initScrollRestore('tasks_scroll');</script>
