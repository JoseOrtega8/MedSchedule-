# 4. Métricas para el monitoreo de la aplicación

## 4.1 Qué se mide y por qué

Una métrica sin decisión asociada es ruido. Cada una de las siguientes existe porque
responde a una pregunta que el equipo necesita contestar.

### Métricas de latencia

| Métrica | Pregunta que responde | Fuente | Umbral |
|---|---|---|---|
| `http_req_duration` p95 | ¿Cumple el sistema el acuerdo de servicio? | k6 | < 5000 ms |
| `http_req_duration` p99 | ¿Cuánto espera el usuario más desafortunado? | k6 | Sin umbral, se vigila |
| `http_req_waiting` | ¿El tiempo se va en el servidor o en la red? | k6 | Sin umbral, diagnóstico |
| `duracion_panel` p95 | ¿El endpoint más costoso aguanta? | k6, métrica propia | < 5000 ms |
| `duracion_login_post` | ¿Cuánto cuesta autenticar, incluido `bcrypt`? | k6, métrica propia | Sin umbral, diagnóstico |

La separación entre `http_req_duration` y `http_req_waiting` es la que permite decidir
dónde optimizar: si la diferencia es grande, el problema es de transferencia; si son
casi iguales, el servidor es el cuello de botella.

### Métricas de corrección

| Métrica | Pregunta que responde | Fuente | Umbral |
|---|---|---|---|
| `http_req_failed` | ¿Qué proporción de peticiones falla? | k6 | < 1 % |
| `checks` | ¿La respuesta es la que el negocio espera? | k6 | Se vigila |
| `tasa_login_exitoso` | ¿La autenticación funciona bajo carga? | k6, métrica propia | > 99 % |
| `tasa_about_correcto` | ¿La vista pública responde 200? | k6, métrica propia | Se vigila, hoy expone el defecto #97 |
| `errores_negocio` | ¿Hay fallos que no son de red? | k6, métrica propia | Se vigila |

La distinción importa: una petición puede devolver 200 y ser incorrecta. Cuando la
sesión de Laravel caduca, el panel redirige al formulario de login y k6 sigue la
redirección, de modo que la respuesta llega con código 200 y HTML en lugar de JSON.
`http_req_failed` no lo detecta; el `check` de tipo de contenido sí.

### Métricas de capacidad

| Métrica | Pregunta que responde | Fuente |
|---|---|---|
| `http_reqs` | ¿Cuántas peticiones por segundo soporta? | k6 |
| `iterations` / `iteration_duration` | ¿Cuántos recorridos completos de usuario se logran? | k6 |
| `vus` / `vus_max` | ¿Cuántos usuarios simultáneos se sostuvieron? | k6 |
| `data_received` / `data_sent` | ¿Cuánto tráfico genera? | k6 |
| `respuestas_limitadas` | ¿Cuántas veces actuó el limitador de intentos? | k6, métrica propia |

`data_received` no es decorativa: en la corrida con `/about` roto, el sistema devolvía
139 MB en 60 segundos porque cada error 500 generaba una página de depuración de unos
945 KB. Esa cifra, comparada con los 12 MB de la corrida correcta, es la que delata el
coste de un error no controlado bajo carga.

### Métricas de calidad del código

| Métrica | Pregunta que responde | Fuente | Estado |
|---|---|---|---|
| Incidencias de seguridad | ¿Hay riesgos que las reglas detecten? | SonarQube | 0, calificación A |
| Incidencias de fiabilidad | ¿Hay código que pueda fallar en ejecución? | SonarQube | **2, calificación C** |
| Incidencias de mantenibilidad | ¿Cuánto cuesta mantener esto? | SonarQube | 94, 447 min de deuda |
| Duplicación | ¿Cuánto código repetido hay? | SonarQube | 2.8 % |
| Cobertura | ¿Qué proporción del código ejercitan las pruebas? | PHPUnit vía SonarQube | Pendiente, ver §8.5 |

Las métricas de SonarQube usan la taxonomía Clean Code de la versión 26. Las antiguas
(`bugs`, `code_smells`, `reliability_rating`) están deprecadas y **contradicen a la
interfaz**; el apartado §8.3 lo detalla.

## 4.2 Umbral contractual y umbral de vigilancia

El acuerdo de la unidad fija el p95 en 5 s. La medición real en el entorno de liberación
es de 76 ms. Un umbral sesenta y seis veces mayor que la medición no detecta
degradaciones: el rendimiento podría empeorar un 6 000 % sin que nadie se entere.

Por eso se proponen dos niveles:

| Nivel | Umbral | Qué provoca |
|---|---|---|
| **Contractual** | p95 < 5000 ms | Detiene la liberación. Es el acuerdo del apartado 3 |
| **De vigilancia** | p95 < 300 ms | No detiene nada; avisa de que algo cambió |

El segundo nivel es el que detectaría una consulta N+1 recién introducida. El primero
solo se enteraría cuando el sistema ya fuera inusable.

## 4.3 Cómo se recogen hoy

```bash
bash scripts/pruebas-liberacion.sh
```

Genera dos archivos:

| Archivo | Contenido |
|---|---|
| `tests/carga/resultados/jri-resumen.json` | Resumen agregado: todas las métricas con sus percentiles |
| `tests/carga/resultados/jri-metricas.json` | Un registro por punto de datos, para analizar la serie completa |

En el pipeline, el paso `Publicar los resultados de la prueba de carga` los sube como
artefacto con 30 días de retención, de modo que dos corridas separadas en el tiempo se
puedan comparar.

## 4.4 Qué no se mide todavía

Conviene ser explícito sobre los huecos, que son los que da la cara la unidad siguiente:

| Hueco | Consecuencia |
|---|---|
| No hay recolección continua | Solo se sabe cómo se comportaba el sistema en el momento de liberar |
| No hay alertas | Nadie se entera de una degradación hasta que alguien mira |
| No hay métricas de infraestructura | No se mide CPU, memoria ni conexiones de base de datos |
| No hay histórico consultable | Comparar corridas exige descargar artefactos a mano |
| No hay trazas | Se sabe que una petición tardó, no en qué parte del código |

## 4.5 Interpretación de la corrida de esta unidad

| Métrica | Valor | Lectura |
|---|---|---|
| `http_req_duration` p95 | 75.92 ms | Sesenta y seis veces por debajo del acuerdo |
| `http_req_duration` mediana | 20.19 ms | La mayoría de peticiones son inmediatas |
| `http_req_duration` p99 | 277.40 ms | La cola la marcan las autenticaciones |
| `duracion_panel` p95 | 67.51 ms | El endpoint más costoso es, en realidad, muy rápido |
| `duracion_login_post` p95 | 290.89 ms | Aquí vive el coste: `BCRYPT_ROUNDS=12` |
| `http_req_failed` | 0 % | Ninguna respuesta inesperada |
| `checks` | 100 % (2586 de 2586) | Todas las aserciones funcionales correctas |
| `respuestas_limitadas` | 14 | El limitador actuó 14 veces en dos minutos |

La conclusión operativa es clara: **el cuello de botella no es la base de datos, es el
hash de contraseñas**. El panel, que consulta tres veces la base, responde en 67 ms;
autenticar cuesta más de cuatro veces eso. Es el comportamiento correcto — `bcrypt` está
diseñado para ser caro — pero conviene saberlo antes de optimizar consultas que no lo
necesitan.

## 4.6 Trabajo pendiente para la unidad siguiente

Los huecos del apartado 4.4 se cubren con un stack de observabilidad continua. El
diseño previsto, no implementado en esta entrega:

| Componente | Función |
|---|---|
| Prometheus | Recolección y almacenamiento de series temporales |
| Grafana | Paneles y visualización del histórico |
| Alertmanager | Alertas por umbral con enrutamiento de avisos |

k6 puede exportar sus métricas directamente a Prometheus mediante
`--out experimental-prometheus-rw`, de modo que las mismas métricas de esta unidad
alimentarían los paneles sin reescribir la prueba. El stack se añadiría al mismo
`docker compose` que ya levanta el entorno, que es la razón por la que el entorno se
declaró como código desde el principio.
