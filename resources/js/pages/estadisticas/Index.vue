<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    ShoppingBag, AlertCircle, AlertTriangle, Calendar, 
    Printer, RefreshCw, Banknote 
} from 'lucide-vue-next';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

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



const salesChartCanvas = ref<HTMLCanvasElement | null>(null);
const categoriesChartCanvas = ref<HTMLCanvasElement | null>(null);
const claimsChartCanvas = ref<HTMLCanvasElement | null>(null);

let salesChart: Chart | null = null;
let categoriesChart: Chart | null = null;
let claimsChart: Chart | null = null;

const initSalesChart = () => {
    if (!salesChartCanvas.value) return;
    
    if (salesChart) {
        salesChart.destroy();
    }
    
    const ctx = salesChartCanvas.value.getContext('2d');
    if (!ctx) return;
    
    const labels = props.ventas_diarias.map(point => point.date);
    const data = props.ventas_diarias.map(point => point.revenue);
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
    
    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Ingresos',
                data,
                fill: true,
                backgroundColor: gradient,
                borderColor: '#6366f1',
                borderWidth: 2,
                tension: 0.4,
                pointBackgroundColor: '#6366f1',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 1.5,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' Bs. ' + Number(context.raw).toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    border: {
                        dash: [5, 5]
                    },
                    grid: {
                        color: 'rgba(156, 163, 175, 0.15)'
                    },
                    ticks: {
                        font: {
                            size: 10
                        },
                        callback: function(value) {
                            return 'Bs. ' + value;
                        }
                    }
                }
            }
        }
    });
};

const initCategoriesChart = () => {
    if (!categoriesChartCanvas.value) return;
    
    if (categoriesChart) {
        categoriesChart.destroy();
    }
    
    const ctx = categoriesChartCanvas.value.getContext('2d');
    if (!ctx) return;
    
    const labels = props.ventas_categorias.map(item => item.categoria);
    const data = props.ventas_categorias.map(item => item.ventas);
    
    categoriesChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: [
                    '#6366f1',
                    '#a855f7',
                    '#ec4899',
                    '#0ea5e9',
                    '#10b981',
                    '#f59e0b',
                ],
                borderWidth: 1,
                borderColor: 'transparent'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        font: {
                            size: 11
                        },
                        color: 'currentColor'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.raw);
                            const total = context.dataset.data.reduce((a: any, b: any) => Number(a) + Number(b), 0) as number;
                            const percentage = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                            return ` Bs. ${value.toFixed(2)} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
};

const initClaimsChart = () => {
    if (!claimsChartCanvas.value) return;
    
    if (claimsChart) {
        claimsChart.destroy();
    }
    
    const ctx = claimsChartCanvas.value.getContext('2d');
    if (!ctx) return;
    
    const r = props.reclamos_ratio;
    
    claimsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Pendientes', 'En Proceso', 'Atendidos', 'Rechazados'],
            datasets: [{
                data: [r.pendiente, r.en_proceso, r.atendido, r.rechazado],
                backgroundColor: [
                    'rgba(245, 158, 11, 0.85)',
                    'rgba(37, 99, 235, 0.85)',
                    'rgba(16, 185, 129, 0.85)',
                    'rgba(220, 38, 38, 0.85)',
                ],
                borderColor: [
                    '#f59e0b',
                    '#2563eb',
                    '#10b981',
                    '#dc2626',
                ],
                borderWidth: 1.5,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return ' ' + context.raw + ' reclamos';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    border: {
                        dash: [5, 5]
                    },
                    grid: {
                        color: 'rgba(156, 163, 175, 0.15)'
                    },
                    ticks: {
                        precision: 0,
                        font: {
                            size: 10
                        }
                    }
                }
            }
        }
    });
};

onMounted(() => {
    initSalesChart();
    initCategoriesChart();
    initClaimsChart();
});

onUnmounted(() => {
    if (salesChart) salesChart.destroy();
    if (categoriesChart) categoriesChart.destroy();
    if (claimsChart) claimsChart.destroy();
});

watch(() => props.ventas_diarias, () => {
    initSalesChart();
}, { deep: true });

watch(() => props.ventas_categorias, () => {
    initCategoriesChart();
}, { deep: true });

watch(() => props.reclamos_ratio, () => {
    initClaimsChart();
}, { deep: true });


const printReport = () => {
    window.print();
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Reportes y Estadísticas', href: '/estadisticas' }]">
        <Head title="Reportes y Estadísticas" />

        <div class="space-y-6 max-w-7xl mx-auto print:space-y-4 print:p-0">
            
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

            
            <div class="hidden print:flex flex-col border-b border-border pb-4 mb-4">
                <span class="text-xs uppercase tracking-widest text-muted-foreground font-bold">PIJAMAS CLOUD REPORTES OFICIALES</span>
                <h1 class="text-2xl font-bold text-foreground mt-1">Informe General de Rendimiento Comercial</h1>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Periodo evaluado: {{ startDate }} al {{ endDate }}
                </p>
            </div>

            
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

            
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 print:grid-cols-4 print:gap-4">
                
                
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between print:p-4 print:shadow-none">
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

                
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between print:p-4 print:shadow-none">
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

                
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between print:p-4 print:shadow-none">
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

                
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm flex items-center justify-between print:p-4 print:shadow-none">
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

            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:grid-cols-3 print:gap-4">
                
                
                <div class="lg:col-span-2 p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 flex flex-col justify-between print:col-span-2 print:shadow-none relative">
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
                            Evolución Diaria de Ventas
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Ingresos económicos percibidos por día en el rango de fechas seleccionado.
                        </p>
                    </div>

                    
                    <div class="relative w-full h-[220px] bg-muted/20 border border-border/50 rounded-xl p-3">
                        <canvas ref="salesChartCanvas"></canvas>
                    </div>
                </div>

                
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 flex flex-col justify-between print:col-span-1 print:shadow-none">
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
                            Ventas por Categoría
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Distribución de ingresos generados por cada línea textil.
                        </p>
                    </div>

                    
                    <div class="relative w-full h-[240px] p-2 flex items-center justify-center">
                        <canvas ref="categoriesChartCanvas"></canvas>
                    </div>
                </div>
            </div>

            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:grid-cols-3 print:gap-4">
                
                
                <div class="lg:col-span-2 p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 print:col-span-2 print:shadow-none">
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
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

                
                <div class="p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 flex flex-col justify-between print:col-span-1 print:shadow-none">
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
                            Métricas de Reclamos
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Tasa de resolución y distribución del estado de quejas.
                        </p>
                    </div>

                    
                    <div class="relative w-full h-[220px] p-2">
                        <canvas ref="claimsChartCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>

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
