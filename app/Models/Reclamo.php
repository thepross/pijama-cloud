<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reclamo extends Model
{
    use HasFactory;

    protected $table = 'reclamos';

    protected $fillable = [
        'id_cliente',
        'id_pedido',
        'tipo_reclamo',
        'descripcion',
        'fecha_reclamo',
        'fecha_respuesta',
        'respuesta',
        'estado_reclamo',
        'state',
    ];

    /**
     * Get the customer who filed the claim.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_cliente');
    }

    /**
     * Get the order linked to this claim.
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }
}
