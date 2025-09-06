// ======= STUDENT PAYMENT SYSTEM =======

class StudentPaymentSystem {
    constructor() {
        this.allStudents = window.allStudents || [];
        this.studentSelect = $('#student_id');
        this.classSelect = $('#class_id');
        this.initialized = false;
        
        this.init();
    }

    // ======= INITIALIZATION =======
    init() {
        if (this.initialized) return;
        
        this.initializeSelect2();
        this.populateStudentDropdown(this.allStudents);
        this.bindEvents();
        
        this.initialized = true;
        console.log('Student Payment System initialized');
    }

    // ======= SELECT2 INITIALIZATION =======
    initializeSelect2() {
        if (!window.jQuery || !$.fn.select2) {
            console.error('jQuery or Select2 not available');
            return;
        }

        const select2Config = {
            allowClear: true,
            width: '100%',
            dropdownParent: $('#paymentModal').length ? $('#paymentModal') : $('body')
        };

        // Initialize student dropdown
        this.studentSelect.select2({
            ...select2Config,
            placeholder: "Select or Search Student"
        });

        // Initialize class dropdown
        this.classSelect.select2({
            ...select2Config,
            placeholder: "Select Class"
        });
    }

    // ======= POPULATE STUDENT DROPDOWN =======
    populateStudentDropdown(studentsToDisplay, selectedId = null) {
        if (!this.studentSelect.length) {
            console.error('Student select element not found');
            return;
        }

        // Clear and add default option
        this.studentSelect.empty().append('<option value="">Select Student</option>');

        // Add students
        studentsToDisplay.forEach(student => {
            if (!student.id || !student.first_name || !student.last_name) {
                console.warn('Invalid student data:', student);
                return;
            }

            const option = new Option(
                `${student.first_name} ${student.last_name} (${student.student_id || 'N/A'})`,
                student.id,
                false,
                false
            );
            
            $(option).attr('data-class-id', student.class_id || '');
            this.studentSelect.append(option);
        });

        // Restore selection if still valid
        if (selectedId && studentsToDisplay.some(s => s.id == selectedId)) {
            this.studentSelect.val(selectedId);
        } else {
            this.studentSelect.val(null);
        }

        // Refresh Select2
        this.studentSelect.trigger('change.select2');
    }

    // ======= FILTER STUDENTS BY CLASS =======
    filterStudentsByClass(classId) {
        if (!classId) return this.allStudents;
        
        return this.allStudents.filter(student => 
            student.class_id && student.class_id == classId
        );
    }

    // ======= EVENT HANDLERS =======
    bindEvents() {
        // Class selection change
        this.classSelect.on('change', (e) => {
            const selectedClassId = $(e.target).val();
            const currentStudentId = this.studentSelect.val();
            
            const filteredStudents = this.filterStudentsByClass(selectedClassId);
            this.populateStudentDropdown(filteredStudents, currentStudentId);
        });

        // Student selection change
        this.studentSelect.on('change', (e) => {
            const selectedStudentId = $(e.target).val();
            
            if (selectedStudentId) {
                this.handleStudentSelection(selectedStudentId);
            } else {
                this.clearStudentSelection();
            }
        });
    }

    // ======= HANDLE STUDENT SELECTION =======
    handleStudentSelection(studentId) {
        const student = this.allStudents.find(s => s.id == studentId);
        
        if (student && student.class_id) {
            // Auto-set class
            this.classSelect.val(student.class_id).trigger('change.select2');
            
            // Fetch total paid with error handling
            this.fetchTotalPaidSafely(studentId);
        } else {
            console.warn('Student not found or missing class_id:', studentId);
            this.clearStudentSelection();
        }
    }

    // ======= CLEAR STUDENT SELECTION =======
    clearStudentSelection() {
        this.classSelect.val(null).trigger('change.select2');
        this.hideTotalPaid();
    }

    // ======= FETCH TOTAL PAID WITH ERROR HANDLING =======
    async fetchTotalPaidSafely(studentId) {
        try {
            if (typeof fetchTotalPaid === 'function') {
                await fetchTotalPaid(studentId);
            } else {
                console.warn('fetchTotalPaid function not available');
                this.hideTotalPaid();
            }
        } catch (error) {
            console.error('Error fetching total paid:', error);
            this.hideTotalPaid();
            this.showError('Failed to fetch payment information');
        }
    }

    // ======= UTILITY METHODS =======
    hideTotalPaid() {
        if (typeof hideTotalPaid === 'function') {
            hideTotalPaid();
        }
    }

    showError(message) {
        // You can customize this based on your error display system
        console.error(message);
        // Example: $('#error-container').text(message).show();
    }

    // ======= PUBLIC METHODS =======
    refreshStudentData(newStudentData) {
        if (Array.isArray(newStudentData)) {
            this.allStudents = newStudentData;
            const currentClassId = this.classSelect.val();
            const filteredStudents = this.filterStudentsByClass(currentClassId);
            this.populateStudentDropdown(filteredStudents);
        }
    }

    getSelectedStudent() {
        const studentId = this.studentSelect.val();
        return this.allStudents.find(s => s.id == studentId);
    }

    setSelectedStudent(studentId) {
        if (this.allStudents.some(s => s.id == studentId)) {
            this.studentSelect.val(studentId).trigger('change');
        }
    }

    reset() {
        this.studentSelect.val(null).trigger('change');
        this.classSelect.val(null).trigger('change');
        this.hideTotalPaid();
    }
}

// ======= INITIALIZE SYSTEM =======
$(document).ready(function() {
    // Initialize the payment system
    window.studentPaymentSystem = new StudentPaymentSystem();
});

// ======= LEGACY SUPPORT (if needed) =======
function initializeSelect2() {
    if (window.studentPaymentSystem) {
        window.studentPaymentSystem.initializeSelect2();
    }
}

function populateStudentDropdown(studentsToDisplay, selectedId) {
    if (window.studentPaymentSystem) {
        window.studentPaymentSystem.populateStudentDropdown(studentsToDisplay, selectedId);
    }
}