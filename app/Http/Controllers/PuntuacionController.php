<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Puntuacion;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;

class PuntuacionController extends Controller
{
    /**
     * Store a newly created rating in the database.
     */
    public function store(Request $request): RedirectResponse
    {
        // 1. Authorize: Only users with the role "Cliente" can write reviews.
        if (!Auth::check()) {
            abort(401, 'No autenticado.');
        }

        if (Auth::user()->role->nombre !== 'Cliente') {
            abort(403, 'Solo los clientes pueden calificar productos.');
        }

        // 2. Validate inputs in Spanish
        $request->validate([
            'id_producto' => 'required|exists:productos,id',
            'puntuacion' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:1000',
        ], [
            'id_producto.required' => 'El producto es obligatorio.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'puntuacion.required' => 'La puntuación es obligatoria.',
            'puntuacion.integer' => 'La puntuación debe ser un número entero.',
            'puntuacion.min' => 'La puntuación mínima es 1 estrella.',
            'puntuacion.max' => 'La puntuación máxima es 5 estrellas.',
            'comentario.max' => 'El comentario no puede exceder los 1000 caracteres.',
        ]);

        // 3. Verify product is active
        $producto = Producto::findOrFail($request->id_producto);
        if ($producto->state === 'inactivo') {
            abort(404, 'El producto no está disponible.');
        }

        // 4. Create the Puntuacion
        $puntuacion = Puntuacion::create([
            'id_cliente' => Auth::id(),
            'id_producto' => $request->id_producto,
            'puntuacion' => $request->puntuacion,
            'comentario' => $request->comentario,
            'fecha_puntuacion' => now()->toDateString(),
            'state' => 'activo',
        ]);

        // 5. Audit Log in Bitacora
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_puntuacion',
            'ip' => $request->ip(),
            'recurso' => 'productos/' . $producto->id . '/puntuaciones',
            'detalle' => json_encode([
                'id' => $puntuacion->id,
                'id_producto' => $producto->id,
                'puntuacion' => $puntuacion->puntuacion,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Tu puntuación ha sido registrada exitosamente.');
    }

    /**
     * Logically delete (moderate) the specified rating.
     */
    public function destroy(Request $request, Puntuacion $puntuacion): RedirectResponse
    {
        if (!Auth::check()) {
            abort(401, 'No autenticado.');
        }

        $roleName = Auth::user()->role->nombre;
        if (!in_array($roleName, ['Administrador', 'Vendedor'])) {
            abort(403, 'No tienes permiso para moderar valoraciones.');
        }

        if ($puntuacion->state === 'inactivo') {
            return back()->with('error', 'Esta valoración ya está inactiva.');
        }

        $puntuacion->update(['state' => 'inactivo']);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_puntuacion',
            'ip' => $request->ip(),
            'recurso' => 'productos/' . $puntuacion->id_producto . '/puntuaciones/' . $puntuacion->id,
            'detalle' => json_encode([
                'id' => $puntuacion->id,
                'id_producto' => $puntuacion->id_producto,
                'estado' => 'inactivo',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Valoración eliminada lógicamente.');
    }
}
