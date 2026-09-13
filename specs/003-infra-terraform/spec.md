# Spec: Infraestructura como código con Terraform

## 1. Resumen

MedSchedule se despliega hoy en Railway de forma manual: la configuración de servicios (app + MySQL), variables de entorno y conexión al repositorio se hace a mano desde el panel web de Railway. Esta especificación define **qué** debería lograr una capa de infraestructura como código (IaC) con Terraform para ese mismo entorno, sin implementarla todavía — solo se documenta el esqueleto declarativo (`terraform/main.tf`, `providers.tf`, `variables.tf`) ya presente en el repositorio.

## 2. Problema / necesidad

- La configuración actual de Railway vive únicamente en la interfaz web: no hay forma de reproducirla en otro entorno (staging, otra cuenta) sin repetir los pasos manualmente.
- No existe registro versionado de qué variables de entorno, servicios y configuración de red se usan en producción.
- Un cambio de infraestructura (agregar una réplica, cambiar una variable) no deja rastro en el control de versiones.

## 3. Alcance

**Dentro del alcance de esta spec:**

- Declarar el proveedor de Terraform para Railway (`terraform-community-providers/railway`).
- Declarar como recursos: el proyecto de Railway, el servicio de la aplicación, el servicio de MySQL, y las variables de entorno necesarias.
- Documentar el flujo esperado: `terraform init` → `terraform plan` → `terraform apply`.

**Fuera del alcance (explícitamente, igual que los pilotos de Ramón):**

- Ejecutar `terraform apply` de verdad contra el proyecto real de Railway (no se aplica en esta unidad, solo se especifica).
- Migrar el proyecto actual (ya desplegado manualmente) a que sea gestionado por Terraform — eso implicaría riesgo de romper el despliegue vivo.
- Balanceo de carga o multi-región (fuera de alcance del piloto).

## 4. Requisitos funcionales

- RF1: El código Terraform debe declarar el proveedor de Railway con la versión fijada en `providers.tf`.
- RF2: Debe declarar las variables sensibles (tokens, credenciales) como variables de Terraform, nunca como valores hardcodeados — ejemplo en `terraform.tfvars.example`.
- RF3: Debe ser posible correr `terraform plan` sin errores de sintaxis, generando un plan de ejecución legible (aunque no se aplique).
- RF4: La documentación debe explicar claramente por qué no se ejecutó `apply` en esta unidad (riesgo sobre el entorno productivo real).

## 5. Requisitos no funcionales

- El código debe ser legible por alguien sin experiencia previa en Terraform (comentarios claros).
- Debe seguir la convención de nombres ya usada en el resto del repo (español para descripciones, inglés para nombres técnicos).

## 6. Criterios de aceptación

- [ ] `terraform/main.tf`, `providers.tf` y `variables.tf` existen y declaran los recursos descritos.
- [ ] `terraform validate` no marca errores de sintaxis.
- [ ] Existe un archivo `terraform.tfvars.example` documentando qué variables se necesitan, sin valores reales.
- [ ] Esta spec documenta explícitamente que no se ejecutó `apply`.

## 7. Preguntas abiertas / seguimiento

- ¿En qué unidad futura se plantea ejecutar `terraform apply` de verdad, y contra qué entorno (uno de prueba, no producción)?
- ¿Vale la pena mover también la configuración de CI/CD (`cd-railway.yml`) a Terraform, o quedan como herramientas separadas?
