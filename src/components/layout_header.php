<?php
/**
 * Layout Header Component
 * Include this at the start of every page to get consistent HTML structure.
 * 
 * Required variables before including:
 * - $page_title (string) - The page title
 * 
 * Optional variables:
 * - $current_section (string) - For nav highlighting (default: 'kits')
 * - $current_page (string) - For sidebar highlighting
 * - $extra_styles (string) - Additional CSS to include in <head>
 */

if(!isset($page_title)) { $page_title = 'Gunpla Hangar'; }
if(!isset($current_section)) { $current_section = 'kits'; }
if(!isset($current_page)) { $current_page = ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | Gunpla Hangar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <link rel="stylesheet" href="/assets/css/components.css">
    <style>
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-animate { animation: slideDown 0.3s ease-out; }
        <?php if(isset($extra_styles)) echo $extra_styles; ?>
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
<script>
    function toggleFilterBar(btn) {
        const body = btn.closest('form').querySelector('.filter-bar-body');
        body.classList.toggle('is-open');
        btn.textContent = body.classList.contains('is-open') ? '▲ Hide' : '▼ Filters';
    }
</script>

<?php include __DIR__ . '/header.php'; ?>

<div class="flex min-h-screen">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <main class="flex-1 min-w-0 p-2 sm:p-4 md:p-6">
