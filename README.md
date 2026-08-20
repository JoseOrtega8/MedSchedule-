# 🏥 MedSchedule — Sistema Web de Gestión de Citas Médicas

![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=flat&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat&logo=bootstrap&logoColor=white)
![License](https://img.shields.io/badge/Licencia-MIT-green?style=flat)

Aplicación web profesional para la gestión integral de citas médicas entre pacientes, doctores y administradores. Permite registrar usuarios, gestionar agendas, programar citas, llevar historial y generar registros de auditoría.

---

## 👥 Integrantes del equipo

| Nombre                    | GitHub                                     |
| ------------------------- | ------------------------------------------ |
| Jose Carlos Calles Ortega | [@usuario](https://github.com/JoseOrtega8) |
| Ulises Castro Domínguez   | [@usuario](https://github.com/usuario)     |

> **Grupo:** TIDSM8-2 — Universidad Tecnológica de Hermosillo  
> **Materia:** Desarrollo Web Profesional  
> **Profesor:** Iván Rogelio Chenoweth

---

## 📋 Índice

- [Descripción](#-descripción)
- [Stack Tecnológico](#-stack-tecnológico)
- [Requisitos previos](#-requisitos-previos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Ejecutar el proyecto](#-ejecutar-el-proyecto)
- [Estructura del proyecto](#-estructura-del-proyecto)
- [Roles del sistema](#-roles-del-sistema)
- [Funcionalidades](#-funcionalidades)
- [Licencia](#-licencia)

---

## 📖 Descripción

MedSchedule automatiza la gestión de citas médicas, reduciendo errores administrativos y tiempos de espera. El sistema está diseñado bajo arquitectura cliente-servidor con Laravel como backend, vistas Blade para el frontend, MySQL como base de datos y Bootstrap para la interfaz.

---

## 🛠 Stack Tecnológico

| Capa                 | Tecnología               |
| -------------------- | ------------------------ |
| Backend              | Laravel 11.x (PHP 8.2+)  |
| Frontend             | Blade Templates          |
| Base de datos        | MySQL 8.0                |
| CSS / UI             | Bootstrap 5.3            |
| Control de versiones | Git + GitHub             |
| Autenticación        | Laravel Breeze / Sanctum |
| ORM                  | Eloquent ORM             |

---

## ✅ Requisitos previos

Antes de instalar el proyecto, asegúrate de tener instalado lo siguiente:

- PHP >= 8.2
- Composer >= 2.x
- MySQL >= 8.0
- Node.js >= 18.x y npm >= 9.x
- Git

Verifica las versiones con:

```bash
php -v
composer -V
mysql --version
node -v
npm -v
git --version
```

---

## 📦 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/JoseOrtega8/medschedule.git
cd medschedule
```

### 2. Instalar dependencias de PHP

```bash
composer install
```

### 3. Instalar dependencias de Node

```bash
npm install
```

---

## ⚙️ Configuración

### 4. Crear el archivo de entorno

```bash
cp .env.example .env
```

### 5. Configurar las variables de entorno

Abre el archivo `.env` y edita los siguientes valores:

```env
APP_NAME=MedSchedule
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=medschedule
DB_USERNAME=root
DB_PASSWORD=tu_contraseña
```

### 6. Generar la clave de la aplicación

```bash
php artisan key:generate
```

### 7. Crear la base de datos

Entra a MySQL y ejecuta:

```sql
CREATE DATABASE medschedule CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 8. Ejecutar las migraciones y seeders

```bash
php artisan migrate --seed
```

---

## 🚀 Ejecutar el proyecto

### Iniciar el servidor de desarrollo

```bash
php artisan serve
```

El proyecto estará disponible en: `http://localhost:8000`

### Compilar assets (CSS/JS con Bootstrap)

En una terminal separada:

```bash
npm run dev
```

Para producción:

```bash
npm run build
```

---

## 🗂 Estructura del proyecto

```
medschedule/
├── app/
│   ├── Http/Controllers/    # Controladores por módulo
│   ├── Models/              # Modelos Eloquent
│   └── Middleware/          # Middleware de roles y auth
├── database/
│   ├── migrations/          # Migraciones de base de datos
│   └── seeders/             # Datos iniciales (admin, roles)
├── resources/
│   └── views/               # Vistas Blade
│       ├── admin/           # Panel de administración
│       ├── doctor/          # Vistas del doctor
│       ├── patient/         # Vistas del paciente
│       └── auth/            # Login, registro, reset password
├── routes/
│   └── web.php              # Rutas principales
├── .env.example
├── composer.json
└── README.md
```

---

## 👤 Roles del sistema

| Rol               | Descripción                                               |
| ----------------- | --------------------------------------------------------- |
| **Administrador** | Gestión completa: usuarios, auditoría, estadísticos, logs |
| **Doctor**        | Gestión de agenda médica, consulta de citas e historial   |
| **Paciente**      | Solicitud y seguimiento de citas, actualización de perfil |

### Credenciales del seeder (desarrollo)

| Rol           | Email                    | Contraseña |
| ------------- | ------------------------ | ---------- |
| Administrador | admin@medschedule.com    | password   |
| Doctor        | doctor@medschedule.com   | password   |
| Paciente      | paciente@medschedule.com | password   |

---

## ⚡ Funcionalidades

- ✅ Registro y CRUD de usuarios (rol administrador)
- ✅ Login y reset de contraseña vía email
- ✅ Gestión de agendas médicas (doctores)
- ✅ Programación, modificación y cancelación de citas (pacientes)
- ✅ Cambio de información de perfil (excepto email)
- ✅ Logs de actividad y estadísticos (administrador)
- ✅ Página About / Créditos
- ✅ Integración con API externo de calendario (Google Calendar API)
- ✅ Licencia MIT — repositorio público

---

## 📄 Licencia

Este proyecto está bajo la Licencia **MIT** — consulta el archivo [LICENSE](LICENSE) para más detalles.

```
MIT License

Copyright (c) 2026 Jose Carlos Calles Ortega, Ulises Castro Domínguez

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software...
```

---

> Universidad Tecnológica de Hermosillo · TIDSM8-2 · 2026
