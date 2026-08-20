# Guía de seguridad — MedSchedule

Este documento resume las prácticas de seguridad que el equipo debe seguir al desarrollar nuevas vistas y endpoints.

## 1. Protección XSS (Cross-Site Scripting)

**Regla de oro: nunca usar `{!! !!}` con datos que vengan del usuario o de la base de datos.**

Blade escapa automáticamente cualquier variable impresa con doble llave:

```blade
{{-- ✅ CORRECTO: Blade escapa el contenido automáticamente --}}
<p>{{ $paciente->allergies }}</p>

{{-- ❌ INCORRECTO: {!! !!} imprime HTML crudo, sin escapar --}}
<p>{!! $paciente->allergies !!}</p>
```

Si un paciente escribe en el campo de alergias algo como `<script>alert('hackeado')</script>`, con `{{ }}` el navegador lo muestra como texto plano; con `{!! !!}` lo ejecutaría como código.

**¿Cuándo SÍ usar `{!! !!}`?** Solo cuando el contenido es HTML de confianza generado por el propio sistema (por ejemplo, un editor de texto enriquecido validado en el backend), nunca con texto libre de un formulario.

**En JavaScript:** al insertar datos dinámicos en el DOM, usar `textContent` en vez de `innerHTML`:

```js
// ✅ CORRECTO
elemento.textContent = datos.nombre;

// ❌ INCORRECTO — vulnerable si "datos.nombre" viene del usuario
elemento.innerHTML = datos.nombre;
```

## 2. CORS

Los orígenes permitidos están restringidos en `config/cors.php`. Si se agrega un nuevo dominio (por ejemplo, un subdominio de staging), debe añadirse explícitamente ahí — nunca usar `'*'` en `allowed_origins`.

## 3. Contraseñas

Todo formulario de registro o cambio de contraseña debe usar `Illuminate\Validation\Rules\Password` con mínimo 8 caracteres, mayúscula/minúscula, número y símbolo (ver `app/Http/Requests/Auth/RegisterRequest.php` como referencia).

## 4. Headers de seguridad

Los headers (CSP, X-Frame-Options, etc.) se aplican automáticamente a todas las respuestas vía `App\Http\Middleware\SecurityHeaders`. Si una vista nueva necesita cargar un recurso externo (CDN, fuente, script), debe agregarse explícitamente a la política en ese middleware — no relajar el CSP de forma global.

## 5. Validación de datos

Usar siempre `FormRequest` para validar entradas de formularios (no `$request->validate()` inline en el controlador), siguiendo el patrón de `LoginRequest`, `RegisterRequest` y `ProfileUpdateRequest`.
