<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import {
    TrendingUp, Users, Package, AlertCircle, ShoppingCart,
    Star, MessageSquare, Truck, CheckCircle, Clock, Banknote,
    BarChart2, Activity, ArrowUpRight
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];


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
    
    ventas_diarias?: SalePoint[];
    visitas?: VisitaItem[];
    
    recientes?: PedidoReciente[] | EnvioReciente[];
    categorias?: CategoriaItem[];
    
    envios_diarios?: EnvioPoint[];
}>();


const staffKpis = computed(() => props.kpis as StaffKpis | undefined);
const clienteKpis = computed(() => props.kpis as ClienteKpis | undefined);
const distribuidorKpis = computed(() => props.kpis as DistribuidorKpis | undefined);
const pedidosRecientes = computed(() => (props.recientes as PedidoReciente[] | undefined) ?? []);
const enviosRecientes = computed(() => (props.recientes as EnvioReciente[] | undefined) ?? []);


const salesChartCanvas = ref<HTMLCanvasElement | null>(null);
const categoriesChartCanvas = ref<HTMLCanvasElement | null>(null);
const deliveriesChartCanvas = ref<HTMLCanvasElement | null>(null);

let salesChart: Chart | null = null;
let categoriesChart: Chart | null = null;
let deliveriesChart: Chart | null = null;

const initSalesChart = () => {
    if (!salesChartCanvas.value) return;
    if (salesChart) {
        salesChart.destroy();
    }
    const ctx = salesChartCanvas.value.getContext('2d');
    if (!ctx) return;

    const dataPoints = props.ventas_diarias ?? [];
    const labels = dataPoints.map(point => fmtDate(point.date));
    const data = dataPoints.map(point => point.revenue);

    const gradient = ctx.createLinearGradient(0, 0, 0, 150);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Ventas',
                data,
                fill: true,
                backgroundColor: gradient,
                borderColor: '#6366f1',
                borderWidth: 2.5,
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
                            return ' ' + fmt(Number(context.raw));
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

    const dataPoints = props.categorias ?? [];
    const labels = dataPoints.map(item => item.categoria);
    const data = dataPoints.map(item => item.cantidad);

    categoriesChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: [
                    '#6366f1',
                    '#3b82f6',
                    '#ec4899',
                    '#f59e0b',
                    '#10b981',
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
                            return ` ${value} prendas`;
                        }
                    }
                }
            },
            cutout: '65%'
        }
    });
};

const initDeliveriesChart = () => {
    if (!deliveriesChartCanvas.value) return;
    if (deliveriesChart) {
        deliveriesChart.destroy();
    }
    const ctx = deliveriesChartCanvas.value.getContext('2d');
    if (!ctx) return;

    const dataPoints = props.envios_diarios ?? [];
    const labels = dataPoints.map(point => fmtDate(point.date));
    const data = dataPoints.map(point => point.count);

    const gradient = ctx.createLinearGradient(0, 0, 0, 120);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

    deliveriesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Entregas',
                data,
                fill: true,
                backgroundColor: gradient,
                borderColor: '#10b981',
                borderWidth: 2.5,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
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
                            return ` ${context.raw} entregas`;
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
    if (props.role_type === 'staff') {
        initSalesChart();
    } else if (props.role_type === 'cliente') {
        initCategoriesChart();
    } else if (props.role_type === 'distribuidor') {
        initDeliveriesChart();
    }
});

onUnmounted(() => {
    if (salesChart) salesChart.destroy();
    if (categoriesChart) categoriesChart.destroy();
    if (deliveriesChart) deliveriesChart.destroy();
});

watch(() => props.ventas_diarias, () => {
    if (props.role_type === 'staff') {
        initSalesChart();
    }
}, { deep: true });

watch(() => props.categorias, () => {
    if (props.role_type === 'cliente') {
        initCategoriesChart();
    }
}, { deep: true });

watch(() => props.envios_diarios, () => {
    if (props.role_type === 'distribuidor') {
        initDeliveriesChart();
    }
}, { deep: true });


const visitaBarMax = computed(() =>
    Math.max(...(props.visitas ?? []).map((v) => v.contador), 1),
);


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
        
        <div class="space-y-6 max-w-7xl mx-auto">

            
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

            
            <template v-if="role_type === 'staff'">

                
                <div class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                    
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

                
                <div class="grid gap-6 md:grid-cols-3">

                    
                    <Card class="md:col-span-2 shadow-sm">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <TrendingUp class="h-4 w-4 text-primary" />
                                <CardTitle class="text-base font-bold text-foreground">Evolución Diaria de Ventas</CardTitle>
                            </div>
                            <CardDescription>Ingresos económicos por día de la última semana.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="props.ventas_diarias && props.ventas_diarias.length > 0" class="space-y-4">
                                <div class="relative w-full h-[180px] bg-muted/20 border border-border/50 rounded-xl p-2">
                                    <canvas ref="salesChartCanvas"></canvas>
                                </div>
                            </div>
                            <div v-else class="flex h-[180px] items-center justify-center text-sm text-muted-foreground">
                                Sin datos suficientes para mostrar
                            </div>
                        </CardContent>
                    </Card>

                    
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

                
                <div class="grid gap-6 md:grid-cols-3">

                    
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

                    
                    <Card class="shadow-sm">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <BarChart2 class="h-4 w-4 text-violet-500" />
                                <CardTitle class="text-base font-bold text-foreground">Categorías Favoritas</CardTitle>
                            </div>
                            <CardDescription>Distribución de tus pijamas comprados.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="props.categorias && props.categorias.length" class="relative w-full h-[180px] p-2">
                                <canvas ref="categoriesChartCanvas"></canvas>
                            </div>
                            <p v-else class="py-12 text-center text-sm text-muted-foreground">No hay estadísticas de categorías.</p>
                        </CardContent>
                    </Card>
                </div>

                
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

                
                <div class="grid gap-6 md:grid-cols-2">

                    
                    <Card class="shadow-sm">
                        <CardHeader>
                            <div class="flex items-center gap-2">
                                <TrendingUp class="h-4 w-4 text-emerald-500" />
                                <CardTitle class="text-base font-bold text-foreground">Tendencia de Entregas</CardTitle>
                            </div>
                            <p class="text-xs text-muted-foreground">Envíos concretados por día en la última semana.</p>
                        </CardHeader>
                        <CardContent>
                            <div v-if="props.envios_diarios && props.envios_diarios.length > 0">
                                <div class="relative w-full h-[140px] bg-muted/20 border border-border/50 rounded-xl p-2">
                                    <canvas ref="deliveriesChartCanvas"></canvas>
                                </div>
                            </div>
                            <div v-else class="flex h-[140px] items-center justify-center text-sm text-muted-foreground">
                                Sin datos de entregas recientes
                            </div>
                        </CardContent>
                    </Card>

                    
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
