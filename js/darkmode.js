// darkmode.js - Enhanced Version

document.addEventListener('DOMContentLoaded', function() {
    initializeDarkMode();
});

/**
 * Initializes dark mode functionality with improved performance and accessibility
 */
function initializeDarkMode() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    if (!darkModeToggle) {
        console.warn('Dark mode toggle not found. Dark mode functionality will not be available.');
        return;
    }

    // Constants for localStorage values
    const DARK_MODE_ENABLED = 'enabled';
    const DARK_MODE_DISABLED = 'disabled';
    
    // Media query for system preference
    const systemPreferenceQuery = window.matchMedia('(prefers-color-scheme: dark)');

    /**
     * Applies the dark or light mode to the document body
     * @param {boolean} isDark - True to enable dark mode, false for light mode
     */
    function applyMode(isDark) {
        // Use requestAnimationFrame for smoother transitions
        requestAnimationFrame(() => {
            document.body.classList.toggle('dark-mode', isDark);
            darkModeToggle.checked = isDark;
            
            // Update aria-pressed for accessibility
            darkModeToggle.setAttribute('aria-pressed', isDark);
        });
    }

    /**
     * Sets the initial dark mode state based on localStorage or system preference
     */
    function setInitialState() {
        const storedPreference = localStorage.getItem('darkMode');
        
        // Priority: localStorage > system preference > light
        const shouldBeDark = storedPreference !== null 
            ? storedPreference === DARK_MODE_ENABLED
            : systemPreferenceQuery.matches;

        applyMode(shouldBeDark);
        
        // Dispatch custom event for other components to react to theme changes
        document.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { isDarkMode: shouldBeDark } 
        }));
    }

    /**
     * Toggles the dark mode and persists the preference
     */
    function toggleMode() {
        const isDark = !document.body.classList.contains('dark-mode');
        localStorage.setItem('darkMode', isDark ? DARK_MODE_ENABLED : DARK_MODE_DISABLED);
        applyMode(isDark);
        
        // Dispatch custom event for other components
        document.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { isDarkMode: isDark } 
        }));
    }

    /**
     * Watches for changes in the system's color scheme preference
     */
    function watchSystemPreference() {
        systemPreferenceQuery.addEventListener('change', (e) => {
            // Only follow system preference if no localStorage preference exists
            if (localStorage.getItem('darkMode') === null) {
                applyMode(e.matches);
                
                // Dispatch custom event for other components
                document.dispatchEvent(new CustomEvent('themeChanged', { 
                    detail: { isDarkMode: e.matches } 
                }));
            }
        });
    }

    // Initialize the dark mode state
    setInitialState();

    // Add event listener for the toggle switch
    darkModeToggle.addEventListener('change', toggleMode);
    
    // Add keyboard support for accessibility (Space and Enter keys)
    darkModeToggle.addEventListener('keydown', (e) => {
        if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            toggleMode();
        }
    });

    // Watch for system preference changes
    watchSystemPreference();
}