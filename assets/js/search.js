/* =====================================================
 * Shared search behaviour
 * -----------------------------------------------------
 * The list behind a search box is usually paginated, so a
 * filter that only walks the rows already on screen
 * reports "no results" for something that exists three
 * pages away. These helpers search the whole table by
 * submitting the form instead.
 * ===================================================== */

const NxSearch = (() => {

    /*
     * Submits the search input's form once typing pauses.
     *
     * A new search always restarts at page 1: without this,
     * searching from page 4 can land on a page that no longer
     * exists for the narrower result set and the list looks
     * empty even though matches were found.
     *
     * Enter submits straight away rather than waiting out the
     * pause, so the form still works without JavaScript timing.
     */
    function submitOnType(input, options = {}) {

        if (!input) return;

        /*
         * Page scripts may bind a specific input themselves while the
         * auto-wiring below catches the same one. Binding twice would
         * submit the form twice per keystroke.
         */
        if (input.dataset.nxSearchBound === "1") return;

        const form = input.form || input.closest("form");

        if (!form) return;

        input.dataset.nxSearchBound = "1";

        const delay = typeof options.delay === "number"
            ? options.delay
            : 400;

        let timer = null;

        const submit = () => {

            const pageField = form.querySelector(
                'input[type="hidden"][name$="_page"]'
            );

            if (pageField) pageField.value = "1";

            form.submit();

        };

        input.addEventListener("input", () => {

            clearTimeout(timer);
            timer = setTimeout(submit, delay);

        });

        input.addEventListener("keydown", (event) => {

            if (event.key !== "Enter") return;

            event.preventDefault();
            clearTimeout(timer);
            submit();

        });

    }

    /*
     * "12 of 40" once a filter is applied, "40" when the list is
     * unfiltered. `total` may be omitted when a page has no server
     * total to report.
     */
    function renderCount(element, visible, total) {

        if (!element) return;

        if (visible === 0) {
            element.textContent = "No results";
            element.classList.add("is-empty");
            return;
        }

        element.classList.remove("is-empty");

        const shown = Number(visible).toLocaleString("en-PH");

        if (typeof total !== "number" || total === visible) {
            element.textContent = shown;
            return;
        }

        element.textContent = shown + " of " + Number(total).toLocaleString("en-PH");

    }

    return { submitOnType, renderCount };

})();

/*
 * Every remaining search box that posts a form is wired here so they all
 * behave the same: search as you type, Enter submits immediately, and the
 * visible Search button stays as an explicit alternative. Inputs driven by
 * a page's own AJAX (products, reports) have no form and are skipped.
 */
document.addEventListener("DOMContentLoaded", function () {

    document
        .querySelectorAll('.nx-search__input[name="search"]')
        .forEach(function (input) {
            NxSearch.submitOnType(input);
        });

});