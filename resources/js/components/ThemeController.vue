<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Sun, Moon, Clock, Type, ShieldAlert, Sparkles, Smile, ShieldCheck, HelpCircle } from 'lucide-vue-next';

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
    { value: 'default', label: 'Ecológico (Default)', icon: Sparkles, colorClass: 'bg-neutral-200 dark:bg-neutral-800' },
    { value: 'ninos', label: 'Modo Niños', icon: Smile, colorClass: 'bg-pink-100 text-pink-600 dark:bg-pink-900/20' },
    { value: 'jovenes', label: 'Modo Jóvenes', icon: HelpCircle, colorClass: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/20' },
    { value: 'adultos', label: 'Modo Adultos', icon: ShieldCheck, colorClass: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/20' },
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
    <div class="space-y-8">
        <!-- Theme Picker -->
        <div class="space-y-3">
            <h3 class="text-sm font-medium leading-none text-foreground">Seleccionar Tema</h3>
            <p class="text-xs text-muted-foreground">Elige una de las 3 experiencias visuales personalizadas.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                <button
                    v-for="t in themesList"
                    :key="t.value"
                    @click="updateThemePreference(t.value)"
                    :class="[
                        'flex flex-col items-center justify-center p-4 rounded-xl border-2 transition-all hover:scale-[1.02]',
                        theme === t.value
                            ? 'border-primary bg-accent/50 shadow-md'
                            : 'border-border bg-card'
                    ]"
                >
                    <div :class="['p-3 rounded-full mb-2', t.colorClass]">
                        <component :is="t.icon" class="h-6 w-6" />
                    </div>
                    <span class="text-sm font-medium text-foreground">{{ t.label }}</span>
                </button>
            </div>
        </div>

        <!-- Mode Picker (Day/Night) -->
        <div class="space-y-3">
            <h3 class="text-sm font-medium leading-none text-foreground">Modo Horario (Día / Noche)</h3>
            <p class="text-xs text-muted-foreground">Cambia el aspecto del sitio según tus preferencias o configura el modo automático basado en tu hora local.</p>
            <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                <button
                    v-for="app in appearancesList"
                    :key="app.value"
                    @click="updateAppearance(app.value)"
                    :class="[
                        'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                        appearance === app.value
                            ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    ]"
                >
                    <component :is="app.icon" class="h-4 w-4 mr-1.5" />
                    <span class="text-sm">{{ app.label }}</span>
                </button>
            </div>
        </div>

        <!-- Font Size Accessibility -->
        <div class="space-y-3">
            <h3 class="text-sm font-medium leading-none text-foreground">Tamaño de Letra (Accesibilidad)</h3>
            <p class="text-xs text-muted-foreground">Ajusta el tamaño del texto para una lectura más cómoda.</p>
            <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
                <button
                    v-for="sz in fontSizesList"
                    :key="sz.value"
                    @click="updateFontSize(sz.value)"
                    :class="[
                        'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                        fontSize === sz.value
                            ? 'bg-white shadow-sm dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    ]"
                >
                    <Type class="h-4 w-4 mr-1.5" />
                    <span class="text-sm">{{ sz.label }}</span>
                </button>
            </div>
        </div>

        <!-- High Contrast Accessibility -->
        <div class="space-y-3">
            <h3 class="text-sm font-medium leading-none text-foreground">Alto Contraste</h3>
            <p class="text-xs text-muted-foreground">Mejora la legibilidad activando el contraste extremo en blanco y negro.</p>
            <button
                @click="updateContrast(contrast === 'high' ? 'normal' : 'high')"
                :class="[
                    'flex items-center px-4 py-2 border rounded-xl transition-colors shadow-sm',
                    contrast === 'high'
                        ? 'bg-black text-white dark:bg-white dark:text-black border-black dark:border-white font-semibold'
                        : 'bg-card text-foreground border-border hover:bg-accent'
                ]"
            >
                <ShieldAlert class="h-4 w-4 mr-2" />
                <span>{{ contrast === 'high' ? 'Desactivar Alto Contraste' : 'Activar Alto Contraste' }}</span>
            </button>
        </div>
    </div>
</template>
