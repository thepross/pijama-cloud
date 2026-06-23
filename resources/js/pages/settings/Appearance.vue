<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import ThemeController from '@/components/ThemeController.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Ajustes de apariencia',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Ajustes de apariencia" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Personalización del Sitio" description="Elige tu tema favorito, configura el modo de luz/oscuridad y ajusta las opciones de accesibilidad." />
                <ThemeController />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
