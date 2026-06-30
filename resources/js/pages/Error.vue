<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    status: number;
    message?: string;
}>();

const errorDetails = computed(() => {
    const code = props.status;
    switch (code) {
        case 403:
            return {
                title: 'Acceso Denegado',
                description: props.message || 'No tienes los permisos necesarios para acceder a esta sección.',
            };
        case 404:
            return {
                title: 'Página No Encontrada',
                description: 'La página que buscas no existe o ha sido movida.',
            };
        default:
            return {
                title: 'Error de Servidor',
                description: 'Ha ocurrido un error inesperado. Por favor, inténtalo más tarde.',
            };
    }
});
</script>

<template>
    <div class="min-h-screen w-full flex flex-col items-center justify-center bg-background text-foreground px-6 py-12">
        <Head :title="`${props.status} - ${errorDetails.title}`" />

        <div class="w-full max-w-md text-center space-y-6">
            <!-- Large Status Code -->
            <div class="text-9xl font-black tracking-tighter text-primary/10 select-none">
                {{ props.status }}
            </div>

            <!-- Title & Description -->
            <div class="space-y-2">
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    {{ errorDetails.title }}
                </h1>
                <p class="text-muted-foreground text-sm max-w-xs mx-auto leading-relaxed">
                    {{ errorDetails.description }}
                </p>
            </div>

            <!-- Action Button -->
            <div class="pt-4">
                <Link :href="route('dashboard')">
                    <Button class="rounded-xl px-6 h-10 gap-2 shadow-sm">
                        <ArrowLeft class="h-4 w-4" />
                        Volver al Dashboard
                    </Button>
                </Link>
            </div>
        </div>
    </div>
</template>
