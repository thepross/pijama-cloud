<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedidos';

    protected $fillable = [
        'id_cliente',
        'fecha_pedido',
        'total',
        'estado_pedido',
        'observacion',
        'state',
    ];

    /**
     * Get the customer who placed the order.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_cliente');
    }

    /**
     * Get the items (details) of the order.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'id_pedido');
    }

    /**
     * Get the shipments for this order.
     */
    public function envios(): HasMany
    {
        return $this->hasMany(Envio::class, 'id_pedido');
    }

    /**
     * Get the payments associated with this order.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'id_pedido');
    }

    /**
     * Get the claims associated with this order.
     */
    public function reclamos(): HasMany
    {
        return $this->hasMany(Reclamo::class, 'id_pedido');
    }
}
