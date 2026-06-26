<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Edit, Trash2, ShieldAlert, Check, Shield } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface RoleType {
    id: number;
    nombre: string;
    descripcion: string | null;
    state: string;
    created_at: string;
}

interface PaginatedRoles {
    data: RoleType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    roles: PaginatedRoles;
    filters: { search?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const search = ref(props.filters.search || '');
const roleToDelete = ref<RoleType | null>(null);

// Watch search field to query DB with debounce/throttle
let searchTimeout: any = null;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('roles.index'),
            { search: value },
            { preserveState: true, replace: true }
        );
    }, 300);
});

const confirmDelete = (role: RoleType) => {
    roleToDelete.value = role;
};

const deleteRole = () => {
    if (roleToDelete.value) {
        router.delete(route('roles.destroy', roleToDelete.value.id), {
            onSuccess: () => {
                roleToDelete.value = null;
            },
        });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Roles', href: '/roles' }]">
        <Head title="Gestión de Roles" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Gestión de Roles
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Crea, modifica y gestiona los roles y perfiles de acceso (Matriz de Accesos) de Pijamas Cloud.
                    </p>
                </div>
                <div v-if="$page.props.auth.permissions.includes('roles.crear')">
                    <Link :href="route('roles.create')">
                        <Button class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform">
                            <Plus class="h-4 w-4" />
                            Crear Nuevo Rol
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Flash alerts -->
            <div v-if="props.flash?.success" class="p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <Check class="h-4 w-4" />
                {{ props.flash.success }}
            </div>
            <div v-if="props.flash?.error" class="p-4 rounded-xl border border-destructive/20 bg-destructive/10 text-destructive flex items-center gap-2 text-sm shadow-sm animate-in fade-in slide-in-from-top-2">
                <ShieldAlert class="h-4 w-4" />
                {{ props.flash.error }}
            </div>

            <!-- Filters -->
            <div class="flex items-center max-w-md">
                <div class="relative w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por nombre o descripción..."
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>
            </div>

            <!-- Roles Table -->
            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">ID</th>
                                <th class="p-4">Nombre del Rol</th>
                                <th class="p-4">Descripción</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.roles.data.length === 0">
                                <td colspan="5" class="p-8 text-center text-muted-foreground">
                                    No se encontraron roles activos en el sistema.
                                </td>
                            </tr>
                            <tr v-for="role in props.roles.data" :key="role.id" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4 font-mono font-medium text-muted-foreground">#{{ role.id }}</td>
                                <td class="p-4 font-semibold text-foreground">{{ role.nombre }}</td>
                                <td class="p-4 text-muted-foreground max-w-xs truncate">{{ role.descripcion || 'Sin descripción' }}</td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        {{ role.state }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link v-if="$page.props.auth.permissions.includes('roles.editar')" :href="route('roles.edit', role.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg" title="Editar rol y permisos">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <!-- Disable delete for essential roles -->
                                        <Button
                                            v-if="$page.props.auth.permissions.includes('roles.eliminar') && !['Administrador', 'Cliente'].includes(role.nombre)"
                                            @click="confirmDelete(role)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar rol"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div v-if="props.roles.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.roles.current_page }} de {{ props.roles.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.roles.prev_page_url" :href="props.roles.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.roles.next_page_url" :href="props.roles.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!roleToDelete" @update:open="(val) => !val && (roleToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Eliminar Rol?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>eliminación lógica</strong> del rol <strong>"{{ roleToDelete?.nombre }}"</strong>. El rol se marcará como inactivo y ya no podrá asignarse a nuevos usuarios.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="roleToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteRole" class="rounded-xl">Eliminar Lógicamente</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
