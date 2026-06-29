<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\DetallePedido;
use App\Models\Envio;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PedidoController extends Controller
{
    private function authorizePedidoAction(string $action, ?Pedido $pedido = null): void
    {
        if (! Auth::check()) {
            abort(401, 'No autenticado.');
        }

        $user = Auth::user();
        $mapping = [
            'index' => 'pedidos.ver',
            'show' => 'pedidos.ver',
            'create' => 'pedidos.crear',
            'store' => 'pedidos.crear',
            'edit' => 'pedidos.editar',
            'update' => 'pedidos.editar',
            'destroy' => 'pedidos.eliminar',
        ];

        $perm = $mapping[$action] ?? 'pedidos.ver';

        if (! $user->role->hasPermission($perm)) {
            abort(403, 'No tienes permiso para realizar esta acción sobre pedidos.');
        }

        $roleName = $user->role->nombre;
        if ($roleName === 'Cliente' && $pedido) {
            if ($pedido->id_cliente !== $user->id) {
                abort(403, 'No tienes acceso a este pedido.');
            }
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizePedidoAction('index');

        $search = $request->input('search');
        $status = $request->input('status');

        $roleName = Auth::user()->role->nombre;

        $query = Pedido::query()
            ->with(['cliente:id,nombre,apellido,email,username,ci', 'pagos'])
            ->where('state', 'activo');

        if ($roleName === 'Cliente') {
            $query->where('id_cliente', Auth::id());
        }

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
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorizePedidoAction('create');

        $productos = Producto::where('state', 'activo')
            ->where('stock', '>', 0)
            ->with([
                'ofertas' => function ($query) {
                    $query->where('estado_oferta', 'activa')
                        ->where('fecha_inicio', '<=', now()->toDateString())
                        ->where('fecha_fin', '>=', now()->toDateString())
                        ->where('state', 'activo');
                },
            ])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('pedidos/Create', [
            'productos' => $productos,
        ]);
    }

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

                if ($producto->stock < $item['cantidad']) {
                    DB::rollBack();

                    return back()->withErrors([
                        'items' => "El producto '{$producto->nombre}' no tiene suficiente stock. Disponible: {$producto->stock} unidades.",
                    ])->withInput();
                }

                $offer = $producto->ofertas()
                    ->where('estado_oferta', 'activa')
                    ->where('fecha_inicio', '<=', now()->toDateString())
                    ->where('fecha_fin', '>=', now()->toDateString())
                    ->where('state', 'activo')
                    ->first();

                $precioVenta = (float) $producto->precio_venta;
                $descuento = 0.0;

                if ($offer) {
                    if ($offer->tipo_descuento === 'porcentaje') {
                        $descuento = $precioVenta * ((float) $offer->valor_descuento / 100);
                    } else {
                        $descuento = (float) $offer->valor_descuento;
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
                    'producto_model' => $producto,
                ];
            }

            $pedido = Pedido::create([
                'id_cliente' => Auth::id(),
                'fecha_pedido' => now()->toDateString(),
                'total' => $total,
                'estado_pedido' => 'pendiente',
                'observacion' => $request->observacion,
                'state' => 'activo',
            ]);

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

            return back()->withErrors(['error' => 'Ocurrió un error al procesar el pedido: '.$e->getMessage()])->withInput();
        }
    }

    public function show(Pedido $pedido): Response
    {
        $this->authorizePedidoAction('show', $pedido);

        if ($pedido->state === 'inactivo') {
            abort(404, 'El pedido no se encuentra activo.');
        }

        $pedido->load([
            'cliente:id,nombre,apellido,email,username,ci,telefono,direccion',
            'detalles.producto:id,nombre,codigo_qr,precio_venta,foto',
        ]);

        return Inertia::render('pedidos/Show', [
            'pedido' => $pedido,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function update(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->authorizePedidoAction('update', $pedido);

        if ($pedido->state === 'inactivo') {
            abort(404, 'El pedido no se encuentra activo.');
        }

        $roleName = Auth::user()->role->nombre;

        if ($roleName === 'Cliente') {

            $request->validate([
                'estado_pedido' => 'required|string|in:cancelado',
            ], [
                'estado_pedido.in' => 'Solo puedes cambiar el estado de tu pedido a "cancelado".',
            ]);

            if ($pedido->estado_pedido !== 'pendiente') {
                return back()->with('error', 'Solo puedes cancelar tu pedido si se encuentra en estado "pendiente".');
            }
        } else {

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

            if ($newStatus === 'cancelado' && $oldStatus !== 'cancelado') {

                foreach ($pedido->detalles as $detalle) {
                    $producto = $detalle->producto;
                    $producto->increment('stock', $detalle->cantidad);
                }
            } elseif ($oldStatus === 'cancelado' && $newStatus !== 'cancelado') {

                foreach ($pedido->detalles as $detalle) {
                    $producto = $detalle->producto;
                    if ($producto->stock < $detalle->cantidad) {
                        DB::rollBack();

                        return back()->with('error', "No hay suficiente stock para reactivar este pedido. Producto '{$producto->nombre}' solo tiene {$producto->stock} unidades disponibles.");
                    }
                    $producto->decrement('stock', $detalle->cantidad);
                }
            }

            $pedido->update([
                'estado_pedido' => $newStatus,
                'observacion' => $roleName !== 'Cliente' && $request->has('observacion') ? $request->observacion : $pedido->observacion,
            ]);

            if ($newStatus === 'confirmado' && $oldStatus !== 'confirmado') {
                $hasShipment = Envio::where('id_pedido', $pedido->id)
                    ->where('state', 'activo')
                    ->exists();
                if (! $hasShipment) {
                    Envio::create([
                        'id_pedido' => $pedido->id,
                        'id_distribuidor' => null,
                        'direccion_entrega' => $pedido->cliente->direccion ?? 'Sin dirección especificada',
                        'estado_envio' => 'pendiente',
                        'state' => 'activo',
                    ]);
                }
            }

            $event = ($newStatus === 'cancelado') ? 'cancelar_pedido' : 'actualizar_estado_pedido';
            Bitacora::create([
                'id_usuario' => Auth::id(),
                'evento' => $event,
                'ip' => $request->ip(),
                'recurso' => 'pedidos/'.$pedido->id,
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

            return back()->with('error', 'Ocurrió un error al actualizar el pedido: '.$e->getMessage());
        }
    }

    public function destroy(Request $request, Pedido $pedido): RedirectResponse
    {
        $this->authorizePedidoAction('destroy');

        if ($pedido->state === 'inactivo') {
            abort(404, 'El pedido ya se encuentra inactivo.');
        }

        try {
            DB::beginTransaction();

            if ($pedido->estado_pedido !== 'cancelado') {
                foreach ($pedido->detalles as $detalle) {
                    $detalle->producto->increment('stock', $detalle->cantidad);
                }
            }

            $pedido->update(['state' => 'inactivo']);

            Bitacora::create([
                'id_usuario' => Auth::id(),
                'evento' => 'eliminar_pedido',
                'ip' => $request->ip(),
                'recurso' => 'pedidos/'.$pedido->id,
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

            return back()->with('error', 'Error al eliminar el pedido: '.$e->getMessage());
        }
    }
}
