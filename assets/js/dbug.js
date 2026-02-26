(() => {
    "use strict";

    const KEY_OPEN = "dbug.open";
    const KEY_H = "dbug.height";
    const KEY_TAB = "dbug.tab";

    function qs(sel, root = document) { return root.querySelector(sel); }
    function qsa(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

    function getDock(fromEl) {
        // Prefer the closest dock, otherwise any dock on page
        return (fromEl && fromEl.closest && fromEl.closest("[data-dbug]")) || qs("[data-dbug]");
    }

    function getDrawer(dock) {
        return dock ? qs("#dbug-drawer", dock) : null;
    }

    function setOpen(dock, open, toggleBtn = null) {
        if (!dock) return;

        const drawer = getDrawer(dock);
        // Toggle button may not be inside dock; use passed btn or global fallback
        const toggle = toggleBtn || qs(".dbug-toggle");

        if (!drawer) return;

        dock.classList.toggle("is-open", open);
        drawer.setAttribute("aria-hidden", open ? "false" : "true");

        if (toggle) {
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
        }

        try { localStorage.setItem(KEY_OPEN, open ? "1" : "0"); } catch (_) {}
    }

    function activateTab(dock, name) {
        if (!dock) return;

        const tabs = qsa("[data-dbug-tab]", dock);
        const panels = qsa("[data-dbug-panel]", dock);

        tabs.forEach((t) => {
            const active = t.getAttribute("data-dbug-tab") === name;
            t.classList.toggle("is-active", active);
            t.setAttribute("aria-selected", active ? "true" : "false");
        });

        panels.forEach((p) => {
            const active = p.getAttribute("data-dbug-panel") === name;
            p.classList.toggle("is-active", active);
        });

        try { localStorage.setItem(KEY_TAB, name); } catch (_) {}
    }

    function restoreState(dock) {
        if (!dock) return;

        const drawer = getDrawer(dock);
        if (!drawer) return;

        try {
            const open = localStorage.getItem(KEY_OPEN) === "1";
            const h = localStorage.getItem(KEY_H);
            const tab = localStorage.getItem(KEY_TAB) || "dump";

            if (h) drawer.style.height = h;
            activateTab(dock, tab);
            setOpen(dock, open);
        } catch (_) {
            activateTab(dock, "dump");
            setOpen(dock, false);
        }
    }

    function updateDumpCount(dock) {
        if (!dock) return;

        const countEl = qs('[data-dbug-count="dump"]', dock);
        const target  = qs('[data-dbug-target="dump"]', dock);
        if (!countEl || !target) return;

        countEl.textContent = String(target.querySelectorAll(".dbug-block").length);
    }

    function initDock(dock) {
        if (!dock || dock.__dbugInit) return;
        dock.__dbugInit = true;

        restoreState(dock);
        updateDumpCount(dock);

        const target = qs('[data-dbug-target="dump"]', dock);
        if (target && "MutationObserver" in window) {
            const mo = new MutationObserver(() => updateDumpCount(dock));
            mo.observe(target, { childList: true, subtree: true });
        }
    }

    function boot() {
        qsa("[data-dbug]").forEach(initDock);

        // If dock is injected late
        if ("MutationObserver" in window) {
            const mo = new MutationObserver(() => {
                qsa("[data-dbug]").forEach(initDock);
            });
            mo.observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    // ---------- Events (delegated) ----------

    document.addEventListener("click", (e) => {
        // Toggle
        const toggleBtn = e.target.closest(".dbug-toggle");
        if (toggleBtn) {
            const dock = getDock(toggleBtn);
            initDock(dock);
            if (!dock) return;

            e.preventDefault();
            const open = !dock.classList.contains("is-open");
            setOpen(dock, open, toggleBtn);
            return;
        }

        // Tabs
        const tabBtn = e.target.closest("[data-dbug-tab]");
        if (tabBtn) {
            const dock = getDock(tabBtn);
            initDock(dock);
            if (!dock) return;

            const name = tabBtn.getAttribute("data-dbug-tab") || "dump";
            activateTab(dock, name);
            setOpen(dock, true);
            return;
        }

        // Actions
        const actionBtn = e.target.closest("[data-dbug-action]");
        if (actionBtn) {
            const dock = getDock(actionBtn);
            initDock(dock);
            if (!dock) return;

            const action = actionBtn.getAttribute("data-dbug-action");

            if (action === "close") return setOpen(dock, false);

            if (action === "expandAll" || action === "collapseAll") {
                const open = action === "expandAll";
                dock.querySelectorAll("details.dbug-block, details.dbug-item").forEach((d) => { d.open = open; });
                return;
            }
        }
    });

    document.addEventListener("keydown", (e) => {
        if (e.key !== "Escape") return;
        const dock = qs("[data-dbug].is-open");
        if (dock) setOpen(dock, false);
    });

    // Resize
    (() => {
        let dragging = false;
        let dock = null;
        let drawer = null;

        const move = (e) => {
            if (!dragging || !drawer) return;
            const y = e.touches ? e.touches[0].clientY : e.clientY;
            const vh = window.innerHeight || 800;
            const min = 180;
            const max = Math.floor(vh * 0.85);
            const newH = Math.max(min, Math.min(vh - y, max));
            drawer.style.height = `${newH}px`;
        };

        const up = () => {
            if (!dragging || !drawer) return;
            dragging = false;
            document.body.style.userSelect = "";
            try { localStorage.setItem(KEY_H, drawer.style.height); } catch (_) {}

            window.removeEventListener("mousemove", move);
            window.removeEventListener("mouseup", up);
            window.removeEventListener("touchmove", move);
            window.removeEventListener("touchend", up);

            dock = null;
            drawer = null;
        };

        const down = (e) => {
            const handle = e.target.closest(".dbug-resize");
            if (!handle) return;

            dock = getDock(handle);
            initDock(dock);
            drawer = getDrawer(dock);

            if (!dock || !drawer) return;

            dragging = true;
            document.body.style.userSelect = "none";
            setOpen(dock, true);

            window.addEventListener("mousemove", move);
            window.addEventListener("mouseup", up);
            window.addEventListener("touchmove", move, { passive: true });
            window.addEventListener("touchend", up);

            e.preventDefault?.();
        };

        document.addEventListener("mousedown", down);
        document.addEventListener("touchstart", down, { passive: false });
    })();

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot, { once: true });
    } else {
        boot();
    }
})();