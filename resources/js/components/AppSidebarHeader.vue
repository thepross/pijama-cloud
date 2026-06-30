<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Paintbrush, Search, Loader2, Users, Shield, Archive, HelpCircle, ShoppingBag, Truck, CreditCard, AlertTriangle, Tag } from 'lucide-vue-next';
import ThemeController from '@/components/ThemeController.vue';
import type { BreadcrumbItemType } from '@/types';
import { ref, watch, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage();
const formattedBreadcrumbs = computed(() => {
    const baseUrl = route('home');
    const cleanBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
    return (props.breadcrumbs || []).map(item => {
        if (item.href && item.href.startsWith('/')) {
            return {
                ...item,
                href: cleanBase + item.href,
            };
        }
        return item;
    });
});

const searchQuery = ref('');
const searchResults = ref<any[]>([]);

const formatLink = (link: string) => {
    if (link && link.startsWith('/')) {
        const baseUrl = route('home');
        const cleanBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
        return cleanBase + link;
    }
    return link;
};
const isSearching = ref(false);
const showResults = ref(false);

let debounceTimeout: any = null;

const performSearch = async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }
    isSearching.value = true;
    try {
        const response = await fetch(route('global-search', { query: searchQuery.value }));
        if (response.ok) {
            searchResults.value = await response.json();
        }
    } catch (error) {
        console.error('Error fetching search results:', error);
    } finally {
        isSearching.value = false;
    }
};

watch(searchQuery, (newVal) => {
    clearTimeout(debounceTimeout);
    if (!newVal || newVal.length < 2) {
        searchResults.value = [];
        return;
    }
    debounceTimeout = setTimeout(() => {
        performSearch();
    }, 300);
});

const triggerFullSearch = () => {
    if (searchQuery.value.trim().length >= 2) {
        showResults.value = false;
        router.visit(route('buscar', { query: searchQuery.value.trim() }));
    }
};

const handleBlur = () => {
    setTimeout(() => {
        showResults.value = false;
    }, 200);
};

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
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="formattedBreadcrumbs.length > 0">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(item, index) in formattedBreadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === formattedBreadcrumbs.length - 1">
                                    <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink :href="item.href">
                                        {{ item.title }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== formattedBreadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>

        <div class="flex items-center gap-3">
            <!-- Search bar -->
            <form @submit.prevent="triggerFullSearch" class="relative w-48 sm:w-64">
                <button type="submit" class="absolute left-2.5 top-2.5 text-muted-foreground hover:text-foreground">
                    <Search class="h-4 w-4" />
                </button>
                <Input
                    type="search"
                    placeholder="Buscar en todo el sitio..."
                    v-model="searchQuery"
                    class="pl-8 h-9 text-sm"
                    @focus="showResults = true"
                    @blur="handleBlur"
                />
                <!-- Dropdown results panel -->
                <div
                    v-if="showResults && (isSearching || searchResults.length > 0 || (searchQuery.length >= 2 && searchResults.length === 0))"
                    class="absolute right-0 top-full mt-1.5 w-72 sm:w-96 rounded-lg border border-border bg-card text-card-foreground shadow-lg z-50 p-2 max-h-80 overflow-y-auto"
                >
                    <div v-if="isSearching" class="p-3 text-center text-sm text-muted-foreground flex items-center justify-center gap-2">
                        <Loader2 class="h-4 w-4 animate-spin" />
                        <span>Buscando...</span>
                    </div>
                    <div v-else-if="searchResults.length === 0 && searchQuery.length >= 2" class="p-3 text-center text-sm text-muted-foreground">
                        No se encontraron resultados para "{{ searchQuery }}"
                    </div>
                    <div v-else class="space-y-1">
                        <Link
                            v-for="item in searchResults"
                            :key="item.id + '-' + item.type"
                            :href="formatLink(item.link)"
                            class="flex items-start gap-2.5 rounded-md p-2 hover:bg-accent hover:text-accent-foreground text-left transition-colors"
                            @click="showResults = false"
                        >
                            <div class="mt-0.5 rounded p-1 bg-muted text-muted-foreground">
                                <component :is="getIcon(item.icon)" class="h-4 w-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate flex items-center gap-2">
                                    <span class="truncate">{{ item.title }}</span>
                                    <span class="text-[10px] uppercase tracking-wider font-semibold px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">
                                        {{ item.type }}
                                    </span>
                                </div>
                                <div class="text-xs text-muted-foreground truncate">
                                    {{ item.description }}
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </form>

            <!-- Theme Customization Dialog -->
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="ghost" size="icon" class="h-9 w-9 rounded-lg" title="Personalizar tema y accesibilidad">
                        <Paintbrush class="h-5 w-5 text-muted-foreground hover:text-foreground" />
                    </Button>
                </DialogTrigger>
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Personalizar Aspecto y Accesibilidad</DialogTitle>
                        <DialogDescription>
                            Adapta el diseño de Pijamas Cloud a tus preferencias visuales y de accesibilidad.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="mt-4 max-h-[70vh] overflow-y-auto px-1">
                        <ThemeController />
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    </header>
</template>
