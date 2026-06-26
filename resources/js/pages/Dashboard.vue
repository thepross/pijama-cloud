<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    TrendingUp, Users, Package, AlertCircle, ShoppingCart,
    Star, MessageSquare, Truck, CheckCircle, Clock, Banknote,
    BarChart2, Activity, ArrowUpRight
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

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
const DONUT_COLORS = ['var(--primary)', '#3b82f6', '#ec4899', '#f59e0b', '#10b981'];
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
    return 'Bs. ' + Number(n).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function fmtDate(d: string): string {
    if (!d) return '-';
    return new Date(d + 'T00:00:00').toLocaleDateString('es-AR', { day: '2-digit', month: 'short' });
}

const statusColor: Record<string, string> = {
    pendiente:   'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
    confirmado:  'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20',
    entregado:   'bg-green-500/10 text-green-600 dark:text-green-400 border border-green-500/20',
    en_transito: 'bg-violet-500/10 text-violet-600 dark:text-violet-400 border border-violet-500/20',
    cancelado:   'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20',
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- Main Layout wrapper matches all index views (space-y-6, max-w-7xl, mx-auto) -->
        <div class="space-y-6 max-w-7xl mx-auto">

            <!-- Branded Header Section (Matches Usuarios, Productos, Estadisticas views) -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Panel de Control
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Bienvenido/a de nuevo, <span class="font-semibold text-foreground">{{ user_name }}</span>. Aquí tienes el resumen de tu cuenta de <span class="capitalize text-foreground font-semibold">{{ role }}</span>.
                    </p>
                </div>
            </div>

            <!-- ═══════════════════ STAFF DASHBOARD ═══════════════════ -->
            <template v-if="role_type === 'staff'">

                <!-- KPI Cards Grid -->
                <div class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                    <!-- Ventas totales -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Ventas Totales</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ fmt(staffKpis?.ventas_totales ?? 0) }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">pedidos activos</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <Banknote class="h-5 w-5" />
                        </div>
                    </div>

                    <!-- Clientes -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Clientes</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ staffKpis?.total_clientes ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">registrados activos</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                            <Users class="h-5 w-5" />
                        </div>
                    </div>

                    <!-- Bajo stock -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Bajo Stock</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-destructive">
                                {{ staffKpis?.bajo_stock ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">productos ≤ 5 unid.</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <Package class="h-5 w-5" />
                        </div>
                    </div>

                    <!-- Reclamos activos -->
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Reclamos</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400">
                                {{ staffKpis?.reclamos_activos ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">pendientes de atención</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                            <AlertCircle class="h-5 w-5" />
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid gap-6 md:grid-cols-3">

                    <!-- Sales Trend (Col span 2) -->
                    <Card class="md:col-span-2 shadow-sm">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <TrendingUp class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base font-bold text-foreground">Evolución Diaria de Ventas</CardTitle>
                            </div>
                            <CardDescription>Ingresos económicos por día de la última semana.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="ventasPoints.length > 1" class="space-y-4">
                                <div class="relative w-full h-[180px] bg-muted/20 border border-border/50 rounded-xl overflow-hidden p-2">
                                    <svg viewBox="0 0 400 120" class="w-full h-full" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.3"/>
                                                <stop offset="100%" stop-color="var(--primary)" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <path :d="ventasAreaPath" fill="url(#salesGrad)" />
                                        <path :d="ventasLinePath" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <circle
                                            v-for="(pt, i) in ventasPoints"
                                            :key="i"
                                            :cx="20 + (i / (ventasPoints.length - 1)) * 360"
                                            :cy="20 + 80 - ((pt.value - Math.min(...ventasPoints.map(p => p.value))) / (Math.max(...ventasPoints.map(p => p.value)) - Math.min(...ventasPoints.map(p => p.value)) || 1)) * 80"
                                            r="4"
                                            fill="var(--primary)"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            class="text-card"
                                        />
                                    </svg>
                                </div>
                                <div class="flex justify-between px-1">
                                    <span v-for="(pt, i) in ventasPoints" :key="i" class="text-[10px] text-muted-foreground font-semibold font-mono">{{ fmtDate(pt.label) }}</span>
                                </div>
                            </div>
                            <div v-else class="flex h-[180px] items-center justify-center text-sm text-muted-foreground">
                                Sin datos suficientes para mostrar
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Top Visited Pages Bar Chart -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <Activity class="h-4 w-4 text-violet-500" />
                                <CardTitle class="text-base font-bold text-foreground">Top Páginas Visitadas</CardTitle>
                            </div>
                            <CardDescription>Rutas y contador de accesos.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="visitas && visitas.length" class="space-y-4 py-2">
                                <div v-for="v in visitas" :key="v.ruta" class="flex flex-col gap-1">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="font-mono text-muted-foreground truncate max-w-[150px]">{{ v.ruta }}</span>
                                        <span class="font-bold text-foreground font-mono">{{ v.contador }}</span>
                                    </div>
                                    <div class="w-full rounded-full bg-muted" style="height:6px">
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="{ width: ((v.contador / visitaBarMax) * 100) + '%' }"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex h-[180px] items-center justify-center text-sm text-muted-foreground">
                                Sin registros de visitas
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Quick Actions Grid -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Link href="/pedidos" class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-500 transition-transform group-hover:scale-110">
                            <ShoppingCart class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Gestionar Pedidos</p>
                            <p class="text-[11px] text-muted-foreground">Ver ventas y estados</p>
                        </div>
                    </Link>
                    <Link href="/productos" class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-500 transition-transform group-hover:scale-110">
                            <Package class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Productos</p>
                            <p class="text-[11px] text-muted-foreground">Stock y catálogo</p>
                        </div>
                    </Link>
                    <Link href="/reclamos" class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-500 transition-transform group-hover:scale-110">
                            <AlertCircle class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Reclamos</p>
                            <p class="text-[11px] text-muted-foreground">Responder a clientes</p>
                        </div>
                    </Link>
                    <Link href="/estadisticas" class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500 transition-transform group-hover:scale-110">
                            <BarChart2 class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Estadísticas</p>
                            <p class="text-[11px] text-muted-foreground">Reportes de ventas</p>
                        </div>
                    </Link>
                </div>

            </template>

            <!-- ═══════════════════ CLIENTE DASHBOARD ═══════════════════ -->
            <template v-else-if="role_type === 'cliente'">

                <!-- KPI Cards Grid -->
                <div class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Total Gastado</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ fmt(clienteKpis?.total_gastado ?? 0) }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">en compras realizadas</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <Banknote class="h-5 w-5" />
                        </div>
                    </div>

                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Pedidos Activos</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ clienteKpis?.pedidos_activos ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">en proceso de entrega</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                            <ShoppingCart class="h-5 w-5" />
                        </div>
                    </div>

                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Calificaciones</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ clienteKpis?.calificaciones_hechas ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">reseñas de pijamas</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <Star class="h-5 w-5" />
                        </div>
                    </div>

                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Reclamos</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ clienteKpis?.reclamos_realizados ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">solicitudes enviadas</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center shrink-0">
                            <MessageSquare class="h-5 w-5" />
                        </div>
                    </div>
                </div>

                <!-- Charts and Lists Row -->
                <div class="grid gap-6 md:grid-cols-3">

                    <!-- Recent Orders (Col span 2) -->
                    <Card class="md:col-span-2 shadow-sm">
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <div class="flex items-center gap-2">
                                <ShoppingCart class="h-4 w-4 text-indigo-500" />
                                <CardTitle class="text-base font-bold text-foreground">Mis Últimos Pedidos</CardTitle>
                            </div>
                            <Link href="/pedidos" class="text-xs text-primary hover:underline flex items-center gap-0.5">
                                Ver todos
                                <ArrowUpRight class="h-3 w-3" />
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <div v-if="pedidosRecientes.length" class="space-y-3">
                                <div
                                    v-for="p in pedidosRecientes"
                                    :key="(p as any).id"
                                    class="flex items-center justify-between rounded-xl bg-muted/20 border border-border/40 px-4 py-3"
                                >
                                    <div>
                                        <p class="text-xs font-bold text-foreground font-mono">Pedido #{{ (p as any).id }}</p>
                                        <p class="text-xs text-muted-foreground">{{ fmtDate((p as any).fecha_pedido) }}</p>
                                    </div>
                                    <span :class="['rounded-full px-2.5 py-0.5 text-[10px] font-bold capitalize border', statusColor[(p as any).estado_pedido] ?? 'bg-muted text-muted-foreground']">
                                        {{ (p as any).estado_pedido }}
                                    </span>
                                    <p class="text-sm font-black text-primary font-mono">{{ fmt((p as any).total) }}</p>
                                </div>
                            </div>
                            <p v-else class="py-12 text-center text-sm text-muted-foreground">Aún no has realizado pedidos.</p>
                        </CardContent>
                    </Card>

                    <!-- Categories Donut Chart -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <BarChart2 class="h-4 w-4 text-violet-500" />
                                <CardTitle class="text-base font-bold text-foreground">Categorías Favoritas</CardTitle>
                            </div>
                            <CardDescription>Distribución de tus pijamas comprados.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="categorias && categorias.length" class="flex flex-col items-center justify-center gap-6 py-1">
                                <svg viewBox="0 0 160 160" class="h-28 w-28 shrink-0">
                                    <path v-for="(seg, i) in donutPaths" :key="i" :d="seg.d" :fill="seg.color" />
                                    <circle cx="80" cy="80" r="38" fill="white" class="dark:fill-zinc-950" />
                                </svg>
                                <ul class="w-full grid grid-cols-2 gap-2 text-[11px] text-muted-foreground border-t border-border/40 pt-3">
                                    <li v-for="(seg, i) in donutPaths" :key="i" class="flex items-center gap-1.5 truncate">
                                        <span class="h-2 w-2 rounded-full shrink-0" :style="{ background: seg.color }"></span>
                                        <span class="capitalize truncate font-medium">{{ seg.label ?? 'General' }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="py-12 text-center text-sm text-muted-foreground">No hay estadísticas de categorías.</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Client Quick Actions -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    <Link href="/pedidos/create" class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-500 transition-transform group-hover:scale-110">
                            <ShoppingCart class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Comprar Pijamas</p>
                            <p class="text-[11px] text-muted-foreground">Nueva orden de pijamas</p>
                        </div>
                    </Link>
                    <Link href="/reclamos/create" class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-500 transition-transform group-hover:scale-110">
                            <MessageSquare class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Crear Reclamo</p>
                            <p class="text-[11px] text-muted-foreground">Reportar inconveniente</p>
                        </div>
                    </Link>
                    <Link href="/productos" class="group flex items-center gap-3 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500 transition-transform group-hover:scale-110">
                            <Star class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-foreground">Ver Catálogo</p>
                            <p class="text-[11px] text-muted-foreground">Pijamas y valoraciones</p>
                        </div>
                    </Link>
                </div>

            </template>

            <!-- ═══════════════════ DISTRIBUIDOR DASHBOARD ═══════════════════ -->
            <template v-else-if="role_type === 'distribuidor'">

                <!-- KPI Cards Grid -->
                <div class="grid grid-cols-2 gap-6">
                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Envíos Pendientes</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ distribuidorKpis?.envios_pendientes ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">en distribución o pendiente</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <Clock class="h-5 w-5" />
                        </div>
                    </div>

                    <div class="p-6 rounded-xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform">
                        <div class="space-y-1">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Envíos Entregados</span>
                            <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                                {{ distribuidorKpis?.envios_entregados ?? 0 }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">entregas completadas</p>
                        </div>
                        <div class="h-10 w-10 rounded-xl bg-green-500/10 text-green-600 dark:text-green-400 flex items-center justify-center shrink-0">
                            <CheckCircle class="h-5 w-5" />
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid gap-6 md:grid-cols-2">

                    <!-- Delivery Trend -->
                    <Card class="shadow-sm">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <TrendingUp class="h-4 w-4 text-emerald-500" />
                                <CardTitle class="text-base font-bold text-foreground">Tendencia de Entregas</CardTitle>
                            </div>
                            <p class="text-xs text-muted-foreground">Envíos concretados por día en la última semana.</p>
                        </CardHeader>
                        <CardContent>
                            <div v-if="enviosPoints.length > 1">
                                <div class="relative w-full h-[140px] bg-muted/20 border border-border/50 rounded-xl overflow-hidden p-2">
                                    <svg viewBox="0 0 400 120" class="w-full h-full" preserveAspectRatio="none">
                                        <defs>
                                            <linearGradient id="envioGrad" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.3"/>
                                                <stop offset="100%" stop-color="var(--primary)" stop-opacity="0"/>
                                            </linearGradient>
                                        </defs>
                                        <path :d="enviosAreaPath" fill="url(#envioGrad)" />
                                        <path :d="enviosLinePath" fill="none" stroke="var(--primary)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <circle
                                            v-for="(pt, i) in enviosPoints"
                                            :key="i"
                                            :cx="20 + (i / (enviosPoints.length - 1)) * 360"
                                            :cy="20 + 80 - ((pt.value - Math.min(...enviosPoints.map(p => p.value))) / (Math.max(...enviosPoints.map(p => p.value)) - Math.min(...enviosPoints.map(p => p.value)) || 1)) * 80"
                                            r="4"
                                            fill="var(--primary)"
                                            stroke="currentColor"
                                            stroke-width="1.5"
                                            class="text-card"
                                        />
                                    </svg>
                                </div>
                                <div class="mt-2 flex justify-between px-1">
                                    <span v-for="(pt, i) in enviosPoints" :key="i" class="text-[10px] text-muted-foreground font-semibold font-mono">{{ fmtDate(pt.label) }}</span>
                                </div>
                            </div>
                            <div v-else class="flex h-[140px] items-center justify-center text-sm text-muted-foreground">
                                Sin datos de entregas recientes
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Shipments -->
                    <Card class="shadow-sm">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Truck class="h-4 w-4 text-indigo-500" />
                                <CardTitle class="text-base font-bold text-foreground">Últimos Envíos Asignados</CardTitle>
                            </div>
                            <Link href="/envios" class="text-xs text-primary hover:underline flex items-center gap-0.5">
                                Ver todos
                                <ArrowUpRight class="h-3 w-3" />
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <div v-if="enviosRecientes.length" class="space-y-2.5">
                                <div
                                    v-for="e in enviosRecientes"
                                    :key="(e as any).id"
                                    class="flex items-center justify-between rounded-xl bg-muted/20 border border-border/40 px-4 py-3"
                                >
                                    <div>
                                        <p class="text-xs font-bold text-foreground font-mono">Pedido #{{ (e as any).id_pedido }}</p>
                                        <p class="text-[11px] text-muted-foreground capitalize font-medium">{{ (e as any).cliente }}</p>
                                    </div>
                                    <span :class="['rounded-full px-2.5 py-0.5 text-[10px] font-bold capitalize border', statusColor[(e as any).estado_envio] ?? 'bg-muted text-muted-foreground']">
                                        {{ (e as any).estado_envio?.replace('_', ' ') }}
                                    </span>
                                </div>
                            </div>
                            <p v-else class="py-10 text-center text-sm text-muted-foreground">No tienes envíos asignados.</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Distributor Action Link -->
                <div>
                    <Link href="/envios" class="flex items-center justify-between rounded-xl border border-border bg-card p-5 shadow-sm transition hover:border-primary/50 hover:shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                                <Truck class="h-6 w-6" />
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-foreground">Ver Mi Hoja de Ruta</h3>
                                <p class="text-xs text-muted-foreground">Listado de direcciones de entrega asignadas a tu cuenta</p>
                            </div>
                        </div>
                        <span class="text-primary font-bold text-lg">→</span>
                    </Link>
                </div>

            </template>

            <!-- ═══════════════════ DEFAULT ═══════════════════ -->
            <template v-else>
                <Card class="flex flex-col items-center justify-center py-16 text-center border-dashed">
                    <BarChart2 class="h-12 w-12 text-muted-foreground/40" />
                    <h3 class="mt-4 text-sm font-bold text-foreground">Panel de control vacío</h3>
                    <p class="mt-1 text-xs text-muted-foreground">No existen estadísticas o paneles asignados a tu rol actual.</p>
                </Card>
            </template>

        </div>
    </AppLayout>
</template>
