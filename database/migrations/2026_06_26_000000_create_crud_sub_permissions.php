<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Define all root permissions in the system and their child actions
        $permissionsConfig = [
            'dashboard' => ['ver' => 'Ver Dashboard'],
            'productos' => [
                'ver' => 'Ver Productos',
                'crear' => 'Crear Productos',
                'editar' => 'Editar Productos',
                'eliminar' => 'Eliminar Productos'
            ],
            'pedidos' => [
                'ver' => 'Ver Pedidos',
                'crear' => 'Crear Pedidos',
                'editar' => 'Editar Pedidos',
                'eliminar' => 'Eliminar Pedidos'
            ],
            'envios' => [
                'ver' => 'Ver Envíos',
                'crear' => 'Crear Envíos',
                'editar' => 'Editar Envíos',
                'eliminar' => 'Eliminar Envíos'
            ],
            'pagos' => [
                'ver' => 'Ver Pagos',
                'crear' => 'Registrar Pagos',
                'editar' => 'Confirmar/Editar Pagos',
                'eliminar' => 'Eliminar Pagos'
            ],
            'reclamos' => [
                'ver' => 'Ver Reclamos',
                'crear' => 'Crear Reclamos',
                'editar' => 'Responder/Editar Reclamos',
                'eliminar' => 'Eliminar Reclamos'
            ],
            'estadisticas' => ['ver' => 'Ver Estadísticas'],
            'bitacoras' => ['ver' => 'Ver Bitácora'],
            'roles' => [
                'ver' => 'Ver Roles',
                'crear' => 'Crear Roles',
                'editar' => 'Editar Roles',
                'eliminar' => 'Eliminar Roles'
            ],
            'usuarios' => [
                'ver' => 'Ver Usuarios',
                'crear' => 'Crear Usuarios',
                'editar' => 'Editar/Bloquear Usuarios',
                'eliminar' => 'Eliminar Usuarios'
            ],
            'ofertas' => [
                'ver' => 'Ver Ofertas',
                'crear' => 'Crear Ofertas',
                'editar' => 'Editar Ofertas',
                'eliminar' => 'Eliminar Ofertas'
            ],
        ];

        // We will insert each child permission linked to its parent
        foreach ($permissionsConfig as $parentRuta => $actions) {
            $parent = DB::table('permisos')->where('ruta', $parentRuta)->first();
            if (!$parent) {
                continue;
            }

            $orderIndex = 1;
            foreach ($actions as $actionKey => $actionName) {
                $childRuta = "{$parentRuta}.{$actionKey}";
                
                // Avoid duplication
                $existing = DB::table('permisos')->where('ruta', $childRuta)->first();
                if ($existing) {
                    continue;
                }

                DB::table('permisos')->insert([
                    'nombre' => $actionName,
                    'descripcion' => "Permiso para {$actionName} en el sistema",
                    'ruta' => $childRuta,
                    'icono' => null,
                    'id_padre' => $parent->id,
                    'orden' => $orderIndex++,
                    'state' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Attach child permissions to existing roles
        $roles = DB::table('roles')->get();
        foreach ($roles as $role) {
            $parentPerms = DB::table('rol_permiso')
                ->where('id_rol', $role->id)
                ->pluck('id_permiso')
                ->toArray();

            foreach ($parentPerms as $parentId) {
                $parent = DB::table('permisos')->where('id', $parentId)->first();
                if (!$parent) {
                    continue;
                }

                // Get all child permissions for this parent
                $children = DB::table('permisos')
                    ->where('id_padre', $parent->id)
                    ->get();

                foreach ($children as $child) {
                    // Check if role is allowed to have this child permission based on default constraints
                    $allowed = true;
                    if ($role->nombre === 'Cliente') {
                        // Clients can only read products, cannot modify them
                        if ($parent->ruta === 'productos' && str_contains($child->ruta, '.ver') === false) {
                            $allowed = false;
                        }
                    }
                    if ($role->nombre === 'Distribuidor') {
                        // Distributors cannot create or delete shipments, only view and edit status
                        if ($parent->ruta === 'envios' && (str_contains($child->ruta, '.crear') || str_contains($child->ruta, '.eliminar'))) {
                            $allowed = false;
                        }
                    }

                    if ($allowed) {
                        // Attach to role
                        $exists = DB::table('rol_permiso')
                            ->where('id_rol', $role->id)
                            ->where('id_permiso', $child->id)
                            ->exists();

                        if (!$exists) {
                            DB::table('rol_permiso')->insert([
                                'id_rol' => $role->id,
                                'id_permiso' => $child->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete all child permissions (where id_padre is not null)
        DB::table('rol_permiso')
            ->whereIn('id_permiso', function ($query) {
                $query->select('id')->from('permisos')->whereNotNull('id_padre');
            })->delete();

        DB::table('permisos')->whereNotNull('id_padre')->delete();
    }
};
