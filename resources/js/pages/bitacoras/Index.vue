<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import {
    History, Search, Filter, Calendar, RefreshCw, X, Eye, ShieldAlert,
    User, HardDrive, HelpCircle, Terminal, Activity, FileText, ArrowRight, Laptop
} from 'lucide-vue-next';

// ─── Interfaces ───────────────────────────────────────────────────────────────
interface LogUser {
    id: number;
    username: string;
    nombre: string;
    apellido: string;
    role?: { nombre: string };
}

interface LogEntry {
    id: number;
    id_usuario: number | null;
    evento: string;
    ip: string | null;
    recurso: string | null;
    detalle: string | null;
    user_agent: string | null;
    created_at: string;
    usuario: LogUser | null;
}

interface PaginatedLogs {
    data: LogEntry[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface FilterType {
    search?: string;
    user_id?: string;
    evento?: string;
    fecha_inicio?: string;
    fecha_fin?: string;
}

const props = defineProps<{
    logs: PaginatedLogs;
    usuarios: { id: number; nombre: string; apellido: string; username: string }[];
    eventos_unicos: string[];
    kpis: {
        total_registros: number;
        usuario_activo: { name: string; username: string; count: number } | null;
        evento_frecuente: { evento: string; count: number } | null;
        acciones_modificacion: number;
    };
    filters: FilterType;
}>();

// ─── Reactive Filter States ───────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const userId = ref(props.filters.user_id ?? '');
const evento = ref(props.filters.evento ?? '');
const fechaInicio = ref(props.filters.fecha_inicio ?? '');
const fechaFin = ref(props.filters.fecha_fin ?? '');

// Active selected log for details modal
const selectedLog = ref<LogEntry | null>(null);

// Apply filters function
const applyFilters = () => {
    router.get(
        route('bitacoras.index'),
        {
            search: search.value || undefined,
            user_id: userId.value || undefined,
            evento: evento.value || undefined,
            fecha_inicio: fechaInicio.value || undefined,
            fecha_fin: fechaFin.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

// Debounced watch triggers (only applying search when user presses Enter or clicks button, but selectors apply instantly)
watch([userId, evento, fechaInicio, fechaFin], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    userId.value = '';
    evento.value = '';
    fechaInicio.value = '';
    fechaFin.value = '';
    router.get(route('bitacoras.index'), {}, { replace: true });
};

// ─── Humanizing helpers ───────────────────────────────────────────────────────
function getEventBadgeColor(ev: string): string {
    const e = ev.toLowerCase();
    if (e.includes('eliminar') || e.includes('delete') || e.includes('fail') || e.includes('cancel')) {
        return 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20';
    }
    if (e.includes('crear') || e.includes('store') || e.includes('post') || e.includes('confirmar')) {
        return 'bg-green-500/10 text-green-600 dark:text-green-400 border border-green-500/20';
    }
    if (e.includes('editar') || e.includes('actualizar') || e.includes('update') || e.includes('put') || e.includes('patch')) {
        return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
    }
    if (e.includes('login') || e.includes('sesion') || e.includes('auth')) {
        return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20';
    }
    if (e.includes('report') || e.includes('estadistica') || e.includes('ver_bitacora')) {
        return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20';
    }
    return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/10';
}

function humanizeEvent(ev: string): string {
    const e = ev.toLowerCase();
    // Replacements
    if (e === 'ver_bitacora') return 'Visualizó Bitácora';
    if (e === 'ver_reportes') return 'Consultó Estadísticas';
    if (e === 'login') return 'Inicio de Sesión Exitoso';
    if (e === 'login_failed') return 'Intento Fallido de Sesión';
    if (e === 'acceso_recurso') return 'Acceso a Módulo';
    if (e === 'crear_producto') return 'Creó Producto';
    if (e === 'editar_producto') return 'Editó Producto';
    if (e === 'eliminar_producto') return 'Eliminó Producto';
    if (e === 'crear_pedido') return 'Registró Pedido';
    if (e === 'actualizar_estado_pedido') return 'Actualizó Estado Pedido';
    if (e === 'cancelar_pedido') return 'Canceló Pedido';
    if (e === 'eliminar_pedido') return 'Eliminó Registro Pedido';
    if (e === 'crear_pago') return 'Registró Pago';
    if (e === 'confirmar_pago') return 'Confirmó Pago (Efectivo)';
    if (e === 'callback_pagofacil') return 'Confirmó Pago (QR PagoFacil)';
    if (e === 'eliminar_pago') return 'Eliminó Registro Pago';
    if (e === 'crear_reclamo') return 'Registró Reclamo';
    if (e === 'atender_reclamo') return 'Atendió / Respondió Reclamo';
    if (e === 'eliminar_reclamo') return 'Eliminó Reclamo';
    if (e === 'crear_oferta') return 'Creó Oferta';
    if (e === 'editar_oferta') return 'Editó Oferta';
    if (e === 'eliminar_oferta') return 'Eliminó Oferta';
    if (e === 'crear_rol') return 'Creó Rol de Acceso';
    if (e === 'editar_rol') return 'Editó Rol de Acceso';
    if (e === 'eliminar_rol') return 'Eliminó Rol de Acceso';
    if (e === 'crear_usuario') return 'Creó Cuenta Usuario';
    if (e === 'editar_usuario') return 'Editó Cuenta Usuario';
    if (e === 'eliminar_usuario') return 'Eliminó Cuenta Usuario';
    if (e === 'crear_envio') return 'Generó Envío';
    if (e === 'actualizar_estado_envio') return 'Actualizó Estado Envío';
    if (e === 'asignar_distribuidor') return 'Asignó Distribuidor';
    if (e === 'eliminar_envio') return 'Eliminó Registro Envío';
    if (e === 'crear_puntuacion') return 'Creó Calificación';
    if (e === 'eliminar_puntuacion') return 'Eliminó Calificación';
    
    // Method actions
    if (e === 'accion_post') return 'Creación de Registro';
    if (e === 'accion_put' || e === 'accion_patch') return 'Modificación de Registro';
    if (e === 'accion_delete') return 'Eliminación de Registro';

    return ev.replace('_', ' ');
}

// User Agent parser helper for clean browser/OS representations
function parseUserAgent(ua: string | null): string {
    if (!ua) return 'Desconocido';
    const lower = ua.toLowerCase();
    let browser = 'Explorador';
    let os = 'OS';

    // Browser detection
    if (lower.includes('firefox')) browser = 'Firefox';
    else if (lower.includes('chrome') && !lower.includes('chromium')) browser = 'Chrome';
    else if (lower.includes('safari') && !lower.includes('chrome')) browser = 'Safari';
    else if (lower.includes('edge') || lower.includes('edg')) browser = 'Edge';
    else if (lower.includes('opera') || lower.includes('opr')) browser = 'Opera';

    // OS detection
    if (lower.includes('windows')) os = 'Windows';
    else if (lower.includes('macintosh') || lower.includes('mac os')) os = 'macOS';
    else if (lower.includes('linux') && !lower.includes('android')) os = 'Linux';
    else if (lower.includes('android')) os = 'Android';
    else if (lower.includes('iphone') || lower.includes('ipad')) os = 'iOS';

    return `${browser} en ${os}`;
}

// Format JSON Details helper
const parsedDetails = computed(() => {
    if (!selectedLog.value || !selectedLog.value.detalle) return null;
    try {
        const parsed = JSON.parse(selectedLog.value.detalle);
        if (typeof parsed === 'object' && parsed !== null) {
            return parsed;
        }
        return null;
    } catch {
        return null;
    }
});

function formatDate(d: string): string {
    return new Date(d).toLocaleString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Bitácora', href: '/bitacoras' }]">
        <Head title="Bitácora del Sistema" />

        <div class="space-y-6 max-w-7xl mx-auto pb-8">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <History class="h-8 w-8 text-primary" />
                        Bitácora de Eventos
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Auditoría y registro completo de accesos, modificaciones, altas y bajas realizadas en el sistema.
                    </p>
                </div>
                <div>
                    <Button variant="outline" size="sm" @click="clearFilters" class="h-10 rounded-xl gap-1.5 shadow-sm border-border bg-card">
                        <RefreshCw class="h-4 w-4" />
                        Restablecer Filtros
                    </Button>
                </div>
            </div>

            <!-- KPIs Header Widgets -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <!-- Total entries -->
                <Card class="hover:scale-[1.01] transition-transform">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Total Eventos</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-500">
                            <Activity class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-foreground font-mono">
                            {{ kpis.total_registros }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">registrados en total</p>
                    </CardContent>
                </Card>

                <!-- Modification counts -->
                <Card class="hover:scale-[1.01] transition-transform">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Modificaciones</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <FileText class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono">
                            {{ kpis.acciones_modificacion }}
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">creaciones / ediciones / bajas</p>
                    </CardContent>
                </Card>

                <!-- Most Active User -->
                <Card class="hover:scale-[1.01] transition-transform">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Usuario Más Activo</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-500/10 text-violet-500">
                            <User class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis.usuario_activo" class="truncate text-base font-black text-foreground" :title="kpis.usuario_activo.name">
                            {{ kpis.usuario_activo.name }}
                        </div>
                        <div v-else class="text-sm font-bold text-muted-foreground">-</div>
                        <p class="mt-1 text-xs text-muted-foreground font-mono" v-if="kpis.usuario_activo">
                            {{ kpis.usuario_activo.count }} eventos registrados
                        </p>
                    </CardContent>
                </Card>

                <!-- Most Frequent Event -->
                <Card class="hover:scale-[1.01] transition-transform">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Evento Frecuente</CardTitle>
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500">
                            <Terminal class="h-4 w-4" />
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div v-if="kpis.evento_frecuente" class="truncate text-xs font-bold text-foreground capitalize" :title="kpis.evento_frecuente.evento">
                            {{ humanizeEvent(kpis.evento_frecuente.evento) }}
                        </div>
                        <div v-else class="text-sm font-bold text-muted-foreground">-</div>
                        <p class="mt-1 text-xs text-muted-foreground font-mono" v-if="kpis.evento_frecuente">
                            {{ kpis.evento_frecuente.count }} repeticiones
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters Grid -->
            <div class="p-5 rounded-xl border border-border bg-card shadow-sm space-y-4">
                <div class="flex items-center gap-2 border-b border-border/60 pb-2">
                    <Filter class="h-4 w-4 text-primary" />
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Filtros de Búsqueda</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <!-- Text Search -->
                    <div class="md:col-span-2 space-y-1.5">
                        <label for="search-input" class="text-xs font-bold text-muted-foreground">Búsqueda general</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="search-input"
                                v-model="search"
                                type="text"
                                placeholder="Buscar por evento, IP, detalle..."
                                class="pl-9 h-10 w-full rounded-xl bg-background border-border shadow-sm"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- User Filter -->
                    <div class="space-y-1.5">
                        <label for="user-filter" class="text-xs font-bold text-muted-foreground">Usuario</label>
                        <select
                            id="user-filter"
                            v-model="userId"
                            class="h-10 w-full rounded-xl border border-border bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="">Todos los usuarios</option>
                            <option v-for="user in props.usuarios" :key="user.id" :value="user.id.toString()">
                                {{ user.nombre }} {{ user.apellido }} (@{{ user.username }})
                            </option>
                        </select>
                    </div>

                    <!-- Event Filter -->
                    <div class="space-y-1.5">
                        <label for="event-filter" class="text-xs font-bold text-muted-foreground">Tipo de Evento</label>
                        <select
                            id="event-filter"
                            v-model="evento"
                            class="h-10 w-full rounded-xl border border-border bg-background px-3 text-sm capitalize focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                        >
                            <option value="">Todos los eventos</option>
                            <option v-for="ev in props.eventos_unicos" :key="ev" :value="ev">
                                {{ humanizeEvent(ev) }}
                            </option>
                        </select>
                    </div>

                    <!-- Apply button wrapper -->
                    <div class="flex items-end">
                        <Button @click="applyFilters" class="w-full h-10 rounded-xl font-bold shadow-sm">
                            Buscar
                        </Button>
                    </div>
                </div>

                <!-- Date Range Filters -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-border/40 pt-4">
                    <div class="space-y-1.5">
                        <label for="date-start" class="text-xs font-bold text-muted-foreground flex items-center gap-1">
                            <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                            Rango Desde
                        </label>
                        <Input
                            id="date-start"
                            type="date"
                            v-model="fechaInicio"
                            class="h-10 rounded-xl bg-background border-border"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label for="date-end" class="text-xs font-bold text-muted-foreground flex items-center gap-1">
                            <Calendar class="h-3.5 w-3.5 text-muted-foreground" />
                            Rango Hasta
                        </label>
                        <Input
                            id="date-end"
                            type="date"
                            v-model="fechaFin"
                            class="h-10 rounded-xl bg-background border-border"
                        />
                    </div>
                </div>
            </div>

            <!-- Logs Table Card -->
            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-bold text-muted-foreground uppercase tracking-wider font-mono">
                                <th class="p-4">ID</th>
                                <th class="p-4">Fecha / Hora</th>
                                <th class="p-4">Usuario</th>
                                <th class="p-4">Evento</th>
                                <th class="p-4">Recurso / Ruta</th>
                                <th class="p-4">Origen IP</th>
                                <th class="p-4 text-right">Detalles</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.logs.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No se encontraron registros en la bitácora para los criterios seleccionados.
                                </td>
                            </tr>
                            <tr v-for="log in props.logs.data" :key="log.id" class="hover:bg-muted/10 transition-colors">
                                <!-- ID -->
                                <td class="p-4 font-mono font-bold text-muted-foreground text-xs">
                                    #{{ log.id }}
                                </td>
                                <!-- Date -->
                                <td class="p-4 whitespace-nowrap text-xs text-foreground font-medium">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <!-- User -->
                                <td class="p-4 whitespace-nowrap">
                                    <div v-if="log.usuario" class="flex flex-col">
                                        <Link :href="route('usuarios.index', { search: log.usuario.username })" class="font-bold text-foreground hover:text-primary hover:underline flex items-center gap-1">
                                            @{{ log.usuario.username }}
                                        </Link>
                                        <span class="text-[10px] text-muted-foreground uppercase font-mono tracking-wider font-semibold">
                                            {{ log.usuario.role?.nombre || 'Sin Rol' }}
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground italic">Sistema (Invitado)</span>
                                </td>
                                <!-- Event -->
                                <td class="p-4 whitespace-nowrap">
                                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize border', getEventBadgeColor(log.evento)]">
                                        {{ humanizeEvent(log.evento) }}
                                    </span>
                                </td>
                                <!-- Resource -->
                                <td class="p-4">
                                    <span class="text-xs font-mono bg-muted/60 text-muted-foreground px-2 py-1 rounded border border-border/40 truncate max-w-xs inline-block" :title="log.recurso">
                                        {{ log.recurso || '-' }}
                                    </span>
                                </td>
                                <!-- IP -->
                                <td class="p-4 font-mono text-xs text-muted-foreground">
                                    {{ log.ip || '-' }}
                                </td>
                                <!-- Details Trigger -->
                                <td class="p-4 text-right">
                                    <Button
                                        @click="selectedLog = log"
                                        variant="outline"
                                        size="sm"
                                        class="h-8 w-8 p-0 rounded-lg border-border bg-card shadow-sm hover:scale-105 transition-transform"
                                        title="Ver detalles completos"
                                    >
                                        <Eye class="h-4 w-4 text-muted-foreground hover:text-primary" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div v-if="props.logs.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground font-mono">
                        Página {{ props.logs.current_page }} de {{ props.logs.last_page }}
                    </span>
                    <div class="flex items-center gap-1 font-mono">
                        <Link v-if="props.logs.prev_page_url" :href="props.logs.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg shadow-sm">Anterior</Button>
                        </Link>
                        <Link v-if="props.logs.next_page_url" :href="props.logs.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg shadow-sm">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Modal Dialog -->
        <Dialog :open="!!selectedLog" @update:open="(val) => !val && (selectedLog = null)">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-foreground font-mono">
                        <Terminal class="h-5 w-5 text-primary" />
                        Detalle del Evento #{{ selectedLog?.id }}
                    </DialogTitle>
                    <DialogDescription>
                        Información completa de auditoría registrada para esta acción del sistema.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1">
                    <!-- General Details Grid -->
                    <div class="grid grid-cols-2 gap-4 bg-muted/30 p-4 rounded-xl border border-border/40 text-xs">
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Fecha de Registro</span>
                            <span class="text-foreground font-medium font-mono" v-if="selectedLog">
                                {{ formatDate(selectedLog.created_at) }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Tipo de Evento</span>
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full font-bold border capitalize text-[10px]', selectedLog ? getEventBadgeColor(selectedLog.evento) : '']">
                                {{ selectedLog ? humanizeEvent(selectedLog.evento) : '' }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Dirección IP</span>
                            <span class="text-foreground font-mono font-medium">{{ selectedLog?.ip || 'Local/Desconocida' }}</span>
                        </div>
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Módulo / Ruta</span>
                            <span class="text-foreground font-mono font-medium truncate block" :title="selectedLog?.recurso">{{ selectedLog?.recurso || '-' }}</span>
                        </div>
                    </div>

                    <!-- Actor Profile -->
                    <div class="p-4 border border-border bg-card rounded-xl">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground mb-3 flex items-center gap-1.5">
                            <User class="h-3.5 w-3.5 text-primary" />
                            Actor (Usuario Responsable)
                        </h4>
                        <div v-if="selectedLog?.usuario" class="flex items-center justify-between text-xs">
                            <div>
                                <p class="text-sm font-black text-foreground">{{ selectedLog.usuario.nombre }} {{ selectedLog.usuario.apellido }}</p>
                                <p class="text-muted-foreground font-mono mt-0.5">@{{ selectedLog.usuario.username }}</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-bold bg-primary/10 text-primary uppercase text-[10px]">
                                    {{ selectedLog.usuario.role?.nombre || 'Sin Rol' }}
                                </span>
                                <Link :href="route('usuarios.index', { search: selectedLog.usuario.username })" class="block text-[11px] text-indigo-500 hover:underline mt-1 font-semibold">
                                    Ver perfil usuario →
                                </Link>
                            </div>
                        </div>
                        <div v-else class="flex items-center gap-2 text-xs text-muted-foreground italic py-1">
                            <Laptop class="h-4 w-4" />
                            Acción realizada por un usuario invitado o un proceso automatizado del sistema.
                        </div>
                    </div>

                    <!-- Client User Agent -->
                    <div class="p-4 border border-border bg-card rounded-xl text-xs space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <HardDrive class="h-3.5 w-3.5 text-primary" />
                            Dispositivo & Agente de Usuario
                        </h4>
                        <p class="font-medium text-foreground flex items-center gap-1.5">
                            <Laptop class="h-3.5 w-3.5 text-muted-foreground" />
                            {{ parseUserAgent(selectedLog?.user_agent ?? null) }}
                        </p>
                        <p class="font-mono text-[10px] text-muted-foreground bg-muted/40 p-2.5 rounded border border-border/30 select-all break-all">
                            {{ selectedLog?.user_agent }}
                        </p>
                    </div>

                    <!-- JSON / Payload Details -->
                    <div class="p-4 border border-border bg-card rounded-xl space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                            <FileText class="h-3.5 w-3.5 text-primary" />
                            Carga de Datos / Detalle Técnico
                        </h4>

                        <!-- Formatted Key Value Grid if Detail is JSON -->
                        <div v-if="parsedDetails" class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                            <div
                                v-for="(val, key) in parsedDetails"
                                :key="key"
                                class="grid grid-cols-3 gap-2 border-b border-border/40 pb-1.5 last:border-0 last:pb-0 text-xs font-mono"
                            >
                                <span class="text-muted-foreground font-semibold truncate">{{ key }}</span>
                                <span class="col-span-2 text-foreground font-medium select-all break-all">
                                    {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                                </span>
                            </div>
                        </div>

                        <!-- Fallback to raw text -->
                        <div v-else class="font-mono text-xs select-all text-muted-foreground bg-muted/40 p-3 rounded border border-border/30 break-all whitespace-pre-wrap max-h-48 overflow-y-auto">
                            {{ selectedLog?.detalle || 'Sin parámetros de detalle registrados.' }}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button @click="selectedLog = null" class="rounded-xl px-5 font-bold shadow-sm">
                        Cerrar Detalle
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
