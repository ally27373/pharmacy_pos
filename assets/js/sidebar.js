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