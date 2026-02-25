<?php
/**
 * Toast Notification Component
 * 
 * Usage: Set $message before including this file
 * Example:
 *   $message = get_flash_message();
 *   include '../components/toast.php';
 */

if (!empty($message)): ?>
<div id="toast" class="fixed top-6 right-6 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 animate-slide-in">
    <span><?= $message ?></span>
    <button onclick="closeToast()" class="ml-2 hover:text-green-200">&times;</button>
</div>
<style>
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
    .animate-slide-in { animation: slideIn 0.3s ease-out; }
    .animate-slide-out { animation: slideOut 0.3s ease-in forwards; }
</style>
<script>
    function closeToast() {
        const toast = document.getElementById('toast');
        toast.classList.add('animate-slide-out');
        setTimeout(() => toast.remove(), 300);
    }
    setTimeout(closeToast, 3000);
</script>
<?php endif; ?>
