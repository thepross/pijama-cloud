<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Tag, Shirt } from 'lucide-vue-next';

interface ProductType {
    id: number;
    nombre: string;
    codigo_qr: string;
    precio_venta: number | string;
    foto: string | null;
}

interface OfferType {
    id: number;
    id_producto: number;
    nombre: string;
    descripcion: string | null;
    valor_descuento: number | string;
    tipo_descuento: 'porcentaje' | 'monto';
    fecha_inicio: string;
    fecha_fin: string;
    estado_oferta: 'activa' | 'inactiva';
    state: string;
}

const props = defineProps<{
    oferta: OfferType;
    productos: ProductType[];
}>();

const form = useForm({
    id_producto: props.oferta.id_producto.toString(),
    nombre: props.oferta.nombre,
    descripcion: props.oferta.descripcion || '',
    valor_descuento: props.oferta.valor_descuento.toString(),
    tipo_descuento: props.oferta.tipo_descuento,
    fecha_inicio: props.oferta.fecha_inicio,
    fecha_fin: props.oferta.fecha_fin,
    estado_oferta: props.oferta.estado_oferta,
});

// Find selected product to display its original price in the UI
const selectedProduct = computed(() => {
    if (!form.id_producto) return null;
    return props.productos.find(p => p.id === Number(form.id_producto)) || null;
});

// Calculate tentative final price in the UI
const tentativeFinalPrice = computed(() => {
    if (!selectedProduct.value) return null;
    const basePrice = Number(selectedProduct.value.precio_venta);
    const discountVal = Number(form.valor_descuento || 0);
    if (discountVal <= 0) return basePrice;

    if (form.tipo_descuento === 'porcentaje') {
        const discount = basePrice * (discountVal / 100);
        return Math.max(0, basePrice - discount);
    } else {
        return Math.max(0, basePrice - discountVal);
    }
});

// Check if fixed amount exceeds sale price
const isDiscountAmountTooHigh = computed(() => {
    if (!selectedProduct.value || form.tipo_descuento !== 'monto') return false;
    return Number(form.valor_descuento || 0) >= Number(selectedProduct.value.precio_venta);
});

const submit = () => {
    form.put(route('ofertas.update', props.oferta.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Ofertas', href: '/ofertas' },
        { title: 'Editar Oferta', href: `/ofertas/${props.oferta.id}/edit` }
    ]">
        <Head title="Editar Oferta" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <Tag class="h-8 w-8 text-primary" />
                        Editar Oferta: "{{ props.oferta.nombre }}"
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Modifica los parámetros de la oferta de descuento o su periodo de vigencia.
                    </p>
                </div>
                <div>
                    <Link :href="route('ofertas.index')">
                        <Button variant="ghost" class="flex items-center gap-1.5 rounded-xl">
                            <ArrowLeft class="h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Form inputs card -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic details card -->
                        <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                            <h2 class="text-lg font-bold text-foreground">Detalles de la Oferta</h2>

                            <div class="grid gap-2">
                                <Label for="id_producto">Producto Asociado</Label>
                                <select
                                    id="id_producto"
                                    required
                                    v-model="form.id_producto"
                                    class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="" disabled>Selecciona un producto textil...</option>
                                    <option v-for="prod in props.productos" :key="prod.id" :value="prod.id">
                                        {{ prod.nombre }} ({{ prod.codigo_qr }}) - ${{ Number(prod.precio_venta).toFixed(2) }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.id_producto" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="nombre">Nombre de la Oferta / Promoción</Label>
                                <Input id="nombre" type="text" required v-model="form.nombre" placeholder="ej. Descuento del Día del Padre, Black Friday, etc." class="rounded-xl" />
                                <InputError :message="form.errors.nombre" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="descripcion">Descripción</Label>
                                <textarea
                                    id="descripcion"
                                    v-model="form.descripcion"
                                    placeholder="Detalles opcionales de la promoción..."
                                    rows="3"
                                    class="flex w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                ></textarea>
                                <InputError :message="form.errors.descripcion" />
                            </div>
                        </div>

                        <!-- Configuration card -->
                        <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                            <h2 class="text-lg font-bold text-foreground">Regla de Descuento y Fechas</h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="tipo_descuento">Tipo de Descuento</Label>
                                    <select
                                        id="tipo_descuento"
                                        required
                                        v-model="form.tipo_descuento"
                                        class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="porcentaje">Porcentaje (%)</option>
                                        <option value="monto">Monto Fijo ($)</option>
                                    </select>
                                    <InputError :message="form.errors.tipo_descuento" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="valor_descuento">Valor del Descuento</Label>
                                    <Input
                                        id="valor_descuento"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        v-model="form.valor_descuento"
                                        :placeholder="form.tipo_descuento === 'porcentaje' ? 'ej. 15' : 'ej. 5.99'"
                                        class="rounded-xl"
                                    />
                                    <InputError :message="form.errors.valor_descuento" />
                                    <span v-if="isDiscountAmountTooHigh" class="text-[11px] font-bold text-red-500">
                                        ¡Atención! El descuento es mayor o igual al precio de venta del producto.
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="fecha_inicio">Fecha de Inicio</Label>
                                    <Input id="fecha_inicio" type="date" required v-model="form.fecha_inicio" class="rounded-xl" />
                                    <InputError :message="form.errors.fecha_inicio" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="fecha_fin">Fecha de Finalización</Label>
                                    <Input id="fecha_fin" type="date" required v-model="form.fecha_fin" class="rounded-xl" />
                                    <InputError :message="form.errors.fecha_fin" />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="estado_oferta">Estado</Label>
                                <select
                                    id="estado_oferta"
                                    required
                                    v-model="form.estado_oferta"
                                    class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="activa">Activa (Vigente según las fechas)</option>
                                    <option value="inactiva">Inactiva (Pausada manualmente)</option>
                                </select>
                                <InputError :message="form.errors.estado_oferta" />
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Preview Card -->
                    <div class="space-y-6">
                        <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4 sticky top-6">
                            <h3 class="text-md font-bold text-foreground border-b border-border pb-2">Vista Previa del Descuento</h3>

                            <div v-if="selectedProduct" class="space-y-4">
                                <div class="aspect-square bg-muted rounded-xl flex items-center justify-center overflow-hidden border border-border">
                                    <img v-if="selectedProduct.foto" :src="selectedProduct.foto" :alt="selectedProduct.nombre" class="object-cover w-full h-full" />
                                    <Shirt v-else class="h-16 w-16 text-neutral-300 dark:text-neutral-700" />
                                </div>

                                <div class="space-y-1">
                                    <span class="text-xs text-muted-foreground font-mono">{{ selectedProduct.codigo_qr }}</span>
                                    <h4 class="font-bold text-sm text-foreground leading-snug">{{ selectedProduct.nombre }}</h4>
                                </div>

                                <div class="pt-2 border-t border-border flex justify-between items-baseline">
                                    <span class="text-xs text-muted-foreground">Precio Base:</span>
                                    <span class="font-mono text-sm text-muted-foreground line-through">${{ Number(selectedProduct.precio_venta).toFixed(2) }}</span>
                                </div>

                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs text-muted-foreground">Descuento aplicado:</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ form.tipo_descuento === 'porcentaje' ? `${Number(form.valor_descuento || 0)}%` : `$${Number(form.valor_descuento || 0).toFixed(2)}` }}
                                    </span>
                                </div>

                                <div class="pt-2 border-t border-border flex justify-between items-baseline">
                                    <span class="text-sm font-bold text-foreground">Precio con Oferta:</span>
                                    <span class="font-mono text-lg font-black text-primary">${{ Number(tentativeFinalPrice).toFixed(2) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-center py-12 text-xs text-muted-foreground italic">
                                Selecciona un producto para visualizar la simulación del descuento.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit buttons -->
                <div class="flex items-center justify-end gap-3 max-w-4xl">
                    <Link :href="route('ofertas.index')">
                        <Button variant="outline" type="button" class="rounded-xl">Cancelar</Button>
                    </Link>
                    <Button type="submit" class="flex items-center gap-1.5 rounded-xl shadow-sm" :disabled="form.processing || isDiscountAmountTooHigh">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Actualizar Oferta
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
