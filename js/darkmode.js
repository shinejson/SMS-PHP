
// Dark Mode Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const darkModeIcon = document.querySelector('.dark-mode-toggle .fa-moon');
    
    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    
    // Apply dark mode on page load
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        darkModeToggle.checked = true;
        updateDarkModeIcon(true);
    }
    
    // Toggle dark mode
    darkModeToggle.addEventListener('change', function() {
        if (this.checked) {
            document.body.classList.add('dark-mode');
            localStorage.setItem('darkMode', 'true');
            updateDarkModeIcon(true);
        } else {
            document.body.classList.remove('dark-mode');
            localStorage.setItem('darkMode', 'false');
            updateDarkModeIcon(false);
        }
    });
    
    // Update moon/sun icon based on dark mode state
    function updateDarkModeIcon(isDark) {
        if (isDark) {
            darkModeIcon.classList.remove('fa-moon');
            darkModeIcon.classList.add('fa-sun');
        } else {
            darkModeIcon.classList.remove('fa-sun');
            darkModeIcon.classList.add('fa-moon');
        }
    }
    
    // Also respect system preference if no manual setting
    if (!localStorage.getItem('darkMode')) {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (prefersDark) {
            document.body.classList.add('dark-mode');
            darkModeToggle.checked = true;
            updateDarkModeIcon(true);
        }
    }
});

// Listen for system preference changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
    if (!localStorage.getItem('darkMode')) {
        if (e.matches) {
            document.body.classList.add('dark-mode');
            document.getElementById('darkModeToggle').checked = true;
        } else {
            document.body.classList.remove('dark-mode');
            document.getElementById('darkModeToggle').checked = false;
        }
    }
});