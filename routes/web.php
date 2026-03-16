<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SpecialtyController;
use App\Models\User;

Route::get('/', function () {
	return view('welcome');
});

Route::get('/dashboard', function () {
	return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
	Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
	Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
	Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/test-role', function () {
	$user = User::first();
	return ['roles' => $user->getRoleNames(), 'has_admin' => $user->hasRole('admin')];
});

Route::view('/about', 'about.about')->name('about');

/*
|--------------------------------------------------------------------------
| Dashboards por rol
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->group(function () {
	Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
	Route::get('/admin/dashboard/data', [DashboardController::class, 'adminData'])->name('admin.dashboard.data');
	Route::get('/admin/dashboard/users-chart', [DashboardController::class, 'getUsersChart'])->name('admin.dashboard.users-chart');
	Route::get('/admin/dashboard/appointments-chart', [DashboardController::class, 'getAppointmentsChart'])->name('admin.dashboard.appointments-chart');
	Route::get('/admin/dashboard/recent-activity', [DashboardController::class, 'getRecentActivity'])->name('admin.dashboard.recent-activity');
	Route::get('/admin/logs', [ActivityLogController::class, 'index'])->name('admin.logs.index');
	Route::get('/admin/logs/data', [ActivityLogController::class, 'index'])->name('admin.logs.data');
	Route::get('/admin/logs/user/{user_id}', [ActivityLogController::class, 'getByUser'])->name('admin.logs.user');
	Route::get('/admin/logs/{id}', [ActivityLogController::class, 'show'])->name('admin.logs.show');
	Route::view('/admin/especialidades', 'admin.especialidades')->name('admin.specialties');
	Route::get('/admin/especialidades/data', [SpecialtyController::class, 'indexData'])->name('admin.specialties.data');
	Route::post('/admin/especialidades', [SpecialtyController::class, 'store'])->name('admin.specialties.store');
	Route::patch('/admin/especialidades/{specialty}', [SpecialtyController::class, 'update'])->name('admin.specialties.update');
	Route::delete('/admin/especialidades/{specialty}', [SpecialtyController::class, 'destroy'])->name('admin.specialties.destroy');
	Route::view('/admin/rbac', 'admin.rbac')->name('admin.rbac');
	Route::get('/admin/rbac/data', function () {
		return response()->json(['message' => 'RBAC data endpoint']);
	})->name('admin.rbac.data');
});

Route::middleware(['auth', 'role:doctor'])->group(function () {
	Route::get('/doctor/dashboard', [DashboardController::class, 'index'])->name('doctor.dashboard');
	Route::get('/doctor/dashboard/data', [DashboardController::class, 'doctorData'])->name('doctor.dashboard.data');
	Route::view('/doctor/agenda', 'doctor.agenda')->name('doctor.agenda');
	Route::get('/doctor/agenda/data', [AppointmentController::class, 'agendaData'])->name('doctor.agenda.data');
	Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
	Route::view('/doctor/horarios', 'doctor.horarios')->name('doctor.schedules');
	Route::get('/doctor/horarios/data', [ScheduleController::class, 'indexData'])->name('doctor.schedules.data');
	Route::post('/doctor/horarios', [ScheduleController::class, 'store'])->name('doctor.schedules.store');
	Route::patch('/doctor/horarios/{schedule}', [ScheduleController::class, 'update'])->name('doctor.schedules.update');
	Route::delete('/doctor/horarios/{schedule}', [ScheduleController::class, 'destroy'])->name('doctor.schedules.destroy');
	Route::view('/doctor/perfil', 'doctor.perfil')->name('doctor.profile');
	Route::get('/doctor/perfil/data', [DoctorProfileController::class, 'indexData'])->name('doctor.profile.data');
	Route::patch('/doctor/perfil', [DoctorProfileController::class, 'update'])->name('doctor.profile.update');
	Route::post('/doctor/perfil/photo', [DoctorProfileController::class, 'updatePhoto'])->name('doctor.profile.photo');
});

Route::middleware(['auth', 'role:patient'])->group(function () {
	Route::get('/patient/dashboard', [DashboardController::class, 'index'])->name('patient.dashboard');
	Route::get('/patient/dashboard/data', [DashboardController::class, 'patientData'])->name('patient.dashboard.data');
});

require __DIR__ . '/auth.php';
