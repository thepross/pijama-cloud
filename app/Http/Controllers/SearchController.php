<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

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

        $results = [];
        $user = Auth::user();

        if (!$user) {
            return response()->json([]);
        }

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
                ->limit(5)
                ->get();

            foreach ($users as $u) {
                $results[] = [
                    'id' => $u->id,
                    'title' => "{$u->nombre} {$u->apellido} (@{$u->username})",
                    'description' => "Usuario - CI: {$u->ci} | Email: {$u->email}",
                    'link' => "/usuarios?search=" . urlencode($u->username),
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
                ->limit(5)
                ->get();

            foreach ($roles as $r) {
                $results[] = [
                    'id' => $r->id,
                    'title' => "Rol: {$r->nombre}",
                    'description' => "Rol de acceso - {$r->descripcion}",
                    'link' => "/roles?search=" . urlencode($r->nombre),
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
                ->limit(5)
                ->get();

            foreach ($productos as $p) {
                $results[] = [
                    'id' => $p->id,
                    'title' => $p->nombre,
                    'description' => "Producto - Categoría: {$p->categoria} | Talla: {$p->talla} | Stock: {$p->stock}",
                    'link' => "/productos?search=" . urlencode($p->nombre),
                    'type' => 'Producto',
                    'icon' => 'Archive',
                ];
            }
        }

        return response()->json($results);
    }
}
