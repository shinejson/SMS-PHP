const billingModal = document.getElementById('billingModal');
const billingForm = document.getElementById('billingForm');
const modalTitle = document.getElementById('modalTitle');
const modalSubmitBtn = document.getElementById('modalSubmitBtn');
const billingMessage = document.getElementById('billingMessage');
const paymentTypeSelect = document.getElementById('payment_type');
const tuitionFieldsGroup = document.getElementById('tuitionFields');
const tuitionSubFieldsContainer = document.getElementById('tuitionSubFields');
const simpleFieldGroup = document.getElementById('simpleFieldGroup');
const amountInput = document.getElementById('amount');

// Toggles tuition vs. simple amount fields
function togglePaymentFields(type) {
    if (type === 'Tuition') {
        tuitionFieldsGroup.classList.remove('hidden');
        simpleFieldGroup.classList.add('hidden');
        amountInput.removeAttribute('required');
    } else {
        tuitionFieldsGroup.classList.add('hidden');
        simpleFieldGroup.classList.remove('hidden');
        amountInput.setAttribute('required', 'required');
    }
}

// Add a new tuition sub-field row
function addTuitionSubField(name = '', amount = '') {
    const newField = document.createElement('div');
    newField.className = 'sub-field-group';
    newField.innerHTML = `
        <input type="text" name="sub_fee_name[]" placeholder="e.g. Library Fee" value="${name}" required>
        <input type="number" name="sub_fee_amount[]" class="sub-fee-amount" placeholder="Amount" step="0.01" value="${amount}" required>
        <button type="button" class="remove-sub-fee">×</button>
    `;
    tuitionSubFieldsContainer.appendChild(newField);
    calculateTuitionTotal();
}

// Calculate the total of all tuition sub-fees
function calculateTuitionTotal() {
    let total = 0;
    document.querySelectorAll('.sub-fee-amount').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    amountInput.value = total.toFixed(2);
}

// Show a message in the form
function showFormMessage(message, isSuccess) {
    billingMessage.textContent = message;
    billingMessage.className = isSuccess ? 'success-message' : 'error-message';
}

// Validate the form before submission
function validateForm() {
    const paymentType = paymentTypeSelect.value;
    
    if (!paymentType) {
        showFormMessage('Please select a payment type', false);
        return false;
    }
    
    if (paymentType === 'Tuition') {
        const subFields = tuitionSubFieldsContainer.querySelectorAll('.sub-field-group');
        if (subFields.length === 0) {
            showFormMessage('Please add at least one tuition sub-fee', false);
            return false;
        }
        
        const subFeeNames = new Set();
        let isValid = true;

        subFields.forEach(field => {
            const nameInput = field.querySelector('input[type="text"]');
            const amountInput = field.querySelector('input[type="number"]');
            const name = nameInput.value.trim();
            const amount = amountInput.value;
            
            if (!name || isNaN(amount) || parseFloat(amount) <= 0) {
                isValid = false;
            }

            // Check for duplicate sub-fee names
            if (subFeeNames.has(name)) {
                showFormMessage(`Duplicate sub-fee name found: "${name}"`, false);
                isValid = false;
            } else if (name !== '') {
                subFeeNames.add(name);
            }
        });
        
        if (!isValid) {
            showFormMessage('Please fill all sub-fee fields with valid and unique names', false);
            return false;
        }
    } else {
        const amount = amountInput.value;
        if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
            showFormMessage('Please enter a valid amount greater than 0', false);
            return false;
        }
    }
    
    if (!document.getElementById('term_id').value) {
        showFormMessage('Please select a term', false);
        return false;
    }
    
    if (!document.getElementById('academic_year').value) {
        showFormMessage('Academic year is required', false);
        return false;
    }
    
    if (!document.getElementById('due_date').value) {
        showFormMessage('Due date is required', false);
        return false;
    }
    
    return true;
}

// Global functions for modal control
window.openAddBillingModal = function() {
    billingForm.reset();
    billingForm.action = 'insert_billing.php';
    document.getElementById('billing_id').value = '';
    modalTitle.textContent = 'Add Student Billing';
    modalSubmitBtn.textContent = 'Submit';
    billingMessage.textContent = '';
    billingMessage.className = '';
    
    tuitionSubFieldsContainer.innerHTML = '';
    addTuitionSubField(); // Add default empty row
    togglePaymentFields('');
    
    billingModal.style.display = 'block';
};

window.closeBillingModal = function() {
    billingModal.style.display = 'none';
};

// Event Listeners
paymentTypeSelect.addEventListener('change', function() {
    togglePaymentFields(this.value);
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('add-sub-fee')) {
        addTuitionSubField();
    }
    if (e.target.classList.contains('remove-sub-fee')) {
        e.target.closest('.sub-field-group').remove();
        calculateTuitionTotal();
    }
});

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('sub-fee-amount')) {
        calculateTuitionTotal();
    }
});

billingForm.addEventListener('submit', function(e) {
    e.preventDefault();
    if (!validateForm()) return;
    
    modalSubmitBtn.disabled = true;
    modalSubmitBtn.textContent = 'Processing...';
    
    fetch(this.action, {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        showFormMessage(data.message, data.success);
        if (data.success) {
            setTimeout(() => {
                billingModal.style.display = 'none';
                location.reload();
            }, 1500);
        }
    })
    .catch(error => {
        showFormMessage('Error: ' + error.message, false);
    })
    .finally(() => {
        modalSubmitBtn.disabled = false;
        modalSubmitBtn.textContent = 'Submit';
    });
});