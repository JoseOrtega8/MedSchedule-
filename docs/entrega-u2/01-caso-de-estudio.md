# 1. Caso de estudio

## 1.1 El sistema

MedSchedule es una aplicación web de gestión de citas médicas construida sobre
Laravel 12.53.0 y PHP 8.2, con vistas Blade, Bootstrap 5 y Vite 7 para el empaquetado
de assets. Tres roles la usan: administrador, doctor y paciente, cada uno con su propio
panel y su propio conjunto de permisos, resueltos con `spatie/laravel-permission`.

El sistema tiene 79 métodos de prueba en PHPUnit y una suite de extremo a extremo con
Playwright, heredadas de la fase anterior. El repositorio es público y vive en
`github.com/JoseOrtega8/MedSchedule-`.

## 1.2 El equipo y su restricción real

Cinco integrantes trabajan sobre el mismo repositorio, pero **los reportes de cada
fase son entregas individuales**. Esa combinación define casi todo lo que sigue:

- Ningún integrante puede imponer al resto una infraestructura que dependa de su
  cuenta personal o de su tarjeta de crédito.
- Nadie puede modificar el trabajo que otro tiene asignado sin pisarlo.
- Cualquier entorno que se proponga tiene que poder levantarlo cualquiera, sin pedir
  permisos ni credenciales a nadie.

No es una restricción académica artificial: es exactamente la situación de un equipo
que entra a un proyecto existente y necesita reproducirlo sin acceso a producción.

## 1.3 El problema que este trabajo resuelve

Al terminar la fase anterior del proyecto, el proyecto tenía integración continua parcial y
ninguna forma de responder a tres preguntas:

1. **¿Aguanta?** No existía ninguna medición de rendimiento. Nadie sabía cuánto tarda
   la aplicación en responder bajo uso concurrente, ni a partir de cuántos usuarios
   deja de ser usable.
2. **¿Está sano el código?** No había análisis estático. Los defectos se encontraban
   leyendo diffs en las revisiones, que es el método más caro y el que más se cansa.
3. **¿Dónde se libera?** La documentación de despliegue apuntaba a un hosting IONOS
   que ya no existe, y los checklists de ese hosting contienen credenciales en claro.
   El repositorio es público: esa documentación no puede versionarse.

Las tres preguntas comparten una raíz: **no había un entorno de liberación definido**.
Sin un lugar reproducible donde levantar la aplicación, ni medir el rendimiento ni
desplegar significan nada, porque los resultados dependerían de la máquina de quien
los ejecutó.

## 1.4 Lo que esta entrega construye

| Pregunta | Respuesta de este trabajo |
|---|---|
| ¿Dónde se libera? | Un entorno declarado como código en `.devcontainer/`, que GitHub Codespaces materializa bajo demanda |
| ¿Aguanta? | Pruebas de carga con k6 y un nivel de servicio acordado: p95 por debajo de 5 s |
| ¿Está sano el código? | SonarQube Community en un stack local, con el escaneo del PR de la fase anterior |
| ¿Cómo se automatiza? | Un pipeline de cuatro etapas que invoca los mismos scripts que se usan en local |

## 1.5 El hilo que recorre el documento

Las pruebas de carga de este trabajo no solo midieron: **encontraron tres defectos
reales antes de que llegaran a producción**, uno de ellos visible para cualquier
visitante anónimo del sitio. Ese hallazgo, documentado en el apartado 7, es el mejor
argumento a favor del pipeline que este documento justifica: la compuerta de
rendimiento no es un trámite, es lo que separa medir de suponer.
