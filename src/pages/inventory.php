<?php
require '../includes/bootstrap.php';

$current_section = 'kits';
$current_page = 'inventory';
$page_title = 'Inventory';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_success = false;

    if (isset($_POST['deleteid'])) {
        $action_success = delete_kit($conn, $_POST['deleteid']);
        $msg_text = $action_success ? "✅ Successfully Deleted" : "❌ Delete failed";
    }
    elseif (isset($_POST['archiveid'])) {
        $action_success = archive_kit($conn, $_POST['archiveid']);
        $msg_text = $action_success ? "✅ Successfully Archived" : "❌ Failed to archive";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'add') {
        $action_success = add_kit($conn, $_POST);
        $msg_text = $action_success ? "✅ Successfully added " . htmlspecialchars($_POST["kit_name"]) : "❌ Error adding kit";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'edit') {
        $action_success = update_kit($conn, $_POST);
        $msg_text = $action_success ? "✅ Kit updated successfully" : "❌ Error updating kit";
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'quick_start') {
        $kit_id = (int)($_POST['kit_id'] ?? 0);
        $kit_in_progress_id = get_category_id_by_label($conn, 'kitinventory', 'status', 'In Progress');
        if ($kit_id && $kit_in_progress_id) {
            $stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
            $stmt->bind_param("ii", $kit_in_progress_id, $kit_id);
            $action_success = $stmt->execute();

            $chk = $conn->prepare("SELECT backlogid FROM kit_backlog_plan WHERE inventoryid = ? LIMIT 1");
            $chk->bind_param("i", $kit_id);
            $chk->execute();
            $bp_in_progress = get_category_id_by_label($conn, 'backlogplan', 'status', 'In Progress');
            if ($chk->get_result()->num_rows === 0) {
                $default_bp_id = get_category_id_by_label($conn, 'backlogplan', 'buildplan', 'Clean Build');
                if ($default_bp_id && $bp_in_progress) {
                    $ins_bp = $conn->prepare("INSERT INTO kit_backlog_plan (inventoryid, buildplanid, status, notes) VALUES (?, ?, ?, 'Started from Workbench Hub')");
                    $ins_bp->bind_param("iii", $kit_id, $default_bp_id, $bp_in_progress);
                    $ins_bp->execute();
                }
            } else {
                if ($bp_in_progress) {
                    $upd_bp = $conn->prepare("UPDATE kit_backlog_plan SET status = ? WHERE inventoryid = ?");
                    $upd_bp->bind_param("ii", $bp_in_progress, $kit_id);
                    $upd_bp->execute();
                }
            }
            $msg_text = $action_success ? "🚀 Build started! Kit is now on your cutting mat." : "❌ Error starting build";
        }
    }
    elseif (isset($_POST['action_type']) && $_POST['action_type'] == 'quick_finish') {
        $kit_id = (int)($_POST['kit_id'] ?? 0);
        $kit_done_id = get_category_id_by_label($conn, 'kitinventory', 'status', 'Done');
        if ($kit_id && $kit_done_id) {
            $stmt = $conn->prepare("UPDATE kit_inventory SET status = ? WHERE inventoryid = ?");
            $stmt->bind_param("ii", $kit_done_id, $kit_id);
            $action_success = $stmt->execute();

            $bp_in_progress = get_category_id_by_label($conn, 'backlogplan', 'status', 'In Progress');
            $bp_done = get_category_id_by_label($conn, 'backlogplan', 'status', 'Done');
            if ($bp_in_progress && $bp_done) {
                $upd_bp = $conn->prepare("UPDATE kit_backlog_plan SET status = ? WHERE inventoryid = ? AND status = ?");
                $upd_bp->bind_param("iii", $bp_done, $kit_id, $bp_in_progress);
                $upd_bp->execute();
            }
            $msg_text = $action_success ? "🎉 Congratulations! Kit marked as Done!" : "❌ Error updating kit";
        }
    }

    if (isset($msg_text)) {
        set_flash_message($msg_text);
        header("Location: /inventory");
        exit;
    }
}

$message = get_flash_message();

$brands = get_brands($conn);
$statuses = get_statuses($conn);
$kits = get_kit_inventory($conn, $_GET);

$stats = calculate_kit_stats($kits);

$has_filters = !empty($_GET['filter_brand']) || !empty($_GET['search']) || !empty($_GET['filter_status']);

// Status ID mapping for dynamic color theme alignment with table badges
$status_map = [];
foreach ($statuses as $st) {
    $status_map[strtolower(trim($st['label']))] = (int)$st['id'];
}
$in_progress_id = $status_map['in progress'] ?? null;
$not_started_id = $status_map['not started'] ?? null;
$straight_build_id = $status_map['straight build'] ?? null;

if (!function_exists('get_palette_theme')) {
    function get_palette_theme($palette_class) {
        if (preg_match('/bg-([a-z]+)-100/', $palette_class, $m)) {
            $color = $m[1];
            return [
                'color' => $color,
                'badge' => $palette_class,
                'border' => "border-{$color}-300",
                'bg_gradient' => "from-white to-{$color}-50/40",
                'bg_tint' => "bg-{$color}-50",
                'divider' => "border-{$color}-100",
                'pulse' => "bg-{$color}-500",
                'button' => "bg-{$color}-600 hover:bg-{$color}-700 text-white",
                'text' => "text-{$color}-800"
            ];
        }
        return [
            'color' => 'blue',
            'badge' => 'bg-blue-100 text-blue-800',
            'border' => 'border-blue-300',
            'bg_gradient' => 'from-white to-blue-50/40',
            'bg_tint' => 'bg-blue-50',
            'divider' => 'border-blue-100',
            'pulse' => 'bg-blue-500',
            'button' => 'bg-blue-600 hover:bg-blue-700 text-white',
            'text' => 'text-blue-800'
        ];
    }
}

$in_progress_theme = get_palette_theme(get_brand_color_palette($in_progress_id));
$not_started_theme = get_palette_theme(get_brand_color_palette($not_started_id));
$straight_build_palette = $straight_build_id ? get_brand_color_palette($straight_build_id) : 'bg-lime-100 text-lime-800';

// Resolve Workbench Hub datasets
$all_kits = $has_filters ? get_kit_inventory($conn) : $kits;
$in_progress_kits = array_values(array_filter($all_kits, fn($k) => strtolower(trim($k['status'] ?? '')) === 'in progress'));
$not_started_kits = array_values(array_filter($all_kits, fn($k) => strtolower(trim($k['status'] ?? '')) === 'not started'));
$straight_build_kits = array_values(array_filter($all_kits, fn($k) => strtolower(trim($k['status'] ?? '')) === 'straight build'));

$active_kit = !empty($in_progress_kits) ? $in_progress_kits[0] : null;
$active_backlog = null;
if ($active_kit) {
    $b_stmt = $conn->prepare("SELECT b.backlogid, b.status, c.label as buildplan_label FROM kit_backlog_plan b LEFT JOIN dim_category c ON b.buildplanid = c.id WHERE b.inventoryid = ? ORDER BY b.backlogid DESC LIMIT 1");
    $b_stmt->bind_param("i", $active_kit['actualid']);
    $b_stmt->execute();
    $b_res = $b_stmt->get_result();
    if ($b_res && $b_row = $b_res->fetch_assoc()) {
        $active_backlog = $b_row;
    }
}
$next_kit = !empty($not_started_kits) ? $not_started_kits[0] : null;
?>
<?php include '../components/layout_header.php'; ?>

        <div class="max-w-7xl mx-auto w-full"> <h1 class="page-title font-bold text-gray-700 text-center mb-8">🤖 Gunpla Hangar</h1>
            
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
                    $value = $stats['total_kits'];
                    $label = $has_filters ? 'Filtered Kits' : 'Total Kits';
                    $color = 'blue';
                    include '../components/stats/stat_card.php';
                    
                    $value = format_currency($stats['total_spent']);
                    $label = 'Total Invested';
                    $color = 'green';
                    include '../components/stats/stat_card.php';
                    
                    $value = format_currency($stats['avg_price']);
                    $label = 'Avg Price';
                    $color = 'yellow';
                    include '../components/stats/stat_card.php';
                    
                    $value = format_currency($stats['max_price']);
                    $label = 'Most Expensive';
                    $color = 'purple';
                    include '../components/stats/stat_card.php';
                    ?>
                </div>
            </div>

            <!-- Workbench Hub -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <span>🛠️</span> Workbench Hub
                    </h3>
                    <span class="text-xs text-gray-500 font-medium hidden sm:inline">Active build workbench & backlog pipeline</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <!-- Panel 1: Currently on the Mat -->
                    <div class="bg-gradient-to-br <?= $in_progress_theme['bg_gradient'] ?> border-2 <?= $in_progress_theme['border'] ?> rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?= $in_progress_theme['badge'] ?> border <?= $in_progress_theme['border'] ?>">
                                    <span class="w-2 h-2 rounded-full <?= $in_progress_theme['pulse'] ?> animate-pulse"></span>
                                    In Progress
                                </span>
                                <?php if ($active_kit && !empty($active_kit['datebought'])): ?>
                                    <span class="text-xs text-gray-500 font-mono">Bought: <?= date('M d, Y', strtotime($active_kit['datebought'])) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($active_kit): ?>
                                <div class="mb-4">
                                    <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                        <a href="/kit/<?= $active_kit['actualid'] ?>" class="hover:text-blue-600 transition-colors">
                                            <?= htmlspecialchars($active_kit['name']) ?>
                                        </a>
                                    </h4>
                                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($active_kit['brandid']) ?>">
                                            <?= htmlspecialchars($active_kit['brand']) ?>
                                        </span>
                                        <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= get_brand_color_palette($active_kit['statusid']) ?>">
                                            <?= htmlspecialchars($active_kit['status']) ?>
                                        </span>
                                        <?php if ($active_backlog && !empty($active_backlog['buildplan_label'])): ?>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                                                📋 <?= htmlspecialchars($active_backlog['buildplan_label']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (count($in_progress_kits) > 1): ?>
                                            <span class="text-xs font-medium <?= $in_progress_theme['text'] ?>">
                                                +<?= count($in_progress_kits) - 1 ?> more active
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="py-6 text-center">
                                    <div class="text-3xl mb-2">✂️</div>
                                    <p class="text-sm font-semibold text-gray-700">The cutting mat is clear!</p>
                                    <p class="text-xs text-gray-500 mt-1">Pick a kit from the queue on the right or your hangar to start building.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($active_kit): ?>
                            <div class="pt-3 border-t <?= $in_progress_theme['divider'] ?> flex flex-wrap items-center justify-between gap-2 mt-auto">
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if ($active_backlog): ?>
                                        <a href="/blueprint?backlogid=<?= $active_backlog['backlogid'] ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg transition-colors">
                                            📐 Blueprint
                                        </a>
                                        <a href="/build_progress?filter_backlog=<?= $active_backlog['backlogid'] ?>" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-lg transition-colors">
                                            📝 Build Log
                                        </a>
                                    <?php else: ?>
                                        <a href="/backlog" 
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg transition-colors">
                                            📋 Plan Build
                                        </a>
                                    <?php endif; ?>
                                    <a href="/kit/<?= $active_kit['actualid'] ?>" 
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-gray-500 hover:text-gray-900 transition-colors font-medium">
                                        Details →
                                    </a>
                                </div>
                                
                                <form method="POST" onsubmit="return confirm('Complete this build? Marking as Done will update its status across the hangar and backlog.');">
                                    <input type="hidden" name="action_type" value="quick_finish">
                                    <input type="hidden" name="kit_id" value="<?= $active_kit['actualid'] ?>">
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-800 border border-emerald-300 rounded-lg transition-all shadow-2xs">
                                        ✅ Finish Build
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel 2: Next in Queue & Backlog Randomizer -->
                    <div id="queue-panel" class="bg-gradient-to-br <?= $not_started_theme['bg_gradient'] ?> border-2 <?= $not_started_theme['border'] ?> rounded-xl p-5 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                <span id="queue-badge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?= $not_started_theme['badge'] ?> border <?= $not_started_theme['border'] ?>">
                                    📦 Next Off the Shelf
                                </span>
                                <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 font-medium cursor-pointer select-none bg-white/80 hover:bg-white px-2.5 py-1 rounded-full border border-gray-200 shadow-2xs transition-colors">
                                    <input type="checkbox" id="toggle-include-straight" onchange="toggleIncludeStraight(this.checked)" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                    <span>+ Straight Builds (<?= count($straight_build_kits) ?>)</span>
                                </label>
                            </div>
                            <span id="queue-count-text" class="text-xs <?= $not_started_theme['text'] ?> font-medium mb-3 block">
                                <?= count($not_started_kits) ?> unbuilt waiting
                            </span>

                            <div id="queue-kit-container">
                                <?php if ($next_kit): ?>
                                    <div class="mb-4">
                                        <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                            <a id="queue-kit-link" href="/kit/<?= $next_kit['actualid'] ?>" class="hover:text-blue-600 transition-colors">
                                                <?= htmlspecialchars($next_kit['name']) ?>
                                            </a>
                                        </h4>
                                        <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= get_brand_color_palette($next_kit['brandid']) ?>">
                                                <?= htmlspecialchars($next_kit['brand']) ?>
                                            </span>
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= get_brand_color_palette($next_kit['statusid']) ?>">
                                                <?= htmlspecialchars($next_kit['status']) ?>
                                            </span>
                                            <?php if (!empty($next_kit['pricebought'])): ?>
                                                <span class="text-xs text-gray-500 font-mono">
                                                    <?= format_currency($next_kit['pricebought']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="py-6 text-center">
                                        <div class="text-3xl mb-2">🎉</div>
                                        <p class="text-sm font-semibold text-gray-700">Zero Backlog Left!</p>
                                        <p class="text-xs text-gray-500 mt-1">Every kit in your hangar has been started or completed.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pt-3 border-t <?= $not_started_theme['divider'] ?> flex flex-wrap items-center justify-between gap-2 mt-auto">
                            <form id="queue-start-form" method="POST" <?= !$next_kit ? 'style="display:none;"' : '' ?>>
                                <input type="hidden" name="action_type" value="quick_start">
                                <input type="hidden" name="kit_id" id="queue-form-kit-id" value="<?= $next_kit ? $next_kit['actualid'] : '' ?>">
                                <button id="queue-start-btn" type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 rounded-lg transition-all shadow-2xs">
                                    🚀 Start This Build
                                </button>
                            </form>

                            <button id="queue-random-btn" type="button" onclick="rollRandomKit()" 
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 rounded-lg transition-colors shadow-2xs ml-auto"
                                    <?= (count($not_started_kits) <= 1 && count($straight_build_kits) === 0) ? 'style="display:none;"' : '' ?>>
                                🎲 Pick Random Kit
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Random Roll Modal -->
            <div id="random-roll-modal" style="display:none;" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl max-w-md w-full p-6 text-center shadow-2xl transform transition-all border border-gray-100">
                    <div class="text-4xl mb-2" id="roll-dice-icon">🎲</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1" id="roll-modal-title">Choosing Next Build...</h3>
                    <p class="text-xs text-gray-500 mb-4" id="roll-modal-subtitle">Cycling through available kits</p>
                    
                    <div id="roll-result-card" class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-5 min-h-[100px] flex flex-col justify-center items-center">
                        <h4 id="roll-kit-name" class="text-lg font-bold text-gray-800 leading-snug animate-pulse">Rolling...</h4>
                        <div id="roll-kit-badges" class="flex flex-wrap items-center gap-1.5 mt-2">
                            <span class="text-xs text-gray-500 font-medium">Please wait...</span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" onclick="closeRollModal()" class="flex-1 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                            Close
                        </button>
                        <form id="roll-start-form" method="POST" class="flex-1" style="display:none;">
                            <input type="hidden" name="action_type" value="quick_start">
                            <input type="hidden" name="kit_id" id="roll-kit-id" value="">
                            <button type="submit" id="roll-submit-btn" class="w-full px-4 py-2 text-sm font-semibold bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 border border-blue-300 rounded-lg transition-all shadow-2xs">
                                🚀 Build This!
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($stats['total_kits'] > 0): ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <?php
                $id = 'brandChart';
                $title = 'Brand Distribution';
                include '../components/charts/chart_canvas.php';

                $id = 'statusChart';
                $title = 'Status Distribution';
                include '../components/charts/chart_canvas.php';

                $id = 'spendingChart';
                $title = 'Spending Over Time';
                include '../components/charts/chart_canvas.php';

                $id = 'purchasesChart';
                $title = 'Kits Bought Over Time';
                $extra_controls = '
                    <div class="flex bg-gray-100 rounded-lg p-1 w-max">
                        <button id="btnMonthly" onclick="switchPurchasesView(\'monthly\')" 
                                class="px-3 py-1 text-xs font-medium rounded bg-blue-500 text-white">Monthly</button>
                        <button id="btnYearly" onclick="switchPurchasesView(\'yearly\')" 
                                class="px-3 py-1 text-xs font-medium rounded text-gray-600 hover:bg-gray-200">Yearly</button>
                    </div>';
                include '../components/charts/chart_canvas.php';
                ?>
            </div>

            <?php include '../components/charts/init_charts.php'; ?>
            <script>
            (function() {
                const brandData = <?= json_encode($stats['brand_counts'], JSON_HEX_TAG) ?>;
                const statusData = <?= json_encode($stats['status_counts'], JSON_HEX_TAG) ?>;
                
                initDoughnutChart('brandChart', Object.keys(brandData), Object.values(brandData));
                initDoughnutChart('statusChart', Object.keys(statusData), Object.values(statusData));
                
                const monthlySpending = <?= json_encode($stats['monthly_spending'], JSON_HEX_TAG) ?>;
                const monthlyPurchases = <?= json_encode($stats['monthly_purchases'], JSON_HEX_TAG) ?>;
                const yearlyPurchases = <?= json_encode($stats['yearly_purchases'], JSON_HEX_TAG) ?>;
                const colors = ChartConfig.colors;

                new Chart(document.getElementById('spendingChart'), {
                    type: 'scatter',
                    data: { 
                        datasets: [{ 
                            label: 'Kit Price', 
                            data: monthlySpending.map(d => ({ x: new Date(d.x), y: d.y, name: d.name })),
                            backgroundColor: '#10B981',
                            pointRadius: 6
                        }] 
                    },
                    options: { 
                        responsive: true, maintainAspectRatio: false, 
                        plugins: { 
                            legend: { display: false },
                            tooltip: { callbacks: { label: (ctx) => ctx.raw.name + ': ' + ChartConfig.formatCurrency(ctx.raw.y) } }
                        }, 
                        scales: { 
                            x: { type: 'time', time: { unit: 'month', displayFormats: { month: 'MMM yyyy' } } },
                            y: { beginAtZero: true } 
                        } 
                    }
                });
                
                window.purchasesChart = new Chart(document.getElementById('purchasesChart'), {
                    type: 'bar',
                    data: { labels: Object.keys(monthlyPurchases).map(ym => { const [y,m] = ym.split('-'); return m + '/' + y; }), datasets: [{ label: 'Kits', data: Object.values(monthlyPurchases), backgroundColor: '#3B82F6' }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
                
                window.purchasesData = {
                    monthly: { labels: Object.keys(monthlyPurchases).map(ym => { const [y,m] = ym.split('-'); return m + '/' + y; }), data: Object.values(monthlyPurchases) },
                    yearly: { labels: Object.keys(yearlyPurchases), data: Object.values(yearlyPurchases) }
                };
            })();

            window.switchPurchasesView = function(mode) {
                const chart = window.purchasesChart;
                const data = window.purchasesData[mode];
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.data;
                chart.update();
                
                const btnMonthly = document.getElementById('btnMonthly');
                const btnYearly = document.getElementById('btnYearly');
                if (mode === 'monthly') {
                    btnMonthly.className = 'px-3 py-1 text-xs font-medium rounded bg-blue-500 text-white';
                    btnYearly.className = 'px-3 py-1 text-xs font-medium rounded text-gray-600 hover:bg-gray-200';
                } else {
                    btnYearly.className = 'px-3 py-1 text-xs font-medium rounded bg-blue-500 text-white';
                    btnMonthly.className = 'px-3 py-1 text-xs font-medium rounded text-gray-600 hover:bg-gray-200';
                }
            }
            </script>
            <?php endif; ?>

            <div class="flex items-center justify-between mb-2">
                <h3 class="text-xl font-bold text-gray-700">📦 Current Inventory</h3>
            </div>
            <div class="bg-blue-50 p-4 rounded-lg mb-6 border border-blue-100">
                <form method="GET">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-gray-500 uppercase">Filters</span>
                        <button type="button" class="filter-toggle-btn" onclick="toggleFilterBar(this)">
                            ▼ Filters
                        </button>
                    </div>
                    <div class="filter-bar-body <?= $has_filters ? 'is-open' : '' ?>">
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Search</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
                                   placeholder="Kit name or ID..." 
                                   class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Filter Brand</label>
                            <select name="filter_brand" class="w-full mt-1 p-2 border border-gray-300 rounded">
                                <option value="">All Brands</option>
                                <?php foreach($brands as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" <?= (isset($_GET['filter_brand']) && $_GET['filter_brand'] == $brand['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase">Filter Status</label>
                            <select name="filter_status" class="w-full mt-1 p-2 border border-gray-300 rounded">
                                <option value="">All Statuses</option>
                                <?php foreach($statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == $status['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['label']) ?>
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
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 0, 'number')">ID</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 1, 'text')">Kit Name</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 2, 'text')">Brand</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 3, 'text')">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 4, 'date')">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider sortable-th" onclick="sortTable(this, 5, 'number')">Price</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($kits) > 0): ?>
                            <?php foreach ($kits as $row): ?>
                                <?php 
                                    $price_display = $row['pricebought'] ? format_currency($row['pricebought']) : "-";
                                    $safe_name = htmlspecialchars($row['name'], ENT_QUOTES);
                                    $safe_notes = htmlspecialchars($row['notes'] ?? '-', ENT_QUOTES);
                                    $safe_dates = htmlspecialchars($row['datebought'] ??'-', ENT_QUOTES);
                                    
                                    $brand_class = get_brand_color_palette($row['brandid']);
                                    $status_class = get_brand_color_palette($row['statusid']);
                                ?>
                                <tr class='hover:bg-gray-50 border-b border-gray-100'>
                                    <td data-label="ID" class='px-4 py-3 text-sm font-bold text-gray-500 whitespace-nowrap'><?= e($row['id']) ?></td>
                                    <td data-label="Kit Name" class='px-4 py-3 text-sm font-semibold text-gray-800'>
                                        <a href="/kit/<?= $row['actualid'] ?>" class="text-blue-600 hover:underline"><?= $safe_name ?></a>
                                    </td>
                                    <td data-label="Brand" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $brand_class ?>">
                                            <?= e($row['brand']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Status" class='px-4 py-3 text-sm whitespace-nowrap'>
                                        <span class="px-2 py-1 text-xs font-bold rounded-full <?= $status_class ?>">
                                            <?= e($row['status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Date" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= $safe_dates ?></td>
                                    <td data-label="Price" class='px-4 py-3 text-sm text-gray-600 whitespace-nowrap'><?= $price_display ?></td>
                                    <td data-label="Notes" class='px-4 py-3 text-sm text-gray-600'><?= $safe_notes ?></td>
                                    <td data-label="Actions" class='px-4 py-3 text-sm'>
                                        <div class='flex items-center space-x-2'>
                                            <button type='button' class='p-1 hover:bg-gray-200 rounded text-lg' title='Edit'
                                                data-id='<?= $row['actualid'] ?>'
                                                data-name='<?= $safe_name ?>'
                                                data-brand='<?= $row['brandid'] ?>'
                                                data-status='<?= $row['statusid'] ?>'
                                                data-date='<?= $row['datebought'] ?>'
                                                data-price='<?= $row['pricebought'] ?>'
                                                data-notes='<?= e($row['notes']) ?>'
                                                onclick='openEditModal(this)'>
                                                ✏️
                                            </button>
                                            
                                            <form method='POST' class='inline' onsubmit='return confirm("Archive <?= $safe_name ?>?");'>
                                                <input type='hidden' name='archiveid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-blue-100 rounded text-lg' title='Archive'>📦</button>
                                            </form>

                                            <form method='POST' class='inline' onsubmit='return confirm("Delete this kit?");'>
                                                <input type='hidden' name='deleteid' value='<?= $row['actualid'] ?>'>
                                                <button type='submit' class='p-1 hover:bg-red-100 rounded text-lg' title='Delete'>🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan='8' class='text-center py-6 text-gray-500'>No kits found in the hangar yet! Start buying!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <button onclick="openAddModal()" 
            class="fixed bottom-6 right-6 w-14 h-14 bg-blue-500 hover:bg-blue-600 text-white rounded-full shadow-lg flex items-center justify-center text-3xl transition-all duration-200 hover:scale-110 z-40"
            title="Add New Kit">
        +
    </button>

    <?php $mode = 'add'; include '../components/inventory_modal.php'; ?>
    <?php $mode = 'edit'; include '../components/inventory_modal.php'; ?>

    <script>
        function openEditModal(button) {
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const brand = button.getAttribute('data-brand');
            const status = button.getAttribute('data-status');
            const date = button.getAttribute('data-date');
            const price = button.getAttribute('data-price');
            const notes = button.getAttribute('data-notes');

            document.getElementById('modal_id').value = id;
            document.getElementById('modal_name').value = name;
            document.getElementById('modal_brand').value = brand;   
            document.getElementById('modal_status').value = status; 
            document.getElementById('modal_date').value = date;
            document.getElementById('modal_price').value = price;
            document.getElementById('modal_notes').value = notes;
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').style.display = 'flex'; 
        }

        const notStartedKits = <?= json_encode(array_values(array_map(fn($k) => [
            'actualid' => $k['actualid'],
            'name' => $k['name'],
            'brand' => $k['brand'],
            'brand_badge' => get_brand_color_palette($k['brandid']),
            'status' => $k['status'],
            'status_badge' => get_brand_color_palette($k['statusid']),
            'pricebought' => $k['pricebought']
        ], $not_started_kits)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        const straightBuildKits = <?= json_encode(array_values(array_map(fn($k) => [
            'actualid' => $k['actualid'],
            'name' => $k['name'],
            'brand' => $k['brand'],
            'brand_badge' => get_brand_color_palette($k['brandid']),
            'status' => $k['status'],
            'status_badge' => get_brand_color_palette($k['statusid']),
            'pricebought' => $k['pricebought']
        ], $straight_build_kits)), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

        let currentIncludeStraight = false;

        function getActiveQueuePool() {
            return currentIncludeStraight ? [...notStartedKits, ...straightBuildKits] : notStartedKits;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function updateQueueDisplay() {
            const pool = getActiveQueuePool();
            const countEl = document.getElementById('queue-count-text');
            const container = document.getElementById('queue-kit-container');
            const form = document.getElementById('queue-start-form');
            const formKitId = document.getElementById('queue-form-kit-id');
            const startBtn = document.getElementById('queue-start-btn');
            const randomBtn = document.getElementById('queue-random-btn');

            if (countEl) {
                if (currentIncludeStraight) {
                    countEl.textContent = `${pool.length} kits in queue (${notStartedKits.length} unbuilt, ${straightBuildKits.length} straight builds)`;
                } else {
                    countEl.textContent = `${notStartedKits.length} unbuilt waiting`;
                }
            }

            if (randomBtn) {
                randomBtn.style.display = pool.length > 1 ? 'inline-flex' : 'none';
            }

            if (pool.length === 0) {
                if (container) {
                    container.innerHTML = `
                        <div class="py-6 text-center">
                            <div class="text-3xl mb-2">🎉</div>
                            <p class="text-sm font-semibold text-gray-700">Zero Kits in Queue!</p>
                            <p class="text-xs text-gray-500 mt-1">${currentIncludeStraight ? 'Every kit in your hangar has been completed or is already in progress.' : 'Every unbuilt kit has been started! Toggle "Include Straight Builds" above to customize built kits.'}</p>
                        </div>`;
                }
                if (form) form.style.display = 'none';
            } else {
                const item = pool[0];
                if (container) {
                    const priceText = item.pricebought ? (typeof ChartConfig !== 'undefined' ? ChartConfig.formatCurrency(item.pricebought) : '$' + item.pricebought) : '';
                    container.innerHTML = `
                        <div class="mb-4">
                            <h4 class="text-lg font-bold text-gray-900 leading-snug">
                                <a id="queue-kit-link" href="/kit/${item.actualid}" class="hover:text-blue-600 transition-colors">
                                    ${escapeHtml(item.name)}
                                </a>
                            </h4>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">
                                    ${escapeHtml(item.brand)}
                                </span>
                                <span class="px-2 py-0.5 text-xs font-bold rounded-full ${item.status_badge}">
                                    ${escapeHtml(item.status)}
                                </span>
                                ${priceText ? `<span class="text-xs text-gray-500 font-mono">${priceText}</span>` : ''}
                            </div>
                        </div>`;
                }
                if (form && formKitId) {
                    form.style.display = 'block';
                    formKitId.value = item.actualid;
                    if (startBtn) {
                        startBtn.innerHTML = (item.status && item.status.toLowerCase().includes('straight')) ? '🎨 Customize This Build' : '🚀 Start This Build';
                    }
                }
            }
        }

        function toggleIncludeStraight(include) {
            currentIncludeStraight = !!include;
            try {
                localStorage.setItem('gunpla_include_straight_builds', currentIncludeStraight ? 'true' : 'false');
            } catch(e) {}
            updateQueueDisplay();
        }

        function rollRandomKit() {
            const pool = getActiveQueuePool();
            if (!pool || pool.length === 0) return;
            const modal = document.getElementById('random-roll-modal');
            const nameEl = document.getElementById('roll-kit-name');
            const badgesEl = document.getElementById('roll-kit-badges');
            const formEl = document.getElementById('roll-start-form');
            const idInput = document.getElementById('roll-kit-id');
            const submitBtn = document.getElementById('roll-submit-btn');
            const titleEl = document.getElementById('roll-modal-title');
            const subEl = document.getElementById('roll-modal-subtitle');
            const diceEl = document.getElementById('roll-dice-icon');

            modal.style.display = 'flex';
            formEl.style.display = 'none';
            nameEl.classList.add('animate-pulse');
            titleEl.textContent = 'Choosing Next Build...';
            subEl.textContent = currentIncludeStraight ? 'Cycling through unstarted & straight build kits' : 'Cycling through unstarted hangar kits';
            diceEl.textContent = '🎲';

            let count = 0;
            const maxSteps = 16;
            const interval = setInterval(() => {
                const randIdx = Math.floor(Math.random() * pool.length);
                const item = pool[randIdx];
                nameEl.textContent = item.name;
                badgesEl.innerHTML = `<span class="px-2 py-0.5 text-xs font-semibold rounded-full ${item.brand_badge}">${escapeHtml(item.brand)}</span> <span class="px-2 py-0.5 text-xs font-bold rounded-full ${item.status_badge}">${escapeHtml(item.status)}</span>`;
                count++;

                if (count >= maxSteps) {
                    clearInterval(interval);
                    nameEl.classList.remove('animate-pulse');
                    titleEl.textContent = '🎉 Destiny Has Spoken!';
                    subEl.textContent = 'Here is your next build challenge:';
                    diceEl.textContent = '✨';
                    idInput.value = item.actualid;
                    if (submitBtn) {
                        submitBtn.textContent = (item.status && item.status.toLowerCase().includes('straight')) ? '🎨 Customize This Build!' : '🚀 Build This!';
                    }
                    formEl.style.display = 'block';
                }
            }, 80);
        }

        function closeRollModal() {
            document.getElementById('random-roll-modal').style.display = 'none';
        }

        // Initialize saved preference
        try {
            const savedPref = localStorage.getItem('gunpla_include_straight_builds') === 'true';
            const chk = document.getElementById('toggle-include-straight');
            if (chk && savedPref) {
                chk.checked = true;
                currentIncludeStraight = true;
                updateQueueDisplay();
            }
        } catch(e) {}
    </script>

    <script>initScrollRestore('inventory_scroll');</script>

<?php include '../components/layout_footer.php'; ?>
