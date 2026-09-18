# 9. Cuándo usar las pruebas de carga y cómo se integran

## 9.1 Cuándo tiene sentido ejecutarlas

Una prueba de carga cuesta tiempo de reloj y de máquina. Ejecutarla siempre es tan
inútil como no ejecutarla nunca.

| Momento | ¿Se ejecuta? | Por qué |
|---|---|---|
| En cada commit local | **No** por defecto | Dos minutos por commit haría que nadie commitease. El hook solo lanza una prueba de humo, y únicamente si hay un entorno escuchando |
| Al abrir o actualizar un PR | **Sí** | Es el punto donde el cambio aún es barato de corregir |
| Al integrar en `develop` | **Sí** | Verifica que la suma de cambios no degradó nada |
| Antes de desplegar a `main` | **Sí, como compuerta** | Es el acuerdo de servicio: no se libera lo que no cumple |
| Tras tocar consultas, índices o vistas Blade pesadas | **Sí, a mano** | Son los cambios que degradan latencia sin romper ninguna prueba funcional |
| Tras cambiar solo documentación | **No haría falta** | Hoy se ejecuta igual; afinarlo con filtros de ruta es trabajo pendiente |

## 9.2 Integración en GitHub Actions

Archivo: `.github/workflows/release.yml`

```
integracion ──▶ pruebas (entorno + PHPUnit + k6) ──▶ despliegue
   Pint/Prettier      Compuerta de p95              Solo en main
   sintaxis PHP       Artefacto de resultados       Verificación de salud
```

La compuerta no necesita código propio: **k6 termina con código de salida 99 cuando un
umbral se incumple**, y `scripts/pruebas-liberacion.sh` corre con `set -e`. Un p95 por
encima de 5 s detiene el job, y con él la etapa de despliegue, que depende de esta por
`needs`.

Los resultados se publican como artefacto con 30 días de retención:

```yaml
- name: Publicar los resultados de la prueba de carga
  if: always()
  uses: actions/upload-artifact@v4
  with:
      name: resultados-carga-${{ github.run_number }}
      path: tests/carga/resultados/
      retention-days: 30
```

El `if: always()` es deliberado: los resultados de una corrida **que falló** son los
que más interesa conservar.

## 9.3 Integración local con Husky

Archivo: `.husky/pre-commit`

| Comprobación | Coste | Alcance |
|---|---|---|
| `php -l` sobre los PHP del índice | Milisegundos | Solo lo que se commitea |
| Prettier sobre los JS y CSS del índice | Menos de un segundo | Solo lo que se commitea |
| Prueba de humo de k6 | Diez segundos | Solo si hay un entorno escuchando |

El alcance limitado no es pereza, es la única forma de que el hook sea usable: el
repositorio arrastra 13 archivos que no cumplen Prettier desde antes de esta unidad, y
un hook que los exigiera bloquearía cualquier commit de cualquier integrante.

La prueba de humo es condicional por la misma razón. Un hook que falla por motivos
ajenos al cambio enseña a la gente a usar `--no-verify`, y entonces deja de servir.

## 9.4 Evidencia con y sin la compuerta

Para demostrar que la compuerta hace algo, hay que verla fallar.

### Sin compuerta (comportamiento anterior)

El `ci.yml` heredado ejecuta las pruebas así:

```yaml
run: php artisan test --filter="AuthTest|ActivityLogControllerTest|..." || true
```

Ese `|| true` descarta el código de salida: **el pipeline pasa aunque las pruebas
fallen**. No es una suposición, está escrito en el archivo. Corregirlo es el issue #86,
asignado y fuera del alcance de esta entrega.

Con las pruebas de carga ocurriría lo mismo si se ejecutaran sin umbrales: k6
imprimiría las métricas, el job terminaría en verde y un p95 de treinta segundos pasaría
inadvertido.

### Con compuerta (comportamiento nuevo)

Con los umbrales declarados en `options.thresholds`, la misma corrida termina así:

```
time="..." level=error msg="thresholds on metrics 'http_req_failed, tasa_login_exitoso' have been crossed"
```

y el proceso sale con código 99. Esa salida es real: corresponde a la corrida
documentada en el apartado 7.2, cuando la prueba todavía compartía una sola cuenta
entre todos los usuarios virtuales.

La evidencia de ambos casos está en `evidencia/`.

## 9.5 Lo que esta integración todavía no hace

| Carencia | Consecuencia |
|---|---|
| El umbral es fijo, no relativo | Una degradación del 400 % dentro de los 5 s no dispara nada. El apartado 4.2 propone el umbral de vigilancia |
| No hay comparación entre corridas | Los artefactos se guardan, pero compararlos es manual |
| La prueba corre aunque el cambio sea solo documentación | Gasta minutos de runner sin necesidad |
| El análisis estático no corre en el pipeline | La instancia de SonarQube es local y un runner no la alcanza. Ver 8.6 |
