// dashboard.js - Fixed Version with Working Dropdowns and Charts

document.addEventListener('DOMContentLoaded', function() {
    // Initialize UI Components
    setupMobileMenu();
    setupDesktopSidebarCollapse();
    setupTopNavDropdowns();
    setupUnifiedSidebarDropdowns();
    initializePlugins();
    // Add ripple effect to buttons and action items
    document.querySelectorAll('.btn, .action-item, .btn-icon').forEach(btn => {
        btn.addEventListener('click', createRipple);
    });

     // Fix dropdown positioning
function positionDropdowns() {
    document.querySelectorAll(".sidebar-nav .dropdown.open").forEach(dropdown => {
        const menu = dropdown.querySelector(".dropdown-menu");
        const sidebar = document.querySelector(".sidebar");
        const isCollapsed = sidebar.classList.contains("sidebar-collapsed");
        
        if (isCollapsed && window.innerWidth > 768) {
            // Position for collapsed fly-out
            const toggle = dropdown.querySelector(".dropdown-toggle");
            const rect = toggle.getBoundingClientRect();
            menu.style.top = rect.top + "px";
            menu.style.left = "80px";
            menu.style.position = "fixed";
        } else {
            // Expanded mode: Clear inline styles to let CSS accordion work
            menu.style.top = "";
            menu.style.left = "";
            menu.style.position = "";
        }
    });
}
    
    // Update dropdown positions on toggle
    document.querySelectorAll(".sidebar-nav .dropdown-toggle").forEach(toggle => {
        toggle.addEventListener("click", function() {
            setTimeout(positionDropdowns, 10);
        });
    });
    
    // Update on window resize
    window.addEventListener("resize", positionDropdowns);
});


function setupDesktopSidebarCollapse() {
    const topNav = document.querySelector('.top-nav');
    if (!topNav) return;

    let desktopMenuBtn = document.querySelector('.desktop-menu-btn');
    if (!desktopMenuBtn) {
        desktopMenuBtn = document.createElement('div');
        desktopMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        desktopMenuBtn.className = 'desktop-menu-btn';
        topNav.prepend(desktopMenuBtn);
    }

    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content') || document.querySelector('.main-wrapper');

    if (!sidebar || !mainContent) return;

    let sidebarCollapsedDesktop = localStorage.getItem('sidebarCollapsedDesktop') === 'true';

    // --- HELPER FUNCTIONS ---

    function addTooltipsToSidebarItems() {
        document.querySelectorAll('.sidebar-nav li a').forEach(link => {
            const span = link.querySelector('span');
            if (span) {
                link.setAttribute('data-tooltip', span.textContent.trim());
            }
        });
    }

    function removeTooltipsFromSidebarItems() {
        document.querySelectorAll('.sidebar-nav li a').forEach(link => {
            link.removeAttribute('data-tooltip');
        });
    }

    function toggleOverlay(show) {
        let overlay = document.querySelector('.sidebar-overlay');
        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                overlay.style.display = 'block';
                overlay.addEventListener('click', () => {
                    sidebar.classList.add('sidebar-hidden');
                    toggleOverlay(false);
                });
                document.body.appendChild(overlay);
            }
        } else if (overlay) {
            overlay.remove();
        }
    }

    function updateSidebarUI() {
        if (window.innerWidth > 768) {
            toggleOverlay(false); // Clean up mobile overlay if resizing to desktop
            
            if (sidebarCollapsedDesktop) {
                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.add('sidebar-collapsed-desktop');
                desktopMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                addTooltipsToSidebarItems(); // <--- Re-added
            } else {
                sidebar.classList.remove('sidebar-collapsed');
                mainContent.classList.remove('sidebar-collapsed-desktop');
                desktopMenuBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeTooltipsFromSidebarItems(); // <--- Re-added
            }
        } else {
            // Reset desktop classes on mobile
            sidebar.classList.remove('sidebar-collapsed');
            mainContent.classList.remove('sidebar-collapsed-desktop');
            removeTooltipsFromSidebarItems();
        }
    }

    // --- EVENTS ---

    desktopMenuBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (window.innerWidth > 768) {
            sidebarCollapsedDesktop = !sidebarCollapsedDesktop;
            localStorage.setItem('sidebarCollapsedDesktop', sidebarCollapsedDesktop);
            updateSidebarUI();
        } else {
            const isOpening = sidebar.classList.contains('sidebar-hidden');
            sidebar.classList.toggle('sidebar-hidden');
            toggleOverlay(isOpening); 
        }
    });

    window.addEventListener('resize', updateSidebarUI);
    updateSidebarUI();
}

// Export functions for use in other scripts if needed
window.sidebarCollapse = {
    collapse: function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        if (sidebar && window.innerWidth > 768) {
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.add('sidebar-collapsed-desktop');
            localStorage.setItem('sidebarCollapsedDesktop', 'true');
        }
    },
    expand: function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        if (sidebar && window.innerWidth > 768) {
            sidebar.classList.remove('sidebar-collapsed');
            mainContent.classList.remove('sidebar-collapsed-desktop');
            localStorage.setItem('sidebarCollapsedDesktop', 'false');
        }
    },
    toggle: function() {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar && window.innerWidth > 768) {
            if (sidebar.classList.contains('sidebar-collapsed')) {
                this.expand();
            } else {
                this.collapse();
            }
        }
    }
};


function setupTopNavDropdowns() {
    const notificationsBtn = document.getElementById('notificationsBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    // Remove old listeners by cloning if needed, but better: direct add
    if (notificationsBtn && notificationsDropdown) {
        notificationsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationsDropdown.classList.toggle('show');
            if (userDropdown) userDropdown.classList.remove('show');
        });
    }

    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
            if (notificationsDropdown) notificationsDropdown.classList.remove('show');
        });
    }

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#notificationsBtn') && !e.target.closest('#notificationsDropdown')) {
            if (notificationsDropdown) notificationsDropdown.classList.remove('show');
        }
        if (!e.target.closest('#userMenuBtn') && !e.target.closest('#userDropdown')) {
            if (userDropdown) userDropdown.classList.remove('show');
        }
    });

    // Prevent close inside dropdown
    if (notificationsDropdown) notificationsDropdown.addEventListener('click', e => e.stopPropagation());
    if (userDropdown) userDropdown.addEventListener('click', e => e.stopPropagation());
}



function setupUnifiedSidebarDropdowns() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    const dropdownToggles = document.querySelectorAll(".sidebar-nav .dropdown-toggle");
    
  // In setupUnifiedSidebarDropdowns() or similar
dropdownToggles.forEach(toggle => {
    const newToggle = toggle.cloneNode(true);
    toggle.parentNode.replaceChild(newToggle, toggle);

    newToggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const parentLi = this.parentElement;
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');

        // Close others
        document.querySelectorAll(".sidebar-nav .dropdown").forEach(item => {
            if (item !== parentLi) item.classList.remove("open");
        });

        parentLi.classList.toggle("open");

        const dropdownMenu = parentLi.querySelector('.dropdown-menu');
        if (dropdownMenu && isCollapsed && window.innerWidth > 768) {
            const rect = parentLi.getBoundingClientRect();
            dropdownMenu.style.top = `${rect.top}px`;
            dropdownMenu.style.left = '80px'; // Fixed left for collapsed
        }
    });
});

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        const isClickInsideDropdown = e.target.closest('.sidebar-nav .dropdown-menu') || e.target.closest('.sidebar-nav .dropdown-toggle');
        if (!isClickInsideDropdown) {
            document.querySelectorAll(".sidebar-nav .dropdown").forEach(item => {
                item.classList.remove("open");
            });
        }
    });

    // Reposition on scroll for collapsed sidebar
    let scrollTimeout;
    window.addEventListener('scroll', function() {
        if (sidebar.classList.contains('sidebar-collapsed') && window.innerWidth > 768) {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                document.querySelectorAll(".sidebar-nav .dropdown.open").forEach(dropdown => {
                    const dropdownMenu = dropdown.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        const rect = dropdown.getBoundingClientRect();
                        dropdownMenu.style.top = rect.top + 'px';
                    }
                });
            }, 50);
        }
    });
}

function createRipple(e) {
    const btn = e.currentTarget;
    const circle = document.createElement("span");
    const diameter = Math.max(btn.clientWidth, btn.clientHeight);
    const radius = diameter / 2;

    circle.style.width = circle.style.height = `${diameter}px`;
    circle.style.left = `${e.clientX - btn.getBoundingClientRect().left - radius}px`;
    circle.style.top = `${e.clientY - btn.getBoundingClientRect().top - radius}px`;
    circle.classList.add("ripple");

    // Remove any existing ripple to prevent multiple ripples stacking
    const ripple = btn.getElementsByClassName("ripple")[0];
    if (ripple) {
        ripple.remove();
    }

    // Add the ripple but prevent it from affecting layout
    circle.style.position = 'absolute';
    circle.style.pointerEvents = 'none'; // Prevent interference with clicks
    btn.style.position = 'relative'; // Ensure proper positioning context
    btn.style.overflow = 'hidden'; // Contain the ripple within the button

    btn.appendChild(circle);

    // Remove the ripple after animation completes
    setTimeout(() => {
        if (circle.parentNode === btn) {
            circle.remove();
        }
    }, 600); // Match this with your CSS animation duration
}

/**
 * Sets up the enhanced mobile menu toggle functionality with complete hide/show.
 */
async function setupMobileMenu() {
    const topNav = document.querySelector('.top-nav');
    if (!topNav) return;

    // Check if mobile menu button already exists to avoid duplicates
    let mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    if (!mobileMenuBtn) {
        mobileMenuBtn = document.createElement('div');
        mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        mobileMenuBtn.className = 'mobile-menu-btn';
        topNav.prepend(mobileMenuBtn);
    }

    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (!sidebar || !mainContent) return;

    // Track sidebar visibility state
    let sidebarVisible = true;

    // Function to completely hide sidebar
    async function hideSidebar() {
        sidebar.classList.add('sidebar-hidden');
        mainContent.classList.add('sidebar-collapsed');
        
        // Update button icon
        mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        mobileMenuBtn.setAttribute('title', 'Show Sidebar');
        
        sidebarVisible = false;
        
        // Create overlay for mobile
        if (window.innerWidth <= 768) {
            removeOverlay();
        }
    }

    // Function to show sidebar
    async function showSidebar() {
        sidebar.classList.remove('sidebar-hidden');
        mainContent.classList.remove('sidebar-collapsed');
        
        // Update button icon
        mobileMenuBtn.innerHTML = '<i class="fas fa-times"></i>';
        mobileMenuBtn.setAttribute('title', 'Hide Sidebar');
        
        sidebarVisible = true;
        
        // Create overlay for mobile when sidebar is shown
        if (window.innerWidth <= 768) {
            setTimeout(() => createOverlay(), 100);
        }
    }

    // Function to create overlay for mobile
    function createOverlay() {
        if (window.innerWidth > 768) return;
        
        removeOverlay(); // Remove existing overlay if any
        
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        
        document.body.appendChild(overlay);
        
        // Fade in overlay
        setTimeout(() => {
            overlay.style.opacity = '1';
        }, 10);
        
        // Hide sidebar when overlay is clicked
        overlay.addEventListener('click', hideSidebar);
    }

    // Function to remove overlay
    function removeOverlay() {
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 300);
        }
    }

    // Enhanced click event listener for menu button
    mobileMenuBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (sidebarVisible) {
            hideSidebar();
        } else {
            showSidebar();
        }
    });

    // Handle window resize
    window.addEventListener('resize', async function() {
        if (window.innerWidth > 768) {
            // Desktop view - remove overlay and ensure proper layout
            removeOverlay();
            if (sidebarVisible) {
                sidebar.classList.remove('sidebar-hidden');
                mainContent.classList.remove('sidebar-collapsed');
            }
        } else {
            // Mobile view - create overlay if sidebar is visible
            if (sidebarVisible) {
                createOverlay();
            }
        }
    });

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', async function(e) {
        if (window.innerWidth <= 768 && sidebarVisible) {
            const isClickInsideSidebar = sidebar.contains(e.target);
            const isClickOnMenuBtn = mobileMenuBtn.contains(e.target);
            
            if (!isClickInsideSidebar && !isClickOnMenuBtn) {
                hideSidebar();
            }
        }
    });

    // Initialize proper state
    if (window.innerWidth <= 768) {
        hideSidebar();
    }
}

/**
 * Sets up sidebar dropdown functionality with proper event handling
 */
function setupSidebarDropdowns() {
    const dropdownToggles = document.querySelectorAll(".sidebar-nav .dropdown-toggle");
    
    dropdownToggles.forEach(toggle => {
        // Remove any existing event listeners to prevent duplicates
        const newToggle = toggle.cloneNode(true);
        toggle.parentNode.replaceChild(newToggle, toggle);
        
        newToggle.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parentLi = this.parentElement;
            const isCurrentlyOpen = parentLi.classList.contains("open");

            // Close all other dropdowns (accordion effect)
            document.querySelectorAll(".sidebar-nav .dropdown").forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove("open");
                }
            });

            // Toggle the clicked dropdown
            if (isCurrentlyOpen) {
                parentLi.classList.remove("open");
            } else {
                parentLi.classList.add("open");
            }
        });
    });
}

/**
 * Handles user logout.
 */
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        // In a real application, this would involve invalidating sessions/tokens on the server.
        localStorage.removeItem('isAuthenticated');
        localStorage.removeItem('username');
        localStorage.removeItem('role');
        
        // Redirect to logout page
        window.location.href = 'logout.php';
    }
}

/**
 * Initializes third-party plugins like Chart.js and DataTables.
 */
async function initializePlugins() {
    // Initialize Chart Filters first
    await initializeChartFilters();
    
    // Check if Chart.js is loaded before initializing charts
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    } else {
    }

    // Check if jQuery and DataTables are loaded before initializing tables
    if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
        initializeDataTables();
    } else {
        console.warn('jQuery or DataTables not found. DataTables will not be initialized.');
    }
}

/**
 * Returns common options for line/bar charts.
 * @param {string} unit - The unit to display on the tooltip label.
 * @returns {object} Chart.js options object.
 */
function getChartOptions(unit) {
    return {
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 14 },
                bodyFont: { size: 12 },
                callbacks: {
                    label: context => `${context.parsed.y} ${unit}`
                }
            }
        },
        scales: {
            x: {
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0, 0, 0, 0.05)' },
                ticks: { callback: value => value }
            }
        }
    };
}

/**
 * Initializes Chart.js charts on the dashboard.
 */
function initializeCharts() {
    // Enrollment Chart (Line Chart)
    const enrollmentCtx = document.getElementById('enrollmentChart')?.getContext('2d');
    if (enrollmentCtx) {
        // Destroy existing chart if it exists
        if (window.enrollmentChart instanceof Chart) {
            window.enrollmentChart.destroy();
        }

        window.enrollmentChart = new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Student Enrollment',
                    data: [25, 30, 45, 60, 50, 70, 90, 80, 75, 60, 40, 30],
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

    // Revenue Chart (Doughnut Chart)
    const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
    if (revenueCtx) {
        // Destroy existing chart if it exists
        if (window.revenueChart instanceof Chart) {
            window.revenueChart.destroy();
        }

        window.revenueChart = new Chart(revenueCtx, {
            type: 'doughnut',
            data: {
                labels: ['Tuition Fees', 'Donations', 'Events', 'Other'],
                datasets: [{
                    data: [65, 15, 10, 10],
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
}
/**
 * Initializes chart filters and event handlers
 */
async function initializeChartFilters() {
    
    // Load all filter data from database
    await loadAllFilterData();
    
    // Set current academic year as default
    setDefaultAcademicYear();

    // Event listeners for enrollment chart
    const applyFiltersBtn = document.getElementById('applyFilters');
    const resetFiltersBtn = document.getElementById('resetFilters');
    const applyRevenueFiltersBtn = document.getElementById('applyRevenueFilters');

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', applyEnrollmentFilters);
    }

    if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', resetEnrollmentFilters);
    }

    if (applyRevenueFiltersBtn) {
        applyRevenueFiltersBtn.addEventListener('click', applyRevenueFilters);
    }

 
}

/**
 * Loads all filter data from database
 */
async function loadAllFilterData() {
    try {
        
        // Load academic years, terms, and classes simultaneously
        const [academicYears, terms, classes] = await Promise.all([
            fetchAcademicYears(),
            fetchTerms(),
            fetchClasses()
        ]);

  

        populateAcademicYearFilter(academicYears);
        populateTermFilter(terms);
        populateClassFilter(classes);
        
        // Also populate revenue filters
        populateRevenueAcademicYearFilter(academicYears);
        populateRevenueTermFilter(terms);

    } catch (error) {
       
        showNotification('Error loading filter options', 'error');
    }
}

/**
 * Fetches academic years from database
 */
async function fetchAcademicYears() {
    try {
        const response = await fetch('api/get_academic_years.php');
        if (!response.ok) throw new Error('Failed to fetch academic years');
        const data = await response.json();
      
        return data;
    } catch (error) {
        return [];
    }
}

/**
 * Fetches terms from database
 */
async function fetchTerms() {
    try {
        const response = await fetch('api/get_terms.php');
        if (!response.ok) throw new Error('Failed to fetch terms');
        const data = await response.json();
      
        return data;
    } catch (error) {
       
        return [];
    }
}

/**
 * Fetches classes from database
 */
async function fetchClasses() {
    try {
        const response = await fetch('api/get_classes.php');
        if (!response.ok) throw new Error('Failed to fetch classes');
        const data = await response.json();
       
        return data;
    } catch (error) {
   
        return [];
    }
}

/**
 * Populates academic year filter dropdown
 */
function populateAcademicYearFilter(academicYears) {
    const filter = document.getElementById('academicYearFilter');
    if (!filter) return;

    // Clear existing options
    filter.innerHTML = '<option value="">Select Academic Year</option>';

    if (academicYears.length === 0) {
        filter.innerHTML += '<option value="" disabled>No academic years found</option>';
        console.warn('No academic years found');
        return;
    }

    academicYears.forEach(year => {
        const option = document.createElement('option');
        option.value = year.id;
        option.textContent = year.year_name;
        
        // Mark current academic year
        if (year.is_current == 1) {
            option.selected = true;
        }
        
        filter.appendChild(option);
    });
    

}

/**
 * Populates revenue academic year filter dropdown
 */
function populateRevenueAcademicYearFilter(academicYears) {
    const filter = document.getElementById('revenueYearFilter');
    if (!filter) return;

    filter.innerHTML = '<option value="">Select Academic Year</option>';

    if (academicYears.length === 0) {
        filter.innerHTML += '<option value="" disabled>No academic years found</option>';
        return;
    }

    academicYears.forEach(year => {
        const option = document.createElement('option');
        option.value = year.id;
        option.textContent = year.year_name;
        
        if (year.is_current == 1) {
            option.selected = true;
        }
        
        filter.appendChild(option);
    });
}

/**
 * Populates term filter dropdown
 */
function populateTermFilter(terms) {
    const filter = document.getElementById('termFilter');
    if (!filter) return;

    filter.innerHTML = '<option value="">Select Term</option>';

    if (terms.length === 0) {
        filter.innerHTML += '<option value="" disabled>No terms found</option>';
        return;
    }

    terms.forEach(term => {
        const option = document.createElement('option');
        option.value = term.id;
        option.textContent = term.term_name || `Term ${term.term_number}`;
        filter.appendChild(option);
    });
}

/**
 * Populates revenue term filter dropdown
 */
function populateRevenueTermFilter(terms) {
    const filter = document.getElementById('revenueTermFilter');
    if (!filter) return;

    filter.innerHTML = '<option value="">Select Term</option>';

    if (terms.length === 0) {
        filter.innerHTML += '<option value="" disabled>No terms found</option>';
        return;
    }

    terms.forEach(term => {
        const option = document.createElement('option');
        option.value = term.id;
        option.textContent = term.term_name || `Term ${term.term_number}`;
        filter.appendChild(option);
    });
}

/**
 * Populates the class filter dropdown with classes
 */
function populateClassFilter(classes) {
    const filter = document.getElementById('classFilter');
    if (!filter) return;

    filter.innerHTML = '<option value="">Select Class</option>';
    filter.innerHTML += '<option value="all">All Classes</option>';

    if (classes.length === 0) {
        filter.innerHTML += '<option value="" disabled>No classes found</option>';
        console.warn('No classes found');
        return;
    }

    classes.forEach(classItem => {
        const option = document.createElement('option');
        option.value = classItem.id;
        option.textContent = classItem.class_name;
        
        // Add academic year info if available
        if (classItem.academic_year) {
            option.textContent += ` (${classItem.academic_year})`;
        }
        
        filter.appendChild(option);
    });
    

}

/**
 * Sets the current academic year as default in filters
 */
function setDefaultAcademicYear() {
    const academicYearFilter = document.getElementById('academicYearFilter');
    const revenueYearFilter = document.getElementById('revenueYearFilter');
    
    // Try to find current academic year
    if (academicYearFilter) {
        const currentYearOption = academicYearFilter.querySelector('option[selected]');
        if (!currentYearOption) {
            // Select the first available year if no current year is marked
            const options = academicYearFilter.options;
            if (options.length > 1) {
                academicYearFilter.value = options[1].value;
            }
        }
    }
    
    if (revenueYearFilter) {
        const currentYearOption = revenueYearFilter.querySelector('option[selected]');
        if (!currentYearOption) {
            const options = revenueYearFilter.options;
            if (options.length > 1) {
                revenueYearFilter.value = options[1].value;
            }
        }
    }
}

/**
 * Applies filters to the enrollment chart
 */
async function applyEnrollmentFilters() {
    const academicYearId = document.getElementById('academicYearFilter').value;
    const termId = document.getElementById('termFilter').value;
    const classId = document.getElementById('classFilter').value;

    // Validate filters
    if (!academicYearId) {
        showNotification('Please select an academic year', 'warning');
        return;
    }

    showLoadingState('applyFilters', true);

    try {
        // Fetch filtered data from server
        const enrollmentData = await fetchEnrollmentData(academicYearId, termId, classId);
        
        // Update chart with new data
        updateEnrollmentChart(enrollmentData);
        
        showNotification('Filters applied successfully', 'success');
    } catch (error) {
     
        showNotification('Error applying filters', 'error');
    } finally {
        showLoadingState('applyFilters', false);
    }
}

/**
 * Applies filters to the revenue chart
 */
async function applyRevenueFilters() {
    const academicYearId = document.getElementById('revenueYearFilter').value;
    const termId = document.getElementById('revenueTermFilter').value;

    if (!academicYearId) {
        showNotification('Please select an academic year', 'warning');
        return;
    }

    showLoadingState('applyRevenueFilters', true);

    try {
        const revenueData = await fetchRevenueData(academicYearId, termId);
        updateRevenueChart(revenueData);
        showNotification('Revenue filters applied successfully', 'success');
    } catch (error) {
  
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
 * Fetches enrollment data from the server with database filters
 */
async function fetchEnrollmentData(academicYearId, termId, classId) {
    const params = new URLSearchParams({
        academic_year_id: academicYearId,
        term_id: termId || '',
        class_id: classId || ''
    });

    const response = await fetch(`api/get_enrollment_data.php?${params}`);
    
    if (!response.ok) {
        throw new Error('Failed to fetch enrollment data');
    }

    return await response.json();
}

/**
 * Fetches revenue data from the server with database filters
 */
async function fetchRevenueData(academicYearId, termId) {
    const params = new URLSearchParams({
        academic_year_id: academicYearId,
        term_id: termId || ''
    });

    const response = await fetch(`api/get_revenue_data.php?${params}`);
    
    if (!response.ok) {
        throw new Error('Failed to fetch revenue data');
    }

    return await response.json();
}

/**
 * Updates the enrollment chart with new data and shows filter info
 */
/**
 * Updates the enrollment chart with new data and shows filter info
 */
function updateEnrollmentChart(data) {
    const enrollmentCtx = document.getElementById('enrollmentChart')?.getContext('2d');
    if (!enrollmentCtx) return;

    // Update filter info
    const chartInfo = document.getElementById('enrollmentChartInfo');
    if (chartInfo) {
        const academicYearSelect = document.getElementById('academicYearFilter');
        const classSelect = document.getElementById('classFilter');
        const selectedYear = academicYearSelect.options[academicYearSelect.selectedIndex]?.text || 'All';
        const selectedClass = classSelect.options[classSelect.selectedIndex]?.text || 'All';
        
        chartInfo.innerHTML = `
            <strong>Filters Applied:</strong> 
            Academic Year: ${selectedYear} | 
            Class: ${selectedClass} | 
            Total Students: ${data.total_enrollments || 0}
        `;
    }

    // Destroy existing chart if it exists
    if (window.enrollmentChart instanceof Chart) {
        window.enrollmentChart.destroy();
    }

    // Use default data if no data is returned
    const labels = data.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const values = data.values || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]; // Default to zeros if no data

    window.enrollmentChart = new Chart(enrollmentCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Student Enrollment',
                data: values,
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

    // Use default data if no data is returned
    const labels = data.labels || ['Tuition Fees', 'Donations', 'Events', 'Other'];
    const values = data.values || [0, 0, 0, 0]; // Default to zeros if no data

    window.revenueChart = new Chart(revenueCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: values,
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
                            const percentage = total > 0 ? Math.round((context.raw / total) * 100) : 0;
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

/**
 * Initializes DataTables for any table with the 'data-table' class.
 */
function initializeDataTables() {
    $('.data-table').each(function() {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
                responsive: true,
                dom: '<"top"fB>rt<"bottom"lip><"clear">',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search..."
                }
            });
        }
    });
}

// Mobile sidebar toggle functionality
document.addEventListener("DOMContentLoaded", function() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-hidden');
            // Add overlay when sidebar is open on mobile
            if (!sidebar.classList.contains('sidebar-hidden')) {
                createOverlay();
            } else {
                removeOverlay();
            }
        });
    }
    
    function createOverlay() {
        if (document.querySelector('.sidebar-overlay')) return;
        
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.style.display = 'block';
        
        overlay.addEventListener('click', function() {
            sidebar.classList.add('sidebar-hidden');
            removeOverlay();
        });
        
        document.body.appendChild(overlay);
        
        // Animate overlay in
        setTimeout(() => {
            overlay.style.opacity = '1';
        }, 10);
    }
    
    function removeOverlay() {
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 300);
        }
    }
});