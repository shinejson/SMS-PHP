<?php
require_once 'config.php';
require_once 'session.php';
require_once 'rbac.php';

// For admin-only pages
requirePermission('admin');
// Fetch data from database
$students = [];
$classes = [];
$terms = [];
$academic_years = [];
$school_settings = [];

// Fetch students with class information for filtering
$student_query = "
    SELECT 
        s.id, 
        s.first_name, 
        s.last_name, 
        s.student_id, 
        s.class_id,
        c.class_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.status = 'Active' 
    ORDER BY c.class_name, s.first_name, s.last_name
";

$student_result = $conn->query($student_query);
while ($row = $student_result->fetch_assoc()) {
    $students[] = $row;
}

// Fetch classes
$class_result = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");
while ($row = $class_result->fetch_assoc()) {
    $classes[] = $row;
}

// Fetch terms
$term_result = $conn->query("SELECT id, term_name FROM terms ORDER BY term_order");
while ($row = $term_result->fetch_assoc()) {
    $terms[] = $row;
}

// Fetch academic years
$year_result = $conn->query("SELECT id, year_name, is_current FROM academic_years ORDER BY year_name DESC");
while ($row = $year_result->fetch_assoc()) {
    $academic_years[] = $row;
}

// Fetch school settings
$settings_result = $conn->query("SELECT * FROM school_settings");
while ($row = $settings_result->fetch_assoc()) {
    $school_settings[$row['school_name']] = $row['address'];
}

// Set default values with fallbacks
$school_name = $school_settings['school_name'] ?? 'GLOBAL EVANGELICAL BASIC SCHOOL-TETTEKOPE';
$school_address = $school_settings['school_address'] ?? 'P.O. BOX 182, KETA';
$school_logo = $school_settings['school_logo'] ?? '';
$headmaster_phone = $school_settings['headmaster_phone'] ?? '024409832';
$accountant_phone = $school_settings['accountant_phone'] ?? '0240499159';
$momo_number = $school_settings['momo_number'] ?? '883400';

// Get current term and academic year
$current_term = null;
$current_year = null;

$current_term_result = $conn->query("SELECT id, term_name FROM terms ORDER BY term_order LIMIT 1");
if ($current_term_result && $current_term_result->num_rows > 0) {
    $current_term = $current_term_result->fetch_assoc();
}

$current_year_result = $conn->query("SELECT id, year_name FROM academic_years WHERE is_current = 1 LIMIT 1");
if ($current_year_result && $current_year_result->num_rows > 0) {
    $current_year = $current_year_result->fetch_assoc();
} else {
    // Fallback to the most recent year if no current year is set
    $fallback_year_result = $conn->query("SELECT id, year_name FROM academic_years ORDER BY year_name DESC LIMIT 1");
    if ($fallback_year_result && $fallback_year_result->num_rows > 0) {
        $current_year = $fallback_year_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tuition Fee Report - GEBSCO</title>
    <?php include 'favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="css/bill-report.css">

</head>

<body>
    <div class="container">
        <div class="report-header">
            <div class="school-info">
                <?php if (!empty($school_logo)): ?>
                <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" class="school-logo">
                <?php endif; ?>
                <div>
                    <div class="school-name"><?php echo htmlspecialchars($school_name); ?></div>
                    <div class="school-address"><?php echo htmlspecialchars($school_address); ?></div>
                </div>
            </div>
            <div class="report-title">PUPIL'S BILL</div>
        </div>
        
        <div class="input-form">
            <h3 class="form-title">Generate Bill</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="class_id">Class Filter (optional)</label>
                    <select id="class_id">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="filter-info" id="class-filter-info" style="display: none;">
                        <small>Students filtered by selected class</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="student_id">Student *</label>
                    <select id="student_id" required>
                        <option value="">Search and select student...</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" 
                                data-class-id="<?php echo $student['class_id']; ?>"
                                data-class-name="<?php echo htmlspecialchars($student['class_name'] ?? ''); ?>">
                            <?php 
                            echo htmlspecialchars(
                                $student['first_name'] . ' ' . $student['last_name'] . 
                                ' (' . $student['student_id'] . ')' . 
                                ' - ' . ($student['class_name'] ?? 'No Class')
                            ); 
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="term_id">Term *</label>
                    <select id="term_id" required>
                        <option value="">Select Term</option>
                        <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term['id']; ?>" 
                                <?php echo (isset($current_term) && $current_term['id'] == $term['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($term['term_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="academic_year_id">Academic Year *</label>
                    <select id="academic_year_id" required>
                        <option value="">Select Academic Year</option>
                        <?php foreach ($academic_years as $year): ?>
                        <option value="<?php echo $year['id']; ?>" 
                                <?php echo (isset($current_year) && $current_year['id'] == $year['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year['year_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="action-buttons">
                <button class="btn btn-generate" onclick="generateBill()">
                    <i class="fas fa-cog"></i> Generate Bill
                </button>
            </div>
        </div>
        
        <div class="loading" id="loadingIndicator">
            <i class="fas fa-spinner fa-spin"></i> Generating bill, please wait...
        </div>
        
        <div id="billContent">
            <div class="student-info">
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span id="student-name">___________________________</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Class:</span>
                    <span id="student-class">___________________________</span>
                </div>
            </div>
            
            <div class="term-year">
                <div>TERM: <span id="term"><?php echo isset($current_term) ? htmlspecialchars($current_term['term_name']) : 'Three (3)'; ?></span></div>
                <div>Year: <span id="academic-year"><?php echo isset($current_year) ? htmlspecialchars($current_year['year_name']) : '2024/2025'; ?></span></div>
            </div>
            
            <table class="bill-table">
                <thead>
                    <tr>
                        <th>DEBIT</th>
                        <th>GHC</th>
                        <th>P</th>
                        <th>CREDIT</th>
                        <th>GHC</th>
                        <th>P</th>
                    </tr>
                </thead>
                <tbody id="fee-items">
                    <tr>
                        <td colspan="6" style="text-align: center; color: #666; font-style: italic;">
                            Please generate a bill to view fee details
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="instructions">
                <ol>
                    <li>If there is any mistake on your bill, please bring it to the attention of the headmaster on this number (<?php echo htmlspecialchars($headmaster_phone); ?>) and Account clerk (<?php echo htmlspecialchars($accountant_phone); ?>).</li>
                    <li>Bill must be settled in full on or before re-opening day and bring the bill when coming to make payment.</li>
                    <li>Payments can be made on this MOMO number <?php echo htmlspecialchars($momo_number); ?> (<?php echo htmlspecialchars($school_name); ?>) use your ward's name and class as reference.</li>
                </ol>
            </div>
        </div>
        
        <div class="action-buttons">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Print Bill
            </button>
            <button class="btn btn-download" onclick="downloadPDF()">
                <i class="fas fa-download"></i> Download PDF
            </button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/darkmode.js"></script>
    <script src="js/dashboard.js"></script>
    <script src="js/bill-report.js"></script>

    <script>
        // Store original student options for filtering
        let originalStudentOptions = [];
        
        $(document).ready(function() {
            // Initialize Select2 for searchable dropdowns
            initializeSelect2();
            
            // Store original student options
            storeOriginalOptions();
            
            // Set up event listeners
            setupEventListeners();
        });
        
        function initializeSelect2() {
            // Initialize Select2 for student dropdown with search
            $('#student_id').select2({
                placeholder: "Search and select student...",
                allowClear: true,
                width: '100%',
                matcher: customMatcher
            });
            
            // Initialize Select2 for other dropdowns
            $('#class_id, #term_id, #academic_year_id').select2({
                width: '100%',
                minimumResultsForSearch: 10 // Enable search if more than 10 items
            });
        }
        
        function customMatcher(params, data) {
            // If there are no search terms, return all data
            if ($.trim(params.term) === '') {
                return data;
            }
            
            // Skip if there is no 'text' property
            if (typeof data.text === 'undefined') {
                return null;
            }
            
            // Check if the search term matches student name, ID, or class
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                return data;
            }
            
            // Return null if the term should not be displayed
            return null;
        }
        
        function storeOriginalOptions() {
            $('#student_id option').each(function() {
                if ($(this).val() !== '') {
                    originalStudentOptions.push({
                        value: $(this).val(),
                        text: $(this).text(),
                        classId: $(this).data('class-id'),
                        className: $(this).data('class-name')
                    });
                }
            });
        }
        
        function setupEventListeners() {
            // Class filter change event
            $('#class_id').on('change', function() {
                filterStudentsByClass();
            });
            
            // Student selection change event
            $('#student_id').on('change', function() {
                const studentId = $(this).val();
                if (studentId) {
                    populateStudentDetails();
                }
            });
        }
        
        function filterStudentsByClass() {
            const selectedClassId = $('#class_id').val();
            const studentSelect = $('#student_id');
            const filterInfo = $('#class-filter-info');
            
            // Clear current student selection
            studentSelect.val(null).trigger('change');
            
            // Remove all student options except the placeholder
            studentSelect.find('option:not(:first)').remove();
            
            let filteredStudents = [];
            let placeholderText = "Search and select student...";
            
            if (selectedClassId === '') {
                // Show all students if no class filter
                filteredStudents = originalStudentOptions;
                filterInfo.hide();
            } else {
                // Filter students by selected class
                filteredStudents = originalStudentOptions.filter(function(option) {
                    return option.classId == selectedClassId;
                });
                
                // Show filter info
                filterInfo.show();
                
                // Update placeholder text
                if (filteredStudents.length === 0) {
                    placeholderText = 'No students found in selected class';
                } else {
                    placeholderText = `Search ${filteredStudents.length} students in class...`;
                }
            }
            
            // Add filtered students to dropdown
            filteredStudents.forEach(function(option) {
                studentSelect.append(new Option(option.text, option.value));
            });
            
            // Update Select2 placeholder
            studentSelect.attr('data-placeholder', placeholderText);
            
            // Reinitialize Select2 to reflect changes
            studentSelect.select2('destroy').select2({
                placeholder: placeholderText,
                allowClear: true,
                width: '100%',
                matcher: customMatcher
            });
        }
        
        function populateStudentDetails() {
            const selectedOption = $('#student_id option:selected');
            const studentText = selectedOption.text();
            const className = selectedOption.data('class-name');
            
            if (studentText && studentText !== '') {
                // Extract student name from the option text
                const match = studentText.match(/^(.+?)\s+\([^)]+\)/);
                if (match) {
                    $('#student-name').text(match[1]);
                    $('#student-class').text(className || 'Unknown');
                }
            }
        }
        
        function generateBill() {
            const studentId = $('#student_id').val();
            const classId = $('#class_id').val();
            const termId = $('#term_id').val();
            const academicYearId = $('#academic_year_id').val();
            
            if (!studentId || !termId || !academicYearId) {
                alert('Please select Student, Term, and Academic Year');
                return;
            }
            
            $('#loadingIndicator').show();
            
            // Fetch bill data from server
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
                success: function(response) {
                    $('#loadingIndicator').hide();
                    
                    if (response.success) {
                        // Update student info
                        $('#student-name').text(response.student_name);
                        $('#student-class').text(response.class_name);
                        $('#term').text(response.term_name);
                        $('#academic-year').text(response.academic_year);
                        
                        // Populate fee items
                        const feeItems = $('#fee-items');
                        feeItems.empty();
                        
                        let totalAmount = 0;
                        
                        // Add fee items
                        if (response.fee_items && response.fee_items.length > 0) {
                            response.fee_items.forEach(item => {
                                const amount = parseFloat(item.amount);
                                totalAmount += amount;
                                
                                const ghc = Math.floor(amount);
                                const p = Math.round((amount - ghc) * 100);
                                
                                // Check if this is a tuition item with sub-fees
                                if (item.payment_type === 'Tuition' && item.sub_fees && item.sub_fees.length > 0) {
                                    // Add main tuition row
                                    feeItems.append(`
                                        <tr>
                                            <td><strong>${item.name}</strong></td>
                                            <td class="amount"><strong>${ghc}</strong></td>
                                            <td class="amount"><strong>${p.toString().padStart(2, '0')}</strong></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    `);
                                    
                                    // Add sub-fees
                                    item.sub_fees.forEach(subFee => {
                                        const subAmount = parseFloat(subFee.amount);
                                        const subGhc = Math.floor(subAmount);
                                        const subP = Math.round((subAmount - subGhc) * 100);
                                        
                                        feeItems.append(`
                                            <tr class="sub-item">
                                                <td>${subFee.name}</td>
                                                <td class="amount">${subGhc}</td>
                                                <td class="amount">${subP.toString().padStart(2, '0')}</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        `);
                                    });
                                } else {
                                    // Regular fee item
                                    feeItems.append(`
                                        <tr>
                                            <td>${item.name}</td>
                                            <td class="amount">${ghc}</td>
                                            <td class="amount">${p.toString().padStart(2, '0')}</td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    `);
                                }
                            });
                        } else {
                            feeItems.append(`
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #666;">
                                        No fee items found for this student and term
                                    </td>
                                </tr>
                            `);
                        }
                        
                        // Add total row if there are fees
                        if (totalAmount > 0) {
                            const totalGhc = Math.floor(totalAmount);
                            const totalP = Math.round((totalAmount - totalGhc) * 100);
                            
                            feeItems.append(`
                                <tr class="tuition-total">
                                    <td><strong>Total Amount Due</strong></td>
                                    <td class="amount"><strong>${totalGhc}</strong></td>
                                    <td class="amount"><strong>${totalP.toString().padStart(2, '0')}</strong></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            `);
                        }
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error occurred'));
                    }
                },
                error: function(xhr, status, error) {
                    $('#loadingIndicator').hide();
                    console.error('AJAX Error:', xhr.responseText);
                    alert('Error fetching bill data. Please check the console for details and try again.');
                }
            });
        }
        
        function downloadPDF() {
            alert('PDF download functionality would be implemented here. This requires a server-side component or a JavaScript PDF library like jsPDF.');
        }
    </script>
</body>
</html>