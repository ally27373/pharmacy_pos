console.log("Archive JS Loaded");

document.querySelectorAll(".unarchive-product").forEach(btn => {
    btn.addEventListener("click", async () => {
        if (!confirm("Confirm unarchive this product?")) return;
        const id = btn.dataset.product;
        try {
            const res = await fetch("ajax/unarchive.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ product_id: id })
            });
            const data = await res.json();
            if (data.status === "success") {
                if (typeof NxToast !== "undefined") NxToast.show("✅ Product unarchived.", "success");
                location.reload();
            } else {
                alert(data.message || "Failed to unarchive.");
            }
        } catch (e) {
            alert("Error: " + e.message);
        }
    });
});

document.querySelectorAll(".delete-archived-product").forEach(btn => {
    btn.addEventListener("click", async () => {
        if (!confirm("Confirm PERMANENTLY delete this archived product?\n\nThis cannot be undone.")) return;
        const id = btn.dataset.product;
        try {
            const res = await fetch("ajax/delete_archived.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ product_id: id })
            });
            const data = await res.json();
            if (data.status === "success") {
                if (typeof NxToast !== "undefined") NxToast.show("🗑 Archived product permanently deleted.", "success");
                location.reload();
            } else {
                alert(data.message || "Failed to delete.");
            }
        } catch (e) {
            alert("Error: " + e.message);
        }
    });
});
