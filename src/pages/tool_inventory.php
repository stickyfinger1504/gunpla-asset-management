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

    // Quick restock / quantity increment & decrement
    if (($_POST['action_type'] ?? '') === 'quick_restock') {
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $delta = (int)($_POST['delta'] ?? 1);
        if ($tool_id > 0 && $delta !== 0) {
            $success = update_tool_quantity($conn, $tool_id, $delta);
            $sign = $delta > 0 ? "+{$delta}" : "{$delta}";
            set_flash_message($success ? "✅ Quantity updated ({$sign})" : "❌ Error updating quantity");
        }
        header('Location: /tool_inventory'); exit;
    }

    // Quick status update (e.g., Mark Serviced / Good)
    if (($_POST['action_type'] ?? '') === 'quick_status_update') {
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $status_id = (int)($_POST['status_id'] ?? 0);
        $log_note = trim($_POST['log_note'] ?? '');
        if ($tool_id > 0 && $status_id > 0) {
            $success = update_tool_status($conn, $tool_id, $status_id);
            if ($success && !empty($log_note)) {
                add_tool_log($conn, [
                    'toolid'   => $tool_id,
                    'log_type' => 'Maintenance',
                    'log_date' => date('Y-m-d'),
                    'notes'    => $log_note,
                ]);
            }
            set_flash_message($success ? '✅ Tool restored to Good condition' : '❌ Error updating tool status');
        }
        header('Location: /tool_inventory'); exit;
    }

    // Quick service log submission
    if (($_POST['action_type'] ?? '') === 'quick_log') {
        $tool_id = (int)($_POST['tool_id'] ?? 0);
        $log_type = $_POST['log_type'] ?? 'Maintenance';
        $notes = trim($_POST['notes'] ?? '');
        if ($tool_id > 0 && !empty($notes)) {
            $success = add_tool_log($conn, [
                'toolid'   => $tool_id,
                'log_type' => $log_type,
                'log_date' => date('Y-m-d'),
                'notes'    => $notes,
            ]);
            set_flash_message($success ? '✅ Service log recorded' : '❌ Error recording log');
        } else {
            set_flash_message('⚠️ Please select a tool and enter notes for the service log');
        }
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
        if (($_POST['action_type'] ?? '') === 'edit' && !empty($_POST['existing_imagepath'])) {
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

// Resolve Workbench Tool Hub datasets
$all_tools        = $has_filters ? get_tool_inventory($conn) : $tools;
$latest_tool_logs = get_latest_logs_by_tool($conn);
$recent_logs      = get_recent_tool_logs($conn, 4);

// Find 'Good' status ID for rapid 1-click restoration
$good_status_id = null;
foreach ($tool_statuses as $st) {
    if (stripos($st['label'], 'Good') !== false) {
        $good_status_id = (int)$st['id'];
        break;
    }
}
if (!$good_status_id && !empty($tool_statuses)) {
    $good_status_id = (int)$tool_statuses[0]['id'];
}

// Classify tools needing attention & build supply run checklist
$low_or_degraded = [];
$supply_run_items = [];

foreach ($all_tools as $t) {
    $st_lower = strtolower($t['status'] ?? '');
    $qty = (int)($t['quantity'] ?? 1);
    $is_low = $qty <= 3;
    $is_degraded = str_contains($st_lower, 'worn') || 
                   str_contains($st_lower, 'needs') || 
                   str_contains($st_lower, 'broken') || 
                   str_contains($st_lower, 'retired');

    if ($is_low || $is_degraded) {
        $low_or_degraded[] = $t;
        $reasons = [];
        if ($is_degraded) $reasons[] = "Status: " . ($t['status'] ?? 'Needs service');
        if ($is_low) $reasons[] = "Stock: " . $qty . (!empty($t['unit']) ? ' ' . $t['unit'] : '') . " left";
        
        $supply_run_items[] = [
            'id'       => (int)$t['actualid'],
            'name'     => $t['name'],
            'brand'    => $t['brand'] ?? 'Unknown',
            'category' => $t['category'] ?? 'Tool',
            'qty'      => $qty,
            'unit'     => $t['unit'] ?? '',
            'status'   => $t['status'] ?? '',
            'reasons'  => implode(' | ', $reasons),
        ];
    }
}

// Radar pool prioritizes tools needing attention; falls back to workbench tools
$has_radar_alerts = count($low_or_degraded) > 0;
$radar_tools = $has_radar_alerts ? $low_or_degraded : array_slice($all_tools, 0, 10);

$radar_items = [];
foreach ($radar_tools as $t) {
    $st_lower = strtolower($t['status'] ?? '');
    $qty = (int)($t['quantity'] ?? 1);
    $is_low = $qty <= 3;
    $is_degraded = str_contains($st_lower, 'worn') || 
                   str_contains($st_lower, 'needs') || 
                   str_contains($st_lower, 'broken') || 
                   str_contains($st_lower, 'retired');

    $status_label = $t['status'] ?? '-';
    if (str_contains($st_lower, 'new') || str_contains($st_lower, 'good')) {
        $st_class = 'bg-green-100 text-green-800 border-green-200';
    } elseif (str_contains($st_lower, 'worn')) {
        $st_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
    } elseif (str_contains($st_lower, 'needs')) {
        $st_class = 'bg-orange-100 text-orange-800 border-orange-200';
    } elseif (str_contains($st_lower, 'broken') || str_contains($st_lower, 'retired')) {
        $st_class = 'bg-red-100 text-red-800 border-red-200';
    } else {
        $st_class = 'bg-gray-100 text-gray-800 border-gray-200';
    }

    $tool_id = (int)$t['actualid'];
    $last_log = $latest_tool_logs[$tool_id] ?? null;

    $radar_items[] = [
        'id'             => $tool_id,
        'name'           => $t['name'],
        'brand'          => $t['brand'] ?? '-',
        'brand_class'    => get_brand_color_palette($t['brandid']),
        'category'       => $t['category'] ?? '-',
        'category_class' => get_brand_color_palette($t['categoryid']),
        'status'         => $status_label,
        'status_id'      => (int)($t['statusid'] ?? 0),
        'status_class'   => $st_class,
        'is_degraded'    => $is_degraded,
        'is_low'         => $is_low,
        'quantity'       => $qty,
        'unit'           => $t['unit'] ?? '',
        'imagepath'      => $t['imagepath'] ?? '',
        'last_log_date'  => $last_log ? date('M j, Y', strtotime($last_log['log_date'])) : null,
        'last_log_type'  => $last_log ? $last_log['log_type'] : null,
        'last_log_notes' => $last_log ? $last_log['notes'] : null,
        'modal_data'     => [
            'id'          => $t['actualid'],
            'name'        => $t['name'],
            'brand'       => $t['brandid'] ?? '',
            'category'    => $t['categoryid'] ?? '',
            'status'      => $t['statusid'] ?? '',
            'quantity'    => $t['quantity'],
            'unit'        => $t['unit'] ?? '',
            'pricebought' => $t['pricebought'] ?? '',
            'datebought'  => !empty($t['datebought']) ? date('Y-m-d', strtotime($t['datebought'])) : '',
            'link'        => $t['link'] ?? '',
            'notes'       => $t['notes'] ?? '',
            'imagepath'   => $t['imagepath'] ?? '',
        ]
    ];
}
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-7xl mx-auto w-full">
    <h1 class="page-title font-bold text-gray-700 text-center mb-8">🔧 Tool Inventory</h1>

    <?php include '../components/toast.php'; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php
        $value = $stats['total_tools'];
        $label = $has_filters ? 'Filtered Tools' : 'Total Tools';
        $color = 'blue'; include '../components/stats/stat_card.php';

        $value = $stats['prime_condition'];
        $label = 'Prime Condition';
        $color = 'green'; include '../components/stats/stat_card.php';

        $value = $stats['attention_needed'];
        $label = 'Needs Care / Low';
        $color = $stats['attention_needed'] > 0 ? 'red' : 'green'; include '../components/stats/stat_card.php';

        $value = format_currency($stats['total_spent']);
        $label = 'Total Invested';
        $color = 'yellow'; include '../components/stats/stat_card.php';
        ?>
    </div>

    <!-- Workbench Tool Hub -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span>🧰</span> Workbench Tool Hub
            </h3>
            <span class="text-xs text-gray-500 font-medium hidden sm:inline">Tool maintenance radar, rapid service logging & supply run dispatcher</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Panel 1: 🚦 Tool Health & Supply Radar -->
            <?php
            $radar_border = $has_radar_alerts ? 'border-amber-300' : 'border-emerald-300';
            $radar_gradient = $has_radar_alerts ? 'from-white to-amber-50/40' : 'from-white to-emerald-50/40';
            $radar_badge = $has_radar_alerts ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300';
            $radar_pulse = $has_radar_alerts ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500';
            ?>
            <div class="bg-gradient-to-br <?= $radar_gradient ?> border-2 <?= $radar_border ?> rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <!-- Radar Header -->
                    <div class="flex items-center justify-between mb-3 gap-2 flex-wrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?= $radar_badge ?> border">
                            <span class="w-2 h-2 rounded-full <?= $radar_pulse ?>"></span>
                            <?= $has_radar_alerts ? '⚠️ Maintenance Radar (' . count($low_or_degraded) . ')' : '✨ All Tools Prime' ?>
                        </span>

                        <?php if (!empty($radar_items)): ?>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="stepRadarTool(-1)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 text-xs font-bold transition shadow-2xs" title="Previous tool">◀</button>
                            <span id="radar-counter" class="text-xs font-mono font-bold text-gray-600">1 of <?= count($radar_items) ?></span>
                            <button type="button" onclick="stepRadarTool(1)" class="w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 text-xs font-bold transition shadow-2xs" title="Next tool">▶</button>
                            <select id="radar-tool-select" onchange="selectRadarTool(parseInt(this.value))" class="text-xs bg-white border border-gray-300 rounded-lg px-2 py-1 font-medium max-w-[130px] sm:max-w-[160px] truncate focus:ring-1 focus:ring-blue-500">
                                <?php foreach ($radar_items as $idx => $item): ?>
                                    <option value="<?= $idx ?>"><?= htmlspecialchars($item['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($radar_items)): ?>
                        <!-- Dynamic Tool Card Container -->
                        <div id="radar-card" class="bg-white/95 rounded-lg border border-gray-200/80 p-4 shadow-2xs transition-all">
                            <div class="flex items-start gap-3.5">
                                <div id="radar-image-container" class="flex-shrink-0">
                                    <!-- Populated by JS -->
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a id="radar-tool-link" href="#" class="text-base font-bold text-gray-900 hover:text-blue-600 truncate"></a>
                                        <span id="radar-brand-badge" class="px-2 py-0.5 text-xs font-bold rounded-full"></span>
                                        <span id="radar-category-badge" class="px-2 py-0.5 text-xs font-bold rounded-full"></span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                        <span id="radar-status-badge" class="px-2 py-0.5 text-xs font-bold rounded-full border"></span>
                                        <span id="radar-qty-badge" class="px-2 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-800 border border-gray-200"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Last Service History Callout -->
                            <div class="mt-3 p-2.5 bg-gray-50/90 rounded-lg border border-gray-200/70 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-bold text-gray-700 flex items-center gap-1">
                                        <span>🕒</span> Latest Service:
                                    </span>
                                    <span id="radar-last-log-date" class="text-gray-400 font-mono text-[11px]"></span>
                                </div>
                                <div id="radar-last-log-text" class="text-gray-600 line-clamp-2"></div>
                            </div>

                            <!-- Action Toolbar for Selected Tool -->
                            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between gap-2 flex-wrap">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <form method="POST" class="inline m-0" id="radar-service-form">
                                        <input type="hidden" name="action_type" value="quick_status_update">
                                        <input type="hidden" name="tool_id" id="radar-form-tool-id" value="">
                                        <input type="hidden" name="status_id" value="<?= $good_status_id ?>">
                                        <input type="hidden" name="log_note" value="Marked as serviced & restored to Good condition.">
                                        <button type="submit" id="radar-btn-mark-good" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-2xs transition-colors" title="Mark tool as serviced & set status to Good">
                                            <span>✅</span> Mark Good
                                        </button>
                                    </form>

                                    <!-- Restock Buttons -->
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="action_type" value="quick_restock">
                                        <input type="hidden" name="tool_id" id="radar-form-tool-id-restock1" value="">
                                        <input type="hidden" name="delta" value="1">
                                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-bold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg transition-colors shadow-2xs" title="Add 1 to quantity">
                                            <span>⚡</span> +1
                                        </button>
                                    </form>
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="action_type" value="quick_restock">
                                        <input type="hidden" name="tool_id" id="radar-form-tool-id-restock5" value="">
                                        <input type="hidden" name="delta" value="5">
                                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-bold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg transition-colors shadow-2xs" title="Add 5 to quantity">
                                            <span>⚡</span> +5
                                        </button>
                                    </form>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <button type="button" onclick="syncAndSwitchToLog()" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg transition-colors shadow-2xs" title="Log service event for this tool">
                                        <span>📝</span> Log
                                    </button>
                                    <button type="button" onclick="editCurrentRadarTool()" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-lg transition-colors shadow-2xs" title="Edit full tool details">
                                        <span>✏️</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="py-8 text-center bg-white/90 rounded-lg border border-gray-200">
                            <div class="text-3xl mb-1.5">🔧</div>
                            <p class="text-sm font-bold text-gray-800">No Workshop Tools Yet</p>
                            <p class="text-xs text-gray-500 mt-1">Add your cutters, nippers, airbrush, and sanders to begin monitoring.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="pt-3 border-t border-gray-200/60 flex items-center justify-between text-xs text-gray-500 mt-3">
                    <span>💡 Use steppers to browse tools requiring care or low consumables</span>
                    <a href="#tools-table" class="hover:text-blue-600 underline font-medium">Scroll to tools list ↓</a>
                </div>
            </div>

            <!-- Panel 2: 🛠️ Quick Log & Supply Run Dispatcher -->
            <div class="bg-gradient-to-br from-white to-indigo-50/40 border-2 border-indigo-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <!-- Panel 2 Header: Tabs -->
                    <div class="flex items-center justify-between mb-3 border-b border-indigo-100 pb-2">
                        <div class="flex items-center gap-1 bg-indigo-100/70 p-1 rounded-lg">
                            <button type="button" id="tab-btn-log" onclick="switchToolHubTab('log')" class="px-3 py-1 text-xs font-bold rounded-md transition-all bg-white text-indigo-900 shadow-2xs">
                                📝 Quick Service Log
                            </button>
                            <button type="button" id="tab-btn-supply" onclick="switchToolHubTab('supply')" class="px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-600 hover:text-indigo-900">
                                🛒 Supply Run (<?= count($supply_run_items) ?>)
                            </button>
                        </div>

                        <div id="supply-run-copy-wrapper" class="hidden">
                            <?php if (!empty($supply_run_items)): ?>
                                <button type="button" id="copy-supply-btn" onclick="copySupplyList()" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold bg-white hover:bg-amber-50 text-amber-900 border border-amber-300 rounded-lg shadow-sm transition-all" title="Copy workshop supply checklist to clipboard">
                                    <span>📋</span> <span id="copy-supply-btn-text">Copy Supply List</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 1: Quick Service Log -->
                    <div id="tool-hub-tab-log">
                        <form method="POST" class="space-y-2.5">
                            <input type="hidden" name="action_type" value="quick_log">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-0.5">Target Tool</label>
                                    <select name="tool_id" id="quicklog-tool-select" required class="w-full text-xs p-1.5 border border-gray-300 rounded font-medium bg-white focus:ring-1 focus:ring-indigo-500">
                                        <?php foreach ($all_tools as $t): ?>
                                            <option value="<?= $t['actualid'] ?>">
                                                <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['brand'] ?? 'Tool') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-500 uppercase mb-0.5">Log Type</label>
                                    <select name="log_type" class="w-full text-xs p-1.5 border border-gray-300 rounded font-medium bg-white focus:ring-1 focus:ring-indigo-500">
                                        <option value="Maintenance">Maintenance</option>
                                        <option value="Usage">Usage</option>
                                        <option value="General">General</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Preset Action Chips -->
                            <div>
                                <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Quick Presets:</div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button type="button" onclick="setQuickLogText('🗡️ Blade Swapped')" class="px-2 py-0.5 text-[11px] font-medium bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full transition-colors shadow-2xs">🗡️ Blade Swapped</button>
                                    <button type="button" onclick="setQuickLogText('💧 Cleaned & Oiled')" class="px-2 py-0.5 text-[11px] font-medium bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full transition-colors shadow-2xs">💧 Cleaned & Oiled</button>
                                    <button type="button" onclick="setQuickLogText('🪥 Deep Cleaned')" class="px-2 py-0.5 text-[11px] font-medium bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full transition-colors shadow-2xs">🪥 Deep Cleaned</button>
                                    <button type="button" onclick="setQuickLogText('📐 Re-calibrated')" class="px-2 py-0.5 text-[11px] font-medium bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full transition-colors shadow-2xs">📐 Re-calibrated</button>
                                    <button type="button" onclick="setQuickLogText('🩹 Repaired')" class="px-2 py-0.5 text-[11px] font-medium bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full transition-colors shadow-2xs">🩹 Repaired</button>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <input type="text" id="quicklog-notes" name="notes" placeholder="Enter service notes or select a preset above..." required class="flex-1 text-xs p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 focus:outline-none bg-white">
                                <button type="submit" class="px-3.5 py-2 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors shadow-sm whitespace-nowrap">
                                    + Record Log
                                </button>
                            </div>
                        </form>

                        <!-- Activity Feed -->
                        <div class="mt-3 pt-3 border-t border-indigo-100">
                            <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2 flex items-center justify-between">
                                <span>🕒 Recent Workshop Logs</span>
                                <span class="text-[10px] text-gray-400 font-normal">Last <?= count($recent_logs) ?> recorded</span>
                            </div>
                            <?php if (!empty($recent_logs)): ?>
                                <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                                    <?php foreach ($recent_logs as $log): ?>
                                        <div class="p-1.5 bg-white/90 rounded border border-indigo-100/80 text-xs flex items-center justify-between gap-2 shadow-2xs">
                                            <div class="min-w-0 flex items-center gap-1.5">
                                                <span class="text-[10px] font-mono text-gray-400 font-semibold whitespace-nowrap"><?= date('m/d', strtotime($log['log_date'])) ?></span>
                                                <span class="font-bold text-gray-800 truncate"><?= htmlspecialchars($log['tool_name'] ?? 'Tool') ?>:</span>
                                                <span class="text-gray-600 truncate" title="<?= htmlspecialchars($log['notes'] ?? '') ?>"><?= htmlspecialchars($log['notes'] ?? '-') ?></span>
                                            </div>
                                            <span class="px-1.5 py-0.2 text-[10px] font-semibold rounded bg-gray-100 text-gray-600 flex-shrink-0">
                                                <?= htmlspecialchars($log['log_type']) ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-xs text-gray-400 italic text-center py-2">
                                    No workshop logs yet. Record your first blade swap or maintenance action above!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TAB 2: Supply Run Dispatcher -->
                    <div id="tool-hub-tab-supply" class="hidden">
                        <?php if (!empty($supply_run_items)): ?>
                            <p class="text-xs text-gray-600 mb-2 font-medium">
                                <span class="font-bold text-amber-800"><?= count($supply_run_items) ?> item<?= count($supply_run_items) !== 1 ? 's' : '' ?></span> need replenishment or blade/part replacement:
                            </p>
                            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                <?php foreach ($supply_run_items as $item): ?>
                                    <div class="flex items-center justify-between gap-2 p-2 bg-white/90 border border-amber-200/80 rounded-lg shadow-2xs hover:bg-white transition-all">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <a href="/tool/<?= $item['id'] ?>" class="text-xs font-bold text-gray-900 hover:text-blue-600 truncate">
                                                    <?= htmlspecialchars($item['name']) ?>
                                                </a>
                                                <span class="text-[10px] text-gray-400 font-semibold"><?= htmlspecialchars($item['brand']) ?></span>
                                            </div>
                                            <div class="text-[11px] text-amber-700 font-semibold mt-0.5">
                                                <?= htmlspecialchars($item['reasons']) ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1 flex-shrink-0">
                                            <form method="POST" class="inline m-0">
                                                <input type="hidden" name="action_type" value="quick_restock">
                                                <input type="hidden" name="tool_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="1">
                                                <button type="submit" class="px-2 py-0.5 text-xs font-bold bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 rounded transition shadow-2xs" title="Restock +1">
                                                    +1
                                                </button>
                                            </form>
                                            <form method="POST" class="inline m-0">
                                                <input type="hidden" name="action_type" value="quick_restock">
                                                <input type="hidden" name="tool_id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="5">
                                                <button type="submit" class="px-2 py-0.5 text-xs font-bold bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 rounded transition shadow-2xs" title="Restock +5">
                                                    +5
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-6 text-center">
                                <div class="text-3xl mb-1.5">✨</div>
                                <p class="text-sm font-bold text-gray-800">All tools & consumables well stocked!</p>
                                <p class="text-xs text-gray-500 mt-1">No tools are running low (qty ≤ 3) or marked as worn/broken. Your workshop is ready for action.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-3 border-t border-indigo-100 flex items-center justify-between text-xs text-gray-500 mt-3">
                    <span>⚡ Quick service logs keep your tool history accurate for every build</span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($stats['total_tools'] > 0): ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <?php
        $id = 'categoryChart'; $title = 'By Category'; include '../components/charts/chart_canvas.php';
        $id = 'brandChart';    $title = 'By Brand';    include '../components/charts/chart_canvas.php';
        ?>
    </div>
    <?php include '../components/charts/init_charts.php'; ?>
    <script>
    (function() {
        const catData   = <?= json_encode($stats['category_counts'], JSON_HEX_TAG) ?>;
        const brandData = <?= json_encode($stats['brand_counts'], JSON_HEX_TAG) ?>;
        initDoughnutChart('categoryChart', Object.keys(catData),   Object.values(catData));
        initDoughnutChart('brandChart',    Object.keys(brandData), Object.values(brandData));
    })();
    </script>
    <?php endif; ?>

    <div class="flex items-center justify-between mb-2" id="tools-table">
        <h3 class="text-xl font-bold text-gray-700">🔧 My Tools</h3>
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
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'text')">Name</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Brand</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Category</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 4, 'number')">Qty</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 5, 'number')">Price</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (count($tools) > 0): ?>

                    <?php foreach ($tools as $row): ?>
                        <?php
                        $safe_name  = htmlspecialchars($row['name'], ENT_QUOTES);
                        $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                        $brand_class = get_brand_color_palette($row['brandid']);
                        $cat_class   = get_brand_color_palette($row['categoryid']);

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
                            <td data-label="Name" class="px-4 py-3 text-sm font-semibold text-gray-800">
                                <?php if (!empty($row['imagepath'])): ?>
                                <a href="<?= e($row['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="mr-2">
                                    <img src="<?= e($row['imagepath']) ?>" alt="Tool"
                                         class="w-8 h-8 object-cover rounded inline-block align-middle border">
                                </a>
                                <?php endif; ?>
                                <a href="/tool/<?= $row['actualid'] ?>" class="text-blue-500 hover:underline"><?= $safe_name ?></a>
                            </td>
                            <td data-label="Brand" class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                    <?= e($row['brand'] ?? '-') ?>
                                </span>
                            </td>
                            <td data-label="Category" class="px-4 py-3 text-sm whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?= $cat_class ?>">
                                    <?= e($row['category'] ?? '-') ?>
                                </span>
                            </td>
                            <td data-label="Status" class="px-4 py-3 text-sm whitespace-nowrap">
                                <?php if (!empty($row['status'])): ?>
                                <span class="px-2 py-1 text-xs font-bold rounded-full <?= $status_class ?>">
                                    <?= e($status_label) ?>
                                </span>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Qty" class="px-4 py-3 text-sm text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1 justify-center">
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="action_type" value="quick_restock">
                                        <input type="hidden" name="tool_id" value="<?= $row['actualid'] ?>">
                                        <input type="hidden" name="delta" value="-1">
                                        <button type="submit" class="w-5 h-5 flex items-center justify-center rounded bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition shadow-2xs" title="Decrease quantity by 1" <?= $qty <= 0 ? 'disabled' : '' ?>>−</button>
                                    </form>
                                    <span class="min-w-[2.5rem] font-semibold <?= $qty_class ?>">
                                        <?= e($qty) ?><?= !empty($row['unit']) ? ' <span class="text-xs text-gray-500 font-normal">' . e($row['unit']) . '</span>' : '' ?>
                                    </span>
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="action_type" value="quick_restock">
                                        <input type="hidden" name="tool_id" value="<?= $row['actualid'] ?>">
                                        <input type="hidden" name="delta" value="1">
                                        <button type="submit" class="w-5 h-5 flex items-center justify-center rounded bg-gray-100 hover:bg-blue-100 hover:text-blue-700 text-gray-700 text-xs font-bold transition shadow-2xs" title="Increase quantity by 1">+</button>
                                    </form>
                                </div>
                            </td>
                            <td data-label="Price" class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                <?= $row['pricebought'] ? format_currency($row['pricebought']) : '-' ?>
                            </td>
                            <td data-label="Notes" class="px-4 py-3 text-sm text-gray-600 max-w-[150px] truncate" title="<?= $safe_notes ?>">
                                <?= $safe_notes ?>
                            </td>
                            <td data-label="Actions" class="px-4 py-3 text-sm">
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

<button onclick="openAddModal()"
        class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
        title="Add Tool">+
</button>

<?php $mode = 'add'; include '../components/tool_inventory_modal.php'; ?>

<?php $mode = 'edit'; include '../components/tool_inventory_modal.php'; ?>

<script>
    const radarToolsData = <?= json_encode($radar_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];
    const supplyItemsData = <?= json_encode($supply_run_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];
    let currentRadarIndex = 0;

    function renderRadarTool(idx) {
        if (!radarToolsData.length) return;
        const tool = radarToolsData[idx];
        if (!tool) return;

        // Counter & Select
        const counter = document.getElementById('radar-counter');
        if (counter) counter.textContent = (idx + 1) + ' of ' + radarToolsData.length;
        const select = document.getElementById('radar-tool-select');
        if (select) select.value = idx;

        // Tool link & name
        const link = document.getElementById('radar-tool-link');
        if (link) {
            link.href = '/tool/' + tool.id;
            link.textContent = tool.name;
        }

        // Brand & Category
        const brandBadge = document.getElementById('radar-brand-badge');
        if (brandBadge) {
            brandBadge.className = 'px-2 py-0.5 text-xs font-bold rounded-full ' + tool.brand_class;
            brandBadge.textContent = tool.brand;
        }
        const catBadge = document.getElementById('radar-category-badge');
        if (catBadge) {
            catBadge.className = 'px-2 py-0.5 text-xs font-bold rounded-full ' + tool.category_class;
            catBadge.textContent = tool.category;
        }

        // Status
        const statusBadge = document.getElementById('radar-status-badge');
        if (statusBadge) {
            statusBadge.className = 'px-2 py-0.5 text-xs font-bold rounded-full border ' + tool.status_class;
            statusBadge.textContent = tool.status;
        }

        // Qty
        const qtyBadge = document.getElementById('radar-qty-badge');
        if (qtyBadge) {
            const unitStr = tool.unit ? ' ' + tool.unit : '';
            qtyBadge.textContent = '📦 ' + tool.quantity + unitStr;
            if (tool.is_low) {
                qtyBadge.className = 'px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-700 border border-red-300';
            } else {
                qtyBadge.className = 'px-2 py-0.5 text-xs font-bold rounded-full bg-gray-100 text-gray-800 border border-gray-200';
            }
        }

        // Image / Icon
        const imgContainer = document.getElementById('radar-image-container');
        if (imgContainer) {
            if (tool.imagepath) {
                imgContainer.innerHTML = '<a href="/tool/' + tool.id + '" class="block"><img src="' + tool.imagepath + '" alt="' + escapeHtml(tool.name) + '" class="w-16 h-16 object-cover rounded-lg border border-gray-200 shadow-sm hover:opacity-90 transition"></a>';
            } else {
                imgContainer.innerHTML = '<div class="w-16 h-16 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center text-2xl shadow-inner select-none">🔧</div>';
            }
        }

        // Last service callout
        const logDateEl = document.getElementById('radar-last-log-date');
        const logTextEl = document.getElementById('radar-last-log-text');
        if (tool.last_log_notes) {
            if (logDateEl) logDateEl.textContent = tool.last_log_date + (tool.last_log_type ? ' (' + tool.last_log_type + ')' : '');
            if (logTextEl) logTextEl.innerHTML = '<span class="font-semibold text-gray-800">Note:</span> ' + escapeHtml(tool.last_log_notes);
        } else {
            if (logDateEl) logDateEl.textContent = 'None';
            if (logTextEl) logTextEl.innerHTML = '<span class="text-gray-400 italic">No maintenance history recorded yet.</span>';
        }

        // Form hidden tool IDs
        const fToolId = document.getElementById('radar-form-tool-id');
        if (fToolId) fToolId.value = tool.id;
        const fRestock1 = document.getElementById('radar-form-tool-id-restock1');
        if (fRestock1) fRestock1.value = tool.id;
        const fRestock5 = document.getElementById('radar-form-tool-id-restock5');
        if (fRestock5) fRestock5.value = tool.id;

        // Mark Good button visibility
        const markGoodForm = document.getElementById('radar-service-form');
        if (markGoodForm) {
            if (tool.is_degraded) {
                markGoodForm.classList.remove('hidden');
            } else {
                markGoodForm.classList.add('hidden');
            }
        }
    }

    function stepRadarTool(delta) {
        if (!radarToolsData.length) return;
        currentRadarIndex = (currentRadarIndex + delta + radarToolsData.length) % radarToolsData.length;
        renderRadarTool(currentRadarIndex);
    }

    function selectRadarTool(idx) {
        if (idx >= 0 && idx < radarToolsData.length) {
            currentRadarIndex = idx;
            renderRadarTool(currentRadarIndex);
        }
    }

    function syncAndSwitchToLog() {
        if (!radarToolsData.length) return;
        const cur = radarToolsData[currentRadarIndex];
        const select = document.getElementById('quicklog-tool-select');
        if (select && cur) {
            select.value = cur.id;
        }
        switchToolHubTab('log');
        const notes = document.getElementById('quicklog-notes');
        if (notes) {
            notes.focus();
        }
    }

    function editCurrentRadarTool() {
        if (!radarToolsData.length) return;
        const cur = radarToolsData[currentRadarIndex];
        if (cur && cur.modal_data) {
            openEditModalWithData(cur.modal_data);
        }
    }

    function switchToolHubTab(tab) {
        const tabLog = document.getElementById('tool-hub-tab-log');
        const tabSupply = document.getElementById('tool-hub-tab-supply');
        const btnLog = document.getElementById('tab-btn-log');
        const btnSupply = document.getElementById('tab-btn-supply');
        const copyWrapper = document.getElementById('supply-run-copy-wrapper');

        if (tab === 'log') {
            tabLog.classList.remove('hidden');
            tabSupply.classList.add('hidden');
            btnLog.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all bg-white text-indigo-900 shadow-2xs';
            btnSupply.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-600 hover:text-indigo-900';
            if (copyWrapper) copyWrapper.classList.add('hidden');
        } else {
            tabSupply.classList.remove('hidden');
            tabLog.classList.add('hidden');
            btnSupply.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all bg-white text-indigo-900 shadow-2xs';
            btnLog.className = 'px-3 py-1 text-xs font-bold rounded-md transition-all text-gray-600 hover:text-indigo-900';
            if (copyWrapper) copyWrapper.classList.remove('hidden');
        }
    }

    function setQuickLogText(preset) {
        const notes = document.getElementById('quicklog-notes');
        if (!notes) return;
        const current = notes.value.trim();
        if (!current) {
            notes.value = preset;
        } else if (!current.includes(preset)) {
            notes.value = current + '; ' + preset;
        }
        notes.focus();
    }

    function copySupplyList() {
        if (!supplyItemsData.length) return;
        let text = "🔧 Gunpla Workshop Supply Run Checklist:\n";
        text += "========================================\n";
        supplyItemsData.forEach(item => {
            text += `• ${item.name} (${item.brand}) - ${item.reasons}\n`;
        });
        text += "========================================\n";
        text += "Generated from Gunpla Hangar Workshop Hub";

        const btnText = document.getElementById('copy-supply-btn-text');
        const originalText = btnText ? btnText.textContent : 'Copy Supply List';

        const doCopy = () => {
            if (btnText) btnText.textContent = '✅ Copied!';
            setTimeout(() => {
                if (btnText) btnText.textContent = originalText;
            }, 2500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(doCopy).catch(() => {
                fallbackCopyText(text, doCopy);
            });
        } else {
            fallbackCopyText(text, doCopy);
        }
    }

    function fallbackCopyText(text, callback) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            if (callback) callback();
        } catch (e) {
            alert('Failed to copy list automatically. Please copy manually.');
        }
        document.body.removeChild(ta);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

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
        openEditModalWithData({
            id: get('id'),
            name: get('name'),
            brand: get('brand'),
            category: get('category'),
            status: get('status'),
            quantity: get('quantity'),
            unit: get('unit'),
            pricebought: get('pricebought'),
            datebought: get('datebought'),
            link: get('link'),
            notes: get('notes'),
            imagepath: get('imagepath')
        });
    }

    function openEditModalWithData(data) {
        document.getElementById('edit_id').value           = data.id || '';
        document.getElementById('modal_name').value        = data.name || '';
        document.getElementById('modal_brand').value       = data.brand || '';
        document.getElementById('modal_category').value    = data.category || '';
        document.getElementById('modal_status').value      = data.status || '';
        document.getElementById('modal_quantity').value    = data.quantity || '';
        document.getElementById('modal_unit').value        = data.unit || '';
        document.getElementById('modal_pricebought').value = data.pricebought || '';
        document.getElementById('modal_datebought').value  = data.datebought || '';
        document.getElementById('modal_link').value        = data.link || '';
        document.getElementById('modal_notes').value       = data.notes || '';

        const imagepath = data.imagepath || '';
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

    // Initialize Radar on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        if (radarToolsData && radarToolsData.length > 0) {
            renderRadarTool(0);
        }
    });
</script>

<script>initScrollRestore('tool_inventory_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
