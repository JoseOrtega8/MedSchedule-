# Token de API de Railway. Marcado como sensible para que Terraform no lo
# imprima en los planes ni en los logs. Nunca tiene valor por defecto.
variable "railway_token" {
  description = "Token de API de Railway. Se inyecta via TF_VAR_railway_token."
  type        = string
  sensitive   = true
}

variable "nombre_proyecto" {
  description = "Nombre del proyecto en Railway."
  type        = string
  default     = "medschedule"
}

variable "nombre_entorno" {
  description = "Entorno objetivo del despliegue."
  type        = string
  default     = "production"

  validation {
    condition     = contains(["production", "staging"], var.nombre_entorno)
    error_message = "El entorno debe ser production o staging."
  }
}
