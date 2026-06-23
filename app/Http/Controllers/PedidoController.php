<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Producto;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PedidoController extends Controller
{
    /**
     * Authorize order management actions.
     */
    private function authorizePedidoAction(string $action, ?Pedido $pedido = null): void
    {
        if (!Auth::check()) {
            abort(401, 'No autenticado.');
        }

        // Verify the user has access permission for 'pedidos' in general
        if (!Auth::user()->role->permissions()->where('ruta', 'pedidos')->exists()) {
            abort(403, 'No tienes permiso para acceder a pedidos.');
        }

        $roleName = Auth::user()->role->nombre;

        if ($action === 'create' || $action === 'store') {
            if ($roleName !== 'Cliente') {
                abort(403, 'Solo los clientes pueden crear nuevos pedidos.');
            }
        }

        if ($action === 'show' && $pedido) {
            if ($roleName === 'Cliente' && $pedido->id_cliente !== Auth::id()) {
                abort(403, 'No tienes permiso para ver este pedido.');
            }
        }

        if ($action === 'update' && $pedido) {
            if ($roleName === 'Cliente') {
                if ($pedido->id_cliente !== Auth::id()) {
                    abort(403, 'No puedes modificar este pedido.');
                }
            }
        }

        if ($action === 'destroy') {
            if ($roleName !== 'Administrador') {
                abort(403, 'Solo los administradores pueden eliminar pedidos.');
            }
        }
    }

    /**
     * Display a listing of active orders.
     */
    public function index(Request $request): Response
    {
        $this->authorizePedidoAction('index');

        $search = $request->input('search');
        $status = $request->input('status'); // 'pendiente', 'confirmado', 'entregado', 'cancelado'

        $roleName = Auth::user()->role->nombre;

        $query = Pedido::query()
            ->with('cliente:id,nombre,apellido,email,username,ci')
            ->where('state', 'activo');

        // Customers can only see their own orders
        if ($roleName === 'Cliente') {
            $query->where('id_cliente', Auth::id());
        }

        // Apply filters
        $query->when($search, function ($q, $search) use ($roleName) {
            $q->where(function ($sub) use ($search, $roleName) {
                $sub->where('id', 'like', "%{$search}%")
                    ->orWhere('observacion', 'like', "%{$search}%");

                if ($roleName !== 'Cliente') {
                    $sub->orWhereHas('cliente', function ($custQuery) use ($search) {
                        $custQuery->where('nombre', 'like', "%{$search}%")
                                  ->orWhere('apellido', 'like', "%{$search}%")
                                  ->orWhere('username', 'like', "%{$search}%")
                                  ->orWhere('email', 'like', "%{$search}%")
                                  ->orWhere('ci', 'like', "%{$search}%");
                    });
                }
            });
        });

        $query->when($status, function ($q, $status) {
            $q->where('estado_pedido', $status);
        });

        $pedidos = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('pedidos/Index', [
            'pedidos' => $pedidos,
            'filters' => $request->only(['search', 'status']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new order.
     */
    public function create(): Response
    {
        $this->authorizePedidoAction('create');

        // Fetch active products with stock
        $productos = Producto::where('state', 'activo')
            ->where('stock', '>', 0)
            ->with([
                'ofertas' => function ($query) {
                    $query->where('estado_oferta', 'activa')
                          ->where('fecha_inicio', '<=', now()->toDateString())
                          ->where('fecha_fin', '>=', now()->toDateString())
                          ->where('state', 'activo');
                }
            ])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('pedidos/Create', [
            'productos' => $productos,
        ]);
    }

    /**
     * Store a newly created order in database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizePedidoAction('store');

        $request->validate([
            'observacion' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.id_producto' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|integer|min:1',
        ], [
            'items.required' => 'El pedido debe contener al menos un producto.',
            'items.min' => 'El pedido debe contener al menos un producto.',
            'items.*.id_producto.required' => 'Producto no válido.',
            'items.*.id_producto.exists' => 'El producto seleccionado no existe.',
            'items.*.cantidad.required' => 'Debe ingresar una cantidad.',
            'items.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'items.*.cantidad.min' => 'La cantidad mínima es 1 unidad.',
        ]);

        try {
            DB::beginTransaction();

            $total = 0;
            $detalles = [];

            foreach ($request->items as $item) {
                $producto = Producto::where('id', $item['id_producto'])
                    ->where('state', 'activo')
                    ->firstOrFail();

                // 1. Check stock
                if ($producto->stock < $item['cantidad']) {
                    DB::rollBack();
                    return back()->withErrors([
                        'items' => "El producto '{$producto->nombre}' no tiene suficiente stock. Disponible: {$producto->stock} unidades."
                    ])->withInput();
                }

                // 2. Fetch active offer
                $offer = $producto->ofertas()
                    ->where('estado_oferta', 'activa')
                    ->where('fecha_inicio', '<=', now()->toDateString())
                    ->where('fecha_fin', '>=', now()->toDateString())
                    ->where('state', 'activo')
                    ->first();

                $precioVenta = (float)$producto->precio_venta;
                $descuento = 0.0;

                if ($offer) {
                    if ($offer->tipo_descuento === 'porcentaje') {
                        $descuento = $precioVenta * ((float)$offer->valor_descuento / 100);
                    } else {
                        $descuento = (float)$offer->valor_descuento;
                    }
                }

                $subtotal = $item['cantidad'] * ($precioVenta - $descuento);
                $total += $subtotal;

                $detalles[] = [
                    'id_producto' => $producto->id,
                    'cantidad' => $item['cantidad'],
                    'precio_venta' => $precioVenta,
                    'descuento' => $descuento,
                    'subtotal' => $subtotal,
                    'producto_model' => $producto // save model reference to update stock
                ];
            }

            // 3. Create the order
            $pedido = Pedido::create([
                'id_cliente' => Auth::id(),
                'fecha_pedido' => now()->toDateString(),
                'total' => $total,
                'estado_pedido' => 'pendiente',
                'observacion' => $request->observacion,
                'state' => 'activo',
            ]);

            // 4. Save details and deduct stock
            foreach ($detalles as $det) {
                DetallePedido::create([
                    'id_pedido' => $pedido->id,
                    'id_producto' => $det['id_producto'],
                    'cantidad' => $det['cantidad'],
                    'precio_venta' => $det['precio_venta'],
                    'descuento' => $det['descuento'],
                    'subtotal' => $det['subtotal'],
                    'state' => 'activo',
                ]);

                $det['producto_model']->decrement('stock', $det['cantidad']);
            }

            // 5. Audit Log
            Bitacora::create([
                'id_usuario' => Auth::id(),
                'evento' => 'crear_pedido',
                'ip' => $request->ip(),
                'recurso' => 'pedidos',
                'detalle' => json_encode([
                    'id' => $pedido->id,
                    'total' => $pedido->total,
                    'items_count' => count($detalles),
                ], JSON_UNESCAPED_UNICODE),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return to_route('pedidos.index')->with('success', 'Pedido realizado exitosamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Ocurrió un error al procesar el pedido: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display order details.
     */
    public function show(Pedido $pedido): Response
    {
        $this->authorizePedidoAction('show', $pedido);

        if ($pedido->state === 'inactivo') {
            abort(404, 'El pedido no se encuentra activo.');
        }

        $pedido->load([
            'cliente:id,nombre,apellido,email,username,ci,telefono,direccion',
            'detalles.producto:id,nombre,codigo_qr,precio_venta,foto'
        ]);

        return Inertia::render('pedidos/Show', [
            'pedido' => $pedido,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ]
        ]);
    }

    /**
     * Update the specified order's status.
     */
    public function update(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->authorizePedidoAction('update', $pedido);

        if ($pedido->state === 'inactivo') {
            abort(404, 'El pedido no se encuentra activo.');
        }

        $roleName = Auth::user()->role->nombre;

        // Custom validation based on role bounds
        if ($roleName === 'Cliente') {
            // Customer can only cancel
            $request->validate([
                'estado_pedido' => 'required|string|in:cancelado',
            ], [
                'estado_pedido.in' => 'Solo puedes cambiar el estado de tu pedido a "cancelado".',
            ]);

            if ($pedido->estado_pedido !== 'pendiente') {
                return back()->with('error', 'Solo puedes cancelar tu pedido si se encuentra en estado "pendiente".');
            }
        } else {
            // Staff can transition to any valid state
            $request->validate([
                'estado_pedido' => 'required|string|in:pendiente,confirmado,entregado,cancelado',
                'observacion' => 'nullable|string|max:1000',
            ], [
                'estado_pedido.required' => 'El estado del pedido es obligatorio.',
                'estado_pedido.in' => 'El estado seleccionado no es válido.',
            ]);
        }

        $oldStatus = $pedido->estado_pedido;
        $newStatus = $request->estado_pedido;

        try {
            DB::beginTransaction();

            // Stock adjustments
            if ($newStatus === 'cancelado' && $oldStatus !== 'cancelado') {
                // Restore stock
                foreach ($pedido->detalles as $detalle) {
                    $producto = $detalle->producto;
                    $producto->increment('stock', $detalle->cantidad);
                }
            } elseif ($oldStatus === 'cancelado' && $newStatus !== 'cancelado') {
                // Re-deduct stock and check if available
                foreach ($pedido->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto->stock < $detalle->cantidad) {
                        DB::rollBack();
                        return back()->with('error', "No hay suficiente stock para reactivar este pedido. Producto '{$producto->nombre}' solo tiene {$producto->stock} unidades disponibles.");
                    }
                    $producto->decrement('stock', $detalle->cantidad);
                }
            }

            // Update pedido
            $pedido->update([
                'estado_pedido' => $newStatus,
                'observacion' => $roleName !== 'Cliente' && $request->has('observacion') ? $request->observacion : $pedido->observacion,
            ]);

            // Audit Log
            $event = ($newStatus === 'cancelado') ? 'cancelar_pedido' : 'actualizar_estado_pedido';
            Bitacora::create([
                'id_usuario' => Auth::id(),
                'evento' => $event,
                'ip' => $request->ip(),
                'recurso' => 'pedidos/' . $pedido->id,
                'detalle' => json_encode([
                    'id' => $pedido->id,
                    'estado_anterior' => $oldStatus,
                    'estado_nuevo' => $newStatus,
                ], JSON_UNESCAPED_UNICODE),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            $msg = ($newStatus === 'cancelado') ? 'Pedido cancelado correctamente.' : 'Estado del pedido actualizado.';
            return back()->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ocurrió un error al actualizar el pedido: ' . $e->getMessage());
        }
    }

    /**
     * Remove (logical delete) the specified order.
     */
    public function destroy(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->authorizePedidoAction('destroy');

        if ($pedido->state === 'inactivo') {
            abort(404, 'El pedido ya se encuentra inactivo.');
        }

        try {
            DB::beginTransaction();

            // If deleting an order that is not already cancelled, we should restore stock
            if ($pedido->estado_pedido !== 'cancelado') {
                foreach ($pedido->detalles as $detalle) {
                    $detalle->producto->increment('stock', $detalle->cantidad);
                }
            }

            $pedido->update(['state' => 'inactivo']);

            // Audit Log
            Bitacora::create([
                'id_usuario' => Auth::id(),
                'evento' => 'eliminar_pedido',
                'ip' => $request->ip(),
                'recurso' => 'pedidos/' . $pedido->id,
                'detalle' => json_encode([
                    'id' => $pedido->id,
                    'state' => 'inactivo',
                ], JSON_UNESCAPED_UNICODE),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return to_route('pedidos.index')->with('success', 'Pedido eliminado lógicamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al eliminar el pedido: ' . $e->getMessage());
        }
    }
}
