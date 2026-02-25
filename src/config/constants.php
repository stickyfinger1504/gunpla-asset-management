<?php
/**
 * Site Configuration Constants
 * Centralized configuration for app-wide settings and navigation structure.
 */

define('APP_NAME', 'Gunpla Hangar');
define('APP_EMOJI', '🤖');

// Navigation Structure
$NAV_SECTIONS = [
    'kits' => [
        'label' => 'Kits',
        'default_page' => '/inventory',
        'sidebar_items' => [
            ['page' => 'inventory', 'href' => '/inventory', 'icon' => '📦', 'label' => 'Inventory'],
            ['page' => 'wishlist', 'href' => '/wishlist', 'icon' => '✨', 'label' => 'Wishlist'],
            ['page' => 'backlog', 'href' => '/backlog', 'icon' => '🚧', 'label' => 'Backlog Plan'],
            ['page' => 'tasks', 'href' => '/tasks', 'icon' => '📋', 'label' => 'Tasks'],
            ['page' => 'build_progress','href' => '/build_progress','icon' => '🔨', 'label' => 'Build Progress']
        ]
    ],
    'paints' => [
        'label' => 'Paints',
        'default_page' => '/paint_inventory',
        'sidebar_items' => [
            ['page' => 'paint_inventory', 'href' => '/paint_inventory', 'icon' => '🎨', 'label' => 'Paint Inventory'],
            ['page' => 'paint_wishlist', 'href' => '/paint_wishlist', 'icon' => '✨', 'label' => 'Paint Wishlist'],
            ['page' => 'mixing_recipes', 'href' => '/mixing_recipes', 'icon' => '🧪', 'label' => 'Mixing Recipes'],
        ]
    ]
];

// Image Upload Config
define('UPLOAD_DIR', __DIR__ . '/../assets/transaction_images/');
define('UPLOAD_URL_PREFIX', '/assets/transaction_images/');
define('PAINT_UPLOAD_DIR', __DIR__ . '/../assets/paint_images/');
define('PAINT_UPLOAD_URL_PREFIX', '/assets/paint_images/');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('MAX_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB per image
