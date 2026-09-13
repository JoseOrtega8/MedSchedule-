# Tours guiados — Módulo de driver.js

## Qué es driver.js

[driver.js](https://driverjs.com/) es una librería JavaScript que permite crear tours guiados interactivos en aplicaciones web. Proporciona funcionalidades para:

- Resaltar elementos específicos de la página
- Mostrar instrucciones paso a paso
- Navegar entre pasos del tour
- Personalizar textos de botones y mensajes de progreso

## Por qué está instalado sin cablear

El módulo `tour-ejemplo.js` se incluye como **referencia y plantilla** para la implementación futura. No está cableado a ninguna vista ni entrypoint de Vite porque:

1. La definición final de tours por rol se especifica en `specs/002-tours-guiados/`
2. Cada rol (paciente, médico, administrador, etc.) requerirá una versión personalizada del tour
3. La implementación se realiza en la unidad siguiente, cuando se defina claramente qué elementos de cada panel deben resaltarse

## Especificación de la unidad siguiente

La implementación real se define en estos documentos:

- **`specs/002-tours-guiados/spec.md`** — Especificación funcional de tours por rol
- **`specs/002-tours-guiados/plan.md`** — Plan de implementación
- **`specs/002-tours-guiados/tasks.md`** — Tareas desglosadas

## Cómo se cableará en la unidad siguiente

En la unidad siguiente, cuando se implemente la feature completa:

1. Se importará `iniciar_tour_ejemplo` en el entrypoint de Vite correspondiente
2. Se llamará a la función desde el panel del rol correspondiente (p. ej., `resources/views/patient/dashboard.blade.php`)
3. Se pasarán los pasos del tour como un arreglo de configuración que especifique selectores CSS y textos
4. Se instanciará el tour automáticamente al cargar la página (o a través de un botón interactivo)

## Uso del módulo de ejemplo

```javascript
import { iniciar_tour_ejemplo } from "./tours/tour-ejemplo.js";

// Ejemplo: iniciar un tour sobre elementos específicos
const pasos = [
    {
        element: "#titulo-panel",
        popover: {
            title: "Bienvenida",
            description: "Este es el panel de inicio"
        }
    },
    {
        element: "#boton-accion",
        popover: {
            title: "Acciones",
            description: "Aquí puedes realizar acciones"
        }
    }
];

iniciar_tour_ejemplo(pasos);
```

## Manejo de errores

La función `iniciar_tour_ejemplo` es robusta ante elementos faltantes:

- Si un elemento destino no existe en el DOM, se omite ese paso
- Si ningún elemento existe, se registra un mensaje de advertencia y se retorna `null`
- Los errores de ejecución se registran en la consola sin interrumpir el resto de la aplicación
