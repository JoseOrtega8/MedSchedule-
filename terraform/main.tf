# Esqueleto declarativo de la infraestructura de MedSchedule en Railway.
#
# ESTADO: no aplicado. Estos recursos describen la infraestructura objetivo
# de la unidad siguiente. No ejecutar `terraform apply` con este archivo hasta
# que el proyecto de Railway exista y el equipo lo autorice.
#
# Recursos previstos:
#   - Proyecto de Railway que agrupa los servicios.
#   - Servicio de aplicacion, construido con el nixpacks.toml del repositorio.
#   - Servicio de base de datos MySQL 8.
#
# Se dejan comentados a proposito: activarlos sin revisar provisiona
# infraestructura de pago.

# resource "railway_project" "medschedule" {
#   name = var.nombre_proyecto
# }

# resource "railway_service" "app" {
#   project_id = railway_project.medschedule.id
#   name       = "medschedule-app"
# }

output "nota_estado" {
  description = "Recordatorio de que esta configuracion es un esqueleto no aplicado."
  value       = "Esqueleto de infraestructura. Sin aplicar. Ver terraform/README.md."
}
