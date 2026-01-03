<?php
// Get the current file name to highlight the active link
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="bg-white shadow-md sticky top-0 z-50">
    <div class="max-w-container mx-auto px-4">
        
        <div class="flex justify-between items-center h-16">
            
            <div class="flex-shrink-0">
                <a href="index.php" class="flex items-center">
                    <span class="font-semibold text-gray-500 text-lg">
                        🤖 Gunpla Hangar
                    </span>
                </a>
            </div>

            <div class="flex items-center gap-4"> 
            
                <div class="hidden md:flex items-center space-x-4">
                    <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'text-blue-500 border-b-2 border-blue-500' : 'text-gray-500 hover:text-blue-500'; ?> font-semibold px-2 py-1 transition duration-300">Home</a>
                    <a href="inventory.php" class="<?php echo ($current_page == 'inventory.php') ? 'text-blue-500 border-b-2 border-blue-500' : 'text-gray-500 hover:text-blue-500'; ?> font-semibold px-2 py-1 transition duration-300">Inventory</a>
                    <a href="wishlist.php" class="<?php echo ($current_page == 'wishlist.php') ? 'text-blue-500 border-b-2 border-blue-500' : 'text-gray-500 hover:text-blue-500'; ?> font-semibold px-2 py-1 transition duration-300">Wishlist</a>
                </div>

                <div class="hidden sm:flex items-center gap-2 relative border border-gray-300 rounded-full px-3 py-1.5 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                    </svg>
                    <input type="text" placeholder="Search..." class="bg-transparent outline-none text-sm w-full placeholder:text-gray-400">
                </div>

                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-button" class="text-gray-500 hover:text-blue-500 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>

            </div>
            </div>
    </div>

    <div id="mobile-menu" class="hidden md:hidden border-t border-gray-200">
        <a href="index.php" class="block py-2 px-4 text-sm hover:bg-gray-100 font-semibold text-gray-500">Home</a>
        <a href="inventory.php" class="block py-2 px-4 text-sm hover:bg-gray-100 font-semibold text-gray-500">Inventory</a>
        <a href="wishlist.php" class="block py-2 px-4 text-sm hover:bg-gray-100 font-semibold text-gray-500">Wishlist</a>
    </div>
</nav>
<script>
    const btn = document.getElementById('mobile-menu-button');
    const menu = document.getElementById('mobile-menu');

    if(btn && menu) {
        btn.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });
    }
</script>   