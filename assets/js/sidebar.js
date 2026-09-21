document.addEventListener("DOMContentLoaded", () => {

    // The mobile drawer's own content (starting with the logo) sits
    // flush at the top of .sidebar, but navbar-custom is deliberately
    // painted above it (see the z-index scale note in style.css) so
    // the hamburger toggle stays clickable while the drawer is open.
    // Push the drawer's content down by the navbar's real rendered
    // height so it starts below the bar instead of being hidden
    // behind it. Measured (rather than hardcoded) because the
    // navbar's height changes across breakpoints (e.g. the 480px
    // padding reduction in navbar.css).
    const navbarEl = document.querySelector(".navbar-custom");

    if (navbarEl) {

        const syncNavbarHeight = () => {
            document.documentElement.style.setProperty(
                "--navbar-height",
                navbarEl.offsetHeight + "px"
            );
        };

        syncNavbarHeight();
        window.addEventListener("resize", syncNavbarHeight);

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(syncNavbarHeight);
        }
    }

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