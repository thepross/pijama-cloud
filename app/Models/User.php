<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id_rol',
        'username',
        'password',
        'nombre',
        'apellido',
        'ci',
        'email',
        'telefono',
        'direccion',
        'foto',
        'id_social',
        'proveedor_sso',
        'state',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido}");
    }

    /**
     * Get the role associated with the user.
     */
    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol');
    }

    /**
     * Get the orders placed by the customer.
     */
    public function pedidos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Pedido::class, 'id_cliente');
    }

    /**
     * Get the ratings submitted by the customer.
     */
    public function puntuaciones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Puntuacion::class, 'id_cliente');
    }

    /**
     * Get the complaints made by the customer.
     */
    public function reclamos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Reclamo::class, 'id_cliente');
    }

    /**
     * Get the shipments handled by the distributor.
     */
    public function envios(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Envio::class, 'id_distribuidor');
    }
}
