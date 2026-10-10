/* =====================================================
 * CSRF token helper
 * -----------------------------------------------------
 * The token is minted by PHP and published in
 * <meta name="csrf-token"> on every page that loads
 * includes/header.php. State-changing endpoints validate
 * it with Csrf::requireValid().
 *
 * It is sent as a header rather than a form field so
 * existing payloads (JSON bodies, FormData) keep their
 * shape.
 *
 * Deliberately NOT a window.fetch monkey-patch. That would
 * cover call sites silently and make a mistake in here
 * break every request on the page. Instead each POST goes
 * through NxCsrf.init(), and EndpointCsrfTest fails CI if
 * a state-changing fetch is added without it.
 * ===================================================== */

const NxCsrf = (() => {

    function token() {

        const meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? (meta.getAttribute('content') || '') : '';

    }

    /*
     * Merge the token header into a fetch init.
     *
     *   fetch(url, NxCsrf.init({ method: 'POST', body: formData }))
     *
     * Missing token is passed through rather than thrown: the endpoint
     * answers 403 with a readable reason, which beats a silent client-side
     * exception when a page forgot to render the meta tag.
     */
    function init(options) {

        const merged = Object.assign({}, options || {});
        const headers = Object.assign({}, merged.headers || {});
        const value = token();

        if (value) {
            headers['X-CSRF-Token'] = value;
        }

        merged.headers = headers;

        return merged;

    }

    return { token, init };

})();