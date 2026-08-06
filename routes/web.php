<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorProfileController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\GoogleCalendarController;
use App\Http\Controllers\SpecialtyController;
use App\Models\User;

Route::get('/', function () {
	return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
	Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
	Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
	Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

	// Google Calendar — accesible para admin y doctor
	Route::post('/appointments/{appointment}/sync-calendar', [GoogleCalendarController::class, 'sync'])->name('appointments.calendar.sync');
	Route::delete('/appointments/{appointment}/sync-calendar', [GoogleCalendarController::class, 'unsync'])->name('appointments.calendar.unsync');
});

Route::get('/test-role', function () {
	$user = User::first();
	return ['roles' => $user->getRoleNames(), 'has_admin' => $user->hasRole('admin')];
});

Route::view('/about', 'about.about')->name('about');

Route::middleware(['auth', 'role:admin'])->group(function () {
	Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
	Route::get('/dashboard/data', [DashboardController::class, 'adminData'])->name('dashboard.data');
	Route::get('/admin/dashboard/users-chart', [DashboardController::class, 'getUsersChart'])->name('admin.dashboard.users-chart');
	Route::get('/admin/dashboard/appointments-chart', [DashboardController::class, 'getAppointmentsChart'])->name('admin.dashboard.appointments-chart');
	Route::get('/admin/dashboard/recent-activity', [DashboardController::class, 'getRecentActivity'])->name('admin.dashboard.recent-activity');
	Route::get('/admin/logs', [ActivityLogController::class, 'index'])->name('admin.logs');
	Route::get('/admin/logs/data', [ActivityLogController::class, 'indexData'])->name('admin.logs.data');
	Route::get('/admin/logs/user/{user_id}', [ActivityLogController::class, 'getByUser'])->name('admin.logs.user');
	Route::get('/admin/logs/{id}', [ActivityLogController::class, 'show'])->name('admin.logs.show');
	Route::view('/admin/especialidades', 'admin.especialidades')->name('admin.specialties');
	Route::get('/admin/especialidades/data', [SpecialtyController::class, 'indexData'])->name('admin.specialties.data');
	Route::post('/admin/especialidades', [SpecialtyController::class, 'store'])->name('admin.specialties.store');
	Route::patch('/admin/especialidades/{specialty}', [SpecialtyController::class, 'update'])->name('admin.specialties.update');
	Route::delete('/admin/especialidades/{specialty}', [SpecialtyController::class, 'destroy'])->name('admin.specialties.destroy');
	Route::view('/admin/rbac', 'admin.rbac')->name('admin.rbac');
	Route::get('/admin/rbac/data', function () {
		$users = \App\Models\User::with('roles')->get()->map(function ($u) {
			return [
				'id'       => $u->id,
				'initials' => strtoupper(substr($u->name, 0, 1) . substr($u->last_name, 0, 1)),
				'color'    => '#1976d2',
				'name'     => $u->name . ' ' . $u->last_name,
				'email'    => $u->email,
				'roleId'   => $u->roles->first()?->name ?? 'sin-rol',
				'status'   => $u->status ? 'activo' : 'inactivo',
			];
		});

		$roles = \Spatie\Permission\Models\Role::with('permissions')->get()->map(function ($r) {
			return [
				'id'            => $r->name,
				'label'         => $r->name,
				'icon'          => match ($r->name) {
					'admin'   => 'bi bi-shield-lock',
					'doctor'  => 'bi bi-file-earmark-medical',
					'patient' => 'bi bi-person',
					default   => 'bi bi-circle',
				},
				'tone'          => $r->name,
				'permissionIds' => $r->permissions->pluck('name')->toArray(),
			];
		});

		$permissions = \Spatie\Permission\Models\Permission::all()->map(function ($p) {
			return [
				'id'          => $p->name,
				'label'       => $p->name,
				'description' => $p->name,
				'enabled'     => true,
			];
		});

		return response()->json(compact('users', 'roles', 'permissions'));
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
