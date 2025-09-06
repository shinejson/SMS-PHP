// Enhanced students.js
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#studentsTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });

    // Modal elements
    const studentModal = document.getElementById('studentModal');
    const deleteModal = document.getElementById('deleteModal');
    const viewModal = document.getElementById('viewStudentModal'); // New view modal

    // Get all close buttons (X icon) and cancel buttons across all modals
    const allCloseButtons = document.querySelectorAll('.modal .close');
    const allCancelButtons = document.querySelectorAll('.modal .btn-cancel');

    // Store PHP-generated data globally for JS access
    const allStudents = window.allStudentsData || [];
    const allClasses = window.allClassesData || [];

    // Populate class dropdown in the student form
    const classSelect = document.getElementById('class_id');
    if (classSelect) {
        classSelect.innerHTML = '<option value="">Select Class</option>';
        allClasses.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.id;
            option.textContent = cls.class_name;
            classSelect.appendChild(option);
        });
    }

    // Function to open a modal
    function openModal(modalElement) {
        if (modalElement) {
            modalElement.style.display = 'flex'; // Use flex to center
        }
    }

    // Function to close a specific modal
    function closeSpecificModal(modalElement) {
        if (modalElement) {
            modalElement.style.display = 'none';
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

    // Open modal for adding new student
    document.getElementById('addStudentBtn').addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'Add New Student';
        document.getElementById('formAction').value = 'add_student';
        document.getElementById('studentId').value = '';
        document.getElementById('studentForm').reset();
        openModal(studentModal);
    });

    // Handle edit button clicks
    document.querySelectorAll('.edit-student').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const student = allStudents.find(s => s.id == id);

            if (student) {
                document.getElementById('modalTitle').textContent = 'Edit Student';
                document.getElementById('formAction').value = 'update_student';
                document.getElementById('studentId').value = student.id;

                // Populate form fields
                document.getElementById('first_name').value = student.first_name;
                document.getElementById('last_name').value = student.last_name;
                document.getElementById('dob').value = student.dob;
                document.getElementById('gender').value = student.gender;
                document.getElementById('class_id').value = student.class_id || '';
                document.getElementById('parent_name').value = student.parent_name;
                document.getElementById('parent_contact').value = student.parent_contact;
                document.getElementById('email').value = student.email;
                document.getElementById('address').value = student.address;

                openModal(studentModal);
            } else {
                console.error('Student not found for editing, ID:', id);
            }
        });
    });

    // Handle delete button clicks
// Handle delete button clicks
document.querySelectorAll('.delete-student').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const student = allStudents.find(s => s.id == id);

        if (student) {
            const deleteIdInput = document.getElementById('deleteId');
            if (deleteIdInput) { // Defensive check for the element
                deleteIdInput.value = id;
            } else {
                console.error("Error: 'deleteId' input not found in the DOM. Please ensure the delete modal HTML is correctly loaded.");
            }
            document.querySelector('#deleteModal p').textContent = `Are you sure you want to delete ${student.first_name} ${student.last_name}? This action cannot be undone.`;
            openModal(deleteModal);
        } else {
            console.error('Student not found for deletion, ID:', id);
        }
    });
});

    /**
     * Populates and opens a read-only view modal.
     * @param {string} id - The ID of the student to view.
     */
    function viewStudentDetails(id) {
        const student = allStudents.find(s => s.id == id);

        if (student) {
            document.getElementById('viewStudentId').textContent = student.student_id;
            document.getElementById('viewStudentName').textContent = `${student.first_name} ${student.last_name}`;
            document.getElementById('viewStudentDob').textContent = student.dob;
            document.getElementById('viewStudentGender').textContent = student.gender;
            document.getElementById('viewStudentClass').textContent = student.class_name || 'N/A';
            document.getElementById('viewStudentParentName').textContent = student.parent_name || 'N/A';
            document.getElementById('viewStudentParentContact').textContent = student.parent_contact || 'N/A';
            document.getElementById('viewStudentEmail').textContent = student.email || 'N/A';
            document.getElementById('viewStudentAddress').textContent = student.address || 'N/A';
            document.getElementById('viewStudentStatus').textContent = student.status || 'N/A';
            document.getElementById('viewStudentStatus').className = `status ${student.status ? student.status.toLowerCase() : ''}`;

            openModal(viewModal);
        } else {
            console.error('Student not found for viewing, ID:', id);
        }
    }

    // Attach event listener for view buttons
    document.querySelectorAll('.view-student').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            viewStudentDetails(id);
        });
    });
});

