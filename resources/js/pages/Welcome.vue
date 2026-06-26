<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Paintbrush, ArrowRight, Sparkles, Star, ShoppingBag, Shield, Heart, Mail, MapPin, Phone, Check } from 'lucide-vue-next';
import ThemeController from '@/components/ThemeController.vue';

const scrollToSection = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
};

const contactForm = ref({
    nombre: '',
    email: '',
    mensaje: '',
});

const isSubmitted = ref(false);

const submitContactForm = (e: Event) => {
    e.preventDefault();
    if (contactForm.value.nombre && contactForm.value.email && contactForm.value.mensaje) {
        isSubmitted.value = true;
        setTimeout(() => {
            isSubmitted.value = false;
            contactForm.value = { nombre: '', email: '', mensaje: '' };
        }, 3000);
    }
};
</script>

<template>
    <Head title="Pijamas Cloud - Tu Descanso en la Nube" />

    <div class="min-h-screen flex flex-col bg-background text-foreground transition-colors duration-300">
        <!-- Navigation Header -->
        <header class="sticky top-0 z-40 w-full border-b border-border/80 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="container mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
                <!-- Brand Logo -->
                <div class="flex items-center gap-2 cursor-pointer" @click="scrollToSection('inicio')">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground font-bold text-lg shadow-sm">
                        ☁️
                    </div>
                    <span class="text-xl font-bold tracking-tight text-foreground">Pijamas Cloud</span>
                </div>

                <!-- Nav Menu Links -->
                <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-muted-foreground">
                    <button @click="scrollToSection('caracteristicas')" class="hover:text-foreground transition-colors">Características</button>
                    <button @click="scrollToSection('acerca')" class="hover:text-foreground transition-colors">Nosotros</button>
                    <button @click="scrollToSection('contacto')" class="hover:text-foreground transition-colors">Contacto</button>
                </nav>

                <!-- Action buttons -->
                <div class="flex items-center gap-3">
                    <!-- Style & Accessibility Customizer -->
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="ghost" size="icon" class="h-9 w-9 rounded-lg" title="Personalizar tema y accesibilidad">
                                <Paintbrush class="h-5 w-5 text-muted-foreground hover:text-foreground" />
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>Personalizar Aspecto y Accesibilidad</DialogTitle>
                                <DialogDescription>
                                    Adapta el diseño de Pijamas Cloud a tus preferencias visuales y de accesibilidad.
                                </DialogDescription>
                            </DialogHeader>
                            <div class="mt-4 max-h-[70vh] overflow-y-auto px-1">
                                <ThemeController />
                            </div>
                        </DialogContent>
                    </Dialog>

                    <!-- Auth Links -->
                    <template v-if="$page.props.auth && $page.props.auth.user">
                        <Link :href="route('dashboard')">
                            <Button variant="default" size="sm" class="gap-1.5 shadow-sm font-medium">
                                Ir al Dashboard
                                <ArrowRight class="h-4 w-4" />
                            </Button>
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-2">
                            Iniciar sesión
                        </Link>
                        <Link :href="route('register')">
                            <Button variant="default" size="sm" class="shadow-sm font-medium">
                                Registrarse
                            </Button>
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section id="inicio" class="relative py-16 md:py-24 overflow-hidden border-b border-border/50">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 relative z-10">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    <!-- Left Copy Column -->
                    <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3.5 py-1 text-xs font-semibold text-primary animate-pulse">
                            <Sparkles class="h-3.5 w-3.5" />
                            Colección Confort 2026 ya disponible
                        </div>
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground leading-[1.1]">
                            Tu descanso ideal, <br class="hidden sm:inline" />
                            <span class="text-primary bg-gradient-to-r from-primary to-primary/80 bg-clip-text text-transparent">diseñado para soñar.</span>
                        </h1>
                        <p class="text-lg sm:text-xl text-muted-foreground max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                            Descubre la comodidad absoluta con nuestras pijamas confeccionadas con materiales premium. Elige tu aspecto visual ideal con el cambiador de temas integrados para Niños, Jóvenes y Adultos.
                        </p>
                        <div class="flex flex-wrap justify-center lg:justify-start gap-4">
                            <Link :href="$page.props.auth && $page.props.auth.user ? '/productos' : route('login')">
                                <Button size="lg" class="gap-2 font-semibold shadow-md">
                                    Ver Catálogo de Pijamas
                                    <ShoppingBag class="h-5 w-5" />
                                </Button>
                            </Link>
                            <Button size="lg" variant="outline" @click="scrollToSection('caracteristicas')" class="font-semibold">
                                Saber más
                            </Button>
                        </div>
                    </div>

                    <!-- Right Graphic Column -->
                    <div class="lg:col-span-5 flex justify-center lg:justify-end">
                        <div class="relative w-full max-w-[420px] aspect-square rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 border border-primary/20 p-8 shadow-xl flex flex-col justify-between overflow-hidden">
                            <!-- Visual Teaser of Pijamas Cloud Card -->
                            <div class="absolute -top-10 -right-10 w-40 h-40 bg-primary/10 rounded-full blur-2xl"></div>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-widest text-primary">Tejido Premium</span>
                                <div class="flex gap-0.5 text-yellow-500">
                                    <Star class="h-4 w-4 fill-current" />
                                    <Star class="h-4 w-4 fill-current" />
                                    <Star class="h-4 w-4 fill-current" />
                                    <Star class="h-4 w-4 fill-current" />
                                    <Star class="h-4 w-4 fill-current" />
                                </div>
                            </div>

                            <div class="my-auto py-6 space-y-4">
                                <span class="text-6xl">🛌☁️</span>
                                <h3 class="text-2xl font-bold">Pijama Algodón Pima</h3>
                                <p class="text-sm text-muted-foreground">
                                    Extra suave, hipoalergénica y térmica para noches inolvidables.
                                </p>
                            </div>

                            <div class="flex items-center justify-between border-t border-border/80 pt-4">
                                <div>
                                    <span class="text-xs text-muted-foreground block leading-none">Precio especial</span>
                                    <span class="text-2xl font-black text-foreground">Bs. 39.99</span>
                                </div>
                                <span class="rounded bg-primary/10 text-primary text-xs font-bold px-2 py-1 uppercase">
                                    100% Algodón
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="caracteristicas" class="py-16 md:py-24 border-b border-border/50 bg-muted/30">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center max-w-3xl mx-auto space-y-4 mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground">
                        Diseñado para tu comodidad y accesibilidad
                    </h2>
                    <p class="text-lg text-muted-foreground">
                        Nuestra plataforma ofrece características avanzadas de personalización visual y operativa pensadas para todos.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Feature 1: Dynamic Themes -->
                    <div class="rounded-xl border border-border bg-card p-6 shadow-sm hover:shadow-md transition-shadow space-y-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Paintbrush class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold">Tres Temas Dinámicos</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Cambia la apariencia del sitio con estilos adaptados especialmente para <strong>Niños</strong>, <strong>Jóvenes</strong> y <strong>Adultos</strong>, además del modo inteligente <strong>Día/Noche</strong>.
                        </p>
                    </div>

                    <!-- Feature 2: High Contrast -->
                    <div class="rounded-xl border border-border bg-card p-6 shadow-sm hover:shadow-md transition-shadow space-y-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Shield class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold">Accesibilidad Total</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Ajusta el contraste a blanco y negro puro y escala las tipografías para facilitar la lectura a personas con discapacidad visual.
                        </p>
                    </div>

                    <!-- Feature 3: Premium Catalog -->
                    <div class="rounded-xl border border-border bg-card p-6 shadow-sm hover:shadow-md transition-shadow space-y-4">
                        <div class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ShoppingBag class="h-6 w-6" />
                        </div>
                        <h3 class="text-xl font-bold">Gestión y Envíos</h3>
                        <p class="text-muted-foreground leading-relaxed">
                            Consulta tallas, colores, categorías y ofertas activas en un catálogo visual moderno y controla el estado de tus pedidos en tiempo real.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Us Section -->
        <section id="acerca" class="py-16 md:py-24 border-b border-border/50">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div class="space-y-6">
                        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground">
                            Sobre Pijamas Cloud
                        </h2>
                        <p class="text-lg text-muted-foreground leading-relaxed">
                            Nacimos con el objetivo de llevar el confort al siguiente nivel. Diseñamos pijamas a partir de textiles 100% orgánicos, suaves e hipoalergénicos, pensados para maximizar la relajación.
                        </p>
                        <p class="text-lg text-muted-foreground leading-relaxed">
                            Además, creemos firmemente en la tecnología inclusiva. Por eso, nuestro sitio incorpora herramientas avanzadas de diseño adaptable para asegurar que cualquier persona pueda navegar sin barreras visuales.
                        </p>
                        <div class="grid grid-cols-2 gap-6 pt-4">
                            <div class="space-y-1">
                                <span class="text-3xl font-extrabold text-primary">10k+</span>
                                <p class="text-sm text-muted-foreground font-semibold uppercase">Clientes Felices</p>
                            </div>
                            <div class="space-y-1">
                                <span class="text-3xl font-extrabold text-primary">100%</span>
                                <p class="text-sm text-muted-foreground font-semibold uppercase">Material Orgánico</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-center">
                        <div class="relative p-6 border border-border rounded-2xl bg-card shadow-sm max-w-md w-full space-y-4">
                            <div class="flex items-center gap-2 text-primary">
                                <Heart class="h-5 w-5 fill-current" />
                                <span class="font-bold text-sm">Nuestros Valores</span>
                            </div>
                            <ul class="space-y-3 text-sm">
                                <li class="flex items-start gap-2">
                                    <Check class="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                    <span><strong>Calidad:</strong> Costuras reforzadas y tejidos hipoalergénicos.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <Check class="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                    <span><strong>Inclusión:</strong> Accesibilidad web de nivel superior (WAI-ARIA).</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <Check class="h-5 w-5 text-primary shrink-0 mt-0.5" />
                                    <span><strong>Transparencia:</strong> Matriz de acceso clara y bitácora de auditoría.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section id="contacto" class="py-16 md:py-24 bg-muted/20">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6">
                <div class="text-center max-w-2xl mx-auto space-y-4 mb-16">
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground">Contáctanos</h2>
                    <p class="text-lg text-muted-foreground">
                        ¿Tienes dudas sobre pedidos, tallas o compras corporativas? Escríbenos directamente.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                    <!-- Contact details -->
                    <div class="lg:col-span-5 space-y-6">
                        <div class="flex items-start gap-4 p-4 border border-border rounded-xl bg-card shadow-sm">
                            <div class="rounded-lg bg-primary/10 text-primary p-2.5">
                                <Mail class="h-6 w-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-base">Correo electrónico</h4>
                                <p class="text-sm text-muted-foreground mt-1">contacto@pijamascloud.com</p>
                                <p class="text-xs text-muted-foreground">Soporte 24/7 para clientes</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 border border-border rounded-xl bg-card shadow-sm">
                            <div class="rounded-lg bg-primary/10 text-primary p-2.5">
                                <Phone class="h-6 w-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-base">Línea telefónica</h4>
                                <p class="text-sm text-muted-foreground mt-1">+591 777-77777</p>
                                <p class="text-xs text-muted-foreground">Lun - Vie, 8:00 a 18:00</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 p-4 border border-border rounded-xl bg-card shadow-sm">
                            <div class="rounded-lg bg-primary/10 text-primary p-2.5">
                                <MapPin class="h-6 w-6" />
                            </div>
                            <div>
                                <h4 class="font-bold text-base">Ubicación</h4>
                                <p class="text-sm text-muted-foreground mt-1">Av. Pijamas 456, La Paz</p>
                                <p class="text-xs text-muted-foreground">Bolivia</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact form -->
                    <div class="lg:col-span-7">
                        <form @submit="submitContactForm" class="border border-border rounded-2xl bg-card shadow-sm p-6 sm:p-8 space-y-5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="text-sm font-semibold" for="name">Tu Nombre</label>
                                    <input
                                        id="name"
                                        v-model="contactForm.nombre"
                                        type="text"
                                        required
                                        placeholder="Juan Perez"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-semibold" for="email">Tu Correo</label>
                                    <input
                                        id="email"
                                        v-model="contactForm.email"
                                        type="email"
                                        required
                                        placeholder="juan@pijama.com"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-semibold" for="message">Mensaje</label>
                                <textarea
                                    id="message"
                                    v-model="contactForm.mensaje"
                                    required
                                    rows="4"
                                    placeholder="Hola, me gustaría saber sobre las pijamas de niños..."
                                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                ></textarea>
                            </div>
                            
                            <Button type="submit" class="w-full font-semibold shadow-sm">
                                Enviar mensaje
                            </Button>

                            <div v-if="isSubmitted" class="p-3 bg-green-500/10 text-green-600 dark:text-green-400 rounded-md text-sm text-center font-medium animate-pulse">
                                ¡Mensaje enviado exitosamente! Nos pondremos en contacto pronto.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer Section -->
        <footer class="mt-auto border-t border-border/80 bg-card py-8">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left text-sm text-muted-foreground">
                <div>
                    <p>&copy; 2026 Pijamas Cloud. Todos los derechos reservados.</p>
                </div>
                <!-- Dynamic visits counter (Requirement 7) -->
                <div class="flex items-center gap-1.5 font-medium text-foreground bg-muted px-3 py-1 rounded-full text-xs">
                    <span>👁️ Esta página ha sido visitada:</span>
                    <strong class="text-primary">{{ $page.props.visits_count }} veces</strong>
                </div>
            </div>
        </footer>
    </div>
</template>
