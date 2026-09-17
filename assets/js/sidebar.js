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

    const sidebar = document.getElementById("sidebar");
    const toggleButton = document.getElementById("sidebar-toggle");
    const backdrop = document.getElementById("sidebar-backdrop");

    if (sidebar && toggleButton && backdrop) {

        function openDrawer() {
            sidebar.classList.add("mobile-open");
            backdrop.classList.add("show");
            toggleButton.setAttribute("aria-expanded", "true");
            document.body.classList.add("sidebar-drawer-open");
        }

        function closeDrawer() {
            sidebar.classList.remove("mobile-open");
            backdrop.classList.remove("show");
            toggleButton.setAttribute("aria-expanded", "false");
            document.body.classList.remove("sidebar-drawer-open");
        }

        toggleButton.addEventListener("click", (e) => {
            e.stopPropagation();
            sidebar.classList.contains("mobile-open") ? closeDrawer() : openDrawer();
        });

        backdrop.addEventListener("click", closeDrawer);

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") closeDrawer();
        });

        sidebar.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                const isAccordionOnly = link.classList.contains("submenu-toggle") && link.getAttribute("href") === "#";
                if (!isAccordionOnly) closeDrawer();
            });
        });

        window.matchMedia("(min-width: 992px)").addEventListener("change", (e) => {
            if (e.matches) closeDrawer();
        });
    }

});