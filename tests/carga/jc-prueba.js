// Prueba de carga del autor (JoseOrtega8) para la unidad de pipeline/CICD de MedSchedule.
// Objetivo: medir el endpoint del dashboard de administrador (/dashboard/data),
// cacheado con DashboardStatsService (ver PR #78, Cache::remember TTL 120s).
//
// Nivel de servicio acordado: p95 de la latencia por debajo de 5 s.
//
// Requisitos previos:
//   1. Entorno levantado: php artisan serve (o el entorno de liberación del equipo)
//   2. Usuario admin de prueba existente (admin@test.com) con su password
//      pasado por variable de entorno, nunca escrito en este archivo.
//
// Nota de diseño: /dashboard/data está protegido por role:admin + throttle:60,1,
// y /login por throttle:5,1. Para no disparar el limitador de login con varios
// usuarios virtuales, el login se hace UNA sola vez en setup() y todos los VUs
// reutilizan esa misma cookie de sesión.

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

const duracion_dashboard = new Trend('duracion_dashboard_data', true);
const tasa_200 = new Rate('tasa_respuesta_200');
const respuestas_limitadas = new Counter('respuestas_limitadas_429');

const url_base = __ENV.URL_BASE || 'http://127.0.0.1:8000';
const email = __ENV.K6_EMAIL || 'admin@test.com';
const password = __ENV.K6_PASSWORD;

export const options = {
	vus: 5,
	duration: '30s',
	thresholds: {
		duracion_dashboard_data: ['p(95)<5000'], // nivel de servicio acordado: p95 < 5s
	},
};

// setup() corre una sola vez, antes de que arranquen los usuarios virtuales.
export function setup() {
	if (!password) {
		throw new Error(
			'Falta la variable de entorno K6_PASSWORD. Ejecutar con: K6_PASSWORD=... k6 run tests/carga/jc-prueba.js',
		);
	}

	// 1. GET /login para obtener el token CSRF y la cookie de sesión inicial.
	const paginaLogin = http.get(`${url_base}/login`);
	const match = paginaLogin.body.match(/name="_token" value="([^"]+)"/);
	const token = match ? match[1] : null;

	check(paginaLogin, {
		'pagina de login carga (200)': (r) => r.status === 200,
		'token CSRF encontrado': () => token !== null,
	});

	// 2. POST /login con las credenciales, reusando las cookies de la petición anterior.
	const respuestaLogin = http.post(
		`${url_base}/login`,
		{ email, password, _token: token },
		{
			cookies: paginaLogin.cookies,
			redirects: 0, // no seguir el redirect, solo queremos la cookie de sesión autenticada
		},
	);

	check(respuestaLogin, {
		'login responde redirect (302)': (r) => r.status === 302,
	});

	// Devolvemos la cookie de sesión para que todos los VUs la reutilicen.
	return { cookies: respuestaLogin.cookies };
}

// default() corre repetidamente, una vez por iteración, para cada usuario virtual.
export default function (data) {
	const respuesta = http.get(`${url_base}/dashboard/data`, {
		cookies: data.cookies,
	});

	duracion_dashboard.add(respuesta.timings.duration);

	if (respuesta.status === 429) {
		respuestas_limitadas.add(1);
	} else {
		tasa_200.add(respuesta.status === 200);
		check(respuesta, { 'dashboard responde 200': (r) => r.status === 200 });
	}

	sleep(1);
}
