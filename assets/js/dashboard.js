console.log("Dashboard.js loaded successfully!");

function toggleChartMenu(button)
{
    const menu =
        button.nextElementSibling;

    document
        .querySelectorAll(
            ".chart-menu-dropdown.show"
        )
        .forEach(
            dropdown => {

                if (dropdown !== menu) {

                    dropdown.classList.remove("show");

                }

            }
        );

    menu.classList.toggle("show");
}


function closeAllChartMenus()
{
    document
        .querySelectorAll(
            ".chart-menu-dropdown.show"
        )
        .forEach(
            menu => {

                menu.classList.remove("show");

            }
        );
}


document.addEventListener(
    "click",
    function(event)
    {

        if (
            !event.target.closest(
                ".chart-menu"
            )
        ) {

            closeAllChartMenus();

        }

    }
);


// ================================
// Best Seller Controls
// ================================

function updateBestSeller() {

    const month =
        document.getElementById("bestMonth").value;

    const year =
        document.getElementById("bestYear").value;

    const url =
        new URL(window.location.href);

    url.searchParams.set("best_month", month);
    url.searchParams.set("best_year", year);

    window.location.href = url;
}

function showBestProducts() {

    const url =
        new URL(window.location.href);

    url.searchParams.set("mode", "best");

    window.location.href = url;
}

function showLeastProducts() {

    const url =
        new URL(window.location.href);

    url.searchParams.set("mode", "least");

    window.location.href = url;
}

function setTopProducts(limit) {

    const url =
        new URL(window.location.href);

    url.searchParams.set("best_limit", limit);

    window.location.href = url;
}

function exportBestSellerCSV() {

    window.location.href =
        "/app/export/export_best_seller.php";
}

function updateFastMoving(){

    const month =
        document.getElementById("fastMonth").value;

    const year =
        document.getElementById("fastYear").value;

    const url =
        new URL(window.location.href);

    url.searchParams.set("fast_month", month);

    url.searchParams.set("fast_year", year);

    window.location.href = url.toString();

}

function setFastLimit(limit)
{
    const month =
        document.getElementById("fastMonth").value;

    const year =
        document.getElementById("fastYear").value;

    const url =
        new URL(window.location.href);

    url.searchParams.set(
        "fast_month",
        month
    );

    url.searchParams.set(
        "fast_year",
        year
    );

    url.searchParams.set(
        "fast_limit",
        limit
    );

    window.location.href = url;
}

