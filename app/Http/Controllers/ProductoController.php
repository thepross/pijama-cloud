<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ProductoController extends Controller
{
    private function authorizeProductAction(string $action): void
    {
        if (!Auth::check()) {
            abort(403, 'No autenticado.');
        }

        $user = Auth::user();
        $mapping = [
            'index'   => 'productos.ver',
            'show'    => 'productos.ver',
            'create'  => 'productos.crear',
            'store'   => 'productos.crear',
            'edit'    => 'productos.editar',
            'update'  => 'productos.editar',
            'destroy' => 'productos.eliminar',
        ];

        $perm = $mapping[$action] ?? 'productos.ver';

        if (!$user->role->hasPermission($perm)) {
            abort(403, 'No tienes permiso para realizar esta acción sobre productos.');
        }
    }

    /**
     * Display a listing of the active products.
     */
    public function index(Request $request): Response
    {
        $this->authorizeProductAction('index');

        $search = $request->input('search');
        $category = $request->input('category');
        $size = $request->input('size');
        $gender = $request->input('gender');

        $productos = Producto::query()
            ->with([
                'ofertas' => function ($query) {
                    $query->where('estado_oferta', 'activa')
                          ->where('fecha_inicio', '<=', now())
                          ->where('fecha_fin', '>=', now())
                          ->where('state', 'activo');
                },
                'puntuaciones' => function ($query) {
                    $query->where('state', 'activo')
                          ->with('cliente:id,nombre,apellido,username')
                          ->latest();
                }
            ])
            ->withAvg(['puntuaciones' => function ($query) {
                $query->where('state', 'activo');
            }], 'puntuacion')
            ->withCount(['puntuaciones' => function ($query) {
                $query->where('state', 'activo');
            }])
            ->where('state', 'activo')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'like', "%{$search}%")
                      ->orWhere('descripcion', 'like', "%{$search}%")
                      ->orWhere('codigo_qr', 'like', "%{$search}%")
                      ->orWhere('marca', 'like', "%{$search}%");
                });
            })
            ->when($category, function ($query, $category) {
                $query->where('categoria', $category);
            })
            ->when($size, function ($query, $size) {
                $query->where('talla', $size);
            })
            ->when($gender, function ($query, $gender) {
                $query->where('genero', $gender);
            })
            ->orderBy('id', 'desc')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('productos/Index', [
            'productos' => $productos,
            'filters' => $request->only(['search', 'category', 'size', 'gender']),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        $this->authorizeProductAction('create');

        return Inertia::render('productos/Create');
    }

    /**
     * Store a newly created product in database.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeProductAction('store');

        $request->validate([
            'codigo_qr' => 'nullable|string|max:255|unique:productos,codigo_qr',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'color' => 'nullable|string|max:255',
            'talla' => 'nullable|string|max:255',
            'genero' => 'nullable|string|max:255',
            'marca' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
            'precio_compra' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0|gte:precio_compra',
            'stock' => 'required|integer|min:0',
            'stock_minimo' => 'required|integer|min:0',
            'categoria' => 'required|string|max:255',
            'foto' => 'nullable|string|max:2048', // Image path or base64
        ], [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'codigo_qr.unique' => 'Este código QR ya está registrado.',
            'precio_compra.required' => 'El precio de compra es obligatorio.',
            'precio_compra.numeric' => 'El precio de compra debe ser un número.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'precio_venta.numeric' => 'El precio de venta debe ser un número.',
            'precio_venta.gte' => 'El precio de venta debe ser mayor o igual al precio de compra.',
            'stock.required' => 'El stock inicial es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock_minimo.required' => 'El stock mínimo es obligatorio.',
            'stock_minimo.integer' => 'El stock mínimo debe ser un número entero.',
            'categoria.required' => 'La categoría es obligatoria.',
        ]);

        $qrCode = $request->codigo_qr ?: 'QR-' . strtoupper(uniqid());

        $producto = Producto::create([
            'codigo_qr' => $qrCode,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'color' => $request->color,
            'talla' => $request->talla,
            'genero' => $request->genero,
            'marca' => $request->marca,
            'material' => $request->material,
            'precio_compra' => $request->precio_compra,
            'precio_venta' => $request->precio_venta,
            'stock' => $request->stock,
            'stock_minimo' => $request->stock_minimo,
            'categoria' => $request->categoria,
            'foto' => $request->foto,
            'state' => 'activo',
        ]);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'crear_producto',
            'ip' => $request->ip(),
            'recurso' => 'productos',
            'detalle' => json_encode([
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo_qr' => $producto->codigo_qr,
                'stock' => $producto->stock,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('productos.index')->with('success', 'Producto registrado exitosamente.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Producto $producto): Response
    {
        $this->authorizeProductAction('edit');

        if ($producto->state === 'inactivo') {
            abort(404, 'El producto no se encuentra activo.');
        }

        return Inertia::render('productos/Edit', [
            'producto' => $producto,
        ]);
    }

    /**
     * Update the specified product in database.
     */
    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $this->authorizeProductAction('update');

        if ($producto->state === 'inactivo') {
            abort(404, 'El producto no se encuentra activo.');
        }

        $request->validate([
            'codigo_qr' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('productos', 'codigo_qr')->ignore($producto->id),
            ],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:2000',
            'color' => 'nullable|string|max:255',
            'talla' => 'nullable|string|max:255',
            'genero' => 'nullable|string|max:255',
            'marca' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
            'precio_compra' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0|gte:precio_compra',
            'stock' => 'required|integer|min:0',
            'stock_minimo' => 'required|integer|min:0',
            'categoria' => 'required|string|max:255',
            'foto' => 'nullable|string|max:2048',
        ], [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'codigo_qr.unique' => 'Este código QR ya está registrado.',
            'precio_compra.required' => 'El precio de compra es obligatorio.',
            'precio_compra.numeric' => 'El precio de compra debe ser un número.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
            'precio_venta.numeric' => 'El precio de venta debe ser un número.',
            'precio_venta.gte' => 'El precio de venta debe ser mayor o igual al precio de compra.',
            'stock.required' => 'El stock inicial es obligatorio.',
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock_minimo.required' => 'El stock mínimo es obligatorio.',
            'stock_minimo.integer' => 'El stock mínimo debe ser un número entero.',
            'categoria.required' => 'La categoría es obligatoria.',
        ]);

        $qrCode = $request->codigo_qr ?: $producto->codigo_qr;

        $producto->update([
            'codigo_qr' => $qrCode,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'color' => $request->color,
            'talla' => $request->talla,
            'genero' => $request->genero,
            'marca' => $request->marca,
            'material' => $request->material,
            'precio_compra' => $request->precio_compra,
            'precio_venta' => $request->precio_venta,
            'stock' => $request->stock,
            'stock_minimo' => $request->stock_minimo,
            'categoria' => $request->categoria,
            'foto' => $request->foto,
        ]);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'modificar_producto',
            'ip' => $request->ip(),
            'recurso' => 'productos/' . $producto->id,
            'detalle' => json_encode([
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo_qr' => $producto->codigo_qr,
                'stock' => $producto->stock,
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('productos.index')->with('success', 'Producto actualizado exitosamente.');
    }

    /**
     * Logically delete the specified product from database.
     */
    public function destroy(Request $request, Producto $producto): RedirectResponse
    {
        $this->authorizeProductAction('destroy');

        if ($producto->state === 'inactivo') {
            return to_route('productos.index')->with('error', 'El producto ya se encuentra inactivo.');
        }

        $producto->update(['state' => 'inactivo']);

        // Audit Log
        Bitacora::create([
            'id_usuario' => Auth::id(),
            'evento' => 'eliminar_producto',
            'ip' => $request->ip(),
            'recurso' => 'productos/' . $producto->id,
            'detalle' => json_encode([
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'estado' => 'inactivo',
            ], JSON_UNESCAPED_UNICODE),
            'user_agent' => $request->userAgent(),
        ]);

        return to_route('productos.index')->with('success', 'Producto eliminado lógicamente.');
    }
}
