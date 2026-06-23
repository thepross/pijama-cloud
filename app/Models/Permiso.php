<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    use HasFactory;

    protected $table = 'permisos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'ruta',
        'icono',
        'id_padre',
        'orden',
        'state',
    ];

    /**
     * Get the parent permission/menu item.
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Permiso::class, 'id_padre');
    }

    /**
     * Get child permissions/submenus.
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(Permiso::class, 'id_padre')->orderBy('orden');
    }

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'rol_permiso', 'id_permiso', 'id_rol');
    }
}
