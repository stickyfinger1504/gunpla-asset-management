<?php
/**
 * Reusable Tool Wishlist Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $tool_brands, $tool_categories, $priorities to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Wishlist Tool' : '➕ Add Tool to Wishlist';
$submit_label = $is_edit ? 'Save Changes' : 'Add to Wishlist';
$close_fn = $is_edit ? 'closeEditModal()' : 'closeAddModal()';
?>
<div id="<?= $modal_id ?>" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50" style="display:none;" onclick="if (event.target === this) <?= $close_fn ?>">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-lg p-6 modal-animate relative overflow-y-auto max-h-[90vh]">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600 transition" onclick="<?= $close_fn ?>">&times;</span>
        <h2 class="text-xl font-bold text-gray-800 mb-4"><?= $title ?></h2>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="<?= $mode ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token ?? '') ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="edit_id" id="modal_id">
                <input type="hidden" name="existing_imagepath" id="modal_imagepath">
            <?php endif; ?>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Tool Name *</label>
                <input type="text" name="name" <?= $is_edit ? 'id="modal_name"' : '' ?> required <?= $is_edit ? '' : 'placeholder="e.g. GodHand SPN-120 Ultimate Nipper"' ?>
                       class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Brand</label>
                    <select name="brandid" <?= $is_edit ? 'id="modal_brand"' : '' ?> class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">-- Select Brand --</option>
                        <?php foreach ($tool_brands as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Category</label>
                    <select name="categoryid" <?= $is_edit ? 'id="modal_category"' : '' ?> class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">-- Select Category --</option>
                        <?php foreach ($tool_categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Priority</label>
                    <select name="priorityid" <?= $is_edit ? 'id="modal_priority"' : '' ?> class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">-- Select Priority --</option>
                        <?php foreach ($priorities as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($is_edit): ?>
                <div>
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Status</label>
                    <select name="obtained" id="modal_obtained" class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="0">Wanted (Unobtained)</option>
                        <option value="1">Obtained</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Purchase / Store Link</label>
                <input type="url" name="link" <?= $is_edit ? 'id="modal_link"' : 'placeholder="https://..."' ?>
                       class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Tool Image</label>
                <?php if ($is_edit): ?>
                <div id="modal_image_preview" class="hidden mb-2 flex items-center gap-3 p-2 bg-gray-50 border border-gray-200 rounded">
                    <img id="modal_image_thumb" src="" alt="Tool" class="w-12 h-12 object-cover rounded border">
                    <span class="text-xs text-gray-500">Current photo (upload new to replace)</span>
                </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*"
                       class="w-full p-1.5 border border-gray-300 rounded text-sm bg-gray-50">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-1">Notes</label>
                <textarea name="notes" <?= $is_edit ? 'id="modal_notes"' : '' ?> rows="3"
                          placeholder="e.g. Single-edged nipper for nub removal, wait for sale..."
                          class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm"></textarea>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-sm transition">
                    <?= $submit_label ?>
                </button>
            </div>
        </form>
    </div>
</div>
