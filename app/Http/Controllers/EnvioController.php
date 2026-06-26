<?php

namespace App\Http\Controllers;

use App\Models\Envio;
use App\Models\Pedido;
use App\Models\Role;
use App\Models\User;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class EnvioController extends Controller
{
    /**
     * Authorize shipping actions.
     */
    private function authorizeEnvioAction(string $action, ?Envio $envio = null): void
    {
        if (!Auth::check()) {
            abort(401, 'No autenticado.');
        }

        $user = Auth::user();
        $mapping = [
            'index'   => 'envios.ver',
            'show'    => 'envios.ver',
            'create'  => 'envios.crear',
            'store'   => 'envios.crear',
            'edit'    => 'envios.editar',
            'update'  => 'envios.editar',
            'destroy' => 'envios.eliminar',
        ];

        $perm = $mapping[$action] ?? 'envios.ver';

        if (!$user->role->hasPermission($perm)) {
            abort(403, 'No tienes permiso para realizar esta acción sobre envíos.');
        }

        // Distributor shipment assignment restriction
        $roleName = $user->role->nombre;
        if ($roleName === 'Distribuidor' && $envio) {
            if ($envio->id_distribuidor !== $user->id) {
                abort(403, 'No tienes permiso para gestionar envíos asignados a otros distribuidores.');
            }
        }
    }

    /**
     * Display a listing of dispatches.
     */
    public function index(Request $request): Response
    {
        $this->authorizeEnvioAction('index');

        $search = $request->input('search');
        $status = $request->input('status'); // 'pendiente', 'en_camino', 'entregado', 'fallido'

        $roleName = Auth::user()->role->nombre;

        $query = Envio::query()
            ->with(['pedido.cliente', 'distribuidor:id,nombre,apellido,username,email'])
            ->where('state', 'activo');

        // Distributors only see their own assigned dispatches
        if ($roleName === 'Distribuidor') {
            $query->where('id_distribuidor', Auth::id());
        }

        // Filters
        $query->when($search, function ($q, $search) use ($roleName) {
            $q->where(function ($sub) use ($search, $roleName) {
                $sub->where('id', 'like', "%{$search}%")
                    ->orWhere('ruta', 'like', "%{$search}%")
                    ->orWhere('direccion_entrega', 'like', "%{$search}%")
                    ->orWhere('observacion', 'like', "%{$search}%")
                    ->orWhereHas('pedido', function ($pQuery) use ($search) {
                        $pQuery->where('id', 'like', "%{$search}%");
                    });

                if ($roleName !== 'Distribuidor') {
                    $sub->orWhereHas('distribuidor', function ($dQuery) use ($search) {
                        $dQuery->where('nombre', 'like', "%{$search}%")
                               ->orWhere('apellido', 'like', "%{$search}%");
                    })->orWhereHas('pedido.cliente', function ($cQuery) use ($search) {
                        $cQuery->where('nombre', 'like', "%{$search}%")
                               ->orWhere('apellido', 'like', "%{$search}%")
                               ->orWhere('ci', 'like', "%{$search}%");
                    });
                }
            });
        });

        $query->when($status, function ($q, $status) {
            $q->where('estado_envio', $status);
        });

        $envios = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('envios/Index', [
            'envios' => $envios,
            'filters' => $request->only(['search', 'status']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new shipping.
     */
    public function create(): Response
    {
        $this->authorizeEnvioAction('create');

        // Fetch active orders that don't have active shipments
        $pedidos = Pedido::where('state', 'activo')
            ->where('estado_pedido', 'confirmado')
            ->whereDoesntHave('envios', function ($query) {
                $query->where('state', 'activo');
            })
            ->with('cliente:id,nombre,apellido,direccion,ci')
            ->orderBy('id', 'desc')
            ->get();

        // Fetch all active Distributors
        $distribuidorRole = Role::where('nombre', 'Distribuidor')->first();
        $distribuidores = $distribuidorRole
            ? User::where('id_rol', $distribuidorRole->id)->where('state', 'activo')->orderBy('nombre')->get(['id', 'nombre', 'apellido', 'username'])
            : collect();

        return Inertia::render('envios/Create', [
            'pedidos' => $pedidos,
            'distribuidores' => $distribuidores,
        ]);
    }

    /**
     * Store a newly created shipping in database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEnvioAction('store');

        $request->validate([
            'id_pedido' => 'required|exists:pedidos,id',
            'id_distribuidor' => 'nullable|exists:usuarios,id',
            'direccion_entrega' => 'required|string|max:255',
            'fecha_salida' => 'nullable|date',
            'fecha_entrega' => 'nullable|date|after_or_equal:fecha_salida',
            'estado_envio' => 'required|string|in:pendiente,en_camino,entregado,fallido',
            'ruta' => 'nullable|string|max:255',
            'observacion' => 'nullable|string|max:1000',
        ], [
            'id_pedido.required' => 'El pedido asociado es obligatorio.',
            'id_pedido.exists' => 'El pedido seleccionado no existe.',
            'id_distribuidor.exists' => 'El distribuidor seleccionado no existe.',
            'direccion_entrega.required' => 'La dirección de entrega es obligatoria.',
            'fecha_salida.date' => 'La fecha de salida debe ser una fecha válida.',
            'fecha_entrega.date' => 'La fecha de entrega debe ser una fecha válida.',
            'fecha_entrega.after_or_equal' => 'La fecha de entrega debe ser igual o posterior a la fecha de salida.',
            'estado_envio.required' => 'El estado del envío es obligatorio.',
            'estado_envio.in' => 'El estado de envío no es válido.',
        ]);

        // Check if an active shipment already exists for the order
        $exists = Envio::where('id_pedido', $request->id_pedido)
            ->where('state', 'activo')
            ->exists();

        if ($exists) {
            return back()->withErrors(['id_pedido' => 'Este pedido ya cuenta con un envío activo registrado.'])->withInput();
        }

        $envio = Envio::create([
            'id_pedido' => $request->id_pedido,
            'id_distribuidor' => $request->id_distribuidor,
            'direccion_entrega' => $request->direccion_entrega,
            'fecha_salida' => $request->fecha_salida,
            'fecha_entrega' => $request->fecha_entrega,
            'estado_envio' => $request->estado_envio,
            'ruta' => $request->ruta,
            'observacion' => $request->observacion,
            'state' => 'activo',
        ]);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_envio',
            'ip' => $request->ip(),
            'recurso' => 'envios',
            'detalle' => json_encode([
                'id' => $envio->id,
                'id_pedido' => $envio->id_pedido,
                'distribuidor' => $envio->id_distribuidor,
                'estado' => $envio->estado_envio,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('envios.index')->with('success', 'Envío registrado exitosamente.');
    }

    /**
     * Show the form for editing the specified shipping.
     */
    public function edit(Envio $envio): Response
    {
        $this->authorizeEnvioAction('edit', $envio);

        if ($envio->state === 'inactivo') {
            abort(404, 'El envío no se encuentra activo.');
        }

        $envio->load(['pedido.cliente', 'distribuidor:id,nombre,apellido,username']);

        // Fetch all active Distributors
        $distribuidorRole = Role::where('nombre', 'Distribuidor')->first();
        $distribuidores = $distribuidorRole
            ? User::where('id_rol', $distribuidorRole->id)->where('state', 'activo')->orderBy('nombre')->get(['id', 'nombre', 'apellido', 'username'])
            : collect();

        return Inertia::render('envios/Edit', [
            'envio' => $envio,
            'distribuidores' => $distribuidores,
        ]);
    }

    /**
     * Update the specified shipping in database.
     */
    public function update(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorizeEnvioAction('update', $envio);

        if ($envio->state === 'inactivo') {
            abort(404, 'El envío no se encuentra activo.');
        }

        $roleName = Auth::user()->role->nombre;

        // Custom validation based on role bounds
        if ($roleName === 'Distribuidor') {
            // Distributors can only update dates, status, route, and observations
            $request->validate([
                'fecha_salida' => 'nullable|date',
                'fecha_entrega' => 'nullable|date|after_or_equal:fecha_salida',
                'estado_envio' => 'required|string|in:pendiente,en_camino,entregado,fallido',
                'ruta' => 'nullable|string|max:255',
                'observacion' => 'nullable|string|max:1000',
            ], [
                'fecha_salida.date' => 'La fecha de salida debe ser válida.',
                'fecha_entrega.date' => 'La fecha de entrega debe ser válida.',
                'fecha_entrega.after_or_equal' => 'La fecha de entrega debe ser igual o posterior a la fecha de salida.',
                'estado_envio.required' => 'El estado de envío es obligatorio.',
                'estado_envio.in' => 'El estado seleccionado no es válido.',
            ]);

            $data = $request->only(['fecha_salida', 'fecha_entrega', 'estado_envio', 'ruta', 'observacion']);
        } else {
            // Staff can edit everything
            $request->validate([
                'id_distribuidor' => 'nullable|exists:usuarios,id',
                'direccion_entrega' => 'required|string|max:255',
                'fecha_salida' => 'nullable|date',
                'fecha_entrega' => 'nullable|date|after_or_equal:fecha_salida',
                'estado_envio' => 'required|string|in:pendiente,en_camino,entregado,fallido',
                'ruta' => 'nullable|string|max:255',
                'observacion' => 'nullable|string|max:1000',
            ], [
                'id_distribuidor.exists' => 'El distribuidor seleccionado no existe.',
                'direccion_entrega.required' => 'La dirección es obligatoria.',
                'fecha_salida.date' => 'La fecha de salida debe ser válida.',
                'fecha_entrega.date' => 'La fecha de entrega debe ser válida.',
                'fecha_entrega.after_or_equal' => 'La fecha de entrega debe ser igual o posterior a la de salida.',
                'estado_envio.required' => 'El estado es obligatorio.',
                'estado_envio.in' => 'El estado no es válido.',
            ]);

            $data = $request->only(['id_distribuidor', 'direccion_entrega', 'fecha_salida', 'fecha_entrega', 'estado_envio', 'ruta', 'observacion']);
        }

        $oldStatus = $envio->estado_envio;
        $newStatus = $request->estado_envio;
        $oldDist = $envio->id_distribuidor;
        $newDist = isset($data['id_distribuidor']) ? $data['id_distribuidor'] : $oldDist;

        try {
            DB::beginTransaction();

            $envio->update($data);

            // Sync with Pedido if state changed to 'entregado'
            if ($newStatus === 'entregado' && $oldStatus !== 'entregado') {
                $pedido = $envio->pedido;
                if ($pedido->estado_pedido !== 'entregado') {
                    $pedido->update(['estado_pedido' => 'entregado']);

                    // Log Order update
                    Bitacora::create([
                        'id_usuario' => Auth::id(),
                        'evento' => 'actualizar_estado_pedido',
                        'ip' => $request->ip(),
                        'recurso' => 'pedidos/' . $pedido->id,
                        'detalle' => json_encode([
                            'id' => $pedido->id,
                            'estado_anterior' => 'confirmado',
                            'estado_nuevo' => 'entregado',
                            'motivo' => 'Envío entregado por distribuidor',
                        ], JSON_UNESCAPED_UNICODE),
                        'user_agent' => $request->userAgent(),
                    ]);
                }
            }

            // Log Envio update
            $event = ($oldDist != $newDist) ? 'asignar_distribuidor' : 'actualizar_estado_envio';
            Bitacora::create([
                'id_usuario' => Auth::id(),
                'evento' => $event,
                'ip' => $request->ip(),
                'recurso' => 'envios/' . $envio->id,
                'detalle' => json_encode([
                    'id' => $envio->id,
                    'estado_anterior' => $oldStatus,
                    'estado_nuevo' => $newStatus,
                    'distribuidor_anterior' => $oldDist,
                    'distribuidor_nuevo' => $newDist,
                ], JSON_UNESCAPED_UNICODE),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return to_route('envios.index')->with('success', 'Envío actualizado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al actualizar el envío: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified shipping (logical delete).
     */
    public function destroy(Request $request, Envio $envio): RedirectResponse
    {
        $this->authorizeEnvioAction('destroy');

        if ($envio->state === 'inactivo') {
            abort(404, 'El envío ya se encuentra inactivo.');
        }

        $envio->update(['state' => 'inactivo']);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_envio',
            'ip' => $request->ip(),
            'recurso' => 'envios/' . $envio->id,
            'detalle' => json_encode([
                'id' => $envio->id,
                'state' => 'inactivo',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('envios.index')->with('success', 'Envío desactivado de forma lógica.');
    }
}
