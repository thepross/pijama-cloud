<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Truck } from 'lucide-vue-next';

interface CustomerType {
    id: number;
    nombre: string;
    apellido: string;
    ci: string;
    direccion: string | null;
}

interface OrderType {
    id: number;
    cliente: CustomerType;
}

interface DistributorType {
    id: number;
    nombre: string;
    apellido: string;
    username: string;
}

const props = defineProps<{
    pedidos: OrderType[];
    distribuidores: DistributorType[];
}>();

const form = useForm({
    id_pedido: '',
    id_distribuidor: '',
    direccion_entrega: '',
    fecha_salida: '',
    fecha_entrega: '',
    estado_envio: 'pendiente',
    ruta: '',
    observacion: '',
});

// Watch id_pedido selection to auto-populate delivery address
watch(() => form.id_pedido, (newId) => {
    if (newId) {
        const order = props.pedidos.find(p => p.id === Number(newId));
        if (order && order.cliente.direccion) {
            form.direccion_entrega = order.cliente.direccion;
        }
    }
});

const submit = () => {
    form.post(route('envios.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Envíos', href: '/envios' },
        { title: 'Registrar Envío', href: '/envios/create' }
    ]">
        <Head title="Registrar Envío" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
                        <Truck class="h-8 w-8 text-primary" />
                        Registrar Envío Manual
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Crea un registro de despacho para un pedido confirmado y asigna un distribuidor.
                    </p>
                </div>
                <div>
                    <Link :href="route('envios.index')">
                        <Button variant="ghost" class="flex items-center gap-1.5 rounded-xl">
                            <ArrowLeft class="h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Pedido & Distributor info -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-foreground">Asignación de Despacho</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="id_pedido">Pedido Confirmado Asociado</Label>
                            <select
                                id="id_pedido"
                                required
                                v-model="form.id_pedido"
                                class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="" disabled>Selecciona un pedido...</option>
                                <option v-for="order in props.pedidos" :key="order.id" :value="order.id">
                                    Pedido #{{ order.id }} - {{ order.cliente.nombre }} {{ order.cliente.apellido }} (CI: {{ order.cliente.ci }})
                                </option>
                            </select>
                            <InputError :message="form.errors.id_pedido" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="id_distribuidor">Distribuidor Asignado</Label>
                            <select
                                id="id_distribuidor"
                                v-model="form.id_distribuidor"
                                class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">Sin asignar (pendiente)</option>
                                <option v-for="dist in props.distribuidores" :key="dist.id" :value="dist.id">
                                    {{ dist.nombre }} {{ dist.apellido }} (@{{ dist.username }})
                                </option>
                            </select>
                            <InputError :message="form.errors.id_distribuidor" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="direccion_entrega">Dirección de Entrega</Label>
                        <Input
                            id="direccion_entrega"
                            type="text"
                            required
                            v-model="form.direccion_entrega"
                            placeholder="Dirección del domicilio o sucursal de entrega..."
                            class="rounded-xl"
                        />
                        <InputError :message="form.errors.direccion_entrega" />
                    </div>
                </div>

                <!-- Parameters card -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-foreground">Fechas y Logística</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="fecha_salida">Fecha de Salida</Label>
                            <Input id="fecha_salida" type="date" v-model="form.fecha_salida" class="rounded-xl" />
                            <InputError :message="form.errors.fecha_salida" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="fecha_entrega">Fecha de Entrega</Label>
                            <Input id="fecha_entrega" type="date" v-model="form.fecha_entrega" class="rounded-xl" />
                            <InputError :message="form.errors.fecha_entrega" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="ruta">Ruta de Reparto</Label>
                            <Input id="ruta" type="text" v-model="form.ruta" placeholder="ej. Ruta Este, Zona Central, etc." class="rounded-xl" />
                            <InputError :message="form.errors.ruta" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="estado_envio">Estado del Envío</Label>
                            <select
                                id="estado_envio"
                                required
                                v-model="form.estado_envio"
                                class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="pendiente">Pendiente (No despachado)</option>
                                <option value="en_camino">En Camino</option>
                                <option value="entregado">Entregado</option>
                                <option value="fallido">Fallido (No concretado)</option>
                            </select>
                            <InputError :message="form.errors.estado_envio" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="observacion">Observaciones / Indicaciones Especiales</Label>
                        <textarea
                            id="observacion"
                            v-model="form.observacion"
                            placeholder="Notas opcionales del transportista o detalles del domicilio..."
                            rows="3"
                            class="flex w-full rounded-xl border border-border bg-card px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        ></textarea>
                        <InputError :message="form.errors.observacion" />
                    </div>
                </div>

                <!-- Submit buttons -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('envios.index')">
                        <Button variant="outline" type="button" class="rounded-xl">Cancelar</Button>
                    </Link>
                    <Button type="submit" class="flex items-center gap-1.5 rounded-xl shadow-sm" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Registrar Despacho
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
