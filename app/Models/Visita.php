<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visita extends Model
{
    use HasFactory;

    protected $table = 'visitas';

    protected $fillable = [
        'ruta',
        'contador',
    ];

    /**
     * Increment the visit count for a given route.
     */
    public static function registrarVisita(string $ruta): self
    {
        $visita = self::firstOrCreate(
            ['ruta' => $ruta],
            ['contador' => 0]
        );

        $visita->increment('contador');

        return $visita;
    }
}
