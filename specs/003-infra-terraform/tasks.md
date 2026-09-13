# Tasks: Infraestructura como código con Terraform

- [ ] Verificar que `terraform/providers.tf` declara correctamente el provider de Railway y la versión mínima de Terraform.
- [ ] Verificar que `terraform/variables.tf` no contiene ningún valor sensible hardcodeado.
- [ ] Confirmar que `terraform/terraform.tfvars.example` documenta las variables esperadas sin credenciales reales.
- [ ] Correr `terraform init` en un entorno local y confirmar que descarga el provider sin errores.
- [ ] Correr `terraform validate` y confirmar que no marca errores de sintaxis.
- [ ] Correr `terraform plan` (sin aplicar) y documentar el resultado como evidencia.
- [ ] Documentar en este repo por qué no se ejecuta `terraform apply` en esta unidad (riesgo sobre producción real).
