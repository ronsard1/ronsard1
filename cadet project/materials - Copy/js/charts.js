// Charts functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts when dashboard is active
    const dashboardLink = document.querySelector('[data-page="dashboard"]');
    if (dashboardLink) {
        dashboardLink.addEventListener('click', initializeCharts);
    }
    
    // Initialize charts on page load if dashboard is active
    if (document.getElementById('dashboard').classList.contains('active')) {
        setTimeout(initializeCharts, 500);
    }
});

function initializeCharts() {
    // Today's Materials Chart
    const todayCtx = document.getElementById('todayChart');
    if (todayCtx) {
        new Chart(todayCtx, {
            type: 'doughnut',
            data: {
                labels: ['Electronics', 'Tools', 'Books', 'Uniforms', 'Other'],
                datasets: [{
                    data: [12, 19, 8, 15, 6],
                    backgroundColor: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Weekly Materials Chart
    const weeklyCtx = document.getElementById('weeklyChart');
    if (weeklyCtx) {
        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Materials Registered',
                    data: [12, 19, 15, 25, 22, 18, 10],
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Category Distribution Chart
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: ['Electronics', 'Tools', 'Books', 'Uniforms', 'Sports', 'Lab Equipment'],
                datasets: [{
                    label: 'Materials by Category',
                    data: [65, 59, 80, 81, 56, 55],
                    backgroundColor: '#3498db'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}