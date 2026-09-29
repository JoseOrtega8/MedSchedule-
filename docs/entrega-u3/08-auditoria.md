# 8. Módulo c: visor de auditoría

**Pull request:** [[PR-108]]

## 8.1 Qué resuelve

Antes de esta unidad, `activity_logs` registraba solo el inicio y el cierre de sesión y los
cambios del perfil del paciente. Un cambio de fecha o de doctor en una cita no dejaba rastro, y
cualquiera con acceso a la base de datos podía editar o borrar un registro sin que nadie lo
notara. Además, el registro del perfil del paciente guardaba PII médica en claro (apartado 8.3).

Este módulo convierte `activity_logs` en una auditoría de solo agregado, sellada y encadenada,
con captura automática y un visor exclusivo del administrador.

## 8.2 Qué se audita

| Origen | Eventos | Mecanismo |
|---|---|---|
| Citas, usuarios, especialidades, horarios, perfiles de doctor y de paciente | `creado`, `actualizado`, `eliminado`, con valores antes y después de los campos cambiados | Trait `App\Models\Concerns\Auditable` en los seis modelos |
| Sesión | `login` (ya existente en `AuthenticatedSessionController`), `logout`, `login_fallido`, `password_restablecida` | Listeners de `Logout`, `Failed` y `PasswordReset` |
| Roles y permisos | `rol_asignado`, `rol_retirado`, `permiso_asignado`, `permiso_retirado` | Eventos de `spatie/laravel-permission` (`events_enabled => true`) |
| Accesos denegados | `acceso_denegado` en las rutas de administración | Middleware `auditar.denegado` |
| El propio visor | `auditoria_exportada`, con los filtros usados | `AuditoriaController@exportar` |

Con esto los tipos de evento auditados pasan de los tres que existían a 28: 18 de las seis
entidades (tres operaciones cada una) y 10 de sesión, roles, permisos, accesos y exportación.

Reglas de captura:

- Solo se guardan los campos que cambiaron. Un cambio que solo toca `remember_token` o las
  marcas de tiempo no genera registro.
- Cada registro guarda quién (`user_id`), qué (`action`, entidad e id), cuándo, desde qué IP,
  con qué agente de usuario y, si la petición tenía traza, su `trace_id`. Este último solo se
  llena cuando la rama de trazabilidad está integrada; sin ella la columna queda vacía.
- Un intento de inicio de sesión fallido se registra con el correo enmascarado
  (`j***@dominio`), nunca completo.
- El cierre de sesión lo registra solo el listener: el controlador también lo registraba y
  duplicaba la fila, así que se quitó.

## 8.3 PII protegida

Los campos protegidos se auditan como `[protegido]`: el registro dice que cambiaron, nunca su
valor.

| Modelo | Campos protegidos |
|---|---|
| Todos | `password` |
| `PatientProfile` | `birth_date`, `blood_type`, `allergies`, `chronic_conditions`, `emergency_contact_name`, `emergency_contact_phone`, `curp` |
| `Appointment` | `reason` (motivo de la consulta) y `observaciones`: texto clínico libre |

**Hallazgo corregido.** El `ActivityLog::create` del método `update()` de
`PatientProfileController` guardaba en claro, en `old_values` y `new_values`, la fecha de
nacimiento, el tipo de sangre, las alergias, los padecimientos, los contactos de emergencia y la
CURP. El de `updatePhoto()` solo registraba la acción, sin valores. Ambos se eliminaron: el trait
registra esos mismos cambios con los valores protegidos. De paso,
`FullDataSeeder` dejó de insertar auditoría con `DB::table()` (sin sello) y pasó a usar el
modelo.

## 8.4 Cadena HMAC encadenada

Cada registro se sella al insertarse:

`hash = HMAC-SHA256(hash_anterior | json_canónico(fila), AUDIT_HMAC_KEY)`

| Pieza | Diseño |
|---|---|
| Contenido sellado | Acción, fecha (ISO 8601 en UTC), descripción, IP, entidad e id, valores antes y después, `trace_id`, agente de usuario y usuario |
| JSON canónico | Claves ordenadas (`ksort` con `SORT_STRING`, también dentro de los valores), sin espacios. MySQL reordena las claves de las columnas JSON; sin ordenar, un registro íntegro parecería alterado |
| Encadenamiento | `hash_anterior` es el sello del registro previo. Alterar o borrar una fila intermedia rompe la cadena desde ese punto |
| Concurrencia | La inserción ocurre en una transacción con `lockForUpdate` sobre el último registro, para que dos cambios simultáneos no bifurquen la cadena; se reintenta hasta 3 veces ante un interbloqueo |
| Solo agregado | El modelo Eloquent `ActivityLog` lanza `RegistroAuditoriaInmutable` ante cualquier intento de modificar o eliminar un registro a través del modelo. Una operación masiva (`ActivityLog::query()->update()` o `->delete()`) o SQL directo no se bloquea, pero la verificación de integridad la detecta |
| Llave | `AUDIT_HMAC_KEY` en `.env`. En producción, sin llave la aplicación no arranca. Fuera de producción se deriva de `APP_KEY` con una advertencia en el log, para no romper los entornos del equipo |

La migración `2026_09_23_000000` agrega `trace_id`, `hash_anterior` y `hash`, y **sella en la
misma migración todas las filas existentes**, en orden de id. Después de migrar no existe una
fila "histórica sin sello": toda fila con `hash` nulo cuenta como rota.

### Qué detecta y qué no

| Detecta | No detecta |
|---|---|
| Modificar cualquier campo sellado de una fila | Borrar las **últimas** filas de la tabla: la cadena queda íntegra hasta donde llega |
| Borrar una fila intermedia | A quien posee `AUDIT_HMAC_KEY`: puede recalcular toda la cadena de forma consistente. El sello protege contra quien no tiene la llave |
| Insertar una fila sin sello | Cambios en `id` o `updated_at`, que no forman parte del contenido sellado |
| Anular los sellos de forma masiva | El vaciado completo de la tabla: la verificación la reporta íntegra con 0 registros revisados |

Dos condiciones de operación completan los límites: rotar `AUDIT_HMAC_KEY` invalida la cadena
existente y obliga a volver a sellar el histórico con la llave nueva, y la migración que sella
debe correr en modo mantenimiento (`php artisan down`) para que nadie inserte auditoría a mitad
del sellado.

Esta última condición no la cumple hoy el despliegue automático: `scripts/despliegue.sh` ejecuta
`php artisan migrate --force` sin poner la aplicación en modo mantenimiento. Por eso el primer
despliegue que incluya la migración de integridad requiere un paso manual documentado: ejecutar
`php artisan down`, luego `php artisan migrate --force` y después `php artisan up`. Incorporar
el modo mantenimiento al script queda como mejora pendiente.

## 8.5 La auditoría sobrevive al usuario

La llave foránea original de `activity_logs.user_id` tenía `ON DELETE SET NULL`: al borrar un
usuario, MySQL ponía en nulo el `user_id` de sus registros, lo que cambiaba el contenido de
filas ya selladas y hacía que la verificación reportara una manipulación que nadie cometió. La
migración `2026_09_23_000001` quita la llave foránea y conserva el índice, que sigue sirviendo
a los filtros. Advertencia documentada en la propia migración: el rollback, que restaura la
llave, falla si ya existen registros de usuarios borrados.

## 8.6 Verificación de integridad

`php artisan auditoria:verificar` recorre la cadena completa por lotes de 500, informa los
registros revisados y termina con código 1 señalando el primer registro roto, o con código 0 si
la cadena está íntegra. Deja el resultado en caché para que el visor lo muestre sin recorrer la
tabla en cada visita.

`routes/console.php` lo programa a diario a las 03:00
(`Schedule::command('auditoria:verificar')->dailyAt('03:00')`). La programación de Laravel
necesita que el sistema ejecute `php artisan schedule:run` cada minuto (cron); ese cron no
está declarado en el repositorio y debe configurarse en el servidor donde se despliegue.

## 8.7 El visor `/admin/auditoria`

| Función | Detalle |
|---|---|
| Acceso | Grupo de rutas de administración con middleware en este orden: `auth`, `throttle:60,1`, `auditar.denegado`, `role:admin`. Un no administrador recibe 403 y el intento queda auditado; el límite de tasa va antes para que intentos repetidos reciban 429 sin llenar la auditoría |
| Filtros | Usuario, acción, entidad, rango de fechas e IP, validados con `FiltrarAuditoriaRequest` |
| Listado | Paginado en el servidor, 25 registros por página |
| Detalle | Diff lado a lado, campo por campo, con valores antes y después |
| Línea de tiempo | Historial de una entidad concreta. La URL usa alias (`cita`, `usuario`, `perfil_paciente`…), nunca nombres de clase, y el id está limitado a 18 dígitos para evitar un desbordamiento que respondía 500 |
| Integridad | Tres estados: gris ("Integridad aún no verificada") si nunca se ha ejecutado la verificación, verde ("Cadena íntegra") o rojo ("Cadena comprometida en el registro #n"), con el resultado de la última verificación |
| Enlace a traza | Si el registro tiene `trace_id`, enlace "Ver traza de la petición" a Grafana Explore sobre Tempo |
| Exportación CSV | En streaming; toda celda que empieza con `=`, `+`, `-`, `@`, tabulador o retorno de carro se antepone con `'` para que una hoja de cálculo no la interprete como fórmula; `fputcsv` sin escape invertido; un error a mitad de la exportación se registra en el log y no se expone en el archivo; la exportación queda auditada |

El indicador de integridad refleja la **última** corrida de `auditoria:verificar`, no el estado
en tiempo real.

## 8.8 Pruebas

El módulo agrega 40 métodos de prueba: 36 en `tests/Feature/Auditoria/` y 4 unitarios
(`CsvSeguroTest`, `EnmascararTest`). Entre ellos:

| Prueba | Qué verifica |
|---|---|
| `test_detecta_alteracion_manual_en_la_base` | Un `UPDATE` directo por SQL se detecta en el id exacto |
| `test_detecta_eliminacion_intermedia` | Borrar una fila intermedia rompe la cadena |
| `test_fila_sin_sello_rompe_la_cadena` y `test_anular_todos_los_hashes_rompe_la_cadena` | No hay forma de "perdonar" filas sin sello |
| `test_borrar_usuario_conserva_la_auditoria_y_la_cadena_integra` | Quitar la llave foránea cumple su propósito |
| `test_no_se_puede_modificar` y `test_no_se_puede_eliminar` | Solo agregado a través del modelo |
| `test_no_admin_recibe_403` (y sus variantes en detalle, línea de tiempo y exportación) | Exclusivo del administrador |
| `test_exportar_csv_escapa_formulas_y_queda_auditado` | CSV seguro y exportación auditada |
| `test_login_fallido_se_audita_con_correo_enmascarado` | El correo no se guarda completo |

Resultado de la suite: [[PENDIENTE: resultado de php artisan test --filter="Auditoria|CsvSeguro|Enmascarar" en la rama feat/108-auditoria, de evidencia/pruebas-auditoria.txt]].

## 8.9 Antes y después

### Antes: un cambio sin rastro y una alteración inadvertida

En la rama de la Unidad 2, como administrador se cambió la fecha y el doctor de una cita. La
tabla `activity_logs` no registró nada.

![Consulta a activity_logs sin ningún registro del cambio de fecha y doctor de la cita](evidencia/auditoria-01-antes-sin-rastro.png)

Después se alteró a mano una fila de `activity_logs` directamente en MySQL. Nada lo detectó.

![Fila de activity_logs alterada a mano en MySQL sin ninguna señal de manipulación](evidencia/auditoria-02-antes-alteracion.png)

### Después: quién, qué, cuándo y desde dónde

En la rama de auditoría se repitió el mismo cambio en la cita.

![Listado del visor de auditoría con filtros, el registro del cambio de la cita y el indicador de integridad](evidencia/auditoria-03-listado.png)

![Detalle del registro con el diff de fecha y doctor, antes y después, usuario, fecha e IP](evidencia/auditoria-04-diff.png)

![Línea de tiempo de la cita con todos sus registros de auditoría en orden](evidencia/auditoria-05-linea-tiempo.png)

### Después: la alteración se detecta

Con la cadena íntegra, la verificación termina en éxito:

[[PENDIENTE: salida de php artisan auditoria:verificar con la cadena íntegra (registros revisados y "Cadena íntegra."), de evidencia/auditoria-verificar-integra.txt]]

Se volvió a alterar a mano la misma fila en MySQL (la migración la había sellado como válida al
adoptarse) y se repitió la verificación:

[[PENDIENTE: salida de php artisan auditoria:verificar con la cadena rota (id del registro roto y código de salida), de evidencia/auditoria-verificar-rota.txt]]

![Visor de auditoría con el indicador rojo de cadena comprometida señalando el registro alterado](evidencia/auditoria-06-alteracion-detectada.png)
