<?php
/**
 * Reusable Tool Inventory Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $tool_brands, $tool_cats, $tool_statuses to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Tool' : '➕ Add Tool';
$submit_label = $is_edit ? 'Save Changes' : 'Add to Toolbox';
$close_fn = $is_edit ? 'closeEditModal()' : 'closeAddModal()';
?>
<div id="<?= $modal_id ?>" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-lg p-6 modal-animate relative overflow-y-auto max-h-[90vh]">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="<?= $close_fn ?>">&times;</span>
        <h2 class="text-xl font-bold text-gray-700 mb-4"><?= $title ?></h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="<?= $mode ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="edit_id" id="edit_id">
                <input type="hidden" name="existing_imagepath" id="modal_imagepath">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Tool Name *</label>
                <input type="text" name="name" <?= $is_edit ? 'id="modal_name"' : '' ?> required <?= $is_edit ? '' : 'placeholder="e.g. Tamiya Sharp Nipper"' ?>
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Brand</label>
                    <select name="brand" <?= $is_edit ? 'id="modal_brand"' : '' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select Brand --</option>
                        <?php foreach ($tool_brands as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Category</label>
                    <select name="category" <?= $is_edit ? 'id="modal_category"' : '' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($tool_cats as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Status / Condition</label>
                <select name="status" <?= $is_edit ? 'id="modal_status"' : '' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">-- Optional --</option>
                    <?php foreach ($tool_statuses as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Quantity &amp; Unit</label>
                <div class="flex gap-2 mt-1">
                    <input type="number" name="quantity" <?= $is_edit ? 'id="modal_quantity"' : 'value="1"' ?> min="1" placeholder="1"
                           class="w-1/3 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                    <input type="text" name="unit" <?= $is_edit ? 'id="modal_unit"' : '' ?> placeholder="pcs / sheets"
                           class="flex-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Price Bought (Rp)</label>
                    <input type="number" name="pricebought" <?= $is_edit ? 'id="modal_pricebought"' : '' ?> min="0" <?= $is_edit ? '' : 'placeholder="e.g. 150000"' ?>
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Date Bought</label>
                    <input type="date" name="datebought" <?= $is_edit ? 'id="modal_datebought"' : '' ?>
                           class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Reorder Link</label>
                <input type="url" name="link" <?= $is_edit ? 'id="modal_link"' : '' ?> placeholder="https://..."
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Image</label>
                <?php if ($is_edit): ?>
                <div id="modal_image_preview" class="hidden mb-2">
                    <img id="modal_image_thumb" src="" alt="Current image" class="w-16 h-16 object-cover rounded border">
                    <span class="text-xs text-gray-500 ml-2">Current (upload new to replace)</span>
                </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*"
                       class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes</label>
                <textarea name="notes" <?= $is_edit ? 'id="modal_notes"' : '' ?> rows="2" <?= $is_edit ? '' : 'placeholder="Any extra notes..."' ?>
                          class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                <?= $submit_label ?>
            </button>
        </form>
    </div>
</div>
