<script setup lang="ts">
interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
        <svg viewBox="0 0 24 24" class="size-5 stroke-primary-foreground fill-none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 10V5C3 4.44772 3.44772 4 4 4H8C8 5 8.8 7 12 7C15.2 7 16 5 16 4H20C20.5523 4 21 4.44772 21 5V10C21 10.5523 20.5523 11 20 11H19C18.4477 11 18 11.4477 18 12V19C18 19.5523 17.5523 20 17 20H7C6.44772 20 6 19.5523 6 19V12C6 11.4477 5.55228 11 5 11H4C3.44772 11 3 10.5523 3 10Z" />
        </svg>
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate font-semibold leading-none text-foreground">Pijamas Cloud</span>
    </div>
</template>
