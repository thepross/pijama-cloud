<?php

namespace Tests\Feature;

use App\Models\Envio;
use App\Models\Oferta;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Reclamo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPageTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private User $adminUser;

    private Role $customerRole;

    private User $customerUser;

    private Role $distRole;

    private User $distUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        $this->distRole = Role::create([
            'nombre' => 'Distribuidor',
            'descripcion' => 'Shipment handler',
            'state' => 'activo',
        ]);

        // Create Permissions
        $permissions = [
            'usuarios' => Permiso::firstOrCreate(['nombre' => 'Usuarios'], ['descripcion' => 'u', 'ruta' => 'usuarios', 'icono' => 'Users', 'orden' => 1]),
            'roles' => Permiso::firstOrCreate(['nombre' => 'Roles'], ['descripcion' => 'r', 'ruta' => 'roles', 'icono' => 'Shield', 'orden' => 2]),
            'productos' => Permiso::firstOrCreate(['nombre' => 'Productos'], ['descripcion' => 'p', 'ruta' => 'productos', 'icono' => 'Archive', 'orden' => 3]),
            'pedidos' => Permiso::firstOrCreate(['nombre' => 'Pedidos'], ['descripcion' => 'pe', 'ruta' => 'pedidos', 'icono' => 'ShoppingBag', 'orden' => 4]),
            'envios' => Permiso::firstOrCreate(['nombre' => 'Envíos'], ['descripcion' => 'e', 'ruta' => 'envios', 'icono' => 'Truck', 'orden' => 5]),
            'pagos' => Permiso::firstOrCreate(['nombre' => 'Pagos'], ['descripcion' => 'pa', 'ruta' => 'pagos', 'icono' => 'CreditCard', 'orden' => 6]),
            'reclamos' => Permiso::firstOrCreate(['nombre' => 'Reclamos'], ['descripcion' => 're', 'ruta' => 'reclamos', 'icono' => 'AlertTriangle', 'orden' => 7]),
            'ofertas' => Permiso::firstOrCreate(['nombre' => 'Ofertas'], ['descripcion' => 'o', 'ruta' => 'ofertas', 'icono' => 'Tag', 'orden' => 8]),
        ];

        // Assign permissions
        $this->adminRole->permissions()->attach(collect($permissions)->pluck('id')->toArray());
        $this->customerRole->permissions()->attach([
            $permissions['productos']->id,
            $permissions['pedidos']->id,
            $permissions['envios']->id,
            $permissions['pagos']->id,
            $permissions['reclamos']->id,
        ]);
        $this->distRole->permissions()->attach([
            $permissions['envios']->id,
        ]);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'nombre' => 'AdminJuan',
            'email' => 'admin@pijama.com',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'nombre' => 'ClienteJuan',
            'email' => 'cliente@pijama.com',
        ]);

        $this->distUser = User::factory()->create([
            'id_rol' => $this->distRole->id,
            'nombre' => 'DistribuidorJuan',
            'email' => 'distribuidor@pijama.com',
        ]);

        // Create a product containing "Juan"
        $product = Producto::create([
            'nombre' => 'Pijama Algodon Juan',
            'precio_compra' => 15,
            'precio_venta' => 25,
            'stock' => 10,
            'stock_minimo' => 2,
            'categoria' => 'Adultos',
            'state' => 'activo',
        ]);

        // Create Order for customer
        $order = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now(),
            'total' => 25.00,
            'estado_pedido' => 'confirmado',
            'observacion' => 'Pedido de Juan',
            'state' => 'activo',
        ]);

        // Create Envio (linked to order)
        Envio::create([
            'id_pedido' => $order->id,
            'id_distribuidor' => $this->distUser->id,
            'direccion_entrega' => 'Dirección de Juan',
            'estado_envio' => 'pendiente',
            'observacion' => 'Envío especial para Juan',
            'state' => 'activo',
        ]);

        // Create Pago
        Pago::create([
            'id_pedido' => $order->id,
            'monto' => 25.00,
            'fecha_pago' => now(),
            'tipo_pago' => 'Efectivo',
            'estado_pago' => 'completado',
            'observacion' => 'Pago de Juan',
        ]);

        // Create Reclamo
        Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'id_pedido' => $order->id,
            'tipo_reclamo' => 'Defecto',
            'descripcion' => 'Reclamo de Juan',
            'fecha_reclamo' => now(),
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        // Create Oferta
        Oferta::create([
            'id_producto' => $product->id,
            'nombre' => 'Oferta Especial Juan',
            'descripcion' => 'Descuento para Juan',
            'valor_descuento' => 10,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addDays(5),
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);
    }

    public function test_guest_cannot_access_search_page(): void
    {
        $response = $this->get('/buscar?query=Juan');

        $response->assertRedirect('/login');
    }

    public function test_admin_receives_matching_results_across_all_modules(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/buscar?query=Juan');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('buscar/Index')
            // AdminJuan (User), DistribuidorJuan (User), ClienteJuan (User)
            // Pijama Algodon Juan (Product)
            // Pedido #1 (Order)
            // Envio #1 (Shipment)
            // Pago #1 (Payment)
            // Reclamo #1 (Claim)
            // Oferta Juan (Offer)
            // Total should find 9 results
            ->has('results', 9)
            ->where('query', 'Juan')
        );
    }

    public function test_client_only_receives_their_own_scoped_results(): void
    {
        $response = $this->actingAs($this->customerUser)->get('/buscar?query=Juan');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('buscar/Index')
            // Customer permissions: Productos, Pedidos, Envios, Pagos, Reclamos.
            // Under these, customer is scoped to their own records.
            // Finds:
            // 1. Pijama Algodon Juan (Product)
            // 2. Pedido #1 (Order of customer)
            // 3. Envio #1 (Shipment of customer's order)
            // 4. Pago #1 (Payment of customer's order)
            // 5. Reclamo #1 (Claim of customer)
            // Client does not see Users, Roles, or Ofertas.
            // Total should find 5 results
            ->has('results', 5)
            ->where('query', 'Juan')
        );
    }

    public function test_distributor_only_receives_assigned_shipments(): void
    {
        $response = $this->actingAs($this->distUser)->get('/buscar?query=Juan');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('buscar/Index')
            // Distributor permissions: Envios.
            // Restricted to assigned shipments.
            // Finds: Envio #1.
            // Total should find 1 result
            ->has('results', 1)
            ->where('query', 'Juan')
        );
    }
}
