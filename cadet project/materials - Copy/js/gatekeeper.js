// Main gatekeeper functionality
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
                setTimeout(initializeCharts, 100);
            }
        });
    });
    
    // Cadet telephone matching
    const telephoneInput = document.getElementById('telephone');
    const cadetMatchDiv = document.getElementById('cadet-match');
    
    if (telephoneInput && cadetMatchDiv) {
        telephoneInput.addEventListener('blur', function() {
            const telephone = this.value.trim();
            if (telephone.length > 5) {
                // Simulate AJAX call to check cadet match
                checkCadetMatch(telephone);
            }
        });
    }
    
    function checkCadetMatch(telephone) {
        // In real implementation, make AJAX call to server
        setTimeout(() => {
            const cadets = [
                {name: 'John Doe', rollno: 'C001'},
                {name: 'Jane Smith', rollno: 'C002'},
                {name: 'Mike Johnson', rollno: 'C003'}
            ];
            const randomCadet = cadets[Math.floor(Math.random() * cadets.length)];
            
            cadetMatchDiv.innerHTML = `<i class="fas fa-check-circle"></i> Matched cadet: ${randomCadet.name} (${randomCadet.rollno})`;
            cadetMatchDiv.style.display = 'block';
        }, 500);
    }
});

// Edit material function
function editMaterial(materialId) {
    // In real implementation, fetch material data via AJAX
    const sampleData = {
        name: 'Sample Material ' + materialId,
        category: 'Electronics',
        description: 'Sample description for material ' + materialId,
        size: '10x20x30 cm',
        quantity: 5,
        barcode: 'MAT' + materialId + 'BC',
        telephone: '+255123456789',
        supplier_name: 'Sample Supplier',
        supplier_contact: '+255987654321',
        supplier_email: 'supplier@example.com',
        notes: 'Sample notes for material ' + materialId
    };
    
    document.getElementById('editMaterialId').value = materialId;
    
    // Populate form fields
    Object.keys(sampleData).forEach(key => {
        const field = document.getElementById('edit' + key.charAt(0).toUpperCase() + key.slice(1));
        if (field) field.value = sampleData[key];
    });
    
    document.getElementById('editMaterialModal').style.display = 'flex';
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