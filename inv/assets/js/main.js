/* assets/js/main.js */
$(document).ready(function() {
    // Top Products Chart
    if ($('#topProductsChart').length) {
        const ctx = document.getElementById('topProductsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Product A', 'Product B', 'Product C'],
                datasets: [{
                    data: [12, 19, 3],
                    backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
});
