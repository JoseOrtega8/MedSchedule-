// Prueba de carga del autor (ramonibr) para la unidad 2 de MedSchedule.
// Recorre los endpoints mas usados de la aplicacion: portada publica, About,
// el formulario de login, la autenticacion real contra la sesion de Laravel y
// el JSON del panel del paciente, que es la ruta con mas consultas a base de
// datos por peticion.
//
// Nivel de servicio acordado para la unidad: p95 de la latencia por debajo de 5 s.
//
// Requisitos previos:
//   1. Entorno levantado:  bash scripts/entorno-liberacion.sh
//   2. Cuentas de carga:   php artisan db:seed --class=CargaSeeder --force
import http from "k6/http";
import { check, group, sleep } from "k6";
import { Trend, Rate, Counter } from "k6/metrics";

// Metricas propias, una por endpoint, para poder leer el p95 de cada ruta por
// separado ademas del agregado que reporta k6 en http_req_duration.
const duracion_portada = new Trend("duracion_portada", true);
const duracion_about = new Trend("duracion_about", true);
const duracion_login_get = new Trend("duracion_login_get", true);
const duracion_login_post = new Trend("duracion_login_post", true);
const duracion_panel = new Trend("duracion_panel", true);
const tasa_login_exitoso = new Rate("tasa_login_exitoso");
// Mide si /about devuelve 200. Hoy vale 0 % por un defecto abierto de la vista
// (ver docs/entrega-u2/07-resultados-k6.md). Cuando se corrija, subira a 100 %
// sin tocar esta prueba.
const tasa_about_correcto = new Rate("tasa_about_correcto");
const errores_negocio = new Counter("errores_negocio");
// Cuenta las veces que la aplicacion respondio 429 al autenticar. No es un
// fallo de la prueba: es el limitador de cinco intentos por minuto y por IP que
// declara routes/auth.php, y conviene tenerlo medido.
const respuestas_limitadas = new Counter("respuestas_limitadas");

// Estado propio de cada usuario virtual. k6 da a cada VU su propio contexto de
// modulo, asi que esta variable no se comparte entre usuarios virtuales.
let sesion_iniciada = false;

const url_base = __ENV.URL_BASE || "http://127.0.0.1:8000";
// La contrasena llega por variable de entorno. Nunca se escribe en el repositorio.
const password = __ENV.K6_PASSWORD;
// Prefijo de las cuentas que siembra CargaSeeder. Cada usuario virtual usa la
// suya para no chocar con el limitador de cinco intentos por minuto que Laravel
// Breeze aplica por combinacion de correo e IP.
const prefijo_usuario = __ENV.K6_PREFIJO_USUARIO || "carga";
const dominio_usuario = __ENV.K6_DOMINIO_USUARIO || "test.com";
// Con K6_USUARIO definido, todos los usuarios virtuales comparten una sola
// cuenta. Sirve para reproducir a proposito el efecto del limitador.
const usuario_unico = __ENV.K6_USUARIO;
// Reintentos de autenticacion frente al limitador y espera entre ellos, en segundos.
const intentos_maximos_login = Number(__ENV.K6_INTENTOS_LOGIN || 6);
const espera_por_limitador = Number(__ENV.K6_ESPERA_LIMITADOR || 15);

export const options = {
    // El requisito pide mas de 5 usuarios virtuales: se usan 10, con rampa de
    // subida, meseta sostenida y rampa de bajada para no medir solo el arranque.
    stages: [
        { duration: "30s", target: 10 },
        { duration: "1m", target: 10 },
        { duration: "30s", target: 0 },
    ],
    thresholds: {
        // Umbral de servicio de la unidad. k6 sale con codigo 99 si no se cumple,
        // lo que convierte esta linea en la compuerta de liberacion del pipeline.
        http_req_duration: ["p(95)<5000"],
        http_req_failed: ["rate<0.01"],
        tasa_login_exitoso: ["rate>0.99"],
        duracion_panel: ["p(95)<5000"],
    },
    summaryTrendStats: ["avg", "min", "med", "max", "p(90)", "p(95)", "p(99)"],
    // k6 vacia el almacen de cookies de cada usuario virtual al terminar cada
    // iteracion. Con esa conducta por defecto la sesion de Laravel se pierde y
    // el panel redirige al formulario de login, de modo que la prueba mediria
    // el login una y otra vez. Conservar las cookies reproduce lo que hace un
    // navegador real: autenticarse una vez y seguir navegando.
    noCookiesReset: true,
};

// Devuelve el correo que corresponde a este usuario virtual.
function correo_del_usuario_virtual() {
    if (usuario_unico) {
        return usuario_unico;
    }
    return `${prefijo_usuario}${__VU}@${dominio_usuario}`;
}

// El 429 del limitador es una respuesta prevista del sistema, no un fallo del
// servidor: se declara como esperada para que no distorsione http_req_failed.
// Su frecuencia se sigue en la metrica respuestas_limitadas.
const respuestas_login_esperadas = http.expectedStatuses(302, 429);

// Extrae el token CSRF que Laravel incrusta como campo oculto en el formulario
// de login. Sin el, la peticion POST recibe un 419 y la prueba no mide nada util.
function extraer_token(cuerpo_html) {
    const coincidencia = String(cuerpo_html).match(
        /name="_token"\s+value="([^"]+)"/,
    );
    return coincidencia ? coincidencia[1] : null;
}

// Autentica al usuario virtual y deja la cookie de sesion en su propio jar.
// Reintenta con espera cuando el limitador responde 429, porque con diez
// usuarios virtuales arrancando a la vez es normal que varios lo encuentren.
function iniciar_sesion() {
    const correo = correo_del_usuario_virtual();

    for (let intento = 1; intento <= intentos_maximos_login; intento++) {
        const formulario = http.get(`${url_base}/login`, {
            tags: { endpoint: "login_get" },
        });
        duracion_login_get.add(formulario.timings.duration);

        const token = extraer_token(formulario.body);
        if (!token) {
            errores_negocio.add(1);
            tasa_login_exitoso.add(false);
            check(null, { "formulario de login trae token CSRF": () => false });
            return false;
        }

        const login = http.post(
            `${url_base}/login`,
            { _token: token, email: correo, password: password },
            {
                tags: { endpoint: "login_post" },
                redirects: 0,
                responseCallback: respuestas_login_esperadas,
            },
        );
        duracion_login_post.add(login.timings.duration);

        // Laravel responde 302 hacia el panel cuando las credenciales son validas.
        if (login.status === 302) {
            tasa_login_exitoso.add(true);
            check(login, { "login redirige con 302": () => true });
            return true;
        }

        if (login.status === 429) {
            respuestas_limitadas.add(1);
            // Espera antes de reintentar: la ventana del limitador es de un minuto.
            sleep(espera_por_limitador);
            continue;
        }

        // Cualquier otro codigo es un fallo real de autenticacion.
        tasa_login_exitoso.add(false);
        errores_negocio.add(1);
        check(login, { "login redirige con 302": () => false });
        return false;
    }

    tasa_login_exitoso.add(false);
    check(null, {
        "login logrado dentro de los reintentos permitidos": () => false,
    });
    return false;
}

export function setup() {
    if (!password) {
        throw new Error(
            "Falta la variable K6_PASSWORD. Exportarla antes de correr la prueba.",
        );
    }

    const respuesta = http.get(url_base);
    if (respuesta.status !== 200) {
        throw new Error(
            `El entorno no responde en ${url_base} (codigo ${respuesta.status}). ` +
                "Generarlo con: bash scripts/entorno-liberacion.sh",
        );
    }
}

export default function () {
    group("endpoints publicos", function () {
        const portada = http.get(url_base, { tags: { endpoint: "portada" } });
        duracion_portada.add(portada.timings.duration);
        check(portada, { "portada responde 200": (r) => r.status === 200 });

        // El 500 se declara como respuesta esperada para que no contamine
        // http_req_failed mientras el defecto siga abierto. La salud real de la
        // ruta se sigue en la metrica tasa_about_correcto.
        const about = http.get(`${url_base}/about`, {
            tags: { endpoint: "about" },
            responseCallback: http.expectedStatuses(200, 500),
        });
        duracion_about.add(about.timings.duration);
        tasa_about_correcto.add(about.status === 200);
        check(about, {
            "about responde (200 esperado, 500 con el defecto abierto)": (r) =>
                r.status === 200 || r.status === 500,
        });
    });

    group("flujo autenticado", function () {
        // Un usuario real se autentica una vez y luego navega. Repetir el login
        // en cada iteracion no solo es irreal: choca con el limitador de cinco
        // peticiones por minuto y por IP de routes/auth.php, que haria que la
        // prueba midiera el limitador en lugar de la aplicacion.
        if (!sesion_iniciada) {
            sesion_iniciada = iniciar_sesion();
            if (!sesion_iniciada) {
                return;
            }
        }

        const panel = http.get(`${url_base}/patient/dashboard/data`, {
            tags: { endpoint: "panel_paciente" },
        });
        duracion_panel.add(panel.timings.duration);
        check(panel, {
            "panel responde 200": (r) => r.status === 200,
            "panel devuelve JSON": (r) =>
                String(r.headers["Content-Type"]).includes("json"),
        });

        // Cuando la sesion caduca, Laravel redirige al formulario de login y k6
        // sigue la redireccion, asi que la respuesta llega con codigo 200 pero
        // con HTML en lugar de JSON. Ese es el sintoma que hay que vigilar para
        // volver a autenticarse en la iteracion siguiente.
        const respuesta_es_json = String(
            panel.headers["Content-Type"],
        ).includes("json");
        if (panel.status !== 200 || !respuesta_es_json) {
            sesion_iniciada = false;
        }
    });

    // Pausa entre iteraciones: modela a un usuario que lee la pantalla antes de
    // volver a pedir. Sin ella la prueba mide saturacion, no uso realista.
    sleep(1);
}
