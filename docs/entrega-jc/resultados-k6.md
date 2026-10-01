# Resultados de la prueba de carga — Dashboard del administrador (JoseOrtega8)

## Endpoint probado

`GET /dashboard/data` — estadísticas del dashboard de administrador, cacheadas con
`DashboardStatsService` (`Cache::remember`, TTL 120s). Ver PR #78 de la Unidad de
Pruebas y Liberación, donde se redujo de 19 a 1 query por carga gracias al cache.

## Nivel de servicio acordado

p95 de la latencia por debajo de 5 segundos (5000 ms).

## Configuración de la prueba

- Herramienta: k6 v2.3.0
- Usuarios virtuales: 5
- Duración: 30 segundos
- Script: `tests/carga/jc-prueba.js`
- Autenticación: login único en `setup()`, reutilizado por los 5 VUs (para no
  disparar el rate limiter de login de 5 intentos/minuto construido en la Unidad
  de seguridad, PR #69).

## Resultado

| Métrica                       | Valor                                |
| ----------------------------- | ------------------------------------ |
| p95 `duracion_dashboard_data` | **539.12 ms** ✅ (umbral: < 5000 ms) |
| Promedio                      | 199.01 ms                            |
| Mínimo                        | 110.07 ms                            |
| Máximo                        | 895.29 ms                            |
| Tasa de respuesta 200         | 100.00% (110 de 110)                 |
| Total de peticiones HTTP      | 222                                  |
| Peticiones fallidas           | 0.45% (1 de 222)                     |
| Usuarios virtuales máximos    | 5                                    |

```
✓ 'p(95)<5000' p(95)=539.12ms
```

## Umbral (threshold) de k6

El umbral configurado directamente en el script (`thresholds: { duracion_dashboard_data: ["p(95)<5000"] }`)
se cumplió sin problema, con amplio margen (539ms vs el límite de 5000ms).

## Evidencia

![Resultado de k6](evidencia/k6-dashboard-resultado.png)

## Nota sobre el check de login

El check `login responde redirect (302)` no se cumplió (devolvió otro código de
respuesta exitoso en vez de 302 exacto), pero esto no afectó la prueba: el
`dashboard responde 200` se cumplió el 100% de las veces, confirmando que la
sesión sí quedó autenticada correctamente para las 5 VUs.

## Conclusión

El endpoint cacheado del dashboard cumple el nivel de servicio acordado con
amplio margen bajo una carga de 5 usuarios virtuales concurrentes durante 30
segundos, confirmando en condiciones de carga real la mejora medida en la
Unidad de Pruebas y Liberación (19 → 1 query por carga gracias al cache).
