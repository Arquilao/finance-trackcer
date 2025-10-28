// Global JavaScript for Finance Tracker

document.addEventListener('DOMContentLoaded', function() {
    // Update current date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Initialize tooltips and other UI enhancements
    initializeUI();
});

// Update current date and time display
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };
    const dateTimeString = now.toLocaleDateString('en-US', options);
    
    const dateTimeElement = document.getElementById('currentDateTime');
    if (dateTimeElement) {
        dateTimeElement.textContent = dateTimeString;
    }
}

// Initialize UI components
function initializeUI() {
    // Add smooth scrolling to all links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add loading states to forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
                submitBtn.disabled = true;
            }
        });
    });
    
    // Auto-calculate salary total
    const salaryInputs = document.querySelectorAll('#hours, #hourly_rate, #usd_rate');
    salaryInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', calculateSalaryTotal);
        }
    });
}

// Toggle salary form visibility
function toggleSalaryForm() {
    const form = document.getElementById('salaryForm');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            form.scrollIntoView({ behavior: 'smooth' });
        }
    }
}

// Toggle expense form visibility
function toggleExpenseForm() {
    const form = document.getElementById('expenseForm');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            form.scrollIntoView({ behavior: 'smooth' });
        }
    }
}

// Calculate salary total automatically
function calculateSalaryTotal() {
    const hours = parseFloat(document.getElementById('hours')?.value) || 0;
    const hourlyRate = parseFloat(document.getElementById('hourly_rate')?.value) || 0;
    const usdRate = parseFloat(document.getElementById('usd_rate')?.value) || 1;
    
    const total = hours * hourlyRate * usdRate;
    
    // You can display this somewhere or use it for validation
    console.log('Calculated total:', total);
}

// Add animation to stat cards
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = '$' + value.toLocaleString();
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// Format currency input
function formatCurrency(input) {
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^\d.]/g, '');
        let decimalCount = (value.match(/\./g) || []).length;
        
        if (decimalCount > 1) {
            value = value.substring(0, value.lastIndexOf('.'));
        }
        
        e.target.value = value;
    });
}

// Initialize currency formatting
document.querySelectorAll('input[type="number"]').forEach(input => {
    if (input.id === 'amount' || input.id === 'hourly_rate') {
        formatCurrency(input);
    }
});

// Add confirmation for delete actions
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed?');
}

// Export data functionality
function exportToCSV(data, filename) {
    const csvContent = "data:text/csv;charset=utf-8," + data;
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Responsive menu for mobile
function initMobileMenu() {
    const menuToggle = document.createElement('button');
    menuToggle.className = 'mobile-menu-toggle';
    menuToggle.innerHTML = '☰';
    
    const navLinks = document.querySelector('.nav-links');
    if (navLinks && window.innerWidth <= 768) {
        document.querySelector('.nav-container').appendChild(menuToggle);
        
        menuToggle.addEventListener('click', function() {
            navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-container')) {
                navLinks.style.display = 'none';
            }
        });
    }
}

// Initialize mobile menu
initMobileMenu();

// Add loading spinner CSS
const style = document.createElement('style');
style.textContent = `
    .loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 1s ease-in-out infinite;
        margin-right: 8px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 5px;
    }
    
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: block;
        }
        
        .nav-links {
            display: none;
            flex-direction: column;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 15px;
        }
        
        .nav-links.show {
            display: flex;
        }
    }
`;
document.head.appendChild(style);