document.addEventListener("DOMContentLoaded", function () {
    // Get the canvas element for top actions chart
    var ctx = document.getElementById("topActionsChart").getContext("2d");

    // Use the data passed from PHP (actionLabels and actionCounts)
    // If no data is passed or empty, use fallback sample data
    const labels = typeof actionLabels !== 'undefined' && actionLabels.length > 0 
        ? actionLabels 
        : ["Password Change", "Login", "Logout", "User Addition", "Stock Update"];
    
    const data = typeof actionCounts !== 'undefined' && actionCounts.length > 0 
        ? actionCounts 
        : [10, 8, 6, 4, 3]; // Fallback sample data
    
    // Use colors passed from PHP or default colors
    const colors = typeof chartColors !== 'undefined' ? chartColors : [
        "#4e73df", "#1cc88a", "#36b9cc", "#f6c23e", "#e74a3b"
    ];

    // Create the chart
    var topActionsChart = new Chart(ctx, {
        type: "doughnut",
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                hoverBackgroundColor: colors.map(color => color.replace('rgb', 'rgba').replace(')', ', 0.8)')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.dataset.data.reduce((acc, data) => acc + data, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
});