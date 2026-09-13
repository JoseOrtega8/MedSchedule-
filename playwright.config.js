// Configuracion de Playwright para las pruebas E2E de MedSchedule.
// La app debe estar corriendo en APP_URL (por defecto php artisan serve en :8000).
import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
    testDir: "./tests/playwright_gestion_usuarios",
    // Tiempo maximo por prueba.
    timeout: 30000,
    // Sin reintentos: la prueba debe pasar de forma determinista.
    retries: 0,
    reporter: [
        ["list"],
        ["html", { outputFolder: "tests/playwright-report", open: "never" }],
    ],
    use: {
        baseURL: process.env.APP_URL || "http://127.0.0.1:8000",
        headless: true,
        // Evidencia automatica de la corrida.
        screenshot: "on",
        video: "retain-on-failure",
        trace: "on-first-retry",
    },
    projects: [
        {
            name: "chromium",
            use: { ...devices["Desktop Chrome"] },
        },
    ],
});
