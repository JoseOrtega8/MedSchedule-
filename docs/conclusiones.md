# Conclusiones — MedSchedule U3

## Logros
- Sistema desplegado en producción con Railway + HTTPS automático
- Autenticación con roles (admin, doctor, paciente) usando Spatie Permission
- Dashboard admin con datos reales y gráficas Chart.js
- Panel de logs de actividad con filtros
- Agenda del doctor con citas en tiempo real
- Google Calendar API integrado para sincronización de citas
- CI/CD con GitHub Actions — lint, prettier y tests unitarios
- 28 tests unitarios pasando

## Problemas encontrados
- Conflictos de merge frecuentes al trabajar en ramas separadas
- Spatie Permission requiere PHP 8.3 en su versión más reciente
- Google Calendar Domain-Wide Delegation requiere Google Workspace

## Sugerencias de expansión
- Implementar registro con PIN por correo (pendiente)
- Agregar notificaciones por email al confirmar citas
- Módulo de pagos en línea
- App móvil con Flutter

## TODO
- #TODO: PIN verification on registration
- #TODO: Email notifications
- #TODO: Payment integration
- #TODO: Mobile app
