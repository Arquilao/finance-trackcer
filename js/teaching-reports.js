function toggleDateFields() {
    const reportType = document.getElementById('report_type').value;
    const monthField = document.getElementById('month_field');
    const customDateFields = document.getElementById('custom_date_fields');
    
    // Hide both first
    monthField.style.display = 'none';
    customDateFields.style.display = 'none';
    
    // Show the appropriate one
    if (reportType === 'monthly') {
        monthField.style.display = 'block';
    } else if (reportType === 'custom') {
        customDateFields.style.display = 'flex';
    }
}

// Print Report Function
function printReport() {
    window.print();
}

// Export PDF Function
function exportPDF() {
    // Get the main report content
    const reportContent = document.querySelector('.container') || document.body;
    
    if (!reportContent) {
        alert('Report content not found!');
        return;
    }

    // Check if html2pdf is available
    if (typeof html2pdf === 'undefined') {
        alert('PDF export is not available right now. Please try again later.');
        return;
    }

    // Get report title
    const reportType = document.getElementById('report_type')?.value || 'teaching';
    const reportTitle = getTeachingReportTitle(reportType);

    const options = {
        margin: 15,
        filename: `${reportTitle}-${new Date().toISOString().split('T')[0]}.pdf`,
        image: { 
            type: 'jpeg', 
            quality: 0.98 
        },
        html2canvas: { 
            scale: 2,
            useCORS: true,
            logging: false
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'portrait' 
        }
    };

    // Create a clone to avoid modifying the original
    const element = reportContent.cloneNode(true);
    
    // Remove action buttons and filters from PDF
    const elementsToRemove = element.querySelectorAll('.export-buttons, .btn-secondary, .no-print, .report-filters, .navbar');
    elementsToRemove.forEach(el => el.remove());
    
    // Show loading state
    const originalText = event.target.textContent;
    event.target.textContent = 'Generating PDF...';
    event.target.disabled = true;

    html2pdf()
        .set(options)
        .from(element)
        .save()
        .finally(() => {
            // Restore button state
            event.target.textContent = originalText;
            event.target.disabled = false;
        });
}

function getTeachingReportTitle(reportType) {
    const titles = {
        'monthly': 'Monthly-Teaching-Report',
        'custom': 'Custom-Teaching-Report',
        'teaching': 'Teaching-Report'
    };
    return titles[reportType] || 'Teaching-Report';
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    toggleDateFields();
});