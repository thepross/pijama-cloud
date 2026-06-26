<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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

interface UserType {
    id: number;
    nombre: string;
    apellido: string;
    username: string;
}

interface ShipmentType {
    id: number;
    id_pedido: number;
    id_distribuidor: number | null;
    direccion_entrega: string;
    fecha_salida: string | null;
    fecha_entrega: string | null;
    estado_envio: 'pendiente' | 'en_camino' | 'entregado' | 'fallido';
    observacion: string | null;
    ruta: string | null;
    state: string;
    pedido: OrderType;
    distribuidor: UserType | null;
}

const props = defineProps<{
    envio: ShipmentType;
    distribuidores: UserType[];
}>();

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isDistributor = computed(() => userRole.value === 'Distribuidor');

const form = useForm({
    id_distribuidor: props.envio.id_distribuidor ? props.envio.id_distribuidor.toString() : '',
    direccion_entrega: props.envio.direccion_entrega,
    fecha_salida: props.envio.fecha_salida || '',
    fecha_entrega: props.envio.fecha_entrega || '',
    estado_envio: props.envio.estado_envio,
    ruta: props.envio.ruta || '',
    observacion: props.envio.observacion || '',
});

const submit = () => {
    form.put(route('envios.update', props.envio.id));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Envíos', href: '/envios' },
        { title: `Gestionar Despacho #${props.envio.id}`, href: `/envios/${props.envio.id}/edit` }
    ]">
        <Head :title="`Despacho #${props.envio.id}`" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Gestionar Despacho #{{ props.envio.id }}
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Pedido Asociado: #{{ props.envio.id_pedido }} · Cliente: {{ props.envio.pedido.cliente.nombre }} {{ props.envio.pedido.cliente.apellido }}
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
                <!-- Pedido & Distributor info (Staff editable) -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-foreground">Asignación de Despacho</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <!-- If Distributor, show read-only labels. If Admin/Staff, show selects. -->
                        <div class="grid gap-2">
                            <Label>Transportista Asignado</Label>
                            <div v-if="isDistributor" class="p-2.5 rounded-xl border bg-muted font-medium text-foreground">
                                {{ props.envio.distribuidor ? `${props.envio.distribuidor.nombre} ${props.envio.distribuidor.apellido}` : 'Sin asignar' }}
                            </div>
                            <select
                                v-else
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

                        <div class="grid gap-2">
                            <Label>Pedido Relacionado</Label>
                            <div class="p-2.5 rounded-xl border bg-muted font-mono font-bold text-muted-foreground">
                                Pedido #{{ props.envio.id_pedido }} (Total: Bs. {{ Number(props.envio.pedido.total).toFixed(2) }})
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="direccion_entrega">Dirección de Entrega</Label>
                        <div v-if="isDistributor" class="p-2.5 rounded-xl border bg-muted text-foreground">
                            {{ props.envio.direccion_entrega }}
                        </div>
                        <Input
                            v-else
                            id="direccion_entrega"
                            type="text"
                            required
                            v-model="form.direccion_entrega"
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
                        <Label for="observacion">Observaciones / Reporte de Incidencias</Label>
                        <textarea
                            id="observacion"
                            v-model="form.observacion"
                            placeholder="Añade detalles sobre la entrega, novedades o incidencias en el reparto..."
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
                        Guardar Cambios
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
