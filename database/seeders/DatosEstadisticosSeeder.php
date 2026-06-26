<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Pago;
use App\Models\Reclamo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatosEstadisticosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get our cliente
        $cliente = User::where('username', 'cliente')->first();
        if (!$cliente) {
            $clienteRole = \App\Models\Role::where('nombre', 'Cliente')->first();
            $cliente = User::create([
                'id_rol' => $clienteRole ? $clienteRole->id : 2,
                'username' => 'cliente',
                'password' => \Hash::make('password'),
                'nombre' => 'Juan',
                'apellido' => 'Perez',
                'ci' => '1000002',
                'email' => 'cliente@pijama.com',
                'telefono' => '70000002',
                'direccion' => 'Calle Secundaria 456',
                'state' => 'activo',
            ]);
        }

        // 2. Add extra products with different categories if they don't exist
        $productsData = [
            [
                'codigo_qr' => 'QR-PIJ-004',
                'nombre' => 'Pijama Polar Oso Pardo',
                'descripcion' => 'Pijama polar de cuerpo completo ultra abrigado.',
                'color' => 'Marrón',
                'talla' => 'L',
                'genero' => 'Hombre',
                'marca' => 'Pijama Cloud',
                'material' => 'Polar',
                'precio_compra' => 18.00,
                'precio_venta' => 35.50,
                'stock' => 2, // Bajo stock
                'stock_minimo' => 5,
                'categoria' => 'Adultos',
                'state' => 'activo',
            ],
            [
                'codigo_qr' => 'QR-PIJ-005',
                'nombre' => 'Mameluco Osito Dormilón',
                'descripcion' => 'Mameluco de algodón orgánico para bebés de 6 a 12 meses.',
                'color' => 'Amarillo Pastel',
                'talla' => '9M',
                'genero' => 'Unisex',
                'marca' => 'Pijama Cloud Baby',
                'material' => 'Algodón Orgánico',
                'precio_compra' => 8.00,
                'precio_venta' => 17.99,
                'stock' => 45,
                'stock_minimo' => 10,
                'categoria' => 'Niños',
                'state' => 'activo',
            ],
            [
                'codigo_qr' => 'QR-PIJ-006',
                'nombre' => 'Antifaz de Seda Premium',
                'descripcion' => 'Antifaz de seda natural ultra suave y acolchado.',
                'color' => 'Negro',
                'talla' => 'Única',
                'genero' => 'Unisex',
                'marca' => 'Pijama Cloud',
                'material' => 'Seda',
                'precio_compra' => 4.00,
                'precio_venta' => 12.00,
                'stock' => 3, // Bajo stock
                'stock_minimo' => 10,
                'categoria' => 'Accesorios',
                'state' => 'activo',
            ],
            [
                'codigo_qr' => 'QR-PIJ-007',
                'nombre' => 'Calcetines Térmicos de Lana',
                'descripcion' => 'Calcetines gruesos de invierno con forro de chiporro.',
                'color' => 'Gris',
                'talla' => 'Única',
                'genero' => 'Unisex',
                'marca' => 'Pijama Cloud',
                'material' => 'Lana',
                'precio_compra' => 3.00,
                'precio_venta' => 7.50,
                'stock' => 8,
                'stock_minimo' => 15, // Bajo stock
                'categoria' => 'Accesorios',
                'state' => 'activo',
            ],
            [
                'codigo_qr' => 'QR-PIJ-008',
                'nombre' => 'Pijama Satín Starry Night',
                'descripcion' => 'Elegante pijama de dos piezas con botones en color azul marino satinado.',
                'color' => 'Azul Marino',
                'talla' => 'S',
                'genero' => 'Mujer',
                'marca' => 'Pijama Cloud',
                'material' => 'Satín',
                'precio_compra' => 20.00,
                'precio_venta' => 42.00,
                'stock' => 60,
                'stock_minimo' => 5,
                'categoria' => 'Adultos',
                'state' => 'activo',
            ]
        ];

        foreach ($productsData as $pData) {
            Producto::firstOrCreate(['codigo_qr' => $pData['codigo_qr']], $pData);
        }

        // 3. Clear existing transactional data in correct dependency order
        Reclamo::query()->delete();
        Pago::query()->delete();
        DetallePedido::query()->delete();
        Pedido::query()->delete();

        // Get all products to construct details
        $productos = Producto::where('state', 'activo')->get();

        if ($productos->count() === 0) {
            return;
        }

        // Generate sales history over the last 30 days
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        $paymentTypes = ['efectivo', 'tarjeta', 'qr'];
        $createdOrders = [];

        // Loop through each day in the last 30 days
        for ($date = clone $startDate; $date->lte($endDate); $date->addDay()) {
            // Random number of orders per day (between 0 and 3)
            $numOrders = rand(0, 3);
            
            for ($i = 0; $i < $numOrders; $i++) {
                // Determine order status
                // 70% Entregado, 15% Confirmado, 10% Pendiente, 5% Cancelado
                $statusRand = rand(1, 100);
                if ($statusRand <= 70) {
                    $status = 'entregado';
                } elseif ($statusRand <= 85) {
                    $status = 'confirmado';
                } elseif ($statusRand <= 95) {
                    $status = 'pendiente';
                } else {
                    $status = 'cancelado';
                }

                // Create the order
                $pedido = Pedido::create([
                    'id_cliente' => $cliente->id,
                    'fecha_pedido' => $date->toDateString() . ' ' . sprintf('%02d:%02d:%02d', rand(8, 20), rand(0, 59), rand(0, 59)),
                    'total' => 0.00, // Will update
                    'estado_pedido' => $status,
                    'observacion' => $status === 'cancelado' ? 'El cliente canceló la compra.' : 'Generado por DatosEstadisticosSeeder',
                    'state' => 'activo',
                ]);

                // Choose random products (1 to 3 different ones)
                $orderProducts = $productos->random(rand(1, min(3, $productos->count())));
                $totalOrder = 0.00;

                foreach ($orderProducts as $prod) {
                    $cant = rand(1, 3);
                    $precioVenta = $prod->precio_venta;
                    $descuento = 0.00;
                    
                    // Small chance of offering a discount on detail level
                    if (rand(1, 10) === 1) {
                        $descuento = round($precioVenta * 0.10, 2); // 10% discount
                    }

                    $subtotal = ($precioVenta - $descuento) * $cant;
                    $totalOrder += $subtotal;

                    DetallePedido::create([
                        'id_pedido' => $pedido->id,
                        'id_producto' => $prod->id,
                        'cantidad' => $cant,
                        'precio_venta' => $precioVenta,
                        'descuento' => $descuento,
                        'subtotal' => $subtotal,
                        'state' => 'activo',
                    ]);
                }

                // Update order total
                $pedido->update(['total' => $totalOrder]);
                $createdOrders[] = $pedido;

                // Create associated payments for non-cancelled & non-pending orders
                if ($status !== 'cancelado' && $status !== 'pendiente') {
                    // Check if order has split payments or full payment
                    $payRand = rand(1, 100);
                    if ($payRand <= 30) {
                        // 2 installments
                        $halfTotal = round($totalOrder / 2, 2);
                        
                        // First payment
                        Pago::create([
                            'id_pedido' => $pedido->id,
                            'monto' => $halfTotal,
                            'fecha_pago' => Carbon::parse($pedido->fecha_pedido)->toDateTimeString(),
                            'tipo_pago' => $paymentTypes[rand(0, 2)],
                            'estado_pago' => 'completado',
                            'total_cuotas' => 2,
                            'numero_cuota' => 1,
                            'saldo_pendiente' => $totalOrder - $halfTotal,
                            'observacion' => 'Primer abono de cuotas',
                        ]);

                        // Second payment (maybe a day or 2 later)
                        $payDate2 = Carbon::parse($pedido->fecha_pedido)->addDays(rand(1, 3));
                        if ($payDate2->lte($endDate)) {
                            Pago::create([
                                'id_pedido' => $pedido->id,
                                'monto' => $totalOrder - $halfTotal,
                                'fecha_pago' => $payDate2->toDateTimeString(),
                                'tipo_pago' => $paymentTypes[rand(0, 2)],
                                'estado_pago' => 'completado',
                                'total_cuotas' => 2,
                                'numero_cuota' => 2,
                                'saldo_pendiente' => 0,
                                'observacion' => 'Saldo liquidado de cuotas',
                            ]);
                        }
                    } else {
                        // Single full payment
                        Pago::create([
                            'id_pedido' => $pedido->id,
                            'monto' => $totalOrder,
                            'fecha_pago' => Carbon::parse($pedido->fecha_pedido)->toDateTimeString(),
                            'tipo_pago' => $paymentTypes[rand(0, 2)],
                            'estado_pago' => 'completado',
                            'total_cuotas' => 1,
                            'numero_cuota' => 1,
                            'saldo_pendiente' => 0,
                            'observacion' => 'Pago único total',
                        ]);
                    }
                }
            }
        }

        // 4. Generate Claims (Reclamos)
        $claimTypes = ['producto_defectuoso', 'retraso_envio', 'talla_incorrecta', 'otro'];
        $claimStates = ['pendiente', 'en_proceso', 'atendido', 'rechazado'];
        $claimDescriptions = [
            'La pijama vino descosida en la costura lateral.',
            'El pedido tardó 3 días más de lo prometido.',
            'Pedí talla M pero me llegó talla S.',
            'El color del estampado no coincide con las fotos de la web.',
            'No recibí el antifaz de regalo de la oferta.',
            'El material polar pica al contacto directo.',
            'Se equivocaron de modelo de dinosaurios.',
            'El paquete estaba abierto al momento de la entrega.'
        ];

        $ordersForClaims = collect($createdOrders)
            ->where('estado_pedido', '!=', 'cancelado')
            ->shuffle()
            ->take(min(8, count($createdOrders)));

        $claimIndex = 0;
        foreach ($ordersForClaims as $order) {
            $state = $claimStates[$claimIndex % 4];
            $claimDate = Carbon::parse($order->fecha_pedido)->addDays(rand(1, 3));
            
            Reclamo::create([
                'id_cliente' => $cliente->id,
                'id_pedido' => $order->id,
                'tipo_reclamo' => $claimTypes[rand(0, 3)],
                'descripcion' => $claimDescriptions[$claimIndex % count($claimDescriptions)],
                'fecha_reclamo' => $claimDate->toDateString(),
                'fecha_respuesta' => in_array($state, ['atendido', 'rechazado']) ? $claimDate->addDays(rand(1, 2))->toDateString() : null,
                'respuesta' => in_array($state, ['atendido', 'rechazado']) ? 'Respuesta oficial: Se resolvió con el cliente.' : null,
                'estado_reclamo' => $state,
                'state' => 'activo',
            ]);

            $claimIndex++;
        }
    }
}
