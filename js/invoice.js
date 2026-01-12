

// Enhanced JavaScript functionality
let selectedInvoices = new Set();

function openCreateModal() {
    document.getElementById('createInvoiceModal').style.display = 'flex';
    // Set minimum due date to today
    const dueDateInput = document.getElementById('due_date');
    dueDateInput.min = new Date().toISOString().split('T')[0];
    updateDueDateHelp();
}

function closeModal() {
    document.getElementById('createInvoiceModal').style.display = 'none';
    document.getElementById('createInvoiceForm').reset();
}

function updateStatus(invoiceId, currentStatus) {
    document.getElementById('status_invoice_id').value = invoiceId;
    document.getElementById('status').value = currentStatus;
    
    // Show/hide payment date field
    const paymentDateField = document.getElementById('paymentDateField');
    paymentDateField.style.display = currentStatus === 'paid' ? 'block' : 'none';
    
    document.getElementById('statusModal').style.display = 'flex';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function updateDueDateHelp() {
    const dueDateInput = document.getElementById('due_date');
    const helpText = document.getElementById('dueDateHelp');
    
    if (dueDateInput.value) {
        const dueDate = new Date(dueDateInput.value);
        const today = new Date();
        const diffTime = dueDate - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 0) {
            helpText.textContent = 'Date is in the past';
            helpText.style.color = 'red';
        } else if (diffDays === 0) {
            helpText.textContent = 'Due today';
            helpText.style.color = 'orange';
        } else {
            helpText.textContent = `Due in ${diffDays} day${diffDays !== 1 ? 's' : ''}`;
            helpText.style.color = 'green';
        }
    } else {
        helpText.textContent = '';
    }
}

function exportInvoices() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    
    // Redirect to export script with current filters
    window.location.href = 'export_invoices.php?' + params.toString();
}

function viewInvoice(invoiceId) {
    window.open('view_invoice.php?id=' + invoiceId, '_blank');
}

function printInvoice(invoiceId) {
    const printWindow = window.open('print_invoice.php?id=' + invoiceId, '_blank');
    printWindow.onload = function() {
        printWindow.print();
    };
}

function deleteInvoice(invoiceId) {
    if (confirm('Are you sure you want to cancel this invoice? This action cannot be undone.')) {
        // AJAX request to delete invoice
        fetch('ajax/delete_invoice.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ invoice_id: invoiceId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success('Invoice cancelled successfully');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error('Error: ' + data.message);
            }
        })
        .catch(error => {
            toastr.error('Network error occurred');
        });
    }
}

// Bulk selection functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
        if (this.checked) {
            selectedInvoices.add(parseInt(checkbox.value));
        } else {
            selectedInvoices.delete(parseInt(checkbox.value));
        }
    });
    updateBulkActions();
});

document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const invoiceId = parseInt(this.value);
        if (this.checked) {
            selectedInvoices.add(invoiceId);
        } else {
            selectedInvoices.delete(invoiceId);
            document.getElementById('selectAll').checked = false;
        }
        updateBulkActions();
    });
});

function updateBulkActions() {
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    selectedCount.textContent = selectedInvoices.size;
    
    if (selectedInvoices.size > 0) {
        bulkActions.style.display = 'flex';
    } else {
        bulkActions.style.display = 'none';
    }
}

function clearSelection() {
    selectedInvoices.clear();
    document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAll').checked = false;
    updateBulkActions();
}

// Status change handler
document.getElementById('status').addEventListener('change', function() {
    const paymentDateField = document.getElementById('paymentDateField');
    paymentDateField.style.display = this.value === 'paid' ? 'block' : 'none';
});

// Form validation
document.getElementById('createInvoiceForm').addEventListener('submit', function(e) {
    const amount = parseFloat(document.getElementById('amount').value);
    const dueDate = new Date(document.getElementById('due_date').value);
    const today = new Date();
    
    if (amount <= 0) {
        e.preventDefault();
        toastr.error('Amount must be greater than zero');
        return;
    }
    
    if (dueDate < today.setHours(0,0,0,0)) {
        e.preventDefault();
        toastr.error('Due date cannot be in the past');
        return;
    }
});

// Initialize date help
document.getElementById('due_date').addEventListener('change', updateDueDateHelp);

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const createModal = document.getElementById('createInvoiceModal');
    const statusModal = document.getElementById('statusModal');
    
    if (e.target === createModal) {
        closeModal();
    }
    if (e.target === statusModal) {
        closeStatusModal();
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        openCreateModal();
    }
    
    if (e.key === 'Escape') {
        closeModal();
        closeStatusModal();
    }
});

function deleteInvoice(invoiceId, invoiceNumber) {
    if (!confirm('Are you sure you want to delete invoice #' + invoiceNumber + '?')) {
        return;
    }

    const $deleteBtn = $(event.target);
    const originalHtml = $deleteBtn.html();
    $deleteBtn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...').prop('disabled', true);

    $.ajax({
        url: 'delete_invoice.php',
        type: 'POST',
        dataType: 'json',
        data: JSON.stringify({ invoice_id: invoiceId }),
        contentType: 'application/json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(data) {
            if (data.success) {
                toastr.success(data.message);
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                toastr.error(data.message);
                $deleteBtn.html(originalHtml).prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            toastr.error('Error deleting invoice');
            $deleteBtn.html(originalHtml).prop('disabled', false);
        }
    });
}

// Alternative version using jQuery (if you prefer)
function deleteInvoiceJQuery(invoiceId, invoiceNumber) {
    if (!confirm('Are you sure you want to delete invoice #' + invoiceNumber + '?')) {
        return;
    }

    const $deleteBtn = $(event.target);
    const originalHtml = $deleteBtn.html();
    
    $deleteBtn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...').prop('disabled', true);

    $.ajax({
        url: 'delete_invoice.php',
        method: 'POST',
        dataType: 'json',
        data: JSON.stringify({ invoice_id: invoiceId }),
        contentType: 'application/json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .done(function(data) {
        if (data.success) {
            toastr.success(data.message);
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            toastr.error(data.message);
            $deleteBtn.html(originalHtml).prop('disabled', false);
        }
    })
    .fail(function(xhr, status, error) {
        console.error('Error:', error);
        toastr.error('Error deleting invoice: ' + error);
        $deleteBtn.html(originalHtml).prop('disabled', false);
    });
}
// Enhanced edit invoice function with paid invoice check
function editInvoice(invoiceId, currentStatus) {
    if (currentStatus === 'paid') {
        if (!confirm('This invoice is already paid. Are you sure you want to edit it?')) {
            return;
        }
    }
    window.location.href = 'edit_invoice.php?id=' + invoiceId;
}