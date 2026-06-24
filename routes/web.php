<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\PuntuacionController;
use App\Http\Controllers\OfertaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\EnvioController;
use App\Http\Controllers\ReclamoController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\EstadisticaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BitacoraController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('global-search', [SearchController::class, 'search'])->name('global-search');
    Route::post('puntuaciones', [PuntuacionController::class, 'store'])->name('puntuaciones.store');
    Route::delete('puntuaciones/{puntuacion}', [PuntuacionController::class, 'destroy'])->name('puntuaciones.destroy');
    Route::resource('roles', RoleController::class);
    Route::resource('usuarios', UserController::class);
    Route::resource('productos', ProductoController::class);
    Route::resource('ofertas', OfertaController::class);
    Route::resource('pedidos', PedidoController::class);
    Route::resource('envios', EnvioController::class);
    Route::resource('reclamos', ReclamoController::class);
    Route::resource('pagos', PagoController::class);
    Route::post('pagos/{pago}/simular-callback', [PagoController::class, 'simularCallback'])->name('pagos.simular-callback');
    Route::get('estadisticas', [EstadisticaController::class, 'index'])->name('estadisticas.index');
    Route::get('bitacoras', [BitacoraController::class, 'index'])->name('bitacoras.index');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
