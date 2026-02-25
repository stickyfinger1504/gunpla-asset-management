<aside id="sidebar" class="transform -translate-x-full md:translate-x-0 transition-transform duration-300 fixed md:sticky top-16 left-0 z-40 w-64 h-[calc(100vh-4rem)] bg-white border-r border-gray-200 overflow-y-auto hidden md:block shadow-lg md:shadow-none">
    <div class="p-4 space-y-4">
        
        <?php 
        global $NAV_SECTIONS;
        if (isset($NAV_SECTIONS[$current_section])): 
            $section_data = $NAV_SECTIONS[$current_section];
        ?>
            <div class="mb-6">
                <h3 class="px-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                    <?= e($section_data['label']) ?> Management
                </h3>
                <nav class="space-y-1">
                    <?php foreach ($section_data['sidebar_items'] as $item): ?>
                        <?php 
                        $is_active = ($current_page == $item['page']);
                        $is_disabled = $item['disabled'] ?? false;
                        
                        if ($is_disabled) {
                            $classes = 'text-gray-400 cursor-not-allowed';
                        } elseif ($is_active) {
                            $classes = 'bg-blue-50 text-blue-700';
                        } else {
                            $classes = 'text-gray-600 hover:bg-gray-50';
                        }
                        ?>
                        <a href="<?= $is_disabled ? '#' : e($item['href']) ?>" 
                           class="<?= $classes ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                            <?= $item['icon'] ?> <?= e($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        <?php endif; ?>

    </div>
</aside>

<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-gray-800 bg-opacity-50 z-30 hidden md:hidden"></div>