document.addEventListener('DOMContentLoaded', function() {
    // Role selection on login page
    const roleOptions = document.querySelectorAll('.role-option');
    if (roleOptions.length > 0) {
        roleOptions.forEach(option => {
            option.addEventListener('click', function() {
                roleOptions.forEach(o => o.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
    
    // Login form submission
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const selectedRole = document.querySelector('.role-option.active').getAttribute('data-role');
            
            // Simple validation
            if (username && password) {
                // Redirect to appropriate dashboard
                if (selectedRole === 'admin') {
                    window.location.href = 'admin-dashboard.html';
                } else if (selectedRole === 'gatekeeper') {
                    window.location.href = 'gatekeeper-dashboard.html';
                } else if (selectedRole === 'student') {
                    window.location.href = 'student-dashboard.html';
                }
            }
        });
    }
    
    // Tab functionality
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Deactivate all tabs in the same container
            const parent = this.closest('.tabs');
            parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            
            // Activate current tab
            this.classList.add('active');
            
            // Show corresponding content
            const container = this.closest('.content');
            container.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
            container.getElementById(`${tabName}-tab`).classList.add('active');
        });
    });
    
    // Form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Form submitted! (This would connect to PHP backend in a real application)');
        });
    });
});