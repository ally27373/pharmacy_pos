/* ======================================================
   AUTHENTICATION MODULE
   Pharmacy POS System
   Version: 1.0
   Used by:
   - login.php
   - signup.php
   - forgot_password.php
====================================================== */

    const loginForm = document.getElementById("loginForm");
    const signupForm = document.getElementById("signupForm");

document.addEventListener("DOMContentLoaded", function () {

    console.log("✅ Authentication Module Loaded");

    // ===========================
    // Detect Forms
    // ===========================


    // ===========================
    // Toggle Password
    // ===========================

    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");

    if (togglePassword && passwordInput) {

        // Guard against double-binding if this script is ever included
        // twice: two handlers would cancel each other and the toggle
        // would appear stuck or reversed.
        if (!togglePassword.dataset.toggleBound) {

            togglePassword.dataset.toggleBound = "1";

            togglePassword.addEventListener("click", function () {

            // Icon reflects the CURRENT state (state convention):
            // hidden (password) -> eye-slash; visible (text) -> eye.
            // Clicking always flips to the opposite state.
            const isHidden = passwordInput.type === "password";

            passwordInput.type = isHidden ? "text" : "password";

            this.innerHTML = isHidden
                ? '<i class="bi bi-eye"></i>'
                : '<i class="bi bi-eye-slash"></i>';

            });

        }

    }

    // ===========================
    // Login Form
    // ===========================

    if (loginForm) {

        loginForm.addEventListener("submit", function () {

            const loginButton = document.querySelector(".login-btn");

            if (loginButton) {

                loginButton.disabled = true;

                loginButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm"></span>
                    Signing In...
                `;

            }

        });

    }

    // ===========================
    // Signup Form
    // ===========================

    if (signupForm) {

        signupForm.addEventListener("submit", function () {

            const signupButton = document.querySelector(".login-btn");

            if (signupButton) {

                signupButton.disabled = true;

                signupButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm"></span>
                    Creating Account...
                `;

            }

        });

    }

});

/* ==========================================
   PASSWORD STRENGTH METER
========================================== */

if (signupForm) {

    const passwordField = document.getElementById("password");

    const strengthBar = document.getElementById("passwordStrengthBar");

    const strengthText = document.getElementById("passwordStrengthText");

    passwordField.addEventListener("input", function () {

        const password = this.value;

        let strength = 0;

        if (password.length >= 8)
            strength++;

        if (/[A-Z]/.test(password))
            strength++;

        if (/[0-9]/.test(password))
            strength++;

        if (/[^A-Za-z0-9]/.test(password))
            strength++;

        switch (strength) {

            case 0:

            case 1:

                strengthBar.style.width = "25%";

                strengthBar.className = "progress-bar bg-danger";

                strengthText.innerHTML = "Weak Password";

                break;

            case 2:

                strengthBar.style.width = "50%";

                strengthBar.className = "progress-bar bg-warning";

                strengthText.innerHTML = "Medium Password";

                break;

            case 3:

                strengthBar.style.width = "75%";

                strengthBar.className = "progress-bar bg-info";

                strengthText.innerHTML = "Good Password";

                break;

            case 4:

                strengthBar.style.width = "100%";

                strengthBar.className = "progress-bar bg-success";

                strengthText.innerHTML = "Strong Password";

                break;

        }

    });

}

/* ==========================================
   CONFIRM PASSWORD VALIDATION
========================================== */

if (signupForm) {

    const passwordField = document.getElementById("password");
    const confirmPasswordField = document.getElementById("confirmPassword");
    const passwordMessage = document.getElementById("passwordMatchMessage");

    function checkPasswordMatch() {

        if (confirmPasswordField.value === "") {

            passwordMessage.innerHTML = "";
            return;

        }

        if (passwordField.value === confirmPasswordField.value) {

            passwordMessage.innerHTML = "✅ Passwords match";
            passwordMessage.className = "text-success";

        } else {

            passwordMessage.innerHTML = "❌ Passwords do not match";
            passwordMessage.className = "text-danger";

        }

    }

    passwordField.addEventListener("keyup", checkPasswordMatch);

    confirmPasswordField.addEventListener("keyup", checkPasswordMatch);

}

/* ==========================================
   LIVE EMAIL VALIDATION
========================================== */

if (signupForm) {

    const emailField = document.getElementById("email");

    const emailMessage = document.getElementById("emailMessage");

    emailField.addEventListener("keyup", function () {

        const email = emailField.value.trim();

        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (email.length === 0) {

            emailMessage.innerHTML = "";

            emailField.classList.remove("is-valid");

            emailField.classList.remove("is-invalid");

            return;

        }

        if (regex.test(email)) {

            emailMessage.innerHTML = "✅ Valid Email Address";

            emailMessage.className = "text-success";

            emailField.classList.remove("is-invalid");

            emailField.classList.add("is-valid");

        }

        else {

            emailMessage.innerHTML = "❌ Invalid Email Address";

            emailMessage.className = "text-danger";

            emailField.classList.remove("is-valid");

            emailField.classList.add("is-invalid");

        }

    });

}

/* ==========================================
   PHILIPPINE MOBILE VALIDATION
========================================== */

if (signupForm) {

    const contactField = document.getElementById("contact");
    const contactMessage = document.getElementById("contactMessage");

    const phoneRegex = /^(09\d{9}|\+639\d{9})$/;

    contactField.addEventListener("input", function () {

        const phone = this.value.trim();

        if (phone === "") {

            contactMessage.innerHTML = "";

            contactField.classList.remove("is-valid");
            contactField.classList.remove("is-invalid");

            return;

        }

        if (phoneRegex.test(phone)) {

            contactMessage.innerHTML =
                "✅ Valid Philippine Mobile Number";

            contactMessage.className = "text-success";

            contactField.classList.remove("is-invalid");
            contactField.classList.add("is-valid");

        } else {

            contactMessage.innerHTML =
                "❌ Invalid Mobile Number";

            contactMessage.className = "text-danger";

            contactField.classList.remove("is-valid");
            contactField.classList.add("is-invalid");

        }

    });

}

/* ==========================================
   USERNAME VALIDATION
========================================== */

if (signupForm) {

    const usernameField = document.getElementById("username");

    const usernameMessage = document.getElementById("usernameMessage");

    usernameField.addEventListener("input", function () {

        const username = this.value.trim();

        const regex = /^[A-Za-z0-9_]+$/;

        if (username === "") {

            usernameMessage.innerHTML = "";

            usernameField.classList.remove("is-valid");

            usernameField.classList.remove("is-invalid");

            return;

        }

        if (username.length < 5) {

            usernameMessage.innerHTML =
                "❌ Username must be at least 5 characters.";

            usernameMessage.className = "text-danger";

            usernameField.classList.add("is-invalid");

            usernameField.classList.remove("is-valid");

            return;

        }

        if (username.length > 20) {

            usernameMessage.innerHTML =
                "❌ Maximum of 20 characters.";

            usernameMessage.className = "text-danger";

            usernameField.classList.add("is-invalid");

            usernameField.classList.remove("is-valid");

            return;

        }

        if (!regex.test(username)) {

            usernameMessage.innerHTML =
                "❌ Only letters, numbers and underscore (_) are allowed.";

            usernameMessage.className = "text-danger";

            usernameField.classList.add("is-invalid");

            usernameField.classList.remove("is-valid");

            return;

        }

        usernameMessage.innerHTML =
            "✅ Username looks good.";

        usernameMessage.className = "text-success";

        usernameField.classList.remove("is-invalid");

        usernameField.classList.add("is-valid");

    });

}