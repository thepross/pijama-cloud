<?php

namespace App\Http\Controllers;

use App\Models\Oferta;
use App\Models\Producto;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class OfertaController extends Controller
{
    /**
     * Authorize offer management actions.
     */
    private function authorizeOfferAction(string $action): void
    {
        if (!Auth::check() || !Auth::user()->role->permissions()->where('ruta', 'ofertas')->exists()) {
            abort(403, 'No tienes permiso para ver ofertas.');
        }

        $modifyingActions = ['create', 'store', 'edit', 'update', 'destroy'];
        if (in_array($action, $modifyingActions)) {
            $roleName = Auth::user()->role->nombre;
            if ($roleName !== 'Administrador') {
                abort(403, 'No tienes permiso para modificar ofertas.');
            }
        }
    }

    /**
     * Display a listing of the active offers.
     */
    public function index(Request $request): Response
    {
        $this->authorizeOfferAction('index');

        $search = $request->input('search');
        $status = $request->input('status'); // 'activa', 'inactiva', 'vencida', 'programada'

        $ofertas = Oferta::query()
            ->with('producto:id,nombre,codigo_qr,precio_venta,foto')
            ->where('state', 'activo')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%")
                      ->orWhereHas('producto', function ($prodQuery) use ($search) {
                          $prodQuery->where('nombre', 'like', "%{$search}%")
                                    ->orWhere('codigo_qr', 'like', "%{$search}%");
                      });
                });
            })
            ->when($status, function ($query, $status) {
                $today = now()->toDateString();
                if ($status === 'activa') {
                    $query->where('estado_oferta', 'activa')
                          ->where('fecha_inicio', '<=', $today)
                          ->where('fecha_fin', '>=', $today);
                } elseif ($status === 'inactiva') {
                    $query->where('estado_oferta', 'inactiva');
                } elseif ($status === 'vencida') {
                    $query->where('estado_oferta', 'activa')
                          ->where('fecha_fin', '<', $today);
                } elseif ($status === 'programada') {
                    $query->where('estado_oferta', 'activa')
                          ->where('fecha_inicio', '>', $today);
                }
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('ofertas/Index', [
            'ofertas' => $ofertas,
            'filters' => $request->only(['search', 'status']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new offer.
     */
    public function create(): Response
    {
        $this->authorizeOfferAction('create');

        $productos = Producto::where('state', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo_qr', 'precio_venta', 'foto']);

        return Inertia::render('ofertas/Create', [
            'productos' => $productos,
        ]);
    }

    /**
     * Store a newly created offer in database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeOfferAction('store');

        $request->validate([
            'id_producto' => 'required|exists:productos,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'valor_descuento' => 'required|numeric|min:0.01',
            'tipo_descuento' => 'required|string|in:porcentaje,monto',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado_oferta' => 'required|string|in:activa,inactiva',
        ], [
            'id_producto.required' => 'Debe seleccionar un producto.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'nombre.required' => 'El nombre de la oferta es obligatorio.',
            'valor_descuento.required' => 'El valor de descuento es obligatorio.',
            'valor_descuento.numeric' => 'El valor de descuento debe ser un número.',
            'valor_descuento.min' => 'El descuento mínimo es 0.01.',
            'tipo_descuento.required' => 'El tipo de descuento es obligatorio.',
            'tipo_descuento.in' => 'El tipo de descuento seleccionado no es válido.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'estado_oferta.required' => 'El estado de la oferta es obligatorio.',
            'estado_oferta.in' => 'El estado seleccionado no es válido.',
        ]);

        $producto = Producto::findOrFail($request->id_producto);

        // Business Rule validation checks
        if ($request->tipo_descuento === 'porcentaje') {
            if ($request->valor_descuento > 100) {
                return back()->withErrors(['valor_descuento' => 'El valor de descuento por porcentaje no puede ser mayor a 100%.'])->withInput();
            }
        } elseif ($request->tipo_descuento === 'monto') {
            if ($request->valor_descuento >= $producto->precio_venta) {
                return back()->withErrors(['valor_descuento' => 'El valor de descuento por monto fijo debe ser menor al precio de venta del producto ($' . $producto->precio_venta . ').'])->withInput();
            }
        }

        $oferta = Oferta::create([
            'id_producto' => $request->id_producto,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'valor_descuento' => $request->valor_descuento,
            'tipo_descuento' => $request->tipo_descuento,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'estado_oferta' => $request->estado_oferta,
            'state' => 'activo',
        ]);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_oferta',
            'ip' => $request->ip(),
            'recurso' => 'ofertas',
            'detalle' => json_encode([
                'id' => $oferta->id,
                'nombre' => $oferta->nombre,
                'producto' => $producto->nombre,
                'descuento' => $oferta->valor_descuento . ' (' . $oferta->tipo_descuento . ')',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('ofertas.index')->with('success', 'Oferta registrada exitosamente.');
    }

    /**
     * Show the form for editing the specified offer.
     */
    public function edit(Oferta $oferta): Response
    {
        $this->authorizeOfferAction('edit');

        if ($oferta->state === 'inactivo') {
            abort(404, 'La oferta no se encuentra activa.');
        }

        $productos = Producto::where('state', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo_qr', 'precio_venta', 'foto']);

        return Inertia::render('ofertas/Edit', [
            'oferta' => $oferta,
            'productos' => $productos,
        ]);
    }

    /**
     * Update the specified offer in database.
     */
    public function update(Request $request, Oferta $oferta): RedirectResponse
    {
        $this->authorizeOfferAction('update');

        if ($oferta->state === 'inactivo') {
            abort(404, 'La oferta no se encuentra activa.');
        }

        $request->validate([
            'id_producto' => 'required|exists:productos,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:1000',
            'valor_descuento' => 'required|numeric|min:0.01',
            'tipo_descuento' => 'required|string|in:porcentaje,monto',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'estado_oferta' => 'required|string|in:activa,inactiva',
        ], [
            'id_producto.required' => 'Debe seleccionar un producto.',
            'id_producto.exists' => 'El producto seleccionado no existe.',
            'nombre.required' => 'El nombre de la oferta es obligatorio.',
            'valor_descuento.required' => 'El valor de descuento es obligatorio.',
            'valor_descuento.numeric' => 'El valor de descuento debe ser un número.',
            'valor_descuento.min' => 'El descuento mínimo es 0.01.',
            'tipo_descuento.required' => 'El tipo de descuento es obligatorio.',
            'tipo_descuento.in' => 'El tipo de descuento seleccionado no es válido.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'estado_oferta.required' => 'El estado de la oferta es obligatorio.',
            'estado_oferta.in' => 'El estado seleccionado no es válido.',
        ]);

        $producto = Producto::findOrFail($request->id_producto);

        // Business Rule validation checks
        if ($request->tipo_descuento === 'porcentaje') {
            if ($request->valor_descuento > 100) {
                return back()->withErrors(['valor_descuento' => 'El valor de descuento por porcentaje no puede ser mayor a 100%.'])->withInput();
            }
        } elseif ($request->tipo_descuento === 'monto') {
            if ($request->valor_descuento >= $producto->precio_venta) {
                return back()->withErrors(['valor_descuento' => 'El valor de descuento por monto fijo debe ser menor al precio de venta del producto ($' . $producto->precio_venta . ').'])->withInput();
            }
        }

        $oferta->update([
            'id_producto' => $request->id_producto,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'valor_descuento' => $request->valor_descuento,
            'tipo_descuento' => $request->tipo_descuento,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
            'estado_oferta' => $request->estado_oferta,
        ]);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'editar_oferta',
            'ip' => $request->ip(),
            'recurso' => 'ofertas/' . $oferta->id,
            'detalle' => json_encode([
                'id' => $oferta->id,
                'nombre' => $oferta->nombre,
                'producto' => $producto->nombre,
                'descuento' => $oferta->valor_descuento . ' (' . $oferta->tipo_descuento . ')',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('ofertas.index')->with('success', 'Oferta actualizada exitosamente.');
    }

    /**
     * Remove the specified offer from database (logical delete).
     */
    public function destroy(Request $request, Oferta $oferta): RedirectResponse
    {
        $this->authorizeOfferAction('destroy');

        if ($oferta->state === 'inactivo') {
            abort(404, 'La oferta ya se encuentra inactiva.');
        }

        $oferta->update(['state' => 'inactivo']);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_oferta',
            'ip' => $request->ip(),
            'recurso' => 'ofertas/' . $oferta->id,
            'detalle' => json_encode([
                'id' => $oferta->id,
                'nombre' => $oferta->nombre,
                'state' => 'inactivo',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('ofertas.index')->with('success', 'Oferta desactivada exitosamente.');
    }
}
