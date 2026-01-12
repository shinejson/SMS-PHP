// Add these variables at the top of your JavaScript file
let currentReportData = null;

$(document).ready(function() {
    // Initialize Select2
    $('#academic_year_id, #term_id, #class_id, #status').select2({
        width: '100%'
    });
    
    // Initialize DataTable
    $('#ledgerTable').DataTable({
        responsive: true,
        scrollX: true,
        pageLength: 25,
        order: [[6, 'asc'], [1, 'asc']], // Order by status, then name
        dom: 'Blfrtip',
        buttons: [
            {
                extend: 'copy',
                className: 'btn btn-secondary btn-sm'
            },
            {
                extend: 'csv',
                className: 'btn btn-secondary btn-sm',
                title: 'Student Account Ledger'
            },
            {
                extend: 'excel',
                className: 'btn btn-secondary btn-sm',
                title: 'Student Account Ledger'
            },
            {
                extend: 'pdf',
                className: 'btn btn-secondary btn-sm',
                title: 'Student Account Ledger',
                orientation: 'landscape',
                pageSize: 'A4'
            },
            {
                extend: 'print',
                className: 'btn btn-secondary btn-sm',
                title: 'Student Account Ledger'
            }
        ],
        columnDefs: [
            { 
                targets: [8], // Actions column
                orderable: false 
            },
            {
                targets: [3, 4, 5], // Amount columns
                className: 'text-right'
            }
        ],
        footerCallback: function (row, data, start, end, display) {
            var api = this.api();
            
            // Calculate totals for visible rows
            var totalBilling = api
                .column(3, { page: 'current' })
                .data()
                .reduce(function (a, b) {
                    return parseFloat(a) + parseFloat(b.replace(/[GH₵,\s]/g, ''));
                }, 0);
                
            var totalPaid = api
                .column(4, { page: 'current' })
                .data()
                .reduce(function (a, b) {
                    return parseFloat(a) + parseFloat(b.replace(/[GH₵,\s]/g, ''));
                }, 0);
                
            var totalBalance = api
                .column(5, { page: 'current' })
                .data()
                .reduce(function (a, b) {
                    return parseFloat(a) + parseFloat(b.replace(/[GH₵,\s]/g, ''));
                }, 0);
            
            // Update footer if it exists
            if ($(api.column(3).footer()).length) {
                $(api.column(3).footer()).html('GH₵ ' + totalBilling.toLocaleString());
                $(api.column(4).footer()).html('GH₵ ' + totalPaid.toLocaleString());
                $(api.column(5).footer()).html('GH₵ ' + totalBalance.toLocaleString());
            }
        }
    });
});

// JavaScript functions for the enhanced modal
function toggleCustomDate() {
    const dateRange = document.getElementById('modal_date_range').value;
    const customDateGroup = document.getElementById('customDateGroup');
    const customEndDateGroup = document.getElementById('customEndDateGroup');
    
    if (dateRange === 'custom') {
        customDateGroup.style.display = 'block';
        customEndDateGroup.style.display = 'block';
    } else {
        customDateGroup.style.display = 'none';
        customEndDateGroup.style.display = 'none';
    }
}

function getDateRange() {
    const rangeType = document.getElementById('modal_date_range').value;
    const today = new Date();
    let startDate, endDate;
    
    switch(rangeType) {
        case 'today':
            startDate = today.toISOString().split('T')[0];
            endDate = startDate;
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            startDate = yesterday.toISOString().split('T')[0];
            endDate = startDate;
            break;
        case 'this_week':
            const firstDayOfWeek = new Date(today);
            firstDayOfWeek.setDate(today.getDate() - today.getDay());
            startDate = firstDayOfWeek.toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
        case 'last_week':
            const firstDayOfLastWeek = new Date(today);
            firstDayOfLastWeek.setDate(today.getDate() - today.getDay() - 7);
            const lastDayOfLastWeek = new Date(firstDayOfLastWeek);
            lastDayOfLastWeek.setDate(firstDayOfLastWeek.getDate() + 6);
            startDate = firstDayOfLastWeek.toISOString().split('T')[0];
            endDate = lastDayOfLastWeek.toISOString().split('T')[0];
            break;
        case 'this_month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            endDate = today.toISOString().split('T')[0];
            break;
        case 'last_month':
            const firstDayOfLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            startDate = firstDayOfLastMonth.toISOString().split('T')[0];
            endDate = lastDayOfLastMonth.toISOString().split('T')[0];
            break;
        case 'custom':
            startDate = document.getElementById('start_date').value;
            endDate = document.getElementById('end_date').value;
            break;
        default:
            startDate = today.toISOString().split('T')[0];
            endDate = startDate;
    }
    
    return { startDate, endDate };
}

function loadDailyClosing() {
    // Show modal first
    document.getElementById('dailyClosingModal').style.display = 'block';
    
    const { startDate, endDate } = getDateRange();
    const academicYearId = document.getElementById('modal_academic_year').value;
    const termId = document.getElementById('modal_term').value;
    const classId = document.getElementById('modal_class').value;
    const paymentMethod = document.getElementById('modal_payment_method').value;
    
    // Update report period text
    const periodText = startDate === endDate ? 
        `Date: ${startDate}` : 
        `From: ${startDate} To: ${endDate}`;
    document.getElementById('reportPeriodText').textContent = periodText;
    
    // Show loading state
    document.getElementById('dailyTotal').textContent = 'Loading...';
    document.getElementById('dailyTransactions').textContent = 'Loading...';
    document.getElementById('paymentMethods').innerHTML = '<div class="method-item">Loading...</div>';
    document.getElementById('classSummary').innerHTML = '<div>Loading...</div>';
    document.getElementById('transactionsBody').innerHTML = '<tr><td colspan="8">Loading transactions...</td></tr>';
    
    // Build query string
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        academic_year_id: academicYearId,
        term_id: termId,
        class_id: classId,
        payment_method: paymentMethod
    });
    
    // Fetch daily data
    fetch(`get_daily_closing.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDailyModal(data);
            } else {
                alert('Error loading report: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load report');
        });
}

// Modify the updateDailyModal function to store the data
function updateDailyModal(data) {
    // Store the data for printing
    currentReportData = data;
    
    // Update totals
    document.getElementById('dailyTotal').textContent = `GH₵ ${data.total_amount.toFixed(2)}`;
    document.getElementById('dailyTransactions').textContent = data.transaction_count;
    
    // Update payment methods
    const methodsContainer = document.getElementById('paymentMethods');
    methodsContainer.innerHTML = '';
    
    if (Object.keys(data.payment_methods).length === 0) {
        methodsContainer.innerHTML = '<div class="method-item">No payments found</div>';
    } else {
        Object.entries(data.payment_methods).forEach(([method, amount]) => {
            const methodItem = document.createElement('div');
            methodItem.className = 'method-item';
            methodItem.innerHTML = `<span>${method}: GH₵ ${amount.toFixed(2)}</span>`;
            methodsContainer.appendChild(methodItem);
        });
    }
    
    // Update class summary
    const classContainer = document.getElementById('classSummary');
    classContainer.innerHTML = '';
    
    if (Object.keys(data.class_summary).length === 0) {
        classContainer.innerHTML = '<div>No class data found</div>';
    } else {
        Object.entries(data.class_summary).forEach(([className, amount]) => {
            const classItem = document.createElement('div');
            classItem.className = 'class-item';
            classItem.innerHTML = `<span>${className}: GH₵ ${amount.toFixed(2)}</span>`;
            classContainer.appendChild(classItem);
        });
    }
    
    // Update transactions table
    const transactionsBody = document.getElementById('transactionsBody');
    transactionsBody.innerHTML = '';
    
    if (data.transactions.length === 0) {
        transactionsBody.innerHTML = '<tr><td colspan="8">No transactions found for the selected period</td></tr>';
    } else {
        data.transactions.forEach(transaction => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${transaction.payment_date}</td>
                <td>${transaction.receipt_no}</td>
                <td>${transaction.student_name}</td>
                <td>${transaction.class_name}</td>
                <td>GH₵ ${parseFloat(transaction.amount).toFixed(2)}</td>
                <td>${transaction.payment_method}</td>
                <td>${transaction.payment_type}</td>
                <td>${transaction.collected_by}</td>
            `;
            transactionsBody.appendChild(row);
        });
        
        // Initialize DataTable if not already initialized
        if (!$.fn.DataTable.isDataTable('#dailyTransactionsTable')) {
            $('#dailyTransactionsTable').DataTable({
                responsive: true,
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'print'],
                pageLength: 10,
                order: [[0, 'desc']]
            });
        }
    }
}

// Update the printDailyReport function to use the stored data
function printDailyReport() {
    if (!currentReportData) {
        alert('No report data available. Please generate a report first.');
        return;
    }
    
    // Store original content
    const originalContent = document.body.innerHTML;
    
    // Create print-friendly content
    const printContent = createPrintContent(currentReportData);
    
    // Replace body content with print content
    document.body.innerHTML = printContent;
    
    // Print the document
    window.print();
    
    // Restore original content
    document.body.innerHTML = originalContent;
    
    // Re-initialize any necessary scripts
    if (typeof initPage === 'function') {
        initPage();
    }
}

// Helper function to create print content
function createPrintContent(data) {
    let methodsHTML = '';
    if (Object.keys(data.payment_methods).length === 0) {
        methodsHTML = '<div class="method-item">No payments found</div>';
    } else {
        Object.entries(data.payment_methods).forEach(([method, amount]) => {
            methodsHTML += `<div class="method-item"><span>${method}: GHC ${amount.toFixed(2)}</span></div>`;
        });
    }
    
    let classesHTML = '';
    if (Object.keys(data.class_summary).length === 0) {
        classesHTML = '<div>No class data found</div>';
    } else {
        Object.entries(data.class_summary).forEach(([className, amount]) => {
            classesHTML += `<div class="class-item"><span>${className}: GHC ${amount.toFixed(2)}</span></div>`;
        });
    }
    
    let transactionsHTML = '';
    if (data.transactions.length === 0) {
        transactionsHTML = '<tr><td colspan="8">No transactions found for the selected period</td></tr>';
    } else {
        data.transactions.forEach(transaction => {
            transactionsHTML += `
                <tr>
                    <td>${transaction.payment_date}</td>
                    <td>${transaction.receipt_no}</td>
                    <td>${transaction.student_name}</td>
                    <td>${transaction.class_name}</td>
                    <td>GH₵ ${parseFloat(transaction.amount).toFixed(2)}</td>
                    <td>${transaction.payment_method}</td>
                    <td>${transaction.payment_type}</td>
                    <td>${transaction.collected_by}</td>
                </tr>
            `;
        });
    }
    
    return `
        <div class="print-container">
            <h1>Daily Account Closing Report</h1>
            <div class="print-period">
                ${document.getElementById('reportPeriodText').textContent}
            </div>
            
            <div class="print-summary">
                <div class="print-summary-item">
                    <h3>Total Collections</h3>
                    <div class="print-amount">GH₵ ${data.total_amount.toFixed(2)}</div>
                </div>
                <div class="print-summary-item">
                    <h3>Number of Transactions</h3>
                    <div class="print-count">${data.transaction_count}</div>
                </div>
            </div>
            
            <div class="print-details">
                <h3>Payment Methods Breakdown</h3>
                <div class="print-methods">
                    ${methodsHTML}
                </div>
                
                <h3>Class-wise Summary</h3>
                <div class="print-classes">
                    ${classesHTML}
                </div>
            </div>
            
            <div class="print-transactions">
                <h3>Transaction Details</h3>
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Receipt No</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Type</th>
                            <th>Collected By</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${transactionsHTML}
                    </tbody>
                </table>
            </div>
            
            <div class="print-footer">
                <p>Generated on: ${new Date().toLocaleString()}</p>
            </div>
        </div>
        
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                color: #000;
            }
            .print-container {
                width: 100%;
            }
            h1 {
                text-align: center;
                color: #2c3e50;
                margin-bottom: 10px;
            }
            .print-period {
                text-align: center;
                margin-bottom: 20px;
                font-weight: bold;
            }
            .print-summary {
                display: flex;
                justify-content: space-around;
                margin-bottom: 20px;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
            .print-summary-item {
                text-align: center;
            }
            .print-amount, .print-count {
                font-size: 18px;
                font-weight: bold;
                color: #2c3e50;
            }
            .print-details {
                margin-bottom: 20px;
            }
            .print-details h3 {
                background-color: #f8f9fa;
                padding: 8px;
                border-left: 4px solid #2c3e50;
            }
            .print-methods, .print-classes {
                margin-left: 20px;
                margin-bottom: 15px;
            }
            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            .print-table th {
                background-color: #2c3e50;
                color: white;
                padding: 8px;
                text-align: left;
            }
            .print-table td {
                padding: 8px;
                border-bottom: 1px solid #ddd;
            }
            .print-table tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            .print-footer {
                margin-top: 30px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            @media print {
                .print-summary {
                    border: 1px solid #000;
                }
                .print-table {
                    page-break-inside: avoid;
                }
                @page {
                    margin: 1cm;
                }
            }
        </style>
    `;
}

function exportDailyReport(format) {
    const { startDate, endDate } = getDateRange();
    const academicYearId = document.getElementById('modal_academic_year').value;
    const termId = document.getElementById('modal_term').value;
    const classId = document.getElementById('modal_class').value;
    const paymentMethod = document.getElementById('modal_payment_method').value;
    
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate,
        academic_year_id: academicYearId,
        term_id: termId,
        class_id: classId,
        payment_method: paymentMethod,
        export: format
    });
    
    if (format === 'pdf') {
        // First try the autoTable version
        try {
            // Check if libraries are properly loaded
            if (typeof window.jspdf !== 'undefined' && 
                typeof window.jspdf.jsPDF !== 'undefined') {
                
                const testPdf = new window.jspdf.jsPDF();
                if (typeof testPdf.autoTable === 'function') {
                    generatePDFWithAutoTable();
                    return;
                }
            }
        } catch (error) {
            console.warn('autoTable PDF generation failed, trying simple PDF:', error);
        }
        
        // Fallback to simple PDF generation
        try {
            generateSimplePDF();
            return;
        } catch (error) {
            console.error('Simple PDF generation also failed:', error);
            // Final fallback - use server-side PDF generation
            window.open(`get_daily_closing.php?${params}`, '_blank');
        }
    } else {
        // For CSV and Excel, use the server-side approach
        window.open(`get_daily_closing.php?${params}`, '_blank');
    }
}

function closeDailyModal() {
    document.getElementById('dailyClosingModal').style.display = 'none';
    // Destroy DataTable if it exists
    if ($.fn.DataTable.isDataTable('#dailyTransactionsTable')) {
        $('#dailyTransactionsTable').DataTable().destroy();
    }
}

// Initialize modal when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates
    document.getElementById('start_date').value = new Date().toISOString().split('T')[0];
    document.getElementById('end_date').value = new Date().toISOString().split('T')[0];
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('dailyClosingModal');
        if (event.target === modal) {
            closeDailyModal();
        }
    }
    
    // Close modal with escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeDailyModal();
        }
    });
});

function generatePDFWithAutoTable() {
    if (!currentReportData) {
        alert('No report data available. Please generate a report first.');
        return;
    }
    
    // Show loading indicator
    const originalText = document.querySelector('.btn-export[onclick*="pdf"]').innerHTML;
    document.querySelector('.btn-export[onclick*="pdf"]').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
    
    try {
        
        // Create new PDF document
        const pdf = new jspdf.jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4'
        });
        
        // Set default text color to deep black
        pdf.setTextColor(0, 0, 0);
        
        // Add header
        pdf.setFontSize(18);
        pdf.text('Daily Account Closing Report', 15, 15);
        
        pdf.setFontSize(12);
        pdf.text(`Report Period: ${document.getElementById('reportPeriodText').textContent}`, 15, 22);
        pdf.text(`Generated on: ${new Date().toLocaleString()}`, 15, 28);
        
        // Add summary section
        pdf.setFontSize(14);
        pdf.text('Summary', 15, 40);
        
        pdf.setFontSize(10);
        pdf.text(`Total Collections: GH₵ ${currentReportData.total_amount.toFixed(2)}`, 15, 47);
        pdf.text(`Number of Transactions: ${currentReportData.transaction_count}`, 15, 53);
        
        // Add payment methods table
        if (Object.keys(currentReportData.payment_methods).length > 0) {
            pdf.setFontSize(12);
            pdf.text('Payment Methods Breakdown', 15, 65);
            
            const methodsData = [];
            Object.entries(currentReportData.payment_methods).forEach(([method, amount]) => {
                // Replace GH₵ with text representation to avoid symbol issues
                methodsData.push([method, `GHC ${amount.toFixed(2)}`]);
            });
            
            pdf.autoTable({
                startY: 70,
                head: [['Payment Method', 'Amount']],
                body: methodsData,
                theme: 'grid',
                headStyles: { 
                    fillColor: [44, 62, 80],
                    textColor: [255, 255, 255] // White text on dark background
                },
                styles: { 
                    fontSize: 10,
                    textColor: [0, 0, 0] // Deep black text
                }
            });
        }
        
        // Add class summary table
        if (Object.keys(currentReportData.class_summary).length > 0) {
            const finalY = pdf.lastAutoTable.finalY || 80;
            pdf.setFontSize(12);
            pdf.text('Class-wise Summary', 15, finalY + 15);
            
            const classesData = [];
            Object.entries(currentReportData.class_summary).forEach(([className, amount]) => {
                // Replace GH₵ with text representation
                classesData.push([className, `GHC ${amount.toFixed(2)}`]);
            });
            
            pdf.autoTable({
                startY: finalY + 20,
                head: [['Class', 'Amount']],
                body: classesData,
                theme: 'grid',
                headStyles: { 
                    fillColor: [44, 62, 80],
                    textColor: [255, 255, 255] // White text on dark background
                },
                styles: { 
                    fontSize: 10,
                    textColor: [0, 0, 0] // Deep black text
                }
            });
        }
        
        // Add transactions table
        if (currentReportData.transactions.length > 0) {
            const finalY = pdf.lastAutoTable.finalY || 100;
            pdf.setFontSize(12);
            pdf.text('Transaction Details', 15, finalY + 15);
            
            const transactionsData = currentReportData.transactions.map(transaction => [
                transaction.payment_date,
                transaction.receipt_no,
                transaction.student_name,
                transaction.class_name,
                // Replace GH₵ with text representation
                `GHC ${parseFloat(transaction.amount).toFixed(2)}`,
                transaction.payment_method,
                transaction.payment_type,
                transaction.collected_by
            ]);
            
            pdf.autoTable({
                startY: finalY + 20,
                head: [['Date', 'Receipt No', 'Student', 'Class', 'Amount', 'Method', 'Type', 'Collected By']],
                body: transactionsData,
                theme: 'grid',
                headStyles: { 
                    fillColor: [44, 62, 80],
                    textColor: [255, 255, 255] // White text on dark background
                },
                styles: { 
                    fontSize: 8,
                    textColor: [0, 0, 0] // Deep black text
                },
                pageBreak: 'auto',
                margin: { top: 10 }
            });
        }
        
        // Add page numbers
        const pageCount = pdf.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            pdf.setPage(i);
            pdf.setFontSize(8);
            pdf.setTextColor(0, 0, 0); // Deep black page numbers
            pdf.text(`Page ${i} of ${pageCount}`, pdf.internal.pageSize.getWidth() - 30, pdf.internal.pageSize.getHeight() - 10);
        }
        
        // Save the PDF
        const fileName = `Daily_Report_${new Date().toISOString().split('T')[0]}.pdf`;
        pdf.save(fileName);
        
    } catch (error) {
        console.error('Error generating PDF:', error);
        alert('Error generating PDF. Please try again.');
    } finally {
        // Restore button text
        document.querySelector('.btn-export[onclick*="pdf"]').innerHTML = originalText;
    }
}

// Test function to check if modal can be opened
function testModal() {
    console.log('Testing modal opening...');
    document.getElementById('dailyClosingModal').style.display = 'block';
    console.log('Modal should be visible now');
}

// Call this function from browser console to test
window.testModal = testModal;

// Make sure the PDF generation function is available globally
window.generatePDFWithAutoTable = generatePDFWithAutoTable;
window.exportDailyReport = exportDailyReport;
window.closeDailyModal = closeDailyModal;
window.loadDailyClosing = loadDailyClosing;
window.printDailyReport = printDailyReport;
window.toggleCustomDate = toggleCustomDate;