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
    $('#class_id, #graduation_year').select2({
        width: '100%',
        minimumResultsForSearch: 10
    });
}

function customMatcher(params, data) {
    if ($.trim(params.term) === '') {
        return data;
    }
    
    if (typeof data.text === 'undefined') {
        return null;
    }
    
    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
        return data;
    }
    
    return null;
}

function storeOriginalOptions() {
    $('#student_id option').each(function() {
        if ($(this).val() !== '') {
            originalStudentOptions.push({
                value: $(this).val(),
                text: $(this).text(),
                classId: $(this).data('class-id'),
                className: $(this).data('class-name'),
                name: $(this).data('name'),
                dob: $(this).data('dob'),
                phone: $(this).data('phone'),
                email: $(this).data('email'),
                address: $(this).data('address')
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
    
    // Clear current student selection
    studentSelect.val(null).trigger('change');
    
    // Remove all student options except the placeholder
    studentSelect.find('option:not(:first)').remove();
    
    let filteredStudents = [];
    let placeholderText = "Search and select student...";
    
    if (selectedClassId === '') {
        filteredStudents = originalStudentOptions;
    } else {
        filteredStudents = originalStudentOptions.filter(function(option) {
            return option.classId == selectedClassId;
        });
        
        if (filteredStudents.length === 0) {
            placeholderText = 'No students found in selected class';
        } else {
            placeholderText = `Search ${filteredStudents.length} students in class...`;
        }
    }
    
    // Add filtered students to dropdown
    filteredStudents.forEach(function(option) {
        const newOption = new Option(option.text, option.value);
        $(newOption).data('name', option.name);
        $(newOption).data('dob', option.dob);
        $(newOption).data('phone', option.phone);
        $(newOption).data('email', option.email);
        $(newOption).data('address', option.address);
        $(newOption).data('class-name', option.className);
        studentSelect.append(newOption);
    });
    
    // Update Select2 placeholder
    studentSelect.attr('data-placeholder', placeholderText);
    
    // Reinitialize Select2
    studentSelect.select2('destroy').select2({
        placeholder: placeholderText,
        allowClear: true,
        width: '100%',
        matcher: customMatcher
    });
}

function populateStudentDetails() {
    const selectedOption = $('#student_id option:selected');
    
    if (selectedOption.val()) {
        $('#student-full-name').text(selectedOption.data('name') || 'N/A');
        $('#student-address').text(selectedOption.data('address') || 'N/A');
        $('#student-phone').text(selectedOption.data('phone') || 'N/A');
        $('#student-email').text(selectedOption.data('email') || 'N/A');
        $('#student-dob').text(selectedOption.data('dob') || 'N/A');
    }
}

function generateTranscript() {
    const studentId = $('#student_id').val();
    const graduationYear = $('#graduation_year').val();
    
    if (!studentId) {
        alert('Please select a student');
        return;
    }
    
    $('#loadingIndicator').show();
    
    // Fetch transcript data from server
    $.ajax({
        url: 'get_student_transcript.php',
        type: 'POST',
        data: {
            student_id: studentId,
            graduation_year: graduationYear
        },
        dataType: 'json',
        success: function(response) {
            $('#loadingIndicator').hide();
            
            if (response.success) {
                // Update student info
                $('#student-full-name').text(response.student_name);
                $('#student-address').text(response.student_address || 'N/A');
                $('#student-phone').text(response.student_phone || 'N/A');
                $('#student-email').text(response.student_email || 'N/A');
                $('#student-dob').text(response.student_dob || 'N/A');
                $('#parent-guardian').text(response.parent_name || 'N/A');
                $('#graduation-date').text(response.graduation_date || 'N/A');
                
                // Populate academic records
                populateAcademicRecords(response.academic_records, response.gpa_info);
            } else {
                alert('Error: ' + (response.message || 'Unknown error occurred'));
            }
        },
        error: function(xhr, status, error) {
            $('#loadingIndicator').hide();
            console.error('AJAX Error:', xhr.responseText);
            alert('Error fetching transcript data. Please check the console for details and try again.');
        }
    });
}

function populateAcademicRecords(academicRecords, gpaInfo) {
    const gradesContent = $('#grades-content');
    gradesContent.empty();
    
    if (academicRecords && Object.keys(academicRecords).length > 0) {
        const academicContainer = $('<div class="academic-container"></div>');
        
        // Sort year terms chronologically (newest first)
        const yearTerms = Object.keys(academicRecords).sort((a, b) => {
            const yearA = a.match(/\d{4}/);
            const yearB = b.match(/\d{4}/);
            return (yearB ? parseInt(yearB[0]) : 0) - (yearA ? parseInt(yearA[0]) : 0);
        });
        
        yearTerms.forEach(yearTerm => {
            const yearSection = createYearSection(yearTerm, academicRecords[yearTerm]);
            academicContainer.append(yearSection);
        });
        
        gradesContent.append(academicContainer);
        
        // Add GPA section
        if (gpaInfo) {
            const gpaSection = createGpaSection(gpaInfo);
            gradesContent.append(gpaSection);
        }
    } else {
        gradesContent.html(`
            <p style="text-align: center; padding: 40px; color: #666;">
                No academic records found for this student
            </p>
        `);
    }
}

function createYearSection(yearTerm, classesData) {
    const yearSection = $(`
        <div class="year-section">
            <div class="year-header">ACADEMIC YEAR: ${yearTerm}</div>
        </div>
    `);
    
    // Convert the classesData object to an array if it's not already
    let classesArray = [];
    if (Array.isArray(classesData)) {
        classesArray = classesData;
    } else if (typeof classesData === 'object' && classesData !== null) {
        // Convert object to array of values
        classesArray = Object.values(classesData);
    }
    
    classesArray.forEach(classData => {
        const classSection = createClassSection(classData);
        yearSection.append(classSection);
    });
    
    return yearSection;
}

function createClassSection(classData) {
    const classSection = $(`
        <div class="class-section">
            <div class="class-header">
                <strong>CLASS: ${classData.class_name}</strong> | 
                TERM AVERAGE: ${classData.term_average}% | 
                SUBJECTS: ${classData.subject_count}
            </div>
            <table class="transcript-table">
                <thead>
                    <tr>
                        <th width="30%">SUBJECT</th>
                        <th width="15%">CLASS SCORE (40%)</th>
                        <th width="15%">EXAM SCORE (60%)</th>
                        <th width="15%">TOTAL SCORE</th>
                        <th width="12%">GRADE</th>
                        <th width="13%">REMARKS</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 10px; background-color: #f8f9fa;">
                            <strong>CLASS AVERAGE: ${classData.term_average}%</strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    `);
    
    const tbody = classSection.find('tbody');
    
    if (classData.subjects && Array.isArray(classData.subjects)) {
        classData.subjects.forEach(subject => {
            const row = $(`
                <tr>
                    <td>${subject.subject_name}</td>
                    <td class="text-center">${subject.class_score}</td>
                    <td class="text-center">${subject.exam_score}</td>
                    <td class="text-center"><strong>${subject.total_score}</strong></td>
                    <td class="text-center"><strong>${subject.grade}</strong></td>
                    <td class="text-center">${subject.remark}</td>
                </tr>
            `);
            
            // Add color coding based on grade
            if (subject.grade === 'A') {
                row.find('td').css('background-color', '#e8f5e8'); // Light green for A
            } else if (subject.grade === 'B') {
                row.find('td').css('background-color', '#fff3cd'); // Light yellow for B
            } else if (subject.grade === 'C') {
                row.find('td').css('background-color', '#ffeaa7'); // Light orange for C
            } else if (subject.grade === 'D') {
                row.find('td').css('background-color', '#f8d7da'); // Light red for D
            } else if (subject.grade === 'F') {
                row.find('td').css('background-color', '#f5c6cb'); // Dark red for F
            }
            
            tbody.append(row);
        });
    }
    
    return classSection;
}

function createGpaSection(gpaInfo) {
    return $(`
        <div class="gpa-summary-section">
            <div class="gpa-header">ACADEMIC SUMMARY</div>
            <div class="gpa-grid">
                <div class="gpa-item">
                    <label>Cumulative GPA:</label>
                    <span class="gpa-value">${gpaInfo.cumulative_gpa || 'N/A'}</span>
                </div>
                <div class="gpa-item">
                    <label>Class Rank:</label>
                    <span class="gpa-value">${gpaInfo.class_rank || 'N/A'}</span>
                </div>
                <div class="gpa-item">
                    <label>Total Credits:</label>
                    <span class="gpa-value">${gpaInfo.total_credits || 'N/A'}</span>
                </div>
            </div>
            <div class="grading-scale">
                <h4>GRADING SCALE</h4>
                <div class="scale-grid">
                    <div class="scale-item">A = 80-100% (4.0) - Excellent</div>
                    <div class="scale-item">B = 70-79% (3.0) - Very Good</div>
                    <div class="scale-item">C = 60-69% (2.0) - Good</div>
                    <div class="scale-item">D = 50-59% (1.0) - Pass</div>
                    <div class="scale-item">F = 0-49% (0.0) - Fail</div>
                </div>
            </div>
        </div>
    `);
}

function downloadPDF() {
    alert('PDF download functionality would be implemented here. This requires a server-side component or a JavaScript PDF library like jsPDF.');
}