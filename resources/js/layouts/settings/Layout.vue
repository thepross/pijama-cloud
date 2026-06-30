<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

const sidebarNavItems = [
    {
        title: 'Perfil',
        href: route('profile.edit'),
        active: route().current('profile.edit'),
    },
    {
        title: 'Contraseña',
        href: route('password.edit'),
        active: route().current('password.edit'),
    },
    {
        title: 'Apariencia',
        href: route('appearance'),
        active: route().current('appearance'),
    },
];
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Ajustes" description="Administra la configuración de tu perfil y cuenta" />

        <div class="flex flex-col space-y-8 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': item.active }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
