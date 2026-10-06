<script setup>
import { ref } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    games: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ search: '', status: 'all' }),
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, active: 0, inactive: 0 }),
    },
});

const page = usePage();
const permissions = page.props.auth?.can || {};

// Search & Filter state
const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');

const applyFilter = () => {
    router.get(
        route('admin.games.index'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    );
};

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingGameId = ref(null);

const form = useForm({
    name: '',
    slug: '',
    image: '',
    has_zone_id: false,
    is_active: true,
});

const openCreateModal = () => {
    isEditing.value = false;
    editingGameId.value = null;
    form.reset();
    form.clearErrors();
    form.is_active = true;
    form.has_zone_id = false;
    isModalOpen.value = true;
};

const openEditModal = (game) => {
    isEditing.value = true;
    editingGameId.value = game.id;
    form.reset();
    form.clearErrors();
    form.name = game.name;
    form.slug = game.slug;
    form.image = game.image || '';
    form.has_zone_id = Boolean(game.has_zone_id);
    form.is_active = Boolean(game.is_active);
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditing.value) {
        form.put(route('admin.games.update', editingGameId.value), {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        form.post(route('admin.games.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

// Toggle status
const toggleStatus = (game) => {
    router.patch(route('admin.games.toggle', game.id), {}, { preserveScroll: true });
};

// Delete confirmation modal
const isDeleteModalOpen = ref(false);
const gameToDelete = ref(null);

const confirmDelete = (game) => {
    gameToDelete.value = game;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!gameToDelete.value) return;
    router.delete(route('admin.games.destroy', gameToDelete.value.id), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            gameToDelete.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout title="Games Management">
        <!-- Top Title & Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                    <span>🎮 Game Categories</span>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono font-bold">
                        {{ stats.total }} Total
                    </span>
                </h1>
                <p class="text-slate-400 text-xs mt-1">
                    Manage supported game titles, images, zone requirement, and product packages.
                </p>
            </div>

            <button
                v-if="permissions.create_game"
                @click="openCreateModal"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 hover:to-yellow-300 text-slate-950 text-xs font-black shadow-lg shadow-amber-500/20 transition transform active:scale-95"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add New Game</span>
            </button>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 shadow-xl">
            <!-- Search Input -->
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    v-model="search"
                    @keyup.enter="applyFilter"
                    type="text"
                    placeholder="Search game title or slug..."
                    class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-4 py-2 pl-10 text-xs focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition placeholder-slate-500"
                />
            </div>

            <!-- Status Tabs & Filter Button -->
            <div class="flex items-center gap-3">
                <div class="flex items-center bg-slate-950 p-1 rounded-xl border border-slate-800">
                    <button
                        @click="status = 'all'; applyFilter()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'all' ? 'bg-amber-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        All ({{ stats.total }})
                    </button>
                    <button
                        @click="status = 'active'; applyFilter()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'active' ? 'bg-emerald-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Active ({{ stats.active }})
                    </button>
                    <button
                        @click="status = 'inactive'; applyFilter()"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-semibold transition',
                            status === 'inactive' ? 'bg-rose-500 text-slate-950' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        Inactive ({{ stats.inactive }})
                    </button>
                </div>

                <button
                    @click="applyFilter"
                    class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition"
                >
                    Apply
                </button>
            </div>
        </div>

        <!-- Games Table -->
        <div class="bg-slate-900/80 backdrop-blur-sm border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-950/70 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-4">Game</th>
                            <th class="py-3.5 px-4">Slug</th>
                            <th class="py-3.5 px-4">Packages</th>
                            <th class="py-3.5 px-4">Zone ID Required</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-200">
                        <tr
                            v-for="game in games.data"
                            :key="game.id"
                            class="hover:bg-slate-800/40 transition group"
                        >
                            <!-- Game Image & Title -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center shrink-0 shadow-md">
                                        <img
                                            v-if="game.image"
                                            :src="game.image"
                                            :alt="game.name"
                                            class="w-full h-full object-cover"
                                        />
                                        <span v-else class="text-xl">🎮</span>
                                    </div>
                                    <div>
                                        <div class="font-bold text-white text-sm">{{ game.name }}</div>
                                        <div class="text-[10px] text-slate-500 font-mono">ID: #{{ game.id }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Slug -->
                            <td class="py-3.5 px-4 font-mono text-slate-400">
                                {{ game.slug }}
                            </td>

                            <!-- Packages Count -->
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 font-mono font-bold">
                                    {{ game.products_count }} packages
                                </span>
                            </td>

                            <!-- Zone ID -->
                            <td class="py-3.5 px-4">
                                <span
                                    v-if="game.has_zone_id"
                                    class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20"
                                >
                                    Required (Server/Zone)
                                </span>
                                <span
                                    v-else
                                    class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800/50 text-slate-400"
                                >
                                    User ID Only
                                </span>
                            </td>

                            <!-- Status Toggle -->
                            <td class="py-3.5 px-4">
                                <button
                                    v-if="permissions.edit_game"
                                    @click="toggleStatus(game)"
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold border transition flex items-center gap-1.5',
                                        game.is_active
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20'
                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/30 hover:bg-rose-500/20'
                                    ]"
                                >
                                    <span :class="['w-1.5 h-1.5 rounded-full', game.is_active ? 'bg-emerald-400' : 'bg-rose-400']"></span>
                                    <span>{{ game.is_active ? 'Active' : 'Inactive' }}</span>
                                </button>
                                <span
                                    v-else
                                    :class="[
                                        'px-2.5 py-1 rounded-full text-[10px] font-bold border inline-block',
                                        game.is_active
                                            ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
                                            : 'bg-rose-500/10 text-rose-400 border-rose-500/30'
                                    ]"
                                >
                                    {{ game.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right space-x-1.5">
                                <button
                                    v-if="permissions.edit_game"
                                    @click="openEditModal(game)"
                                    class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition font-medium text-[11px]"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="permissions.delete_game"
                                    @click="confirmDelete(game)"
                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 transition font-medium text-[11px] border border-rose-500/20"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>

                        <!-- Empty State -->
                        <tr v-if="games.data.length === 0">
                            <td colspan="6" class="py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-3xl mb-2">🎮</span>
                                    <span class="text-sm font-medium">No game categories found</span>
                                    <span class="text-xs text-slate-600 mt-1">Try adjusting your search criteria or add a new game.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div
                v-if="games.links && games.links.length > 3"
                class="p-4 border-t border-slate-800 bg-slate-950/40 flex items-center justify-between text-xs"
            >
                <div class="text-slate-400">
                    Showing {{ games.from || 0 }} to {{ games.to || 0 }} of {{ games.total }} games
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-for="(link, idx) in games.links"
                        :key="idx"
                        @click="link.url && router.get(link.url)"
                        :disabled="!link.url"
                        :class="[
                            'px-2.5 py-1 rounded-lg text-xs font-semibold transition',
                            link.active
                                ? 'bg-amber-500 text-slate-950'
                                : 'bg-slate-900 border border-slate-800 text-slate-300 hover:bg-slate-800',
                            !link.url && 'opacity-30 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- ================= CREATE / EDIT MODAL ================= -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg p-6 shadow-2xl relative space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <span>{{ isEditing ? '✏️ Edit Game' : '🎮 Add New Game' }}</span>
                    </h2>
                    <button @click="isModalOpen = false" class="text-slate-400 hover:text-white">✕</button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Game Name -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Game Name *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Mobile Legends: Bang Bang"
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                            :class="{ 'border-rose-500': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.name }}</p>
                    </div>

                    <!-- Slug (Optional) -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            URL Slug (Optional)
                        </label>
                        <input
                            v-model="form.slug"
                            type="text"
                            placeholder="e.g. mobile-legends (leave blank to auto-generate)"
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition font-mono"
                            :class="{ 'border-rose-500': form.errors.slug }"
                        />
                        <p v-if="form.errors.slug" class="text-[11px] text-rose-400 mt-1 font-medium">{{ form.errors.slug }}</p>
                    </div>

                    <!-- Image URL -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                            Banner / Logo Image URL
                        </label>
                        <input
                            v-model="form.image"
                            type="text"
                            placeholder="https://example.com/game-banner.jpg"
                            class="w-full bg-slate-950 border border-slate-800 text-slate-100 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:border-amber-400 transition"
                        />
                    </div>

                    <!-- Toggles -->
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <!-- Has Zone ID -->
                        <label class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center gap-2.5 cursor-pointer hover:border-slate-700">
                            <input
                                v-model="form.has_zone_id"
                                type="checkbox"
                                class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-amber-400"
                            />
                            <div>
                                <span class="text-xs font-bold text-slate-200 block">Requires Zone ID</span>
                                <span class="text-[10px] text-slate-500 block">E.g. MLBB server ID</span>
                            </div>
                        </label>

                        <!-- Is Active -->
                        <label class="p-3 rounded-xl bg-slate-950 border border-slate-800 flex items-center gap-2.5 cursor-pointer hover:border-slate-700">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded bg-slate-900 border-slate-700 text-amber-500 focus:ring-amber-400"
                            />
                            <div>
                                <span class="text-xs font-bold text-slate-200 block">Active Status</span>
                                <span class="text-[10px] text-slate-500 block">Show on storefront</span>
                            </div>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-400 text-slate-950 text-xs font-bold shadow-lg shadow-amber-500/20"
                        >
                            {{ isEditing ? 'Save Changes' : 'Create Game' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= DELETE CONFIRMATION MODAL ================= -->
        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        >
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md p-6 shadow-2xl relative space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 flex items-center justify-center text-xl mb-2">
                    ⚠️
                </div>
                <h3 class="text-base font-bold text-white">
                    Delete '{{ gameToDelete?.name }}'?
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    This will permanently delete this game title along with <strong class="text-rose-400">all associated product packages</strong>. This action cannot be reversed.
                </p>
                <div class="flex items-center justify-end gap-3 pt-3">
                    <button
                        @click="isDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold"
                    >
                        Cancel
                    </button>
                    <button
                        @click="executeDelete"
                        class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-lg shadow-rose-600/30"
                    >
                        Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
