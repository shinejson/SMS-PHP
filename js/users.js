document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing user management');
    
    // Debug: Log all important elements
    const debugElements = {
        modal: document.getElementById('userModal'),
        addBtn: document.getElementById('addUserBtn'),
        form: document.getElementById('userForm')
    };
    console.log('Debug Elements:', debugElements);

    // Only proceed if all elements exist
    if (!debugElements.modal || !debugElements.addBtn || !debugElements.form) {
        console.error('Critical elements missing');
        return;
    }

    // Add event listener with proper error handling
    debugElements.addBtn.addEventListener('click', function(e) {
        console.log('Add button clicked');
        e.preventDefault();
        
        // Reset form
        debugElements.form.reset();
        document.getElementById('formAction').name = 'add_user';
        document.getElementById('userId').value = '';
        
        // Show modal
        debugElements.modal.style.display = 'block';
        debugElements.modal.classList.add('modal-open');
        
        console.log('Modal should be visible now');
    });

    // Modal elements
    const modal = document.getElementById('userModal');
    const addBtn = document.getElementById('addUserBtn');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.querySelector('.btn-cancel');
    const form = document.getElementById('userForm');
    const modalTitle = document.getElementById('modalTitle');
    const passwordLabel = document.getElementById('passwordLabel');
    const passwordHelp = document.getElementById('passwordHelp');
    const passwordField = document.getElementById('password');

    // Open modal for adding new user
 document.getElementById('addUserBtn').addEventListener('click', function() {
    // Reset form and set to add mode
    form.reset();
    document.getElementById('formAction').name = 'add_user';
    document.getElementById('userId').value = '';
    modalTitle.textContent = 'Add New User';
    passwordLabel.textContent = 'Password*';
    passwordField.required = true;
    passwordField.value = ''; // Clear password field
    passwordHelp.style.display = 'none';
    
    // Show modal with animation
    modal.style.display = 'block';
    modal.classList.add('modal-open');
    
    // Debugging log
    console.log('Add User modal opened');
});
    // Close modal
    function closeModal() {
        modal.style.display = 'none';
        const errorAlert = document.querySelector('.alert-danger');
        if (errorAlert) errorAlert.style.display = 'none';
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    // Close when clicking outside modal
    window.addEventListener('click', function(event) {
        if (event.target === modal) closeModal();
    });

    // Edit user functionality
    document.querySelectorAll('.edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const userRow = this.closest('tr');
            
            // Fill form with user data
            document.getElementById('userId').value = userId;
            document.getElementById('username').value = userRow.cells[1].textContent;
            document.getElementById('full_name').value = userRow.cells[2].textContent;
            document.getElementById('email').value = userRow.cells[3].textContent;
            document.getElementById('role').value = userRow.cells[4].textContent.toLowerCase();
            
            // Update form action
            document.getElementById('formAction').name = 'update_user';
            modalTitle.textContent = 'Edit User';
            passwordLabel.textContent = 'New Password';
            passwordField.required = false;
            passwordHelp.style.display = 'block';
            
            modal.style.display = 'block';
        });
    });

    // Delete user functionality with confirmation
    document.querySelectorAll('.delete-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const userName = this.closest('tr').cells[2].textContent;
            
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete user "${userName}". This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'users.php';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = userId;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_user';
                    actionInput.value = '1';
                    
                    form.appendChild(idInput);
                    form.appendChild(actionInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        const isAdd = document.getElementById('formAction').name === 'add_user';
        const password = document.getElementById('password').value;
        
        if (isAdd && password === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Password Required',
                text: 'Password is required when adding a new user',
                confirmButtonColor: '#4e73df'
            });
        }
    });
});