document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with dark mode support
    $('#teachersTable').DataTable({
        responsive: true,
        dom: '<"top"fB>rt<"bottom"lip><"clear">',
        buttons: [
            {
                extend: 'copy',
                text: '<i class="fas fa-copy"></i> Copy',
                className: 'btn-copy'
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn-csv'
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn-excel'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn-pdf'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn-print'
            }
        ],
        columnDefs: [
            { orderable: false, targets: [6] } // Disable sorting for actions column
        ],
        initComplete: function() {
            // Dark mode adjustments
            if (document.body.classList.contains('dark-mode')) {
                this.api().tables().header().to$().addClass('dark-mode-header');
                $('.dataTables_wrapper').addClass('dark-mode-wrapper');
            }
        }
    });
    
    // Modal elements
    const modal = document.getElementById('teacherModal');
    const deleteModal = document.getElementById('deleteModal');
    const modalTitle = document.getElementById('modalTitle');
    const teacherForm = document.getElementById('teacherForm');
    const formAction = document.getElementById('formAction');
    const teacherId = document.getElementById('teacherId');
    const deleteIdInput = document.getElementById('deleteId');
    const closeBtn = document.querySelector('#teacherModal .close');
    const cancelBtn = document.querySelector('#teacherModal .btn-cancel');
    const deleteCloseBtn = document.querySelector('#deleteModal .close');
    const deleteCancelBtn = document.querySelector('#deleteModal .btn-cancel');
    
    // Auto-hide alert messages after 5 seconds
    const alertMessages = document.querySelectorAll('.alert');
    alertMessages.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500); // Wait for fade out animation to complete
        }, 5000); // 5 seconds delay
    });
    
    // Modal utility functions
    function openModal(modalElement) {
        modalElement.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
    
    function closeModal(modalElement) {
        modalElement.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
    
    // Open modal for adding new teacher
    document.getElementById('addTeacherBtn').addEventListener('click', function() {
        modalTitle.textContent = 'Add New Teacher';
        formAction.name = 'add_teacher';
        formAction.value = '1';
        teacherId.value = '';
        teacherForm.reset();
        openModal(modal);
    });
    
    // Close teacher modal handlers
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            closeModal(modal);
        });
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            closeModal(modal);
        });
    }
    
    // Close delete modal handlers
    if (deleteCloseBtn) {
        deleteCloseBtn.addEventListener('click', function() {
            closeModal(deleteModal);
        });
    }
    
    if (deleteCancelBtn) {
        deleteCancelBtn.addEventListener('click', function() {
            closeModal(deleteModal);
        });
    }
    
    // Removed click outside to close modal functionality
    
    // Handle edit button clicks
    document.querySelectorAll('.edit-teacher').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Fetch teacher data
            fetchTeacherData(id).then(teacher => {
                modalTitle.textContent = 'Edit Teacher';
                formAction.name = 'update_teacher';
                formAction.value = '1';
                teacherId.value = id;
                
                // Populate form fields
                document.getElementById('first_name').value = teacher.first_name || '';
                document.getElementById('last_name').value = teacher.last_name || '';
                document.getElementById('email').value = teacher.email || '';
                document.getElementById('phone').value = teacher.phone || '';
                document.getElementById('specialization').value = teacher.specialization || '';
                document.getElementById('status').value = teacher.status || 'Active';
                
                openModal(modal);
            });
        });
    });
    
    // Handle delete button clicks
    document.querySelectorAll('.delete-teacher').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Get teacher data for confirmation message
            fetchTeacherData(id).then(teacher => {
                if (teacher && teacher.first_name) {
                    deleteIdInput.value = id;
                    const confirmMessage = `Are you sure you want to delete ${teacher.first_name} ${teacher.last_name}? This action cannot be undone.`;
                    document.querySelector('#deleteModal p').textContent = confirmMessage;
                    openModal(deleteModal);
                } else {
                    // Fallback if teacher data not found
                    deleteIdInput.value = id;
                    document.querySelector('#deleteModal p').textContent = 'Are you sure you want to delete this teacher? This action cannot be undone.';
                    openModal(deleteModal);
                }
            }).catch(error => {
                console.error('Error fetching teacher data:', error);
                // Still allow deletion with generic message
                deleteIdInput.value = id;
                document.querySelector('#deleteModal p').textContent = 'Are you sure you want to delete this teacher? This action cannot be undone.';
                openModal(deleteModal);
            });
        });
    });
    
    // Fetch teacher data from PHP
    function fetchTeacherData(id) {
        return new Promise((resolve, reject) => {
            try {
                // Get teachers data from PHP (this should be available from the PHP file)
                const teachers = window.teachersData || [];
                const teacher = teachers.find(t => t.id == id);
                resolve(teacher || {});
            } catch (error) {
                reject(error);
            }
        });
    }
    
    // Handle form submissions
    if (teacherForm) {
        teacherForm.addEventListener('submit', function(e) {
            // Add any client-side validation here if needed
            // The form will submit normally to the PHP handler
        });
    }
    
    // Escape key to close modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (modal.style.display === 'block') {
                closeModal(modal);
            }
            if (deleteModal.style.display === 'block') {
                closeModal(deleteModal);
            }
        }
    });
});