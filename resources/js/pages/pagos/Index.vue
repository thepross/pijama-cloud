<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Eye, Trash2, CreditCard, ShieldAlert, Check, AlertCircle, RefreshCw } from 'lucide-vue-next';
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
    fecha_pedido: string;
    total: number | string;
    estado_pedido: string;
    cliente: UserType;
}

interface PaymentType {
    id: number;
    id_pedido: number;
    monto: number | string;
    fecha_pago: string;
    tipo_pago: 'efectivo' | 'tarjeta' | 'qr';
    estado_pago: 'pendiente' | 'completado' | 'fallido';
    total_cuotas: number;
    numero_cuota: number;
    saldo_pendiente: number | string;
    observacion: string | null;
    pedido: OrderType;
}

interface PaginatedPayments {
    data: PaymentType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    pagos: PaginatedPayments;
    filters: { search?: string; status?: string; type?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isClient = computed(() => userRole.value === 'Cliente');
const isAdmin = computed(() => userRole.value === 'Administrador');

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const type = ref(props.filters.type || '');
const paymentToDelete = ref<PaymentType | null>(null);


let searchTimeout: any = null;
const applyFilters = () => {
    router.get(
        route('pagos.index'),
        { search: search.value, status: status.value, type: type.value },
        { preserveState: true, replace: true }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);
watch(type, applyFilters);

const confirmDelete = (payment: PaymentType) => {
    paymentToDelete.value = payment;
};

const deletePayment = () => {
    if (paymentToDelete.value) {
        router.delete(route('pagos.destroy', paymentToDelete.value.id), {
            onSuccess: () => {
                paymentToDelete.value = null;
            },
        });
    }
};

const getStatusBadge = (payment: PaymentType) => {
    const map = {
        pendiente: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50' },
        completado: { label: 'Completado', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' },
        fallido: { label: 'Fallido', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' },
    };
    return map[payment.estado_pago] || { label: payment.estado_pago, class: 'bg-neutral-100 text-neutral-800' };
};

const getMethodLabel = (method: string) => {
    const map: Record<string, string> = {
        efectivo: 'Efectivo',
        tarjeta: 'Tarjeta',
        qr: 'QR PagoFacil',
    };
    return map[method] || method;
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Pagos', href: '/pagos' }]">

        <Head title="Gestión de Pagos" />

        <div class="space-y-6 max-w-7xl mx-auto">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        {{ isClient ? 'Mis Pagos y Cuotas' : 'Gestión de Pagos' }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ isClient
                            ? 'Visualiza tu historial de pagos, cuotas y realiza nuevos abonos a tus pedidos.'
                            : 'Monitorea transacciones, confirma recepciones de efectivo y concilia abonos.' }}
                    </p>
                </div>
                <div v-if="$page.props.auth.permissions.includes('pagos.crear')">
                    <Link :href="route('pagos.create')">
                        <Button
                            class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform rounded-xl">
                            <Plus class="h-4 w-4" />
                            Registrar Nuevo Pago
                        </Button>
                    </Link>
                </div>
            </div>


            <div v-if="props.flash?.success"
                class="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <Check class="h-4 w-4" />
                {{ props.flash.success }}
            </div>
            <div v-if="props.flash?.error"
                class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <AlertCircle class="h-4 w-4" />
                {{ props.flash.error }}
            </div>


            <div class="flex flex-col sm:flex-row gap-4 sm:items-center justify-between">
                <div class="relative max-w-md w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input v-model="search" type="text"
                        :placeholder="isClient ? 'Buscar por ID de pago o de pedido...' : 'Buscar por ID, pedido o cliente...'"
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary" />
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-muted-foreground shrink-0">Método:</span>
                        <select v-model="type"
                            class="flex h-10 w-40 rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                            <option value="">Todos</option>
                            <option value="efectivo">💵 Efectivo</option>
                            <option value="tarjeta">💳 Tarjeta</option>
                            <option value="qr">📱 QR PagoFacil</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-muted-foreground shrink-0">Estado:</span>
                        <select v-model="status"
                            class="flex h-10 w-40 rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                            <option value="">Todos</option>
                            <option value="pendiente">Pendiente</option>
                            <option value="completado">Completado</option>
                            <option value="fallido">Fallido</option>
                        </select>
                    </div>
                </div>
            </div>


            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">Pago</th>
                                <th class="p-4">Pedido</th>
                                <th v-if="!isClient" class="p-4">Cliente</th>
                                <th class="p-4">Monto</th>
                                <th class="p-4">Método</th>
                                <th class="p-4">Cuota</th>
                                <th class="p-4">Fecha</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.pagos.data.length === 0">
                                <td :colspan="isClient ? 8 : 9" class="p-8 text-center text-muted-foreground">
                                    No se encontraron registros de pagos.
                                </td>
                            </tr>
                            <tr v-for="payment in props.pagos.data" :key="payment.id"
                                class="hover:bg-accent/30 transition-colors">
                                <td class="p-4 font-mono font-medium text-muted-foreground">#{{ payment.id }}</td>
                                <td class="p-4">
                                    <Link :href="route('pedidos.show', payment.id_pedido)"
                                        class="font-mono text-primary font-bold hover:underline">
                                        #{{ payment.id_pedido }}
                                    </Link>
                                </td>
                                <td v-if="!isClient" class="p-4">
                                    <div v-if="payment.pedido?.cliente" class="flex flex-col">
                                        <span class="font-semibold text-foreground">{{ payment.pedido.cliente.nombre }}
                                            {{ payment.pedido.cliente.apellido }}</span>
                                        <span class="text-xs text-muted-foreground">C.I. {{ payment.pedido.cliente.ci
                                            }}</span>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground italic">Sin cliente</span>
                                </td>
                                <td class="p-4 font-mono font-black text-foreground">
                                    Bs. {{ Number(payment.monto).toFixed(2) }}
                                </td>
                                <td class="p-4">
                                    <span class="text-xs font-medium text-foreground">
                                        {{ getMethodLabel(payment.tipo_pago) }}
                                    </span>
                                </td>
                                <td class="p-4 text-xs font-medium text-muted-foreground">
                                    Cuota {{ payment.numero_cuota }} / {{ payment.total_cuotas }}
                                </td>
                                <td class="p-4 text-muted-foreground">{{ payment.fecha_pago }}</td>
                                <td class="p-4">
                                    <span
                                        :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(payment).class]">
                                        {{ getStatusBadge(payment).label }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('pagos.show', payment.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg"
                                                title="Ver detalles y comprobante">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button v-if="$page.props.auth.permissions.includes('pagos.eliminar')"
                                            @click="confirmDelete(payment)" variant="ghost" size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar registro">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>


                <div v-if="props.pagos.last_page > 1"
                    class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.pagos.current_page }} de {{ props.pagos.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.pagos.prev_page_url" :href="props.pagos.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.pagos.next_page_url" :href="props.pagos.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>


        <Dialog :open="!!paymentToDelete" @update:open="(val) => !val && (paymentToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Registro de Pago?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará la <strong>eliminación</strong> de la transacción <strong>#{{
                            paymentToDelete?.id }}</strong>. El saldo pendiente del pedido volverá a ajustarse en el
                        sistema.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="paymentToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deletePayment" class="rounded-xl">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
