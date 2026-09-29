# 1. Caso de estudio

## 1.1 El sistema

MedSchedule es una aplicación web de gestión de citas médicas construida sobre Laravel 12.53.0
y PHP 8.2, con vistas Blade, Bootstrap 5 y MySQL 8. Tres roles la usan: administrador, doctor y
paciente, cada uno con su panel y sus permisos, resueltos con `spatie/laravel-permission`. El
sistema maneja datos personales y clínicos: alergias, padecimientos crónicos, tipo de sangre,
CURP, contactos de emergencia y el motivo de cada cita.

El repositorio es público y vive en `github.com/JoseOrtega8/MedSchedule-`. Esta unidad se apila
sobre el trabajo de la Unidad 2 (rama `feat/100-sonarqube`), que dejó un entorno de liberación
declarado como código, pruebas de carga con k6 como compuerta y un análisis estático con
SonarQube.

## 1.2 El equipo y su restricción

Cinco integrantes trabajan sobre el mismo repositorio, pero cada entrega es individual. De ahí
salen tres reglas que condicionan todo el diseño:

- Ninguna pieza puede depender de la cuenta de pago o de la infraestructura de un integrante.
- Nadie modifica el trabajo asignado a otro: los defectos ajenos se reportan, no se corrigen.
- Todo lo que se propone debe poder levantarlo cualquiera desde el repositorio, sin pedir
  credenciales a nadie.

Por eso los stacks de observabilidad corren en contenedores declarados en `infra/`, con
versiones fijadas y sin secretos versionados.

## 1.3 El problema que resuelve esta unidad

Al cerrar la Unidad 2, MedSchedule verificaba su calidad **en el momento de liberar**, pero no
tenía forma de saber qué ocurría **después**. Concretamente:

| Pregunta | Situación al iniciar la unidad |
|---|---|
| ¿La aplicación está arriba y responde a tiempo? | No había recolección continua de métricas ni alertas. Si la aplicación se caía o se volvía lenta, nadie se enteraba hasta que un usuario se quejaba |
| ¿Qué pasó en una petición que falló? | Solo existía `storage/logs/laravel.log`, texto plano sin forma de relacionar una línea con su petición ni de ver en qué se fue el tiempo |
| ¿Quién cambió una cita, un usuario o un rol? | `activity_logs` registraba solo el inicio y el cierre de sesión y los cambios del perfil del paciente. Cualquiera con acceso a la base podía editar ese registro sin que nadie lo notara |
| ¿Las dependencias tienen vulnerabilidades conocidas? | `composer.lock` y `package-lock.json` no se analizaban en ningún punto del pipeline |
| ¿La puerta de calidad detiene algo? | El análisis de SonarQube era un reporte; su veredicto no detenía la liberación |

A esto se suma un hallazgo hecho al planear la auditoría: los dos `ActivityLog::create` de
`PatientProfileController` guardaban alergias, padecimientos, tipo de sangre y CURP **en claro**
en las columnas `old_values` y `new_values`. Es decir, el único registro de actividad existente
filtraba PII médica. Se corrigió en el módulo de auditoría (apartado 8).

## 1.4 Lo que esta entrega construye

| Pregunta | Respuesta de esta unidad | Apartado |
|---|---|---|
| ¿La puerta de calidad detiene algo? | El escáner espera el veredicto y el pipeline se detiene si no se supera | 0 y 11 |
| ¿Está arriba y responde a tiempo? | Prometheus, Grafana y Alertmanager con seis alertas ligadas a los niveles de servicio | 6 |
| ¿Qué pasó en una petición? | Logs JSON con PII enmascarada y trazas OpenTelemetry, enlazados en Grafana | 7 |
| ¿Quién cambió qué? | Auditoría automática de seis entidades con sello HMAC encadenado y visor propio | 8 |
| ¿Las dependencias son seguras? | Snyk como compuerta del pipeline de liberación | 9 |

## 1.5 Alcance y límites

- El monitoreo, las trazas y el análisis estático corren en contenedores locales o en el
  entorno de desarrollo. Su despliegue en un entorno productivo queda fuera de alcance.
- Las notificaciones de alerta llegan a un buzón de pruebas local (Mailpit). No se usa correo
  real, Slack ni Telegram.
- La vista de logs de actividad existente (`/admin/logs`) no se modifica; el visor de auditoría
  es una vista nueva.
- No se toca trabajo asignado a otros integrantes. La suite de PHPUnit arrastra fallos
  anteriores a esta unidad, documentados en el issue #86; la comparación válida es "mismos
  fallos antes y después".
