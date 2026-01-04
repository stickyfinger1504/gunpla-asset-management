<?php
if(!isset($section)) {$section ='kits';}
?>

<nav class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50 h-16">
    <div class="max-w-container mx-auto px-4 h-full">
        <div class="flex justify-between items-center h-full">
            
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-500 hover:text-blue-500 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                
                <a href="index.php" class="flex items-center gap-2">
                    <span class="text-2xl">🤖</span>
                    <span class="font-bold text-gray-700 hidden sm:block">Gunpla Hangar</span>
                </a>
            </div>
            <div class="flex items-center gap-6">
            <div class="hidden md:flex items-center space-x-4">
                <a href="inventory.php" class="<?php echo ($current_section == 'kits') ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> px-1 py-4 text-sm font-medium transition">
                    Kits
                </a>
                <a href="#" class="<?php echo ($current_section == 'paints') ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> px-1 py-4 text-sm font-medium transition">
                    Paints
                </a>
            </div>

            
                <div class="relative">
                    <input type="text" placeholder="Search..." class="bg-gray-100 text-sm rounded-full pl-4 pr-10 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 w-32 sm:w-64 transition-all">
                    <div class="absolute right-3 top-2 text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>
<script>
    function sidebar(){
        const sidebar= document.getElementById('sidebar')
        const sidebar= document.getElementById('sidebar-overlay')

        if(sidebar.classlist.contains('hidden')){
            
            sidebar.classList.remove('hidden');
            setTimeout(() => {
                sidebar.classList.remove('-translate-x-full');
            }, 10);
            overlay.classList.remove('hidden');
        }
        else{
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            setTimeout(() => {
                sidebar.classList.add('hidden');
            }, 300);
        }
    }


</script>   