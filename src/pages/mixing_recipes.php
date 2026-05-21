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
?>
<?php include '../components/layout_header.php'; ?>

<div class="max-w-7xl mx-auto w-full">
    <h1 class="page-title font-bold text-gray-700 text-center mb-8">🧪 Mixing Recipes</h1>

    <?php include '../components/toast.php'; ?>

    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
        <?php
        $value = count($recipes);
        $label = 'Total Recipes';
        $color = 'blue';
        include '../components/stats/stat_card.php';

        $total_paints_used = 0;
        foreach ($recipes as $r) { $total_paints_used += count($r['items']); }
        $value = $total_paints_used;
        $label = 'Paints Used';
        $color = 'green';
        include '../components/stats/stat_card.php';

        $unique_paints = [];
        foreach ($recipes as $r) {
            foreach ($r['items'] as $item) {
                $unique_paints[$item['paintid']] = true;
            }
        }
        $value = count($unique_paints);
        $label = 'Unique Paints';
        $color = 'purple';
        include '../components/stats/stat_card.php';
        ?>
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
                    <h3 class="text-lg font-bold text-gray-800"><?= e($recipe['name']) ?></h3>

         
                    <div class="mt-3 space-y-1">
                        <?php foreach ($recipe['items'] as $item): ?>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-mono text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded w-12 text-center">
                                <?= $item['percentage'] ?>%
                            </span>
                            <span class="text-gray-700"><?= e($item['paint_name'] ?? 'Unknown Paint') ?></span>
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
                <a href="<?= e($recipe['imagepath']) ?>" target="_blank" class="ml-4 flex-shrink-0">
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



<script>
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

    function openEditRecipeModal(button) {
        const recipe = JSON.parse(button.getAttribute('data-recipe'));

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


    window.onclick = function(event) {
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        if (event.target == addModal) closeAddModal();
        if (event.target == editModal) closeEditModal();
    }

</script>

<script>
    (function() {
        var pos = sessionStorage.getItem('mixing_recipes_scroll');
        if (pos) {
            window.scrollTo(0, parseInt(pos));
            sessionStorage.removeItem('mixing_recipes_scroll');
        }
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function() {
                sessionStorage.setItem('mixing_recipes_scroll', window.scrollY);
            });
        });
    })();
</script>

<?php include '../components/layout_footer.php'; ?>
