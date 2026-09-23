# MedSchedule Constitution

## Core Principles

### I. Estilo de código
Comentarios en español; nombres de variables y funciones en snake_case.

### II. Manejo de errores
Manejo de errores explícito con try/catch; nunca silenciar excepciones.

### III. Gestión de secretos
Ningún secreto en el código; todo en .env, que jamás se commitea.

### IV. Validación de entrada
Validar y sanitizar todo input antes de procesarlo.

### V. Exposición de errores al cliente
Los errores internos no se exponen al cliente.

### VI. Spec-Driven Development
Toda funcionalidad nueva entra por spec antes que por código.

### VII. Convenciones de control de versiones
Commits convencionales; una rama por issue.

## Restricciones Adicionales

El repositorio de MedSchedule es público: ningún archivo puede contener claves, tokens,
contraseñas ni cadenas de conexión; los ejemplos usan marcadores de posición. Las versiones de
dependencias relevantes para la entrega de documentación y CI/CD quedan fijadas explícitamente
donde aplique (por ejemplo, `driver.js@1.8.0`, `@playwright/test@^1.62.1`).

## Flujo de Desarrollo

El flujo Spec-Driven Development es obligatorio para funcionalidad nueva: especificación
(`/speckit-specify`), plan (`/speckit-plan`) y tareas (`/speckit-tasks`) preceden a la
implementación. Los mensajes de commit usan el formato convencional `type(scope): description`,
sin líneas de atribución.

## Governance

Esta constitución prevalece sobre cualquier otra práctica de desarrollo del proyecto. Toda
enmienda requiere: documentación del cambio, justificación explícita y actualización de la
versión según semver (MAJOR: eliminación o redefinición incompatible de un principio; MINOR:
adición de un principio o sección; PATCH: aclaraciones o correcciones de redacción sin cambio de
sentido). Las revisiones de código y de pull requests deben verificar el cumplimiento de estos
principios; cualquier complejidad que se aparte de ellos debe justificarse explícitamente.

**Version**: 1.0.0 | **Ratified**: 2026-09-09 | **Last Amended**: 2026-09-09
