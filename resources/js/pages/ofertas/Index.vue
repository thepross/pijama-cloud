<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Edit, Trash2, Tag, ShieldAlert, Check, AlertTriangle, Shirt } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface ProductType {
    id: number;
    nombre: string;
    codigo_qr: string;
    precio_venta: number;
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
    producto: ProductType;
}

interface PaginatedOffers {
    data: OfferType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    ofertas: PaginatedOffers;
    filters: { search?: string; status?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const offerToDelete = ref<OfferType | null>(null);

// Apply filters on change
let searchTimeout: any = null;
const applyFilters = () => {
    router.get(
        route('ofertas.index'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

const confirmDelete = (offer: OfferType) => {
    offerToDelete.value = offer;
};

const deleteOffer = () => {
    if (offerToDelete.value) {
        router.delete(route('ofertas.destroy', offerToDelete.value.id), {
            onSuccess: () => {
                offerToDelete.value = null;
            },
        });
    }
};

const getOfferStatus = (offer: OfferType) => {
    if (offer.estado_oferta === 'inactiva') {
        return { label: 'Inactiva', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' };
    }
    const today = new Date().toISOString().split('T')[0];
    if (offer.fecha_inicio > today) {
        return { label: 'Programada', class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50' };
    }
    if (offer.fecha_fin < today) {
        return { label: 'Vencida', class: 'bg-neutral-100 text-neutral-700 dark:bg-neutral-850 dark:text-neutral-400 border border-neutral-200 dark:border-neutral-800' };
    }
    return { label: 'Activa', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' };
};

const formatDiscount = (offer: OfferType) => {
    const val = Number(offer.valor_descuento);
    return offer.tipo_descuento === 'porcentaje' ? `${val.toFixed(0)}%` : `Bs. ${val.toFixed(2)}`;
};

const calculateFinalPrice = (offer: OfferType) => {
    const basePrice = Number(offer.producto.precio_venta);
    const val = Number(offer.valor_descuento);
    let discount = 0;
    if (offer.tipo_descuento === 'porcentaje') {
        discount = basePrice * (val / 100);
    } else {
        discount = val;
    }
    return Math.max(0, basePrice - discount).toFixed(2);
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Ofertas', href: '/ofertas' }]">
        <Head title="Gestión de Ofertas" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Gestión de Ofertas
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Crea, edita y administra ofertas de descuentos especiales asociadas a los productos de Pijamas Cloud.
                    </p>
                </div>
                <div v-if="$page.props.auth.permissions.includes('ofertas.crear')">
                    <Link :href="route('ofertas.create')">
                        <Button class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform rounded-xl">
                            <Plus class="h-4 w-4" />
                            Crear Nueva Oferta
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
                <AlertTriangle class="h-4 w-4" />
                {{ props.flash.error }}
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-4 sm:items-center justify-between">
                <div class="relative max-w-md w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por nombre de oferta o producto..."
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-muted-foreground shrink-0">Filtrar por Estado:</span>
                    <select
                        v-model="status"
                        class="flex h-10 w-44 rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos los estados</option>
                        <option value="activa">Activa</option>
                        <option value="inactiva">Inactiva (Pausada)</option>
                        <option value="programada">Programada</option>
                        <option value="vencida">Vencida</option>
                    </select>
                </div>
            </div>

            <!-- Offers Table -->
            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">Producto</th>
                                <th class="p-4">Nombre de la Oferta</th>
                                <th class="p-4">Descuento</th>
                                <th class="p-4">Precio Final</th>
                                <th class="p-4">Vigencia</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.ofertas.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No se encontraron ofertas registradas.
                                </td>
                            </tr>
                            <tr v-for="offer in props.ofertas.data" :key="offer.id" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-lg bg-neutral-100 dark:bg-neutral-800 flex items-center justify-center overflow-hidden border border-border">
                                            <img v-if="offer.producto.foto" :src="offer.producto.foto" :alt="offer.producto.nombre" class="object-cover h-full w-full" />
                                            <Shirt v-else class="h-5 w-5 text-neutral-400" />
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-foreground leading-none mb-1">{{ offer.producto.nombre }}</span>
                                            <span class="text-xs font-mono text-muted-foreground">{{ offer.producto.codigo_qr }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-foreground">{{ offer.nombre }}</span>
                                        <span class="text-xs text-muted-foreground max-w-xs truncate" :title="offer.descripcion || ''">
                                            {{ offer.descripcion || 'Sin descripción' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ formatDiscount(offer) }}
                                </td>
                                <td class="p-4 font-mono font-bold text-foreground">
                                    Bs. {{ calculateFinalPrice(offer) }}
                                    <span class="text-xs text-muted-foreground line-through font-normal block">
                                        Bs. {{ Number(offer.producto.precio_venta).toFixed(2) }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs text-muted-foreground">
                                    <div class="flex flex-col gap-0.5">
                                        <span>Desde: {{ offer.fecha_inicio }}</span>
                                        <span>Hasta: {{ offer.fecha_fin }}</span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getOfferStatus(offer).class]">
                                        {{ getOfferStatus(offer).label }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link v-if="$page.props.auth.permissions.includes('ofertas.editar')" :href="route('ofertas.edit', offer.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg" title="Editar oferta">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            v-if="$page.props.auth.permissions.includes('ofertas.eliminar')"
                                            @click="confirmDelete(offer)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar oferta"
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
                <div v-if="props.ofertas.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.ofertas.current_page }} de {{ props.ofertas.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.ofertas.prev_page_url" :href="props.ofertas.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.ofertas.next_page_url" :href="props.ofertas.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!offerToDelete" @update:open="(val) => !val && (offerToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Oferta?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>eliminación lógica</strong> de la oferta <strong>"{{ offerToDelete?.nombre }}"</strong>. El descuento dejará de aplicarse inmediatamente al producto.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="offerToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteOffer" class="rounded-xl">Eliminar Lógicamente</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
