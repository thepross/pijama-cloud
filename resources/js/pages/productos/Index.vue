<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Edit, Trash2, ShieldAlert, Check, Shirt, QrCode, AlertCircle, ShoppingCart, Star, Loader2 } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface OfferType {
    id: number;
    nombre: string;
    valor_descuento: number;
    tipo_descuento: 'porcentaje' | 'monto';
}

interface ReviewType {
    id: number;
    puntuacion: number;
    comentario: string | null;
    fecha_puntuacion: string;
    cliente: {
        id: number;
        nombre: string;
        apellido: string;
        username: string;
    };
}

interface ProductType {
    id: number;
    codigo_qr: string;
    nombre: string;
    descripcion: string | null;
    color: string | null;
    talla: string | null;
    genero: string | null;
    marca: string | null;
    material: string | null;
    precio_compra: number;
    precio_venta: number;
    stock: number;
    stock_minimo: number;
    categoria: string;
    foto: string | null;
    state: string;
    ofertas: OfferType[];
    puntuaciones?: ReviewType[];
    puntuaciones_avg_puntuacion?: number | string | null;
    puntuaciones_count?: number;
}

interface PaginatedProducts {
    data: ProductType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    productos: PaginatedProducts;
    filters: { search?: string; category?: string; size?: string; gender?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isStaff = computed(() => {
    const perms = (page.props.auth as any)?.permissions || [];
    return perms.includes('productos.crear') || perms.includes('productos.editar') || perms.includes('productos.eliminar');
});

const search = ref(props.filters.search || '');
const category = ref(props.filters.category || '');
const size = ref(props.filters.size || '');
const gender = ref(props.filters.gender || '');

const productToDelete = ref<ProductType | null>(null);
const qrProduct = ref<ProductType | null>(null);

const selectedProduct = ref<ProductType | null>(null);
const showDetailsModal = ref(false);

const ratingForm = useForm({
    id_producto: 0,
    puntuacion: 5,
    comentario: '',
});

const openProductDetails = (product: ProductType) => {
    selectedProduct.value = product;
    showDetailsModal.value = true;
    ratingForm.id_producto = product.id;
    ratingForm.puntuacion = 5;
    ratingForm.comentario = '';
};

const submitRating = () => {
    ratingForm.post(route('puntuaciones.store'), {
        preserveScroll: true,
        onSuccess: () => {
            ratingForm.reset('comentario');
            // Refresh detail modal view with the newly loaded review from paginated props
            const updatedProduct = props.productos.data.find(p => p.id === selectedProduct.value?.id);
            if (updatedProduct) {
                selectedProduct.value = updatedProduct;
            }
        },
    });
};

const deleteReview = (id: number) => {
    if (confirm('¿Estás seguro de que deseas eliminar esta valoración?')) {
        router.delete(route('puntuaciones.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                const updatedProduct = props.productos.data.find(p => p.id === selectedProduct.value?.id);
                if (updatedProduct) {
                    selectedProduct.value = updatedProduct;
                }
            },
        });
    }
};

// Apply filters
watch([search, category, size, gender], ([newSearch, newCat, newSize, newGen]) => {
    router.get(
        route('productos.index'),
        { search: newSearch, category: newCat, size: newSize, gender: newGen },
        { preserveState: true, replace: true }
    );
});

const confirmDelete = (product: ProductType) => {
    productToDelete.value = product;
};

const deleteProduct = () => {
    if (productToDelete.value) {
        router.delete(route('productos.destroy', productToDelete.value.id), {
            onSuccess: () => {
                productToDelete.value = null;
            },
        });
    }
};

// Calculate final price based on active offers
const calculatePrice = (product: ProductType) => {
    const basePrice = Number(product.precio_venta);
    if (product.ofertas.length === 0) {
        return { hasDiscount: false, finalPrice: basePrice };
    }
    const offer = product.ofertas[0];
    let discount = 0;
    if (offer.tipo_descuento === 'porcentaje') {
        discount = basePrice * (Number(offer.valor_descuento) / 100);
    } else {
        discount = Number(offer.valor_descuento);
    }
    const finalPrice = Math.max(0, basePrice - discount);
    return {
        hasDiscount: true,
        discountName: offer.nombre,
        originalPrice: basePrice,
        finalPrice: finalPrice,
    };
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Productos', href: '/productos' }]">
        <Head title="Productos Textiles" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Catálogo de Productos
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ isStaff ? 'Administra el inventario, detalles de compra y códigos de barra de las prendas textiles.' : 'Explora nuestra amplia colección de pijamas y ropa de casa premium.' }}
                    </p>
                </div>
                <div v-if="$page.props.auth.permissions.includes('productos.crear')">
                    <Link :href="route('productos.create')">
                        <Button class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform">
                            <Plus class="h-4 w-4" />
                            Registrar Producto
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Flash alerts -->
            <div v-if="props.flash?.success" class="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <Check class="h-4 w-4" />
                {{ props.flash.success }}
            </div>
            <div v-if="props.flash?.error" class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <ShieldAlert class="h-4 w-4" />
                {{ props.flash.error }}
            </div>

            <!-- Search and filters -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                <div class="relative md:col-span-2">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por nombre, marca o QR..."
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>

                <div>
                    <select
                        v-model="category"
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        <option value="">Todas las Categorías</option>
                        <option value="Niños">Niños</option>
                        <option value="Jóvenes">Jóvenes</option>
                        <option value="Adultos">Adultos</option>
                    </select>
                </div>

                <div>
                    <select
                        v-model="size"
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        <option value="">Todas las Tallas</option>
                        <option value="XS">XS</option>
                        <option value="S">S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                        <option value="8">Talla 8 (Niños)</option>
                        <option value="10">Talla 10 (Niños)</option>
                    </select>
                </div>

                <div>
                    <select
                        v-model="gender"
                        class="h-10 w-full rounded-xl border border-border bg-card px-3 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        <option value="">Todos los Géneros</option>
                        <option value="Unisex">Unisex</option>
                        <option value="Mujer">Mujer</option>
                        <option value="Hombre">Hombre</option>
                    </select>
                </div>
            </div>

            <!-- DUAL VIEW LOGIC -->

            <!-- View 1: Customer Grid Catalog -->
            <div v-if="!isStaff" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <div v-if="props.productos.data.length === 0" class="col-span-full py-12 text-center text-muted-foreground">
                    No se encontraron prendas textiles que coincidan con los filtros seleccionados.
                </div>

                <div
                    v-for="prod in props.productos.data"
                    :key="prod.id"
                    @click="openProductDetails(prod)"
                    class="group flex flex-col rounded-2xl border border-border bg-card overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1 cursor-pointer"
                >
                    <!-- Product image -->
                    <div class="relative aspect-square w-full bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center overflow-hidden">
                        <img
                            v-if="prod.foto"
                            :src="prod.foto"
                            :alt="prod.nombre"
                            class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500"
                        />
                        <Shirt v-else class="h-20 w-20 text-neutral-300 dark:text-neutral-700" />

                        <!-- Active offer tag -->
                        <div
                            v-if="calculatePrice(prod).hasDiscount"
                            class="absolute top-3 left-3 bg-red-500 text-white text-[10px] font-bold uppercase tracking-wider px-2 py-1 rounded-md shadow-sm"
                        >
                            ¡Oferta!
                        </div>

                        <!-- Float Edit/Delete Buttons for authorized users -->
                        <div 
                            v-if="$page.props.auth.permissions.includes('productos.editar') || $page.props.auth.permissions.includes('productos.eliminar')"
                            class="absolute top-3 right-3 flex items-center gap-1.5 z-20"
                            @click.stop
                        >
                            <Link 
                                v-if="$page.props.auth.permissions.includes('productos.editar')" 
                                :href="route('productos.edit', prod.id)"
                            >
                                <Button 
                                    size="sm" 
                                    variant="secondary" 
                                    class="h-8 w-8 p-0 rounded-xl shadow-md border border-border/50 bg-card hover:bg-muted text-foreground"
                                    title="Editar producto"
                                >
                                    <Edit class="h-4 w-4" />
                                </Button>
                            </Link>
                            <Button 
                                v-if="$page.props.auth.permissions.includes('productos.eliminar')" 
                                size="sm" 
                                variant="destructive" 
                                @click="confirmDelete(prod)"
                                class="h-8 w-8 p-0 rounded-xl shadow-md border border-red-500/20 hover:bg-red-600 text-white"
                                title="Eliminar producto"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="p-4 flex-1 flex flex-col">
                        <span class="text-[10px] uppercase font-bold text-muted-foreground tracking-wider mb-1">
                            {{ prod.categoria }} · {{ prod.genero }}
                        </span>
                        <h3 class="font-bold text-base text-foreground leading-snug mb-1 group-hover:text-primary transition-colors">
                            {{ prod.nombre }}
                        </h3>

                        <!-- Rating stars -->
                        <div class="flex items-center gap-0.5 mb-2">
                            <Star 
                                v-for="i in 5" 
                                :key="i" 
                                class="h-3.5 w-3.5"
                                :class="i <= Math.round(Number(prod.puntuaciones_avg_puntuacion || 0)) 
                                    ? 'fill-amber-400 text-amber-400' 
                                    : 'text-neutral-300 dark:text-neutral-700'"
                            />
                            <span class="text-xs text-muted-foreground ml-1.5">
                                ({{ prod.puntuaciones_avg_puntuacion ? Number(prod.puntuaciones_avg_puntuacion).toFixed(1) : '0' }})
                            </span>
                        </div>

                        <p class="text-xs text-muted-foreground line-clamp-2 leading-relaxed mb-4 flex-1">
                            {{ prod.descripcion || 'Pijama de diseño exclusivo suave y transpirable.' }}
                        </p>

                        <!-- Attributes badges -->
                        <div class="flex flex-wrap gap-1.5 mb-4">
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ prod.talla }}
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ prod.material }}
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300">
                                {{ prod.color }}
                            </span>
                        </div>

                        <!-- Price & buy -->
                        <div class="flex items-center justify-between mt-auto pt-3 border-t border-border/50">
                            <div>
                                <template v-if="calculatePrice(prod).hasDiscount">
                                    <span class="text-xs text-muted-foreground line-through mr-1.5">
                                        Bs. {{ calculatePrice(prod).originalPrice }}
                                    </span>
                                    <span class="text-lg font-extrabold text-foreground">
                                        Bs. {{ calculatePrice(prod).finalPrice }}
                                    </span>
                                </template>
                                <span v-else class="text-lg font-extrabold text-foreground">
                                    Bs. {{ prod.precio_venta }}
                                </span>
                            </div>
                            <Button size="sm" @click.stop class="flex items-center gap-1 rounded-xl">
                                <ShoppingCart class="h-4 w-4" />
                                Comprar
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View 2: Staff Management Table -->
            <div v-else class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4 w-12 text-center">QR</th>
                                <th class="p-4">Prenda / Código</th>
                                <th class="p-4">Categoría / Marca</th>
                                <th class="p-4">Atributos</th>
                                <th class="p-4">Costo (Compra)</th>
                                <th class="p-4">Venta al Público</th>
                                <th class="p-4">Stock</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.productos.data.length === 0">
                                <td colspan="8" class="p-8 text-center text-muted-foreground">
                                    No se encontraron productos textiles en el inventario.
                                </td>
                            </tr>
                            <tr v-for="prod in props.productos.data" :key="prod.id" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4 text-center">
                                    <Button
                                        @click="qrProduct = prod"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 hover:bg-primary/10 hover:text-primary rounded-lg"
                                        title="Ver código QR"
                                    >
                                        <QrCode class="h-4 w-4" />
                                    </Button>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center overflow-hidden border border-border">
                                            <img v-if="prod.foto" :src="prod.foto" :alt="prod.nombre" class="object-cover h-full w-full" />
                                            <Shirt v-else class="h-5 w-5 text-neutral-400" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-foreground leading-snug">{{ prod.nombre }}</span>
                                            <span class="text-xs font-mono text-muted-foreground">{{ prod.codigo_qr }}</span>
                                            <div 
                                                v-if="prod.puntuaciones_count && prod.puntuaciones_count > 0"
                                                @click="openProductDetails(prod)"
                                                class="flex items-center gap-1 mt-1 text-xs text-amber-500 hover:text-amber-600 cursor-pointer font-medium"
                                                title="Ver comentarios del producto"
                                            >
                                                <Star class="h-3 w-3 fill-amber-400 text-amber-400" />
                                                <span>{{ Number(prod.puntuaciones_avg_puntuacion).toFixed(1) }} ({{ prod.puntuaciones_count }})</span>
                                            </div>
                                            <span v-else class="text-[10px] text-muted-foreground mt-0.5">Sin valoraciones</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="text-foreground font-medium">{{ prod.categoria }}</span>
                                        <span class="text-xs text-muted-foreground">{{ prod.marca || '-' }}</span>
                                    </div>
                                </td>
                                <td class="p-4 text-xs text-muted-foreground">
                                    <div class="flex flex-col gap-0.5">
                                        <span>Talla: {{ prod.talla || '-' }} · Género: {{ prod.genero || '-' }}</span>
                                        <span>Material: {{ prod.material || '-' }} · Color: {{ prod.color || '-' }}</span>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-muted-foreground">Bs. {{ prod.precio_compra }}</td>
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="font-semibold font-mono text-foreground">Bs. {{ prod.precio_venta }}</span>
                                        <span v-if="calculatePrice(prod).hasDiscount" class="text-[10px] text-red-500 font-bold">
                                            Desc: Bs. {{ calculatePrice(prod).finalPrice }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <!-- Stock warning logic -->
                                        <span
                                            :class="[
                                                'font-mono font-bold text-sm px-2 py-0.5 rounded-md',
                                                prod.stock <= prod.stock_minimo
                                                    ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400'
                                                    : 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300'
                                            ]"
                                        >
                                            {{ prod.stock }}
                                        </span>
                                        <AlertCircle
                                            v-if="prod.stock <= prod.stock_minimo"
                                            class="h-4 w-4 text-red-500"
                                            title="¡Stock bajo el mínimo establecido!"
                                        />
                                    </div>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link v-if="$page.props.auth.permissions.includes('productos.editar')" :href="route('productos.edit', prod.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg" title="Editar producto">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            v-if="$page.props.auth.permissions.includes('productos.eliminar')"
                                            @click="confirmDelete(prod)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar producto"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div v-if="props.productos.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.productos.current_page }} de {{ props.productos.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.productos.prev_page_url" :href="props.productos.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.productos.next_page_url" :href="props.productos.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Preview Dialog -->
        <Dialog :open="!!qrProduct" @update:open="(val) => !val && (qrProduct = null)">
            <DialogContent class="sm:max-w-xs flex flex-col items-center justify-center p-6 text-center">
                <DialogHeader>
                    <DialogTitle class="text-foreground">Código QR del Producto</DialogTitle>
                    <DialogDescription class="text-xs">
                        {{ qrProduct?.nombre }} ({{ qrProduct?.codigo_qr }})
                    </DialogDescription>
                </DialogHeader>
                <div class="my-4 bg-white p-3 rounded-xl border border-border shadow-inner">
                    <img
                        v-if="qrProduct"
                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrProduct.codigo_qr)}`"
                        alt="Código QR del producto"
                        class="h-48 w-48"
                    />
                </div>
                <Button @click="qrProduct = null" class="w-full rounded-xl">Cerrar</Button>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!productToDelete" @update:open="(val) => !val && (productToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Producto del Catálogo?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>eliminación lógica</strong> del producto <strong>"{{ productToDelete?.nombre }}"</strong>. El producto ya no se mostrará a los clientes ni podrá ser comprado.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="productToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteProduct" class="rounded-xl">Eliminar Lógicamente</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Product Details & Review Modal (CU3) -->
        <Dialog :open="showDetailsModal" @update:open="(val) => !val && (showDetailsModal = false)">
            <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto p-6">
                <DialogHeader v-if="selectedProduct">
                    <DialogTitle class="text-xl font-bold flex items-center justify-between">
                        <span>{{ selectedProduct.nombre }}</span>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-primary/10 text-primary uppercase shrink-0">
                            {{ selectedProduct.categoria }}
                        </span>
                    </DialogTitle>
                    <DialogDescription>
                        Detalles completos y puntuaciones de los clientes
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedProduct" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                    <!-- Column 1: Details & Info -->
                    <div class="space-y-4">
                        <div class="aspect-square bg-muted rounded-xl flex items-center justify-center overflow-hidden border border-border">
                            <img
                                v-if="selectedProduct.foto"
                                :src="selectedProduct.foto"
                                :alt="selectedProduct.nombre"
                                class="object-cover w-full h-full"
                            />
                            <Shirt v-else class="h-24 w-24 text-neutral-300 dark:text-neutral-700" />
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-xs bg-muted/40 p-3 rounded-xl border border-border/50">
                            <div>
                                <span class="text-muted-foreground block">Marca</span>
                                <strong class="text-foreground">{{ selectedProduct.marca || 'N/A' }}</strong>
                            </div>
                            <div>
                                <span class="text-muted-foreground block">Material</span>
                                <strong class="text-foreground">{{ selectedProduct.material || 'N/A' }}</strong>
                            </div>
                            <div>
                                <span class="text-muted-foreground block">Talla</span>
                                <strong class="text-foreground">{{ selectedProduct.talla || 'N/A' }}</strong>
                            </div>
                            <div>
                                <span class="text-muted-foreground block">Color</span>
                                <strong class="text-foreground">{{ selectedProduct.color || 'N/A' }}</strong>
                            </div>
                        </div>

                        <div class="text-sm leading-relaxed text-muted-foreground">
                            {{ selectedProduct.descripcion || 'Pijama de diseño exclusivo, suave y transpirable ideal para un descanso reparador.' }}
                        </div>
                    </div>

                    <!-- Column 2: Ratings & Comments -->
                    <div class="flex flex-col space-y-4">
                        <div class="border-b border-border/60 pb-3">
                            <h4 class="font-bold text-sm text-foreground mb-1.5">Puntuación Promedio</h4>
                            <div class="flex items-center gap-2">
                                <div class="flex gap-0.5">
                                    <Star 
                                        v-for="i in 5" 
                                        :key="i" 
                                        class="h-5 w-5"
                                        :class="i <= Math.round(Number(selectedProduct.puntuaciones_avg_puntuacion || 0)) 
                                            ? 'fill-amber-400 text-amber-400' 
                                            : 'text-neutral-300 dark:text-neutral-700'"
                                    />
                                </div>
                                <span class="text-lg font-black">
                                    {{ selectedProduct.puntuaciones_avg_puntuacion ? Number(selectedProduct.puntuaciones_avg_puntuacion).toFixed(1) : '0.0' }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    ({{ selectedProduct.puntuaciones_count || 0 }} {{ selectedProduct.puntuaciones_count === 1 ? 'valoración' : 'valoraciones' }})
                                </span>
                            </div>
                        </div>

                        <!-- Scrollable Reviews log -->
                        <div class="flex-1 max-h-48 overflow-y-auto space-y-3 pr-1">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-wide block">
                                Comentarios de clientes
                            </span>
                            <div v-if="!selectedProduct.puntuaciones || selectedProduct.puntuaciones.length === 0" class="text-xs text-muted-foreground py-4 text-center">
                                Este producto aún no tiene valoraciones.
                            </div>
                            <div 
                                v-else 
                                v-for="review in selectedProduct.puntuaciones" 
                                :key="review.id"
                                class="p-3 border border-border/60 rounded-xl bg-muted/20 space-y-1.5 text-xs"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-foreground">
                                        {{ review.cliente.nombre }} {{ review.cliente.apellido }} (@{{ review.cliente.username }})
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-muted-foreground">
                                            {{ review.fecha_puntuacion }}
                                        </span>
                                        <button 
                                            v-if="isStaff"
                                            @click="deleteReview(review.id)"
                                            class="text-destructive hover:bg-destructive/10 p-1 rounded transition-colors"
                                            title="Eliminar valoración (Moderar)"
                                        >
                                            <Trash2 class="h-3 w-3" />
                                        </button>
                                    </div>
                                </div>
                                <div class="flex gap-0.5">
                                    <Star 
                                        v-for="i in 5" 
                                        :key="i" 
                                        class="h-3 w-3"
                                        :class="i <= review.puntuacion ? 'fill-amber-400 text-amber-400' : 'text-neutral-300 dark:text-neutral-700'"
                                    />
                                </div>
                                <p v-if="review.comentario" class="text-muted-foreground leading-relaxed italic">
                                    "{{ review.comentario }}"
                                </p>
                            </div>
                        </div>

                        <!-- Write Review Form (Only for Cliente role) -->
                        <div v-if="userRole === 'Cliente'" class="border-t border-border/80 pt-4 space-y-3">
                            <h4 class="font-bold text-sm text-foreground">Escribir una valoración</h4>
                            <form @submit.prevent="submitRating" class="space-y-3">
                                <!-- Star Selector -->
                                <div class="space-y-1">
                                    <label class="text-xs text-muted-foreground">Calificación:</label>
                                    <div class="flex gap-1.5 items-center">
                                        <button 
                                            v-for="star in 5" 
                                            :key="star" 
                                            type="button"
                                            @click="ratingForm.puntuacion = star"
                                            class="hover:scale-110 transition-transform focus:outline-none"
                                        >
                                            <Star 
                                                class="h-6 w-6 transition-colors"
                                                :class="star <= ratingForm.puntuacion ? 'fill-amber-400 text-amber-400' : 'text-neutral-300 dark:text-neutral-700'"
                                            />
                                        </button>
                                        <span class="text-xs font-bold text-foreground ml-2">
                                            {{ ratingForm.puntuacion }} {{ ratingForm.puntuacion === 1 ? 'Estrella' : 'Estrellas' }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Comment textarea -->
                                <div class="space-y-1">
                                    <label for="comentario" class="text-xs text-muted-foreground">Tu comentario (opcional):</label>
                                    <textarea
                                        id="comentario"
                                        rows="2"
                                        v-model="ratingForm.comentario"
                                        placeholder="Cuéntanos qué te pareció el tejido, la talla o la comodidad..."
                                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    ></textarea>
                                </div>

                                <Button type="submit" size="sm" class="w-full rounded-xl" :disabled="ratingForm.processing">
                                    <Loader2 v-if="ratingForm.processing" class="h-3.5 w-3.5 animate-spin mr-1.5" />
                                    Enviar valoración
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
