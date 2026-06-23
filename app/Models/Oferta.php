<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Oferta extends Model
{
    use HasFactory;

    protected $table = 'ofertas';

    protected $fillable = [
        'id_producto',
        'nombre',
        'descripcion',
        'valor_descuento',
        'tipo_descuento',
        'fecha_inicio',
        'fecha_fin',
        'estado_oferta',
        'state',
    ];

    /**
     * Get the product associated with this offer.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}
