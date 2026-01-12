// assignments.js - Fixed with proper form handling
console.log('Assignments JS loading...');

document.addEventListener('DOMContentLoaded', function() {
    console.log('Assignments JS loaded successfully');
    
    // Initialize create button event listener
    const createBtn = document.getElementById('createAssignmentBtn');
    if (createBtn) {
        createBtn.addEventListener('click', showCreateModal);
    }
    
    // Initialize filters
    const filters = document.querySelectorAll('#class_filter, #subject_filter, #status_filter, #type_filter, #teacher_filter');
    
    filters.forEach(filter => {
        if (filter) {
            filter.addEventListener('change', function() {
                // Optional: auto-submit form when filters change
                // this.form.submit();
            });
        }
    });
    
    // Initialize date inputs
    const today = new Date().toISOString().split('T')[0];
    const dateTo = document.getElementById('date_to');
    if (dateTo) {
        dateTo.max = today;
    }
    
    // Form validation
    const form = document.getElementById('assignmentForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const dueDate = document.getElementById('due_date').value;
            const assignmentDate = document.getElementById('assignment_date').value;
            
            if (dueDate && assignmentDate && new Date(dueDate) < new Date(assignmentDate)) {
                e.preventDefault();
                alert('Due date cannot be before assignment date.');
                return false;
            }
            
            // Show loading state on submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
            
            return true;
        });
    }
    
    // Academic year and term filtering
    const academicYearSelect = document.getElementById('academic_year');
    const termSelect = document.getElementById('term_id');
    
    if (academicYearSelect && termSelect) {
        academicYearSelect.addEventListener('change', function() {
            // Show all terms - they are independent
            for (let option of termSelect.options) {
                option.style.display = '';
            }
        });
    }
    
    // Filter version for main page
    const academicYearFilter = document.getElementById('academic_year_filter');
    const termFilter = document.getElementById('term_filter');
    
    if (academicYearFilter && termFilter) {
        academicYearFilter.addEventListener('change', function() {
            // Show all terms - they are independent
            for (let option of termFilter.options) {
                option.style.display = '';
            }
        });
    }
});

// Modal functions
// In showCreateModal function - FIXED
function showCreateModal() {
    console.log('showCreateModal called');
    const modal = document.getElementById('assignmentModal');
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('form_action');
    const modalSubmit = document.getElementById('modalSubmit');
    const statusField = document.getElementById('statusField');
    const assignmentForm = document.getElementById('assignmentForm');
    const teacherIdField = document.getElementById('teacher_id');
    
    if (!modal) {
        console.error('Modal element not found');
        return;
    }
    
    modalTitle.textContent = 'Create New Assignment';
    formAction.value = 'create_assignment';
    formAction.name = 'create_assignment'; // This is correct
    modalSubmit.textContent = 'Create Assignment';
    
    if (statusField) {
        statusField.style.display = 'none';
    }
    
    if (assignmentForm) {
        assignmentForm.reset();
        // Reset the form action name
        assignmentForm.querySelector('input[name="update_assignment"]')?.remove();
    }
    
    document.getElementById('assignment_id').value = '';
    document.getElementById('assignment_date').value = new Date().toISOString().split('T')[0];
    
    modal.style.display = 'block';
}

function editAssignment(id) {
    console.log('Editing assignment:', id);
    
    fetch(`get_assignment.php?id=${id}`)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            
            return response.text(); // Get raw text first
        })
        .then(text => {
            console.log('Raw response:', text.substring(0, 500)); // Log first 500 chars
            
            try {
                const data = JSON.parse(text);
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                console.log('Parsed data:', data);
                
                const modal = document.getElementById('assignmentModal');
                const modalTitle = document.getElementById('modalTitle');
                const formAction = document.getElementById('form_action');
                const modalSubmit = document.getElementById('modalSubmit');
                const statusField = document.getElementById('statusField');
                
                modalTitle.textContent = 'Edit Assignment';
                formAction.value = 'update_assignment';
                formAction.name = 'update_assignment';
                modalSubmit.textContent = 'Update Assignment';
                
                if (statusField) {
                    statusField.style.display = 'block';
                }
                
                // Populate form fields
                document.getElementById('assignment_id').value = data.id || '';
                document.getElementById('title').value = data.title || '';
                document.getElementById('class_id').value = data.class_id || '';
                
                // Set subject by ID
                const subjectSelect = document.getElementById('subject_id');
                if (data.subject_id && data.subject_id > 0) {
                    subjectSelect.value = data.subject_id;
                } else if (data.subject) {
                    // Fallback: find by name
                    for (let option of subjectSelect.options) {
                        const optionText = option.textContent.toLowerCase().trim();
                        const subjectName = data.subject.toLowerCase().trim();
                        if (optionText.includes(subjectName) || subjectName.includes(optionText)) {
                            subjectSelect.value = option.value;
                            break;
                        }
                    }
                }
                
                document.getElementById('academic_year').value = data.academic_year || '';
                document.getElementById('term_id').value = data.term_id || '';
                document.getElementById('assignment_type').value = data.assignment_type || 'homework';
                document.getElementById('assignment_date').value = data.assignment_date || '';
                document.getElementById('due_date').value = data.due_date || '';
                document.getElementById('max_marks').value = data.max_marks || 100;
                document.getElementById('description').value = data.description || '';
                document.getElementById('instructions').value = data.instructions || '';
                document.getElementById('status').value = data.status || 'active';
                
                // If admin, set teacher_id
                const teacherIdField = document.getElementById('teacher_id');
                if (teacherIdField && data.teacher_id) {
                    teacherIdField.value = data.teacher_id;
                }
                
                modal.style.display = 'block';
                
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', text);
                throw new Error('Invalid response from server. Check console for details.');
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            showToast(error.message || 'Failed to load assignment', 'error');
        });
}

function closeModal() {
    const modal = document.getElementById('assignmentModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function deleteAssignment(id) {
    if (confirm('Are you sure you want to delete this assignment? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'assignments.php';
        form.innerHTML = `
            <input type="hidden" name="assignment_id" value="${id}">
            <input type="hidden" name="delete_assignment" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewAssignment(id) {
    window.location.href = `assignment_details.php?id=${id}`;
}

function viewSubmissions(id) {
    window.location.href = `assignment_submissions.php?id=${id}`;
}

function exportAssignments() {
    // Add export functionality
    alert('Export functionality would be implemented here');
}

// Filter functionality
function toggleAdvancedFilters() {
    const advancedFilters = document.getElementById('advancedFilters');
    const toggle = document.querySelector('.filter-toggle');
    
    if (advancedFilters && toggle) {
        advancedFilters.classList.toggle('active');
        toggle.classList.toggle('active');
    }
}

function applyQuickFilter(type) {
    // Remove active class from all chips
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.classList.remove('active');
    });
    
    // Add active class to clicked chip
    event.target.classList.add('active');
    
    // Apply filter based on type
    switch(type) {
        case 'pending':
            window.location.href = 'assignments.php?status=active&has_submissions=1';
            break;
        case 'overdue':
            window.location.href = 'assignments.php?status=active&overdue=1';
            break;
        case 'today':
            window.location.href = 'assignments.php?due_today=1';
            break;
        default:
            window.location.href = 'assignments.php';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('assignmentModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Attach functions to window for global access
window.showCreateModal = showCreateModal;
window.editAssignment = editAssignment;
window.closeModal = closeModal;
window.deleteAssignment = deleteAssignment;
window.viewAssignment = viewAssignment;
window.viewSubmissions = viewSubmissions;
window.exportAssignments = exportAssignments;
window.toggleAdvancedFilters = toggleAdvancedFilters;
window.applyQuickFilter = applyQuickFilter;

console.log('All functions defined and attached to window');

// Toast notification system
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Add CSS for toast notifications
const toastCSS = `
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    max-width: 500px;
    z-index: 10000;
    animation: slideInRight 0.3s ease-out;
    border-left: 4px solid;
}

.toast-success {
    border-left-color: #28a745;
    background: #d4edda;
    color: #155724;
}

.toast-error {
    border-left-color: #dc3545;
    background: #f8d7da;
    color: #721c24;
}

.toast-warning {
    border-left-color: #ffc107;
    background: #fff3cd;
    color: #856404;
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.toast-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: inherit;
    opacity: 0.7;
}

.toast-close:hover {
    opacity: 1;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
`;

// Inject CSS
const style = document.createElement('style');
style.textContent = toastCSS;
document.head.appendChild(style);

// Auto-dismiss flash messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const flashMessages = document.querySelectorAll('.alert');
    flashMessages.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });
});