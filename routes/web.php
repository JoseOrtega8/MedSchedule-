<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SpecialtyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/about', 'about.about')->name('about');
Route::view('/dashboard', 'dashboard.dashboard')->name('dashboard');
Route::view('/doctor/agenda', 'doctor.agenda')->name('doctor.agenda');
Route::view('/doctor/horarios', 'doctor.horarios')->name('doctor.schedules');
Route::view('/doctor/perfil', 'doctor.perfil')->name('doctor.profile');
Route::view('/admin/especialidades', 'admin.especialidades')->name('admin.specialties');
Route::view('/admin/logs', 'admin.logs')->name('admin.logs');

Route::get('/dashboard/data', function () {
    return response()->json([
        'stats' => [
            'totalUsers' => 148,
            'appointmentsToday' => 34,
            'activeDoctors' => 12,
            'appointmentsMonth' => 312,
            'details' => [
                'totalUsers' => '+12 este mes',
                'appointmentsToday' => '8 pendientes',
                'activeDoctors' => '2 en consulta',
                'appointmentsMonth' => '+18% vs mes anterior',
            ],
        ],
        'recentAppointments' => [
            ['patient' => 'Maria Lopez', 'doctor' => 'Dr. Garcia', 'datetime' => '01/03/2026 10:00', 'status' => 'confirmada'],
            ['patient' => 'Carlos Ruiz', 'doctor' => 'Dra. Martinez', 'datetime' => '01/03/2026 11:30', 'status' => 'confirmada'],
            ['patient' => 'Ana Torres', 'doctor' => 'Dr. Garcia', 'datetime' => '01/03/2026 14:00', 'status' => 'cancelada'],
            ['patient' => 'Pedro Soto', 'doctor' => 'Dr. Ramirez', 'datetime' => '02/03/2026 09:00', 'status' => 'pendiente'],
            ['patient' => 'Laura Perez', 'doctor' => 'Dra. Martinez', 'datetime' => '02/03/2026 10:15', 'status' => 'confirmada'],
            ['patient' => 'Miguel Vega', 'doctor' => 'Dr. Herrera', 'datetime' => '02/03/2026 12:40', 'status' => 'pendiente'],
            ['patient' => 'Sofia Campos', 'doctor' => 'Dra. Navarro', 'datetime' => '02/03/2026 16:00', 'status' => 'confirmada'],
            ['patient' => 'Jorge Ibarra', 'doctor' => 'Dr. Ramirez', 'datetime' => '03/03/2026 09:30', 'status' => 'cancelada'],
            ['patient' => 'Elena Mora', 'doctor' => 'Dra. Navarro', 'datetime' => '03/03/2026 11:00', 'status' => 'confirmada'],
            ['patient' => 'Daniel Cruz', 'doctor' => 'Dr. Herrera', 'datetime' => '03/03/2026 13:20', 'status' => 'pendiente'],
        ],
        'activityLogs' => [
            ['icon' => 'bi-person-plus', 'tone' => 'success', 'text' => 'Nuevo usuario registrado', 'time' => 'hace 5 min'],
            ['icon' => 'bi-calendar-plus', 'tone' => 'info', 'text' => 'Cita programada #312', 'time' => 'hace 30 min'],
            ['icon' => 'bi-calendar-x', 'tone' => 'danger', 'text' => 'Cita cancelada #309', 'time' => 'hace 45 min'],
            ['icon' => 'bi-pencil-square', 'tone' => 'warning', 'text' => 'Perfil actualizado', 'time' => 'hace 1 hora'],
            ['icon' => 'bi-box-arrow-in-right', 'tone' => 'primary', 'text' => 'Login: admin@medschedule.com', 'time' => 'hace 2 horas'],
        ],
    ]);
})->name('dashboard.data');

Route::get('/doctor/agenda/data', [AppointmentController::class, 'agendaData'])->name('doctor.agenda.data');
Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');

Route::get('/doctor/horarios/data', [ScheduleController::class, 'indexData'])->name('doctor.schedules.data');
Route::post('/doctor/horarios', [ScheduleController::class, 'store'])->name('doctor.schedules.store');
Route::patch('/doctor/horarios/{schedule}', [ScheduleController::class, 'update'])->name('doctor.schedules.update');
Route::delete('/doctor/horarios/{schedule}', [ScheduleController::class, 'destroy'])->name('doctor.schedules.destroy');

Route::get('/doctor/perfil/data', [DoctorProfileController::class, 'indexData'])->name('doctor.profile.data');
Route::patch('/doctor/perfil', [DoctorProfileController::class, 'update'])->name('doctor.profile.update');
Route::post('/doctor/perfil/photo', [DoctorProfileController::class, 'updatePhoto'])->name('doctor.profile.photo');

Route::get('/admin/especialidades/data', [SpecialtyController::class, 'indexData'])->name('admin.specialties.data');
Route::post('/admin/especialidades', [SpecialtyController::class, 'store'])->name('admin.specialties.store');
Route::patch('/admin/especialidades/{specialty}', [SpecialtyController::class, 'update'])->name('admin.specialties.update');
Route::delete('/admin/especialidades/{specialty}', [SpecialtyController::class, 'destroy'])->name('admin.specialties.destroy');

Route::get('/admin/logs/data', [ActivityLogController::class, 'indexData'])->name('admin.logs.data');

Route::middleware('admin.role')->group(function () {
    Route::view('/admin/rbac', 'admin.rbac')->name('admin.rbac');

    Route::get('/admin/rbac/data', function () {
        // TEMPORARY MOCK DATA.
        // Remove this payload when the admin RBAC backend is available in production.
        return response()->json([
            'users' => [
                [
                    'id' => 1,
                    'initials' => 'JC',
                    'color' => '#1976d2',
                    'name' => 'Jose Carlos Calles',
                    'email' => 'admin@medschedule.com',
                    'roleId' => 'admin',
                    'status' => 'activo',
                ],
                [
                    'id' => 2,
                    'initials' => 'MG',
                    'color' => '#28a745',
                    'name' => 'Dr. Miguel Garcia',
                    'email' => 'garcia@medschedule.com',
                    'roleId' => 'doctor',
                    'status' => 'activo',
                ],
                [
                    'id' => 3,
                    'initials' => 'AL',
                    'color' => '#fd7e14',
                    'name' => 'Ana Lopez',
                    'email' => 'ana@gmail.com',
                    'roleId' => 'patient',
                    'status' => 'activo',
                ],
                [
                    'id' => 4,
                    'initials' => 'CR',
                    'color' => '#6b7280',
                    'name' => 'Carlos Ramirez',
                    'email' => 'carlos@gmail.com',
                    'roleId' => 'patient',
                    'status' => 'inactivo',
                ],
            ],
            'roles' => [
                [
                    'id' => 'admin',
                    'label' => 'admin',
                    'icon' => 'bi bi-shield-lock',
                    'tone' => 'admin',
                    'permissionIds' => [
                        'gestionar_usuarios',
                        'ver_logs',
                        'ver_estadisticas',
                        'gestionar_citas',
                    ],
                ],
                [
                    'id' => 'doctor',
                    'label' => 'doctor',
                    'icon' => 'bi bi-file-earmark-medical',
                    'tone' => 'doctor',
                    'permissionIds' => [
                        'ver_agenda',
                        'confirmar_citas',
                        'escribir_historial',
                    ],
                ],
                [
                    'id' => 'patient',
                    'label' => 'patient',
                    'icon' => 'bi bi-person',
                    'tone' => 'patient',
                    'permissionIds' => [
                        'agendar_cita',
                        'cancelar_cita',
                        'ver_mis_citas',
                    ],
                ],
            ],
            'permissions' => [
                [
                    'id' => 'gestionar_usuarios',
                    'label' => 'gestionar_usuarios',
                    'description' => 'Crear, editar y desactivar usuarios',
                    'enabled' => true,
                ],
                [
                    'id' => 'ver_logs',
                    'label' => 'ver_logs',
                    'description' => 'Ver registros de actividad',
                    'enabled' => true,
                ],
                [
                    'id' => 'ver_estadisticas',
                    'label' => 'ver_estadisticas',
                    'description' => 'Consultar paneles administrativos',
                    'enabled' => true,
                ],
                [
                    'id' => 'gestionar_citas',
                    'label' => 'gestionar_citas',
                    'description' => 'Crear y reasignar citas medicas',
                    'enabled' => true,
                ],
                [
                    'id' => 'ver_agenda',
                    'label' => 'ver_agenda',
                    'description' => 'Revisar agenda diaria del doctor',
                    'enabled' => true,
                ],
                [
                    'id' => 'confirmar_citas',
                    'label' => 'confirmar_citas',
                    'description' => 'Confirmar o cancelar citas',
                    'enabled' => true,
                ],
                [
                    'id' => 'escribir_historial',
                    'label' => 'escribir_historial',
                    'description' => 'Agregar diagnostico y tratamiento',
                    'enabled' => false,
                ],
                [
                    'id' => 'agendar_cita',
                    'label' => 'agendar_cita',
                    'description' => 'Solicitar nueva cita medica',
                    'enabled' => true,
                ],
                [
                    'id' => 'cancelar_cita',
                    'label' => 'cancelar_cita',
                    'description' => 'Cancelar citas propias',
                    'enabled' => true,
                ],
                [
                    'id' => 'ver_mis_citas',
                    'label' => 'ver_mis_citas',
                    'description' => 'Consultar citas agendadas',
                    'enabled' => true,
                ],
            ],
        ]);
    })->name('admin.rbac.data');
});
