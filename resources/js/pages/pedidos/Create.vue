<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, ShoppingBag, Plus, Minus, Trash2, Shirt } from 'lucide-vue-next';

interface OfferType {
    id: number;
    nombre: string;
    valor_descuento: number | string;
    tipo_descuento: 'porcentaje' | 'monto';
}

interface ProductType {
    id: number;
    codigo_qr: string;
    nombre: string;
    descripcion: string | null;
    color: string | null;
    talla: string | null;
    marca: string | null;
    precio_venta: number | string;
    stock: number;
    categoria: string;
    foto: string | null;
    ofertas: OfferType[];
}

const props = defineProps<{
    productos: ProductType[];
}>();


interface CartItem {
    id_producto: number;
    producto: ProductType;
    cantidad: number;
}

const cart = ref<CartItem[]>([]);
const search = ref('');
const categoryFilter = ref('');

const form = useForm({
    observacion: '',
    items: [] as { id_producto: number; cantidad: number }[],
});


const filteredProducts = computed(() => {
    return props.productos.filter(prod => {
        const matchesSearch = prod.nombre.toLowerCase().includes(search.value.toLowerCase()) ||
                              prod.codigo_qr.toLowerCase().includes(search.value.toLowerCase()) ||
                              (prod.marca && prod.marca.toLowerCase().includes(search.value.toLowerCase()));
        
        const matchesCategory = categoryFilter.value === '' || prod.categoria === categoryFilter.value;
        
        return matchesSearch && matchesCategory;
    });
});


const categories = computed(() => {
    const cats = props.productos.map(p => p.categoria);
    return [...new Set(cats)];
});


const getProductPriceDetails = (product: ProductType) => {
    const basePrice = Number(product.precio_venta);
    if (!product.ofertas || product.ofertas.length === 0) {
        return { hasDiscount: false, basePrice, discount: 0, finalPrice: basePrice };
    }
    const offer = product.ofertas[0];
    let discount = 0;
    if (offer.tipo_descuento === 'porcentaje') {
        discount = basePrice * (Number(offer.valor_descuento) / 100);
    } else {
        discount = Number(offer.valor_descuento);
    }
    return {
        hasDiscount: true,
        basePrice,
        discount,
        finalPrice: Math.max(0, basePrice - discount),
        offerName: offer.nombre
    };
};

const addToCart = (producto: ProductType) => {
    const existing = cart.value.find(item => item.id_producto === producto.id);
    if (existing) {
        if (existing.cantidad < producto.stock) {
            existing.cantidad++;
        }
    } else {
        cart.value.push({
            id_producto: producto.id,
            producto,
            cantidad: 1
        });
    }
};

const updateQty = (id: number, delta: number) => {
    const item = cart.value.find(i => i.id_producto === id);
    if (item) {
        const newQty = item.cantidad + delta;
        if (newQty <= 0) {
            cart.value = cart.value.filter(i => i.id_producto !== id);
        } else if (newQty <= item.producto.stock) {
            item.cantidad = newQty;
        }
    }
};

const removeFromCart = (id: number) => {
    cart.value = cart.value.filter(i => i.id_producto !== id);
};


const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => {
        const priceDetails = getProductPriceDetails(item.producto);
        return sum + (item.cantidad * priceDetails.finalPrice);
    }, 0);
});

const submit = () => {
    form.items = cart.value.map(item => ({
        id_producto: item.id_producto,
        cantidad: item.cantidad
    }));
    
    form.post(route('pedidos.store'), {
        onError: () => {
            
        }
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Pedidos', href: '/pedidos' },
        { title: 'Realizar Pedido', href: '/pedidos/create' }
    ]">
        <Head title="Realizar Pedido" />

        <div class="max-w-7xl mx-auto space-y-6">
            
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Realizar Nuevo Pedido
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Selecciona las prendas y cantidades para armar tu pedido de pijamas.
                    </p>
                </div>
                <div>
                    <Link :href="route('pedidos.index')">
                        <Button variant="ghost" class="flex items-center gap-1.5 rounded-xl">
                            <ArrowLeft class="h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                </div>
            </div>

            
            <div v-if="form.errors.items" class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive text-sm font-semibold flex items-center gap-2">
                <AlertCircle class="h-5 w-5" />
                {{ form.errors.items }}
            </div>
            <div v-if="form.errors.error" class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive text-sm font-semibold flex items-center gap-2">
                <AlertCircle class="h-5 w-5" />
                {{ form.errors.error }}
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 space-y-4">
                    
                    <div class="flex flex-col sm:flex-row gap-4 justify-between bg-card p-4 rounded-xl border border-border shadow-sm">
                        <div class="relative flex-1">
                            <Input
                                v-model="search"
                                type="text"
                                placeholder="Buscar prendas por nombre o código..."
                                class="rounded-xl pl-3"
                            />
                        </div>
                        <select
                            v-model="categoryFilter"
                            class="flex h-10 w-full sm:w-48 rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="">Todas las categorías</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>

                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-if="filteredProducts.length === 0" class="col-span-full p-12 text-center text-muted-foreground border border-dashed rounded-xl bg-card">
                            No se encontraron prendas con los filtros ingresados.
                        </div>
                        
                        <div
                            v-for="prod in filteredProducts"
                            :key="prod.id"
                            class="p-4 rounded-xl border border-border bg-card shadow-sm flex gap-4 hover:shadow-md transition-shadow"
                        >
                            
                            <div class="h-20 w-20 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center overflow-hidden border border-border shrink-0">
                                <img v-if="prod.foto" :src="prod.foto" :alt="prod.nombre" class="object-cover h-full w-full" />
                                <Shirt v-else class="h-6 w-6 text-neutral-400" />
                            </div>

                            
                            <div class="flex-1 flex flex-col justify-between">
                                <div class="space-y-0.5">
                                    <div class="flex justify-between items-baseline gap-1">
                                        <span class="text-xs font-mono text-muted-foreground">{{ prod.codigo_qr }}</span>
                                        <span class="text-[10px] uppercase font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-full shrink-0">
                                            {{ prod.categoria }}
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-sm text-foreground leading-snug">{{ prod.nombre }}</h4>
                                    <span class="text-xs text-muted-foreground">Talla: {{ prod.talla || '-' }} · Color: {{ prod.color || '-' }}</span>
                                </div>
                                <div class="pt-2 border-t border-border/60 flex items-center justify-between">
                                    <div class="flex flex-col">
                                        
                                        <div v-if="getProductPriceDetails(prod).hasDiscount" class="flex flex-col">
                                            <span class="font-mono text-sm font-black text-foreground">Bs. {{ getProductPriceDetails(prod).finalPrice.toFixed(2) }}</span>
                                            <span class="text-[10px] text-muted-foreground line-through font-mono leading-none">Bs. {{ Number(prod.precio_venta).toFixed(2) }}</span>
                                        </div>
                                        <span v-else class="font-mono text-sm font-black text-foreground">Bs. {{ Number(prod.precio_venta).toFixed(2) }}</span>
                                        <span class="text-[10px] font-semibold text-muted-foreground">Stock: {{ prod.stock }}</span>
                                    </div>
                                    
                                    <Button
                                        @click="addToCart(prod)"
                                        size="sm"
                                        variant="outline"
                                        class="rounded-lg h-8 text-xs font-semibold"
                                        :disabled="prod.stock <= 0"
                                    >
                                        {{ prod.stock > 0 ? 'Agregar' : 'Agotado' }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                
                <div class="space-y-6">
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6 sticky top-6">
                        <h3 class="text-md font-bold text-foreground border-b border-border pb-2 flex items-center justify-between">
                            <span>Resumen de Compra</span>
                            <span class="text-xs bg-primary/10 text-primary px-2.5 py-0.5 rounded-full font-bold font-mono">
                                {{ cart.length }} {{ cart.length === 1 ? 'item' : 'items' }}
                            </span>
                        </h3>

                        
                        <div class="space-y-4 max-h-80 overflow-y-auto pr-1">
                            <div v-if="cart.length === 0" class="text-center py-16 text-xs text-muted-foreground italic">
                                Tu carrito está vacío. Agrega pijamas del catálogo.
                            </div>
                            
                            <div
                                v-for="item in cart"
                                :key="item.id_producto"
                                class="flex items-start gap-3 text-xs border-b border-border/50 pb-3"
                            >
                                <div class="flex-1 space-y-1">
                                    <div class="font-semibold text-foreground leading-snug">{{ item.producto.nombre }}</div>
                                    <div class="text-[10px] text-muted-foreground">
                                        Unit: Bs. {{ getProductPriceDetails(item.producto).finalPrice.toFixed(2) }}
                                        <span v-if="getProductPriceDetails(item.producto).hasDiscount" class="text-emerald-500 font-bold ml-1">
                                            (Oferta)
                                        </span>
                                    </div>
                                    
                                    
                                    <div class="flex items-center gap-2 mt-1">
                                        <button
                                            type="button"
                                            @click="updateQty(item.id_producto, -1)"
                                            class="p-0.5 rounded border border-border bg-muted hover:bg-accent"
                                        >
                                            <Minus class="h-3 w-3" />
                                        </button>
                                        <span class="font-mono font-bold">{{ item.cantidad }}</span>
                                        <button
                                            type="button"
                                            @click="updateQty(item.id_producto, 1)"
                                            class="p-0.5 rounded border border-border bg-muted hover:bg-accent"
                                            :disabled="item.cantidad >= item.producto.stock"
                                        >
                                            <Plus class="h-3 w-3" />
                                        </button>
                                        <span class="text-[10px] text-muted-foreground font-mono">/ {{ item.producto.stock }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end gap-1">
                                    <span class="font-mono font-bold">
                                        Bs. {{ (item.cantidad * getProductPriceDetails(item.producto).finalPrice).toFixed(2) }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="removeFromCart(item.id_producto)"
                                        class="text-destructive hover:bg-destructive/10 p-0.5 rounded"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        
                        <div class="pt-4 border-t border-border space-y-4">
                            <div class="flex justify-between items-baseline">
                                <span class="text-sm font-bold text-foreground">Total del Pedido:</span>
                                <span class="font-mono text-xl font-black text-primary">Bs. {{ cartTotal.toFixed(2) }}</span>
                            </div>

                            
                            <div class="grid gap-1.5">
                                <Label for="observacion" class="text-xs">Observaciones / Indicaciones especiales</Label>
                                <textarea
                                    id="observacion"
                                    v-model="form.observacion"
                                    placeholder="ej. Dejar en portería, envolver para regalo..."
                                    rows="2"
                                    class="flex w-full rounded-xl border border-border bg-card px-3 py-1.5 text-xs ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                ></textarea>
                                <InputError :message="form.errors.observacion" />
                            </div>

                            <Button
                                @click="submit"
                                class="w-full flex items-center justify-center gap-1.5 rounded-xl shadow-sm"
                                :disabled="form.processing || cart.length === 0"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Save v-else class="h-4 w-4" />
                                Confirmar y Realizar Pedido
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
