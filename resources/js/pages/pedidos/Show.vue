<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ArrowLeft, Save, LoaderCircle, ShoppingBag, Trash2, CheckCircle, Ban, AlertTriangle, ShieldAlert } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface ProductType {
    id: number;
    nombre: string;
    codigo_qr: string;
    precio_venta: number | string;
    foto: string | null;
}

interface DetailType {
    id: number;
    id_producto: number;
    cantidad: number;
    precio_venta: number | string;
    descuento: number | string;
    subtotal: number | string;
    producto: ProductType;
}

interface CustomerType {
    id: number;
    nombre: string;
    apellido: string;
    email: string;
    username: string;
    ci: string;
    telefono: string | null;
    direccion: string | null;
}

interface OrderType {
    id: number;
    id_cliente: number;
    fecha_pedido: string;
    total: number | string;
    estado_pedido: 'pendiente' | 'confirmado' | 'entregado' | 'cancelado';
    observacion: string | null;
    state: string;
    cliente: CustomerType;
    detalles: DetailType[];
}

const props = defineProps<{
    pedido: OrderType;
    flash?: { success?: string | null; error?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isClient = computed(() => userRole.value === 'Cliente');
const isAdmin = computed(() => userRole.value === 'Administrador');
const isStaff = computed(() => ['Administrador', 'Vendedor'].includes(userRole.value));

const showDeleteDialog = ref(false);

const form = useForm({
    estado_pedido: props.pedido.estado_pedido,
    observacion: props.pedido.observacion || '',
});

const submitUpdate = () => {
    form.put(route('pedidos.update', props.pedido.id), {
        preserveScroll: true,
    });
};

const cancelOrderClient = () => {
    if (confirm('¿Estás seguro de que deseas cancelar este pedido?')) {
        router.put(route('pedidos.update', props.pedido.id), {
            estado_pedido: 'cancelado',
        }, {
            preserveScroll: true,
        });
    }
};

const deleteOrderAdmin = () => {
    router.delete(route('pedidos.destroy', props.pedido.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
        }
    });
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { label: string; class: string }> = {
        pendiente: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50' },
        confirmado: { label: 'Confirmado', class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50' },
        entregado: { label: 'Entregado', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' },
        cancelado: { label: 'Cancelado', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' },
    };
    return map[status] || { label: status, class: 'bg-neutral-100 text-neutral-800' };
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Pedidos', href: '/pedidos' },
        { title: `Detalles Pedido #${props.pedido.id}`, href: `/pedidos/${props.pedido.id}` }
    ]">
        <Head :title="`Pedido #${props.pedido.id}`" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-foreground">
                            Pedido #{{ props.pedido.id }}
                        </h1>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Fecha: {{ props.pedido.fecha_pedido }}
                        </p>
                    </div>
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

            <!-- Flash messages -->
            <div v-if="props.flash?.success" class="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-sm shadow-sm">
                <CheckCircle class="h-4 w-4" />
                {{ props.flash.success }}
            </div>
            <div v-if="props.flash?.error" class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive flex items-center gap-2 text-sm shadow-sm">
                <Ban class="h-4 w-4" />
                {{ props.flash.error }}
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Details & Items list (2 cols) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Customer card info -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                        <h2 class="text-lg font-bold text-foreground">Información del Cliente y Entrega</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="space-y-1">
                                <span class="text-muted-foreground block">Nombre completo</span>
                                <strong class="text-foreground text-sm">{{ props.pedido.cliente.nombre }} {{ props.pedido.cliente.apellido }}</strong>
                            </div>
                            <div class="space-y-1">
                                <span class="text-muted-foreground block">Cédula de Identidad (CI)</span>
                                <strong class="text-foreground text-sm font-mono">{{ props.pedido.cliente.ci }}</strong>
                            </div>
                            <div class="space-y-1">
                                <span class="text-muted-foreground block">Correo electrónico</span>
                                <strong class="text-foreground text-sm">{{ props.pedido.cliente.email }}</strong>
                            </div>
                            <div class="space-y-1">
                                <span class="text-muted-foreground block">Teléfono de contacto</span>
                                <strong class="text-foreground text-sm">{{ props.pedido.cliente.telefono || 'N/A' }}</strong>
                            </div>
                            <div class="space-y-1 md:col-span-2">
                                <span class="text-muted-foreground block">Dirección de despacho</span>
                                <strong class="text-foreground text-sm">{{ props.pedido.cliente.direccion || 'N/A' }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Items table card -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                        <h2 class="text-lg font-bold text-foreground">Detalle de Prendas</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-border text-muted-foreground font-bold uppercase tracking-wider pb-2">
                                        <th class="pb-3">Prenda</th>
                                        <th class="pb-3">Precio Base</th>
                                        <th class="pb-3">Descuento</th>
                                        <th class="pb-3 text-center">Cantidad</th>
                                        <th class="pb-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr v-for="det in props.pedido.detalles" :key="det.id" class="align-middle">
                                        <td class="py-3 flex items-center gap-3">
                                            <div class="h-10 w-10 rounded bg-muted flex items-center justify-center overflow-hidden border border-border shrink-0">
                                                <img v-if="det.producto.foto" :src="det.producto.foto" :alt="det.producto.nombre" class="object-cover h-full w-full" />
                                                <Shirt v-else class="h-4 w-4 text-neutral-400" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-foreground leading-snug">{{ det.producto.nombre }}</span>
                                                <span class="font-mono text-[10px] text-muted-foreground">{{ det.producto.codigo_qr }}</span>
                                            </div>
                                        </td>
                                        <td class="py-3 font-mono text-muted-foreground">Bs. {{ Number(det.precio_venta).toFixed(2) }}</td>
                                        <td class="py-3 font-mono text-emerald-600 dark:text-emerald-400 font-bold">
                                            {{ Number(det.descuento) > 0 ? `-Bs. ${Number(det.descuento).toFixed(2)}` : '-' }}
                                        </td>
                                        <td class="py-3 text-center font-bold font-mono">{{ det.cantidad }}</td>
                                        <td class="py-3 text-right font-mono font-bold text-foreground">
                                            Bs. {{ Number(det.subtotal).toFixed(2) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-border/80 font-bold text-sm">
                                        <td colspan="4" class="pt-4 text-right">Total a pagar:</td>
                                        <td class="pt-4 text-right font-mono text-base font-black text-primary">
                                            Bs. {{ Number(props.pedido.total).toFixed(2) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Actions card (1 col) -->
                <div class="space-y-6">
                    <!-- Status display & observations -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-5">
                        <div class="flex items-center justify-between border-b border-border/60 pb-3">
                            <span class="text-sm font-bold text-foreground">Estado del Pedido</span>
                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(props.pedido.estado_pedido).class]">
                                {{ getStatusBadge(props.pedido.estado_pedido).label }}
                            </span>
                        </div>

                        <!-- Observation displaying -->
                        <div class="space-y-1">
                            <span class="text-xs font-bold text-muted-foreground">Nota / Observación</span>
                            <p class="text-xs text-foreground bg-muted/40 p-3 rounded-lg border border-border/60 italic leading-relaxed">
                                {{ props.pedido.observacion ? `"${props.pedido.observacion}"` : 'Sin observaciones del pedido.' }}
                            </p>
                        </div>

                        <!-- Client cancellation button -->
                        <div v-if="isClient && props.pedido.estado_pedido === 'pendiente'" class="pt-2">
                            <Button
                                @click="cancelOrderClient"
                                variant="destructive"
                                class="w-full flex items-center justify-center gap-1.5 rounded-xl shadow-sm"
                            >
                                <Ban class="h-4 w-4" />
                                Cancelar Pedido
                            </Button>
                        </div>

                        <!-- Staff update panel -->
                        <form v-if="isStaff" @submit.prevent="submitUpdate" class="space-y-4 pt-2 border-t border-border/60">
                            <div class="grid gap-2">
                                <Label for="estado_pedido">Actualizar Estado</Label>
                                <select
                                    id="estado_pedido"
                                    required
                                    v-model="form.estado_pedido"
                                    class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="pendiente">Pendiente</option>
                                    <option value="confirmado">Confirmado</option>
                                    <option value="entregado">Entregado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label for="observacion">Editar Observaciones</Label>
                                <textarea
                                    id="observacion"
                                    v-model="form.observacion"
                                    placeholder="Añade o modifica las notas internas de control..."
                                    rows="3"
                                    class="flex w-full rounded-xl border border-border bg-card px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                ></textarea>
                            </div>

                            <Button
                                type="submit"
                                class="w-full flex items-center justify-center gap-1.5 rounded-xl shadow-sm"
                                :disabled="form.processing"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Save v-else class="h-4 w-4" />
                                Guardar Cambios
                            </Button>
                        </form>

                        <!-- Admin logical delete -->
                        <div v-if="isAdmin" class="pt-4 border-t border-border/60">
                            <Button
                                @click="showDeleteDialog = true"
                                variant="ghost"
                                class="w-full flex items-center justify-center gap-1.5 rounded-xl text-destructive hover:bg-destructive/10 border border-transparent hover:border-destructive/20"
                            >
                                <Trash2 class="h-4 w-4" />
                                Eliminar Pedido
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="showDeleteDialog" @update:open="(val) => !val && (showDeleteDialog = false)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Pedido?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>eliminación lógica</strong> del pedido <strong>#{{ props.pedido.id }}</strong>. El stock de los productos asociados se restaurará de forma automática en el inventario.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="showDeleteDialog = false" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteOrderAdmin" class="rounded-xl">Eliminar Lógicamente</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
