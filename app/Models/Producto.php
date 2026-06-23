<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    protected $table = 'productos';

    protected $fillable = [
        'codigo_qr',
        'nombre',
        'descripcion',
        'color',
        'talla',
        'genero',
        'marca',
        'material',
        'precio_compra',
        'precio_venta',
        'stock',
        'stock_minimo',
        'categoria',
        'foto',
        'state',
    ];

    /**
     * Get the ratings for this product.
     */
    public function puntuaciones(): HasMany
    {
        return $this->hasMany(Puntuacion::class, 'id_producto');
    }

    /**
     * Get the offers/discounts for this product.
     */
    public function ofertas(): HasMany
    {
        return $this->hasMany(Oferta::class, 'id_producto');
    }

    /**
     * Get the order details that contain this product.
     */
    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'id_producto');
    }
}
