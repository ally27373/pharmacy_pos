document.addEventListener("DOMContentLoaded", () => {

    document.querySelectorAll(".submenu-toggle").forEach(button => {

        button.addEventListener("click", function(e){

            const icon = e.target.closest(".dropdown-icon");

if (icon) {
    e.preventDefault();

    const submenu = this.nextElementSibling;

    submenu.classList.toggle("show");

    icon.classList.toggle("rotate");
}
            e.stopPropagation();

            const menuItem = this.closest(".menu-item");

            menuItem.querySelector(".submenu")
                    .classList.toggle("show");

            this.querySelector(".dropdown-icon")
                .classList.toggle("rotate");

        });

    });

});

// ================= UI FIX PACK - Phase 2 (mobile off-canvas toggle) =================
// Additive only: existing dropdown logic above is untouched.
(function () {
    function closeSidebar() {
        document.body.classList.remove("sidebar-open");
        var btn = document.getElementById("navbar-hamburger");
        if (btn) btn.setAttribute("aria-expanded", "false");
    }
    document.addEventListener("DOMContentLoaded", () => {
        var btn = document.getElementById("navbar-hamburger");
        var backdrop = document.getElementById("sidebar-backdrop");
        if (btn) {
            btn.addEventListener("click", (e) => {
                e.stopPropagation();
                var open = document.body.classList.toggle("sidebar-open");
                btn.setAttribute("aria-expanded", open ? "true" : "false");
            });
        }
        if (backdrop) backdrop.addEventListener("click", closeSidebar);
        // Close after following a real link (but NOT when merely expanding a submenu).
        document.querySelectorAll(".sidebar a:not(.submenu-toggle)").forEach((a) => {
            a.addEventListener("click", () => {
                if (window.innerWidth <= 900) closeSidebar();
            });
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") closeSidebar();
        });
    });
})();