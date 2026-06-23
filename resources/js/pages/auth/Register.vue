<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    username: '',
    nombre: '',
    apellido: '',
    ci: '',
    email: '',
    telefono: '',
    direccion: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Crear una cuenta" description="Ingresa tus datos a continuación para registrarte en Pijamas Cloud">
        <Head title="Registro" />

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <div class="grid gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="username">Nombre de usuario</Label>
                        <Input id="username" type="text" required autofocus tabindex="1" v-model="form.username" placeholder="ej. juanito12" />
                        <InputError :message="form.errors.username" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="ci">Cédula de Identidad (CI)</Label>
                        <Input id="ci" type="text" required tabindex="2" v-model="form.ci" placeholder="Cédula de identidad" />
                        <InputError :message="form.errors.ci" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="nombre">Nombre</Label>
                        <Input id="nombre" type="text" required tabindex="3" v-model="form.nombre" placeholder="Nombre" />
                        <InputError :message="form.errors.nombre" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="apellido">Apellido</Label>
                        <Input id="apellido" type="text" required tabindex="4" v-model="form.apellido" placeholder="Apellido" />
                        <InputError :message="form.errors.apellido" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input id="email" type="email" required tabindex="5" v-model="form.email" placeholder="correo@ejemplo.com" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="telefono">Teléfono</Label>
                        <Input id="telefono" type="text" tabindex="6" v-model="form.telefono" placeholder="Número de teléfono" />
                        <InputError :message="form.errors.telefono" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="direccion">Dirección</Label>
                    <Input id="direccion" type="text" tabindex="7" v-model="form.direccion" placeholder="Dirección de entrega" />
                    <InputError :message="form.errors.direccion" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="password">Contraseña</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabindex="8"
                            autocomplete="new-password"
                            v-model="form.password"
                            placeholder="Contraseña"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirmar contraseña</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            tabindex="9"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            placeholder="Confirmar contraseña"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="10" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Crear cuenta
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                ¿Ya tienes una cuenta?
                <TextLink :href="route('login')" class="underline underline-offset-4" tabindex="11">Inicia sesión</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
