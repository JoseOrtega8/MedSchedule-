# 06 — Guión del video demo (criterio SA)

Este documento es el guión para grabar el video que exige el criterio SA de la rúbrica: "todos los
puntos más video explicativo". Es una **explicación narrada de cinco minutos**, no una demostración
en vivo: nada de lo que se muestra en pantalla es un comando ejecutándose ni un servidor
arrancando — todo lo que aparece es un documento o un archivo que ya existe en el repositorio,
abierto para leerlo o señalarlo mientras se habla. Cada cifra que se menciona ya está verificada en
el documento que se cita en cada tramo; este guión no repite esa verificación, solo la resume para
que se pueda explicar en voz alta dentro del tiempo asignado.

## 1. Checklist previo a grabar

Solo lo necesario para grabar una explicación, sin nada que ejecutar:

| # | Qué preparar |
|---|---|
| 1 | Tener abiertos en el editor, en pestañas o ventanas accesibles: `docs/entrega/00-indice.md` a `06-guion-video-demo.md`, y `docs/sdd/sdd-proposal.md` y `sdd-implementation.md` |
| 2 | Tener a la vista el árbol de archivos del repositorio (en el editor o en el explorador de GitHub), para señalar `specs/`, `.specify/` y `terraform/` sin necesidad de listarlos por comando |
| 3 | Tener abiertos, sin ejecutarlos, los archivos ya capturados `docs/entrega/evidencia/phpunit-baseline.txt` y `docs/entrega/evidencia/playwright-baseline.txt`, por si se quiere mostrar el resultado real del suite como texto en pantalla |
| 4 | Cerrar notificaciones y aplicaciones que puedan interrumpir la grabación o el audio |
| 5 | Hacer una prueba corta de audio y de captura de pantalla antes de grabar la toma completa |
| 6 | Tener la tabla de la sección 2 de este documento a la vista mientras se graba, como guía de tiempos y de texto, sin necesidad de memorizarlo palabra por palabra |

## 2. Guión con tiempos

Duración objetivo: **5 minutos**. Los tiempos son de inicio de tramo, no cronómetro estricto — el
texto de "qué se dice" está calculado para leerse con calma dentro del tiempo del tramo.

| Tiempo | Qué se muestra en pantalla | Qué se dice |
|---|---|---|
| 0:00–0:30 | `README.md` del repositorio, abierto en el título y la descripción del proyecto | "MedSchedule es una aplicación Laravel para gestión de citas médicas: pacientes, doctores y administradores, con roles reales de autorización y sincronización con Google Calendar. Este video cubre la entrega de la unidad de documentación, pruebas y CI/CD: seis documentos numerados en `docs/entrega/`, dos documentos de Spec-Driven Development, un plan de pruebas con ejecución real, y un esqueleto de infraestructura como código con Terraform." |
| 0:30–1:15 | `docs/sdd/sdd-implementation.md`, sección "Qué es Spec-Driven Development" | "Antes de tocar código, esta entrega documenta por qué. MedSchedule no tenía ningún artefacto de especificación previo al código: los requisitos vivían en issues de GitHub, y el código se escribía primero. Spec-Driven Development invierte ese orden: primero una especificación aprobada, con historias de usuario y criterios de aceptación verificables; después el plan técnico; después las tareas; y solo entonces el código. Esta entrega instala Spec Kit, la herramienta que formaliza ese flujo, y lo demuestra con dos especificaciones piloto ya escritas, aunque todavía sin implementar: ampliar la cobertura de pruebas end-to-end, y agregar tours guiados con driver.js." |
| 1:15–2:00 | `docs/entrega/01-configuracion-herramientas.md`, tabla de inventario; luego `docs/entrega/02-plan-de-pruebas.md`, sección de niveles de prueba | "Punto 1: configuración de herramientas. El inventario documenta 16 herramientas con su versión exacta y su criterio de elección — por ejemplo, PHP 8.2 y Node 20 fijos en CI para reproducibilidad, aunque local use lo que trae MAMP. Punto 2: plan de pruebas. Define tres niveles — unitario, feature y end-to-end — y justifica Playwright sobre Selenium o Katalon porque ya está integrado al proyecto, corre headless de fábrica y comparte JavaScript con el resto del código. El plan también describe, sin ejecutarla en este video, la corrida real que ya se hizo del suite completo." |
| 2:00–3:00 | `docs/entrega/evidencia/phpunit-baseline.txt` (ya capturado); luego `docs/entrega/02-plan-de-pruebas.md`, sección 6.4 (taxonomía) | "Esto es lo que realmente demuestra trabajo, no documentación copiada: la ejecución real del suite. El resultado, ya capturado en esta evidencia: 64 pruebas pasan, 15 fallan, 149 aserciones. Y los 15 fallos no son un solo problema — la sección 6.4 del plan de pruebas los separa en tres grupos con causa raíz distinta. Diez fallos son tests obsoletos que nunca autentican: siguen probando un mecanismo de sesión simulada que ya no protege ninguna ruta, así que sembrar roles no los arreglaría. Tres fallos son una divergencia real entre código y test en la ruta `/dashboard`: la aplicación redirige distinto de lo que el test espera, y aquí sí hay un comportamiento cuestionable del propio sistema. Y dos fallos apuntan a un defecto probable en la integración con Google Calendar: el mock del servicio se invoca de forma distinta a la esperada, posiblemente por el despacho asíncrono del trabajo de sincronización." |
| 3:00–3:45 | `docs/entrega/04-flujo-cicd.md`, hallazgo central del pipeline; luego `docs/entrega/05-estrategia-despliegue.md`, sección 8 (encabezado de defectos) | "Punto 4: control de versiones y CI/CD. El hallazgo central: `ci.yml` tiene tres pasos con `\|\| true` que nunca reportan fallo, y un filtro que solo corre 5 de las 20 clases de prueba. El check verde de GitHub Actions hoy no certifica que el código funcione. Punto 5: estrategia de despliegue con Railway. Este documento verifica cuatro defectos de despliegue que hoy impedirían un despliegue exitoso: un archivo de assets fuera del build, un contenedor que no arrancaría, una dependencia no declarada, y migraciones que correrían dos veces. Aparte, documenta un incidente de seguridad, ya resuelto en esta misma entrega: unos documentos con credenciales en claro que estuvieron a punto de commitearse." |
| 3:45–4:30 | `.specify/memory/constitution.md`; árbol de archivos de `specs/` y `terraform/` | "Para la unidad siguiente queda instalado y listo: Spec Kit, con su constitución de siete principios de proyecto; dos skills propias — una que investiga el dominio antes de escribir una especificación, y otra que convierte una especificación aprobada en casos de prueba, y que ya prohíbe por escrito el patrón que causó diez de los quince fallos que acabamos de ver; dos especificaciones piloto completas, listas para implementarse; el esqueleto de Terraform para Railway, sin aplicar a propósito; y el flujo de despliegue continuo, con disparo manual y verificación del token antes de desplegar." |
| 4:30–5:00 | `docs/entrega/00-indice.md`, sección de conclusión | "En resumen: esta unidad no corrige nada del sistema — lo audita. Documenta parámetros de configuración, un plan de pruebas ejecutado de verdad, el flujo de CI/CD y la estrategia de despliegue, con sus defectos reales verificados y no maquillados. Lo que sigue es la unidad siguiente: corregir los quince fallos por grupo, resolver los cuatro defectos de despliegue, y quitar los `\|\| true` del pipeline. Gracias por ver." |

## 3. Plan de contingencia

Regla general: **un tropiezo al narrar no detiene la grabación**, se retoma desde el inicio del
tramo en curso.

| Situación | Qué hacer |
|---|---|
| Se pierde el hilo de la narración a mitad de un tramo | Pausar, respirar, y retomar desde el inicio de ese mismo tramo — no desde el principio del video |
| Un documento o archivo citado no abre o cambió de ruta | Decirlo en voz alta ("este archivo cambió de ruta desde que se escribió el guión") y continuar con el siguiente punto, sin detener la grabación |
| Se agota el tiempo antes de llegar al cierre | Comprimir el tramo de hallazgos (2:00–3:00) a una frase por grupo de fallos, sin recortar el cierre |
| Falla el audio o la captura a mitad de grabación | Detener, corregir, y regrabar solo el tramo afectado si el editor de video permite empalmarlo; si no, repetir la toma completa |
