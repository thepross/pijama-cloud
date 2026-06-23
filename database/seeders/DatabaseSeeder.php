<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Oferta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Roles
        $adminRole = Role::create([
            'nombre' => 'Administrador',
            'descripcion' => 'Administrador del sistema con acceso total',
            'state' => 'activo',
        ]);

        $clienteRole = Role::create([
            'nombre' => 'Cliente',
            'descripcion' => 'Cliente del negocio que realiza compras y califica productos',
            'state' => 'activo',
        ]);

        $distribuidorRole = Role::create([
            'nombre' => 'Distribuidor',
            'descripcion' => 'Distribuidor encargado de realizar los envíos',
            'state' => 'activo',
        ]);

        $vendedorRole = Role::create([
            'nombre' => 'Vendedor',
            'descripcion' => 'Vendedor encargado de gestionar pedidos, reclamos y productos',
            'state' => 'activo',
        ]);

        // 2. Create Permissions (Menus)
        $permDashboard = Permiso::create([
            'nombre' => 'Dashboard',
            'descripcion' => 'Acceso al panel de control principal',
            'ruta' => 'dashboard',
            'icono' => 'LayoutDashboard',
            'orden' => 1,
        ]);

        $permProductos = Permiso::create([
            'nombre' => 'Productos',
            'descripcion' => 'Catálogo y gestión de productos',
            'ruta' => 'productos',
            'icono' => 'Shirt',
            'orden' => 2,
        ]);

        $permPedidos = Permiso::create([
            'nombre' => 'Pedidos',
            'descripcion' => 'Visualización y gestión de pedidos',
            'ruta' => 'pedidos',
            'icono' => 'ShoppingBag',
            'orden' => 3,
        ]);

        $permEnvios = Permiso::create([
            'nombre' => 'Envíos',
            'descripcion' => 'Seguimiento y asignación de despachos',
            'ruta' => 'envios',
            'icono' => 'Truck',
            'orden' => 4,
        ]);

        $permPagos = Permiso::create([
            'nombre' => 'Pagos',
            'descripcion' => 'Registro de métodos de pago y cuotas',
            'ruta' => 'pagos',
            'icono' => 'CreditCard',
            'orden' => 5,
        ]);

        $permReclamos = Permiso::create([
            'nombre' => 'Reclamos',
            'descripcion' => 'Formulación y atención de quejas',
            'ruta' => 'reclamos',
            'icono' => 'AlertTriangle',
            'orden' => 6,
        ]);

        $permEstadisticas = Permiso::create([
            'nombre' => 'Estadísticas',
            'descripcion' => 'Reportes y estadísticas del negocio',
            'ruta' => 'estadisticas',
            'icono' => 'BarChart3',
            'orden' => 7,
        ]);

        $permBitacoras = Permiso::create([
            'nombre' => 'Bitácora',
            'descripcion' => 'Registro de eventos y accesos del sistema',
            'ruta' => 'bitacoras',
            'icono' => 'History',
            'orden' => 8,
        ]);

        $permRoles = Permiso::create([
            'nombre' => 'Roles',
            'descripcion' => 'Gestión de roles y permisos de acceso',
            'ruta' => 'roles',
            'icono' => 'Shield',
            'orden' => 9,
        ]);

        $permUsuarios = Permiso::create([
            'nombre' => 'Usuarios',
            'descripcion' => 'Gestión de usuarios y cuentas de acceso',
            'ruta' => 'usuarios',
            'icono' => 'Users',
            'orden' => 10,
        ]);

        $permOfertas = Permiso::create([
            'nombre' => 'Ofertas',
            'descripcion' => 'Registro y gestión de ofertas y promociones',
            'ruta' => 'ofertas',
            'icono' => 'Tag',
            'orden' => 11,
        ]);

        // 3. Setup Access Matrix (roles_permissions)
        // Admin gets all
        $adminRole->permissions()->attach([
            $permDashboard->id, $permProductos->id, $permPedidos->id, $permEnvios->id,
            $permPagos->id, $permReclamos->id, $permEstadisticas->id, $permBitacoras->id,
            $permRoles->id, $permUsuarios->id, $permOfertas->id
        ]);

        // Cliente gets Dashboard, Catalog, Pedidos (own), Pagos (own), Reclamos (own)
        $clienteRole->permissions()->attach([
            $permDashboard->id, $permProductos->id, $permPedidos->id, $permPagos->id, $permReclamos->id
        ]);

        // Distribuidor gets Dashboard, Envios
        $distribuidorRole->permissions()->attach([
            $permDashboard->id, $permEnvios->id
        ]);

        // Vendedor gets Dashboard, Productos, Pedidos, Envios, Pagos, Reclamos, Estadisticas
        $vendedorRole->permissions()->attach([
            $permDashboard->id, $permProductos->id, $permPedidos->id, $permEnvios->id,
            $permPagos->id, $permReclamos->id, $permEstadisticas->id
        ]);

        // 4. Create Users for each Role
        User::create([
            'id_rol' => $adminRole->id,
            'username' => 'admin',
            'password' => Hash::make('password'),
            'nombre' => 'Administrador',
            'apellido' => 'General',
            'ci' => '1000001',
            'email' => 'admin@pijama.com',
            'telefono' => '70000001',
            'direccion' => 'Av. Principal 123',
            'state' => 'activo',
        ]);

        User::create([
            'id_rol' => $clienteRole->id,
            'username' => 'cliente',
            'password' => Hash::make('password'),
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'ci' => '1000002',
            'email' => 'cliente@pijama.com',
            'telefono' => '70000002',
            'direccion' => 'Calle Secundaria 456',
            'state' => 'activo',
        ]);

        User::create([
            'id_rol' => $distribuidorRole->id,
            'username' => 'distribuidor',
            'password' => Hash::make('password'),
            'nombre' => 'Carlos',
            'apellido' => 'Gomez',
            'ci' => '1000003',
            'email' => 'distribuidor@pijama.com',
            'telefono' => '70000003',
            'direccion' => 'Av. Circunvalacion 789',
            'state' => 'activo',
        ]);

        User::create([
            'id_rol' => $vendedorRole->id,
            'username' => 'vendedor',
            'password' => Hash::make('password'),
            'nombre' => 'Maria',
            'apellido' => 'Lopez',
            'ci' => '1000004',
            'email' => 'vendedor@pijama.com',
            'telefono' => '70000004',
            'direccion' => 'Calle Comercial 101',
            'state' => 'activo',
        ]);

        // 5. Create Sample Products
        $p1 = Producto::create([
            'codigo_qr' => 'QR-PIJ-001',
            'nombre' => 'Pijama de Algodón Nubes',
            'descripcion' => 'Pijama de algodón 100% ultra suave con estampado de nubes.',
            'color' => 'Celeste',
            'talla' => 'M',
            'genero' => 'Unisex',
            'marca' => 'Pijama Cloud',
            'material' => 'Algodón',
            'precio_compra' => 15.00,
            'precio_venta' => 29.99,
            'stock' => 50,
            'stock_minimo' => 5,
            'categoria' => 'Adultos',
            'state' => 'activo',
        ]);

        $p2 = Producto::create([
            'codigo_qr' => 'QR-PIJ-002',
            'nombre' => 'Pijama de Seda Elegance',
            'descripcion' => 'Elegante pijama de seda italiana ideal para noches especiales.',
            'color' => 'Rojo Borgoña',
            'talla' => 'S',
            'genero' => 'Mujer',
            'marca' => 'Pijama Cloud',
            'material' => 'Seda',
            'precio_compra' => 25.00,
            'precio_venta' => 49.99,
            'stock' => 30,
            'stock_minimo' => 3,
            'categoria' => 'Jóvenes',
            'state' => 'activo',
        ]);

        $p3 = Producto::create([
            'codigo_qr' => 'QR-PIJ-003',
            'nombre' => 'Pijama Térmica Polar Dino',
            'descripcion' => 'Pijama térmica tipo polar para niños con diseño de dinosaurios divertidos.',
            'color' => 'Verde',
            'talla' => '8',
            'genero' => 'Niños',
            'marca' => 'Pijama Cloud Kids',
            'material' => 'Polar',
            'precio_compra' => 10.00,
            'precio_venta' => 19.99,
            'stock' => 100,
            'stock_minimo' => 10,
            'categoria' => 'Niños',
            'state' => 'activo',
        ]);

        // 6. Create Sample Offers
        Oferta::create([
            'id_producto' => $p1->id,
            'nombre' => 'Descuento de Lanzamiento',
            'descripcion' => '10% de descuento en el nuevo modelo Nubes',
            'valor_descuento' => 10.00,
            'tipo_descuento' => 'porcentaje',
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addMonth(),
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);

        Oferta::create([
            'id_producto' => $p3->id,
            'nombre' => 'Oferta Especial Niños',
            'descripcion' => 'Descuento fijo de $3.00 en pijamas infantiles',
            'valor_descuento' => 3.00,
            'tipo_descuento' => 'monto',
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addWeek(),
            'estado_oferta' => 'activa',
            'state' => 'activo',
        ]);
    }
}
