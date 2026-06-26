<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PagoController extends Controller
{
    /**
     * Authorize payment management actions.
     *
     * @param string $action
     * @param Pago|null $pago
     * @return void
     */
    private function authorizePagoAction(string $action, ?Pago $pago = null): void
    {
        if (!Auth::check()) {
            abort(401, 'No autenticado.');
        }

        $user = Auth::user();
        $mapping = [
            'index'           => 'pagos.ver',
            'show'            => 'pagos.ver',
            'create'          => 'pagos.crear',
            'store'           => 'pagos.crear',
            'update'          => 'pagos.editar',
            'simularCallback' => 'pagos.editar',
            'destroy'         => 'pagos.eliminar',
        ];

        $perm = $mapping[$action] ?? 'pagos.ver';

        if (!$user->role->hasPermission($perm)) {
            abort(403, 'No tienes permiso para realizar esta acción sobre pagos.');
        }

        // Ownership enforcement for clients
        $roleName = $user->role->nombre;
        if ($roleName === 'Cliente' && $pago) {
            if ($pago->pedido && $pago->pedido->id_cliente !== $user->id) {
                abort(403, 'No tienes acceso a este pago.');
            }
        }
    }

    /**
     * Display a listing of payments.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $this->authorizePagoAction('index');

        $search = $request->input('search');
        $status = $request->input('status');
        $type = $request->input('type');

        $query = Pago::query()
            ->with(['pedido.cliente'])
            ->orderBy('id', 'desc');

        if (Auth::user()->role->nombre === 'Cliente') {
            $query->whereHas('pedido', function ($q) {
                $q->where('id_cliente', Auth::id());
            });
        }

        $query->when($search, function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('id_pedido', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%")
                      ->orWhereHas('pedido.cliente', function ($cQuery) use ($search) {
                          $cQuery->where('nombre', 'like', "%{$search}%")
                                 ->orWhere('apellido', 'like', "%{$search}%");
                      });
            });
        })
        ->when($status, function ($q, $status) {
            $q->where('estado_pago', $status);
        })
        ->when($type, function ($q, $type) {
            $q->where('tipo_pago', $type);
        });

        $pagos = $query->paginate(10)->withQueryString();

        return Inertia::render('pagos/Index', [
            'pagos' => $pagos,
            'filters' => $request->only(['search', 'status', 'type']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new payment.
     *
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        $this->authorizePagoAction('create');

        // Retrieve active orders that still have a pending balance
        $pedidos = Pedido::with(['pagos' => function ($q) {
                $q->where('estado_pago', 'completado');
            }])
            ->where('state', 'activo')
            ->when(Auth::user()->role->nombre === 'Cliente', function ($query) {
                $query->where('id_cliente', Auth::id());
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($pedido) {
                $totalPagado = $pedido->pagos->sum('monto');
                $saldoPendiente = max(0, $pedido->total - $totalPagado);
                return [
                    'id' => $pedido->id,
                    'fecha_pedido' => $pedido->fecha_pedido,
                    'total' => $pedido->total,
                    'estado_pedido' => $pedido->estado_pedido,
                    'total_pagado' => $totalPagado,
                    'saldo_pendiente' => $saldoPendiente,
                ];
            })
            ->filter(function ($pedido) {
                return $pedido['saldo_pendiente'] > 0;
            })
            ->values();

        return Inertia::render('pagos/Create', [
            'pedidos' => $pedidos,
        ]);
    }

    /**
     * Store a newly created payment in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizePagoAction('store');

        $request->validate([
            'id_pedido' => 'required|integer|exists:pedidos,id',
            'monto' => 'required|numeric|min:0.01',
            'tipo_pago' => 'required|string|in:efectivo,tarjeta,qr',
            'total_cuotas' => 'required|integer|min:1',
            'numero_cuota' => 'required|integer|min:1',
            'observacion' => 'nullable|string|max:1000',
        ], [
            'id_pedido.required' => 'El pedido es obligatorio.',
            'id_pedido.exists' => 'El pedido seleccionado no es válido.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un valor numérico.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'tipo_pago.required' => 'El tipo de pago es obligatorio.',
            'tipo_pago.in' => 'El tipo de pago seleccionado no es válido.',
            'total_cuotas.required' => 'El total de cuotas es obligatorio.',
            'total_cuotas.integer' => 'El total de cuotas debe ser un número entero.',
            'total_cuotas.min' => 'El total de cuotas debe ser al menos 1.',
            'numero_cuota.required' => 'El número de cuota es obligatorio.',
            'numero_cuota.integer' => 'El número de cuota debe ser un número entero.',
            'numero_cuota.min' => 'El número de cuota debe ser al menos 1.',
            'observacion.max' => 'La observación no puede exceder los 1000 caracteres.',
        ]);

        $pedido = Pedido::where('id', $request->id_pedido)
            ->where('state', 'activo')
            ->firstOrFail();

        // Verify order ownership for Clients
        if (Auth::user()->role->nombre === 'Cliente' && $pedido->id_cliente !== Auth::id()) {
            return back()->withErrors(['id_pedido' => 'El pedido seleccionado no le pertenece.']);
        }

        // Calculate outstanding balance before this payment
        $totalPagado = Pago::where('id_pedido', $pedido->id)
            ->where('estado_pago', 'completado')
            ->sum('monto');
        $saldoPendiente = max(0, $pedido->total - $totalPagado);

        if (round($request->monto, 2) > round($saldoPendiente, 2)) {
            return back()->withErrors(['monto' => 'El monto ingresado excede el saldo pendiente de $' . number_format($saldoPendiente, 2)]);
        }

        // Initial payment status
        // - 'tarjeta': instantly 'completado' (visual-only checkout)
        // - 'efectivo', 'qr': stays 'pendiente' until scan/callback or seller confirms
        $estadoPago = 'pendiente';
        if ($request->tipo_pago === 'tarjeta') {
            $estadoPago = 'completado';
        }

        // Calculate new pending balance after this transaction (if transaction is complete)
        $newSaldoPendiente = $saldoPendiente;
        if ($estadoPago === 'completado') {
            $newSaldoPendiente = max(0, $saldoPendiente - $request->monto);
        }

        $pago = Pago::create([
            'id_pedido' => $pedido->id,
            'monto' => $request->monto,
            'fecha_pago' => now()->toDateString(),
            'tipo_pago' => $request->tipo_pago,
            'estado_pago' => $estadoPago,
            'total_cuotas' => $request->total_cuotas,
            'numero_cuota' => $request->numero_cuota,
            'saldo_pendiente' => $newSaldoPendiente,
            'observacion' => $request->observacion,
        ]);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_pago',
            'ip' => $request->ip(),
            'recurso' => 'pagos/' . $pago->id,
            'detalle' => json_encode([
                'id' => $pago->id,
                'id_pedido' => $pago->id_pedido,
                'monto' => $pago->monto,
                'tipo_pago' => $pago->tipo_pago,
                'estado_pago' => $pago->estado_pago,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        if ($pago->tipo_pago === 'qr') {
            // Redirect to show page so the customer can scan the simulated PagoFacil QR
            return redirect()->route('pagos.show', $pago->id)->with('success', 'Registro de pago iniciado. Por favor escanea el código QR de PagoFacil.');
        }

        return redirect()->route('pagos.index')->with('success', 'Pago registrado exitosamente.');
    }

    /**
     * Display the specified payment.
     *
     * @param Pago $pago
     * @return Response
     */
    public function show(Pago $pago): Response
    {
        $pago->load(['pedido.cliente']);
        $this->authorizePagoAction('show', $pago);

        return Inertia::render('pagos/Show', [
            'pago' => $pago,
        ]);
    }

    /**
     * Update the specified payment status (Manual confirmation by staff).
     *
     * @param Request $request
     * @param Pago $pago
     * @return RedirectResponse
     */
    public function update(Request $request, Pago $pago): RedirectResponse
    {
        $this->authorizePagoAction('update', $pago);

        $request->validate([
            'estado_pago' => 'required|string|in:pendiente,completado,fallido',
            'observacion' => 'nullable|string|max:1000',
        ], [
            'estado_pago.required' => 'El estado de pago es obligatorio.',
            'estado_pago.in' => 'El estado de pago seleccionado no es válido.',
            'observacion.max' => 'La observación no puede exceder los 1000 caracteres.',
        ]);

        $transitionToCompleted = ($request->estado_pago === 'completado' && $pago->estado_pago !== 'completado');

        // Calculate outstanding balance
        $totalPagado = Pago::where('id_pedido', $pago->id_pedido)
            ->where('estado_pago', 'completado')
            ->where('id', '!=', $pago->id)
            ->sum('monto');
        
        $saldoPendiente = max(0, $pago->pedido->total - $totalPagado);

        if ($transitionToCompleted && round($pago->monto, 2) > round($saldoPendiente, 2)) {
            return back()->with('error', 'No se puede completar este pago porque excede el saldo pendiente actual del pedido ($' . number_format($saldoPendiente, 2) . ').');
        }

        $newSaldoPendiente = $saldoPendiente;
        if ($request->estado_pago === 'completado') {
            $newSaldoPendiente = max(0, $saldoPendiente - $pago->monto);
        }

        $pago->update([
            'estado_pago' => $request->estado_pago,
            'saldo_pendiente' => $newSaldoPendiente,
            'observacion' => $request->observacion ?? $pago->observacion,
        ]);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'confirmar_pago',
            'ip' => $request->ip(),
            'recurso' => 'pagos/' . $pago->id,
            'detalle' => json_encode([
                'id' => $pago->id,
                'estado_pago' => $pago->estado_pago,
                'saldo_pendiente' => $pago->saldo_pendiente,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('pagos.show', $pago->id)->with('success', 'Estado del pago actualizado exitosamente.');
    }

    /**
     * Simulate PagoFacil checkout callback for QR payments.
     *
     * @param Pago $pago
     * @return RedirectResponse
     */
    public function simularCallback(Pago $pago): RedirectResponse
    {
        $this->authorizePagoAction('simularCallback', $pago);

        if ($pago->tipo_pago !== 'qr') {
            return back()->with('error', 'Solo los pagos por código QR soportan callbacks de PagoFacil.');
        }

        if ($pago->estado_pago === 'completado') {
            return back()->with('error', 'Este pago ya ha sido completado anteriormente.');
        }

        // Calculate outstanding balance
        $totalPagado = Pago::where('id_pedido', $pago->id_pedido)
            ->where('estado_pago', 'completado')
            ->where('id', '!=', $pago->id)
            ->sum('monto');
        
        $saldoPendiente = max(0, $pago->pedido->total - $totalPagado);

        if (round($pago->monto, 2) > round($saldoPendiente, 2)) {
            return back()->with('error', 'No se puede confirmar el pago porque excede el saldo pendiente ($' . number_format($saldoPendiente, 2) . ').');
        }

        $newSaldoPendiente = max(0, $saldoPendiente - $pago->monto);

        $pago->update([
            'estado_pago' => 'completado',
            'saldo_pendiente' => $newSaldoPendiente,
        ]);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'callback_pagofacil',
            'ip' => request()->ip(),
            'recurso' => 'pagos/' . $pago->id,
            'detalle' => json_encode([
                'id' => $pago->id,
                'gateway' => 'pagofacil',
                'estado' => 'completado',
                'saldo_pendiente' => $pago->saldo_pendiente,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => request()->userAgent(),
        ]);

        return redirect()->route('pagos.show', $pago->id)->with('success', '¡Simulación exitosa! PagoFacil ha reportado el pago como completado.');
    }

    /**
     * Remove the specified payment.
     *
     * @param Request $request
     * @param Pago $pago
     * @return RedirectResponse
     */
    public function destroy(Request $request, Pago $pago): RedirectResponse
    {
        $this->authorizePagoAction('destroy', $pago);

        $id = $pago->id;
        $pago->delete();

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_pago',
            'ip' => $request->ip(),
            'recurso' => 'pagos/' . $id,
            'detalle' => json_encode([
                'id' => $id,
                'eliminado' => true,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('pagos.index')->with('success', 'Registro de pago eliminado exitosamente.');
    }
}
