function abrirLink(str) {
    if (!str) return;
    window.open(str, "_blank", "noopener,noreferrer");
}

const viewMap = {
    0: "login",
    1: "reset",
    2: "dashboard",
    3: "usuarios",
    4: "perfil",
    5: "about",
};

function normalizeViewId(id) {
    if (typeof id === "number") return viewMap[id];
    if (/^\d+$/.test(String(id))) return viewMap[Number(id)];
    return String(id).toLowerCase().trim();
}

function getAppRoutes() {
    const fallbackRoutes = {
        dashboard: "/dashboard",
        about: "/about",
    };

    if (
        window.medScheduleRoutes &&
        typeof window.medScheduleRoutes === "object"
    ) {
        return { ...fallbackRoutes, ...window.medScheduleRoutes };
    }

    return fallbackRoutes;
}

function show(id) {
    const viewId = normalizeViewId(id);
    const routes = getAppRoutes();

    if (routes[viewId]) {
        window.location.href = routes[viewId];
        return;
    }

    const target = document.getElementById(viewId);
    if (!target) return;

    document
        .querySelectorAll(".screen")
        .forEach((s) => s.classList.remove("active"));
    target.classList.add("active");
    document
        .querySelectorAll(".mockup-tabs button")
        .forEach((b) => b.classList.remove("active"));
    const map = {
        login: 0,
        reset: 1,
        dashboard: 2,
        usuarios: 3,
        perfil: 4,
        about: 5,
    };
    document
        .querySelectorAll(".mockup-tabs button")
        [map[viewId]]?.classList.add("active");
    window.scrollTo(0, 0);
}

function toggleSidebar(forceState) {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");

    if (!sidebar || !overlay) return;

    const nextState =
        typeof forceState === "boolean"
            ? forceState
            : !sidebar.classList.contains("show");

    sidebar.classList.toggle("show", nextState);
    overlay.classList.toggle("show", nextState);
}

function toggleSubmenu(submenuId, triggerElement) {
    const submenu = document.getElementById(submenuId);
    if (!submenu) return;

    const willExpand = !submenu.classList.contains("expanded");

    document.querySelectorAll(".submenu").forEach((menu) => {
        menu.classList.remove("expanded");
    });

    document.querySelectorAll(".chevron").forEach((icon) => {
        icon.classList.remove("rotated");
    });

    if (willExpand) {
        submenu.classList.add("expanded");
        triggerElement?.querySelector(".chevron")?.classList.add("rotated");
    }
}

function handleResize() {
    if (window.innerWidth > 768) {
        toggleSidebar(false);
    }
}

function toggleAvatarMenu(event) {
    event?.stopPropagation();

    const avatarMenu = document.getElementById("avatarMenu");
    if (!avatarMenu) return;

    const willOpen = !avatarMenu.classList.contains("open");
    closeAvatarMenu();

    if (willOpen) {
        avatarMenu.classList.add("open");
    }
}

function closeAvatarMenu() {
    document.querySelectorAll(".avatar-menu.open").forEach((menu) => {
        menu.classList.remove("open");
    });
}

function initRevealOnScroll() {
    const sections = document.querySelectorAll(".reveal-section");
    if (!sections.length) return;

    if (!("IntersectionObserver" in window)) {
        sections.forEach((section) => section.classList.add("is-visible"));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            });
        },
        {
            threshold: 0.18,
            rootMargin: "0px 0px -40px 0px",
        }
    );

    sections.forEach((section, index) => {
        section.style.transitionDelay = `${Math.min(index * 60, 220)}ms`;
        observer.observe(section);
    });
}

document.addEventListener("click", (event) => {
    if (!event.target.closest(".avatar-menu")) {
        closeAvatarMenu();
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        closeAvatarMenu();
    }
});

document.addEventListener("DOMContentLoaded", () => {
    initRevealOnScroll();
});

window.addEventListener("resize", handleResize);

window.abrirLink = abrirLink;
window.show = show;
window.toggleSidebar = toggleSidebar;
window.toggleSubmenu = toggleSubmenu;
window.toggleAvatarMenu = toggleAvatarMenu;
window.closeAvatarMenu = closeAvatarMenu;
