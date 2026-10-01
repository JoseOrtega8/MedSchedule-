<?php

use App\Http\Controllers\MetricasController;
use Illuminate\Support\Facades\Route;

// Rutas de observabilidad: sin sesion, cookies ni CSRF, porque las consume
// Prometheus cada 15 s y no un navegador
Route::get('/metrics', MetricasController::class)->name('metricas');
