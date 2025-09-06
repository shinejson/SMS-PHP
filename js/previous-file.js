// Add these variables at the top of your marks.js file
const deleteMarkModal = document.getElementById('deleteMarkModal');
const viewMarkModal = document.getElementById('viewMarkModal');
const viewScoresModal = document.getElementById('viewScoresModal');
const deleteMarkForm = document.getElementById('deleteMarkForm');
const marksDetailsContent = document.getElementById('marksDetailsContent');

// Store DataTable instances for view scores modal
let scoresDataTables = {};

// Global variable to track if main DataTable is already initialized
let mainDataTableInitialized = false;

document.addEventListener('DOMContentLoaded', function () {
    // Initialize main marks table only if not already initialized
    if (!mainDataTableInitialized && $('#marksTable').length) {
        const marksTable = $('#marksTable').DataTable({
            responsive: true,
            dom: '<"top"<"export-buttons"B><"table-filters"f>>rt<"bottom"lip><"clear">',
            buttons: [
                { extend: 'excel', className: 'btn-excel', text: '<i class="fas fa-file-excel"></i> Excel' },
                { extend: 'pdf', className: 'btn-pdf', text: '<i class="fas fa-file-pdf"></i> PDF' },
                { extend: 'print', className: 'btn-print', text: '<i class="fas fa-print"></i> Print' },
                { extend: 'copy', className: 'btn-copy', text: '<i class="fas fa-copy"></i> Copy' },
                { extend: 'csv', className: 'btn-csv', text: '<i class="fas fa-file-csv"></i> CSV' }
            ],
            columnDefs: [{ orderable: false, targets: [9] }], // Actions column (now index 9 after adding academic year column)
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            initComplete: function(settings, json) {
                // Ensure buttons are visible after table initialization
                $('.dt-buttons').show();
            }
        });
        
        mainDataTableInitialized = true;
        
        //starts - Cascading filter functionality
        const classFilter = document.getElementById('classFilter');
        const studentFilter = document.getElementById('studentFilter');
        const subjectFilter = document.getElementById('subjectFilter');
        const yearFilter = document.getElementById('yearFilter');
        const clearFiltersBtn = document.getElementById('clearFilters');

        // Store original options for reset purposes
        const originalStudentOptions = studentFilter ? Array.from(studentFilter.options) : [];
        const originalSubjectOptions = subjectFilter ? Array.from(subjectFilter.options) : [];
        const originalYearOptions = yearFilter ? Array.from(yearFilter.options) : [];

        // Auto-select current academic year
        if (yearFilter) {
            const currentYearOption = yearFilter.querySelector('option[data-is-current="1"]');
            if (currentYearOption) {
                currentYearOption.selected = true;
                // Filter table by current academic year
                const yearName = currentYearOption.textContent;
                marksTable.columns(3).search(yearName).draw(); // Assuming academic year is column 3
            }
        }

        if (classFilter) {
            classFilter.addEventListener('change', function() {
                const classId = this.value;
                const className = this.options[this.selectedIndex].text;
                
                if (classId === '') {
                    // Clear class filter
                    marksTable.columns(1).search('').draw();
                    
                    // Reset student and subject filters to show all options
                    resetStudentFilter();
                    resetSubjectFilter();
                } else {
                    // Filter table by class name
                    marksTable.columns(1).search(className).draw();
                    
                    // Update student filter to show only students from selected class
                    updateStudentFilter(classId, className);
                    
                    // Update subject filter to show only subjects from selected class
                    updateSubjectFilter(classId, className);
                }
                
                // Clear student and subject selections when class changes
                if (studentFilter) studentFilter.value = '';
                if (subjectFilter) subjectFilter.value = '';
            });
        }

        if (studentFilter) {
            studentFilter.addEventListener('change', function() {
                const studentId = this.value;
                
                if (studentId === '') {
                    // Clear student filter but maintain class filter if active
                    marksTable.columns(0).search('').draw();
                } else {
                    // Filter by student name
                    const studentName = this.options[this.selectedIndex].text;
                    marksTable.columns(0).search(studentName).draw();
                }
            });
        }

        if (subjectFilter) {
            subjectFilter.addEventListener('change', function() {
                const subjectId = this.value;
                
                if (subjectId === '') {
                    // Clear subject filter but maintain other filters
                    marksTable.columns(2).search('').draw();
                } else {
                    // Filter by subject name
                    const subjectName = this.options[this.selectedIndex].text;
                    marksTable.columns(2).search(subjectName).draw();
                }
            });
        }

        if (yearFilter) {
            yearFilter.addEventListener('change', function() {
                const yearId = this.value;
                
                if (yearId === '') {
                    // Clear year filter
                    marksTable.columns(3).search('').draw();
                } else {
                    // Filter by academic year name
                    const yearName = this.options[this.selectedIndex].text;
                    marksTable.columns(3).search(yearName).draw();
                }
            });
        }

        // Clear all filters button
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                // Reset all filter dropdowns
                classFilter.value = '';
                if (studentFilter) studentFilter.value = '';
                if (subjectFilter) subjectFilter.value = '';
                if (yearFilter) yearFilter.value = '';
                
                // Clear all table filters
                marksTable.columns(0).search('').draw(); // Student column
                marksTable.columns(1).search('').draw(); // Class column  
                marksTable.columns(2).search('').draw(); // Subject column
                marksTable.columns(3).search('').draw(); // Academic Year column
                
                // Reset dropdown options
                resetStudentFilter();
                resetSubjectFilter();
                resetYearFilter();
                
                // Re-select current academic year
                if (yearFilter) {
                    const currentYearOption = yearFilter.querySelector('option[data-is-current="1"]');
                    if (currentYearOption) {
                        currentYearOption.selected = true;
                        // Filter table by current academic year
                        const yearName = currentYearOption.textContent;
                        marksTable.columns(3).search(yearName).draw();
                    }
                }
            });
        }

        // Function to update student filter based on selected class
        function updateStudentFilter(classId, className) {
            if (!studentFilter) return;
            
            // Clear current options except the first one (placeholder)
            studentFilter.innerHTML = '<option value="">Select a Student</option>';
            
            // Get students from the selected class from table data
            const studentsInClass = new Set();
            marksTable.rows().every(function(rowIdx, tableLoop, rowLoop) {
                const rowData = this.data();
                // Assuming class is in column 1 (index 1)
                if (rowData[1] === className) {
                    // Assuming student name is in column 0 (index 0)
                    studentsInClass.add(rowData[0]);
                }
            });
            
            // Add students to dropdown
            studentsInClass.forEach(studentName => {
                const option = document.createElement('option');
                // You might need to adjust this to get the actual student ID
                option.value = studentName; // or use student ID if available
                option.textContent = studentName;
                studentFilter.appendChild(option);
            });
        }

        // Function to update subject filter based on selected class
        function updateSubjectFilter(classId, className) {
            if (!subjectFilter) return;
            
            // Clear current options except the first one (placeholder)
            subjectFilter.innerHTML = '<option value="">Select a Subject</option>';
            
            // Get subjects from the selected class from table data
            const subjectsInClass = new Set();
            marksTable.rows().every(function(rowIdx, tableLoop, rowLoop) {
                const rowData = this.data();
                // Assuming class is in column 1 (index 1)
                if (rowData[1] === className) {
                    // Assuming subject name is in column 2 (index 2)
                    subjectsInClass.add(rowData[2]);
                }
            });
            
            // Add subjects to dropdown
            subjectsInClass.forEach(subjectName => {
                const option = document.createElement('option');
                // You might need to adjust this to get the actual subject ID
                option.value = subjectName; // or use subject ID if available
                option.textContent = subjectName;
                subjectFilter.appendChild(option);
            });
        }

        // Function to reset student filter to original options
        function resetStudentFilter() {
            if (!studentFilter) return;
            
            studentFilter.innerHTML = '';
            originalStudentOptions.forEach(option => {
                studentFilter.appendChild(option.cloneNode(true));
            });
        }

        // Function to reset subject filter to original options
        function resetSubjectFilter() {
            if (!subjectFilter) return;
            
            subjectFilter.innerHTML = '';
            originalSubjectOptions.forEach(option => {
                subjectFilter.appendChild(option.cloneNode(true));
            });
        }


        // Function to reset year filter to original options
        function resetYearFilter() {
            if (!yearFilter) return;
            
            yearFilter.innerHTML = '';
            originalYearOptions.forEach(option => {
                yearFilter.appendChild(option.cloneNode(true));
            });
        }
        //end
    }

    // editevent
       // Setup edit mark buttons
    setupEditMarkButtons();
    
    // Close modal logic for edit modal
    document.querySelectorAll('#editMarkModal .close, #editMarkModal .btn-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editMarkModal').style.display = 'none';
        });
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === document.getElementById('editMarkModal')) {
            document.getElementById('editMarkModal').style.display = 'none';
        }
    });
      const openScoresBtn = document.getElementById('viewScoresBtn');
    if (openScoresBtn && viewScoresModal) {
        openScoresBtn.addEventListener('click', () => {
            viewScoresModal.style.display = 'block';
            
            // Initialize tables first
            setTimeout(() => {
                initScoresDataTables();
            }, 100);
            
            // Then initialize edit buttons after tables are ready
            setTimeout(() => {
                initializeEditButtons();
            }, 800); // Give more time for DataTables to render
        });
    }

    if (closeScoresBtn) {
        closeScoresBtn.addEventListener('click', () => {
            viewScoresModal.style.display = 'none';
            destroyScoresDataTables();
        });
    }

    if (viewScoresModal) {
        window.addEventListener('click', (e) => {
            if (e.target === viewScoresModal) {
                viewScoresModal.style.display = 'none';
                destroyScoresDataTables();
            }
        });
    }

 document.querySelectorAll('.tab-nav li').forEach(tab => {
    tab.addEventListener('click', function() {
        const activeTabId = this.dataset.tab;
        
        // Remove active class from all tabs and panes
        document.querySelectorAll('.tab-nav li').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        // Add active class to clicked tab and corresponding pane
        this.classList.add('active');
        document.getElementById(activeTabId).classList.add('active');

        // Redraw DataTable for the active tab
        if (scoresDataTables[activeTabId]) {
            setTimeout(() => {
                scoresDataTables[activeTabId].columns.adjust().responsive.recalc();
                const buttonWrapper = $(`#${activeTabId} .dt-buttons`);
                if (buttonWrapper.length) {
                    buttonWrapper.show();
                }
                
                // CRITICAL: Reinitialize edit buttons for the new active tab
                setTimeout(() => {
                    initializeEditButtons();
                }, 200);
            }, 150);
        }
    });
});

    // Rest of your existing code (addMarkModal, classSelect, etc.)...
    const addMarkModal = document.getElementById('addMarkModal');
    const markTypeSelect = document.getElementById('mark_type');
    const subjectFieldsContainer = document.getElementById('subject-fields-container');
    const addSubjectBtn = document.getElementById('addSubjectBtn');
    const marksForm = document.getElementById('marksForm');
    const classSelect = document.getElementById('class_id');
    const studentSelect = document.getElementById('student_id');
    const yearSelect = document.getElementById('academic_year_id');
    
    // Auto-select current academic year in add mark form
    if (yearSelect) {
        const currentYearOption = yearSelect.querySelector('option[data-is-current="1"]');
        if (currentYearOption) {
            currentYearOption.selected = true;
        }
    }
    
    // Handle 'Add Marks' button click
    if (document.getElementById('addMarkBtn')) {
        document.getElementById('addMarkBtn').addEventListener('click', function() {
            addMarkModal.style.display = 'block';
            // Clear form
            marksForm.reset();
            subjectFieldsContainer.style.display = 'none';
            addSubjectBtn.style.display = 'none';
            subjectFieldsContainer.innerHTML = '';
            studentSelect.disabled = true;
            studentSelect.innerHTML = '<option value="">First select a class</option>';
            
            // Auto-select current academic year
            if (yearSelect) {
                const currentYearOption = yearSelect.querySelector('option[data-is-current="1"]');
                if (currentYearOption) {
                    currentYearOption.selected = true;
                }
            }
        });
    }

    // Handle Class selection change to filter students
    if (classSelect) {
        classSelect.addEventListener('change', function() {
            const classId = this.value;
            const yearId = yearSelect ? yearSelect.value : '';
            studentSelect.innerHTML = '<option value="">Loading...</option>';
            studentSelect.disabled = true;
            
            if (classId && yearId) {
                // Fetch students for the selected class and academic year via AJAX
                fetch(`marks_control.php?action=get_students_by_class&class_id=${classId}&academic_year_id=${yearId}`)
                    .then(response => response.json())
                    .then(data => {
                        studentSelect.innerHTML = '<option value="">Select Student</option>';
                        
                        if (data.status === 'success' && data.students.length > 0) {
                            data.students.forEach(student => {
                                const option = document.createElement('option');
                                option.value = student.student_id;
                                option.textContent = `${student.first_name} ${student.last_name}`;
                                studentSelect.appendChild(option);
                            });
                            studentSelect.disabled = false;
                        } else {
                            studentSelect.innerHTML = '<option value="">No students found in this class</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching students:', error);
                        studentSelect.innerHTML = '<option value="">Error loading students</option>';
                        showMessage('Error loading students for the selected class.', 'alert-danger');
                    });
            } else {
                studentSelect.disabled = true;
                studentSelect.innerHTML = '<option value="">First select a class and academic year</option>';
            }
        });
    }

    // Handle Academic Year selection change to filter students
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            const classId = classSelect ? classSelect.value : '';
            const yearId = this.value;
            
            if (classId && yearId) {
                // Trigger the class change event to reload students
                const event = new Event('change');
                classSelect.dispatchEvent(event);
            }
        });
    }

    // Handle Mark Type change
    if (markTypeSelect) {
        markTypeSelect.addEventListener('change', function() {
            if (this.value) {
                subjectFieldsContainer.style.display = 'block';
                addSubjectBtn.style.display = 'inline-block';
                // Clear previous fields and add one new one
                subjectFieldsContainer.innerHTML = '';
                addSubjectInput();
            } else {
                subjectFieldsContainer.style.display = 'none';
                addSubjectBtn.style.display = 'none';
            }
        });
    }

    // Handle 'Add More Subject' button click
    if (addSubjectBtn) {
        addSubjectBtn.addEventListener('click', addSubjectInput);
    }

    function addSubjectInput() {
        const newSubjectRow = document.createElement('div');
        newSubjectRow.classList.add('form-group-row');
        newSubjectRow.innerHTML = `
            <div class="form-group subject-group">
                <label>Subject</label>
                <select name="subject_id[]" class="subject-select" required>
                    <option value="">Select Subject</option>
                    ${subjectsData.map(subject => `<option value="${subject.subject_id}">${subject.subject_name}</option>`).join('')}
                </select>
            </div>
            <div class="marks-container" style="display:none;">
                <div class="marks-fields">
                    <div class="form-group">
                        <label>Mark 1</label>
                        <input type="number" name="mark1[]" min="0" max="100" step="0.01" class="mark-input" data-mark="1">
                    </div>
                    <div class="form-group">
                        <label>Mark 2</label>
                        <input type="number" name="mark2[]" min="0" max="100" step="0.01" class="mark-input" data-mark="2">
                    </div>
                    <div class="form-group">
                        <label>Mark 3</label>
                        <input type="number" name="mark3[]" min="0" max="100" step="0.01" class="mark-input" data-mark="3">
                    </div>
                </div>
                <div class="marks-controls">
                    <button type="button" class="btn-add-mark" title="Add More Mark">
                        <i class="fas fa-plus"></i> Add Mark
                    </button>
                    <button type="button" class="btn-calculate" title="Calculate Total">
                        <i class="fas fa-calculator"></i> Calculate
                    </button>
                </div>
                <div class="total-display">
                    <label>Total Marks</label>
                    <input type="number" name="total_marks[]" class="total-marks" readonly>
                </div>
            </div>
            <button type="button" class="btn-remove btn-icon" title="Remove Subject">
                <i class="fas fa-times-circle"></i>
            </button>
        `;
        subjectFieldsContainer.appendChild(newSubjectRow);

        // Add event listener to the new subject select
        const newSubjectSelect = newSubjectRow.querySelector('.subject-select');
        newSubjectSelect.addEventListener('change', function() {
            const marksContainer = newSubjectRow.querySelector('.marks-container');
            if (this.value) {
                marksContainer.style.display = 'block';
            } else {
                marksContainer.style.display = 'none';
            }
        });

        // Add event listeners for mark calculations
        setupMarkCalculation(newSubjectRow);
        setupAddMoreMarks(newSubjectRow);
    }

    function setupMarkCalculation(row) {
        const markInputs = row.querySelectorAll('.mark-input');
        const totalInput = row.querySelector('.total-marks');
        const calculateBtn = row.querySelector('.btn-calculate');

        // Auto-calculate on input change
        markInputs.forEach(input => {
            input.addEventListener('input', () => calculateTotal(row));
        });

        // Manual calculate button
        calculateBtn.addEventListener('click', () => calculateTotal(row));

        function calculateTotal(row) {
            const inputs = row.querySelectorAll('.mark-input');
            let total = 0;
            let hasValues = false;

            inputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                if (value > 0) hasValues = true;
                total += value;
            });

            totalInput.value = hasValues ? total.toFixed(2) : '';
        }
    }

    function setupAddMoreMarks(row) {
        const addMarkBtn = row.querySelector('.btn-add-mark');
        const marksFields = row.querySelector('.marks-fields');
        let markCount = 3; // We start with 3 marks

        addMarkBtn.addEventListener('click', () => {
            if (markCount < 10) { // Limit to 10 marks maximum
                markCount++;
                const newMarkField = document.createElement('div');
                newMarkField.className = 'form-group';
                newMarkField.innerHTML = `
                    <label>Mark ${markCount} <button type="button" class="btn-remove-mark" data-mark="${markCount}">×</button></label>
                    <input type="number" name="mark${markCount}[]" min="0" max="100" step="0.01" class="mark-input" data-mark="${markCount}">
                `;
                marksFields.appendChild(newMarkField);

                // Add event listener for new mark input
                const newInput = newMarkField.querySelector('.mark-input');
                newInput.addEventListener('input', () => calculateTotal(row));

                // Add event listener for remove mark button
                const removeMarkBtn = newMarkField.querySelector('.btn-remove-mark');
                removeMarkBtn.addEventListener('click', () => {
                    newMarkField.remove();
                    calculateTotal(row);
                });

                // Recalculate total
                calculateTotal(row);
            } else {
                showMessage('Maximum 10 marks allowed per subject.', 'alert-warning');
            }
        });

        function calculateTotal(row) {
            const inputs = row.querySelectorAll('.mark-input');
            const totalInput = row.querySelector('.total-marks');
            let total = 0;
            let hasValues = false;

            inputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                if (value > 0) hasValues = true;
                total += value;
            });

            totalInput.value = hasValues ? total.toFixed(2) : '';
        }
    }
    
    // Handle remove subject button click
    if (subjectFieldsContainer) {
        subjectFieldsContainer.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove')) {
                const row = e.target.closest('.form-group-row');
                if (subjectFieldsContainer.children.length > 1) {
                    row.remove();
                } else {
                    showMessage('At least one subject is required.', 'alert-warning');
                }
            }
        });
    }

    // Handle form submission with AJAX
    if (marksForm) {
        marksForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate that at least one subject is selected
            const subjectSelects = this.querySelectorAll('.subject-select');
            let hasValidSubject = false;
            
            subjectSelects.forEach(select => {
                if (select.value) {
                    hasValidSubject = true;
                }
            });
            
            if (!hasValidSubject) {
                showMessage('Please select at least one subject.', 'alert-warning');
                return;
            }

            // Validate that each selected subject has at least one mark
            const rows = subjectFieldsContainer.querySelectorAll('.form-group-row');
            let allSubjectsHaveMarks = true;
            
            rows.forEach(row => {
                const subjectSelect = row.querySelector('.subject-select');
                const markInputs = row.querySelectorAll('.mark-input');
                
                if (subjectSelect.value) {
                    let hasMarks = false;
                    markInputs.forEach(input => {
                        if (input.value && parseFloat(input.value) > 0) {
                            hasMarks = true;
                        }
                    });
                    
                    if (!hasMarks) {
                        allSubjectsHaveMarks = false;
                    }
                }
            });
            
            if (!allSubjectsHaveMarks) {
                showMessage('Please enter at least one mark for each selected subject.', 'alert-warning');
                return;
            }
            
            const formData = new FormData(this);
            
            // Add a loading state
            const submitBtn = this.querySelector('.btn-submit');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage(data.message, 'alert-success');
                    addMarkModal.style.display = 'none';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message, 'alert-danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An unexpected error occurred.', 'alert-danger');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    // Close modal logic
    document.querySelectorAll('.close, .btn-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            if (addMarkModal) addMarkModal.style.display = 'none';
        });
    });

    if (addMarkModal) {
        window.addEventListener('click', (e) => {
            if (e.target === addMarkModal) {
                addMarkModal.style.display = 'none';
            }
        });
    }

    // A simple function to display alert messages
    function showMessage(message, type) {
        let messageContainer = document.getElementById('message-container');
        if (!messageContainer) {
            messageContainer = document.createElement('div');
            messageContainer.id = 'message-container';
            messageContainer.style.position = 'fixed';
            messageContainer.style.top = '20px';
            messageContainer.style.right = '20px';
            messageContainer.style.zIndex = '9999';
            document.body.appendChild(messageContainer);
        }
        
        messageContainer.innerHTML = `<div class="alert ${type}">${message}</div>`;
        setTimeout(() => {
            messageContainer.innerHTML = '';
        }, 5000);
    }
    
    // Setup the new modal functionality
    setupDeleteMarkButtons();
    setupViewMarkButtons();
    
    // Handle delete form submission
    if (deleteMarkForm) {
        deleteMarkForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('.btn-danger');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Deleting...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showMessage(data.message, 'alert-success');
                    deleteMarkModal.style.display = 'none';
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message, 'alert-danger');
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An unexpected error occurred.', 'alert-danger');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
    
    // Close modal logic for the new modals
    document.querySelectorAll('#deleteMarkModal .close, #deleteMarkModal .btn-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            if (deleteMarkModal) deleteMarkModal.style.display = 'none';
        });
    });
    
    document.querySelectorAll('#viewMarkModal .close, #viewMarkModal .btn-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            if (viewMarkModal) viewMarkModal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', (e) => {
        if (e.target === deleteMarkModal) {
            deleteMarkModal.style.display = 'none';
        }
        if (e.target === viewMarkModal) {
            viewMarkModal.style.display = 'none';
        }
    });
});

// Update your initScoresDataTables function to include edit button setup
function initScoresDataTables() {
    const tables = {
        'midterm-tab': '#midterm-tab table',
        'class-tab': '#class-tab table', 
        'exam-tab': '#exam-tab table'
    };

    // Destroy existing tables first
    destroyScoresDataTables();

    for (const [tabId, selector] of Object.entries(tables)) {
        const tableElement = $(selector);
        if (tableElement.length) {
            // Initialize DataTable with proper button configuration
            scoresDataTables[tabId] = tableElement.DataTable({
                responsive: true,
                dom: '<"datatable-header"<"export-buttons"B><"search-box"f>>rt<"datatable-footer"<"info"i><"pagination"p>><"clear">',
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                buttons: [
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn-excel export-btn',
                        exportOptions: { 
                            columns: ':not(:last-child)' 
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn-pdf export-btn',
                        exportOptions: { 
                            columns: ':not(:last-child)' 
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print',
                        className: 'btn-print export-btn',
                        exportOptions: { 
                            columns: ':not(:last-child)' 
                        }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn-csv export-btn',
                        exportOptions: { 
                            columns: ':not(:last-child)' 
                        }
                    },
                    {
                        extend: 'copy',
                        text: '<i class="fas fa-copy"></i> Copy',
                        className: 'btn-copy export-btn',
                        exportOptions: { 
                            columns: ':not(:last-child)' 
                        }
                    }
                ],
                initComplete: function(settings, json) {
                    // Add custom filters
                    addCustomFilters(this.api(), tabId);
                    
                    // Ensure buttons are visible
                    setTimeout(() => {
                        $(selector).closest('.tab-pane').find('.dt-buttons').show();
                        $(selector).closest('.tab-pane').find('.export-btn').show();
                    }, 50);
                }
            });
        }
    }
    
    // Show buttons for the initially active tab
    setTimeout(() => {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            $(activeTab).find('.dt-buttons').show();
        }
        
        // IMPORTANT: Initialize edit buttons after all tables are created
        initializeEditButtons();
    }, 100);
}

// Destroy DataTables to prevent conflicts - UPDATED
function destroyScoresDataTables() {
    for (const [tabId, dataTable] of Object.entries(scoresDataTables)) {
        if (dataTable && $.fn.DataTable.isDataTable(dataTable.table().node())) {
            try {
                dataTable.destroy();
            } catch (error) {
                console.warn('Error destroying DataTable:', error);
            }
        }
    }
    scoresDataTables = {};
    
    // Clean up any orphaned button containers
    $('.dataTables-filter-container').remove();
}

// Add custom filters (class, year, term) - SIMPLIFIED VERSION
// Simplified version - add filters to the tab content directly
function addCustomFilters(dataTable, tabId) {
    try {
        const tabContent = document.getElementById(tabId);
        if (!tabContent) return;

        // Check if filters already exist
        if (tabContent.querySelector('.dataTables-filter-container')) {
            return;
        }

        const filterContainer = document.createElement('div');
        filterContainer.className = 'dataTables-filter-container';
        filterContainer.innerHTML = `
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-class-${tabId}">Class:</label>
                    <select id="filter-class-${tabId}" class="filter-select">
                        <option value="">All Classes</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-year-${tabId}">Academic Year:</label>
                    <select id="filter-year-${tabId}" class="filter-select">
                        <option value="">All Years</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-term-${tabId}">Term:</label>
                    <select id="filter-term-${tabId}" class="filter-select">
                        <option value="">All Terms</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-subject-${tabId}">Subject:</label>
                    <select id="filter-subject-${tabId}" class="filter-select">
                        <option value="">All Subjects</option>
                    </select>
                </div>
                <button class="btn-clear-filters" data-table="${tabId}">Clear Filters</button>
            </div>
        `;
        
        // Insert the filter container at the top of the tab content
        tabContent.insertBefore(filterContainer, tabContent.firstChild);

        // Get the filter elements
        const classFilter = document.getElementById(`filter-class-${tabId}`);
        const yearFilter = document.getElementById(`filter-year-${tabId}`);
        const termFilter = document.getElementById(`filter-term-${tabId}`);
        const subjectFilter = document.getElementById(`filter-subject-${tabId}`);
        const clearButton = tabContent.querySelector(`button[data-table="${tabId}"]`);

        // Populate filters with data from the table
        populateFiltersFromTable(dataTable, classFilter, yearFilter, termFilter, subjectFilter);

        // Add event listeners
        if (classFilter) classFilter.addEventListener('change', () => dataTable.column(2).search(classFilter.value).draw());
        if (yearFilter) yearFilter.addEventListener('change', () => dataTable.column(3).search(yearFilter.value).draw());
        if (termFilter) termFilter.addEventListener('change', () => dataTable.column(4).search(termFilter.value).draw());
        if (subjectFilter) subjectFilter.addEventListener('change', () => dataTable.column(1).search(subjectFilter.value).draw());
        if (clearButton) clearButton.addEventListener('click', () => {
            if (classFilter) classFilter.value = '';
            if (yearFilter) yearFilter.value = '';
            if (termFilter) termFilter.value = '';
            if (subjectFilter) subjectFilter.value = '';
            dataTable.columns().search('').draw();
        });
    } catch (error) {
        console.error('Error in addCustomFilters:', error);
    }
}

// New function to populate filters from table data
function populateFiltersFromTable(dataTable, classFilter, yearFilter, termFilter, subjectFilter) {
    // Get unique values from each column
    const uniqueClasses = new Set();
    const uniqueYears = new Set();
    const uniqueTerms = new Set();
    const uniqueSubjects = new Set();
    
    // Extract data from all table rows
    dataTable.rows().every(function(rowIdx, tableLoop, rowLoop) {
        const rowData = this.data();
        
        // Assuming column indices: 0=Student, 1=Subject, 2=Class, 3=Academic Year, 4=Term
        if (rowData[2]) uniqueClasses.add(rowData[2]);    // Class column
        if (rowData[3]) uniqueYears.add(rowData[3]);      // Academic Year column
        if (rowData[4]) uniqueTerms.add(rowData[4]);      // Term column
        if (rowData[1]) uniqueSubjects.add(rowData[1]);   // Subject column
    });
    
    // Populate class filter
    if (classFilter) {
        uniqueClasses.forEach(className => {
            const option = document.createElement('option');
            option.value = className;
            option.textContent = className;
            classFilter.appendChild(option);
        });
    }
    
    // Populate academic year filter
    if (yearFilter) {
        uniqueYears.forEach(yearName => {
            const option = document.createElement('option');
            option.value = yearName;
            option.textContent = yearName;
            yearFilter.appendChild(option);
        });
    }
    
    // Populate term filter
    if (termFilter) {
        uniqueTerms.forEach(termName => {
            const option = document.createElement('option');
            option.value = termName;
            option.textContent = termName;
            termFilter.appendChild(option);
        });
    }
    
    // Populate subject filter
    if (subjectFilter) {
        uniqueSubjects.forEach(subjectName => {
            const option = document.createElement('option');
            option.value = subjectName;
            option.textContent = subjectName;
            subjectFilter.appendChild(option);
        });
    }
}

// Add this function to handle delete mark clicks
function setupDeleteMarkButtons() {
    document.querySelectorAll('.delete-mark').forEach(btn => {
        btn.addEventListener('click', function() {
            const studentId = this.getAttribute('data-student-id');
            const subjectId = this.getAttribute('data-subject-id');
            const termId = this.getAttribute('data-term-id');
            const yearId = this.getAttribute('data-year-id');
            
            // Set the values in the delete form
            document.getElementById('delete_student_id').value = studentId;
            document.getElementById('delete_subject_id').value = subjectId;
            document.getElementById('delete_term_id').value = termId;
            document.getElementById('delete_year_id').value = yearId;
            
            // Try to determine mark type for better confirmation message
            const row = this.closest('tr');
            const midMarks = parseFloat(row.cells[5].textContent) || 0; // Adjusted for academic year column
            const classMarks = parseFloat(row.cells[6].textContent) || 0;
            const examMarks = parseFloat(row.cells[7].textContent) || 0;
            
            let markType = 'all';
            if (midMarks > 0 && classMarks === 0 && examMarks === 0) markType = 'midterm';
            else if (classMarks > 0 && midMarks === 0 && examMarks === 0) markType = 'class_score';
            else if (examMarks > 0 && midMarks === 0 && classMarks === 0) markType = 'exam_score';
            
            document.getElementById('delete_mark_type').value = markType;
            
            // Update confirmation text
            const studentName = row.cells[0].textContent; // Student Name column
            const className = row.cells[1].textContent; // Class column
            const subjectName = row.cells[2].textContent; // Subject column
            const yearName = row.cells[3].textContent; // Academic Year column
            const termName = row.cells[4].textContent; // Term column
            
            document.getElementById('deleteConfirmationText').textContent = 
                `Are you sure you want to delete ${markType} marks for ${studentName} in ${subjectName} (${termName}, ${yearName})?`;
            
            // Show the modal
            if (deleteMarkModal) deleteMarkModal.style.display = 'block';
        });
    });
}

// Add this function to handle view mark clicks
function setupViewMarkButtons() {
    document.querySelectorAll('.view-details').forEach(btn => {
        btn.addEventListener('click', function() {
            const studentId = this.getAttribute('data-student-id');
            const subjectId = this.getAttribute('data-subject-id');
            const termId = this.getAttribute('data-term-id');
            const yearId = this.getAttribute('data-year-id');
            
            // Show loading state
            marksDetailsContent.innerHTML = '<div class="loading-spinner">Loading marks details...</div>';
            
            // Show the modal
            if (viewMarkModal) viewMarkModal.style.display = 'block';
            
            // Load marks details via AJAX
            loadMarksDetails(studentId, subjectId, termId, yearId);
        });
    });
}

// Function to load marks details via AJAX
function loadMarksDetails(studentId, subjectId, termId, yearId) {
    const formData = new FormData();
    formData.append('action', 'get_marks_details');
    formData.append('student_id', studentId);
    formData.append('subject_id', subjectId);
    formData.append('term_id', termId);
    formData.append('academic_year_id', yearId);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('marks_control.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            displayMarksDetails(data.details);
        } else {
            marksDetailsContent.innerHTML = `
                <div class="alert alert-danger">
                    Error loading marks details: ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        marksDetailsContent.innerHTML = `
            <div class="alert alert-danger">
                An error occurred while loading marks details.
            </div>
        `;
    });
}

// Function to display marks details in the modal
function displayMarksDetails(details) {
    let html = `
        <div class="marks-details">
            <div class="detail-row">
                <span class="detail-label">Student:</span>
                <span class="detail-value">${details.student_name}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Subject:</span>
                <span class="detail-value">${details.subject_name}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Academic Year:</span>
                <span class="detail-value">${details.year_name}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Term:</span>
                <span class="detail-value">${details.term_name}</span>
            </div>
    `;
    
    // Add mark breakdown if available
    if (details.mark_breakdown && details.mark_breakdown.length > 0) {
        html += `
            <div class="mark-breakdown">
                <h4>Mark Breakdown</h4>
        `;
        
        let totalMarks = 0;
        details.mark_breakdown.forEach((mark, index) => {
            html += `
                <div class="mark-item">
                    <span>Mark ${index + 1}:</span>
                    <span>${mark}</span>
                </div>
            `;
            totalMarks += parseFloat(mark) || 0;
        });
        
        html += `
                <div class="mark-total">
                    <span>Total Marks:</span>
                    <span>${totalMarks.toFixed(2)}</span>
                </div>
            </div>
        `;
    }
    
    html += `
            <div class="detail-row">
                <span class="detail-label">Midterm:</span>
                <span class="detail-value">${details.mid_marks}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Class Score:</span>
                <span class="detail-value">${details.class_marks}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Exam:</span>
                <span class="detail-value">${details.exam_marks}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Score:</span>
                <span class="detail-value"><strong>${details.total_marks}</strong></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Grade:</span>
                <span class="detail-value grade-badge grade-${details.grade.toLowerCase()}">${details.grade}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Remarks:</span>
                <span class="detail-value">${details.remark}</span>
            </div>
        </div>
    `;
    
    marksDetailsContent.innerHTML = html;
}

// Weight validation function
function validateWeights() {
    const mid = parseInt(document.getElementById("mid_weight").value) || 0;
    const classW = parseInt(document.getElementById("class_weight").value) || 0;
    const exam = parseInt(document.getElementById("exam_weight").value) || 0;

    const total = mid + classW + exam;
    const totalDisplay = document.getElementById("weight-total");
    const saveBtn = document.getElementById("saveBtn");

    if (totalDisplay) totalDisplay.textContent = "Total: " + total + "%";

    const inputs = [
        document.getElementById("mid_weight"),
        document.getElementById("class_weight"),
        document.getElementById("exam_weight")
    ];

    if (total !== 100) {
        inputs.forEach(el => { 
            if (el) {
                el.classList.add("invalid"); 
                el.classList.remove("valid"); 
            }
        });
        if (totalDisplay) {
            totalDisplay.classList.add("error");
            totalDisplay.classList.remove("ok");
        }
        if (saveBtn) saveBtn.disabled = true;
    } else {
        inputs.forEach(el => { 
            if (el) {
                el.classList.add("valid"); 
                el.classList.remove("invalid"); 
            }
        });
        if (totalDisplay) {
            totalDisplay.classList.add("ok");
            totalDisplay.classList.remove("error");
        }
        if (saveBtn) saveBtn.disabled = false;
    }
}

// Initialize weight validation
document.addEventListener("DOMContentLoaded", function() {
    validateWeights();
    document.querySelectorAll("#mid_weight, #class_weight, #exam_weight").forEach(el => {
        el.addEventListener("input", validateWeights);
    });
});

// Score modal functionality
document.addEventListener("DOMContentLoaded", function() {
    // Score modal functionality
    document.querySelectorAll('.view-scores').forEach(btn => {
        btn.addEventListener('click', function() {
            const studentName = this.getAttribute('data-student-name');
            const className = this.getAttribute('data-class-name');
            const subjectName = this.getAttribute('data-subject-name');
            const yearName = this.getAttribute('data-year-name');
            const termName = this.getAttribute('data-term-name');
            const midMarks = parseFloat(this.getAttribute('data-mid-marks')) || 0;
            const classMarks = parseFloat(this.getAttribute('data-class-marks')) || 0;
            const examMarks = parseFloat(this.getAttribute('data-exam-marks')) || 0;
            const totalMarks = parseFloat(this.getAttribute('data-total-marks')) || 0;
            const grade = this.getAttribute('data-grade');
            const rank = this.getAttribute('data-rank');
            const remark = this.getAttribute('data-remark');
            
            // Calculate weighted scores
            const midtermWeighted = (midMarks * midWeight / 100).toFixed(2);
            const classWeighted = (classMarks * classWeight / 100).toFixed(2);
            const examWeighted = (examMarks * examWeight / 100).toFixed(2);
            
            // Populate modal content
            document.getElementById('scoreStudentName').textContent = studentName;
            document.getElementById('scoreClassName').textContent = className;
            document.getElementById('scoreSubjectName').textContent = subjectName;
            document.getElementById('scoreYearName').textContent = yearName;
            document.getElementById('scoreTermName').textContent = termName;
            
            document.getElementById('midtermScore').textContent = midMarks;
            document.getElementById('classScore').textContent = classMarks;
            document.getElementById('examScore').textContent = examMarks;
            
            document.getElementById('midtermWeighted').textContent = midtermWeighted;
            document.getElementById('classWeighted').textContent = classWeighted;
            document.getElementById('examWeighted').textContent = examWeighted;
            
            document.getElementById('totalScoreDisplay').textContent = totalMarks;
            document.getElementById('scoreGrade').textContent = grade;
            document.getElementById('scoreRank').textContent = rank;
            document.getElementById('scoreRemark').textContent = remark;
            
            // Show modal
            document.getElementById('scoreModal').style.display = 'block';
        });
    });
    
    // Close modal when clicking on close button
    document.querySelectorAll('.modal .close').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    // Close modal when clicking outside of modal content
    window.addEventListener('click', function(event) {
        document.querySelectorAll('.modal').forEach(modal => {
            if (modal.style.display === 'block' && event.target === modal) {
                modal.style.display = 'none';
                // Clean up DataTables when closing scores modal
                if (modal.id === 'viewScoresModal') {
                    destroyScoresDataTables();
                }
            }
        });
    });
    
    // Close modal with cancel button
    document.querySelectorAll('.btn-cancel').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.style.display = 'none';
            // Clean up DataTables when closing scores modal
            if (modal && modal.id === 'viewScoresModal') {
                destroyScoresDataTables();
            }
        });
    });
});



//edit// Setup edit mark buttons
function setupEditMarkButtons() {
    document.querySelectorAll('.edit-mark').forEach(btn => {
        btn.addEventListener('click', function() {
            const markId = this.getAttribute('data-id');
            const markType = this.closest('.tab-pane').id.replace('-tab', '');
            
            // Load mark details via AJAX
            loadMarkDetails(markId, markType);
        });
    });

}

// Function to load mark details via AJAX
function loadMarkDetails(markId, markType) {
    const formData = new FormData();
    formData.append('action', 'get_mark_details');
    formData.append('mark_id', markId);
    formData.append('mark_type', markType);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch('marks_control.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            populateEditForm(data.details, markType);
            document.getElementById('editMarkModal').style.display = 'block';
        } else {
            showMessage('Error loading mark details: ' + data.message, 'alert-danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('An error occurred while loading mark details.', 'alert-danger');
    });
}

// Function to populate edit form
function populateEditForm(details, markType) {
    document.getElementById('edit_mark_id').value = details.id;
    document.getElementById('edit_mark_type').value = markType;
    document.getElementById('edit_student_name').value = details.first_name + ' ' + details.last_name;
    document.getElementById('edit_class_name').value = details.class_name;
    document.getElementById('edit_subject_name').value = details.subject_name;
    document.getElementById('edit_term_name').value = details.term;
    document.getElementById('edit_year_name').value = details.year_name || 'N/A';
    document.getElementById('edit_total_marks').value = details.total_marks;
}

// Handle edit form submission
if (document.getElementById('editMarkForm')) {
    document.getElementById('editMarkForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Updating...';
        submitBtn.disabled = true;
        
        const formData = new FormData(this);
        
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showMessage(data.message, 'alert-success');
                document.getElementById('editMarkModal').style.display = 'none';
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage(data.message, 'alert-danger');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An unexpected error occurred.', 'alert-danger');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });
}


function loadMarkDetails(markId, markType, additionalData = {}) {
    if (!markId || !markType) {
        showMessage('Missing required data for editing mark.', 'alert-danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'get_mark_details');
    formData.append('mark_id', markId);
    formData.append('mark_type', markType);
    
    // Add additional data if available
    if (additionalData.studentId) formData.append('student_id', additionalData.studentId);
    if (additionalData.subjectId) formData.append('subject_id', additionalData.subjectId);
    if (additionalData.termId) formData.append('term_id', additionalData.termId);
    if (additionalData.yearId) formData.append('academic_year_id', additionalData.yearId);
    
    // Get CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    if (csrfToken) {
        formData.append('csrf_token', csrfToken.value);
    } else {
        showMessage('Security token missing. Please refresh the page.', 'alert-danger');
        return;
    }
        
    // Get modal element - IMPORTANT CHECK
    const editModal = document.getElementById('editMarkModal');
    if (!editModal) {
        showMessage('Edit modal not found. Please refresh the page.', 'alert-danger');
        return;
    }
    
       // Show modal immediately with loading state
    editModal.style.display = 'block';
    const modalContent = editModal.querySelector('.modal-content');
    const originalContent = modalContent.innerHTML;
    
    modalContent.innerHTML = `
        <div class="loading-container" style="text-align: center; padding: 50px;">
            <div class="loading-spinner">Loading mark details...</div>
        </div>
    `;
    
    
       fetch('marks_control.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Mark details response:', data);
        
        // Restore original modal content first
        modalContent.innerHTML = originalContent;
        
        if (data.status === 'success') {
            // Populate the form
            populateEditForm(data.details, markType);
        } else {
            editModal.style.display = 'none';
            showMessage('Error loading mark details: ' + (data.message || 'Unknown error'), 'alert-danger');
        }
    })
    .catch(error => {
        console.error('Error loading mark details:', error);
        // Restore original content and hide modal
        modalContent.innerHTML = originalContent;
        editModal.style.display = 'none';
        showMessage('An error occurred while loading mark details: ' + error.message, 'alert-danger');
    });
}


// Improved populateEditForm function
function populateEditForm(details, markType) {
    console.log('Populating form with:', details, markType);
    
    // Basic form fields
    const fields = {
        'edit_mark_id': details.id,
        'edit_mark_type': markType,
        'edit_student_name': (details.first_name || '') + ' ' + (details.last_name || ''),
        'edit_class_name': details.class_name || 'N/A',
        'edit_subject_name': details.subject_name || 'N/A',
        'edit_term_name': details.term || 'N/A',
        'edit_year_name': details.year_name || 'N/A',
        'edit_total_marks': details.total_marks || 0
    };
    
    // Populate form fields
    for (const [fieldId, value] of Object.entries(fields)) {
        const element = document.getElementById(fieldId);
        if (element) {
            element.value = value;
        } else {
            console.warn(`Element with id '${fieldId}' not found`);
        }
    }
    
    // Focus on the editable field
    const totalMarksField = document.getElementById('edit_total_marks');
    if (totalMarksField) {
        setTimeout(() => totalMarksField.focus(), 100);
    }
}

function initializeEditButtons() {
    console.log('Initializing edit buttons...');
    
    // Check if we're in the scores modal context
    const viewScoresModal = document.getElementById('viewScoresModal');
    if (viewScoresModal && viewScoresModal.style.display === 'block') {
        // Wait for DataTables to fully initialize
        setTimeout(() => {
            setupEditMarkButtons();
            console.log('Edit buttons initialized for scores modal');
        }, 500); // Increased delay
    } else {
        // For regular page context
        setupEditMarkButtons();
        console.log('Edit buttons initialized for main page');
    }
}

// Update the tab switching code to reinitialize edit buttons
document.addEventListener('DOMContentLoaded', function() {
    // ... your existing code ...
    
    // Modified tab switching for view scores modal
    document.querySelectorAll('.tab-nav li').forEach(tab => {
        tab.addEventListener('click', function() {
            const activeTabId = this.dataset.tab;
            
            // Remove active class from all tabs and panes
            document.querySelectorAll('.tab-nav li').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

            // Add active class to clicked tab and corresponding pane
            this.classList.add('active');
            document.getElementById(activeTabId).classList.add('active');

            // Redraw DataTable for the active tab and ensure buttons are visible
            if (scoresDataTables[activeTabId]) {
                setTimeout(() => {
                    scoresDataTables[activeTabId].columns.adjust().responsive.recalc();
                    // Ensure export buttons are visible for this tab
                    const buttonWrapper = $(`#${activeTabId} .dt-buttons`);
                    if (buttonWrapper.length) {
                        buttonWrapper.show();
                    }
                    
                    // IMPORTANT: Reinitialize edit buttons for the new active tab
                    initializeEditButtons();
                }, 150);
            }
        });
    });
    
    // ... rest of your existing code ...
});

// Enhanced form submission with better error handling
document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.getElementById('editMarkForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('.btn-submit');
            const originalText = submitBtn.textContent;
            const totalMarksInput = document.getElementById('edit_total_marks');
            
            // Basic validation
            if (!totalMarksInput.value || parseFloat(totalMarksInput.value) < 0) {
                showMessage('Please enter a valid total marks value.', 'alert-warning');
                return;
            }
            
            // Show loading state
            submitBtn.textContent = 'Updating...';
            submitBtn.disabled = true;
            
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Edit form response:', data);
                
                if (data.status === 'success') {
                    showMessage(data.message || 'Mark updated successfully!', 'alert-success');
                    document.getElementById('editMarkModal').style.display = 'none';
                    
                    // Refresh the page or update the table
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message || 'Failed to update mark.', 'alert-danger');
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error updating mark:', error);
                showMessage('An unexpected error occurred: ' + error.message, 'alert-danger');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});




<!-- Add this at the top of your marks page, right after the opening body tag or in your header section -->
<div class="message-container">
    <?php if (isset($_SESSION['message'])): ?>
        <?php 
        $messageType = $_SESSION['message_type'] ?? 'info';
        $alertClass = '';
        
        switch ($messageType) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
                $alertClass = 'alert-danger';
                break;
            case 'info':
                $alertClass = 'alert-info';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
            default:
                $alertClass = 'alert-info';
        }
        ?>
        <div class="alert <?php echo $alertClass; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php 
        unset($_SESSION['message']); 
        unset($_SESSION['message_type']); 
        ?>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
</div>

<style>
/* Add this CSS for better message styling */
.message-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
}

.alert {
    margin-bottom: 10px;
    padding: 12px 15px;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert-info {
    background-color: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.alert .close {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    margin-left: auto;
    color: inherit;
    opacity: 0.7;
}

.alert .close:hover {
    opacity: 1;
}

.alert.fade.show {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Auto-hide messages after 5 seconds */
.alert-dismissible {
    animation: slideIn 0.3s ease-out, slideOut 0.3s ease-in 5s forwards;
}

@keyframes slideOut {
    to {
        transform: translateX(100%);
        opacity: 0;
        height: 0;
        margin: 0;
        padding: 0;
    }
}
</style>

<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        }, 5000);
    });
    
    // Handle manual close buttons
    const closeButtons = document.querySelectorAll('.alert .close');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const alert = this.closest('.alert');
            alert.style.animation = 'slideOut 0.3s ease-in forwards';
            setTimeout(function() {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 300);
        });
    });
});
</script>