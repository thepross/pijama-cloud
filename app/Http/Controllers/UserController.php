<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private function authorizeUserAction(string $action): void
    {
        if (! Auth::check()) {
            abort(403, 'No autenticado.');
        }

        $user = Auth::user();
        $mapping = [
            'index' => 'usuarios.ver',
            'show' => 'usuarios.ver',
            'create' => 'usuarios.crear',
            'store' => 'usuarios.crear',
            'edit' => 'usuarios.editar',
            'update' => 'usuarios.editar',
            'destroy' => 'usuarios.eliminar',
        ];

        $perm = $mapping[$action] ?? 'usuarios.ver';

        if (! $user->role->hasPermission($perm)) {
            abort(403, 'No tienes permiso para realizar esta acción sobre usuarios.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeUserAction('index');

        $search = $request->input('search');
        $roleId = $request->input('role_id');

        $usuarios = User::query()
            ->with('role')
            ->where('state', 'activo')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                        ->orWhere('apellido', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%");
                });
            })
            ->when($roleId, function ($query, $roleId) {
                $query->where('id_rol', $roleId);
            })
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::where('state', 'activo')->get(['id', 'nombre']);

        return Inertia::render('usuarios/Index', [
            'usuarios' => $usuarios,
            'roles' => $roles,
            'filters' => $request->only(['search', 'role_id']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorizeUserAction('create');

        $roles = Role::where('state', 'activo')->get(['id', 'nombre']);

        return Inertia::render('usuarios/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUserAction('store');

        $request->validate([
            'username' => 'required|string|max:255|unique:usuarios,username',
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'ci' => 'required|string|max:255|unique:usuarios,ci',
            'email' => 'required|string|lowercase|email|max:255|unique:usuarios,email',
            'telefono' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'id_rol' => 'required|exists:roles,id',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.unique' => 'Este nombre de usuario ya está registrado.',
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'ci.required' => 'La cédula de identidad (CI) es obligatoria.',
            'ci.unique' => 'Esta cédula de identidad ya está registrada.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'id_rol.required' => 'Debes asignar un rol al usuario.',
            'id_rol.exists' => 'El rol seleccionado no es válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $user = User::create([
            'id_rol' => $request->id_rol,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'ci' => $request->ci,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'direccion' => $request->direccion,
            'state' => 'activo',
        ]);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_usuario',
            'ip' => $request->ip(),
            'recurso' => 'usuarios',
            'detalle' => json_encode([
                'id' => $user->id,
                'username' => $user->username,
                'id_rol' => $user->id_rol,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('usuarios.index')->with('success', 'Usuario creado exitosamente.');
    }

    public function edit(User $usuario): Response
    {
        $this->authorizeUserAction('edit');

        if ($usuario->state === 'inactivo') {
            abort(404, 'El usuario no se encuentra activo.');
        }

        $roles = Role::where('state', 'activo')->get(['id', 'nombre']);

        return Inertia::render('usuarios/Edit', [
            'usuario' => $usuario,
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $usuario): RedirectResponse
    {
        $this->authorizeUserAction('update');

        if ($usuario->state === 'inactivo') {
            abort(404, 'El usuario no se encuentra activo.');
        }

        $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('usuarios', 'username')->ignore($usuario->id),
            ],
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'ci' => [
                'required',
                'string',
                'max:255',
                Rule::unique('usuarios', 'ci')->ignore($usuario->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('usuarios', 'email')->ignore($usuario->id),
            ],
            'telefono' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'id_rol' => 'required|exists:roles,id',
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ], [
            'username.required' => 'El nombre de usuario es obligatorio.',
            'username.unique' => 'Este nombre de usuario ya está registrado.',
            'nombre.required' => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'ci.required' => 'La cédula de identidad (CI) es obligatoria.',
            'ci.unique' => 'Esta cédula de identidad ya está registrada.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección válida.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'id_rol.required' => 'Debes asignar un rol al usuario.',
            'id_rol.exists' => 'El rol seleccionado no es válido.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        $usuario->update([
            'id_rol' => $request->id_rol,
            'username' => $request->username,
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'ci' => $request->ci,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'direccion' => $request->direccion,
        ]);

        if ($request->filled('password')) {
            $usuario->update([
                'password' => Hash::make($request->password),
            ]);
        }

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'modificar_usuario',
            'ip' => $request->ip(),
            'recurso' => 'usuarios/'.$usuario->id,
            'detalle' => json_encode([
                'id' => $usuario->id,
                'username' => $usuario->username,
                'id_rol' => $usuario->id_rol,
                'password_cambiado' => $request->filled('password'),
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('usuarios.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    public function destroy(Request $request, User $usuario): RedirectResponse
    {
        $this->authorizeUserAction('destroy');

        if ($usuario->state === 'inactivo') {
            return to_route('usuarios.index')->with('error', 'El usuario ya se encuentra inactivo.');
        }

        if (Auth::id() === $usuario->id) {
            return to_route('usuarios.index')->with('error', 'No puedes eliminar lógicamente tu propia cuenta.');
        }

        $usuario->update(['state' => 'inactivo']);

        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_usuario',
            'ip' => $request->ip(),
            'recurso' => 'usuarios/'.$usuario->id,
            'detalle' => json_encode([
                'id' => $usuario->id,
                'username' => $usuario->username,
                'estado' => 'inactivo',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('usuarios.index')->with('success', 'Usuario eliminado lógicamente.');
    }
}
