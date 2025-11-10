// Admin-specific functionality
document.addEventListener('DOMContentLoaded', function() {
    // Navigation functionality
    const navLinks = document.querySelectorAll('.nav-link');
    const pageContents = document.querySelectorAll('.page-content');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const pageId = this.getAttribute('data-page');
            
            navLinks.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
            
            pageContents.forEach(page => page.classList.remove('active'));
            document.getElementById(pageId).classList.add('active');
            
            // Initialize charts when dashboard is opened
            if (pageId === 'dashboard') {
                setTimeout(initializeAdminCharts, 100);
            }
        });
    });
    
    // Initialize charts on page load if dashboard is active
    if (document.getElementById('dashboard').classList.contains('active')) {
        setTimeout(initializeAdminCharts, 500);
    }
});

function initializeAdminCharts() {
    // User Distribution Chart
    const userCtx = document.getElementById('userChart');
    if (userCtx) {
        new Chart(userCtx, {
            type: 'pie',
            data: {
                labels: ['Administrators', 'Gatekeepers', 'Students'],
                datasets: [{
                    data: [2, 5, 150],
                    backgroundColor: ['#e74c3c', '#3498db', '#27ae60']
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
    
    // Material Status Chart
    const materialCtx = document.getElementById('materialChart');
    if (materialCtx) {
        new Chart(materialCtx, {
            type: 'bar',
            data: {
                labels: ['Available', 'Assigned', 'Checked Out', 'Maintenance'],
                datasets: [{
                    label: 'Materials',
                    data: [120, 85, 45, 12],
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
    
    // Weekly Activity Chart
    const weeklyCtx = document.getElementById('weeklyChart');
    if (weeklyCtx) {
        new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Materials Registered',
                    data: [15, 22, 18, 25, 30, 12, 8],
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
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
}

// Edit material status function
function editMaterialStatus(materialId) {
    document.getElementById('statusMaterialId').value = materialId;
    document.getElementById('editStatusModal').style.display = 'flex';
}

// Edit user function (placeholder)
function editUser(userId) {
    alert('Edit user functionality for user ID: ' + userId + '\n\nThis would open a user edit form in a real implementation.');
}

// Close modal function
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

// Export functionality
document.querySelectorAll('.export-options .btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const exportType = this.textContent.trim();
        alert('Exporting ' + exportType + ' data...\n\nIn a real implementation, this would generate and download a file.');
    });
});