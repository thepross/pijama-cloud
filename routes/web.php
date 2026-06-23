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

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('global-search', [SearchController::class, 'search'])->name('global-search');
    Route::post('puntuaciones', [PuntuacionController::class, 'store'])->name('puntuaciones.store');
    Route::delete('puntuaciones/{puntuacion}', [PuntuacionController::class, 'destroy'])->name('puntuaciones.destroy');
    Route::resource('roles', RoleController::class);
    Route::resource('usuarios', UserController::class);
    Route::resource('productos', ProductoController::class);
    Route::resource('ofertas', OfertaController::class);
    Route::resource('pedidos', PedidoController::class);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
