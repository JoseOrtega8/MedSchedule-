# Esqueleto declarativo de la infraestructura de MedSchedule en Railway.
#
# ESTADO: no aplicado. Estos recursos describen la infraestructura objetivo
# de la unidad siguiente. No ejecutar `terraform apply` con este archivo hasta
# que el proyecto de Railway exista y el equipo lo autorice.
#
# Recursos previstos:
#   - Proyecto de Railway que agrupa los servicios.
#   - Servicio de aplicación, construido con el nixpacks.toml del repositorio.
#   - Servicio de base de datos MySQL 8.
#
# Se dejan comentados a propósito: activarlos sin revisar provisiona
# infraestructura de pago.

# resource "railway_project" "medschedule" {
#   name = var.nombre_proyecto
# }

# resource "railway_service" "app" {
#   project_id = railway_project.medschedule.id
#   name       = "medschedule-app"
# }

# Servicio de base de datos MySQL 8 previsto para la unidad siguiente. Se deja comentado por la
# misma razón que los dos recursos anteriores: aplicarlo sin revisar provisiona infraestructura de
# pago. El nombre exacto del recurso del proveedor "terraform-community-providers/railway" para una
# base de datos gestionada no se verificó en esta entrega (no se instaló Terraform ni se ejecutó
# "terraform providers schema"); se deja el esqueleto con la forma esperada para completarlo cuando
# se aplique.
# resource "railway_service" "mysql" {
#   project_id = railway_project.medschedule.id
#   name       = "mysql"
#   source_image = "mysql:8"
# }

# Nota sobre "var.nombre_entorno": la variable se declara y valida en variables.tf, pero ningún
# recurso de este archivo la usa todavía porque los tres recursos de arriba están comentados. Se
# consumirá (por ejemplo, en el nombre del proyecto o del servicio, o en una variable de entorno del
# servicio de aplicación) cuando esos recursos se descomenten y se aplique la infraestructura real.

output "nota_estado" {
  description = "Recordatorio de que esta configuración es un esqueleto no aplicado."
  value       = "Esqueleto de infraestructura. Sin aplicar. Ver terraform/README.md."
}
