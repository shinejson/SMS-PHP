// File: js/auth.js

document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.getElementById("login-form");
    const errorMessage = document.querySelector(".error-message");

    loginForm.addEventListener("submit", async function (e) {
        e.preventDefault();

        const username = document.getElementById("username").value.trim();
        const password = document.getElementById("password").value.trim();

        // Optional: show loading state or disable button here

        try {
            const response = await fetch("auth.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ username, password }),
            });

            const result = await response.json();

            if (result.success) {
                // Redirect to dashboard
                window.location.href = "index.php";
            } else {
                // Show error message from PHP
                errorMessage.textContent = result.message;
                errorMessage.style.display = "block";
            }
        } catch (err) {
            errorMessage.textContent = "An error occurred. Please try again.";
            errorMessage.style.display = "block";
        }
    });
});

// Add this to your users.js file
document.getElementById('userForm').addEventListener('submit', function(e) {
    // Validate username (client-side)
    const username = document.getElementById('username').value.trim();
    if (!username) {
        e.preventDefault();
        alert('Username is required');
        return;
    }
    
    // For new users, validate password
    if (document.getElementById('formAction').value === '1' && 
        !document.getElementById('password').value) {
        e.preventDefault();
        alert('Password is required for new users');
        return;
    }
});


