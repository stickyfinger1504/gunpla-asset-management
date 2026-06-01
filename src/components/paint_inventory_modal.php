<?php
/**
 * Reusable Paint Inventory Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $paint_brands, $paint_types, $thinned_statuses, $amount_levels to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Paint' : '➕ Add Paint';
$submit_label = $is_edit ? 'Save Changes' : 'Save to Collection';
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
                <input type="hidden" name="existing_imagepath" id="modal_imagepath">
            <?php endif; ?>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Paint Name:</label>
                <input type="text" name="name" <?= $is_edit ? 'id="modal_name"' : '' ?> required <?= $is_edit ? '' : 'placeholder="e.g. Flat Black"' ?>
                       class="w-full mt-1 p-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Brand:</label>
                    <select name="brand" <?= $is_edit ? 'id="modal_brand"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select --</option>
                        <?php foreach($paint_brands as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Paint Type:</label>
                    <select name="painttype" <?= $is_edit ? 'id="modal_painttype"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select --</option>
                        <?php foreach($paint_types as $pt): ?>
                            <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Thinned:</label>
                    <select name="thinned" <?= $is_edit ? 'id="modal_thinned"' : '' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- N/A --</option>
                        <?php foreach($thinned_statuses as $ts): ?>
                            <option value="<?= $ts['id'] ?>"><?= htmlspecialchars($ts['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Amount:</label>
                    <select name="amount" <?= $is_edit ? 'id="modal_amount"' : '' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- N/A --</option>
                        <?php foreach($amount_levels as $al): ?>
                            <option value="<?= $al['id'] ?>"><?= htmlspecialchars($al['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Date Added:</label>
                <input type="date" name="createddate" <?= $is_edit ? 'id="modal_createddate"' : '' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Color Hex:</label>
                    <input type="color" name="color_hex" <?= $is_edit ? 'id="modal_color_hex"' : 'id="add_color_hex"' ?> class="w-full mt-1 h-10 border border-gray-300 rounded cursor-pointer">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Finish:</label>
                    <select name="finishid" <?= $is_edit ? 'id="modal_finishid"' : 'id="add_finishid"' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- N/A --</option>
                        <?php foreach($paint_finishes as $pf): ?>
                            <option value="<?= $pf['id'] ?>"><?= htmlspecialchars($pf['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                <textarea name="notes" <?= $is_edit ? 'id="modal_notes"' : '' ?> rows="3" <?= $is_edit ? '' : 'placeholder="Color code, usage notes..."' ?> class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Image:</label>
                <?php if ($is_edit): ?>
                    <div id="modal_image_preview" class="hidden mb-2">
                        <img id="modal_image_thumb" src="" alt="Current image" class="w-20 h-20 object-cover rounded border">
                        <span class="text-xs text-gray-500">Current image (upload new to replace)</span>
                    </div>
                <?php endif; ?>
                <input type="file" name="image" <?= $is_edit ? 'id="edit_image_upload"' : 'id="add_image_upload"' ?> accept="image/*" class="w-full mt-1 p-2 border border-gray-300 rounded text-sm">
                <!-- Hidden canvas for OpenCV processing -->
                <canvas <?= $is_edit ? 'id="edit_cv_canvas"' : 'id="add_cv_canvas"' ?> style="display:none;"></canvas>
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                <?= $submit_label ?>
            </button>
        </form>
    </div>
</div>
