/**
 * Clickable Table Header Sorting
 * Sorts table rows client-side without page reload.
 * Supports text, number, and date data types.
 */
function sortTable(th, columnIndex, dataType) {
    const table = th.closest('table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    // Initialize original order index on first use
    if (!table.dataset.sortInitialized) {
        rows.forEach((row, index) => {
            row.dataset.originalIndex = index;
        });
        table.dataset.sortInitialized = "true";
    }

    // Determine current sort direction
    const isAsc = th.classList.contains('is-sorted-asc');
    const isDesc = th.classList.contains('is-sorted-desc');
    let nextState;

    if (dataType === 'text') {
        // Text cycle: asc -> desc -> reset
        if (isAsc) nextState = 'desc';
        else if (isDesc) nextState = 'none';
        else nextState = 'asc';
    } else {
        // Number/Date cycle: desc -> asc -> reset
        if (isDesc) nextState = 'asc';
        else if (isAsc) nextState = 'none';
        else nextState = 'desc';
    }

    // Clear sort indicators from all th in this thead
    const allTh = th.closest('thead').querySelectorAll('th');
    allTh.forEach(function(header) {
        header.classList.remove('is-sorted-asc', 'is-sorted-desc');
    });

    if (nextState === 'none') {
        // 3rd click: Reset to original order
        rows.sort((a, b) => {
            return parseInt(a.dataset.originalIndex) - parseInt(b.dataset.originalIndex);
        });
    } else {
        // 1st or 2nd click: Set indicator on clicked th
        th.classList.add(nextState === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
        const ascending = (nextState === 'asc');

        // Sort rows
        rows.sort(function(rowA, rowB) {
            const cellA = rowA.querySelectorAll('td')[columnIndex];
            const cellB = rowB.querySelectorAll('td')[columnIndex];

            if (!cellA || !cellB) return 0;

            let a = (cellA.textContent || '').trim();
            let b = (cellB.textContent || '').trim();

            let comparison = 0;

            if (dataType === 'number') {
                const numA = parseFloat(a.replace(/[^0-9.\-]/g, '')) || 0;
                const numB = parseFloat(b.replace(/[^0-9.\-]/g, '')) || 0;
                comparison = numA - numB;
            } else if (dataType === 'date') {
                const dateA = new Date(a);
                const dateB = new Date(b);
                const timeA = isNaN(dateA.getTime()) ? 0 : dateA.getTime();
                const timeB = isNaN(dateB.getTime()) ? 0 : dateB.getTime();
                comparison = timeA - timeB;
            } else {
                comparison = a.localeCompare(b, undefined, { sensitivity: 'base' });
            }

            return ascending ? comparison : -comparison;
        });
    }

    // Re-append rows in sorted order
    rows.forEach(function(row) {
        tbody.appendChild(row);
    });
}
