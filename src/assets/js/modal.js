/**
 * Shared modal and utility functions used across pages.
 */

function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
    document.getElementById('addModal').style.display = 'flex';
}

function closeAddModal() {
    document.getElementById('addModal').classList.add('hidden');
    document.getElementById('addModal').style.display = 'none';
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').style.display = 'none';
}

function clearFilters(btn) {
    const form = btn.closest('form');
    form.querySelectorAll('input[type="text"]').forEach(el => el.value = '');
    form.querySelectorAll('select').forEach(el => el.selectedIndex = 0);
    form.submit();
}

function initScrollRestore(storageKey) {
    var pos = sessionStorage.getItem(storageKey);
    if (pos) {
        window.scrollTo(0, parseInt(pos));
        sessionStorage.removeItem(storageKey);
    }
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            sessionStorage.setItem(storageKey, window.scrollY);
        });
    });
}

// Backdrop click to close modals
window.addEventListener('click', function(event) {
    if (event.target == document.getElementById('addModal')) closeAddModal();
    if (event.target == document.getElementById('editModal')) closeEditModal();
});
