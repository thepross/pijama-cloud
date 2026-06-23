<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Envio extends Model
{
    use HasFactory;

    protected $table = 'envios';

    protected $fillable = [
        'id_pedido',
        'id_distribuidor',
        'direccion_entrega',
        'fecha_salida',
        'fecha_entrega',
        'estado_envio',
        'observacion',
        'ruta',
        'state',
    ];

    /**
     * Get the order associated with the shipment.
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }

    /**
     * Get the distributor assigned to this shipment.
     */
    public function distribuidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_distribuidor');
    }
}
