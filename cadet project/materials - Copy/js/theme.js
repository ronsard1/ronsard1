// Theme management functionality
document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    const themeSelector = document.getElementById('themeSelector');
    const themeOptions = document.querySelectorAll('.theme-option');
    
    // Toggle theme selector
    themeToggle.addEventListener('click', function() {
        themeSelector.style.display = themeSelector.style.display === 'block' ? 'none' : 'block';
    });
    
    // Handle theme selection
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            document.body.className = theme + '-theme';
            
            themeOptions.forEach(opt => opt.classList.remove('active'));
            this.classList.add('active');
            
            themeSelector.style.display = 'none';
            localStorage.setItem('selectedTheme', theme);
        });
    });
    
    // Load saved theme
    const savedTheme = localStorage.getItem('selectedTheme') || 'default';
    document.body.className = savedTheme + '-theme';
    document.querySelector(`.theme-option[data-theme="${savedTheme}"]`)?.classList.add('active');
    
    // Window controls functionality
    document.querySelector('.control-btn.minimize')?.addEventListener('click', function() {
        console.log('Minimize window');
    });
    
    document.querySelector('.control-btn.maximize')?.addEventListener('click', function() {
        console.log('Maximize/Restore window');
    });
    
    document.querySelector('.control-btn.close')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to close the application?')) {
            window.close();
        }
    });
    
    // Close theme selector when clicking outside
    document.addEventListener('click', function(event) {
        if (!themeToggle.contains(event.target) && !themeSelector.contains(event.target)) {
            themeSelector.style.display = 'none';
        }
    });
});