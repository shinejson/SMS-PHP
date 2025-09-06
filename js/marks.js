// marks.js - Fixed version with proper mark type handling and delete/view functionality
class MarksManager {
    constructor() {
        this.modals = {};
        this.dataTables = {};
        this.cachedElements = {};
        this.scoresDataTables = {};
        this.mainDataTableInitialized = false;
        
        this.init();
    }

    init() {
        this.cacheElements();
        this.initModals();
        this.setupEventListeners();
        this.initMainDataTable();
    }

    cacheElements() {
        // Cache frequently used elements
        const elements = {
            'marksTable': '#marksTable',
            'addMarkModal': '#addMarkModal',
            'editMarkModal': '#editMarkModal',
            'deleteMarkModal': '#deleteMarkModal',
            'viewMarkModal': '#viewMarkModal',
            'viewScoresModal': '#viewScoresModal',
            'marksForm': '#marksForm',
            'deleteMarkForm': '#deleteMarkForm',
            'editMarkForm': '#editMarkForm',
            'marksDetailsContent': '#marksDetailsContent',
            'classFilter': '#classFilter',
            'studentFilter': '#studentFilter',
            'subjectFilter': '#subjectFilter',
            'yearFilter': '#yearFilter',
            'clearFilters': '#clearFilters',
            'addMarkBtn': '#addMarkBtn',
            'viewScoresBtn': '#viewScoresBtn',
            'closeScoresBtn': '#closeScoresBtn',
            'markTypeSelect': '#mark_type',
            'subjectFieldsContainer': '#subject-fields-container',
            'addSubjectBtn': '#addSubjectBtn',
            'classSelect': '#class_id',
            'studentSelect': '#student_id',
            'yearSelect': '#academic_year_id'
        };

        for (const [key, selector] of Object.entries(elements)) {
            this.cachedElements[key] = document.querySelector(selector);
        }

        // Cache filter dropdown original options
        this.cachedElements.originalStudentOptions = this.cachedElements.studentFilter ? 
            Array.from(this.cachedElements.studentFilter.options) : [];
        this.cachedElements.originalSubjectOptions = this.cachedElements.subjectFilter ? 
            Array.from(this.cachedElements.subjectFilter.options) : [];
        this.cachedElements.originalYearOptions = this.cachedElements.yearFilter ? 
            Array.from(this.cachedElements.yearFilter.options) : [];
    }

    initModals() {
        // Initialize all modals
        const modalIds = ['addMarkModal', 'editMarkModal', 'deleteMarkModal', 'viewMarkModal', 'viewScoresModal'];
        
        modalIds.forEach(id => {
            this.modals[id] = this.cachedElements[id];
        });
    }

    setupEventListeners() {
        // Use event delegation for dynamic elements
        document.addEventListener('click', this.handleDocumentClick.bind(this));
        
        // Form submissions
        if (this.cachedElements.marksForm) {
            this.cachedElements.marksForm.addEventListener('submit', this.handleMarksFormSubmit.bind(this));
        }
        
        if (this.cachedElements.deleteMarkForm) {
            this.cachedElements.deleteMarkForm.addEventListener('submit', this.handleDeleteMarkFormSubmit.bind(this));
        }
        
        if (this.cachedElements.editMarkForm) {
            this.cachedElements.editMarkForm.addEventListener('submit', this.handleEditMarkFormSubmit.bind(this));
        }

        // Filter events
        this.setupFilterEvents();
        
        // Mark type change
        if (this.cachedElements.markTypeSelect) {
            this.cachedElements.markTypeSelect.addEventListener('change', this.handleMarkTypeChange.bind(this));
        }
        
        // Add subject button
        if (this.cachedElements.addSubjectBtn) {
            this.cachedElements.addSubjectBtn.addEventListener('click', this.addSubjectInput.bind(this));
        }
        
        // Class and year selection changes
        if (this.cachedElements.classSelect) {
            this.cachedElements.classSelect.addEventListener('change', this.handleClassChange.bind(this));
        }
        
        if (this.cachedElements.yearSelect) {
            this.cachedElements.yearSelect.addEventListener('change', this.handleYearChange.bind(this));
        }
        
        // View scores button
        if (this.cachedElements.viewScoresBtn && this.modals.viewScoresModal) {
            this.cachedElements.viewScoresBtn.addEventListener('click', this.handleViewScoresClick.bind(this));
        }
        
        // Close scores button
        if (this.cachedElements.closeScoresBtn) {
            this.cachedElements.closeScoresBtn.addEventListener('click', this.hideScoresModal.bind(this));
        }
        
        // Tab navigation
        document.querySelectorAll('.tab-nav li').forEach(tab => {
            tab.addEventListener('click', this.handleTabClick.bind(this));
        });

        // Weight validation
        const weightInputs = ["mid_weight", "class_weight", "exam_weight"];
        weightInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener("input", this.validateWeights.bind(this));
            }
        });

        // Initial validation on page load
        setTimeout(() => {
            this.validateWeights();
        }, 100);
    }

    handleDocumentClick(e) {
        // Handle edit mark buttons
        if (e.target.closest('.edit-mark')) {
            const btn = e.target.closest('.edit-mark');
            const markId = btn.getAttribute('data-id');
            
            // Extract additional data from button attributes
            const additionalData = {
                studentId: btn.getAttribute('data-student-id'),
                subjectId: btn.getAttribute('data-subject-id'), 
                termId: btn.getAttribute('data-term-id'),
                yearId: btn.getAttribute('data-year-id')
            };
            
            // Determine mark type based on context
            let markType = this.determineMarkType(btn);
            
            this.loadMarkDetails(markId, markType, additionalData);
            return;
        }
        
        // Handle delete mark buttons
        if (e.target.closest('.delete-mark')) {
            const btn = e.target.closest('.delete-mark');
            this.handleDeleteMarkClick(btn);
            return;
        }
        
        // Handle view details buttons
        if (e.target.closest('.view-details')) {
            const btn = e.target.closest('.view-details');
            this.handleViewDetailsClick(btn);
            return;
        }
        
        // Handle view mark buttons (in raw scores modal)
        if (e.target.closest('.view-mark')) {
            const btn = e.target.closest('.view-mark');
            this.handleViewMarkClick(btn);
            return;
        }
        
        // Handle remove subject buttons
        if (e.target.closest('.btn-remove') && this.cachedElements.subjectFieldsContainer) {
            const btn = e.target.closest('.btn-remove');
            this.handleRemoveSubjectClick(btn);
            return;
        }
        
        // Handle calculate buttons
        if (e.target.closest('.btn-calculate')) {
            const btn = e.target.closest('.btn-calculate');
            this.calculateTotal(btn.closest('.form-group-row'));
            return;
        }
        
        // Handle add mark buttons
        if (e.target.closest('.btn-add-mark')) {
            const btn = e.target.closest('.btn-add-mark');
            this.handleAddMarkClick(btn);
            return;
        }
        
        // Handle remove mark buttons
        if (e.target.closest('.btn-remove-mark')) {
            const btn = e.target.closest('.btn-remove-mark');
            btn.closest('.form-group').remove();
            this.calculateTotal(btn.closest('.form-group-row'));
            return;
        }
        
        // Handle modal background clicks
        if (e.target.classList.contains('modal')) {
            this.hideModal(e.target.id);
            
            // Clean up DataTables when closing scores modal
            if (e.target.id === 'viewScoresModal') {
                this.destroyScoresDataTables();
            }
            return;
        }
        
        // Handle close buttons
        if (e.target.classList.contains('close') || e.target.closest('.close')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                this.hideModal(modal.id);
                
                // Clean up DataTables when closing scores modal
                if (modal.id === 'viewScoresModal') {
                    this.destroyScoresDataTables();
                }
            }
            return;
        }
        
        // Handle cancel buttons
        if (e.target.classList.contains('btn-cancel') || e.target.closest('.btn-cancel')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                this.hideModal(modal.id);
                
                // Clean up DataTables when closing scores modal
                if (modal.id === 'viewScoresModal') {
                    this.destroyScoresDataTables();
                }
            }
            return;
        }
        
        // Handle add mark button
        if (e.target.id === 'addMarkBtn' || e.target.closest('#addMarkBtn')) {
            this.showAddMarkModal();
            return;
        }
    }

    // NEW: Determine mark type based on context
// FIXED: Determine mark type based on context
determineMarkType(btn) {
    let markType = '';
    
    // If we're in the scores modal, get from active tab
    if (this.modals.viewScoresModal && this.modals.viewScoresModal.style.display === 'block') {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            if (tabId === 'midterm-tab') {
                markType = 'midterm';
            } else if (tabId === 'class-tab') {
                markType = 'class_score'; // Changed from 'class_score' to match server
            } else if (tabId === 'exam-tab') {
                markType = 'exam_score'; // Changed from 'exam_score' to match server
            }
        }
    } else {
        // For regular page, get from tab pane if available
        const tabPane = btn.closest('.tab-pane');
        if (tabPane) {
            const tabId = tabPane.id;
            if (tabId === 'midterm-tab') {
                markType = 'midterm';
            } else if (tabId === 'class-tab') {
                markType = 'class_score';
            } else if (tabId === 'exam-tab') {
                markType = 'exam_score';
            }
        }
    }
    
    console.log('Determined mark type:', markType); // Debug log
    return markType;
}

    // UPDATED: Handle delete mark click with proper mark type detection
    handleDeleteMarkClick(btn) {
        const markId = btn.getAttribute('data-id');
        const studentId = btn.getAttribute('data-student-id');
        const subjectId = btn.getAttribute('data-subject-id');
        const termId = btn.getAttribute('data-term-id');
        const yearId = btn.getAttribute('data-year-id');
        
        // Determine mark type based on context
        let markType = this.determineMarkType(btn);
        
        // Set the values in the delete form
        const deleteForm = document.getElementById('deleteMarkForm');
        if (deleteForm) {
            // Set hidden fields
            const fields = {
                'delete_mark_id': markId,
                'delete_student_id': studentId,
                'delete_subject_id': subjectId,
                'delete_term_id': termId,
                'delete_year_id': yearId,
                'delete_mark_type': markType
            };
            
            for (const [fieldId, value] of Object.entries(fields)) {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.value = value || '';
                }
            }
            
            // Update confirmation text
            const confirmationText = document.getElementById('deleteConfirmationText');
            if (confirmationText) {
                const markTypeText = this.getMarkTypeDisplayName(markType);
                confirmationText.textContent = `Are you sure you want to delete this ${markTypeText} mark?`;
            }
            
            this.showModal('deleteMarkModal');
        }
    }

    // NEW: Handle view mark click (for raw scores modal)
    handleViewMarkClick(btn) {
        const markId = btn.getAttribute('data-id');
        const studentId = btn.getAttribute('data-student-id');
        const subjectId = btn.getAttribute('data-subject-id');
        const termId = btn.getAttribute('data-term-id');
        const yearId = btn.getAttribute('data-year-id');
        
        // Determine mark type
        const markType = this.determineMarkType(btn);
        
        // Load mark details for viewing
        this.loadMarkDetailsForView(markId, markType, {
            studentId, subjectId, termId, yearId
        });
    }

    // NEW: Load mark details for view modal
    async loadMarkDetailsForView(markId, markType, additionalData) {
        const formData = new FormData();
        formData.append('action', 'get_mark_details');
        formData.append('mark_id', markId);
        formData.append('mark_type', this.convertMarkTypeForServer(markType));
        
        // Add additional data if available
        if (additionalData.studentId) formData.append('student_id', additionalData.studentId);
        if (additionalData.subjectId) formData.append('subject_id', additionalData.subjectId);
        if (additionalData.termId) formData.append('term_id', additionalData.termId);
        if (additionalData.yearId) formData.append('academic_year_id', additionalData.yearId);
        
        // Get CSRF token
        const csrfToken = document.querySelector('input[name="csrf_token"]') || 
                         document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            const tokenValue = csrfToken.value || csrfToken.content;
            formData.append('csrf_token', tokenValue);
        }
        
        try {
            const response = await fetch('marks_control.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.displayMarksDetails(data.details);
                this.showModal('viewMarkModal');
            } else {
                this.showMessage('Error loading mark details: ' + (data.message || 'Unknown error'), 'alert-danger');
            }
        } catch (error) {
            console.error('Error loading mark details:', error);
            this.showMessage('An error occurred while loading mark details: ' + error.message, 'alert-danger');
        }
    }

  // UPDATED: Convert mark type for server - simplified and consistent
convertMarkTypeForServer(markType) {
    // The mark types should match what the PHP expects
    const typeMap = {
        'midterm': 'midterm',
        'class_score': 'class_score', 
        'class': 'class_score',
        'exam_score': 'exam_score',
        'exam': 'exam_score'
    };
    
    const converted = typeMap[markType] || markType;
    console.log('Converting mark type:', markType, '->', converted); // Debug log
    return converted;
}

    // NEW: Get display name for mark type
    getMarkTypeDisplayName(markType) {
        const displayNames = {
            'midterm': 'Midterm',
            'midterm_score': 'Midterm',
            'class_score': 'Class Score',
            'class': 'Class Score',
            'exam_score': 'Exam',
            'exam': 'Exam'
        };
        
        return displayNames[markType] || 'Mark';
    }

    handleViewDetailsClick(btn) {
        // Extract all data from the button's data attributes
        const studentId = btn.getAttribute('data-student-id');
        const studentName = btn.getAttribute('data-student-name');
        const className = btn.getAttribute('data-class-name');
        const subjectId = btn.getAttribute('data-subject-id');
        const subjectName = btn.getAttribute('data-subject-name');
        const termId = btn.getAttribute('data-term-id');
        const termName = btn.getAttribute('data-term-name');
        const yearName = btn.getAttribute('data-year-name');
        const midMarks = btn.getAttribute('data-mid-marks');
        const classMarks = btn.getAttribute('data-class-marks');
        const examMarks = btn.getAttribute('data-exam-marks');
        const totalMarks = btn.getAttribute('data-total-marks');
        const grade = btn.getAttribute('data-grade');
        const rank = btn.getAttribute('data-rank');
        const remark = btn.getAttribute('data-remark');
        
        // Use the direct data approach (no AJAX needed since we have all data)
        this.displayMarksDetails({
            student_name: studentName,
            class_name: className,
            subject_name: subjectName,
            term_name: termName,
            year_name: yearName,
            mid_marks: parseFloat(midMarks) || 0,
            class_marks: parseFloat(classMarks) || 0,
            exam_marks: parseFloat(examMarks) || 0,
            total_marks: parseFloat(totalMarks) || 0,
            grade: grade,
            rank: rank,
            remark: remark,
            mark_breakdown: [] // Empty array since we don't have breakdown data in attributes
        });
        
        this.showModal('viewMarkModal');
    }

    // Continue with the rest of the methods (initMainDataTable, setupTableFilters, etc.)
    initMainDataTable() {
        if (this.mainDataTableInitialized || !this.cachedElements.marksTable) return;
        
        this.dataTables.marks = $('#marksTable').DataTable({
            responsive: true,
            dom: '<"top"<"export-buttons"B><"table-filters"f>>rt<"bottom"lip><"clear">',
            buttons: [
                { extend: 'excel', className: 'btn-excel', text: '<i class="fas fa-file-excel"></i> Excel' },
                { extend: 'pdf', className: 'btn-pdf', text: '<i class="fas fa-file-pdf"></i> PDF' },
                { extend: 'print', className: 'btn-print', text: '<i class="fas fa-print"></i> Print' },
                { extend: 'copy', className: 'btn-copy', text: '<i class="fas fa-copy"></i> Copy' },
                { extend: 'csv', className: 'btn-csv', text: '<i class="fas fa-file-csv"></i> CSV' }
            ],
            columnDefs: [{ orderable: false, targets: [9] }],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            initComplete: (settings, json) => {
                $('.dt-buttons').show();
            }
        });
        
        this.mainDataTableInitialized = true;
        this.setupTableFilters();
    }

    setupTableFilters() {
        // Auto-select current academic year
        if (this.cachedElements.yearFilter) {
            const currentYearOption = this.cachedElements.yearFilter.querySelector('option[data-is-current="1"]');
            if (currentYearOption) {
                currentYearOption.selected = true;
                const yearName = currentYearOption.textContent;
                this.dataTables.marks.columns(3).search(yearName).draw();
            }
        }
    }

    setupFilterEvents() {
        // Class filter
        if (this.cachedElements.classFilter) {
            this.cachedElements.classFilter.addEventListener('change', this.handleClassFilterChange.bind(this));
        }
        
        // Student filter
        if (this.cachedElements.studentFilter) {
            this.cachedElements.studentFilter.addEventListener('change', this.handleStudentFilterChange.bind(this));
        }
        
        // Subject filter
        if (this.cachedElements.subjectFilter) {
            this.cachedElements.subjectFilter.addEventListener('change', this.handleSubjectFilterChange.bind(this));
        }
        
        // Year filter
        if (this.cachedElements.yearFilter) {
            this.cachedElements.yearFilter.addEventListener('change', this.handleYearFilterChange.bind(this));
        }
        
        // Clear filters
        if (this.cachedElements.clearFilters) {
            this.cachedElements.clearFilters.addEventListener('click', this.handleClearFiltersClick.bind(this));
        }
    }

    handleClassFilterChange(e) {
        const classId = e.target.value;
        const className = e.target.options[e.target.selectedIndex].text;
        
        if (classId === '') {
            this.dataTables.marks.columns(1).search('').draw();
            this.resetStudentFilter();
            this.resetSubjectFilter();
        } else {
            this.dataTables.marks.columns(1).search(className).draw();
            this.updateStudentFilter(classId, className);
            this.updateSubjectFilter(classId, className);
        }
        
        if (this.cachedElements.studentFilter) this.cachedElements.studentFilter.value = '';
        if (this.cachedElements.subjectFilter) this.cachedElements.subjectFilter.value = '';
    }

    handleStudentFilterChange(e) {
        const studentId = e.target.value;
        
        if (studentId === '') {
            this.dataTables.marks.columns(0).search('').draw();
        } else {
            const studentName = e.target.options[e.target.selectedIndex].text;
            this.dataTables.marks.columns(0).search(studentName).draw();
        }
    }

    handleSubjectFilterChange(e) {
        const subjectId = e.target.value;
        
        if (subjectId === '') {
            this.dataTables.marks.columns(2).search('').draw();
        } else {
            const subjectName = e.target.options[e.target.selectedIndex].text;
            this.dataTables.marks.columns(2).search(subjectName).draw();
        }
    }

    handleYearFilterChange(e) {
        const yearId = e.target.value;
        
        if (yearId === '') {
            this.dataTables.marks.columns(3).search('').draw();
        } else {
            const yearName = e.target.options[e.target.selectedIndex].text;
            this.dataTables.marks.columns(3).search(yearName).draw();
        }
    }

    handleClearFiltersClick() {
        // Reset all filter dropdowns
        if (this.cachedElements.classFilter) this.cachedElements.classFilter.value = '';
        if (this.cachedElements.studentFilter) this.cachedElements.studentFilter.value = '';
        if (this.cachedElements.subjectFilter) this.cachedElements.subjectFilter.value = '';
        if (this.cachedElements.yearFilter) this.cachedElements.yearFilter.value = '';
        
        // Clear all table filters
        this.dataTables.marks.columns(0).search('').draw();
        this.dataTables.marks.columns(1).search('').draw();
        this.dataTables.marks.columns(2).search('').draw();
        this.dataTables.marks.columns(3).search('').draw();
        
        // Reset dropdown options
        this.resetStudentFilter();
        this.resetSubjectFilter();
        this.resetYearFilter();
        
        // Re-select current academic year
        if (this.cachedElements.yearFilter) {
            const currentYearOption = this.cachedElements.yearFilter.querySelector('option[data-is-current="1"]');
            if (currentYearOption) {
                currentYearOption.selected = true;
                const yearName = currentYearOption.textContent;
                this.dataTables.marks.columns(3).search(yearName).draw();
            }
        }
    }

    updateStudentFilter(classId, className) {
        if (!this.cachedElements.studentFilter) return;
        
        this.cachedElements.studentFilter.innerHTML = '<option value="">Select a Student</option>';
        const studentsInClass = new Set();
        
        this.dataTables.marks.rows().every((rowIdx, tableLoop, rowLoop) => {
            const rowData = this.dataTables.marks.row(rowIdx).data();
            if (rowData[1] === className) {
                studentsInClass.add(rowData[0]);
            }
        });
        
        studentsInClass.forEach(studentName => {
            const option = document.createElement('option');
            option.value = studentName;
            option.textContent = studentName;
            this.cachedElements.studentFilter.appendChild(option);
        });
    }

    updateSubjectFilter(classId, className) {
        if (!this.cachedElements.subjectFilter) return;
        
        this.cachedElements.subjectFilter.innerHTML = '<option value="">Select a Subject</option>';
        const subjectsInClass = new Set();
        
        this.dataTables.marks.rows().every((rowIdx, tableLoop, rowLoop) => {
            const rowData = this.dataTables.marks.row(rowIdx).data();
            if (rowData[1] === className) {
                subjectsInClass.add(rowData[2]);
            }
        });
        
        subjectsInClass.forEach(subjectName => {
            const option = document.createElement('option');
            option.value = subjectName;
            option.textContent = subjectName;
            this.cachedElements.subjectFilter.appendChild(option);
        });
    }

    resetStudentFilter() {
        if (!this.cachedElements.studentFilter) return;
        
        this.cachedElements.studentFilter.innerHTML = '';
        this.cachedElements.originalStudentOptions.forEach(option => {
            this.cachedElements.studentFilter.appendChild(option.cloneNode(true));
        });
    }

    resetSubjectFilter() {
        if (!this.cachedElements.subjectFilter) return;
        
        this.cachedElements.subjectFilter.innerHTML = '';
        this.cachedElements.originalSubjectOptions.forEach(option => {
            this.cachedElements.subjectFilter.appendChild(option.cloneNode(true));
        });
    }

    resetYearFilter() {
        if (!this.cachedElements.yearFilter) return;
        
        this.cachedElements.yearFilter.innerHTML = '';
        this.cachedElements.originalYearOptions.forEach(option => {
            this.cachedElements.yearFilter.appendChild(option.cloneNode(true));
        });
    }

    handleRemoveSubjectClick(btn) {
        const row = btn.closest('.form-group-row');
        if (this.cachedElements.subjectFieldsContainer.children.length > 1) {
            row.remove();
        } else {
            this.showMessage('At least one subject is required.', 'alert-warning');
        }
    }

    handleAddMarkClick(btn) {
        const row = btn.closest('.form-group-row');
        const marksFields = row.querySelector('.marks-fields');
        let markCount = row.querySelectorAll('.mark-input').length;
        
        if (markCount < 10) {
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
            newInput.addEventListener('input', () => this.calculateTotal(row));
            
            // Recalculate total
            this.calculateTotal(row);
        } else {
            this.showMessage('Maximum 10 marks allowed per subject.', 'alert-warning');
        }
    }

    handleMarkTypeChange(e) {
        if (e.target.value) {
            this.cachedElements.subjectFieldsContainer.style.display = 'block';
            this.cachedElements.addSubjectBtn.style.display = 'inline-block';
            this.cachedElements.subjectFieldsContainer.innerHTML = '';
            this.addSubjectInput();
        } else {
            this.cachedElements.subjectFieldsContainer.style.display = 'none';
            this.cachedElements.addSubjectBtn.style.display = 'none';
        }
    }

    handleClassChange(e) {
        const classId = e.target.value;
        const yearId = this.cachedElements.yearSelect ? this.cachedElements.yearSelect.value : '';
        
        if (this.cachedElements.studentSelect) {
            this.cachedElements.studentSelect.innerHTML = '<option value="">Loading...</option>';
            this.cachedElements.studentSelect.disabled = true;
        }
        
        if (classId && yearId) {
            this.fetchStudentsByClass(classId, yearId);
        } else if (this.cachedElements.studentSelect) {
            this.cachedElements.studentSelect.disabled = true;
            this.cachedElements.studentSelect.innerHTML = '<option value="">First select a class and academic year</option>';
        }
    }

    handleYearChange(e) {
        const classId = this.cachedElements.classSelect ? this.cachedElements.classSelect.value : '';
        const yearId = e.target.value;
        
        if (classId && yearId) {
            this.fetchStudentsByClass(classId, yearId);
        }
    }

    handleViewScoresClick() {
        this.showModal('viewScoresModal');
        
        // Initialize tables and edit buttons
        setTimeout(() => {
            this.initScoresDataTables();
            this.initializeEditButtons();
        }, 100);
    }

    handleTabClick(e) {
        const tab = e.currentTarget;
        const activeTabId = tab.dataset.tab;
        
        // Remove active class from all tabs and panes
        document.querySelectorAll('.tab-nav li').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        // Add active class to clicked tab and corresponding pane
        tab.classList.add('active');
        document.getElementById(activeTabId).classList.add('active');

        // Redraw DataTable for the active tab - FIXED: Check if DataTable exists first
        if (this.scoresDataTables[activeTabId] && $.fn.DataTable.isDataTable(this.scoresDataTables[activeTabId].table().node())) {
            setTimeout(() => {
                this.scoresDataTables[activeTabId].columns.adjust();
                
                // Only call responsive.recalc() if the DataTable has responsive extension
                if (this.scoresDataTables[activeTabId].responsive) {
                    this.scoresDataTables[activeTabId].responsive.recalc();
                }
                
                const buttonWrapper = $(`#${activeTabId} .dt-buttons`);
                if (buttonWrapper.length) {
                    buttonWrapper.show();
                }
                
                // Reinitialize edit buttons for the new active tab
                setTimeout(() => {
                    this.initializeEditButtons();
                }, 200);
            }, 150);
        } else {
            // Initialize DataTable if it doesn't exist yet
            setTimeout(() => {
                this.initScoresDataTables();
                this.initializeEditButtons();
            }, 100);
        }
    }

    handleMarksFormSubmit(e) {
        e.preventDefault();
        
        // Validate form
        if (!this.validateMarksForm()) return;
        
        const formData = new FormData(e.target);
        
        // Add a loading state
        const submitBtn = e.target.querySelector('.btn-submit');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;
        
        this.submitForm(e.target.action, formData)
            .then(data => {
                if (data.status === 'success') {
                    this.showMessage(data.message, 'alert-success');
                    this.hideModal('addMarkModal');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    this.showMessage(data.message, 'alert-danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showMessage('An unexpected error occurred.', 'alert-danger');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
    }

handleDeleteMarkFormSubmit(e) {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('.btn-danger');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Deleting...';
    submitBtn.disabled = true;
    
    const formData = new FormData(e.target);
    formData.append('action', 'delete_marks');
    
    // Get CSRF token and add it to form data
    const csrfToken = this.getCsrfToken();
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    } else {
        this.showMessage('Security token missing. Please refresh the page.', 'alert-danger');
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        return;
    }
    
    const url = 'marks_control.php';
    
    this.submitForm(url, formData)
        .then(data => {
            if (data.status === 'success') {
                this.showMessage(data.message, 'alert-success');
                this.hideModal('deleteMarkModal');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                this.showMessage(data.message, 'alert-danger');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.showMessage('An unexpected error occurred: ' + error.message, 'alert-danger');
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
}

// Add this method to extract CSRF token
getCsrfToken() {
    // Try to get token from meta tag
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }
    
    // Try to get token from input field
    const inputToken = document.querySelector('input[name="csrf_token"]');
    if (inputToken) {
        return inputToken.value;
    }
    
    // Try to get token from form
    const formToken = document.querySelector('form input[name="csrf_token"]');
    if (formToken) {
        return formToken.value;
    }
    
    console.error('CSRF token not found');
    return null;
}

async submitForm(url, formData) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        
        // Check if response is valid JSON
        if (!responseText.trim()) {
            throw new Error('Empty response from server');
        }
        
        return JSON.parse(responseText);
    } catch (error) {
        console.error('Error submitting form:', error);
        throw error;
    }
}

handleEditMarkFormSubmit(e) {
    e.preventDefault();
    
    const submitBtn = e.target.querySelector('.btn-submit');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Updating...';
    submitBtn.disabled = true;
    
    // Hide the modal first
    this.hideModal('editMarkModal');
    
    // Create a hidden form to submit the data
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'marks_control.php'; // This should be the PHP file that processes the form
    form.style.display = 'none';
    
    // Add all form data
    const formData = new FormData(e.target);
    for (let [key, value] of formData.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value;
        form.appendChild(input);
    }
    
    // Add the form to the document and submit it
    document.body.appendChild(form);
    form.submit();
    
    // The page will redirect after form submission
}
    validateMarksForm() {
        // Validate that at least one subject is selected
        const subjectSelects = this.cachedElements.marksForm.querySelectorAll('.subject-select');
        let hasValidSubject = false;
        
        subjectSelects.forEach(select => {
            if (select.value) {
                hasValidSubject = true;
            }
        });
        
        if (!hasValidSubject) {
            this.showMessage('Please select at least one subject.', 'alert-warning');
            return false;
        }

        // Validate that each selected subject has at least one mark
        const rows = this.cachedElements.subjectFieldsContainer.querySelectorAll('.form-group-row');
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
            this.showMessage('Please enter at least one mark for each selected subject.', 'alert-warning');
            return false;
        }
        
        return true;
    }

    async submitForm(url, formData) {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }

    async fetchStudentsByClass(classId, yearId) {
        // Input validation
        if (!classId || classId <= 0) {
            console.warn('Invalid class ID provided:', classId);
            if (this.cachedElements?.studentSelect) {
                this.cachedElements.studentSelect.innerHTML = '<option value="">Select class first</option>';
                this.cachedElements.studentSelect.disabled = true;
            }
            return;
        }

        try {
            // Show loading state
            if (this.cachedElements?.studentSelect) {
                this.cachedElements.studentSelect.innerHTML = '<option value="">Loading students...</option>';
                this.cachedElements.studentSelect.disabled = true;
            }

            // Build URL with proper parameter handling
            const params = new URLSearchParams({
                action: 'get_students_by_class',
                class_id: classId
            });
            
            // Only add academic_year_id if it's provided and valid
            if (yearId && yearId > 0) {
                params.append('academic_year_id', yearId);
            }
            
            const url = `marks_control.php?${params.toString()}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            
            // First check if response is HTML (error)
            const responseText = await response.text();
            
            // Try to parse as JSON, if it fails, we know it's HTML
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Server returned HTML instead of JSON:', responseText.substring(0, 200));
                throw new Error('Server error - please check PHP error logs');
            }
            
            // Handle response
            if (!this.cachedElements?.studentSelect) {
                console.warn('Student select element not found');
                return;
            }
            
            this.cachedElements.studentSelect.innerHTML = '<option value="">Select Student</option>';
            
            if (data.status === 'success') {
                if (data.students && Array.isArray(data.students) && data.students.length > 0) {
                    data.students.forEach(student => {
                        const option = document.createElement('option');
                        option.value = student.student_id;
                        option.textContent = `${student.first_name} ${student.last_name}`;
                        this.cachedElements.studentSelect.appendChild(option);
                    });
                    this.cachedElements.studentSelect.disabled = false;
                } else {
                    this.cachedElements.studentSelect.innerHTML = '<option value="">No students found in this class</option>';
                    this.cachedElements.studentSelect.disabled = true;
                }
            } else {
                throw new Error(data.message || 'Failed to fetch students');
            }
            
        } catch (error) {
            console.error('Error fetching students:', error);
            
            if (this.cachedElements?.studentSelect) {
                this.cachedElements.studentSelect.innerHTML = '<option value="">Error loading students</option>';
                this.cachedElements.studentSelect.disabled = true;
            }
            
            this.showMessage('Error loading students for the selected class.', 'alert-danger');
        }
    }

    addSubjectInput() {
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
        
        this.cachedElements.subjectFieldsContainer.appendChild(newSubjectRow);

        // Add event listener to the new subject select
        const newSubjectSelect = newSubjectRow.querySelector('.subject-select');
        newSubjectSelect.addEventListener('change', (e) => {
            const marksContainer = newSubjectRow.querySelector('.marks-container');
            marksContainer.style.display = e.target.value ? 'block' : 'none';
        });

        // Setup mark calculations
        this.setupMarkCalculation(newSubjectRow);
    }

    setupMarkCalculation(row) {
        const markInputs = row.querySelectorAll('.mark-input');
        const calculateBtn = row.querySelector('.btn-calculate');

        // Auto-calculate on input change
        markInputs.forEach(input => {
            input.addEventListener('input', () => this.calculateTotal(row));
        });

        // Manual calculate button
        calculateBtn.addEventListener('click', () => this.calculateTotal(row));
    }

    calculateTotal(row) {
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

    showAddMarkModal() {
        this.showModal('addMarkModal');
        
        // Clear form
        if (this.cachedElements.marksForm) {
            this.cachedElements.marksForm.reset();
        }
        
        this.cachedElements.subjectFieldsContainer.style.display = 'none';
        this.cachedElements.addSubjectBtn.style.display = 'none';
        this.cachedElements.subjectFieldsContainer.innerHTML = '';
        
        if (this.cachedElements.studentSelect) {
            this.cachedElements.studentSelect.disabled = true;
            this.cachedElements.studentSelect.innerHTML = '<option value="">First select a class</option>';
        }
        
        // Auto-select current academic year
        if (this.cachedElements.yearSelect) {
            const currentYearOption = this.cachedElements.yearSelect.querySelector('option[data-is-current="1"]');
            if (currentYearOption) {
                currentYearOption.selected = true;
            }
        }
    }

    showModal(modalId) {
        // Hide all modals first
        this.hideAllModals();
        
        // Show the requested modal
        if (this.modals[modalId]) {
            this.modals[modalId].style.display = 'block';
        }
    }

    hideModal(modalId) {
        if (this.modals[modalId]) {
            this.modals[modalId].style.display = 'none';
        }
    }

    hideAllModals() {
        for (const modal of Object.values(this.modals)) {
            if (modal) modal.style.display = 'none';
        }
    }

    hideScoresModal() {
        this.hideModal('viewScoresModal');
        this.destroyScoresDataTables();
    }

showMessage(message, type) {
    // Remove any existing messages first
    this.removeAllMessages();
    
    // Create message container if it doesn't exist
    let messageContainer = document.getElementById('message-container');
    if (!messageContainer) {
        messageContainer = document.createElement('div');
        messageContainer.id = 'message-container';
        messageContainer.style.position = 'fixed';
        messageContainer.style.top = '20px';
        messageContainer.style.right = '20px';
        messageContainer.style.zIndex = '9999';
        messageContainer.style.maxWidth = '400px';
        document.body.appendChild(messageContainer);
    }
    
    // Create message element
    const messageElement = document.createElement('div');
    messageElement.className = `alert ${type} message-alert`;
    messageElement.style.margin = '10px 0';
    messageElement.style.padding = '12px 15px';
    messageElement.style.borderRadius = '4px';
    messageElement.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
    messageElement.style.animation = 'slideIn 0.3s ease-out';
    messageElement.innerHTML = `
        <span style="flex: 1;">${message}</span>
        <button type="button" class="close-message" style="background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 10px;">&times;</button>
    `;
    
    // Add styles for different message types
    if (type === 'alert-success') {
        messageElement.style.backgroundColor = '#d4edda';
        messageElement.style.color = '#155724';
        messageElement.style.border = '1px solid #c3e6cb';
    } else if (type === 'alert-danger') {
        messageElement.style.backgroundColor = '#f8d7da';
        messageElement.style.color = '#721c24';
        messageElement.style.border = '1px solid #f5c6cb';
    } else if (type === 'alert-warning') {
        messageElement.style.backgroundColor = '#fff3cd';
        messageElement.style.color = '#856404';
        messageElement.style.border = '1px solid #ffeaa7';
    }
    
    messageContainer.appendChild(messageElement);
    
    // Add click handler to close button
    const closeBtn = messageElement.querySelector('.close-message');
    closeBtn.addEventListener('click', () => {
        messageElement.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (messageElement.parentNode) {
                messageElement.remove();
            }
        }, 300);
    });
    
    // Auto-remove after 5 seconds with fade-out animation
    setTimeout(() => {
        if (messageElement.parentNode) {
            messageElement.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (messageElement.parentNode) {
                    messageElement.remove();
                }
            }, 300);
        }
    }, 5000);
    
    // Add CSS animations if not already added
    this.addMessageStyles();
}

addMessageStyles() {
    // Check if styles already exist
    if (document.getElementById('message-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'message-styles';
    style.textContent = `
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
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .message-alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .close-message:hover {
            color: #000;
        }
    `;
    
    document.head.appendChild(style);
}

removeAllMessages() {
    const messageContainer = document.getElementById('message-container');
    if (messageContainer) {
        messageContainer.innerHTML = '';
    }
}

removeAllMessages() {
    const messageContainer = document.getElementById('message-container');
    if (messageContainer) {
        messageContainer.innerHTML = '';
    }
}

    initScoresDataTables() {
        const tables = {
            'midterm-tab': '#midterm-tab table',
            'class-tab': '#class-tab table', 
            'exam-tab': '#exam-tab table'
        };

        // Destroy existing tables first
        this.destroyScoresDataTables();

        for (const [tabId, selector] of Object.entries(tables)) {
            const tableElement = $(selector);
            if (tableElement.length) {
                // Initialize DataTable with proper button configuration
                this.scoresDataTables[tabId] = tableElement.DataTable({
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
                    initComplete: (settings, json) => {
                        // Use setTimeout to ensure DataTable is fully initialized
                        setTimeout(() => {
                            this.addCustomFilters(this.scoresDataTables[tabId], tabId);
                        }, 100);
                        
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
            
            // Initialize edit buttons after all tables are created
            this.initializeEditButtons();
        }, 100);
    }

    destroyScoresDataTables() {
        for (const [tabId, dataTable] of Object.entries(this.scoresDataTables)) {
            if (dataTable && $.fn.DataTable.isDataTable(dataTable.table().node())) {
                try {
                    dataTable.destroy();
                } catch (error) {
                    console.warn('Error destroying DataTable:', error);
                }
            }
        }
        this.scoresDataTables = {};
        
        // Clean up any orphaned button containers
        $('.dataTables-filter-container').remove();
    }

    addCustomFilters(dataTable, tabId) {
        try {
            // More robust check for DataTable initialization
            if (!dataTable || typeof dataTable.table !== 'function') {
                console.warn('DataTable instance not valid for tab:', tabId);
                return;
            }
            
            const tableNode = dataTable.table().node();
            if (!tableNode || !$.fn.DataTable.isDataTable(tableNode)) {
                console.warn('DataTable not properly initialized for tab:', tabId);
                return;
            }
            
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
            this.populateFiltersFromTable(dataTable, classFilter, yearFilter, termFilter, subjectFilter);

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

    populateFiltersFromTable(dataTable, classFilter, yearFilter, termFilter, subjectFilter) {
        // Get unique values from each column
        const uniqueClasses = new Set();
        const uniqueYears = new Set();
        const uniqueTerms = new Set();
        const uniqueSubjects = new Set();
        
        // Extract data from all table rows
        dataTable.rows().every((rowIdx, tableLoop, rowLoop) => {
            const rowData = dataTable.row(rowIdx).data();
            
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


// UPDATED: Load mark details with better debugging
async loadMarkDetails(markId, markType, additionalData = {}) {
    console.log('Loading mark details:', { markId, markType, additionalData }); // Debug
    
    // Convert mark type to match server expectations
    const serverMarkType = this.convertMarkTypeForServer(markType);
    
    if (!markId || !serverMarkType) {
        console.error('Missing data:', { markId, serverMarkType }); // Debug
        this.showMessage('Missing required data for editing mark.', 'alert-danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'get_mark_details');
    formData.append('mark_id', markId);
    formData.append('mark_type', serverMarkType);
    
    // Add additional data if available
    if (additionalData.studentId) formData.append('student_id', additionalData.studentId);
    if (additionalData.subjectId) formData.append('subject_id', additionalData.subjectId);
    if (additionalData.termId) formData.append('term_id', additionalData.termId);
    if (additionalData.yearId) formData.append('academic_year_id', additionalData.yearId);
    
    // Get CSRF token
    const csrfToken = this.getCsrfToken();
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    } else {
        this.showMessage('Security token missing. Please refresh the page.', 'alert-danger');
        return;
    }
    
    // Debug: Log what we're sending
    console.log('Sending to server:', {
        action: 'get_mark_details',
        mark_id: markId,
        mark_type: serverMarkType,
        student_id: additionalData.studentId,
        subject_id: additionalData.subjectId,
        term_id: additionalData.termId,
        academic_year_id: additionalData.yearId
    });
    
    // Get modal element
    const editModal = this.modals.editMarkModal;
    if (!editModal) {
        this.showMessage('Edit modal not found. Please refresh the page.', 'alert-danger');
        return;
    }
    
    // Show modal immediately with loading state
    this.showModal('editMarkModal');
    const modalContent = editModal.querySelector('.modal-content');
    const originalContent = modalContent.innerHTML;
    
    modalContent.innerHTML = `
        <div class="loading-container" style="text-align: center; padding: 50px;">
            <div class="loading-spinner">Loading mark details...</div>
        </div>
    `;
    
    try {
        const response = await fetch('marks_control.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseText = await response.text();
        console.log('Server response:', responseText); // Debug
        
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('JSON parse error:', parseError);
            console.error('Response text:', responseText);
            throw new Error('Invalid JSON response from server');
        }
        
        // Restore original modal content first
        modalContent.innerHTML = originalContent;
        
        if (data.status === 'success') {
            // Populate the form with individual marks
            this.populateEditFormWithIndividualMarks(data.details, markType);
        } else {
            this.hideModal('editMarkModal');
            this.showMessage('Error loading mark details: ' + (data.message || 'Unknown error'), 'alert-danger');
        }
    } catch (error) {
        console.error('Error loading mark details:', error);
        // Restore original content and hide modal
        modalContent.innerHTML = originalContent;
        this.hideModal('editMarkModal');
        this.showMessage('An error occurred while loading mark details: ' + error.message, 'alert-danger');
    }
}

displayMarksDetails(details) {
    let html = `
        <div class="marks-details">
            <div class="detail-row">
                <span class="detail-label">Student:</span>
                <span class="detail-value">${details.student_name}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Class:</span>
                <span class="detail-value">${details.class_name}</span>
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
    
    // Add mark breakdown if available and not empty
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
                <span class="detail-value grade-badge grade-${details.grade ? details.grade.toLowerCase() : 'unknown'}">${details.grade || 'N/A'}</span>
            </div>
            <div class='detail-row'>
                <span class='detail-label'>Remarks:</span>
                <span class='detail-value'>${details.remark || 'N/A'}</span>
            </div>
        </div>
    `;
    
    this.cachedElements.marksDetailsContent.innerHTML = html;
}

async loadMarkDetails(markId, markType, additionalData = {}) {
 // Convert mark type to match server expectations
let serverMarkType = markType;
if (markType === 'class') {
    serverMarkType = 'class_score';
} else if (markType === 'exam') {
    serverMarkType = 'exam_score';
} else if (markType === 'midterm') {
    serverMarkType = 'midterm'; // ✅ NOT midterm_score
}

    
    if (!markId || !serverMarkType) {
        this.showMessage('Missing required data for editing mark.', 'alert-danger');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'get_mark_details');
    formData.append('mark_id', markId);
    formData.append('mark_type', serverMarkType); // Use converted mark type
    
    // Add additional data if available
    if (additionalData.studentId) formData.append('student_id', additionalData.studentId);
    if (additionalData.subjectId) formData.append('subject_id', additionalData.subjectId);
    if (additionalData.termId) formData.append('term_id', additionalData.termId);
    if (additionalData.yearId) formData.append('academic_year_id', additionalData.yearId);
    
    // Get CSRF token
    const csrfToken = document.querySelector('input[name="csrf_token"]') || 
                     document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        const tokenValue = csrfToken.value || csrfToken.content;
        formData.append('csrf_token', tokenValue);
    } else {
        this.showMessage('Security token missing. Please refresh the page.', 'alert-danger');
        return;
    }
    
    // Get modal element
    const editModal = this.modals.editMarkModal;
    if (!editModal) {
        this.showMessage('Edit modal not found. Please refresh the page.', 'alert-danger');
        return;
    }
    
    // Show modal immediately with loading state
    this.showModal('editMarkModal');
    const modalContent = editModal.querySelector('.modal-content');
    const originalContent = modalContent.innerHTML;
    
    modalContent.innerHTML = `
        <div class="loading-container" style="text-align: center; padding: 50px;">
            <div class="loading-spinner">Loading mark details...</div>
        </div>
    `;
    
    try {
        const response = await fetch('marks_control.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        // Restore original modal content first
        modalContent.innerHTML = originalContent;
        
        if (data.status === 'success') {
            // Populate the form with individual marks
            this.populateEditFormWithIndividualMarks(data.details, markType);
        } else {
            this.hideModal('editMarkModal');
            this.showMessage('Error loading mark details: ' + (data.message || 'Unknown error'), 'alert-danger');
        }
    } catch (error) {
        console.error('Error loading mark details:', error);
        // Restore original content and hide modal
        modalContent.innerHTML = originalContent;
        this.hideModal('editMarkModal');
        this.showMessage('An error occurred while loading mark details: ' + error.message, 'alert-danger');
    }
}

  populateEditFormWithIndividualMarks(details, markType) {
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
        }
    }
    
    // Create individual mark inputs
    const marksContainer = document.getElementById('individual-marks-container');
    if (marksContainer && details.mark_breakdown && details.mark_breakdown.length > 0) {
        marksContainer.innerHTML = '';
        
        details.mark_breakdown.forEach((mark, index) => {
            const markRow = document.createElement('div');
            markRow.className = 'mark-input-row';
            markRow.innerHTML = `
                <label>Mark ${index + 1}</label>
                <input type="number" name="mark_${index + 1}" value="${mark}" min="0" max="100" step="0.01" class="individual-mark">
                <button type="button" class="btn-remove-mark" data-index="${index + 1}">×</button>
            `;
            marksContainer.appendChild(markRow);
        });
        
        // Add event listeners for individual marks
        marksContainer.querySelectorAll('.individual-mark').forEach(input => {
            input.addEventListener('input', () => this.calculateEditTotal());
        });
        
        // Add event listeners for remove buttons
        marksContainer.querySelectorAll('.btn-remove-mark').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = e.target.getAttribute('data-index');
                this.removeMarkInput(index);
            });
        });
    }
    
    // Add "Add Mark" button
    const addMarkBtn = document.getElementById('add-mark-btn');
    if (addMarkBtn) {
        addMarkBtn.onclick = () => this.addMarkInput();
    }
    
    // Calculate initial total
    this.calculateEditTotal();
}

addMarkInput() {
    const marksContainer = document.getElementById('individual-marks-container');
    if (!marksContainer) return;
    
    const markCount = marksContainer.querySelectorAll('.mark-input-row').length;
    
    if (markCount < 10) {
        const markRow = document.createElement('div');
        markRow.className = 'mark-input-row';
        markRow.innerHTML = `
            <label>Mark ${markCount + 1}</label>
            <input type="number" name="mark_${markCount + 1}" value="0" min="0" max="100" step="0.01" class="individual-mark">
            <button type="button" class="btn-remove-mark" data-index="${markCount + 1}">×</button>
        `;
        marksContainer.appendChild(markRow);
        
        // Add event listeners
        const newInput = markRow.querySelector('.individual-mark');
        newInput.addEventListener('input', () => this.calculateEditTotal());
        
        const removeBtn = markRow.querySelector('.btn-remove-mark');
        removeBtn.addEventListener('click', (e) => {
            const index = e.target.getAttribute('data-index');
            this.removeMarkInput(index);
        });
        
        // Recalculate total
        this.calculateEditTotal();
    } else {
        this.showMessage('Maximum 10 marks allowed.', 'alert-warning');
    }
}

removeMarkInput(index) {
    const marksContainer = document.getElementById('individual-marks-container');
    if (!marksContainer) return;
    
    const markRows = marksContainer.querySelectorAll('.mark-input-row');
    if (markRows.length <= 1) {
        this.showMessage('At least one mark is required.', 'alert-warning');
        return;
    }
    
    const markToRemove = marksContainer.querySelector(`.btn-remove-mark[data-index="${index}"]`).parentNode;
    marksContainer.removeChild(markToRemove);
    
    // Renumber the remaining marks
    markRows.forEach((row, i) => {
        const label = row.querySelector('label');
        const input = row.querySelector('input');
        const btn = row.querySelector('button');
        
        if (label) label.textContent = `Mark ${i + 1}`;
        if (input) {
            input.name = `mark_${i + 1}`;
            btn.setAttribute('data-index', i + 1);
        }
    });
    
    // Recalculate total
    this.calculateEditTotal();
}

calculateEditTotal() {
    const marksContainer = document.getElementById('individual-marks-container');
    const totalInput = document.getElementById('edit_total_marks');
    
    if (!marksContainer || !totalInput) return;
    
    const markInputs = marksContainer.querySelectorAll('.individual-mark');
    let total = 0;
    
    markInputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    
    totalInput.value = total.toFixed(2);
}

    initializeEditButtons() {
        // Check if we're in the scores modal context
        const viewScoresModal = this.modals.viewScoresModal;
        if (viewScoresModal && viewScoresModal.style.display === 'block') {
            // Wait for DataTables to fully initialize
            setTimeout(() => {
                this.setupEditMarkButtons();
            }, 500);
        } else {
            // For regular page context
            this.setupEditMarkButtons();
        }
    }

    setupEditMarkButtons() {
        // This is now handled by event delegation in handleDocumentClick
    }

  validateWeights() {
    const mid = parseInt(document.getElementById("mid_weight").value) || 0;
    const classW = parseInt(document.getElementById("class_weight").value) || 0;
    const exam = parseInt(document.getElementById("exam_weight").value) || 0;

    const total = mid + classW + exam;
    const totalDisplay = document.getElementById("weight-total");
    const saveBtn = document.getElementById("saveBtn");

    if (totalDisplay) {
        totalDisplay.textContent = "Total: " + total + "%";
    }

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
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('disabled');
        }
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
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.classList.remove('disabled');
        }
    }
}
}

// Initialize the MarksManager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.marksManager = new MarksManager();
});


// Dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    initDropdowns();
});

function initDropdowns() {
    // Get all dropdown toggle buttons
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    // Remove any existing event listeners to prevent duplicates
    dropdownToggles.forEach(toggle => {
        // Clone the toggle to remove existing event listeners
        const newToggle = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(newToggle, toggle);
    });
    
    // Re-select the toggles after cloning
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropdown = this.closest('.dropdown');
            if (!dropdown) return;
            
            // Close all other dropdowns
            document.querySelectorAll('.dropdown').forEach(otherDropdown => {
                if (otherDropdown !== dropdown && otherDropdown.classList.contains('open')) {
                    otherDropdown.classList.remove('open');
                }
            });
            
            // Toggle this dropdown
            dropdown.classList.toggle('open');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown.open').forEach(dropdown => {
                dropdown.classList.remove('open');
            });
        }
    });
    
    // Close dropdowns when pressing Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown.open').forEach(dropdown => {
                dropdown.classList.remove('open');
            });
        }
    });
    
    // Prevent dropdown menu clicks from closing the parent dropdown
    const dropdownMenus = document.querySelectorAll('.dropdown-menu');
    dropdownMenus.forEach(menu => {
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
}

// Function to refresh dropdowns (call this if you add dropdowns dynamically)
window.refreshDropdowns = function() {
    initDropdowns();
};