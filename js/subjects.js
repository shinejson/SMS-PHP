document.addEventListener('DOMContentLoaded', function () {
    // Initialize DataTable with enhanced buttons and no ID column
    const subjectsTable = $('#subjectsTable').DataTable({
        responsive: true,
        dom: '<"top"Bf>rt<"bottom"lip><"clear">',
        buttons: [
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
            },
            {
                extend: 'copy',
                className: 'btn-copy',
                text: '<i class="fas fa-copy"></i> Copy'
            },
            {
                extend: 'csv',
                className: 'btn-csv',
                text: '<i class="fas fa-file-csv"></i> CSV'
            }
        ],
        columnDefs: [
            { 
                // Hide the ID column (index 0)
                targets: 0,
                visible: false,
                searchable: false
            },
            { 
                // Make actions column not orderable
                targets: 4, // Now index 4 because we removed ID column from display
                orderable: false 
            }
        ],
        order: [[1, 'asc']], // Order by subject name (now column index 1)
        pageLength: 10,
        lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search subjects...",
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
    const subjectModal = document.getElementById('subjectModal');
    const deleteModal = document.getElementById('deleteModal');
    const modalTitle = document.getElementById('modalTitle');
    const subjectForm = document.getElementById('subjectForm');
    const formAction = document.getElementById('formAction');
    const subjectId = document.getElementById('subjectId');
    const subjectNameInput = document.getElementById('subject_name');
    const subjectCodeInput = document.getElementById('subject_code');
    const descriptionInput = document.getElementById('description');
    const deleteIdInput = document.getElementById('deleteId');

    // Function to show/hide modal
    function toggleModal(modalElement, show = true) {
        if (show) {
            modalElement.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        } else {
            modalElement.style.display = 'none';
            document.body.style.overflow = ''; // Re-enable scrolling
        }
    }

    // Add Subject button click
    document.getElementById('addSubjectBtn').addEventListener('click', () => {
        modalTitle.textContent = 'Add New Subject';
        subjectForm.reset();
        formAction.value = 'add_subject';
        subjectId.value = '';
        // Hide the subject code field for new entries
        document.getElementById('subjectCodeGroup').style.display = 'none';
        toggleModal(subjectModal, true);
    });

    // Edit Subject button click (using event delegation)
    document.addEventListener('click', function (e) {
        if (e.target.closest('.edit-subject')) {
            const btn = e.target.closest('.edit-subject');
            // Get the row data from DataTables
            const row = subjectsTable.row(btn.closest('tr'));
            const data = row.data();
            
            modalTitle.textContent = 'Edit Subject';
            formAction.value = 'update_subject';
            subjectId.value = data[0]; // ID is in hidden column
            subjectNameInput.value = data[1]; // Subject name
            subjectCodeInput.value = data[2]; // Subject code
            descriptionInput.value = data[3]; // Description
            
            // Show the subject code field for editing
            document.getElementById('subjectCodeGroup').style.display = 'block';
            toggleModal(subjectModal, true);
        }
    });

    // Delete Subject button click (using event delegation)
    document.addEventListener('click', function (e) {
        if (e.target.closest('.delete-subject')) {
            const btn = e.target.closest('.delete-subject');
            const id = btn.getAttribute('data-id');
            deleteIdInput.value = id;
            toggleModal(deleteModal, true);
        }
    });

    // Close modals
    document.querySelectorAll('.close, .btn-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            toggleModal(subjectModal, false);
            toggleModal(deleteModal, false);
        });
    });

    // Close modal by clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === subjectModal) {
            toggleModal(subjectModal, false);
        }
        if (e.target === deleteModal) {
            toggleModal(deleteModal, false);
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            toggleModal(subjectModal, false);
            toggleModal(deleteModal, false);
        }
    });

    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade-out');
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // Handle sidebar collapse
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle && mainContent) {
        sidebarToggle.addEventListener('click', () => {
            mainContent.classList.toggle('collapsed');
        });
    }
});