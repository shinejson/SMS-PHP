// Enhanced students.js with academic year and class status support
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with proper configuration
    const studentsTable = $('#studentsTable').DataTable({
        responsive: true,
        dom: '<"top"Bf>rt<"bottom"lip><"clear">',
        buttons: [
            {
                extend: 'copy',
                className: 'btn-copy',
                text: '<i class="fas fa-copy"></i> Copy'
            },
            {
                extend: 'csv',
                className: 'btn-csv',
                text: '<i class="fas fa-file-csv"></i> CSV'
            },
            {
                extend: 'excel',
                className: 'btn-excel',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            {
                extend: 'pdf',
                className: 'btn-pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF'
            },
            {
                extend: 'print',
                className: 'btn-print',
                text: '<i class="fas fa-print"></i> Print'
            }
        ],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[1, 'asc']], // Sort by name by default
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search students...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });

    // Modal elements
    const studentModal = document.getElementById('studentModal');
    const deleteModal = document.getElementById('deleteModal');
    const viewModal = document.getElementById('viewStudentModal');

    // Get all close buttons (X icon) and cancel buttons across all modals
    const allCloseButtons = document.querySelectorAll('.modal .close');
    const allCancelButtons = document.querySelectorAll('.modal .btn-cancel');

    // Store PHP-generated data globally for JS access
    const allStudents = window.allStudentsData || [];
    const allClasses = window.allClassesData || [];
    const allAcademicYears = window.allAcademicYears || [];

    // Populate class dropdown in the student form
    const classSelect = document.getElementById('class_id');
    const academicYearSelect = document.getElementById('academic_year');
    const classStatusSelect = document.getElementById('class_status');

    function populateClassDropdown() {
        if (classSelect) {
            classSelect.innerHTML = '<option value="">Select Class</option>';
            
            if (allClasses.length === 0) {
                console.warn('No classes available');
                return;
            }
            
            // Show ALL classes without any academic year information
            allClasses.forEach(cls => {
                const option = document.createElement('option');
                option.value = cls.id;
                
                // Only show class name, no academic year
                let displayText = cls.class_name || 'Unnamed Class';
                
                option.textContent = displayText;
                classSelect.appendChild(option);
            });
        }
    }

    function populateAcademicYearDropdown() {
        if (academicYearSelect) {
            academicYearSelect.innerHTML = '<option value="">Select Academic Year</option>';
            allAcademicYears.forEach(year => {
                const option = document.createElement('option');
                option.value = year.id;
                option.textContent = year.year_name;
                academicYearSelect.appendChild(option);
            });
        }
    }

    // Initialize dropdowns
    populateAcademicYearDropdown();
    populateClassDropdown();

    // Update class dropdown when academic year changes
    if (academicYearSelect) {
        academicYearSelect.addEventListener('change', function() {
            const selectedYear = this.value;
            populateClassDropdown(selectedYear);
        });
    }

    // Function to open a modal
    function openModal(modalElement) {
        if (modalElement) {
            modalElement.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    }

    // Function to close a specific modal
    function closeSpecificModal(modalElement) {
        if (modalElement) {
            modalElement.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Attach event listeners for all close buttons (X icon)
    allCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            const parentModal = button.closest('.modal');
            closeSpecificModal(parentModal);
        });
    });

    // Attach event listeners for all cancel buttons
    allCancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            const parentModal = button.closest('.modal');
            closeSpecificModal(parentModal);
        });
    });

    // Close modal when clicking outside of the modal content
    window.addEventListener('click', function(event) {
        if (event.target === studentModal) {
            closeSpecificModal(studentModal);
        } else if (event.target === deleteModal) {
            closeSpecificModal(deleteModal);
        } else if (event.target === viewModal) {
            closeSpecificModal(viewModal);
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSpecificModal(studentModal);
            closeSpecificModal(deleteModal);
            closeSpecificModal(viewModal);
        }
    });

    // Open modal for adding new student
    document.getElementById('addStudentBtn').addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'Add New Student';
        document.getElementById('formAction').value = 'add_student';
        document.getElementById('studentId').value = '';
        document.getElementById('studentForm').reset();
        
        // Reset dropdowns
        populateAcademicYearDropdown();
        populateClassDropdown();
        
        // Enable all form elements
        document.getElementById('studentForm').querySelectorAll('input, select, textarea').forEach(element => {
            element.disabled = false;
        });
        document.querySelector('.btn-submit').disabled = false;
        document.querySelector('.btn-submit').textContent = 'Save';
        
        // Remove any existing warning
        const existingWarning = document.querySelector('#studentForm .alert-warning');
        if (existingWarning) {
            existingWarning.remove();
        }
        
        openModal(studentModal);
    });

    // FIXED: Handle form submission - Submit directly to students.php instead of AJAX
    document.getElementById('studentForm').addEventListener('submit', function(e) {
        // Don't prevent default - let the form submit normally
        // Just do basic validation
        
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const classId = document.getElementById('class_id').value;
        const academicYear = document.getElementById('academic_year').value;
        const classStatus = document.getElementById('class_status').value;
        
        if (!firstName || !lastName) {
            e.preventDefault();
            showNotification('Please enter first and last name!', 'error');
            return false;
        }
        
        if (!classId) {
            e.preventDefault();
            showNotification('Please select a class!', 'error');
            return false;
        }
        
        if (!academicYear) {
            e.preventDefault();
            showNotification('Please select an academic year!', 'error');
            return false;
        }
        
        if (!classStatus) {
            e.preventDefault();
            showNotification('Please select a class status!', 'error');
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('.btn-submit');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;
        
        // Allow form to submit normally to students.php
        return true;
    });

    // Handle edit button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-student')) {
            const button = e.target.closest('.edit-student');
            const id = button.getAttribute('data-id');
            const student = allStudents.find(s => s.id == id);

            if (student) {
                document.getElementById('modalTitle').textContent = 'Edit Student';
                document.getElementById('formAction').value = 'update_student';
                document.getElementById('studentId').value = student.id;

                // Populate form fields
                document.getElementById('first_name').value = student.first_name || '';
                document.getElementById('last_name').value = student.last_name || '';
                document.getElementById('dob').value = student.dob || '';
                document.getElementById('gender').value = student.gender || '';
                document.getElementById('parent_name').value = student.parent_name || '';
                document.getElementById('parent_contact').value = student.parent_contact || '';
                document.getElementById('email').value = student.email || '';
                document.getElementById('address').value = student.address || '';
                
                // Populate academic year and class
                populateAcademicYearDropdown();
                document.getElementById('academic_year').value = student.academic_year_id || '';
                
                // Populate ALL classes
                populateClassDropdown();
                
                // Set the student's class after dropdown is populated
                setTimeout(() => {
                    document.getElementById('class_id').value = student.class_id || '';
                }, 100);
                
                // Set class status
                document.getElementById('class_status').value = student.class_status || 'active';

                // Disable form if student is graduated
                if (student.class_status === 'graduated') {
                    document.getElementById('studentForm').querySelectorAll('input, select, textarea').forEach(element => {
                        element.disabled = true;
                    });
                    document.querySelector('.btn-submit').disabled = true;
                    document.querySelector('.btn-submit').textContent = 'Edit Disabled (Graduated)';
                    
                    // Show warning message
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-warning';
                    warningDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> This student has graduated. Editing is not allowed.';
                    document.getElementById('studentForm').prepend(warningDiv);
                } else {
                    // Enable all form elements
                    document.getElementById('studentForm').querySelectorAll('input, select, textarea').forEach(element => {
                        element.disabled = false;
                    });
                    document.querySelector('.btn-submit').disabled = false;
                    document.querySelector('.btn-submit').textContent = 'Save';
                    
                    // Remove any existing warning
                    const existingWarning = document.querySelector('#studentForm .alert-warning');
                    if (existingWarning) {
                        existingWarning.remove();
                    }
                }

                openModal(studentModal);
            } else {
                console.error('Student not found for editing, ID:', id);
                showNotification('Student not found!', 'error');
            }
        }
    });

    // Handle delete button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-student')) {
            const button = e.target.closest('.delete-student');
            const id = button.getAttribute('data-id');
            const student = allStudents.find(s => s.id == id);

            if (student) {
                const deleteIdInput = document.getElementById('deleteId');
                if (deleteIdInput) {
                    deleteIdInput.value = id;
                } else {
                    console.error("Error: 'deleteId' input not found in the DOM.");
                }
                document.querySelector('#deleteModal p').textContent = `Are you sure you want to delete ${student.first_name} ${student.last_name}? This action cannot be undone.`;
                openModal(deleteModal);
            } else {
                console.error('Student not found for deletion, ID:', id);
            }
        }
    });

    // FIXED: Handle delete form submission - Submit directly to students.php
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        // Show loading state
        const submitBtn = this.querySelector('.btn-danger');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        submitBtn.disabled = true;
        
        // Allow form to submit normally
        return true;
    });

    /**
     * Populates and opens a read-only view modal.
     * @param {string} id - The ID of the student to view.
     */
    function viewStudentDetails(id) {
        const student = allStudents.find(s => s.id == id);

        if (student) {
            document.getElementById('viewStudentId').textContent = student.student_id || 'N/A';
            document.getElementById('viewStudentName').textContent = `${student.first_name} ${student.last_name}`;
            document.getElementById('viewStudentDob').textContent = student.dob || 'N/A';
            document.getElementById('viewStudentGender').textContent = student.gender || 'N/A';
            document.getElementById('viewStudentClass').textContent = student.class_name || 'N/A';
            document.getElementById('viewStudentAcademicYear').textContent = student.academic_year_name || 'N/A';
            
            // Class status with badge
            const classStatusElement = document.getElementById('viewStudentClassStatus');
            classStatusElement.textContent = student.class_status ? 
                student.class_status.charAt(0).toUpperCase() + student.class_status.slice(1) : 'Active';
            classStatusElement.className = `status-badge status-${student.class_status || 'active'}`;
            
            document.getElementById('viewStudentParentName').textContent = student.parent_name || 'N/A';
            document.getElementById('viewStudentParentContact').textContent = student.parent_contact || 'N/A';
            document.getElementById('viewStudentEmail').textContent = student.email || 'N/A';
            document.getElementById('viewStudentAddress').textContent = student.address || 'N/A';
            
            const statusElement = document.getElementById('viewStudentStatus');
            statusElement.textContent = student.status || 'N/A';
            statusElement.className = `status ${student.status ? student.status.toLowerCase() : ''}`;

            openModal(viewModal);
        } else {
            console.error('Student not found for viewing, ID:', id);
        }
    }

    // Attach event listener for view buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-student')) {
            const button = e.target.closest('.view-student');
            const id = button.getAttribute('data-id');
            viewStudentDetails(id);
        }
    });

    // Filter form enhancement
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        // Add loading state to filter buttons
        filterForm.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
                submitBtn.disabled = true;
            }
        });
    }

    // Notification function
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `custom-notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(notification);

        // Show notification
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        // Hide after 5 seconds
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
    }
});