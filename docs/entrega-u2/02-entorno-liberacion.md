# 2. Entorno requerido para la liberación y el despliegue

## 2.1 Qué exige el entorno

Para que una liberación signifique algo, el entorno donde se prueba tiene que cumplir
cuatro condiciones:

1. **Reproducible.** Dos integrantes que lo levanten obtienen lo mismo, hasta la
   versión de PHP y el motor de base de datos.
2. **Desechable.** Se crea y se destruye sin consecuencias, para que una prueba de
   carga que deje la base sucia no contamine la siguiente.
3. **Declarado en el repositorio.** Si el entorno vive en la cabeza de alguien o en
   su máquina, no es un entorno: es una costumbre.
4. **Sin coste ni titular único.** Cinco personas con entregas individuales no pueden
   depender de la cuenta de pago de una de ellas.

## 2.2 Decisión: GitHub Codespaces

El entorno de liberación y despliegue de MedSchedule pasa a ser **GitHub Codespaces**,
descrito por completo en `.devcontainer/`.

| Archivo | Qué declara |
|---|---|
| `.devcontainer/devcontainer.json` | Imagen base PHP 8.2, Node 20, Docker dentro de Docker, GitHub CLI, puertos publicados y comando de creación |
| `.devcontainer/docker-compose.yml` | Servicio de aplicación y servicio MySQL 8 con su comprobación de salud y su volumen de datos |
| `.devcontainer/post-create.sh` | Instalación idempotente: Composer, npm, `.env`, clave de aplicación, k6 y generación del entorno |

Un integrante abre el Codespace desde la rama que quiera y obtiene la aplicación lista.
No instala PHP, no instala MySQL, no configura nada. El entorno **es** un artefacto
versionado: quien abra un Codespace desde `main` obtiene exactamente la versión que
`main` describe.

### Por qué cumple las cuatro condiciones

| Condición | Cómo la cumple |
|---|---|
| Reproducible | La imagen y las versiones están fijadas en archivos versionados |
| Desechable | Borrar el Codespace destruye la base de datos y el volumen |
| Declarado | Vive en `.devcontainer/`, dentro del repositorio |
| Sin titular único | Cada integrante consume su propia cuota gratuita de GitHub; nadie paga por nadie |

### Qué no resuelve

Conviene decirlo con claridad en lugar de venderlo de más: un Codespace **no es un
servidor de producción**. Se suspende por inactividad, su URL cambia entre instancias
y su cuota es limitada. Para esta asignatura es el entorno de liberación y de
despliegue verificable; para un sistema en operación real haría falta un servicio con
disponibilidad garantizada, y la transición sería directa porque el pipeline no
depende de Codespaces: depende de los scripts de `scripts/`.

## 2.3 Por qué se descarta IONOS

La documentación de despliegue heredada apuntaba a un hosting IONOS. Se descarta por
tres razones, en orden de gravedad:

1. **Credenciales en claro.** Los archivos `docs/IONOS_Deploy_Checklist.md` y
   `docs/MedSchedule_Estructura_DB_IONOS.md` contienen host, usuario y contraseña
   escritos directamente. El repositorio es público. Están excluidos del control de
   versiones a propósito y se conservan solo en local como registro histórico. Un
   entorno cuyo procedimiento de acceso no se puede versionar no se puede automatizar.
2. **La infraestructura ya no existe.** El hosting fue dado de baja. Mantener su
   documentación como referencia de despliegue describe algo que no se puede levantar.
3. **Despliegue manual por FTP.** No deja registro de quién desplegó qué ni cuándo, y
   no se puede encadenar a una compuerta de calidad.

Durante esta entrega se detectó, además, que las reglas de exclusión de esos dos
archivos existían **solo en la rama de la fase anterior**. En `develop` aparecían
como archivos sin rastrear: un `git add` amplio los habría publicado. La corrección va
en el primer commit de esta entrega.

## 2.4 Por qué se descarta Railway

La fase anterior dejó esbozado un despliegue en Railway, con un workflow manual
(`cd-railway.yml`, aún en el PR #95 sin integrar) y un esqueleto de Terraform sin
aplicar. Se descarta por dos razones:

1. **Requiere medio de pago y un titular único.** El proyecto queda atado a la cuenta
   personal de un integrante. Con entregas individuales, los demás no podrían ni
   desplegar ni verificar.
2. **Nunca llegó a existir.** El workflow comprueba que el secreto `RAILWAY_TOKEN`
   esté presente y falla si no lo está; el proyecto de Railway nunca se creó. Documentar
   como entorno de despliegue algo que jamás se levantó sería describir una intención,
   no un entorno.

El esqueleto de Terraform se conserva como constancia del trabajo previo, sin aplicar.

## 2.5 Comparación

| Criterio | IONOS | Railway | Codespaces |
|---|---|---|---|
| Existe hoy | No | No | Sí |
| Coste para el equipo | Hosting contratado | Requiere tarjeta | Cuota gratuita por cuenta |
| Titular | Único | Único | Cada integrante el suyo |
| Declarado como código | No | Parcial, sin aplicar | Sí, `.devcontainer/` |
| Credenciales versionables | No, van en claro | Secretos de repositorio | Sin credenciales propias |
| Reproducible por cualquiera | No | No | Sí |
| Desechable | No | Sí | Sí |

## 2.6 Cómo se levanta

```bash
# Desde la máquina de cualquier integrante, sin instalar nada previo.
gh codespace create --repo JoseOrtega8/MedSchedule- --branch <rama>
gh codespace ssh

# Ya dentro, el entorno se genera con un solo comando.
bash scripts/entorno-liberacion.sh
```

El `postCreateCommand` del devcontainer ya dejó instaladas las dependencias, el
archivo `.env`, la clave de aplicación y k6.
