<?php
/**
 * Reusable Task Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $backlog_items to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Task' : '➕ Add Task';
$submit_label = $is_edit ? 'Save Changes' : 'Add Task';
$close_fn = $is_edit ? 'closeEditModal()' : 'closeAddModal()';
$preview_id = $is_edit ? 'editPreview' : 'addPreview';
?>
<div id="<?= $modal_id ?>" class="hidden fixed inset-0 z-50 flex justify-center items-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-11/12 max-w-md p-6 modal-animate relative">
        <span class="absolute top-4 right-4 text-2xl cursor-pointer text-gray-400 hover:text-gray-600" onclick="<?= $close_fn ?>">&times;</span>
        <h2 class="text-xl font-bold text-gray-700 mb-4"><?= $title ?></h2>

        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action_type" value="<?= $mode ?>">
            <?php if ($is_edit): ?>
                <input type="hidden" name="edit_id" id="modal_id">
                <input type="hidden" name="existing_imagepath" id="modal_existing_imagepath">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Link to Kit (optional):</label>
                <select name="backlogid" <?= $is_edit ? 'id="modal_backlogid"' : '' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value=""><?= $is_edit ? '📌 General Task (no kit)' : 'General Task (no kit)' ?></option>
                    <?php foreach($backlog_items as $bl): ?>
                        <option value="<?= $bl['actualid'] ?>"><?= e($bl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Task:</label>
                <input type="text" name="description" <?= $is_edit ? 'id="modal_description"' : '' ?> required
                       <?= $is_edit ? '' : 'placeholder="e.g. Remove seamlines from knee joint"' ?>
                       class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Reference Image (optional):</label>
                <input type="file" name="image" accept="image/*" class="w-full mt-1 p-2 border border-gray-300 rounded"
                       onchange="previewImage(this, '<?= $preview_id ?>')">
                <img id="<?= $preview_id ?>" class="mt-2 hidden w-32 h-32 object-cover rounded border">
                <?php if ($is_edit): ?>
                    <p id="editCurrentImage" class="mt-1 text-xs text-gray-400 hidden">Current image will be kept if no new one is selected.</p>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                <?= $submit_label ?>
            </button>
        </form>
    </div>
</div>
