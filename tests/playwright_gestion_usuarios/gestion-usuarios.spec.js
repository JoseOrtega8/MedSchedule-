// Prueba E2E (#61 / requisito 5.7 Unidad 4): flujo de gestion de usuarios.
// Flujo: login como admin -> abrir gestion de usuarios (RBAC) -> crear usuario
//        -> verificar que aparece en la tabla -> editar su rol -> verificar el cambio.
import { test, expect } from "@playwright/test";

// Credenciales del admin sembrado por el seeder (entorno local de pruebas).
const admin_email = "admin@test.com";
const admin_password = "password";

// Carpeta donde se guardan las capturas de evidencia del flujo.
const dir_evidencia = "tests/playwright_gestion_usuarios/evidencia";

// Inicia sesion con el usuario administrador y espera el dashboard.
async function login_como_admin(page) {
    await page.goto("/login");
    await page.fill('input[name="email"]', admin_email);
    await page.fill('input[name="password"]', admin_password);
    // El boton de login no tiene atributo type; se envia el formulario con Enter.
    await page.press('input[name="password"]', "Enter");
    await page.waitForURL("**/admin/dashboard");
}

test("gestion de usuarios: crear un usuario y editar su rol", async ({ page }) => {
    // Datos unicos por corrida para evitar choque de correos.
    const marca = Date.now();
    const nombre = `QA Playwright ${marca}`;
    const correo = `qa.playwright.${marca}@medschedule.test`;

    // 1. Login como admin.
    await login_como_admin(page);

    // 2. Abrir la gestion de usuarios (panel RBAC = Roles y Permisos).
    await page.goto("/admin/rbac");
    const tabla_usuarios = page.locator("#rbacUsersTableBody");
    await expect(tabla_usuarios.locator("tr").first()).toBeVisible();

    // Evidencia: estado inicial de la tabla de usuarios.
    await page.screenshot({ path: `${dir_evidencia}/01_antes_crear.png`, fullPage: true });

    // 3. Crear un usuario nuevo desde el modal.
    await page.click("#btnOpenNewUserModal");
    await expect(page.locator("#rbacUserName")).toBeVisible();
    await page.fill("#rbacUserName", nombre);
    await page.fill("#rbacUserEmail", correo);
    await page.selectOption("#rbacUserRole", "doctor");
    await page.selectOption("#rbacUserStatus", "activo");
    await page.click('#rbacUserForm button[type="submit"]');

    // 4. Verificar que el usuario aparece en la tabla con el rol asignado.
    const fila = tabla_usuarios.locator("tr", { hasText: correo });
    await expect(fila).toBeVisible();
    await expect(fila.locator(".rbac-role-badge")).toHaveText("doctor");

    // Evidencia: usuario recien creado visible en la tabla.
    await page.screenshot({ path: `${dir_evidencia}/02_usuario_creado.png`, fullPage: true });

    // 5. Editar el usuario: cambiar su rol de doctor a admin.
    await fila.locator('[data-action="change-role"]').click();
    await expect(page.locator("#rbacRoleSelect")).toBeVisible();
    await page.selectOption("#rbacRoleSelect", "admin");
    await page.click('#rbacRoleForm button[type="submit"]');

    // 6. Verificar que el cambio se refleja en la tabla.
    await expect(fila.locator(".rbac-role-badge")).toHaveText("admin");

    // Evidencia: rol del usuario actualizado.
    await page.screenshot({ path: `${dir_evidencia}/03_rol_editado.png`, fullPage: true });
});
