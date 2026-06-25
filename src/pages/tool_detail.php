<?php
require_once '../includes/bootstrap.php';

$current_section = 'tools';
$current_page = 'tool_inventory';
$tool_id = (int)($_GET['id'] ?? 0);

$tool = get_tool_by_id($conn, $tool_id);

if (!$tool) {
    http_response_code(404);
    $page_title = 'Tool Not Found';
    include '../components/layout_header.php';
    echo "<div class='max-w-7xl mx-auto text-center py-20'>";
    echo "<h1 class='text-4xl mb-4'>🔧 Tool not found</h1>";
    echo "<a href='/tool_inventory' class='text-blue-500 hover:underline'>← Back to Tool Inventory</a>";
    echo "</div>";
    include '../components/layout_footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_log_id'])) {
        $success = delete_tool_log($conn, $_POST['delete_log_id']);
        set_flash_message($success ? '✅ Log entry deleted' : '❌ Error deleting log entry');
        header("Location: /tool/$tool_id"); exit;
    }
    
    // Handle log creation
    $imagepath = '';
    if (!empty($_FILES['image']['name'])) {
        $uploaded = handle_image_upload($_FILES['image'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
        if (!$uploaded['success']) {
            set_flash_message('❌ ' . $uploaded['error']);
            header("Location: /tool/$tool_id"); exit;
        }
        $imagepath = $uploaded['path'];
    }
    $_POST['toolid'] = $tool_id;
    $_POST['imagepath'] = $imagepath;
    $success = add_tool_log($conn, $_POST);
    set_flash_message($success ? '✅ Log entry added' : '❌ Error adding log entry');
    header("Location: /tool/$tool_id"); exit;
}

$page_title = $tool['name'];
$logs = get_tool_logs($conn, $tool_id);
$backlog_kits = get_backlog_items($conn); // For assigning usage to kits

$message = get_flash_message();

// Status Badge colors
$status_label = $tool['status'] ?? '-';
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
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-7xl mx-auto w-full">
    <div class="flex items-center justify-between mb-6">
        <a href="/tool_inventory" class="text-blue-500 hover:underline text-sm">← Back to Tool Inventory</a>
        <button onclick="openLogModal()" class="bg-blue-500 hover:bg-blue-600 text-white font-bold px-4 py-2 rounded shadow text-sm">
            ➕ Add Log Entry
        </button>
    </div>

    <?php include '../components/toast.php'; ?>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex flex-col md:flex-row gap-6">
            <?php if (!empty($tool['imagepath'])): ?>
            <div class="flex-shrink-0">
                <a href="<?= e($tool['imagepath']) ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?= e($tool['imagepath']) ?>" alt="<?= e($tool['name']) ?>"
                         class="w-48 h-48 object-cover rounded-lg border shadow-sm hover:opacity-90 transition">
                </a>
            </div>
            <?php endif; ?>

            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-800 mb-2"><?= e($tool['name']) ?></h1>
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800">
                        Brand: <?= e($tool['brand'] ?? '-') ?>
                    </span>
                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-purple-100 text-purple-800">
                        Category: <?= e($tool['category'] ?? '-') ?>
                    </span>
                    <span class="px-2 py-1 text-xs font-bold rounded-full <?= $status_class ?>">
                        Status: <?= e($status_label) ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-4">
                    <div>
                        <span class="font-semibold text-gray-500">Quantity</span>
                        <p><?= e($tool['quantity']) ?> <?= e($tool['unit'] ?? 'pcs') ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500">Price bought</span>
                        <p><?= $tool['pricebought'] ? format_currency($tool['pricebought']) : '-' ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500">Date bought</span>
                        <p><?= !empty($tool['datebought']) ? date('d M Y', strtotime($tool['datebought'])) : '-' ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500">Purchase Link</span>
                        <p>
                            <?php if (!empty($tool['link'])): ?>
                            <a href="<?= e($tool['link']) ?>" target="_blank" rel="noopener noreferrer" class="text-blue-500 hover:underline">Buy Link</a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($tool['notes'])): ?>
                <div class="p-3 bg-gray-50 rounded-lg">
                    <span class="text-xs font-semibold text-gray-500 uppercase">Notes</span>
                    <p class="text-sm text-gray-700 mt-1"><?= e($tool['notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-xl font-bold text-gray-700 mb-4">📜 Usage & Maintenance Logs</h3>
        
        <?php if (!empty($logs)): ?>
        <div class="space-y-4">
            <?php foreach ($logs as $log): ?>
            <?php 
            $type = strtolower($log['log_type']);
            if ($type === 'maintenance') {
                $badge_class = 'bg-yellow-100 text-yellow-800';
            } elseif ($type === 'usage') {
                $badge_class = 'bg-green-100 text-green-800';
            } else {
                $badge_class = 'bg-gray-100 text-gray-800';
            }
            ?>
            <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div class="flex items-start gap-4 flex-1">
                    <?php if (!empty($log['imagepath'])): ?>
                    <a href="<?= e($log['imagepath']) ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?= e($log['imagepath']) ?>" alt="Log Swatch" class="w-16 h-16 object-cover rounded border">
                    </a>
                    <?php endif; ?>
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $badge_class ?>">
                                <?= e($log['log_type']) ?>
                            </span>
                            <span class="text-xs text-gray-400"><?= date('d M Y', strtotime($log['log_date'])) ?></span>
                        </div>
                        <p class="text-sm text-gray-700"><?= e($log['notes']) ?></p>
                        <?php if (!empty($log['kit_name'])): ?>
                        <p class="text-xs text-blue-500 mt-1">
                            🔗 Used on: <a href="/kit/<?= $log['inventory_id'] ?>" class="underline font-semibold"><?= e($log['kit_name']) ?></a>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" onsubmit="return confirm('Delete this log entry?');">
                    <input type="hidden" name="delete_log_id" value="<?= $log['logid'] ?>">
                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 border border-red-100 hover:border-red-300 px-2.5 py-1 rounded bg-red-50">
                        🗑️ Delete
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center text-gray-500">
            No maintenance or usage logs recorded yet. Add your first log entry!
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Log Modal -->
<div id="logModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 justify-center items-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md overflow-hidden">
        <div class="bg-blue-500 text-white px-4 py-3 flex justify-between items-center">
            <h3 class="font-bold">Add Tool Log Entry</h3>
            <button onclick="closeLogModal()" class="text-white hover:text-gray-200 text-xl font-bold">×</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-4 space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Log Type</label>
                <select name="log_type" required class="w-full border border-gray-300 p-2 rounded text-sm">
                    <option value="Usage">Usage (Logged against build)</option>
                    <option value="Maintenance">Maintenance (Clean, sharpen, lubricate)</option>
                    <option value="General">General / Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Log Date</label>
                <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required class="w-full border border-gray-300 p-2 rounded text-sm">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Associated Kit (Optional)</label>
                <select name="backlogid" class="w-full border border-gray-300 p-2 rounded text-sm">
                    <option value="">-- None / General --</option>
                    <?php foreach ($backlog_kits as $kit): ?>
                    <option value="<?= $kit['actualid'] ?>"><?= e($kit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Notes</label>
                <textarea name="notes" required class="w-full border border-gray-300 p-2 rounded text-sm h-24" placeholder="Describe the maintenance action or tool usage details..."></textarea>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">Photo Upload (Optional)</label>
                <input type="file" name="image" accept="image/*" class="w-full text-xs">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeLogModal()" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded text-sm">Cancel</button>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold px-4 py-2 rounded text-sm">Save Log</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openLogModal() {
        document.getElementById('logModal').style.display = 'flex';
        document.getElementById('logModal').classList.remove('hidden');
    }
    function closeLogModal() {
        document.getElementById('logModal').style.display = 'none';
        document.getElementById('logModal').classList.add('hidden');
    }
</script>

<?php include '../components/layout_footer.php'; ?>
