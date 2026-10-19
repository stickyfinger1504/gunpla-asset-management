<?php
require '../includes/bootstrap.php';

$current_section = 'paints';
$current_page = 'mixing_recipes';
$page_title = 'Mixing Recipes';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $action_success = delete_recipe($conn, (int)$_POST['deleteid']);
        $msg_text = $action_success ? "✅ Recipe deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $imagepath = '';
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /mixing_recipes");
                exit;
            }
            $imagepath = $upload['path'];
        }
        $_POST['imagepath'] = $imagepath;


        $items = [];
        if (!empty($_POST['paintid']) && is_array($_POST['paintid'])) {
            foreach ($_POST['paintid'] as $i => $pid) {
                if (!empty($pid) && !empty($_POST['percentage'][$i])) {
                    $items[] = [
                        'paintid' => (int)$pid,
                        'percentage' => (int)$_POST['percentage'][$i],
                    ];
                }
            }
        }

        if (empty($items)) {
            set_flash_message('❌ Add at least one ingredient');
            header("Location: /mixing_recipes");
            exit;
        }

        $action_success = add_recipe($conn, $_POST, $items);
        $msg_text = $action_success ? "✅ Recipe added" : "❌ Error adding recipe";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        if (!empty($_FILES['image']['name'])) {
            $upload = handle_image_upload($_FILES['image'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
            if (!$upload['success']) {
                set_flash_message('❌ ' . $upload['error']);
                header("Location: /mixing_recipes");
                exit;
            }
            if (!empty($_POST['existing_imagepath'])) {
                delete_image_file($_POST['existing_imagepath'], PAINT_UPLOAD_DIR, PAINT_UPLOAD_URL_PREFIX);
            }
            $_POST['imagepath'] = $upload['path'];
        } else {
            $_POST['imagepath'] = $_POST['existing_imagepath'] ?? '';
        }


        $items = [];
        if (!empty($_POST['paintid']) && is_array($_POST['paintid'])) {
            foreach ($_POST['paintid'] as $i => $pid) {
                if (!empty($pid) && !empty($_POST['percentage'][$i])) {
                    $items[] = [
                        'paintid' => (int)$pid,
                        'percentage' => (int)$_POST['percentage'][$i],
                    ];
                }
            }
        }

        if (empty($items)) {
            set_flash_message('❌ Add at least one ingredient');
            header("Location: /mixing_recipes");
            exit;
        }

        $action_success = update_recipe($conn, $_POST, $items);
        $msg_text = $action_success ? "✅ Recipe updated" : "❌ Error updating recipe";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'add_to_wishlist') {
        $paint_name = trim($_POST['paint_name'] ?? '');
        $brandid = (int)($_POST['brandid'] ?? 0);
        $painttypeid = !empty($_POST['painttypeid']) ? (int)$_POST['painttypeid'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if ($brandid <= 0) {
            $brands = get_paint_brands($conn);
            $brandid = !empty($brands) ? (int)$brands[0]['id'] : 1;
        }

        $priorities = get_paint_wishlist_priorities($conn);
        $priorityid = null;
        foreach ($priorities as $p) {
            if (strcasecmp($p['label'], 'High') === 0) {
                $priorityid = (int)$p['id'];
                break;
            }
        }
        if (!$priorityid && !empty($priorities)) {
            $priorityid = (int)$priorities[0]['id'];
        }

        if (!empty($paint_name) && $priorityid && $brandid) {
            $wishlist_data = [
                'name' => $paint_name,
                'brandid' => $brandid,
                'painttypeid' => $painttypeid,
                'priorityid' => $priorityid,
                'link' => null,
                'notes' => $notes ?: 'Needed for mixing recipe'
            ];
            $action_success = add_paint_wishlist_item($conn, $wishlist_data);
            $msg_text = $action_success ? "✅ Added '{$paint_name}' to Paint Wishlist with High priority!" : "❌ Failed to add paint to wishlist";
        } else {
            $msg_text = "❌ Missing required paint details";
        }
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /mixing_recipes");
        exit;
    }
}

$message = get_flash_message();
$recipes = get_recipes($conn, $_GET);
$paint_dropdown = get_paints_for_dropdown($conn);
$has_filters = !empty($_GET['search']);

$ready_recipes = [];
$low_stock_recipes = [];
$missing_paint_recipes = [];
$unique_paints = [];
$all_shortage_items = [];

foreach ($recipes as &$r) {
    $has_missing = false;
    $has_low = false;

    foreach ($r['items'] as &$item) {
        $pid = (int)$item['paintid'];
        $unique_paints[$pid] = true;

        $is_missing = empty($item['paint_actualid']) || empty($item['paint_name']);
        $is_low = !$is_missing && is_low_stock_paint_amount($item['amount'] ?? null);

        if ($is_missing) {
            $item['stock_status'] = 'missing';
            $has_missing = true;
            $all_shortage_items[] = [
                'type' => 'missing',
                'recipe_name' => $r['name'],
                'recipeid' => $r['recipeid'],
                'paintid' => $pid,
                'paint_name' => $item['paint_name'] ?? ('Bottle #' . $pid),
                'paint_code' => $item['paint_code'] ?? '',
                'brand' => $item['brand'] ?? '',
                'brandid' => $item['brandid'] ?? null,
                'painttype' => $item['painttype'] ?? '',
                'painttypeid' => $item['painttypeid'] ?? null,
                'percentage' => $item['percentage'],
                'amount' => 'Missing from Shelf'
            ];
        } elseif ($is_low) {
            $item['stock_status'] = 'low';
            $has_low = true;
            $all_shortage_items[] = [
                'type' => 'low',
                'recipe_name' => $r['name'],
                'recipeid' => $r['recipeid'],
                'paintid' => $pid,
                'paint_name' => $item['paint_name'] ?? ('Bottle #' . $pid),
                'paint_code' => $item['paint_code'] ?? '',
                'brand' => $item['brand'] ?? '',
                'brandid' => $item['brandid'] ?? null,
                'painttype' => $item['painttype'] ?? '',
                'painttypeid' => $item['painttypeid'] ?? null,
                'percentage' => $item['percentage'],
                'amount' => $item['amount'] ?? 'Low'
            ];
        } else {
            $item['stock_status'] = 'ok';
        }
    }
    unset($item);

    if ($has_missing) {
        $r['readiness'] = 'missing';
        $r['readiness_label'] = 'Missing Bottle';
        $missing_paint_recipes[] = $r;
    } elseif ($has_low) {
        $r['readiness'] = 'low';
        $r['readiness_label'] = 'Low Stock';
        $low_stock_recipes[] = $r;
    } else {
        $r['readiness'] = 'ready';
        $r['readiness_label'] = 'Ready to Spray';
        $ready_recipes[] = $r;
    }
}
unset($r);

// Group shortages by paint for the restock bridge panel
$grouped_shortages = [];
foreach ($all_shortage_items as $shortage) {
    $key = $shortage['paintid'] ?: $shortage['paint_name'];
    if (!isset($grouped_shortages[$key])) {
        $grouped_shortages[$key] = [
            'paintid' => $shortage['paintid'],
            'paint_name' => $shortage['paint_name'],
            'paint_code' => $shortage['paint_code'],
            'brand' => $shortage['brand'],
            'brandid' => $shortage['brandid'],
            'painttype' => $shortage['painttype'],
            'painttypeid' => $shortage['painttypeid'],
            'type' => $shortage['type'],
            'amount' => $shortage['amount'],
            'needed_in' => []
        ];
    } else {
        if ($shortage['type'] === 'missing') {
            $grouped_shortages[$key]['type'] = 'missing';
            $grouped_shortages[$key]['amount'] = 'Missing from Shelf';
        }
    }
    $grouped_shortages[$key]['needed_in'][] = $shortage['recipe_name'] . ' (' . $shortage['percentage'] . '%)';
}
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-7xl mx-auto w-full">
    <h1 class="page-title font-bold text-gray-700 text-center mb-8">🧪 Mixing Recipes</h1>

    <?php include '../components/toast.php'; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <?php
        $value = count($recipes);
        $label = 'Total Recipes';
        $color = 'blue';
        include '../components/stats/stat_card.php';

        $value = count($ready_recipes);
        $label = 'Ready to Spray';
        $color = 'green';
        include '../components/stats/stat_card.php';

        $attention_count = count($low_stock_recipes) + count($missing_paint_recipes);
        $value = $attention_count;
        $label = 'Need Attention';
        $color = ($attention_count > 0 ? (count($missing_paint_recipes) > 0 ? 'red' : 'yellow') : 'gray');
        include '../components/stats/stat_card.php';

        $value = count($unique_paints);
        $label = 'Unique Paints';
        $color = 'purple';
        include '../components/stats/stat_card.php';
        ?>
    </div>


    <!-- Recipe Supply & Readiness Radar Hub (Option 2) -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span>🎯</span> Recipe Supply & Readiness Radar
            </h3>
            <span class="text-xs text-gray-500 font-medium hidden sm:inline">Real-time bottle stock verification, cup batch scaler & 1-click wishlist bridge</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Panel 1: Recipe Readiness Inspector -->
            <div id="radar-panel" class="bg-gradient-to-br from-white to-blue-50/40 border-2 border-blue-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-200">
                            <span class="inline-block w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                            🚦 Recipe Readiness Inspector
                        </span>
                        <?php if (count($recipes) > 1): ?>
                            <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
                                <button type="button" onclick="stepRadarRecipe(-1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Previous Recipe">◀</button>
                                <span id="radar-stepper-counter">1 of <?= count($recipes) ?></span>
                                <button type="button" onclick="stepRadarRecipe(1)" class="p-1 hover:bg-white rounded border border-gray-200 shadow-2xs transition-colors" title="Next Recipe">▶</button>
                                <select id="radar-recipe-select" onchange="selectRadarRecipe(parseInt(this.value, 10))" class="ml-1 text-xs border border-gray-200 rounded px-1.5 py-1 bg-white focus:outline-none focus:ring-1 focus:ring-blue-400 max-w-[140px] truncate">
                                    <?php foreach ($recipes as $idx => $r): ?>
                                        <option value="<?= $idx ?>"><?= e($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="text-xs text-blue-900 font-medium mb-3 block">
                        <?= count($ready_recipes) ?> of <?= count($recipes) ?> recipes ready to spray from current shelf stock
                    </span>

                    <div id="radar-card-content">
                        <?php if (!empty($recipes)): ?>
                            <?php 
                                $r0 = $recipes[0];
                                $badge_class = match($r0['readiness']) {
                                    'ready' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                                    'low' => 'bg-amber-100 text-amber-800 border border-amber-200',
                                    default => 'bg-red-100 text-red-800 border border-red-200'
                                };
                            ?>
                            <div class="flex items-start justify-between gap-3 mb-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <h4 class="text-lg font-bold text-gray-900 leading-snug"><?= e($r0['name']) ?></h4>
                                        <span class="px-2.5 py-0.5 text-xs font-bold rounded-full <?= $badge_class ?>">
                                            <?= ($r0['readiness'] === 'ready' ? '🟢 Ready to Spray' : ($r0['readiness'] === 'low' ? '🟡 Low Stock' : '🔴 Missing Bottle')) ?>
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <?php if (!empty($r0['thinner_ratio'])): ?>
                                            <span class="inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 font-medium">💧 Thinner: <?= e($r0['thinner_ratio']) ?></span>
                                        <?php endif; ?>
                                        <span class="text-xs text-gray-400"><?= count($r0['items']) ?> ingredient<?= count($r0['items']) === 1 ? '' : 's' ?></span>
                                    </div>
                                </div>
                                <?php if (!empty($r0['imagepath'])): ?>
                                    <a href="<?= e($r0['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="shrink-0">
                                        <img src="<?= e($r0['imagepath']) ?>" alt="Color swatch" class="w-16 h-16 object-cover rounded-lg border border-gray-200 hover:opacity-80 transition shadow-2xs">
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100 max-h-40 overflow-y-auto mb-4 space-y-1">
                                <?php foreach ($r0['items'] as $item): ?>
                                    <div class="flex items-center justify-between text-xs py-1.5 border-b border-blue-50 last:border-0">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="font-mono text-2xs bg-blue-100 text-blue-800 font-bold px-1.5 py-0.5 rounded w-10 text-center shrink-0"><?= $item['percentage'] ?>%</span>
                                            <span class="text-gray-800 font-medium truncate"><?= e($item['paint_name'] ?? 'Unknown Paint') ?></span>
                                            <?php if (!empty($item['brand'])): ?>
                                                <span class="text-2xs px-1.5 py-0.5 rounded-full <?= get_brand_color_palette($item['brandid']) ?> font-medium"><?= e($item['brand']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="shrink-0 ml-2">
                                            <?php if ($item['stock_status'] === 'missing'): ?>
                                                <span class="text-2xs px-2 py-0.5 rounded font-bold bg-red-100 text-red-800 border border-red-200">🔴 Missing</span>
                                            <?php elseif ($item['stock_status'] === 'low'): ?>
                                                <span class="text-2xs px-2 py-0.5 rounded font-bold bg-amber-100 text-amber-800 border border-amber-200">🟡 Low (<?= e($item['amount']) ?>)</span>
                                            <?php else: ?>
                                                <span class="text-2xs px-2 py-0.5 rounded font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">🟢 In Stock</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-8 text-center text-gray-500">
                                <div class="text-3xl mb-2">🧪</div>
                                <p class="text-sm font-semibold text-gray-700">No Mixing Recipes Yet</p>
                                <p class="text-xs text-gray-500 mt-1">Click the + button below to create your first custom formula.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($recipes)): ?>
                    <div class="pt-3 border-t border-blue-100 flex items-center justify-between gap-2 mt-auto">
                        <button type="button" onclick="triggerRadarScale()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold bg-green-600 hover:bg-green-700 text-white rounded-lg shadow-2xs transition-all">
                            <span>📏</span> Quick Scale Batch
                        </button>
                        <button type="button" onclick="triggerRadarEdit()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 bg-white hover:bg-blue-50 border border-blue-200 rounded-lg transition-colors shadow-2xs">
                            <span>✏️</span> Edit Formula
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Panel 2: Missing Ingredients & Restock Bridge -->
            <div id="bridge-panel" class="bg-gradient-to-br from-white to-amber-50/40 border-2 border-amber-300 rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                            <span class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                            🛒 Ingredient Restock Bridge
                        </span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= count($grouped_shortages) > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                            <?= count($grouped_shortages) > 0 ? count($grouped_shortages) . ' Bottleneck' . (count($grouped_shortages) === 1 ? '' : 's') : 'All In Stock' ?>
                        </span>
                    </div>
                    <span class="text-xs text-amber-900 font-medium mb-3 block">
                        Add missing or depleted paints straight to your Paint Wishlist with High priority in 1 click
                    </span>

                    <div id="bridge-card-content">
                        <?php if (!empty($grouped_shortages)): ?>
                            <div class="max-h-56 overflow-y-auto space-y-2.5 pr-1">
                                <?php foreach ($grouped_shortages as $s): ?>
                                    <div class="p-2.5 bg-white/90 border border-amber-200 rounded-lg shadow-2xs flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-sm font-bold text-gray-800 truncate"><?= e($s['paint_name']) ?></span>
                                                <?php if (!empty($s['paint_code'])): ?>
                                                    <span class="text-2xs font-mono text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded"><?= e($s['paint_code']) ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($s['brand'])): ?>
                                                    <span class="text-2xs px-1.5 py-0.5 rounded-full <?= get_brand_color_palette($s['brandid']) ?> font-medium"><?= e($s['brand']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($s['type'] === 'missing'): ?>
                                                    <span class="text-2xs px-2 py-0.5 rounded-full font-bold bg-red-100 text-red-800 border border-red-200">🔴 Missing Bottle</span>
                                                <?php else: ?>
                                                    <span class="text-2xs px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-800 border border-amber-200">🟡 Low (<?= e($s['amount']) ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1 truncate">
                                                Needed in: <span class="text-gray-700 font-medium"><?= e(implode(', ', $s['needed_in'])) ?></span>
                                            </div>
                                        </div>

                                        <form method="POST" class="inline m-0 shrink-0">
                                            <input type="hidden" name="action_type" value="add_to_wishlist">
                                            <input type="hidden" name="paint_name" value="<?= htmlspecialchars($s['paint_name']) ?>">
                                            <input type="hidden" name="brandid" value="<?= (int)($s['brandid'] ?? 0) ?>">
                                            <input type="hidden" name="painttypeid" value="<?= (int)($s['painttypeid'] ?? 0) ?>">
                                            <input type="hidden" name="notes" value="Needed for recipe: <?= htmlspecialchars(implode(', ', $s['needed_in'])) ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow-2xs transition-all whitespace-nowrap"
                                                    title="Add this paint to Paint Wishlist with High priority">
                                                <span>➕</span> Wishlist
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="py-8 text-center">
                                <div class="text-3xl mb-2">✨</div>
                                <p class="text-sm font-semibold text-gray-700">All Recipe Ingredients In Stock!</p>
                                <p class="text-xs text-gray-500 mt-1">Every paint required across your <?= count($recipes) ?> recipes is currently on your paint shelf. You are ready to spray!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="pt-3 border-t border-amber-200 flex items-center justify-between gap-2 mt-auto">
                    <span class="text-xs text-amber-800 font-medium">
                        <?php if (!empty($grouped_shortages)): ?>
                            💡 Tip: Wishlisted bottles sync with your Paint Wishlist Supply Run
                        <?php else: ?>
                            🎨 Shelf inventory is fully prepared for all registered recipes
                        <?php endif; ?>
                    </span>
                    <a href="/paint_wishlist" class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 hover:text-amber-900">
                        View Wishlist <span>→</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
        <form method="GET" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       placeholder="Recipe name..."
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Search</button>
                <?php if ($has_filters): ?>
                <button type="button" onclick="clearFilters(this)" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Clear</button>
                <?php endif; ?>
            </div>
        </form>
    </div>


    <?php if (!empty($recipes)): ?>
    <div class="space-y-4">
        <?php foreach ($recipes as $recipe): ?>
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg font-bold text-gray-800"><?= e($recipe['name']) ?></h3>
                        <?php if ($recipe['readiness'] === 'ready'): ?>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                                🟢 Ready to Spray
                            </span>
                        <?php elseif ($recipe['readiness'] === 'low'): ?>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                                🟡 Low Stock
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800 border border-red-200">
                                🔴 Missing Bottle
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3 space-y-1">
                        <?php foreach ($recipe['items'] as $item): ?>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-mono text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded w-12 text-center">
                                <?= $item['percentage'] ?>%
                            </span>
                            <span class="text-gray-700 font-medium"><?= e($item['paint_name'] ?? 'Unknown Paint') ?></span>
                            <?php if (!empty($item['paint_code'])): ?>
                                <span class="text-xs font-mono text-gray-400"><?= e($item['paint_code']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($item['brand'])): ?>
                                <span class="px-1.5 py-0.2 text-2xs rounded-full <?= get_brand_color_palette($item['brandid']) ?>">
                                    <?= e($item['brand']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($item['stock_status'] === 'missing'): ?>
                                <span class="text-2xs px-1.5 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 font-bold">Missing</span>
                            <?php elseif ($item['stock_status'] === 'low'): ?>
                                <span class="text-2xs px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 font-bold">Low (<?= e($item['amount']) ?>)</span>
                            <?php else: ?>
                                <span class="text-2xs px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium"><?= e($item['amount'] ?: 'In Stock') ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-3 flex items-center gap-4 text-xs text-gray-400">
                        <?php if (!empty($recipe['thinner_ratio'])): ?>
                        <span>💧 Thinner: <?= e($recipe['thinner_ratio']) ?></span>
                        <?php endif; ?>
                        <span><?= date('d M Y', strtotime($recipe['createdat'])) ?></span>
                    </div>

                    <?php if (!empty($recipe['notes'])): ?>
                    <p class="mt-2 text-sm text-gray-500"><?= e($recipe['notes']) ?></p>
                    <?php endif; ?>
                </div>


                <?php if (!empty($recipe['imagepath'])): ?>
                <a href="<?= e($recipe['imagepath']) ?>" target="_blank" rel="noopener noreferrer" class="ml-4 flex-shrink-0">
                    <img src="<?= e($recipe['imagepath']) ?>" alt="Color swatch"
                         class="w-20 h-20 object-cover rounded-lg border hover:opacity-80 transition"
                         loading="lazy">
                </a>
                <?php endif; ?>
            </div>

            <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-2">
                <button type="button" class="text-sm text-blue-500 hover:text-blue-700"
                        data-recipe='<?= htmlspecialchars(json_encode($recipe), ENT_QUOTES) ?>'
                        onclick="openEditRecipeModal(this)">
                    ✏️ Edit
                </button>
                <button type="button" class="text-sm text-green-500 hover:text-green-700 ml-2"
                        data-recipe='<?= htmlspecialchars(json_encode($recipe), ENT_QUOTES) ?>'
                        onclick="openScaleModal(this)">
                    📏 Scale
                </button>
                <form method="POST" class="inline" onsubmit='return confirm("Delete <?= e($recipe['name']) ?>?");'>
                    <input type="hidden" name="deleteid" value="<?= $recipe['recipeid'] ?>">
                    <button type="submit" class="text-sm text-red-500 hover:text-red-700">🗑️ Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center text-gray-500">
        No recipes yet. Start mixing!
    </div>
    <?php endif; ?>
</div>

<button onclick="openAddModal()"
        class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
        title="New Recipe">
    +
</button>

<?php $mode = 'add'; include '../components/mixing_recipe_modal.php'; ?>

<?php $mode = 'edit'; include '../components/mixing_recipe_modal.php'; ?>

<!-- Scale Modal Swatch -->
<div id="scaleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 justify-center items-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md overflow-hidden">
        <div class="bg-green-500 text-white px-4 py-3 flex justify-between items-center">
            <h3 class="font-bold flex items-center gap-1">📏 Recipe Scaler: <span id="scale-recipe-name"></span></h3>
            <button onclick="closeScaleModal()" class="text-white hover:text-gray-200 text-xl font-bold">×</button>
        </div>
        <div class="p-4 space-y-4">
            <div class="flex gap-4 items-start">
                <div class="flex-1">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Target Volume</label>
                    <input type="number" id="scale-volume" value="10" min="0.1" step="any" oninput="calculateScaledRecipe()" class="w-full border border-gray-300 p-2 rounded text-sm focus:ring-2 focus:ring-green-500 focus:outline-none">
                    
                    <!-- Airbrush Quick Cup Presets -->
                    <div class="mt-2">
                        <span class="block text-2xs font-bold text-gray-400 uppercase tracking-wider mb-1">Airbrush Cup Presets</span>
                        <div class="flex flex-wrap gap-1">
                            <button type="button" onclick="setTargetVolume(2, 'ml')" class="px-2 py-1 text-2xs font-semibold bg-gray-100 hover:bg-green-100 hover:text-green-800 text-gray-700 rounded border border-gray-200 transition">2ml (Mini Cup)</button>
                            <button type="button" onclick="setTargetVolume(7, 'ml')" class="px-2 py-1 text-2xs font-semibold bg-gray-100 hover:bg-green-100 hover:text-green-800 text-gray-700 rounded border border-gray-200 transition">7ml (Std Gravity)</button>
                            <button type="button" onclick="setTargetVolume(15, 'ml')" class="px-2 py-1 text-2xs font-semibold bg-gray-100 hover:bg-green-100 hover:text-green-800 text-gray-700 rounded border border-gray-200 transition">15ml (Large Cup)</button>
                            <button type="button" onclick="setTargetVolume(30, 'ml')" class="px-2 py-1 text-2xs font-semibold bg-gray-100 hover:bg-green-100 hover:text-green-800 text-gray-700 rounded border border-gray-200 transition">30ml (Mixing Jar)</button>
                        </div>
                    </div>
                </div>
                <div class="w-28">
                    <label class="block text-sm font-bold text-gray-700 mb-1">Unit</label>
                    <select id="scale-unit" onchange="calculateScaledRecipe()" class="w-full border border-gray-300 p-2 rounded text-sm focus:ring-2 focus:ring-green-500 focus:outline-none">
                        <option value="ml">ml</option>
                        <option value="drops">Drops</option>
                        <option value="parts">Parts</option>
                        <option value="oz">oz</option>
                    </select>
                </div>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Ingredients</h4>
                <div id="scale-ingredients-list" class="space-y-2 max-h-48 overflow-y-auto"></div>
            </div>
            <div id="scale-thinner-area" class="p-3 bg-blue-50 border border-blue-100 rounded-lg text-sm text-blue-800 hidden">
                <span class="font-bold">💧 Thinner Calculation:</span>
                <p id="scale-thinner-result" class="mt-1"></p>
            </div>
        </div>
    </div>
</div>

<script>
    const recipesData = <?= json_encode($recipes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let currentRadarIndex = 0;
    let currentScaleRecipe = null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stepRadarRecipe(delta) {
        if (!recipesData || recipesData.length === 0) return;
        currentRadarIndex = (currentRadarIndex + delta + recipesData.length) % recipesData.length;
        renderRadarRecipe();
    }

    function selectRadarRecipe(idx) {
        if (!recipesData || !recipesData[idx]) return;
        currentRadarIndex = idx;
        renderRadarRecipe();
    }

    function renderRadarRecipe() {
        if (!recipesData || recipesData.length === 0) return;
        const r = recipesData[currentRadarIndex];

        const counterEl = document.getElementById('radar-stepper-counter');
        if (counterEl) counterEl.textContent = `${currentRadarIndex + 1} of ${recipesData.length}`;

        const selectEl = document.getElementById('radar-recipe-select');
        if (selectEl) selectEl.value = currentRadarIndex;

        const contentEl = document.getElementById('radar-card-content');
        if (!contentEl) return;

        let readinessBadge = '';
        if (r.readiness === 'ready') {
            readinessBadge = '<span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">🟢 Ready to Spray</span>';
        } else if (r.readiness === 'low') {
            readinessBadge = '<span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">🟡 Low Stock</span>';
        } else {
            readinessBadge = '<span class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-red-100 text-red-800 border border-red-200">🔴 Missing Bottle</span>';
        }

        let thinnerHtml = r.thinner_ratio ? `<span class="inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 font-medium">💧 Thinner: ${escapeHtml(r.thinner_ratio)}</span>` : '';

        let itemsHtml = '';
        r.items.forEach(item => {
            let stockHtml = '';
            if (item.stock_status === 'missing') {
                stockHtml = '<span class="text-2xs px-2 py-0.5 rounded font-bold bg-red-100 text-red-800 border border-red-200">🔴 Missing</span>';
            } else if (item.stock_status === 'low') {
                stockHtml = `<span class="text-2xs px-2 py-0.5 rounded font-bold bg-amber-100 text-amber-800 border border-amber-200">🟡 Low (${escapeHtml(item.amount || 'Few')})</span>`;
            } else {
                stockHtml = `<span class="text-2xs px-2 py-0.5 rounded font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">🟢 In Stock</span>`;
            }

            let brandHtml = item.brand ? `<span class="text-2xs px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-700 font-medium">${escapeHtml(item.brand)}</span>` : '';

            itemsHtml += `
                <div class="flex items-center justify-between text-xs py-1.5 border-b border-blue-50 last:border-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-mono text-2xs bg-blue-100 text-blue-800 font-bold px-1.5 py-0.5 rounded w-10 text-center shrink-0">${item.percentage}%</span>
                        <span class="text-gray-800 font-medium truncate">${escapeHtml(item.paint_name || 'Unknown Paint')}</span>
                        ${brandHtml}
                    </div>
                    <div class="shrink-0 ml-2">
                        ${stockHtml}
                    </div>
                </div>
            `;
        });

        let imgHtml = r.imagepath ? `
            <a href="${escapeHtml(r.imagepath)}" target="_blank" rel="noopener noreferrer" class="shrink-0">
                <img src="${escapeHtml(r.imagepath)}" alt="Color swatch" class="w-16 h-16 object-cover rounded-lg border border-gray-200 hover:opacity-80 transition shadow-2xs">
            </a>
        ` : '';

        contentEl.innerHTML = `
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h4 class="text-lg font-bold text-gray-900 leading-snug">${escapeHtml(r.name)}</h4>
                        ${readinessBadge}
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        ${thinnerHtml}
                        <span class="text-xs text-gray-400">${r.items.length} ingredient${r.items.length === 1 ? '' : 's'}</span>
                    </div>
                </div>
                ${imgHtml}
            </div>
            <div class="bg-white/80 rounded-lg p-2.5 border border-blue-100 max-h-40 overflow-y-auto mb-4 space-y-1">
                ${itemsHtml}
            </div>
        `;
    }

    function triggerRadarScale() {
        if (!recipesData || !recipesData[currentRadarIndex]) return;
        openScaleModal(recipesData[currentRadarIndex]);
    }

    function triggerRadarEdit() {
        if (!recipesData || !recipesData[currentRadarIndex]) return;
        openEditRecipeModal(recipesData[currentRadarIndex]);
    }

    function setTargetVolume(vol, unit = 'ml') {
        document.getElementById('scale-volume').value = vol;
        if (unit) {
            document.getElementById('scale-unit').value = unit;
        }
        calculateScaledRecipe();
    }

    function openScaleModal(source) {
        if (!source) return;
        if (source && source.getAttribute && source.getAttribute('data-recipe')) {
            currentScaleRecipe = JSON.parse(source.getAttribute('data-recipe'));
        } else if (typeof source === 'object') {
            currentScaleRecipe = source;
        } else if (typeof source === 'string') {
            currentScaleRecipe = JSON.parse(source);
        }
        if (!currentScaleRecipe) return;

        document.getElementById('scale-recipe-name').textContent = currentScaleRecipe.name;
        document.getElementById('scale-volume').value = 10; // Default target
        
        calculateScaledRecipe();

        document.getElementById('scaleModal').classList.remove('hidden');
        document.getElementById('scaleModal').style.display = 'flex';
    }

    function closeScaleModal() {
        document.getElementById('scaleModal').classList.add('hidden');
        document.getElementById('scaleModal').style.display = 'none';
        currentScaleRecipe = null;
    }

    function calculateScaledRecipe() {
        if (!currentScaleRecipe) return;
        
        const volume = parseFloat(document.getElementById('scale-volume').value) || 0;
        const unit = document.getElementById('scale-unit').value;
        const listContainer = document.getElementById('scale-ingredients-list');
        listContainer.innerHTML = '';

        if (volume <= 0) return;

        // Render scaled quantities with stock badges
        currentScaleRecipe.items.forEach(item => {
            const calculatedQty = ((item.percentage / 100) * volume).toFixed(2);
            const row = document.createElement('div');
            row.className = 'flex justify-between items-center text-sm border-b pb-1.5 border-gray-100';

            let stockBadge = '';
            if (item.stock_status === 'missing') {
                stockBadge = '<span class="text-2xs px-1.5 py-0.5 rounded font-bold bg-red-100 text-red-700">Missing</span>';
            } else if (item.stock_status === 'low') {
                stockBadge = `<span class="text-2xs px-1.5 py-0.5 rounded font-bold bg-amber-100 text-amber-700">Low (${escapeHtml(item.amount || 'Few')})</span>`;
            } else {
                stockBadge = '<span class="text-2xs px-1.5 py-0.5 rounded font-bold bg-emerald-100 text-emerald-700">In Stock</span>';
            }

            row.innerHTML = `
                <div class="flex items-center gap-1.5 min-w-0 pr-2">
                    <span class="text-gray-700 font-medium truncate">${escapeHtml(item.paint_name || 'Unknown Paint')}</span>
                    ${stockBadge}
                </div>
                <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-800 font-semibold shrink-0 text-xs">${calculatedQty} ${unit} <span class="text-2xs text-gray-400 font-normal">(${item.percentage}%)</span></span>
            `;
            listContainer.appendChild(row);
        });

        // Parse Thinner Ratios (e.g. "1:1", "1:1.5", "1:2")
        const thinnerArea = document.getElementById('scale-thinner-area');
        const thinnerRatioStr = currentScaleRecipe.thinner_ratio;
        
        if (thinnerRatioStr && thinnerRatioStr.trim() !== '') {
            thinnerArea.classList.remove('hidden');
            const ratioPattern = /([0-9.]+):([0-9.]+)/;
            const match = thinnerRatioStr.match(ratioPattern);
            
            if (match) {
                const paintRatio = parseFloat(match[1]);
                const thinnerRatio = parseFloat(match[2]);
                if (paintRatio > 0) {
                    const multiplier = thinnerRatio / paintRatio;
                    const calculatedThinner = (volume * multiplier).toFixed(2);
                    document.getElementById('scale-thinner-result').innerHTML = 
                        `Using a <strong>${escapeHtml(thinnerRatioStr)}</strong> ratio, add <strong>${calculatedThinner} ${unit}</strong> of thinner for a total mix of <strong>${(volume + parseFloat(calculatedThinner)).toFixed(2)} ${unit}</strong>.`;
                }
            } else {
                // Fallback if not standard numbers
                document.getElementById('scale-thinner-result').innerHTML = 
                    `Thinner ratio is set to <strong>${escapeHtml(thinnerRatioStr)}</strong>. Scale manually as needed.`;
            }
        } else {
            thinnerArea.classList.add('hidden');
        }
    }

    const paintOptionsHTML = `
        <option value="">-- Select Paint --</option>
        <?php foreach($paint_dropdown as $p): ?>
            <option value="<?= $p['actualid'] ?>"><?= e($p['id'] . ' — ' . $p['name']) ?></option>
        <?php endforeach; ?>
    `;

    function addIngredientRow(containerId, paintid = '', percentage = '') {
        const container = document.getElementById(containerId);
        const row = document.createElement('div');
        row.className = 'flex gap-2 items-center ingredient-row';
        row.innerHTML = `
            <select name="paintid[]" required class="flex-1 p-2 border border-gray-300 rounded text-sm">
                ${paintOptionsHTML}
            </select>
            <input type="number" name="percentage[]" required placeholder="%" min="1" max="100"
                   value="${percentage}"
                   class="w-20 p-2 border border-gray-300 rounded text-sm text-center">
            <button type="button" onclick="removeIngredient(this)"
                    class="text-red-400 hover:text-red-600 text-lg px-1" title="Remove">🗑️</button>
        `;
        container.appendChild(row);

        if (paintid) {
            row.querySelector('select').value = paintid;
        }
    }

    function removeIngredient(button) {
        const container = button.closest('.ingredient-row').parentElement;
        if (container.querySelectorAll('.ingredient-row').length > 1) {
            button.closest('.ingredient-row').remove();
        }
    }

    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
        document.getElementById('addModal').style.display = 'none';
    }

    function openEditRecipeModal(source) {
        if (!source) return;
        let recipe = null;
        if (source && source.getAttribute && source.getAttribute('data-recipe')) {
            recipe = JSON.parse(source.getAttribute('data-recipe'));
        } else if (typeof source === 'object') {
            recipe = source;
        } else if (typeof source === 'string') {
            recipe = JSON.parse(source);
        }
        if (!recipe) return;

        document.getElementById('edit_recipeid').value = recipe.recipeid;
        document.getElementById('edit_name').value = recipe.name;
        document.getElementById('edit_thinner_ratio').value = recipe.thinner_ratio || '';
        document.getElementById('edit_notes').value = recipe.notes || '';
        document.getElementById('edit_existing_imagepath').value = recipe.imagepath || '';

        if (recipe.imagepath) {
            document.getElementById('edit_image_preview').classList.remove('hidden');
            document.getElementById('edit_image_thumb').src = recipe.imagepath;
        } else {
            document.getElementById('edit_image_preview').classList.add('hidden');
        }

        const container = document.getElementById('edit-ingredients-list');
        container.innerHTML = '';
        recipe.items.forEach(item => {
            addIngredientRow('edit-ingredients-list', item.paintid, item.percentage);
        });

        document.getElementById('editModal').classList.remove('hidden');
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.getElementById('editModal').style.display = 'none';
    }
</script>

<script>initScrollRestore('mixing_recipes_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
