<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
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
	return ['roles' => $user->getRoleNames(), 'has_admin' => $user->hasRole('admin'),];
});
/*
|--------------------------------------------------------------------------
| Dashboards por rol
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->group(function () {
	Route::get('/admin/dashboard', function () {
		return "Admin Dashboard";
	})->name('admin.dashboard');
});

Route::middleware(['auth', 'role:doctor'])->group(function () {
	Route::get('/doctor/dashboard', function () {
		return "Doctor Dashboard";
	})->name('doctor.dashboard');
});

Route::middleware(['auth', 'role:patient'])->group(function () {
	Route::get('/patient/dashboard', function () {
		return "Patient Dashboard";
	})->name('patient.dashboard');
});
require __DIR__ . '/auth.php';
