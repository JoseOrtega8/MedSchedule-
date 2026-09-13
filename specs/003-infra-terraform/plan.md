# Plan: Infraestructura como código con Terraform

## Cómo se implementa

- Se usa el esqueleto ya existente en `terraform/` (creado en el merge de `feat/unidad-docs-sdd`):
    - `providers.tf` — declara el provider de Railway y la versión mínima de Terraform requerida.
    - `variables.tf` — declara las variables de entrada (tokens, nombres de proyecto).
    - `main.tf` — declara los recursos (proyecto, servicio de app, servicio de MySQL).
    - `terraform.tfvars.example` — plantilla de valores de ejemplo, sin credenciales reales.
- No se ejecuta `terraform apply` sobre el proyecto real de Railway en esta unidad — el objetivo es dejar el código validado sintácticamente (`terraform validate`), no aplicado.
- Las variables sensibles (tokens de Railway) se manejan vía `terraform.tfvars` (archivo real, ignorado por git) a partir de la plantilla `.example`.

## Dónde vive en el proyecto

- `terraform/main.tf`
- `terraform/providers.tf`
- `terraform/variables.tf`
- `terraform/terraform.tfvars.example`

## Verificación planeada (sin aplicar)

- `terraform init` — inicializa el proveedor sin tocar infraestructura real.
- `terraform validate` — confirma que la sintaxis es correcta.
- `terraform plan` — genera un plan de cambios _hipotético_ para revisión, sin ejecutarlo.

## Riesgos identificados

- Ejecutar `apply` por error contra el proyecto real de Railway podría recrear o modificar el servicio en producción. Por eso el flujo de esta unidad se detiene deliberadamente en `plan`, nunca en `apply`.
