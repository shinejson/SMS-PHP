document.addEventListener('DOMContentLoaded', function () {
    // --- Core UI Elements ---
    const billingModal = document.getElementById('billingModal');
    const deleteModal = document.getElementById('deleteModal');
    const deleteIdInput = document.getElementById('deleteId');
    const billingForm = document.getElementById('billingForm');
    const modalTitle = document.getElementById('modalTitle');
    const billingMessage = document.getElementById('billingMessage');
    
    // --- Form-specific Elements ---
    const billingIdInput = document.getElementById('billingId');
    const paymentTypeSelect = document.getElementById('payment_type');
    const termIdSelect = document.getElementById('term_id');
    const academicYearInput = document.getElementById('academic_year');
    const dueDateInput = document.getElementById('due_date');
    const descriptionInput = document.getElementById('description');
    const classIdSelect = document.getElementById('class_id');
    const tuitionSection = document.getElementById('tuitionSection');
    const tuitionSubFieldsContainer = document.getElementById('tuitionSubFields');
    const simpleFieldGroup = document.getElementById('simpleFieldGroup');
    const amountInput = document.getElementById('amount');
    
    // --- Initialize DataTable ---
    let billingTable;

    function initializeDataTable() {
        if ($.fn.dataTable.isDataTable('#billingTable')) {
            $('#billingTable').DataTable().destroy();
        }
        
        billingTable = $('#billingTable').DataTable({
            responsive: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            order: [[0, 'desc']], // Order by first column (usually ID) descending
            buttons: [
                { extend: 'copy', className: 'btn-dt-copy' },
                { extend: 'csv', className: 'btn-dt-csv' },
                { extend: 'excel', className: 'btn-dt-excel' },
                { extend: 'pdf', className: 'btn-dt-pdf' },
                {
                    extend: 'print',
                    className: 'btn-dt-print',
                    text: '<i class="fas fa-print"></i> Print',
                    title: 'Billing Records',
                    customize: function (win) {
                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');
                    }
                }
            ],
            columnDefs: [{ orderable: false, targets: [6] }], // Actions column not orderable
            stateSave: true, // Save pagination state
            drawCallback: function (settings) {
                // Re-bind event handlers after each draw using event delegation
                bindActionButtons();
            }
        });
        
        // Use event delegation for better performance and pagination handling
        bindGlobalEventListeners();
    }

    // --- Improved event binding with delegation ---
    function bindActionButtons() {
        // Remove old event listeners to prevent duplicates
        $(document).off('click.billing');
        
        // Use event delegation for dynamic content
        $(document).on('click.billing', '.edit-payment', function () {
            const id = $(this).data('id');
            editBilling(id);
        });
        
        $(document).on('click.billing', '.delete-payment', function () {
            const id = $(this).data('id');
            confirmDeleteBilling(id);
        });
    }

    function bindGlobalEventListeners() {
        // These don't need to be rebound as they're not affected by pagination
        document.getElementById('addBillBtn').addEventListener('click', window.openAddBillingModal);
        
        paymentTypeSelect.addEventListener('change', function() {
            togglePaymentFields(this.value);
        });

        // Event delegation for dynamic tuition fee elements
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
    }

    // --- Helper functions ---
    function togglePaymentFields(type) {
        if (type === 'Tuition') {
            tuitionSection.classList.remove('hidden');
            simpleFieldGroup.classList.add('hidden');
            
            tuitionSubFieldsContainer.querySelectorAll('input').forEach(input => {
                input.setAttribute('required', 'required');
            });
            amountInput.removeAttribute('required');

        } else {
            tuitionSection.classList.add('hidden');
            simpleFieldGroup.classList.remove('hidden');
            
            amountInput.setAttribute('required', 'required');
            tuitionSubFieldsContainer.querySelectorAll('input').forEach(input => {
                input.removeAttribute('required');
            });
        }
    }

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

    function calculateTuitionTotal() {
        let total = 0;
        document.querySelectorAll('.sub-fee-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        amountInput.value = total.toFixed(2);
    }
    
    function showFormMessage(message, isSuccess) {
        const messageElement = document.getElementById('billingMessage');
        messageElement.textContent = message;
        messageElement.className = isSuccess ? 'form-message success-message' : 'form-message error-message';
        
        const icon = document.createElement('i');
        icon.className = isSuccess ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
        messageElement.prepend(icon);
        
        setTimeout(() => {
            messageElement.style.opacity = '0';
            setTimeout(() => {
                messageElement.textContent = '';
                messageElement.className = 'form-message';
                messageElement.style.opacity = '1';
            }, 300);
        }, 5000);
    }

    function validateForm() {
        const paymentType = paymentTypeSelect.value;
        const classId = classIdSelect.value;
        
        if (!paymentType) {
            showFormMessage('Please select a payment type', false);
            return false;
        }

        if (!classId) {
            showFormMessage('Please select a class', false);
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
                    showFormMessage('Please fill all sub-fee fields with valid data.', false);
                    isValid = false;
                }
    
                if (subFeeNames.has(name) && name !== '') {
                    showFormMessage(`Duplicate sub-fee name found: "${name}"`, false);
                    isValid = false;
                } else if (name !== '') {
                    subFeeNames.add(name);
                }
            });
            
            if (!isValid) return false;
        } else {
            const amount = amountInput.value;
            if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
                showFormMessage('Please enter a valid amount greater than 0', false);
                return false;
            }
        }
        
        if (!termIdSelect.value) {
            showFormMessage('Please select a term', false);
            return false;
        }
        
        if (!academicYearInput.value) {
            showFormMessage('Academic year is required', false);
            return false;
        }
        
        if (!dueDateInput.value) {
            showFormMessage('Due date is required', false);
            return false;
        }
        
        return true;
    }

    // --- Modal Control Functions ---
    window.openAddBillingModal = function() {
        billingForm.reset();
        billingForm.action = 'insert_billing.php';
        billingIdInput.value = '';
        modalTitle.textContent = 'Add Student Billing';
        billingMessage.textContent = '';
        billingMessage.className = '';
        
        tuitionSubFieldsContainer.innerHTML = '';
        addTuitionSubField();
        togglePaymentFields('');
        
        billingModal.style.display = 'block';
    };

    window.closeModal = function() {
        billingModal.style.display = 'none';
    };

    window.closeDeleteModal = function() {
        deleteModal.style.display = 'none';
    };

    // --- Edit Billing Record ---
// --- Edit Billing Record ---
window.editBilling = function(id) {
    fetch(`get_billing.php?id=${id}`)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP ${response.status}: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data); // Debug log
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load billing data');
            }

            // Fix: Use data.data instead of data.billing based on your PHP response structure
            const billing = data.data;
            
            if (!billing) {
                throw new Error('No billing data received');
            }

            // Clear previous state
            billingForm.reset();
            tuitionSubFieldsContainer.innerHTML = '';

            // Populate form with safe property access
            billingIdInput.value = billing.id || '';
            paymentTypeSelect.value = billing.payment_type || '';
            termIdSelect.value = billing.term_id || '';
            academicYearInput.value = billing.academic_year || '';
            dueDateInput.value = billing.due_date ? billing.due_date.split(' ')[0] : '';
            descriptionInput.value = billing.description || '';
            classIdSelect.value = billing.class_id || '';

            // Handle payment type specific fields
            if (billing.payment_type === 'Tuition' && billing.fee_breakdown) {
                const breakdown = billing.fee_breakdown;
                
                if (Array.isArray(breakdown) && breakdown.length > 0) {
                    breakdown.forEach(item => {
                        addTuitionSubField(item.name || '', item.amount || '');
                    });
                } else {
                    console.warn('Fee breakdown is empty or not an array:', breakdown);
                    addTuitionSubField(); // Add an empty field as fallback
                }
            } else {
                amountInput.value = billing.amount || '';
            }

            togglePaymentFields(billing.payment_type || '');
            billingModal.style.display = 'block';
            modalTitle.textContent = 'Edit Billing Record';
            billingForm.action = 'update_billing.php';
        })
        .catch(error => {
            console.error('Edit Billing Error:', error);
            alert('Failed to load billing data: ' + error.message);
        });
};
    // --- Confirm Delete Modal ---
    window.confirmDeleteBilling = function (id) {
        deleteIdInput.value = id;
        deleteModal.style.display = 'block';
    };

    // Add/Edit Form submission
    billingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!validateForm()) return;
        
        const submitBtn = this.querySelector('.submit-btn');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Processing...';

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
                    // Simply reload the page to refresh data and maintain proper state
                    window.location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            showFormMessage('Error: ' + error.message, false);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = billingIdInput.value ? 'Update' : 'Submit';
        });
    });

    // Delete form submission
    const deleteForm = document.getElementById('deleteForm');
    deleteForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('delete_billing.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showFormMessage(data.message, true);
                deleteModal.style.display = 'none';
                setTimeout(() => {
                    window.location.reload(); // Reload to refresh data
                }, 1500);
            } else {
                showFormMessage(data.message, false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showFormMessage('An error occurred. Please try again.', false);
        });
    });

    // Initial table load
    initializeDataTable();
});