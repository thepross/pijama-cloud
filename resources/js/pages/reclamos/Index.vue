<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Eye, Trash2, AlertTriangle, ShieldAlert, Check, AlertCircle } from 'lucide-vue-next';
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
    cliente: UserType;
    pedido: OrderType | null;
}

interface PaginatedClaims {
    data: ClaimType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    reclamos: PaginatedClaims;
    filters: { search?: string; status?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isClient = computed(() => userRole.value === 'Cliente');
const isAdmin = computed(() => userRole.value === 'Administrador');

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const claimToDelete = ref<ClaimType | null>(null);

// Apply filters
let searchTimeout: any = null;
const applyFilters = () => {
    router.get(
        route('reclamos.index'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    );
};

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);

const confirmDelete = (claim: ClaimType) => {
    claimToDelete.value = claim;
};

const deleteClaim = () => {
    if (claimToDelete.value) {
        router.delete(route('reclamos.destroy', claimToDelete.value.id), {
            onSuccess: () => {
                claimToDelete.value = null;
            },
        });
    }
};

const getStatusBadge = (claim: ClaimType) => {
    const map = {
        pendiente: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50' },
        en_proceso: { label: 'En Proceso', class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50' },
        atendido: { label: 'Atendido', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' },
        rechazado: { label: 'Rechazado', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' },
    };
    return map[claim.estado_reclamo] || { label: claim.estado_reclamo, class: 'bg-neutral-100 text-neutral-800' };
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reclamos y Comentarios', href: '/reclamos' }]">
        <Head title="Gestión de Reclamos y Comentarios" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        {{ isClient ? 'Mis Reclamos y Comentarios' : 'Gestión de Reclamos y Comentarios' }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ isClient 
                            ? 'Consulta y realiza el seguimiento de tus quejas, reclamos o sugerencias.' 
                            : 'Administra y responde los reclamos y comentarios enviados por los clientes.' }}
                    </p>
                </div>
                <div v-if="$page.props.auth.permissions.includes('reclamos.crear')">
                    <Link :href="route('reclamos.create')">
                        <Button class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform rounded-xl">
                            <Plus class="h-4 w-4" />
                            Registrar Reclamo
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
                        :placeholder="isClient ? 'Buscar por descripción o tipo...' : 'Buscar por descripción, tipo o cliente...'"
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-muted-foreground shrink-0">Filtrar Estado:</span>
                    <select
                        v-model="status"
                        class="flex h-10 w-48 rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Todos los reclamos</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="en_proceso">En Proceso</option>
                        <option value="atendido">Atendido</option>
                        <option value="rechazado">Rechazado</option>
                    </select>
                </div>
            </div>

            <!-- Claims Listing -->
            <!-- Client Cards Layout -->
            <div v-if="isClient" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-if="props.reclamos.data.length === 0" class="col-span-full p-12 text-center text-muted-foreground border border-dashed rounded-2xl bg-card">
                    Aún no has registrado ningún reclamo o comentario.
                </div>
                <div
                    v-for="claim in props.reclamos.data"
                    :key="claim.id"
                    class="p-6 rounded-2xl border border-border bg-card shadow-sm hover:shadow-md transition-all duration-200 flex flex-col justify-between gap-4"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-semibold text-muted-foreground">Reclamo #{{ claim.id }}</span>
                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(claim).class]">
                                {{ getStatusBadge(claim).label }}
                            </span>
                        </div>
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-primary">{{ claim.tipo_reclamo }}</span>
                            <p class="text-xs text-muted-foreground mt-0.5">Fecha: {{ claim.fecha_reclamo }}</p>
                        </div>
                        <p class="text-sm text-foreground/80 line-clamp-3">
                            {{ claim.descripcion }}
                        </p>
                        <div v-if="claim.id_pedido" class="text-xs text-muted-foreground">
                            Pedido asociado: <span class="font-mono text-foreground font-semibold">#{{ claim.id_pedido }}</span>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-border flex items-center justify-between">
                        <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-wide">
                            {{ claim.respuesta ? 'Respondido' : 'Espera de respuesta' }}
                        </span>
                        <Link :href="route('reclamos.show', claim.id)">
                            <Button variant="outline" size="sm" class="flex items-center gap-1 rounded-lg">
                                <Eye class="h-4 w-4" />
                                Ver Detalle
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Admin/Seller Table Layout -->
            <div v-else class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">Código</th>
                                <th class="p-4">Cliente</th>
                                <th class="p-4">Tipo</th>
                                <th class="p-4">Fecha</th>
                                <th class="p-4">Pedido Asoc.</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.reclamos.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No se encontraron reclamos o comentarios registrados.
                                </td>
                            </tr>
                            <tr v-for="claim in props.reclamos.data" :key="claim.id" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4 font-mono font-medium text-muted-foreground">#{{ claim.id }}</td>
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-foreground">{{ claim.cliente.nombre }} {{ claim.cliente.apellido }}</span>
                                        <span class="text-xs text-muted-foreground">@{{ claim.cliente.username }} · C.I. {{ claim.cliente.ci }}</span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="font-medium text-foreground">{{ claim.tipo_reclamo }}</span>
                                </td>
                                <td class="p-4 text-muted-foreground">{{ claim.fecha_reclamo }}</td>
                                <td class="p-4 font-mono text-muted-foreground">
                                    {{ claim.id_pedido ? `#${claim.id_pedido}` : 'Ninguno' }}
                                </td>
                                <td class="p-4">
                                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', getStatusBadge(claim).class]">
                                        {{ getStatusBadge(claim).label }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('reclamos.show', claim.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg" title="Ver detalles y gestionar">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button
                                            v-if="$page.props.auth.permissions.includes('reclamos.eliminar')"
                                            @click="confirmDelete(claim)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar reclamo"
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
                <div v-if="props.reclamos.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.reclamos.current_page }} de {{ props.reclamos.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.reclamos.prev_page_url" :href="props.reclamos.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.reclamos.next_page_url" :href="props.reclamos.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!claimToDelete" @update:open="(val) => !val && (claimToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Reclamo?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>eliminación lógica</strong> del reclamo <strong>#{{ claimToDelete?.id }}</strong>. Dejará de mostrarse en las listas públicas y de gestión.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="claimToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteClaim" class="rounded-xl">Eliminar Lógicamente</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
