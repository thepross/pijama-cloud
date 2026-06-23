<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Users } from 'lucide-vue-next';

interface RoleType {
    id: number;
    nombre: string;
}

interface UserType {
    id: number;
    username: string;
    nombre: string;
    apellido: string;
    email: string;
    ci: string;
    telefono: string | null;
    direccion: string | null;
    id_rol: number;
}

const props = defineProps<{
    usuario: UserType;
    roles: RoleType[];
}>();

const form = useForm({
    username: props.usuario.username,
    nombre: props.usuario.nombre,
    apellido: props.usuario.apellido,
    ci: props.usuario.ci,
    email: props.usuario.email,
    telefono: props.usuario.telefono || '',
    direccion: props.usuario.direccion || '',
    id_rol: props.usuario.id_rol,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(route('usuarios.update', props.usuario.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Usuarios', href: '/usuarios' },
        { title: 'Editar Usuario', href: `/usuarios/${props.usuario.id}/edit` }
    ]">
        <Head title="Editar Usuario" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <Users class="h-8 w-8 text-primary" />
                        Editar Usuario: {{ props.usuario.nombre }} {{ props.usuario.apellido }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Actualiza la información personal, asignación de rol o cambia la contraseña del usuario.
                    </p>
                </div>
                <div>
                    <Link :href="route('usuarios.index')">
                        <Button variant="ghost" class="flex items-center gap-1.5 rounded-xl">
                            <ArrowLeft class="h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- General Info Card -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-foreground">Información Personal y de Contacto</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="nombre">Nombre</Label>
                            <Input id="nombre" type="text" required autofocus v-model="form.nombre" placeholder="Nombre" class="rounded-xl" />
                            <InputError :message="form.errors.nombre" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="apellido">Apellido</Label>
                            <Input id="apellido" type="text" required v-model="form.apellido" placeholder="Apellido" class="rounded-xl" />
                            <InputError :message="form.errors.apellido" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="ci">Cédula de Identidad (CI)</Label>
                            <Input id="ci" type="text" required v-model="form.ci" placeholder="Cédula de identidad" class="rounded-xl" />
                            <InputError :message="form.errors.ci" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="telefono">Teléfono</Label>
                            <Input id="telefono" type="text" v-model="form.telefono" placeholder="Número de teléfono" class="rounded-xl" />
                            <InputError :message="form.errors.telefono" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="direccion">Dirección</Label>
                        <Input id="direccion" type="text" v-model="form.direccion" placeholder="Dirección de entrega o sucursal" class="rounded-xl" />
                        <InputError :message="form.errors.direccion" />
                    </div>
                </div>

                <!-- Account Credentials Card -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-foreground">Credenciales e Identificación de Cuenta</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="username">Nombre de Usuario</Label>
                            <Input id="username" type="text" required v-model="form.username" placeholder="ej. juanperez1" class="rounded-xl" />
                            <InputError :message="form.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Correo Electrónico</Label>
                            <Input id="email" type="email" required v-model="form.email" placeholder="correo@ejemplo.com" class="rounded-xl" />
                            <InputError :message="form.errors.email" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="id_rol">Rol asignado</Label>
                            <select
                                id="id_rol"
                                required
                                v-model="form.id_rol"
                                class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="" disabled>Selecciona un rol...</option>
                                <option v-for="role in props.roles" :key="role.id" :value="role.id">
                                    {{ role.nombre }}
                                </option>
                            </select>
                            <InputError :message="form.errors.id_rol" />
                        </div>
                    </div>

                    <!-- Password fields (optional) -->
                    <div class="p-4 rounded-xl border border-dashed border-border bg-muted/20 space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">Cambiar Contraseña (Opcional)</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Deja estos campos en blanco si deseas conservar la contraseña actual.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="grid gap-2">
                                <Label for="password">Nueva Contraseña</Label>
                                <Input id="password" type="password" v-model="form.password" placeholder="Nueva contraseña de la cuenta" class="rounded-xl bg-card" />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">Confirmar Nueva Contraseña</Label>
                                <Input id="password_confirmation" type="password" v-model="form.password_confirmation" placeholder="Confirmar nueva contraseña" class="rounded-xl bg-card" />
                                <InputError :message="form.errors.password_confirmation" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit buttons -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('usuarios.index')">
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
