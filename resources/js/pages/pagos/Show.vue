<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    ArrowLeft, CheckCircle, Ban, AlertTriangle, LoaderCircle, Printer, QrCode,
    ShieldAlert, Banknote, CreditCard, Clock, Calendar, ShoppingBag, User
} from 'lucide-vue-next';

interface UserType {
    id: number;
    nombre: string;
    apellido: string;
    email: string;
    username: string;
    ci: string;
    telefono: string | null;
}

interface OrderType {
    id: number;
    fecha_pedido: string;
    total: number | string;
    estado_pedido: string;
    cliente: UserType;
}

interface PaymentType {
    id: number;
    id_pedido: number;
    monto: number | string;
    fecha_pago: string;
    tipo_pago: 'efectivo' | 'tarjeta' | 'qr';
    estado_pago: 'pendiente' | 'completado' | 'fallido';
    total_cuotas: number;
    numero_cuota: number;
    saldo_pendiente: number | string;
    observacion: string | null;
    pedido: OrderType;
    transaction_id: string | null;
}

const props = defineProps<{
    pago: PaymentType;
    flash?: { success?: string | null; error?: string | null };
}>();

console.log("Transaction ID:", props.pago.transaction_id);

const page = usePage();
const userRole = computed(() => (page.props.auth as any)?.user?.role?.nombre || '');
const isClient = computed(() => userRole.value === 'Cliente');
const isAdmin = computed(() => userRole.value === 'Administrador');
const isStaff = computed(() => ['Administrador', 'Vendedor'].includes(userRole.value));

const showDeleteDialog = ref(false);

const timeLeft = ref(120);
let timerInterval: any = null;

const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
};

onMounted(() => {
    if (props.pago.tipo_pago === 'qr' && props.pago.estado_pago === 'pendiente') {
        timerInterval = setInterval(() => {
            if (timeLeft.value > 0) {
                timeLeft.value--;
                if (timeLeft.value % 5 === 0) {
                    router.reload({
                        only: ['pago', 'flash'],
                        onSuccess: () => {
                            if (props.pago.estado_pago === 'completado') {
                                clearInterval(timerInterval);
                            }
                        }
                    });
                }
            } else {
                clearInterval(timerInterval);
            }
        }, 1000);
    }
});

onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
    }
});

const formCallback = useForm({});
const formManual = useForm({
    estado_pago: 'completado',
    observacion: props.pago.observacion || '',
});

const triggerCallback = () => {
    formCallback.post(route('pagos.simular-callback', props.pago.id), {
        preserveScroll: true,
    });
};

const confirmManualPayment = () => {
    formManual.put(route('pagos.update', props.pago.id), {
        preserveScroll: true,
    });
};

const deletePaymentAdmin = () => {
    router.delete(route('pagos.destroy', props.pago.id), {
        onSuccess: () => {
            showDeleteDialog.value = false;
        }
    });
};

const getStatusBadge = (status: string) => {
    const map: Record<string, { label: string; class: string }> = {
        pendiente: { label: 'Pendiente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50' },
        completado: { label: 'Completado', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50' },
        fallido: { label: 'Fallido', class: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400 border border-red-200 dark:border-red-900/50' },
    };
    return map[status] || { label: status, class: 'bg-neutral-100 text-neutral-800' };
};

const getMethodLabel = (method: string) => {
    const map: Record<string, string> = {
        efectivo: 'Efectivo',
        tarjeta: 'Tarjeta',
        qr: 'QR PagoFacil',
    };
    return map[method] || method;
};

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Pagos', href: '/pagos' },
        { title: `Recibo de Pago #${props.pago.id}`, href: `/pagos/${props.pago.id}` }
    ]">

        <Head :title="`Pago #${props.pago.id}`" />

        <div class="space-y-6 max-w-4xl mx-auto">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-foreground">
                            Recibo de Pago #{{ props.pago.id }}
                            <span
                                class="items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-700 dark:bg-gray-950/40 dark:text-gray-400 border border-gray-200 dark:border-gray-900/50">
                                {{ props.pago.transaction_id || 'N/A' }}
                            </span>
                        </h1>
                        <div class="flex items-center gap-2 text-xs text-muted-foreground mt-0.5">
                            <span class="font-medium text-foreground uppercase tracking-wider text-primary">
                                {{ getMethodLabel(props.pago.tipo_pago) }}
                            </span>
                            <span>•</span>
                            <span>Fecha: {{ props.pago.fecha_pago }}</span>
                            <span>•</span>
                            <span
                                :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold', getStatusBadge(props.pago.estado_pago).class]">
                                {{ getStatusBadge(props.pago.estado_pago).label }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="props.pago.estado_pago === 'completado'" variant="outline" size="sm"
                        @click="printReceipt" class="flex items-center gap-1 rounded-xl">
                        <Printer class="h-4 w-4" />
                        Imprimir Recibo
                    </Button>
                    <Link :href="route('pagos.index')">
                        <Button variant="ghost" class="flex items-center gap-1.5 rounded-xl">
                            <ArrowLeft class="h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                </div>
            </div>

            <div v-if="props.flash?.success"
                class="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-sm shadow-sm">
                <CheckCircle class="h-4 w-4" />
                {{ props.flash.success }}
            </div>
            <div v-if="props.flash?.error"
                class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive flex items-center gap-2 text-sm shadow-sm">
                <Ban class="h-4 w-4" />
                {{ props.flash.error }}
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2 space-y-6">
                    <div id="receipt-card"
                        class="p-8 rounded-2xl border border-border bg-card shadow-sm space-y-6 relative overflow-hidden">
                        <div v-if="props.pago.estado_pago === 'fallido'"
                            class="absolute right-6 top-6 border-4 border-red-500/40 text-red-500/40 text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-xl rotate-12 scale-110 pointer-events-none select-none font-mono">
                            ANULADO
                        </div>

                        <div class="border-b border-dashed border-border pb-6 space-y-2">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Detalle de la
                                Transacción</h2>
                            <div class="flex justify-between items-baseline pt-2">
                                <span class="text-sm text-muted-foreground">Monto Abonado</span>
                                <span class="font-mono text-3xl font-black text-foreground">
                                    Bs. {{ Number(props.pago.monto).toFixed(2) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                            <div class="space-y-1">
                                <span
                                    class="text-xs text-muted-foreground uppercase font-bold tracking-wider flex items-center gap-1">
                                    <User class="h-3.5 w-3.5 text-primary" />
                                    Cliente
                                </span>
                                <p class="font-semibold text-foreground" v-if="props.pago.pedido?.cliente">
                                    {{ props.pago.pedido.cliente.nombre }} {{ props.pago.pedido.cliente.apellido }}
                                </p>
                                <p class="text-xs text-muted-foreground" v-if="props.pago.pedido?.cliente">
                                    C.I. {{ props.pago.pedido.cliente.ci }} · @{{ props.pago.pedido.cliente.username }}
                                </p>
                            </div>
                            <div class="space-y-1">
                                <span
                                    class="text-xs text-muted-foreground uppercase font-bold tracking-wider flex items-center gap-1">
                                    <ShoppingBag class="h-3.5 w-3.5 text-primary" />
                                    Pedido Relacionado
                                </span>
                                <p class="font-semibold text-foreground">
                                    Pedido #{{ props.pago.id_pedido }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Monto original del pedido: Bs. {{ Number(props.pago.pedido?.total).toFixed(2) }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-2 sm:grid-cols-3 gap-6 text-xs p-4 rounded-xl bg-muted/40 border border-border">
                            <div>
                                <span
                                    class="text-muted-foreground font-bold uppercase tracking-wider block">Método</span>
                                <span class="font-semibold text-foreground block mt-0.5">
                                    {{ getMethodLabel(props.pago.tipo_pago) }}
                                </span>
                            </div>
                            <div>
                                <span
                                    class="text-muted-foreground font-bold uppercase tracking-wider block">Cuotas</span>
                                <span class="font-semibold text-foreground block mt-0.5">
                                    Cuota {{ props.pago.numero_cuota }} de {{ props.pago.total_cuotas }}
                                </span>
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <span class="text-muted-foreground font-bold uppercase tracking-wider block">Saldo
                                    Pendiente Restante</span>
                                <span class="font-mono font-bold text-foreground block mt-0.5">
                                    Bs. {{ Number(props.pago.saldo_pendiente).toFixed(2) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="props.pago.observacion" class="space-y-1 pt-2">
                            <span class="text-xs text-muted-foreground uppercase font-bold tracking-wider">Nota /
                                Observación</span>
                            <p
                                class="text-xs text-foreground bg-muted/20 p-3 rounded-lg border border-border italic leading-relaxed">
                                "{{ props.pago.observacion }}"
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div v-if="props.pago.tipo_pago === 'qr' && props.pago.estado_pago === 'pendiente'"
                        class="p-6 rounded-2xl border border-border bg-card shadow-sm text-center space-y-4 animate-in slide-in-from-right-2 fade-in">
                        <h3 class="text-sm font-bold text-foreground flex items-center justify-center gap-1.5">
                            <QrCode class="h-5 w-5 text-primary" />
                            Pago QR PagoFacil
                        </h3>

                        <div
                            class="h-56 w-56 mx-auto bg-muted border border-border rounded-xl flex items-center justify-center overflow-hidden p-2">
                            <img v-if="props.pago.qr_base64" :src="`data:image/png;base64,${props.pago.qr_base64}`"
                                class="h-full w-full object-contain" alt="PagoFacil QR" />
                            <div v-else class="text-xs text-muted-foreground flex flex-col items-center gap-2">
                                <LoaderCircle class="h-5 w-5 animate-spin text-primary" />
                                Generando código QR...
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-center gap-1.5 text-xs font-semibold text-muted-foreground">
                            <Clock class="h-4 w-4 text-primary" />
                            <span>El código QR expira en: </span>
                            <span class="font-mono font-bold text-foreground">{{ formatTime(timeLeft) }}</span>
                        </div>

                        <div
                            class="p-3 rounded-xl border border-primary/20 bg-primary/5 text-xs text-primary flex items-center justify-center gap-2 animate-pulse">
                            <LoaderCircle class="h-4 w-4 animate-spin shrink-0" />
                            <span>Esperando confirmación de cobro...</span>
                        </div>

                        <Button @click="triggerCallback" :disabled="formCallback.processing || timeLeft === 0"
                            class="w-full flex items-center justify-center gap-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 shadow-sm transition-transform hover:scale-[1.02]">
                            <LoaderCircle v-if="formCallback.processing" class="h-4 w-4 animate-spin" />
                            <QrCode v-else class="h-4 w-4" />
                            Simular Escaneo y Pago
                        </Button>
                    </div>

                    <div v-if="props.pago.tipo_pago === 'efectivo' && props.pago.estado_pago === 'pendiente'"
                        class="p-6 rounded-2xl border border-border bg-card shadow-sm space-y-4 animate-in slide-in-from-right-2 fade-in">
                        <h3 class="text-sm font-bold text-foreground flex items-center gap-1.5">
                            <Banknote class="h-5 w-5 text-primary" />
                            Confirmar Efectivo
                        </h3>

                        <div v-if="isClient"
                            class="text-xs leading-relaxed text-muted-foreground p-4 bg-muted/40 border rounded-xl">
                            ⏳ Tu pago está registrado y pendiente de confirmación física de efectivo. Por favor, entrega
                            el monto de <strong>Bs. {{ Number(props.pago.monto).toFixed(2) }}</strong> a tu vendedor
                            asignado en tienda para acreditar tu abono.
                        </div>

                        <div v-else class="space-y-4">
                            <p class="text-xs text-muted-foreground">
                                Confirma que has recibido los <strong>Bs. {{ Number(props.pago.monto).toFixed(2)
                                }}</strong> en efectivo físicamente.
                            </p>
                            <div class="space-y-2">
                                <Label for="manual_obs" class="text-xs font-semibold text-foreground">Comentario de
                                    conciliación</Label>
                                <textarea id="manual_obs" v-model="formManual.observacion" rows="2"
                                    placeholder="Confirmado efectivo en caja principal..."
                                    class="flex min-h-[40px] w-full rounded-lg border border-border bg-card px-3 py-2 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"></textarea>
                            </div>
                            <Button @click="confirmManualPayment" :disabled="formManual.processing"
                                class="w-full flex items-center justify-center gap-1.5 rounded-xl">
                                <LoaderCircle v-if="formManual.processing" class="h-4 w-4 animate-spin" />
                                <CheckCircle v-else class="h-4 w-4" />
                                Confirmar Recepción
                            </Button>
                        </div>
                    </div>

                    <div v-if="props.pago.estado_pago === 'completado'"
                        class="p-6 rounded-2xl border border-emerald-500/20 bg-emerald-500/5 text-center space-y-4 animate-in slide-in-from-right-2 fade-in">
                        <div
                            class="h-12 w-12 rounded-full bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto border border-emerald-200 dark:border-emerald-900/50">
                            <CheckCircle class="h-6 w-6" />
                        </div>
                        <div class="space-y-1">
                            <span class="text-sm font-bold text-foreground block">Pago Completado</span>
                            <span class="text-xs text-muted-foreground leading-relaxed block">
                                La transacción fue registrada y confirmada.
                            </span>
                        </div>
                    </div>

                    <div v-if="isAdmin" class="pt-2">
                        <Button variant="destructive" class="w-full rounded-xl"
                            @click="showDeleteDialog = true">
                            Eliminar registro de pago
                        </Button>
                    </div>

                </div>
            </div>
        </div>

        <Dialog :open="showDeleteDialog" @update:open="(val) => !val && (showDeleteDialog = false)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Registro de Pago?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción eliminará el registro del pago y el saldo pendiente del pedido aumentará automáticamente en el sistema.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="showDeleteDialog = false" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deletePaymentAdmin" class="rounded-xl">Eliminar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
