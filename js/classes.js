(function() {
    // Get CSRF token (make sure this is available from PHP)
    const csrfToken = window.csrfToken || '';

    document.addEventListener('DOMContentLoaded', function () {
        // Auto-hide alert messages after 5 seconds
        const alertMessages = document.querySelectorAll('.alert');
        alertMessages.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });

        // Initialize DataTable with proper pagination and export buttons
        const classesTable = $('#classesTable').DataTable({
            responsive: true,
            dom: '<"top"Bf>rt<"bottom"lip><"clear">',
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
                { orderable: false, targets: [5] } // Disable sorting for actions column
            ],
            // Pagination settings
            paging: true,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
            pagingType: "full_numbers",
            
            // Search settings
            searching: true,
            
            // Info settings
            info: true,
            
            // Language settings for better UX
            language: {
                search: "Search classes:",
                lengthMenu: "Show _MENU_ classes per page",
                info: "Showing _START_ to _END_ of _TOTAL_ classes",
                infoEmpty: "No classes available",
                infoFiltered: "(filtered from _MAX_ total classes)",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                },
                emptyTable: "No classes found",
                zeroRecords: "No matching classes found"
            },

            // Dark mode adjustments
            initComplete: function() {
                if (document.body.classList.contains('dark-mode')) {
                    this.api().tables().header().to$().addClass('dark-mode-header');
                    $('.dataTables_wrapper').addClass('dark-mode-wrapper');
                }
            }
        });

        // Modal elements
        const modal = document.getElementById('classModal');
        const modalTitle = document.getElementById('modalTitle');
        const classForm = document.getElementById('classForm');
        const classIdInput = document.getElementById('classId');
        const formActionInput = document.getElementById('formAction');

        // Delete Modal elements
        const deleteModal = document.getElementById('deleteModal');
        const deleteIdInput = document.getElementById('deleteId');

        // Modal utility functions
        function openModal(modalElement) {
            modalElement.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalElement) {
            modalElement.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Add Class Button
        const addClassBtn = document.getElementById('addClassBtn');
        if (addClassBtn) {
            addClassBtn.addEventListener('click', () => {
                modalTitle.textContent = 'Add New Class';
                classForm.reset();
                classIdInput.value = '';
                formActionInput.value = 'add_class';
                openModal(modal);
            });
        }

        // Edit Class (event delegation for dynamic content)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.edit-class');
            if (!btn) return;

            const id = btn.getAttribute('data-id');
            const className = btn.getAttribute('data-class-name') || '';
            const academicYear = btn.getAttribute('data-academic-year') || '';
            const teacherId = btn.getAttribute('data-teacher-id') || '';
            const description = btn.getAttribute('data-description') || '';

            modalTitle.textContent = 'Edit Class';
            classIdInput.value = id;
            formActionInput.value = 'update_class';

            // Fill form fields
            const classNameInput = document.getElementById('class_name');
            const academicYearSelect = document.getElementById('academic_year');
            const teacherSelect = document.getElementById('class_teacher_id');
            const descriptionInput = document.getElementById('description');

            if (classNameInput) classNameInput.value = className;
            if (academicYearSelect) academicYearSelect.value = academicYear;
            if (teacherSelect) teacherSelect.value = teacherId;
            if (descriptionInput) descriptionInput.value = description;

            openModal(modal);
        });

        // Delete Class (event delegation)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.delete-class');
            if (!btn) return;

            const id = btn.getAttribute('data-id');
            const className = btn.getAttribute('data-class-name') || 'this class';
            const billingCount = parseInt(btn.getAttribute('data-billing-count') || '0');
            const studentCount = parseInt(btn.getAttribute('data-student-count') || '0');
            const assignmentCount = parseInt(btn.getAttribute('data-assignment-count') || '0');
            const attendanceCount = parseInt(btn.getAttribute('data-attendance-count') || '0');

            if (deleteModal && deleteIdInput) {
                deleteIdInput.value = id;
                const deleteMessage = document.querySelector('#deleteModal p');
                if (deleteMessage) {
                    let message = `Are you sure you want to delete the class "${className}"?`;
                    
                    // Add warnings about dependencies
                    if (billingCount > 0 || studentCount > 0 || assignmentCount > 0 || attendanceCount > 0) {
                        message += '\n\nWarning: This class has associated records:';
                        if (studentCount > 0) {
                            message += `\n• ${studentCount} student(s)`;
                        }
                        if (billingCount > 0) {
                            message += `\n• ${billingCount} billing record(s)`;
                        }
                        if (assignmentCount > 0) {
                            message += `\n• ${assignmentCount} assignment(s)`;
                        }
                        if (attendanceCount > 0) {
                            message += `\n• ${attendanceCount} attendance record(s)`;
                        }
                        message += '\n\nThese records may prevent deletion. Consider removing or reassigning them first.';
                    }
                    
                    message += '\n\nThis action cannot be undone.';
                    deleteMessage.textContent = message;
                }
                openModal(deleteModal);
            } else {
                // Fallback to confirm dialog if modal not available
                let confirmMessage = `Are you sure you want to delete "${className}"?`;
                
                if (billingCount > 0 || studentCount > 0 || assignmentCount > 0 || attendanceCount > 0) {
                    confirmMessage += '\n\nWarning: This class has associated records that may prevent deletion.';
                }
                
                confirmMessage += '\n\nThis action cannot be undone.';
                
                if (confirm(confirmMessage)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.pathname;

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = id;

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_class';
                    actionInput.value = '1';

                    if (csrfToken) {
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = 'csrf_token';
                        csrfInput.value = csrfToken;
                        form.appendChild(csrfInput);
                    }

                    form.appendChild(idInput);
                    form.appendChild(actionInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        });

        // Close modal handlers
        const closeButtons = document.querySelectorAll('.close');
        closeButtons.forEach(closeBtn => {
            closeBtn.addEventListener('click', function() {
                const modalParent = this.closest('.modal');
                if (modalParent) {
                    closeModal(modalParent);
                }
            });
        });

        const cancelButtons = document.querySelectorAll('.btn-cancel');
        cancelButtons.forEach(cancelBtn => {
            cancelBtn.addEventListener('click', function() {
                const modalParent = this.closest('.modal');
                if (modalParent) {
                    closeModal(modalParent);
                }
            });
        });

        // Escape key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (modal && modal.style.display === 'block') {
                    closeModal(modal);
                }
                if (deleteModal && deleteModal.style.display === 'block') {
                    closeModal(deleteModal);
                }
            }
        });

        // Form submission handler (optional validation)
        if (classForm) {
            classForm.addEventListener('submit', function(e) {
                // Add any client-side validation here if needed
                const className = document.getElementById('class_name');
                if (className && !className.value.trim()) {
                    e.preventDefault();
                    alert('Please enter a class name.');
                    className.focus();
                    return false;
                }
            });
        }
    });
})();