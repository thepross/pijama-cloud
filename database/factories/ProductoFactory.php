<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $precioCompra = $this->faker->randomFloat(2, 5, 50);
        return [
            'codigo_qr' => 'QR-' . $this->faker->unique()->isbn10(),
            'nombre' => $this->faker->word() . ' Pijama',
            'descripcion' => $this->faker->sentence(),
            'color' => $this->faker->colorName(),
            'talla' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
            'genero' => $this->faker->randomElement(['Unisex', 'Hombre', 'Mujer']),
            'marca' => $this->faker->company(),
            'material' => $this->faker->randomElement(['Algodón', 'Seda', 'Poliéster']),
            'precio_compra' => $precioCompra,
            'precio_venta' => $precioCompra + $this->faker->randomFloat(2, 5, 30),
            'stock' => $this->faker->numberBetween(10, 100),
            'stock_minimo' => $this->faker->numberBetween(2, 5),
            'categoria' => $this->faker->randomElement(['Niños', 'Jóvenes', 'Adultos']),
            'foto' => null,
            'state' => 'activo',
        ];
    }
}
