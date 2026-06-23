<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    TrendingUp, Users, Package, AlertCircle, ShoppingCart,
    Star, MessageSquare, Truck, CheckCircle, Clock, DollarSign,
    BarChart2, Activity,
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

// ─── Props ────────────────────────────────────────────────────────────────────
interface StaffKpis {
    ventas_totales: number;
    total_clientes: number;
    bajo_stock: number;
    reclamos_activos: number;
}

interface ClienteKpis {
    total_gastado: number;
    pedidos_activos: number;
    calificaciones_hechas: number;
    reclamos_realizados: number;
}

interface DistribuidorKpis {
    envios_pendientes: number;
    envios_entregados: number;
}

interface SalePoint {
    date: string;
    revenue: number;
}

interface VisitaItem {
    ruta: string;
    contador: number;
}

interface PedidoReciente {
    id: number;
    fecha_pedido: string;
    total: number;
    estado_pedido: string;
}

interface CategoriaItem {
    categoria: string;
    cantidad: number;
}

interface EnvioReciente {
    id: number;
    id_pedido: number;
    cliente: string;
    estado_envio: string;
    fecha_salida: string;
}

interface EnvioPoint {
    date: string;
    count: number;
}

const props = defineProps<{
    role_type: 'staff' | 'cliente' | 'distribuidor' | 'default';
    role: string;
    user_name: string;
    kpis?: StaffKpis | ClienteKpis | DistribuidorKpis;
    // Staff
    ventas_diarias?: SalePoint[];
    visitas?: VisitaItem[];
    // Cliente
    recientes?: PedidoReciente[] | EnvioReciente[];
    categorias?: CategoriaItem[];
    // Distribuidor
    envios_diarios?: EnvioPoint[];
}>();

// ─── Typed accessors ──────────────────────────────────────────────────────────
const staffKpis = computed(() => props.kpis as StaffKpis | undefined);
const clienteKpis = computed(() => props.kpis as ClienteKpis | undefined);
const distribuidorKpis = computed(() => props.kpis as DistribuidorKpis | undefined);
const pedidosRecientes = computed(() => (props.recientes as PedidoReciente[] | undefined) ?? []);
const enviosRecientes = computed(() => (props.recientes as EnvioReciente[] | undefined) ?? []);

// ─── SVG Line Chart helpers ───────────────────────────────────────────────────
function buildLinePath(
    data: { value: number }[],
    width: number,
    height: number,
    padding = 20,
): string {
    if (!data.length) return '';
    const values = data.map((d) => d.value);
    const minV = Math.min(...values);
    const maxV = Math.max(...values);
    const range = maxV - minV || 1;
    const w = width - padding * 2;
    const h = height - padding * 2;
    const pts = data.map((d, i) => {
        const x = padding + (i / (data.length - 1 || 1)) * w;
        const y = padding + h - ((d.value - minV) / range) * h;
        return `${x},${y}`;
    });
    return `M ${pts.join(' L ')}`;
}

function buildAreaPath(
    data: { value: number }[],
    width: number,
    height: number,
    padding = 20,
): string {
    if (!data.length) return '';
    const line = buildLinePath(data, width, height, padding);
    const w = width - padding * 2;
    const lastX = padding + w;
    return `${line} L ${lastX},${height - padding} L ${padding},${height - padding} Z`;
}

const ventasPoints = computed(() =>
    (props.ventas_diarias ?? []).map((d) => ({ label: d.date, value: d.revenue })),
);
const enviosPoints = computed(() =>
    (props.envios_diarios ?? []).map((d) => ({ label: d.date, value: d.count })),
);

const ventasLinePath = computed(() => buildLinePath(ventasPoints.value, 400, 120));
const ventasAreaPath = computed(() => buildAreaPath(ventasPoints.value, 400, 120));
const enviosLinePath = computed(() => buildLinePath(enviosPoints.value, 400, 120));
const enviosAreaPath = computed(() => buildAreaPath(enviosPoints.value, 400, 120));

// ─── Bar chart for visits ─────────────────────────────────────────────────────
const visitaBarMax = computed(() =>
    Math.max(...(props.visitas ?? []).map((v) => v.contador), 1),
);

// ─── Donut chart for categories ───────────────────────────────────────────────
const DONUT_COLORS = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'];
const donutPaths = computed(() => {
    const cats = props.categorias ?? [];
    const total = cats.reduce((s, c) => s + c.cantidad, 0) || 1;
    let startAngle = -Math.PI / 2;
    return cats.map((cat, i) => {
        const slice = (cat.cantidad / total) * 2 * Math.PI;
        const endAngle = startAngle + slice;
        const R = 60;
        const cx = 80;
        const cy = 80;
        const x1 = cx + R * Math.cos(startAngle);
        const y1 = cy + R * Math.sin(startAngle);
        const x2 = cx + R * Math.cos(endAngle);
        const y2 = cy + R * Math.sin(endAngle);
        const large = slice > Math.PI ? 1 : 0;
        const d = `M ${cx},${cy} L ${x1},${y1} A ${R},${R} 0 ${large},1 ${x2},${y2} Z`;
        const result = { d, color: DONUT_COLORS[i % DONUT_COLORS.length], label: cat.categoria };
        startAngle = endAngle;
        return result;
    });
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function fmt(n: number): string {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n);
}

function fmtDate(d: string): string {
    if (!d) return '-';
    return new Date(d + 'T00:00:00').toLocaleDateString('es-AR', { day: '2-digit', month: 'short' });
}

const statusColor: Record<string, string> = {
    pendiente:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    confirmado:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    entregado:   'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    en_transito: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    cancelado:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">

            <!-- Welcome Banner -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 p-6 text-white shadow-xl">
                <div class="absolute inset-0 opacity-10">
                    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse">
                                <path d="M 30 0 L 0 0 0 30" fill="none" stroke="white" stroke-width="0.5"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#grid)" />
                    </svg>
                </div>
                <div class="relative z-10 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/70">Bienvenido/a</p>
                        <h1 class="mt-1 text-2xl font-bold">{{ user_name }}</h1>
                        <p class="mt-1 text-sm capitalize text-white/60">{{ role }}</p>
                    </div>
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                        <BarChart2 class="h-8 w-8 text-white" />
                    </div>
                </div>
            </div>

            <!-- ═══════════════════ STAFF DASHBOARD ═══════════════════ -->
            <template v-if="role_type === 'staff'">

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <!-- Ventas totales -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Ventas totales</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40">
                                <DollarSign class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ fmt(staffKpis?.ventas_totales ?? 0) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">pedidos activos</p>
                    </div>

                    <!-- Clientes -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Clientes</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-900/40">
                                <Users class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ staffKpis?.total_clientes ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">registrados activos</p>
                    </div>

                    <!-- Bajo stock -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Bajo stock</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/40">
                                <Package class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ staffKpis?.bajo_stock ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">productos ≤ 5 unid.</p>
                    </div>

                    <!-- Reclamos activos -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Reclamos</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/40">
                                <AlertCircle class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ staffKpis?.reclamos_activos ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">pendientes de atención</p>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid gap-4 md:grid-cols-2">

                    <!-- Sales Trend -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center gap-2">
                            <TrendingUp class="h-4 w-4 text-indigo-500" />
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Ventas últimos 7 días</h2>
                        </div>
                        <div v-if="ventasPoints.length > 1">
                            <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#6366f1" stop-opacity="0.3"/>
                                        <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                                <path :d="ventasAreaPath" fill="url(#salesGrad)" />
                                <path :d="ventasLinePath" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                <circle
                                    v-for="(pt, i) in ventasPoints"
                                    :key="i"
                                    :cx="20 + (i / (ventasPoints.length - 1)) * 360"
                                    :cy="20 + 80 - ((pt.value - Math.min(...ventasPoints.map(p => p.value))) / (Math.max(...ventasPoints.map(p => p.value)) - Math.min(...ventasPoints.map(p => p.value)) || 1)) * 80"
                                    r="3"
                                    fill="#6366f1"
                                />
                            </svg>
                            <div class="mt-2 flex justify-between">
                                <span v-for="(pt, i) in ventasPoints" :key="i" class="text-[10px] text-gray-400">{{ fmtDate(pt.label) }}</span>
                            </div>
                        </div>
                        <div v-else class="flex h-28 items-center justify-center text-sm text-gray-400">
                            Sin datos suficientes
                        </div>
                    </div>

                    <!-- Top Visited Pages Bar Chart -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center gap-2">
                            <Activity class="h-4 w-4 text-violet-500" />
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Top páginas visitadas</h2>
                        </div>
                        <div v-if="visitas && visitas.length" class="space-y-3">
                            <div v-for="v in visitas" :key="v.ruta" class="flex items-center gap-3">
                                <span class="w-32 truncate text-xs text-gray-500 dark:text-gray-400">{{ v.ruta }}</span>
                                <div class="flex-1 rounded-full bg-gray-100 dark:bg-zinc-800" style="height:8px">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-violet-500 to-indigo-500 transition-all"
                                        :style="{ width: ((v.contador / visitaBarMax) * 100) + '%' }"
                                    />
                                </div>
                                <span class="w-8 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">{{ v.contador }}</span>
                            </div>
                        </div>
                        <div v-else class="flex h-28 items-center justify-center text-sm text-gray-400">
                            Sin visitas registradas
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Link href="/pedidos" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <ShoppingCart class="h-5 w-5 text-indigo-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Pedidos</span>
                    </Link>
                    <Link href="/productos" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-violet-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <Package class="h-5 w-5 text-violet-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Productos</span>
                    </Link>
                    <Link href="/reclamos" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-rose-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <AlertCircle class="h-5 w-5 text-rose-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Reclamos</span>
                    </Link>
                    <Link href="/estadisticas" class="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <BarChart2 class="h-5 w-5 text-emerald-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Estadísticas</span>
                    </Link>
                </div>

            </template>

            <!-- ═══════════════════ CLIENTE DASHBOARD ═══════════════════ -->
            <template v-else-if="role_type === 'cliente'">

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Total gastado</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40">
                                <DollarSign class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ fmt(clienteKpis?.total_gastado ?? 0) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">en compras realizadas</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Pedidos activos</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-900/40">
                                <ShoppingCart class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ clienteKpis?.pedidos_activos ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">pendientes / confirmados</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Calificaciones</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/40">
                                <Star class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ clienteKpis?.calificaciones_hechas ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">reseñas publicadas</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Reclamos</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/40">
                                <MessageSquare class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ clienteKpis?.reclamos_realizados ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">realizados en total</p>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid gap-4 md:grid-cols-2">

                    <!-- Recent Orders -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <ShoppingCart class="h-4 w-4 text-indigo-500" />
                                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Últimos pedidos</h2>
                            </div>
                            <Link href="/pedidos" class="text-xs text-indigo-500 hover:underline">Ver todos →</Link>
                        </div>
                        <div v-if="pedidosRecientes.length" class="space-y-3">
                            <div
                                v-for="p in pedidosRecientes"
                                :key="(p as any).id"
                                class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 dark:bg-zinc-800"
                            >
                                <div>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">#{{ (p as any).id }}</p>
                                    <p class="text-xs text-gray-400">{{ fmtDate((p as any).fecha_pedido) }}</p>
                                </div>
                                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize', statusColor[(p as any).estado_pedido] ?? 'bg-gray-100 text-gray-600']">
                                    {{ (p as any).estado_pedido }}
                                </span>
                                <p class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ fmt((p as any).total) }}</p>
                            </div>
                        </div>
                        <p v-else class="py-6 text-center text-sm text-gray-400">Sin pedidos recientes</p>
                    </div>

                    <!-- Categories Donut -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center gap-2">
                            <BarChart2 class="h-4 w-4 text-violet-500" />
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Mis categorías compradas</h2>
                        </div>
                        <div v-if="categorias && categorias.length" class="flex items-center gap-6">
                            <svg viewBox="0 0 160 160" class="h-32 w-32 shrink-0">
                                <path v-for="(seg, i) in donutPaths" :key="i" :d="seg.d" :fill="seg.color" />
                                <circle cx="80" cy="80" r="38" fill="white" class="dark:fill-zinc-900" />
                            </svg>
                            <ul class="space-y-1.5">
                                <li v-for="(seg, i) in donutPaths" :key="i" class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: seg.color }"></span>
                                    <span class="capitalize">{{ seg.label ?? 'Sin categoría' }}</span>
                                </li>
                            </ul>
                        </div>
                        <p v-else class="py-6 text-center text-sm text-gray-400">Aún no hay compras registradas</p>
                    </div>
                </div>

                <!-- Quick links -->
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <Link href="/pedidos/create" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <ShoppingCart class="h-5 w-5 text-indigo-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Nuevo pedido</span>
                    </Link>
                    <Link href="/reclamos/create" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-rose-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <MessageSquare class="h-5 w-5 text-rose-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Nuevo reclamo</span>
                    </Link>
                    <Link href="/puntuaciones/create" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-amber-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                        <Star class="h-5 w-5 text-amber-500" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Calificar producto</span>
                    </Link>
                </div>

            </template>

            <!-- ═══════════════════ DISTRIBUIDOR DASHBOARD ═══════════════════ -->
            <template v-else-if="role_type === 'distribuidor'">

                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Envíos pendientes</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/40">
                                <Clock class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ distribuidorKpis?.envios_pendientes ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">pendiente / en tránsito</p>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Entregados</span>
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-green-100 text-green-600 dark:bg-green-900/40">
                                <CheckCircle class="h-4 w-4" />
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ distribuidorKpis?.envios_entregados ?? 0 }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">entregas completadas</p>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid gap-4 md:grid-cols-2">

                    <!-- Delivery Trend -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center gap-2">
                            <TrendingUp class="h-4 w-4 text-emerald-500" />
                            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Entregas últimos 7 días</h2>
                        </div>
                        <div v-if="enviosPoints.length > 1">
                            <svg viewBox="0 0 400 120" class="w-full" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="envioGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.3"/>
                                        <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                                    </linearGradient>
                                </defs>
                                <path :d="enviosAreaPath" fill="url(#envioGrad)" />
                                <path :d="enviosLinePath" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                <circle
                                    v-for="(pt, i) in enviosPoints"
                                    :key="i"
                                    :cx="20 + (i / (enviosPoints.length - 1)) * 360"
                                    :cy="20 + 80 - ((pt.value - Math.min(...enviosPoints.map(p => p.value))) / (Math.max(...enviosPoints.map(p => p.value)) - Math.min(...enviosPoints.map(p => p.value)) || 1)) * 80"
                                    r="3"
                                    fill="#10b981"
                                />
                            </svg>
                            <div class="mt-2 flex justify-between">
                                <span v-for="(pt, i) in enviosPoints" :key="i" class="text-[10px] text-gray-400">{{ fmtDate(pt.label) }}</span>
                            </div>
                        </div>
                        <div v-else class="flex h-28 items-center justify-center text-sm text-gray-400">
                            Sin datos suficientes
                        </div>
                    </div>

                    <!-- Recent Shipments -->
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Truck class="h-4 w-4 text-indigo-500" />
                                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Últimos envíos</h2>
                            </div>
                            <Link href="/envios" class="text-xs text-indigo-500 hover:underline">Ver todos →</Link>
                        </div>
                        <div v-if="enviosRecientes.length" class="space-y-3">
                            <div
                                v-for="e in enviosRecientes"
                                :key="(e as any).id"
                                class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 dark:bg-zinc-800"
                            >
                                <div>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-200">Pedido #{{ (e as any).id_pedido }}</p>
                                    <p class="text-xs text-gray-400">{{ (e as any).cliente }}</p>
                                </div>
                                <span :class="['rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize', statusColor[(e as any).estado_envio] ?? 'bg-gray-100 text-gray-600']">
                                    {{ (e as any).estado_envio?.replace('_', ' ') }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="py-6 text-center text-sm text-gray-400">Sin envíos recientes</p>
                    </div>
                </div>

            </template>

            <!-- ═══════════════════ DEFAULT ═══════════════════ -->
            <template v-else>
                <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white py-16 dark:border-zinc-700 dark:bg-zinc-900">
                    <BarChart2 class="h-12 w-12 text-gray-300 dark:text-zinc-600" />
                    <p class="mt-3 text-sm text-gray-400">Sin estadísticas disponibles para tu rol.</p>
                </div>
            </template>

        </div>
    </AppLayout>
</template>
