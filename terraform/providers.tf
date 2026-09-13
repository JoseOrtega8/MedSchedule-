# Declaración de proveedores. El proveedor de Railway es de la comunidad;
# se fija la versión para que la infraestructura sea reproducible.
terraform {
  required_version = ">= 1.6.0"

  required_providers {
    railway = {
      source  = "terraform-community-providers/railway"
      version = "~> 0.4"
    }
  }
}

# El token JAMÁS se escribe aquí. Se inyecta por variable de entorno
# TF_VAR_railway_token o por el gestor de secretos del pipeline.
provider "railway" {
  token = var.railway_token
}
