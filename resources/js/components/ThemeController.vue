<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon, Clock, Type, ShieldAlert } from 'lucide-vue-next';

const {
    appearance,
    theme,
    fontSize,
    contrast,
    updateAppearance,
    updateThemePreference,
    updateFontSize,
    updateContrast,
} = useAppearance();

const themesList = [
    { value: 'default', label: 'Por defecto' },
    { value: 'ninos', label: 'Modo Niños' },
    { value: 'jovenes', label: 'Modo Jóvenes' },
    { value: 'adultos', label: 'Modo Adultos' },
] as const;

const appearancesList = [
    { value: 'light', label: 'Día', icon: Sun },
    { value: 'dark', label: 'Noche', icon: Moon },
    { value: 'auto', label: 'Auto (Horario)', icon: Clock },
] as const;

const fontSizesList = [
    { value: 'normal', label: 'Normal' },
    { value: 'large', label: 'Grande' },
    { value: 'xlarge', label: 'Muy Grande' },
] as const;
</script>

<template>
    <div class="space-y-6">
        <div class="space-y-2">
            <h3 class="text-sm font-medium leading-none text-foreground">Seleccionar Tema</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                <button v-for="t in themesList" :key="t.value" @click="updateThemePreference(t.value)" :class="[
                    'flex items-center justify-center p-3 rounded-xl border transition-all text-center',
                    theme === t.value
                        ? 'border-primary bg-primary/5 text-primary font-semibold shadow-sm'
                        : 'border-border bg-card text-foreground hover:bg-accent/50'
                ]">
                    <span class="text-sm font-medium">{{ t.label }}</span>
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <h3 class="text-sm font-medium leading-none text-foreground">Modo Horario (Día / Noche)</h3>
            <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                <button v-for="app in appearancesList" :key="app.value" @click="updateAppearance(app.value)" :class="[
                    'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                    appearance === app.value
                        ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                        : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                ]">
                    <component :is="app.icon" class="h-4 w-4 mr-1.5" />
                    <span class="text-sm">{{ app.label }}</span>
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <h3 class="text-sm font-medium leading-none text-foreground">Tamaño de Letra (Accesibilidad)</h3>
            <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                <button v-for="sz in fontSizesList" :key="sz.value" @click="updateFontSize(sz.value)" :class="[
                    'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                    fontSize === sz.value
                        ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                        : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                ]">
                    <Type class="h-4 w-4 mr-1.5" />
                    <span class="text-sm">{{ sz.label }}</span>
                </button>
            </div>
        </div>

        <div class="space-y-2">
            <h3 class="text-sm font-medium leading-none text-foreground">Alto Contraste</h3>
            <button @click="updateContrast(contrast === 'high' ? 'normal' : 'high')" :class="[
                'flex items-center px-4 py-2 border rounded-xl transition-colors shadow-sm',
                contrast === 'high'
                    ? 'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white font-semibold'
                    : 'bg-card text-foreground border-border hover:bg-accent'
            ]">
                <ShieldAlert class="h-4 w-4 mr-2" />
                <span>{{ contrast === 'high' ? 'Desactivar Alto Contraste' : 'Activar Alto Contraste' }}</span>
            </button>
        </div>
    </div>
</template>
