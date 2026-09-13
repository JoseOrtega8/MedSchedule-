---
name: "generar-casos-prueba"
description: "A partir de una spec de MedSchedule, deriva la matriz de casos de prueba y emite el esqueleto de test PHPUnit o Playwright correspondiente, siguiendo el estilo de tests/playwright_gestion_usuarios/gestion-usuarios.spec.js. Usar después de tener una spec.md aprobada (de /speckit-specify o de generar-spec-modulo) y antes de implementar."
argument-hint: "Ruta al spec.md del módulo (por ejemplo: specs/003-recordatorios-citas/spec.md)"
compatibility: "Requiere la estructura de spec-kit instalada en .specify/"
metadata:
  author: "equipo-medschedule"
  source: "spec-driven-development"
user-invocable: true
disable-model-invocation: false
---

## Cuándo usar esta skill

Después de que una spec (`spec.md`) está escrita y antes de implementar el módulo. Convierte los
criterios de aceptación de la spec en una matriz de casos de prueba trazable y en esqueletos de
test ejecutables. Complementa a `/speckit-tasks` (que genera `tasks.md` con tareas de
implementación); esta skill genera la contraparte de pruebas, alineada a los mismos User Stories
(P1, P2, P3...) de la spec.

## Entradas que necesita

- El `spec.md` del módulo (ruta pasada como argumento), con sus User Stories, Acceptance
  Scenarios (Given/When/Then) y Functional Requirements (`FR-00X`).
- `.specify/memory/constitution.md` (principios de estilo y seguridad a respetar en los tests).
- Los roles del sistema: `admin`, `doctor`, `patient` (minúscula).
- `tests/playwright_gestion_usuarios/gestion-usuarios.spec.js` como referencia de estilo E2E.
- Un ejemplo de PHPUnit correcto con Spatie: `tests/Feature/ActivityLogControllerTest.php`
  (usa `RefreshDatabase` + `Role::firstOrCreate(...)` + `$user->assignRole($role)`).

## Pasos

1. **Leer la spec** y extraer, para cada User Story, sus Acceptance Scenarios
   (Given/When/Then), los `FR-00X` que la sustentan y los Edge Cases declarados.

2. **Derivar la matriz de casos de prueba** en una tabla Markdown con estas columnas exactas:

   | Campo | Descripción |
   |---|---|
   | `id` | `TC-<NNN>` secuencial |
   | `modulo` | Nombre del módulo/feature |
   | `precondiciones` | Estado previo requerido (rol autenticado, datos existentes, etc.) |
   | `pasos` | Pasos numerados de la acción |
   | `datos` | Datos de entrada usados (valores concretos o generados, nunca datos reales de producción) |
   | `resultado_esperado` | Resultado verificable, tomado del Acceptance Scenario o del FR |
   | `tipo` | `unitario` / `feature` / `e2e` |
   | `prioridad` | Heredada de la prioridad de la User Story (P1/P2/P3) |

   Reglas de derivación:
   - Cada Acceptance Scenario (Given/When/Then) produce al menos un caso `feature` o `e2e`.
   - Cada `FR-00X` que valide una regla de negocio pura (sin HTTP, sin BD) produce un caso
     `unitario`.
   - Cada Edge Case de la spec produce al menos un caso de prueba adicional, con
     `resultado_esperado` explícito de rechazo o manejo controlado (nunca "no debe fallar" sin
     precisar el código de estado o mensaje).
   - Si un caso depende del rol, `precondiciones` declara el rol exacto en minúscula
     (`admin`, `doctor` o `patient`) y cómo se asigna (vía Spatie).

3. **Emitir el esqueleto de test correspondiente**, eligiendo el tipo según la columna `tipo`
   de cada caso:

   - **PHPUnit feature** (`tests/Feature/<Modulo>Test.php`): sigue el estilo de
     `tests/Feature/ActivityLogControllerTest.php`:
     - `use Illuminate\Foundation\Testing\RefreshDatabase;` y `use RefreshDatabase;` en la
       clase — **obligatorio** en todo test que toque la base de datos.
     - `use Spatie\Permission\Models\Role;` y helpers privados tipo `makeAdmin()`,
       `makeDoctor()`, `makePatient()` que hagan `Role::firstOrCreate(['name' => 'admin',
       'guard_name' => 'web'])` y `$user->assignRole($role)`.
     - Toda ruta protegida se prueba con `$this->actingAs($usuario)->get(...)` /
       `->post(...)`, **nunca** con `withSession(['mock_current_user' => [...]])`. Esa forma
       usa el middleware legado `EnsureAdminRole` y no ejercita la autorización real de Spatie:
       es la causa raíz de 10 de los 15 fallos actuales del suite. Si la ruta bajo prueba
       todavía está detrás de `EnsureAdminRole` exclusivamente, señalarlo como hallazgo en el
       caso de prueba en vez de replicar el patrón `mock_current_user`.
     - Nombres de método de test descriptivos en snake_case:
       `test_<accion>_<condicion>_<resultado>()`.

   - **PHPUnit unitario** (`tests/Unit/<Clase>Test.php`): sin `RefreshDatabase` salvo que la
     unidad bajo prueba lo requiera; prueba una sola responsabilidad (método de modelo, regla de
     negocio) sin HTTP.

   - **Playwright E2E** (`tests/playwright_gestion_usuarios/<nombre-flujo>.spec.js` o carpeta
     equivalente para el módulo nuevo): sigue el estilo de `gestion-usuarios.spec.js`:
     - Comentario de cabecera con el número de issue/requisito y el flujo cubierto.
     - `import { test, expect } from "@playwright/test";`.
     - Credenciales de prueba como constantes con comentario indicando que vienen del seeder de
       entorno local (nunca credenciales reales).
     - Una función async `login_como_<rol>(page)` reutilizable si el flujo requiere login.
     - Pasos numerados en comentarios (`// 1. ...`, `// 2. ...`) que reflejen el Given/When/Then
       de la spec.
     - Capturas de evidencia con `page.screenshot(...)` en las transiciones clave, guardadas
       bajo una carpeta `evidencia/` del módulo (ya cubierta por `.gitignore`).

4. **Verificar antes de entregar**:
   - Todo test de ruta protegida usa `actingAs()` con rol asignado vía Spatie, no
     `mock_current_user`.
   - Todo test que toca base de datos usa `RefreshDatabase`.
   - Los valores de rol usados son exactamente `admin`, `doctor`, `patient` en minúscula.
   - La matriz de casos cubre las tres prioridades presentes en la spec (si existen) y todos
     los Edge Cases declarados.
   - No se incluyen credenciales, tokens ni datos reales de pacientes; solo datos sintéticos o
     generados (`Date.now()`, factories).
   - Comentarios y nombres de test en español/snake_case donde el lenguaje del archivo lo
     permita (PHP: comentarios en español, métodos en snake_case; JS de Playwright: comentarios
     en español, siguiendo el archivo de referencia).

## Formato exacto de salida

Dos artefactos por invocación:

1. Una tabla Markdown de la matriz de casos (columnas `id, modulo, precondiciones, pasos, datos,
   resultado_esperado, tipo, prioridad`), lista para pegar en el plan de pruebas
   (`02-plan-de-pruebas.md`) o en el PR.
2. El o los archivos de esqueleto de test (`tests/Feature/...`, `tests/Unit/...` o
   `tests/playwright_gestion_usuarios/...`) con los casos convertidos en funciones de test
   (`test_...` en PHPUnit, `test(...)` en Playwright), con el cuerpo de aserciones marcado con
   `// TODO:` donde falte un detalle que solo la implementación real puede resolver.

## Errores que evita

- **Tests de rutas protegidas que nunca autentican de verdad**: usar
  `withSession(['mock_current_user' => [...]])` en vez de `actingAs($usuario)` con rol Spatie.
  Este es el patrón responsable de 10 de los 15 fallos actuales del suite — esta skill lo
  prohíbe explícitamente en el paso 3.
- **Tests de base de datos sin `RefreshDatabase`**, que dejan estado sucio entre corridas.
- **Comparaciones de rol con mayúscula** (`Admin`, `DOCTOR`) en vez de minúscula.
- **Casos de prueba no trazables a la spec**: todo `TC-00X` debe poder mapearse a un Acceptance
  Scenario, un `FR-00X` o un Edge Case concreto; no se generan casos genéricos sin origen.
- **Credenciales o datos de pacientes reales** en los esqueletos de test.
