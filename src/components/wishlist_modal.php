<?php
/**
 * Reusable Wishlist Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $brands, $priorities to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Wishlist Item' : '➕ Add to Wishlist';
$submit_label = $is_edit ? 'Save Changes' : 'Add to Wishlist';
$close_fn = $is_edit ? 'closeEditModal()' : 'closeAddModal()';
?>
<div id="<?= $modal_id ?>" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-md p-6 modal-animate relative">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="<?= $close_fn ?>">&times;</span>
        <h2 class="text-xl font-bold text-gray-700 mb-4"><?= $title ?></h2>
        
        <form method="post" class="space-y-4">
            <input type="hidden" name="action_type" value="<?= $mode ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="edit_id" id="modal_id">
            <?php endif; ?>
            
            <div>
                <label class="block text-sm font-semibold text-gray-600">Kit Name:</label>
                <input type="text" name="kit_name" <?= $is_edit ? 'id="modal_name"' : '' ?> required <?= $is_edit ? '' : 'placeholder="e.g. PG Unleashed RX-78-2"' ?>
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Brand:</label>
                    <select name="brandid" <?= $is_edit ? 'id="modal_brand"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select --</option>
                        <?php foreach($brands as $brand): ?>
                            <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Priority:</label>
                    <select name="priorityid" <?= $is_edit ? 'id="modal_priority"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <?php foreach($priorities as $priority): ?>
                            <option value="<?= $priority['id'] ?>"><?= htmlspecialchars($priority['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($is_edit): ?>
            <div>
                <label class="block text-sm font-semibold text-gray-600">Obtained:</label>
                <select name="obtained" id="modal_obtained" class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="0">Not Obtained</option>
                    <option value="1">Obtained</option>
                </select>
            </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Link<?= $is_edit ? '' : ' (optional)' ?>:</label>
                <input type="url" name="link" <?= $is_edit ? 'id="modal_link"' : 'placeholder="https://..."' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                <textarea name="notes" <?= $is_edit ? 'id="modal_notes"' : '' ?> rows="3" <?= $is_edit ? '' : 'placeholder="Details..."' ?> class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                <?= $submit_label ?>
            </button>
        </form>
    </div>
</div>
