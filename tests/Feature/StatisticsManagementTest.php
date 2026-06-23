<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Pedido;
use App\Models\DetallePedido;
use App\Models\Producto;
use App\Models\Reclamo;
use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;
    private User $adminUser;

    private Role $vendorRole;
    private User $vendorUser;

    private Role $customerRole;
    private User $customerUser;

    private Role $distributorRole;
    private User $distributorUser;

    private Permiso $statsPermission;
    private Producto $pajamaKids;
    private Producto $pajamaAdult;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Roles
        $this->adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'System admin',
            'state' => 'activo',
        ]);

        $this->vendorRole = Role::create([
            'nombre' => 'Vendedor',
            'descripcion' => 'Seller',
            'state' => 'activo',
        ]);

        $this->customerRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Customer',
            'state' => 'activo',
        ]);

        $this->distributorRole = Role::create([
            'nombre' => 'Distribuidor',
            'descripcion' => 'Distributor',
            'state' => 'activo',
        ]);

        // Create Permission for Stats
        $this->statsPermission = Permiso::create([
            'nombre' => 'Estadísticas',
            'descripcion' => 'Ver informes y estadísticas comerciales',
            'ruta' => 'estadisticas',
            'icono' => 'TrendingUp',
            'orden' => 7,
        ]);

        // Attach permissions (only Admin and Vendor have estadisticas)
        $this->adminRole->permissions()->attach($this->statsPermission->id);
        $this->vendorRole->permissions()->attach($this->statsPermission->id);

        // Create Users
        $this->adminUser = User::factory()->create([
            'id_rol' => $this->adminRole->id,
            'email' => 'admin@pijama.com',
            'state' => 'activo',
        ]);

        $this->vendorUser = User::factory()->create([
            'id_rol' => $this->vendorRole->id,
            'email' => 'vendedor@pijama.com',
            'state' => 'activo',
        ]);

        $this->customerUser = User::factory()->create([
            'id_rol' => $this->customerRole->id,
            'email' => 'cliente@pijama.com',
            'state' => 'activo',
        ]);

        $this->distributorUser = User::factory()->create([
            'id_rol' => $this->distributorRole->id,
            'email' => 'distribuidor@pijama.com',
            'state' => 'activo',
        ]);

        // Create Products
        $this->pajamaKids = Producto::factory()->create([
            'nombre' => 'Pijama Dinosaurio Niños',
            'categoria' => 'KIDS',
            'precio_venta' => 45.00,
            'stock' => 12,
            'state' => 'activo',
        ]);

        $this->pajamaAdult = Producto::factory()->create([
            'nombre' => 'Pijama Seda Adultos',
            'categoria' => 'ADULTS',
            'precio_venta' => 80.00,
            'stock' => 3, // Low stock critical trigger
            'state' => 'activo',
        ]);
    }

    public function test_guest_cannot_access_statistics(): void
    {
        $this->get(route('estadisticas.index'))->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_access_statistics(): void
    {
        $response = $this->actingAs($this->distributorUser)->get(route('estadisticas.index'));
        $response->assertStatus(403);
    }

    public function test_client_cannot_access_statistics(): void
    {
        $response = $this->actingAs($this->customerUser)->get(route('estadisticas.index'));
        $response->assertStatus(403);
    }

    public function test_staff_can_access_statistics(): void
    {
        $response = $this->actingAs($this->vendorUser)->get(route('estadisticas.index'));
        $response->assertStatus(200);
    }

    public function test_statistics_aggregates_are_correct(): void
    {
        // 1. Create paid/confirmed orders
        $order1 = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 170.00,
            'estado_pedido' => 'confirmado',
            'state' => 'activo',
        ]);

        DetallePedido::create([
            'id_pedido' => $order1->id,
            'id_producto' => $this->pajamaKids->id,
            'cantidad' => 2,
            'precio_venta' => 45.00,
            'subtotal' => 90.00,
            'state' => 'activo',
        ]);

        DetallePedido::create([
            'id_pedido' => $order1->id,
            'id_producto' => $this->pajamaAdult->id,
            'cantidad' => 1,
            'precio_venta' => 80.00,
            'subtotal' => 80.00,
            'state' => 'activo',
        ]);

        // 2. Create claims
        Reclamo::create([
            'id_cliente' => $this->customerUser->id,
            'tipo_reclamo' => 'Prenda defectuosa',
            'descripcion' => 'Falta un botón.',
            'fecha_reclamo' => now()->toDateString(),
            'estado_reclamo' => 'pendiente',
            'state' => 'activo',
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('estadisticas.index'));
        $response->assertStatus(200);

        $data = $response->original->getData()['page']['props'];

        // Assert KPIs
        $this->assertEquals(170.00, $data['kpis']['ingresos_totales']);
        $this->assertEquals(1, $data['kpis']['total_pedidos']);
        $this->assertEquals(1, $data['kpis']['bajo_stock_count']); // pajamaAdult stock is 3 (<= 5)
        $this->assertEquals(1, $data['kpis']['reclamos_pendientes']);

        // Assert Best Selling Products
        $this->assertCount(2, $data['mejores_productos']);
        $this->assertEquals('Pijama Dinosaurio Niños', $data['mejores_productos'][0]['nombre']);
        $this->assertEquals(2, $data['mejores_productos'][0]['cantidad_vendida']);

        // Assert Category distribution
        $this->assertCount(2, $data['ventas_categorias']);
        
        // Assert Claims status ratio
        $this->assertEquals(1, $data['reclamos_ratio']['pendiente']);
    }

    public function test_statistics_respects_date_filtering(): void
    {
        // Order inside the filter window (today)
        $orderInside = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->toDateString(),
            'total' => 100.00,
            'estado_pedido' => 'confirmado',
            'state' => 'activo',
        ]);

        // Order outside the filter window (35 days ago)
        $orderOutside = Pedido::create([
            'id_cliente' => $this->customerUser->id,
            'fecha_pedido' => now()->subDays(35)->toDateString(),
            'total' => 300.00,
            'estado_pedido' => 'confirmado',
            'state' => 'activo',
        ]);

        // Query default (last 30 days) -> only inside order is counted
        $response1 = $this->actingAs($this->adminUser)->get(route('estadisticas.index'));
        $data1 = $response1->original->getData()['page']['props'];
        $this->assertEquals(100.00, $data1['kpis']['ingresos_totales']);

        // Query custom window (last 40 days to today) -> both are counted
        $response2 = $this->actingAs($this->adminUser)->get(route('estadisticas.index', [
            'fecha_inicio' => now()->subDays(40)->toDateString(),
            'fecha_fin' => now()->toDateString(),
        ]));
        $data2 = $response2->original->getData()['page']['props'];
        $this->assertEquals(400.00, $data2['kpis']['ingresos_totales']);
    }

    public function test_statistics_viewing_logs_audit_trail(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('estadisticas.index'));
        $response->assertStatus(200);

        $this->assertDatabaseHas('bitacoras', [
            'id_usuario' => $this->adminUser->id,
            'evento' => 'ver_reportes',
            'recurso' => 'estadisticas',
        ]);
    }
}
