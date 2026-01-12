document.addEventListener('DOMContentLoaded', function () {
    // ======= DATATABLE INITIALIZATION =======
    let paymentsTable;
    const paymentsTableElem = document.getElementById('paymentsTable');
    
    if (paymentsTableElem) {
        paymentsTable = $('#paymentsTable').DataTable({
            responsive: true,
            autoWidth: false,
            scrollX: true,
            scrollY: "500px",
            scrollCollapse: true,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            pageLength: 11,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            buttons: [
                { extend: 'copy', className: 'btn-dt-copy', text: '<i class="fas fa-copy"></i> Copy' },
                { extend: 'csv', className: 'btn-dt-csv', text: '<i class="fas fa-file-csv"></i> CSV' },
                { extend: 'excel', className: 'btn-dt-excel', text: '<i class="fas fa-file-excel"></i> Excel' },
                { extend: 'pdf', className: 'btn-dt-pdf', text: '<i class="fas fa-file-pdf"></i> PDF' },
                {
                    extend: 'print',
                    className: 'btn-dt-print',
                    text: '<i class="fas fa-print"></i> Print',
                    title: 'Payment Records',
                    customize: function (win) {
                        $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');
                    }
                }
            ],
            columnDefs: [
                { orderable: false, targets: [9], className: 'action-column' },
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: 1 }
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search payments...",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)"
            },
            drawCallback: function() {
                $('#paymentsTable tbody tr').each(function() {
                    const td = $(this).find('td.action-column');
                    if (td.length && !td.hasClass('wrapped')) {
                        const buttons = td.children('a, button');
                        td.empty().append(
                            $('<div class="action-buttons"></div>').append(buttons)
                        );
                        td.addClass('wrapped');
                    }
                });
                bindActionButtons();
            }
        });
    }

    // DataTable layout adjustment
    function adjustDataTableLayout() {
        if (paymentsTable) {
            setTimeout(function() {
                paymentsTable.columns.adjust().draw();
            }, 300);
        }
    }

    document.addEventListener('click', function(event) {
        if (event.target.closest('.sidebar-toggler-btn')) {
            adjustDataTableLayout();
        }
    });

    window.addEventListener('resize', adjustDataTableLayout);

    // ======= MODAL ELEMENTS (DECLARED ONCE) =======
    const modal = document.getElementById('paymentModal');
    const modalTitle = document.getElementById('modalTitle');
    const paymentForm = document.getElementById('paymentForm');
    const formAction = document.getElementById('formAction');
    const paymentId = document.getElementById('paymentId');
    
    // Form fields
    const studentSelect = document.getElementById('student_id');
    const paymentTypeSelect = document.getElementById('payment_type');
    const termSelect = document.getElementById('term_id');
    const academicYearSelect = document.getElementById('academic_year_id');
    const totalBillingAmountInput = document.getElementById('total_billing_amount');
    const amountField = document.getElementById('amount');
    const accountNumberField = document.getElementById('account_number_display');
    const accountContainer = document.getElementById('accountNumberContainer');
    
    // Total paid elements
    let totalPaidContainer = document.getElementById('totalPaidContainer');
    let totalPaidField = document.getElementById('total_paid');
    
    // Balance elements
    let balanceDueContainer = document.getElementById('balanceDueContainer');
    let balanceDueField = document.getElementById('balance_due');
    let validationMessage = document.getElementById('validationMessage');
    let paymentStatusDisplay = document.getElementById('paymentStatusDisplay');

    // Create total paid elements if they don't exist
    if (!totalPaidContainer && studentSelect) {
        totalPaidContainer = document.createElement('div');
        totalPaidContainer.id = 'totalPaidContainer';
        totalPaidContainer.className = 'total-paid-container';
        totalPaidContainer.style.display = 'none';
        
        totalPaidField = document.createElement('input');
        totalPaidField.id = 'total_paid';
        totalPaidField.type = 'text';
        totalPaidField.readOnly = true;
        totalPaidField.className = 'total-paid-field';
        
        totalPaidContainer.appendChild(totalPaidField);
        studentSelect.parentNode.appendChild(totalPaidContainer);
    }
    
    // Create balance due elements if they don't exist
    if (!balanceDueContainer && amountField) {
        balanceDueContainer = document.createElement('div');
        balanceDueContainer.id = 'balanceDueContainer';
        balanceDueContainer.className = 'balance-due-container';
        balanceDueContainer.style.display = 'none';

        balanceDueField = document.createElement('input');
        balanceDueField.id = 'balance_due';
        balanceDueField.type = 'text';
        balanceDueField.readOnly = true;
        balanceDueField.className = 'balance-due-field';
        
        validationMessage = document.createElement('div');
        validationMessage.id = 'validationMessage';
        validationMessage.className = 'validation-message text-danger';
        
        balanceDueContainer.appendChild(balanceDueField);
        balanceDueContainer.appendChild(validationMessage);
        
        amountField.parentNode.insertBefore(balanceDueContainer, amountField.nextSibling);
    }

    // Create payment status display if it doesn't exist
    if (!paymentStatusDisplay && amountField) {
        paymentStatusDisplay = document.createElement('div');
        paymentStatusDisplay.id = 'paymentStatusDisplay';
        paymentStatusDisplay.className = 'form-group';
        paymentStatusDisplay.innerHTML = '<label>Payment Status</label><span id="statusBadge" class="badge"></span>';
        amountField.parentNode.insertBefore(paymentStatusDisplay, amountField);
        paymentStatusDisplay.style.display = 'none';
    }

    // ======= FILTER FUNCTIONALITY =======
    function setupFilters() {
        const classFilter = document.getElementById('classFilter');
        const studentFilter = document.getElementById('studentFilter');
        const termFilter = document.getElementById('termFilter');
        const yearFilter = document.getElementById('yearFilter');
        const statusFilter = document.getElementById('statusFilter');
        const clearFiltersBtn = document.getElementById('clearFilters');

        if (classFilter) classFilter.addEventListener('change', applyFilters);
        if (studentFilter) studentFilter.addEventListener('change', applyFilters);
        if (termFilter) termFilter.addEventListener('change', applyFilters);
        if (yearFilter) yearFilter.addEventListener('change', applyFilters);
        if (statusFilter) statusFilter.addEventListener('change', applyFilters);

        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                if (classFilter) classFilter.value = '';
                if (studentFilter) studentFilter.value = '';
                if (termFilter) termFilter.value = '';
                if (yearFilter) yearFilter.value = '';
                if (statusFilter) statusFilter.value = '';
                applyFilters();
            });
        }
    }

    function applyFilters() {
        const classFilter = document.getElementById('classFilter')?.value || '';
        const studentFilter = document.getElementById('studentFilter')?.value || '';
        const termFilter = document.getElementById('termFilter')?.value || '';
        const yearFilter = document.getElementById('yearFilter')?.value || '';
        const statusFilter = document.getElementById('statusFilter')?.value || '';

        paymentsTable.columns().search('').draw();
        
        if (classFilter) paymentsTable.column(2).search(classFilter, true, false);
        if (studentFilter) paymentsTable.column(1).search(studentFilter, true, false);
        if (termFilter) paymentsTable.column(4).search(termFilter, true, false);
        if (yearFilter) paymentsTable.column(5).search(yearFilter, true, false);
        if (statusFilter) paymentsTable.column(8).search(statusFilter, true, false);
        
        paymentsTable.draw();
    }

    // ======= ACCOUNT NUMBER FETCH =======
    function fetchAccountNumber() {
        const studentId = studentSelect?.value;
        const paymentType = paymentTypeSelect?.value;
        
        if (!studentId || !paymentType || !accountContainer || !accountNumberField) {
            if (accountContainer) accountContainer.style.display = 'none';
            return;
        }
        
        accountNumberField.value = 'Loading account information...';
        accountContainer.style.display = 'block';
        
        fetch(`get_student_account.php?student_id=${studentId}&payment_type=${encodeURIComponent(paymentType)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.account_number) {
                    const balance = parseFloat(data.balance);
                    accountNumberField.value = `${data.account_number} | Balance: GHC ${balance.toFixed(2)} | Type: ${data.account_type}`;
                    
                    if (balance < 0) {
                        accountNumberField.style.backgroundColor = '#fee2e2';
                        accountNumberField.style.color = '#991b1b';
                        accountNumberField.style.borderColor = '#dc2626';
                    } else if (balance === 0) {
                        accountNumberField.style.backgroundColor = '#fef3c7';
                        accountNumberField.style.color = '#92400e';
                        accountNumberField.style.borderColor = '#f59e0b';
                    } else {
                        accountNumberField.style.backgroundColor = '#d1fae5';
                        accountNumberField.style.color = '#065f46';
                        accountNumberField.style.borderColor = '#059669';
                    }
                } else {
                    accountNumberField.value = data.message || 'No account linked for this payment type';
                    accountNumberField.style.backgroundColor = '#f3f4f6';
                    accountNumberField.style.color = '#6b7280';
                    accountNumberField.style.borderColor = '#d1d5db';
                }
            })
            .catch(error => {
                console.error('Error fetching account:', error);
                accountNumberField.value = 'Error loading account information';
                accountNumberField.style.backgroundColor = '#fee2e2';
                accountNumberField.style.color = '#991b1b';
            });
    }

    // ======= CALCULATION FUNCTIONS =======
    let fetchedData = { totalPaid: 0, billingAmount: 0 };
    
    function calculateAndDisplayBalance() {
        const balance = fetchedData.billingAmount - fetchedData.totalPaid;
        
        if (balanceDueField && balanceDueContainer) {
            balanceDueField.value = `Balance Due: GHC ${balance.toFixed(2)}`;
            balanceDueContainer.style.display = 'block';
            if (balance < 0) {
                balanceDueField.classList.add('overpaid');
            } else {
                balanceDueField.classList.remove('overpaid');
            }
        }
        
        updatePaymentStatus(fetchedData.totalPaid, fetchedData.billingAmount);
    }
    
    function validatePaymentAmount() {
        const amount = parseFloat(amountField.value);
        const balance = fetchedData.billingAmount - fetchedData.totalPaid;
        
        if (validationMessage) {
            if (amount > balance && balance >= 0) {
                validationMessage.textContent = 'Warning: Amount exceeds remaining balance.';
            } else if (amount <= 0 || isNaN(amount)) {
                validationMessage.textContent = 'Amount must be a positive number.';
            } else {
                validationMessage.textContent = '';
            }
        }
    }
    
    function updatePaymentStatus(totalPaid, billingAmount) {
        const statusBadge = document.getElementById('statusBadge');
        if (!statusBadge || !paymentStatusDisplay) return;

        paymentStatusDisplay.style.display = 'block';
        statusBadge.className = 'badge';
        
        if (totalPaid >= billingAmount && billingAmount > 0) {
            statusBadge.textContent = 'Full';
            statusBadge.classList.add('badge-success');
        } else if (totalPaid > 0 && totalPaid < billingAmount) {
            statusBadge.textContent = 'Part';
            statusBadge.classList.add('badge-warning');
        } else {
            statusBadge.textContent = 'Not Paid';
            statusBadge.classList.add('badge-danger');
        }
    }

    // ======= FETCH TOTAL PAID =======
    async function fetchTotalPaid(studentId, paymentType, termId, academicYearId) {
        if (!studentId || !paymentType || !termId || !academicYearId) {
            hideTotalPaid();
            fetchedData.totalPaid = 0;
            calculateAndDisplayBalance();
            return;
        }

        try {
            const response = await fetch(`get_total_paid.php?student_id=${studentId}&payment_type=${encodeURIComponent(paymentType)}&term_id=${encodeURIComponent(termId)}&academic_year_id=${encodeURIComponent(academicYearId)}`);
            const data = await response.json();

            if (data.success) {
                const totalPaid = parseFloat(data.total_paid || 0);
                showTotalPaid(totalPaid);
                fetchedData.totalPaid = totalPaid;
                calculateAndDisplayBalance();
            } else {
                showTotalPaidError('Error fetching total');
                fetchedData.totalPaid = 0;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showTotalPaidError('Network error');
            fetchedData.totalPaid = 0;
        }
    }

    function showTotalPaid(amount) {
        if (totalPaidField && totalPaidContainer) {
            totalPaidField.value = `Total Previously Paid: GHC ${amount.toFixed(2)}`;
            totalPaidContainer.style.display = 'block';
        }
    }

    function showTotalPaidError(message) {
        if (totalPaidField && totalPaidContainer) {
            totalPaidField.value = message;
            totalPaidContainer.style.display = 'block';
        }
    }

    function hideTotalPaid() {
        if (totalPaidContainer) {
            totalPaidContainer.style.display = 'none';
        }
    }

    // ======= BILLING AMOUNT FETCH =======
    async function fetchBillingAmount() {
        const studentId = studentSelect?.value;
        const payment_type = paymentTypeSelect?.value;
        const term_id = termSelect?.value;
        const academic_year_id = academicYearSelect?.value;
        const class_id = studentSelect?.selectedOptions[0]?.getAttribute('data-class-id');

        if (!studentId || !payment_type || !class_id || !term_id || !academic_year_id) {
            if (totalBillingAmountInput) totalBillingAmountInput.style.display = 'none';
            if (balanceDueContainer) balanceDueContainer.style.display = 'none';
            if (paymentStatusDisplay) paymentStatusDisplay.style.display = 'none';
            fetchedData.billingAmount = 0;
            calculateAndDisplayBalance();
            return;
        }

        try {
            const res = await fetch(
                `get_billing_amount.php?payment_type=${encodeURIComponent(payment_type)}&class_id=${encodeURIComponent(class_id)}&term_id=${encodeURIComponent(term_id)}&academic_year_id=${encodeURIComponent(academic_year_id)}`
            );
            const data = await res.json();

            if (data.success && data.amount > 0) {
                if (totalBillingAmountInput) {
                    totalBillingAmountInput.value = `Expected: GHC ${parseFloat(data.amount).toFixed(2)}`;
                    totalBillingAmountInput.style.display = 'block';
                }
                fetchedData.billingAmount = parseFloat(data.amount);
                if (amountField) amountField.value = data.amount;
            } else {
                if (totalBillingAmountInput) {
                    totalBillingAmountInput.value = 'No billing record found';
                    totalBillingAmountInput.style.display = 'block';
                }
                fetchedData.billingAmount = 0;
                if (amountField) amountField.value = '';
            }
            fetchTotalPaid(studentId, payment_type, term_id, academic_year_id);
        } catch (err) {
            console.error('Error fetching billing:', err);
            if (totalBillingAmountInput) {
                totalBillingAmountInput.value = 'Error fetching amount';
                totalBillingAmountInput.style.display = 'block';
            }
            fetchedData.billingAmount = 0;
            calculateAndDisplayBalance();
        }
    }

    // ======= STUDENT FILTERING =======
    function setupStudentFiltering() {
        const classSelect = document.getElementById('class_id');
        
        if (classSelect && studentSelect) {
            $(studentSelect).select2({
                placeholder: "Select Student",
                allowClear: true,
                width: '100%'
            });
            
            classSelect.addEventListener('change', function() {
                filterStudentsByClass(this.value);
            });
            
            if (classSelect.value) {
                filterStudentsByClass(classSelect.value);
            }
        }
    }
    
    function filterStudentsByClass(classId, preselectedStudentId = null) {
        while (studentSelect.options.length > 1) {
            studentSelect.remove(1);
        }
        
        if (!classId) {
            window.allStudents.forEach(student => addStudentOption(student));
            if (preselectedStudentId) studentSelect.value = preselectedStudentId;
            if (typeof $(studentSelect).select2 !== 'undefined') {
                $(studentSelect).trigger('change');
            }
            return;
        }
        
        const filteredStudents = window.allStudents.filter(student => 
            student.class_id == classId
        );
        
        filteredStudents.forEach(student => addStudentOption(student));
        
        if (preselectedStudentId) {
            studentSelect.value = preselectedStudentId;
        }
        
        $(studentSelect).trigger('change');
    }
    
    function addStudentOption(student) {
        const option = document.createElement('option');
        option.value = student.id;
        option.textContent = `${student.first_name} ${student.last_name} (${student.student_id})`;
        option.setAttribute('data-class-id', student.class_id);
        studentSelect.appendChild(option);
    }
    
    window.filterStudentsByClass = filterStudentsByClass;

    // ======= MODAL FUNCTIONS =======
    function closeModal() {
        if (!modal || !paymentForm) return;

        modal.style.display = 'none';
        paymentForm.reset();
        hideTotalPaid();

        if (totalBillingAmountInput) {
            totalBillingAmountInput.value = '';
            totalBillingAmountInput.style.display = 'none';
        }
        if (balanceDueContainer) balanceDueContainer.style.display = 'none';
        if (paymentStatusDisplay) paymentStatusDisplay.style.display = 'none';
        if (validationMessage) validationMessage.textContent = '';
        if (accountContainer) accountContainer.style.display = 'none';
        if (accountNumberField) accountNumberField.value = '';
    }

    // ======= ADD PAYMENT =======
    const addPaymentBtn = document.getElementById('addPaymentBtn');
    if (addPaymentBtn) {
        addPaymentBtn.addEventListener('click', function () {
            if (!modal || !paymentForm) return;

            modalTitle.textContent = 'Record New Payment';
            formAction.value = 'add_payment';
            paymentId.value = '';
            paymentForm.reset();

            hideTotalPaid();
            if (totalBillingAmountInput) totalBillingAmountInput.style.display = 'none';
            if (balanceDueContainer) balanceDueContainer.style.display = 'none';
            if (paymentStatusDisplay) paymentStatusDisplay.style.display = 'none';
            if (validationMessage) validationMessage.textContent = '';
            if (accountContainer) accountContainer.style.display = 'none';

            const paymentDate = document.getElementById('payment_date');
            if (paymentDate) {
                const today = new Date();
                paymentDate.value = today.toISOString().split('T')[0];
            }

            modal.style.display = 'block';
        });
    }

    // ======= EDIT PAYMENT =======
    function editPayment(id) {
        if (!modal) return;

        const payment = window.allPaymentsData.find(p => p.id == id);
        if (!payment) {
            alert('Payment data not found');
            return;
        }

        modalTitle.textContent = 'Edit Payment';
        formAction.value = 'update_payment';
        paymentId.value = id;

        const classSelect = document.getElementById('class_id');
        if (classSelect) {
            const student = window.allStudents.find(s => s.id == payment.student_id);
            if (student && student.class_id) {
                classSelect.value = student.class_id;
                
                if (typeof window.filterStudentsByClass === 'function') {
                    window.filterStudentsByClass(student.class_id, payment.student_id);
                } else {
                    const event = new Event('change');
                    classSelect.dispatchEvent(event);
                    setTimeout(() => {
                        if (studentSelect) studentSelect.value = payment.student_id;
                    }, 100);
                }
            }
        }

        if (document.getElementById('payment_date')) {
            document.getElementById('payment_date').value = payment.payment_date.split(' ')[0];
        }
        if (termSelect) termSelect.value = payment.term_id;
        if (academicYearSelect) academicYearSelect.value = payment.academic_year_id;
        if (paymentTypeSelect) paymentTypeSelect.value = payment.payment_type;
        if (amountField) amountField.value = payment.amount;
        if (document.getElementById('payment_method')) {
            document.getElementById('payment_method').value = payment.payment_method;
        }
        if (document.getElementById('description')) {
            document.getElementById('description').value = payment.description || '';
        }

        modal.style.display = 'block';

        setTimeout(() => {
            fetchBillingAmount();
        }, 200);
    }

    // ======= DELETE PAYMENT =======
    function deletePayment(id) {
        const payment = window.allPaymentsData.find(p => p.id == id);
        if (!payment) return;

        document.getElementById('deleteId').value = id;
        document.getElementById('deleteMessage').textContent =
            `Are you sure you want to delete payment ${payment.receipt_no}?`;

        const deleteModal = document.getElementById('deleteModal');
        deleteModal.style.display = 'block';
    }

    // ======= EVENT BINDING =======
    function bindActionButtons() {
        $(document).off('click.payments');
        
        $(document).on('click.payments', '.edit-payment', function() {
            const id = this.getAttribute('data-id');
            editPayment(id);
        });
        
        $(document).on('click.payments', '.delete-payment', function() {
            const id = this.getAttribute('data-id');
            deletePayment(id);
        });
    }

    // ======= EVENT LISTENERS =======
    if (studentSelect) {
        studentSelect.addEventListener('change', function() {
            fetchBillingAmount();
            fetchAccountNumber();
        });
    }

    if (paymentTypeSelect) {
        paymentTypeSelect.addEventListener('change', function() {
            fetchBillingAmount();
            fetchAccountNumber();
        });
    }

    if (termSelect) termSelect.addEventListener('change', fetchBillingAmount);
    if (academicYearSelect) academicYearSelect.addEventListener('change', fetchBillingAmount);
    if (amountField) amountField.addEventListener('input', validatePaymentAmount);

    // ======= MODAL CLOSE EVENTS =======
  // ======= MODAL CLOSE EVENTS =======
// Close button (X) for payment modal
const closeButtons = document.querySelectorAll('.modal .close');
closeButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        const modal = this.closest('.modal');
        if (modal && modal.id) {
            closeModal(modal.id);
        } else {
            closeModal(); // Default
        }
    });
});

// Cancel buttons for payment modal
const cancelButtons = document.querySelectorAll('.modal .btn-cancel');
cancelButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        const modal = this.closest('.modal');
        if (modal && modal.id) {
            closeModal(modal.id);
        } else {
            closeModal(); // Default
        }
    });
});

// Click outside modal
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});

    // Delete modal close events
   // Delete modal close events
const deleteModal = document.getElementById('deleteModal');
if (deleteModal) {
    const deleteClose = deleteModal.querySelector('.close');
    const deleteCancel = deleteModal.querySelector('.btn-cancel');
    
    if (deleteClose) {
        deleteClose.addEventListener('click', function() {
            closeModal('deleteModal');
        });
    }
    
    if (deleteCancel) {
        deleteCancel.addEventListener('click', function() {
            closeModal('deleteModal');
        });
    }
}

    // ======= INITIALIZATION =======
    bindActionButtons();
    setupFilters();
    setupStudentFiltering();

    // Make functions globally available
    window.editPayment = editPayment;
    window.deletePayment = deletePayment;
    window.fetchTotalPaid = fetchTotalPaid;
    window.fetchBillingAmount = fetchBillingAmount;
    window.closeModal = closeModal;
});