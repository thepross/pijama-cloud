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


const search = ref(props.filters.search ?? '');
const userId = ref(props.filters.user_id ?? '');
const evento = ref(props.filters.evento ?? '');
const fechaInicio = ref(props.filters.fecha_inicio ?? '');
const fechaFin = ref(props.filters.fecha_fin ?? '');


const selectedLog = ref<LogEntry | null>(null);


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
    
    
    if (e === 'accion_post') return 'Creación de Registro';
    if (e === 'accion_put' || e === 'accion_patch') return 'Modificación de Registro';
    if (e === 'accion_delete') return 'Eliminación de Registro';

    return ev.replace('_', ' ');
}


function parseUserAgent(ua: string | null): string {
    if (!ua) return 'Desconocido';
    const lower = ua.toLowerCase();
    let browser = 'Explorador';
    let os = 'OS';

    
    if (lower.includes('firefox')) browser = 'Firefox';
    else if (lower.includes('chrome') && !lower.includes('chromium')) browser = 'Chrome';
    else if (lower.includes('safari') && !lower.includes('chrome')) browser = 'Safari';
    else if (lower.includes('edge') || lower.includes('edg')) browser = 'Edge';
    else if (lower.includes('opera') || lower.includes('opr')) browser = 'Opera';

    
    if (lower.includes('windows')) os = 'Windows';
    else if (lower.includes('macintosh') || lower.includes('mac os')) os = 'macOS';
    else if (lower.includes('linux') && !lower.includes('android')) os = 'Linux';
    else if (lower.includes('android')) os = 'Android';
    else if (lower.includes('iphone') || lower.includes('ipad')) os = 'iOS';

    return `${browser} en ${os}`;
}


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
            
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Bitácora de Eventos
                    </h1>

                </div>
                <div>
                    <Button variant="outline" size="sm" @click="clearFilters" class="h-10 rounded-xl gap-1.5 shadow-sm border-border bg-card">
                        <RefreshCw class="h-4 w-4" />
                        Restablecer Filtros
                    </Button>
                </div>
            </div>

            


            
            <div class="p-5 rounded-xl border border-border bg-card shadow-sm space-y-4">
                <div class="flex items-center gap-2 border-b border-border/60 pb-2">
                    <Filter class="h-4 w-4 text-primary" />
                    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Filtros de Búsqueda</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    
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

                    
                    <div class="flex items-end">
                        <Button @click="applyFilters" class="w-full h-10 rounded-xl font-bold shadow-sm">
                            Buscar
                        </Button>
                    </div>
                </div>

                
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
                                
                                <td class="p-4 font-mono font-bold text-muted-foreground text-xs">
                                    #{{ log.id }}
                                </td>
                                
                                <td class="p-4 whitespace-nowrap text-xs text-foreground font-medium">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                
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
                                
                                <td class="p-4 whitespace-nowrap">
                                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize border', getEventBadgeColor(log.evento)]">
                                        {{ humanizeEvent(log.evento) }}
                                    </span>
                                </td>
                                
                                <td class="p-4">
                                    <span class="text-xs font-mono bg-muted/60 text-muted-foreground px-2 py-1 rounded border border-border/40 truncate max-w-xs inline-block" :title="log.recurso">
                                        {{ log.recurso || '-' }}
                                    </span>
                                </td>
                                
                                <td class="p-4 font-mono text-xs text-muted-foreground">
                                    {{ log.ip || '-' }}
                                </td>
                                
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

        
        <Dialog :open="!!selectedLog" @update:open="(val) => !val && (selectedLog = null)">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-foreground font-mono">
                        <Terminal class="h-5 w-5 text-primary" />
                        Detalle del Evento #{{ selectedLog?.id }}
                    </DialogTitle>

                </DialogHeader>

                <div class="space-y-4 max-h-[60vh] overflow-y-auto pr-1 text-sm">
                    <div class="grid grid-cols-2 gap-4 bg-muted/30 p-4 rounded-xl border border-border/40 text-xs">
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Fecha</span>
                            <span class="text-foreground font-medium font-mono" v-if="selectedLog">
                                {{ formatDate(selectedLog.created_at) }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Usuario</span>
                            <span class="text-foreground font-medium font-mono" v-if="selectedLog?.usuario">
                                @{{ selectedLog.usuario.username }} ({{ selectedLog.usuario.role?.nombre || 'Sin Rol' }})
                            </span>
                            <span class="text-foreground italic" v-else>Sistema (Invitado)</span>
                        </div>
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Evento</span>
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full font-bold border capitalize text-[10px]', selectedLog ? getEventBadgeColor(selectedLog.evento) : '']">
                                {{ selectedLog ? humanizeEvent(selectedLog.evento) : '' }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">IP / Origen</span>
                            <span class="text-foreground font-mono font-medium">{{ selectedLog?.ip || '-' }}</span>
                        </div>
                        <div class="col-span-2 space-y-1">
                            <span class="text-muted-foreground font-bold uppercase tracking-wider block">Ruta / Recurso</span>
                            <span class="text-foreground font-mono font-medium break-all block" :title="selectedLog?.recurso">{{ selectedLog?.recurso || '-' }}</span>
                        </div>
                    </div>

                    <div v-if="selectedLog?.detalle" class="p-4 border border-border bg-card rounded-xl space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                            Datos del Registro
                        </h4>
                        <div v-if="parsedDetails" class="space-y-1 text-xs font-mono max-h-48 overflow-y-auto">
                            <div
                                v-for="(val, key) in parsedDetails"
                                :key="key"
                                class="flex gap-2 py-0.5 border-b border-border/20 last:border-0"
                            >
                                <span class="text-muted-foreground font-semibold min-w-[120px] truncate">{{ key }}:</span>
                                <span class="text-foreground font-medium break-all flex-1">
                                    {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="font-mono text-xs text-muted-foreground bg-muted/40 p-3 rounded border border-border/30 break-all whitespace-pre-wrap max-h-48 overflow-y-auto">
                            {{ selectedLog.detalle }}
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
