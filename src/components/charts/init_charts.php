<script>
/**
 * Shared Chart Configuration
 * Defines common colors and helper functions for all charts.
 */
const ChartConfig = {
    colors: [
        '#3B82F6',
        '#10B981',
        '#F59E0B',
        '#EF4444',
        '#8B5CF6',
        '#EC4899',
        '#06B6D4',
        '#84CC16', 
        '#F97316',
        '#6366F1'  
    ],

    pieOptions: {
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { 
            legend: { position: 'right' } 
        }
    },

    formatCurrency: (value) => {
        return 'Rp. ' + value.toLocaleString('id-ID');
    },

    getColors: (count) => {
        return ChartConfig.colors.slice(0, count);
    }
};

function initDoughnutChart(canvasId, labels, dataValues) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    return new Chart(ctx, {
        type: 'doughnut',
        data: { 
            labels: labels, 
            datasets: [{ 
                data: dataValues, 
                backgroundColor: ChartConfig.getColors(dataValues.length) 
            }] 
        },
        options: ChartConfig.pieOptions
    });
}
</script>
