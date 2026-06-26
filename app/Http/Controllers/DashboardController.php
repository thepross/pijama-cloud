<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reclamo;
use App\Models\User;
use App\Models\Envio;
use App\Models\Visita;
use App\Models\DetallePedido;
use App\Models\Puntuacion;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the main role-specific dashboard.
     *
     * @return Response
     */
    public function index(): Response
    {
        if (!Auth::check()) {
            abort(401, 'No autenticado.');
        }

        if (!Auth::user()->role->hasPermission('dashboard.ver')) {
            abort(403, 'No tienes permiso para acceder al panel de control.');
        }

        $user = Auth::user();
        $roleName = $user->role->nombre;

        $data = [
            'user_name' => $user->nombre . ' ' . $user->apellido,
            'role' => strtolower($roleName),
        ];

        if (in_array($roleName, ['Administrador', 'Vendedor'])) {
            $data['role_type'] = 'staff';
            
            // KPIs
            $data['kpis'] = [
                'ventas_totales' => (float) Pedido::where('state', 'activo')
                    ->where('estado_pedido', '!=', 'cancelado')
                    ->sum('total'),
                'total_clientes' => User::where('state', 'activo')
                    ->whereHas('role', function($q) { $q->where('nombre', 'Cliente'); })
                    ->count(),
                'bajo_stock' => Producto::where('state', 'activo')
                    ->where('stock', '<=', 5)
                    ->count(),
                'reclamos_activos' => Reclamo::where('state', 'activo')
                    ->where('estado_reclamo', 'pendiente')
                    ->count(),
            ];

            // 7 Days Sales Trend
            $data['ventas_diarias'] = Pedido::selectRaw('fecha_pedido as date, SUM(total) as revenue')
                ->where('state', 'activo')
                ->where('estado_pedido', '!=', 'cancelado')
                ->where('fecha_pedido', '>=', now()->subDays(6)->toDateString())
                ->groupBy('fecha_pedido')
                ->orderBy('fecha_pedido', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'revenue' => (float) $item->revenue,
                    ];
                });

            // Top 5 Visited Pages
            $data['visitas'] = Visita::orderBy('contador', 'desc')
                ->limit(5)
                ->get(['ruta', 'contador']);

        } elseif ($roleName === 'Cliente') {
            $data['role_type'] = 'cliente';

            // KPIs
            $data['kpis'] = [
                'total_gastado' => (float) Pedido::where('id_cliente', $user->id)
                    ->where('state', 'activo')
                    ->where('estado_pedido', '!=', 'cancelado')
                    ->sum('total'),
                'pedidos_activos' => Pedido::where('id_cliente', $user->id)
                    ->where('state', 'activo')
                    ->whereIn('estado_pedido', ['pendiente', 'confirmado'])
                    ->count(),
                'calificaciones_hechas' => Puntuacion::where('id_cliente', $user->id)
                    ->where('state', 'activo')
                    ->count(),
                'reclamos_realizados' => Reclamo::where('id_cliente', $user->id)
                    ->where('state', 'activo')
                    ->count(),
            ];

            // Last 3 Orders
            $data['recientes'] = Pedido::where('id_cliente', $user->id)
                ->where('state', 'activo')
                ->orderBy('id', 'desc')
                ->limit(3)
                ->get(['id', 'fecha_pedido', 'total', 'estado_pedido']);

            // Bought Categories
            $data['categorias'] = DetallePedido::join('pedidos', 'detalle_pedido.id_pedido', '=', 'pedidos.id')
                ->join('productos', 'detalle_pedido.id_producto', '=', 'productos.id')
                ->where('pedidos.id_cliente', $user->id)
                ->where('pedidos.state', 'activo')
                ->where('pedidos.estado_pedido', '!=', 'cancelado')
                ->groupBy('productos.categoria')
                ->selectRaw('productos.categoria, SUM(detalle_pedido.cantidad) as total_qty')
                ->orderByRaw('SUM(detalle_pedido.cantidad) DESC')
                ->get()
                ->map(function ($item) {
                    return [
                        'categoria' => $item->categoria,
                        'cantidad' => (int) $item->total_qty,
                    ];
                });

        } elseif ($roleName === 'Distribuidor') {
            $data['role_type'] = 'distribuidor';

            // KPIs
            $data['kpis'] = [
                'envios_pendientes' => Envio::where('id_distribuidor', $user->id)
                    ->whereIn('estado_envio', ['pendiente', 'en_transito'])
                    ->where('state', 'activo')
                    ->count(),
                'envios_entregados' => Envio::where('id_distribuidor', $user->id)
                    ->where('estado_envio', 'entregado')
                    ->where('state', 'activo')
                    ->count(),
            ];

            // Last 3 Shipments
            $data['recientes'] = Envio::with('pedido.cliente')
                ->where('id_distribuidor', $user->id)
                ->where('state', 'activo')
                ->orderBy('id', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($envio) {
                    return [
                        'id' => $envio->id,
                        'id_pedido' => $envio->id_pedido,
                        'cliente' => $envio->pedido->cliente ? ($envio->pedido->cliente->nombre . ' ' . $envio->pedido->cliente->apellido) : 'General',
                        'estado_envio' => $envio->estado_envio,
                        'fecha_salida' => $envio->fecha_salida,
                    ];
                });

            // Past 7 Days Delivery Count Trend
            $data['envios_diarios'] = Envio::selectRaw('fecha_entrega as date, COUNT(id) as count')
                ->where('id_distribuidor', $user->id)
                ->where('estado_envio', 'entregado')
                ->where('state', 'activo')
                ->whereNotNull('fecha_entrega')
                ->where('fecha_entrega', '>=', now()->subDays(6)->toDateString())
                ->groupBy('fecha_entrega')
                ->orderBy('fecha_entrega', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'count' => (int) $item->count,
                    ];
                });
        } else {
            $data['role_type'] = 'default';
        }

        return Inertia::render('Dashboard', $data);
    }
}
