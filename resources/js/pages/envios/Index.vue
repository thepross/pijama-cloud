<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Edit, Trash2, Truck, ShieldAlert, Check, AlertCircle } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface UserType {
    id: number;
    nombre: string;
    apellido: string;
    email: string;
    username: string;
    ci: string;
}

interface OrderType {
    id: number;
    id_cliente: number;
    fecha_pedido: string;
    total: number | string;
    estado_pedido: string;
    cliente: UserType;
}

interface ShipmentType {
    id: number;
    id_pedido: number;
    id_distribuidor: number | null;
    direccion_entrega: string;
    fecha_salida: string | null;
    fecha_entrega: string | null;
    estado_envio: 'pendiente' | 'en_camino' | 'entregado' | 'fallido';
    observacion: string | null;
    ruta: string | null;
    state: string;
    pedido: OrderType;
    distribuidor: UserType | null;
}

interface PaginatedShipments {
    data: ShipmentType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    envios: PaginatedShipments;
    filters: { search?: string; status?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isDistributor = computed(() => userRole.value === 'Distribuidor');
const isAdmin = computed(() => userRole.value === 'Administrador');
const isStaff = computed(() => ['Administrador', 'Vendedor'].includes(userRole.value));

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const shipmentToDelete = ref<ShipmentType | null>(null);

// Apply filters
let searchTimeout: any = null;
const applyFilters = () => {
    router.get(
        route('envios.index'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

const confirmDelete = (shipment: ShipmentType) => {
    shipmentToDelete.value = shipment;
};

const deleteShipment = () => {
    if (shipmentToDelete.value) {
        router.delete(route('envios.destroy', shipmentToDelete.value.id), {
            onSuccess: () => {
                shipmentToDelete.value = null;
            },
        });
    }
};

const getStatusBadge = (shipment: ShipmentType) => {
    const map = {
        pendiente: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50' },
        en_camino: { label: 'En Camino', class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50' },
        entregado: { label: 'Entregado', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' },
        fallido: { label: 'Fallido', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' },
    };
    return map[shipment.estado_envio] || { label: shipment.estado_envio, class: 'bg-neutral-100 text-neutral-800' };
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Envíos', href: '/envios' }]">
        <Head title="Gestión de Envíos" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <Truck class="h-8 w-8 text-primary" />
                        {{ isDistributor ? 'Mis Despachos' : 'Gestión de Envíos' }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ isDistributor
                            ? 'Consulta y gestiona las entregas asignadas a tu cuenta y reporta su estado en tiempo real.'
                            : 'Asigna transportistas, define rutas de reparto y supervisa el estado de entrega de los pedidos.' }}
                    </p>
                </div>
                <div v-if="isStaff">
                    <Link :href="route('envios.create')">
                        <Button class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform rounded-xl">
                            <Plus class="h-4 w-4" />
                            Registrar Envío Manual
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
                <AlertCircle class="h-4 w-4" />
                {{ props.flash.error }}
            </div>

            <!-- Filters -->
            <div class="flex flex-col sm:flex-row gap-4 sm:items-center justify-between">
                <div class="relative max-w-md w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="text"
                        :placeholder="isDistributor ? 'Buscar por ID pedido, dirección o ruta...' : 'Buscar por ID, cliente, transportista o ruta...'"
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-muted-foreground shrink-0">Filtrar Estado:</span>
                    <select
                        v-model="status"
                        class="flex h-10 w-44 rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos los envíos</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="en_camino">En Camino</option>
                        <option value="entregado">Entregado</option>
                        <option value="fallido">Fallido</option>
                    </select>
                </div>
            </div>

            <!-- Table listing -->
            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">Pedido</th>
                                <th class="p-4">Cliente / Dirección</th>
                                <th class="p-4">Distribuidor</th>
                                <th class="p-4">Ruta asignada</th>
                                <th class="p-4">Vigencia Fechas</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.envios.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No se encontraron registros de envíos.
                                </td>
                            </tr>
                            <tr v-for="shipment in props.envios.data" :key="shipment.id" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4 font-mono font-bold text-muted-foreground">
                                    <Link :href="route('pedidos.show', shipment.id_pedido)" class="hover:underline text-primary">
                                        #{{ shipment.id_pedido }}
                                    </Link>
                                </td>
                                <td class="p-4 max-w-xs">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-foreground">
                                            {{ shipment.pedido.cliente.nombre }} {{ shipment.pedido.cliente.apellido }}
                                        </span>
                                        <span class="text-xs text-muted-foreground leading-normal truncate" :title="shipment.direccion_entrega">
                                            {{ shipment.direccion_entrega }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div v-if="shipment.distribuidor" class="flex flex-col">
                                        <span class="font-medium text-foreground">
                                            {{ shipment.distribuidor.nombre }} {{ shipment.distribuidor.apellido }}
                                        </span>
                                        <span class="text-xs text-muted-foreground">@{{ shipment.distribuidor.username }}</span>
                                    </div>
                                    <span v-else class="text-xs font-bold text-amber-500 bg-amber-500/10 px-2.5 py-0.5 rounded-full">
                                        Sin asignar
                                    </span>
                                </td>
                                <td class="p-4 font-medium text-foreground">{{ shipment.ruta || 'No definida' }}</td>
                                <td class="p-4 text-xs text-muted-foreground">
                                    <div class="flex flex-col gap-0.5">
                                        <span>Salida: {{ shipment.fecha_salida || '-' }}</span>
                                        <span>Entrega: {{ shipment.fecha_entrega || '-' }}</span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(shipment).class]">
                                        {{ getStatusBadge(shipment).label }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('envios.edit', shipment.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg" title="Editar detalles de entrega">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            v-if="isAdmin"
                                            @click="confirmDelete(shipment)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar registro de envío"
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
                <div v-if="props.envios.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.envios.current_page }} de {{ props.envios.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.envios.prev_page_url" :href="props.envios.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.envios.next_page_url" :href="props.envios.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!shipmentToDelete" @update:open="(val) => !val && (shipmentToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Registro de Envío?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>eliminación lógica</strong> del registro de envío <strong>#{{ shipmentToDelete?.id }}</strong> asociado al pedido #{{ shipmentToDelete?.id_pedido }}.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="shipmentToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteShipment" class="rounded-xl">Eliminar Lógicamente</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
