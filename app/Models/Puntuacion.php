<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Puntuacion extends Model
{
    use HasFactory;

    protected $table = 'puntuaciones';

    protected $fillable = [
        'id_cliente',
        'id_producto',
        'puntuacion',
        'comentario',
        'fecha_puntuacion',
        'state',
    ];

    /**
     * Get the customer who rated the product.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_cliente');
    }

    /**
     * Get the rated product.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto');
    }
}
