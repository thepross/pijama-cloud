<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permiso;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class RoleController extends Controller
{
    private function authorizeRoleAction(string $action): void
    {
        if (!Auth::check()) {
            abort(403, 'No autenticado.');
        }

        $user = Auth::user();
        $mapping = [
            'index'   => 'roles.ver',
            'show'    => 'roles.ver',
            'create'  => 'roles.crear',
            'store'   => 'roles.crear',
            'edit'    => 'roles.editar',
            'update'  => 'roles.editar',
            'destroy' => 'roles.eliminar',
        ];

        $perm = $mapping[$action] ?? 'roles.ver';

        if (!$user->role->hasPermission($perm)) {
            abort(403, 'No tienes permiso para realizar esta acción sobre roles.');
        }
    }

    /**
     * Display a listing of active roles.
     */
    public function index(Request $request): Response
    {
        $this->authorizeRoleAction('index');

        $search = $request->input('search');

        $roles = Role::query()
            ->where('state', 'activo')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'filters' => $request->only('search'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create(): Response
    {
        $this->authorizeRoleAction('create');

        $permissions = Permiso::whereNull('id_padre')
            ->where('state', 'activo')
            ->with(['hijos' => function ($q) {
                $q->where('state', 'activo')->orderBy('orden');
            }])
            ->orderBy('orden')
            ->get();

        return Inertia::render('roles/Create', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role in database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRoleAction('store');

        $request->validate([
            'nombre' => 'required|string|max:255|unique:roles,nombre',
            'descripcion' => 'nullable|string|max:1000',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permisos,id',
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio.',
            'nombre.unique' => 'Este nombre de rol ya está registrado.',
            'permissions.required' => 'Debes asignar al menos un permiso al rol.',
            'permissions.min' => 'Debes asignar al menos un permiso al rol.',
        ]);

        $role = Role::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'state' => 'activo',
        ]);

        $role->permissions()->sync($request->permissions);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_rol',
            'ip' => $request->ip(),
            'recurso' => 'roles',
            'detalle' => json_encode([
                'id' => $role->id,
                'nombre' => $role->nombre,
                'permisos_asignados' => $request->permissions,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('roles.index')->with('success', 'Rol creado exitosamente.');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): Response
    {
        $this->authorizeRoleAction('edit');

        if ($role->state === 'inactivo') {
            abort(404, 'El rol no se encuentra activo o disponible.');
        }

        $permissions = Permiso::whereNull('id_padre')
            ->where('state', 'activo')
            ->with(['hijos' => function ($q) {
                $q->where('state', 'activo')->orderBy('orden');
            }])
            ->orderBy('orden')
            ->get();

        $assignedPermissionIds = $role->permissions()->pluck('id')->toArray();

        return Inertia::render('roles/Edit', [
            'role' => $role,
            'permissions' => $permissions,
            'assignedPermissionIds' => $assignedPermissionIds,
        ]);
    }

    /**
     * Update the specified role in database.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAction('update');

        if ($role->state === 'inactivo') {
            abort(404, 'El rol no se encuentra activo.');
        }

        $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'nombre')->ignore($role->id),
            ],
            'descripcion' => 'nullable|string|max:1000',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permisos,id',
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio.',
            'nombre.unique' => 'Este nombre de rol ya está registrado.',
            'permissions.required' => 'Debes asignar al menos un permiso al rol.',
            'permissions.min' => 'Debes asignar al menos un permiso al rol.',
        ]);

        $role->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
        ]);

        $role->permissions()->sync($request->permissions);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'modificar_rol',
            'ip' => $request->ip(),
            'recurso' => 'roles/' . $role->id,
            'detalle' => json_encode([
                'id' => $role->id,
                'nombre' => $role->nombre,
                'permisos_actualizados' => $request->permissions,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('roles.index')->with('success', 'Rol actualizado exitosamente.');
    }

    /**
     * Logically delete the specified role from database.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeRoleAction('destroy');

        if ($role->state === 'inactivo') {
            return to_route('roles.index')->with('error', 'El rol ya se encuentra inactivo.');
        }

        // Avoid logically deleting essential roles like Administrador or Cliente
        if (in_array($role->nombre, ['Administrador', 'Cliente'])) {
            return to_route('roles.index')->with('error', 'No se pueden eliminar los roles esenciales del sistema.');
        }

        $role->update(['state' => 'inactivo']);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_rol',
            'ip' => $request->ip(),
            'recurso' => 'roles/' . $role->id,
            'detalle' => json_encode([
                'id' => $role->id,
                'nombre' => $role->nombre,
                'estado' => 'inactivo',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('roles.index')->with('success', 'Rol eliminado lógicamente.');
    }
}
