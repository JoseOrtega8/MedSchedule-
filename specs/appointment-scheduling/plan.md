# Plan: Agendado de citas sin duplicados

## Cómo se implementa
- Se usa el constraint único ya existente en la tabla `schedules`
  (doctor + fecha + hora) para que la base de datos rechace duplicados.
- El controlador de citas debe capturar ese error y devolver un mensaje
  de error legible al usuario, en vez de un error genérico del servidor.

## Dónde vive en el proyecto
- Backend: controlador de citas (Laravel).
- Frontend: vista Blade de agendado, mostrando el mensaje de error.
- Pruebas: nuevo archivo `tests/e2e/agendado-citas.spec.js` (Playwright).