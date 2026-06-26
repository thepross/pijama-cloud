<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    TrendingUp, ShoppingBag, AlertCircle, AlertTriangle, Calendar, 
    Printer, RefreshCw, Banknote, Award, Shirt 
} from 'lucide-vue-next';

interface KpiType {
    ingresos_totales: number;
    total_pedidos: number;
    bajo_stock_count: number;
    reclamos_pendientes: number;
}

interface TrendPoint {
    date: string;
    revenue: number;
    orders_count: number;
}

interface BestProduct {
    id_producto: number;
    nombre: string;
    categoria: string;
    foto: string | null;
    precio: number;
    cantidad_vendida: number;
    ingresos: number;
}

interface CategorySales {
    categoria: string;
    ventas: number;
}

interface ClaimsRatio {
    pendiente: number;
    en_proceso: number;
    atendido: number;
    rechazado: number;
}

const props = defineProps<{
    kpis: KpiType;
    ventas_diarias: TrendPoint[];
    mejores_productos: BestProduct[];
    ventas_categorias: CategorySales[];
    reclamos_ratio: ClaimsRatio;
    filters: { fecha_inicio: string; fecha_fin: string };
}>();

const startDate = ref(props.filters.fecha_inicio);
const endDate = ref(props.filters.fecha_fin);

const applyFilters = () => {
    router.get(
        route('estadisticas.index'),
        { fecha_inicio: startDate.value, fecha_fin: endDate.value },
        { preserveState: true, replace: true }
    );
};

const refreshData = () => {
    router.get(route('estadisticas.index'), {}, { replace: true });
};

// Calculate total sales category to find shares
const totalCategorySales = computed(() => {
    return props.ventas_categorias.reduce((sum, item) => sum + item.ventas, 0);
});

// Calculate total claims to find ratio percentages
const totalClaims = computed(() => {
    const r = props.reclamos_ratio;
    return r.pendiente + r.en_proceso + r.atendido + r.rechazado;
});

// SVG Line Chart Calculations for Sales Trend
const svgWidth = 500;
const svgHeight = 220;
const padding = 30;

const trendPoints = computed(() => {
    if (props.ventas_diarias.length === 0) {
        return [];
    }

    const maxRevenue = Math.max(...props.ventas_diarias.map(p => p.revenue), 10);
    const minRevenue = 0;
    const revenueRange = maxRevenue - minRevenue;

    const totalPoints = props.ventas_diarias.length;
    const xStep = totalPoints > 1 ? (svgWidth - padding * 2) / (totalPoints - 1) : 0;

    return props.ventas_diarias.map((point, index) => {
        const x = padding + index * xStep;
        const y = svgHeight - padding - ((point.revenue - minRevenue) / revenueRange) * (svgHeight - padding * 2);
        return {
            x,
            y,
            date: point.date,
            revenue: point.revenue,
            orders_count: point.orders_count,
        };
    });
});

const svgLinePath = computed(() => {
    const pts = trendPoints.value;
    if (pts.length === 0) {
        return '';
    }
    return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
});

const svgAreaPath = computed(() => {
    const pts = trendPoints.value;
    if (pts.length === 0) {
        return '';
    }
    const linePath = svgLinePath.value;
    const firstX = pts[0].x;
    const lastX = pts[pts.length - 1].x;
    const baseY = svgHeight - padding;
    return `${linePath} L ${lastX} ${baseY} L ${firstX} ${baseY} Z`;
});

// Print Report action
const printReport = () => {
    window.print();
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reportes y Estadísticas', href: '/estadisticas' }]">
        <Head title="Reportes y Estadísticas" />

        <div class="space-y-6 max-w-7xl mx-auto print:space-y-4 print:p-0">
            <!-- Header section (Hidden in print) -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Reportes y Estadísticas
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Analiza las métricas comerciales de la tienda de pijamas, ingresos, productos vendidos y gestión de reclamos.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="refreshData" class="rounded-xl shadow-sm h-10">
                        <RefreshCw class="h-4 w-4" />
                    </Button>
                    <Button @click="printReport" class="flex items-center gap-1.5 shadow-sm rounded-xl h-10">
                        <Printer class="h-4 w-4" />
                        Imprimir Reporte
                    </Button>
                </div>
            </div>

            <!-- Header Section in Print Only -->
            <div class="hidden print:flex flex-col border-b border-border pb-4 mb-4">
                <span class="text-xs uppercase tracking-widest text-muted-foreground font-bold">PIJAMAS CLOUD REPORTES OFICIALES</span>
                <h1 class="text-2xl font-bold text-foreground mt-1">Informe General de Rendimiento Comercial</h1>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Periodo evaluado: {{ startDate }} al {{ endDate }}
                </p>
            </div>

            <!-- Filters Section (Hidden in print) -->
            <div class="p-4 rounded-2xl border border-border bg-card shadow-sm flex flex-col sm:flex-row items-end gap-4 justify-between print:hidden">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full max-w-xl">
                    <div class="space-y-2">
                        <Label for="start_date" class="text-xs font-semibold text-muted-foreground">Fecha Inicio</Label>
                        <div class="relative">
                            <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="start_date"
                                type="date"
                                v-model="startDate"
                                class="pl-9 rounded-xl bg-background"
                            />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label for="end_date" class="text-xs font-semibold text-muted-foreground">Fecha Fin</Label>
                        <div class="relative">
                            <Calendar class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="end_date"
                                type="date"
                                v-model="endDate"
                                class="pl-9 rounded-xl bg-background"
                            />
                        </div>
                    </div>
                </div>
                <Button @click="applyFilters" class="rounded-xl px-6 h-10 font-semibold shadow-sm w-full sm:w-auto">
                    Filtrar Datos
                </Button>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 print:grid-cols-4 print:gap-4">
                
                <!-- Ingresos Totales -->
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform print:p-4 print:shadow-none">
                    <div class="space-y-1">
                        <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Ingresos Totales</span>
                        <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                        Bs. {{ Number(props.kpis.ingresos_totales).toFixed(2) }}
                    </p>
                </div>
                <div class="h-10 w-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <Banknote class="h-5 w-5" />
                    </div>
                </div>

                <!-- Pedidos Concretados -->
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform print:p-4 print:shadow-none">
                    <div class="space-y-1">
                        <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Pedidos Registrados</span>
                        <p class="font-mono text-xl sm:text-2xl font-black text-foreground">
                            {{ props.kpis.total_pedidos }}
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <ShoppingBag class="h-5 w-5" />
                    </div>
                </div>

                <!-- Bajo Stock -->
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform print:p-4 print:shadow-none">
                    <div class="space-y-1">
                        <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Productos Críticos</span>
                        <p class="font-mono text-xl sm:text-2xl font-black text-destructive">
                            {{ props.kpis.bajo_stock_count }}
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-red-500/10 text-red-600 dark:text-red-400 flex items-center justify-center shrink-0">
                        <AlertCircle class="h-5 w-5" />
                    </div>
                </div>

                <!-- Reclamos Pendientes -->
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between hover:scale-[1.01] transition-transform print:p-4 print:shadow-none">
                    <div class="space-y-1">
                        <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Reclamos Activos</span>
                        <p class="font-mono text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400">
                            {{ props.kpis.reclamos_pendientes }}
                        </p>
                    </div>
                    <div class="h-10 w-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                </div>
            </div>

            <!-- Charts Section (2-cols: Trend Line & Category comparative) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:grid-cols-3 print:gap-4">
                
                <!-- Line Chart: Daily Sales Trend (2 cols in desktop) -->
                <div class="lg:col-span-2 p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 flex flex-col justify-between print:col-span-2 print:shadow-none">
                    <div>
                        <h2 class="text-lg font-bold text-foreground flex items-center gap-1.5">
                            <TrendingUp class="h-5 w-5 text-primary" />
                            Evolución Diaria de Ventas
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Ingresos económicos percibidos por día en el rango de fechas seleccionado.
                        </p>
                    </div>

                    <!-- SVG Chart Container -->
                    <div class="relative w-full h-[220px] bg-muted/20 border border-border/50 rounded-xl flex items-center justify-center overflow-hidden">
                        <svg 
                            v-if="trendPoints.length > 0" 
                            class="w-full h-full" 
                            :viewBox="`0 0 ${svgWidth} ${svgHeight}`" 
                            preserveAspectRatio="none"
                        >
                            <!-- Gradients -->
                            <defs>
                                <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="var(--primary)" stop-opacity="0.3" />
                                    <stop offset="100%" stop-color="var(--primary)" stop-opacity="0" />
                                </linearGradient>
                            </defs>

                            <!-- Grid Lines -->
                            <line :x1="padding" :y1="padding" :x2="svgWidth - padding" :y2="padding" stroke="currentColor" class="text-border/20" stroke-dasharray="2" />
                            <line :x1="padding" :y1="svgHeight/2" :x2="svgWidth - padding" :y2="svgHeight/2" stroke="currentColor" class="text-border/20" stroke-dasharray="2" />
                            <line :x1="padding" :y1="svgHeight - padding" :x2="svgWidth - padding" :y2="svgHeight - padding" stroke="currentColor" class="text-border/40" />

                            <!-- Area fill -->
                            <path :d="svgAreaPath" fill="url(#salesGrad)" />

                            <!-- Path Line -->
                            <path 
                                :d="svgLinePath" 
                                fill="none" 
                                stroke="var(--primary)" 
                                stroke-width="3" 
                                stroke-linecap="round" 
                                stroke-linejoin="round"
                            />

                            <!-- Data Points circles -->
                            <circle 
                                v-for="(p, i) in trendPoints" 
                                :key="i"
                                :cx="p.x" 
                                :cy="p.y" 
                                r="4" 
                                fill="var(--background)" 
                                stroke="var(--primary)" 
                                stroke-width="2"
                                class="hover:r-6 cursor-pointer transition-all"
                            />
                        </svg>
                        
                        <div v-else class="text-center text-xs text-muted-foreground p-6">
                            ⚠️ No se registran ventas facturadas en el periodo del reporte.
                        </div>
                    </div>
                </div>

                <!-- Progress Bars: Category Share (1 col) -->
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 flex flex-col justify-between print:col-span-1 print:shadow-none">
                    <div>
                        <h2 class="text-lg font-bold text-foreground flex items-center gap-1.5">
                            <Shirt class="h-5 w-5 text-primary" />
                            Ventas por Categoría
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Distribución de ingresos generados por cada línea textil.
                        </p>
                    </div>

                    <div class="space-y-4 pt-2 w-full">
                        <div v-if="props.ventas_categorias.length === 0" class="text-center text-xs text-muted-foreground py-6">
                            No se registran categorías de pijamas vendidas.
                        </div>
                        <div 
                            v-else
                            v-for="item in props.ventas_categorias" 
                            :key="item.categoria"
                            class="space-y-1.5"
                        >
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span class="text-foreground block">{{ item.categoria }}</span>
                                <span class="font-mono text-muted-foreground block">
                                    Bs. {{ Number(item.ventas).toFixed(2) }}
                                </span>
                            </div>
                            <!-- Writable HTML progress bar -->
                            <div class="w-full h-2.5 rounded-full bg-muted border overflow-hidden">
                                <div 
                                    class="h-full rounded-full bg-gradient-to-r from-primary to-indigo-500 transition-all duration-500"
                                    :style="{ width: `${totalCategorySales > 0 ? (item.ventas / totalCategorySales) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Third Section: Top Products & Claims gauges -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:grid-cols-3 print:gap-4">
                
                <!-- Best Selling Products Table (2 cols in desktop) -->
                <div class="lg:col-span-2 p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 print:col-span-2 print:shadow-none">
                    <div>
                        <h2 class="text-lg font-bold text-foreground flex items-center gap-1.5">
                            <Award class="h-5 w-5 text-primary" />
                            Top 5 Productos Más Vendidos
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Los productos de pijama con mayor volumen de unidades vendidas.
                        </p>
                    </div>

                    <div class="rounded-xl border border-border overflow-hidden bg-card">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-border bg-muted/40 font-semibold text-muted-foreground uppercase tracking-wider">
                                    <th class="p-3">Prenda</th>
                                    <th class="p-3">Categoría</th>
                                    <th class="p-3">Precio</th>
                                    <th class="p-3">U. Vendidas</th>
                                    <th class="p-3 text-right">Recaudado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="props.mejores_productos.length === 0">
                                    <td colspan="5" class="p-6 text-center text-muted-foreground italic">
                                        No se registran productos vendidos en este lapso.
                                    </td>
                                </tr>
                                <tr 
                                    v-for="item in props.mejores_productos" 
                                    :key="item.id_producto"
                                    class="hover:bg-accent/20 transition-colors"
                                >
                                    <td class="p-3 flex items-center gap-2">
                                        <div class="h-8 w-8 rounded-lg bg-muted flex items-center justify-center border overflow-hidden shrink-0">
                                            <img v-if="item.foto" :src="item.foto" class="h-full w-full object-cover" />
                                            <span v-else>👕</span>
                                        </div>
                                        <span class="font-semibold text-foreground truncate max-w-[150px]">{{ item.nombre }}</span>
                                    </td>
                                    <td class="p-3">
                                        <span class="inline-flex px-2 py-0.5 rounded bg-muted border font-medium text-foreground">
                                            {{ item.categoria }}
                                        </span>
                                    </td>
                                    <td class="p-3 font-mono text-muted-foreground">Bs. {{ Number(item.precio).toFixed(2) }}</td>
                                    <td class="p-3 font-semibold text-foreground">{{ item.cantidad_vendida }} uds</td>
                                    <td class="p-3 font-mono font-bold text-foreground text-right">
                                        Bs. {{ Number(item.ingresos).toFixed(2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Claims Resolutive Circular Gauges (1 col) -->
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 flex flex-col justify-between print:col-span-1 print:shadow-none">
                    <div>
                        <h2 class="text-lg font-bold text-foreground flex items-center gap-1.5">
                            <AlertTriangle class="h-5 w-5 text-primary" />
                            Métricas de Reclamos
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Tasa de resolución y distribución del estado de quejas.
                        </p>
                    </div>

                    <!-- Gauges side-by-side grid -->
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        
                        <!-- Pendiente Gauge -->
                        <div class="p-3 rounded-xl border border-border bg-card flex flex-col items-center gap-1 text-center">
                            <svg class="w-12 h-12" viewBox="0 0 36 36">
                                <path class="text-muted/20" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path 
                                    class="text-amber-500" 
                                    stroke="currentColor" 
                                    stroke-width="3" 
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${totalClaims > 0 ? (props.reclamos_ratio.pendiente / totalClaims) * 100 : 0}, 100`" 
                                    fill="none" 
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
                                />
                                <text x="18" y="21.5" class="font-mono text-[9px] font-bold text-foreground" text-anchor="middle">
                                    {{ props.reclamos_ratio.pendiente }}
                                </text>
                            </svg>
                            <span class="text-[10px] font-bold text-foreground">Pendientes</span>
                        </div>

                        <!-- En Proceso Gauge -->
                        <div class="p-3 rounded-xl border border-border bg-card flex flex-col items-center gap-1 text-center">
                            <svg class="w-12 h-12" viewBox="0 0 36 36">
                                <path class="text-muted/20" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path 
                                    class="text-blue-500" 
                                    stroke="currentColor" 
                                    stroke-width="3" 
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${totalClaims > 0 ? (props.reclamos_ratio.en_proceso / totalClaims) * 100 : 0}, 100`" 
                                    fill="none" 
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
                                />
                                <text x="18" y="21.5" class="font-mono text-[9px] font-bold text-foreground" text-anchor="middle">
                                    {{ props.reclamos_ratio.en_proceso }}
                                </text>
                            </svg>
                            <span class="text-[10px] font-bold text-foreground">En Proceso</span>
                        </div>

                        <!-- Atendido Gauge -->
                        <div class="p-3 rounded-xl border border-border bg-card flex flex-col items-center gap-1 text-center">
                            <svg class="w-12 h-12" viewBox="0 0 36 36">
                                <path class="text-muted/20" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path 
                                    class="text-emerald-500" 
                                    stroke="currentColor" 
                                    stroke-width="3" 
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${totalClaims > 0 ? (props.reclamos_ratio.atendido / totalClaims) * 100 : 0}, 100`" 
                                    fill="none" 
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
                                />
                                <text x="18" y="21.5" class="font-mono text-[9px] font-bold text-foreground" text-anchor="middle">
                                    {{ props.reclamos_ratio.atendido }}
                                </text>
                            </svg>
                            <span class="text-[10px] font-bold text-foreground">Atendidos</span>
                        </div>

                        <!-- Rechazado Gauge -->
                        <div class="p-3 rounded-xl border border-border bg-card flex flex-col items-center gap-1 text-center">
                            <svg class="w-12 h-12" viewBox="0 0 36 36">
                                <path class="text-muted/20" stroke="currentColor" stroke-width="3" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path 
                                    class="text-red-500" 
                                    stroke="currentColor" 
                                    stroke-width="3" 
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${totalClaims > 0 ? (props.reclamos_ratio.rechazado / totalClaims) * 100 : 0}, 100`" 
                                    fill="none" 
                                    d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
                                />
                                <text x="18" y="21.5" class="font-mono text-[9px] font-bold text-foreground" text-anchor="middle">
                                    {{ props.reclamos_ratio.rechazado }}
                                </text>
                            </svg>
                            <span class="text-[10px] font-bold text-foreground">Rechazados</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Custom Print Styles */
@media print {
    body {
        background-color: white !important;
        color: black !important;
    }
    .print\:hidden {
        display: none !important;
    }
    .print\:col-span-2 {
        grid-column: span 2 / span 2 !important;
    }
    .print\:col-span-1 {
        grid-column: span 1 / span 1 !important;
    }
    .print\:grid-cols-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    }
    .print\:grid-cols-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    .print\:shadow-none {
        box-shadow: none !important;
        border: 1px solid #e2e8f0 !important;
    }
    .print\:p-0 {
        padding: 0 !important;
    }
    .print\:gap-4 {
        gap: 1rem !important;
    }
}
</style>
