<aside id="sidebar" class="transform -translate-x-full md:translate-x-0 transition-transform duration-300 fixed md:sticky top-16 left-0 z-40 w-64 h-[calc(100vh-4rem)] bg-white border-r border-gray-200 overflow-y-auto hidden md:block shadow-lg md:shadow-none">
    <div class="p-4 space-y-4">
        
        <?php if ($section == 'kits'): ?>
            <div class="mb-6">
                <h3 class="px-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                    Kit Management
                </h3>
                <nav class="space-y-1">
                    <a href="inventory.php" class="<?php echo ($current_page == 'inventory') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        📦 Inventory
                    </a>
                    <a href="wishlist.php" class="<?php echo ($current_page == 'wishlist') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        ✨ Wishlist
                    </a>
                    <a href="#" class="text-gray-400 group flex items-center px-2 py-2 text-sm font-medium rounded-md cursor-not-allowed">
                        🚧 Backlog Plan
                    </a>
                </nav>
            </div>
        <?php endif; ?>

        <?php if ($section == 'paints'): ?>
            <div class="mb-6">
                <h3 class="px-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                    Paint Studio
                </h3>
                <nav class="space-y-1">
                    <a href="#" class="text-gray-600 hover:bg-gray-50 group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        🎨 Paint Inventory
                    </a>
                    <a href="#" class="text-gray-600 hover:bg-gray-50 group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                        🧪 Mixing Recipes
                    </a>
                </nav>
            </div>
        <?php endif; ?>

    </div>
</aside>

<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-gray-800 bg-opacity-50 z-30 hidden md:hidden"></div>