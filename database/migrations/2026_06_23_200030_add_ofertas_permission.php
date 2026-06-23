<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Insert the permission
        $permId = DB::table('permisos')->insertGetId([
            'nombre' => 'Ofertas',
            'descripcion' => 'Registro y gestión de ofertas y promociones',
            'ruta' => 'ofertas',
            'icono' => 'Tag',
            'id_padre' => null,
            'orden' => 11,
            'state' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Get the Administrador role ID
        $adminRole = DB::table('roles')->where('nombre', 'Administrador')->first();

        if ($adminRole) {
            // 3. Attach permission to Administrador
            DB::table('rol_permiso')->insert([
                'id_rol' => $adminRole->id,
                'id_permiso' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $perm = DB::table('permisos')->where('nombre', 'Ofertas')->first();

        if ($perm) {
            DB::table('rol_permiso')->where('id_permiso', $perm->id)->delete();
            DB::table('permisos')->where('id', $perm->id)->delete();
        }
    }
};
