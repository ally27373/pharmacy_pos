/* ==========================================================
   NX PHARMACY POS — SWEETALERT2 WRAPPER
   Promise-based confirm()/alert() replacements.

   Why a wrapper instead of calling Swal directly:
   - Every call site reads the same: `if (!await NxSwal.confirm(...)) return;`
   - Falls back to the native confirm()/alert() if the CDN is blocked or
     slow, so a network hiccup can never leave a destructive action
     silently unguarded.
   - Central place to keep the palette consistent with the rest of the UI.
   ========================================================== */
(function () {
    "use strict";

    var THEME = {
        primary: "#0F8A6B",
        danger: "#dc3545",
        warning: "#f0ad4e",
        info: "#1685c5",
        success: "#118d6d",
        dark: "#1a2230",
    };

    function hasSwal() {
        return typeof window.Swal !== "undefined";
    }

    /** Green/white/grey palette + rounded buttons to match the app shell. */
    function baseSwalOptions() {
        return {
            customClass: {
                popup: "nx-swal-popup",
                confirmButton: "nx-swal-confirm",
                cancelButton: "nx-swal-cancel",
                title: "nx-swal-title",
                htmlContainer: "nx-swal-body",
            },
            buttonsStyling: false,
            reverseButtons: true,
        };
    }

    function merge(base, extra) {
        var out = {};
        Object.keys(base).forEach(function (k) { out[k] = base[k]; });
        if (extra) {
            Object.keys(extra).forEach(function (k) { out[k] = extra[k]; });
        }
        return out;
    }

    /**
     * Confirmation dialog. Resolves true when confirmed, false otherwise.
     *
     * options:
     *   icon       - "success" | "warning" | "error" | "info" | "question"
     *   confirmText / cancelText
     *   danger     - true renders the confirm button red (destructive actions)
     */
    function confirm(title, text, options) {
        options = options || {};

        if (!hasSwal()) {
            return Promise.resolve(window.confirm(title + (text ? "\n\n" + text : "")));
        }

        var isDanger = !!options.danger;
        var icon = options.icon || (isDanger ? "warning" : "question");

        return window.Swal.fire(
            merge(baseSwalOptions(), {
                title: title || "Are you sure?",
                text: text || "",
                icon: icon,
                showCancelButton: true,
                confirmButtonText: options.confirmText || "Yes, continue",
                cancelButtonText: options.cancelText || "Cancel",
                confirmButtonColor: isDanger ? THEME.danger : THEME.primary,
                cancelButtonColor: "#8a949d",
                focusCancel: isDanger,
                returnFocus: true,
            })
        ).then(function (result) {
            return result.isConfirmed === true;
        });
    }

    /** Fire-and-forget informational dialog. Resolves when dismissed. */
    function message(title, text, icon, options) {
        options = options || {};

        if (!hasSwal()) {
            if (window.alert) window.alert(title + (text ? "\n\n" + text : ""));
            return Promise.resolve();
        }

        var color = {
            success: THEME.success,
            error: THEME.danger,
            warning: THEME.warning,
            info: THEME.info,
        }[icon] || THEME.info;

        return window.Swal.fire(
            merge(baseSwalOptions(), {
                title: title || "",
                text: text || "",
                icon: icon || "info",
                showCancelButton: false,
                confirmButtonText: options.confirmText || "OK",
                confirmButtonColor: color,
            })
        ).then(function () {});
    }

    function success(title, text) { return message(title, text, "success"); }
    function error(title, text) { return message(title, text, "error"); }
    function warning(title, text) { return message(title, text, "warning"); }
    function info(title, text) { return message(title, text, "info"); }

    window.NxSwal = {
        confirm: confirm,
        message: message,
        success: success,
        error: error,
        warning: warning,
        info: info,
        ready: hasSwal,
    };
})();