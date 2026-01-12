// Additional utility functions for the enhanced bill report

// Function to get student details when student is selected (for auto-populating class)
function getStudentDetails(studentId) {
    if (!studentId) return;
    
    $.ajax({
        url: 'get_student_details.php',
        type: 'POST',
        data: { student_id: studentId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.class_id) {
                // Auto-select the class if not already filtered
                if ($('#class_id').val() === '') {
                    $('#class_id').val(response.class_id).trigger('change');
                }
            }
        },
        error: function(xhr, status, error) {
            console.warn('Could not fetch student details:', error);
        }
    });
}

// Function to validate form before submission
function validateBillForm() {
    const studentId = $('#student_id').val();
    const termId = $('#term_id').val();
    const academicYearId = $('#academic_year_id').val();
    
    const errors = [];
    
    if (!studentId) {
        errors.push('Please select a student');
        $('#student_id').closest('.form-group').addClass('error');
    } else {
        $('#student_id').closest('.form-group').removeClass('error');
    }
    
    if (!termId) {
        errors.push('Please select a term');
        $('#term_id').closest('.form-group').addClass('error');
    } else {
        $('#term_id').closest('.form-group').removeClass('error');
    }
    
    if (!academicYearId) {
        errors.push('Please select an academic year');
        $('#academic_year_id').closest('.form-group').addClass('error');
    } else {
        $('#academic_year_id').closest('.form-group').removeClass('error');
    }
    
    if (errors.length > 0) {
        alert('Please correct the following errors:\n\n' + errors.join('\n'));
        return false;
    }
    
    return true;
}

// Function to format currency display
function formatCurrency(amount) {
    const num = parseFloat(amount);
    if (isNaN(num)) return { ghc: 0, p: 0 };
    
    const ghc = Math.floor(num);
    const p = Math.round((num - ghc) * 100);
    
    return { 
        ghc: ghc, 
        p: p.toString().padStart(2, '0') 
    };
}

// Function to create fee row HTML
function createFeeRow(item, isSubItem = false) {
    const currency = formatCurrency(item.amount);
    const rowClass = isSubItem ? 'sub-item' : '';
    const itemName = isSubItem ? `&nbsp;&nbsp;&nbsp;&nbsp;${item.name}` : item.name;
    const fontWeight = isSubItem ? 'normal' : 'bold';
    
    return `
        <tr class="${rowClass}">
            <td style="font-weight: ${fontWeight};">${itemName}</td>
            <td class="amount" style="font-weight: ${fontWeight};">${currency.ghc}</td>
            <td class="amount" style="font-weight: ${fontWeight};">${currency.p}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    `;
}

// Function to show loading state
function showLoading(message = 'Loading...') {
    $('#loadingIndicator').find('i').next().text(message);
    $('#loadingIndicator').show();
}

// Function to hide loading state
function hideLoading() {
    $('#loadingIndicator').hide();
}

// Function to show success message
function showSuccessMessage(message, duration = 3000) {
    const alertDiv = $(`
        <div class="alert alert-success" style="
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 20px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 300px;
        ">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
            ${message}
            <button type="button" style="
                float: right;
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                margin-left: 10px;
            " onclick="$(this).parent().remove();">&times;</button>
        </div>
    `);
    
    $('body').append(alertDiv);
    
    setTimeout(() => {
        alertDiv.fadeOut(500, function() {
            $(this).remove();
        });
    }, duration);
}

// Function to show error message
function showErrorMessage(message, duration = 5000) {
    const alertDiv = $(`
        <div class="alert alert-error" style="
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 20px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 300px;
        ">
            <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
            ${message}
            <button type="button" style="
                float: right;
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                margin-left: 10px;
            " onclick="$(this).parent().remove();">&times;</button>
        </div>
    `);
    
    $('body').append(alertDiv);
    
    setTimeout(() => {
        alertDiv.fadeOut(500, function() {
            $(this).remove();
        });
    }, duration);
}

// Enhanced generate bill function with better error handling
function generateBillEnhanced() {
    if (!validateBillForm()) {
        return;
    }
    
    const studentId = $('#student_id').val();
    const classId = $('#class_id').val();
    const termId = $('#term_id').val();
    const academicYearId = $('#academic_year_id').val();
    
    showLoading('Generating bill, please wait...');
    
    // Disable the generate button to prevent multiple submissions
    const generateBtn = $('.btn-generate');
    const originalBtnText = generateBtn.html();
    generateBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating...');
    
    $.ajax({
        url: 'get_student_bill.php',
        type: 'POST',
        data: {
            student_id: studentId,
            class_id: classId,
            term_id: termId,
            academic_year_id: academicYearId
        },
        dataType: 'json',
        timeout: 30000, // 30 second timeout
        success: function(response) {
            hideLoading();
            
            if (response.success) {
                // Update student info
                $('#student-name').text(response.student_name || 'Unknown Student');
                $('#student-class').text(response.class_name || 'Unknown Class');
                $('#term').text(response.term_name || 'Unknown Term');
                $('#academic-year').text(response.academic_year || 'Unknown Year');
                
                // Populate fee items
                populateFeeTable(response.fee_items);
                
                showSuccessMessage('Bill generated successfully!');
                
                // Scroll to the bill content
                $('html, body').animate({
                    scrollTop: $("#billContent").offset().top - 20
                }, 500);
                
            } else {
                showErrorMessage('Error: ' + (response.message || 'Unknown error occurred'));
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            
            let errorMessage = 'Error generating bill. ';
            
            if (status === 'timeout') {
                errorMessage += 'Request timed out. Please try again.';
            } else if (xhr.status === 404) {
                errorMessage += 'Server endpoint not found.';
            } else if (xhr.status === 500) {
                errorMessage += 'Server error occurred.';
            } else {
                errorMessage += 'Please check your connection and try again.';
            }
            
            showErrorMessage(errorMessage);
            console.error('AJAX Error:', { status, error, response: xhr.responseText });
        },
        complete: function() {
            // Re-enable the generate button
            generateBtn.prop('disabled', false).html(originalBtnText);
        }
    });
}

// Function to populate the fee table
function populateFeeTable(feeItems) {
    const feeTable = $('#fee-items');
    feeTable.empty();
    
    let totalAmount = 0;
    
    if (!feeItems || feeItems.length === 0) {
        feeTable.append(`
            <tr>
                <td colspan="6" style="text-align: center; color: #666; font-style: italic;">
                    No fee items found for this student and term
                </td>
            </tr>
        `);
        return;
    }
    
    // Process each fee item
    feeItems.forEach(item => {
        const amount = parseFloat(item.amount) || 0;
        totalAmount += amount;
        
        // Check if this is a tuition item with sub-fees
        if (item.payment_type === 'Tuition' && item.sub_fees && item.sub_fees.length > 0) {
            // Add main tuition row
            feeTable.append(createFeeRow({
                name: `<strong>${item.name}</strong>`,
                amount: amount
            }));
            
            // Add sub-fees
            item.sub_fees.forEach(subFee => {
                feeTable.append(createFeeRow(subFee, true));
            });
        } else {
            // Regular fee item
            feeTable.append(createFeeRow(item));
        }
    });
    
    // Add total row
    if (totalAmount > 0) {
        const totalCurrency = formatCurrency(totalAmount);
        feeTable.append(`
            <tr class="tuition-total" style="background-color: #f8f9fa; font-weight: bold;">
                <td><strong>Total Amount Due</strong></td>
                <td class="amount"><strong>${totalCurrency.ghc}</strong></td>
                <td class="amount"><strong>${totalCurrency.p}</strong></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        `);
    }
}

// Update the original generateBill function to use the enhanced version
function generateBill() {
    generateBillEnhanced();
}

// Add CSS for error states
$('<style>')
    .prop('type', 'text/css')
    .html(`
        .form-group.error select {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25) !important;
        }
        
        .form-group.error label {
            color: #dc3545 !important;
        }
        
        .alert {
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `)
    .appendTo('head');