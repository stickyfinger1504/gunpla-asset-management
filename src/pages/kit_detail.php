<?php
require_once '../includes/bootstrap.php';

$current_section = 'kits';
$current_page = 'inventory';
$kit_id = (int)($_GET['id'] ?? 0);

$data = get_kit_detail($conn, $kit_id);

if (!$data) {
    http_response_code(404);
    $page_title = 'Kit Not Found';
    include '../components/layout_header.php';
    echo "<div class='max-w-5xl mx-auto text-center py-20'>";
    echo "<h1 class='text-4xl mb-4'>Kit not found</h1>";
    echo "<a href='/inventory' class='text-blue-500 hover:underline'>← Back to Inventory</a>";
    echo "</div>";
    include '../components/layout_footer.php';
    exit;
}

$kit = $data['kit'];
$wishlist = $data['wishlist'];
$backlogs = $data['backlogs'];
$logs = $data['logs'];

$page_title = $kit['name'];
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-5xl mx-auto w-full">

    <div class="flex items-center justify-between mb-6">
        <a href="/inventory" class="text-blue-500 hover:underline text-sm">← Back to Inventory</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2"><?= e($kit['name']) ?></h1>
        <div class="flex flex-wrap gap-2 mb-3">
            <span class="px-2 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800">
                <?= e($kit['brand']) ?>
            </span>
            <span class="px-2 py-1 text-xs font-bold rounded-full bg-green-100 text-green-800">
                <?= e($kit['status']) ?>
            </span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
            <div>
                <span class="font-semibold text-gray-500">ID</span>
                <p><?= e($kit['id']) ?></p>
            </div>
            <div>
                <span class="font-semibold text-gray-500">Date Bought</span>
                <p><?= $kit['datebought'] ? date('d M Y', strtotime($kit['datebought'])) : '-' ?></p>
            </div>
            <div>
                <span class="font-semibold text-gray-500">Price</span>
                <p><?= format_currency($kit['pricebought']) ?></p>
            </div>
            <div>
                <span class="font-semibold text-gray-500">Notes</span>
                <p><?= e($kit['notes']) ?: '-' ?></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm text-center">
            <p class="text-3xl font-bold text-blue-600"><?= count($logs) ?></p>
            <p class="text-sm text-gray-500 mt-1">Build Logs</p>
        </div>
        <div class="bg-white border border-green-200 rounded-lg p-4 shadow-sm text-center">
            <p class="text-3xl font-bold text-green-600"><?= count($backlogs) ?></p>
            <p class="text-sm text-gray-500 mt-1">Backlog Plans</p>
        </div>
        <div class="bg-white border border-purple-200 rounded-lg p-4 shadow-sm text-center">
            <?php
            $week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));
            $recent = count(array_filter($logs, fn($l) => ($l['createdat'] ?? '') >= $week_ago));
            ?>
            <p class="text-3xl font-bold text-purple-600"><?= $recent ?></p>
            <p class="text-sm text-gray-500 mt-1">Logs This Week</p>
        </div>
    </div>

    <?php if ($wishlist): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
        <h3 class="text-sm font-bold text-yellow-800 mb-1">✨ From Wishlist</h3>
        <p class="text-sm text-yellow-700">
            Priority: <?= e($wishlist['priority'] ?? '-') ?>
            <?php if (!empty($wishlist['link'])): ?>
                • <a href="<?= e($wishlist['link']) ?>" target="_blank" class="underline">Original Link</a>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <?php if (!empty($backlogs)): ?>
    <div class="mb-6">
        <h3 class="text-xl font-bold text-gray-700 mb-3">🚧 Backlog Plans</h3>
        <div class="space-y-3">
            <?php foreach ($backlogs as $bl): ?>
            <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                <div class="flex items-center gap-2 mb-2">
                    <?php if (!empty($bl['buildplan_label'])): ?>
                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-purple-100 text-purple-800">
                        <?= e($bl['buildplan_label']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($bl['status_label'])): ?>
                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800">
                        <?= e($bl['status_label']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($bl['notes'])): ?>
                <p class="text-sm text-gray-600"><?= e($bl['notes']) ?></p>
                <?php endif; ?>
                <?php if (!empty($bl['references'])): ?>
                <a href="<?= e($bl['references']) ?>" target="_blank" class="text-sm text-blue-500 hover:underline mt-1 inline-block">🔗 Reference</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $kit_tasks = get_tasks_for_kit($conn, $kit_id);
    $task_stats = calculate_task_stats($kit_tasks);
    ?>
    <?php if (!empty($backlogs)): ?>
    <div class="mb-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xl font-bold text-gray-700">
                📋 Tasks
                <span class="text-sm font-normal text-gray-400">
                    (<?= $task_stats['done'] ?>/<?= $task_stats['total'] ?> done)
                </span>
            </h3>
            <a href="/tasks?filter_kit=<?= $kit_id ?>" class="text-sm text-blue-500 hover:underline">
                View all →
            </a>
        </div>

        <?php if (!empty($kit_tasks)): ?>
        <div class="bg-white rounded-lg shadow divide-y divide-gray-100">
            <?php foreach ($kit_tasks as $task): ?>
            <div class="flex items-center px-4 py-3 gap-3 <?= $task['is_done'] ? 'opacity-50' : '' ?>">
                <span class="text-lg"><?= $task['is_done'] ? '☑️' : '⬜' ?></span>
                <span class="flex-1 text-sm <?= $task['is_done'] ? 'line-through text-gray-400' : 'text-gray-800' ?>">
                    <?= e($task['description']) ?>
                </span>
                <?php if (!empty($task['imagepath'])): ?>
                <a href="<?= e($task['imagepath']) ?>" target="_blank" class="flex-shrink-0">
                    <img src="<?= e($task['imagepath']) ?>" alt="Reference"
                         class="w-10 h-10 object-cover rounded border hover:opacity-80 transition"
                         loading="lazy">
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center text-sm text-gray-500">
            No tasks planned for this kit yet.
            <a href="/tasks" class="text-blue-500 hover:underline">Add some →</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $timeline = build_kit_timeline($kit_tasks, $logs);
    ?>
    <?php if (!empty($timeline)): ?>
    <div class="mb-6">
        <h3 class="text-xl font-bold text-gray-700 mb-3">⏳ Timeline</h3>

        <div class="relative">

            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200"></div>

            <div class="space-y-0">
                <?php
                $last_date = '';
                foreach ($timeline as $event):
                    $event_date = date('d M Y', strtotime($event['date']));
                    $show_date = ($event_date !== $last_date);
                    $last_date = $event_date;
                ?>

                    <?php if ($show_date): ?>

                    <div class="relative flex items-center pl-10 py-3">

                        <div class="absolute left-[11px] w-3 h-3 rounded-full bg-gray-300 border-2 border-white"></div>
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wide"><?= $event_date ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($event['type'] === 'build_log'): ?>

                    <div class="relative flex items-start pl-10 py-2">
                        <div class="absolute left-[11px] w-3 h-3 rounded-full bg-blue-400 border-2 border-white"></div>
                        <div class="bg-white border border-gray-200 rounded-lg p-3 shadow-sm flex-1">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-gray-800">
                                        <span class="mr-1"><?= $event['icon'] ?></span>
                                        <?= e($event['label']) ?>
                                    </p>
                                    <?php if (!empty($event['notes'])): ?>
                                    <p class="text-xs text-gray-500 mt-1">"<?= e($event['notes']) ?>"</p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?= date('H:i', strtotime($event['date'])) ?>
                                    </p>
                                </div>
                                <?php if (!empty($event['imagepath'])): ?>
                                <a href="<?= e($event['imagepath']) ?>" target="_blank" class="ml-3 flex-shrink-0">
                                    <img src="<?= e($event['imagepath']) ?>" alt="Build photo"
                                         class="w-14 h-14 object-cover rounded border hover:opacity-80 transition"
                                         loading="lazy">
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php elseif ($event['type'] === 'task_done'): ?>

                    <div class="relative flex items-center pl-10 py-1.5">
                        <div class="absolute left-[11px] w-3 h-3 rounded-full bg-green-400 border-2 border-white"></div>
                        <p class="text-sm text-green-700">
                            <span class="mr-1"><?= $event['icon'] ?></span>
                            <span class="font-medium">Task completed:</span>
                            <?= e($event['label']) ?>
                            <span class="text-xs text-gray-400 ml-2"><?= date('H:i', strtotime($event['date'])) ?></span>
                        </p>
                    </div>

                    <?php elseif ($event['type'] === 'task_created'): ?>

                    <div class="relative flex items-center pl-10 py-1.5">
                        <div class="absolute left-[11px] w-3 h-3 rounded-full bg-gray-300 border-2 border-white"></div>
                        <p class="text-sm text-gray-400">
                            <span class="mr-1"><?= $event['icon'] ?></span>
                            Task created:
                            <?= e($event['label']) ?>
                            <?php if (!empty($event['imagepath'])): ?>
                            <a href="<?= e($event['imagepath']) ?>" target="_blank" class="ml-1">
                                <img src="<?= e($event['imagepath']) ?>" alt="Ref"
                                     class="w-6 h-6 object-cover rounded inline-block align-middle border"
                                     loading="lazy">
                            </a>
                            <?php endif; ?>
                            <span class="text-xs text-gray-300 ml-2"><?= date('H:i', strtotime($event['date'])) ?></span>
                        </p>
                    </div>
                    <?php endif; ?>

                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include '../components/layout_footer.php'; ?>
