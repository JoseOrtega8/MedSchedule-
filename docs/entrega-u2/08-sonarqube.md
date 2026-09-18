# 8. Análisis de código estático con SonarQube

## 8.1 Instalación del stack local

SonarQube Community necesita una base de datos externa: el H2 embebido solo sirve para
evaluación y la propia herramienta lo desaconseja. El stack son dos contenedores.

Archivo: `infra/sonarqube/docker-compose.yml`

```yaml
services:
    sonarqube:
        image: sonarqube:community
        depends_on: [db]
        environment:
            SONAR_JDBC_URL: jdbc:postgresql://db:5432/sonar
            SONAR_JDBC_USERNAME: sonar
            SONAR_JDBC_PASSWORD: sonar
        ports: ['9000:9000']
        volumes:
            - datos_sonarqube:/opt/sonarqube/data
            - extensiones_sonarqube:/opt/sonarqube/extensions
            - logs_sonarqube:/opt/sonarqube/logs
    db:
        image: postgres:16
        environment:
            POSTGRES_USER: sonar
            POSTGRES_PASSWORD: sonar
            POSTGRES_DB: sonar
        volumes:
            - datos_postgres:/var/lib/postgresql/data
```

### Pasos

```bash
# 1. Levantar el stack y esperar a que quede operativo.
bash scripts/sonarqube-local.sh

# 2. Entrar a http://localhost:9000 con admin / admin.
#    La interfaz obliga a cambiar la contraseña en el primer acceso.
#    Requisito verificado: debe incluir al menos un carácter especial.

# 3. Generar un token en Mi cuenta > Security y exportarlo.
export SONAR_TOKEN='<token generado>'

# 4. Analizar.
bash scripts/sonarqube-escanear.sh <rama>
```

El escáner corre como contenedor (`sonarsource/sonar-scanner-cli`), **así que no hace
falta instalar Java en la máquina**. En la máquina de esta entrega, `java -version`
responde «Unable to locate a Java Runtime» y el análisis funcionó igual.

Versión verificada: **SonarQube 26.9.0.129388**.

## 8.2 Dos limitaciones encontradas durante la instalación

### La edición Community no analiza ramas

`sonar.branch.name` es una propiedad de la edición Developer en adelante. En Community
el análisis sobrescribe siempre el mismo proyecto y no hay comparación entre ramas.

**Solución adoptada:** `scripts/sonarqube-escanear.sh` deriva una clave de proyecto a
partir del nombre de la rama (`medschedule-<rama>`, con las barras convertidas en
guiones). Cada rama aparece como su propio proyecto y las comparaciones siguen siendo
posibles, sin pagar una licencia.

### El publicador SCM se cuelga en este repositorio

El primer análisis quedó detenido más de veinte minutos en la etapa
`SCM Publisher 86 source files to be analyzed`, sin avanzar. Esa etapa recorre el
histórico de cada archivo con jgit para atribuir autoría línea a línea.

**Solución adoptada:** `sonar.scm.disabled=true` por defecto en el script, reactivable
con `SONAR_SCM=false`. No afecta a ninguna regla de análisis: solo se pierde la
atribución de autor y el cálculo de «código nuevo» por fecha.

**Efecto medido:** de más de 20 minutos colgado a **25.8 segundos** de análisis completo.

## 8.3 Resultados del escaneo del PR #95

Rama analizada: `feat/unidad-docs-sdd`, correspondiente al
[PR #95](https://github.com/JoseOrtega8/MedSchedule-/pull/95), la entrega de la unidad
anterior. Evidencia cruda en `evidencia/sonar-metricas-pr95.json` y
`evidencia/sonar-incidencias-pr95.json`.

### Tamaño y estructura

| Métrica | Valor |
|---|---|
| Líneas de código | 5 615 |
| Archivos | 64 |
| Clases | 48 |
| Funciones | 491 |
| Complejidad ciclomática | 1 116 |
| Complejidad cognitiva | 529 |
| Densidad de comentarios | 2.8 % |

### Calidad

SonarQube 26 clasifica las incidencias por **cualidad del software** (la taxonomía Clean
Code), no por el tipo antiguo de bug, vulnerabilidad y *code smell*. Las cifras de esta
tabla son las que muestra la interfaz, capturadas en
`evidencia/sonar-01-panel-general.png`:

| Métrica | Valor | Calificación | Lectura |
|---|---|---|---|
| Seguridad | **0** incidencias | **A** | Ninguna incidencia de seguridad |
| Fiabilidad | **2** incidencias | **C** | Ver el apartado 8.3.1: son las dos que bajan la nota |
| Mantenibilidad | **94** incidencias | **A** | El grueso de la deuda |
| Security hotspots | **0** | **A** | Nada que requiera revisión manual |
| Deuda técnica | **447 minutos** (7 h 27 min) | | Estimación para saldar la mantenibilidad entera |
| Duplicación | **2.8 %** en 12 bloques | | Por debajo del 3 % que se suele tomar como umbral |
| Cobertura | **0 %** | | Ver el apartado 8.5 |
| Puerta de calidad | **Passed** | | Con avisos en el análisis |

**Cuidado con las métricas antiguas.** La API sigue exponiendo `bugs`, `vulnerabilities`,
`code_smells` y `reliability_rating`, pero están deprecadas y **no coinciden con la
interfaz**: devuelven `bugs = 0` y fiabilidad `A`, mientras que la pantalla muestra dos
incidencias de fiabilidad y calificación `C`. Un informe construido sobre esas métricas
afirmaría que el proyecto no tiene ningún problema de fiabilidad, y quien abriera la
interfaz vería lo contrario. Las cifras de este documento salen de las métricas
`software_quality_*`, que son las que la herramienta muestra.

### 8.3.1 Las dos incidencias de fiabilidad

| Regla | Severidad | Archivo | Qué señala |
|---|---|---|---|
| `javascript:S8786` | MEDIA | `resources/js/admin-rbac.js:30` | Expresión regular con rendimiento super-lineal por *backtracking* |
| `javascript:S7781` | BAJA | `resources/js/topbar-date.js:9` | Usar `String#replaceAll()` en lugar de `String#replace()` |

La primera merece atención en una unidad dedicada al rendimiento: una expresión regular
con *backtracking* super-lineal es el patrón que hace posible un ReDoS, es decir, una
denegación de servicio provocada por una entrada construida a propósito para disparar el
coste de la expresión. Está en la pantalla de administración de roles, que recibe datos
escritos por el usuario. **La prueba de carga no la habría encontrado**, porque las
entradas que genera son benignas; el análisis estático sí.

Captura en `evidencia/sonar-02-fiabilidad.png`.

### Incidencias por severidad

| Severidad | Cantidad |
|---|---|
| Bloqueantes | 0 |
| Críticas | 11 |
| Mayores | 20 |
| Menores | 64 |
| Informativas | 0 |
| **Total** | **95** |

### Las once incidencias críticas

| Regla | Cantidad | Qué señala |
|---|---|---|
| `php:S1192` | 9 | Literal de cadena repetido tres o más veces: debe ser una constante. Afecta a cadenas como `"Cita agendada"`, `"09:30:00"`, `"127.0.0.1"` o `"Mozilla/5.0"` |
| `php:S121` | 2 | Sentencia anidada sin llaves. Es la clase de código en la que una línea añadida después queda fuera del `if` sin que se note |

### Las mayores más repetidas

| Regla | Cantidad | Qué señala |
|---|---|---|
| `javascript:S7761` | 11 | Usar `.dataset` en lugar de `getAttribute(...)` en el JavaScript de las vistas |
| `php:S1172` | 5 | Parámetro de función sin usar, casi siempre `$request` en métodos de controlador |
| `php:S1142` | 1 | Un método con 5 puntos de retorno, más de los 3 que permite la regla |
| `php:S112` | 1 | Se lanza una excepción genérica en lugar de una propia |
| `php:S138` | 1 | La función `run` de un seeder tiene 175 líneas, sobre el límite de 150 |

## 8.4 Lectura honesta de estos resultados

Tres matices que conviene decir antes de que los pregunte quien revise:

1. **El PR #95 es casi todo markdown.** Su diff son documentos, no código. Por eso
   SonarQube no reporta «código nuevo» del PR: lo que analiza es **el árbol completo de
   la rama**, es decir, todo el PHP y el JavaScript del proyecto en ese punto. Las
   métricas de arriba describen el estado del sistema, no la aportación del PR. Es una
   limitación de la edición Community combinada con la naturaleza de ese PR, y es
   preferible explicarla a presentar el número como si fuera otra cosa.
2. **Cero incidencias de seguridad no significa código seguro.** Significa que ninguna
   regla del perfil por defecto se disparó. SonarQube no entiende la lógica de negocio: el
   defecto #97, que deja `/about` en error 500 para cualquier visitante anónimo, **no
   aparece en este informe**. Lo encontró la prueba de carga. A la inversa, la expresión
   regular vulnerable a ReDoS del apartado 8.3.1 no la habría encontrado ninguna prueba de
   carga. Las dos herramientas son complementarias, no sustitutas.
3. **La calificación A de mantenibilidad es fácil de malinterpretar.** Se calcula sobre
   la razón entre deuda y tamaño del código: 447 minutos sobre 5 615 líneas da A, pero
   siguen siendo más de siete horas de trabajo pendiente. Y la fiabilidad no es A, es
   **C**, por una sola incidencia de severidad media.

## 8.5 Por qué la cobertura sale en 0 %

SonarQube no calcula cobertura: la lee de un informe que genera la suite de pruebas. El
parámetro `sonar.php.coverage.reportPaths=coverage.xml` apunta a ese informe, que no se
generó para este análisis porque exige Xdebug o PCOV instalados, ausentes en la máquina
donde se ejecutó.

Para obtenerlo:

```bash
php artisan test --coverage-clover=coverage.xml
bash scripts/sonarqube-escanear.sh <rama>
```

Se documenta como pendiente en lugar de dejar el 0 % sin explicación, que se leería
como «no hay pruebas» cuando el proyecto tiene 79 métodos de PHPUnit.

## 8.6 Integración en el pipeline

El análisis obligatorio de esta entrega es el local, por dos razones: el requisito pide
expresamente un stack local, y una instancia en `localhost` no es alcanzable desde un
runner de GitHub Actions.

El job correspondiente queda escrito y condicionado a que existan los secretos
`SONAR_HOST_URL` y `SONAR_TOKEN`. Mientras no exista una instancia accesible desde
internet, no se ejecuta. Se declara así en lugar de fingir que corre.
