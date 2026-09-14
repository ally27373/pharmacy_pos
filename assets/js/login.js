

document.addEventListener("DOMContentLoaded", function () {

    // ===========================
    // Elements
    // ===========================

    const loginForm = document.getElementById("loginForm");

    const password = document.getElementById("password");

    const togglePassword = document.getElementById("togglePassword");

    const loginButton = document.querySelector(".login-btn");

    // ===========================
    // Show / Hide Password
    // ===========================

    togglePassword.addEventListener("click", function () {

        const type = password.getAttribute("type") === "password"
            ? "text"
            : "password";

        password.setAttribute("type", type);

        this.innerHTML = type === "password"
            ? '<i class="bi bi-eye"></i>'
            : '<i class="bi bi-eye-slash"></i>';

    });

    // ===========================
    // Submit Form
    // ===========================

    loginForm.addEventListener("submit", function () {

        loginButton.disabled = true;

        loginButton.innerHTML = `
            <span class="spinner-border spinner-border-sm"></span>
            Signing In...
        `;

    });

});