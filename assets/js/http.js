/* =====================================================
 * Response reader
 * -----------------------------------------------------
 * Every fetch handler in this app began with
 *
 *     const data = await response.json();
 *
 * and wrapped failures in one catch. The problem is that
 * response.json() does not fail on an HTTP error, only on a
 * body that is not JSON -- which is exactly what a PHP fatal
 * produces, because display_errors is off and the 500 page is
 * HTML. Those all collapsed into the same message:
 *
 *     "Unable to connect to the server"
 *
 * which reads as a network problem and sent the investigation
 * after the connectivity instead of the server.
 *
 * This reads the body first, so a non-JSON response can say what
 * it actually was, and keeps the snippet short enough for a dialog.
 * ===================================================== */

const NxHttp = (() => {

    const SNIPPET = 200;

    function describe(raw) {
        const text = (raw || '')
            .replace(/\s+/g, ' ')
            .trim();

        if (!text) return 'The response body was empty.';

        const clipped = text.length > SNIPPET
            ? text.slice(0, SNIPPET) + '...'
            : text;

        return 'The server said: ' + clipped;
    }

    /**
     * Parse a JSON response, raising an error that names the real cause.
     */
    async function json(response) {
        const raw = await response.text();

        try {
            return JSON.parse(raw);
        } catch (error) {
            throw new Error(
                'HTTP ' + response.status + ' - the response was not JSON. '
                + describe(raw)
            );
        }
    }

    return { json, describe };

})();