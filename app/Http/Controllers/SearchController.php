<?php

namespace App\Http\Controllers;

use App\Models\Envio;
use App\Models\Oferta;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Reclamo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SearchController extends Controller
{
    /**
     * Search across system resources based on user permissions.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('query');
        if (empty($query) || strlen($query) < 2) {
            return response()->json([]);
        }

        $user = Auth::user();
        if (! $user) {
            return response()->json([]);
        }

        $results = $this->getSearchResults($user, $query, 5);

        return response()->json($results);
    }

    /**
     * Show search results page.
     */
    public function showResults(Request $request)
    {
        $query = $request->input('query');
        $results = [];
        $user = Auth::user();

        if ($user && ! empty($query) && strlen($query) >= 2) {
            $results = $this->getSearchResults($user, $query, 50);
        }

        return Inertia::render('buscar/Index', [
            'query' => $query ?? '',
            'results' => $results,
        ]);
    }

    /**
     * Helper to perform cross-resource search based on user permissions and scoping rules.
     */
    private function getSearchResults(User $user, string $query, int $limit): array
    {
        $results = [];

        // 1. Search Users (if authorized)
        if ($user->role && $user->role->hasPermission('usuarios.ver')) {
            $users = User::query()
                ->where(function ($q) use ($query) {
                    $q->where('username', 'like', "%{$query}%")
                        ->orWhere('nombre', 'like', "%{$query}%")
                        ->orWhere('apellido', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('ci', 'like', "%{$query}%")
                        ->orWhere('telefono', 'like', "%{$query}%")
                        ->orWhere('direccion', 'like', "%{$query}%");
                })
                ->where('state', 'activo')
                ->limit($limit)
                ->get();

            foreach ($users as $u) {
                $results[] = [
                    'id' => $u->id,
                    'title' => "{$u->nombre} {$u->apellido} (@{$u->username})",
                    'description' => "Usuario - CI: {$u->ci} | Email: {$u->email} | Teléfono: {$u->telefono}",
                    'link' => '/usuarios?search='.urlencode($u->username),
                    'type' => 'Usuario',
                    'icon' => 'Users',
                ];
            }
        }

        // 2. Search Roles (if authorized)
        if ($user->role && $user->role->hasPermission('roles.ver')) {
            $roles = Role::query()
                ->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%{$query}%")
                        ->orWhere('descripcion', 'like', "%{$query}%");
                })
                ->where('state', 'activo')
                ->limit($limit)
                ->get();

            foreach ($roles as $r) {
                $results[] = [
                    'id' => $r->id,
                    'title' => "Rol: {$r->nombre}",
                    'description' => "Rol de acceso - {$r->descripcion}",
                    'link' => '/roles?search='.urlencode($r->nombre),
                    'type' => 'Rol',
                    'icon' => 'Shield',
                ];
            }
        }

        // 3. Search Products (if authorized)
        if ($user->role && $user->role->hasPermission('productos.ver')) {
            $productos = Producto::query()
                ->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%{$query}%")
                        ->orWhere('descripcion', 'like', "%{$query}%")
                        ->orWhere('codigo_qr', 'like', "%{$query}%")
                        ->orWhere('color', 'like', "%{$query}%")
                        ->orWhere('talla', 'like', "%{$query}%")
                        ->orWhere('marca', 'like', "%{$query}%")
                        ->orWhere('material', 'like', "%{$query}%")
                        ->orWhere('categoria', 'like', "%{$query}%");
                })
                ->where('state', 'activo')
                ->limit($limit)
                ->get();

            foreach ($productos as $p) {
                $results[] = [
                    'id' => $p->id,
                    'title' => $p->nombre,
                    'description' => "Producto - Categoría: {$p->categoria} | Talla: {$p->talla} | Stock: {$p->stock} | Precio: Bs. {$p->precio_venta}",
                    'link' => '/productos?search='.urlencode($p->nombre),
                    'type' => 'Producto',
                    'icon' => 'Archive',
                ];
            }
        }

        // 4. Search Pedidos (if authorized)
        if ($user->role && $user->role->hasPermission('pedidos.ver')) {
            $queryBuilder = Pedido::query()->with('cliente');
            if ($user->role->nombre === 'Cliente') {
                $queryBuilder->where('id_cliente', $user->id);
            }
            $pedidos = $queryBuilder->where(function ($q) use ($query, $user) {
                $q->where('estado_pedido', 'like', "%{$query}%")
                    ->orWhere('observacion', 'like', "%{$query}%");
                if ($user->role->nombre !== 'Cliente') {
                    $q->orWhereHas('cliente', function ($sq) use ($query) {
                        $sq->where('nombre', 'like', "%{$query}%")
                            ->orWhere('apellido', 'like', "%{$query}%")
                            ->orWhere('username', 'like', "%{$query}%");
                    });
                }
            })
                ->where('state', 'activo')
                ->limit($limit)
                ->get();

            foreach ($pedidos as $ped) {
                $results[] = [
                    'id' => $ped->id,
                    'title' => "Pedido #{$ped->id} - Total: Bs. {$ped->total}",
                    'description' => 'Pedido - Estado: '.ucfirst($ped->estado_pedido)." | Cliente: {$ped->cliente->nombre} {$ped->cliente->apellido} | Obs: ".($ped->observacion ?: 'Ninguna'),
                    'link' => "/pedidos/{$ped->id}",
                    'type' => 'Pedido',
                    'icon' => 'ShoppingBag',
                ];
            }
        }

        // 5. Search Envíos (if authorized)
        if ($user->role && $user->role->hasPermission('envios.ver')) {
            $queryBuilder = Envio::query()->with(['distribuidor', 'pedido.cliente']);
            if ($user->role->nombre === 'Distribuidor') {
                $queryBuilder->where('id_distribuidor', $user->id);
            } elseif ($user->role->nombre === 'Cliente') {
                $queryBuilder->whereHas('pedido', function ($sq) use ($user) {
                    $sq->where('id_cliente', $user->id);
                });
            }
            $envios = $queryBuilder->where(function ($q) use ($query, $user) {
                $q->where('direccion_entrega', 'like', "%{$query}%")
                    ->orWhere('estado_envio', 'like', "%{$query}%")
                    ->orWhere('observacion', 'like', "%{$query}%")
                    ->orWhere('ruta', 'like', "%{$query}%");
                if ($user->role->nombre !== 'Cliente') {
                    $q->orWhereHas('distribuidor', function ($sq) use ($query) {
                        $sq->where('nombre', 'like', "%{$query}%")
                            ->orWhere('apellido', 'like', "%{$query}%");
                    });
                }
            })
                ->where('state', 'activo')
                ->limit($limit)
                ->get();

            foreach ($envios as $env) {
                $distName = $env->distribuidor ? "{$env->distribuidor->nombre} {$env->distribuidor->apellido}" : 'Sin asignar';
                $results[] = [
                    'id' => $env->id,
                    'title' => "Envío #{$env->id} - Pedido #{$env->id_pedido}",
                    'description' => 'Envío - Estado: '.ucfirst($env->estado_envio).' | Ruta: '.($env->ruta ?: 'N/A')." | Destino: {$env->direccion_entrega} | Repartidor: {$distName}",
                    'link' => '/envios',
                    'type' => 'Envío',
                    'icon' => 'Truck',
                ];
            }
        }

        // 6. Search Pagos (if authorized)
        if ($user->role && $user->role->hasPermission('pagos.ver')) {
            $queryBuilder = Pago::query()->with('pedido.cliente');
            if ($user->role->nombre === 'Cliente') {
                $queryBuilder->whereHas('pedido', function ($sq) use ($user) {
                    $sq->where('id_cliente', $user->id);
                });
            }
            $pagos = $queryBuilder->where(function ($q) use ($query) {
                $q->where('tipo_pago', 'like', "%{$query}%")
                    ->orWhere('estado_pago', 'like', "%{$query}%")
                    ->orWhere('observacion', 'like', "%{$query}%");
            })
                ->limit($limit)
                ->get();

            foreach ($pagos as $pag) {
                $results[] = [
                    'id' => $pag->id,
                    'title' => "Pago #{$pag->id} - Monto: Bs. {$pag->monto}",
                    'description' => "Pago - Método: {$pag->tipo_pago} | Estado: ".ucfirst($pag->estado_pago)." | Cliente: {$pag->pedido->cliente->nombre} {$pag->pedido->cliente->apellido}",
                    'link' => "/pagos/{$pag->id}",
                    'type' => 'Pago',
                    'icon' => 'CreditCard',
                ];
            }
        }

        // 7. Search Reclamos (if authorized)
        if ($user->role && $user->role->hasPermission('reclamos.ver')) {
            $queryBuilder = Reclamo::query()->with('cliente');
            if ($user->role->nombre === 'Cliente') {
                $queryBuilder->where('id_cliente', $user->id);
            }
            $reclamos = $queryBuilder->where(function ($q) use ($query) {
                $q->where('tipo_reclamo', 'like', "%{$query}%")
                    ->orWhere('descripcion', 'like', "%{$query}%")
                    ->orWhere('respuesta', 'like', "%{$query}%")
                    ->orWhere('estado_reclamo', 'like', "%{$query}%");
            })
                ->where('state', 'activo')
                ->limit($limit)
                ->get();

            foreach ($reclamos as $rec) {
                $results[] = [
                    'id' => $rec->id,
                    'title' => "Reclamo #{$rec->id} - Tipo: {$rec->tipo_reclamo}",
                    'description' => 'Reclamo - Estado: '.ucfirst($rec->estado_reclamo)." | Cliente: {$rec->cliente->nombre} {$rec->cliente->apellido} | Desc: {$rec->descripcion}",
                    'link' => "/reclamos/{$rec->id}",
                    'type' => 'Reclamo',
                    'icon' => 'AlertTriangle',
                ];
            }
        }

        // 8. Search Ofertas (if authorized)
        if ($user->role && $user->role->hasPermission('ofertas.ver')) {
            $ofertas = Oferta::query()->with('producto')
                ->where(function ($q) use ($query) {
                    $q->where('nombre', 'like', "%{$query}%")
                        ->orWhere('descripcion', 'like', "%{$query}%")
                        ->orWhere('tipo_descuento', 'like', "%{$query}%")
                        ->orWhere('estado_oferta', 'like', "%{$query}%");
                })
                ->where('state', 'activo')
                ->limit($limit)
                ->get();

            foreach ($ofertas as $of) {
                $prodName = $of->producto ? $of->producto->nombre : 'Varios';
                $results[] = [
                    'id' => $of->id,
                    'title' => "Oferta: {$of->nombre}",
                    'description' => 'Oferta - Descuento: '.($of->tipo_descuento === 'porcentaje' ? "{$of->valor_descuento}%" : "Bs. {$of->valor_descuento}")." | Producto: {$prodName} | Estado: ".ucfirst($of->estado_oferta),
                    'link' => '/ofertas',
                    'type' => 'Oferta',
                    'icon' => 'Tag',
                ];
            }
        }

        return $results;
    }
}
