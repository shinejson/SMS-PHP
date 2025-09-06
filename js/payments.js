document.addEventListener('DOMContentLoaded', function () {
    // ======= DATATABLE INITIALIZATION =======
    let paymentsTable;
    const paymentsTableElem = document.getElementById('paymentsTable');
    
    if (paymentsTableElem) {
        paymentsTable = $('#paymentsTable').DataTable({
            responsive: true,
            autoWidth: false,
            dom: '<"top"fB>rt<"bottom"lip><"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            buttons: [
                { extend: 'copy', className: 'btn-dt-copy' },
                { extend: 'csv', className: 'btn-dt-csv' },
                { extend: 'excel', className: 'btn-dt-excel' },
                { extend: 'pdf', className: 'btn-dt-pdf' },
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
                { orderable: false, targets: [7], className: 'action-column' },
                { responsivePriority: 1, targets: 0 },
                { responsivePriority: 2, targets: 1 }
            ],
            drawCallback: function() {
                // Rebind event handlers after each draw (pagination, search, etc.)
                bindActionButtons();
            }
        });
    }

    // ======= MODAL ELEMENTS =======
    const modal = document.getElementById('paymentModal');
    const modalTitle = document.getElementById('modalTitle');
    const paymentForm = document.getElementById('paymentForm');
    const formAction = document.getElementById('formAction');
    const paymentId = document.getElementById('paymentId');

    // Form fields
    const studentSelect = document.getElementById('student_id');
    const paymentTypeSelect = document.getElementById('payment_type');
    const termSelect = document.getElementById('term_id');
    const totalBillingAmountInput = document.getElementById('total_billing_amount');
    const amountField = document.getElementById('amount');
    
    // Total paid elements - we'll create them if they don't exist
    let totalPaidContainer = document.getElementById('totalPaidContainer');
    let totalPaidField = document.getElementById('total_paid');
    
    // Elements for total balance and validation
    let balanceDueContainer = document.getElementById('balanceDueContainer');
    let balanceDueField = document.getElementById('balance_due');
    let validationMessage = document.getElementById('validationMessage');

    // NEW: Payment status display element
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
    
    // Create balance due and validation message elements if they don't exist
    if (!balanceDueContainer && studentSelect) {
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
        
        // Insert the new fields after the amount field
        amountField.parentNode.insertBefore(balanceDueContainer, amountField.nextSibling);
    }

    // NEW: Create and insert the payment status element
    if (!paymentStatusDisplay && amountField) {
        paymentStatusDisplay = document.createElement('div');
        paymentStatusDisplay.id = 'paymentStatusDisplay';
        paymentStatusDisplay.className = 'form-group';
        paymentStatusDisplay.innerHTML = '<label>Payment Status</label><span id="statusBadge" class="badge"></span>';
        amountField.parentNode.insertBefore(paymentStatusDisplay, amountField);
        paymentStatusDisplay.style.display = 'none';
    }

    // Show modal if there's an error
    if (modal && document.querySelector('.alert-danger')) {
        modal.style.display = 'block';
    }

    // ======= EVENT BINDING FUNCTIONS =======
    function bindActionButtons() {
        // Remove existing event listeners to prevent duplicates
        $(document).off('click.payments');
        
        // Use event delegation for dynamic content
        $(document).on('click.payments', '.edit-payment', function() {
            const id = this.getAttribute('data-id');
            editPayment(id);
        });
        
        $(document).on('click.payments', '.delete-payment', function() {
            const id = this.getAttribute('data-id');
            deletePayment(id);
        });
    }

    // ======= NEW LOGIC: CALCULATION AND DISPLAY FUNCTIONS =======

    // A helper function to manage fetched data and trigger balance calculation
    let fetchedData = { totalPaid: 0, billingAmount: 0 };
    
    // Calculates and displays the remaining balance
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
        
        // NEW: Call the function to update the status after calculations
        updatePaymentStatus(fetchedData.totalPaid, fetchedData.billingAmount);
    }
    
    // Validates the entered amount against the balance due
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
    
    // NEW: Function to update the payment status badge
    function updatePaymentStatus(totalPaid, billingAmount) {
        const statusBadge = document.getElementById('statusBadge');
        if (!statusBadge || !paymentStatusDisplay) return;

        paymentStatusDisplay.style.display = 'block';
        statusBadge.className = 'badge'; // Reset classes
        
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

    // ======= FETCH TOTAL PAID AMOUNT =======
    // UPDATED: Now takes paymentType and termId as arguments to filter the sum
    async function fetchTotalPaid(studentId, paymentType, termId) {
        if (!studentId || !paymentType || !termId) {
            hideTotalPaid();
            fetchedData.totalPaid = 0; // Reset data
            calculateAndDisplayBalance();
            return;
        }

        console.log('Fetching total paid for student:', studentId); // Debug log

        try {
            // UPDATED: Added payment_type and term_id to the fetch URL
            const response = await fetch(`get_total_paid.php?student_id=${studentId}&payment_type=${encodeURIComponent(paymentType)}&term_id=${encodeURIComponent(termId)}`);
            const data = await response.json();

            console.log('Total paid API response:', data); // Debug log

            if (data.success) {
                const totalPaid = parseFloat(data.total_paid || 0);
                showTotalPaid(totalPaid);
                fetchedData.totalPaid = totalPaid; // Store fetched value
                calculateAndDisplayBalance();
            } else {
                console.error('API error:', data.error);
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
            console.log('Showing total paid:', amount); // Debug log
        } else {
            console.error('Total paid elements not found');
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

    // ======= BILLING AMOUNT AUTO-FETCH =======
    async function fetchBillingAmount() {
        const studentId = studentSelect?.value;
        const payment_type = paymentTypeSelect?.value;
        const term_id = termSelect?.value;
        const class_id = studentSelect?.selectedOptions[0]?.getAttribute('data-class-id');

        if (!studentId || !payment_type || !class_id || !term_id) {
            if (totalBillingAmountInput) totalBillingAmountInput.style.display = 'none';
            if (balanceDueContainer) balanceDueContainer.style.display = 'none';
            if (paymentStatusDisplay) paymentStatusDisplay.style.display = 'none';
            fetchedData.billingAmount = 0; // Reset data
            calculateAndDisplayBalance();
            return;
        }

        try {
            const res = await fetch(
                `get_billing_amount.php?payment_type=${encodeURIComponent(payment_type)}&class_id=${encodeURIComponent(class_id)}&term_id=${encodeURIComponent(term_id)}`
            );
            const data = await res.json();

            if (data.success && data.amount > 0) {
                if (totalBillingAmountInput) {
                    totalBillingAmountInput.value = `Expected: GHC ${parseFloat(data.amount).toFixed(2)}`;
                    totalBillingAmountInput.style.display = 'block';
                }
                fetchedData.billingAmount = parseFloat(data.amount); // Store fetched value
                if (amountField) amountField.value = data.amount; // Set the default amount to the full billing
            } else {
                if (totalBillingAmountInput) {
                    totalBillingAmountInput.value = 'No billing record found';
                    totalBillingAmountInput.style.display = 'block';
                }
                fetchedData.billingAmount = 0;
                if (amountField) amountField.value = '';
            }
            // UPDATED: Now call fetchTotalPaid after fetching billing, so both values are ready
            fetchTotalPaid(studentId, payment_type, term_id);
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

    // ======= ADD PAYMENT =======
    const addPaymentBtn = document.getElementById('addPaymentBtn');
    if (addPaymentBtn) {
        addPaymentBtn.addEventListener('click', function () {
            if (!modal || !paymentForm) return;

            modalTitle.textContent = 'Record New Payment';
            formAction.value = 'add_payment';
            paymentId.value = '';
            paymentForm.reset();

            // Hide all helper fields on new entry
            hideTotalPaid();
            if (totalBillingAmountInput) totalBillingAmountInput.style.display = 'none';
            if (balanceDueContainer) balanceDueContainer.style.display = 'none';
            if (paymentStatusDisplay) paymentStatusDisplay.style.display = 'none';
            if (validationMessage) validationMessage.textContent = '';


            const paymentDate = document.getElementById('payment_date');
            if (paymentDate) {
                const today = new Date();
                paymentDate.value = today.toISOString().split('T')[0];
            }

            modal.style.display = 'block';
        });
    }

    // ======= CLOSE MODAL =======
    function closeModal() {
        if (!modal || !paymentForm) return;

        modal.style.display = 'none';
        paymentForm.reset();
        hideTotalPaid();

        if (totalBillingAmountInput) {
            totalBillingAmountInput.value = '';
            totalBillingAmountInput.style.display = 'none';
        }
        if (balanceDueContainer) {
            balanceDueContainer.style.display = 'none';
        }
        if (paymentStatusDisplay) {
            paymentStatusDisplay.style.display = 'none';
        }
        if (validationMessage) {
            validationMessage.textContent = '';
        }
    }

    document.querySelector('.close')?.addEventListener('click', closeModal);
    document.querySelector('.btn-cancel')?.addEventListener('click', closeModal);
    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    // ======= EDIT PAYMENT =======
    function editPayment(id) {
        if (!modal) return;

        const payment = window.allPaymentsData.find(p => p.id == id);
        if (!payment) {
            // Replaced alert() with a message box for better UX in an iframe
            const messageBox = document.createElement('div');
            messageBox.className = 'custom-alert';
            messageBox.textContent = 'Payment data not found';
            document.body.appendChild(messageBox);
            setTimeout(() => {
                document.body.removeChild(messageBox);
            }, 2000);
            return;
        }

        modalTitle.textContent = 'Edit Payment';
        formAction.value = 'update_payment';
        paymentId.value = id;

        // Populate form fields
        if (studentSelect) studentSelect.value = payment.student_id;
        if (document.getElementById('payment_date')) {
            document.getElementById('payment_date').value = payment.payment_date.split(' ')[0];
        }
        if (termSelect) termSelect.value = payment.term_id;
        if (paymentTypeSelect) paymentTypeSelect.value = payment.payment_type;
        if (amountField) amountField.value = payment.amount;
        if (document.getElementById('payment_method')) {
            document.getElementById('payment_method').value = payment.payment_method;
        }
        if (document.getElementById('description')) {
            document.getElementById('description').value = payment.description || '';
        }

        modal.style.display = 'block';

        // Fetch total paid and billing amount after setting fields
        // UPDATED: Call fetchBillingAmount first, which will in turn call fetchTotalPaid
        fetchBillingAmount();
    }

    // ======= DELETE PAYMENT =======
function deletePayment(id) {
    const payment = window.allPaymentsData.find(p => p.id == id);
    if (!payment) return;

    // Fill modal with payment details
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteMessage').textContent =
        `Are you sure you want to delete payment ${payment.receipt_no}?`;

    // Show modal
    const deleteModal = document.getElementById('deleteModal');
    deleteModal.style.display = 'block';

    // Close handlers
    deleteModal.querySelector('.close').onclick = () => deleteModal.style.display = 'none';
    deleteModal.querySelector('.btn-cancel').onclick = () => deleteModal.style.display = 'none';
    window.onclick = (event) => {
        if (event.target === deleteModal) deleteModal.style.display = 'none';
    };
}



    // ======= EVENT LISTENERS FOR FORM FIELDS =======
    if (studentSelect) {
        studentSelect.addEventListener('change', function() {
            // UPDATED: Now calls fetchBillingAmount which handles the chained fetches
            fetchBillingAmount();
        });
    }

    if (paymentTypeSelect) {
        paymentTypeSelect.addEventListener('change', fetchBillingAmount);
    }

    if (termSelect) {
        termSelect.addEventListener('change', fetchBillingAmount);
    }
    
    // Listen for changes on the amount field to validate
    if (amountField) {
        amountField.addEventListener('input', validatePaymentAmount);
    }

    // ======= INITIAL BINDING =======
    bindActionButtons();

    // Make functions globally available
    window.editPayment = editPayment;
    window.deletePayment = deletePayment;
    window.fetchTotalPaid = fetchTotalPaid;
    window.fetchBillingAmount = fetchBillingAmount;
    window.closeModal = closeModal;
});
