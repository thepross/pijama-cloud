<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'id_pedido',
        'monto',
        'fecha_pago',
        'tipo_pago',
        'estado_pago',
        'total_cuotas',
        'numero_cuota',
        'saldo_pendiente',
        'observacion',
    ];

    /**
     * Get the order associated with the payment.
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'id_pedido');
    }
}
