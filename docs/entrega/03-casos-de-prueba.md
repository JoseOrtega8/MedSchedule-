# 03 — Casos de prueba

Punto 3 de la rúbrica: "casos de prueba". Este documento es la matriz formal de los casos de
prueba automatizados que ya existen en la rama `feat/unidad-docs-sdd`, más los casos propuestos
para los flujos E2E que todavía no tienen cobertura, y la trazabilidad entre módulos, casos y el
requisito o especificación que los origina.

## 0. Método de verificación

Cada caso de la sección 3 corresponde a un método real de un archivo real de `tests/`, contado con
`grep -cE 'public function test|#\[Test\]'` sobre cada uno de los 20 archivos de clase de
`tests/Unit/` y `tests/Feature/`, más el único spec de `tests/playwright_gestion_usuarios/`. La
suma de esa cuenta es **79 métodos de PHPUnit**, cifra que coincide exactamente con la evidencia
real de ejecución de `docs/entrega/evidencia/phpunit-baseline.txt`: **64 pasan y 15 fallan**
(`Tests: 15 failed, 64 passed (149 assertions)`, línea final de esa evidencia). El estado de cada
caso de esta matriz se tomó de esa misma evidencia, no se infiere ni se supone: ningún caso que
falla en `phpunit-baseline.txt` aparece aquí como aprobado. Los 15 fallos se etiquetan con su grupo
de la taxonomía verificada en `docs/entrega/02-plan-de-pruebas.md` §6.4 (Grupo A, B o C). Ningún defecto se corrige aquí — regla
vinculante de esta entrega.

Los casos propuestos de la sección 4 se derivan de `specs/001-pruebas-e2e/spec.md`, la
especificación piloto ya aprobada que cubre exactamente los cuatro flujos sin prueba E2E: control
de acceso por rol, agenda del doctor, agendado y cancelación de cita del paciente, y CRUD de
especialidades. Ninguno de esos casos tiene código de prueba todavía: se marcan con estado
"propuesto, unidad siguiente" y no se implementan en esta entrega.

## 1. Convenciones de la matriz

- **Identificador**: `CP-XXX`, correlativo en todo el documento. Los casos existentes van de
  `CP-001` a `CP-038` (37 filas de PHPUnit + 1 fila de Playwright); los propuestos van de `CP-039`
  a `CP-058`.
- **Tipo**: `unitario` (sin HTTP ni base de datos real de negocio), `feature` (ruta HTTP completa
  con PHPUnit) o `E2E` (navegador real con Playwright).
- **Agrupación**: 79 métodos en filas individuales harían la tabla ilegible. Cuando varios métodos
  de la misma clase comparten precondiciones (mismo `setUp()`, mismo helper de autenticación, mismo
  endpoint) se agrupan en una sola fila. La fila declara explícitamente **cuántos métodos cubre** y
  los nombra en la columna "Datos de prueba / variantes"; ninguna fila agrupada omite un método sin
  decirlo. La suma de "métodos cubiertos" de todas las filas de la sección 3.1 a 3.11 es
  exactamente 79.
- **Estado**: `Aprobado` (PASS en la evidencia), `Fallido — Grupo A/B/C` (FAIL en la evidencia, con
  su grupo de causa raíz), `Sin cobertura (stub vacío)` (para `RegistrationTest`), o `Propuesto,
  unidad siguiente` (sección 4, sin código todavía).
- **Datos de prueba**: valores ficticios generados por `Faker` (vía `User::factory()`) o literales
  ya presentes en el propio código de prueba (por ejemplo `'Cardiologia'`, `'Miguel'`). Ninguna
  credencial real. Donde el flujo requiere el usuario fijo del seeder de demostración
  (`database/seeders/DatabaseSeeder.php`), se referencia por su correo (`admin@test.com`) sin
  repetir el valor de la contraseña — documentado ya en `02-plan-de-pruebas.md` §7 como marcador de
  entorno de prueba, no como una clave sensible, y no se reproduce aquí por prudencia adicional al
  ser este repositorio público.

## 2. Leyenda de grupos de fallo (referencia rápida)

| Grupo | Causa raíz verificada | Archivos afectados |
|---|---|---|
| A | Tests obsoletos que nunca autentican (`actingAs()` ausente; algunos usan el mecanismo legado `withSession(['mock_current_user' => ...])` contra rutas ya protegidas por Spatie) | `AdminRbacAccessTest`, `AppointmentControllerTest`, `DoctorProfileControllerTest`, `ScheduleControllerTest`, `SpecialtyControllerTest` |
| B | Drift real entre código y test: `/dashboard` siempre redirige (302) en vez de responder 200 para `admin`/`patient`, y manda al doctor a `doctor.dashboard` en vez de `doctor.agenda` | `DashboardControllerTest` |
| C | Defecto funcional probable en la integración con Google Calendar: el mock de `createEvent`/`deleteEvent` no se invoca como el test espera | `GoogleCalendarControllerTest` |

## 3. Matriz de casos existentes (79 métodos de PHPUnit + 1 spec de Playwright)

### 3.1 Autenticación (scaffold Laravel Breeze)

Precondición común a toda la subsección: `RefreshDatabase` reinicia la base antes de cada método;
ninguno depende de seeds externos.

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-010 | Autenticación | feature | Base limpia (`RefreshDatabase`); usuario creado con `User::factory()` | GET a `/login`; POST a `/login` con credenciales correctas del usuario recién creado | Cubre 2 métodos: `test_login_screen_can_be_rendered`, `test_users_can_authenticate_using_the_login_screen` | La pantalla de login renderiza; con credenciales correctas el usuario queda autenticado | Aprobado | `tests/Feature/Auth/AuthenticationTest.php` |
| CP-011 | Autenticación | feature | Base limpia; usuario creado con `User::factory()`, ya autenticado para el caso de logout | POST a `/login` con contraseña incorrecta; POST a `/logout` sobre un usuario autenticado | Cubre 2 métodos: `test_users_can_not_authenticate_with_invalid_password`, `test_users_can_logout` | Contraseña incorrecta no autentica (permanece sin sesión); logout cierra la sesión y redirige | Aprobado | `tests/Feature/Auth/AuthenticationTest.php` |
| CP-012 | Autenticación — verificación de correo | feature | Base limpia; usuario con `email_verified_at = null` | GET a la pantalla de verificación; POST al enlace firmado de verificación con hash válido y con hash inválido | Cubre 3 métodos: `test_email_verification_screen_can_be_rendered`, `test_email_can_be_verified`, `test_email_is_not_verified_with_invalid_hash` | La pantalla renderiza; un hash válido marca el correo como verificado; un hash inválido no lo verifica | Aprobado | `tests/Feature/Auth/EmailVerificationTest.php` |
| CP-013 | Autenticación — confirmación de contraseña | feature | Base limpia; usuario autenticado | GET a la pantalla de confirmación; POST con contraseña correcta e incorrecta | Cubre 3 métodos: `test_confirm_password_screen_can_be_rendered`, `test_password_can_be_confirmed`, `test_password_is_not_confirmed_with_invalid_password` | La pantalla renderiza; contraseña correcta confirma la sesión; contraseña incorrecta la rechaza | Aprobado | `tests/Feature/Auth/PasswordConfirmationTest.php` |
| CP-014 | Autenticación — reseteo de contraseña (solicitud) | feature | Base limpia; usuario existente por correo | GET a la pantalla de "olvidé mi contraseña"; POST solicitando el enlace de reseteo | Cubre 2 métodos: `test_reset_password_link_screen_can_be_rendered`, `test_reset_password_link_can_be_requested` | La pantalla renderiza; la solicitud dispara la notificación de reseteo | Aprobado | `tests/Feature/Auth/PasswordResetTest.php` |
| CP-015 | Autenticación — reseteo de contraseña (aplicación) | feature | Base limpia; token de reseteo generado por el flujo anterior | GET a la pantalla de reseteo con token; POST con nueva contraseña ficticia y el token válido | Cubre 2 métodos: `test_reset_password_screen_can_be_rendered`, `test_password_can_be_reset_with_valid_token` | La pantalla renderiza; con token válido la contraseña se actualiza | Aprobado | `tests/Feature/Auth/PasswordResetTest.php` |
| CP-016 | Autenticación — actualización de contraseña | feature | Base limpia; usuario autenticado | PUT al endpoint de actualización con contraseña actual correcta e incorrecta | Cubre 2 métodos: `test_password_can_be_updated`, `test_correct_password_must_be_provided_to_update_password` | Con la contraseña actual correcta se actualiza; sin ella se rechaza con error de validación | Aprobado | `tests/Feature/Auth/PasswordUpdateTest.php` |
| CP-017 | Autenticación — registro de usuarios | feature | Ninguna: la clase no declara ningún método | N/A — clase vacía (`class RegistrationTest extends TestCase {}`) | 0 métodos cubiertos | N/A | Sin cobertura (stub vacío) | `tests/Feature/Auth/RegistrationTest.php` |

### 3.2 Autenticación y control de acceso por rol (implementación propia de MedSchedule)

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-001 | Middleware de rol admin (legado) | unitario | Ninguna (invoca el middleware directamente con un `Request` construido a mano) | Invocar `EnsureAdminRole::handle()` con un usuario simulado con rol `admin` y con uno sin ese rol | Cubre 2 métodos: `test_handle_allows_admin_user`, `test_handle_throws_for_non_admin_user` | Con rol admin deja pasar la petición; sin rol admin lanza `HttpException` | Aprobado | `tests/Unit/EnsureAdminRoleTest.php` |
| CP-008 | Panel de RBAC (gestión de roles) | feature | Sin `actingAs()`, sin `RefreshDatabase`; inyecta `withSession(['mock_current_user' => [...]])`, mecanismo del middleware legado | GET a `route('admin.rbac')` simulando sesión de admin y de un rol no-admin (`doctor`) | Cubre 2 métodos: `test_admin_rbac_screen_returns_success_for_admin_session`, `test_admin_rbac_screen_returns_forbidden_for_non_admin_session` | Se espera 200 para admin y 403 para no-admin | Fallido — Grupo A (recibe 302 en ambos casos: la ruta real está protegida por `auth`+`role:admin` de Spatie, y el test nunca inicia sesión real) | `tests/Feature/AdminRbacAccessTest.php` |
| CP-018 | Autenticación (implementación propia, en español) | feature | Base limpia (`RefreshDatabase`); roles `admin`/`doctor`/`patient` creados en `setUp()` | GET a `/login`; POST con credenciales de un admin recién creado | Cubre 2 métodos: `test_login_muestra_formulario`, `test_login_exitoso_admin_redirige_a_dashboard` | El formulario se muestra; login exitoso de admin redirige a su dashboard | Aprobado | `tests/Feature/AuthTest.php` |
| CP-019 | Autenticación (implementación propia, en español) | feature | Igual que CP-018 | POST con contraseña incorrecta; POST a `/logout` autenticado; GET a una ruta protegida sin sesión | Cubre 3 métodos: `test_login_fallido_con_credenciales_incorrectas`, `test_logout_destruye_sesion`, `test_ruta_protegida_redirige_a_login_sin_autenticar` | Credenciales incorrectas no autentican; logout destruye la sesión; ruta protegida sin sesión redirige a `/login` | Aprobado | `tests/Feature/AuthTest.php` |

### 3.3 Perfil de usuario

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-026 | Perfil del doctor | feature | Sin `actingAs()`, sin `RefreshDatabase` | PATCH a `route('doctor.profile.update')` con número de licencia duplicado; POST a `route('doctor.profile.photo')` con una foto en `data:` URL | Cubre 2 métodos: `test_update_rejects_duplicate_license_number` (nombre ficticio `'Miguel'`), `test_profile_photo_update_accepts_data_url` (imagen PNG ficticia de 1×1 en base64) | Se espera 422 con error de validación en licencia duplicada; 200 con la foto reflejada | Fallido — Grupo A (recibe 401 en ambos casos: sin sesión autenticada, la ruta protegida por `auth` rechaza antes de llegar a la validación de negocio) | `tests/Feature/DoctorProfileControllerTest.php` |
| CP-034 | Perfil de usuario (Breeze) | feature | Base limpia (`RefreshDatabase`); usuario autenticado | GET a la página de perfil; PATCH actualizando datos; PATCH con el mismo correo (sin cambio) | Cubre 3 métodos: `test_profile_page_is_displayed`, `test_profile_information_can_be_updated`, `test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged` | La página se muestra; los datos se actualizan; si el correo no cambia, el estado de verificación no se reinicia | Aprobado | `tests/Feature/ProfileTest.php` |
| CP-035 | Perfil de usuario (Breeze) — eliminación de cuenta | feature | Igual que CP-034 | DELETE a la cuenta con contraseña correcta e incorrecta | Cubre 2 métodos: `test_user_can_delete_their_account`, `test_correct_password_must_be_provided_to_delete_account` | Con contraseña correcta la cuenta se elimina; sin ella se rechaza con error de validación | Aprobado | `tests/Feature/ProfileTest.php` |

### 3.4 Dashboard y estadísticas

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-003 | Servicio de estadísticas del dashboard | unitario | `Cache` facade fake/mock; sin base de datos real | Invocar `DashboardStatsService` con distintos rangos de fecha, verificando la clave y el TTL de `Cache::remember` usados | Cubre 3 métodos: `test_admin_stats_uses_cache_remember_with_correct_key_and_ttl`, `test_users_chart_cache_key_differs_by_date_range`, `test_appointments_chart_uses_cache_remember_with_ttl_of_five_minutes` | La clave de caché incluye el rango de fechas; el TTL de estadísticas de citas es de 5 minutos | Aprobado | `tests/Unit/Services/DashboardStatsServiceTest.php` |
| CP-020 | Dashboard — vista índice por rol | feature | `RefreshDatabase`; helper `makeUser($rol)` crea roles con `Role::firstOrCreate` y asigna vía Spatie — **sí autentica correctamente** | `actingAs()` con un usuario `admin`, uno `doctor` y uno `patient`; GET a `route('dashboard')` en cada caso | Cubre 3 métodos: `test_admin_dashboard_returns_ok`, `test_doctor_dashboard_redirects_to_agenda`, `test_patient_dashboard_returns_ok` | Se espera 200 para admin y patient, y redirección a `doctor.agenda` para doctor | Fallido — Grupo B (`/dashboard` siempre redirige 302 según el rol en vez de responder 200 para admin/patient, y manda al doctor a `doctor.dashboard` en vez de `doctor.agenda`; drift real entre ruta y test, no un problema de autenticación) | `tests/Feature/DashboardControllerTest.php` |
| CP-021 | Dashboard — datos del admin | feature | Igual que CP-020 (autentica correctamente) | GET a los endpoints JSON de estadísticas generales del admin | Cubre 2 métodos: `test_admin_data_endpoint_returns_json`, `test_admin_data_stats_contain_required_keys` | Responde JSON con la estructura de estadísticas esperada | Aprobado | `tests/Feature/DashboardControllerTest.php` |
| CP-022 | Dashboard — gráfica de usuarios | feature | Igual que CP-020 | GET al endpoint de gráfica de usuarios como admin, como no-admin, y con filtro de rango de fechas | Cubre 3 métodos: `test_users_chart_returns_json_for_admin`, `test_users_chart_rejects_non_admin`, `test_users_chart_filters_by_date_range` | Admin recibe JSON de la gráfica; no-admin es rechazado; el filtro de fechas se aplica | Aprobado | `tests/Feature/DashboardControllerTest.php` |
| CP-023 | Dashboard — gráfica de citas | feature | Igual que CP-020 | GET al endpoint de gráfica de citas como admin, como no-admin, y con filtro de rango de fechas | Cubre 3 métodos: `test_appointments_chart_returns_json_for_admin`, `test_appointments_chart_rejects_non_admin`, `test_appointments_chart_filters_by_date_range` | Admin recibe JSON de la gráfica; no-admin es rechazado; el filtro de fechas se aplica | Aprobado | `tests/Feature/DashboardControllerTest.php` |
| CP-024 | Dashboard — actividad reciente | feature | Igual que CP-020 | GET al endpoint de actividad reciente como admin y como no-admin | Cubre 2 métodos: `test_recent_activity_returns_json_for_admin`, `test_recent_activity_rejects_non_admin` | Admin recibe el JSON de actividad reciente; no-admin es rechazado | Aprobado | `tests/Feature/DashboardControllerTest.php` |
| CP-025 | Dashboard — datos del paciente | feature | Igual que CP-020 | GET al endpoint de datos del paciente autenticado | Cubre 1 método: `test_patient_data_returns_json` | Responde JSON con los datos propios del paciente | Aprobado | `tests/Feature/DashboardControllerTest.php` |

### 3.5 Bitácora de actividad (Activity Log)

Precondición común: `RefreshDatabase`; helpers `makeAdmin()`/`makeDoctor()` crean el rol vía Spatie
y asignan el usuario — autentica correctamente en los 14 métodos.

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-004 | Bitácora — índice | feature | `actingAs()` admin y doctor; sin sesión para el caso de auth | GET a la vista de índice de bitácora como admin, como doctor (no-admin), y sin autenticar | Cubre 3 métodos: `test_logs_index_returns_view_for_admin`, `test_logs_index_rejects_non_admin`, `test_logs_index_requires_auth` | Admin ve la vista; doctor es rechazado; sin sesión se exige autenticación | Aprobado | `tests/Feature/ActivityLogControllerTest.php` |
| CP-005 | Bitácora — detalle de un registro | feature | Igual que CP-004; un registro de bitácora ficticio creado con `makeLog()` | GET al detalle de un log existente como admin, como doctor, y a un id inexistente | Cubre 3 métodos: `test_logs_show_returns_view_for_admin`, `test_logs_show_rejects_non_admin`, `test_logs_show_returns_404_for_missing_log` | Admin ve el detalle; doctor es rechazado; id inexistente responde 404 | Aprobado | `tests/Feature/ActivityLogControllerTest.php` |
| CP-006 | Bitácora — listado JSON filtrable | feature | Igual que CP-004; varios registros ficticios de bitácora | GET al endpoint JSON de datos con filtros por acción, por tipo de modelo y por rango de fechas; sin filtro; como doctor; sin sesión | Cubre 6 métodos: `test_logs_data_returns_json_structure`, `test_logs_data_filters_by_action`, `test_logs_data_filters_by_model_type`, `test_logs_data_filters_by_date_range`, `test_logs_data_rejects_non_admin`, `test_logs_data_requires_auth` | Responde JSON con la estructura esperada; cada filtro reduce el resultado correctamente; doctor y sin sesión son rechazados | Aprobado | `tests/Feature/ActivityLogControllerTest.php` |
| CP-007 | Bitácora — registros por usuario | feature | Igual que CP-004 | GET a los logs de un usuario específico como admin y como doctor | Cubre 2 métodos: `test_get_by_user_returns_logs_for_admin`, `test_get_by_user_rejects_non_admin` | Admin ve los logs del usuario; doctor es rechazado | Aprobado | `tests/Feature/ActivityLogControllerTest.php` |

### 3.6 Citas y agenda del doctor

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-009 | Agenda del doctor y confirmación de citas | feature | Sin `actingAs()`, sin `RefreshDatabase` | GET a `route('doctor.agenda.data')`; PATCH a `route('appointments.update', 2)` marcando una cita como confirmada | Cubre 2 métodos: `test_agenda_data_returns_doctor_and_items`, `test_pending_appointment_can_be_confirmed` | Se espera 200 con la estructura de agenda; 200 con la cita confirmada | Fallido — Grupo A (recibe 401 en ambos casos: sin sesión autenticada, la ruta protegida por `auth` rechaza la petición) | `tests/Feature/AppointmentControllerTest.php` |

### 3.7 Horarios del doctor

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-036 | Horarios (Schedule) | feature | Sin `actingAs()`, sin `RefreshDatabase`; usa `withSession(['doctor_profile_payload' => [...]])`, mecanismo legado | POST creando un horario con duración de consulta de 45 minutos; DELETE de un horario con cita agendada | Cubre 2 métodos: `test_store_calculates_end_time_using_consultation_duration`, `test_blocked_schedule_cannot_be_deleted` | Se espera 200 con la hora de fin calculada; 422 rechazando el borrado de un horario bloqueado | Fallido — Grupo A (recibe 401 en ambos casos: sin sesión real autenticada) | `tests/Feature/ScheduleControllerTest.php` |

### 3.8 Especialidades médicas

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-037 | Especialidades (Specialty) | feature | Sin `actingAs()`, sin `RefreshDatabase` | POST creando una especialidad con nombre duplicado (`'Cardiologia'`); DELETE de una especialidad con doctores asignados | Cubre 2 métodos: `test_create_rejects_duplicate_specialty_name`, `test_delete_rejects_specialty_with_assigned_doctors` | Se espera 422 en ambos casos, con mensaje de validación/negocio | Fallido — Grupo A (recibe 401 en ambos casos: sin sesión real autenticada) | `tests/Feature/SpecialtyControllerTest.php` |

### 3.9 Integración con Google Calendar

Precondición común: `RefreshDatabase`; helper `makeAdmin()` crea el rol admin vía Spatie y
autentica correctamente; usa Mockery para simular `GoogleCalendarService`.

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-028 | Google Calendar — requiere autenticación | feature | Sin `actingAs()` (caso deliberado de "no autenticado") | POST a `sync` y a `unsync` sin sesión | Cubre 2 métodos: `test_sync_requires_authentication`, `test_unsync_requires_authentication` | Ambos responden 401 | Aprobado | `tests/Feature/GoogleCalendarControllerTest.php` |
| CP-029 | Google Calendar — validaciones de estado | feature | `actingAs()` admin; cita ficticia creada con `makeAppointment()` | POST a `sync` sobre un id de cita inexistente; POST a `sync` sobre una cita no confirmada (`pending`) | Cubre 2 métodos: `test_sync_returns_404_for_nonexistent_appointment`, `test_sync_rejects_non_confirmed_appointment` | Id inexistente responde 404; cita no confirmada es rechazada | Aprobado | `tests/Feature/GoogleCalendarControllerTest.php` |
| CP-030 | Google Calendar — sincronizar cita confirmada | feature | `actingAs()` admin; cita ficticia en estado `confirmed`; mock de `GoogleCalendarService::createEvent` configurado para devolver `'fake-event-id-123'` | POST a `sync` sobre la cita confirmada | Cubre 1 método: `test_sync_confirmed_appointment_creates_event` | Se espera `google_event_id` igual a `'fake-event-id-123'` | Fallido — Grupo C (`google_event_id` regresa `null`: el controlador no invoca el mock como se espera, probablemente por despacho asíncrono del job de sincronización) | `tests/Feature/GoogleCalendarControllerTest.php` |
| CP-031 | Google Calendar — maneja fallo del servicio | feature | `actingAs()` admin; mock de `createEvent` configurado para lanzar una excepción | POST a `sync` cuando el servicio de Google falla | Cubre 1 método: `test_sync_handles_calendar_failure` | La falla del servicio se maneja sin romper la respuesta HTTP | Aprobado | `tests/Feature/GoogleCalendarControllerTest.php` |
| CP-032 | Google Calendar — desincronizar cita inexistente | feature | `actingAs()` admin | POST a `unsync` sobre un id de cita inexistente | Cubre 1 método: `test_unsync_returns_404_for_nonexistent_appointment` | Responde 404 | Aprobado | `tests/Feature/GoogleCalendarControllerTest.php` |
| CP-033 | Google Calendar — limpiar id de evento al desincronizar | feature | `actingAs()` admin; cita ficticia con `google_event_id` ya asignado (`'existing-event-id'`); mock de `deleteEvent` | POST a `unsync` sobre esa cita | Cubre 1 método: `test_unsync_clears_google_event_id` | Se espera que `deleteEvent` se invoque exactamente 1 vez sobre el mock | Fallido — Grupo C (`deleteEvent` se invoca 0 veces: `InvalidCountException`, el controlador no llama al servicio mockeado en el flujo probado) | `tests/Feature/GoogleCalendarControllerTest.php` |

### 3.10 Pruebas de scaffold (heredadas de la instalación de Laravel)

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba / variantes | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-002 | Scaffold — ejemplo unitario | unitario | Ninguna | Aserción trivial (`assertTrue(true)`) | Cubre 1 método: `test_that_true_is_true` | La aserción se cumple | Aprobado | `tests/Unit/ExampleTest.php` |
| CP-027 | Scaffold — ejemplo de feature | feature | Ninguna | GET a `/` | Cubre 1 método: `test_the_application_returns_a_successful_response` | Responde 200 | Aprobado | `tests/Feature/ExampleTest.php` |

### 3.11 E2E — Gestión de usuarios (único flujo E2E existente)

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-038 | Gestión de usuarios (RBAC) — crear usuario y editar su rol | E2E | App servida con `php artisan serve`; base con el usuario admin fijo del seeder (correo `admin@test.com`; contraseña: la fija de `DatabaseSeeder`, no repetida aquí) | 1) Login como admin. 2) Abrir `/admin/rbac`. 3) Captura de evidencia del estado inicial. 4) Crear un usuario nuevo con rol `doctor` desde el modal. 5) Verificar que aparece en la tabla con el rol correcto. 6) Captura de evidencia. 7) Editar su rol a `admin`. 8) Verificar el cambio reflejado en la tabla. 9) Captura de evidencia final | Nombre y correo generados con marca de tiempo (`QA Playwright <marca>`, `qa.playwright.<marca>@medschedule.test`) para evitar choque entre corridas; rol inicial `doctor`, rol final `admin` | El usuario se crea con el rol `doctor` visible en la tabla, y tras la edición el badge de rol cambia a `admin` | Aprobado (1 de 1, corrida real en 4.8s según `docs/entrega/evidencia/playwright-baseline.txt`) | `tests/playwright_gestion_usuarios/gestion-usuarios.spec.js` |

## 4. Casos propuestos (E2E, sin implementar) — `specs/001-pruebas-e2e/spec.md`

Estos 20 casos derivan de las cuatro historias de usuario de la spec piloto `001-pruebas-e2e`, que
cubre exactamente los flujos hoy sin cobertura E2E. **Ninguno tiene código de prueba todavía**;
todos quedan marcados con estado `Propuesto, unidad siguiente` y su columna de archivo indica la
ruta sugerida, que no existe en el repositorio.

### 4.1 Control de acceso por rol (User Story 1, prioridad P1)

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-039 | Control de acceso por rol | E2E (propuesto) | Usuarios de prueba sembrados con los tres roles y correos ficticios distintos del seeder de demostración | Login con un usuario de rol `admin` y credenciales ficticias correctas | Correo ficticio de prueba con rol `admin` | Redirige al panel de administrador | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-040 | Control de acceso por rol | E2E (propuesto) | Igual que CP-039, con un usuario de rol `doctor` | Login con un usuario de rol `doctor` y credenciales ficticias correctas | Correo ficticio de prueba con rol `doctor` | Redirige al panel de doctor | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-041 | Control de acceso por rol | E2E (propuesto) | Igual que CP-039, con un usuario de rol `patient` | Login con un usuario de rol `patient` y credenciales ficticias correctas | Correo ficticio de prueba con rol `patient` | Redirige al panel de paciente | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-042 | Control de acceso por rol | E2E (propuesto) | Usuario ficticio existente | Login con contraseña ficticia incorrecta | Contraseña deliberadamente incorrecta (marcador de prueba, no una clave real) | No autentica; muestra mensaje de error; permanece en `/login` | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-043 | Control de acceso por rol | E2E (propuesto) | Usuario ficticio autenticado con rol `patient` | Navegar directamente a una ruta reservada al rol `admin` | Ruta administrativa protegida | Responde 403; no muestra contenido de administrador | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-044 | Control de acceso por rol | E2E (propuesto) | Usuario ficticio autenticado sin ningún rol asignado | Navegar al panel principal propio | Usuario sin rol Spatie asignado | Responde 403 en vez de redirigir a un panel | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-045 | Control de acceso por rol — caso límite | E2E (propuesto) | Usuario ficticio con el nombre de rol guardado con variación de mayúsculas/minúsculas (regresión del bug histórico de comparación de roles) | Intentar iniciar sesión y navegar a su panel | Rol con capitalización distinta a la esperada (por ejemplo `Admin` en vez de `admin`) | El sistema sigue tratando los roles en minúscula de forma consistente; no autentica accesos indebidos por esta causa | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |

### 4.2 Agenda del doctor (User Story 2, prioridad P2)

Complementa, no reemplaza, la cobertura de PHPUnit ya existente de `AppointmentControllerTest`
(CP-009, hoy fallida — Grupo A): esa prueba ejercita el endpoint JSON directamente; esta se propone
como flujo E2E de navegador sobre la vista de agenda.

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-046 | Agenda del doctor | E2E (propuesto) | Doctor ficticio autenticado con citas ya agendadas en su horario | Abrir la vista de agenda del doctor | Citas ficticias asignadas al doctor de prueba | Se listan únicamente las citas que le pertenecen | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-047 | Agenda del doctor — caso límite | E2E (propuesto) | Doctor ficticio autenticado sin citas agendadas en el rango visible | Abrir la vista de agenda del doctor | Doctor de prueba sin citas ni horarios en el rango | La agenda se muestra vacía, sin error | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-048 | Agenda del doctor | E2E (propuesto) | Dos doctores ficticios, cada uno con citas propias | Abrir la agenda del primer doctor | Citas de ambos doctores sembradas en la base | No aparece ninguna cita perteneciente al segundo doctor | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |

### 4.3 Agendado y cancelación de citas del paciente (User Story 3, prioridad P3)

**Nota obligatoria de la spec**: el paciente no tiene una vista dedicada de agendado. El flujo
completo vive en `resources/views/patient/dashboard.blade.php`, orquestado por
`resources/js/patient-dashboard.js` contra endpoints JSON (`POST /appointments`, `POST
/appointments/{id}/cancel`, `GET /patient/dashboard/data`). Las pruebas propuestas deben esperar y
verificar esas respuestas JSON y el refresco de la interfaz resultante, no una navegación entre
páginas distintas.

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-049 | Agendado de citas (dashboard del paciente) | E2E (propuesto) | Paciente ficticio autenticado; doctor ficticio con un horario disponible y una especialidad determinada, ya sembrados | Desde el dashboard del paciente, seleccionar especialidad/doctor/horario y confirmar el agendado (AJAX) | Horario y especialidad ficticios sembrados de antemano | La respuesta JSON confirma el agendado; la cita aparece en el listado del dashboard con estado agendada | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-050 | Agendado de citas (dashboard del paciente) | E2E (propuesto) | Igual que CP-049, con el horario ya ocupado por otra cita | Intentar agendar en el mismo horario ya ocupado | Horario ya reservado por otra cita ficticia | La operación se rechaza; no se crea una segunda cita en ese horario | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-051 | Cancelación de citas (dashboard del paciente) | E2E (propuesto) | Paciente ficticio autenticado con una cita propia en estado agendada | Cancelar la cita desde el dashboard (AJAX) | Cita propia ficticia en estado agendada | La cita cambia a estado cancelada; el horario vuelve a quedar disponible | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-052 | Cancelación de citas (dashboard del paciente) | E2E (propuesto) | Dos pacientes ficticios; una cita pertenece al segundo | El primer paciente intenta cancelar la cita del segundo | Cita ficticia de otro paciente | La operación se rechaza | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-053 | Cancelación de citas — caso límite | E2E (propuesto) | Paciente ficticio con una cita propia ya en estado cancelada | Intentar cancelarla de nuevo | Cita ficticia ya cancelada previamente | La operación se rechaza; no duplica el cambio de estado | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-054 | Agendado de citas — condición de carrera | E2E (propuesto) | Dos pacientes ficticios; un mismo horario disponible | Ambos intentan agendar el mismo horario casi simultáneamente | Un horario disponible compartido como blanco de la carrera | Solo uno de los dos logra reservarlo; el horario no queda reservado dos veces | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |

### 4.4 CRUD de especialidades del administrador (User Story 4, prioridad P4)

| ID | Módulo | Tipo | Precondiciones | Pasos | Datos de prueba | Resultado esperado | Estado | Archivo de automatización |
|---|---|---|---|---|---|---|---|---|
| CP-055 | CRUD de especialidades | E2E (propuesto) | Administrador ficticio autenticado | Crear una especialidad con un nombre nuevo | Nombre de especialidad ficticio no existente en el catálogo | La especialidad aparece en el listado | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-056 | CRUD de especialidades | E2E (propuesto) | Administrador ficticio autenticado; especialidad ficticia ya existente | Editar el nombre de la especialidad | Especialidad ficticia existente y nuevo nombre ficticio | El listado refleja el nombre actualizado | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-057 | CRUD de especialidades | E2E (propuesto) | Administrador ficticio autenticado; especialidad ficticia sin citas asociadas | Eliminar la especialidad | Especialidad ficticia sin citas asociadas | Deja de aparecer en el listado | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |
| CP-058 | CRUD de especialidades | E2E (propuesto) | Administrador ficticio autenticado; especialidad ficticia ya existente | Intentar crear una especialidad con el mismo nombre | Nombre de especialidad ficticio ya presente en el catálogo | La creación se rechaza; no genera un duplicado | Propuesto, unidad siguiente | (no existe todavía; unidad siguiente) |

## 5. Trazabilidad módulo → casos → requisito/spec de origen

Para los módulos ya existentes (sección 3) no hay una spec numerada con `FR-00X`: son
funcionalidad ya construida antes de esta entrega, y su origen es el propio código de producción y
la evidencia de ejecución. Para los módulos propuestos (sección 4), el origen es
`specs/001-pruebas-e2e/spec.md`, que sí numera sus requisitos funcionales.

| Módulo | Casos | Requisito / spec de origen |
|---|---|---|
| Autenticación (scaffold Breeze) | CP-010 a CP-017 | Scaffold de autenticación de Laravel Breeze; sin spec numerada. La brecha de `RegistrationTest` (CP-017) está documentada en `docs/entrega/02-plan-de-pruebas.md` §6.4 |
| Autenticación y control de acceso por rol (implementación propia) | CP-001, CP-008, CP-018, CP-019 | `routes/web.php` (middleware `auth` + `role:admin`/`role:doctor` de Spatie); middleware legado `App\Http\Middleware\EnsureAdminRole` |
| Perfil de usuario | CP-026, CP-034, CP-035 | `App\Http\Controllers\DoctorProfileController`; `App\Http\Controllers\ProfileController` (scaffold Breeze) |
| Dashboard y estadísticas | CP-003, CP-020 a CP-025 | `App\Http\Controllers\DashboardController`; `App\Services\DashboardStatsService` |
| Bitácora de actividad | CP-004 a CP-007 | `App\Http\Controllers\ActivityLogController` |
| Citas y agenda del doctor (cobertura PHPUnit existente) | CP-009 | `App\Http\Controllers\AppointmentController` |
| Horarios del doctor | CP-036 | `App\Http\Controllers\ScheduleController` |
| Especialidades médicas (cobertura PHPUnit existente) | CP-037 | `App\Http\Controllers\SpecialtyController` |
| Integración con Google Calendar | CP-028 a CP-033 | `App\Http\Controllers\GoogleCalendarController`; `App\Services\GoogleCalendarService` |
| Scaffold / ejemplo | CP-002, CP-027 | Instalación por defecto de Laravel; sin spec |
| E2E — Gestión de usuarios (existente) | CP-038 | Comentario de cabecera del propio spec: "#61 / requisito 5.7 Unidad 4" |
| Control de acceso por rol (propuesto) | CP-039 a CP-045 | `specs/001-pruebas-e2e/spec.md`, User Story 1 (P1), requisitos FR-001, FR-002, FR-003, FR-004 |
| Agenda del doctor (propuesto) | CP-046 a CP-048 | `specs/001-pruebas-e2e/spec.md`, User Story 2 (P2), requisito FR-005 |
| Agendado y cancelación de citas del paciente (propuesto) | CP-049 a CP-054 | `specs/001-pruebas-e2e/spec.md`, User Story 3 (P3), requisitos FR-006, FR-007, FR-008, FR-009 |
| CRUD de especialidades del administrador (propuesto) | CP-055 a CP-058 | `specs/001-pruebas-e2e/spec.md`, User Story 4 (P4), requisitos FR-010, FR-011 |

**Nota sobre los requisitos de pipeline de la misma spec.** `specs/001-pruebas-e2e/spec.md` numera
además FR-012 (el pipeline debe ejecutar las pruebas E2E en cada solicitud de cambios), FR-013 (el
pipeline debe fallar cuando una prueba E2E falla, sin mecanismo que lo oculte) y FR-014 (el pipeline
debe dejar disponibles las capturas de pantalla como artefacto). Estos tres no producen un caso de
prueba funcional propio en esta matriz: se verifican mediante el diseño del pipeline de CI, no
mediante una prueba sobre la aplicación. `docs/entrega/02-plan-de-pruebas.md` §8 ya documenta, con
`grep -n` contra `.github/workflows/ci.yml`, que hoy ninguno de los tres se cumple (`|| true` en los
pasos de lint, formato y PHPUnit; filtro reducido a 4 clases; Playwright ausente del pipeline por
completo), sin modificar ese archivo, conforme a la restricción vinculante de esta entrega.

## 6. Verificación de esta matriz

Se ejecutó, sobre este mismo archivo ya con el contenido final, un conteo de filas de caso
(patrón `^| CP-` al inicio de línea, contando solo filas de datos porque los encabezados de cada
subtabla usan la etiqueta `ID` en vez de repetir `CP-XXX`) y una búsqueda de patrones de
credenciales (contraseña seguida de un valor de seis o más caracteres, o las palabras clave que
identifican una clave de aplicación o un valor sensible de configuración).

Resultado real: 58 filas de caso (`CP-001` a `CP-058`, sin huecos en la numeración), y ningún
patrón de clave, token ni contraseña con aspecto verosímil encontrado en el archivo. La suma de
métodos declarados como cubiertos en las filas de la sección 3.1 a 3.11 es
79 métodos reales de PHPUnit, repartidos en las 36 filas que sí cubren métodos (la fila de
`RegistrationTest`, CP-017, no suma ninguno porque es un stub vacío), que coincide con el total
verificado en la sección 0 y con `docs/entrega/evidencia/phpunit-baseline.txt`; a esos 79 se suma
1 spec de Playwright (CP-038), para 80 artefactos de prueba ya existentes documentados, y 20 casos
propuestos sin implementar (CP-039 a CP-058).

## 7. Conclusión

Esta matriz documenta con precisión los 79 métodos de PHPUnit y el único spec de Playwright que ya
existen en la rama `feat/unidad-docs-sdd`, agrupados en 38 filas trazables por módulo, sin omitir
ni inventar ningún método: cada fila declara cuántos cubre y su estado sale directamente de
`docs/entrega/evidencia/phpunit-baseline.txt`. A esa base se suman 20 casos propuestos para los
cuatro flujos E2E que `specs/001-pruebas-e2e/spec.md` ya especifica y que hoy no tienen ninguna
prueba automatizada, incluida la particularidad de que el agendado de citas del paciente se
verifica contra el dashboard y sus endpoints JSON, no contra una pantalla propia. Ningún caso de
esta matriz —existente ni propuesto— se corrige o se implementa en esta entrega: los 15 fallos
reales quedan documentados con su grupo de causa raíz para que la unidad siguiente los aborde con
criterio, y los 20 casos propuestos quedan como el punto de partida verificable de esa unidad.
