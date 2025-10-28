function toggleDateFields() {
    const reportType = document.getElementById('report_type').value;
    const yearField = document.getElementById('year_field');
    const customDateFields = document.getElementById('custom_date_fields');
            
    if (reportType === 'custom') {
        yearField.style.display = 'none';
        customDateFields.style.display = 'block';
    } else {
        yearField.style.display = 'block';
        customDateFields.style.display = 'none';
    }
}
        
function printReport() {
    window.print();
}
        
function exportToPDF() {
    // Get the report content
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

    // Get report title based on type
    const reportType = document.getElementById('report_type')?.value || 'financial';
    const reportTitle = getReportTitle(reportType);

    const options = {
        margin: 10,
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
    
    // Remove action buttons from PDF
    const actionButtons = element.querySelectorAll('.export-buttons, .btn-secondary, .no-print, .report-filters');
    actionButtons.forEach(btn => btn.remove());
    
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

function getReportTitle(reportType) {
    const titles = {
        'monthly': 'Monthly-Financial-Report',
        'custom': 'Custom-Financial-Report',
        'financial': 'Financial-Report'
    };
    return titles[reportType] || 'Financial-Report';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleDateFields();
});