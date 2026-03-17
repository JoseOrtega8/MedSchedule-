<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ActivityLogController;
use App\Models\User;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleCalendarController;


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
	return ['roles' => $user->getRoleNames(), 'has_admin' => $user->hasRole('admin'),];
});
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
	Route::get('/admin/logs/user/{user_id}', [ActivityLogController::class, 'getByUser'])->name('admin.logs.user');
	Route::get('/admin/logs/{id}', [ActivityLogController::class, 'show'])->name('admin.logs.show');
});

Route::middleware(['auth', 'role:doctor'])->group(function () {
	Route::get('/doctor/dashboard', [DashboardController::class, 'index'])->name('doctor.dashboard');
	Route::get('/doctor/dashboard/data', [DashboardController::class, 'doctorData'])->name('doctor.dashboard.data');
	Route::post('/appointments/{appointment}/sync-calendar', [GoogleCalendarController::class, 'sync'])->name('appointments.calendar.sync');
	Route::delete('/appointments/{appointment}/sync-calendar', [GoogleCalendarController::class, 'unsync'])->name('appointments.calendar.unsync');
});

Route::middleware(['auth', 'role:patient'])->group(function () {
	Route::get('/patient/dashboard', [DashboardController::class, 'index'])->name('patient.dashboard');
	Route::get('/patient/dashboard/data', [DashboardController::class, 'patientData'])->name('patient.dashboard.data');
});
require __DIR__ . '/auth.php';
