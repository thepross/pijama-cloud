<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Shield } from 'lucide-vue-next';

interface PermissionType {
    id: number;
    nombre: string;
    descripcion: string | null;
}

const props = defineProps<{
    permissions: PermissionType[];
}>();

const form = useForm({
    nombre: '',
    descripcion: '',
    permissions: [] as number[],
});

const togglePermission = (id: number, checked: boolean) => {
    if (checked) {
        form.permissions.push(id);
    } else {
        const index = form.permissions.indexOf(id);
        if (index > -1) {
            form.permissions.splice(index, 1);
        }
    }
};

const submit = () => {
    form.post(route('roles.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Roles', href: '/roles' },
        { title: 'Crear Rol', href: '/roles/create' }
    ]">
        <Head title="Crear Nuevo Rol" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <Shield class="h-8 w-8 text-primary" />
                        Crear Nuevo Rol
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Registra un nuevo rol y configura sus permisos de acceso al sistema.
                    </p>
                </div>
                <div>
                    <Link :href="route('roles.index')">
                        <Button variant="ghost" class="flex items-center gap-1.5 rounded-xl">
                            <ArrowLeft class="h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Form Card -->
            <form @submit.prevent="submit" class="space-y-6">
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <!-- General details -->
                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="nombre">Nombre del Rol</Label>
                            <Input
                                id="nombre"
                                type="text"
                                required
                                autofocus
                                v-model="form.nombre"
                                placeholder="ej. Supervisor de Envíos"
                                class="rounded-xl border-border bg-card shadow-sm"
                            />
                            <InputError :message="form.errors.nombre" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="descripcion">Descripción</Label>
                            <textarea
                                id="descripcion"
                                v-model="form.descripcion"
                                placeholder="Describe el propósito y responsabilidades de este rol..."
                                rows="3"
                                class="flex w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-y min-h-[80px]"
                            ></textarea>
                            <InputError :message="form.errors.descripcion" />
                        </div>
                    </div>
                </div>

                <!-- Access Matrix Section -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-4">
                    <div>
                        <h2 class="text-lg font-bold text-foreground">Matriz de Accesos (Permisos)</h2>
                        <p class="text-xs text-muted-foreground mt-1">
                            Selecciona los módulos y vistas a las que este rol tendrá acceso.
                        </p>
                        <InputError :message="form.errors.permissions" class="mt-2" />
                    </div>

                    <!-- Permissions Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div
                            v-for="perm in props.permissions"
                            :key="perm.id"
                            :class="[
                                'flex items-start p-4 rounded-xl border transition-all cursor-pointer select-none',
                                form.permissions.includes(perm.id)
                                    ? 'border-primary bg-accent/20'
                                    : 'border-border bg-card hover:bg-accent/10'
                            ]"
                            @click="togglePermission(perm.id, !form.permissions.includes(perm.id))"
                        >
                            <div class="flex items-center h-5 mr-3" @click.stop>
                                <Checkbox
                                    :id="`perm-${perm.id}`"
                                    :checked="form.permissions.includes(perm.id)"
                                    @update:checked="(val) => togglePermission(perm.id, !!val)"
                                />
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`perm-${perm.id}`" class="text-sm font-semibold text-foreground cursor-pointer">
                                    {{ perm.nombre }}
                                </Label>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    {{ perm.descripcion || 'Sin descripción detallada' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('roles.index')">
                        <Button variant="outline" type="button" class="rounded-xl">Cancelar</Button>
                    </Link>
                    <Button type="submit" class="flex items-center gap-1.5 rounded-xl shadow-sm" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Guardar Rol
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
