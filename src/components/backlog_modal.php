<?php
/**
 * Reusable Backlog Modal Component
 * Usage: set $mode = 'add' or 'edit', then include this file.
 * Requires $inventory_kits, $buildplans, $statuses to be available in scope.
 */
$is_edit = ($mode === 'edit');
$modal_id = $is_edit ? 'editModal' : 'addModal';
$title = $is_edit ? '✏️ Edit Backlog Item' : '➕ Add to Backlog';
$submit_label = $is_edit ? 'Save Changes' : 'Add to Backlog';
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
                <label class="block text-sm font-semibold text-gray-600">Kit (from Inventory):</label>
                <select name="inventoryid" <?= $is_edit ? 'id="modal_inventoryid"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                    <option value="">-- Select Kit --</option>
                    <?php foreach($inventory_kits as $kit): ?>
                        <?php if (empty($kit['notes'])): ?>
                            <option value="<?= $kit['actualid'] ?>"><?= htmlspecialchars($kit['name']) ?></option>
                        <?php else: ?>
                            <option value="<?= $kit['actualid'] ?>"><?= htmlspecialchars($kit['name'] . ' - ' . $kit['notes']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Build Plan:</label>
                    <select name="buildplanid" <?= $is_edit ? 'id="modal_buildplanid"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <option value="">-- Select --</option>
                        <?php foreach($buildplans as $bp): ?>
                            <option value="<?= $bp['id'] ?>"><?= htmlspecialchars($bp['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Status:</label>
                    <select name="statusid" <?= $is_edit ? 'id="modal_status"' : '' ?> required class="w-full mt-1 p-2 border border-gray-300 rounded">
                        <?php foreach($statuses as $status): ?>
                            <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">Notes:</label>
                <textarea name="notes" <?= $is_edit ? 'id="modal_notes"' : '' ?> rows="3" <?= $is_edit ? '' : 'placeholder="Details..."' ?> class="w-full mt-1 p-2 border border-gray-300 rounded"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-600">References (URL):</label>
                <input type="url" name="references" <?= $is_edit ? 'id="modal_references"' : 'placeholder="https://..."' ?> class="w-full mt-1 p-2 border border-gray-300 rounded">
            </div>

            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded transition">
                <?= $submit_label ?>
            </button>
        </form>
    </div>
</div>
