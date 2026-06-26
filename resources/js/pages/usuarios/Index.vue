<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, UserPlus, Edit, Trash2, ShieldAlert, Check, Users } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface UserType {
    id: number;
    username: string;
    nombre: string;
    apellido: string;
    email: string;
    ci: string;
    telefono: string | null;
    state: string;
    role: { id: number; nombre: string } | null;
}

interface RoleType {
    id: number;
    nombre: string;
}

interface PaginatedUsers {
    data: UserType[];
    links: any[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    usuarios: PaginatedUsers;
    roles: RoleType[];
    filters: { search?: string; role_id?: string };
    flash?: { success?: string | null; error?: string | null };
}>();

const search = ref(props.filters.search || '');
const roleId = ref(props.filters.role_id || '');
const userToDelete = ref<UserType | null>(null);

// Trigger search/filter query on change
watch([search, roleId], ([newSearch, newRole]) => {
    router.get(
        route('usuarios.index'),
        { search: newSearch, role_id: newRole },
        { preserveState: true, replace: true }
    );
});

const confirmDelete = (user: UserType) => {
    userToDelete.value = user;
};

const deleteUser = () => {
    if (userToDelete.value) {
        router.delete(route('usuarios.destroy', userToDelete.value.id), {
            onSuccess: () => {
                userToDelete.value = null;
            },
        });
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Usuarios', href: '/usuarios' }]">
        <Head title="Gestión de Usuarios" />

        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground">
                        Gestión de Usuarios
                    </h1>
                    <p class="text-sm text-muted-foreground mt-1">
                        Registra, edita y supervisa las cuentas de clientes, distribuidores, operadores y administradores.
                    </p>
                </div>
                <div v-if="$page.props.auth.permissions.includes('usuarios.crear')">
                    <Link :href="route('usuarios.create')">
                        <Button class="flex items-center gap-1.5 shadow-sm hover:scale-[1.02] transition-transform">
                            <UserPlus class="h-4 w-4" />
                            Nuevo Usuario
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
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full max-w-md">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="search"
                        type="text"
                        placeholder="Buscar por nombre, email, usuario o CI..."
                        class="pl-9 h-10 w-full rounded-xl bg-card border-border shadow-sm focus-visible:ring-primary"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <label for="role-filter" class="text-sm font-medium text-muted-foreground whitespace-nowrap">Filtrar por Rol:</label>
                    <select
                        id="role-filter"
                        v-model="roleId"
                        class="h-10 rounded-xl border border-border bg-card px-3 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                    >
                        <option value="">Todos los Roles</option>
                        <option v-for="role in props.roles" :key="role.id" :value="role.id.toString()">
                            {{ role.nombre }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Users Table -->
            <div class="rounded-xl border border-border bg-card shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border bg-muted/40 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                                <th class="p-4">Usuario / CI</th>
                                <th class="p-4">Nombre Completo</th>
                                <th class="p-4">Correo</th>
                                <th class="p-4">Teléfono</th>
                                <th class="p-4">Rol</th>
                                <th class="p-4">Estado</th>
                                <th class="p-4 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border text-sm">
                            <tr v-if="props.usuarios.data.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground">
                                    No se encontraron cuentas de usuario activas.
                                </td>
                            </tr>
                            <tr v-for="user in props.usuarios.data" :key="user.id" class="hover:bg-accent/30 transition-colors">
                                <td class="p-4">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-foreground">@{{ user.username }}</span>
                                        <span class="text-xs text-muted-foreground">CI: {{ user.ci }}</span>
                                    </div>
                                </td>
                                <td class="p-4 font-semibold text-foreground">
                                    {{ user.nombre }} {{ user.apellido }}
                                </td>
                                <td class="p-4 text-muted-foreground">{{ user.email }}</td>
                                <td class="p-4 text-muted-foreground">{{ user.telefono || '-' }}</td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        {{ user.role?.nombre || 'Sin Rol' }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        {{ user.state }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link v-if="$page.props.auth.permissions.includes('usuarios.editar')" :href="route('usuarios.edit', user.id)">
                                            <Button variant="outline" size="sm" class="h-8 px-2 rounded-lg" title="Editar usuario">
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <!-- Prevent self deletion -->
                                        <Button
                                            v-if="$page.props.auth.permissions.includes('usuarios.eliminar') && $page.props.auth.user.id !== user.id"
                                            @click="confirmDelete(user)"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 px-2 rounded-lg text-destructive hover:bg-destructive/10"
                                            title="Eliminar usuario"
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
                <div v-if="props.usuarios.last_page > 1" class="border-t border-border p-4 bg-muted/20 flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Página {{ props.usuarios.current_page }} de {{ props.usuarios.last_page }}
                    </span>
                    <div class="flex items-center gap-1">
                        <Link v-if="props.usuarios.prev_page_url" :href="props.usuarios.prev_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Anterior</Button>
                        </Link>
                        <Link v-if="props.usuarios.next_page_url" :href="props.usuarios.next_page_url">
                            <Button variant="outline" size="sm" class="h-8 rounded-lg">Siguiente</Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog :open="!!userToDelete" @update:open="(val) => !val && (userToDelete = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-destructive">
                        <ShieldAlert class="h-5 w-5" />
                        ¿Desactivar Cuenta de Usuario?
                    </DialogTitle>
                    <DialogDescription>
                        Esta acción realizará una <strong>desactivación lógica</strong> de la cuenta de <strong>"{{ userToDelete?.nombre }} {{ userToDelete?.apellido }}"</strong> (@{{ userToDelete?.username }}).
                        El usuario ya no podrá iniciar sesión en la plataforma.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="userToDelete = null" class="rounded-xl">Cancelar</Button>
                    <Button variant="destructive" @click="deleteUser" class="rounded-xl">Desactivar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
