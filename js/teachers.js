document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with export buttons
    const teachersTable = $('#teachersTable').DataTable({
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
            searchPlaceholder: "Search teachers...",
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
    const teacherModal = document.getElementById('teacherModal');
    const deleteModal = document.getElementById('deleteModal');
    const teacherForm = document.getElementById('teacherForm');
    const deleteForm = document.getElementById('deleteForm');

    // Get all close buttons and cancel buttons
    const allCloseButtons = document.querySelectorAll('.modal .close');
    const allCancelButtons = document.querySelectorAll('.modal .btn-cancel');

    // Store teachers data globally
    const allTeachers = window.teachersData || [];

    // Function to open a modal with proper scrolling
    function openModal(modalElement) {
        if (modalElement) {
            modalElement.style.display = 'block'; // ✅ Changed from 'flex' to 'block'
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            // Scroll modal to top when opening
            modalElement.scrollTop = 0;
            // Scroll modal content to top
            const modalContent = modalElement.querySelector('.modal-content');
            if (modalContent) {
                modalContent.scrollTop = 0;
            }
        }
    }

    // Function to close a specific modal
    function closeSpecificModal(modalElement) {
        if (modalElement) {
            modalElement.style.display = 'none';
            document.body.style.overflow = ''; // Restore background scrolling
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
        if (event.target === teacherModal) {
            closeSpecificModal(teacherModal);
        } else if (event.target === deleteModal) {
            closeSpecificModal(deleteModal);
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSpecificModal(teacherModal);
            closeSpecificModal(deleteModal);
        }
    });

    // Open modal for adding new teacher
    document.getElementById('addTeacherBtn').addEventListener('click', function() {
        document.getElementById('modalTitle').textContent = 'Add New Teacher';
        document.getElementById('formAction').name = 'add_teacher';
        document.getElementById('formAction').value = '1';
        document.getElementById('teacherId').value = '';
        teacherForm.reset();
        
        // Enable all form elements
        teacherForm.querySelectorAll('input, select').forEach(element => {
            element.disabled = false;
        });
        
        openModal(teacherModal);
    });

   // Handle edit button clicks
document.addEventListener('click', function(e) {
    if (e.target.closest('.edit-teacher')) {
        const button = e.target.closest('.edit-teacher');
        const id = button.getAttribute('data-id');
        const teacher = allTeachers.find(t => t.id == id);

        if (teacher) {
            document.getElementById('modalTitle').textContent = 'Edit Teacher';
            document.getElementById('formAction').name = 'update_teacher'; // ← CHANGED TO 'update_teacher'
            document.getElementById('formAction').value = '1';
            document.getElementById('teacherId').value = teacher.id;

            // Populate form fields
            document.getElementById('first_name').value = teacher.first_name || '';
            document.getElementById('last_name').value = teacher.last_name || '';
            document.getElementById('email').value = teacher.email || '';
            document.getElementById('phone').value = teacher.phone || '';
            document.getElementById('specialization').value = teacher.specialization || '';
            document.getElementById('status').value = teacher.status || 'Active';

            openModal(teacherModal);
        } else {
            console.error('Teacher not found for editing, ID:', id);
            showNotification('Teacher not found!', 'error');
        }
    }
});

    // Handle delete button clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-teacher')) {
            const button = e.target.closest('.delete-teacher');
            const id = button.getAttribute('data-id');
            const teacher = allTeachers.find(t => t.id == id);

            if (teacher) {
                document.getElementById('deleteId').value = id;
                openModal(deleteModal);
            } else {
                console.error('Teacher not found for deletion, ID:', id);
            }
        }
    });

    // Handle form submission
    teacherForm.addEventListener('submit', function(e) {
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const email = document.getElementById('email').value.trim();

        // Basic validation
        if (!firstName || !lastName || !email) {
            e.preventDefault();
            showNotification('Please fill in all required fields!', 'error');
            return false;
        }

        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            e.preventDefault();
            showNotification('Please enter a valid email address!', 'error');
            return false;
        }

        // Show loading state
        const submitBtn = this.querySelector('.btn-submit');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        submitBtn.disabled = true;

        // Allow form to submit normally
        return true;
    });

    // Handle delete form submission
    deleteForm.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('.btn-danger');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
        submitBtn.disabled = true;
        
        return true;
    });

    // Notification function
    function showNotification(message, type = 'info') {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.custom-notification');
        existingNotifications.forEach(notification => notification.remove());

        const notification = document.createElement('div');
        notification.className = `custom-notification notification-${type}`;
        
        const iconMap = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${iconMap[type] || 'info-circle'}"></i>
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

    // Add CSS for notifications if not already present
    if (!document.getElementById('notification-styles')) {
        const style = document.createElement('style');
        style.id = 'notification-styles';
        style.textContent = `
            .custom-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                min-width: 300px;
                max-width: 500px;
                padding: 1rem 1.5rem;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                opacity: 0;
                transform: translateX(400px);
                transition: all 0.3s ease;
            }
            .custom-notification.show {
                opacity: 1;
                transform: translateX(0);
            }
            .custom-notification.notification-success {
                border-left: 4px solid #4caf50;
            }
            .custom-notification.notification-error {
                border-left: 4px solid #f44336;
            }
            .custom-notification.notification-warning {
                border-left: 4px solid #ff9800;
            }
            .custom-notification.notification-info {
                border-left: 4px solid #2196f3;
            }
            .notification-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
            .notification-content i {
                font-size: 1.5rem;
            }
            .notification-success .notification-content i {
                color: #4caf50;
            }
            .notification-error .notification-content i {
                color: #f44336;
            }
            .notification-warning .notification-content i {
                color: #ff9800;
            }
            .notification-info .notification-content i {
                color: #2196f3;
            }
        `;
        document.head.appendChild(style);
    }

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 500);
        }, 5000);
    });

    // Click to dismiss alerts
    alerts.forEach(alert => {
        alert.style.cursor = 'pointer';
        alert.addEventListener('click', function() {
            this.style.opacity = '0';
            this.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                if (this.parentNode) {
                    this.remove();
                }
            }, 500);
        });
    });
});