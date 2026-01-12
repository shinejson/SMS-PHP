document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const modal = document.getElementById('userModal');
    const form = document.getElementById('userForm');
    const teacherForm = document.getElementById('teacherUserForm');
    const addBtn = document.getElementById('addUserBtn');
    const addTeacherBtn = document.getElementById('addTeacherUserBtn');
    const closeBtn = document.querySelector('.close');
    const cancelBtns = document.querySelectorAll('.btn-cancel');
    const modalTitle = document.getElementById('modalTitle');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const profileImageInput = document.getElementById('profile_image');
    const passwordField = document.getElementById('password');
    const passwordLabel = document.getElementById('passwordLabel');
    const passwordHelp = document.getElementById('passwordHelp');
    const fullNameInput = document.getElementById('full_name');
    const emailInput = document.getElementById('email');
    const signatureInput = document.getElementById('signature');

    if (!modal || !form || !addBtn) {
        console.error('Critical elements missing');
        return;
    }

    // Image preview
    if (profileImageInput && imagePreview && previewImg) {
        profileImageInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagePreview.style.display = 'none';
            }
        });
    }

    // Close modal function
    function closeModal() {
        modal.style.display = 'none';
        imagePreview.style.display = 'none';
        form.reset();
        teacherForm.reset();
        const errorAlert = document.querySelector('.alert-danger');
        if (errorAlert) errorAlert.style.display = 'none';
        // Clear readonly states and warnings
        if (fullNameInput) fullNameInput.removeAttribute('readonly');
        if (emailInput) emailInput.removeAttribute('readonly');
        const teacherWarning = document.getElementById('teacherWarning');
        if (teacherWarning) teacherWarning.style.display = 'none';
        // Switch to default tab
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.querySelector('[data-tab="regular-user"]').classList.add('active');
        document.getElementById('regular-user-tab').classList.add('active');
    }

    // Event listeners for close
    closeBtn.addEventListener('click', closeModal);
    cancelBtns.forEach(btn => btn.addEventListener('click', closeModal));
    window.addEventListener('click', function(event) {
        if (event.target === modal) closeModal();
    });

    // Add regular user
    if (addBtn) {
        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modalTitle.textContent = 'Add New User';
            form.reset();
            imagePreview.style.display = 'none';
            document.getElementById('formAction').name = 'add_user';
            document.getElementById('userId').value = '';
            passwordLabel.textContent = 'Password*';
            passwordField.required = true;
            passwordField.value = '';
            passwordHelp.style.display = 'none';
            // Open regular tab
            document.querySelector('[data-tab="regular-user"]').click();
            modal.style.display = 'block';
            modal.classList.add('modal-open');
        });
    }

    // Add teacher user
    if (addTeacherBtn) {
        addTeacherBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modalTitle.textContent = 'Create Teacher User Account';
            teacherForm.reset();
            // Open teacher tab
            document.querySelector('[data-tab="teacher-user"]').click();
            modal.style.display = 'block';
            modal.classList.add('modal-open');
        });
    }

    // Auto-fill teacher form
    const teacherSelect = document.getElementById('teacher_id');
    if (teacherSelect) {
        teacherSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const firstName = selectedOption.getAttribute('data-firstname');
            const lastName = selectedOption.getAttribute('data-lastname');
            const email = selectedOption.getAttribute('data-email');
            document.getElementById('teacher_full_name').value = firstName + ' ' + lastName;
            document.getElementById('teacher_email').value = email;
            if (firstName) {
                document.getElementById('teacher_username').value = firstName.toLowerCase();
            }
        });
    }

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab') + '-tab';
            document.getElementById(tabId).classList.add('active');
        });
    });

    // Event delegation for edit/delete (single handler, no duplicates)
    document.addEventListener('click', function(e) {
        // Edit
        if (e.target.closest('.edit-user')) {
            const btn = e.target.closest('.edit-user');
            const userId = btn.getAttribute('data-id');
            const userRow = btn.closest('tr');
            const isTeacher = btn.getAttribute('data-is-teacher') === '1';
            const teacherId = btn.getAttribute('data-teacher-id') || '';
            const signature = btn.getAttribute('data-signature') || '';
            const profileImage = btn.getAttribute('data-image') || '';

            // Populate form
            document.getElementById('userId').value = userId;
            document.getElementById('username').value = userRow.cells[1].textContent.trim(); // Username
            document.getElementById('full_name').value = userRow.cells[2].textContent.trim(); // Full Name
            document.getElementById('email').value = userRow.cells[3].textContent.trim(); // Email
            document.getElementById('role').value = userRow.cells[4].textContent.toLowerCase().trim(); // Role
            signatureInput.value = signature;

            // Profile image
            if (profileImage) {
                previewImg.src = 'uploads/users/' + profileImage;
                imagePreview.style.display = 'block';
            } else {
                imagePreview.style.display = 'none';
            }

            // Set to edit mode
            document.getElementById('formAction').name = 'update_user';
            modalTitle.textContent = 'Edit User';
            passwordLabel.textContent = 'New Password (optional)';
            passwordField.required = false;
            passwordHelp.style.display = 'block';

            // FIXED: Handle teacher-linked users
            if (isTeacher && teacherId) {
                fullNameInput.setAttribute('readonly', true);
                emailInput.setAttribute('readonly', true);
                // Show warning
                let warning = document.getElementById('teacherWarning');
                if (!warning) {
                    warning = document.createElement('div');
                    warning.id = 'teacherWarning';
                    warning.className = 'alert alert-warning';
                    warning.innerHTML = '<i class="fas fa-info-circle"></i> This user is linked to a teacher profile (ID: ' + teacherId + '). Full Name and Email are read-only – edit the teacher record separately.';
                    fullNameInput.parentNode.insertBefore(warning, fullNameInput);
                }
                warning.style.display = 'block';
            }

            // Open regular tab for edits
            document.querySelector('[data-tab="regular-user"]').click();
            modal.style.display = 'block';
            modal.classList.add('modal-open');
        }

        // Delete
        if (e.target.closest('.delete-user')) {
            const btn = e.target.closest('.delete-user');
            const userId = btn.getAttribute('data-id');
            const userName = btn.closest('tr').cells[2].textContent.trim();
            const isTeacher = btn.getAttribute('data-is-teacher') === '1';

            let confirmText = `You are about to delete user "${userName}". This action cannot be undone!`;
            if (isTeacher) {
                confirmText += '\n\nNote: This will not delete the linked teacher profile.';
            }

            Swal.fire({
                title: 'Are you sure?',
                text: confirmText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
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
        }
    });

    // Form submission validation
    [form, teacherForm].forEach(f => {
        if (f) {
            f.addEventListener('submit', function(e) {
                const isAddUser = document.getElementById('formAction')?.name === 'add_user';
                const password = passwordField?.value || '';

                // File size (2MB)
                const fileInput = document.getElementById('profile_image');
                if (fileInput?.files[0] && fileInput.files[0].size > 2 * 1024 * 1024) {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'File Too Large', text: 'Profile image must be < 2MB' });
                    return;
                }

                if (isAddUser && password === '') {
                    e.preventDefault();
                    Swal.fire({ icon: 'error', title: 'Password Required', text: 'Required for new users' });
                }
            });
        }
    });
});