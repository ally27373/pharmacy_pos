/* ==========================================================
   NX PHARMACY POS - SHARED TOAST NOTIFICATIONS
   No dependencies. Safe to load on any page (footer.php).
   - NxToast.show(message, type)   : toast right now
   - NxToast.flash(message, type)  : toast after reload/redirect
   Types: "success" (default), "danger", "info".
   ========================================================== */
(function () {
    var CONTAINER_ID = "nx-toast-container";
    var FLASH_KEY = "nx_flash_toast";
    var AUTO_DISMISS_MS = 6000;

    function container() {
        if (!document.body) return null;
        var c = document.getElementById(CONTAINER_ID);
        if (!c) {
            c = document.createElement("div");
            c.id = CONTAINER_ID;
            c.className = "nx-toast-container";
            c.setAttribute("aria-live", "polite");
            document.body.appendChild(c);
        }
        return c;
    }

    function show(message, type) {
        var box = container();
        if (!box) return;
        type = type === "danger" ? "danger" : (type === "info" ? "info" : "success");

        var el = document.createElement("div");
        el.className = "nx-toast nx-toast-" + type;

        var msg = document.createElement("div");
        msg.className = "nx-toast-message";
        msg.textContent = String(message == null ? "" : message);

        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "nx-toast-close";
        btn.setAttribute("aria-label", "Dismiss notification");
        btn.textContent = "×";
        btn.addEventListener("click", function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        });

        el.appendChild(msg);
        el.appendChild(btn);
        box.appendChild(el);

        setTimeout(function () {
            if (el.parentNode) el.classList.add("nx-toast-hide");
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 400);
        }, AUTO_DISMISS_MS);
    }

    function flash(message, type) {
        try {
            sessionStorage.setItem(FLASH_KEY, JSON.stringify({
                message: String(message == null ? "" : message),
                type: type || "success"
            }));
        } catch (e) {
            show(message, type);
        }
    }

    function renderFlash() {
        var raw = null;
        try {
            raw = sessionStorage.getItem(FLASH_KEY);
            sessionStorage.removeItem(FLASH_KEY);
        } catch (e) {
            return;
        }
        if (!raw) return;
        try {
            var data = JSON.parse(raw);
            if (data && data.message) show(data.message, data.type);
        } catch (e) {}
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", renderFlash);
    } else {
        renderFlash();
    }

    window.NxToast = { show: show, flash: flash, renderFlash: renderFlash };
})();
