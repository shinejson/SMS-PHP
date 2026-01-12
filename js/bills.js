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
const dueDateInput = document.getElementById('due_date');
const descriptionInput = document.getElementById('description');
const classIdSelect = document.getElementById('class_id');
const tuitionSection = document.getElementById('tuitionSection');
const tuitionSubFieldsContainer = document.getElementById('tuitionSubFields');
const simpleFieldGroup = document.getElementById('simpleFieldGroup');
const amountInput = document.getElementById('amount');

// Academic year input - get it when needed or use a function
function getAcademicYearInput() {
    return document.getElementById('academic_year_id');
}
// --- Initialize DataTable ---
let billingTable;

function initializeDataTable() {
    // Destroy existing instance if it exists
    if ($.fn.dataTable.isDataTable('#billingTable')) {
        $('#billingTable').DataTable().destroy();
        $('#billingTable').empty();
    }
    
    // Initialize DataTable
    window.billingTable = $('#billingTable').DataTable({
        responsive: true,
        dom: '<"top"fB>rt<"bottom"lip><"clear">',
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[0, 'desc']],
        buttons: [
            { 
                extend: 'copy', 
                className: 'btn-dt-copy',
                text: '<i class="fas fa-copy"></i> Copy'
            },
            { 
                extend: 'csv', 
                className: 'btn-dt-csv',
                text: '<i class="fas fa-file-csv"></i> CSV'
            },
            { 
                extend: 'excel', 
                className: 'btn-dt-excel',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            { 
                extend: 'pdf', 
                className: 'btn-dt-pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF'
            },
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
        columnDefs: [{ orderable: false, targets: [7] }],
        stateSave: true,
        drawCallback: function (settings) {
            bindActionButtons();
        },
        initComplete: function() {
            // Setup filters after a short delay to ensure complete initialization
            setTimeout(() => {
                setupFilters();
            }, 200);
        }
    });
    
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
    // Your existing code...
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

// In the validateForm() function, remove any validation that might affect the description
function validateForm() {
    const paymentType = paymentTypeSelect.value;
    const classId = classIdSelect.value;
    const academicYearInput = getAcademicYearInput();
    
    if (!academicYearInput) {
        showFormMessage('Academic year element not found', false);
        return false;
    }
    
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
    
    // Check academic year value
    if (!academicYearInput.value) {
        showFormMessage('Academic year is required', false);
        return false;
    }
    
    if (!dueDateInput.value) {
        showFormMessage('Due date is required', false);
        return false;
    }
    
    // Description is optional, so no validation needed
    
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
            console.log('API Response:', data);
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load billing data');
            }

            const billing = data.data;
            
            if (!billing) {
                throw new Error('No billing data received');
            }

            // Clear previous state
            billingForm.reset();
            tuitionSubFieldsContainer.innerHTML = '';

            // Populate form with null checks
            billingIdInput.value = billing.id || '';
            paymentTypeSelect.value = billing.payment_type || '';
            termIdSelect.value = billing.term_id || '';
            
            // Set academic year value safely
            const academicYearInput = getAcademicYearInput();
            if (academicYearInput) {
                academicYearInput.value = billing.academic_year_id || '';
            }
            
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
                    addTuitionSubField();
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
// --- Filter Functionality ---
function applyFilters() {
    if (!window.billingTable || !$.fn.DataTable.isDataTable('#billingTable')) {
        console.warn('DataTable not ready, retrying...');
        setTimeout(applyFilters, 100);
        return;
    }

    try {
        const academicYearFilter = document.getElementById('academicYearFilter')?.value || '';
        const paymentTypeFilter = document.getElementById('paymentTypeFilter')?.value || '';
        const classFilter = document.getElementById('classFilter')?.value || '';
        const termFilter = document.getElementById('termFilter')?.value || '';

        // Reset all searches first
        window.billingTable.columns().search('').draw();
        
        // Apply filters with exact matching
        if (academicYearFilter) {
            // Use exact matching for academic year ID (column 8 - hidden column)
            window.billingTable.column(8).search('^' + academicYearFilter + '$', true, false);
        }
        
        if (paymentTypeFilter) {
            // Use exact matching for payment type
            window.billingTable.column(0).search('^' + paymentTypeFilter + '$', true, false);
        }
        
        if (classFilter) {
            // Use exact matching for class name
            window.billingTable.column(5).search('^' + classFilter + '$', true, false);
        }
        
        if (termFilter) {
            // Use exact matching for term name
            window.billingTable.column(2).search('^' + termFilter + '$', true, false);
        }
        
        // Draw the table with all filters applied
        window.billingTable.draw();
    } catch (error) {
        console.error('Error applying filters:', error);
    }
}

function clearAllFilters() {
    const academicYearFilter = document.getElementById('academicYearFilter');
    const paymentTypeFilter = document.getElementById('paymentTypeFilter');
    const classFilter = document.getElementById('classFilter');
    const termFilter = document.getElementById('termFilter');
    
    if (academicYearFilter) academicYearFilter.value = '';
    if (paymentTypeFilter) paymentTypeFilter.value = '';
    if (classFilter) classFilter.value = '';
    if (termFilter) termFilter.value = '';
    
    // Clear all DataTable filters
    if (window.billingTable) {
        window.billingTable.columns().search('').draw();
    }
}

function setupFilters() {
    const academicYearFilter = document.getElementById('academicYearFilter');
    const paymentTypeFilter = document.getElementById('paymentTypeFilter');
    const classFilter = document.getElementById('classFilter');
    const termFilter = document.getElementById('termFilter');
    const clearFiltersBtn = document.getElementById('clearFilters');

    // Remove any existing event listeners to prevent duplicates
    if (academicYearFilter) {
        academicYearFilter.removeEventListener('change', applyFilters);
        academicYearFilter.addEventListener('change', applyFilters);
    }

    if (paymentTypeFilter) {
        paymentTypeFilter.removeEventListener('change', applyFilters);
        paymentTypeFilter.addEventListener('change', applyFilters);
    }

    if (classFilter) {
        classFilter.removeEventListener('change', applyFilters);
        classFilter.addEventListener('change', applyFilters);
    }

    if (termFilter) {
        termFilter.removeEventListener('change', applyFilters);
        termFilter.addEventListener('change', applyFilters);
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.removeEventListener('click', clearAllFilters);
        clearFiltersBtn.addEventListener('click', clearAllFilters);
    }
    
    // Apply initial filter if academic year is pre-selected
    const initialAcademicYear = academicYearFilter?.value;
    if (initialAcademicYear) {
        applyFilters();
    }
}
