<?php
$height = $height ?? 'h-64';
?>
<div class="bg-white rounded-lg shadow p-4">
    <?php if (!empty($title)): ?>
        <div class="text-sm font-semibold text-gray-600 mb-3"><?= e($title) ?></div>
    <?php endif; ?>
    
    <div class="<?= $height ?>">
        <canvas id="<?= e($id) ?>"></canvas>
    </div>

    <?php if (isset($extra_controls)): ?>
        <div class="mt-2">
            <?= $extra_controls ?>
        </div>
    <?php endif; ?>
</div>
