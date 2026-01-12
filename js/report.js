// Report Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeReportSidebar();
    initializeReportFilters();
    loadFilterOptions();
});

// ============================================================
// REPORT SIDEBAR MANAGEMENT
// ============================================================

function initializeReportSidebar() {
    const reportToggles = document.querySelectorAll('.report-toggle');
    
    reportToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const category = this.closest('.report-category');
            const submenu = category.querySelector('.report-submenu');
            const isOpen = category.classList.contains('active');
            
            // Close all other categories (accordion effect)
            document.querySelectorAll('.report-category').forEach(cat => {
                if (cat !== category) {
                    cat.classList.remove('active');
                    const otherSubmenu = cat.querySelector('.report-submenu');
                    if (otherSubmenu) otherSubmenu.classList.remove('active');
                }
            });
            
            // Toggle current category
            if (isOpen) {
                category.classList.remove('active');
                submenu.classList.remove('active');
            } else {
                category.classList.add('active');
                submenu.classList.add('active');
            }
        });
    });
    
    // Handle report link clicks
    const reportLinks = document.querySelectorAll('.report-submenu a');
    reportLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            reportLinks.forEach(l => l.classList.remove('active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Get report type
            const reportType = this.getAttribute('data-report');
            const reportTitle = this.textContent.trim();
            
            // Update report title
            document.getElementById('reportTitle').textContent = reportTitle;
            
            // Show appropriate filters for report type
            updateFiltersForReportType(reportType);
            
            // Clear previous report content
            document.getElementById('reportContent').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-file-alt fa-5x"></i>
                    <h3>Configure Report Parameters</h3>
                    <p>Select your filters and click "Generate Report" to view ${reportTitle}</p>
                </div>
            `;
        });
    });
}

// ============================================================
// FILTER MANAGEMENT
// ============================================================

function initializeReportFilters() {
    const generateBtn = document.getElementById('generateReport');
    const resetBtn = document.getElementById('resetFilters');
    const printBtn = document.getElementById('printReport');
    const excelBtn = document.getElementById('exportExcel');
    const pdfBtn = document.getElementById('exportPDF');
    
    if (generateBtn) {
        generateBtn.addEventListener('click', generateReport);
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', resetFilters);
    }
    
    if (printBtn) {
        printBtn.addEventListener('click', printReport);
    }
    
    if (excelBtn) {
        excelBtn.addEventListener('click', exportToExcel);
    }
    
    if (pdfBtn) {
        pdfBtn.addEventListener('click', exportToPDF);
    }
}

async function loadFilterOptions() {
    try {
        // Load academic years
        const academicYears = await fetch('api/get_academic_years.php').then(r => r.json());
        const academicYearSelect = document.getElementById('academicYear');
        
        if (academicYearSelect && academicYears) {
            academicYearSelect.innerHTML = '<option value="">All Years</option>';
            academicYears.forEach(year => {
                const option = document.createElement('option');
                option.value = year.id;
                option.textContent = year.year_name;
                if (year.is_current == 1) option.selected = true;
                academicYearSelect.appendChild(option);
            });
        }
        
        // Load terms
        const terms = await fetch('api/get_terms.php').then(r => r.json());
        const termSelect = document.getElementById('term');
        
        if (termSelect && terms) {
            termSelect.innerHTML = '<option value="">All Terms</option>';
            terms.forEach(term => {
                const option = document.createElement('option');
                option.value = term.id;
                option.textContent = term.term_name || `Term ${term.term_number}`;
                termSelect.appendChild(option);
            });
        }
        
        // Load classes
        const classes = await fetch('api/get_classes.php').then(r => r.json());
        const classSelect = document.getElementById('class');
        
        if (classSelect && classes) {
            classSelect.innerHTML = '<option value="">All Classes</option>';
            classes.forEach(cls => {
                const option = document.createElement('option');
                option.value = cls.id;
                option.textContent = cls.class_name;
                classSelect.appendChild(option);
            });
        }
        
    } catch (error) {
        console.error('Error loading filter options:', error);
    }
}

function updateFiltersForReportType(reportType) {
    // Show/hide relevant filters based on report type
    const filterGroups = document.querySelectorAll('.filter-group');
    
    // Default: show all filters
    filterGroups.forEach(group => {
        group.style.display = 'flex';
    });
    
    // Customize based on report type
    // You can add specific logic here for different reports
}

// ============================================================
// REPORT GENERATION
// ============================================================

async function generateReport() {
    const activeLink = document.querySelector('.report-submenu a.active');
    if (!activeLink) {
        alert('Please select a report type from the sidebar');
        return;
    }
    
    const reportType = activeLink.getAttribute('data-report');
    const filters = getFilterValues();
    
    // Show loading state
    const reportContent = document.getElementById('reportContent');
    reportContent.innerHTML = `
        <div class="empty-state">
            <i class="fas fa-spinner fa-spin fa-5x"></i>
            <h3>Generating Report...</h3>
            <p>Please wait while we fetch your data</p>
        </div>
    `;
    
    try {
        // Make API call based on report type
        const data = await fetchReportData(reportType, filters);
        
        // Display report
        displayReport(reportType, data);
        
    } catch (error) {
        console.error('Error generating report:', error);
        reportContent.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle fa-5x" style="color: #ef4444;"></i>
                <h3>Error Generating Report</h3>
                <p>${error.message || 'There was an error fetching your report data. Please try again.'}</p>
            </div>
        `;
    }
}

function getFilterValues() {
    return {
        academicYear: document.getElementById('academicYear')?.value || '',
        term: document.getElementById('term')?.value || '',
        class: document.getElementById('class')?.value || '',
        dateFrom: document.getElementById('dateFrom')?.value || '',
        dateTo: document.getElementById('dateTo')?.value || ''
    };
}

async function fetchReportData(reportType, filters) {
    // Build query string
    const params = new URLSearchParams(filters);
    
    try {
        const response = await fetch(`api/reports/${reportType}.php?${params}`);
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to check if it's valid JSON
        const text = await response.text();
        
        // Try to parse as JSON
        try {
            const data = JSON.parse(text);
            
            // Check if there's an error in the response
            if (data.error) {
                throw new Error(data.message || 'Failed to fetch report data');
            }
            
            return data;
        } catch (e) {
            console.error('Invalid JSON response:', text);
            throw new Error('Server returned invalid data. Please check if the API endpoint exists.');
        }
        
    } catch (error) {
        console.error('Fetch error:', error);
        throw error;
    }
}

function displayReport(reportType, response) {
    const reportContent = document.getElementById('reportContent');
    
    // Check if response has data
    if (!response.data || response.data.length === 0) {
        reportContent.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-info-circle fa-5x" style="color: #3b82f6;"></i>
                <h3>No Data Found</h3>
                <p>No records match your filter criteria. Try adjusting your filters.</p>
            </div>
        `;
        return;
    }
    
    const data = response.data;
    let tableHTML = '';
    
    // Generate table based on report type
    switch(reportType) {
        case 'student-list':
            tableHTML = generateStudentListTable(data, response);
            break;
        case 'attendance-summary':
            tableHTML = generateAttendanceSummaryTable(data, response);
            break;
        case 'student-performance':
            tableHTML = generateStudentPerformanceTable(data, response);
            break;
        case 'enrollment-report':
            tableHTML = generateEnrollmentReportTable(data, response);
            break;
        case 'payment-summary':
            tableHTML = generatePaymentSummaryTable(data, response);
            break;
        case 'outstanding-fees':
            tableHTML = generateOutstandingFeesTable(data, response);
            break;
        case 'revenue-analysis':
            tableHTML = generateRevenueAnalysisTable(data, response);
            break;
        case 'grade-report':
            tableHTML = generateGradeReportTable(data, response);
            break;
        case 'teacher-list':
            tableHTML = generateTeacherListTable(data, response);
            break;
        case 'class-list':
            tableHTML = generateClassListTable(data, response);
            break;
        case 'expense-report':
            tableHTML = generateExpenseReportTable(data, response);
            break;
        case 'subject-performance':
            tableHTML = generateSubjectPerformanceTable(data, response);
            break;
        case 'class-average':
            tableHTML = generateClassAverageTable(data, response);
            break;
        case 'exam-analysis':
            tableHTML = generateExamAnalysisTable(data, response);
            break;
        case 'teacher-workload':
            tableHTML = generateTeacherWorkloadTable(data, response);
            break;
        case 'teacher-attendance':
            tableHTML = generateTeacherAttendanceTable(data, response);
            break;
        case 'class-strength':
            tableHTML = generateClassStrengthTable(data, response);
            break;
        case 'subject-allocation':
            tableHTML = generateSubjectAllocationTable(data, response);
            break;
        case 'custom-builder':
        case 'saved-reports':
            tableHTML = generateGenericTable(data, response);
            break;
        default:
            tableHTML = generateGenericTable(data, response);
    }
    
    reportContent.innerHTML = tableHTML;
}

// ============================================================
// TABLE GENERATORS
// ============================================================

function generateStudentListTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Full Name</th>
                        <th>Class</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Parent Name</th>
                        <th>Parent Contact</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(student => {
        const statusClass = student.status === 'Active' ? 'badge-success' : 'badge-secondary';
        html += `
            <tr>
                <td>${student.student_id || 'N/A'}</td>
                <td>${student.full_name || student.first_name + ' ' + student.last_name}</td>
                <td>${student.class_name || 'N/A'}</td>
                <td>${student.gender || 'N/A'}</td>
                <td>${student.dob || 'N/A'}</td>
                <td>${student.parent_name || 'N/A'}</td>
                <td>${student.parent_contact || 'N/A'}</td>
                <td><span class="badge ${statusClass}">${student.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Students:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateAttendanceSummaryTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Total Days</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Excused</th>
                        <th>Attendance %</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const percentage = parseFloat(record.attendance_percentage || 0);
        const percentageClass = percentage >= 80 ? 'text-success' : 
                               percentage >= 60 ? 'text-warning' : 'text-danger';
        html += `
            <tr>
                <td>${record.student_id || 'N/A'}</td>
                <td>${record.student_name}</td>
                <td>${record.class_name || 'N/A'}</td>
                <td>${record.total_days || 0}</td>
                <td>${record.present_days || 0}</td>
                <td>${record.absent_days || 0}</td>
                <td>${record.late_days || 0}</td>
                <td>${record.excused_days || 0}</td>
                <td class="${percentageClass}"><strong>${percentage.toFixed(2)}%</strong></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Records:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateStudentPerformanceTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Subjects</th>
                        <th>Average Score</th>
                        <th>Highest</th>
                        <th>Lowest</th>
                        <th>Grade A</th>
                        <th>Grade B</th>
                        <th>Failed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const avgScore = parseFloat(record.average_score || 0).toFixed(2);
        const statusClass = record.performance_status === 'Excellent' ? 'badge-success' : 
                          record.performance_status === 'Good' ? 'badge-primary' :
                          record.performance_status === 'Average' ? 'badge-warning' : 'badge-danger';
        
        html += `
            <tr>
                <td><strong>${record.rank}</strong></td>
                <td>${record.student_id}</td>
                <td>${record.student_name}</td>
                <td>${record.class_name || 'N/A'}</td>
                <td>${record.subjects_taken || 0}</td>
                <td><strong>${avgScore}</strong></td>
                <td>${parseFloat(record.highest_score || 0).toFixed(2)}</td>
                <td>${parseFloat(record.lowest_score || 0).toFixed(2)}</td>
                <td>${record.grade_a_count || 0}</td>
                <td>${record.grade_b_count || 0}</td>
                <td>${record.failed_subjects || 0}</td>
                <td><span class="badge ${statusClass}">${record.performance_status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Students:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateEnrollmentReportTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>Class</th>
                        <th>Parent Name</th>
                        <th>Parent Contact</th>
                        <th>Enrollment Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const statusClass = record.status === 'Active' ? 'badge-success' : 'badge-secondary';
        html += `
            <tr>
                <td>${record.student_id}</td>
                <td>${record.student_name}</td>
                <td>${record.gender}</td>
                <td>${record.age || 'N/A'}</td>
                <td>${record.class_name || 'N/A'}</td>
                <td>${record.parent_name || 'N/A'}</td>
                <td>${record.parent_contact || 'N/A'}</td>
                <td>${record.enrollment_date}</td>
                <td><span class="badge ${statusClass}">${record.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    if (response.statistics) {
        const stats = response.statistics;
        html += `
            <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                    <div><strong>Total:</strong> ${stats.total}</div>
                    <div><strong>Male:</strong> ${stats.male}</div>
                    <div><strong>Female:</strong> ${stats.female}</div>
                    <div><strong>Active:</strong> ${stats.active}</div>
                    <div><strong>Inactive:</strong> ${stats.inactive}</div>
                </div>
            </div>
        `;
    }
    
    return html;
}

function generatePaymentSummaryTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Payment Type</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Received By</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(payment => {
        const statusClass = payment.status === 'Paid' ? 'badge-success' : 'badge-warning';
        html += `
            <tr>
                <td>${payment.receipt_no}</td>
                <td>${payment.payment_date}</td>
                <td>${payment.student_id}</td>
                <td>${payment.student_name}</td>
                <td>${payment.class_name || 'N/A'}</td>
                <td>${payment.payment_type}</td>
                <td>GH₵ ${parseFloat(payment.amount).toFixed(2)}</td>
                <td>${payment.payment_method}</td>
                <td><span class="badge ${statusClass}">${payment.status}</span></td>
                <td>${payment.received_by || 'N/A'}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Payments:</strong> ${response.count || data.length} | 
            <strong>Total Amount:</strong> GH₵ ${parseFloat(response.total_amount || 0).toFixed(2)}
        </div>
    `;
    
    return html;
}

function generateOutstandingFeesTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Parent Contact</th>
                        <th>Fee Type</th>
                        <th>Total Fee</th>
                        <th>Paid</th>
                        <th>Outstanding</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const statusClass = record.payment_status === 'Overdue' ? 'badge-danger' : 
                          record.payment_status === 'Due Today' ? 'badge-warning' : 'badge-info';
        html += `
            <tr>
                <td>${record.student_id}</td>
                <td>${record.student_name}</td>
                <td>${record.class_name}</td>
                <td>${record.parent_contact || 'N/A'}</td>
                <td>${record.payment_type}</td>
                <td>GH₵ ${parseFloat(record.total_fee).toFixed(2)}</td>
                <td>GH₵ ${parseFloat(record.amount_paid).toFixed(2)}</td>
                <td><strong>GH₵ ${parseFloat(record.outstanding_amount).toFixed(2)}</strong></td>
                <td>${record.due_date}</td>
                <td><span class="badge ${statusClass}">${record.payment_status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Outstanding:</strong> GH₵ ${parseFloat(response.total_outstanding || 0).toFixed(2)} | 
            <strong>Records:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateRevenueAnalysisTable(data, response) {
    let html = `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    `;
    
    // By Type
    if (response.by_type && response.by_type.length > 0) {
        html += `
            <div class="card" style="padding: 1rem;">
                <h4 style="margin-bottom: 1rem;">Revenue by Payment Type</h4>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Payment Type</th>
                            <th>Transactions</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        response.by_type.forEach(item => {
            html += `
                <tr>
                    <td>${item.payment_type}</td>
                    <td>${item.transaction_count}</td>
                    <td>GH₵ ${parseFloat(item.total_amount).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    // By Method
    if (response.by_method && response.by_method.length > 0) {
        html += `
            <div class="card" style="padding: 1rem;">
                <h4 style="margin-bottom: 1rem;">Revenue by Payment Method</h4>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Transactions</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        response.by_method.forEach(item => {
            html += `
                <tr>
                    <td>${item.payment_method}</td>
                    <td>${item.transaction_count}</td>
                    <td>GH₵ ${parseFloat(item.total_amount).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    html += `</div>`;
    
    // Monthly Trend
    if (response.monthly_trend && response.monthly_trend.length > 0) {
        html += `
            <div class="card" style="padding: 1rem;">
                <h4 style="margin-bottom: 1rem;">Monthly Revenue Trend</h4>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        response.monthly_trend.forEach(item => {
            html += `
                <tr>
                    <td>${item.month}</td>
                    <td>${item.transaction_count}</td>
                    <td>GH₵ ${parseFloat(item.total_amount).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    html += `
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Grand Total Revenue:</strong> GH₵ ${parseFloat(response.grand_total || 0).toFixed(2)}
        </div>
    `;
    
    return html;
}

function generateGradeReportTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Midterm</th>
                        <th>Class Score</th>
                        <th>Exam</th>
                        <th>Total Score</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const gradeClass = record.grade === 'A' ? 'badge-success' : 
                         record.grade === 'B' ? 'badge-primary' :
                         record.grade === 'C' ? 'badge-info' :
                         record.grade === 'D' ? 'badge-warning' : 'badge-danger';
        
        html += `
            <tr>
                <td>${record.student_id}</td>
                <td>${record.student_name}</td>
                <td>${record.class_name || 'N/A'}</td>
                <td>${record.subject_name}</td>
                <td>${parseFloat(record.midterm_marks || 0).toFixed(2)}</td>
                <td>${parseFloat(record.class_marks || 0).toFixed(2)}</td>
                <td>${parseFloat(record.exam_marks || 0).toFixed(2)}</td>
                <td><strong>${parseFloat(record.total_score || 0).toFixed(2)}</strong></td>
                <td><span class="badge ${gradeClass}">${record.grade}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Records:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateTeacherListTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Teacher ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Specialization</th>
                        <th>Assigned Classes</th>
                        <th>Total Classes</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(teacher => {
        const statusClass = teacher.status === 'Active' ? 'badge-success' : 'badge-secondary';
        html += `
            <tr>
                <td>${teacher.teacher_id}</td>
                <td>${teacher.full_name}</td>
                <td>${teacher.email}</td>
                <td>${teacher.phone || 'N/A'}</td>
                <td>${teacher.specialization || 'N/A'}</td>
                <td>${teacher.assigned_classes || 'None'}</td>
                <td>${teacher.total_classes || 0}</td>
                <td><span class="badge ${statusClass}">${teacher.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Teachers:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateClassListTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Academic Year</th>
                        <th>Class Teacher</th>
                        <th>Total Students</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(cls => {
        html += `
            <tr>
                <td><strong>${cls.class_name}</strong></td>
                <td>${cls.academic_year}</td>
                <td>${cls.class_teacher || 'Not Assigned'}</td>
                <td>${cls.total_students || 0}</td>
                <td>${cls.male_students || 0}</td>
                <td>${cls.female_students || 0}</td>
                <td>${cls.description || 'N/A'}</td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Classes:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateExpenseReportTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Expense Type</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(expense => {
        const statusClass = expense.status === 'Paid' ? 'badge-success' : 
                          expense.status === 'Approved' ? 'badge-primary' : 'badge-warning';
        html += `
            <tr>
                <td>${expense.expense_type}</td>
                <td>${expense.description}</td>
                <td>GH₵ ${parseFloat(expense.amount).toFixed(2)}</td>
                <td>${expense.expense_date}</td>
                <td><span class="badge ${statusClass}">${expense.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Expenses:</strong> GH₵ ${parseFloat(response.total_amount || 0).toFixed(2)} | 
            <strong>Records:</strong> ${response.count || data.length}
        </div>
    `;
    
    if (response.message) {
        html += `
            <div style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                <p style="margin: 0; color: #92400e;"><i class="fas fa-info-circle"></i> ${response.message}</p>
            </div>
        `;
    }
    
    return html;
}

function generateSubjectPerformanceTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Subject Code</th>
                        <th>Class</th>
                        <th>Total Students</th>
                        <th>Average Score</th>
                        <th>Highest</th>
                        <th>Lowest</th>
                        <th>Pass Count</th>
                        <th>Fail Count</th>
                        <th>Pass Rate</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const passRate = parseFloat(record.pass_rate || 0);
        const rateClass = passRate >= 80 ? 'text-success' : passRate >= 60 ? 'text-warning' : 'text-danger';
        html += `
            <tr>
                <td><strong>${record.subject_name}</strong></td>
                <td>${record.subject_code || 'N/A'}</td>
                <td>${record.class_name || 'N/A'}</td>
                <td>${record.total_students || 0}</td>
                <td>${parseFloat(record.average_score || 0).toFixed(2)}</td>
                <td>${parseFloat(record.highest_score || 0).toFixed(2)}</td>
                <td>${parseFloat(record.lowest_score || 0).toFixed(2)}</td>
                <td>${record.pass_count || 0}</td>
                <td>${record.fail_count || 0}</td>
                <td class="${rateClass}"><strong>${passRate.toFixed(2)}%</strong></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Subjects:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateClassAverageTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Class</th>
                        <th>Class Teacher</th>
                        <th>Total Students</th>
                        <th>Subjects</th>
                        <th>Class Average</th>
                        <th>Highest</th>
                        <th>Lowest</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const gradeClass = record.performance_grade === 'Excellent' ? 'badge-success' : 
                         record.performance_grade === 'Very Good' ? 'badge-primary' :
                         record.performance_grade === 'Good' ? 'badge-info' :
                         record.performance_grade === 'Average' ? 'badge-warning' : 'badge-danger';
        html += `
            <tr>
                <td><strong>${record.rank}</strong></td>
                <td><strong>${record.class_name}</strong></td>
                <td>${record.class_teacher || 'Not Assigned'}</td>
                <td>${record.total_students || 0}</td>
                <td>${record.subjects_offered || 0}</td>
                <td><strong>${parseFloat(record.class_average || 0).toFixed(2)}</strong></td>
                <td>${parseFloat(record.highest_score || 0).toFixed(2)}</td>
                <td>${parseFloat(record.lowest_score || 0).toFixed(2)}</td>
                <td><span class="badge ${gradeClass}">${record.performance_grade}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Classes:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateExamAnalysisTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Class</th>
                        <th>Students</th>
                        <th>Average</th>
                        <th>Highest</th>
                        <th>Lowest</th>
                        <th>Grade A</th>
                        <th>Grade B</th>
                        <th>Grade C</th>
                        <th>Grade D</th>
                        <th>Grade F</th>
                        <th>Pass Rate</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const passRate = parseFloat(record.pass_rate || 0);
        const rateClass = passRate >= 80 ? 'text-success' : passRate >= 60 ? 'text-warning' : 'text-danger';
        html += `
            <tr>
                <td><strong>${record.subject_name}</strong></td>
                <td>${record.class_name || 'N/A'}</td>
                <td>${record.students_examined || 0}</td>
                <td>${parseFloat(record.average_exam_score || 0).toFixed(2)}</td>
                <td>${parseFloat(record.highest_score || 0).toFixed(2)}</td>
                <td>${parseFloat(record.lowest_score || 0).toFixed(2)}</td>
                <td>${record.grade_a || 0}</td>
                <td>${record.grade_b || 0}</td>
                <td>${record.grade_c || 0}</td>
                <td>${record.grade_d || 0}</td>
                <td>${record.grade_f || 0}</td>
                <td class="${rateClass}"><strong>${passRate.toFixed(2)}%</strong></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Subjects Analyzed:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateTeacherWorkloadTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Teacher ID</th>
                        <th>Teacher Name</th>
                        <th>Specialization</th>
                        <th>Classes Assigned</th>
                        <th>Total Students</th>
                        <th>Class List</th>
                        <th>Workload Status</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(teacher => {
        const workloadClass = teacher.workload_status === 'Heavy' ? 'badge-danger' : 
                            teacher.workload_status === 'Moderate' ? 'badge-warning' : 'badge-success';
        const statusClass = teacher.status === 'Active' ? 'badge-success' : 'badge-secondary';
        html += `
            <tr>
                <td>${teacher.teacher_id}</td>
                <td><strong>${teacher.teacher_name}</strong></td>
                <td>${teacher.specialization || 'N/A'}</td>
                <td>${teacher.classes_assigned || 0}</td>
                <td>${teacher.total_students || 0}</td>
                <td>${teacher.class_list || 'None'}</td>
                <td><span class="badge ${workloadClass}">${teacher.workload_status}</span></td>
                <td><span class="badge ${statusClass}">${teacher.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Teachers:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function generateTeacherAttendanceTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Teacher ID</th>
                        <th>Teacher Name</th>
                        <th>Specialization</th>
                        <th>Total Days</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Attendance %</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const percentage = parseFloat(record.attendance_percentage || 0);
        const percentageClass = percentage >= 90 ? 'text-success' : 
                               percentage >= 75 ? 'text-warning' : 'text-danger';
        const statusClass = record.status === 'Active' ? 'badge-success' : 'badge-secondary';
        html += `
            <tr>
                <td>${record.teacher_id}</td>
                <td><strong>${record.teacher_name}</strong></td>
                <td>${record.specialization || 'N/A'}</td>
                <td>${record.total_days || 0}</td>
                <td>${record.present_days || 0}</td>
                <td>${record.absent_days || 0}</td>
                <td>${record.late_days || 0}</td>
                <td class="${percentageClass}"><strong>${percentage.toFixed(2)}%</strong></td>
                <td><span class="badge ${statusClass}">${record.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Teachers:</strong> ${response.count || data.length}
        </div>
    `;
    
    if (response.message) {
        html += `
            <div style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                <p style="margin: 0; color: #92400e;"><i class="fas fa-info-circle"></i> ${response.message}</p>
            </div>
        `;
    }
    
    return html;
}

function generateClassStrengthTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Academic Year</th>
                        <th>Class Teacher</th>
                        <th>Total Students</th>
                        <th>Male</th>
                        <th>Female</th>
                        <th>Active</th>
                        <th>Promoted</th>
                        <th>Repeated</th>
                        <th>Capacity Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const capacityClass = record.capacity_status === 'Overcrowded' ? 'badge-danger' : 
                            record.capacity_status === 'Full' ? 'badge-warning' :
                            record.capacity_status === 'Normal' ? 'badge-success' : 'badge-info';
        html += `
            <tr>
                <td><strong>${record.class_name}</strong></td>
                <td>${record.academic_year}</td>
                <td>${record.class_teacher || 'Not Assigned'}</td>
                <td><strong>${record.total_students || 0}</strong></td>
                <td>${record.male_students || 0}</td>
                <td>${record.female_students || 0}</td>
                <td>${record.active_students || 0}</td>
                <td>${record.promoted_students || 0}</td>
                <td>${record.repeated_students || 0}</td>
                <td><span class="badge ${capacityClass}">${record.capacity_status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    if (response.summary) {
        html += `
            <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                    <div><strong>Total Students:</strong> ${response.summary.total_students}</div>
                    <div><strong>Male:</strong> ${response.summary.total_male}</div>
                    <div><strong>Female:</strong> ${response.summary.total_female}</div>
                    <div><strong>Classes:</strong> ${response.count || data.length}</div>
                </div>
            </div>
        `;
    }
    
    return html;
}

function generateSubjectAllocationTable(data, response) {
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Subject</th>
                        <th>Subject Code</th>
                        <th>Students Taking</th>
                        <th>Teacher Assigned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(record => {
        const statusClass = record.status === 'Active' ? 'badge-success' : 'badge-secondary';
        html += `
            <tr>
                <td><strong>${record.class_name}</strong></td>
                <td>${record.subject_name}</td>
                <td>${record.subject_code || 'N/A'}</td>
                <td>${record.students_taking || 0}</td>
                <td>${record.teacher_assigned || 'Not Assigned'}</td>
                <td><span class="badge ${statusClass}">${record.status}</span></td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Allocations:</strong> ${response.count || data.length}
        </div>
    `;
    
    if (response.message) {
        html += `
            <div style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border-radius: 0.5rem; border-left: 4px solid #f59e0b;">
                <p style="margin: 0; color: #92400e;"><i class="fas fa-info-circle"></i> ${response.message}</p>
            </div>
        `;
    }
    
    return html;
}

function generateGenericTable(data, response) {
    if (!data || data.length === 0) {
        return `
            <div class="empty-state">
                <i class="fas fa-info-circle fa-5x" style="color: #3b82f6;"></i>
                <h3>No Data Available</h3>
                <p>This report type is not yet implemented or has no data.</p>
            </div>
        `;
    }
    
    // Get column headers from first data object
    const headers = Object.keys(data[0]);
    
    let html = `
        <div class="table-responsive">
            <table class="report-table">
                <thead>
                    <tr>
    `;
    
    headers.forEach(header => {
        html += `<th>${header.replace(/_/g, ' ').toUpperCase()}</th>`;
    });
    
    html += `
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            html += `<td>${row[header] || 'N/A'}</td>`;
        });
        html += '</tr>';
    });
    
    html += `
                </tbody>
            </table>
        </div>
        <div style="margin-top: 1rem; padding: 1rem; background: #f3f4f6; border-radius: 0.5rem;">
            <strong>Total Records:</strong> ${response.count || data.length}
        </div>
    `;
    
    return html;
}

function resetFilters() {
    document.getElementById('academicYear').value = '';
    document.getElementById('term').value = '';
    document.getElementById('class').value = '';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    
    // Reset to default academic year
    const academicYearSelect = document.getElementById('academicYear');
    const currentYearOption = academicYearSelect.querySelector('option[selected]');
    if (currentYearOption) {
        academicYearSelect.value = currentYearOption.value;
    }
    
    // Clear report content
    document.getElementById('reportContent').innerHTML = `
        <div class="empty-state">
            <i class="fas fa-file-alt fa-5x"></i>
            <h3>Filters Reset</h3>
            <p>Configure your parameters and generate a new report</p>
        </div>
    `;
}

// ============================================================
// EXPORT FUNCTIONS
// ============================================================

function printReport() {
    window.print();
}

function exportToExcel() {
    const activeLink = document.querySelector('.report-submenu a.active');
    if (!activeLink) {
        alert('Please generate a report first');
        return;
    }
    
    // Get table data
    const table = document.querySelector('.report-table');
    if (!table) {
        alert('No report data to export');
        return;
    }
    
    // Convert table to CSV
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        cols.forEach(col => {
            // Remove HTML tags and get text content
            let text = col.textContent.trim();
            // Escape quotes
            text = text.replace(/"/g, '""');
            csvRow.push(`"${text}"`);
        });
        csv.push(csvRow.join(','));
    });
    
    // Create blob and download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const reportType = activeLink.getAttribute('data-report');
    const timestamp = new Date().toISOString().slice(0, 10);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `${reportType}_${timestamp}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportToPDF() {
    const activeLink = document.querySelector('.report-submenu a.active');
    if (!activeLink) {
        alert('Please generate a report first');
        return;
    }
    
    // Get report content
    const reportContent = document.querySelector('.report-display');
    if (!reportContent) {
        alert('No report data to export');
        return;
    }
    
    // Show loading indicator
    const originalContent = reportContent.innerHTML;
    const loadingDiv = document.createElement('div');
    loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 9999;';
    loadingDiv.innerHTML = `
        <div style="text-align: center;">
            <i class="fas fa-spinner fa-spin fa-3x" style="color: #3b82f6;"></i>
            <p style="margin-top: 1rem; font-size: 16px;">Generating PDF...</p>
        </div>
    `;
    document.body.appendChild(loadingDiv);
    
    // Use html2pdf library
    const reportType = activeLink.getAttribute('data-report');
    const reportTitle = activeLink.textContent.trim();
    const timestamp = new Date().toISOString().slice(0, 10);
    const filename = `${reportType}_${timestamp}.pdf`;
    
    // Configure PDF options
    const opt = {
        margin: [10, 10, 10, 10],
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2,
            useCORS: true,
            letterRendering: true,
            logging: false
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'landscape'  // Better for wide tables
        },
        pagebreak: { 
            mode: ['avoid-all', 'css', 'legacy'],
            before: '.page-break'
        }
    };
    
    // Create a container with header for PDF
    const pdfContainer = document.createElement('div');
    pdfContainer.style.cssText = 'background: white; padding: 20px;';
    
    // Add header
    const header = document.createElement('div');
    header.style.cssText = 'text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px;';
    header.innerHTML = `
        <h1 style="margin: 0; font-size: 24px; color: #333;">${reportTitle}</h1>
        <p style="margin: 5px 0; font-size: 14px; color: #666;">Generated on: ${new Date().toLocaleString()}</p>
    `;
    pdfContainer.appendChild(header);
    
    // Clone report content
    const contentClone = reportContent.cloneNode(true);
    
    // Remove action buttons from clone
    const buttons = contentClone.querySelectorAll('button, .btn');
    buttons.forEach(btn => btn.remove());
    
    // Style tables for PDF
    const tables = contentClone.querySelectorAll('table');
    tables.forEach(table => {
        table.style.cssText = 'width: 100%; border-collapse: collapse; font-size: 10px;';
        const cells = table.querySelectorAll('th, td');
        cells.forEach(cell => {
            cell.style.cssText = 'border: 1px solid #ddd; padding: 6px; text-align: left;';
        });
        const headers = table.querySelectorAll('th');
        headers.forEach(header => {
            header.style.cssText += 'background-color: #f3f4f6; font-weight: bold;';
        });
    });
    
    pdfContainer.appendChild(contentClone);
    
    // Generate PDF
    html2pdf()
        .set(opt)
        .from(pdfContainer)
        .save()
        .then(() => {
            // Remove loading indicator
            document.body.removeChild(loadingDiv);
        })
        .catch(error => {
            console.error('PDF generation error:', error);
            document.body.removeChild(loadingDiv);
            alert('Failed to generate PDF. Please try again.');
        });
}