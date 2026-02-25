<?php
/**
 * Chart Canvas Component
 * 
 * Renders the HTML container for a Chart.js chart.
 * 
 * @param string $id       Unique ID for the canvas element
 * @param string $title    Title displayed above the chart
 * @param string $height   CSS height (default: 'h-64')
 */

$height = $height ?? 'h-64';
?>
<div class="bg-white rounded-lg shadow p-4">
    <?php if (!empty($title)): ?>
        <div class="text-sm font-semibold text-gray-600 mb-3"><?= e($title) ?></div>
    <?php endif; ?>
    
    <div class="<?= $height ?>">
        <canvas id="<?= e($id) ?>"></canvas>
    </div>
    <!-- Slot for extra controls (like buttons) -->
    <?php if (isset($extra_controls)): ?>
        <div class="mt-2">
            <?= $extra_controls ?>
        </div>
    <?php endif; ?>
</div>
