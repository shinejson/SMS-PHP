
document.addEventListener("DOMContentLoaded", function () {
    const alerts = document.querySelectorAll(".alert");
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.classList.add("hide");
            });
        }, 5000);
    }
    
    // Get all dropdown toggle buttons
    document.querySelectorAll(".sidebar-nav .dropdown-toggle").forEach(toggle => {
        toggle.addEventListener("click", function (e) {
            e.preventDefault();
            const parentLi = this.parentElement;

            // Close all other dropdowns
            document.querySelectorAll(".sidebar-nav .dropdown").forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove("open");
                }
            });

            // Toggle the 'open' class on the clicked dropdown's parent list item
            parentLi.classList.toggle("open");
        });
    });

    // Add logic for the dark mode toggle
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            document.body.classList.toggle('dark-mode', this.checked);
        });
    }
});

// Academic Year Modal Functions
function openAcademicYearModal() {
    document.getElementById('academicYearModal').style.display = 'block';
    document.getElementById('year_name_input').value = '';
    document.getElementById('is_current').checked = false;
}

function closeAcademicYearModal() {
    document.getElementById('academicYearModal').style.display = 'none';
}

function updateYearName(value) {
    document.querySelector('input[name="year_name"]').value = value;
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('academicYearModal');
    if (event.target === modal) {
        closeAcademicYearModal();
    }
}

// Handle form submission
document.getElementById('academicYearForm').addEventListener('submit', function(e) {
    const yearName = document.querySelector('input[name="year_name"]').value;
    if (!yearName) {
        e.preventDefault();
        alert('Please enter a year name');
    }
});

// Remarks Modal Functions
function openRemarksModal() {
    document.getElementById('remarksModal').style.display = 'block';
    loadRemarks();
}

function closeRemarksModal() {
    document.getElementById('remarksModal').style.display = 'none';
}

function loadRemarks() {
    fetch('get_remarks.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                renderRemarksTable(data.remarks);
            } else {
                document.getElementById('remarksContainer').innerHTML = 
                    '<div class="alert alert-error">Error loading grading system: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            document.getElementById('remarksContainer').innerHTML = 
                '<div class="alert alert-error">Error loading grading system: ' + error.message + '</div>';
        });
}

function renderRemarksTable(remarks) {
    let html = `
        <table class="remarks-table">
            <thead>
                <tr>
                    <th>Min Mark</th>
                    <th>Max Mark</th>
                    <th>Grade</th>
                    <th>Remark</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    remarks.forEach((remark, index) => {
        html += `
            <tr data-id="${remark.id || 'new'}">
                <td>
                    <input type="number" name="min_mark[]" value="${remark.min_mark}" 
                           min="0" max="100" step="0.01" required 
                           oninput="validateMarks(${index})">
                </td>
                <td>
                    <input type="number" name="max_mark[]" value="${remark.max_mark}" 
                           min="0" max="100" step="0.01" required 
                           oninput="validateMarks(${index})">
                </td>
                <td>
                    <input type="text" name="grade[]" value="${remark.grade}" 
                           maxlength="2" required style="text-transform: uppercase;">
                </td>
                <td>
                    <input type="text" name="remark[]" value="${remark.remark}" required>
                </td>
                <td>
                    <button type="button" class="btn-icon btn-danger" onclick="deleteRemarkRow(this)" 
                            ${remarks.length <= 1 ? 'disabled' : ''}>
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += `
            </tbody>
        </table>
    `;
    
    document.getElementById('remarksContainer').innerHTML = html;
}

function addNewRemarkRow() {
    const tbody = document.querySelector('.remarks-table tbody');
    const newRow = document.createElement('tr');
    newRow.setAttribute('data-id', 'new');
    newRow.innerHTML = `
        <td>
            <input type="number" name="min_mark[]" value="0" 
                   min="0" max="100" step="0.01" required 
                   oninput="validateMarks(-1)">
        </td>
        <td>
            <input type="number" name="max_mark[]" value="0" 
                   min="0" max="100" step="0.01" required 
                   oninput="validateMarks(-1)">
        </td>
        <td>
            <input type="text" name="grade[]" value="" 
                   maxlength="2" required style="text-transform: uppercase;">
        </td>
        <td>
            <input type="text" name="remark[]" value="" required>
        </td>
        <td>
            <button type="button" class="btn-icon btn-danger" onclick="deleteRemarkRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
}

function deleteRemarkRow(button) {
    const row = button.closest('tr');
    const rows = document.querySelectorAll('.remarks-table tbody tr');
    
    if (rows.length > 1) {
        row.remove();
    } else {
        alert('Cannot delete the last grade. At least one grade is required.');
    }
}

function validateMarks(index) {
    const rows = document.querySelectorAll('.remarks-table tbody tr');
    const row = index === -1 ? rows[rows.length - 1] : rows[index];
    
    const minMarkInput = row.querySelector('input[name="min_mark[]"]');
    const maxMarkInput = row.querySelector('input[name="max_mark[]"]');
    
    const minMark = parseFloat(minMarkInput.value) || 0;
    const maxMark = parseFloat(maxMarkInput.value) || 0;
    
    if (minMark > maxMark) {
        maxMarkInput.setCustomValidity('Max mark must be greater than or equal to min mark');
    } else {
        maxMarkInput.setCustomValidity('');
    }
}

function saveRemarks() {
    const formData = new FormData();
    formData.append('action', 'save_remarks');
    
    // Get CSRF token from the hidden input field
    const csrfToken = document.getElementById('remarks_csrf_token').value;
    if (csrfToken) {
        formData.append('csrf_token', csrfToken);
    } else {
        showMessage('Security token missing. Please refresh the page.', 'alert-error');
        return;
    }
    
    // Validate all inputs first
    const rows = document.querySelectorAll('.remarks-table tbody tr');
    let isValid = true;
    let remarksData = [];
    
    rows.forEach((row, index) => {
        const minMark = parseFloat(row.querySelector('input[name="min_mark[]"]').value) || 0;
        const maxMark = parseFloat(row.querySelector('input[name="max_mark[]"]').value) || 0;
        const grade = row.querySelector('input[name="grade[]"]').value.trim();
        const remark = row.querySelector('input[name="remark[]"]').value.trim();
        
        if (minMark < 0 || maxMark > 100 || minMark > maxMark || !grade || !remark) {
            isValid = false;
            showMessage('Please check all fields. Min mark ≤ Max mark, and all fields are required.', 'alert-error');
            return;
        }
        
        remarksData.push({
            min_mark: minMark,
            max_mark: maxMark,
            grade: grade,
            remark: remark
        });
    });
    
    if (!isValid) return;
    
    // Add remarks data as JSON for easier debugging
    formData.append('remarks_json', JSON.stringify(remarksData));
    remarksData.forEach((remark, index) => {
        formData.append(`remarks[${index}][min_mark]`, remark.min_mark);
        formData.append(`remarks[${index}][max_mark]`, remark.max_mark);
        formData.append(`remarks[${index}][grade]`, remark.grade);
        formData.append(`remarks[${index}][remark]`, remark.remark);
    });
    
    // Show loading state
    const saveBtn = document.querySelector('button[onclick="saveRemarks()"]');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;
    
    fetch('save_remarks.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        console.log('Save response:', data); // Debugging
        if (data.status === 'success') {
            showMessage(data.message, 'alert-success');
            // Reload the remarks to verify they were saved
            setTimeout(() => {
                loadRemarks();
            }, 1000);
        } else {
            showMessage('Error: ' + data.message, 'alert-error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error saving grading system: ' + error.message, 'alert-error');
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function showMessage(message, type) {
    // Remove any existing messages first
    const existingMessages = document.querySelectorAll('.alert-message');
    existingMessages.forEach(msg => msg.remove());
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-message ${type}`;
    messageDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        padding: 15px;
        border-radius: 4px;
        color: white;
        font-weight: 500;
        max-width: 400px;
        animation: slideIn 0.3s ease-out;
    `;
    
    if (type === 'alert-success') {
        messageDiv.style.backgroundColor = '#28a745';
    } else if (type === 'alert-error') {
        messageDiv.style.backgroundColor = '#dc3545';
    }
    
    messageDiv.innerHTML = `
        <span>${message}</span>
        <button style="background: none; border: none; color: white; margin-left: 10px; cursor: pointer;" 
                onclick="this.parentElement.remove()">×</button>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 300);
        }
    }, 5000);
}
// Add this to your existing window.onclick function
window.onclick = function(event) {
    const academicModal = document.getElementById('academicYearModal');
    const remarksModal = document.getElementById('remarksModal');
    
    if (event.target === academicModal) {
        closeAcademicYearModal();
    }
    if (event.target === remarksModal) {
        closeRemarksModal();
    }
}

// Add this CSS for the remarks table
const style = document.createElement('style');
style.textContent = `
    .remarks-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    
    .remarks-table th,
    .remarks-table td {
        padding: 10px;
        border: 1px solid #e3e6f0;
        text-align: left;
    }
    
    .remarks-table th {
        background-color: #f8f9fc;
        font-weight: 600;
    }
    
    .remarks-table input {
        width: 100%;
        padding: 8px;
        border: 1px solid #d1d3e2;
        border-radius: 4px;
        box-sizing: border-box;
    }
    
    .remarks-table input:focus {
        border-color: #4e73df;
        outline: none;
    }
    
    .btn-icon {
        background: none;
        border: none;
        cursor: pointer;
        padding: 5px;
        font-size: 14px;
    }
    
    .btn-danger {
        color: #e74a3b;
    }
    
    .btn-danger:hover {
        color: #c53030;
    }
    
    .btn-danger:disabled {
        color: #6c757d;
        cursor: not-allowed;
    }
    
    body.dark-mode .remarks-table th,
    body.dark-mode .remarks-table td {
        border-color: #4a5063;
    }
    
    body.dark-mode .remarks-table th {
        background-color: #3a4256;
    }
    
    body.dark-mode .remarks-table input {
        background-color: #3a4256;
        border-color: #4a5063;
        color: #f8f9fa;
    }
`;
document.head.appendChild(style);
