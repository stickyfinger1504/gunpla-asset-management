<?php
/**
 * Reusable Mixing Recipe Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $paint_dropdown to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Recipe' : '➕ New Recipe';
$submit_label = $is_edit ? 'Save Changes' : 'Save Recipe';
$close_fn = $is_edit ? 'closeEditModal()' : 'closeAddModal()';
$ingredients_list_id = $is_edit ? 'edit-ingredients-list' : 'add-ingredients-list';
?>
<div id="<?= $modal_id ?>" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-lg p-6 modal-animate relative max-h-[90vh] overflow-y-auto">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="<?= $close_fn ?>">&times;</span>
        <h2 class="text-xl font-bold text-gray-700 mb-4"><?= $title ?></h2>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="<?= $mode ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="edit_id" id="edit_recipeid">
                <input type="hidden" name="existing_imagepath" id="edit_existing_imagepath">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Recipe Name:</label>
                <input type="text" name="name" <?= $is_edit ? 'id="edit_name"' : '' ?> required <?= $is_edit ? '' : 'placeholder="e.g. Char\'s Custom Red"' ?>
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Thinner Ratio:</label>
                <input type="text" name="thinner_ratio" <?= $is_edit ? 'id="edit_thinner_ratio"' : 'placeholder="e.g. 1.5:1 or 2:1"' ?>
                       class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-2">Ingredients:</label>
                <div id="<?= $ingredients_list_id ?>" class="space-y-2">
                    <?php if (!$is_edit): ?>
                    <div class="flex gap-2 items-center ingredient-row">
                        <select name="paintid[]" required class="flex-1 p-2 border border-gray-300 rounded text-sm">
                            <option value="">-- Select Paint --</option>
                            <?php foreach($paint_dropdown as $p): ?>
                                <option value="<?= $p['actualid'] ?>"><?= e($p['id'] . ' — ' . $p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="percentage[]" required placeholder="%" min="1" max="100"
                               class="w-20 p-2 border border-gray-300 rounded text-sm text-center">
                        <button type="button" onclick="removeIngredient(this)"
                                class="text-red-400 hover:text-red-600 text-lg px-1" title="Remove">🗑️</button>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addIngredientRow('<?= $ingredients_list_id ?>')"
                        class="mt-2 text-sm text-blue-500 hover:text-blue-700 font-medium">
                    + Add Ingredient
                </button>
            </div>

            <?php if ($is_edit): ?>
            <div id="edit_image_preview" class="hidden">
                <label class="block text-sm font-semibold text-gray-600">Current Image:</label>
                <img id="edit_image_thumb" src="" alt="Swatch" class="w-16 h-16 object-cover rounded border mt-1">
            </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-600"><?= $is_edit ? 'Replace Image (optional)' : 'Color Swatch (optional)' ?>:</label>
                <input type="file" name="image" accept="image/*" class="w-full mt-1 text-sm">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                <textarea name="notes" <?= $is_edit ? 'id="edit_notes"' : '' ?> rows="2" <?= $is_edit ? '' : 'placeholder="Spray pressure, coats, etc."' ?>
                          class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                <?= $submit_label ?>
            </button>
        </form>
    </div>
</div>
