/**
 * Initializes chart filters and event handlers
 */
function initializeChartFilters() {
    // Enrollment chart filters
    const academicYearFilter = document.getElementById('academicYearFilter');
    const termFilter = document.getElementById('termFilter');
    const classFilter = document.getElementById('classFilter');
    const applyFiltersBtn = document.getElementById('applyFilters');
    const resetFiltersBtn = document.getElementById('resetFilters');

    // Revenue chart filters
    const revenueYearFilter = document.getElementById('revenueYearFilter');
    const revenueTermFilter = document.getElementById('revenueTermFilter');
    const applyRevenueFiltersBtn = document.getElementById('applyRevenueFilters');

    // Set current academic year as default
    setDefaultAcademicYear();

    // Event listeners for enrollment chart
    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', applyEnrollmentFilters);
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', resetEnrollmentFilters);
    }

    // Event listeners for revenue chart
    if (applyRevenueFiltersBtn) {
        applyRevenueFiltersBtn.addEventListener('click', applyRevenueFilters);
    }

    // Load classes dynamically
    loadClassesForFilter();
}

/**
 * Sets the current academic year as default in filters
 */
function setDefaultAcademicYear() {
    const currentYear = new Date().getFullYear();
    const nextYear = currentYear + 1;
    const currentAcademicYear = `${currentYear}-${nextYear}`;
    
    // Set default for enrollment chart
    const academicYearFilter = document.getElementById('academicYearFilter');
    if (academicYearFilter) {
        academicYearFilter.value = currentAcademicYear;
    }
    
    // Set default for revenue chart
    const revenueYearFilter = document.getElementById('revenueYearFilter');
    if (revenueYearFilter) {
        revenueYearFilter.value = currentAcademicYear;
    }
}

/**
 * Loads classes from database for the class filter
 */
async function loadClassesForFilter() {
    try {
        const response = await fetch('api/get_classes.php');
        if (response.ok) {
            const classes = await response.json();
            populateClassFilter(classes);
        } else {
            console.warn('Could not load classes from API, using default options');
        }
    } catch (error) {
        console.error('Error loading classes:', error);
    }
}

/**
 * Populates the class filter dropdown with classes
 */
function populateClassFilter(classes) {
    const classFilter = document.getElementById('classFilter');
    if (!classFilter || !classes) return;

    // Clear existing options except the first one
    while (classFilter.options.length > 1) {
        classFilter.remove(1);
    }

    // Add classes from API response
    classes.forEach(classItem => {
        const option = document.createElement('option');
        option.value = classItem.id;
        option.textContent = classItem.class_name || `Class ${classItem.id}`;
        classFilter.appendChild(option);
    });
}

/**
 * Applies filters to the enrollment chart
 */
async function applyEnrollmentFilters() {
    const academicYear = document.getElementById('academicYearFilter').value;
    const term = document.getElementById('termFilter').value;
    const classId = document.getElementById('classFilter').value;

    // Validate filters
    if (!academicYear) {
        showNotification('Please select an academic year', 'warning');
        return;
    }

    showLoadingState('applyFilters', true);

    try {
        // Fetch filtered data from server
        const enrollmentData = await fetchEnrollmentData(academicYear, term, classId);
        
        // Update chart with new data
        updateEnrollmentChart(enrollmentData);
        
        showNotification('Filters applied successfully', 'success');
    } catch (error) {
        console.error('Error applying filters:', error);
        showNotification('Error applying filters', 'error');
    } finally {
        showLoadingState('applyFilters', false);
    }
}

/**
 * Applies filters to the revenue chart
 */
async function applyRevenueFilters() {
    const academicYear = document.getElementById('revenueYearFilter').value;
    const term = document.getElementById('revenueTermFilter').value;

    if (!academicYear) {
        showNotification('Please select an academic year', 'warning');
        return;
    }

    showLoadingState('applyRevenueFilters', true);

    try {
        const revenueData = await fetchRevenueData(academicYear, term);
        updateRevenueChart(revenueData);
        showNotification('Revenue filters applied successfully', 'success');
    } catch (error) {
        console.error('Error applying revenue filters:', error);
        showNotification('Error applying revenue filters', 'error');
    } finally {
        showLoadingState('applyRevenueFilters', false);
    }
}

/**
 * Resets all enrollment chart filters
 */
function resetEnrollmentFilters() {
    document.getElementById('academicYearFilter').value = '';
    document.getElementById('termFilter').value = '';
    document.getElementById('classFilter').value = '';
    
    // Reset to default academic year
    setDefaultAcademicYear();
    
    // Reload default chart data
    initializeCharts();
    
    showNotification('Filters reset successfully', 'info');
}

/**
 * Fetches enrollment data from the server
 */
async function fetchEnrollmentData(academicYear, term, classId) {
    const params = new URLSearchParams({
        academic_year: academicYear,
        term: term || '',
        class_id: classId || ''
    });

    const response = await fetch(`api/get_enrollment_data.php?${params}`);
    
    if (!response.ok) {
        throw new Error('Failed to fetch enrollment data');
    }

    return await response.json();
}

/**
 * Fetches revenue data from the server
 */
async function fetchRevenueData(academicYear, term) {
    const params = new URLSearchParams({
        academic_year: academicYear,
        term: term || ''
    });

    const response = await fetch(`api/get_revenue_data.php?${params}`);
    
    if (!response.ok) {
        throw new Error('Failed to fetch revenue data');
    }

    return await response.json();
}

/**
 * Updates the enrollment chart with new data
 */
function updateEnrollmentChart(data) {
    const enrollmentCtx = document.getElementById('enrollmentChart')?.getContext('2d');
    if (!enrollmentCtx) return;

    // Destroy existing chart if it exists
    if (window.enrollmentChart instanceof Chart) {
        window.enrollmentChart.destroy();
    }

    window.enrollmentChart = new Chart(enrollmentCtx, {
        type: 'line',
        data: {
            labels: data.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Student Enrollment',
                data: data.values || [25, 30, 45, 60, 50, 70, 90, 80, 75, 60, 40, 30],
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderColor: 'rgba(78, 115, 223, 1)',
                borderWidth: 2,
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: '#fff',
                pointHoverRadius: 5,
                pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointHoverBorderColor: '#fff',
                pointHitRadius: 10,
                pointBorderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: getChartOptions('students')
    });
}

/**
 * Updates the revenue chart with new data
 */
function updateRevenueChart(data) {
    const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
    if (!revenueCtx) return;

    if (window.revenueChart instanceof Chart) {
        window.revenueChart.destroy();
    }

    window.revenueChart = new Chart(revenueCtx, {
        type: 'doughnut',
        data: {
            labels: data.labels || ['Tuition Fees', 'Donations', 'Events', 'Other'],
            datasets: [{
                data: data.values || [65, 15, 10, 10],
                backgroundColor: [
                    'rgba(78, 115, 223, 0.8)',
                    'rgba(28, 200, 138, 0.8)',
                    'rgba(246, 194, 62, 0.8)',
                    'rgba(231, 74, 59, 0.8)'
                ],
                hoverBackgroundColor: [
                    'rgba(78, 115, 223, 1)',
                    'rgba(28, 200, 138, 1)',
                    'rgba(246, 194, 62, 1)',
                    'rgba(231, 74, 59, 1)'
                ],
                hoverBorderColor: 'rgba(234, 236, 244, 1)',
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    bodyFont: { size: 12 },
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: $${context.raw.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
}

/**
 * Shows loading state for filter buttons
 */
function showLoadingState(buttonId, isLoading) {
    const button = document.getElementById(buttonId);
    if (!button) return;

    if (isLoading) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';
        button.disabled = true;
    } else {
        if (buttonId === 'applyFilters') {
            button.innerHTML = '<i class="fas fa-filter"></i> Apply';
        } else if (buttonId === 'applyRevenueFilters') {
            button.innerHTML = '<i class="fas fa-filter"></i> Apply';
        }
        button.disabled = false;
    }
}

/**
 * Shows notification messages
 */
function showNotification(message, type = 'info') {
    // You can integrate with your existing notification system
    console.log(`${type.toUpperCase()}: ${message}`);
    
    // Simple browser notification
    alert(`${type.toUpperCase()}: ${message}`);
}

// Update the DOMContentLoaded event to include chart filters
document.addEventListener('DOMContentLoaded', function() {
    // Initialize UI Components
    setupMobileMenu();
    setupDropdowns();
    setupSidebarDropdowns();
    
    // Initialize Plugins
    initializePlugins();
    
    // Initialize Chart Filters
    initializeChartFilters();

    // Add ripple effect to buttons
    document.querySelectorAll('.btn, .action-item, .btn-icon').forEach(btn => {
        btn.addEventListener('click', createRipple);
    });
});