# Esqueleto de Infraestructura como Código — Terraform

## Propósito

Este directorio contiene la declaración de infraestructura para **MedSchedule** usando Terraform, implementando el principio de "software como infraestructura" (Infrastructure as Code, IaC). En lugar de provisionar recursos manualmente desde consolas web, describimos la infraestructura objetivo en archivos de configuración que son versionables, revisables y reproducibles.

La infraestructura se despliega en **Railway**, una plataforma que construye automáticamente aplicaciones siguiendo el archivo `nixpacks.toml` del repositorio. Esta es la razón por la que usamos el proveedor `terraform-community-providers/railway`, un proveedor comunitario que integra Railway con Terraform.

## Estado actual

**Este esqueleto NO está aplicado.** Los recursos se dejan comentados porque:
- La unidad presente (entrega SDD-CI/CD) es fundamentalmente de documentación y andamiaje.
- La aplicación de esta configuración a un proyecto real en Railway es responsabilidad de la unidad siguiente.
- La estrategia de despliegue completa se documenta en `docs/entrega/05-estrategia-despliegue.md` (pendiente de redacción en la Tarea 15).

Por lo tanto, no ejecutes `terraform apply` hasta que:
1. Exista un proyecto confirmado en Railway.
2. El equipo revise y autorice la aplicación de estos recursos.

## Estructura de archivos

- **`providers.tf`**: Declara la versión mínima de Terraform (≥ 1.6.0) y especifica el proveedor de Railway. El token de autenticación se inyecta por variable de entorno, nunca por archivo.
- **`variables.tf`**: Define las variables de entrada:
  - `railway_token` (sensible, sin valor por defecto): Token de API de Railway.
  - `nombre_proyecto` (default: "medschedule"): Nombre del proyecto en Railway.
  - `nombre_entorno` (default: "production"): Entorno de despliegue, validado a "production" o "staging".
- **`main.tf`**: Contiene el esqueleto de recursos (comentados). Prevé:
  - Un proyecto de Railway que agrupa los servicios.
  - Un servicio de aplicación, construido con `nixpacks.toml`.
  - Un servicio de base de datos MySQL 8.
- **`terraform.tfvars.example`**: Plantilla de valores de variables. Cópialo a `terraform.tfvars` para proporcionar valores reales locales. **Nunca commitees `terraform.tfvars` con valores verdaderos.**

## Instalación de Terraform

En macOS:

```bash
brew install terraform
terraform version
```

En otros sistemas operativos, descarga desde https://www.terraform.io/downloads.

## Flujo de trabajo previsto

Una vez que el proyecto en Railway exista y esté autorizado:

1. **Inicializar el directorio de trabajo:**
   ```bash
   cd terraform/
   terraform init
   ```
   Esto descarga el proveedor especificado en `providers.tf` y crea el directorio `.terraform/` (ignorado por git).

2. **Revisar el plan (sin aplicar cambios):**
   ```bash
   terraform plan
   ```
   Muestra los recursos que serán creados, modificados o destruidos. Este paso es crítico: **siempre revisa el plan antes de aplicar.**

3. **Aplicar la configuración:**
   ```bash
   terraform apply
   ```
   Crea o modifica recursos en Railway según la configuración. Terraform mantiene un estado en `terraform.tfstate` (ignorado por git, protegido).

4. **Actualizar recursos futuros:**
   ```bash
   terraform plan
   terraform apply
   ```

## Seguridad: Token de Railway

El token de API de Railway **jamás** debe guardarse en archivos versionados. En su lugar:

- **Desarrollo local:** establece la variable de entorno antes de ejecutar Terraform:
  ```bash
  export TF_VAR_railway_token="tu_token_real"
  terraform plan
  terraform apply
  ```

- **CI/CD (pipeline):** El token se proporciona por el gestor de secretos (GitHub Secrets, GitLab CI/CD variables, etc.), inyectado como `TF_VAR_railway_token` en el entorno del pipeline sin nunca escribirse en archivo.

**Recuerda:** `terraform.tfvars` es ignorado por `.gitignore`, así como `terraform.tfstate` y los archivos de lock. Esto previene que secretos o estado sensible se filtre al repositorio.

## Validación de sintaxis

Para verificar que la configuración de Terraform es sintácticamente correcta (sin ejecutar plan ni apply):

```bash
cd terraform/
terraform validate
```

Este comando no requiere conexión a Railway ni proporciona el token.

## Nota sobre el proveedor comunitario

El proveedor `terraform-community-providers/railway` es mantenido por la comunidad, no es oficial de HashiCorp. Se especifica la versión `~> 0.4` en `providers.tf` para asegurar reproducibilidad. Si en el futuro HashiCorp publica un proveedor oficial, este esqueleto se actualizará en consecuencia.

## Referencias

- Documentación oficial de Terraform: https://www.terraform.io/docs/
- Proveedor comunitario de Railway: https://registry.terraform.io/providers/terraform-community-providers/railway/
- Estrategia de despliegue completa: `docs/entrega/05-estrategia-despliegue.md` (Tarea 15)
