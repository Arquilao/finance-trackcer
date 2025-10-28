function toggleExpenseForm() {
    const form = document.getElementById('expenseForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        form.scrollIntoView({ behavior: 'smooth' });
    }
}
        
// Show form if not in edit mode
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('date').value = new Date().toISOString().split('T')[0];
});