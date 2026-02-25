<?php
/**
 * Reusable Log Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $backlog_items to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Log Entry' : '➕ Log Build Progress';
$submit_label = $is_edit ? 'Save Changes' : 'Log Progress';
$close_fn = $is_edit ? 'closeEditModal()' : 'closeAddModal()';
?>
<div id="<?= $modal_id ?>" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-md p-6 modal-animate relative">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="<?= $close_fn ?>">&times;</span>
        <h2 class="text-xl font-bold text-gray-700 mb-4"><?= $title ?></h2>
        
        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="<?= $mode ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="edit_id" id="modal_id">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Kit (from Backlog):</label>
                <select name="backlogid" <?= $is_edit ? 'id="modal_backlogid"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">-- Select Kit --</option>
                    <?php foreach($backlog_items as $bl): ?>
                        <option value="<?= $bl['actualid'] ?>"><?= htmlspecialchars($bl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Log Entry Title:</label>
                <input type="text" name="logname" <?= $is_edit ? 'id="modal_logname"' : '' ?> required 
                       placeholder="e.g. Panel lining completed" class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                <textarea name="notes" <?= $is_edit ? 'id="modal_notes"' : '' ?> rows="3" 
                          placeholder="Details about this step..." class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Image:</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
                       class="w-full mt-1 p-2 border border-gray-300 rounded file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <?php if ($is_edit): ?>
                    <div id="modal_image_preview" class="mt-2 hidden">
                        <img id="modal_image_thumb" src="" class="w-20 h-20 object-cover rounded border" alt="Current">
                        <span class="text-xs text-gray-400 ml-2">Current image (upload new to replace)</span>
                    </div>
                    <input type="hidden" name="existing_imagepath" id="modal_imagepath">
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                <?= $submit_label ?>
            </button>
        </form>
    </div>
</div>
