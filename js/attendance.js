// attendance.js - FIXED VERSION with Academic Year Correction

// ============================================
// MODULE: Configuration & Constants
// ============================================
const CONFIG = {
    API_ENDPOINTS: {
        GET_STUDENTS: 'get_attendance.php?action=get_students',
        GET_CLASSES: 'get_attendance.php?action=get_classes',
        GET_ATTENDANCE: 'get_attendance.php?action=get_attendance',
        GET_ATTENDANCE_BY_ID: 'get_attendance.php?action=get_attendance_by_id',
        ATTENDANCE_ACTION: 'attendance_action.php'
    },
    MESSAGES: {
        NO_CLASS_SELECTED: 'Please select a class to view students',
        NO_STUDENTS_FOUND: 'No students found in this class.',
        LOADING_STUDENTS: 'Loading students...',
        LOADING_CLASSES: 'Loading classes...',
        LOADING_ATTENDANCE: 'Loading existing attendance...',
        LOADING_RECORD: 'Loading attendance record...',
        SAVING_ATTENDANCE: 'Saving attendance...',
        DELETING_RECORD: 'Deleting attendance record...',
        ERROR_LOADING: 'Error loading students. Please try again.',
        SELECT_CLASS_FIRST: 'Please select a class first',
        SELECT_ACADEMIC_YEAR_FIRST: 'Please select an academic year first',
        MARK_ONE_STUDENT: 'Please mark attendance for at least one student.',
        FILL_REQUIRED_FIELDS: 'Please fill all required fields (Class, Date, Academic Year, and Term).',
        CONFIRM_DELETE: 'Are you sure you want to delete this attendance record?'
    }
};

// ============================================
// MODULE: State Management
// ============================================
const AttendanceState = {
    modal: null,
    
    init() {
        this.modal = document.getElementById('markAttendanceModal');
    },
    
    getCurrentFormData() {
        return {
            classId: document.getElementById('class_id')?.value,
            date: document.getElementById('attendance_date')?.value,
            academicYearId: document.getElementById('academic_year_id')?.value,
            termId: document.getElementById('term_id')?.value,
            attendanceId: document.getElementById('attendance_id')?.value
        };
    },
    
    // NEW: Get academic year name from the select dropdown
    getAcademicYearName() {
        const academicYearSelect = document.getElementById('academic_year_id');
        if (!academicYearSelect || !academicYearSelect.value) return null;
        
        const selectedOption = academicYearSelect.options[academicYearSelect.selectedIndex];
        return selectedOption ? selectedOption.textContent.trim().replace(/\s+/g, ' ') : null;
    }
};

// ============================================
// MODULE: API Service
// ============================================
const AttendanceAPI = {
    async fetchStudents(classId, academicYear = null, specificStudentId = null) {
        let url = `${CONFIG.API_ENDPOINTS.GET_STUDENTS}`;
        const params = [];
        
        if (classId) params.push(`class_id=${encodeURIComponent(classId)}`);
        if (academicYear) params.push(`academic_year=${encodeURIComponent(academicYear)}`);
        if (specificStudentId) params.push(`student_id=${encodeURIComponent(specificStudentId)}`);
        
        if (params.length > 0) {
            url += '&' + params.join('&');
        }
        
        console.log('Fetching students URL:', url);
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('Students API Response:', data);
        return data;
    },
    
async fetchClasses(academicYearName) {
    if (!academicYearName) {
        console.error('No academic year name provided');
        const classSelect = document.getElementById('class_id');
        classSelect.innerHTML = '<option value="">Select Academic Year First</option>';
        return { success: false, classes: [] }; // 🔸 return a safe fallback object
    }

    try {
        UIUtils.showLoading(CONFIG.MESSAGES.LOADING_CLASSES);

        console.log('Academic Year Name being sent:', academicYearName);
        console.log('Type of academicYearName:', typeof academicYearName);

        const url = `${CONFIG.API_ENDPOINTS.GET_CLASSES}&academic_year=${encodeURIComponent(academicYearName)}`;
        console.log('Fetching classes URL (no encoding):', url);

        const response = await fetch(url);
        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('Server error response:', errorText);
            throw new Error(`HTTP error! status: ${response.status}. Server message: ${errorText}`);
        }

        const data = await response.json();
        console.log('Classes API Response:', data);
        UIUtils.hideLoading();

        const classSelect = document.getElementById('class_id');
        classSelect.innerHTML = '<option value="">Select Class</option>';

        if (data.success && data.classes && Array.isArray(data.classes)) {
            data.classes.forEach(cls => {
                const option = document.createElement('option');
                option.value = cls.id;
                option.textContent = cls.class_name;
                classSelect.appendChild(option);
            });
            console.log(`Loaded ${data.classes.length} classes for academic year: ${academicYearName}`);
        } else {
            console.warn('No classes found or invalid response:', data);
            Toast.warning('No classes found for the selected academic year.');
        }

        return data; // 🟢 FIX: return the parsed response object

    } catch (error) {
        UIUtils.hideLoading();
        console.error('Error loading classes:', error);
        Toast.error('Error loading classes: ' + error.message);
        return { success: false, classes: [], message: error.message }; // 🔸 fallback
    }
},
  
  async fetchAttendance(classId, date, academicYearId = null, termId = null) {
    let url = `${CONFIG.API_ENDPOINTS.GET_ATTENDANCE}&class_id=${classId}&attendance_date=${date}`;
    if (academicYearId) url += `&academic_year_id=${academicYearId}`;
    if (termId) url += `&term_id=${termId}`;
        
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    },
    
    async fetchAttendanceById(id) {
        const url = `${CONFIG.API_ENDPOINTS.GET_ATTENDANCE_BY_ID}&id=${id}`;
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    },
    
    async saveAttendance(formData) {
        const response = await fetch(CONFIG.API_ENDPOINTS.ATTENDANCE_ACTION, {
            method: 'POST',
            body: formData
        });
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Server returned HTML instead of JSON. Response: ${text.substring(0, 200)}`);
        }
        
        return response.json();
    },
    
    async deleteAttendance(id) {
        const response = await fetch(CONFIG.API_ENDPOINTS.ATTENDANCE_ACTION, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_attendance', id })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    }
};

// ============================================
// MODULE: UI Utilities
// ============================================
const UIUtils = {
    showLoading(message = 'Loading...') {
        this.hideLoading();
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'loadingIndicator';
        loadingDiv.className = 'loading-indicator';
        loadingDiv.innerHTML = `
            <div class="loading-spinner"></div>
            <div class="loading-text">${message}</div>
        `;
        document.body.appendChild(loadingDiv);
    },
    
    hideLoading() {
        const loadingIndicator = document.getElementById('loadingIndicator');
        if (loadingIndicator) {
            loadingIndicator.remove();
        }
    },
    
    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },
    
    updateRowStatus(row, status) {
        row.classList.remove('status-present', 'status-absent', 'status-late', 'status-excused');
        if (status) {
            row.classList.add('status-' + status);
        }
    }
};

// ============================================
// MODULE: Modal Management
// ============================================
const ModalManager = {
    open() {
        this.resetForm();
        this.setDefaultDate();
        this.clearStudentsList();
        
        AttendanceState.modal.style.display = 'block';
        
        const { classId } = AttendanceState.getCurrentFormData();
        if (classId) {
            setTimeout(() => AttendanceController.loadExistingAttendance(), 100);
        }
    },
    
    close() {
        AttendanceState.modal.style.display = 'none';
        this.resetStudentSelection();
    },
    
    resetForm() {
        document.getElementById('attendanceForm')?.reset();
        document.getElementById('attendance_id').value = '';
    },
    
    setDefaultDate() {
        const dateInput = document.getElementById('attendance_date');
        if (dateInput) {
            dateInput.value = new Date().toISOString().split('T')[0];
        }
    },
    
    clearStudentsList() {
        const studentsList = document.getElementById('studentsAttendanceList');
        if (studentsList) {
            studentsList.innerHTML = `<tr><td colspan="4" class="text-center">${CONFIG.MESSAGES.NO_CLASS_SELECTED}</td></tr>`;
        }
    },
    
    resetStudentSelection() {
        const studentSelect = document.getElementById('student_select');
        const classSelect = document.getElementById('class_id');
        const studentsList = document.getElementById('studentsAttendanceList');
        
        if (studentSelect) studentSelect.value = '';
        if (classSelect) classSelect.value = '';
        if (studentsList) {
            studentsList.innerHTML = `<tr><td colspan="4" class="text-center">${CONFIG.MESSAGES.NO_CLASS_SELECTED}</td></tr>`;
        }
    }
};

// ============================================
// MODULE: Students Rendering
// ============================================
const StudentsRenderer = {
    render(students, existingData = null, specificStudentId = null) {
        if (!Array.isArray(students)) {
            console.error('Students data is not an array:', students);
            students = [];
        }
        
        const html = students.length === 0 
            ? this.renderEmptyState()
            : this.renderStudentRows(students, existingData, specificStudentId);
        
        document.getElementById('studentsAttendanceList').innerHTML = html;
        FormManager.autoPopulateAcademicInfo();
        this.addEventListeners();
    },
    
    renderEmptyState() {
        return `<tr><td colspan="4" class="text-center">${CONFIG.MESSAGES.NO_STUDENTS_FOUND}</td></tr>`;
    },
    
    renderStudentRows(students, existingData, specificStudentId) {
        return students.map(student => {
            if (!student || !student.id || !student.first_name || !student.last_name || !student.student_id) {
                console.warn('Invalid student data:', student);
                return '';
            }
            
            const existing = existingData?.[student.id];
            const isSelected = specificStudentId && student.id == specificStudentId;
            const rowClass = isSelected ? 'student-row selected' : 'student-row';
            const initials = this.getInitials(student);
            
            return this.renderStudentRow(student, rowClass, initials, existing);
        }).join('');
    },
    
    getInitials(student) {
        const first = student.first_name?.charAt(0)?.toUpperCase() || '?';
        const last = student.last_name?.charAt(0)?.toUpperCase() || '?';
        return first + last;
    },
    
    renderStudentRow(student, rowClass, initials, existing) {
        return `
            <tr class="${rowClass}" data-student-id="${student.id}">
                <td>
                    <div class="student-info">
                        <div class="student-avatar">${initials}</div>
                        <div class="student-details">
                            <div class="student-name">${UIUtils.escapeHtml(student.first_name)} ${UIUtils.escapeHtml(student.last_name)}</div>
                            <div class="student-id">ID: ${UIUtils.escapeHtml(student.student_id)}</div>
                        </div>
                    </div>
                </td>
                ${this.renderAttendanceOptions(student.id, existing)}
            </tr>
        `;
    },
    
    renderAttendanceOptions(studentId, existing) {
        const options = [
            { value: 'present', label: '✓', title: 'Present', className: 'checkbox-present' },
            { value: 'absent', label: '✗', title: 'Absent', className: 'checkbox-absent' },
            { value: 'late', label: '⌚', title: 'Late', className: 'checkbox-late' }
        ];
        
        return options.map(option => `
            <td class="attendance-checkbox">
                <div class="checkbox-group">
                    <div class="checkbox-item ${option.className}">
                        <input type="radio" 
                               name="attendance[${studentId}]" 
                               value="${option.value}" 
                               id="${option.value}_${studentId}"
                               ${existing?.status === option.value ? 'checked' : ''}>
                        <label for="${option.value}_${studentId}" title="${option.title}">${option.label}</label>
                    </div>
                </div>
            </td>
        `).join('');
    },
    
    addEventListeners() {
        const checkboxes = document.querySelectorAll('input[type="radio"][name^="attendance"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const row = this.closest('tr');
                UIUtils.updateRowStatus(row, this.value);
            });
            
            if (checkbox.checked) {
                const row = checkbox.closest('tr');
                UIUtils.updateRowStatus(row, checkbox.value);
            }
        });
    }
};

// ============================================
// MODULE: Form Management
// ============================================
const FormManager = {
    getAttendanceData() {
        const attendanceData = {};
        const checkboxes = document.querySelectorAll('input[type="radio"][name^="attendance"]:checked');
        
        checkboxes.forEach(checkbox => {
            const match = checkbox.name.match(/\[(\d+)\]/);
            if (match?.[1]) {
                attendanceData[match[1]] = checkbox.value;
            }
        });
        
        return attendanceData;
    },
    
    validateForm(attendanceData, formData) {
        if (Object.keys(attendanceData).length === 0) {
            alert(CONFIG.MESSAGES.MARK_ONE_STUDENT);
            return false;
        }
        
        if (!formData.classId || !formData.date || !formData.academicYearId || !formData.termId) {
            alert(CONFIG.MESSAGES.FILL_REQUIRED_FIELDS);
            return false;
        }
        
        return true;
    },
    
    autoPopulateAcademicInfo() {
        const academicYearSelect = document.getElementById('academic_year_id');
        const termSelect = document.getElementById('term_id');
        
        if (academicYearSelect && !academicYearSelect.value && academicYearSelect.options.length > 1) {
            academicYearSelect.selectedIndex = 1;
        }
        
        if (termSelect && !termSelect.value && termSelect.options.length > 1) {
            termSelect.selectedIndex = 1;
        }
    },
    
    resetAttendanceSelections() {
        const checkboxes = document.querySelectorAll('input[type="radio"][name^="attendance"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
            const row = checkbox.closest('tr');
            if (row) {
                row.classList.remove('status-present', 'status-absent', 'status-late');
            }
        });
    }
};

// ============================================
// MODULE: Main Attendance Controller
// ============================================
const AttendanceController = {
async loadStudents(classId, academicYear = null, specificStudentId = null) {
    return new Promise(async (resolve, reject) => {
        try {
            if (!classId) {
                document.getElementById('studentsAttendanceList').innerHTML =
                    `<tr><td colspan="4" class="text-center">${CONFIG.MESSAGES.NO_CLASS_SELECTED}</td></tr>`;
                return resolve();
            }

            UIUtils.showLoading(CONFIG.MESSAGES.LOADING_STUDENTS);
            const response = await AttendanceAPI.fetchStudents(classId, academicYear, specificStudentId);
            UIUtils.hideLoading();

            let students = [];
            if (Array.isArray(response)) {
                students = response;
            } else if (response && Array.isArray(response.students)) {
                students = response.students;
            } else if (response && response.success && Array.isArray(response.data)) {
                students = response.data;
            }

            StudentsRenderer.render(students, null, specificStudentId);
            resolve(); // ✅ Ensure promise resolves after rendering

        } catch (error) {
            UIUtils.hideLoading();
            console.error('Error loading students:', error);
            document.getElementById('studentsAttendanceList').innerHTML =
                `<tr><td colspan="4" class="text-center">${CONFIG.MESSAGES.ERROR_LOADING}</td></tr>`;
            reject(error);
        }
    });
},
    
async loadClasses(academicYearName) {
    const classSelect = document.getElementById('class_id');
    if (!academicYearName) {
        classSelect.innerHTML = '<option value="">Select Academic Year First</option>';
        console.warn('No academic year name provided to loadClasses()');
        return;
    }

    try {
        UIUtils.showLoading(CONFIG.MESSAGES.LOADING_CLASSES);
        console.log('Loading classes for academic year:', academicYearName);

        const data = await AttendanceAPI.fetchClasses(academicYearName);
        UIUtils.hideLoading();

        // 🔸 Ensure `data` is a valid object
        if (!data || typeof data !== 'object') {
            console.error('Invalid or empty response for loadClasses:', data);
            alert('Error: Received invalid response from server.');
            classSelect.innerHTML = '<option value="">No Classes Found</option>';
            return;
        }

        classSelect.innerHTML = '<option value="">Select Class</option>';

        // 🧭 Safely handle response
        if (data.success === true && Array.isArray(data.classes) && data.classes.length > 0) {
            data.classes.forEach(cls => {
                if (cls && cls.id && cls.class_name) {
                    const option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.class_name;
                    classSelect.appendChild(option);
                } else {
                    console.warn('Skipping invalid class object:', cls);
                }
            });
            console.log(`Loaded ${data.classes.length} classes for academic year: ${academicYearName}`);
        } else if (data.success === true && Array.isArray(data.classes) && data.classes.length === 0) {
            console.warn('No classes found for this academic year:', academicYearName);
            alert(`No classes found for academic year: ${academicYearName}`);
        } else {
            console.warn('Unexpected response structure:', data);
            alert('Error: Invalid server response while loading classes.');
        }

    } catch (error) {
        UIUtils.hideLoading();
        console.error('Error loading classes:', error);
        alert('Error loading classes: ' + error.message);
        classSelect.innerHTML = '<option value="">Error Loading Classes</option>';
    }
},
  
    async loadExistingAttendance() {
        const { classId, date, academicYearId, termId } = AttendanceState.getCurrentFormData();
        
        if (!classId || !date) return;
        
        try {
            UIUtils.showLoading(CONFIG.MESSAGES.LOADING_ATTENDANCE);
            const data = await AttendanceAPI.fetchAttendance(classId, date, academicYearId, termId);
            UIUtils.hideLoading();
            
            if (data.success && data.records?.length > 0) {
                this.updateFormWithExistingRecords(data.records || [data.record]);
            } else {
                document.getElementById('attendance_id').value = '';
                FormManager.resetAttendanceSelections();
            }
        } catch (error) {
            UIUtils.hideLoading();
            console.error('Error loading existing attendance:', error);
        }
    },
    
updateFormWithExistingRecords(records) {
    const recordsByStudent = {};
    records.forEach(record => {
        recordsByStudent[record.student_id] = record;
    });

    if (records.length > 0) {
        document.getElementById('attendance_id').value = records[0].id;
    }

    const academicYearName = AttendanceState.getAcademicYearName();
    const { classId } = AttendanceState.getCurrentFormData();

    this.loadStudents(classId, academicYearName).then(() => {
        Object.keys(recordsByStudent).forEach(studentId => {
            const status = recordsByStudent[studentId].status;
            const radio = document.querySelector(`input[name="attendance[${studentId}]"][value="${status}"]`);
            if (radio) {
                radio.checked = true;
                const row = radio.closest('tr');
                UIUtils.updateRowStatus(row, status);
            }
        });
    });
},
    
async submitAttendance(form) {
    const attendanceData = FormManager.getAttendanceData();
    const formData = AttendanceState.getCurrentFormData();
    
    if (!FormManager.validateForm(attendanceData, formData)) return;
    
    try {
        UIUtils.showLoading(CONFIG.MESSAGES.SAVING_ATTENDANCE);
        const data = await AttendanceAPI.saveAttendance(new FormData(form));
        UIUtils.hideLoading();
        
        if (data.success) {
            this.showSuccessMessage(data.message, true); // true = auto close and reload
        } else {
            this.showErrorMessage(data.message || 'An error occurred while saving attendance.');
        }
    } catch (error) {
        UIUtils.hideLoading();
        console.error('Error:', error);
        this.showErrorMessage('An error occurred while saving attendance: ' + error.message);
    }
},
    
async editAttendance(attendanceId) {
    if (!attendanceId) return;
    
    try {
        UIUtils.showLoading(CONFIG.MESSAGES.LOADING_RECORD);
        const data = await AttendanceAPI.fetchAttendanceById(attendanceId);
        UIUtils.hideLoading();
        
        if (data.success && data.record) {
            this.populateFormForEdit(data.record);
            AttendanceState.modal.style.display = 'block';
        } else {
            alert('Error loading attendance record: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        UIUtils.hideLoading();
        console.error('Error:', error);
        Toast.error('Failed to load attendance record. ');
    }
},

// Replace the populateFormForEdit function with this FINAL corrected version:

populateFormForEdit(record) {
    console.log('Editing attendance record:', record);
    
    // Populate form fields
    document.getElementById('attendance_id').value = record.id;
    document.getElementById('attendance_date').value = record.attendance_date;
    document.getElementById('academic_year_id').value = record.academic_year_id;
    document.getElementById('term_id').value = record.term_id;
    document.getElementById('general_remarks').value = record.remarks || '';
    
    // Get academic year name after setting the dropdown
    const academicYearName = AttendanceState.getAcademicYearName();
    console.log('Academic year for edit:', academicYearName);
    
    // IMPORTANT: Use the numeric database ID, not the student code
    const studentDatabaseId = record.student_db_id || record.student_id;
    const attendanceStatus = record.status;
    
    console.log('Student database ID (numeric):', studentDatabaseId);
    console.log('Student code (string):', record.student_code);
    console.log('Attendance status:', attendanceStatus);
    
    // First load the classes for the selected academic year
    this.loadClasses(academicYearName).then(() => {
        // After classes load, set the class
        document.getElementById('class_id').value = record.class_id;
        
        // Load students for that class (pass the numeric student ID)
        return this.loadStudents(record.class_id, academicYearName, studentDatabaseId);
    }).then(() => {
        // Small delay to ensure DOM is fully rendered
        return new Promise(resolve => setTimeout(resolve, 150));
    }).then(() => {
        // Mark the attendance status using the numeric database ID
        console.log('Attempting to mark attendance for student ID:', studentDatabaseId, 'Status:', attendanceStatus);
        
        const radioSelector = `input[name="attendance[${studentDatabaseId}]"][value="${attendanceStatus}"]`;
        console.log('Radio selector:', radioSelector);
        
        const radio = document.querySelector(radioSelector);
        
        if (radio) {
            radio.checked = true;
            const row = radio.closest('tr');
            UIUtils.updateRowStatus(row, attendanceStatus);
            console.log('✅ Successfully marked attendance status');
        } else {
            console.warn(`❌ Radio button not found with selector: ${radioSelector}`);
            console.log('Available attendance inputs:');
            document.querySelectorAll('input[type="radio"][name^="attendance"]').forEach(r => {
                console.log(` - ${r.name} = ${r.value}`);
            });
            Toast.warning('Could not populate existing attendance status. Please select manually.');
        }
    }).catch(error => {
        console.error('Error during edit form population:', error);
        Toast.error('Failed to load form data for editing.');
    });
},
 
    async deleteAttendance(id) {
        if (!confirm(CONFIG.MESSAGES.CONFIRM_DELETE)) return;
        
        try {
            UIUtils.showLoading(CONFIG.MESSAGES.DELETING_RECORD);
            const data = await AttendanceAPI.deleteAttendance(id);
            UIUtils.hideLoading();
            
            if (data.success) {
                Toast.success(data.message);
                location.reload();
            } else {
                Toast.error('Error: ' + data.message);
            }
        } catch (error) {
            UIUtils.hideLoading();
            console.error('Error:', error);
            Toast.warning('Failed to delete attendance record.');
        }
    },
    
    loadStudentDetails(studentId) {
        const { classId } = AttendanceState.getCurrentFormData();
        const academicYearName = AttendanceState.getAcademicYearName();
        
        if (!classId) {
            alert(CONFIG.MESSAGES.SELECT_CLASS_FIRST);
            document.getElementById('student_select').value = '';
            return;
        }
        
        if (!academicYearName) {
            alert(CONFIG.MESSAGES.SELECT_ACADEMIC_YEAR_FIRST);
            document.getElementById('student_select').value = '';
            return;
        }
        
        if (studentId) {
            this.loadStudents(classId, academicYearName, studentId);
        } else {
            this.loadStudents(classId, academicYearName);
        }
    },
    // Add these methods to AttendanceController
showSuccessMessage(message, autoCloseAndReload = false) {
    const successModal = document.getElementById('successModal');
    const successMessage = document.getElementById('successMessage');
    
    if (successModal && successMessage) {
        successMessage.textContent = message;
        successModal.style.display = 'block';
        
        if (autoCloseAndReload) {
            // Auto close after 3 seconds and reload
            successModal.classList.add('auto-close');
            setTimeout(() => {
                this.closeSuccessModal();
                location.reload();
            }, 3000);
        }
    } else {
        // Fallback to alert if modal not found
        alert('Success: ' + message);
        if (autoCloseAndReload) {
            location.reload();
        }
    }
},

showErrorMessage(message) {
    // You can create an error modal similar to success modal, or use alert
    const errorModal = document.getElementById('errorModal'); // You can create this similarly
    if (errorModal) {
        // Similar to success modal but with error styling
        document.getElementById('errorMessage').textContent = message;
        errorModal.style.display = 'block';
    } else {
        alert('Error: ' + message);
    }
},

closeSuccessModal() {
    const successModal = document.getElementById('successModal');
    if (successModal) {
        successModal.style.display = 'none';
        successModal.classList.remove('auto-close');
    }
}
};


// Add to global functions section
function closeSuccessModal() {
    AttendanceController.closeSuccessModal();
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const successModal = document.getElementById('successModal');
    if (event.target === successModal) {
        closeSuccessModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeSuccessModal();
    }
});
// ============================================
// MODULE: Event Handlers & Initialization
// ============================================
const EventHandlers = {
    init() {
        document.addEventListener('DOMContentLoaded', () => {
            AttendanceState.init();
            this.setupFormHandlers();
            this.setupClassAndDateHandlers();
            this.setupAcademicYearHandler();
            this.setupModalHandlers();
        });
    },
    
    setupFormHandlers() {
        const attendanceForm = document.getElementById('attendanceForm');
        if (attendanceForm) {
            attendanceForm.addEventListener('submit', (e) => {
                e.preventDefault();
                AttendanceController.submitAttendance(e.target);
            });
        }
    },
    
    setupClassAndDateHandlers() {
        const classSelect = document.getElementById('class_id');
        const dateInput = document.getElementById('attendance_date');
        
        if (classSelect) {
            classSelect.addEventListener('change', function() {
                const academicYearName = AttendanceState.getAcademicYearName();
                AttendanceController.loadStudents(this.value, academicYearName);
                AttendanceController.loadExistingAttendance();
            });
        }
        
        if (dateInput) {
            dateInput.addEventListener('change', () => {
                AttendanceController.loadExistingAttendance();
            });
        }
    },
    
setupAcademicYearHandler() {
    const academicYearSelect = document.getElementById('academic_year_id');
    if (academicYearSelect) {
        academicYearSelect.addEventListener('change', function() {
            if (this.value) {
                // Get the clean academic year name from data attribute
                const selectedOption = this.options[this.selectedIndex];
                const academicYearName = selectedOption.getAttribute('data-year-name');
                
                console.log('Academic year name from data attribute:', academicYearName);
                AttendanceController.loadClasses(academicYearName);
            } else {
                const classSelect = document.getElementById('class_id');
                classSelect.innerHTML = '<option value="">Select Academic Year First</option>';
            }
            
            document.getElementById('studentsAttendanceList').innerHTML = 
                '<tr><td colspan="4" class="text-center">Select class to load students</td></tr>';
        });
    }
},
    
    setupModalHandlers() {
        const closeBtn = document.querySelector('.close');
        if (closeBtn) {
            closeBtn.onclick = () => ModalManager.close();
        }
        
        window.onclick = (event) => {
            if (event.target === AttendanceState.modal) {
                ModalManager.close();
            }
        };
    }
};


function debugAcademicYearOptions() {
    const academicYearSelect = document.getElementById('academic_year_id');
    console.log('=== DEBUG ACADEMIC YEAR OPTIONS ===');
    for (let i = 0; i < academicYearSelect.options.length; i++) {
        const option = academicYearSelect.options[i];
        const rawText = option.textContent;
        const cleanedText = rawText.replace(/\s+/g, ' ').trim();
        console.log(`Option ${i}: value="${option.value}", rawText="${rawText}", cleanedText="${cleanedText}"`);
    }
}

// Call this in your DOMContentLoaded or after page load
document.addEventListener('DOMContentLoaded', function() {
    debugAcademicYearOptions();
});

// ============================================
// GLOBAL FUNCTIONS (for backward compatibility)
// ============================================
function markAttendance() {
    ModalManager.open();
}

function closeModal() {
    ModalManager.close();
}

function loadStudents(classId, academicYearId = null, specificStudentId = null) {
    // Convert academicYearId to name if needed
    const academicYearName = academicYearId || AttendanceState.getAcademicYearName();
    AttendanceController.loadStudents(classId, academicYearName, specificStudentId);
}

function loadClasses(academicYearId) {
    AttendanceController.loadClasses(academicYearId);
}

function editAttendance(attendanceId) {
    AttendanceController.editAttendance(attendanceId);
}

function deleteAttendance(id) {
    AttendanceController.deleteAttendance(id);
}

function loadStudentDetails(studentId) {
    AttendanceController.loadStudentDetails(studentId);
}

// Initialize the application
EventHandlers.init();

// Handle escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

// Handle mobile virtual keyboard issues
window.addEventListener('resize', function() {
    const modal = document.getElementById('markAttendanceModal');
    if (modal && modal.style.display === 'block') {
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) modalBody.scrollTop = 0;
    }
});

// ============================================
// MODULE: Toast Notifications
// ============================================
const Toast = {
    container: null,

    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            this.container.style.position = 'fixed';
            this.container.style.top = '20px';
            this.container.style.right = '20px';
            this.container.style.zIndex = '9999';
            this.container.style.display = 'flex';
            this.container.style.flexDirection = 'column';
            this.container.style.gap = '10px';
            document.body.appendChild(this.container);
        }
    },

    show(message, type = 'info', duration = 3000) {
        this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;

        // Styling (simple and clean)
        toast.style.padding = '10px 15px';
        toast.style.borderRadius = '6px';
        toast.style.color = '#fff';
        toast.style.fontSize = '14px';
        toast.style.boxShadow = '0 2px 6px rgba(0,0,0,0.2)';
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';

        // Type colors
        if (type === 'success') toast.style.backgroundColor = '#28a745';
        else if (type === 'error') toast.style.backgroundColor = '#dc3545';
        else if (type === 'warning') toast.style.backgroundColor = '#ffc107';
        else toast.style.backgroundColor = '#007bff';

        this.container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        });

        // Auto-remove
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    success(message, duration) {
        this.show(message, 'success', duration);
    },
    error(message, duration) {
        this.show(message, 'error', duration);
    },
    warning(message, duration) {
        this.show(message, 'warning', duration);
    },
    info(message, duration) {
        this.show(message, 'info', duration);
    }
};
