<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Eye, Trash2, ShoppingBag, ShieldAlert, Check, AlertCircle, CreditCard } from 'lucide-vue-next';
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
    estado_pedido: 'pendiente' | 'confirmado' | 'entregado' | 'cancelado';
    observacion: string | null;
    state: string;
    cliente: UserType;
    pagos?: any[];
}

interface PaginatedOrders {
    data: OrderType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    pedidos: PaginatedOrders;
    filters: { search?: string; status?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isClient = computed(() => userRole.value === 'Cliente');
const isAdmin = computed(() => userRole.value === 'Administrador');

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const orderToDelete = ref<OrderType | null>(null);


let searchTimeout: any = null;
const applyFilters = () => {
    router.get(
        route('pedidos.index'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

const confirmDelete = (order: OrderType) => {
    orderToDelete.value = order;
};

const deleteOrder = () => {
    if (orderToDelete.value) {
        router.delete(route('pedidos.destroy', orderToDelete.value.id), {
            onSuccess: () => {
                orderToDelete.value = null;
            },
        });
    }
};

const getStatusBadge = (order: OrderType) => {
    const map = {
        pendiente: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50' },
        confirmado: { label: 'Confirmado', class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50' },
        entregado: { label: 'Entregado', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' },
        cancelado: { label: 'Cancelado', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' },
    };
    return map[order.estado_pedido] || { label: order.estado_pedido, class: 'bg-neutral-100 text-neutral-800' };
};

const getSaldoPendiente = (order: OrderType) => {
    if (!order.pagos) return parseFloat(order.total as string);
    const totalPagado = order.pagos
        .filter((p: any) => p.estado_pago === 'completado')
        .reduce((sum: number, p: any) => sum + parseFloat(p.monto), 0);
    return Math.max(0, parseFloat(order.total as string) - totalPagado);
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Pedidos', href: '/pedidos' }]">
        <Head title="Gestión de Pedidos" />

        <div class="space-y-6 max-w-7xl mx-auto">
            
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        {{ isClient ? 'Mis Pedidos' : 'Gestión de Pedidos' }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ isClient 
                            ? 'Consulta el estado, historial y detalles de tus pedidos de pijamas.' 
                            : 'Realiza el seguimiento, confirmación y despacho de los pedidos textiles recibidos.' }}
                    </p>
                </div>
                <div v-if="$page.props.auth.permissions.includes('pedidos.crear')">
                    <Link :href="route('pedidos.create')">
                        <Button class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform rounded-xl">
                            <Plus class="h-4 w-4" />
                            Realizar Nuevo Pedido
                        </Button>
                    </Link>
                </div>
            </div>

            
            <div v-if="props.flash?.success" class="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <Check class="h-4 w-4" />
                {{ props.flash.success }}
            </div>
            <div v-if="props.flash?.error" class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <AlertCircle class="h-4 w-4" />
                {{ props.flash.error }}
            </div>

            
            <div class="flex flex-col sm:flex-row gap-4 sm:items-center justify-between">
                <div class="relative max-w-md w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="text"
                        :placeholder="isClient ? 'Buscar por ID o nota de observación...' : 'Buscar por ID, cliente, correo o CI...'"
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-muted-foreground shrink-0">Filtrar Estado:</span>
                    <select
                        v-model="status"
                        class="flex h-10 w-44 rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos los pedidos</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="confirmado">Confirmado</option>
                        <option value="entregado">Entregado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
            </div>

            
            
            <div v-if="isClient" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-if="props.pedidos.data.length === 0" class="col-span-full p-12 text-center text-muted-foreground border border-dashed rounded-2xl bg-card">
                    Aún no has realizado ningún pedido. ¡Empieza a comprar ahora!
                </div>
                <div
                    v-for="order in props.pedidos.data"
                    :key="order.id"
                    class="p-6 rounded-2xl border border-border bg-card shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between gap-4"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-semibold text-muted-foreground">Pedido #{{ order.id }}</span>
                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(order).class]">
                                {{ getStatusBadge(order).label }}
                            </span>
                        </div>
                        <div class="text-xs text-muted-foreground">Fecha: {{ order.fecha_pedido }}</div>
                        <p class="text-xs text-foreground/80 italic line-clamp-2">
                            {{ order.observacion ? `"${order.observacion}"` : 'Sin observaciones' }}
                        </p>
                    </div>
                    <div class="pt-4 border-t border-border flex items-center justify-between">
                        <div class="flex flex-col gap-0.5">
                            <div class="flex items-center gap-1.5 text-xs">
                                <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-wide">Total:</span>
                                <span class="font-mono font-bold text-foreground">Bs. {{ Number(order.total).toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs">
                                <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-wide">
                                    {{ getSaldoPendiente(order) > 0 ? 'Pendiente:' : 'Pagado:' }}
                                </span>
                                <span :class="['font-mono font-black', getSaldoPendiente(order) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400']">
                                    Bs. {{ Number(getSaldoPendiente(order) > 0 ? getSaldoPendiente(order) : order.total).toFixed(2) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="route('pedidos.show', order.id)">
                                <Button variant="outline" size="sm" class="flex items-center gap-1 rounded-lg">
                                    <Eye class="h-4 w-4" />
                                    Detalles
                                </Button>
                            </Link>
                            <Link 
                                v-if="getSaldoPendiente(order) > 0 && order.estado_pedido !== 'cancelado' && $page.props.auth.permissions.includes('pagos.crear')"
                                :href="route('pagos.create', { id_pedido: order.id })"
                            >
                                <Button size="sm" class="flex items-center gap-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white border-none shadow-sm">
                                    <CreditCard class="h-4 w-4" />
                                    Pagar
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            
            <div v-else class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">Código</th>
                                <th class="p-4">Cliente</th>
                                <th class="p-4">C.I.</th>
                                <th class="p-4">Fecha</th>
                                <th class="p-4">Total</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.pedidos.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No se encontraron pedidos registrados.
                                </td>
                            </tr>
                            <tr v-for="order in props.pedidos.data" :key="order.id" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4 font-mono font-medium text-muted-foreground">#{{ order.id }}</td>
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-foreground">{{ order.cliente.nombre }} {{ order.cliente.apellido }}</span>
                                        <span class="text-xs text-muted-foreground">@{{ order.cliente.username }} · {{ order.cliente.email }}</span>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-muted-foreground">{{ order.cliente.ci }}</td>
                                <td class="p-4 text-muted-foreground">{{ order.fecha_pedido }}</td>
                                <td class="p-4 font-mono font-bold text-foreground">Bs. {{ Number(order.total).toFixed(2) }}</td>
                                <td class="p-4">
                                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(order).class]">
                                        {{ getStatusBadge(order).label }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link 
                                            v-if="getSaldoPendiente(order) > 0 && order.estado_pedido !== 'cancelado' && $page.props.auth.permissions.includes('pagos.crear')"
                                            :href="route('pagos.create', { id_pedido: order.id })"
                                        >
                                            <Button size="sm" class="h-8 px-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white border-none shadow-sm flex items-center gap-1" title="Registrar Pago">
                                                <CreditCard class="h-4 w-4" />
                                                <span class="sr-only sm:not-sr-only text-xs">Pagar</span>
                                            </Button>
                                        </Link>
                                        <Link :href="route('pedidos.show', order.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg" title="Ver detalles y gestionar">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            v-if="$page.props.auth.permissions.includes('pedidos.eliminar')"
                                            @click="confirmDelete(order)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar pedido"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                
                <div v-if="props.pedidos.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.pedidos.current_page }} de {{ props.pedidos.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.pedidos.prev_page_url" :href="props.pedidos.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.pedidos.next_page_url" :href="props.pedidos.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        
        <Dialog :open="!!orderToDelete" @update:open="(val) => !val && (orderToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Pedido?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará la <strong>eliminación</strong> del pedido <strong>#{{ orderToDelete?.id }}</strong>. El stock de los productos asociados se restaurará de forma automática si el pedido no estaba cancelado.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="orderToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteOrder" class="rounded-xl">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
