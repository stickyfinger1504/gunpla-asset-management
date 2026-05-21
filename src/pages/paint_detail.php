<?php
require_once '../includes/bootstrap.php';

$current_section = 'paints';
$current_page = 'paint_inventory';
$paint_id = (int)($_GET['id'] ?? 0);

$data = get_paint_detail($conn, $paint_id);

if (!$data) {
    http_response_code(404);
    $page_title = 'Paint Not Found';
    include '../components/layout_header.php';
    echo "<div class='max-w-7xl mx-auto text-center py-20'>";
    echo "<h1 class='text-4xl mb-4'>🎨 Paint not found</h1>";
    echo "<a href='/paint_inventory' class='text-blue-500 hover:underline'>← Back to Paint Inventory</a>";
    echo "</div>";
    include '../components/layout_footer.php';
    exit;
}

$paint = $data['paint'];
$wishlist = $data['wishlist'];
$recipes = $data['recipes'];
$page_title = $paint['name'];

$amount_label = $paint['amount'] ?? '-';
$amount_lower = strtolower($amount_label);
if (str_contains($amount_lower, 'full')) {
    $amount_class = 'bg-green-100 text-green-800';
} elseif (str_contains($amount_lower, 'high') || str_contains($amount_lower, '75') || str_contains($amount_lower, 'most')) {
    $amount_class = 'bg-blue-100 text-blue-800';
} elseif (str_contains($amount_lower, 'half') || str_contains($amount_lower, 'mid') || str_contains($amount_lower, '50')) {
    $amount_class = 'bg-yellow-100 text-yellow-800';
} elseif (str_contains($amount_lower, 'low') || str_contains($amount_lower, '25')) {
    $amount_class = 'bg-orange-100 text-orange-800';
} elseif (str_contains($amount_lower, 'empty') || str_contains($amount_lower, '0')) {
    $amount_class = 'bg-red-100 text-red-800';
} else {
    $amount_class = 'bg-gray-100 text-gray-800';
}
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-7xl mx-auto w-full">

    <div class="flex items-center justify-between mb-6">
        <a href="/paint_inventory" class="text-blue-500 hover:underline text-sm">← Back to Paint Inventory</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex flex-col md:flex-row gap-6">

            <?php if (!empty($paint['imagepath'])): ?>
            <div class="flex-shrink-0">
                <a href="<?= e($paint['imagepath']) ?>" target="_blank">
                    <img src="<?= e($paint['imagepath']) ?>" alt="<?= e($paint['name']) ?>"
                         class="w-48 h-48 object-cover rounded-lg border shadow-sm hover:opacity-90 transition"
                         loading="lazy">
                </a>
            </div>
            <?php endif; ?>

            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-800 mb-2"><?= e($paint['name']) ?></h1>
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800">
                        <?= e($paint['brand']) ?>
                    </span>
                    <?php if (!empty($paint['painttype'])): ?>
                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-purple-100 text-purple-800">
                        <?= e($paint['painttype']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="px-2 py-1 text-xs font-bold rounded-full <?= $amount_class ?>">
                        <?= e($amount_label) ?>
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-600">
                    <div>
                        <span class="font-semibold text-gray-500">ID</span>
                        <p><?= e($paint['id']) ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500">Thinned</span>
                        <p><?= e($paint['thinned'] ?? '-') ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500">Added</span>
                        <p><?= !empty($paint['createddate']) ? date('d M Y', strtotime($paint['createddate'])) : '-' ?></p>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-500">Last Updated</span>
                        <p><?= !empty($paint['lastupdate']) ? date('d M Y', strtotime($paint['lastupdate'])) : '-' ?></p>
                    </div>
                </div>

                <?php if (!empty($paint['notes'])): ?>
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <span class="text-xs font-semibold text-gray-500 uppercase">Notes</span>
                    <p class="text-sm text-gray-700 mt-1"><?= e($paint['notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white border border-blue-200 rounded-lg p-4 shadow-sm text-center">
            <p class="text-3xl font-bold text-blue-600"><?= count($recipes) ?></p>
            <p class="text-sm text-gray-500 mt-1">Used in Recipes</p>
        </div>
        <div class="bg-white border border-<?= $wishlist ? 'green' : 'gray' ?>-200 rounded-lg p-4 shadow-sm text-center">
            <p class="text-3xl font-bold text-<?= $wishlist ? 'green' : 'gray' ?>-600">
                <?= $wishlist ? '✅' : '—' ?>
            </p>
            <p class="text-sm text-gray-500 mt-1"><?= $wishlist ? 'From Wishlist' : 'Not from Wishlist' ?></p>
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
            <?php if (!empty($wishlist['notes'])): ?>
                • <?= e($wishlist['notes']) ?>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>

    <div class="mb-6">
        <h3 class="text-xl font-bold text-gray-700 mb-3">🧪 Used in Recipes</h3>

        <?php if (!empty($recipes)): ?>
        <div class="space-y-3">
            <?php foreach ($recipes as $recipe): ?>
            <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <?php if (!empty($recipe['imagepath'])): ?>
                    <img src="<?= e($recipe['imagepath']) ?>" alt="Swatch"
                         class="w-10 h-10 object-cover rounded border" loading="lazy">
                    <?php endif; ?>
                    <div>
                        <p class="text-sm font-semibold text-gray-800"><?= e($recipe['name']) ?></p>
                        <?php if (!empty($recipe['thinner_ratio'])): ?>
                        <p class="text-xs text-gray-400">💧 Thinner: <?= e($recipe['thinner_ratio']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="px-2 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-800">
                    <?= $recipe['percentage'] ?>%
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center text-sm text-gray-500">
            Not used in any recipes yet.
            <a href="/mixing_recipes" class="text-blue-500 hover:underline">Create one →</a>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../components/layout_footer.php'; ?>
