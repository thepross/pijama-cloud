<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reclamo;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EstadisticaController extends Controller
{
    /**
     * Display a statistics dashboard and summary reports.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        if (!Auth::user()->role->hasPermission('estadisticas.ver')) {
            abort(403, 'No tienes permiso para acceder a estadísticas.');
        }

        // 2. Validate input filters in Spanish
        $request->validate([
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
        ], [
            'fecha_inicio.date' => 'La fecha de inicio no es válida.',
            'fecha_fin.date' => 'La fecha de fin no es válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ]);

        $fechaInicio = $request->input('fecha_inicio', now()->subDays(30)->toDateString());
        $fechaFin = $request->input('fecha_fin', now()->toDateString());

        // 3. Compute KPI Metrics
        $ingresosTotales = (float) Pedido::where('state', 'activo')
            ->where('estado_pedido', '!=', 'cancelado')
            ->whereBetween('fecha_pedido', [$fechaInicio, $fechaFin])
            ->sum('total');

        $totalPedidos = Pedido::where('state', 'activo')
            ->whereBetween('fecha_pedido', [$fechaInicio, $fechaFin])
            ->count();

        $bajoStockCount = Producto::where('state', 'activo')
            ->where('stock', '<=', 5)
            ->count();

        $reclamosPendientes = Reclamo::where('state', 'activo')
            ->where('estado_reclamo', 'pendiente')
            ->count();

        // 4. Sales Daily Trend
        $ventasDiarias = Pedido::selectRaw('fecha_pedido as date, SUM(total) as revenue, COUNT(id) as orders_count')
            ->where('state', 'activo')
            ->where('estado_pedido', '!=', 'cancelado')
            ->whereBetween('fecha_pedido', [$fechaInicio, $fechaFin])
            ->groupBy('fecha_pedido')
            ->orderBy('fecha_pedido', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'revenue' => (float) $item->revenue,
                    'orders_count' => (int) $item->orders_count,
                ];
            });

        // 5. Best Selling Products (Top 5)
        $mejoresProductos = \App\Models\DetallePedido::selectRaw('detalle_pedido.id_producto, SUM(detalle_pedido.cantidad) as total_vendido, SUM(detalle_pedido.subtotal) as total_recaudado')
            ->join('pedidos', 'detalle_pedido.id_pedido', '=', 'pedidos.id')
            ->join('productos', 'detalle_pedido.id_producto', '=', 'productos.id')
            ->where('pedidos.state', 'activo')
            ->where('pedidos.estado_pedido', '!=', 'cancelado')
            ->whereBetween('pedidos.fecha_pedido', [$fechaInicio, $fechaFin])
            ->groupBy('detalle_pedido.id_producto')
            ->orderByRaw('SUM(detalle_pedido.cantidad) DESC')
            ->limit(5)
            ->with('producto:id,nombre,categoria,foto,precio_venta')
            ->get()
            ->map(function ($item) {
                return [
                    'id_producto' => $item->id_producto,
                    'nombre' => $item->producto->nombre ?? 'Producto Desconocido',
                    'categoria' => $item->producto->categoria ?? 'General',
                    'foto' => $item->producto->foto ?? null,
                    'precio' => $item->producto ? (float) $item->producto->precio_venta : 0,
                    'cantidad_vendida' => (int) $item->total_vendido,
                    'ingresos' => (float) $item->total_recaudado,
                ];
            });

        // 6. Sales by Category
        $ventasCategorias = \App\Models\DetallePedido::selectRaw('productos.categoria, SUM(detalle_pedido.subtotal) as total_ventas')
            ->join('pedidos', 'detalle_pedido.id_pedido', '=', 'pedidos.id')
            ->join('productos', 'detalle_pedido.id_producto', '=', 'productos.id')
            ->where('pedidos.state', 'activo')
            ->where('pedidos.estado_pedido', '!=', 'cancelado')
            ->whereBetween('pedidos.fecha_pedido', [$fechaInicio, $fechaFin])
            ->groupBy('productos.categoria')
            ->get()
            ->map(function ($item) {
                return [
                    'categoria' => $item->categoria,
                    'ventas' => (float) $item->total_ventas,
                ];
            });

        // 7. Claims Status breakdown
        $reclamosEstados = Reclamo::selectRaw('estado_reclamo, COUNT(id) as count')
            ->where('state', 'activo')
            ->groupBy('estado_reclamo')
            ->get()
            ->pluck('count', 'estado_reclamo')
            ->toArray();

        $reclamosRatio = [
            'pendiente' => $reclamosEstados['pendiente'] ?? 0,
            'en_proceso' => $reclamosEstados['en_proceso'] ?? 0,
            'atendido' => $reclamosEstados['atendido'] ?? 0,
            'rechazado' => $reclamosEstados['rechazado'] ?? 0,
        ];

        // 8. Register Audit Trail Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'ver_reportes',
            'ip' => $request->ip(),
            'recurso' => 'estadisticas',
            'detalle' => json_encode([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return Inertia::render('estadisticas/Index', [
            'kpis' => [
                'ingresos_totales' => $ingresosTotales,
                'total_pedidos' => $totalPedidos,
                'bajo_stock_count' => $bajoStockCount,
                'reclamos_pendientes' => $reclamosPendientes,
            ],
            'ventas_diarias' => $ventasDiarias,
            'mejores_productos' => $mejoresProductos,
            'ventas_categorias' => $ventasCategorias,
            'reclamos_ratio' => $reclamosRatio,
            'filters' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ],
        ]);
    }
}
