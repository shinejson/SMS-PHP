// dashboard.js - Fixed Version with Working Dropdowns

document.addEventListener('DOMContentLoaded', function() {
    // Initialize UI Components
    setupMobileMenu();
    setupDropdowns();
    setupSidebarDropdowns(); // Separate function for sidebar dropdowns
    
    // Initialize Plugins (Charts and DataTables)
    initializePlugins();

    // Add ripple effect to buttons and action items
    document.querySelectorAll('.btn, .action-item, .btn-icon').forEach(btn => {
        btn.addEventListener('click', createRipple);
    });
});

/**
 * Creates a ripple effect on button clicks.
 * @param {Event} e - The click event.
 */
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

    btn.appendChild(circle);
}

/**
 * Sets up the enhanced mobile menu toggle functionality with complete hide/show.
 */
function setupMobileMenu() {
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
    function hideSidebar() {
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
    function showSidebar() {
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
    window.addEventListener('resize', function() {
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
    document.addEventListener('click', function(e) {
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
 * Sets up dropdown menus for notifications and user profile.
 */
function setupDropdowns() {
    // Clean up any existing dropdowns first
    const existingDropdowns = document.querySelectorAll('.notification-dropdown, .user-dropdown');
    existingDropdowns.forEach(dropdown => dropdown.remove());

    // Notification Dropdown
    const notificationTrigger = document.querySelector('.notifications');
    if (notificationTrigger) {
        createTopNavDropdown(
            notificationTrigger,
            'notification-dropdown',
            `
            <div class="dropdown-header">
                <h4>Notifications</h4>
                <small>3 New</small>
            </div>
            <div class="dropdown-list">
                <a href="#" class="dropdown-item">
                    <i class="fas fa-user-plus"></i>
                    <div class="item-content">
                        <p>5 new students enrolled</p>
                        <small>2 hours ago</small>
                    </div>
                </a>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-exclamation-circle"></i>
                    <div class="item-content">
                        <p>3 pending applications</p>
                        <small>5 hours ago</small>
                    </div>
                </a>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-calendar-check"></i>
                    <div class="item-content">
                        <p>Upcoming event tomorrow</p>
                        <small>1 day ago</small>
                    </div>
                </a>
            </div>
            <div class="dropdown-footer">
                <a href="messages.php">View All Notifications</a>
            </div>
            `,
            true
        );
    }

    // User Menu Dropdown
    const userMenuTrigger = document.querySelector('.user-menu');
    if (userMenuTrigger) {
        createTopNavDropdown(
            userMenuTrigger,
            'user-dropdown',
            `
            <div class="dropdown-header">
                <img src="./img/founder.jpg" alt="User Avatar">
                <div class="user-info">
                    <strong>Super Admin</strong>
                    <small>Administrator</small>
                </div>
            </div>
            <div class="dropdown-list">
                <a href="profile.php" class="dropdown-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="school_settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="#" class="dropdown-item">
                    <i class="fas fa-question-circle"></i>
                    <span>Help</span>
                </a>
                <a href="#" class="dropdown-item" id="user-logout-dropdown">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            `,
            true
        );
    }

    // Attach logout handlers after dropdowns are created
    setTimeout(() => {
        const userLogoutDropdown = document.getElementById('user-logout-dropdown');
        if (userLogoutDropdown) {
            userLogoutDropdown.addEventListener('click', function(e) {
                e.preventDefault();
                logout();
            });
        }

        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                logout();
            });
        }
    }, 100);
}

/**
 * Creates and manages a top navigation dropdown menu.
 * @param {HTMLElement} trigger - The element that triggers the dropdown.
 * @param {string} dropdownClass - CSS class to apply to the dropdown element.
 * @param {string} content - HTML string for the dropdown's content.
 * @param {boolean} [rightAlign=false] - Whether to align the dropdown to the right of the trigger.
 */
function createTopNavDropdown(trigger, dropdownClass, content, rightAlign = false) {
    if (!trigger) return;

    // Create dropdown element
    const dropdown = document.createElement('div');
    dropdown.className = `top-nav-dropdown ${dropdownClass}`;
    dropdown.innerHTML = content;
    dropdown.style.display = 'none';
    
    // Insert dropdown right after the trigger element
    trigger.parentNode.insertBefore(dropdown, trigger.nextSibling);

    let isOpen = false;

    // Add click handler to trigger
    trigger.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Close other dropdowns
        document.querySelectorAll('.top-nav-dropdown').forEach(dd => {
            if (dd !== dropdown) {
                dd.style.display = 'none';
            }
        });

        // Toggle current dropdown
        isOpen = !isOpen;
        dropdown.style.display = isOpen ? 'block' : 'none';

        if (isOpen) {
            positionTopNavDropdown(trigger, dropdown, rightAlign);
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (isOpen && !dropdown.contains(e.target) && !trigger.contains(e.target)) {
            dropdown.style.display = 'none';
            isOpen = false;
        }
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (isOpen) {
            positionTopNavDropdown(trigger, dropdown, rightAlign);
        }
    });
}

/**
 * Positions a top navigation dropdown relative to its trigger element.
 * @param {HTMLElement} trigger - The element that triggers the dropdown.
 * @param {HTMLElement} dropdown - The dropdown element to position.
 * @param {boolean} rightAlign - Whether to align the dropdown to the right.
 */
function positionTopNavDropdown(trigger, dropdown, rightAlign = false) {
    const rect = trigger.getBoundingClientRect();
    
    dropdown.style.position = 'absolute';
    dropdown.style.top = '100%';
    dropdown.style.zIndex = '1001';

    if (rightAlign) {
        dropdown.style.right = '0';
        dropdown.style.left = 'auto';
    } else {
        dropdown.style.left = '0';
        dropdown.style.right = 'auto';
    }
}

/**
 * Initializes third-party plugins like Chart.js and DataTables.
 */
function initializePlugins() {
    // Check if Chart.js is loaded before initializing charts
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    } else {
        console.warn('Chart.js not found. Charts will not be initialized.');
    }

    // Check if jQuery and DataTables are loaded before initializing tables
    if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
        initializeDataTables();
    } else {
        console.warn('jQuery or DataTables not found. DataTables will not be initialized.');
    }
}

/**
 * Initializes Chart.js charts on the dashboard.
 */
function initializeCharts() {
    // Enrollment Chart (Line Chart)
    const enrollmentCtx = document.getElementById('enrollmentChart')?.getContext('2d');
    if (enrollmentCtx) {
        new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'New Students',
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
            options: getChartOptions('new students')
        });
    }

    // Revenue Chart (Doughnut Chart)
    const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
    if (revenueCtx) {
        new Chart(revenueCtx, {
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