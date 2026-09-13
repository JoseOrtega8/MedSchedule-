// Tour de ejemplo con driver.js. Sirve como plantilla de referencia; no está
// cableado a ninguna vista. La implementación por rol se define en la spec
// specs/002-tours-guiados y se construye en la unidad siguiente.
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

// Devuelve la configuración base compartida por todos los tours del sistema.
function construir_configuracion_base() {
    return {
        showProgress: true,
        nextBtnText: "Siguiente",
        prevBtnText: "Anterior",
        doneBtnText: "Finalizar",
        progressText: "Paso {{current}} de {{total}}",
    };
}

// Inicia el tour de ejemplo sobre los elementos indicados en pasos_tour.
// Si un elemento destino no existe en el DOM, se omite ese paso en lugar de
// romper la aplicación.
export function iniciar_tour_ejemplo(pasos_tour) {
    try {
        const pasos_validos = pasos_tour.filter(
            (paso) => document.querySelector(paso.element) !== null,
        );

        if (pasos_validos.length === 0) {
            console.warn("Tour de ejemplo: ningún elemento destino existe en la página.");
            return null;
        }

        const instancia_tour = driver({
            ...construir_configuracion_base(),
            steps: pasos_validos,
        });

        instancia_tour.drive();
        return instancia_tour;
    } catch (error) {
        // No silenciar: se registra el error y se continúa sin tour.
        console.error("Tour de ejemplo: fallo al iniciar.", error);
        return null;
    }
}
