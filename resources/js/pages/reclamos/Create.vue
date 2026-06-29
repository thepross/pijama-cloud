<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, AlertTriangle } from 'lucide-vue-next';

interface OrderType {
    id: number;
    fecha_pedido: string;
    total: number | string;
    estado_pedido: string;
}

const props = defineProps<{
    pedidos: OrderType[];
}>();

const form = useForm({
    tipo_reclamo: '',
    id_pedido: '' as string | number,
    descripcion: '',
});

const submit = () => {
    
    const submitData = {
        ...form,
        id_pedido: form.id_pedido === '' ? null : form.id_pedido
    };
    
    
    form.transform((data) => ({
        ...data,
        id_pedido: data.id_pedido === '' ? null : Number(data.id_pedido)
    })).post(route('reclamos.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Reclamos y Comentarios', href: '/reclamos' },
        { title: 'Registrar Reclamo', href: '/reclamos/create' }
    ]">
        <Head title="Registrar Reclamo" />

        <div class="max-w-3xl mx-auto space-y-6">
            
            <div class="flex items-center gap-4">
                <Link :href="route('reclamos.index')">
                    <Button variant="outline" size="icon" class="h-9 w-9 rounded-xl shadow-sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Registrar Reclamo o Comentario
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Describe el inconveniente para que nuestro equipo pueda darte una pronta solución.
                    </p>
                </div>
            </div>

            
            <div class="rounded-2xl border border-border bg-card shadow-sm overflow-hidden">
                <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">
                    
                    <div class="space-y-2">
                        <Label for="tipo_reclamo" class="text-sm font-semibold text-foreground">
                            Tipo de Reclamo / Comentario <span class="text-destructive">*</span>
                        </Label>
                        <select
                            id="tipo_reclamo"
                            v-model="form.tipo_reclamo"
                            class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">Seleccione una opción...</option>
                            <option value="Retraso de envío">Retraso de envío</option>
                            <option value="Prenda defectuosa">Prenda defectuosa</option>
                            <option value="Talla/Color incorrecto">Talla o Color incorrecto</option>
                            <option value="Mala atención">Mala atención</option>
                            <option value="Otro">Otro / Sugerencia general</option>
                        </select>
                        <InputError :message="form.errors.tipo_reclamo" />
                    </div>

                    
                    <div class="space-y-2">
                        <Label for="id_pedido" class="text-sm font-semibold text-foreground">
                            Vincular a un Pedido (Opcional)
                        </Label>
                        <select
                            id="id_pedido"
                            v-model="form.id_pedido"
                            class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">No aplica / Ninguno</option>
                            <option
                                v-for="pedido in props.pedidos"
                                :key="pedido.id"
                                :value="pedido.id"
                            >
                                Pedido #{{ pedido.id }} — {{ pedido.fecha_pedido }} (Bs. {{ Number(pedido.total).toFixed(2) }}) [{{ pedido.estado_pedido }}]
                            </option>
                        </select>
                        <p class="text-xs text-muted-foreground">
                            Asocia tu reclamo a una compra específica para agilizar la revisión.
                        </p>
                        <InputError :message="form.errors.id_pedido" />
                    </div>

                    
                    <div class="space-y-2">
                        <Label for="descripcion" class="text-sm font-semibold text-foreground">
                            Detalles del Reclamo <span class="text-destructive">*</span>
                        </Label>
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            rows="6"
                            placeholder="Por favor, describe detalladamente la situación (ej. talla errónea, costura suelta, demora del repartidor)..."
                            class="flex min-h-[80px] w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        ></textarea>
                        <div class="flex justify-between items-center text-xs text-muted-foreground">
                            <span>Sé lo más descriptivo posible.</span>
                            <span :class="{'text-destructive': form.descripcion.length > 2000}">
                                {{ form.descripcion.length }}/2000
                            </span>
                        </div>
                        <InputError :message="form.errors.descripcion" />
                    </div>

                    
                    <div class="pt-4 border-t border-border flex items-center justify-end gap-3">
                        <Link :href="route('reclamos.index')">
                            <Button variant="outline" type="button" class="rounded-xl">
                                Cancelar
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="flex items-center gap-1.5 rounded-xl shadow-sm hover:scale-[1.01] transition-transform"
                        >
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                            Registrar Reclamo
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
