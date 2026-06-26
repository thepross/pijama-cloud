<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Shield, CheckSquare, Square, MinusSquare } from 'lucide-vue-next';

interface PermissionType {
    id: number;
    nombre: string;
    descripcion: string | null;
    ruta?: string;
    icono?: string | null;
    hijos?: PermissionType[];
}

interface RoleType {
    id: number;
    nombre: string;
    descripcion: string | null;
}

const props = defineProps<{
    role: RoleType;
    permissions: PermissionType[];
    assignedPermissionIds: number[];
}>();

const form = useForm({
    nombre: props.role.nombre,
    descripcion: props.role.descripcion || '',
    permissions: [...props.assignedPermissionIds],
});

// Toggle a single permission
const togglePermission = (id: number, checked: boolean, parentId?: number) => {
    if (checked) {
        if (!form.permissions.includes(id)) {
            form.permissions.push(id);
        }
        // If checking a child permission, auto-check the parent menu so it appears in sidebar navigation
        if (parentId && !form.permissions.includes(parentId)) {
            form.permissions.push(parentId);
        }
    } else {
        const index = form.permissions.indexOf(id);
        if (index > -1) {
            form.permissions.splice(index, 1);
        }
        
        // If unchecking a parent permission, also uncheck all its children
        const parent = props.permissions.find(p => p.id === id);
        if (parent && parent.hijos) {
            parent.hijos.forEach(child => {
                const childIdx = form.permissions.indexOf(child.id);
                if (childIdx > -1) {
                    form.permissions.splice(childIdx, 1);
                }
            });
        }
    }
};

// Toggle all permissions under a parent module
const toggleParentGroup = (parent: PermissionType) => {
    const allIds = [parent.id, ...(parent.hijos || []).map(h => h.id)];
    const allChecked = allIds.every(id => form.permissions.includes(id));

    if (allChecked) {
        // Deselect all
        form.permissions = form.permissions.filter(id => !allIds.includes(id));
    } else {
        // Select all
        allIds.forEach(id => {
            if (!form.permissions.includes(id)) {
                form.permissions.push(id);
            }
        });
    }
};

const isParentPartiallyChecked = (parent: PermissionType) => {
    const allIds = [parent.id, ...(parent.hijos || []).map(h => h.id)];
    const someChecked = allIds.some(id => form.permissions.includes(id));
    const allChecked = allIds.every(id => form.permissions.includes(id));
    return someChecked && !allChecked;
};

const isParentFullyChecked = (parent: PermissionType) => {
    const allIds = [parent.id, ...(parent.hijos || []).map(h => h.id)];
    return allIds.every(id => form.permissions.includes(id));
};

const submit = () => {
    form.put(route('roles.update', props.role.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Roles', href: '/roles' },
        { title: 'Editar Rol', href: `/roles/${props.role.id}/edit` }
    ]">
        <Head title="Editar Rol" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Editar Rol: {{ props.role.nombre }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Modifica los detalles generales y los permisos de acceso y acciones CRUD.
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
                <!-- General Details -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
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
                                class="rounded-xl border-border bg-card shadow-sm font-medium"
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
                <div class="space-y-4">
                    <div>
                        <h2 class="text-lg font-bold text-foreground">Matriz de Accesos y Acciones (CRUD)</h2>
                        <p class="text-xs text-muted-foreground mt-1">
                            Selecciona los módulos a mostrar en el menú y las acciones específicas (ver, crear, editar, eliminar) permitidas.
                        </p>
                        <InputError :message="form.errors.permissions" class="mt-2" />
                    </div>

                    <!-- Permissions Hierarchy Cards -->
                    <div class="space-y-4">
                        <div
                            v-for="parent in props.permissions"
                            :key="parent.id"
                            class="rounded-xl border border-border bg-card shadow-sm overflow-hidden"
                        >
                            <!-- Parent Header Block -->
                            <div class="flex items-center justify-between p-4 bg-muted/30 border-b border-border/60">
                                <div class="flex items-start gap-3">
                                    <div class="flex items-center h-5 mt-0.5">
                                        <Checkbox
                                            :id="`parent-${parent.id}`"
                                            :checked="form.permissions.includes(parent.id)"
                                            @update:checked="(val) => togglePermission(parent.id, !!val)"
                                        />
                                    </div>
                                    <div>
                                        <Label :for="`parent-${parent.id}`" class="text-sm font-bold text-foreground cursor-pointer flex items-center gap-1.5">
                                            {{ parent.nombre }}
                                        </Label>
                                        <p class="text-xs text-muted-foreground mt-0.5">
                                            {{ parent.descripcion || 'Acceso al módulo' }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Master Toggle for children -->
                                <div v-if="parent.hijos && parent.hijos.length" class="flex items-center">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="toggleParentGroup(parent)"
                                        class="h-8 rounded-lg text-xs gap-1.5 px-3 border-border hover:bg-muted/50"
                                    >
                                        <CheckSquare v-if="isParentFullyChecked(parent)" class="h-3.5 w-3.5 text-primary" />
                                        <MinusSquare v-else-if="isParentPartiallyChecked(parent)" class="h-3.5 w-3.5 text-primary" />
                                        <Square v-else class="h-3.5 w-3.5 text-muted-foreground" />
                                        Todos
                                    </Button>
                                </div>
                            </div>

                            <!-- Child Permissions Sub-Grid -->
                            <div v-if="parent.hijos && parent.hijos.length" class="p-4 bg-card grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div
                                    v-for="child in parent.hijos"
                                    :key="child.id"
                                    :class="[
                                        'flex items-center p-3 rounded-lg border transition-all cursor-pointer select-none text-xs',
                                        form.permissions.includes(child.id)
                                            ? 'border-primary bg-primary/5 font-semibold text-foreground'
                                            : 'border-border/60 bg-card hover:bg-muted/30 text-muted-foreground'
                                    ]"
                                    @click="togglePermission(child.id, !form.permissions.includes(child.id), parent.id)"
                                >
                                    <div class="flex items-center h-4 mr-2.5" @click.stop>
                                        <Checkbox
                                            :id="`child-${child.id}`"
                                            :checked="form.permissions.includes(child.id)"
                                            @update:checked="(val) => togglePermission(child.id, !!val, parent.id)"
                                        />
                                    </div>
                                    <Label :for="`child-${child.id}`" class="cursor-pointer text-xs flex-1 truncate">
                                        {{ child.nombre }}
                                    </Label>
                                </div>
                            </div>
                            <div v-else class="p-3 text-center text-xs text-muted-foreground bg-card">
                                Este módulo no cuenta con acciones adicionales.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('roles.index')">
                        <Button variant="outline" type="button" class="rounded-xl">Cancelar</Button>
                    </Link>
                    <Button type="submit" class="flex items-center gap-1.5 rounded-xl shadow-sm" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Guardar Cambios
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
