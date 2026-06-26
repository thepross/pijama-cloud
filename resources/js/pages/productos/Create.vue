<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { ArrowLeft, Save, LoaderCircle, Shirt } from 'lucide-vue-next';

const form = useForm({
    codigo_qr: '',
    nombre: '',
    descripcion: '',
    color: '',
    talla: '',
    genero: '',
    marca: '',
    material: '',
    precio_compra: '',
    precio_venta: '',
    stock: '',
    stock_minimo: '',
    categoria: '',
    foto: '',
});

const submit = () => {
    form.post(route('productos.store'));
};
</script>

<template>
    <AppLayout :breadcrumbs="[
        { title: 'Productos', href: '/productos' },
        { title: 'Registrar Producto', href: '/productos/create' }
    ]">
        <Head title="Registrar Producto" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Registrar Prenda Textil
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Agrega una nueva prenda al catálogo de inventario.
                    </p>
                </div>
                <div>
                    <Link :href="route('productos.index')">
                        <Button variant="ghost" class="flex items-center gap-1.5 rounded-xl">
                            <ArrowLeft class="h-4 w-4" />
                            Volver
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Info Gral -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-foreground">Detalles del Producto</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="nombre">Nombre del Producto</Label>
                            <Input id="nombre" type="text" required autofocus v-model="form.nombre" placeholder="ej. Pijama de Seda Premium" class="rounded-xl" />
                            <InputError :message="form.errors.nombre" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="codigo_qr">Código QR / Identificador (Opcional)</Label>
                            <Input id="codigo_qr" type="text" v-model="form.codigo_qr" placeholder="Dejar vacío para auto-generar" class="rounded-xl" />
                            <InputError :message="form.errors.codigo_qr" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="descripcion">Descripción</Label>
                        <textarea
                            id="descripcion"
                            v-model="form.descripcion"
                            placeholder="Especifica los detalles del corte, la suavidad del material..."
                            rows="3"
                            class="flex w-full rounded-xl border border-border bg-card px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 resize-y min-h-[80px]"
                        ></textarea>
                        <InputError :message="form.errors.descripcion" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="grid gap-2">
                            <Label for="categoria">Categoría</Label>
                            <select id="categoria" required v-model="form.categoria" class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                <option value="" disabled>Selecciona...</option>
                                <option value="Niños">Niños</option>
                                <option value="Jóvenes">Jóvenes</option>
                                <option value="Adultos">Adultos</option>
                            </select>
                            <InputError :message="form.errors.categoria" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="genero">Género</Label>
                            <select id="genero" required v-model="form.genero" class="flex h-10 w-full rounded-xl border border-border bg-card px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2">
                                <option value="" disabled>Selecciona...</option>
                                <option value="Unisex">Unisex</option>
                                <option value="Mujer">Mujer</option>
                                <option value="Hombre">Hombre</option>
                            </select>
                            <InputError :message="form.errors.genero" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="talla">Talla</Label>
                            <Input id="talla" type="text" required v-model="form.talla" placeholder="ej. M, L, 10" class="rounded-xl" />
                            <InputError :message="form.errors.talla" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="grid gap-2">
                            <Label for="marca">Marca</Label>
                            <Input id="marca" type="text" v-model="form.marca" placeholder="ej. Pijama Cloud" class="rounded-xl" />
                            <InputError :message="form.errors.marca" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="material">Material</Label>
                            <Input id="material" type="text" v-model="form.material" placeholder="ej. Algodón 100%" class="rounded-xl" />
                            <InputError :message="form.errors.material" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="color">Color</Label>
                            <Input id="color" type="text" v-model="form.color" placeholder="ej. Azul Marino" class="rounded-xl" />
                            <InputError :message="form.errors.color" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="foto">URL de Imagen del Producto</Label>
                        <Input id="foto" type="text" v-model="form.foto" placeholder="https://ejemplo.com/imagen.jpg" class="rounded-xl" />
                        <InputError :message="form.errors.foto" />
                    </div>
                </div>

                <!-- Stock and prices -->
                <div class="p-6 rounded-xl border border-border bg-card shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-foreground">Inventario y Costos</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="precio_compra">Costo Unitario (Compra)</Label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">$</span>
                                <Input id="precio_compra" type="number" step="0.01" required v-model="form.precio_compra" placeholder="0.00" class="pl-7 rounded-xl" />
                            </div>
                            <InputError :message="form.errors.precio_compra" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="precio_venta">Precio al Público (Venta)</Label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">$</span>
                                <Input id="precio_venta" type="number" step="0.01" required v-model="form.precio_venta" placeholder="0.00" class="pl-7 rounded-xl" />
                            </div>
                            <InputError :message="form.errors.precio_venta" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="grid gap-2">
                            <Label for="stock">Cantidad en Inventario (Stock)</Label>
                            <Input id="stock" type="number" required v-model="form.stock" placeholder="ej. 50" class="rounded-xl" />
                            <InputError :message="form.errors.stock" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="stock_minimo">Stock Mínimo (Alerta)</Label>
                            <Input id="stock_minimo" type="number" required v-model="form.stock_minimo" placeholder="ej. 5" class="rounded-xl" />
                            <InputError :message="form.errors.stock_minimo" />
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="route('productos.index')">
                        <Button variant="outline" type="button" class="rounded-xl">Cancelar</Button>
                    </Link>
                    <Button type="submit" class="flex items-center gap-1.5 rounded-xl shadow-sm" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        <Save v-else class="h-4 w-4" />
                        Registrar Prenda
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
