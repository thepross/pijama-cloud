<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Info, CreditCard, Banknote, QrCode } from 'lucide-vue-next';

interface OrderOption {
    id: number;
    fecha_pedido: string;
    total: number | string;
    estado_pedido: string;
    total_pagado: number;
    saldo_pendiente: number;
    total_cuotas_existente?: number | null;
    cuotas_registradas?: number[];
}

const props = defineProps<{
    pedidos: OrderOption[];
    selected_id_pedido?: string | number | null;
}>();

const form = useForm({
    id_pedido: '' as string | number,
    monto: '',
    tipo_pago: 'qr',
    total_cuotas: 1,
    numero_cuota: 1,
    observacion: '',
});


const cardNumber = ref('');
const cardHolder = ref('');
const cardExpiry = ref('');
const cardCvv = ref('');


const selectedOrder = computed(() => {
    if (!form.id_pedido) {
        return null;
    }
    return props.pedidos.find(p => p.id === Number(form.id_pedido)) || null;
});


const isFirstPayment = computed(() => {
    if (!selectedOrder.value) return true;
    return selectedOrder.value.total_cuotas_existente === null || selectedOrder.value.total_cuotas_existente === undefined;
});


const selectableCuotas = computed(() => {
    if (!selectedOrder.value) return [1];
    
    const total = selectedOrder.value.total_cuotas_existente || form.total_cuotas || 1;
    const registradas = selectedOrder.value.cuotas_registradas || [];
    
    const options = [];
    for (let i = 1; i <= total; i++) {
        if (!registradas.includes(i)) {
            options.push(i);
        }
    }
    return options.length > 0 ? options : [1];
});


watch(selectedOrder, (newOrder) => {
    if (newOrder) {
        form.monto = Number(newOrder.saldo_pendiente).toFixed(2);
        
        if (newOrder.total_cuotas_existente) {
            form.total_cuotas = newOrder.total_cuotas_existente;
            
            
            const available = selectableCuotas.value;
            if (available.length > 0) {
                form.numero_cuota = available[0];
            } else {
                form.numero_cuota = 1;
            }
        } else {
            form.total_cuotas = 1;
            form.numero_cuota = 1;
        }
    } else {
        form.monto = '';
        form.total_cuotas = 1;
        form.numero_cuota = 1;
    }
});


watch(() => form.total_cuotas, (newTotal) => {
    if (isFirstPayment.value) {
        const val = Number(newTotal);
        if (form.numero_cuota > val) {
            form.numero_cuota = 1;
        }
    }
});


onMounted(() => {
    if (props.selected_id_pedido) {
        const orderId = Number(props.selected_id_pedido);
        const exists = props.pedidos.some(p => p.id === orderId);
        if (exists) {
            form.id_pedido = orderId;
        }
    }
});


const cardType = computed(() => {
    const cleanNum = cardNumber.value.replace(/\D/g, '');
    if (cleanNum.startsWith('4')) {
        return 'visa';
    }
    if (/^5[1-5]/.test(cleanNum)) {
        return 'mastercard';
    }
    if (/^3[47]/.test(cleanNum)) {
        return 'amex';
    }
    return 'generic';
});


const formattedCardNumber = computed(() => {
    const cleanNum = cardNumber.value.replace(/\D/g, '').substring(0, 16);
    const parts = [];
    for (let i = 0; i < cleanNum.length; i += 4) {
        parts.push(cleanNum.substring(i, i + 4));
    }
    return parts.join(' ') || '•••• •••• •••• ••••';
});

const submit = () => {
    form.post(route('pagos.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Pagos', href: '/pagos' },
        { title: 'Registrar Pago', href: '/pagos/create' }
    ]">
        <Head title="Registrar Pago" />

        <div class="max-w-5xl mx-auto space-y-6">
            
            <div class="flex items-center gap-4">
                <Link :href="route('pagos.index')">
                    <Button variant="outline" size="icon" class="h-9 w-9 rounded-xl shadow-sm">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Registrar Nuevo Pago
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Realiza o registra abonos (parciales o totales) vinculados a pedidos vigentes.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <div class="lg:col-span-2 space-y-6">
                    <div class="rounded-2xl border border-border bg-card shadow-sm overflow-hidden">
                        <form @submit.prevent="submit" class="p-6 sm:p-8 space-y-6">
                            
                            
                            <div class="space-y-2">
                                <Label for="id_pedido" class="text-sm font-semibold text-foreground">
                                    Seleccionar Pedido <span class="text-destructive">*</span>
                                </Label>
                                <select
                                    id="id_pedido"
                                    v-model="form.id_pedido"
                                    class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">Seleccione un pedido pendiente...</option>
                                    <option
                                        v-for="pedido in props.pedidos"
                                        :key="pedido.id"
                                        :value="pedido.id"
                                    >
                                        Pedido #{{ pedido.id }} — {{ pedido.fecha_pedido }} (Saldo: Bs. {{ Number(pedido.saldo_pendiente).toFixed(2) }})
                                    </option>
                                </select>
                                <InputError :message="form.errors.id_pedido" />
                            </div>

                            
                            <div 
                                v-if="selectedOrder" 
                                class="p-4 rounded-xl border border-border bg-muted/30 grid grid-cols-3 gap-4 text-center animate-in fade-in slide-in-from-top-1"
                            >
                                <div class="space-y-0.5">
                                    <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-wide">Total Pedido</span>
                                    <p class="font-mono text-sm font-bold text-foreground">Bs. {{ Number(selectedOrder.total).toFixed(2) }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-wide font-medium">Abonado</span>
                                    <p class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">Bs. {{ Number(selectedOrder.total_pagado).toFixed(2) }}</p>
                                </div>
                                <div class="space-y-0.5">
                                    <span class="text-[10px] text-muted-foreground uppercase font-bold tracking-wide font-medium">Saldo Pendiente</span>
                                    <p class="font-mono text-sm font-bold text-amber-600 dark:text-amber-400">Bs. {{ Number(selectedOrder.saldo_pendiente).toFixed(2) }}</p>
                                </div>
                            </div>

                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="space-y-2">
                                    <Label for="monto" class="text-sm font-semibold text-foreground">
                                        Monto a Pagar <span class="text-destructive">*</span>
                                    </Label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground font-mono">$</span>
                                        <Input
                                            id="monto"
                                            v-model="form.monto"
                                            type="number"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="pl-7 rounded-xl"
                                        />
                                    </div>
                                    <InputError :message="form.errors.monto" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="numero_cuota" class="text-sm font-semibold text-foreground">
                                        Número de Cuota <span class="text-destructive">*</span>
                                    </Label>
                                    <select
                                        id="numero_cuota"
                                        v-model="form.numero_cuota"
                                        class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    >
                                        <option v-for="cuota in selectableCuotas" :key="cuota" :value="cuota">
                                            Cuota {{ cuota }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.numero_cuota" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="total_cuotas" class="text-sm font-semibold text-foreground">
                                        Total Cuotas <span class="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="total_cuotas"
                                        v-model="form.total_cuotas"
                                        type="number"
                                        min="1"
                                        :disabled="!isFirstPayment"
                                        class="rounded-xl disabled:opacity-80 disabled:bg-muted"
                                    />
                                    <InputError :message="form.errors.total_cuotas" />
                                </div>
                            </div>

                            
                            <div class="space-y-3">
                                <Label class="text-sm font-semibold text-foreground">Método de Pago</Label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    
                                    
                                    <label 
                                        :class="[
                                            'p-4 rounded-xl border-2 flex flex-col items-center justify-center gap-2 cursor-pointer transition-all hover:bg-accent/40',
                                            form.tipo_pago === 'qr' ? 'border-primary bg-primary/5' : 'border-border bg-card'
                                        ]"
                                    >
                                        <input type="radio" value="qr" v-model="form.tipo_pago" class="sr-only" />
                                        <QrCode class="h-6 w-6 text-primary" />
                                        <span class="text-xs font-bold">📱 Código QR</span>
                                        <span class="text-[10px] text-muted-foreground text-center leading-tight">Pasarela PagoFacil</span>
                                    </label>

                                    
                                    <label 
                                        :class="[
                                            'p-4 rounded-xl border-2 flex flex-col items-center justify-center gap-2 cursor-pointer transition-all hover:bg-accent/40',
                                            form.tipo_pago === 'tarjeta' ? 'border-primary bg-primary/5' : 'border-border bg-card'
                                        ]"
                                    >
                                        <input type="radio" value="tarjeta" v-model="form.tipo_pago" class="sr-only" />
                                        <CreditCard class="h-6 w-6 text-primary" />
                                        <span class="text-xs font-bold">💳 Tarjeta</span>
                                        <span class="text-[10px] text-muted-foreground text-center leading-tight">Crédito o Débito</span>
                                    </label>

                                    
                                    <label 
                                        :class="[
                                            'p-4 rounded-xl border-2 flex flex-col items-center justify-center gap-2 cursor-pointer transition-all hover:bg-accent/40',
                                            form.tipo_pago === 'efectivo' ? 'border-primary bg-primary/5' : 'border-border bg-card'
                                        ]"
                                    >
                                        <input type="radio" value="efectivo" v-model="form.tipo_pago" class="sr-only" />
                                        <Banknote class="h-6 w-6 text-primary" />
                                        <span class="text-xs font-bold">💵 Efectivo</span>
                                        <span class="text-[10px] text-muted-foreground text-center leading-tight">Manual / En Tienda</span>
                                    </label>
                                </div>
                            </div>

                            
                            
                            <div 
                                v-if="form.tipo_pago === 'efectivo'"
                                class="p-4 rounded-xl border border-border bg-muted/40 text-xs text-muted-foreground flex gap-2.5 animate-in slide-in-from-bottom-2 fade-in"
                            >
                                <Info class="h-5 w-5 text-primary shrink-0" />
                                <div>
                                    <span class="font-bold text-foreground block">Instrucciones de Pago en Efectivo</span>
                                    Al registrar el pago en efectivo, este quedará en estado <strong class="text-amber-600 dark:text-amber-400">pendiente</strong>. Deberás entregar el dinero físicamente en tienda o a tu vendedor asignado para que lo valide y cambie a estado completado.
                                </div>
                            </div>

                            
                            <div 
                                v-if="form.tipo_pago === 'qr'"
                                class="p-4 rounded-xl border border-border bg-muted/40 text-xs text-muted-foreground flex gap-2.5 animate-in slide-in-from-bottom-2 fade-in"
                            >
                                <Info class="h-5 w-5 text-primary shrink-0" />
                                <div>
                                    <span class="font-bold text-foreground block">Pasarela Digital PagoFacil (QR)</span>
                                    Al presionar Registrar se generará un código QR dinámico mediante la pasarela de PagoFacil. Serás redirigido a la pantalla de abono para escanear y confirmar tu pago.
                                </div>
                            </div>

                            
                            <div 
                                v-if="form.tipo_pago === 'tarjeta'" 
                                class="space-y-4 border-t border-border pt-4 animate-in slide-in-from-bottom-2 fade-in"
                            >
                                <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Datos de la Tarjeta (Checkout Visual)</span>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="card_holder" class="text-xs font-semibold text-foreground">Nombre del Titular</Label>
                                        <Input id="card_holder" v-model="cardHolder" placeholder="JUAN PEREZ" class="rounded-xl" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="card_number" class="text-xs font-semibold text-foreground">Número de Tarjeta</Label>
                                        <Input id="card_number" v-model="cardNumber" placeholder="4000 1234 5678 9010" class="rounded-xl" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <Label for="card_expiry" class="text-xs font-semibold text-foreground">Fecha Expiración</Label>
                                        <Input id="card_expiry" v-model="cardExpiry" placeholder="MM/AA" class="rounded-xl" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="card_cvv" class="text-xs font-semibold text-foreground">CVV</Label>
                                        <Input id="card_cvv" v-model="cardCvv" type="password" maxlength="4" placeholder="•••" class="rounded-xl" />
                                    </div>
                                </div>
                            </div>

                            
                            <div class="space-y-2">
                                <Label for="observacion" class="text-sm font-semibold text-foreground">Nota / Observación</Label>
                                <textarea
                                    id="observacion"
                                    v-model="form.observacion"
                                    rows="3"
                                    placeholder="Escribe alguna referencia (ej. pago parcial de cuota de pijamas, depósito inicial)..."
                                    class="flex min-h-[60px] w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                ></textarea>
                                <InputError :message="form.errors.observacion" />
                            </div>

                            
                            <div class="pt-4 border-t border-border flex items-center justify-end gap-3">
                                <Link :href="route('pagos.index')">
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
                                    Registrar Pago
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>

                
                <div class="lg:col-span-1 space-y-6">
                    <div 
                        v-if="form.tipo_pago === 'tarjeta'" 
                        class="p-6 rounded-2xl border border-border bg-card shadow-sm space-y-6 animate-in slide-in-from-right-3 fade-in duration-300"
                    >
                        <h3 class="text-sm font-bold text-foreground">Vista Previa de Tarjeta</h3>
                        
                        
                        <div class="relative w-full h-48 rounded-xl bg-gradient-to-tr from-slate-900 via-indigo-950 to-slate-900 text-white p-6 shadow-md overflow-hidden flex flex-col justify-between select-none">
                            
                            <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-primary/20 blur-xl"></div>
                            <div class="absolute -left-10 -bottom-10 w-32 h-32 rounded-full bg-indigo-500/20 blur-xl"></div>

                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <span class="text-[8px] uppercase tracking-widest text-slate-400 font-bold">PIJAMAS CLOUD SECURE</span>
                                    <div class="h-7 w-10 rounded bg-amber-500/80 border border-amber-400/40 relative overflow-hidden">
                                        
                                        <div class="absolute top-1/2 left-0 right-0 h-[1px] bg-amber-900/30"></div>
                                        <div class="absolute left-1/2 top-0 bottom-0 w-[1px] bg-amber-900/30"></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm font-black italic block">
                                        {{ cardType === 'visa' ? 'VISA' : cardType === 'mastercard' ? 'Mastercard' : cardType === 'amex' ? 'AMEX' : 'CARD' }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                
                                <p class="font-mono text-lg tracking-widest font-semibold text-center text-slate-100">
                                    {{ formattedCardNumber }}
                                </p>

                                <div class="flex items-center justify-between text-[9px] uppercase tracking-wider text-slate-300">
                                    <div class="space-y-0.5">
                                        <span class="text-[7px] text-slate-400 font-bold block">Titular</span>
                                        <span class="font-mono text-[11px] font-bold block truncate max-w-[130px]">
                                            {{ cardHolder || 'NOMBRE APELLIDO' }}
                                        </span>
                                    </div>
                                    <div class="text-right space-y-0.5">
                                        <span class="text-[7px] text-slate-400 font-bold block">Expira</span>
                                        <span class="font-mono text-[11px] font-bold block">
                                            {{ cardExpiry || 'MM/AA' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground text-center italic">
                            Los datos no se almacenan ni se transmiten. Este pago se confirmará inmediatamente como una simulación estética.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
