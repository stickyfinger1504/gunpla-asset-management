<?php
/**
 * Stat Card Component
 * 
 * @param string $value     The main number/value to display
 * @param string $label     Label below the value
 * @param string $color     Color theme (blue, green, yellow, purple, red)
 * @param string|null $icon Optional emoji or icon character
 */

$color = $color ?? 'blue';
$color_map = [
    'blue'   => 'border-blue-500',
    'green'  => 'border-green-500',
    'yellow' => 'border-yellow-500',
    'red'    => 'border-red-500',
    'purple' => 'border-purple-500', 
    'pink'   => 'border-pink-500',
    'gray'   => 'border-gray-500',
];
$border_class = $color_map[$color] ?? 'border-blue-500';
?>
<div class="bg-white rounded-lg shadow p-4 border-l-4 <?= $border_class ?>">
    <div class="text-2xl font-bold text-gray-800">
        <?php if (!empty($icon)): ?>
            <span class="mr-1"><?= $icon ?></span>
        <?php endif; ?>
        <?= e($value) ?>
    </div>
    <div class="text-sm text-gray-500 uppercase tracking-wide">
        <?= e($label) ?>
    </div>
</div>
