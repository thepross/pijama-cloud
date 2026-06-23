<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="flex flex-col min-h-screen">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex-1 p-6 md:p-4">
                <slot />
            </div>
            <footer class="mt-auto border-t border-sidebar-border/50 py-4 px-6 text-center text-xs text-muted-foreground bg-sidebar-background/20">
                <p>© {{ new Date().getFullYear() }} Pijamas Cloud. Todos los derechos reservados.</p>
                <p class="mt-1 font-medium">Visitas a esta página: <span class="text-primary font-bold">{{ page.props.visits_count }}</span></p>
            </footer>
        </AppContent>
    </AppShell>
</template>
