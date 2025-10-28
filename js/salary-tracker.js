let currentIncomeType = document.getElementById('income_type') ? document.getElementById('income_type').value : 'teaching';

function selectIncomeType(type) {
    currentIncomeType = type;
    document.getElementById('income_type').value = type;
    
    // Update tabs
    document.querySelectorAll('.income-tab').forEach(tab => {
        // Remove 'active' from all tabs
        tab.classList.remove('active');
        
        // --- THIS IS THE FIX ---
        // Add 'active' to the one that was clicked by checking its onclick attribute.
        // This is safer than event.target or event.currentTarget.
        if (tab.getAttribute('onclick').includes(`'${type}'`)) {
            tab.classList.add('active');
        }
    });
    
    // Update forms
    document.querySelectorAll('.income-form').forEach(form => {
        form.classList.remove('active');
    });
    document.getElementById(type + 'Form').classList.add('active');
    
    // Recalculate
    calculateIncome();
}

function calculateIncome() {
    if (currentIncomeType === 'teaching') {
        calculateTeachingIncome();
    } else {
        calculateGiftIncome();
    }
}

function calculateTeachingIncome() {
    const regular = parseInt(document.getElementById('regular_students').value) || 0;
    const trial = parseInt(document.getElementById('trial_students').value) || 0;
    const absent = parseInt(document.getElementById('trial_absent').value) || 0; // NEW
    const conversions = parseInt(document.getElementById('trial_conversions').value) || 0;
    const exchangeRate = parseFloat(document.getElementById('exchange_rate').value) || 56;
    
    const regularIncome = regular * 2;
    const trialIncome = trial * 1;
    const absentIncome = absent * 0.80; // NEW
    const conversionBonus = conversions * 3;

    // UPDATED total
    const totalUSD = regularIncome + trialIncome + absentIncome + conversionBonus;
    const totalPHP = totalUSD * exchangeRate;
    
    // UPDATED calculation preview string
    document.getElementById('teachingCalculation').innerHTML = `
        <strong>Income Calculation:</strong><br>
        Regular: ${regular} students × $2 = $${regularIncome.toFixed(2)}<br>
        Trial: ${trial} students × $1 = $${trialIncome.toFixed(2)}<br>
        Trial Absent: ${absent} students × $0.80 = $${absentIncome.toFixed(2)}<br>
        Conversion Bonus: ${conversions} × $3 = $${conversionBonus.toFixed(2)}<br>
        <strong>Total: $${totalUSD.toFixed(2)} (₱${totalPHP.toFixed(2)})</strong>
    `;
}

function calculateGiftIncome() {
    const amount = parseFloat(document.getElementById('gift_amount').value) || 0;
    const currency = document.getElementById('gift_currency').value;
    const exchangeRate = parseFloat(document.getElementById('exchange_rate').value) || 56;
    
    let phpAmount, usdAmount;
    
    if (currency === 'USD') {
        usdAmount = amount;
        phpAmount = amount * exchangeRate;
    } else {
        // Avoid division by zero if exchange rate is missing
        phpAmount = amount;
        usdAmount = exchangeRate > 0 ? amount / exchangeRate : 0;
    }
    
    document.getElementById('giftCalculation').innerHTML = `
        <strong>Gift Value:</strong><br>
        <strong>Total: ₱${phpAmount.toFixed(2)} ($${usdAmount.toFixed(2)})</strong>
    `;
}

function toggleSalaryForm() {
    const form = document.getElementById('salaryForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        form.scrollIntoView({ behavior: 'smooth' });
        calculateIncome();
    }
}

// Auto-update exchange rate based on selected date
function updateExchangeRateForDate(selectedDate) {
    // This function can be expanded later with AJAX to fetch rates from the database
    console.log('Date changed to:', selectedDate);
    // For now, it doesn't need to do anything as the rate is loaded with the page
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const dateInput = document.getElementById('date');
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }
    // Calculate initial income on load (for both add and edit modes)
    calculateIncome();
});