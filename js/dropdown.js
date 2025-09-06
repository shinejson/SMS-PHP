   // Wait for the DOM to be fully loaded
    document.addEventListener("DOMContentLoaded", function() {

        // Get all dropdown toggle buttons
        document.querySelectorAll(".sidebar-nav .dropdown-toggle").forEach(toggle => {
            toggle.addEventListener("click", function (e) {
                e.preventDefault();
                const parentLi = this.parentElement;

                // Close all other dropdowns
                document.querySelectorAll(".sidebar-nav .dropdown").forEach(item => {
                    // Check if the current dropdown is not the one being clicked
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