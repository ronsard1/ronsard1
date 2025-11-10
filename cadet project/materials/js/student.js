// Student-specific functionality
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
        });
    });
    
    // Set default date for expected return (3 days from now)
    const today = new Date();
    const returnDate = new Date(today);
    returnDate.setDate(today.getDate() + 3);
    const returnDateInput = document.getElementById('expected_return_date');
    if (returnDateInput) {
        returnDateInput.valueAsDate = returnDate;
    }
    
    // Auto-fill form when navigating from available materials
    const urlParams = new URLSearchParams(window.location.search);
    const materialId = urlParams.get('material_id');
    if (materialId && document.getElementById('material_id')) {
        document.getElementById('material_id').value = materialId;
    }
});

// Function to request specific material
function requestMaterial(materialId) {
    if (document.getElementById('material_id')) {
        document.getElementById('material_id').value = materialId;
        // Navigate to request page
        document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
        document.querySelectorAll('.page-content').forEach(page => page.classList.remove('active'));
        
        document.querySelector('[data-page="request-checkout"]').classList.add('active');
        document.getElementById('request-checkout').classList.add('active');
    }
}

// Close modal function (if needed in future)
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