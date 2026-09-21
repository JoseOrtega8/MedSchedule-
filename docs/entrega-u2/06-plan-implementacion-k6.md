# 6. Plan de implementación de las pruebas de carga

Este apartado es el plan que se presentó en el pull request de implementación, antes
de escribir una sola línea de la prueba.

## 6.1 Elección de la herramienta

| Herramienta | A favor | En contra | Decisión |
|---|---|---|---|
| **k6** | Scripts en JavaScript, umbrales integrados que devuelven código de salida, métricas propias, salida JSON | Requiere instalar un binario | **Elegida** |
| JMeter | Interfaz gráfica, muy extendido | Plan de pruebas en XML, difícil de revisar en un diff; requiere Java | Descartada |
| Apache Benchmark (`ab`) | Ya instalado en macOS, inmediato | No mantiene sesión ni CSRF, no expresa umbrales, una sola URL por ejecución | Descartada |

El factor decisivo es el flujo autenticado. MedSchedule guarda la sesión en cookies y
protege los formularios con token CSRF: `ab` no puede recorrer ese flujo, y en JMeter
el plan resultante no se revisa cómodamente en un pull request. Los umbrales de k6,
además, hacen de compuerta del pipeline sin escribir código adicional: k6 devuelve el
código de salida 99 cuando un umbral se incumple.

## 6.2 Instalación

### Linux (Codespaces y GitHub Actions)

```bash
sudo gpg --no-default-keyring \
    --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
    --keyserver hkp://keyserver.ubuntu.com:80 \
    --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
    | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install -y k6
```

### macOS

```bash
brew install k6
```

### Verificación

```bash
k6 version
```

Los tres casos están automatizados e son idempotentes en `scripts/instalar-k6.sh`: si
k6 ya está presente, el script no hace nada. Versión verificada en esta entrega:
**k6 v2.2.0**.

## 6.3 Endpoints elegidos y por qué

Los endpoints salen de `routes/web.php` y `routes/auth.php`, escogidos por frecuencia
de uso real, no por conveniencia.

| # | Endpoint | Método | Autenticación | Por qué está en la prueba |
|---|---|---|---|---|
| 1 | `/` | GET | No | Portada. La primera petición de todo visitante |
| 2 | `/about` | GET | No | Vista pública enlazada desde toda la aplicación |
| 3 | `/login` | GET | No | Se pide en cada sesión; de aquí sale el token CSRF |
| 4 | `/login` | POST | No | Autenticación real, incluye el coste de `bcrypt` |
| 5 | `/patient/dashboard/data` | GET | Sí, rol paciente | **El más costoso.** Tres consultas por petición: citas próximas, agregado por estado y perfil |

El quinto es el que justifica la prueba. `DashboardController::patientDashboard()`
ejecuta una consulta de citas futuras con filtro de fecha y estado, un `GROUP BY` de
citas por estado, y una relación de perfil. Es el endpoint que más trabajo de base de
datos hace por petición en todo el sistema.

## 6.4 Diseño de la prueba

```
Rampa de subida     30 s   0 → 10 usuarios virtuales
Meseta sostenida    60 s   10 usuarios virtuales
Rampa de bajada     30 s   10 → 0 usuarios virtuales
```

Guion de cada iteración:

1. `GET /` y `GET /about` — recorrido público.
2. Si el usuario virtual aún no tiene sesión: `GET /login`, extraer token CSRF,
   `POST /login`.
3. `GET /patient/dashboard/data` con la sesión viva.
4. Pausa de un segundo.

## 6.5 Nomenclatura por integrante

El requisito pide un script por integrante con el patrón `iniciales+prueba.js`. Esta
entrega aporta el del autor:

| Script | Autor | Endpoints |
|---|---|---|
| `tests/carga/jri-prueba.js` | `ramonibr` | Los cinco de la tabla anterior |

El directorio `tests/carga/` queda preparado para que cada integrante añada el suyo
sin colisionar: los scripts son independientes y `CargaSeeder` siembra cuentas
suficientes para diez usuarios virtuales simultáneos.

## 6.6 Datos de prueba

`database/seeders/CargaSeeder.php` crea diez cuentas de paciente
(`carga1@test.com` … `carga10@test.com`), cada una con perfil médico y ocho citas
repartidas entre pasado y futuro. Las citas importan: sin ellas, las consultas del
panel se resolverían sobre un conjunto vacío y la latencia medida no significaría nada.

La contraseña llega por la variable `K6_PASSWORD`. **Ninguna credencial se escribe en
el repositorio.**

## 6.7 Riesgos previstos en el plan

| Riesgo | Mitigación prevista |
|---|---|
| El coste de `bcrypt` domina la medición | Autenticarse una vez por usuario virtual, no en cada iteración |
| Un limitador de intentos interfiere | Una cuenta distinta por usuario virtual |
| La sesión se pierde entre iteraciones | Revisar la conducta del almacén de cookies de k6 |
| El p95 supera el acuerdo | Diagnosticar antes de tocar el umbral. Subir el umbral para que pase es falsificar la medición |

Los tres primeros riesgos **se materializaron**. El apartado 7 documenta qué pasó
exactamente y cómo se resolvió cada uno.

## 6.8 Criterios de aceptación del plan

- [x] Más de 5 usuarios virtuales.
- [x] Al menos un endpoint; la prueba recorre cinco.
- [x] La mayor cantidad posible de métricas: nueve propias más las de k6.
- [x] `http_req_duration` p95 por debajo de 5000 ms.
- [x] Ninguna credencial en el repositorio.
- [x] Resultados commiteados en markdown.
