<?php
require_once '../includes/bootstrap.php';

$current_section = 'tools';
$current_page    = 'tool_inventory';
$tool_id         = (int)($_GET['id'] ?? 0);

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
    if (isset($_POST['csrf_token']) && !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('❌ Security validation failed (invalid CSRF token)');
        header("Location: /tool/$tool_id");
        exit;
    }

    if (isset($_POST['delete_log_id'])) {
        $success = delete_tool_log($conn, $_POST['delete_log_id']);
        set_flash_message($success ? '✅ Log entry deleted' : '❌ Error deleting log entry');
        header("Location: /tool/$tool_id");
        exit;
    }
    
    // Handle log creation
    $imagepath = '';
    if (!empty($_FILES['image']['name'])) {
        $uploaded = handle_image_upload($_FILES['image'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
        if (!$uploaded['success']) {
            set_flash_message('❌ ' . $uploaded['error']);
            header("Location: /tool/$tool_id");
            exit;
        }
        $imagepath = $uploaded['path'];
    }
    $_POST['toolid'] = $tool_id;
    $_POST['imagepath'] = $imagepath;
    $success = add_tool_log($conn, $_POST);
    set_flash_message($success ? '✅ Log entry added' : '❌ Error adding log entry');
    header("Location: /tool/$tool_id");
    exit;
}

$page_title   = $tool['name'];
$logs         = get_tool_logs($conn, $tool_id);
$backlog_kits = get_backlog_kits_for_log_dropdown($conn); // For assigning usage to active kits
$log_types    = get_tool_log_types();
$csrf_token   = csrf_token();
$message      = get_flash_message();

// Separate logs into test samples and operational logs
$test_samples = array_values(array_filter($logs, fn($l) => strcasecmp($l['log_type'] ?? '', 'Test Sample') === 0));

// Count categories for filter pills
$usage_count       = count(array_filter($logs, fn($l) => strcasecmp($l['log_type'] ?? '', 'Usage') === 0));
$maintenance_count = count(array_filter($logs, fn($l) => strcasecmp($l['log_type'] ?? '', 'Maintenance') === 0));
$sample_count      = count($test_samples);

// Status Badge colors
$status_label = $tool['status'] ?? '-';
$status_lower = strtolower($status_label);
if (str_contains($status_lower, 'new') || str_contains($status_lower, 'good') || str_contains($status_lower, 'prime')) {
    $status_class = 'bg-green-100 text-green-800 border border-green-200';
} elseif (str_contains($status_lower, 'worn')) {
    $status_class = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
} elseif (str_contains($status_lower, 'needs')) {
    $status_class = 'bg-orange-100 text-orange-800 border border-orange-200';
} elseif (str_contains($status_lower, 'broken') || str_contains($status_lower, 'retired')) {
    $status_class = 'bg-red-100 text-red-800 border border-red-200';
} else {
    $status_class = 'bg-gray-100 text-gray-800 border border-gray-200';
}
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-7xl mx-auto w-full">
    <!-- Top Navigation Bar -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-2">
        <a href="/tool_inventory" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-sm font-semibold transition">
            ← Back to Tool Inventory
        </a>
        <div class="flex items-center gap-2">
            <button type="button" onclick="openLogModal('Test Sample')"
                    class="inline-flex items-center gap-1.5 bg-indigo-50 hover:bg-indigo-600 hover:text-white text-indigo-700 font-bold px-3.5 py-2 rounded-lg border border-indigo-200 shadow-2xs text-xs sm:text-sm transition-all">
                <span>🔬</span> Add Test Sample
            </button>
            <button type="button" onclick="openLogModal('Usage')"
                    class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg shadow-sm text-xs sm:text-sm transition-all">
                <span>➕</span> Log Activity
            </button>
        </div>
    </div>

    <?php include '../components/toast.php'; ?>

    <!-- Tool Profile Information Card -->
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
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                        Brand: <?= e($tool['brand'] ?? '-') ?>
                    </span>
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full bg-purple-100 text-purple-800 border border-purple-200">
                        Category: <?= e($tool['category'] ?? '-') ?>
                    </span>
                    <span class="px-2.5 py-1 text-xs font-bold rounded-full <?= $status_class ?>">
                        Status: <?= e($status_label) ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-4">
                    <div>
                        <span class="font-semibold text-gray-500 text-xs uppercase tracking-wider block">Quantity</span>
                        <p class="font-medium text-gray-800 mt-0.5"><?= e($tool['quantity']) ?> <?= e($tool['unit'] ?? 'pcs') ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500 text-xs uppercase tracking-wider block">Price bought</span>
                        <p class="font-medium text-gray-800 mt-0.5"><?= $tool['pricebought'] ? format_currency($tool['pricebought']) : '-' ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500 text-xs uppercase tracking-wider block">Date bought</span>
                        <p class="font-medium text-gray-800 mt-0.5"><?= !empty($tool['datebought']) ? date('d M Y', strtotime($tool['datebought'])) : '-' ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500 text-xs uppercase tracking-wider block">Purchase Link</span>
                        <p class="mt-0.5">
                            <?php if (!empty($tool['link'])): ?>
                            <a href="<?= e($tool['link']) ?>" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline font-medium inline-flex items-center gap-1">
                                <span>🔗</span> Store Link
                            </a>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($tool['notes'])): ?>
                <div class="p-3.5 bg-gray-50 border border-gray-200/80 rounded-lg">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Workshop Notes</span>
                    <p class="text-sm text-gray-700 whitespace-pre-line"><?= e($tool['notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dedicated Performance & Test Samples Card -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
            <div>
                <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span>🔬</span> Performance Evaluation &amp; Test Samples
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Macro cut tests, airbrush spray pattern cards, scribed panel lines, and sanding finish samples.</p>
            </div>
            <button type="button" onclick="openLogModal('Test Sample')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-2xs transition-all">
                <span>➕</span> Add Test Sample
            </button>
        </div>

        <?php if (!empty($test_samples)): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($test_samples as $sample): ?>
                    <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden shadow-2xs hover:shadow-md transition-all flex flex-col justify-between">
                        <!-- Image Container with Aspect Ratio -->
                        <div class="relative bg-gray-900 aspect-video overflow-hidden group">
                            <?php if (!empty($sample['imagepath'])): ?>
                                <a href="<?= e($sample['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="block w-full h-full" title="View macro photo">
                                    <img src="<?= e($sample['imagepath']) ?>" alt="Test Sample Photo"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                </a>
                                <span class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded text-[10px] bg-black/60 text-white backdrop-blur-xs font-mono font-medium">
                                    🔍 Zoom
                                </span>
                            <?php else: ?>
                                <div class="w-full h-full flex flex-col items-center justify-center text-gray-400 bg-gray-100 p-4">
                                    <span class="text-3xl mb-1">🔬</span>
                                    <span class="text-xs font-semibold text-gray-500">No Photo Attached</span>
                                </div>
                            <?php endif; ?>
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200 shadow-2xs">
                                🔬 Test Sample
                            </span>
                        </div>

                        <!-- Card Content & Evaluation Notes -->
                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-xs text-gray-500 font-mono mb-2">
                                    <span>🗓️ <?= date('d M Y', strtotime($sample['log_date'])) ?></span>
                                    <?php if (!empty($sample['kit_name'])): ?>
                                        <span class="text-[11px] text-gray-600 truncate max-w-[120px]" title="<?= e($sample['kit_name']) ?>">
                                            🤖 <?= e($sample['kit_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-700 whitespace-pre-line leading-relaxed">
                                    <?= nl2br(e($sample['notes'])) ?>
                                </div>
                            </div>

                            <!-- Delete Action -->
                            <div class="pt-3 mt-3 border-t border-gray-200 flex justify-end">
                                <form method="POST" onsubmit="return confirm('Delete this test sample entry?');" class="inline m-0">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                    <input type="hidden" name="delete_log_id" value="<?= e($sample['logid']) ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 text-xs text-red-500 hover:text-red-700 font-semibold px-2 py-1 rounded hover:bg-red-50 transition">
                                        <span>🗑️</span> Delete Sample
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl p-8 text-center">
                <div class="text-4xl mb-2">🔬</div>
                <h4 class="text-sm font-bold text-gray-800">No test samples logged yet</h4>
                <p class="text-xs text-gray-500 mt-1 max-w-md mx-auto">
                    Document your test cuts, spray patterns, or scribed lines.
                </p>
                <button type="button" onclick="openLogModal('Test Sample')"
                        class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm transition-all">
                    <span>➕</span> Add First Sample
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Usage & Maintenance Logs Timeline -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3">
            <div>
                <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <span>📜</span> Usage &amp; Maintenance Logs
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Chronological history of workshop servicing, sharpening, and build logs.</p>
            </div>

            <!-- Quick Filter Pills -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" onclick="filterLogs('all', this)"
                        class="log-filter-btn px-3 py-1 text-xs font-bold rounded-lg border border-blue-600 bg-blue-600 text-white shadow-2xs transition-all">
                    All Logs (<?= count($logs) ?>)
                </button>
                <button type="button" onclick="filterLogs('usage', this)"
                        class="log-filter-btn px-3 py-1 text-xs font-semibold rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 transition-all">
                    📦 Kit Usage (<?= $usage_count ?>)
                </button>
                <button type="button" onclick="filterLogs('maintenance', this)"
                        class="log-filter-btn px-3 py-1 text-xs font-semibold rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 transition-all">
                    🛠️ Maintenance (<?= $maintenance_count ?>)
                </button>
                <button type="button" onclick="filterLogs('test sample', this)"
                        class="log-filter-btn px-3 py-1 text-xs font-semibold rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-100 transition-all">
                    🔬 Test Samples (<?= $sample_count ?>)
                </button>
            </div>
        </div>
        
        <?php if (!empty($logs)): ?>
        <div class="space-y-4" id="log-timeline-container">
            <?php foreach ($logs as $log): ?>
            <?php 
            $type = strtolower($log['log_type'] ?? 'general');
            $raw_type = $log['log_type'] ?? 'General';
            $badge_class = get_log_type_badge_class($raw_type);
            ?>
            <div class="log-item bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-2xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4 transition-all"
                 data-log-type="<?= e($type) ?>">
                <div class="flex items-start gap-4 flex-1">
                    <?php if (!empty($log['imagepath'])): ?>
                    <a href="<?= e($log['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="flex-shrink-0" title="Click to view full photo">
                        <img src="<?= e($log['imagepath']) ?>" alt="Log Swatch" class="w-16 h-16 object-cover rounded-lg border border-gray-300 shadow-2xs hover:opacity-90 transition">
                    </a>
                    <?php endif; ?>
                    <div>
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="px-2.5 py-0.5 text-xs font-bold rounded-full border <?= $badge_class ?>">
                                <?= e($log['log_type']) ?>
                            </span>
                            <span class="text-xs text-gray-500 font-mono">🗓️ <?= date('d M Y', strtotime($log['log_date'])) ?></span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line"><?= e($log['notes']) ?></p>
                        <?php if (!empty($log['kit_name'])): ?>
                        <p class="text-xs text-blue-600 mt-1 font-medium">
                            🔗 Used on: 
                            <?php if (!empty($log['inventory_id'])): ?>
                                <a href="/kit/<?= (int)$log['inventory_id'] ?>" class="underline font-bold hover:text-blue-800"><?= e($log['kit_name']) ?></a>
                            <?php else: ?>
                                <span class="font-bold text-gray-700"><?= e($log['kit_name']) ?></span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST" onsubmit="return confirm('Delete this log entry?');" class="inline m-0 flex-shrink-0">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="delete_log_id" value="<?= e($log['logid']) ?>">
                    <button type="submit" class="text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-300 px-2.5 py-1 rounded bg-red-50 transition">
                        🗑️ Delete
                    </button>
                </form>
            </div>
            <?php endforeach; ?>

            <div id="timeline-empty-notice" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-6 text-center text-gray-500 text-sm">
                No logs found matching this filter.
            </div>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center text-gray-500">
            No maintenance or usage logs recorded yet. Add your first log entry!
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Log Modal -->
<div id="logModal" role="dialog" aria-modal="true" aria-labelledby="modal-log-title"
     class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50 p-4" style="display:none;"
     onclick="if (event.target === this) closeLogModal()">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg overflow-hidden modal-animate">
        <div class="bg-blue-600 text-white px-5 py-3.5 flex justify-between items-center">
            <h3 id="modal-log-title" class="font-bold text-base flex items-center gap-2">
                <span>📝</span> Record Tool Activity &amp; Test Samples
            </h3>
            <button type="button" onclick="closeLogModal()" class="text-white hover:text-blue-100 text-2xl font-bold leading-none">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Log Type *</label>
                <select name="log_type" id="modal_log_type" required onchange="handleLogTypeChange()"
                        class="w-full border border-gray-300 p-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                    <?php foreach ($log_types as $lt): ?>
                        <option value="<?= e($lt) ?>"><?= e($lt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Log Date *</label>
                <input type="date" name="log_date" value="<?= date('Y-m-d') ?>" required
                       class="w-full border border-gray-300 p-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div id="modal_backlog_group">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Associated Kit (Optional)</label>
                <select name="backlogid" id="modal_backlogid" class="w-full border border-gray-300 p-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                    <option value="">-- None / Scrap Plastic Test --</option>
                    <?php foreach ($backlog_kits as $kit): ?>
                    <option value="<?= $kit['actualid'] ?>"><?= e($kit['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-gray-400 mt-0.5">Select a kit if this tool log was performed directly on a specific build.</p>
            </div>

            <div>
                <label id="modal_notes_label" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Notes / Evaluation *</label>
                <textarea name="notes" id="modal_notes" required rows="3"
                          class="w-full border border-gray-300 p-2 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                          placeholder="Describe the maintenance action or tool usage details..."></textarea>
            </div>

            <div>
                <label id="modal_photo_label" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Photo Upload (Optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
                       class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p id="modal_photo_hint" class="text-[11px] text-gray-400 mt-0.5">Attach sprue cut macro, spray test card, or scribed line photo.</p>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                <button type="button" onclick="closeLogModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-lg text-sm transition">Cancel</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-lg text-sm transition shadow-sm">Save Log</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openLogModal(defaultType = 'Usage') {
        const typeSelect = document.getElementById('modal_log_type');
        if (typeSelect && defaultType) {
            typeSelect.value = defaultType;
            handleLogTypeChange();
        }
        const modal = document.getElementById('logModal');
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
    }

    function closeLogModal() {
        const modal = document.getElementById('logModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
        }
    }

    function handleLogTypeChange() {
        const typeSelect = document.getElementById('modal_log_type');
        const backlogGroup = document.getElementById('modal_backlog_group');
        const notesInput = document.getElementById('modal_notes');
        const notesLabel = document.getElementById('modal_notes_label');
        const photoLabel = document.getElementById('modal_photo_label');
        const photoHint = document.getElementById('modal_photo_hint');

        if (!typeSelect) return;
        const isTestSample = (typeSelect.value === 'Test Sample');

        if (backlogGroup) {
            if (isTestSample) {
                backlogGroup.classList.add('hidden');
                const backlogSelect = document.getElementById('modal_backlogid');
                if (backlogSelect) backlogSelect.value = '';
            } else {
                backlogGroup.classList.remove('hidden');
            }
        }

        if (isTestSample) {
            if (notesLabel) notesLabel.innerHTML = 'Performance Evaluation Notes *';
            if (notesInput) notesInput.placeholder = 'Document cut smoothness, stress marks, spray atomization, scribing sharpness, or finish quality...';
            if (photoLabel) photoLabel.innerHTML = 'Macro Sample Photo (Recommended)';
            if (photoHint) photoHint.textContent = 'Upload a clear macro shot of runner cuts, spray cards, or scribed lines.';
        } else {
            if (notesLabel) notesLabel.innerHTML = 'Notes / Description *';
            if (notesInput) notesInput.placeholder = 'Describe the maintenance action or tool usage details...';
            if (photoLabel) photoLabel.innerHTML = 'Photo Upload (Optional)';
            if (photoHint) photoHint.textContent = 'Attach photo of the tool or workbench session.';
        }
    }

    function filterLogs(type, btn) {
        document.querySelectorAll('.log-filter-btn').forEach(b => {
            b.classList.remove('border-blue-600', 'bg-blue-600', 'text-white', 'shadow-2xs');
            b.classList.add('border-gray-200', 'bg-white', 'text-gray-600', 'hover:bg-gray-100');
        });
        if (btn) {
            btn.classList.remove('border-gray-200', 'bg-white', 'text-gray-600', 'hover:bg-gray-100');
            btn.classList.add('border-blue-600', 'bg-blue-600', 'text-white', 'shadow-2xs');
        }

        const items = document.querySelectorAll('.log-item');
        let visibleCount = 0;
        const normalizedFilter = type.toLowerCase();

        items.forEach(item => {
            const itemType = (item.getAttribute('data-log-type') || '').toLowerCase();
            if (normalizedFilter === 'all' || itemType === normalizedFilter) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        const emptyNotice = document.getElementById('timeline-empty-notice');
        if (emptyNotice) {
            emptyNotice.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    // Escape Key Dismiss
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLogModal();
        }
    });
</script>

<?php include '../components/layout_footer.php'; ?>
