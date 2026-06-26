<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Trash2, CheckCircle, Ban, AlertTriangle, ShieldAlert, FileText, ShoppingCart, User, Calendar } from 'lucide-vue-next';
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
    cantidad: number;
    precio_venta: number | string;
    subtotal: number | string;
    producto: ProductType;
}

interface OrderType {
    id: number;
    fecha_pedido: string;
    total: number | string;
    estado_pedido: string;
    detalles: DetailType[];
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

interface ClaimType {
    id: number;
    id_cliente: number;
    id_pedido: number | null;
    tipo_reclamo: string;
    descripcion: string;
    fecha_reclamo: string;
    fecha_respuesta: string | null;
    respuesta: string | null;
    estado_reclamo: 'pendiente' | 'en_proceso' | 'atendido' | 'rechazado';
    state: string;
    cliente: CustomerType;
    pedido: OrderType | null;
}

const props = defineProps<{
    reclamo: ClaimType;
    flash?: { success?: string | null; error?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isClient = computed(() => userRole.value === 'Cliente');
const isAdmin = computed(() => userRole.value === 'Administrador');
const isStaff = computed(() => ['Administrador', 'Vendedor'].includes(userRole.value));

const showDeleteDialog = ref(false);

const form = useForm({
    estado_reclamo: props.reclamo.estado_reclamo,
    respuesta: props.reclamo.respuesta || '',
});

const submitUpdate = () => {
    form.put(route('reclamos.update', props.reclamo.id), {
        preserveScroll: true,
    });
};

const deleteClaimAdmin = () => {
    router.delete(route('reclamos.destroy', props.reclamo.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
        }
    });
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { label: string; class: string }> = {
        pendiente: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50' },
        en_proceso: { label: 'En Proceso', class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50' },
        atendido: { label: 'Atendido', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' },
        rechazado: { label: 'Rechazado', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' },
    };
    return map[status] || { label: status, class: 'bg-neutral-100 text-neutral-800' };
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Reclamos y Comentarios', href: '/reclamos' },
        { title: `Detalles Reclamo #${props.reclamo.id}`, href: `/reclamos/${props.reclamo.id}` }
    ]">
        <Head :title="`Reclamo #${props.reclamo.id}`" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-foreground">
                            Reclamo #{{ props.reclamo.id }}
                        </h1>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                            <span class="font-medium text-foreground uppercase tracking-wider text-primary">{{ props.reclamo.tipo_reclamo }}</span>
                            <span>•</span>
                            <span>Fecha de envío: {{ props.reclamo.fecha_reclamo }}</span>
                            <span>•</span>
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold', getStatusBadge(props.reclamo.estado_reclamo).class]">
                                {{ getStatusBadge(props.reclamo.estado_reclamo).label }}
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <Link :href="route('reclamos.index')">
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
                <!-- Left side: Claim detail and linked order (2 cols) -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Claim Detail Card -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                        <h2 class="text-lg font-bold text-foreground flex items-center gap-2">
                            <FileText class="h-5 w-5 text-primary" />
                            Detalle del Reclamo
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm pt-2">
                            <div class="space-y-1">
                                <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Cliente</span>
                                <p class="font-semibold text-foreground">
                                    {{ props.reclamo.cliente.nombre }} {{ props.reclamo.cliente.apellido }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    C.I. {{ props.reclamo.cliente.ci }} · @{{ props.reclamo.cliente.username }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Contacto</span>
                                <p class="text-foreground font-medium">{{ props.reclamo.cliente.email }}</p>
                                <p class="text-xs text-muted-foreground">Telf: {{ props.reclamo.cliente.telefono || 'Sin registrar' }}</p>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-border space-y-2">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Descripción del Suceso</span>
                            <div class="p-4 rounded-xl bg-muted/30 border border-border text-sm text-foreground leading-relaxed whitespace-pre-wrap">
                                {{ props.reclamo.descripcion }}
                            </div>
                        </div>
                    </div>

                    <!-- Linked Order Card (If associated) -->
                    <div v-if="props.reclamo.pedido" class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-bold text-foreground flex items-center gap-2">
                                <ShoppingCart class="h-5 w-5 text-primary" />
                                Pedido Vinculado
                            </h2>
                            <Link :href="route('pedidos.show', props.reclamo.pedido.id)" class="text-xs text-primary font-semibold hover:underline">
                                Ver pedido completo →
                            </Link>
                        </div>

                        <div class="grid grid-cols-3 gap-4 text-xs p-4 rounded-xl bg-muted/40 border border-border">
                            <div>
                                <span class="text-muted-foreground uppercase font-bold tracking-wide">Código</span>
                                <p class="font-mono text-sm font-bold text-foreground mt-0.5">#{{ props.reclamo.pedido.id }}</p>
                            </div>
                            <div>
                                <span class="text-muted-foreground uppercase font-bold tracking-wide">Fecha</span>
                                <p class="text-sm font-semibold text-foreground mt-0.5">{{ props.reclamo.pedido.fecha_pedido }}</p>
                            </div>
                            <div>
                                <span class="text-muted-foreground uppercase font-bold tracking-wide">Monto</span>
                                <p class="font-mono text-sm font-bold text-foreground mt-0.5">Bs. {{ Number(props.reclamo.pedido.total).toFixed(2) }}</p>
                            </div>
                        </div>

                        <!-- Order items preview -->
                        <div class="space-y-3">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Productos en el pedido</span>
                            <div class="divide-y divide-border border rounded-xl overflow-hidden bg-card">
                                <div 
                                    v-for="item in props.reclamo.pedido.detalles" 
                                    :key="item.id"
                                    class="p-4 flex items-center justify-between text-sm hover:bg-muted/10 transition-colors"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-lg bg-muted flex items-center justify-center border overflow-hidden">
                                            <img v-if="item.producto.foto" :src="item.producto.foto" class="h-full w-full object-cover" />
                                            <span v-else class="text-xs text-muted-foreground">👕</span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-foreground">{{ item.producto.nombre }}</p>
                                            <p class="text-xs text-muted-foreground font-mono">Cod: {{ item.producto.codigo_qr }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-foreground">{{ item.cantidad }}x</p>
                                        <p class="text-xs font-mono text-muted-foreground">Bs. {{ Number(item.precio_venta).toFixed(2) }} c/u</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right side: Response and status management (1 col) -->
                <div class="space-y-6">
                    
                    <!-- Response / Status Box -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                        <h2 class="text-lg font-bold text-foreground flex items-center gap-2">
                            <CheckCircle class="h-5 w-5 text-primary" />
                            Resolución y Respuesta
                        </h2>

                        <!-- Staff Form -->
                        <form v-if="isStaff" @submit.prevent="submitUpdate" class="space-y-4 pt-2">
                            <div class="space-y-2">
                                <Label for="estado_reclamo" class="text-sm font-semibold text-foreground">
                                    Estado del Reclamo
                                </Label>
                                <select
                                    id="estado_reclamo"
                                    v-model="form.estado_reclamo"
                                    class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="pendiente">Pendiente</option>
                                    <option value="en_proceso">En Proceso</option>
                                    <option value="atendido">Atendido</option>
                                    <option value="rechazado">Rechazado</option>
                                </select>
                                <InputError :message="form.errors.estado_reclamo" />
                            </div>

                            <div class="space-y-2">
                                <Label for="respuesta" class="text-sm font-semibold text-foreground">
                                    Respuesta al Cliente
                                </Label>
                                <textarea
                                    id="respuesta"
                                    v-model="form.respuesta"
                                    rows="5"
                                    placeholder="Escribe la respuesta formal para el cliente..."
                                    class="flex min-h-[80px] w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                ></textarea>
                                <div class="text-[10px] text-right text-muted-foreground">
                                    {{ form.respuesta.length }}/2000
                                </div>
                                <InputError :message="form.errors.respuesta" />
                            </div>

                            <Button 
                                type="submit" 
                                :disabled="form.processing"
                                class="w-full flex items-center justify-center gap-1.5 rounded-xl shadow-sm hover:scale-[1.01] transition-transform"
                            >
                                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <Save v-else class="h-4 w-4" />
                                Guardar Respuesta
                            </Button>
                        </form>

                        <!-- Client Read-Only Mode -->
                        <div v-else class="space-y-4 pt-2">
                            <div class="space-y-1">
                                <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Estado actual</span>
                                <div class="mt-1">
                                    <span :class="['inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold', getStatusBadge(props.reclamo.estado_reclamo).class]">
                                        {{ getStatusBadge(props.reclamo.estado_reclamo).label }}
                                    </span>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-border space-y-2">
                                <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Respuesta Oficial</span>
                                <div v-if="props.reclamo.respuesta" class="space-y-2">
                                    <div class="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20 text-sm text-foreground leading-relaxed whitespace-pre-wrap">
                                        {{ props.reclamo.respuesta }}
                                    </div>
                                    <p class="text-[10px] text-muted-foreground flex items-center gap-1.5">
                                        <Calendar class="h-3 w-3" />
                                        Atendido el: {{ props.reclamo.fecha_respuesta }}
                                    </p>
                                </div>
                                <div v-else class="p-4 rounded-xl bg-muted/40 border border-dashed text-center text-xs text-muted-foreground py-6">
                                    ⏳ Tu reclamo se encuentra en estado <strong>{{ getStatusBadge(props.reclamo.estado_reclamo).label }}</strong>. Nuestro personal responderá a la brevedad.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Delete Action Box -->
                    <div v-if="isAdmin" class="p-6 rounded-xl border border-destructive/20 bg-destructive/5 shadow-sm space-y-4">
                        <h2 class="text-lg font-bold text-destructive flex items-center gap-2">
                            <ShieldAlert class="h-5 w-5" />
                            Acciones de Moderación
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Los administradores pueden eliminar lógicamente este reclamo del sistema si infringe normas o fue duplicado.
                        </p>
                        <Button 
                            variant="destructive" 
                            class="w-full flex items-center justify-center gap-1.5 rounded-xl"
                            @click="showDeleteDialog = true"
                        >
                            <Trash2 class="h-4 w-4" />
                            Eliminar Reclamo
                        </Button>
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
                        ¿Eliminar Reclamo?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>eliminación lógica</strong> del reclamo <strong>#{{ props.reclamo.id }}</strong>. Dejará de visualizarse en la lista general de reclamos activos.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="showDeleteDialog = false" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteClaimAdmin" class="rounded-xl">Eliminar Lógicamente</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
