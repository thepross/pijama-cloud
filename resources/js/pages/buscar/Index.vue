<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Users, Shield, Archive, Search, HelpCircle, ArrowRight, ShoppingBag, Truck, CreditCard, AlertTriangle, Tag } from 'lucide-vue-next';

interface SearchResult {
    id: number;
    title: string;
    description: string;
    link: string;
    type: 'Producto' | 'Usuario' | 'Rol' | 'Pedido' | 'Envío' | 'Pago' | 'Reclamo' | 'Oferta';
    icon: string;
}

const props = defineProps<{
    query: string;
    results: SearchResult[];
}>();

const localQuery = ref(props.query || '');
const activeTab = ref('Todos');

const triggerSearch = () => {
    if (localQuery.value.trim().length >= 2) {
        router.get('/buscar', { query: localQuery.value.trim() }, { preserveState: true });
    }
};

const filteredResults = computed(() => {
    if (activeTab.value === 'Todos') {
        return props.results;
    }
    return props.results.filter(r => r.type === activeTab.value);
});

const counts = computed(() => {
    return {
        Todos: props.results.length,
        Producto: props.results.filter(r => r.type === 'Producto').length,
        Usuario: props.results.filter(r => r.type === 'Usuario').length,
        Rol: props.results.filter(r => r.type === 'Rol').length,
        Pedido: props.results.filter(r => r.type === 'Pedido').length,
        Envío: props.results.filter(r => r.type === 'Envío').length,
        Pago: props.results.filter(r => r.type === 'Pago').length,
        Reclamo: props.results.filter(r => r.type === 'Reclamo').length,
        Oferta: props.results.filter(r => r.type === 'Oferta').length,
    };
});

const tabs = [
    { id: 'Todos', label: 'Todos' },
    { id: 'Producto', label: 'Productos' },
    { id: 'Usuario', label: 'Usuarios' },
    { id: 'Rol', label: 'Roles' },
    { id: 'Pedido', label: 'Pedidos' },
    { id: 'Envío', label: 'Envíos' },
    { id: 'Pago', label: 'Pagos' },
    { id: 'Reclamo', label: 'Reclamos' },
    { id: 'Oferta', label: 'Ofertas' },
];

const getIcon = (iconName: string) => {
    switch (iconName) {
        case 'Users': return Users;
        case 'Shield': return Shield;
        case 'Archive': return Archive;
        case 'ShoppingBag': return ShoppingBag;
        case 'Truck': return Truck;
        case 'CreditCard': return CreditCard;
        case 'AlertTriangle': return AlertTriangle;
        case 'Tag': return Tag;
        default: return HelpCircle;
    }
};

const getTypeBadgeClass = (type: string) => {
    switch (type) {
        case 'Producto': return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800/50';
        case 'Usuario': return 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400 border-sky-200 dark:border-sky-800/50';
        case 'Rol': return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800/50';
        case 'Pedido': return 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800/50';
        case 'Envío': return 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400 border-teal-200 dark:border-teal-800/50';
        case 'Pago': return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/50';
        case 'Reclamo': return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800/50';
        case 'Oferta': return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 border-purple-200 dark:border-purple-800/50';
        default: return 'bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-400 border-slate-200 dark:border-slate-800/50';
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Buscador', href: '/buscar' }]">
        <Head title="Resultados de Búsqueda" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground">
                    Resultados de Búsqueda
                </h1>
                <p class="text-sm text-muted-foreground mt-1" v-if="props.query">
                    Resultados de la búsqueda global para: <span class="font-semibold text-foreground">"{{ props.query }}"</span>
                </p>
                <p class="text-sm text-muted-foreground mt-1" v-else>
                    Realiza una búsqueda global en todos los recursos del sistema.
                </p>
            </div>

            <!-- Search input bar -->
            <form @submit.prevent="triggerSearch" class="flex gap-2 max-w-2xl">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="localQuery"
                        type="text"
                        placeholder="Buscar productos, usuarios, pedidos, pagos, reclamos..."
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>
                <Button type="submit" class="rounded-xl shadow-sm px-6 h-10 hover:scale-[1.02] transition-transform">
                    Buscar
                </Button>
            </form>

            <!-- Tabs filters -->
            <div class="flex flex-wrap gap-2 border-b border-border pb-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'px-4 py-2 text-sm font-medium border-b-2 transition-colors',
                        activeTab === tab.id
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    ]"
                >
                    {{ tab.label }} ({{ counts[tab.id as keyof typeof counts] }})
                </button>
            </div>

            <!-- Results Table -->
            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4 w-36">Tipo</th>
                                <th class="p-4 w-1/3">Resultado / Nombre</th>
                                <th class="p-4">Descripción / Detalles</th>
                                <th class="p-4 w-24 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="filteredResults.length === 0">
                                <td colspan="4" class="p-12 text-center text-muted-foreground">
                                    <div class="flex flex-col items-center justify-center space-y-3">
                                        <div class="rounded-full bg-muted p-3">
                                            <Search class="h-6 w-6 text-muted-foreground/60" />
                                        </div>
                                        <div>
                                            <p class="font-medium text-foreground">No se encontraron resultados</p>
                                            <p class="text-xs text-muted-foreground mt-1">
                                                Intenta buscando con palabras clave diferentes o verifica tus permisos.
                                            </p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="item in filteredResults" :key="item.id + '-' + item.type" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4">
                                    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border', getTypeBadgeClass(item.type)]">
                                        <component :is="getIcon(item.icon)" class="h-3.5 w-3.5" />
                                        {{ item.type }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-foreground">{{ item.title }}</div>
                                </td>
                                <td class="p-4 text-muted-foreground">
                                    {{ item.description }}
                                </td>
                                <td class="p-4 text-right">
                                    <Link :href="item.link">
                                        <Button variant="outline" size="sm" class="h-8 px-3 rounded-lg flex items-center gap-1 hover:bg-primary hover:text-primary-foreground group">
                                            <span>Ir</span>
                                            <ArrowRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                                        </Button>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
