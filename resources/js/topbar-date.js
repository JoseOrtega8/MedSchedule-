function formatCurrentDate(date = new Date()) {
    const formatter = new Intl.DateTimeFormat("es-MX", {
        weekday: "long",
        day: "numeric",
        month: "short",
        year: "numeric",
    });

    const formatted = formatter.format(date).replace(/\./g, "");
    return formatted.charAt(0).toUpperCase() + formatted.slice(1);
}

function updateDynamicDates() {
    document.querySelectorAll("[data-dynamic-date='true']").forEach((node) => {
        node.textContent = formatCurrentDate();
    });
}

document.addEventListener("DOMContentLoaded", () => {
    updateDynamicDates();
    setInterval(updateDynamicDates, 60_000);
});
