<?php

namespace App\Http\Controllers;

use App\Models\Reclamo;
use App\Models\Pedido;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ReclamoController extends Controller
{
    /**
     * Authorize claim management actions based on permissions and roles.
     *
     * @param string $action
     * @param Reclamo|null $reclamo
     * @return void
     */
    private function authorizeReclamoAction(string $action, ?Reclamo $reclamo = null): void
    {
        if (!Auth::check()) {
            abort(401, 'No autenticado.');
        }

        // Verify route permission for 'reclamos'
        if (!Auth::user()->role->permissions()->where('ruta', 'reclamos')->exists()) {
            abort(403, 'No tienes permiso para acceder a reclamos.');
        }

        $roleName = Auth::user()->role->nombre;

        if ($action === 'create' || $action === 'store') {
            if ($roleName !== 'Cliente') {
                abort(403, 'Solo los clientes pueden registrar reclamos.');
            }
        }

        if ($action === 'show') {
            if ($roleName === 'Cliente' && $reclamo && $reclamo->id_cliente !== Auth::id()) {
                abort(403, 'No tienes permiso para ver este reclamo.');
            }
        }

        if ($action === 'update') {
            if (!in_array($roleName, ['Administrador', 'Vendedor'])) {
                abort(403, 'Solo el personal autorizado puede responder reclamos.');
            }
        }

        if ($action === 'destroy') {
            if ($roleName !== 'Administrador') {
                abort(403, 'Solo los administradores pueden eliminar reclamos.');
            }
        }
    }

    /**
     * Display a listing of claims.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $this->authorizeReclamoAction('index');

        $search = $request->input('search');
        $status = $request->input('status');

        $query = Reclamo::query()
            ->with(['cliente', 'pedido'])
            ->where('state', 'activo');

        if (Auth::user()->role->nombre === 'Cliente') {
            $query->where('id_cliente', Auth::id());
        }

        $query->when($search, function ($q, $search) {
            $q->where(function ($inner) use ($search) {
                $inner->where('descripcion', 'like', "%{$search}%")
                      ->orWhere('tipo_reclamo', 'like', "%{$search}%")
                      ->orWhereHas('cliente', function ($cQuery) use ($search) {
                          $cQuery->where('nombre', 'like', "%{$search}%")
                                 ->orWhere('apellido', 'like', "%{$search}%");
                      });
            });
        })
        ->when($status, function ($q, $status) {
            $q->where('estado_reclamo', $status);
        });

        $reclamos = $query->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('reclamos/Index', [
            'reclamos' => $reclamos,
            'filters' => $request->only(['search', 'status']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new claim.
     *
     * @return Response
     */
    public function create(): Response
    {
        $this->authorizeReclamoAction('create');

        $pedidos = Pedido::where('id_cliente', Auth::id())
            ->where('state', 'activo')
            ->orderBy('id', 'desc')
            ->get(['id', 'fecha_pedido', 'total', 'estado_pedido']);

        return Inertia::render('reclamos/Create', [
            'pedidos' => $pedidos,
        ]);
    }

    /**
     * Store a newly created claim in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeReclamoAction('store');

        $request->validate([
            'id_pedido' => 'nullable|integer|exists:pedidos,id',
            'tipo_reclamo' => 'required|string|max:255',
            'descripcion' => 'required|string|max:2000',
        ], [
            'tipo_reclamo.required' => 'El tipo de reclamo es obligatorio.',
            'tipo_reclamo.string' => 'El tipo de reclamo debe ser una cadena de texto.',
            'tipo_reclamo.max' => 'El tipo de reclamo no puede exceder los 255 caracteres.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.string' => 'La descripción debe ser una cadena de texto.',
            'descripcion.max' => 'La descripción no puede exceder los 2000 caracteres.',
            'id_pedido.exists' => 'El pedido seleccionado no es válido.',
            'id_pedido.integer' => 'El identificador del pedido debe ser un número entero.',
        ]);

        if ($request->filled('id_pedido')) {
            $pedido = Pedido::where('id', $request->id_pedido)
                ->where('id_cliente', Auth::id())
                ->where('state', 'activo')
                ->first();

            if (!$pedido) {
                return back()->withErrors(['id_pedido' => 'El pedido seleccionado no le pertenece o no está activo.']);
            }
        }

        $reclamo = Reclamo::create([
            'id_cliente' => Auth::id(),
            'id_pedido' => $request->id_pedido,
            'tipo_reclamo' => $request->tipo_reclamo,
            'descripcion' => $request->descripcion,
            'fecha_reclamo' => now()->toDateString(),
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_reclamo',
            'ip' => $request->ip(),
            'recurso' => 'reclamos/' . $reclamo->id,
            'detalle' => json_encode([
                'id' => $reclamo->id,
                'tipo_reclamo' => $reclamo->tipo_reclamo,
                'id_pedido' => $reclamo->id_pedido,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('reclamos.index')->with('success', 'Reclamo registrado exitosamente.');
    }

    /**
     * Display the specified claim.
     *
     * @param Reclamo $reclamo
     * @return Response
     */
    public function show(Reclamo $reclamo): Response
    {
        $this->authorizeReclamoAction('show', $reclamo);

        $reclamo->load(['cliente', 'pedido.detalles.producto']);

        return Inertia::render('reclamos/Show', [
            'reclamo' => $reclamo,
        ]);
    }

    /**
     * Update the specified claim (reply and status change by staff).
     *
     * @param Request $request
     * @param Reclamo $reclamo
     * @return RedirectResponse
     */
    public function update(Request $request, Reclamo $reclamo): RedirectResponse
    {
        $this->authorizeReclamoAction('update', $reclamo);

        $request->validate([
            'estado_reclamo' => 'required|string|in:pendiente,en_proceso,atendido,rechazado',
            'respuesta' => 'required|string|max:2000',
        ], [
            'estado_reclamo.required' => 'El estado del reclamo es obligatorio.',
            'estado_reclamo.in' => 'El estado del reclamo no es válido.',
            'respuesta.required' => 'La respuesta es obligatoria.',
            'respuesta.string' => 'La respuesta debe ser una cadena de texto.',
            'respuesta.max' => 'La respuesta no puede exceder los 2000 caracteres.',
        ]);

        $reclamo->update([
            'estado_reclamo' => $request->estado_reclamo,
            'respuesta' => $request->respuesta,
            'fecha_respuesta' => now()->toDateString(),
        ]);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'atender_reclamo',
            'ip' => $request->ip(),
            'recurso' => 'reclamos/' . $reclamo->id,
            'detalle' => json_encode([
                'id' => $reclamo->id,
                'estado_reclamo' => $reclamo->estado_reclamo,
                'respuesta' => $reclamo->respuesta,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('reclamos.show', $reclamo->id)->with('success', 'Respuesta de reclamo guardada exitosamente.');
    }

    /**
     * Remove the specified claim (logical delete).
     *
     * @param Request $request
     * @param Reclamo $reclamo
     * @return RedirectResponse
     */
    public function destroy(Request $request, Reclamo $reclamo): RedirectResponse
    {
        $this->authorizeReclamoAction('destroy', $reclamo);

        $reclamo->update(['state' => 'inactivo']);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_reclamo',
            'ip' => $request->ip(),
            'recurso' => 'reclamos/' . $reclamo->id,
            'detalle' => json_encode([
                'id' => $reclamo->id,
                'state' => 'inactivo',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('reclamos.index')->with('success', 'Reclamo eliminado lógicamente.');
    }
}
