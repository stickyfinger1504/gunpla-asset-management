<?php
require_once '../includes/bootstrap.php';

$current_section = 'tools';
$current_page    = 'tool_wishlist';
$page_title      = 'Tool Wishlist';

// ============================================
// POST REQUEST DISPATCH
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification if token is submitted
    if (isset($_POST['csrf_token']) && !verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('❌ Security validation failed (invalid CSRF token)');
        header('Location: /tool_wishlist');
        exit;
    }

    $action_success = false;
    $msg_text = null;

    // 1. Delete Item
    if (!empty($_POST['deleteid']) || (($_POST['action_type'] ?? '') === 'delete' && !empty($_POST['wishlistid']))) {
        $delete_id = (int)(!empty($_POST['deleteid']) ? $_POST['deleteid'] : $_POST['wishlistid']);
        $action_success = delete_tool_wishlist_item($conn, $delete_id);
        $msg_text = $action_success ? '✅ Wishlist item deleted successfully' : '❌ Failed to delete wishlist item';
    }
    // 2. Mark as Obtained
    elseif (!empty($_POST['obtainedid']) || (($_POST['action_type'] ?? '') === 'mark_obtained' && !empty($_POST['wishlistid']))) {
        $obtained_id = (int)(!empty($_POST['obtainedid']) ? $_POST['obtainedid'] : $_POST['wishlistid']);
        $action_success = mark_tool_wishlist_obtained($conn, $obtained_id);
        $msg_text = $action_success ? '✅ Wishlist tool marked as obtained' : '❌ Failed to mark item as obtained';
    }
    // 3. Add Item
    elseif (($_POST['action_type'] ?? '') === 'add') {
        if (!empty($_FILES['image']['name'])) {
            $uploaded = handle_image_upload($_FILES['image'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
            if (!$uploaded['success']) {
                set_flash_message('❌ ' . $uploaded['error']);
                header('Location: /tool_wishlist');
                exit;
            }
            $_POST['imagepath'] = $uploaded['path'];
        }
        $action_success = add_tool_wishlist_item($conn, $_POST);
        $name = !empty($_POST['name']) ? htmlspecialchars($_POST['name']) : 'Item';
        $msg_text = $action_success ? "✅ Successfully added {$name} to wishlist" : '❌ Error adding tool to wishlist';
    }
    // 4. Edit Item
    elseif (($_POST['action_type'] ?? '') === 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $uploaded = handle_image_upload($_FILES['image'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
            if (!$uploaded['success']) {
                set_flash_message('❌ ' . $uploaded['error']);
                header('Location: /tool_wishlist');
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath'], TOOL_UPLOAD_DIR, TOOL_UPLOAD_URL_PREFIX);
            }
            $_POST['imagepath'] = $uploaded['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? '';
        }
        $action_success = update_tool_wishlist_item($conn, $_POST);
        $msg_text = $action_success ? '✅ Wishlist item updated successfully' : '❌ Error updating wishlist item';
    }
    // 5. Induct into Tool Inventory
    elseif (($_POST['action_type'] ?? '') === 'induct' || ($_POST['action_type'] ?? '') === 'register_to_toolbox') {
        $wishlist_id = (int)($_POST['from_wishlist_id'] ?? $_POST['wishlistid'] ?? 0);
        if ($wishlist_id > 0) {
            $new_tool_id = induct_tool_to_inventory($conn, $wishlist_id, $_POST);
            $action_success = (bool)$new_tool_id;
            $name = !empty($_POST['name']) ? htmlspecialchars($_POST['name']) : 'tool';
            $msg_text = $action_success 
                ? "✅ Successfully inducted {$name} into your Tool Inventory!"
                : '❌ Error inducting tool into inventory';
        } else {
            $msg_text = '⚠️ Please select a valid tool wishlist item to induct';
        }
    }

    if ($msg_text !== null) {
        set_flash_message($msg_text);
    }
    header('Location: /tool_wishlist');
    exit;
}

// ============================================
// DATA FETCHING FOR RENDERING
// ============================================
$message         = get_flash_message();
$csrf_token      = csrf_token();
$tool_brands     = get_tool_brands($conn);
$tool_categories = get_tool_categories($conn);
$tool_statuses   = get_tool_statuses($conn);
$priorities      = get_tool_wishlist_priorities($conn);
$items           = get_tool_wishlist_items($conn, $_GET);
$stats           = calculate_tool_wishlist_stats($items);

$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['filter_category']) || !empty($_GET['filter_priority']) || !empty($_GET['filter_obtained']) || !empty($_GET['search']);

$all_items        = $has_filters ? get_tool_wishlist_items($conn) : $items;
$unobtained_items = array_values(array_filter($all_items, fn($i) => (int)($i['obtainedid'] ?? 0) === 0));
$obtained_items   = array_values(array_filter($all_items, fn($i) => (int)($i['obtainedid'] ?? 0) === 1));

// Resolve 'New' status ID for intake modal
$default_new_status_id = null;
foreach ($tool_statuses as $st) {
    if (stripos($st['label'], 'New') !== false) {
        $default_new_status_id = (int)$st['id'];
        break;
    }
}
if (!$default_new_status_id && !empty($tool_statuses)) {
    $default_new_status_id = (int)$tool_statuses[0]['id'];
}

// Shopping list dataset (unobtained items sorted High -> Mid -> Low)
$unobtained_for_shopping = $unobtained_items;
usort($unobtained_for_shopping, function($a, $b) {
    $prioMap = ['High' => 1, 'Mid' => 2, 'Low' => 3];
    $pA = $prioMap[$a['priority'] ?? ''] ?? 99;
    $pB = $prioMap[$b['priority'] ?? ''] ?? 99;
    return $pA <=> $pB;
});

// Option 1: Workshop Procurement Command datasets
$high_priority_targets = array_values(array_filter($unobtained_items, fn($i) => strcasecmp($i['priority'] ?? '', 'High') === 0));
$target_pool = !empty($high_priority_targets) ? $high_priority_targets : $unobtained_items;
$top_target = !empty($target_pool) ? $target_pool[0] : null;

$uninducted_hauls = array_values(array_filter($obtained_items, fn($i) => empty($i['toolid'])));
$haul_pool = !empty($uninducted_hauls) ? $uninducted_hauls : $obtained_items;
$latest_haul = !empty($haul_pool) ? $haul_pool[0] : null;

$unobtained_category_counts = [];
foreach ($unobtained_items as $uItem) {
    $catName = $uItem['category'] ?? 'General';
    $unobtained_category_counts[$catName] = ($unobtained_category_counts[$catName] ?? 0) + 1;
}

include '../components/layout_header.php';
?>

<div class="max-w-7xl mx-auto w-full">
    <h1 class="page-title font-bold text-gray-700 text-center mb-8">🔧 Tool Wishlist</h1>

    <?php include '../components/toast.php'; ?>

    <!-- Summary Stats Bar & Category Breakdown -->
    <div class="mb-8">
        <?php if ($has_filters): ?>
            <div class="text-sm text-blue-600 mb-2 flex items-center gap-2">
                <span>📊</span>
                <span>Showing stats for current filter</span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php
            unset($icon);
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

        <?php if (!empty($stats['category_counts'])): ?>
            <div class="mt-3 flex items-center gap-2 flex-wrap text-xs text-gray-600">
                <span class="font-bold text-gray-700">Categories:</span>
                <?php foreach ($stats['category_counts'] as $cat_name => $cat_cnt): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-white border border-gray-200 shadow-2xs font-medium">
                        <span class="font-bold text-gray-800"><?= e($cat_name) ?></span>
                        <span class="px-1.5 py-0.2 rounded-full bg-blue-100 text-blue-800 text-[10px] font-bold"><?= $cat_cnt ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Workshop Procurement Command Hub (Option 1) -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span>🧰</span> Workshop Procurement Command
            </h3>
            <span class="text-xs text-gray-500 font-medium hidden sm:inline">Priority acquisition radar, supply run dispatcher & toolbox intake</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Panel 1: Priority Tool Target Radar -->
            <div id="target-panel" class="bg-gradient-to-br from-white to-amber-50/40 border-2 border-amber-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            🎯 Priority Target Radar
                        </span>
                        <?php if (count($target_pool) > 1): ?>
                            <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                <button type="button" onclick="stepTarget(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Target">◀</button>
                                <span id="target-stepper-counter">1 of <?= count($target_pool) ?></span>
                                <button type="button" onclick="stepTarget(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Target">▶</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs text-amber-800 font-medium mb-3 block">
                        <?= !empty($high_priority_targets) ? count($high_priority_targets) . ' high priority tools queued' : count($unobtained_items) . ' unobtained tools on wishlist' ?>
                    </span>

                    <div id="target-card-content">
                        <?php if ($top_target): ?>
                            <?php
                                $top_priority_class = match(strtolower($top_target['priority'] ?? '')) {
                                    'high' => 'bg-red-100 text-red-800',
                                    'mid'  => 'bg-yellow-100 text-yellow-800',
                                    'low'  => 'bg-green-100 text-green-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                            ?>
                            <div class="mb-4">
                                <div class="flex items-start gap-3">
                                    <?php if (!empty($top_target['imagepath'])): ?>
                                        <img src="<?= e($top_target['imagepath']) ?>" alt="Tool" class="w-14 h-14 object-cover rounded-lg border border-amber-200 shadow-2xs flex-shrink-0">
                                    <?php endif; ?>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                            <?= htmlspecialchars($top_target['name']) ?>
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                            <?php if (!empty($top_target['brand'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($top_target['brandid']) ?>">
                                                <?= htmlspecialchars($top_target['brand']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($top_target['category'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($top_target['categoryid']) ?>">
                                                <?= htmlspecialchars($top_target['category']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $top_priority_class ?>">
                                                <?= htmlspecialchars($top_target['priority'] ?? 'Mid') ?> Priority
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($top_target['notes']) && $top_target['notes'] !== '-'): ?>
                                    <div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-amber-100 rounded-lg p-2.5 italic">
                                        📝 <?= htmlspecialchars($top_target['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-6 text-center">
                                <div class="text-3xl mb-2">🎉</div>
                                <p class="text-sm font-semibold text-gray-700">All Tool Targets Secured!</p>
                                <p class="text-xs text-gray-500 mt-1">All tools on your wishlist have been obtained. Click + below to queue up new workshop tools.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($top_target): ?>
                    <div class="pt-3 border-t border-amber-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                        <div id="target-store-link-container">
                            <?php if (!empty($top_target['link'])): ?>
                                <a href="<?= htmlspecialchars($top_target['link']) ?>" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 bg-white hover:bg-blue-50 border border-blue-200 rounded-lg transition-colors shadow-2xs">
                                    <span>🔗</span> Open Store
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-400 italic">No store link provided</span>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2">
                            <form method="POST" class="inline m-0">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token ?? '') ?>">
                                <input type="hidden" name="obtainedid" id="target-form-obtained-id" value="<?= (int)$top_target['actualid'] ?>">
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow-sm transition-all"
                                        title="Mark this tool as obtained">
                                    <span>⚡</span> Got It!
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Panel 2: Supply Run Dispatcher & Toolbox Intake -->
            <div id="haul-panel" class="bg-gradient-to-br from-white to-emerald-50/40 border-2 border-emerald-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <!-- Supply Run Mode (Top of Panel 2) -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                            <span>🛒</span> Supply Run Dispatcher
                        </span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-white border border-emerald-200 text-emerald-800">
                            <?= count($unobtained_items) ?> Wanted
                        </span>
                    </div>

                    <div class="mb-3">
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <p class="text-xs text-gray-600 font-medium">
                                Export pending tools to clipboard for store runs or online orders:
                            </p>
                            <button type="button" id="copy-shopping-btn" onclick="copyToolShoppingList()"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold bg-white hover:bg-emerald-50 text-emerald-900 border border-emerald-300 rounded-lg shadow-2xs transition-all flex-shrink-0"
                                    title="Copy unobtained wishlist items as a shopping checklist">
                                <span>📋</span> <span id="copy-shopping-btn-text">Copy Supply List</span>
                            </button>
                        </div>

                        <?php if (!empty($unobtained_category_counts)): ?>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <?php foreach ($unobtained_category_counts as $cat_label => $cnt): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-white/90 border border-emerald-200 text-gray-700">
                                        <span class="font-bold text-emerald-700"><?= $cnt ?></span> <?= htmlspecialchars($cat_label) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-emerald-100 my-3"></div>

                    <!-- Recent Haul Section (Bottom of Panel 2) -->
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <span class="text-xs font-bold text-emerald-900 uppercase tracking-wider flex items-center gap-1">
                            <span>🎉</span> Recent Haul Intake
                        </span>
                        <?php if (count($haul_pool) > 1): ?>
                            <div class="flex items-center gap-1 text-xs text-gray-500 font-medium">
                                <button type="button" onclick="stepHaul(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Haul">◀</button>
                                <span id="haul-stepper-counter">1 of <?= count($haul_pool) ?></span>
                                <button type="button" onclick="stepHaul(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Haul">▶</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="haul-card-content">
                        <?php if ($latest_haul): ?>
                            <?php $is_latest_inducted = !empty($latest_haul['toolid']); ?>
                            <div class="mb-2">
                                <div class="flex items-start gap-3">
                                    <?php if (!empty($latest_haul['imagepath'])): ?>
                                        <img src="<?= e($latest_haul['imagepath']) ?>" alt="Tool" class="w-12 h-12 object-cover rounded-lg border border-emerald-200 shadow-2xs flex-shrink-0">
                                    <?php endif; ?>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-base font-bold text-gray-900 leading-snug">
                                            <?= htmlspecialchars($latest_haul['name']) ?>
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-2 mt-1">
                                            <?php if (!empty($latest_haul['brand'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($latest_haul['brandid']) ?>">
                                                <?= htmlspecialchars($latest_haul['brand']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($latest_haul['category'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($latest_haul['categoryid']) ?>">
                                                <?= htmlspecialchars($latest_haul['category']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($is_latest_inducted): ?>
                                                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800">
                                                    🧰 In Toolbox
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">
                                                    ✅ Secured
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($latest_haul['notes']) && $latest_haul['notes'] !== '-'): ?>
                                    <div class="mt-2 text-xs text-gray-600 bg-white/80 border border-emerald-100 rounded-lg p-2 italic truncate" title="<?= htmlspecialchars($latest_haul['notes']) ?>">
                                        📝 <?= htmlspecialchars($latest_haul['notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-4 text-center">
                                <div class="text-2xl mb-1">📦</div>
                                <p class="text-xs font-semibold text-gray-700">No Hauls Claimed Yet!</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">When you purchase a tool and click "Got It!", it will appear here ready to induct straight into your workbench inventory.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($latest_haul): ?>
                    <div class="pt-3 border-t border-emerald-100 flex flex-wrap items-center justify-between gap-2 mt-auto">
                        <a href="/tool_inventory" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-gray-900 transition-colors font-medium">
                            View Tool Inventory →
                        </a>
                        <div id="haul-action-container">
                            <?php if (!empty($latest_haul['toolid'])): ?>
                                <a href="/tool/<?= (int)$latest_haul['toolid'] ?>"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition-colors border border-gray-300">
                                    🔍 View in Toolbox
                                </a>
                            <?php else: ?>
                                <button type="button" onclick="openInductFromCurrent()"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 rounded-lg transition-all shadow-2xs">
                                    🧰 Induct into Workshop
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
        <form method="GET" action="/tool_wishlist">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-bold text-gray-500 uppercase">Filters</span>
                <button type="button" class="filter-toggle-btn" onclick="toggleFilterBar(this)">▼ Filters</button>
            </div>
            <div class="filter-bar-body <?= $has_filters ? 'is-open' : '' ?>">
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                    <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Tool name or notes..."
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Brand</label>
                    <select name="filter_brand" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">All Brands</option>
                        <?php foreach ($tool_brands as $brand): ?>
                            <option value="<?= (int)$brand['id'] ?>" <?= (isset($_GET['filter_brand']) && (int)$_GET['filter_brand'] === (int)$brand['id']) ? 'selected' : '' ?>>
                                <?= e($brand['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Category</label>
                    <select name="filter_category" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">All Categories</option>
                        <?php foreach ($tool_categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (isset($_GET['filter_category']) && (int)$_GET['filter_category'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                <?= e($cat['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Priority</label>
                    <select name="filter_priority" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">All Priorities</option>
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (isset($_GET['filter_priority']) && (int)$_GET['filter_priority'] === (int)$p['id']) ? 'selected' : '' ?>>
                                <?= e($p['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Status</label>
                    <select name="filter_obtained" class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                        <option value="">All Items</option>
                        <option value="unobtained" <?= (($_GET['filter_obtained'] ?? '') === 'unobtained') ? 'selected' : '' ?>>Wanted Only</option>
                        <option value="obtained" <?= (($_GET['filter_obtained'] ?? '') === 'obtained') ? 'selected' : '' ?>>Obtained Only</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">Apply</button>
                    <?php if ($has_filters): ?>
                        <a href="/tool_wishlist" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300 transition text-center flex items-center justify-center">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Interactive Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 mobile-stack-table">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'text')">Tool Name</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Brand</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Category</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Priority</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 4, 'text')">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Link</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $safe_name   = htmlspecialchars($row['name'] ?? '', ENT_QUOTES);
                        $safe_notes  = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                        $safe_link   = htmlspecialchars($row['link'] ?? '', ENT_QUOTES);
                        $brand_class = get_brand_color_palette($row['brandid'] ?? 0);
                        $cat_class   = get_brand_color_palette($row['categoryid'] ?? 0);

                        $priority_str = $row['priority'] ?? '';
                        $priority_class = match(strtolower($priority_str)) {
                            'high' => 'bg-red-100 text-red-800 border-red-200',
                            'mid'  => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                            'low'  => 'bg-green-100 text-green-800 border-green-200',
                            default => 'bg-gray-100 text-gray-800 border-gray-200'
                        };

                        $is_obtained = ((int)($row['obtainedid'] ?? 0) === 1 || ($row['obtained'] ?? '') === 'Yes');
                        $is_inducted = !empty($row['toolid']);
                        ?>
                        <tr class="hover:bg-gray-50 border-b border-gray-100">
                            <td data-label="Tool Name" class="px-4 py-3 text-sm font-semibold text-gray-800">
                                <?php if (!empty($row['imagepath'])): ?>
                                    <a href="<?= e($row['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="mr-2 inline-block align-middle">
                                        <img src="<?= e($row['imagepath']) ?>" alt="Tool"
                                             class="w-8 h-8 object-cover rounded border border-gray-200 hover:opacity-90 transition">
                                    </a>
                                <?php endif; ?>
                                <span><?= $safe_name ?></span>
                            </td>
                            <td data-label="Brand" class="px-4 py-3 text-sm whitespace-nowrap">
                                <?php if (!empty($row['brand'])): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                        <?= e($row['brand']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Category" class="px-4 py-3 text-sm whitespace-nowrap">
                                <?php if (!empty($row['category'])): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-full <?= $cat_class ?>">
                                        <?= e($row['category']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Priority" class="px-4 py-3 text-sm whitespace-nowrap">
                                <?php if (!empty($row['priority'])): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-full border <?= $priority_class ?>">
                                        <?= e($row['priority']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status" class="px-4 py-3 text-sm whitespace-nowrap">
                                <?php if ($is_obtained): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800 border border-green-200">
                                        Obtained
                                    </span>
                                    <?php if ($is_inducted): ?>
                                        <a href="/tool/<?= (int)$row['toolid'] ?>"
                                           class="ml-1 inline-flex items-center gap-0.5 px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition"
                                           title="View in Tool Inventory">
                                            <span>🧰</span> In Toolbox
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                                        Wanted
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Link" class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                <?php if (!empty($row['link'])): ?>
                                    <a href="<?= $safe_link ?>" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 hover:underline">
                                        <span>🔗</span> Link
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Notes" class="px-4 py-3 text-sm text-gray-600 max-w-[150px] truncate" title="<?= $safe_notes ?>">
                                <?= $safe_notes ?>
                            </td>
                            <td data-label="Actions" class="px-4 py-3 text-sm">
                                <div class="flex items-center space-x-1">
                                    <button type="button" class="p-1 hover:bg-gray-200 rounded text-lg transition" title="Edit"
                                            data-id="<?= $row['actualid'] ?>"
                                            data-name="<?= $safe_name ?>"
                                            data-brand="<?= $row['brandid'] ?? '' ?>"
                                            data-category="<?= $row['categoryid'] ?? '' ?>"
                                            data-priority="<?= $row['priorityid'] ?? '' ?>"
                                            data-obtained="<?= $row['obtainedid'] ?>"
                                            data-link="<?= $safe_link ?>"
                                            data-notes="<?= $safe_notes ?>"
                                            data-imagepath="<?= htmlspecialchars($row['imagepath'] ?? '', ENT_QUOTES) ?>"
                                            onclick="openEditModal(this)">
                                        ✏️
                                    </button>

                                    <?php if (!$is_obtained): ?>
                                        <!-- Quick Mark Obtained -->
                                        <form method="POST" class="inline m-0" onsubmit="return confirm('Mark <?= $safe_name ?> as obtained?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token ?? '') ?>">
                                            <input type="hidden" name="obtainedid" value="<?= $row['actualid'] ?>">
                                            <button type="submit" class="p-1 hover:bg-green-100 rounded text-lg transition" title="Mark as Obtained">
                                                ✅
                                            </button>
                                        </form>

                                        <!-- Fast Intake / Induct directly into Workshop -->
                                        <button type="button" class="p-1 hover:bg-emerald-100 text-emerald-700 rounded text-lg transition" title="Induct directly into Workshop"
                                                onclick='openInductModal({
                                                    id: <?= (int)$row['actualid'] ?>,
                                                    name: <?= json_encode($row['name']) ?>,
                                                    brand: <?= (int)($row['brandid'] ?? 0) ?>,
                                                    category: <?= (int)($row['categoryid'] ?? 0) ?>,
                                                    link: <?= json_encode($row['link'] ?? '') ?>,
                                                    notes: <?= json_encode($row['notes'] ?? '') ?>,
                                                    imagepath: <?= json_encode($row['imagepath'] ?? '') ?>
                                                })'>
                                            📥
                                        </button>
                                    <?php elseif (!$is_inducted): ?>
                                        <!-- Already obtained, but not yet registered into tool_inventory -->
                                        <button type="button" class="p-1 hover:bg-blue-100 text-blue-700 rounded text-lg transition" title="Induct into Tool Inventory"
                                                onclick='openInductModal({
                                                    id: <?= (int)$row['actualid'] ?>,
                                                    name: <?= json_encode($row['name']) ?>,
                                                    brand: <?= (int)($row['brandid'] ?? 0) ?>,
                                                    category: <?= (int)($row['categoryid'] ?? 0) ?>,
                                                    link: <?= json_encode($row['link'] ?? '') ?>,
                                                    notes: <?= json_encode($row['notes'] ?? '') ?>,
                                                    imagepath: <?= json_encode($row['imagepath'] ?? '') ?>
                                                })'>
                                            🧰
                                        </button>
                                    <?php else: ?>
                                        <!-- Already inducted: link to tool details -->
                                        <a href="/tool/<?= (int)$row['toolid'] ?>" class="p-1 hover:bg-blue-100 rounded text-lg transition" title="View Tool in Inventory">
                                            🔍
                                        </a>
                                    <?php endif; ?>

                                    <!-- Delete Item -->
                                    <form method="POST" class="inline m-0" onsubmit="return confirm('Delete <?= $safe_name ?> from wishlist?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token ?? '') ?>">
                                        <input type="hidden" name="deleteid" value="<?= $row['actualid'] ?>">
                                        <button type="submit" class="p-1 hover:bg-red-100 rounded text-lg transition" title="Delete">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center py-10 text-gray-500">
                            <span class="text-3xl block mb-2">✨</span>
                            <p class="font-semibold text-gray-700">No tools on your wishlist yet!</p>
                            <p class="text-xs text-gray-400 mt-1">Add tools you want to acquire using the button above.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Floating Action Button -->
<button onclick="openAddModal()"
        class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
        title="Add Tool to Wishlist">+
</button>

<!-- Modals -->
<?php $mode = 'add'; include '../components/tool_wishlist_modal.php'; ?>
<?php $mode = 'edit'; include '../components/tool_wishlist_modal.php'; ?>

<!-- Induct into Workshop Intake Modal -->
<div id="inductModal" role="dialog" aria-modal="true" aria-labelledby="induct-modal-title"
     class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50" style="display:none;"
     onclick="if (event.target === this) closeInductModal()">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-lg p-6 modal-animate relative overflow-y-auto max-h-[90vh]">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600 transition" onclick="closeInductModal()">&times;</span>
        
        <div class="flex items-center gap-2 mb-1">
            <span class="text-2xl">🧰</span>
            <h2 id="induct-modal-title" class="text-xl font-bold text-gray-800">Induct Tool into Workshop</h2>
        </div>
        <p class="text-xs text-gray-500 mb-4">Register this acquired tool into your active Tool Inventory as New.</p>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="induct">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token ?? '') ?>">
            <input type="hidden" name="from_wishlist_id" id="induct_wishlist_id">
            <input type="hidden" name="imagepath" id="induct_imagepath">

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Tool Name *</label>
                <input type="text" name="name" id="induct_name" required
                       class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Brand</label>
                    <select name="brand" id="induct_brand" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
                        <option value="">-- Select Brand --</option>
                        <?php foreach ($tool_brands as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Category</label>
                    <select name="category" id="induct_category" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($tool_categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Initial Condition</label>
                    <select name="status" id="induct_status" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
                        <?php foreach ($tool_statuses as $st): ?>
                            <option value="<?= (int)$st['id'] ?>" <?= ((int)$st['id'] === (int)$default_new_status_id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($st['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Quantity &amp; Unit</label>
                    <div class="flex gap-2">
                        <input type="number" name="quantity" id="induct_quantity" min="1" value="1"
                               class="w-16 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
                        <input type="text" name="unit" id="induct_unit" placeholder="pcs"
                               class="flex-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Price Bought (Rp)</label>
                    <input type="number" name="pricebought" id="induct_pricebought" min="0" placeholder="e.g. 150000"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Date Acquired</label>
                    <input type="date" name="datebought" id="induct_datebought" value="<?= date('Y-m-d') ?>"
                           class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Reorder / Store Link</label>
                <input type="url" name="link" id="induct_link" placeholder="https://..."
                       class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Workshop Notes</label>
                <textarea name="notes" id="induct_notes" rows="2" placeholder="e.g. Inducted from wishlist, kept in top drawer..."
                          class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-emerald-500 text-sm"></textarea>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-sm transition">
                    ✅ Confirm &amp; Induct to Toolbox
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const unobtainedShoppingItems = <?= json_encode($unobtained_for_shopping, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];

    // Workshop Procurement Command (Option 1) Datasets & Logic
    const targetPool = <?= json_encode(array_map(function($t) {
        $prioClass = match(strtolower($t['priority'] ?? '')) {
            'high' => 'bg-red-100 text-red-800 border-red-200',
            'mid'  => 'bg-yellow-100 text-yellow-800 border-yellow-200',
            'low'  => 'bg-green-100 text-green-800 border-green-200',
            default => 'bg-gray-100 text-gray-800 border-gray-200'
        };
        return [
            'actualid'       => (int)$t['actualid'],
            'name'           => $t['name'] ?? '',
            'brand'          => $t['brand'] ?? '',
            'brandid'        => (int)($t['brandid'] ?? 0),
            'brand_badge'    => get_brand_color_palette($t['brandid'] ?? 0),
            'category'       => $t['category'] ?? '',
            'categoryid'     => (int)($t['categoryid'] ?? 0),
            'category_badge' => get_brand_color_palette($t['categoryid'] ?? 0),
            'priority'       => $t['priority'] ?? 'Mid',
            'priority_class' => $prioClass,
            'link'           => $t['link'] ?? '',
            'notes'          => $t['notes'] ?? '',
            'imagepath'      => $t['imagepath'] ?? '',
        ];
    }, $target_pool), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];

    const haulPool = <?= json_encode(array_map(function($t) {
        return [
            'actualid'       => (int)$t['actualid'],
            'name'           => $t['name'] ?? '',
            'brand'          => $t['brand'] ?? '',
            'brandid'        => (int)($t['brandid'] ?? 0),
            'brand_badge'    => get_brand_color_palette($t['brandid'] ?? 0),
            'category'       => $t['category'] ?? '',
            'categoryid'     => (int)($t['categoryid'] ?? 0),
            'category_badge' => get_brand_color_palette($t['categoryid'] ?? 0),
            'link'           => $t['link'] ?? '',
            'notes'          => $t['notes'] ?? '',
            'imagepath'      => $t['imagepath'] ?? '',
            'toolid'         => !empty($t['toolid']) ? (int)$t['toolid'] : null,
        ];
    }, $haul_pool), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> || [];

    let currentTargetIdx = 0;
    let currentHaulIdx = 0;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stepTarget(delta) {
        if (!targetPool || targetPool.length === 0) return;
        currentTargetIdx = (currentTargetIdx + delta + targetPool.length) % targetPool.length;
        renderTarget();
    }

    function renderTarget() {
        const item = targetPool[currentTargetIdx];
        if (!item) return;

        const counter = document.getElementById('target-stepper-counter');
        if (counter) counter.textContent = `${currentTargetIdx + 1} of ${targetPool.length}`;

        const formId = document.getElementById('target-form-obtained-id');
        if (formId) formId.value = item.actualid;

        const container = document.getElementById('target-card-content');
        if (container) {
            const notesHtml = (item.notes && item.notes !== '-') ? 
                `<div class="mt-2.5 text-xs text-gray-600 bg-white/80 border border-amber-100 rounded-lg p-2.5 italic">📝 ${escapeHtml(item.notes)}</div>` : '';

            const imgHtml = item.imagepath ? 
                `<img src="${escapeHtml(item.imagepath)}" alt="Tool" class="w-14 h-14 object-cover rounded-lg border border-amber-200 shadow-2xs flex-shrink-0">` : '';

            const brandPill = item.brand ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span>` : '';
            const catPill = item.category ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.category_badge}">${escapeHtml(item.category)}</span>` : '';

            container.innerHTML = `
                <div class="mb-4">
                    <div class="flex items-start gap-3">
                        ${imgHtml}
                        <div class="min-w-0 flex-1">
                            <h4 class="text-lg font-bold text-gray-900 leading-snug">${escapeHtml(item.name)}</h4>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                ${brandPill}
                                ${catPill}
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full ${item.priority_class}">
                                    ${escapeHtml(item.priority)} Priority
                                </span>
                            </div>
                        </div>
                    </div>
                    ${notesHtml}
                </div>
            `;
        }

        const storeContainer = document.getElementById('target-store-link-container');
        if (storeContainer) {
            if (item.link) {
                storeContainer.innerHTML = `
                    <a href="${escapeHtml(item.link)}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 bg-white hover:bg-blue-50 border border-blue-200 rounded-lg transition-colors shadow-2xs">
                        <span>🔗</span> Open Store
                    </a>
                `;
            } else {
                storeContainer.innerHTML = '<span class="text-xs text-gray-400 italic">No store link provided</span>';
            }
        }
    }

    function stepHaul(delta) {
        if (!haulPool || haulPool.length === 0) return;
        currentHaulIdx = (currentHaulIdx + delta + haulPool.length) % haulPool.length;
        renderHaul();
    }

    function renderHaul() {
        const item = haulPool[currentHaulIdx];
        if (!item) return;

        const counter = document.getElementById('haul-stepper-counter');
        if (counter) counter.textContent = `${currentHaulIdx + 1} of ${haulPool.length}`;

        const container = document.getElementById('haul-card-content');
        if (container) {
            const notesHtml = (item.notes && item.notes !== '-') ? 
                `<div class="mt-2 text-xs text-gray-600 bg-white/80 border border-emerald-100 rounded-lg p-2 italic truncate" title="${escapeHtml(item.notes)}">📝 ${escapeHtml(item.notes)}</div>` : '';

            const imgHtml = item.imagepath ? 
                `<img src="${escapeHtml(item.imagepath)}" alt="Tool" class="w-12 h-12 object-cover rounded-lg border border-emerald-200 shadow-2xs flex-shrink-0">` : '';

            const brandPill = item.brand ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span>` : '';
            const catPill = item.category ? `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.category_badge}">${escapeHtml(item.category)}</span>` : '';

            const statusPill = item.toolid ? 
                `<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-blue-100 text-blue-800">🧰 In Toolbox</span>` :
                `<span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">✅ Secured</span>`;

            container.innerHTML = `
                <div class="mb-2">
                    <div class="flex items-start gap-3">
                        ${imgHtml}
                        <div class="min-w-0 flex-1">
                            <h4 class="text-base font-bold text-gray-900 leading-snug">${escapeHtml(item.name)}</h4>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                ${brandPill}
                                ${catPill}
                                ${statusPill}
                            </div>
                        </div>
                    </div>
                    ${notesHtml}
                </div>
            `;
        }

        const actionContainer = document.getElementById('haul-action-container');
        if (actionContainer) {
            if (item.toolid) {
                actionContainer.innerHTML = `
                    <a href="/tool/${item.toolid}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg transition-colors border border-gray-300">
                        🔍 View in Toolbox
                    </a>
                `;
            } else {
                actionContainer.innerHTML = `
                    <button type="button" onclick="openInductFromCurrent()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 rounded-lg transition-all shadow-2xs">
                        🧰 Induct into Workshop
                    </button>
                `;
            }
        }
    }

    function openInductFromCurrent() {
        if (!haulPool || haulPool.length === 0) return;
        const item = haulPool[currentHaulIdx];
        if (item) {
            openInductModal({
                id: item.actualid,
                name: item.name,
                brand: item.brandid,
                category: item.categoryid,
                link: item.link,
                notes: item.notes,
                imagepath: item.imagepath
            });
        }
    }

    function openAddModal() {
        const modal = document.getElementById('addModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function closeAddModal() {
        const modal = document.getElementById('addModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }

    function openEditModal(button) {
        const get = attr => button.getAttribute('data-' + attr);

        document.getElementById('modal_id').value        = get('id');
        document.getElementById('modal_name').value      = get('name');
        document.getElementById('modal_brand').value     = get('brand');
        document.getElementById('modal_category').value  = get('category');
        document.getElementById('modal_priority').value  = get('priority');
        document.getElementById('modal_obtained').value  = get('obtained');
        document.getElementById('modal_link').value      = get('link');
        document.getElementById('modal_notes').value     = get('notes') === '-' ? '' : get('notes');

        const imagepath = get('imagepath');
        document.getElementById('modal_imagepath').value = imagepath;
        const preview = document.getElementById('modal_image_preview');
        const thumb   = document.getElementById('modal_image_thumb');
        if (imagepath) {
            thumb.src = imagepath;
            preview.classList.remove('hidden');
        } else if (preview) {
            preview.classList.add('hidden');
        }

        const modal = document.getElementById('editModal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }

    function openInductModal(data) {
        document.getElementById('induct_wishlist_id').value = data.id || '';
        document.getElementById('induct_name').value        = data.name || '';
        document.getElementById('induct_brand').value       = data.brand || '';
        document.getElementById('induct_category').value    = data.category || '';
        document.getElementById('induct_link').value        = data.link || '';
        document.getElementById('induct_notes').value       = (data.notes && data.notes !== '-') ? data.notes : '';
        document.getElementById('induct_imagepath').value   = data.imagepath || '';

        const modal = document.getElementById('inductModal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function closeInductModal() {
        const modal = document.getElementById('inductModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }

    function copyToolShoppingList() {
        if (!unobtainedShoppingItems.length) {
            alert('Your tool wishlist has no unobtained items right now!');
            return;
        }

        let text = "🔧 Gunpla Workshop Tool Supply & Shopping List:\n";
        text += "===============================================\n";

        const high = unobtainedShoppingItems.filter(i => (i.priority || '').toLowerCase() === 'high');
        const mid  = unobtainedShoppingItems.filter(i => (i.priority || '').toLowerCase() === 'mid');
        const low  = unobtainedShoppingItems.filter(i => (i.priority || '').toLowerCase() === 'low');
        const others = unobtainedShoppingItems.filter(i => !['high','mid','low'].includes((i.priority || '').toLowerCase()));

        if (high.length) {
            text += "\n🔥 HIGH PRIORITY:\n";
            high.forEach(i => {
                const brandStr = i.brand ? `[${i.brand}] ` : '';
                const catStr = i.category ? ` (${i.category})` : '';
                const linkStr = i.link ? ` | Link: ${i.link}` : '';
                text += `• ${brandStr}${i.name}${catStr}${linkStr}\n`;
            });
        }

        if (mid.length) {
            text += "\n⚡ MID PRIORITY:\n";
            mid.forEach(i => {
                const brandStr = i.brand ? `[${i.brand}] ` : '';
                const catStr = i.category ? ` (${i.category})` : '';
                text += `• ${brandStr}${i.name}${catStr}\n`;
            });
        }

        if (low.length) {
            text += "\n🌱 LOW PRIORITY:\n";
            low.forEach(i => {
                const brandStr = i.brand ? `[${i.brand}] ` : '';
                const catStr = i.category ? ` (${i.category})` : '';
                text += `• ${brandStr}${i.name}${catStr}\n`;
            });
        }

        if (others.length) {
            text += "\n📋 OTHER TARGETS:\n";
            others.forEach(i => {
                const brandStr = i.brand ? `[${i.brand}] ` : '';
                text += `• ${brandStr}${i.name}\n`;
            });
        }

        text += "\n===============================================\n";
        text += `Total items to acquire: ${unobtainedShoppingItems.length}\n`;
        text += "Generated from Gunpla Hangar Tool Wishlist";

        const btnText = document.getElementById('copy-shopping-btn-text');
        const originalText = btnText ? btnText.textContent : 'Copy Supply List';

        const doSuccess = () => {
            if (btnText) btnText.textContent = '✅ Copied!';
            setTimeout(() => {
                if (btnText) btnText.textContent = originalText;
            }, 2500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(doSuccess).catch(() => {
                fallbackCopy(text, doSuccess);
            });
        } else {
            fallbackCopy(text, doSuccess);
        }
    }

    function fallbackCopy(text, callback) {
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
            alert('Failed to copy to clipboard automatically.');
        }
        document.body.removeChild(ta);
    }

    // Escape key closes modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddModal();
            closeEditModal();
            closeInductModal();
        }
    });
</script>

<script>initScrollRestore('tool_wishlist_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
