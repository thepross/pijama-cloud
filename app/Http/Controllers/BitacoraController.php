<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BitacoraController extends Controller
{
    /**
     * Authorize access to bitacora.
     */
    private function authorizeBitacoraAccess(): void
    {
        if (!Auth::check() || !Auth::user()->role->permissions()->where('ruta', 'bitacoras')->exists()) {
            abort(403, 'No tienes permiso para ver la bitácora del sistema.');
        }
    }

    /**
     * Display a listing of log entries.
     */
    public function index(Request $request): Response
    {
        $this->authorizeBitacoraAccess();

        // 1. Audit access to logs
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'ver_bitacora',
            'ip' => $request->ip(),
            'recurso' => 'bitacoras',
            'user_agent' => $request->userAgent(),
        ]);

        $search = $request->input('search');
        $userId = $request->input('user_id');
        $evento = $request->input('evento');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        $logs = Bitacora::query()
            ->with('usuario.role')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('evento', 'like', "%{$search}%")
                      ->orWhere('recurso', 'like', "%{$search}%")
                      ->orWhere('detalle', 'like', "%{$search}%")
                      ->orWhere('ip', 'like', "%{$search}%")
                      ->orWhereHas('usuario', function ($uQuery) use ($search) {
                          $uQuery->where('nombre', 'like', "%{$search}%")
                                 ->orWhere('apellido', 'like', "%{$search}%")
                                 ->orWhere('username', 'like', "%{$search}%");
                      });
                });
            })
            ->when($userId, function ($query, $userId) {
                $query->where('id_usuario', $userId);
            })
            ->when($evento, function ($query, $evento) {
                $query->where('evento', $evento);
            })
            ->when($fechaInicio, function ($query, $fechaInicio) {
                $query->whereDate('created_at', '>=', $fechaInicio);
            })
            ->when($fechaFin, function ($query, $fechaFin) {
                $query->whereDate('created_at', '<=', $fechaFin);
            })
            ->orderBy('id', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Fetch auxiliary data for filter selectors
        $usuarios = User::where('state', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido', 'username']);

        $eventosUnicos = Bitacora::distinct()
            ->orderBy('evento')
            ->pluck('evento');

        // Log Stats summary for KPIs
        $totalLogs = Bitacora::count();
        
        $mostActiveUser = Bitacora::selectRaw('id_usuario, COUNT(*) as count')
            ->whereNotNull('id_usuario')
            ->groupBy('id_usuario')
            ->orderBy('count', 'desc')
            ->with('usuario')
            ->first();

        $frequentEvent = Bitacora::selectRaw('evento, COUNT(*) as count')
            ->groupBy('evento')
            ->orderBy('count', 'desc')
            ->first();

        $kpis = [
            'total_registros' => $totalLogs,
            'usuario_activo' => $mostActiveUser ? [
                'name' => $mostActiveUser->usuario ? ($mostActiveUser->usuario->nombre . ' ' . $mostActiveUser->usuario->apellido) : 'Usuario Eliminado',
                'username' => $mostActiveUser->usuario ? $mostActiveUser->usuario->username : '',
                'count' => $mostActiveUser->count
            ] : null,
            'evento_frecuente' => $frequentEvent ? [
                'evento' => $frequentEvent->evento,
                'count' => $frequentEvent->count
            ] : null,
            'acciones_modificacion' => Bitacora::where(function($q) {
                $q->where('evento', 'like', '%crear%')
                  ->orWhere('evento', 'like', '%editar%')
                  ->orWhere('evento', 'like', '%eliminar%')
                  ->orWhere('evento', 'like', '%accion_%');
            })->count()
        ];

        return Inertia::render('bitacoras/Index', [
            'logs' => $logs,
            'usuarios' => $usuarios,
            'eventos_unicos' => $eventosUnicos,
            'kpis' => $kpis,
            'filters' => $request->only(['search', 'user_id', 'evento', 'fecha_inicio', 'fecha_fin']),
        ]);
    }
}
