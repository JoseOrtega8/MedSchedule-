function formatCurrentDate(date = new Date()) {
    const formatter = new Intl.DateTimeFormat("es-MX", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
    });

    const formatted = formatter.format(date).replace(/\./g, "");
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
}

function updateDynamicDates() {
    document.querySelectorAll("[data-dynamic-date]").forEach((node) => {
        node.textContent = formatCurrentDate();
    });
}

function closeAvatarMenus() {
    document.querySelectorAll("[data-avatar-menu].open").forEach((menu) => {
        menu.classList.remove("open");
        const trigger = menu.querySelector("[data-avatar-trigger]");
        if (trigger) {
            trigger.setAttribute("aria-expanded", "false");
        }
    });
}

function handleUserMenuAction(action) {
    if (typeof window.handleUserMenuAction === "function") {
        window.handleUserMenuAction(action);
        return;
    }

    const routeMap = window.userMenuRoutes || {};
    if (routeMap[action]) {
        window.location.href = routeMap[action];
        return;
    }

    const messages = {
        profile: "Abrir perfil de usuario",
        "reset-password": "Abrir reseteo de contrasena",
        logout: "Cerrar sesion",
    };

    window.alert(messages[action] || "Accion no disponible");
}

function initAvatarMenus() {
    document.querySelectorAll("[data-avatar-menu]").forEach((menu) => {
        const trigger = menu.querySelector("[data-avatar-trigger]");
        if (!trigger || trigger.dataset.bound === "true") {
            return;
        }

        trigger.dataset.bound = "true";
        trigger.addEventListener("click", (event) => {
            event.stopPropagation();
            const willOpen = !menu.classList.contains("open");
            closeAvatarMenus();
            menu.classList.toggle("open", willOpen);
            trigger.setAttribute("aria-expanded", String(willOpen));
        });
    });

    document.querySelectorAll("[data-user-action]").forEach((button) => {
        if (button.dataset.bound === "true") {
            return;
        }

        button.dataset.bound = "true";
        button.addEventListener("click", () => {
            const { userAction } = button.dataset;
            closeAvatarMenus();
            handleUserMenuAction(userAction);
        });
    });
}

function initTopbarUi() {
    updateDynamicDates();
    initAvatarMenus();
    setInterval(updateDynamicDates, 60_000);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTopbarUi);
} else {
    initTopbarUi();
}

document.addEventListener("click", (event) => {
    if (!event.target.closest("[data-avatar-menu]")) {
        closeAvatarMenus();
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeAvatarMenus();
    }
});
